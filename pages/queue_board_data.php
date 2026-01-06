<?php
session_start();
header('Content-Type: application/json');

$role = isset($_SESSION['sess_userrole']) ? strtoupper($_SESSION['sess_userrole']) : '';
$assignedDepartmentNames = isset($_SESSION['sess_department']) ? trim((string)$_SESSION['sess_department']) : '';
$hasDepartmentAssignments = $assignedDepartmentNames !== '';
$allowedRoles = ['REGISTRATION', 'COUNTER', 'ADMIN', 'CASHIER', 'DEPARTMENT'];
if (!in_array($role, $allowedRoles, true) && !$hasDepartmentAssignments) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied.'
    ]);
    exit;
}
$isAdmin = ($role === 'ADMIN');
$fullDepartmentRoles = ['ADMIN', 'REGISTRATION', 'COUNTER'];
$hasFullDepartmentAccess = in_array($role, $fullDepartmentRoles, true);
$requiresRegistrationAssignment = in_array($role, ['REGISTRATION', 'COUNTER'], true);

require_once __DIR__ . '/db2.php';
require_once __DIR__ . '/queue_table_check.php';

if (!isset($mysqli) || !$mysqli) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection unavailable.'
    ]);
    exit;
}

$tableStatus = ensureQueueTablesExist($mysqli);
if ($tableStatus !== null) {
    echo json_encode([
        'success' => false,
        'message' => $tableStatus
    ]);
    exit;
}

$userId = isset($_SESSION['sess_user_id']) ? (int)$_SESSION['sess_user_id'] : 0;

$registrationInfo = null;
$registrationAssignmentMissing = false;
if ($requiresRegistrationAssignment || $isAdmin) {
    $registrationInfo = fetchRegistrationInfo($mysqli, $userId);
    if ($registrationInfo === null && $requiresRegistrationAssignment && !$isAdmin) {
        $registrationAssignmentMissing = true;
    }
}

if ($registrationInfo !== null) {
    list($username, $registrationNumber) = $registrationInfo;
} else {
    $username = null;
    $registrationNumber = null;
}

$waiting = [];
$activeQueues = [
    'active' => [],
    'routed' => []
];
$registrationRouted = [];
$registrationOptions = [];

if (($requiresRegistrationAssignment && !$registrationAssignmentMissing) || $isAdmin) {
    $waiting = fetchWaitingQueue($mysqli);
    $activeQueues = fetchActiveQueue($mysqli, $userId, $isAdmin);
    $registrationRouted = fetchRegistrationRoutedQueue($mysqli, $userId, $isAdmin);
    $registrationOptions = fetchRegistrationOptions($mysqli);
}

if (empty($registrationOptions)) {
    $registrationOptions = fetchRegistrationOptions($mysqli);
}

$serviceOptions = fetchProductOptions($mysqli);

$departmentFilter = $hasFullDepartmentAccess ? null : resolveUserDepartmentIds($mysqli, $userId, $role);
$departmentQueues = fetchDepartmentQueues($mysqli, $departmentFilter);
$finishedQueues = isset($departmentQueues['finished']) ? $departmentQueues['finished'] : [];

// Filter out finished queues from registration lists so they don't reappear in registration serving.
if (!empty($finishedQueues)) {
    $finishedIds = array_column($finishedQueues, 'queue_id');
    $finishedMap = array_fill_keys(array_map('strval', $finishedIds), true);

    if (!empty($activeQueues['active'])) {
        $activeQueues['active'] = array_values(array_filter($activeQueues['active'], function ($row) use ($finishedMap) {
            $qid = isset($row['queue_id']) ? (string)$row['queue_id'] : '';
            return $qid === '' || !isset($finishedMap[$qid]);
        }));
    }
    if (!empty($activeQueues['routed'])) {
        $activeQueues['routed'] = array_values(array_filter($activeQueues['routed'], function ($row) use ($finishedMap) {
            $qid = isset($row['queue_id']) ? (string)$row['queue_id'] : '';
            return $qid === '' || !isset($finishedMap[$qid]);
        }));
    }
    if (!empty($registrationRouted)) {
        $registrationRouted = array_values(array_filter($registrationRouted, function ($row) use ($finishedMap) {
            $qid = isset($row['queue_id']) ? (string)$row['queue_id'] : '';
            return $qid === '' || !isset($finishedMap[$qid]);
        }));
    }
    if (!empty($waiting)) {
        $waiting = array_values(array_filter($waiting, function ($row) use ($finishedMap) {
            $qid = isset($row['queue_id']) ? (string)$row['queue_id'] : '';
            if ($qid === '' && isset($row['id'])) {
                $qid = (string)$row['id'];
            }
            return $qid === '' || !isset($finishedMap[$qid]);
        }));
    }
}

