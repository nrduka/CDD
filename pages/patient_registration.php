<!-- Base URL setup -->
<?php
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>

<?php
session_start();

$uploadSummary = null;
$uploadErrors = [];
$uploadWarnings = [];
$previewRows = [];
$selectedFilename = '';
$triggerSuccessAlert = false;
$successAlertMessage = '';

/**
 * Convert the birthday string from the export into Y-m-d.
 */
function parsePatientBirthday($raw)
{
    $normalized = trim($raw);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : (isset($_POST['form_action']) ? $_POST['form_action'] : 'preview');

    if ($action === 'preview') {
        unset($_SESSION['patient_import_preview']);

        if (!isset($_FILES['patient_file']) || !is_uploaded_file($_FILES['patient_file']['tmp_name'])) {
            $uploadErrors[] = 'Please choose an exported HTML file before generating a preview.';
        } elseif ($_FILES['patient_file']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors[] = 'The upload failed. Please try again with a fresh export.';
        } else {
            $uploadedPath = $_FILES['patient_file']['tmp_name'];
            $selectedFilename = isset($_FILES['patient_file']['name']) ? $_FILES['patient_file']['name'] : 'patients.html';

            $extension = strtolower(pathinfo($selectedFilename, PATHINFO_EXTENSION));
            if (!in_array($extension, ['html', 'htm'], true)) {
                $uploadErrors[] = 'Only HTML exports from Dashlabs are supported.';
            }
        }

        if (!$uploadErrors) {
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $loadResult = $dom->loadHTMLFile($uploadedPath);
            libxml_clear_errors();

            if (!$loadResult) {
                $uploadErrors[] = 'Unable to read the uploaded file. Please ensure it is a Dashlabs patient export.';
            } else {
                $xpath = new DOMXPath($dom);
                $rowNodes = $xpath->query('//tbody//tr');

                $parsedRows = [];
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
                        'firstname'       => isset($cells[0]) ? $cells[0] : '',
                        'lastname'        => isset($cells[1]) ? $cells[1] : '',
                        'retrieval_code'  => isset($cells[2]) ? $cells[2] : '',
                        'birthday_raw'    => isset($cells[3]) ? $cells[3] : '',
                        'sex'             => isset($cells[4]) ? $cells[4] : '',
                        'email'           => isset($cells[5]) ? $cells[5] : '',
                        'branches'        => isset($cells[6]) ? $cells[6] : '',
                    ];

                    if ($row['firstname'] === '' && $row['lastname'] === '' && $row['retrieval_code'] === '') {
                        continue;
                    }

                    $parsedDob = parsePatientBirthday($row['birthday_raw']);
                    $row['dob'] = $parsedDob ? $parsedDob : '1970-01-01';
                    if ($row['dob'] === '1970-01-01') {
                        $uploadWarnings[] = "Unable to parse birthday for {$row['firstname']} {$row['lastname']}. Using 1970-01-01.";
                    }

                    $parsedRows[] = $row;
                }

                if (!$parsedRows) {
                    $uploadErrors[] = 'No patient rows were detected in the uploaded file.';
                } else {
                    $previewRows = $parsedRows;
                    $_SESSION['patient_import_preview'] = [
                        'rows' => $parsedRows,
                        'warnings' => $uploadWarnings,
                        'filename' => $selectedFilename,
                    ];
                }
            }
        }
    } elseif ($action === 'import') {
        $payload = isset($_SESSION['patient_import_preview']) && is_array($_SESSION['patient_import_preview']) ? $_SESSION['patient_import_preview'] : null;
        if (!$payload || !isset($payload['rows']) || !is_array($payload['rows']) || !count($payload['rows'])) {
            $uploadErrors[] = 'No queued patient data found. Please upload and preview a file first.';
        } else {
            $parsedRows = $payload['rows'];
            if (isset($payload['filename'])) {
                $selectedFilename = $payload['filename'];
            }
            if (isset($payload['warnings']) && is_array($payload['warnings'])) {
                $uploadWarnings = array_merge($uploadWarnings, $payload['warnings']);
            }

            require_once 'database-config.php';

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
                    $uploadErrors[] = "Missing required column `$requiredColumn` in patient_info table.";
                }
            }

            if (!$uploadErrors) {
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
                    $uploadErrors[] = 'Failed to prepare the insert statement: ' . $conn->error;
                    $conn->close();
                } else {
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
                            $uploadWarnings[] = "Unable to store {$row['firstname']} {$row['lastname']} ({$row['retrieval_code']}): {$stmt->error}";
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

                    $uploadSummary = [
                        'processed' => count($parsedRows),
                        'inserted' => $inserted,
                        'updated' => $updated,
                        'skipped' => $skipped,
                    ];

                    if (!$uploadErrors) {
                        $successAlertMessage = sprintf(
                            'Patient import completed for %s. Inserted %d, updated %d, skipped %d.',
                            $selectedFilename !== '' ? $selectedFilename : 'the selected file',
                            $inserted,
                            $updated,
                            $skipped
                        );
                        $triggerSuccessAlert = true;
                    }

                    $previewRows = [];
                    unset($_SESSION['patient_import_preview']);
                }
            }

            if ($uploadErrors) {
                if (!count($previewRows)) {
                    $previewRows = $parsedRows;
                }
            }
        }
    } elseif ($action === 'reset') {
        unset($_SESSION['patient_import_preview']);
        $previewRows = [];
        $selectedFilename = '';
        $uploadWarnings = [];
    }
}

