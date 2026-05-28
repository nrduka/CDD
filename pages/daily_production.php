<!-- Base URL setup -->
<?php
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>

<?php
session_start();

$role = isset($_SESSION['sess_userrole']) ? strtoupper($_SESSION['sess_userrole']) : '';
$assignedDepartments = isset($_SESSION['sess_department']) ? trim((string)$_SESSION['sess_department']) : '';
$allowedRoles = ['ADMIN', 'CASHIER', 'REGISTRATION', 'COUNTER', 'DEPARTMENT'];
if (!in_array($role, $allowedRoles, true) && $assignedDepartments === '') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/db2.php';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sendDailyProductionJsonResponse($payload, $statusCode = 200)
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

function sanitizeProductionText($value)
{
    return trim((string)$value);
}

function getCurrentProductionEditUser()
{
    $userId = isset($_SESSION['sess_user_id']) ? (int)$_SESSION['sess_user_id'] : 0;
    $name = trim((string)(isset($_SESSION['sess_firstname']) ? $_SESSION['sess_firstname'] : '') . ' ' . (string)(isset($_SESSION['sess_lastname']) ? $_SESSION['sess_lastname'] : ''));
    if ($name === '') {
        $name = 'System';
    }

    return [$userId, $name];
}

function ensureDailyProductionEditHistoryTable($mysqli)
{
    $sql = "CREATE TABLE IF NOT EXISTS ice_production_edit_history (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        ice_production_id INT NOT NULL,
        edited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        edited_by_user_id INT NULL,
        edited_by VARCHAR(150) NULL,
        changed_fields TEXT NOT NULL,
        old_values LONGTEXT NOT NULL,
        new_values LONGTEXT NOT NULL,
        PRIMARY KEY (id),
        KEY idx_ice_production_edit_history_production (ice_production_id),
        KEY idx_ice_production_edit_history_edited_at (edited_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return $mysqli->query($sql) === true;
}

function fetchProductionMachineOptions($mysqli)
{
    $options = [];
    $result = $mysqli->query('SELECT id, machine_description, product_type FROM ice_machine ORDER BY machine_description');
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $options[] = [
                'id' => isset($row['id']) ? (int)$row['id'] : 0,
                'machine_description' => isset($row['machine_description']) ? (string)$row['machine_description'] : '',
                'product_type' => isset($row['product_type']) ? (string)$row['product_type'] : ''
            ];
        }
        $result->free();
    }

    return $options;
}

function parseProductionDateTimeInput($value, $label, &$errors)
{
    $value = sanitizeProductionText($value);
    if ($value === '') {
        $errors[] = $label . ' is required.';
        return null;
    }

    $dateTime = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    if (!$dateTime || $dateTime->format('Y-m-d\TH:i') !== $value) {
        $errors[] = $label . ' must be a valid date and time.';
        return null;
    }

    return $dateTime;
}

function fetchProductionEditRecord($mysqli, $productionId)
{
    $sql = "SELECT ip.id,
                   ip.dpr_code,
                   ip.production_code,
                   ip.date_production,
                   ip.time_dropped,
                   ip.datetime_started,
                   ip.datetime_ended,
                   ip.machine,
                   ip.finished_ice_qty,
                   ip.loose_ice_qty,
                   ip.remarks,
                   ip.product_type,
                   im.machine_description,
                   im.product_type AS machine_product_type
            FROM ice_production ip
            LEFT JOIN ice_machine im ON im.id = ip.machine
            WHERE ip.id = ?
            LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $productionId);
    $row = null;
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
    }
    $stmt->close();

    return $row;
}

function formatProductionDateTimeForInput($datetimeValue, $dateValue = '', $timeValue = '')
{
    $label = resolveProductionDateTimeLabel($datetimeValue, $dateValue, $timeValue);
    if ($label === '') {
        return '';
    }
    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $label);
    if ($dateTime instanceof DateTime) {
        return $dateTime->format('Y-m-d\TH:i');
    }

    return str_replace(' ', 'T', substr($label, 0, 16));
}

function formatProductionHistoryValue($field, $row, $machineLookup)
{
    if ($field === 'machine') {
        $machineId = isset($row['machine']) ? (int)$row['machine'] : 0;
        return isset($machineLookup[$machineId]) ? $machineLookup[$machineId]['machine_description'] : ($machineId > 0 ? 'Machine ' . $machineId : '');
    }

    if ($field === 'datetime_started') {
        return resolveProductionDateTimeLabel(isset($row['datetime_started']) ? $row['datetime_started'] : '', isset($row['date_production']) ? $row['date_production'] : '', isset($row['time_dropped']) ? $row['time_dropped'] : '');
    }

    if ($field === 'datetime_ended') {
        return resolveProductionDateTimeLabel(isset($row['datetime_ended']) ? $row['datetime_ended'] : '');
    }

    if ($field === 'finished_ice_qty' || $field === 'loose_ice_qty') {
        return number_format((float)(isset($row[$field]) ? $row[$field] : 0), 2, '.', '');
    }

    return isset($row[$field]) ? (string)$row[$field] : '';
}

