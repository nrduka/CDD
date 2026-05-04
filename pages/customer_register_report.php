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

if (
    !isset($mysqli)
    || !($mysqli instanceof mysqli)
    || (function_exists('mysqli_ping') && !@mysqli_ping($mysqli))
) {
    $fallbackHost = isset($databaseHost) ? (string)$databaseHost : 'localhost';
    $fallbackName = isset($databaseName) ? (string)$databaseName : 'cdd';
    $fallbackUser = isset($databaseUsername) ? (string)$databaseUsername : 'root';
    $fallbackPass = isset($databasePassword) ? (string)$databasePassword : '';
    $fallbackMysqli = @mysqli_connect($fallbackHost, $fallbackUser, $fallbackPass, $fallbackName);
    if ($fallbackMysqli instanceof mysqli) {
        $mysqli = $fallbackMysqli;
    }
}

$pageError = null;
$sourceColumns = [];
$customerRows = [];
$generatedAt = date('Y-m-d H:i:s');

function customerRegisterBuildName($row)
{
    $storeName = trim((string)(isset($row['storeName']) ? $row['storeName'] : ''));
    if ($storeName !== '') {
        return $storeName;
    }

    $nameParts = [];
    foreach (['firstName', 'middleName', 'lastName'] as $field) {
        $value = trim((string)(isset($row[$field]) ? $row[$field] : ''));
        if ($value !== '') {
            $nameParts[] = $value;
        }
    }

    return trim(implode(' ', $nameParts));
}

function customerRegisterFetchSources($mysqli)
{
    $sources = [];
    $sql = "SELECT customer_source, customer_channel
            FROM source_channel
            ORDER BY customer_source, customer_channel";
    $result = $mysqli->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $sourceId = trim((string)(isset($row['customer_source']) ? $row['customer_source'] : ''));
            if ($sourceId === '') {
                continue;
            }
            $sources[$sourceId] = [
                'source_id' => $sourceId,
                'label' => trim((string)(isset($row['customer_channel']) ? $row['customer_channel'] : ''))
            ];
            if ($sources[$sourceId]['label'] === '') {
                $sources[$sourceId]['label'] = 'SOURCE ' . $sourceId;
            }
        }
        $result->free();
    }

    return array_values($sources);
}

function customerRegisterFetchCustomers($mysqli, $sourceColumns)
{
    $rows = [];
    $sourceLookup = [];
    foreach ($sourceColumns as $sourceColumn) {
        $sourceLookup[(string)$sourceColumn['source_id']] = true;
    }

    $sql = "SELECT c.customer_code,
                   c.firstName,
                   c.middleName,
                   c.lastName,
                   c.address,
                   c.storeName,
                   c.area,
                   c.customer_source,
                   sc.customer_channel
            FROM customer_register c
            LEFT JOIN source_channel sc ON sc.customer_source = c.customer_source
            ORDER BY c.customer_source, c.storeName, c.firstName, c.lastName, c.customer_code";
    $result = $mysqli->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $sourceId = trim((string)(isset($row['customer_source']) ? $row['customer_source'] : ''));
            if ($sourceId !== '' && !isset($sourceLookup[$sourceId])) {
                $sourceLookup[$sourceId] = true;
            }
            $rows[] = [
                'customer_name' => customerRegisterBuildName($row),
                'customer_code' => trim((string)(isset($row['customer_code']) ? $row['customer_code'] : '')),
                'address' => trim((string)(isset($row['address']) ? $row['address'] : '')),
                'area' => trim((string)(isset($row['area']) ? $row['area'] : '')),
                'source_id' => $sourceId
            ];
        }
        $result->free();
    }

    return $rows;
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    $pageError = 'Database connection unavailable.';
} else {
    $sourceColumns = customerRegisterFetchSources($mysqli);
    $customerRows = customerRegisterFetchCustomers($mysqli, $sourceColumns);
    $knownSources = [];
    foreach ($sourceColumns as $sourceColumn) {
        $knownSources[(string)$sourceColumn['source_id']] = true;
    }
    foreach ($customerRows as $row) {
        $sourceId = trim((string)$row['source_id']);
        if ($sourceId !== '' && !isset($knownSources[$sourceId])) {
            $knownSources[$sourceId] = true;
            $sourceColumns[] = [
                'source_id' => $sourceId,
                'label' => 'SOURCE ' . $sourceId
            ];
        }
    }
}