if (isset($_SESSION['patient_import_preview']) && is_array($_SESSION['patient_import_preview'])) {
    $payload = $_SESSION['patient_import_preview'];
    if (!count($previewRows) && isset($payload['rows']) && is_array($payload['rows'])) {
        $previewRows = $payload['rows'];
    }
    if ($selectedFilename === '' && isset($payload['filename'])) {
        $selectedFilename = $payload['filename'];
    }
    if (!count($uploadWarnings) && isset($payload['warnings']) && is_array($payload['warnings'])) {
        $uploadWarnings = $payload['warnings'];
    }
}
?>

<?php ob_start(); ?>
    <style>
        .card-upload-helper {
            border: 1px dashed #d0d4dc;
            background: #f8f9fc;
            padding: 1.5rem;
            border-radius: .75rem;
            text-align: center;
        }
        .card-upload-helper input[type="file"] {
            display: none;
        }
        .card-upload-helper .file-label {
            cursor: pointer;
        }
        .upload-preview-table th,
        .upload-preview-table td {
            white-space: nowrap;
            vertical-align: middle;
        }
    </style>
<?php $styles = ob_get_clean(); ?>

<?php ob_start(); ?>
    <div class="container-fluid">
        <div class="page-header-breadcrumb d-md-flex d-block align-items-center justify-content-between my-4">
            <div>
                <h6 class="fw-semibold mb-0">Patient Registration Import</h6>
                <span class="text-muted">Upload the Dashlabs patient export to populate the registry.</span>
            </div>
        </div>

        <?php if ($uploadErrors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($uploadErrors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($uploadSummary && !$uploadErrors): ?>
            <div class="alert alert-success">
                Processed <?php echo (int) $uploadSummary['processed']; ?> row(s) -
                inserted <?php echo (int) $uploadSummary['inserted']; ?>,
                updated <?php echo (int) $uploadSummary['updated']; ?>,
                skipped <?php echo (int) $uploadSummary['skipped']; ?>.
            </div>
        <?php endif; ?>

        <?php if ($uploadWarnings && !$uploadErrors): ?>
            <div class="alert alert-warning">
                <ul class="mb-0">
                    <?php foreach ($uploadWarnings as $warning): ?>
                        <li><?php echo htmlspecialchars($warning, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card custom-card">
            <div class="card-body">
                <form method="post" enctype="multipart/form-data" data-patient-upload-form>
                    <input type="hidden" name="form_action" value="preview" data-action-field>
                    <div class="card-upload-helper mb-4">
                        <label class="file-label">
                            <input type="file" name="patient_file" accept=".html,.htm">
                            <div class="mb-2">
                                <i class="bx bx-cloud-upload fs-1 text-primary"></i>
                            </div>
                            <h6 class="fw-semibold mb-1">Drop the Dashlabs export here</h6>
                            <p class="text-muted mb-2">Accepted formats: .html, .htm</p>
                            <button type="button" class="btn btn-outline-primary btn-sm">Choose File</button>
                        </label>
                        <p class="mt-3 mb-0 text-muted">
                            Selected file:
                            <span class="fw-semibold" data-selected-file>
                                <?php echo $selectedFilename !== '' ? htmlspecialchars($selectedFilename, ENT_QUOTES, 'UTF-8') : 'None'; ?>
                            </span>
                        </p>
                        <small class="text-muted d-block mt-2" data-preview-status style="display: none;">
                            Generating preview...
                        </small>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" name="action" value="import" class="btn btn-success" <?php echo count($previewRows) ? '' : 'disabled'; ?>>
                            Import Patients
                        </button>
                        <?php if (count($previewRows)): ?>
                            <button type="submit" name="action" value="reset" class="btn btn-outline-secondary">
                                Clear Preview
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($previewRows && !$uploadErrors): ?>
            <div class="card custom-card mt-4">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <div class="card-title mb-0">
                        Preview &mdash; <?php echo count($previewRows); ?> row<?php echo count($previewRows) === 1 ? '' : 's'; ?>
                    </div>
                    <span class="text-muted small ms-auto">
                        File: <?php echo $selectedFilename !== '' ? htmlspecialchars($selectedFilename, ENT_QUOTES, 'UTF-8') : 'None'; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered upload-preview-table">
                            <thead>
                                <tr>
                                    <th>First name</th>
                                    <th>Last name</th>
                                    <th>Retrieval code</th>
                                    <th>Birthday</th>
                                    <th>Sex</th>
                                    <th>Email</th>
                                    <th>Branches</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($previewRows as $preview): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($preview['firstname'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($preview['lastname'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($preview['retrieval_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($preview['dob'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($preview['sex'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($preview['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($preview['branches'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php $content = ob_get_clean(); ?>

<?php ob_start(); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var form = document.querySelector('[data-patient-upload-form]');
            var actionField = document.querySelector('[data-action-field]');
            var fileInput = document.querySelector('.card-upload-helper input[type="file"]');
            var triggerButton = document.querySelector('.card-upload-helper button');
            var fileNameTarget = document.querySelector('[data-selected-file]');
            var previewStatus = document.querySelector('[data-preview-status]');
            var importButton = document.querySelector('button[name="action"][value="import"]');

            if (triggerButton && fileInput) {
                triggerButton.addEventListener('click', function () {
                    fileInput.click();
                });
            }

            if (fileInput && fileNameTarget) {
                fileInput.addEventListener('change', function () {
                    var selectedName = 'None';
                    if (fileInput.files && fileInput.files.length) {
                        selectedName = fileInput.files[0].name;
                        if (previewStatus) {
                            previewStatus.style.display = 'block';
                            previewStatus.textContent = 'Generating preview...';
                        }
                        if (importButton) {
                            importButton.disabled = true;
                        }
                        if (actionField) {
                            actionField.value = 'preview';
                        }
                        if (form) {
                            window.requestAnimationFrame(function () {
                                form.submit();
                            });
                        }
                    } else if (previewStatus) {
                        previewStatus.style.display = 'none';
                    }
                    fileNameTarget.textContent = selectedName;
                });
            }
        });
    </script>
<?php if ($triggerSuccessAlert && $successAlertMessage !== ''): ?>
    <script>
        window.setTimeout(function () {
            alert(<?php echo json_encode($successAlertMessage); ?>);
        }, 150);
    </script>
<?php endif; ?>
<?php $scripts = ob_get_clean(); ?>

<?php include 'layouts/base.php'; ?>