echo json_encode([
    'success' => true,
    'registration' => $registrationInfo !== null ? [
        'username' => $username,
        'number' => $registrationNumber
    ] : null,
    'waiting' => $waiting,
    'active' => isset($activeQueues['active']) ? $activeQueues['active'] : [],
    'routed_active' => isset($activeQueues['routed']) ? $activeQueues['routed'] : [],
    'departments' => $departmentQueues['list'],
    'department_options' => $departmentQueues['options'],
    'cashier_department_id' => isset($departmentQueues['cashier_department_id']) ? $departmentQueues['cashier_department_id'] : null,
    'finished' => $finishedQueues,
    'registration_routed' => $registrationRouted,
    'registration_options' => $registrationOptions,
    'service_options' => $serviceOptions,
    'message' => $registrationAssignmentMissing ? 'Registration assignment not found. Department queues only.' : '',
    'message_type' => $registrationAssignmentMissing ? 'warning' : ''
]);

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

function describe_queue_category($category)
{
    static $map = [
        'P' => 'Priority',
        'R' => 'Regular'
    ];
    $normalized = strtoupper(trim((string)$category));
    return isset($map[$normalized]) ? $map[$normalized] : $normalized;
}

function fetchWaitingQueue($mysqli)
{
    $results = [];
    $sql = "SELECT dq.id, dq.category, dq.queue_number, dq.unique_code, dq.created_at, dq.retrieval_code
            FROM daily_queue dq
            LEFT JOIN queue_calls qc ON qc.queue_id = dq.id AND qc.status = 'ACTIVE'
            LEFT JOIN registration_queue rq ON rq.queue_id = dq.id AND rq.status = 'WAITING'
            WHERE dq.queue_date = CURDATE()
              AND qc.id IS NULL
              AND rq.id IS NULL
            ORDER BY dq.created_at ASC";

    if ($query = $mysqli->query($sql)) {
        while ($row = $query->fetch_assoc()) {
            $ticketNumber = format_ticket_number_value($row['category'], $row['queue_number']);
            $referenceLabel = format_queue_reference_label($row['category'], $row['queue_number'], $row['unique_code']);
            $categoryLabel = describe_queue_category($row['category']);
            $displayParts = array_filter([
                $ticketNumber,
                $categoryLabel !== '' ? $categoryLabel : null,
                $row['unique_code'] ? $row['unique_code'] : null
            ]);
            $results[] = [
                'id' => (int)$row['id'],
                'category' => $row['category'],
                'category_label' => $categoryLabel,
                'queue_number' => (int)$row['queue_number'],
                'ticket_number' => $ticketNumber,
                'unique_code' => $row['unique_code'],
                'reference' => $referenceLabel,
                'label' => implode(' - ', $displayParts),
                'retrieval_code' => isset($row['retrieval_code']) ? $row['retrieval_code'] : ''
            ];
        }
        $query->free();
    }

    return $results;
}

