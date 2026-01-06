<?php
session_start();
header('Content-Type: application/json');

$role = isset($_SESSION['sess_userrole']) ? strtoupper($_SESSION['sess_userrole']) : '';
$assignedDepartmentNames = isset($_SESSION['sess_department']) ? trim((string)$_SESSION['sess_department']) : '';
$hasDepartmentAssignments = $assignedDepartmentNames !== '';
if (!in_array($role, ['REGISTRATION', 'COUNTER', 'ADMIN', 'DEPARTMENT', 'CASHIER'], true) && !$hasDepartmentAssignments) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied.'
    ]);
    exit;
}

require_once __DIR__ . '/db2.php';
require_once __DIR__ . '/queue_table_check.php';

if (!isset($mysqli) || !$mysqli) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection unavailable.'
    ]);
    exit;
}

if (($tableStatus = ensureQueueTablesExist($mysqli)) !== null) {
    echo json_encode([
        'success' => false,
        'message' => $tableStatus
    ]);
    exit;
}

$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$rawIds = isset($_POST['ids']) ? $_POST['ids'] : [];
if (!is_array($rawIds)) {
    $rawIds = [$rawIds];
}
$ids = [];
foreach ($rawIds as $rawId) {
    $castId = (int)$rawId;
    if ($castId > 0) {
        $ids[] = $castId;
    }
}
$departmentId = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
$departmentQueueId = isset($_POST['department_queue_id']) ? (int)$_POST['department_queue_id'] : 0;
$routeDepartmentId = isset($_POST['route_department_id']) ? (int)$_POST['route_department_id'] : 0;
$routeDepartmentIds = [];
if (isset($_POST['route_department_ids'])) {
    $rawRouteDepartmentIds = $_POST['route_department_ids'];
    if (!is_array($rawRouteDepartmentIds)) {
        $rawRouteDepartmentIds = [$rawRouteDepartmentIds];
    }
    foreach ($rawRouteDepartmentIds as $rawDeptId) {
        $castDeptId = (int)$rawDeptId;
        if ($castDeptId > 0) {
            $routeDepartmentIds[] = $castDeptId;
        }
    }
}
if ($routeDepartmentId > 0) {
    $routeDepartmentIds[] = $routeDepartmentId;
}
$routeDepartmentIds = array_values(array_unique($routeDepartmentIds));
$routeRegistrationId = isset($_POST['route_registration_id']) ? (int)$_POST['route_registration_id'] : 0;
if ($routeRegistrationId <= 0) {
    $routeRegistrationId = isset($_POST['route_counter_id']) ? (int)$_POST['route_counter_id'] : 0;
}
$paymentQueueId = isset($_POST['payment_queue_id']) ? (int)$_POST['payment_queue_id'] : 0;
$paymentDepartmentQueueId = isset($_POST['payment_department_queue_id']) ? (int)$_POST['payment_department_queue_id'] : 0;
$amountReceived = isset($_POST['amount_received']) ? (float)$_POST['amount_received'] : 0;
$receiptNo = isset($_POST['receipt_no']) ? trim((string)$_POST['receipt_no']) : '';
$callId = isset($_POST['call_id']) ? (int)$_POST['call_id'] : 0;
$rawServices = [];
if (isset($_POST['services'])) {
    $rawServices = $_POST['services'];
} elseif (isset($_POST['service_ids'])) {
    $rawServices = $_POST['service_ids'];
}
if (!is_array($rawServices)) {
    $rawServices = $rawServices !== '' ? [$rawServices] : [];
}
$serviceIds = [];
foreach ($rawServices as $rawServiceId) {
    $castServiceId = (int)$rawServiceId;
    if ($castServiceId > 0) {
        $serviceIds[] = $castServiceId;
    }
}
$serviceIds = array_values(array_unique($serviceIds));

if ($action === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request payload.'
    ]);
    exit;
}

$actionsRequiringIds = ['call', 'complete', 'return', 'announce', 'route_department', 'counter_routed_call', 'counter_routed_complete'];
if (in_array($action, $actionsRequiringIds, true) && empty($ids)) {
    echo json_encode([
        'success' => false,
        'message' => 'No records were selected for the requested action.'
    ]);
    exit;
}

$userId = isset($_SESSION['sess_user_id']) ? (int)$_SESSION['sess_user_id'] : 0;

$fullDepartmentRoles = ['ADMIN', 'REGISTRATION', 'COUNTER'];
$hasFullDepartmentAccess = in_array($role, $fullDepartmentRoles, true);
$departmentAccessIds = $hasFullDepartmentAccess ? null : resolveUserDepartmentIds($mysqli, $userId, $role);

$needsRegistration = in_array($action, ['call', 'complete', 'return', 'announce', 'route_department', 'tag_patient'], true);
$registrationInfo = null;
$registrationNumber = null;
if ($needsRegistration) {
    $registrationInfo = fetchRegistrationInfo($mysqli, $userId);
    if ($registrationInfo === null) {
        echo json_encode([
            'success' => false,
            'message' => 'Registration assignment not found.'
        ]);
        exit;
    }
    list($username, $registrationNumber) = $registrationInfo;
}

switch ($action) {
    case 'call':
        handleCallAction($mysqli, $ids, $userId, $registrationNumber);
        break;
    case 'complete':
        handleStatusUpdate($mysqli, $ids, $userId, 'DONE');
        break;
    case 'return':
        handleStatusUpdate($mysqli, $ids, $userId, 'RETURNED');
        break;
    case 'announce':
        handleAnnounce($mysqli, $ids, $userId);
        break;
    case 'route_department':
        handleRouteDepartment($mysqli, $ids, $departmentId, $userId);
        break;
    case 'department_call':
        if (!userHasDepartmentAccess($departmentId, $hasFullDepartmentAccess, $departmentAccessIds)) {
            echo json_encode([
                'success' => false,
                'message' => 'You do not have permission to manage this department.'
            ]);
            exit;
        }
        handleDepartmentCall($mysqli, $departmentId, $departmentQueueId, $userId);
        break;
    case 'department_complete':
        if (!userHasDepartmentAccess($departmentId, $hasFullDepartmentAccess, $departmentAccessIds)) {
            echo json_encode([
                'success' => false,
                'message' => 'You do not have permission to manage this department.'
            ]);
            exit;
        }
        handleDepartmentComplete($mysqli, $departmentId, $departmentQueueId, $userId, $routeDepartmentIds, $routeRegistrationId);
        break;
    case 'counter_routed_call':
        handleRegistrationRoutedCall($mysqli, $ids, $userId, $registrationNumber);
        break;
    case 'counter_routed_complete':
        handleRegistrationRoutedComplete($mysqli, $ids, $userId, $registrationNumber, $routeDepartmentId);
        break;
    case 'load_payment_details':
        handleLoadPaymentDetails($mysqli, $paymentQueueId, $paymentDepartmentQueueId);
        break;
    case 'submit_payment':
        handleSubmitPayment($mysqli, $paymentQueueId, $paymentDepartmentQueueId, $amountReceived, $receiptNo, $userId);
        break;
    case 'search_patients':
        handleSearchPatients($mysqli, isset($_POST['query']) ? trim((string)$_POST['query']) : '');
        break;
    case 'tag_patient':
        $queueId = isset($_POST['queue_id']) ? (int)$_POST['queue_id'] : 0;
        $patientId = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
        handleTagPatient($mysqli, $queueId, $patientId, $userId, $registrationNumber, $serviceIds, $callId);
        break;
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Unknown action.'
        ]);
        exit;
}

function fetchRegistrationInfo($mysqli, $userId)
{
    if ($userId <= 0) {
        return null;
    }

    $stmt = $mysqli->prepare("SELECT u.username, ra.assigned_registration 
        FROM user u 
        LEFT JOIN registration_attendant ra ON ra.username = u.username 
        WHERE u.id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($username, $registrationNumber);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found || $registrationNumber === null || $registrationNumber === '') {
        return null;
    }

    return [$username, $registrationNumber];
}

function format_ticket_number_value($category, $queueNumber)
{
    $normalizedCategory = strtoupper(trim((string)$category));
    if (!is_numeric($queueNumber)) {
        $queueNumber = (int)$queueNumber;
    }
    $number = (int)$queueNumber;
    if ($number < 0) {
        $number = 0;
    }
    $ticket = str_pad((string)$number, 2, '0', STR_PAD_LEFT);
    return $normalizedCategory !== '' ? $normalizedCategory . $ticket : $ticket;
}

function format_queue_reference_label($category, $queueNumber, $uniqueCode)
{
    $ticketNumber = format_ticket_number_value($category, $queueNumber);
    $code = trim((string)$uniqueCode);
    return $code !== '' ? ($ticketNumber . ' (' . $code . ')') : $ticketNumber;
}

function handleSearchPatients($mysqli, $query)
{
    if (!$mysqli) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection unavailable.'
        ]);
        return;
    }

    $query = trim((string)$query);
    if ($query === '' || strlen($query) < 2) {
        echo json_encode([
            'success' => true,
            'patients' => []
        ]);
        return;
    }

    $like = '%' . $query . '%';
    $sql = "SELECT id, retrieval_code, firstname, middlename, lastname, dob, sex
            FROM patient_info
            WHERE retrieval_code LIKE ?
               OR firstname LIKE ?
               OR middlename LIKE ?
               OR lastname LIKE ?
            ORDER BY lastname ASC, firstname ASC
            LIMIT 20";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Unable to search patients at this time.'
        ]);
        return;
    }

    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $stmt->bind_result($patientId, $retrievalCode, $firstName, $middleName, $lastName, $dob, $sex);

    $patients = [];
    while ($stmt->fetch()) {
        $displayName = formatPatientDisplayName($firstName, $middleName, $lastName);
        $patients[] = [
            'id' => (int)$patientId,
            'retrieval_code' => $retrievalCode,
            'full_name' => $displayName,
            'display_name' => $displayName,
            'firstname' => $firstName,
            'middlename' => $middleName,
            'lastname' => $lastName,
            'last_name' => $lastName,
            'dob' => $dob,
            'sex' => $sex
        ];
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'patients' => $patients
    ]);
}

