<?php
session_start();

$role = isset($_SESSION['sess_userrole']) ? strtoupper($_SESSION['sess_userrole']) : '';
$assignedDepartments = isset($_SESSION['sess_department']) ? trim((string)$_SESSION['sess_department']) : '';
$allowedRoles = ['ADMIN', 'CASHIER', 'REGISTRATION', 'COUNTER', 'DEPARTMENT'];
if (!in_array($role, $allowedRoles, true) && $assignedDepartments === '') {
    header('Location: index.php');
    exit;
}

$rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));

require_once __DIR__ . '/db2.php';
require_once __DIR__ . '/daily_issuance_register_common.php';

list($startDate, $endDate, $startObject, $endObject) = validateDailyIssuanceDateRange(
    isset($_GET['start']) ? $_GET['start'] : '',
    isset($_GET['end']) ? $_GET['end'] : ''
);

$generatedAt = date('Y-m-d H:i:s');
$pageError = null;
$reportData = [
    'date_columns' => buildDailyIssuanceDateColumns($startDate, $endDate),
    'report_columns' => buildDailyIssuanceReportColumns(buildDailyIssuanceDateColumns($startDate, $endDate)),
    'source_groups' => [],
    'others_source_groups' => [],
    'groups' => [],
    'total_row' => [
        'daily_qty' => initializeDailyIssuanceDailyQty(buildDailyIssuanceDateColumns($startDate, $endDate)),
        'subtotal' => 0.0
    ],
    'others_total_row' => [
        'daily_qty' => initializeDailyIssuanceDailyQty(buildDailyIssuanceDateColumns($startDate, $endDate)),
        'subtotal' => 0.0
    ],
    'product_count' => 0,
    'source_channel_count' => 0,
    'total_issued_qty' => 0.0
];

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    $pageError = 'Database connection unavailable.';
} else {
    $reportData = fetchDailyIssuanceRegisterData($mysqli, $startDate, $endDate);
}

$dayCount = $startObject && $endObject ? $startObject->diff($endObject)->days + 1 : 1;
$averageIssuedQty = $dayCount > 0 ? ((float)$reportData['total_issued_qty'] / $dayCount) : 0.0;
$rangeLabel = formatDailyIssuanceRangeLabel($startDate, $endDate);

