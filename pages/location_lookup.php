<?php
require_once __DIR__ . '/database-config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ]);
    exit;
}

function sendLocationResponse($success, $message = '', $items = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'items'   => $items,
    ]);
    exit;
}

function queryLocation(mysqli $conn, $sql)
{
    $rows = [];
    $result = $conn->query($sql);
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    return $rows;
}

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';

switch ($type) {
    case 'provinces':
        $regionId = isset($_GET['region_id']) ? (int)$_GET['region_id'] : 0;
        if ($regionId <= 0) {
            sendLocationResponse(false, 'Region is required.');
        }
        $items = queryLocation($conn, 'SELECT id, name FROM philippine_provinces WHERE region_id = ' . $regionId . ' ORDER BY name');
        sendLocationResponse(true, '', $items);
        break;

    case 'cities':
        $provinceId = isset($_GET['province_id']) ? (int)$_GET['province_id'] : 0;
        if ($provinceId <= 0) {
            sendLocationResponse(false, 'Province is required.');
        }
        $items = queryLocation($conn, 'SELECT id, name FROM philippine_cities WHERE province_id = ' . $provinceId . ' ORDER BY name');
        sendLocationResponse(true, '', $items);
        break;

    case 'barangays':
        $cityId = isset($_GET['city_id']) ? (int)$_GET['city_id'] : 0;
        if ($cityId <= 0) {
            sendLocationResponse(false, 'City is required.');
        }
        $items = queryLocation($conn, 'SELECT id, name FROM philippine_barangays WHERE city_id = ' . $cityId . ' ORDER BY name');
        sendLocationResponse(true, '', $items);
        break;

    default:
        sendLocationResponse(false, 'Unsupported lookup type.');
}
