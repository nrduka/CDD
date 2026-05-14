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

$today = date('Y-m-d');
$startInput = isset($_GET['start']) ? trim((string)$_GET['start']) : '';
$endInput = isset($_GET['end']) ? trim((string)$_GET['end']) : '';
$reportTypeInput = isset($_GET['report_type']) ? trim((string)$_GET['report_type']) : '';
$reportType = in_array($reportTypeInput, ['detailed', 'by_product_machine'], true) ? $reportTypeInput : 'detailed';

$startDate = $startInput !== '' ? $startInput : $today;
$endDate = $endInput !== '' ? $endInput : $today;

$startObject = DateTime::createFromFormat('Y-m-d', $startDate);
if (!$startObject || $startObject->format('Y-m-d') !== $startDate) {
    $startDate = $today;
    $startObject = DateTime::createFromFormat('Y-m-d', $startDate);
}

$endObject = DateTime::createFromFormat('Y-m-d', $endDate);
if (!$endObject || $endObject->format('Y-m-d') !== $endDate) {
    $endDate = $today;
    $endObject = DateTime::createFromFormat('Y-m-d', $endDate);
}

if ($startDate > $endDate) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
    $startObject = DateTime::createFromFormat('Y-m-d', $startDate);
    $endObject = DateTime::createFromFormat('Y-m-d', $endDate);
}

function resolveProductionRangeDateTimeLabel($datetimeValue, $dateValue = '', $timeValue = '')
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

function calculateToDateProductionHours($startedValue, $endedValue)
{
    $startedValue = trim((string)$startedValue);
    $endedValue = trim((string)$endedValue);
    if ($startedValue === '' || $endedValue === '') {
        return 0.0;
    }

    $startedAt = strtotime($startedValue);
    $endedAt = strtotime($endedValue);
    if ($startedAt === false || $endedAt === false || $endedAt < $startedAt) {
        return 0.0;
    }

    return ($endedAt - $startedAt) / 3600;
}

function formatToDateProductionMachineLabel($machineDescription)
{
    $label = strtoupper(trim((string)$machineDescription));
    $label = preg_replace('/\s*\([^)]*\)\s*$/', '', $label);
    $label = preg_replace('/\s+MACHINE\b/', '', $label);
    $label = preg_replace('/\s+/', ' ', $label);

    return trim($label);
}

function fetchToDateProductionRows($mysqli, $startDate, $endDate)
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
                   im.machine_description,
                   im.product_type AS machine_product_type
            FROM ice_production ip
            LEFT JOIN ice_machine im ON im.id = ip.machine
            WHERE ip.date_production BETWEEN ? AND ?
            ORDER BY COALESCE(ip.datetime_started, ip.date_production), COALESCE(ip.time_dropped, '00:00:00'), im.machine_description, ip.id";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ss', $startDate, $endDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $machineId = isset($row['machine']) ? (string)$row['machine'] : '';
                $machineDescription = trim((string)(isset($row['machine_description']) ? $row['machine_description'] : ''));
                if ($machineDescription === '') {
                    $machineDescription = $machineId !== '' ? 'Machine ' . $machineId : 'Unknown Machine';
                }

                $productType = trim((string)(isset($row['machine_product_type']) ? $row['machine_product_type'] : ''));
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
                    'datetime_started' => resolveProductionRangeDateTimeLabel(isset($row['datetime_started']) ? $row['datetime_started'] : '', isset($row['date_production']) ? $row['date_production'] : '', isset($row['time_dropped']) ? $row['time_dropped'] : ''),
                    'datetime_ended' => resolveProductionRangeDateTimeLabel(isset($row['datetime_ended']) ? $row['datetime_ended'] : ''),
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