function handleTagPatient($mysqli, $queueId, $patientId, $userId, $registrationNumber, $serviceIds = [], $callId = 0)
{
    $queueId = (int)$queueId;
    $patientId = (int)$patientId;
    $callId = (int)$callId;
    $serviceIds = array_values(array_unique(array_filter(array_map('intval', (array)$serviceIds), function ($id) {
        return $id > 0;
    })));

    if ($queueId <= 0 || $patientId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid queue or patient reference.'
        ]);
        return;
    }

    $patientStmt = null;
    $patientQueries = [
        "SELECT retrieval_code, firstname, middlename, lastname FROM patient_info WHERE id = ? LIMIT 1",
        "SELECT retrieval_code, firstname, middlename, last_name AS lastname FROM patient_info WHERE id = ? LIMIT 1"
    ];
    foreach ($patientQueries as $query) {
        $patientStmt = $mysqli->prepare($query);
        if ($patientStmt) {
            break;
        }
    }

    if (!$patientStmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Unable to load patient information.'
        ]);
        return;
    }

    $patientStmt->bind_param('i', $patientId);
    $patientStmt->execute();
    $patientStmt->bind_result($retrievalCode, $firstName, $middleName, $lastName);
    $found = $patientStmt->fetch();
    $patientStmt->close();

    if (!$found) {
        echo json_encode([
            'success' => false,
            'message' => 'Patient record not found.'
        ]);
        return;
    }

    $retrievalCode = trim((string)$retrievalCode);
    if ($retrievalCode === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Selected patient does not have a retrieval code.'
        ]);
        return;
    }

    $queueCheck = $mysqli->prepare("SELECT id, retrieval_code FROM daily_queue WHERE id = ? AND queue_date = CURDATE() LIMIT 1");
    if (!$queueCheck) {
        echo json_encode([
            'success' => false,
            'message' => 'Queue record unavailable.'
        ]);
        return;
    }
    $queueCheck->bind_param('i', $queueId);
    $queueCheck->execute();
    $currentRetrievalCode = '';
    $queueCheck->bind_result($existingQueueId, $currentRetrievalCode);
    $queueFound = $queueCheck->fetch();
    $queueCheck->close();

    if (!$queueFound) {
        echo json_encode([
            'success' => false,
            'message' => 'Queue record not found or not part of today\'s queue.'
        ]);
        return;
    }

    if (empty($serviceIds)) {
        echo json_encode([
            'success' => false,
            'message' => 'Select at least one service for this patient.'
        ]);
        return;
    }

    if (!tableExists($mysqli, 'product_register')) {
        echo json_encode([
            'success' => false,
            'message' => 'Service list is unavailable. Please import product_register first.'
        ]);
        return;
    }

    $departmentMap = buildDepartmentMap($mysqli);
    $productsById = fetchProductsByIds($mysqli, $serviceIds);
    if (empty($productsById)) {
        echo json_encode([
            'success' => false,
            'message' => 'Selected services could not be found in the product register.'
        ]);
        return;
    }

    $selectedProducts = [];
    foreach ($serviceIds as $serviceId) {
        if (isset($productsById[$serviceId])) {
            $selectedProducts[] = $productsById[$serviceId];
        }
    }

    if (empty($selectedProducts)) {
        echo json_encode([
            'success' => false,
            'message' => 'No valid services were selected.'
        ]);
        return;
    }

    if (!tableExists($mysqli, 'daily_sales')) {
        echo json_encode([
            'success' => false,
            'message' => 'Daily sales table is missing. Please run daily_sales.sql first.'
        ]);
        return;
    }

    $departmentTargets = [];
    foreach ($selectedProducts as $productRow) {
        $deptId = resolveDepartmentIdFromProduct($productRow, $departmentMap);
        if ($deptId !== null) {
            $departmentTargets[] = $deptId;
        }
    }
    $cashierDeptId = findCashierDepartmentId($departmentMap);
    if ($cashierDeptId !== null) {
        $departmentTargets[] = $cashierDeptId;
    }
    $departmentTargets = array_values(array_unique(array_filter($departmentTargets, function ($value) {
        return (int)$value > 0;
    })));

    $callContext = null;
    if (!empty($departmentTargets)) {
        $callContext = fetchActiveQueueCall($mysqli, $queueId, $callId, $userId);
    }

    $routeSummaryDefaults = [
        'success' => true,
        'routed' => 0,
        'duplicates' => 0,
        'skipped' => 0,
        'errors' => [],
        'missing_call' => false
    ];
    $routeSummary = $routeSummaryDefaults;
    $transactionStarted = false;
    if ($mysqli && method_exists($mysqli, 'begin_transaction')) {
        $transactionStarted = $mysqli->begin_transaction();
    }

    $updateStmt = $mysqli->prepare("UPDATE daily_queue SET retrieval_code = ? WHERE id = ?");
    if (!$updateStmt) {
        if ($transactionStarted) {
            $mysqli->rollback();
        }
        echo json_encode([
            'success' => false,
            'message' => 'Unable to update queue ticket.'
        ]);
        return;
    }
    $updateStmt->bind_param('si', $retrievalCode, $queueId);
    $updateStmt->execute();
    $affected = $updateStmt->affected_rows;
    $updateStmt->close();

    $salesResult = recordDailySalesEntries($mysqli, $queueId, $retrievalCode, $selectedProducts);
    if (!$salesResult['success']) {
        if ($transactionStarted) {
            $mysqli->rollback();
        }
        echo json_encode([
            'success' => false,
            'message' => $salesResult['message']
        ]);
        return;
    }

    if (!empty($departmentTargets) && $callContext !== null) {
        $routeResult = routeDepartmentsForServices($mysqli, $callContext['id'], $callContext['queue_id'], $userId, $departmentTargets);
        if (is_array($routeResult)) {
            // Merge to ensure expected keys exist, preventing undefined-index notices.
            $routeSummary = array_merge($routeSummaryDefaults, $routeResult);
        } else {
            $routeSummary = $routeSummaryDefaults;
            $routeSummary['success'] = false;
            $routeSummary['errors'] = ['Unable to route services.'];
        }
    } elseif (!empty($departmentTargets)) {
        $routeSummary['missing_call'] = true;
    }

    if (!$routeSummary['success']) {
        if ($transactionStarted) {
            $mysqli->rollback();
        }
        echo json_encode([
            'success' => false,
            'message' => !empty($routeSummary['errors']) ? implode(' ', $routeSummary['errors']) : 'Unable to route services.'
        ]);
        return;
    }

    if ($transactionStarted) {
        $mysqli->commit();
    }

    $nameParts = array_filter([
        trim((string)$firstName),
        trim((string)$middleName),
        trim((string)$lastName)
    ]);

    $messages = [];
    $messages[] = $affected === 0 && $currentRetrievalCode === $retrievalCode
        ? 'Patient tag already applied to this ticket.'
        : 'Patient information tagged successfully.';
    $messages[] = sprintf('%d service(s) saved for this ticket.', $salesResult['inserted']);
    if ($routeSummary['routed'] > 0) {
        $messages[] = sprintf('Routed to %d department(s).', $routeSummary['routed']);
    }
    if ($routeSummary['duplicates'] > 0) {
        $messages[] = sprintf('%d existing routes were kept.', $routeSummary['duplicates']);
    }
    if ($routeSummary['missing_call']) {
        $messages[] = 'No active call found for routing; services were still recorded.';
    }
    if (!empty($routeSummary['errors'])) {
        $messages = array_merge($messages, $routeSummary['errors']);
    }

    echo json_encode([
        'success' => true,
        'message' => implode(' ', array_filter($messages)),
        'retrieval_code' => $retrievalCode,
        'patient_name' => implode(' ', $nameParts),
        'queue_id' => $queueId,
        'services_saved' => $salesResult['inserted'],
        'routed_departments' => $routeSummary['routed']
    ]);
}

function fetchProductsByIds($mysqli, array $productIds)
{
    $products = [];
    if (!$mysqli || empty($productIds) || !tableExists($mysqli, 'product_register')) {
        return $products;
    }

    $columns = describeTableColumns($mysqli, 'product_register');
    if ($columns === null) {
        return $products;
    }

    $columnMap = [
        'id' => 'id',
        'item_code' => 'item_code',
        'item_description' => 'item_description',
        'department_code' => 'department_code',
        'department' => 'department',
        'amount' => 'amount'
    ];

    $selectColumns = [];
    foreach ($columnMap as $alias => $columnName) {
        if (isset($columns[strtolower($columnName)])) {
            $selectColumns[$alias] = $columnName;
        }
    }

    if (!isset($selectColumns['id'])) {
        return $products;
    }

    $idColumn = $selectColumns['id'];
    $idList = implode(',', array_map('intval', $productIds));
    $sql = 'SELECT ' . implode(', ', array_map(function ($col) {
        return '`' . $col . '`';
    }, $selectColumns)) . " FROM product_register WHERE `{$idColumn}` IN ({$idList})";

    if ($result = $mysqli->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $productId = isset($row[$idColumn]) ? (int)$row[$idColumn] : 0;
            if ($productId <= 0) {
                continue;
            }
            $products[$productId] = [
                'id' => $productId,
                'item_code' => (isset($selectColumns['item_code']) && isset($row[$selectColumns['item_code']])) ? $row[$selectColumns['item_code']] : '',
                'item_description' => (isset($selectColumns['item_description']) && isset($row[$selectColumns['item_description']])) ? $row[$selectColumns['item_description']] : '',
                'department_code' => (isset($selectColumns['department_code']) && isset($row[$selectColumns['department_code']])) ? $row[$selectColumns['department_code']] : '',
                'department' => (isset($selectColumns['department']) && isset($row[$selectColumns['department']])) ? $row[$selectColumns['department']] : '',
                'amount' => (isset($selectColumns['amount']) && isset($row[$selectColumns['amount']])) ? $row[$selectColumns['amount']] : null
            ];
        }
        $result->free();
    }

    return $products;
}