function fetchDepartmentQueues($mysqli, $restrictedDepartmentIds = null)
{
    $departments = [];
    $options = [];
    $cashierDepartmentId = null;
    $cashierTokens = ['CASH', 'CASHIER'];

    $visibleDepartmentIds = null;
    if ($restrictedDepartmentIds !== null) {
        $visibleDepartmentIds = array_map('intval', (array)$restrictedDepartmentIds);
        $visibleDepartmentIds = array_values(array_unique(array_filter($visibleDepartmentIds, function ($id) {
            return $id > 0;
        })));
    }

    $deptQuery = $mysqli->query("SELECT id, department_code, department FROM department ORDER BY department ASC");
    if ($deptQuery) {
        while ($row = $deptQuery->fetch_assoc()) {
            $deptId = (int)$row['id'];
            $deptCode = isset($row['department_code']) ? strtoupper(trim((string)$row['department_code'])) : '';
            $deptName = isset($row['department']) ? strtoupper(trim((string)$row['department'])) : '';
            $options[] = [
                'id' => $deptId,
                'label' => $row['department']
            ];

            if ($cashierDepartmentId === null && ($deptCode !== '' || $deptName !== '')) {
                foreach ($cashierTokens as $token) {
                    if ($deptCode === $token || $deptName === $token) {
                        $cashierDepartmentId = $deptId;
                        break;
                    }
                }
            }

            if ($visibleDepartmentIds !== null && !in_array($deptId, $visibleDepartmentIds, true)) {
                continue;
            }

            $departments[$deptId] = [
                'id' => $deptId,
                'code' => $row['department_code'],
                'name' => $row['department'],
                'waiting' => [],
                'active' => [],
                'tickets' => [],
                'current' => null,
                'waiting_count' => 0,
                'route_options' => []
            ];
        }
        $deptQuery->free();
    }

    if (empty($departments)) {
        return [
            'list' => [],
            'options' => $options,
            'cashier_department_id' => $cashierDepartmentId !== null ? (int)$cashierDepartmentId : null
        ];
    }

    $placeholders = implode(',', array_fill(0, count($departments), '?'));
    $types = str_repeat('i', count($departments));

    $sql = "SELECT dq.id AS record_id,
                   dq.department_id,
                   dq.queue_call_id,
                   dq.queue_id,
                   dq.status,
                   dq.routed_at,
                   dq.called_at,
                   qc.registration_number AS registration_number,
                   qc.called_at AS registration_called_at,
                   daily.category,
                   daily.queue_number,
                   daily.unique_code,
                   daily.retrieval_code,
                   daily.payment_status
            FROM department_queue dq
            INNER JOIN queue_calls qc ON qc.id = dq.queue_call_id
            INNER JOIN daily_queue daily ON daily.id = qc.queue_id
            WHERE dq.department_id IN ($placeholders)
              AND daily.queue_date = CURDATE()
              AND dq.status IN ('WAITING', 'CALLED')
              AND dq.is_history = 0
            ORDER BY dq.routed_at ASC";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $deptIds = array_keys($departments);
        $stmt->bind_param($types, ...$deptIds);
        $stmt->execute();
        $stmt->bind_result(
            $recordId,
            $departmentId,
            $queueCallId,
            $queueId,
            $status,
            $routedAt,
            $calledAt,
            $registrationNumber,
            $registrationCalledAt,
            $category,
            $queueNumber,
            $uniqueCode,
            $retrievalCode,
            $paymentStatus
        );

        while ($stmt->fetch()) {
            if (!isset($departments[$departmentId])) {
                continue;
            }

            $ticketNumber = format_ticket_number_value($category, $queueNumber);
            $referenceLabel = format_queue_reference_label($category, $queueNumber, $uniqueCode);

            $entry = [
                'record_id' => (int)$recordId,
                'queue_call_id' => (int)$queueCallId,
                'queue_id' => (int)$queueId,
                'status' => $status,
                'routed_at' => $routedAt,
                'called_at' => $calledAt,
                'registration_number' => $registrationNumber,
                'queue_label' => $referenceLabel,
                'queue_number' => (int)$queueNumber,
                'ticket_number' => $ticketNumber,
                'reference' => $referenceLabel,
                'unique_code' => $uniqueCode,
                'retrieval_code' => $retrievalCode,
                'category' => $category,
                'category_label' => describe_queue_category($category),
                'registration_called_at' => $registrationCalledAt,
                'payment_status' => is_numeric($paymentStatus) ? (int)$paymentStatus : 0
            ];

            if ($status === 'CALLED') {
                $departments[$departmentId]['active'][] = $entry;
            } else {
                $departments[$departmentId]['waiting'][] = $entry;
                $departments[$departmentId]['waiting_count']++;
            }
        }
        $stmt->close();
    }

    foreach ($departments as $deptId => &$department) {
        if (!empty($department['active'])) {
            usort($department['active'], function ($a, $b) {
                $aCalled = isset($a['called_at']) ? $a['called_at'] : '';
                $bCalled = isset($b['called_at']) ? $b['called_at'] : '';
                if ($aCalled === $bCalled) {
                    $aRoute = isset($a['routed_at']) ? $a['routed_at'] : '';
                    $bRoute = isset($b['routed_at']) ? $b['routed_at'] : '';
                    return strcmp($bRoute, $aRoute);
                }
                return strcmp($bCalled, $aCalled);
            });
        }
        if (!empty($department['waiting'])) {
            usort($department['waiting'], function ($a, $b) {
                $aRoute = isset($a['routed_at']) ? $a['routed_at'] : '';
                $bRoute = isset($b['routed_at']) ? $b['routed_at'] : '';
                return strcmp($aRoute, $bRoute);
            });
        }
        $department['current'] = !empty($department['active']) ? $department['active'][0] : null;
        $department['tickets'] = array_merge($department['active'], $department['waiting']);
        $department['route_options'] = array_values(array_filter($options, function ($option) use ($deptId) {
            return isset($option['id']) && (int)$option['id'] !== (int)$deptId;
        }));
    }
    unset($department);

    // Finished queues: all department entries for the queue are DONE (no pending departments).
    $finished = [];
    $finishedSql = "
        SELECT dq.queue_id,
               MIN(daily.category) AS category,
               MIN(daily.queue_number) AS queue_number,
               MIN(daily.unique_code) AS unique_code,
               MIN(daily.retrieval_code) AS retrieval_code,
               MAX(dq.completed_at) AS completed_at,
               SUM(CASE WHEN dq.status = 'DONE' THEN 0 ELSE 1 END) AS pending_count
        FROM department_queue dq
        INNER JOIN queue_calls qc ON qc.id = dq.queue_call_id
        INNER JOIN daily_queue daily ON daily.id = qc.queue_id
        WHERE DATE(daily.queue_date) = CURDATE()
          AND dq.is_history = 0
        GROUP BY dq.queue_id
        HAVING pending_count = 0
        ORDER BY completed_at DESC";
    if ($doneResult = $mysqli->query($finishedSql)) {
        while ($row = $doneResult->fetch_assoc()) {
            $category = isset($row['category']) ? $row['category'] : '';
            $queueNumber = isset($row['queue_number']) ? (int)$row['queue_number'] : 0;
            $uniqueCode = isset($row['unique_code']) ? $row['unique_code'] : '';
            $finished[] = [
                'queue_id' => isset($row['queue_id']) ? (int)$row['queue_id'] : 0,
                'ticket_number' => format_ticket_number_value($category, $queueNumber),
                'reference' => format_queue_reference_label($category, $queueNumber, $uniqueCode),
                'retrieval_code' => isset($row['retrieval_code']) ? $row['retrieval_code'] : '',
                'completed_at' => isset($row['completed_at']) ? $row['completed_at'] : null
            ];
        }
        $doneResult->free();
    }

    return [
        'list' => array_values($departments),
        'options' => $options,
        'cashier_department_id' => $cashierDepartmentId !== null ? (int)$cashierDepartmentId : null,
        'finished' => $finished
    ];
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