$sourceTotals = [];
foreach ($sourceColumns as $sourceColumn) {
    $sourceTotals[(string)$sourceColumn['source_id']] = 0;
}
foreach ($customerRows as $row) {
    $sourceId = (string)$row['source_id'];
    if ($sourceId !== '' && isset($sourceTotals[$sourceId])) {
        $sourceTotals[$sourceId]++;
    }
}

ob_start();
?>
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
<style>
    .customer-register-table .dataTables_wrapper,
    .customer-register-table .dataTables_scroll,
    .customer-register-table .dataTables_scrollHead,
    .customer-register-table .dataTables_scrollBody,
    .customer-register-table .dataTables_scrollHeadInner,
    .customer-register-table .dataTables_scrollHeadInner table,
    .customer-register-table .dataTables_scrollBody table {
        width: 100% !important;
    }
    .customer-register-table table {
        min-width: 1200px;
        white-space: nowrap;
    }
    .customer-register-table th {
        text-align: center;
        vertical-align: middle;
    }
    .customer-register-table .source-header {
        background-color: #e5e7eb;
        font-weight: 700;
    }
</style>
<?php
$styles = ob_get_clean();

ob_start();
?>
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-lg-7">
            <h3 class="mb-1">Customer Register</h3>
            <p class="text-muted mb-0">Customer masterlist grouped by source channel.</p>
            <p class="text-muted small mb-0">Generated: <?php echo htmlspecialchars($generatedAt); ?></p>
        </div>
        <div class="col-lg-5 mt-3 mt-lg-0 text-lg-end">
            <a class="btn btn-outline-secondary" href="print_customer_register_report.php">Export Excel</a>
        </div>
    </div>

    <?php if ($pageError !== null): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($pageError); ?></div>
    <?php else: ?>
        <div class="card custom-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Customer Register</h5>
                <span class="text-muted small"><?php echo count($customerRows); ?> record(s)</span>
            </div>
            <div class="card-body">
                <?php if (empty($customerRows)): ?>
                    <p class="text-muted mb-0">No customers found.</p>
                <?php else: ?>
                    <div class="table-responsive customer-register-table">
                        <table id="customer-register-table" class="table table-striped table-bordered align-middle mb-0 w-100">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <?php if (!empty($sourceColumns)): ?>
                                        <th colspan="<?php echo count($sourceColumns); ?>" class="source-header">Source Channel</th>
                                    <?php endif; ?>
                                </tr>
                                <tr>
                                    <th rowspan="2">No</th>
                                    <th rowspan="2">Customer Name</th>
                                    <th rowspan="2">Customer Code</th>
                                    <th rowspan="2">Address</th>
                                    <th rowspan="2">Area</th>
                                    <?php foreach ($sourceColumns as $sourceColumn): ?>
                                        <th><?php echo htmlspecialchars($sourceColumn['source_id']); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                                <?php if (!empty($sourceColumns)): ?>
                                    <tr>
                                        <?php foreach ($sourceColumns as $sourceColumn): ?>
                                            <th><?php echo htmlspecialchars($sourceColumn['label']); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endif; ?>
                            </thead>
                            <tbody>
                                <?php foreach ($customerRows as $index => $row): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['customer_code']); ?></td>
                                        <td><?php echo htmlspecialchars($row['address']); ?></td>
                                        <td><?php echo htmlspecialchars($row['area']); ?></td>
                                        <?php foreach ($sourceColumns as $sourceColumn): ?>
                                            <td class="text-center"><?php echo (string)$row['source_id'] === (string)$sourceColumn['source_id'] ? '1' : ''; ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td colspan="5">Total Count</td>
                                    <?php foreach ($sourceColumns as $sourceColumn): ?>
                                        <?php $sourceId = (string)$sourceColumn['source_id']; ?>
                                        <td class="text-center"><?php echo number_format(isset($sourceTotals[$sourceId]) ? (int)$sourceTotals[$sourceId] : 0); ?></td>
                                    <?php endforeach; ?>
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
        document.addEventListener('DOMContentLoaded', function () {
            var table = document.getElementById('customer-register-table');
            if (!table || !window.jQuery || !jQuery.fn.DataTable) { return; }
            jQuery(table).DataTable({
                autoWidth: false,
                pageLength: 25,
                lengthChange: false,
                ordering: false,
                paging: true,
                scrollX: true,
                dom: "<'row align-items-center mb-2'<'col-sm-6'f><'col-sm-6 text-end'>>rt<'row align-items-center mt-2'<'col-sm-6'i><'col-sm-6'p>>",
                language: {
                    search: 'Filter:',
                    emptyTable: 'No customers found.',
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