$reportMeta = [
    'start_date' => $startDate,
    'end_date' => $endDate,
    'generated_at' => $generatedAt,
    'range_label' => $rangeLabel,
    'product_count' => isset($reportData['product_count']) ? (int)$reportData['product_count'] : 0,
    'group_count' => isset($reportData['source_channel_count']) ? (int)$reportData['source_channel_count'] : 0,
    'total_issued_qty' => isset($reportData['total_issued_qty']) ? (float)$reportData['total_issued_qty'] : 0.0,
    'average_issued_qty' => $averageIssuedQty
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
    .issuance-table .dataTables_wrapper,
    .issuance-table .dataTables_scroll,
    .issuance-table .dataTables_scrollHead,
    .issuance-table .dataTables_scrollBody,
    .issuance-table .dataTables_scrollHeadInner,
    .issuance-table .dataTables_scrollHeadInner table,
    .issuance-table .dataTables_scrollBody table {
        width: 100% !important;
    }
    .issuance-table .dataTables_scrollBody {
        overflow-x: scroll !important;
        overflow-y: auto !important;
        scrollbar-gutter: stable both-edges;
        scrollbar-width: auto;
        scrollbar-color: #4b5563 #d1d5db;
    }
    .issuance-table table {
        min-width: <?php echo max(1100, 760 + (count($reportData['report_columns']) * 130)); ?>px;
        white-space: nowrap;
    }
    .issuance-head {
        background: #e5e7eb;
        color: #111827;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .issuance-group-row td {
        background: #f8fafc;
        font-size: 0.95rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .issuance-source-row td {
        background: #eef2ff;
        font-weight: 700;
        text-transform: uppercase;
    }
    .issuance-others-section-row td {
        background: #fff7ed;
        font-weight: 700;
        text-transform: uppercase;
    }
    .issuance-subtotal-row td,
    .issuance-product-subtotal-row td,
    .issuance-total-row td {
        background: #f3f4f6;
        font-weight: 700;
    }
    .issuance-product-subtotal-row td {
        background: #fafafa;
    }
    .issuance-total-row td {
        background: #e5e7eb;
    }
    .issuance-week-total,
    .issuance-month-total {
        background: #f9fafb;
        font-weight: 700;
    }
</style>
<?php
$styles = ob_get_clean();

ob_start();
?>
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-start">
        <div class="col-lg-6">
            <h3 class="mb-1">Daily Issuance Register</h3>
            <p class="text-muted mb-1">Date Range: <?php echo htmlspecialchars($reportMeta['range_label']); ?></p>
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
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">View</button>
                    </div>
                    <div class="col-auto">
                        <a class="btn btn-outline-secondary" href="print_todate_daily_issuance_register.php?start=<?php echo urlencode($reportMeta['start_date']); ?>&end=<?php echo urlencode($reportMeta['end_date']); ?>">
                            Export Excel
                        </a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered summary-table mb-0">
                        <tbody>
                            <tr>
                                <th class="text-uppercase small text-muted">Product Count</th>
                                <td class="value"><?php echo number_format($reportMeta['product_count']); ?></td>
                            </tr>
                            <tr>
                                <th class="text-uppercase small text-muted">Source Channels</th>
                                <td class="value"><?php echo number_format($reportMeta['group_count']); ?></td>
                            </tr>
                            <tr>
                                <th class="text-uppercase small text-muted">Total Issued Qty</th>
                                <td class="value"><?php echo number_format($reportMeta['total_issued_qty'], 2); ?></td>
                            </tr>
                            <tr>
                                <th class="text-uppercase small text-muted">Average Daily Issued Qty</th>
                                <td class="value"><?php echo number_format($reportMeta['average_issued_qty'], 2); ?></td>
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
                <h5 class="card-title mb-0">Daily Issuance Register</h5>
                <span class="text-muted small"><?php echo number_format($reportMeta['product_count']); ?> product(s)</span>
            </div>
            <div class="card-body">
                <?php if (empty($reportData['source_groups'])): ?>
                    <p class="text-muted mb-0">No product records are available.</p>
                <?php else: ?>
                    <div class="table-responsive issuance-table">
                        <table id="daily-issuance-table" class="table table-striped align-middle mb-0 w-100">
                            <thead>
                                <tr class="issuance-head text-center align-middle">
                                    <th>No.</th>
                                    <th>Item Code</th>
                                    <th>Item Description</th>
                                    <th class="text-end">Unit Price</th>
                                    <th>UOM</th>
                                    <?php foreach ($reportData['report_columns'] as $columnMeta): ?>
                                        <?php
                                        $columnClass = isset($columnMeta['type']) && $columnMeta['type'] !== 'date' ? ' issuance-week-total' : '';
                                        if (isset($columnMeta['type']) && $columnMeta['type'] === 'month_total') {
                                            $columnClass = ' issuance-month-total';
                                        }
                                        ?>
                                        <th class="text-end<?php echo $columnClass; ?>"><?php echo htmlspecialchars($columnMeta['date_label']); ?><br><?php echo htmlspecialchars($columnMeta['day_label']); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reportData['source_groups'] as $sourceGroup): ?>
                                    <tr class="issuance-source-row">
                                        <td><?php echo htmlspecialchars($sourceGroup['source_channel']); ?></td>
                                        <?php for ($emptyCell = 1; $emptyCell < 5 + count($reportData['report_columns']); $emptyCell++): ?>
                                            <td></td>
                                        <?php endfor; ?>
                                    </tr>
                                    <?php foreach ($sourceGroup['product_groups'] as $group): ?>
                                        <tr class="issuance-group-row">
                                            <td><?php echo htmlspecialchars($group['product_type']); ?></td>
                                            <?php for ($emptyCell = 1; $emptyCell < 5 + count($reportData['report_columns']); $emptyCell++): ?>
                                                <td></td>
                                            <?php endfor; ?>
                                        </tr>
                                        <?php $lineNumber = 1; ?>
                                        <?php foreach ($group['rows'] as $row): ?>
                                            <tr>
                                                <td class="text-center"><?php echo $lineNumber++; ?></td>
                                                <td><?php echo htmlspecialchars($row['product_code']); ?></td>
                                                <td><?php echo htmlspecialchars($row['item_description']); ?></td>
                                                <td class="text-end"><?php echo number_format((float)$row['unit_price'], 2); ?></td>
                                                <td class="text-center"><?php echo htmlspecialchars($row['uom']); ?></td>
                                                <?php foreach ($reportData['report_columns'] as $columnMeta): ?>
                                                    <?php
                                                    $qty = calculateDailyIssuanceColumnQty($row, $columnMeta);
                                                    $columnClass = isset($columnMeta['type']) && $columnMeta['type'] !== 'date' ? ' issuance-week-total' : '';
                                                    if (isset($columnMeta['type']) && $columnMeta['type'] === 'month_total') {
                                                        $columnClass = ' issuance-month-total';
                                                    }
                                                    ?>
                                                    <td class="text-end<?php echo $columnClass; ?>"><?php echo $qty != 0.0 ? number_format($qty, 2) : ''; ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="issuance-product-subtotal-row">
                                            <td></td>
                                            <td></td>
                                            <td>SUBTOTAL - <?php echo htmlspecialchars($group['product_type']); ?></td>
                                            <td></td>
                                            <td></td>
                                            <?php foreach ($reportData['report_columns'] as $columnMeta): ?>
                                                <?php $qty = calculateDailyIssuanceColumnQty($group['subtotal_row'], $columnMeta); ?>
                                                <td class="text-end"><?php echo $qty != 0.0 ? number_format($qty, 2) : ''; ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="issuance-subtotal-row">
                                        <td></td>
                                        <td></td>
                                        <td>TOTAL <?php echo htmlspecialchars($sourceGroup['source_channel']); ?></td>
                                        <td></td>
                                        <td></td>
                                        <?php foreach ($reportData['report_columns'] as $columnMeta): ?>
                                            <?php $qty = calculateDailyIssuanceColumnQty($sourceGroup['subtotal_row'], $columnMeta); ?>
                                            <td class="text-end"><?php echo $qty != 0.0 ? number_format($qty, 2) : ''; ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="issuance-total-row">
                                    <td></td>
                                    <td></td>
                                    <td>GRAND TOTAL</td>
                                    <td></td>
                                    <td></td>
                                    <?php foreach ($reportData['report_columns'] as $columnMeta): ?>
                                        <?php $qty = calculateDailyIssuanceColumnQty($reportData['total_row'], $columnMeta); ?>
                                        <td class="text-end"><?php echo $qty != 0.0 ? number_format($qty, 2) : ''; ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php if (!empty($reportData['others_source_groups'])): ?>
                                    <tr class="issuance-others-section-row">
                                        <td>OTHERS</td>
                                        <?php for ($emptyCell = 1; $emptyCell < 5 + count($reportData['report_columns']); $emptyCell++): ?>
                                            <td></td>
                                        <?php endfor; ?>
                                    </tr>
                                    <?php foreach ($reportData['others_source_groups'] as $sourceGroup): ?>
                                        <tr class="issuance-source-row">
                                            <td><?php echo htmlspecialchars($sourceGroup['source_channel']); ?></td>
                                            <?php for ($emptyCell = 1; $emptyCell < 5 + count($reportData['report_columns']); $emptyCell++): ?>
                                                <td></td>
                                            <?php endfor; ?>
                                        </tr>
                                        <?php foreach ($sourceGroup['product_groups'] as $group): ?>
                                            <?php $lineNumber = 1; ?>
                                            <?php foreach ($group['rows'] as $row): ?>
                                                <tr>
                                                    <td class="text-center"><?php echo $lineNumber++; ?></td>
                                                    <td><?php echo htmlspecialchars($row['product_code']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['item_description']); ?></td>
                                                    <td class="text-end"><?php echo number_format((float)$row['unit_price'], 2); ?></td>
                                                    <td class="text-center"><?php echo htmlspecialchars($row['uom']); ?></td>
                                                    <?php foreach ($reportData['report_columns'] as $columnMeta): ?>
                                                        <?php $qty = calculateDailyIssuanceColumnQty($row, $columnMeta); ?>
                                                        <td class="text-end"><?php echo $qty != 0.0 ? number_format($qty, 2) : ''; ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                        <tr class="issuance-subtotal-row">
                                            <td></td>
                                            <td></td>
                                            <td>TOTAL <?php echo htmlspecialchars($sourceGroup['source_channel']); ?></td>
                                            <td></td>
                                            <td></td>
                                            <?php foreach ($reportData['report_columns'] as $columnMeta): ?>
                                                <?php $qty = calculateDailyIssuanceColumnQty($sourceGroup['subtotal_row'], $columnMeta); ?>
                                                <td class="text-end"><?php echo $qty != 0.0 ? number_format($qty, 2) : ''; ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="issuance-total-row">
                                        <td></td>
                                        <td></td>
                                        <td>TOTAL OTHERS</td>
                                        <td></td>
                                        <td></td>
                                        <?php foreach ($reportData['report_columns'] as $columnMeta): ?>
                                            <?php $qty = calculateDailyIssuanceColumnQty($reportData['others_total_row'], $columnMeta); ?>
                                            <td class="text-end"><?php echo $qty != 0.0 ? number_format($qty, 2) : ''; ?></td>
                                        <?php endforeach; ?>
                                    </tr>
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
            var table = document.getElementById('daily-issuance-table');
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
                    emptyTable: 'No issuance records found.',
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