function fetchToDateProductionMachineColumns($mysqli, $rows)
{
    $columns = [];
    $seen = [];
    $appendColumn = function ($machineId, $machineDescription, $productType) use (&$columns, &$seen) {
        $machineId = trim((string)$machineId);
        $machineDescription = trim((string)$machineDescription);
        $productType = strtoupper(trim((string)$productType));
        if ($productType === '') {
            $productType = 'UNASSIGNED';
        }
        if ($machineDescription === '') {
            $machineDescription = $machineId !== '' ? 'Machine ' . $machineId : 'Unknown Machine';
        }

        $key = $machineId . '|' . $machineDescription;
        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $columns[] = [
            'key' => $key,
            'machine_id' => $machineId,
            'product_type' => $productType,
            'machine_description' => $machineDescription,
            'machine_label' => formatToDateProductionMachineLabel($machineDescription)
        ];
    };

    if ($mysqli) {
        $result = $mysqli->query("SELECT id, machine_description, product_type FROM ice_machine ORDER BY product_type, machine_description");
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $appendColumn(
                    isset($row['id']) ? $row['id'] : '',
                    isset($row['machine_description']) ? $row['machine_description'] : '',
                    isset($row['product_type']) ? $row['product_type'] : ''
                );
            }
            $result->free();
        }
    }

    foreach ($rows as $row) {
        $appendColumn(
            isset($row['machine_id']) ? $row['machine_id'] : '',
            isset($row['machine_description']) ? $row['machine_description'] : '',
            isset($row['product_type']) ? $row['product_type'] : ''
        );
    }

    usort($columns, function ($left, $right) {
        $typeCompare = strcasecmp((string)$left['product_type'], (string)$right['product_type']);
        if ($typeCompare !== 0) {
            return $typeCompare;
        }

        return strcasecmp((string)$left['machine_label'], (string)$right['machine_label']);
    });

    return $columns;
}

function buildToDateProductionMachineMatrix($rows, $startDate, $endDate, $machineColumns)
{
    $machineColumnMap = [];
    foreach ($machineColumns as $column) {
        $key = isset($column['key']) ? (string)$column['key'] : '';
        if ($key !== '') {
            $machineColumnMap[$key] = true;
        }
    }

    $matrixRows = [];
    $startObject = DateTime::createFromFormat('Y-m-d', $startDate);
    $endObject = DateTime::createFromFormat('Y-m-d', $endDate);
    if ($startObject && $endObject) {
        $currentObject = clone $startObject;
        while ($currentObject <= $endObject) {
            $dateKey = $currentObject->format('Y-m-d');
            $matrixRows[$dateKey] = [
                'date' => $dateKey,
                'date_label' => $currentObject->format('d-M-y'),
                'machines' => []
            ];
            foreach ($machineColumns as $column) {
                $matrixRows[$dateKey]['machines'][$column['key']] = [
                    'hours' => 0.0,
                    'qty' => 0.0
                ];
            }
            $currentObject->modify('+1 day');
        }
    }

    foreach ($rows as $row) {
        $dateKey = isset($row['date_production']) ? (string)$row['date_production'] : '';
        if (!isset($matrixRows[$dateKey])) {
            continue;
        }

        $machineKey = trim((string)(isset($row['machine_id']) ? $row['machine_id'] : '')) . '|' . trim((string)(isset($row['machine_description']) ? $row['machine_description'] : ''));
        if ($machineKey === '|' || !isset($machineColumnMap[$machineKey])) {
            continue;
        }

        $finishedQty = isset($row['finished_ice_qty']) ? (float)$row['finished_ice_qty'] : 0.0;
        $looseQty = isset($row['loose_ice_qty']) ? (float)$row['loose_ice_qty'] : 0.0;
        $matrixRows[$dateKey]['machines'][$machineKey]['hours'] += calculateToDateProductionHours(
            isset($row['datetime_started']) ? $row['datetime_started'] : '',
            isset($row['datetime_ended']) ? $row['datetime_ended'] : ''
        );
        $matrixRows[$dateKey]['machines'][$machineKey]['qty'] += $finishedQty + $looseQty;
    }

    return array_values($matrixRows);
}

function buildToDateProductionProductGroups($machineColumns)
{
    $groups = [];
    foreach ($machineColumns as $column) {
        $productType = isset($column['product_type']) ? (string)$column['product_type'] : 'UNASSIGNED';
        if (!isset($groups[$productType])) {
            $groups[$productType] = [
                'product_type' => $productType,
                'colspan' => 0,
                'machine_columns' => []
            ];
        }
        $groups[$productType]['machine_columns'][] = $column;
        $groups[$productType]['colspan'] += 2;
    }

    foreach ($groups as &$group) {
        $group['colspan'] += 1;
    }
    unset($group);

    return array_values($groups);
}