function fetchProductionEditHistory($mysqli, $productionId)
{
    $history = [];
    if (!ensureDailyProductionEditHistoryTable($mysqli)) {
        return $history;
    }

    $stmt = $mysqli->prepare("SELECT id, edited_at, edited_by, changed_fields, old_values, new_values
                              FROM ice_production_edit_history
                              WHERE ice_production_id = ?
                              ORDER BY edited_at DESC, id DESC");
    if (!$stmt) {
        return $history;
    }
    $stmt->bind_param('i', $productionId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($result && ($row = $result->fetch_assoc())) {
            $row['changed_fields'] = json_decode((string)$row['changed_fields'], true);
            $row['old_values'] = json_decode((string)$row['old_values'], true);
            $row['new_values'] = json_decode((string)$row['new_values'], true);
            if (!is_array($row['changed_fields'])) {
                $row['changed_fields'] = [];
            }
            if (!is_array($row['old_values'])) {
                $row['old_values'] = [];
            }
            if (!is_array($row['new_values'])) {
                $row['new_values'] = [];
            }
            $history[] = $row;
        }
    }
    $stmt->close();

    return $history;
}

function handleDailyProductionEditRequest($mysqli)
{
    if (!isset($mysqli) || !$mysqli) {
        sendDailyProductionJsonResponse(['success' => false, 'message' => 'Database connection unavailable.'], 500);
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        sendDailyProductionJsonResponse(['success' => false, 'message' => 'Invalid request payload.'], 400);
    }

    $action = sanitizeProductionText(isset($payload['action']) ? $payload['action'] : '');
    $productionId = isset($payload['id']) ? (int)$payload['id'] : 0;
    if ($productionId <= 0) {
        sendDailyProductionJsonResponse(['success' => false, 'message' => 'Production entry is required.'], 422);
    }

    if ($action === 'get_production_history') {
        sendDailyProductionJsonResponse(['success' => true, 'history' => fetchProductionEditHistory($mysqli, $productionId)]);
    }

    if ($action !== 'update_production') {
        sendDailyProductionJsonResponse(['success' => false, 'message' => 'Unsupported action.'], 400);
    }

    if (!ensureDailyProductionEditHistoryTable($mysqli)) {
        sendDailyProductionJsonResponse(['success' => false, 'message' => 'Unable to prepare edit history table.'], 500);
    }

    $errors = [];
    $startedAt = parseProductionDateTimeInput(isset($payload['datetime_started']) ? $payload['datetime_started'] : '', 'Date/time started', $errors);
    $endedAt = parseProductionDateTimeInput(isset($payload['datetime_ended']) ? $payload['datetime_ended'] : '', 'Date/time end', $errors);
    if ($startedAt && $endedAt && $endedAt < $startedAt) {
        $errors[] = 'Date/time end cannot be earlier than date/time started.';
    }

    $machineId = isset($payload['machine']) ? (int)$payload['machine'] : 0;
    if ($machineId <= 0) {
        $errors[] = 'Please select a machine.';
    }

    $finishedQtyInput = sanitizeProductionText(isset($payload['finished_ice_qty']) ? $payload['finished_ice_qty'] : '');
    $looseQtyInput = sanitizeProductionText(isset($payload['loose_ice_qty']) ? $payload['loose_ice_qty'] : '');
    if ($finishedQtyInput === '' || !is_numeric($finishedQtyInput)) {
        $errors[] = 'Finished ice quantity must be a valid number.';
    }
    if ($looseQtyInput === '' || !is_numeric($looseQtyInput)) {
        $errors[] = 'Loose ice quantity must be a valid number.';
    }

    $machineLookup = [];
    foreach (fetchProductionMachineOptions($mysqli) as $machineOption) {
        $machineLookup[(int)$machineOption['id']] = $machineOption;
    }
    if ($machineId > 0 && !isset($machineLookup[$machineId])) {
        $errors[] = 'Selected machine is invalid.';
    }

    if ($errors) {
        sendDailyProductionJsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);
    }

    $oldRow = fetchProductionEditRecord($mysqli, $productionId);
    if (!$oldRow) {
        sendDailyProductionJsonResponse(['success' => false, 'message' => 'Production entry was not found.'], 404);
    }

    $productType = trim((string)(isset($machineLookup[$machineId]['product_type']) ? $machineLookup[$machineId]['product_type'] : ''));
    if ($productType === '') {
        sendDailyProductionJsonResponse(['success' => false, 'message' => 'Product type is not configured for the selected machine.'], 422);
    }

    $newRow = [
        'date_production' => $startedAt->format('Y-m-d'),
        'time_dropped' => $startedAt->format('H:i:s'),
        'datetime_started' => $startedAt->format('Y-m-d H:i:s'),
        'datetime_ended' => $endedAt->format('Y-m-d H:i:s'),
        'machine' => $machineId,
        'finished_ice_qty' => (float)$finishedQtyInput,
        'loose_ice_qty' => (float)$looseQtyInput,
        'remarks' => sanitizeProductionText(isset($payload['remarks']) ? $payload['remarks'] : ''),
        'product_type' => $productType
    ];

    $fieldLabels = [
        'datetime_started' => 'Date/Time Started',
        'datetime_ended' => 'Date/Time End',
        'machine' => 'Machine',
        'product_type' => 'Product Type',
        'finished_ice_qty' => 'Finished Ice Qty',
        'loose_ice_qty' => 'Loose Ice Qty',
        'remarks' => 'Remarks'
    ];

    $changedFields = [];
    $oldValues = [];
    $newValues = [];
    foreach ($fieldLabels as $field => $label) {
        $oldComparable = $field === 'finished_ice_qty' || $field === 'loose_ice_qty'
            ? number_format((float)(isset($oldRow[$field]) ? $oldRow[$field] : 0), 2, '.', '')
            : (string)(isset($oldRow[$field]) ? $oldRow[$field] : '');
        $newComparable = $field === 'finished_ice_qty' || $field === 'loose_ice_qty'
            ? number_format((float)$newRow[$field], 2, '.', '')
            : (string)$newRow[$field];

        if ($oldComparable !== $newComparable) {
            $changedFields[] = $label;
            $oldValues[$label] = formatProductionHistoryValue($field, $oldRow, $machineLookup);
            $newValues[$label] = formatProductionHistoryValue($field, $newRow, $machineLookup);
        }
    }

    if (empty($changedFields)) {
        sendDailyProductionJsonResponse(['success' => false, 'message' => 'No changes were made.'], 422);
    }

    $mysqli->begin_transaction();
    $updateStmt = $mysqli->prepare("UPDATE ice_production
                                    SET date_production = ?,
                                        machine = ?,
                                        time_dropped = ?,
                                        datetime_started = ?,
                                        datetime_ended = ?,
                                        finished_ice_qty = ?,
                                        loose_ice_qty = ?,
                                        remarks = ?,
                                        product_type = ?
                                    WHERE id = ?");
    if (!$updateStmt) {
        $mysqli->rollback();
        sendDailyProductionJsonResponse(['success' => false, 'message' => 'Unable to prepare production update.'], 500);
    }

    $remarks = $newRow['remarks'] !== '' ? $newRow['remarks'] : null;
    $updateStmt->bind_param(
        'sisssddssi',
        $newRow['date_production'],
        $newRow['machine'],
        $newRow['time_dropped'],
        $newRow['datetime_started'],
        $newRow['datetime_ended'],
        $newRow['finished_ice_qty'],
        $newRow['loose_ice_qty'],
        $remarks,
        $newRow['product_type'],
        $productionId
    );
    if (!$updateStmt->execute()) {
        $updateStmt->close();
        $mysqli->rollback();
        sendDailyProductionJsonResponse(['success' => false, 'message' => 'Unable to update production entry.'], 500);
    }
    $updateStmt->close();

    list($editedByUserId, $editedBy) = getCurrentProductionEditUser();
    $changedFieldsJson = json_encode($changedFields);
    $oldValuesJson = json_encode($oldValues);
    $newValuesJson = json_encode($newValues);

    $historyStmt = $mysqli->prepare("INSERT INTO ice_production_edit_history
        (ice_production_id, edited_by_user_id, edited_by, changed_fields, old_values, new_values)
        VALUES (?, ?, ?, ?, ?, ?)");
    if (!$historyStmt) {
        $mysqli->rollback();
        sendDailyProductionJsonResponse(['success' => false, 'message' => 'Unable to prepare edit history log.'], 500);
    }
    $historyStmt->bind_param('iissss', $productionId, $editedByUserId, $editedBy, $changedFieldsJson, $oldValuesJson, $newValuesJson);
    if (!$historyStmt->execute()) {
        $historyStmt->close();
        $mysqli->rollback();
        sendDailyProductionJsonResponse(['success' => false, 'message' => 'Unable to save edit history log.'], 500);
    }
    $historyStmt->close();

    $mysqli->commit();
    sendDailyProductionJsonResponse([
        'success' => true,
        'message' => 'Production entry updated.',
        'history' => fetchProductionEditHistory($mysqli, $productionId)
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleDailyProductionEditRequest($mysqli);
}

$reportDateInput = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
$reportDate = $reportDateInput !== '' ? $reportDateInput : date('Y-m-d');
$reportDateObject = DateTime::createFromFormat('Y-m-d', $reportDate);
if (!$reportDateObject || $reportDateObject->format('Y-m-d') !== $reportDate) {
    $reportDate = date('Y-m-d');
}

$reportTypeInput = isset($_GET['report_type']) ? trim((string)$_GET['report_type']) : '';
$reportType = in_array($reportTypeInput, ['detailed', 'by_product_machine'], true) ? $reportTypeInput : 'detailed';

function resolveProductionDateTimeLabel($datetimeValue, $dateValue = '', $timeValue = '')
{
    $datetimeValue = trim((string)$datetimeValue);
    if ($datetimeValue !== '' && $datetimeValue !== '0000-00-00 00:00:00') {
        $datetimeObject = DateTime::createFromFormat('Y-m-d H:i:s', $datetimeValue);
        if ($datetimeObject instanceof DateTime) {
            return $datetimeObject->format('Y-m-d H:i:s');
        }

        return $datetimeValue;
    }

    $dateValue = trim((string)$dateValue);
    $timeValue = trim((string)$timeValue);
    if ($dateValue !== '' && $timeValue !== '' && $timeValue !== '00:00:00') {
        return $dateValue . ' ' . $timeValue;
    }

    return $dateValue;
}

function fetchDailyProductionRows($mysqli, $reportDate)
{
    $rows = [];
    $sql = "SELECT ip.id,
                   ip.dpr_code,
                   ip.production_code,
                   ip.date_production,
                   ip.time_dropped,
                   ip.datetime_started,
                   ip.datetime_ended,
                   ip.machine,
                   ip.finished_ice_qty,
                   ip.loose_ice_qty,
                   ip.remarks,
                   ip.product_type,
                   im.machine_description,
                   im.product_type AS machine_product_type
            FROM ice_production ip
            LEFT JOIN ice_machine im ON im.id = ip.machine
            WHERE ip.date_production = ?
            ORDER BY COALESCE(ip.datetime_started, ip.date_production), COALESCE(ip.time_dropped, '00:00:00'), im.machine_description, ip.id";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $machineId = isset($row['machine']) ? (string)$row['machine'] : '';
                $machineDescription = trim((string)(isset($row['machine_description']) ? $row['machine_description'] : ''));
                if ($machineDescription === '') {
                    $machineDescription = $machineId !== '' ? 'Machine ' . $machineId : 'Unknown Machine';
                }

                $productType = trim((string)(isset($row['machine_product_type']) ? $row['machine_product_type'] : ''));
                if ($productType === '') {
                    $productType = trim((string)(isset($row['product_type']) ? $row['product_type'] : ''));
                }
                if ($productType === '') {
                    $productType = 'UNASSIGNED';
                }

                $dprCode = trim((string)(isset($row['dpr_code']) ? $row['dpr_code'] : ''));
                if ($dprCode === '') {
                    $dprCode = trim((string)(isset($row['production_code']) ? $row['production_code'] : ''));
                }

                $rows[] = [
                    'id' => isset($row['id']) ? (int)$row['id'] : 0,
                    'date_production' => isset($row['date_production']) ? (string)$row['date_production'] : '',
                    'datetime_started_input' => formatProductionDateTimeForInput(isset($row['datetime_started']) ? $row['datetime_started'] : '', isset($row['date_production']) ? $row['date_production'] : '', isset($row['time_dropped']) ? $row['time_dropped'] : ''),
                    'datetime_ended_input' => formatProductionDateTimeForInput(isset($row['datetime_ended']) ? $row['datetime_ended'] : ''),
                    'datetime_started' => resolveProductionDateTimeLabel(isset($row['datetime_started']) ? $row['datetime_started'] : '', isset($row['date_production']) ? $row['date_production'] : '', isset($row['time_dropped']) ? $row['time_dropped'] : ''),
                    'datetime_ended' => resolveProductionDateTimeLabel(isset($row['datetime_ended']) ? $row['datetime_ended'] : ''),
                    'dpr_code' => $dprCode,
                    'machine_id' => $machineId,
                    'machine_description' => $machineDescription,
                    'product_type' => $productType,
                    'finished_ice_qty' => isset($row['finished_ice_qty']) ? (float)$row['finished_ice_qty'] : 0.0,
                    'loose_ice_qty' => isset($row['loose_ice_qty']) ? (float)$row['loose_ice_qty'] : 0.0,
                    'remarks' => isset($row['remarks']) ? (string)$row['remarks'] : ''
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

function buildProductionMachineGroups($rows)
{
    $groups = [];
    foreach ($rows as $row) {
        $productType = isset($row['product_type']) ? trim((string)$row['product_type']) : '';
        if ($productType === '') {
            $productType = 'UNASSIGNED';
        }

        $machineId = isset($row['machine_id']) ? trim((string)$row['machine_id']) : '';
        $machineDescription = isset($row['machine_description']) ? trim((string)$row['machine_description']) : '';
        $machineKey = $machineId . '|' . $machineDescription;

        if (!isset($groups[$productType])) {
            $groups[$productType] = [
                'subtotal_entries' => 0,
                'subtotal_finished' => 0.0,
                'subtotal_loose' => 0.0,
                'machines' => []
            ];
        }

        if (!isset($groups[$productType]['machines'][$machineKey])) {
            $groups[$productType]['machines'][$machineKey] = [
                'machine_description' => $machineDescription !== '' ? $machineDescription : 'Unknown Machine',
                'entry_count' => 0,
                'finished_ice_qty' => 0.0,
                'loose_ice_qty' => 0.0
            ];
        }

        $finishedQty = isset($row['finished_ice_qty']) ? (float)$row['finished_ice_qty'] : 0.0;
        $looseQty = isset($row['loose_ice_qty']) ? (float)$row['loose_ice_qty'] : 0.0;

        $groups[$productType]['machines'][$machineKey]['entry_count'] += 1;
        $groups[$productType]['machines'][$machineKey]['finished_ice_qty'] += $finishedQty;
        $groups[$productType]['machines'][$machineKey]['loose_ice_qty'] += $looseQty;

        $groups[$productType]['subtotal_entries'] += 1;
        $groups[$productType]['subtotal_finished'] += $finishedQty;
        $groups[$productType]['subtotal_loose'] += $looseQty;
    }

    if (!empty($groups)) {
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($groups as &$groupData) {
            uasort($groupData['machines'], function ($left, $right) {
                return strcasecmp((string)$left['machine_description'], (string)$right['machine_description']);
            });
        }
        unset($groupData);
    }

    return $groups;
}

$generatedAt = date('Y-m-d H:i:s');
$pageError = null;
$registerRows = [];
$groupRows = [];
$machineOptions = [];
$totalFinishedIceQty = 0.0;
$totalLooseIceQty = 0.0;
$entryCount = 0;

if (!isset($mysqli) || !$mysqli) {
    $pageError = 'Database connection unavailable.';
} else {
    $machineOptions = fetchProductionMachineOptions($mysqli);
    $registerRows = fetchDailyProductionRows($mysqli, $reportDate);
    foreach ($registerRows as $row) {
        $totalFinishedIceQty += isset($row['finished_ice_qty']) ? (float)$row['finished_ice_qty'] : 0.0;
        $totalLooseIceQty += isset($row['loose_ice_qty']) ? (float)$row['loose_ice_qty'] : 0.0;
    }
    $entryCount = count($registerRows);

    if ($reportType === 'by_product_machine') {
        $groups = buildProductionMachineGroups($registerRows);
        foreach ($groups as $productType => $groupData) {
            foreach ($groupData['machines'] as $machineData) {
                $groupRows[] = [
                    'product_type' => $productType,
                    'machine_description' => isset($machineData['machine_description']) ? (string)$machineData['machine_description'] : '',
                    'entry_count' => isset($machineData['entry_count']) ? (int)$machineData['entry_count'] : 0,
                    'finished_ice_qty' => isset($machineData['finished_ice_qty']) ? (float)$machineData['finished_ice_qty'] : 0.0,
                    'loose_ice_qty' => isset($machineData['loose_ice_qty']) ? (float)$machineData['loose_ice_qty'] : 0.0
                ];
            }
        }
    }
}

$dayCount = 1;
$averageDailyEntries = $dayCount > 0 ? ($entryCount / $dayCount) : 0;
$reportRangeLabel = 'from ' . date('F d', strtotime($reportDate)) . ' to ' . date('d, Y', strtotime($reportDate));

$reportMeta = [
    'report_date' => $reportDate,
    'report_type' => $reportType,
    'generated_at' => $generatedAt,
    'records' => $reportType === 'by_product_machine' ? count($groupRows) : count($registerRows),
    'total_finished_ice_qty' => $totalFinishedIceQty,
    'total_loose_ice_qty' => $totalLooseIceQty,
    'entry_count' => $entryCount,
    'average_daily_entries' => $averageDailyEntries,
    'report_range' => $reportRangeLabel
];

ob_start();
?>
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
<style>
    .summary-table {
        width: auto;
        min-width: 320px;
    }
    .summary-table td,
    .summary-table th {
        padding: 0.35rem 0.6rem;
    }
    .summary-table .value {
        text-align: right;
        min-width: 160px;
    }
    .production-head {
        background: #e5e7eb;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .daily-production-table .dataTables_wrapper,
    .daily-production-table .dataTables_scroll,
    .daily-production-table .dataTables_scrollHead,
    .daily-production-table .dataTables_scrollBody,
    .daily-production-table .dataTables_scrollHeadInner,
    .daily-production-table .dataTables_scrollHeadInner table,
    .daily-production-table .dataTables_scrollBody table {
        width: 100% !important;
    }
</style>
<?php
$styles = ob_get_clean();

ob_start();
?>
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-start">
        <div class="col-lg-6">
            <h3 class="mb-1">Daily Production Report</h3>
            <p class="text-muted mb-1"><?php echo htmlspecialchars($reportMeta['report_range']); ?></p>
            <p class="text-muted small mb-0">Generated: <?php echo htmlspecialchars($reportMeta['generated_at']); ?></p>
        </div>
        <div class="col-lg-6 mt-3 mt-lg-0">
            <div class="d-flex flex-column gap-3 align-items-lg-end">
                <form class="row g-2 align-items-end justify-content-lg-end" method="get" novalidate>
                    <div class="col-sm-5 col-md-4">
                        <label for="report-date" class="form-label text-muted small mb-1">Production Date</label>
                        <input type="date" id="report-date" name="date" class="form-control" value="<?php echo htmlspecialchars($reportMeta['report_date']); ?>">
                    </div>
                    <div class="col-sm-5 col-md-4">
                        <label for="report-type" class="form-label text-muted small mb-1">Report Type</label>
                        <select id="report-type" name="report_type" class="form-select">
                            <option value="detailed" <?php echo $reportMeta['report_type'] === 'detailed' ? 'selected' : ''; ?>>Detailed</option>
                            <option value="by_product_machine" <?php echo $reportMeta['report_type'] === 'by_product_machine' ? 'selected' : ''; ?>>By Product Type / Machine</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">View</button>
                    </div>
                    <div class="col-auto">
                        <a class="btn btn-outline-secondary" href="print_daily_production.php?date=<?php echo urlencode($reportMeta['report_date']); ?>&amp;report_type=<?php echo urlencode($reportMeta['report_type']); ?>">
                            Export Excel
                        </a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered summary-table mb-0">
                        <tbody>
                            <tr>
                                <th class="text-uppercase small text-muted">Total Finished Ice Qty (Kg)</th>
                                <td class="value"><?php echo number_format($reportMeta['total_finished_ice_qty'], 2); ?></td>
                            </tr>
                            <tr>
                                <th class="text-uppercase small text-muted">Total Loose Ice Qty (Kg)</th>
                                <td class="value"><?php echo number_format($reportMeta['total_loose_ice_qty'], 2); ?></td>
                            </tr>
                            <tr>
                                <th class="text-uppercase small text-muted">Total Production</th>
                                <td class="value"><?php echo number_format($reportMeta['entry_count']); ?></td>
                            </tr>
                            <tr>
                                <th class="text-uppercase small text-muted">Average Daily Production</th>
                                <td class="value"><?php echo number_format($reportMeta['average_daily_entries'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if ($pageError !== null): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($pageError); ?></div>
    <?php else: ?>
        <div class="card custom-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Daily Production Report</h5>
                <span class="text-muted small"><?php echo number_format($reportMeta['records']); ?> record(s)</span>
            </div>
            <div class="card-body">
                <?php if (($reportType === 'by_product_machine' && empty($groupRows)) || ($reportType !== 'by_product_machine' && empty($registerRows))): ?>
                    <p class="text-muted mb-0">No production was recorded for the selected date.</p>
                <?php else: ?>
                    <div class="table-responsive daily-production-table">
                        <table id="daily-production-table" class="table table-striped align-middle mb-0 w-100">
                            <thead>
                                <?php if ($reportType === 'by_product_machine'): ?>
                                    <tr class="production-head text-center align-middle">
                                        <th>No.</th>
                                        <th>Product Type</th>
                                        <th>Machine</th>
                                        <th class="text-end">Production</th>
                                        <th class="text-end">Finished Ice Qty (Kg)</th>
                                        <th class="text-end">Loose Ice Qty (Kg)</th>
                                    </tr>
                                <?php else: ?>
                                    <tr class="production-head text-center align-middle">
                                        <th>No.</th>
                                        <th>Date/Time Started</th>
                                        <th>Date/Time End</th>
                                        <th>DPR Code</th>
                                        <th>Machine</th>
                                        <th>Product Type</th>
                                        <th class="text-end">Finished Ice Qty (Kg)</th>
                                        <th class="text-end">Loose Ice Qty (Kg)</th>
                                        <th>Remarks</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                <?php endif; ?>
                            </thead>
                            <tbody>
                                <?php if ($reportType === 'by_product_machine'): ?>
                                    <?php foreach ($groupRows as $index => $row): ?>
                                        <tr>
                                            <td class="text-center"><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($row['product_type']); ?></td>
                                            <td><?php echo htmlspecialchars($row['machine_description']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['entry_count']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['finished_ice_qty'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($row['loose_ice_qty'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($registerRows as $index => $row): ?>
                                        <tr>
                                            <td class="text-center"><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($row['datetime_started']); ?></td>
                                            <td><?php echo htmlspecialchars($row['datetime_ended']); ?></td>
                                            <td><?php echo htmlspecialchars($row['dpr_code']); ?></td>
                                            <td><?php echo htmlspecialchars($row['machine_description']); ?></td>
                                            <td><?php echo htmlspecialchars($row['product_type']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['finished_ice_qty'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($row['loose_ice_qty'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($row['remarks']); ?></td>
                                            <td class="text-center">
                                                <?php
                                                    $editPayload = [
                                                        'id' => $row['id'],
                                                        'dpr_code' => $row['dpr_code'],
                                                        'datetime_started' => $row['datetime_started_input'],
                                                        'datetime_ended' => $row['datetime_ended_input'],
                                                        'machine' => $row['machine_id'],
                                                        'finished_ice_qty' => number_format($row['finished_ice_qty'], 2, '.', ''),
                                                        'loose_ice_qty' => number_format($row['loose_ice_qty'], 2, '.', ''),
                                                        'remarks' => $row['remarks'],
                                                        'product_type' => $row['product_type']
                                                    ];
                                                ?>
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-primary edit-production-btn"
                                                        data-production="<?php echo h(json_encode($editPayload, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP)); ?>">
                                                    Edit
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="edit-production-modal" tabindex="-1" aria-labelledby="edit-production-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <form id="edit-production-form" novalidate>
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="edit-production-modal-title">Edit Production Entry</h5>
                            <div class="text-muted small" id="edit-production-dpr-label"></div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="edit-production-alert" class="alert d-none" role="alert"></div>
                        <input type="hidden" name="id" id="edit-production-id">

                        <div class="row g-3">
                            <div class="col-12 col-md-3">
                                <label class="form-label fw-semibold" for="edit-production-dpr-code">DPR Code</label>
                                <input type="text" class="form-control" id="edit-production-dpr-code" readonly>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label fw-semibold" for="edit-production-started">Date/Time Started *</label>
                                <input type="datetime-local" class="form-control" name="datetime_started" id="edit-production-started" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label fw-semibold" for="edit-production-ended">Date/Time End *</label>
                                <input type="datetime-local" class="form-control" name="datetime_ended" id="edit-production-ended" required>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label fw-semibold" for="edit-production-machine">Machine *</label>
                                <select class="form-select" name="machine" id="edit-production-machine" required>
                                    <option value="">Select machine</option>
                                    <?php foreach ($machineOptions as $machineOption): ?>
                                        <option value="<?php echo (int)$machineOption['id']; ?>" data-product-type="<?php echo h($machineOption['product_type']); ?>">
                                            <?php echo h($machineOption['machine_description']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label fw-semibold" for="edit-production-finished">Finished Ice Qty *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="finished_ice_qty" id="edit-production-finished" min="0" step="0.01" required>
                                    <span class="input-group-text">KG</span>
                                </div>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label fw-semibold" for="edit-production-loose">Loose Ice Qty *</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="loose_ice_qty" id="edit-production-loose" min="0" step="0.01" required>
                                    <span class="input-group-text">KG</span>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold" for="edit-production-product-type">Product Type</label>
                                <input type="text" class="form-control" id="edit-production-product-type" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="edit-production-remarks">Remarks</label>
                                <textarea class="form-control" name="remarks" id="edit-production-remarks" rows="2"></textarea>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Edit History</h6>
                            <span class="text-muted small" id="edit-production-history-status"></span>
                        </div>
                        <div id="edit-production-history" class="table-responsive">
                            <p class="text-muted mb-0">No edits have been logged for this entry.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="edit-production-save">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

$scriptsBuffer = function () use ($baseUrl) {
    ob_start();
    ?>
    <script src="<?php echo $baseUrl; ?>/assets/libs/jquery/jquery-3.6.1.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/libs/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var table = document.getElementById('daily-production-table');
            if (table && window.jQuery && jQuery.fn.DataTable) {
                jQuery(table).DataTable({
                    autoWidth: false,
                    pageLength: 25,
                    lengthChange: false,
                    ordering: false,
                    paging: true,
                    scrollX: true,
                    scrollY: '60vh',
                    scrollCollapse: true,
                    dom: "<'row align-items-center mb-2'<'col-sm-6'f><'col-sm-6 text-end'>>rt<'row align-items-center mt-2'<'col-sm-6'i><'col-sm-6'p>>",
                    language: {
                        search: 'Filter:',
                        emptyTable: 'No records found.',
                        info: 'Showing _START_ to _END_ of _TOTAL_ records',
                        infoEmpty: 'Showing 0 to 0 of 0 records',
                        paginate: { previous: 'Previous', next: 'Next' }
                    }
                });
            }

            var modalElement = document.getElementById('edit-production-modal');
            var editForm = document.getElementById('edit-production-form');
            var saveButton = document.getElementById('edit-production-save');
            var alertBox = document.getElementById('edit-production-alert');
            var historyBox = document.getElementById('edit-production-history');
            var historyStatus = document.getElementById('edit-production-history-status');
            var modal = modalElement && window.bootstrap ? new bootstrap.Modal(modalElement) : null;

            function escapeHtml(value) {
                return String(value === null || value === undefined ? '' : value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function showEditAlert(message, type) {
                if (!alertBox) { return; }
                alertBox.className = 'alert alert-' + type;
                alertBox.textContent = message;
            }

            function clearEditAlert() {
                if (!alertBox) { return; }
                alertBox.className = 'alert d-none';
                alertBox.textContent = '';
            }

            function updateEditProductType() {
                var machineSelect = document.getElementById('edit-production-machine');
                var productTypeInput = document.getElementById('edit-production-product-type');
                if (!machineSelect || !productTypeInput) { return; }
                var option = machineSelect.options[machineSelect.selectedIndex];
                productTypeInput.value = option && option.dataset ? option.dataset.productType || '' : '';
            }

            function renderHistoryRows(history) {
                if (!historyBox) { return; }
                if (!history || !history.length) {
                    historyBox.innerHTML = '<p class="text-muted mb-0">No edits have been logged for this entry.</p>';
                    return;
                }

                var rows = [];
                history.forEach(function (entry) {
                    var oldValues = entry.old_values || {};
                    var newValues = entry.new_values || {};
                    var fields = entry.changed_fields || Object.keys(newValues);
                    fields.forEach(function (field, index) {
                        rows.push(
                            '<tr>' +
                                '<td>' + (index === 0 ? escapeHtml(entry.edited_at || '') : '') + '</td>' +
                                '<td>' + (index === 0 ? escapeHtml(entry.edited_by || '') : '') + '</td>' +
                                '<td>' + escapeHtml(field) + '</td>' +
                                '<td>' + escapeHtml(oldValues[field] || '') + '</td>' +
                                '<td>' + escapeHtml(newValues[field] || '') + '</td>' +
                            '</tr>'
                        );
                    });
                });

                historyBox.innerHTML =
                    '<table class="table table-sm table-bordered align-middle mb-0">' +
                        '<thead class="table-light">' +
                            '<tr>' +
                                '<th>Edited At</th>' +
                                '<th>Edited By</th>' +
                                '<th>Field</th>' +
                                '<th>Old Value</th>' +
                                '<th>New Value</th>' +
                            '</tr>' +
                        '</thead>' +
                        '<tbody>' + rows.join('') + '</tbody>' +
                    '</table>';
            }

            function loadProductionHistory(id) {
                if (historyStatus) {
                    historyStatus.textContent = 'Loading history...';
                }
                return fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_production_history', id: id })
                })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    if (!payload.success) {
                        throw new Error(payload.message || 'Unable to load history.');
                    }
                    renderHistoryRows(payload.history || []);
                    if (historyStatus) {
                        historyStatus.textContent = (payload.history || []).length + ' edit(s)';
                    }
                })
                .catch(function (error) {
                    if (historyStatus) {
                        historyStatus.textContent = '';
                    }
                    historyBox.innerHTML = '<p class="text-danger mb-0">' + escapeHtml(error.message) + '</p>';
                });
            }

            function openEditModal(record) {
                if (!editForm || !modal) { return; }
                clearEditAlert();
                editForm.reset();
                document.getElementById('edit-production-id').value = record.id || '';
                document.getElementById('edit-production-dpr-code').value = record.dpr_code || '';
                document.getElementById('edit-production-dpr-label').textContent = record.dpr_code ? 'DPR Code: ' + record.dpr_code : '';
                document.getElementById('edit-production-started').value = record.datetime_started || '';
                document.getElementById('edit-production-ended').value = record.datetime_ended || '';
                document.getElementById('edit-production-machine').value = record.machine || '';
                document.getElementById('edit-production-finished').value = record.finished_ice_qty || '';
                document.getElementById('edit-production-loose').value = record.loose_ice_qty || '';
                document.getElementById('edit-production-remarks').value = record.remarks || '';
                updateEditProductType();
                renderHistoryRows([]);
                modal.show();
                loadProductionHistory(record.id);
            }

            document.addEventListener('click', function (event) {
                var button = event.target.closest('.edit-production-btn');
                if (!button) { return; }
                try {
                    openEditModal(JSON.parse(button.getAttribute('data-production') || '{}'));
                } catch (error) {
                    showEditAlert('Unable to read production entry details.', 'danger');
                }
            });

            var machineSelect = document.getElementById('edit-production-machine');
            if (machineSelect) {
                machineSelect.addEventListener('change', updateEditProductType);
            }

            if (editForm) {
                editForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                    clearEditAlert();
                    if (saveButton) {
                        saveButton.disabled = true;
                        saveButton.textContent = 'Saving...';
                    }

                    var payload = {
                        action: 'update_production',
                        id: document.getElementById('edit-production-id').value,
                        datetime_started: document.getElementById('edit-production-started').value,
                        datetime_ended: document.getElementById('edit-production-ended').value,
                        machine: document.getElementById('edit-production-machine').value,
                        finished_ice_qty: document.getElementById('edit-production-finished').value,
                        loose_ice_qty: document.getElementById('edit-production-loose').value,
                        remarks: document.getElementById('edit-production-remarks').value
                    };

                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            if (!response.ok || !data.success) {
                                throw new Error(data.message || 'Unable to save production entry.');
                            }
                            return data;
                        });
                    })
                    .then(function (data) {
                        showEditAlert(data.message || 'Production entry updated.', 'success');
                        renderHistoryRows(data.history || []);
                        if (historyStatus) {
                            historyStatus.textContent = (data.history || []).length + ' edit(s)';
                        }
                        window.setTimeout(function () {
                            window.location.reload();
                        }, 700);
                    })
                    .catch(function (error) {
                        showEditAlert(error.message, 'danger');
                    })
                    .finally(function () {
                        if (saveButton) {
                            saveButton.disabled = false;
                            saveButton.textContent = 'Save Changes';
                        }
                    });
                });
            }
        });
    </script>
    <?php
    return ob_get_clean();
};
$scripts = $scriptsBuffer();

include 'layouts/base.php';
