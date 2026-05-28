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
require_once __DIR__ . '/production_vs_sales_common.php';

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

list($startDate, $endDate, $startObject, $endObject) = prodSalesResolveDateRange(
    isset($_GET['start']) ? $_GET['start'] : '',
    isset($_GET['end']) ? $_GET['end'] : ''
);
$prodSalesClass = prodSalesNormalizeClass(isset($_GET['prod_sales_class']) ? $_GET['prod_sales_class'] : '');
$classOptions = prodSalesClassOptions();

$generatedAt = date('Y-m-d H:i:s');
$pageError = null;
$productionRows = [];
$machineColumns = [];
$productionMatrixRows = [];
$salesRows = [];
$salesProductColumns = [];
$salesProductGroups = [];
$salesMatrixRows = [];
$totalProductionQty = 0.0;
$totalProductionHours = 0.0;
$totalSalesQty = 0.0;

if (!isset($mysqli) || !$mysqli) {
    $pageError = 'Database connection unavailable.';
} else {
    $productionRows = prodSalesFetchProductionRows($mysqli, $startDate, $endDate, $prodSalesClass);
    foreach ($productionRows as $row) {
        $totalProductionQty += isset($row['production_qty']) ? (float)$row['production_qty'] : 0.0;
        $totalProductionHours += isset($row['hours']) ? (float)$row['hours'] : 0.0;
    }
    $machineColumns = prodSalesFetchMachineColumns($mysqli, $productionRows, $prodSalesClass);
    $productionMatrixRows = prodSalesBuildProductionMatrix($productionRows, $startDate, $endDate, $machineColumns);
    $salesRows = prodSalesFetchSalesRows($mysqli, $startDate, $endDate, $prodSalesClass);
    $salesProductColumns = prodSalesFetchSalesProductColumns($mysqli, $prodSalesClass, $salesRows);
    $salesProductGroups = prodSalesBuildSalesProductGroups($salesProductColumns);
    $salesMatrixRows = prodSalesBuildSalesMatrix($salesRows, $startDate, $endDate, $salesProductColumns);
    $totalSalesQty = prodSalesCalculateSalesTotalQty($salesRows);
}