function formatToDateProductionReportHeading($startDate, $endDate)
{
    if ($startDate === $endDate) {
        return strtoupper(date('F d, Y', strtotime($startDate)));
    }

    return strtoupper(date('F d, Y', strtotime($startDate)) . ' TO ' . date('F d, Y', strtotime($endDate)));
}

function formatToDateProductionMatrixNumber($value)
{
    $value = (float)$value;
    if (abs($value) < 0.005) {
        return '';
    }

    return number_format($value, 2);
}

$generatedAt = date('Y-m-d H:i:s');
$pageError = null;
$registerRows = [];
$machineColumns = [];
$machineProductGroups = [];
$machineMatrixRows = [];
$totalFinishedIceQty = 0.0;
$totalLooseIceQty = 0.0;
$entryCount = 0;

if (!isset($mysqli) || !$mysqli) {
    $pageError = 'Database connection unavailable.';
} else {
    $registerRows = fetchToDateProductionRows($mysqli, $startDate, $endDate);
    foreach ($registerRows as $row) {
        $totalFinishedIceQty += isset($row['finished_ice_qty']) ? (float)$row['finished_ice_qty'] : 0.0;
        $totalLooseIceQty += isset($row['loose_ice_qty']) ? (float)$row['loose_ice_qty'] : 0.0;
    }
    $entryCount = count($registerRows);

    if ($reportType === 'by_product_machine') {
        $machineColumns = fetchToDateProductionMachineColumns($mysqli, $registerRows);
        $machineProductGroups = buildToDateProductionProductGroups($machineColumns);
        $machineMatrixRows = buildToDateProductionMachineMatrix($registerRows, $startDate, $endDate, $machineColumns);
    }
}

$dayCount = $startObject && $endObject ? $startObject->diff($endObject)->days + 1 : 1;
$averageDailyEntries = $dayCount > 0 ? ($entryCount / $dayCount) : 0;
$reportRangeLabel = 'from ' . date('F d', strtotime($startDate)) . ' to ' . date('d, Y', strtotime($endDate));