function fetchRegistrationRoutedQueue($mysqli, $userId, $showAll = false)
{
    $results = [];
    $baseSql = "SELECT rq.id,
                       rq.queue_id,
                       rq.queue_call_id,
                       rq.department_id,
                       rq.registration_user_id,
                       rq.registration_number,
                       rq.status,
                       rq.routed_at,
                       rq.called_at,
                       department.department AS department_name,
                       daily.category,
                       daily.queue_number,
                       daily.unique_code,
                       daily.retrieval_code
                FROM registration_queue rq
                INNER JOIN daily_queue daily ON daily.id = rq.queue_id
                LEFT JOIN department ON department.id = rq.department_id
                WHERE rq.status = 'WAITING'
                  AND daily.queue_date = CURDATE()";

    if ($showAll) {
        $sql = $baseSql . " ORDER BY rq.routed_at ASC";
        $stmt = $mysqli->prepare($sql);
    } else {
        $sql = $baseSql . " AND rq.registration_user_id = ? ORDER BY rq.routed_at ASC";
        $stmt = $mysqli->prepare($sql);
        if ($stmt === false) {
            return $results;
        }
        $stmt->bind_param('i', $userId);
    }

    if ($stmt === false) {
        return $results;
    }

    $stmt->execute();
    $stmt->bind_result(
        $id,
        $queueId,
        $queueCallId,
        $departmentId,
        $registrationUserId,
        $registrationNumber,
        $status,
        $routedAt,
        $calledAt,
        $departmentName,
        $category,
        $queueNumber,
        $uniqueCode,
        $retrievalCode
    );

    while ($stmt->fetch()) {
        $ticketNumber = format_ticket_number_value($category, $queueNumber);
        $referenceLabel = format_queue_reference_label($category, $queueNumber, $uniqueCode);
        $labelParts = [];
        if ($departmentName) {
            $labelParts[] = $departmentName;
        }
        $labelParts[] = $referenceLabel;

        $results[] = [
            'id' => (int)$id,
            'queue_id' => (int)$queueId,
            'queue_call_id' => $queueCallId !== null ? (int)$queueCallId : null,
            'department_id' => (int)$departmentId,
            'registration_user_id' => (int)$registrationUserId,
            'registration_number' => $registrationNumber,
            'status' => $status,
            'routed_at' => $routedAt,
            'called_at' => $calledAt,
            'label' => implode(' - ', $labelParts),
            'ticket_number' => $ticketNumber,
            'reference' => $referenceLabel,
            'unique_code' => $uniqueCode,
            'retrieval_code' => $retrievalCode,
            'category' => $category,
            'category_label' => describe_queue_category($category)
        ];
    }
    $stmt->close();

    return $results;
}

