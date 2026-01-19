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

$generatedAt = date('Y-m-d H:i:s');
$pageError = null;
$salesRows = [];
$groupedSales = [];
$dateTotals = [];
$grandTotals = [
    'transactions' => 0,
    'lineitem_discount' => 0,
    'discount' => 0,
    'qty' => 0,
    'amount' => 0
];

if (!isset($mysqli) || !$mysqli) {
    $pageError = 'Database connection unavailable.';
} else {
    $salesRows = fetchToDateDailySales($mysqli, $startDate, $endDate);
    foreach ($salesRows as $row) {
        $dateKey = $row['sale_date'];
        if (!isset($groupedSales[$dateKey])) {
            $groupedSales[$dateKey] = [];
            $dateTotals[$dateKey] = [
                'transactions' => 0,
                'lineitem_discount' => 0,
                'discount' => 0,
                'qty' => 0,
                'amount' => 0
            ];
        }
        $groupedSales[$dateKey][] = $row;
        $dateTotals[$dateKey]['transactions'] += $row['total_transactions'];
        $dateTotals[$dateKey]['lineitem_discount'] += $row['lineitem_discount'];
        $dateTotals[$dateKey]['discount'] += $row['discount'];
        $dateTotals[$dateKey]['qty'] += $row['qty'];
        $dateTotals[$dateKey]['amount'] += $row['amount'];

        $grandTotals['transactions'] += $row['total_transactions'];
        $grandTotals['lineitem_discount'] += $row['lineitem_discount'];
        $grandTotals['discount'] += $row['discount'];
        $grandTotals['qty'] += $row['qty'];
        $grandTotals['amount'] += $row['amount'];
    }
    ksort($groupedSales);
}

