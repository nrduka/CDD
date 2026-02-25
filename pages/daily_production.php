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

$reportDateInput = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
$reportDate = $reportDateInput !== '' ? $reportDateInput : date('Y-m-d');
$reportDateObject = DateTime::createFromFormat('Y-m-d', $reportDate);
if (!$reportDateObject || $reportDateObject->format('Y-m-d') !== $reportDate) {
    $reportDate = date('Y-m-d');
}

$reportTypeInput = isset($_GET['report_type']) ? trim((string)$_GET['report_type']) : '';
$reportType = in_array($reportTypeInput, ['detailed', 'by_product_machine'], true) ? $reportTypeInput : 'detailed';

function resolveProductionTimeLabel($value)
{
    $timeValue = trim((string)$value);
    if ($timeValue === '' || $timeValue === '00:00:00') {
        return '';
    }
    $timeObject = DateTime::createFromFormat('H:i:s', $timeValue);
    if ($timeObject instanceof DateTime) {
        return $timeObject->format('H:i:s');
    }

    return $timeValue;
}

function fetchDailyProductionRows($mysqli, $reportDate)
{
    $rows = [];
    $sql = "SELECT ip.id,
                   ip.dpr_code,
                   ip.production_code,
                   ip.date_production,
                   ip.time_dropped,
                   ip.machine,
                   ip.finished_ice_qty,
                   ip.loose_ice_qty,
                   ip.remarks,
                   im.machine_description,
                   im.product_type AS machine_product_type
            FROM ice_production ip
            LEFT JOIN ice_machine im ON im.id = ip.machine
            WHERE ip.date_production = ?
            ORDER BY ip.date_production, COALESCE(ip.time_dropped, '00:00:00'), im.machine_description, ip.id";

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
                    $productType = 'UNASSIGNED';
                }

                $dprCode = trim((string)(isset($row['dpr_code']) ? $row['dpr_code'] : ''));
                if ($dprCode === '') {
                    $dprCode = trim((string)(isset($row['production_code']) ? $row['production_code'] : ''));
                }

                $rows[] = [
                    'id' => isset($row['id']) ? (int)$row['id'] : 0,
                    'date_production' => isset($row['date_production']) ? (string)$row['date_production'] : '',
                    'time_dropped' => resolveProductionTimeLabel(isset($row['time_dropped']) ? $row['time_dropped'] : ''),
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
$totalFinishedIceQty = 0.0;
$totalLooseIceQty = 0.0;
$entryCount = 0;

if (!isset($mysqli) || !$mysqli) {
    $pageError = 'Database connection unavailable.';
} else {
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
                                <th class="text-uppercase small text-muted">Total Finished Ice Qty (Sack)</th>
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
                                        <th class="text-end">Finished Ice Qty (Sack)</th>
                                        <th class="text-end">Loose Ice Qty (Kg)</th>
                                    </tr>
                                <?php else: ?>
                                    <tr class="production-head text-center align-middle">
                                        <th>No.</th>
                                        <th>Date</th>
                                        <th>Time Dropped</th>
                                        <th>DPR Code</th>
                                        <th>Machine</th>
                                        <th>Product Type</th>
                                        <th class="text-end">Finished Ice Qty (Sack)</th>
                                        <th class="text-end">Loose Ice Qty (Kg)</th>
                                        <th>Remarks</th>
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
                                            <td><?php echo htmlspecialchars($row['date_production']); ?></td>
                                            <td><?php echo htmlspecialchars($row['time_dropped']); ?></td>
                                            <td><?php echo htmlspecialchars($row['dpr_code']); ?></td>
                                            <td><?php echo htmlspecialchars($row['machine_description']); ?></td>
                                            <td><?php echo htmlspecialchars($row['product_type']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['finished_ice_qty'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($row['loose_ice_qty'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($row['remarks']); ?></td>
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
            if (!table || !window.jQuery || !jQuery.fn.DataTable) { return; }
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
        });
    </script>
    <?php
    return ob_get_clean();
};
$scripts = $scriptsBuffer();

include 'layouts/base.php';