function fetchRegistrationOptions($mysqli)
{
    $options = [];
    $sql = "SELECT ra.user_id,
                   ra.assigned_registration,
                   u.firstname,
                   u.lastname,
                   u.username
            FROM registration_attendant ra
            INNER JOIN user u ON u.id = ra.user_id
            ORDER BY ra.assigned_registration ASC";

    if ($result = $mysqli->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $label = 'Registration ' . $row['assigned_registration'];
            $nameParts = [];
            if (!empty($row['firstname'])) {
                $nameParts[] = $row['firstname'];
            }
            if (!empty($row['lastname'])) {
                $nameParts[] = $row['lastname'];
            }
            if (!empty($nameParts)) {
                $label .= ' - ' . implode(' ', $nameParts);
            } elseif (!empty($row['username'])) {
                $label .= ' - ' . $row['username'];
            }

            $options[] = [
                'id' => (int)$row['user_id'],
                'number' => $row['assigned_registration'],
                'label' => $label
            ];
        }
        $result->free();
    }

    return $options;
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

function fetchProductOptions($mysqli)
{
    $options = [];
    if (!$mysqli || !tableExists($mysqli, 'product_register')) {
        return $options;
    }

    $columns = describeTableColumns($mysqli, 'product_register');
    if ($columns === null) {
        return $options;
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
        return $options;
    }

    $orderParts = [];
    if (isset($selectColumns['item_code'])) {
        $orderParts[] = '`' . $selectColumns['item_code'] . '` ASC';
    }
    if (isset($selectColumns['item_description'])) {
        $orderParts[] = '`' . $selectColumns['item_description'] . '` ASC';
    }
    if (empty($orderParts)) {
        $orderParts[] = '`id` ASC';
    }

    $sql = 'SELECT ' . implode(', ', array_map(function ($col) {
        return '`' . $col . '`';
    }, $selectColumns)) . ' FROM product_register ORDER BY ' . implode(', ', $orderParts);

    $departmentMap = buildDepartmentMap($mysqli);

    if ($result = $mysqli->query($sql)) {
        while ($row = $result->fetch_assoc()) {
            $productId = isset($row[$selectColumns['id']]) ? (int)$row[$selectColumns['id']] : 0;
            if ($productId <= 0) {
                continue;
            }
            $itemCode = (isset($selectColumns['item_code']) && isset($row[$selectColumns['item_code']])) ? (string)$row[$selectColumns['item_code']] : '';
            $description = (isset($selectColumns['item_description']) && isset($row[$selectColumns['item_description']])) ? (string)$row[$selectColumns['item_description']] : '';
            $amountRaw = (isset($selectColumns['amount']) && isset($row[$selectColumns['amount']])) ? $row[$selectColumns['amount']] : null;
            $amount = is_numeric($amountRaw) ? (float)$amountRaw : null;

            $deptId = null;
            $deptCodeRaw = (isset($selectColumns['department_code']) && isset($row[$selectColumns['department_code']])) ? $row[$selectColumns['department_code']] : '';
            $deptCode = strtoupper(trim((string)$deptCodeRaw));
            $deptValueRaw = (isset($selectColumns['department']) && isset($row[$selectColumns['department']])) ? $row[$selectColumns['department']] : '';
            $deptValue = strtoupper(trim((string)$deptValueRaw));
            $itemCodePadded = $itemCode !== '' ? str_pad((string)$itemCode, 5, '0', STR_PAD_LEFT) : '';

            if (is_numeric($deptValueRaw) && isset($departmentMap['id'][(int)$deptValueRaw])) {
                $deptId = (int)$deptValueRaw;
            } elseif ($deptValue !== '' && isset($departmentMap['name'][$deptValue])) {
                $deptId = $departmentMap['name'][$deptValue];
            } elseif ($deptCode !== '' && isset($departmentMap['code'][$deptCode])) {
                $deptId = $departmentMap['code'][$deptCode];
                $deptValue = $deptCode;
            }

            $deptName = ($deptId !== null && isset($departmentMap['id'][$deptId]['name'])) ? $departmentMap['id'][$deptId]['name'] : '';
            $amountLabel = $amount !== null ? number_format($amount, 2) : null;

            $description = trim((string)$description);
            $codeDisplay = $itemCodePadded !== '' ? $itemCodePadded : ($itemCode !== '' ? (string)$itemCode : '');
            $label = $codeDisplay !== '' ? $codeDisplay : 'Product #' . $productId;
            if ($description !== '') {
                $label .= ' - ' . $description;
            }
            if ($amountLabel !== null) {
                $label .= ' : ' . $amountLabel;
            }

            $options[] = [
                'id' => $productId,
                'item_code' => $itemCode,
                'item_code_padded' => $itemCodePadded,
                'description' => $description,
                'amount' => $amount,
                'amount_label' => $amountLabel,
                'department_id' => $deptId !== null ? (int)$deptId : null,
                'department_code' => $deptCode !== '' ? $deptCode : ($deptValue !== '' ? $deptValue : null),
                'department_name' => $deptName,
                'label' => $label
            ];
        }
        $result->free();
    }

    if (!empty($options)) {
        // Remove duplicate products and enforce a stable item_code ordering for the dropdown.
        $seenCodes = [];
        $unique = [];

        foreach ($options as $option) {
            $rawCode = isset($option['item_code']) ? trim((string)$option['item_code']) : '';
            $normalizedCode = $rawCode !== '' ? strtoupper($rawCode) : '';
            $dedupeKey = $normalizedCode !== '' ? $normalizedCode : 'ID-' . (isset($option['id']) ? (int)$option['id'] : 0);

            if (isset($seenCodes[$dedupeKey])) {
                continue;
            }
            $seenCodes[$dedupeKey] = true;
            $unique[] = $option;
        }

        usort($unique, function ($a, $b) {
            $codeA = '';
            $codeB = '';

            if (isset($a['item_code_padded']) && $a['item_code_padded'] !== '') {
                $codeA = $a['item_code_padded'];
            } elseif (isset($a['item_code'])) {
                $codeA = trim((string)$a['item_code']);
            }

            if (isset($b['item_code_padded']) && $b['item_code_padded'] !== '') {
                $codeB = $b['item_code_padded'];
            } elseif (isset($b['item_code'])) {
                $codeB = trim((string)$b['item_code']);
            }

            $cmp = strnatcasecmp($codeA, $codeB);
            if ($cmp !== 0) {
                return $cmp;
            }

            $descA = isset($a['description']) ? trim((string)$a['description']) : '';
            $descB = isset($b['description']) ? trim((string)$b['description']) : '';
            $cmpDesc = strnatcasecmp($descA, $descB);
            if ($cmpDesc !== 0) {
                return $cmpDesc;
            }

            $idA = isset($a['id']) ? (int)$a['id'] : 0;
            $idB = isset($b['id']) ? (int)$b['id'] : 0;
            if ($idA === $idB) {
                return 0;
            }
            return ($idA < $idB) ? -1 : 1;
        });

        $options = $unique;
    }

    return $options;
}

