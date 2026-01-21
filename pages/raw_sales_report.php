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

$columns = [
    'sales_id' => 'SALES ID',
    'sales_order_no' => 'SALES ORDER NO',
    'sales_customer_code' => 'SALES CUSTOMER CODE',
    'sales_delivery_instruction' => 'SALES DELIVERY INSTRUCTION',
    'sales_lineitem' => 'SALES LINEITEM',
    'sales_qty' => 'SALES QTY',
    'sales_product_code' => 'SALES PRODUCT CODE',
    'sales_product_description' => 'SALES PRODUCT DESCRIPTION',
    'sales_lineitem_discount' => 'SALES LINEITEM DISCOUNT',
    'sales_discount' => 'SALES DISCOUNT',
    'sales_unit_price' => 'SALES UNIT PRICE',
    'sales_amount' => 'SALES AMOUNT',
    'sales_date_delivery' => 'SALES DATE DELIVERY',
    'sales_time_delivery' => 'SALES TIME DELIVERY',
    'sales_date_created' => 'SALES DATE CREATED',
    'sales_amt_received' => 'SALES AMT RECEIVED',
    'sales_amt_change' => 'SALES AMT CHANGE',
    'product_id' => 'PRODUCT ID',
    'product_code' => 'PRODUCT CODE',
    'product_description' => 'PRODUCT DESCRIPTION',
    'product_uom' => 'PRODUCT UOM',
    'product_unit_price' => 'PRODUCT UNIT PRICE',
    'product_date_created' => 'PRODUCT DATE CREATED',
    'product_type' => 'PRODUCT TYPE',
    'product_status' => 'PRODUCT STATUS',
    'customer_id' => 'CUSTOMER ID',
    'customer_code' => 'CUSTOMER CODE',
    'customer_first_name' => 'CUSTOMER FIRST NAME',
    'customer_middle_name' => 'CUSTOMER MIDDLE NAME',
    'customer_last_name' => 'CUSTOMER LAST NAME',
    'customer_address' => 'CUSTOMER ADDRESS',
    'customer_store_name' => 'CUSTOMER STORE NAME',
    'customer_type' => 'CUSTOMER TYPE',
    'customer_series' => 'CUSTOMER SERIES',
    'customer_source' => 'CUSTOMER SOURCE',
    'customer_area' => 'CUSTOMER AREA',
    'customer_sales_channel' => 'CUSTOMER SALES CHANNEL'
];

$pageError = null;
$salesRows = [];
$generatedAt = date('Y-m-d H:i:s');

if (!isset($mysqli) || !$mysqli) {
    $pageError = 'Database connection unavailable.';
} else {
    $sql = "SELECT s.id AS sales_id,
                   s.order_no AS sales_order_no,
                   s.customer_code AS sales_customer_code,
                   s.delivery_instruction AS sales_delivery_instruction,
                   s.lineitem AS sales_lineitem,
                   s.qty AS sales_qty,
                   s.product_code AS sales_product_code,
                   s.product_description AS sales_product_description,
                   s.lineitem_discount AS sales_lineitem_discount,
                   s.discount AS sales_discount,
                   s.unit_price AS sales_unit_price,
                   s.amount AS sales_amount,
                   s.date_delivery AS sales_date_delivery,
                   s.time_delivery AS sales_time_delivery,
                   s.date_created AS sales_date_created,
                   s.amt_received AS sales_amt_received,
                   s.amt_change AS sales_amt_change,
                   p.id AS product_id,
                   p.product_code AS product_code,
                   p.product_description AS product_description,
                   p.uom AS product_uom,
                   p.unit_price AS product_unit_price,
                   p.date_created AS product_date_created,
                   p.product_type AS product_type,
                   p.status AS product_status,
                   c.id AS customer_id,
                   c.customer_code AS customer_code,
                   c.firstName AS customer_first_name,
                   c.middleName AS customer_middle_name,
                   c.lastName AS customer_last_name,
                   c.address AS customer_address,
                   c.storeName AS customer_store_name,
                   c.type AS customer_type,
                   c.customer_series AS customer_series,
                   c.customer_source AS customer_source,
                   c.area AS customer_area,
                   c.sales_channel AS customer_sales_channel
            FROM sales s
            LEFT JOIN ice_product p ON p.product_code = s.product_code
            LEFT JOIN customer c ON c.customer_code = s.customer_code
            WHERE DATE(s.date_created) = ?
            ORDER BY s.date_created, s.order_no, s.lineitem";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        $pageError = 'Unable to load sales data.';
    } else {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $salesRows[] = $row;
            }
            $result->free();
        }
        $stmt->close();
    }
}

$reportDateLabel = date('F d, Y', strtotime($reportDate));

$reportMeta = [
    'report_date' => $reportDate,
    'generated_at' => $generatedAt,
    'records' => count($salesRows)
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
    .raw-sales-table .dataTables_wrapper,
    .raw-sales-table .dataTables_scroll,
    .raw-sales-table .dataTables_scrollHead,
    .raw-sales-table .dataTables_scrollBody,
    .raw-sales-table .dataTables_scrollHeadInner,
    .raw-sales-table .dataTables_scrollHeadInner table,
    .raw-sales-table .dataTables_scrollBody table {
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
            <h3 class="mb-1">Raw Sales Report</h3>
            <p class="text-muted mb-0">Sales recorded on <?php echo htmlspecialchars($reportDateLabel); ?>.</p>
        </div>
        <div class="col-lg-6 mt-3 mt-lg-0">
            <form class="row g-2 align-items-end justify-content-end" method="get" novalidate>
                <div class="col-sm-7 col-md-6">
                    <label for="report-date" class="form-label text-muted small mb-1">Transaction Date</label>
                    <input type="date" id="report-date" name="date" class="form-control" value="<?php echo htmlspecialchars($reportMeta['report_date']); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">View</button>
                </div>
                <div class="col-auto">
                    <a class="btn btn-outline-secondary" href="print_raw_sales_report.php?date=<?php echo urlencode($reportMeta['report_date']); ?>">
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
                    <p class="text-muted mb-0">Sales records for <?php echo htmlspecialchars($reportDateLabel); ?>.</p>
                </div>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Raw Sales</h5>
                <span class="text-muted small"><?php echo $reportMeta['records']; ?> row(s)</span>
            </div>
            <div class="card-body">
                <?php if (empty($salesRows)): ?>
                    <p class="text-muted mb-0">No sales were recorded for the selected date.</p>
                <?php else: ?>
                    <div class="table-responsive raw-sales-table">
                        <table id="raw-sales-table" class="table table-striped align-middle mb-0 w-100">
                            <thead>
                                <tr>
                                    <?php foreach ($columns as $label): ?>
                                        <th><?php echo htmlspecialchars($label); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salesRows as $row): ?>
                                    <tr>
                                        <?php foreach ($columns as $key => $label): ?>
                                            <?php $value = isset($row[$key]) ? $row[$key] : ''; ?>
                                            <td><?php echo htmlspecialchars((string)$value); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
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
        function initRawSalesTable() {
            if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) {
                return;
            }
            var table = jQuery('#raw-sales-table');
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
                    emptyTable: 'No sales for this date.',
                    info: 'Showing _START_ to _END_ of _TOTAL_ rows',
                    infoEmpty: 'Showing 0 to 0 of 0 rows',
                    paginate: { previous: 'Previous', next: 'Next' }
                }
            };
            table.DataTable(options);
        }

        document.addEventListener('DOMContentLoaded', function () {
            initRawSalesTable();
        });
    </script>
    <?php
    return ob_get_clean();
};
$scripts = $scriptsBuffer();

include 'layouts/base.php';