function recordDailySalesEntries($mysqli, $queueId, $retrievalCode, array $products)
{
    $result = [
        'success' => true,
        'inserted' => 0,
        'message' => ''
    ];

    if (!$mysqli || !tableExists($mysqli, 'daily_sales')) {
        $result['success'] = false;
        $result['message'] = 'Daily sales table is not available.';
        return $result;
    }

    $queueId = (int)$queueId;

    $deleteStmt = $mysqli->prepare("DELETE FROM daily_sales WHERE queue_id = ?");
    if ($deleteStmt) {
        $deleteStmt->bind_param('i', $queueId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    $insertStmt = $mysqli->prepare("INSERT INTO daily_sales (queue_id, retrieval_code, item_code, lineitem, amount) VALUES (?, ?, ?, ?, ?)");
    if (!$insertStmt) {
        $result['success'] = false;
        $result['message'] = 'Unable to prepare daily sales entry.';
        return $result;
    }

    $lineItem = 1;
    foreach ($products as $product) {
        $itemCode = (isset($product['item_code']) && $product['item_code'] !== '') ? (string)$product['item_code'] : (string)$product['id'];
        $amountValue = (isset($product['amount']) && is_numeric($product['amount'])) ? (float)$product['amount'] : 0;
        $insertStmt->bind_param('issid', $queueId, $retrievalCode, $itemCode, $lineItem, $amountValue);
        if ($insertStmt->execute()) {
            $result['inserted']++;
        }
        $lineItem++;
    }
    $insertStmt->close();

    return $result;
}

function formatPatientDisplayName($firstName, $middleName, $lastName)
{
    $firstName = trim((string)$firstName);
    $middleName = trim((string)$middleName);
    $lastName = trim((string)$lastName);
    $parts = [];
    if ($lastName !== '') {
        $parts[] = $lastName;
    }
    if ($firstName !== '') {
        $parts[] = ($lastName !== '' ? ', ' : '') . $firstName;
    }
    if ($middleName !== '') {
        $parts[] = $middleName;
    }
    return trim(implode(' ', $parts));
}

function fetchPaymentDetails($mysqli, $queueId)
{
    if (!$mysqli || $queueId <= 0) {
        return null;
    }

    $stmt = $mysqli->prepare("SELECT id, category, queue_number, unique_code, retrieval_code, payment_status 
        FROM daily_queue 
        WHERE id = ? AND queue_date = CURDATE() 
        LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $queueId);
    $stmt->execute();
    $stmt->bind_result($id, $category, $queueNumber, $uniqueCode, $retrievalCode, $paymentStatus);
    if (!$stmt->fetch()) {
        $stmt->close();
        return null;
    }
    $stmt->close();

    $patientName = '';
    $patientId = null;
    $retrievalCode = trim((string)$retrievalCode);
    if ($retrievalCode !== '' && tableExists($mysqli, 'patient_info')) {
        $patientStmt = null;
        $patientQueries = [
            "SELECT id, firstname, middlename, lastname FROM patient_info WHERE retrieval_code = ? LIMIT 1",
            "SELECT id, firstname, middlename, last_name AS lastname FROM patient_info WHERE retrieval_code = ? LIMIT 1"
        ];
        foreach ($patientQueries as $query) {
            $patientStmt = $mysqli->prepare($query);
            if ($patientStmt) {
                break;
            }
        }
        if ($patientStmt) {
            $patientStmt->bind_param('s', $retrievalCode);
            $patientStmt->execute();
            $patientStmt->bind_result($pId, $firstName, $middleName, $lastName);
            if ($patientStmt->fetch()) {
                $patientId = $pId;
                $patientName = formatPatientDisplayName($firstName, $middleName, $lastName);
            }
            $patientStmt->close();
        }
    }

    $services = [];
    $totalAmount = 0;
    $receiptNoValue = '';
    $totalAmountPaidValue = null;
    if (tableExists($mysqli, 'daily_sales')) {
        $joinProduct = tableExists($mysqli, 'product_register');
        $serviceSql = "SELECT ds.item_code, ds.lineitem, ds.amount, ds.payment_status";
        if ($joinProduct) {
            $serviceSql .= ", pr.item_description, pr.department, pr.department_code";
        }
        $serviceSql .= " FROM daily_sales ds";
        if ($joinProduct) {
            $serviceSql .= " LEFT JOIN (
                SELECT item_code,
                       MAX(item_description) AS item_description,
                       MAX(department) AS department,
                       MAX(department_code) AS department_code
                FROM product_register
                GROUP BY item_code
            ) pr ON pr.item_code = ds.item_code";
        }
        $serviceSql .= " WHERE ds.queue_id = ? ORDER BY ds.lineitem ASC";

        $serviceStmt = $mysqli->prepare($serviceSql);
        if ($serviceStmt) {
            $serviceStmt->bind_param('i', $queueId);
            $serviceStmt->execute();
            if ($joinProduct) {
                $serviceStmt->bind_result($itemCode, $lineItem, $amount, $linePaymentStatus, $description, $departmentName, $departmentCode);
            } else {
                $serviceStmt->bind_result($itemCode, $lineItem, $amount, $linePaymentStatus);
            }
            while ($serviceStmt->fetch()) {
                $lineAmount = is_numeric($amount) ? (float)$amount : 0;
                $services[] = [
                    'item_code' => $itemCode,
                    'lineitem' => (int)$lineItem,
                    'description' => $joinProduct ? $description : '',
                    'department' => $joinProduct ? (string)$departmentName : '',
                    'department_code' => $joinProduct ? (string)$departmentCode : '',
                    'amount' => $lineAmount,
                    'payment_status' => is_numeric($linePaymentStatus) ? (int)$linePaymentStatus : 0
                ];
                $totalAmount += $lineAmount;
            }
            $serviceStmt->close();
        }
        // Attempt to load optional cashier metadata without relying on SHOW COLUMNS privileges.
        $metaStmt = $mysqli->prepare("SELECT MAX(total_amount_paid) AS total_amount_paid, MAX(receipt_no) AS receipt_no FROM daily_sales WHERE queue_id = ?");
        if ($metaStmt) {
            $metaStmt->bind_param('i', $queueId);
            if ($metaStmt->execute()) {
                $metaStmt->bind_result($paidAmount, $receiptNo);
                if ($metaStmt->fetch()) {
                    $totalAmountPaidValue = is_numeric($paidAmount) ? (float)$paidAmount : null;
                    $receiptNoValue = isset($receiptNo) ? trim((string)$receiptNo) : '';
                }
            }
            $metaStmt->close();
        } else {
            // Backwards-compatible fallback for older schemas (missing one or both columns).
            $paidStmt = $mysqli->prepare("SELECT MAX(total_amount_paid) AS total_amount_paid FROM daily_sales WHERE queue_id = ?");
            if ($paidStmt) {
                $paidStmt->bind_param('i', $queueId);
                if ($paidStmt->execute()) {
                    $paidStmt->bind_result($paidAmount);
                    if ($paidStmt->fetch()) {
                        $totalAmountPaidValue = is_numeric($paidAmount) ? (float)$paidAmount : null;
                    }
                }
                $paidStmt->close();
            }
            $receiptStmt = $mysqli->prepare("SELECT MAX(receipt_no) AS receipt_no FROM daily_sales WHERE queue_id = ?");
            if ($receiptStmt) {
                $receiptStmt->bind_param('i', $queueId);
                if ($receiptStmt->execute()) {
                    $receiptStmt->bind_result($receiptNo);
                    if ($receiptStmt->fetch()) {
                        $receiptNoValue = isset($receiptNo) ? trim((string)$receiptNo) : '';
                    }
                }
                $receiptStmt->close();
            }
        }
    }

    $ticketNumber = format_ticket_number_value($category, $queueNumber);
    $queueLabel = format_queue_reference_label($category, $queueNumber, $uniqueCode);

    return [
        'queue_id' => (int)$queueId,
        'queue_label' => $queueLabel,
        'ticket_number' => $ticketNumber,
        'unique_code' => $uniqueCode,
        'retrieval_code' => $retrievalCode,
        'payment_status' => is_numeric($paymentStatus) ? (int)$paymentStatus : 0,
        'patient_name' => $patientName,
        'patient_id' => $patientId,
        'services' => $services,
        'total_amount' => $totalAmount,
        'total_amount_paid' => $totalAmountPaidValue,
        'receipt_no' => $receiptNoValue
    ];
}

function handleLoadPaymentDetails($mysqli, $queueId, $departmentQueueId = 0)
{
    $details = fetchPaymentDetails($mysqli, $queueId);
    if ($details === null) {
        echo json_encode([
            'success' => false,
            'message' => 'Unable to load payment details for this ticket.'
        ]);
        return;
    }

    $details['department_queue_id'] = (int)$departmentQueueId;

    echo json_encode([
        'success' => true,
        'data' => $details
    ]);
}

function handleSubmitPayment($mysqli, $queueId, $departmentQueueId, $amountReceived, $receiptNo, $userId)
{
    $details = fetchPaymentDetails($mysqli, $queueId);
    if ($details === null) {
        echo json_encode([
            'success' => false,
            'message' => 'Unable to locate the queue record for payment.'
        ]);
        return;
    }

    $isAlreadyPaid = isset($details['payment_status']) && (int)$details['payment_status'] === 1;
    $totalAmount = isset($details['total_amount']) ? (float)$details['total_amount'] : 0.0;
    $amountReceived = (float)$amountReceived;
    $receiptNo = trim((string)$receiptNo);

    // Accept any received amount for now, even if less than the total due.

    $now = date('Y-m-d H:i:s');
    $updatesOk = true;

    if (tableExists($mysqli, 'daily_sales')) {
        if ($isAlreadyPaid) {
            // Keep existing payment_status/date_paid, but allow recording receipt and paid amount.
            $salesStmt = $mysqli->prepare("UPDATE daily_sales SET total_amount_paid = ?, receipt_no = ? WHERE queue_id = ?");
            if ($salesStmt) {
                $salesStmt->bind_param('dsi', $amountReceived, $receiptNo, $queueId);
                $updatesOk = $salesStmt->execute() && $updatesOk;
                $salesStmt->close();
            } else {
                // Backwards-compatible fallback for older schemas.
                $paidStmt = $mysqli->prepare("UPDATE daily_sales SET total_amount_paid = ? WHERE queue_id = ?");
                if ($paidStmt) {
                    $paidStmt->bind_param('di', $amountReceived, $queueId);
                    $updatesOk = $paidStmt->execute() && $updatesOk;
                    $paidStmt->close();
                }
                $receiptStmt = $mysqli->prepare("UPDATE daily_sales SET receipt_no = ? WHERE queue_id = ?");
                if ($receiptStmt) {
                    $receiptStmt->bind_param('si', $receiptNo, $queueId);
                    $updatesOk = $receiptStmt->execute() && $updatesOk;
                    $receiptStmt->close();
                }
            }
        } else {
            // First-time payment: mark lines as paid and store receipt/amount if columns exist.
            $salesStmt = $mysqli->prepare("UPDATE daily_sales SET payment_status = 1, date_paid = ?, total_amount_paid = ?, receipt_no = ? WHERE queue_id = ?");
            if ($salesStmt) {
                $salesStmt->bind_param('sdsi', $now, $amountReceived, $receiptNo, $queueId);
                $updatesOk = $salesStmt->execute() && $updatesOk;
                $salesStmt->close();
            } else {
                // Backwards-compatible fallback for older schemas.
                $paidStmt = $mysqli->prepare("UPDATE daily_sales SET payment_status = 1, date_paid = ?, total_amount_paid = ? WHERE queue_id = ?");
                if ($paidStmt) {
                    $paidStmt->bind_param('sdi', $now, $amountReceived, $queueId);
                    $updatesOk = $paidStmt->execute() && $updatesOk;
                    $paidStmt->close();
                }
                $receiptStmt = $mysqli->prepare("UPDATE daily_sales SET payment_status = 1, date_paid = ?, receipt_no = ? WHERE queue_id = ?");
                if ($receiptStmt) {
                    $receiptStmt->bind_param('ssi', $now, $receiptNo, $queueId);
                    $updatesOk = $receiptStmt->execute() && $updatesOk;
                    $receiptStmt->close();
                }
                $fallbackStmt = $mysqli->prepare("UPDATE daily_sales SET payment_status = 1, date_paid = ? WHERE queue_id = ?");
                if ($fallbackStmt) {
                    $fallbackStmt->bind_param('si', $now, $queueId);
                    $updatesOk = $fallbackStmt->execute() && $updatesOk;
                    $fallbackStmt->close();
                }
            }
        }
    }

    if (!$isAlreadyPaid) {
        $queueStmt = $mysqli->prepare("UPDATE daily_queue SET payment_status = 1, date_paid = ? WHERE id = ?");
        if ($queueStmt) {
            $queueStmt->bind_param('si', $now, $queueId);
            $updatesOk = $queueStmt->execute() && $updatesOk;
            $queueStmt->close();
        }
    }

    $departmentId = null;
    if ($departmentQueueId > 0) {
        $deptStmt = $mysqli->prepare("SELECT department_id FROM department_queue WHERE id = ? LIMIT 1");
        if ($deptStmt) {
            $deptStmt->bind_param('i', $departmentQueueId);
            $deptStmt->execute();
            $deptStmt->bind_result($deptId);
            if ($deptStmt->fetch()) {
                $departmentId = (int)$deptId;
            }
            $deptStmt->close();
        }
    }

    if ($departmentQueueId > 0) {
        $completeStmt = $mysqli->prepare("UPDATE department_queue 
            SET status = 'DONE',
                completed_at = ?,
                called_at = COALESCE(called_at, ?)
            WHERE id = ? AND is_history = 0");
        if ($completeStmt) {
            $completeStmt->bind_param('ssi', $now, $now, $departmentQueueId);
            $updatesOk = $completeStmt->execute() && $updatesOk;
            $completeStmt->close();
        }
    }

    if ($departmentQueueId > 0 && $departmentId !== null) {
        logDepartmentQueueEvent($mysqli, $departmentQueueId, 'DONE', 'PAYMENT', [
            'completed_at' => $now
        ]);
    }

    $messageParts = [];
    if (!$updatesOk) {
        $messageParts[] = 'Payment was recorded with some issues. Please verify records.';
    } elseif ($isAlreadyPaid) {
        $messageParts[] = 'This ticket is already marked as paid.';
    } else {
        $messageParts[] = 'Payment recorded successfully.';
    }

    $changeDue = $amountReceived - $totalAmount;
    if ($changeDue < 0) {
        $messageParts[] = sprintf(
            'Total: %s. Received: %s. Balance: %s.',
            number_format($totalAmount, 2),
            number_format($amountReceived, 2),
            number_format(abs($changeDue), 2)
        );
    } else {
        $messageParts[] = sprintf(
            'Total: %s. Received: %s. Change: %s.',
            number_format($totalAmount, 2),
            number_format($amountReceived, 2),
            number_format($changeDue, 2)
        );
    }

    echo json_encode([
        'success' => $updatesOk,
        'message' => implode(' ', $messageParts),
        'paid' => true,
        'payment_status' => 1,
        'department_queue_id' => $departmentQueueId,
        'queue_id' => $queueId
    ]);
}

function fetchActiveQueueCall($mysqli, $queueId, $callId, $userId)
{
    if (!$mysqli) {
        return null;
    }

    if ($callId > 0) {
        $stmt = $mysqli->prepare("SELECT id, queue_id FROM queue_calls WHERE id = ? AND registration_user_id = ? AND status = 'ACTIVE' LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ii', $callId, $userId);
    } else {
        $stmt = $mysqli->prepare("SELECT id, queue_id FROM queue_calls WHERE queue_id = ? AND registration_user_id = ? AND status = 'ACTIVE' ORDER BY called_at DESC LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('ii', $queueId, $userId);
    }

    $stmt->execute();
    $stmt->bind_result($foundCallId, $foundQueueId);
    if ($stmt->fetch()) {
        $stmt->close();
        return [
            'id' => (int)$foundCallId,
            'queue_id' => (int)$foundQueueId
        ];
    }
    $stmt->close();
    return null;
}

function buildDepartmentMap($mysqli)
{
    $map = [
        'id' => [],
        'code' => [],
        'name' => []
    ];

    if (!$mysqli) {
        return $map;
    }

    if ($result = $mysqli->query("SELECT id, department_code, department FROM department")) {
        while ($row = $result->fetch_assoc()) {
            $deptId = isset($row['id']) ? (int)$row['id'] : 0;
            if ($deptId <= 0) {
                continue;
            }
            $code = isset($row['department_code']) ? strtoupper(trim((string)$row['department_code'])) : '';
            $name = isset($row['department']) ? strtoupper(trim((string)$row['department'])) : '';
            $map['id'][$deptId] = [
                'id' => $deptId,
                'code' => $code,
                'name' => isset($row['department']) ? $row['department'] : ''
            ];
            if ($code !== '') {
                $map['code'][$code] = $deptId;
            }
            if ($name !== '') {
                $map['name'][$name] = $deptId;
            }
        }
        $result->free();
    }

    return $map;
}

function resolveDepartmentIdFromProduct(array $productRow, array $departmentMap)
{
    $deptValueRaw = isset($productRow['department']) ? $productRow['department'] : '';
    if ($deptValueRaw !== '') {
        if (is_numeric($deptValueRaw) && isset($departmentMap['id'][(int)$deptValueRaw])) {
            return (int)$deptValueRaw;
        }
        $deptName = strtoupper(trim((string)$deptValueRaw));
        if ($deptName !== '' && isset($departmentMap['name'][$deptName])) {
            return $departmentMap['name'][$deptName];
        }
    }

    $deptCodeRaw = isset($productRow['department_code']) ? $productRow['department_code'] : '';
    $deptCode = strtoupper(trim((string)$deptCodeRaw));
    if ($deptCode !== '' && isset($departmentMap['code'][$deptCode])) {
        return $departmentMap['code'][$deptCode];
    }

    return null;
}

function findCashierDepartmentId(array $departmentMap)
{
    $candidates = ['CASHIER', 'CASH'];
    foreach ($candidates as $code) {
        if (isset($departmentMap['code'][$code])) {
            return $departmentMap['code'][$code];
        }
        if (isset($departmentMap['name'][$code])) {
            return $departmentMap['name'][$code];
        }
    }
    return null;
}

function routeDepartmentsForServices($mysqli, $callId, $queueId, $userId, array $departmentIds)
{
    $result = [
        'success' => true,
        'routed' => 0,
        'duplicates' => 0,
        'skipped' => 0,
        'errors' => []
    ];

    if (!$mysqli || $callId <= 0 || $queueId <= 0) {
        $result['success'] = false;
        $result['errors'][] = 'Routing failed because the active call could not be verified.';
        return $result;
    }

    $departmentIds = array_values(array_unique(array_filter(array_map('intval', $departmentIds), function ($id) {
        return $id > 0;
    })));
    if (empty($departmentIds)) {
        return $result;
    }

    $checkStmt = $mysqli->prepare("SELECT id FROM department_queue WHERE queue_call_id = ? AND department_id = ? AND status IN ('WAITING', 'CALLED') AND is_history = 0 LIMIT 1");
    if (!$checkStmt) {
        $result['success'] = false;
        $result['errors'][] = 'Unable to check existing department routes.';
        return $result;
    }

    $insertStmt = $mysqli->prepare("INSERT INTO department_queue (queue_call_id, queue_id, department_id, status, routed_by, routed_at) VALUES (?, ?, ?, 'WAITING', ?, ?)");
    if (!$insertStmt) {
        $checkStmt->close();
        $result['success'] = false;
        $result['errors'][] = 'Unable to prepare department routing statement.';
        return $result;
    }

    $now = date('Y-m-d H:i:s');

    foreach ($departmentIds as $deptId) {
        $checkStmt->bind_param('ii', $callId, $deptId);
        $checkStmt->execute();
        $checkStmt->store_result();
        if ($checkStmt->num_rows > 0) {
            $result['duplicates']++;
            continue;
        }

        $insertStmt->bind_param('iiiis', $callId, $queueId, $deptId, $userId, $now);
        if ($insertStmt->execute()) {
            $result['routed']++;
        } else {
            $result['errors'][] = sprintf('Unable to route to department ID %d.', $deptId);
        }
    }

    $checkStmt->close();
    $insertStmt->close();

    if (!empty($result['errors'])) {
        $result['success'] = false;
    }

    return $result;
}

function patientInfoHasColumn($mysqli, $columnName)
{
    static $cache = [];
    $key = strtolower((string)$columnName);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    if (!$mysqli) {
        $cache[$key] = false;
        return false;
    }

    $stmt = $mysqli->prepare("SHOW COLUMNS FROM patient_info LIKE ?");
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }
    $stmt->bind_param('s', $columnName);
    $executed = $stmt->execute();
    $stmt->store_result();
    $cache[$key] = $executed && $stmt->num_rows > 0;
    $stmt->close();
    return $cache[$key];
}

function dailySalesHasColumn($mysqli, $columnName)
{
    static $cache = [];
    $key = strtolower((string)$columnName);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    if (!$mysqli) {
        $cache[$key] = false;
        return false;
    }

    $stmt = $mysqli->prepare("SHOW COLUMNS FROM daily_sales LIKE ?");
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }
    $stmt->bind_param('s', $columnName);
    $executed = $stmt->execute();
    $stmt->store_result();
    $cache[$key] = $executed && $stmt->num_rows > 0;
    $stmt->close();

    return $cache[$key];
}

function resolvePatientLastNameColumn($mysqli)
{
    static $cached = null;
    if ($cached !== null) {
        return $cached ?: false;
    }

    $candidates = ['lastname', 'last_name'];
    foreach ($candidates as $column) {
        if (patientInfoHasColumn($mysqli, $column)) {
            $cached = $column;
            return $cached;
        }
    }

    $cached = '';
    return false;
}

function handleCallAction($mysqli, $queueIds, $userId, $registrationNumber)
{
    $inserted = 0;
    $now = date('Y-m-d H:i:s');

    foreach ($queueIds as $queueId) {
        // Ensure queue entry exists for today
        $stmt = $mysqli->prepare("SELECT id FROM daily_queue WHERE id = ? AND queue_date = CURDATE() LIMIT 1");
        $stmt->bind_param('i', $queueId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            continue;
        }
        $stmt->close();

        // Skip if already active
        $stmt = $mysqli->prepare("SELECT id FROM queue_calls WHERE queue_id = ? AND status = 'ACTIVE' LIMIT 1");
        $stmt->bind_param('i', $queueId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->close();
            continue;
        }
        $stmt->close();

        $stmt = $mysqli->prepare("INSERT INTO queue_calls (queue_id, registration_user_id, registration_number, status, called_at) VALUES (?, ?, ?, 'ACTIVE', ?)");
        $stmt->bind_param('iiss', $queueId, $userId, $registrationNumber, $now);
        if ($stmt->execute()) {
            $inserted++;
        }
        $stmt->close();
    }

    echo json_encode([
        'success' => true,
        'message' => $inserted > 0 ? 'Customer(s) moved to serving list.' : 'No customers updated.',
        'updated' => $inserted
    ]);
}

function handleStatusUpdate($mysqli, $callIds, $userId, $newStatus)
{
    if (!in_array($newStatus, ['DONE', 'RETURNED'], true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Unsupported status.'
        ]);
        return;
    }

    $updated = 0;
    $now = date('Y-m-d H:i:s');

    foreach ($callIds as $callId) {
        $stmt = $mysqli->prepare("UPDATE queue_calls 
            SET status = ?, completed_at = ? 
            WHERE id = ? AND registration_user_id = ? AND status = 'ACTIVE'");
        $stmt->bind_param('ssii', $newStatus, $now, $callId, $userId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $updated++;
        }
        $stmt->close();

        $counterStmt = $mysqli->prepare("UPDATE registration_queue 
            SET status = 'DONE', completed_at = ? 
            WHERE queue_call_id = ? AND status <> 'DONE'");
        if ($counterStmt) {
            $counterStmt->bind_param('si', $now, $callId);
            $counterStmt->execute();
            $counterStmt->close();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $updated > 0 ? 'Queue updated.' : 'No records updated.',
        'updated' => $updated
    ]);
}

function handleAnnounce($mysqli, $callIds, $userId)
{
    $updated = 0;
    $now = date('Y-m-d H:i:s');

    foreach ($callIds as $callId) {
        $stmt = $mysqli->prepare("UPDATE queue_calls 
            SET called_at = ? 
            WHERE id = ? AND registration_user_id = ? AND status = 'ACTIVE'");
        $stmt->bind_param('sii', $now, $callId, $userId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $updated++;
        }
        $stmt->close();
    }

    echo json_encode([
        'success' => true,
        'message' => $updated > 0 ? 'Announcement triggered.' : 'No records updated.',
        'updated' => $updated
    ]);
}

function handleRouteDepartment($mysqli, $callIds, $departmentId, $userId)
{
    if ($departmentId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Please select a department.'
        ]);
        return;
    }

    $departmentName = null;
    $stmt = $mysqli->prepare("SELECT department FROM department WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $departmentId);
        $stmt->execute();
        $stmt->bind_result($deptName);
        if ($stmt->fetch()) {
            $departmentName = $deptName;
        }
        $stmt->close();
    }

    if ($departmentName === null) {
        echo json_encode([
            'success' => false,
            'message' => 'Selected department does not exist.'
        ]);
        return;
    }

    $routed = 0;
    $duplicates = 0;
    $skipped = 0;
    $now = date('Y-m-d H:i:s');

    foreach ($callIds as $callId) {
        $queueId = null;
        $stmt = $mysqli->prepare("SELECT queue_id FROM queue_calls WHERE id = ? AND registration_user_id = ? AND status = 'ACTIVE' LIMIT 1");
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('ii', $callId, $userId);
        $stmt->execute();
        $stmt->bind_result($queueIdValue);
        if ($stmt->fetch()) {
            $queueId = (int)$queueIdValue;
        }
        $stmt->close();

        if ($queueId === null) {
            $skipped++;
            continue;
        }

        $checkStmt = $mysqli->prepare("SELECT id FROM department_queue WHERE queue_call_id = ? AND department_id = ? AND status IN ('WAITING', 'CALLED') AND is_history = 0 LIMIT 1");
        if ($checkStmt) {
            $checkStmt->bind_param('ii', $callId, $departmentId);
            $checkStmt->execute();
            $checkStmt->store_result();
            if ($checkStmt->num_rows > 0) {
                $duplicates++;
                $checkStmt->close();
                continue;
            }
            $checkStmt->close();
        }

        $insertStmt = $mysqli->prepare("INSERT INTO department_queue (queue_call_id, queue_id, department_id, status, routed_by, routed_at) VALUES (?, ?, ?, 'WAITING', ?, ?)");
        if ($insertStmt) {
            $insertStmt->bind_param('iiiis', $callId, $queueId, $departmentId, $userId, $now);
            if ($insertStmt->execute()) {
                $routed++;
            }
            $insertStmt->close();
        }
    }

    $messages = [];
    if ($routed > 0) {
        $messages[] = sprintf('%d customer(s) routed to %s.', $routed, $departmentName);
    }
    if ($duplicates > 0) {
        $messages[] = sprintf('%d already assigned to this department.', $duplicates);
    }
    if ($skipped > 0) {
        $messages[] = sprintf('%d could not be routed (inactive or unavailable).', $skipped);
    }
    if (empty($messages)) {
        $messages[] = 'No customers were routed.';
    }

    echo json_encode([
        'success' => $routed > 0,
        'message' => implode(' ', $messages),
        'routed' => $routed,
        'duplicates' => $duplicates,
        'skipped' => $skipped,
        'department_id' => $departmentId
    ]);
}

function handleDepartmentCall($mysqli, $departmentId, $departmentQueueId, $userId)
{
    if ($departmentId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Please select a department.'
        ]);
        return;
    }

    $departmentName = null;
    $stmt = $mysqli->prepare("SELECT department FROM department WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $departmentId);
        $stmt->execute();
        $stmt->bind_result($deptName);
        if ($stmt->fetch()) {
            $departmentName = $deptName;
        }
        $stmt->close();
    }

    if ($departmentName === null) {
        echo json_encode([
            'success' => false,
            'message' => 'Selected department does not exist.'
        ]);
        return;
    }

    $departmentMap = buildDepartmentMap($mysqli);
    $cashierDeptId = findCashierDepartmentId($departmentMap);
    $isCashierDepartment = ($cashierDeptId !== null && (int)$cashierDeptId === (int)$departmentId);

    $now = date('Y-m-d H:i:s');
    $targetId = null;
    $actionType = 'called';
    $candidate = null;

    if ($departmentQueueId > 0) {
        $stmt = $mysqli->prepare("SELECT dq.id, dq.status, daily.payment_status 
            FROM department_queue dq
            INNER JOIN queue_calls qc ON qc.id = dq.queue_call_id
            INNER JOIN daily_queue daily ON daily.id = qc.queue_id
            WHERE dq.id = ? AND dq.department_id = ? AND dq.is_history = 0
            LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ii', $departmentQueueId, $departmentId);
            $stmt->execute();
            $stmt->bind_result($rowId, $rowStatus, $rowPaymentStatus);
            if ($stmt->fetch()) {
                $candidate = [
                    'id' => (int)$rowId,
                    'status' => $rowStatus,
                    'payment_status' => is_numeric($rowPaymentStatus) ? (int)$rowPaymentStatus : 0
                ];
            }
            $stmt->close();
        }
    }

    if ($candidate === null) {
        $stmt = $mysqli->prepare("SELECT dq.id, dq.status, daily.payment_status 
            FROM department_queue dq
            INNER JOIN queue_calls qc ON qc.id = dq.queue_call_id
            INNER JOIN daily_queue daily ON daily.id = qc.queue_id
            WHERE dq.department_id = ? AND dq.status = 'WAITING' AND dq.is_history = 0
            ORDER BY dq.routed_at ASC
            LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $departmentId);
            $stmt->execute();
            $stmt->bind_result($rowId, $rowStatus, $rowPaymentStatus);
            if ($stmt->fetch()) {
                $candidate = [
                    'id' => (int)$rowId,
                    'status' => $rowStatus,
                    'payment_status' => is_numeric($rowPaymentStatus) ? (int)$rowPaymentStatus : 0
                ];
            }
            $stmt->close();
        }
    }

    if ($candidate !== null && !$isCashierDepartment && (int)$candidate['payment_status'] === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Payment is required before this department can call the patient. Please direct the customer to cashier.',
            'department_id' => $departmentId
        ]);
        return;
    }

    if ($candidate !== null) {
        $targetId = $candidate['id'];
        $actionType = (isset($candidate['status']) && strtoupper((string)$candidate['status']) === 'CALLED') ? 'reannounced' : 'called';
        $stmt = $mysqli->prepare("UPDATE department_queue 
            SET status = 'CALLED', called_at = ?, called_by = ? 
            WHERE id = ? AND department_id = ? AND is_history = 0");
        if ($stmt) {
            $stmt->bind_param('siii', $now, $userId, $targetId, $departmentId);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($targetId === null) {
        $stmt = $mysqli->prepare("SELECT dq.id, daily.payment_status 
            FROM department_queue dq
            INNER JOIN queue_calls qc ON qc.id = dq.queue_call_id
            INNER JOIN daily_queue daily ON daily.id = qc.queue_id
            WHERE dq.department_id = ? AND dq.status = 'CALLED' AND dq.is_history = 0
            ORDER BY dq.called_at DESC
            LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $departmentId);
            $stmt->execute();
            $stmt->bind_result($calledId, $paymentStatus);
            if ($stmt->fetch()) {
                if ($isCashierDepartment || (int)$paymentStatus !== 0) {
                    $targetId = (int)$calledId;
                    $actionType = 'reannounced';
                } else {
                    $stmt->close();
                    echo json_encode([
                        'success' => false,
                        'message' => 'Payment is required before this department can call the patient.',
                        'department_id' => $departmentId
                    ]);
                    return;
                }
            }
            $stmt->close();
        }

        if ($targetId !== null) {
            $stmt = $mysqli->prepare("UPDATE department_queue 
                SET called_at = ?, called_by = ? 
                WHERE id = ? AND department_id = ? AND is_history = 0");
            if ($stmt) {
                $stmt->bind_param('siii', $now, $userId, $targetId, $departmentId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    if ($targetId === null) {
        echo json_encode([
            'success' => false,
            'message' => 'No customers are queued for this department.',
            'department_id' => $departmentId
        ]);
        return;
    }

    $logAction = $actionType === 'reannounced' ? 'REANNOUNCE' : 'CALL';
    logDepartmentQueueEvent($mysqli, $targetId, 'CALLED', $logAction, [
        'called_by' => $userId,
        'called_at' => $now
    ]);

    echo json_encode([
        'success' => true,
        'message' => $actionType === 'reannounced'
            ? sprintf('Re-announced current customer for %s.', $departmentName)
            : sprintf('Calling next customer for %s.', $departmentName),
        'department_id' => $departmentId,
        'department_queue_id' => $targetId,
        'action' => $actionType
    ]);
}

function fetchRegistrationAssignment($mysqli, $registrationUserId)
{
    if ($registrationUserId <= 0) {
        return null;
    }

    $stmt = $mysqli->prepare("SELECT ra.user_id, ra.assigned_registration, u.username 
        FROM registration_attendant ra 
        INNER JOIN user u ON u.id = ra.user_id 
        WHERE ra.user_id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $registrationUserId);
    $stmt->execute();
    $stmt->bind_result($userId, $assignedRegistration, $username);
    if (!$stmt->fetch()) {
        $stmt->close();
        return null;
    }
    $stmt->close();

    return [
        'user_id' => (int)$userId,
        'registration_number' => $assignedRegistration,
        'username' => $username
    ];
}

function ensureActiveCallForQueue($mysqli, $queueId, $userId, $registrationNumber, &$errorMessage = null)
{
    $errorMessage = null;
    if ($queueId <= 0 || $userId <= 0) {
        $errorMessage = 'Invalid registration or queue selection.';
        return null;
    }

    if ($registrationNumber === null || $registrationNumber === '') {
        $errorMessage = 'Registration number is not assigned.';
        return null;
    }

    $stmt = $mysqli->prepare("SELECT id, registration_user_id FROM queue_calls WHERE queue_id = ? AND status = 'ACTIVE' ORDER BY called_at DESC LIMIT 1");
    if (!$stmt) {
        $errorMessage = 'Unable to verify existing calls.';
        return null;
    }
    $stmt->bind_param('i', $queueId);
    $stmt->execute();
    $stmt->bind_result($existingId, $existingUserId);
    if ($stmt->fetch()) {
        $callId = (int)$existingId;
        $assignedUser = (int)$existingUserId;
        $stmt->close();
        if ($assignedUser !== (int)$userId) {
            $errorMessage = 'Queue is currently assigned to another registration station.';
            return null;
        }
        return $callId;
    }
    $stmt->close();

    $insertStmt = $mysqli->prepare("INSERT INTO queue_calls (queue_id, registration_user_id, registration_number, status, called_at) VALUES (?, ?, ?, 'ACTIVE', ?)");
    if (!$insertStmt) {
        $errorMessage = 'Unable to create queue call entry.';
        return null;
    }
    $now = date('Y-m-d H:i:s');
    $insertStmt->bind_param('iiss', $queueId, $userId, $registrationNumber, $now);
    if (!$insertStmt->execute()) {
        $insertStmt->close();
        $errorMessage = 'Unable to create queue call entry.';
        return null;
    }
    $callId = $insertStmt->insert_id ?: $mysqli->insert_id;
    $insertStmt->close();

    return $callId > 0 ? (int)$callId : null;
}

function routeActiveCallToDepartment($mysqli, $callId, $queueId, $departmentId, $userId, $queueLabel)
{
    $result = [
        'success' => false,
        'messages' => []
    ];

    $deptStmt = $mysqli->prepare("SELECT department FROM department WHERE id = ? LIMIT 1");
    if (!$deptStmt) {
        $result['messages'][] = 'Unable to load department information.';
        return $result;
    }
    $deptStmt->bind_param('i', $departmentId);
    $deptStmt->execute();
    $deptStmt->bind_result($departmentName);
    if (!$deptStmt->fetch()) {
        $deptStmt->close();
        $result['messages'][] = 'Selected department does not exist.';
        return $result;
    }
    $deptStmt->close();

    $existingStmt = $mysqli->prepare("SELECT status FROM department_queue WHERE queue_call_id = ? AND department_id = ? AND is_history = 0 LIMIT 1");
    if ($existingStmt) {
        $existingStmt->bind_param('ii', $callId, $departmentId);
        $existingStmt->execute();
        $existingStmt->bind_result($existingStatus);
        if ($existingStmt->fetch()) {
            if (in_array($existingStatus, ['WAITING', 'CALLED'], true)) {
                $result['messages'][] = sprintf('%s is already queued for %s.', $queueLabel, $departmentName);
            } else {
                $result['messages'][] = sprintf('%s has already been processed for %s.', $queueLabel, $departmentName);
            }
            $existingStmt->close();
            return $result;
        }
        $existingStmt->close();
    }

    $insertStmt = $mysqli->prepare("INSERT INTO department_queue (queue_call_id, queue_id, department_id, status, routed_by, routed_at) VALUES (?, ?, ?, 'WAITING', ?, ?)");
    if (!$insertStmt) {
        $result['messages'][] = 'Unable to route customer to department.';
        return $result;
    }
    $now = date('Y-m-d H:i:s');
    $routeUserId = $userId > 0 ? $userId : 0;
    $insertStmt->bind_param('iiiis', $callId, $queueId, $departmentId, $routeUserId, $now);
    if ($insertStmt->execute()) {
        $result['success'] = true;
        $result['messages'][] = sprintf('%s routed to %s.', $queueLabel, $departmentName);
    } else {
        $result['messages'][] = sprintf('Unable to route %s to %s.', $queueLabel, $departmentName);
    }
    $insertStmt->close();

    return $result;
}

function markQueueCallDone($mysqli, $callId, $userId, $timestamp)
{
    $stmt = $mysqli->prepare("UPDATE queue_calls 
        SET status = 'DONE', completed_at = ? 
        WHERE id = ? AND registration_user_id = ? AND status <> 'DONE'");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('sii', $timestamp, $callId, $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        $checkStmt = $mysqli->prepare("SELECT status FROM queue_calls WHERE id = ? LIMIT 1");
        if ($checkStmt) {
            $checkStmt->bind_param('i', $callId);
            $checkStmt->execute();
            $checkStmt->bind_result($currentStatus);
            if ($checkStmt->fetch() && $currentStatus === 'DONE') {
                $checkStmt->close();
                return true;
            }
            $checkStmt->close();
        }
        return false;
    }
    return true;
}

function handleRegistrationRoutedCall($mysqli, $routeIds, $userId, $registrationNumber)
{
    $now = date('Y-m-d H:i:s');
    $called = 0;
    $messages = [];

    foreach ($routeIds as $routeId) {
        $stmt = $mysqli->prepare("SELECT rq.queue_id,
                                         rq.status,
                                         rq.registration_number,
                                         dq.category,
                                         dq.queue_number,
                                         dq.unique_code
                                  FROM registration_queue rq
                                  INNER JOIN daily_queue dq ON dq.id = rq.queue_id
                                  WHERE rq.id = ? AND rq.registration_user_id = ? LIMIT 1");
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('ii', $routeId, $userId);
        $stmt->execute();
        $stmt->bind_result($queueId, $status, $storedRegistrationNumber, $category, $queueNumber, $uniqueCode);
        if (!$stmt->fetch()) {
            $stmt->close();
            continue;
        }
        $stmt->close();

        $ticketNumber = format_ticket_number_value($category, $queueNumber);

        if ($status !== 'WAITING') {
            $messages[] = sprintf('Ticket %s is no longer waiting for a registration station.', $ticketNumber);
            continue;
        }

        $queueLabel = format_queue_reference_label($category, $queueNumber, $uniqueCode);
        $effectiveRegistrationNumber = $registrationNumber ?: $storedRegistrationNumber;
        $error = null;
        $callId = ensureActiveCallForQueue($mysqli, $queueId, $userId, $effectiveRegistrationNumber, $error);
        if (!$callId) {
            $messages[] = $error ?: sprintf('Unable to call %s.', $queueLabel);
            continue;
        }

        $updateStmt = $mysqli->prepare("UPDATE registration_queue 
            SET status = 'CALLED', called_at = ?, queue_call_id = ?, registration_number = ? 
            WHERE id = ? AND registration_user_id = ? AND status = 'WAITING'");
        if ($updateStmt) {
            $updateStmt->bind_param('sissi', $now, $callId, $effectiveRegistrationNumber, $routeId, $userId);
            if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
                $called++;
                $messages[] = sprintf('Calling %s for Registration %s.', $queueLabel, $effectiveRegistrationNumber);
            } else {
                $messages[] = sprintf('Unable to update routed entry for %s.', $queueLabel);
            }
            $updateStmt->close();
        } else {
            $messages[] = sprintf('Unable to update routed entry for %s.', $queueLabel);
        }
    }

    echo json_encode([
        'success' => $called > 0,
        'message' => $called > 0 ? implode(' ', $messages) : (isset($messages[0]) ? $messages[0] : 'No routed customers were called.'),
        'updated' => $called
    ]);
}

function handleRegistrationRoutedComplete($mysqli, $routeIds, $userId, $registrationNumber, $routeDepartmentId = 0)
{
    $now = date('Y-m-d H:i:s');
    $completed = 0;
    $messages = [];

    foreach ($routeIds as $routeId) {
        $stmt = $mysqli->prepare("SELECT rq.queue_id,
                                         rq.status,
                                         rq.queue_call_id,
                                         rq.registration_number,
                                         rq.department_id,
                                         dq.category,
                                         dq.queue_number,
                                         dq.unique_code
                                  FROM registration_queue rq
                                  INNER JOIN daily_queue dq ON dq.id = rq.queue_id
                                  WHERE rq.id = ? AND rq.registration_user_id = ? LIMIT 1");
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('ii', $routeId, $userId);
        $stmt->execute();
        $stmt->bind_result($queueId, $status, $queueCallId, $storedRegistrationNumber, $departmentId, $category, $queueNumber, $uniqueCode);
        if (!$stmt->fetch()) {
            $stmt->close();
            continue;
        }
        $stmt->close();

        $ticketNumber = format_ticket_number_value($category, $queueNumber);

        if ($status === 'DONE') {
            $messages[] = sprintf('Ticket %s already completed.', $ticketNumber);
            continue;
        }

        $queueLabel = format_queue_reference_label($category, $queueNumber, $uniqueCode);
        $effectiveRegistrationNumber = $registrationNumber ?: $storedRegistrationNumber;
        $callId = $queueCallId ? (int)$queueCallId : null;
        $error = null;

        if ($routeDepartmentId > 0) {
            if (!$callId) {
                $callId = ensureActiveCallForQueue($mysqli, $queueId, $userId, $effectiveRegistrationNumber, $error);
            }
            if (!$callId) {
                $messages[] = $error ?: sprintf('Unable to prepare %s for routing.', $queueLabel);
                continue;
            }
            $routeResult = routeActiveCallToDepartment($mysqli, $callId, $queueId, $routeDepartmentId, $userId, $queueLabel);
            $messages = array_merge($messages, $routeResult['messages']);
            if (!$routeResult['success']) {
                continue;
            }
        } else {
            if (!$callId) {
                $callId = ensureActiveCallForQueue($mysqli, $queueId, $userId, $effectiveRegistrationNumber, $error);
                if (!$callId) {
                    $messages[] = $error ?: sprintf('Unable to update %s.', $queueLabel);
                    continue;
                }
            }
            if (!markQueueCallDone($mysqli, $callId, $userId, $now)) {
                $messages[] = sprintf('Unable to mark %s done.', $queueLabel);
                continue;
            }
            $messages[] = sprintf('%s completed for Registration %s.', $queueLabel, $effectiveRegistrationNumber);
        }

        if ($callId) {
            $updateStmt = $mysqli->prepare("UPDATE registration_queue 
                SET status = 'DONE', completed_at = ?, queue_call_id = ? 
                WHERE id = ? AND registration_user_id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param('siii', $now, $callId, $routeId, $userId);
                if ($updateStmt->execute()) {
                    $completed++;
                }
                $updateStmt->close();
            } else {
                $messages[] = sprintf('Unable to update routed record for %s.', $queueLabel);
            }
        } else {
            $updateStmt = $mysqli->prepare("UPDATE registration_queue 
                SET status = 'DONE', completed_at = ? 
                WHERE id = ? AND registration_user_id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param('sii', $now, $routeId, $userId);
                if ($updateStmt->execute()) {
                    $completed++;
                }
                $updateStmt->close();
            } else {
                $messages[] = sprintf('Unable to update routed record for %s.', $queueLabel);
            }
        }
    }

    echo json_encode([
        'success' => $completed > 0,
        'message' => $completed > 0 ? implode(' ', $messages) : (isset($messages[0]) ? $messages[0] : 'No routed customers were updated.'),
        'updated' => $completed,
        'routed_department_id' => $routeDepartmentId
    ]);
}

function handleDepartmentComplete($mysqli, $departmentId, $departmentQueueId, $userId, $routeDepartmentIds = [], $routeRegistrationId = 0)
{
    if ($departmentId <= 0 || $departmentQueueId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Please select a ticket to complete.',
            'department_id' => $departmentId
        ]);
        return;
    }

    $stmt = $mysqli->prepare("SELECT dq.queue_call_id,
                                     dq.queue_id,
                                     dq.status,
                                     dq.called_at,
                                     dq.routed_at,
                                     d.department AS department_name,
                                     daily.category,
                                     daily.queue_number,
                                     daily.unique_code
                              FROM department_queue dq
                              INNER JOIN department d ON d.id = dq.department_id
                              INNER JOIN queue_calls qc ON qc.id = dq.queue_call_id
                              INNER JOIN daily_queue daily ON daily.id = qc.queue_id
                              WHERE dq.id = ? AND dq.department_id = ? AND dq.is_history = 0
                              LIMIT 1");
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => 'Unable to load department queue record.',
            'department_id' => $departmentId
        ]);
        return;
    }

    $stmt->bind_param('ii', $departmentQueueId, $departmentId);
    $stmt->execute();
    $stmt->bind_result(
        $queueCallId,
        $queueId,
        $status,
        $calledAt,
        $routedAt,
        $departmentName,
        $category,
        $queueNumber,
        $uniqueCode
    );

    if (!$stmt->fetch()) {
        $stmt->close();
        echo json_encode([
            'success' => false,
            'message' => 'The selected record could not be found.',
            'department_id' => $departmentId
        ]);
        return;
    }
    $stmt->close();

    $queueLabel = format_queue_reference_label($category, $queueNumber, $uniqueCode);

    $messages = [];
    $now = date('Y-m-d H:i:s');
    $updated = false;
    $alreadyDone = false;
    $routeDepartmentIds = array_values(array_unique(array_filter(array_map('intval', (array)$routeDepartmentIds), function ($id) {
        return $id > 0;
    })));

    if ($routeRegistrationId > 0 && !empty($routeDepartmentIds)) {
        $messages[] = 'Cannot route to a department and registration at the same time. Department routing will take priority.';
        $routeRegistrationId = 0;
    }

    if ($status === 'DONE') {
        $alreadyDone = true;
        $messages[] = sprintf('%s already marked done for %s.', $queueLabel, $departmentName);
    } else {
        $updateStmt = $mysqli->prepare("UPDATE department_queue 
            SET status = 'DONE',
                completed_at = ?,
                called_at = COALESCE(called_at, ?)
            WHERE id = ? AND department_id = ? AND status <> 'DONE' AND is_history = 0");
        if ($updateStmt) {
            $updateStmt->bind_param('ssii', $now, $now, $departmentQueueId, $departmentId);
            if ($updateStmt->execute() && $updateStmt->affected_rows > 0) {
                $updated = true;
                $messages[] = sprintf('%s marked done for %s.', $queueLabel, $departmentName);
                $completionAction = (!empty($routeDepartmentIds) || $routeRegistrationId > 0) ? 'COMPLETE_ROUTE' : 'COMPLETE_FINISH';
                $logOverrides = [
                    'completed_at' => $now
                ];
                if (empty($calledAt)) {
                    $logOverrides['called_at'] = $now;
                }
                logDepartmentQueueEvent($mysqli, $departmentQueueId, 'DONE', $completionAction, $logOverrides);
            }
            $updateStmt->close();
        }

        if (!$updated) {
            $messages[] = sprintf('No changes were made for %s in %s.', $queueLabel, $departmentName);
        }
    }

    $routeSuccess = false;
    if (!empty($routeDepartmentIds)) {
        $departmentNamesCache = [];
        foreach ($routeDepartmentIds as $routeDepartmentId) {
            if ($routeDepartmentId === $departmentId) {
                $messages[] = 'Cannot route to the same department.';
                continue;
            }

            if (!isset($departmentNamesCache[$routeDepartmentId])) {
                $targetName = null;
                $deptStmt = $mysqli->prepare("SELECT department FROM department WHERE id = ? LIMIT 1");
                if ($deptStmt) {
                    $deptStmt->bind_param('i', $routeDepartmentId);
                    $deptStmt->execute();
                    $deptStmt->bind_result($routeDepartmentName);
                    if ($deptStmt->fetch()) {
                        $targetName = $routeDepartmentName;
                    }
                    $deptStmt->close();
                }
                $departmentNamesCache[$routeDepartmentId] = $targetName;
            } else {
                $targetName = $departmentNamesCache[$routeDepartmentId];
            }

            if ($targetName === null) {
                $messages[] = sprintf('Selected destination department (ID %d) does not exist.', $routeDepartmentId);
                continue;
            }

            $existingStatus = null;
            $checkStmt = $mysqli->prepare("SELECT status FROM department_queue WHERE queue_call_id = ? AND department_id = ? AND is_history = 0 LIMIT 1");
            if ($checkStmt) {
                $checkStmt->bind_param('ii', $queueCallId, $routeDepartmentId);
                $checkStmt->execute();
                $checkStmt->bind_result($existingStatusValue);
                if ($checkStmt->fetch()) {
                    $existingStatus = $existingStatusValue;
                }
                $checkStmt->close();
            }

            if ($existingStatus !== null) {
                if (in_array($existingStatus, ['WAITING', 'CALLED'], true)) {
                    $messages[] = sprintf('%s is already queued for %s.', $queueLabel, $targetName);
                } else {
                    $messages[] = sprintf('%s has already been processed for %s.', $queueLabel, $targetName);
                }
                continue;
            }

            $insertStmt = $mysqli->prepare("INSERT INTO department_queue (queue_call_id, queue_id, department_id, status, routed_by, routed_at) VALUES (?, ?, ?, 'WAITING', ?, ?)");
            if ($insertStmt) {
                $routeUserId = $userId > 0 ? $userId : 0;
                $insertStmt->bind_param('iiiis', $queueCallId, $queueId, $routeDepartmentId, $routeUserId, $now);
                if ($insertStmt->execute()) {
                    $routeSuccess = true;
                    $messages[] = sprintf('%s routed to %s.', $queueLabel, $targetName);
                } else {
                    $messages[] = sprintf('Unable to route %s to %s.', $queueLabel, $targetName);
                }
                $insertStmt->close();
            } else {
                $messages[] = 'Unable to create routing entry. Please try again.';
            }
        }
    }

    $registrationRouteSuccess = false;
    if ($routeRegistrationId > 0) {
        $registrationDetails = fetchRegistrationAssignment($mysqli, $routeRegistrationId);
        if ($registrationDetails === null) {
            $messages[] = 'Selected registration is not available.';
        } else {
            $existingRegistrationQueueId = 0;
            $checkStmt = $mysqli->prepare("SELECT id FROM registration_queue WHERE queue_id = ? AND status IN ('WAITING', 'CALLED') LIMIT 1");
            if ($checkStmt) {
                $checkStmt->bind_param('i', $queueId);
                $checkStmt->execute();
                $checkStmt->bind_result($existingRegistrationQueueId);
                if ($checkStmt->fetch()) {
                    $messages[] = sprintf('%s is already queued for a registration station.', $queueLabel);
                }
                $checkStmt->close();
            }

            if (!isset($existingRegistrationQueueId) || !$existingRegistrationQueueId) {
                $insertStmt = $mysqli->prepare("INSERT INTO registration_queue (department_queue_id, queue_id, queue_call_id, department_id, registration_user_id, registration_number, status, routed_by, routed_at) VALUES (?, ?, ?, ?, ?, ?, 'WAITING', ?, ?)");
                if ($insertStmt) {
                    $routeUserId = $userId > 0 ? $userId : null;
                    $registrationNumber = $registrationDetails['registration_number'];
                    $routedByValue = $routeUserId !== null ? (int)$routeUserId : 0;
                    $insertStmt->bind_param('iiiiisis', $departmentQueueId, $queueId, $queueCallId, $departmentId, $registrationDetails['user_id'], $registrationNumber, $routedByValue, $now);
                    if ($insertStmt->execute()) {
                        $registrationRouteSuccess = true;
                        $messages[] = sprintf('%s routed to Registration %s.', $queueLabel, $registrationNumber);
                    } else {
                        $messages[] = sprintf('Unable to route %s to Registration %s.', $queueLabel, $registrationNumber);
                    }
                    $insertStmt->close();
                } else {
                    $messages[] = 'Unable to create registration routing entry.';
                }
            }

            $callUpdateStmt = $mysqli->prepare("UPDATE queue_calls SET status = 'DONE', completed_at = ? WHERE id = ? AND status = 'ACTIVE'");
            if ($callUpdateStmt) {
                $callUpdateStmt->bind_param('si', $now, $queueCallId);
                $callUpdateStmt->execute();
                $callUpdateStmt->close();
            }
        }
    }

    $success = $updated || $alreadyDone || $routeSuccess || $registrationRouteSuccess;

    echo json_encode([
        'success' => $success,
        'message' => implode(' ', array_filter($messages)),
        'department_id' => $departmentId,
        'department_queue_id' => $departmentQueueId,
        'route_department_ids' => $routeDepartmentIds,
        'route_registration_id' => $routeRegistrationId,
        'updated' => $updated,
        'already_done' => $alreadyDone,
        'routed' => $routeSuccess,
        'routed_registration' => $registrationRouteSuccess
    ]);
}

function logDepartmentQueueEvent($mysqli, $sourceDepartmentQueueId, $status, $action, array $overrides = [])
{
    if (!$mysqli || $sourceDepartmentQueueId <= 0) {
        return;
    }

    $allowedStatuses = ['WAITING', 'CALLED', 'DONE'];
    $statusValue = in_array($status, $allowedStatuses, true) ? $status : 'WAITING';
    $actionLabel = strtoupper(trim((string)$action));
    if ($actionLabel === '') {
        $actionLabel = null;
    }

    $calledBy = array_key_exists('called_by', $overrides) ? $overrides['called_by'] : null;
    $calledAt = array_key_exists('called_at', $overrides) ? $overrides['called_at'] : null;
    $completedAt = array_key_exists('completed_at', $overrides) ? $overrides['completed_at'] : null;

    if ($calledBy !== null) {
        $calledBy = (int)$calledBy;
    }
    if ($calledAt !== null && $calledAt !== '') {
        $calledAt = (string)$calledAt;
    } else {
        $calledAt = null;
    }
    if ($completedAt !== null && $completedAt !== '') {
        $completedAt = (string)$completedAt;
    } else {
        $completedAt = null;
    }

    $sql = "INSERT INTO department_queue (queue_call_id, queue_id, department_id, status, routed_by, routed_at, called_by, called_at, completed_at, is_history, source_department_queue_id, log_action)
            SELECT queue_call_id,
                   queue_id,
                   department_id,
                   ? AS status,
                   routed_by,
                   routed_at,
                   COALESCE(?, called_by) AS called_by,
                   COALESCE(?, called_at) AS called_at,
                   COALESCE(?, completed_at) AS completed_at,
                   1 AS is_history,
                   id AS source_department_queue_id,
                   ? AS log_action
            FROM department_queue
            WHERE id = ? AND is_history = 0
            LIMIT 1";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('sisssi', $statusValue, $calledBy, $calledAt, $completedAt, $actionLabel, $sourceDepartmentQueueId);
    $stmt->execute();
    $stmt->close();
}

function resolveUserDepartmentIds($mysqli, $userId, $role)
{
    $departmentIds = [];
    if (!$mysqli) {
        return $departmentIds;
    }

    $departmentMap = [
        'code' => [],
        'name' => []
    ];

    if ($deptResult = $mysqli->query("SELECT id, department_code, department FROM department")) {
        while ($deptRow = $deptResult->fetch_assoc()) {
            $deptId = isset($deptRow['id']) ? (int)$deptRow['id'] : 0;
            if ($deptId <= 0) {
                continue;
            }
            $code = strtoupper(trim((string)$deptRow['department_code']));
            $name = strtoupper(trim((string)$deptRow['department']));
            if ($code !== '') {
                $departmentMap['code'][$code] = $deptId;
            }
            if ($name !== '') {
                $departmentMap['name'][$name] = $deptId;
            }
        }
        $deptResult->free();
    }

    if ($userId > 0) {
        $stmt = $mysqli->prepare("SELECT department FROM user WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            if ($stmt->execute()) {
                $stmt->bind_result($userDepartmentsRaw);
                if ($stmt->fetch()) {
                    $rawValue = isset($userDepartmentsRaw) ? (string)$userDepartmentsRaw : '';
                    $tokens = preg_split('/[,\r\n;|]+/', $rawValue);
                    foreach ($tokens as $token) {
                        $normalized = strtoupper(trim((string)$token));
                        if ($normalized === '') {
                            continue;
                        }
                        if (isset($departmentMap['code'][$normalized])) {
                            $departmentIds[] = $departmentMap['code'][$normalized];
                            continue;
                        }
                        if (isset($departmentMap['name'][$normalized])) {
                            $departmentIds[] = $departmentMap['name'][$normalized];
                        }
                    }
                }
            }
            $stmt->close();
        }
    }

    if (!empty($departmentIds)) {
        return array_values(array_unique(array_filter($departmentIds, function ($id) {
            return (int)$id > 0;
        })));
    }

    $assignmentSources = [
        ['user_department', 'user_id', 'department_id'],
        ['department_user', 'user_id', 'department_id'],
        ['department_assignment', 'user_id', 'department_id'],
        ['department_staff', 'user_id', 'department_id']
    ];

    foreach ($assignmentSources as $source) {
        list($tableName, $userColumn, $departmentColumn) = $source;
        if (!tableExists($mysqli, $tableName)) {
            continue;
        }

        $sql = sprintf(
            "SELECT %s FROM %s WHERE %s = ?",
            $departmentColumn,
            $tableName,
            $userColumn
        );
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('i', $userId);
        if ($stmt->execute()) {
            $stmt->bind_result($deptId);
            while ($stmt->fetch()) {
                $departmentIds[] = (int)$deptId;
            }
        }
        $stmt->close();

        if (!empty($departmentIds)) {
            break;
        }
    }

    $departmentIds = array_values(array_unique(array_filter($departmentIds, function ($id) {
        return (int)$id > 0;
    })));

    if (!empty($departmentIds)) {
        return $departmentIds;
    }

    $normalizedRole = strtoupper(trim((string)$role));
    if ($normalizedRole === '') {
        return [];
    }

    if (isset($departmentMap['code'][$normalizedRole])) {
        $departmentIds[] = $departmentMap['code'][$normalizedRole];
    } elseif (isset($departmentMap['name'][$normalizedRole])) {
        $departmentIds[] = $departmentMap['name'][$normalizedRole];
    } else {
        $stmt = $mysqli->prepare("SELECT id FROM department WHERE UPPER(department_code) = ? OR UPPER(department) = ?");
        if ($stmt) {
            $stmt->bind_param('ss', $normalizedRole, $normalizedRole);
            if ($stmt->execute()) {
                $stmt->bind_result($deptId);
                while ($stmt->fetch()) {
                    $departmentIds[] = (int)$deptId;
                }
            }
            $stmt->close();
        }
    }

    return array_values(array_unique(array_filter($departmentIds, function ($id) {
        return (int)$id > 0;
    })));
}

function userHasDepartmentAccess($departmentId, $hasFullAccess, $allowedDepartments)
{
    if ($hasFullAccess) {
        return true;
    }
    $departmentId = (int)$departmentId;
    if ($departmentId <= 0) {
        return false;
    }
    if (empty($allowedDepartments) || !is_array($allowedDepartments)) {
        return false;
    }
    return in_array($departmentId, $allowedDepartments, true);
}