$reportMeta = [
    'start_date' => $startDate,
    'end_date' => $endDate,
    'report_type' => $reportType,
    'generated_at' => $generatedAt,
    'records' => $reportType === 'by_product_machine' ? count($machineMatrixRows) : count($registerRows),
    'total_finished_ice_qty' => $totalFinishedIceQty,
    'total_loose_ice_qty' => $totalLooseIceQty,
    'entry_count' => $entryCount,
    'average_daily_entries' => $averageDailyEntries,
    'report_range' => $reportRangeLabel,
    'production_heading' => formatToDateProductionReportHeading($startDate, $endDate)
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
    .todate-production-table .dataTables_wrapper,
    .todate-production-table .dataTables_scroll,
    .todate-production-table .dataTables_scrollHead,
    .todate-production-table .dataTables_scrollBody,
    .todate-production-table .dataTables_scrollHeadInner,
    .todate-production-table .dataTables_scrollHeadInner table,
    .todate-production-table .dataTables_scrollBody table {
        width: 100% !important;
    }
    .todate-production-table .dataTables_scrollBody {
        overflow-x: scroll !important;
        overflow-y: auto !important;
        scrollbar-gutter: stable both-edges;
        scrollbar-width: auto;
        scrollbar-color: #4b5563 #d1d5db;
    }
    .todate-production-table .dataTables_scrollBody::-webkit-scrollbar {
        height: 12px;
    }
    .todate-production-table .dataTables_scrollBody::-webkit-scrollbar-track {
        background: #d1d5db;
    }
    .todate-production-table .dataTables_scrollBody::-webkit-scrollbar-thumb {
        background: #4b5563;
        border-radius: 10px;
        border: 2px solid #d1d5db;
    }
    .todate-production-table .dataTables_scrollBody::-webkit-scrollbar-thumb:hover {
        background: #374151;
    }
    .todate-production-table table {
        min-width: 1600px;
        white-space: nowrap;
    }
    .production-summary-title {
        color: #000;
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .production-summary-date {
        color: #000;
        font-size: 1.15rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .production-summary-matrix th,
    .production-summary-matrix td {
        border-color: #e5e7eb !important;
        padding: 0.75rem 0.85rem;
        white-space: nowrap;
    }
    .production-summary-matrix thead th {
        background: #fff;
        color: #1f2937;
        font-size: 0.78rem;
        font-weight: 700;
        text-align: center;
        text-transform: uppercase;
        vertical-align: middle;
    }
    .production-summary-matrix tbody td {
        color: #4b5563;
        font-size: 0.9rem;
    }
    .production-summary-matrix .matrix-spacer {
        background: #fff;
        min-width: 130px;
    }
    .production-summary-matrix .product-group-head,
    .production-summary-matrix .section-head {
        text-align: center !important;
    }
    .production-summary-matrix .date-cell {
        min-width: 130px;
        text-align: left;
    }
    .production-summary-matrix .measure-cell {
        min-width: 78px;
        text-align: right;
    }
    .production-summary-matrix .subtotal-head {
        min-width: 104px;
    }
    .production-summary-matrix .subtotal-cell {
        font-weight: 700;
        min-width: 104px;
        text-align: right;
    }
</style>
<?php
$styles = ob_get_clean();

ob_start();
?>
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-start">
        <div class="col-lg-6">
            <h3 class="mb-1">To-Date Production Report</h3>
            <p class="text-muted mb-1"><?php echo htmlspecialchars($reportMeta['report_range']); ?></p>
            <p class="text-muted small mb-0">Generated: <?php echo htmlspecialchars($reportMeta['generated_at']); ?></p>
        </div>
        <div class="col-lg-6 mt-3 mt-lg-0">
            <div class="d-flex flex-column gap-3 align-items-lg-end">
                <form class="row g-2 align-items-end justify-content-lg-end" method="get" novalidate>
                    <div class="col-sm-5 col-md-4">
                        <label for="start-date" class="form-label text-muted small mb-1">Start Date</label>
                        <input type="date" id="start-date" name="start" class="form-control" value="<?php echo htmlspecialchars($reportMeta['start_date']); ?>">
                    </div>
                    <div class="col-sm-5 col-md-4">
                        <label for="end-date" class="form-label text-muted small mb-1">End Date</label>
                        <input type="date" id="end-date" name="end" class="form-control" value="<?php echo htmlspecialchars($reportMeta['end_date']); ?>">
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
                        <a class="btn btn-outline-secondary" href="print_todate_production.php?start=<?php echo urlencode($reportMeta['start_date']); ?>&end=<?php echo urlencode($reportMeta['end_date']); ?>&amp;report_type=<?php echo urlencode($reportMeta['report_type']); ?>">
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
                <h5 class="card-title mb-0">To-Date Production Report</h5>
                <span class="text-muted small"><?php echo number_format($reportMeta['records']); ?> record(s)</span>
            </div>
            <div class="card-body">
                <?php if (($reportType === 'by_product_machine' && empty($machineColumns)) || ($reportType !== 'by_product_machine' && empty($registerRows))): ?>
                    <p class="text-muted mb-0">No production was recorded for the selected date range.</p>
                <?php else: ?>
                    <?php if ($reportType === 'by_product_machine'): ?>
                        <div class="mb-4">
                            <div class="production-summary-title">DAILY PRODUCTION SUMMARY - BY MACHINE</div>
                            <div class="production-summary-date"><?php echo htmlspecialchars($reportMeta['production_heading']); ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="table-responsive todate-production-table" style="overflow-x:auto;">
                        <?php if ($reportType === 'by_product_machine'): ?>
                            <?php
                                $machineMeasureColspan = 0;
                                foreach ($machineProductGroups as $productGroup) {
                                    $machineMeasureColspan += (int)$productGroup['colspan'];
                                }
                                $machineMeasureColspan = max(1, $machineMeasureColspan);
                            ?>
                            <table id="todate-production-table" class="table table-striped production-summary-matrix align-middle mb-0 w-100">
                                <thead>
                                    <tr>
                                        <th class="matrix-spacer" rowspan="2"></th>
                                        <th class="section-head" colspan="<?php echo $machineMeasureColspan; ?>">IN (IN KG)</th>
                                    </tr>
                                    <tr>
                                        <th class="section-head" colspan="<?php echo $machineMeasureColspan; ?>">PRODUCTION</th>
                                    </tr>
                                    <tr>
                                        <th rowspan="3">DATE</th>
                                        <?php foreach ($machineProductGroups as $productGroup): ?>
                                            <th class="product-group-head" colspan="<?php echo (int)$productGroup['colspan']; ?>"><?php echo htmlspecialchars($productGroup['product_type']); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <?php foreach ($machineProductGroups as $productGroup): ?>
                                            <?php foreach ($productGroup['machine_columns'] as $machineColumn): ?>
                                                <th colspan="2"><?php echo htmlspecialchars($machineColumn['machine_label']); ?></th>
                                            <?php endforeach; ?>
                                            <th class="subtotal-head" rowspan="2">SUB-TOTAL</th>
                                        <?php endforeach; ?>
                                    </tr>
                                    <tr>
                                        <?php foreach ($machineProductGroups as $productGroup): ?>
                                            <?php foreach ($productGroup['machine_columns'] as $machineColumn): ?>
                                                <th>HRS</th>
                                                <th>QTY</th>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($machineMatrixRows as $matrixRow): ?>
                                        <tr>
                                            <td class="date-cell"><?php echo htmlspecialchars($matrixRow['date_label']); ?></td>
                                            <?php foreach ($machineProductGroups as $productGroup): ?>
                                                <?php $productSubtotalQty = 0.0; ?>
                                                <?php foreach ($productGroup['machine_columns'] as $machineColumn): ?>
                                                    <?php
                                                        $cell = isset($matrixRow['machines'][$machineColumn['key']]) ? $matrixRow['machines'][$machineColumn['key']] : ['hours' => 0.0, 'qty' => 0.0];
                                                        $productSubtotalQty += isset($cell['qty']) ? (float)$cell['qty'] : 0.0;
                                                    ?>
                                                    <td class="measure-cell"><?php echo formatToDateProductionMatrixNumber($cell['hours']); ?></td>
                                                    <td class="measure-cell"><?php echo formatToDateProductionMatrixNumber($cell['qty']); ?></td>
                                                <?php endforeach; ?>
                                                <td class="subtotal-cell"><?php echo formatToDateProductionMatrixNumber($productSubtotalQty); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <table id="todate-production-table" class="table table-striped align-middle mb-0 w-100">
                                <thead>
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
                                    </tr>
                                </thead>
                                <tbody>
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
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
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
        function initToDateProductionTable() {
            if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) {
                return;
            }
            var table = jQuery('#todate-production-table');
            if (!table.length || jQuery.fn.DataTable.isDataTable(table)) {
                return;
            }
            var options = {
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
                },
                initComplete: function () {
                    disableToDateProductionSearchAutofill();
                }
            };
            table.DataTable(options);
            table.on('draw.dt', disableToDateProductionSearchAutofill);
        }

        function disableToDateProductionSearchAutofill() {
            var filterInput = document.querySelector('#todate-production-table_filter input');
            if (!filterInput) {
                return;
            }
            filterInput.setAttribute('name', 'todate_production_filter');
            filterInput.setAttribute('autocomplete', 'off');
            filterInput.setAttribute('autocorrect', 'off');
            filterInput.setAttribute('autocapitalize', 'off');
            filterInput.setAttribute('spellcheck', 'false');
            filterInput.setAttribute('data-lpignore', 'true');
            filterInput.setAttribute('data-1p-ignore', 'true');
            filterInput.setAttribute('data-form-type', 'other');
        }

        document.addEventListener('DOMContentLoaded', function () {
            initToDateProductionTable();
        });
    </script>
    <?php
    return ob_get_clean();
};
$scripts = $scriptsBuffer();

include 'layouts/base.php';
