<?php
header('Content-Type: application/json');

require_once __DIR__ . '/db2.php';

if (!isset($mysqli) || !$mysqli) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection unavailable.'
    ]);
    exit;
}

require_once __DIR__ . '/queue_table_check.php';
$tableStatus = ensureQueueTablesExist($mysqli);
if ($tableStatus !== null) {
    echo json_encode([
        'success' => false,
        'message' => $tableStatus
    ]);
    exit;
}

$entries = [];

// Finished queues (all departments DONE today).
$finishedMap = [];
$finishedSql = "
    SELECT dq.queue_id,
           SUM(CASE WHEN dq.status = 'DONE' THEN 0 ELSE 1 END) AS pending
    FROM department_queue dq
    INNER JOIN queue_calls qc ON qc.id = dq.queue_call_id
    INNER JOIN daily_queue daily ON daily.id = qc.queue_id
    WHERE DATE(daily.queue_date) = CURDATE()
      AND dq.is_history = 0
    GROUP BY dq.queue_id
    HAVING pending = 0";
if ($resFinished = $mysqli->query($finishedSql)) {
    while ($row = $resFinished->fetch_assoc()) {
        $qid = isset($row['queue_id']) ? (string)(int)$row['queue_id'] : '';
        if ($qid !== '') {
            $finishedMap[$qid] = true;
        }
    }
    $resFinished->free();
}

// Registration calls that are active and not already in a department.
$sql = "SELECT qc.id AS call_id,
               dq.id AS queue_id,
               qc.registration_number AS registration_number,
               qc.called_at,
               dq.category,
               dq.queue_number,
               dq.unique_code
        FROM queue_calls qc
        INNER JOIN daily_queue dq ON dq.id = qc.queue_id
        LEFT JOIN department_queue dq_active
            ON dq_active.queue_call_id = qc.id
           AND dq_active.status IN ('WAITING', 'CALLED')
           AND dq_active.is_history = 0
        WHERE qc.status = 'ACTIVE'
          AND dq.queue_date = CURDATE()
          AND dq_active.id IS NULL
        ORDER BY qc.registration_number ASC, qc.called_at DESC";

$result = $mysqli->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $queueId = isset($row['queue_id']) ? (int)$row['queue_id'] : 0;
        if ($queueId > 0 && isset($finishedMap[(string)$queueId])) {
            continue;
        }
        $entries[] = [
            'type' => 'registration',
            'call_id' => (int)$row['call_id'],
            'queue_id' => $queueId,
            'registration_number' => $row['registration_number'],
            'called_at' => $row['called_at'],
            'category' => $row['category'],
            'queue_number' => (int)$row['queue_number'],
            'unique_code' => $row['unique_code'],
            'display_label' => sprintf(
                'Registration %s - %s%02d (%s)',
                $row['registration_number'],
                strtoupper($row['category']),
                (int)$row['queue_number'],
                $row['unique_code']
            )
        ];
    }
    $result->free();
}

// Department calls (called state) for today.
$deptSql = "SELECT dq.id AS department_queue_id,
                   dq.department_id,
                   dq.queue_id,
                   dq.called_at,
                   d.department AS department_name,
                   qc.registration_number AS registration_number,
                   daily.category,
                   daily.queue_number,
                   daily.unique_code
            FROM department_queue dq
            INNER JOIN department d ON d.id = dq.department_id
            INNER JOIN queue_calls qc ON qc.id = dq.queue_call_id
            INNER JOIN daily_queue daily ON daily.id = qc.queue_id
            WHERE dq.status = 'CALLED'
              AND dq.is_history = 0
              AND dq.called_at IS NOT NULL
              AND daily.queue_date = CURDATE()
            ORDER BY dq.called_at DESC";

$deptResult = $mysqli->query($deptSql);
if ($deptResult) {
    while ($row = $deptResult->fetch_assoc()) {
        $queueId = isset($row['queue_id']) ? (int)$row['queue_id'] : 0;
        if ($queueId > 0 && isset($finishedMap[(string)$queueId])) {
            continue;
        }
        $entries[] = [
            'type' => 'department',
            'call_id' => (int)$row['department_queue_id'],
            'queue_id' => $queueId,
            'department_id' => (int)$row['department_id'],
            'department_name' => $row['department_name'],
            'called_at' => $row['called_at'],
            'category' => $row['category'],
            'queue_number' => (int)$row['queue_number'],
            'unique_code' => $row['unique_code'],
            'registration_number' => $row['registration_number'],
            'display_label' => sprintf(
                '%s Department - %s%02d (%s)',
                $row['department_name'],
                strtoupper($row['category']),
                (int)$row['queue_number'],
                $row['unique_code']
            )
        ];
    }
    $deptResult->free();
}

$departments = [];
$deptListSql = "SELECT id, department FROM department ORDER BY department ASC";
if ($deptListResult = $mysqli->query($deptListSql)) {
    while ($deptRow = $deptListResult->fetch_assoc()) {
        $departments[] = [
            'id' => isset($deptRow['id']) ? (int)$deptRow['id'] : 0,
            'department' => $deptRow['department']
        ];
    }
    $deptListResult->free();
}

echo json_encode([
    'success' => true,
    'entries' => $entries,
    'departments' => $departments
]);