function fetchActiveQueue($mysqli, $userId, $showAll = false)
{
    $results = [
        'active' => [],
        'routed' => []
    ];
    $sql = "SELECT qc.id AS call_id, qc.queue_id, qc.registration_number AS registration_number, qc.called_at,
                   dq.category, dq.queue_number, dq.unique_code, dq.retrieval_code
            FROM queue_calls qc
            INNER JOIN daily_queue dq ON dq.id = qc.queue_id
            WHERE %s
              AND dq.queue_date = CURDATE()
              AND qc.status = 'ACTIVE'
            ORDER BY qc.called_at ASC";

    if ($showAll) {
        $query = sprintf($sql, '1=1');
        $stmt = $mysqli->prepare($query);
    } else {
        $query = sprintf($sql, 'qc.registration_user_id = ?');
        $stmt = $mysqli->prepare($query);
        if ($stmt === false) {
            return $results;
        }
        $stmt->bind_param('i', $userId);
    }

    if ($stmt === false) {
        return $results;
    }

    $stmt->execute();
    $stmt->bind_result($callId, $queueId, $registrationNumber, $calledAt, $category, $queueNumber, $uniqueCode, $retrievalCode);

    $callEntries = [];
    $callOrder = [];

    while ($stmt->fetch()) {
        $callIdInt = (int)$callId;
        $callOrder[] = $callIdInt;
        $ticketNumber = format_ticket_number_value($category, $queueNumber);
        $referenceLabel = format_queue_reference_label($category, $queueNumber, $uniqueCode);
        $callEntries[$callIdInt] = [
            'call_id' => $callIdInt,
            'queue_id' => (int)$queueId,
            'category' => $category,
            'queue_number' => (int)$queueNumber,
             'ticket_number' => $ticketNumber,
            'reference' => $referenceLabel,
            'unique_code' => $uniqueCode,
            'retrieval_code' => $retrievalCode,
            'called_at' => $calledAt,
            'registration_number' => $registrationNumber,
            'label' => sprintf(
                'Registration %s - %s',
                $registrationNumber,
                $ticketNumber
            ),
            'category_label' => describe_queue_category($category)
        ];
    }
    $stmt->close();

    if (empty($callEntries)) {
        return $results;
    }

    $callIds = array_keys($callEntries);
    $routesByCall = [];
    $idList = implode(',', array_map('intval', $callIds));

    if ($idList !== '') {
        $routeSql = "SELECT dq.queue_call_id, dq.department_id, dq.status, dq.routed_at, dq.called_at,
                            dept.department AS department_name, dept.department_code
                     FROM department_queue dq
                     INNER JOIN department dept ON dept.id = dq.department_id
                     WHERE dq.queue_call_id IN ($idList)
                       AND dq.status IN ('WAITING', 'CALLED')
                       AND dq.is_history = 0
                     ORDER BY dq.routed_at ASC, dq.id ASC";

        if ($routeResult = $mysqli->query($routeSql)) {
            while ($routeRow = $routeResult->fetch_assoc()) {
                $callKey = isset($routeRow['queue_call_id']) ? (int)$routeRow['queue_call_id'] : 0;
                if ($callKey <= 0 || !isset($callEntries[$callKey])) {
                    continue;
                }
                if (!isset($routesByCall[$callKey])) {
                    $routesByCall[$callKey] = [];
                }
                $routesByCall[$callKey][] = [
                    'department_id' => isset($routeRow['department_id']) ? (int)$routeRow['department_id'] : 0,
                    'department_name' => $routeRow['department_name'],
                    'department_code' => $routeRow['department_code'],
                    'status' => $routeRow['status'],
                    'routed_at' => $routeRow['routed_at'],
                    'called_at' => $routeRow['called_at']
                ];
            }
            $routeResult->free();
        }
    }

    foreach ($callOrder as $callIdValue) {
        $entry = $callEntries[$callIdValue];
        $routes = isset($routesByCall[$callIdValue]) ? $routesByCall[$callIdValue] : [];
        if (!empty($routes)) {
            $entry['routes'] = $routes;
            $results['routed'][] = $entry;
        } else {
            $results['active'][] = $entry;
        }
    }

    return $results;
}