$dayCount = $startObject && $endObject ? $startObject->diff($endObject)->days + 1 : 1;
$averageDailyProduction = $dayCount > 0 ? ($totalProductionQty / $dayCount) : 0;
$reportRangeLabel = 'from ' . date('F d', strtotime($startDate)) . ' to ' . date('d, Y', strtotime($endDate));
$productionHeading = prodSalesFormatHeading($startDate, $endDate);
$measureColumnCount = count($machineColumns) * 2 + 1;
$salesColumnCount = count($salesProductColumns) + count($salesProductGroups) + 2;
$salesMatrixByDate = [];
foreach ($salesMatrixRows as $salesMatrixRow) {
    $salesDateKey = isset($salesMatrixRow['date']) ? (string)$salesMatrixRow['date'] : '';
    if ($salesDateKey !== '') {
        $salesMatrixByDate[$salesDateKey] = $salesMatrixRow;
    }
}

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
        min-width: 160px;
        text-align: right;
    }
    .production-analysis-table .dataTables_wrapper,
    .production-analysis-table .dataTables_scroll,
    .production-analysis-table .dataTables_scrollHead,
    .production-analysis-table .dataTables_scrollBody,
    .production-analysis-table .dataTables_scrollHeadInner,
    .production-analysis-table .dataTables_scrollHeadInner table,
    .production-analysis-table .dataTables_scrollBody table {
        width: 100% !important;
    }
    .production-analysis-table .dataTables_scrollBody {
        overflow-x: scroll !important;
        overflow-y: auto !important;
        scrollbar-gutter: stable both-edges;
    }
    .production-analysis-table table {
        min-width: 980px;
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
        min-width: 130px;
    }
    .production-summary-matrix .date-cell {
        min-width: 130px;
        text-align: left;
    }
    .production-summary-matrix .measure-cell,
    .production-summary-matrix .subtotal-cell {
        min-width: 78px;
        text-align: right;
    }
    .production-summary-matrix .subtotal-cell {
        font-weight: 700;
    }
    .production-summary-matrix .product-cell,
    .production-summary-matrix .total-cell {
        min-width: 120px;
        text-align: right;
    }
    .production-summary-matrix tfoot td {
        color: #000;
        font-size: 0.9rem;
        font-weight: 700;
    }
    .sales-summary-matrix th,
    .sales-summary-matrix td {
        border-color: #111827 !important;
        padding: 0.35rem 0.45rem;
        white-space: nowrap;
    }
    .sales-summary-matrix thead th {
        background: #fff;
        color: #000;
        font-size: 0.78rem;
        font-weight: 700;
        text-align: center;
        text-transform: uppercase;
        vertical-align: middle;
    }
    .sales-summary-matrix tbody td,
    .sales-summary-matrix tfoot td {
        color: #000;
        font-size: 0.9rem;
    }
    .sales-summary-matrix .date-cell {
        min-width: 90px;
        text-align: left;
    }
    .sales-summary-matrix .product-cell,
    .sales-summary-matrix .subtotal-cell,
    .sales-summary-matrix .total-cell {
        min-width: 120px;
        text-align: right;
    }
    .sales-summary-matrix .subtotal-cell,
    .sales-summary-matrix .total-cell,
    .sales-summary-matrix tfoot td {
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
            <h3 class="mb-1">Production vs Sales Analysis</h3>
            <p class="text-muted mb-1"><?php echo h($classOptions[$prodSalesClass]); ?> production <?php echo h($reportRangeLabel); ?></p>
            <p class="text-muted small mb-0">Generated: <?php echo h($generatedAt); ?></p>
        </div>
        <div class="col-lg-6 mt-3 mt-lg-0">
            <div class="d-flex flex-column gap-3 align-items-lg-end">
                <form class="row g-2 align-items-end justify-content-lg-end" method="get" novalidate>
                    <div class="col-sm-5 col-md-4">
                        <label for="start-date" class="form-label text-muted small mb-1">Start Date</label>
                        <input type="date" id="start-date" name="start" class="form-control" value="<?php echo h($startDate); ?>">
                    </div>
                    <div class="col-sm-5 col-md-4">
                        <label for="end-date" class="form-label text-muted small mb-1">End Date</label>
                        <input type="date" id="end-date" name="end" class="form-control" value="<?php echo h($endDate); ?>">
                    </div>
                    <div class="col-sm-5 col-md-4">
                        <label for="prod-sales-class" class="form-label text-muted small mb-1">Product Type</label>
                        <select id="prod-sales-class" name="prod_sales_class" class="form-select">
                            <?php foreach ($classOptions as $classValue => $classLabel): ?>
                                <option value="<?php echo h($classValue); ?>" <?php echo $prodSalesClass === $classValue ? 'selected' : ''; ?>>
                                    <?php echo h($classLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">View</button>
                    </div>
                    <div class="col-auto">
                        <a class="btn btn-outline-secondary" href="print_production_vs_sales.php?start=<?php echo urlencode($startDate); ?>&end=<?php echo urlencode($endDate); ?>&amp;prod_sales_class=<?php echo urlencode($prodSalesClass); ?>">
                            Export Excel
                        </a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered summary-table mb-0">
                        <tbody>
                            <tr>
                                <th class="text-uppercase small text-muted">Total Production Qty (Kg)</th>
                                <td class="value"><?php echo number_format($totalProductionQty, 2); ?></td>
                            </tr>
                            <tr>
                                <th class="text-uppercase small text-muted">Total Production Hours</th>
                                <td class="value"><?php echo number_format($totalProductionHours, 2); ?></td>
                            </tr>
                            <tr>
                                <th class="text-uppercase small text-muted">Production Entries</th>
                                <td class="value"><?php echo number_format(count($productionRows)); ?></td>
                            </tr>
                            <tr>
                                <th class="text-uppercase small text-muted">Average Daily Production (Kg)</th>
                                <td class="value"><?php echo number_format($averageDailyProduction, 2); ?></td>
                            </tr>
                            <tr>
                                <th class="text-uppercase small text-muted">Total Sales Qty (Kg)</th>
                                <td class="value"><?php echo number_format($totalSalesQty, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if ($pageError !== null): ?>
        <div class="alert alert-danger"><?php echo h($pageError); ?></div>
    <?php else: ?>
        <div class="card custom-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Production Analysis</h5>
                <span class="text-muted small"><?php echo number_format(count($productionMatrixRows)); ?> day(s)</span>
            </div>
            <div class="card-body">
                <?php if (empty($machineColumns) && empty($salesProductColumns)): ?>
                    <p class="text-muted mb-0">No production machines or sales products were found for the selected product type.</p>
                <?php else: ?>
                    <div class="mb-4">
                        <div class="production-summary-title">DAILY PROD SALES ANALYSIS - BY PRODUCT CLASS</div>
                        <div class="production-summary-date"><?php echo h($productionHeading); ?></div>
                    </div>
                    <div class="table-responsive production-analysis-table" style="overflow-x:auto;">
                        <table id="production-analysis-table" class="table table-striped production-summary-matrix align-middle mb-0 w-100">
                            <thead>
                                <tr>
                                    <th rowspan="5">DATE</th>
                                    <th colspan="<?php echo (int)$measureColumnCount; ?>">IN (IN KG)</th>
                                    <?php if (!empty($salesProductColumns)): ?>
                                        <th colspan="<?php echo (int)$salesColumnCount; ?>">SALES</th>
                                    <?php endif; ?>
                                </tr>
                                <tr>
                                    <th colspan="<?php echo (int)$measureColumnCount; ?>">PRODUCTION</th>
                                    <?php if (!empty($salesProductColumns)): ?>
                                        <th colspan="<?php echo (int)$salesColumnCount; ?>"><?php echo h($prodSalesClass); ?></th>
                                    <?php endif; ?>
                                </tr>
                                <tr>
                                    <th colspan="<?php echo (int)$measureColumnCount; ?>"><?php echo h($prodSalesClass); ?></th>
                                    <?php if (!empty($salesProductColumns)): ?>
                                        <?php foreach ($salesProductGroups as $salesGroup): ?>
                                            <th colspan="<?php echo count($salesGroup['products']); ?>"><?php echo h($salesGroup['label']); ?></th>
                                            <th></th>
                                        <?php endforeach; ?>
                                        <th></th>
                                        <th></th>
                                    <?php endif; ?>
                                </tr>
                                <tr>
                                    <?php foreach ($machineColumns as $machineColumn): ?>
                                        <th colspan="2"><?php echo h($machineColumn['machine_label']); ?></th>
                                    <?php endforeach; ?>
                                    <th rowspan="2">TOTAL</th>
                                    <?php if (!empty($salesProductColumns)): ?>
                                        <?php foreach ($salesProductGroups as $salesGroup): ?>
                                            <?php foreach ($salesGroup['products'] as $salesProduct): ?>
                                                <th><?php echo h($salesProduct['product_code']); ?></th>
                                            <?php endforeach; ?>
                                            <th rowspan="2">SUB-TOTAL</th>
                                        <?php endforeach; ?>
                                        <th rowspan="2">TOTAL</th>
                                        <th rowspan="2">INVENTORY VARIANCE</th>
                                    <?php endif; ?>
                                </tr>
                                <tr>
                                    <?php foreach ($machineColumns as $machineColumn): ?>
                                        <th>HRS</th>
                                        <th>QTY</th>
                                    <?php endforeach; ?>
                                    <?php if (!empty($salesProductColumns)): ?>
                                        <?php foreach ($salesProductGroups as $salesGroup): ?>
                                            <?php foreach ($salesGroup['products'] as $salesProduct): ?>
                                                <th>
                                                    <?php echo nl2br(h(prodSalesFormatSalesProductHeader($salesProduct))); ?>
                                                </th>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productionMatrixRows as $matrixRow): ?>
                                    <?php
                                        $salesMatrixRow = isset($salesMatrixByDate[$matrixRow['date']])
                                            ? $salesMatrixByDate[$matrixRow['date']]
                                            : ['products' => []];
                                    ?>
                                    <tr>
                                        <td class="date-cell"><?php echo h($matrixRow['date_label']); ?></td>
                                        <?php $dailySubtotalQty = 0.0; ?>
                                        <?php foreach ($machineColumns as $machineColumn): ?>
                                            <?php
                                                $cell = isset($matrixRow['machines'][$machineColumn['key']]) ? $matrixRow['machines'][$machineColumn['key']] : ['hours' => 0.0, 'qty' => 0.0];
                                                $dailySubtotalQty += isset($cell['qty']) ? (float)$cell['qty'] : 0.0;
                                            ?>
                                            <td class="measure-cell"><?php echo prodSalesFormatMatrixNumber($cell['hours']); ?></td>
                                            <td class="measure-cell"><?php echo prodSalesFormatMatrixNumber($cell['qty']); ?></td>
                                        <?php endforeach; ?>
                                        <td class="subtotal-cell"><?php echo prodSalesFormatMatrixNumber($dailySubtotalQty); ?></td>
                                        <?php if (!empty($salesProductColumns)): ?>
                                            <?php $dailySalesGrandTotal = 0.0; ?>
                                            <?php foreach ($salesProductGroups as $salesGroup): ?>
                                                <?php $dailySalesGroupTotal = 0.0; ?>
                                                <?php foreach ($salesGroup['products'] as $salesProduct): ?>
                                                    <?php
                                                        $productQty = isset($salesMatrixRow['products'][$salesProduct['key']]) ? (float)$salesMatrixRow['products'][$salesProduct['key']] : 0.0;
                                                        $dailySalesGroupTotal += $productQty;
                                                        $dailySalesGrandTotal += $productQty;
                                                    ?>
                                                    <td class="product-cell"><?php echo prodSalesFormatSalesMatrixNumber($productQty); ?></td>
                                                <?php endforeach; ?>
                                                <td class="subtotal-cell"><?php echo prodSalesFormatSalesMatrixNumber($dailySalesGroupTotal); ?></td>
                                            <?php endforeach; ?>
                                            <td class="total-cell"><?php echo prodSalesFormatSalesMatrixNumber($dailySalesGrandTotal, true); ?></td>
                                            <td class="total-cell"><?php echo prodSalesFormatSalesMatrixNumber($dailySubtotalQty - $dailySalesGrandTotal, true); ?></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="date-cell">TOTAL</td>
                                    <?php foreach ($machineColumns as $machineColumn): ?>
                                        <?php
                                            $machineHoursTotal = 0.0;
                                            $machineQtyTotal = 0.0;
                                            foreach ($productionMatrixRows as $matrixRow) {
                                                $cell = isset($matrixRow['machines'][$machineColumn['key']]) ? $matrixRow['machines'][$machineColumn['key']] : ['hours' => 0.0, 'qty' => 0.0];
                                                $machineHoursTotal += isset($cell['hours']) ? (float)$cell['hours'] : 0.0;
                                                $machineQtyTotal += isset($cell['qty']) ? (float)$cell['qty'] : 0.0;
                                            }
                                        ?>
                                        <td class="measure-cell"><?php echo prodSalesFormatMatrixNumber($machineHoursTotal); ?></td>
                                        <td class="measure-cell"><?php echo prodSalesFormatMatrixNumber($machineQtyTotal); ?></td>
                                    <?php endforeach; ?>
                                    <td class="subtotal-cell"><?php echo prodSalesFormatMatrixNumber($totalProductionQty); ?></td>
                                    <?php if (!empty($salesProductColumns)): ?>
                                        <?php $grandSalesTotal = 0.0; ?>
                                        <?php foreach ($salesProductGroups as $salesGroup): ?>
                                            <?php $groupGrandTotal = 0.0; ?>
                                            <?php foreach ($salesGroup['products'] as $salesProduct): ?>
                                                <?php
                                                    $productGrandTotal = 0.0;
                                                    foreach ($salesMatrixRows as $salesMatrixRow) {
                                                        $productGrandTotal += isset($salesMatrixRow['products'][$salesProduct['key']]) ? (float)$salesMatrixRow['products'][$salesProduct['key']] : 0.0;
                                                    }
                                                    $groupGrandTotal += $productGrandTotal;
                                                    $grandSalesTotal += $productGrandTotal;
                                                ?>
                                                <td class="product-cell"><?php echo prodSalesFormatSalesMatrixNumber($productGrandTotal, true); ?></td>
                                            <?php endforeach; ?>
                                            <td class="subtotal-cell"><?php echo prodSalesFormatSalesMatrixNumber($groupGrandTotal, true); ?></td>
                                        <?php endforeach; ?>
                                        <td class="total-cell"><?php echo prodSalesFormatSalesMatrixNumber($grandSalesTotal, true); ?></td>
                                        <td class="total-cell"><?php echo prodSalesFormatSalesMatrixNumber($totalProductionQty - $grandSalesTotal, true); ?></td>
                                    <?php endif; ?>
                                </tr>
                            </tfoot>
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
        function initProductionAnalysisTable() {
            if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) {
                return;
            }
            var table = jQuery('#production-analysis-table');
            if (!table.length || jQuery.fn.DataTable.isDataTable(table)) {
                return;
            }
            table.DataTable({
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
                initComplete: disableProductionAnalysisSearchAutofill
            });
            table.on('draw.dt', disableProductionAnalysisSearchAutofill);
        }

        function disableProductionAnalysisSearchAutofill() {
            var filterInput = document.querySelector('#production-analysis-table_filter input');
            if (!filterInput) {
                return;
            }
            filterInput.setAttribute('name', 'production_analysis_filter');
            filterInput.setAttribute('autocomplete', 'off');
            filterInput.setAttribute('autocorrect', 'off');
            filterInput.setAttribute('autocapitalize', 'off');
            filterInput.setAttribute('spellcheck', 'false');
            filterInput.setAttribute('data-lpignore', 'true');
            filterInput.setAttribute('data-1p-ignore', 'true');
            filterInput.setAttribute('data-form-type', 'other');
        }

        document.addEventListener('DOMContentLoaded', initProductionAnalysisTable);
    </script>
    <?php
    return ob_get_clean();
};
$scripts = $scriptsBuffer();

include 'layouts/base.php';