function fetchToDateDailySales($mysqli, $startDate, $endDate)
{
    $rows = [];
    $sql = "SELECT DATE(s.date_created) AS sale_date,
                   s.product_code,
                   COALESCE(p.product_description, s.product_description) AS product_description,
                   COALESCE(p.unit_price, s.unit_price) AS unit_price,
                   COUNT(DISTINCT s.order_no) AS total_transactions,
                   SUM(COALESCE(s.lineitem_discount, 0)) AS lineitem_discount_total,
                   SUM(COALESCE(s.discount, 0)) AS discount_total,
                   SUM(COALESCE(s.qty, 0)) AS qty_total,
                   SUM(COALESCE(s.amount, 0)) AS amount_total
            FROM sales s
            LEFT JOIN ice_product p ON p.product_code = s.product_code
            WHERE DATE(s.date_created) BETWEEN ? AND ?
            GROUP BY DATE(s.date_created),
                     s.product_code,
                     COALESCE(p.product_description, s.product_description),
                     COALESCE(p.unit_price, s.unit_price)
            ORDER BY DATE(s.date_created), s.product_code";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ss', $startDate, $endDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = [
                    'sale_date' => isset($row['sale_date']) ? (string)$row['sale_date'] : '',
                    'product_code' => isset($row['product_code']) ? (string)$row['product_code'] : '',
                    'product_description' => isset($row['product_description']) ? (string)$row['product_description'] : '',
                    'total_transactions' => isset($row['total_transactions']) ? (int)$row['total_transactions'] : 0,
                    'lineitem_discount' => isset($row['lineitem_discount_total']) ? (float)$row['lineitem_discount_total'] : 0,
                    'discount' => isset($row['discount_total']) ? (float)$row['discount_total'] : 0,
                    'qty' => isset($row['qty_total']) ? (float)$row['qty_total'] : 0,
                    'unit_price' => isset($row['unit_price']) ? (float)$row['unit_price'] : 0,
                    'amount' => isset($row['amount_total']) ? (float)$row['amount_total'] : 0
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

function formatRangeLabel($startDate, $endDate)
{
    $startLabel = date('F d, Y', strtotime($startDate));
    $endLabel = date('F d, Y', strtotime($endDate));
    return $startLabel === $endLabel ? $startLabel : $startLabel . ' - ' . $endLabel;
}

$reportLabel = formatRangeLabel($startDate, $endDate);
$reportMeta = [
    'start_date' => $startDate,
    'end_date' => $endDate,
    'generated_at' => $generatedAt,
    'records' => count($salesRows),
    'grand_total' => $grandTotals['amount']
];

ob_start();
?>
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
<style>
    .report-card {
        border-radius: 0.75rem;
        border: 1px solid rgba(226, 232, 240, 0.9);
        padding: 1.25rem;
        background-color: #fff;
        height: 100%;
    }
    .report-card .label {
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 0.75rem;
        color: #94a3b8;
    }
    .report-card h4 {
        font-size: 2rem;
        margin: 0;
    }
    .subtotal-row {
        background-color: #f8fafc;
    }
    .grand-total-row {
        background-color: #e2e8f0;
    }
    .todate-daily-sales-table .dataTables_wrapper,
    .todate-daily-sales-table .dataTables_scroll,
    .todate-daily-sales-table .dataTables_scrollHead,
    .todate-daily-sales-table .dataTables_scrollBody,
    .todate-daily-sales-table .dataTables_scrollHeadInner,
    .todate-daily-sales-table .dataTables_scrollHeadInner table,
    .todate-daily-sales-table .dataTables_scrollBody table {
        width: 100% !important;
    }
</style>
<?php
$styles = ob_get_clean();

ob_start();
?>
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-lg-6">
            <h3 class="mb-1">To-Date Daily Sales</h3>
            <p class="text-muted mb-0">Sales by product from <?php echo htmlspecialchars($reportLabel); ?>.</p>
        </div>
        <div class="col-lg-6 mt-3 mt-lg-0">
            <form class="row g-2 align-items-end justify-content-end" method="get" novalidate>
                <div class="col-sm-6 col-md-5">
                    <label for="start-date" class="form-label text-muted small mb-1">Start Date</label>
                    <input type="date" id="start-date" name="start" class="form-control" value="<?php echo htmlspecialchars($reportMeta['start_date']); ?>">
                </div>
                <div class="col-sm-6 col-md-5">
                    <label for="end-date" class="form-label text-muted small mb-1">End Date</label>
                    <input type="date" id="end-date" name="end" class="form-control" value="<?php echo htmlspecialchars($reportMeta['end_date']); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">View</button>
                </div>
                <div class="col-auto">
                    <a class="btn btn-outline-secondary" href="print_todate_daily_sales.php?start=<?php echo urlencode($reportMeta['start_date']); ?>&end=<?php echo urlencode($reportMeta['end_date']); ?>">
                        Export Excel
                    </a>
                </div>
                <div class="col-12 text-end">
                    <span class="text-muted small">Generated: <?php echo htmlspecialchars($reportMeta['generated_at']); ?></span>
                </div>
            </form>
        </div>
    </div>

    <?php if ($pageError !== null): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($pageError); ?></div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="report-card">
                    <span class="label">Total Rows</span>
                    <h4><?php echo number_format($reportMeta['records']); ?></h4>
                    <p class="text-muted mb-0">Line items in the selected range.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-card">
                    <span class="label">Grand Total</span>
                    <h4><?php echo number_format($reportMeta['grand_total'], 2); ?></h4>
                    <p class="text-muted mb-0">Sum of all amounts in range.</p>
                </div>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Daily Sales by Product</h5>
                <span class="text-muted small">
                    <?php echo $reportMeta['records']; ?> record(s)
                    <?php if ($reportMeta['grand_total'] > 0): ?>
                        &bull; Total: <?php echo number_format($reportMeta['grand_total'], 2); ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="card-body">
                <?php if (empty($salesRows)): ?>
                    <p class="text-muted mb-0">No sales were recorded for the selected date range.</p>
                <?php else: ?>
                    <div class="table-responsive todate-daily-sales-table">
                        <table id="todate-daily-sales-table" class="table table-striped align-middle mb-0 w-100">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Product Code</th>
                                    <th>Product Description</th>
                                    <th class="text-end">Total Transaction</th>
                                    <th class="text-end">Line Item Discount</th>
                                    <th class="text-end">Discount</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedSales as $saleDate => $items): ?>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($saleDate); ?></td>
                                            <td><?php echo htmlspecialchars($item['product_code']); ?></td>
                                            <td><?php echo htmlspecialchars($item['product_description']); ?></td>
                                            <td class="text-end"><?php echo number_format($item['total_transactions']); ?></td>
                                            <td class="text-end"><?php echo number_format($item['lineitem_discount'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($item['discount'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($item['qty'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($item['amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="subtotal-row fw-semibold">
                                        <td><?php echo htmlspecialchars($saleDate); ?></td>
                                        <td>Subtotal</td>
                                        <td></td>
                                        <td class="text-end"><?php echo number_format($dateTotals[$saleDate]['transactions']); ?></td>
                                        <td class="text-end"><?php echo number_format($dateTotals[$saleDate]['lineitem_discount'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($dateTotals[$saleDate]['discount'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($dateTotals[$saleDate]['qty'], 2); ?></td>
                                        <td class="text-end"></td>
                                        <td class="text-end"><?php echo number_format($dateTotals[$saleDate]['amount'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="grand-total-row fw-bold">
                                    <td></td>
                                    <td>Grand Total</td>
                                    <td></td>
                                    <td class="text-end"><?php echo number_format($grandTotals['transactions']); ?></td>
                                    <td class="text-end"><?php echo number_format($grandTotals['lineitem_discount'], 2); ?></td>
                                    <td class="text-end"><?php echo number_format($grandTotals['discount'], 2); ?></td>
                                    <td class="text-end"><?php echo number_format($grandTotals['qty'], 2); ?></td>
                                    <td class="text-end"></td>
                                    <td class="text-end"><?php echo number_format($grandTotals['amount'], 2); ?></td>
                                </tr>
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
            var table = document.getElementById('todate-daily-sales-table');
            if (!table || !window.jQuery || !jQuery.fn.DataTable) { return; }
            jQuery(table).DataTable({
                autoWidth: false,
                pageLength: 25,
                lengthChange: false,
                ordering: false,
                paging: true,
                scrollX: false,
                scrollY: '60vh',
                scrollCollapse: true,
                dom: "<'row align-items-center mb-2'<'col-sm-6'f><'col-sm-6 text-end'>>rt<'row align-items-center mt-2'<'col-sm-6'i><'col-sm-6'p>>",
                language: {
                    search: 'Filter:',
                    emptyTable: 'No sales for this date range.',
                    info: 'Showing _START_ to _END_ of _TOTAL_ sales',
                    infoEmpty: 'Showing 0 to 0 of 0 sales',
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
