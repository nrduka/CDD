<?php
session_start();
header('Content-Type: application/json');

/**
 * Utility to send a JSON response and exit.
 */
function respond($success, $message, array $extra = [])
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $extra));
    exit;
}

/**
 * Convert a birthday string from the Dashlabs export into Y-m-d.
 */
function parseBirthday($raw)
{
    $normalized = trim((string)$raw);
    if ($normalized === '') {
        return null;
    }

    $normalized = preg_replace('/\s+/', ' ', strtoupper($normalized));
    $normalized = ucwords(strtolower($normalized));

    $formats = ['M j, Y', 'M d, Y', 'F j, Y', 'F d, Y', 'Y-m-d'];
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $normalized);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d');
        }
    }

    return null;
}

/**
 * Reduce whitespace noise introduced by the saved HTML page.
 */
function extractCellText($node)
{
    if (!$node) {
        return '';
    }

    $raw = '';
    if (isset($node->textContent)) {
        $raw = $node->textContent;
    }
    $clean = trim(preg_replace('/\s+/', ' ', $raw));
    return $clean;
}

$action = isset($_POST['action']) ? $_POST['action'] : 'preview';
$sessionKey = 'tag_patient_import_preview';

if ($action === 'preview') {
    $errors = [];
    $warnings = [];
    $previewRows = [];
    $selectedFilename = '';

    if (!isset($_FILES['patient_file']) || !is_uploaded_file($_FILES['patient_file']['tmp_name'])) {
        respond(false, 'Please choose an exported HTML file before generating a preview.');
    }

    if ($_FILES['patient_file']['error'] !== UPLOAD_ERR_OK) {
        respond(false, 'The upload failed. Please try again with a fresh export.');
    }

    $uploadedPath = $_FILES['patient_file']['tmp_name'];
    $selectedFilename = isset($_FILES['patient_file']['name']) ? $_FILES['patient_file']['name'] : 'patients.html';
    $extension = strtolower(pathinfo($selectedFilename, PATHINFO_EXTENSION));
    if (!in_array($extension, ['html', 'htm'], true)) {
        respond(false, 'Only HTML exports from Dashlabs are supported.');
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $loadResult = $dom->loadHTMLFile($uploadedPath);
    libxml_clear_errors();

    if (!$loadResult) {
        respond(false, 'Unable to read the uploaded file. Please ensure it is a Dashlabs patient export.');
    }

    $xpath = new DOMXPath($dom);
    $rowNodes = $xpath->query('//tbody//tr');

    /** @var DOMElement $rowNode */
    foreach ($rowNodes as $rowNode) {
        $cellNodes = $rowNode->getElementsByTagName('td');
        $cells = [];
        foreach ($cellNodes as $cellNode) {
            $class = $cellNode->getAttribute('class');
            if (strpos($class, 'MuiTableCell-paddingCheckbox') !== false) {
                continue; // Skip the action column
            }
            $cells[] = extractCellText($cellNode);
        }

        if (count($cells) < 5) {
            continue;
        }

        $row = [
            'firstname'      => isset($cells[0]) ? $cells[0] : '',
            'lastname'       => isset($cells[1]) ? $cells[1] : '',
            'retrieval_code' => isset($cells[2]) ? $cells[2] : '',
            'birthday_raw'   => isset($cells[3]) ? $cells[3] : '',
            'sex'            => isset($cells[4]) ? $cells[4] : '',
            'email'          => isset($cells[5]) ? $cells[5] : '',
            'branches'       => isset($cells[6]) ? $cells[6] : '',
        ];

        if ($row['firstname'] === '' && $row['lastname'] === '' && $row['retrieval_code'] === '') {
            continue;
        }

        $parsedDob = parseBirthday($row['birthday_raw']);
        $row['dob'] = $parsedDob ? $parsedDob : '1970-01-01';
        if ($row['dob'] === '1970-01-01') {
            $warnings[] = "Unable to parse birthday for {$row['firstname']} {$row['lastname']}. Using 1970-01-01.";
        }

        $previewRows[] = $row;
    }

    if (!$previewRows) {
        respond(false, 'No patient rows were detected in the uploaded file.');
    }

    $_SESSION[$sessionKey] = [
        'rows' => $previewRows,
        'warnings' => $warnings,
        'filename' => $selectedFilename,
    ];

    respond(true, 'Preview generated.', [
        'rows' => $previewRows,
        'warnings' => $warnings,
        'filename' => $selectedFilename,
        'count' => count($previewRows),
    ]);
}

if ($action === 'import') {
    $payload = isset($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey]) ? $_SESSION[$sessionKey] : null;
    if (!$payload || !isset($payload['rows']) || !is_array($payload['rows']) || !count($payload['rows'])) {
        respond(false, 'No queued patient data found. Please upload and preview a file first.');
    }

    $parsedRows = $payload['rows'];
    $selectedFilename = isset($payload['filename']) ? $payload['filename'] : '';
    $warnings = isset($payload['warnings']) && is_array($payload['warnings']) ? $payload['warnings'] : [];

    require_once __DIR__ . '/database-config.php';

    $columnLookup = [];
    $columnResult = $conn->query('SHOW COLUMNS FROM patient_info');
    if ($columnResult instanceof mysqli_result) {
        while ($info = $columnResult->fetch_assoc()) {
            $columnLookup[$info['Field']] = true;
        }
        $columnResult->free();
    }

    $requiredColumns = ['retrieval_code', 'firstname', 'dob', 'sex'];
    foreach ($requiredColumns as $requiredColumn) {
        if (!isset($columnLookup[$requiredColumn])) {
            respond(false, "Missing required column `$requiredColumn` in patient_info table.");
        }
    }

    $columnDefaults = [
        'retrieval_code' => '',
        'firstname' => '',
        'lastname' => '',
        'middlename' => '',
        'dob' => '1970-01-01',
        'sex' => '',
        'email' => '',
        'branches' => '',
        'phone_no' => '',
        'address' => '',
        'region' => '',
        'province' => '',
        'city' => '',
        'barangay' => '',
        'occupation' => '',
    ];

    $insertColumns = [];
    foreach ($columnDefaults as $column => $defaultValue) {
        if (isset($columnLookup[$column])) {
            $insertColumns[] = $column;
        }
    }

    $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
    $updateTargets = [];
    foreach ($insertColumns as $column) {
        if ($column === 'retrieval_code') {
            continue;
        }
        $updateTargets[] = "$column = VALUES($column)";
    }
    $updateClause = $updateTargets ? ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateTargets) : '';

    $sql = sprintf(
        'INSERT INTO patient_info (%s) VALUES (%s)%s',
        implode(', ', $insertColumns),
        $placeholders,
        $updateClause
    );

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        respond(false, 'Failed to prepare the insert statement: ' . $conn->error);
    }

    $types = str_repeat('s', count($insertColumns));
    $rowValues = array_fill(0, count($insertColumns), '');
    $bindParams = [$types];
    foreach ($rowValues as $index => $value) {
        $bindParams[] = &$rowValues[$index];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);

    $inserted = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($parsedRows as $row) {
        if (trim($row['retrieval_code']) === '') {
            $skipped++;
            continue;
        }

        foreach ($insertColumns as $position => $column) {
            switch ($column) {
                case 'retrieval_code':
                    $rowValues[$position] = $row['retrieval_code'];
                    break;
                case 'firstname':
                    $rowValues[$position] = $row['firstname'];
                    break;
                case 'lastname':
                    $rowValues[$position] = $row['lastname'];
                    break;
                case 'middlename':
                    $rowValues[$position] = '';
                    break;
                case 'dob':
                    $rowValues[$position] = $row['dob'];
                    break;
                case 'sex':
                    $rowValues[$position] = $row['sex'] ? $row['sex'] : 'UNSPECIFIED';
                    break;
                case 'email':
                    $rowValues[$position] = $row['email'];
                    break;
                case 'branches':
                    $rowValues[$position] = $row['branches'];
                    break;
                default:
                    $rowValues[$position] = isset($columnDefaults[$column]) ? $columnDefaults[$column] : '';
                    break;
            }
        }

        if (!$stmt->execute()) {
            $warnings[] = "Unable to store {$row['firstname']} {$row['lastname']} ({$row['retrieval_code']}): {$stmt->error}";
            $skipped++;
            continue;
        }

        if ($stmt->affected_rows === 1) {
            $inserted++;
        } elseif ($stmt->affected_rows === 2) {
            $updated++;
        } else {
            $skipped++;
        }
    }

    $stmt->close();
    $conn->close();

    unset($_SESSION[$sessionKey]);

    respond(true, 'Patient import completed.', [
        'summary' => [
            'processed' => count($parsedRows),
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'filename' => $selectedFilename,
        ],
        'warnings' => $warnings,
    ]);
}

respond(false, 'Unsupported action.');
