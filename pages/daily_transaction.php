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

$isVoidRequest = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';

function send_daily_transaction_json_response($payload, $statusCode = 200)
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

if ($isVoidRequest) {
    if (!isset($mysqli) || !$mysqli) {
        send_daily_transaction_json_response(['success' => false, 'message' => 'Database connection unavailable.'], 500);
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        send_daily_transaction_json_response(['success' => false, 'message' => 'Invalid request payload.'], 400);
    }

    $action = trim((string)(isset($payload['action']) ? $payload['action'] : ''));
    if ($action !== 'void_order') {
        send_daily_transaction_json_response(['success' => false, 'message' => 'Unsupported action.'], 400);
    }
    $redirectToPos = isset($payload['redirect']) ? (bool)$payload['redirect'] : true;

    $orderNo = trim((string)(isset($payload['order_no']) ? $payload['order_no'] : ''));
    $remarks = trim((string)(isset($payload['remarks']) ? $payload['remarks'] : ''));
    $supervisorUsername = trim((string)(isset($payload['supervisor_username']) ? $payload['supervisor_username'] : ''));
    $supervisorPassword = trim((string)(isset($payload['supervisor_password']) ? $payload['supervisor_password'] : ''));

    if ($orderNo === '' || $remarks === '' || $supervisorUsername === '' || $supervisorPassword === '') {
        send_daily_transaction_json_response(['success' => false, 'message' => 'Order number, reason, and admin credentials are required.'], 422);
    }

    if (strlen($remarks) > 100) {
        $remarks = substr($remarks, 0, 100);
    }

    $supervisorStmt = $mysqli->prepare("SELECT username, role, firstname, lastname FROM user WHERE username = ? AND password = ? LIMIT 1");
    if (!$supervisorStmt) {
        send_daily_transaction_json_response(['success' => false, 'message' => 'Unable to validate admin credentials.'], 500);
    }
    $supervisorStmt->bind_param('ss', $supervisorUsername, $supervisorPassword);
    if (!$supervisorStmt->execute()) {
        $supervisorStmt->close();
        send_daily_transaction_json_response(['success' => false, 'message' => 'Unable to validate admin credentials.'], 500);
    }
    $supervisorResult = $supervisorStmt->get_result();
    $supervisorRow = $supervisorResult ? $supervisorResult->fetch_assoc() : null;
    $supervisorStmt->close();

    if (!$supervisorRow) {
        send_daily_transaction_json_response(['success' => false, 'message' => 'Admin credentials are invalid.'], 403);
    }

    $supervisorRole = strtoupper(trim((string)(isset($supervisorRow['role']) ? $supervisorRow['role'] : '')));
    $allowedSupervisorRoles = ['ADMIN'];
    if (!in_array($supervisorRole, $allowedSupervisorRoles, true)) {
        send_daily_transaction_json_response(['success' => false, 'message' => 'Admin access is required to void orders.'], 403);
    }

    $authorizedBy = trim((string)(isset($supervisorRow['firstname']) ? $supervisorRow['firstname'] : '') . ' ' . (string)(isset($supervisorRow['lastname']) ? $supervisorRow['lastname'] : ''));
    if ($authorizedBy === '') {
        $authorizedBy = trim((string)(isset($supervisorRow['username']) ? $supervisorRow['username'] : ''));
    }

    $voidByUser = '';
    $sessionUserId = isset($_SESSION['sess_user_id']) ? (int)$_SESSION['sess_user_id'] : 0;
    if ($sessionUserId > 0) {
        $voidUserStmt = $mysqli->prepare("SELECT username, firstname, lastname FROM user WHERE id = ? LIMIT 1");
        if ($voidUserStmt) {
            $voidUserStmt->bind_param('i', $sessionUserId);
            if ($voidUserStmt->execute()) {
                $voidUserResult = $voidUserStmt->get_result();
                $voidUserRow = $voidUserResult ? $voidUserResult->fetch_assoc() : null;
                if ($voidUserRow) {
                    $voidByUser = trim((string)(isset($voidUserRow['username']) ? $voidUserRow['username'] : ''));
                    if ($voidByUser === '') {
                        $voidByUser = trim((string)(isset($voidUserRow['firstname']) ? $voidUserRow['firstname'] : '') . ' ' . (string)(isset($voidUserRow['lastname']) ? $voidUserRow['lastname'] : ''));
                    }
                }
            }
            $voidUserStmt->close();
        }
    }
    if ($voidByUser === '') {
        $voidByUser = trim((string)(isset($_SESSION['sess_firstname']) ? $_SESSION['sess_firstname'] : '') . ' ' . (string)(isset($_SESSION['sess_lastname']) ? $_SESSION['sess_lastname'] : ''));
    }

    $checkStmt = $mysqli->prepare("SELECT COUNT(*) AS total_rows, SUM(CASE WHEN void_status = 1 THEN 1 ELSE 0 END) AS voided_rows FROM sales WHERE order_no = ?");
    if (!$checkStmt) {
        send_daily_transaction_json_response(['success' => false, 'message' => 'Unable to verify order status.'], 500);
    }
    $checkStmt->bind_param('s', $orderNo);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkRow = $checkResult ? $checkResult->fetch_assoc() : null;
    $checkStmt->close();

    if (!$checkRow || (int)$checkRow['total_rows'] === 0) {
        send_daily_transaction_json_response(['success' => false, 'message' => 'Order not found.'], 404);
    }
    if ((int)$checkRow['voided_rows'] > 0) {
        send_daily_transaction_json_response(['success' => false, 'message' => 'Order is already voided.'], 409);
    }

    $updateStmt = $mysqli->prepare("UPDATE sales SET void_status = 1, void_remarks = ?, void_authorized_by = ?, void_by_user = ?, datetime_void = NOW() WHERE order_no = ? AND (void_status IS NULL OR void_status = 0)");
    if (!$updateStmt) {
        send_daily_transaction_json_response(['success' => false, 'message' => 'Unable to void order.'], 500);
    }
    $updateStmt->bind_param('ssss', $remarks, $authorizedBy, $voidByUser, $orderNo);
    if (!$updateStmt->execute()) {
        $updateStmt->close();
        send_daily_transaction_json_response(['success' => false, 'message' => 'Unable to void order.'], 500);
    }
    $affectedRows = $updateStmt->affected_rows;
    $updateStmt->close();

    if ($affectedRows <= 0) {
        send_daily_transaction_json_response(['success' => false, 'message' => 'Order is already voided.'], 409);
    }

    if ($redirectToPos) {
        $redirectUrl = $baseUrl . '/pages/pos_terminal.php?void_order_no=' . urlencode($orderNo);
        send_daily_transaction_json_response(['success' => true, 'redirect_url' => $redirectUrl]);
    }

    send_daily_transaction_json_response(['success' => true, 'message' => 'Order is now voided.']);
}

$reportDateInput = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
$reportDate = $reportDateInput !== '' ? $reportDateInput : date('Y-m-d');
$reportDateObject = DateTime::createFromFormat('Y-m-d', $reportDate);
if (!$reportDateObject || $reportDateObject->format('Y-m-d') !== $reportDate) {
    $reportDate = date('Y-m-d');
}

function buildCustomerDisplayName($row)
{
    $storeName = trim((string)(isset($row['storeName']) ? $row['storeName'] : ''));
    if ($storeName !== '') {
        return $storeName;
    }

    $firstName = trim((string)(isset($row['firstName']) ? $row['firstName'] : ''));
    $middleName = trim((string)(isset($row['middleName']) ? $row['middleName'] : ''));
    $lastName = trim((string)(isset($row['lastName']) ? $row['lastName'] : ''));
    $nameParts = [];
    if ($firstName !== '') {
        $nameParts[] = $firstName;
    }
    if ($middleName !== '') {
        $nameParts[] = $middleName;
    }
    if ($lastName !== '') {
        $nameParts[] = $lastName;
    }

    $displayName = trim(implode(' ', $nameParts));
    $customerCode = trim((string)(isset($row['customer_code']) ? $row['customer_code'] : ''));
    if ($displayName === '' && $customerCode !== '') {
        $displayName = $customerCode;
    }

    return $displayName;
}

$pageError = null;
$orders = [];
$orderList = [];
$grandTotal = 0;
$generatedAt = date('Y-m-d H:i:s');

if (!isset($mysqli) || !$mysqli) {
    $pageError = 'Database connection unavailable.';
} else {
    $sql = "SELECT s.order_no,
                   s.customer_code,
                   s.delivery_instruction,
                   s.lineitem,
                   s.qty,
                   s.product_code,
                   s.product_description,
                   s.lineitem_discount,
                   s.discount,
                   s.unit_price,
                   s.amount,
                   s.date_delivery,
                   s.time_delivery,
                   s.date_created,
                   s.amt_received,
                   s.amt_change,
                   s.void_status,
                   s.void_remarks,
                   s.void_authorized_by,
                   c.storeName,
                   c.address,
                   c.firstName,
                   c.middleName,
                   c.lastName,
                   p.uom
            FROM sales s
            LEFT JOIN customer_register c ON c.customer_code = s.customer_code
            LEFT JOIN ice_product p ON p.product_code = s.product_code
            WHERE DATE(s.date_created) = ?
            ORDER BY s.order_no, s.lineitem";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        $pageError = 'Unable to load transactions.';
    } else {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $orderNo = trim((string)(isset($row['order_no']) ? $row['order_no'] : ''));
                if ($orderNo === '') {
                    $orderNo = 'UNKNOWN';
                }

                if (!isset($orders[$orderNo])) {
                    $orders[$orderNo] = [
                        'order_no' => $orderNo,
                        'customer_code' => trim((string)(isset($row['customer_code']) ? $row['customer_code'] : '')),
                        'customer_name' => buildCustomerDisplayName($row),
                        'customer_address' => trim((string)(isset($row['address']) ? $row['address'] : '')),
                        'delivery_instruction' => trim((string)(isset($row['delivery_instruction']) ? $row['delivery_instruction'] : '')),
                        'date_delivery' => (string)(isset($row['date_delivery']) ? $row['date_delivery'] : ''),
                        'time_delivery' => (string)(isset($row['time_delivery']) ? $row['time_delivery'] : ''),
                        'date_created' => (string)(isset($row['date_created']) ? $row['date_created'] : ''),
                        'discount' => isset($row['discount']) ? (float)$row['discount'] : 0.0,
                        'amt_received' => isset($row['amt_received']) ? (float)$row['amt_received'] : 0.0,
                        'amt_change' => isset($row['amt_change']) ? (float)$row['amt_change'] : 0.0,
                        'void_status' => isset($row['void_status']) ? (int)$row['void_status'] : 0,
                        'void_remarks' => trim((string)(isset($row['void_remarks']) ? $row['void_remarks'] : '')),
                        'void_authorized_by' => trim((string)(isset($row['void_authorized_by']) ? $row['void_authorized_by'] : '')),
                        'items' => [],
                        'total_qty' => 0,
                        'total_amount' => 0
                    ];
                }

                $qty = isset($row['qty']) ? (float)$row['qty'] : 0.0;
                $unitPrice = isset($row['unit_price']) ? (float)$row['unit_price'] : 0.0;
                $lineDiscount = isset($row['lineitem_discount']) ? (float)$row['lineitem_discount'] : 0.0;
                $amount = isset($row['amount']) ? (float)$row['amount'] : 0.0;
                $gross = $qty * $unitPrice;
                $unitDiscount = $qty > 0 ? $lineDiscount / $qty : 0.0;

                $orders[$orderNo]['items'][] = [
                    'code' => (string)(isset($row['product_code']) ? $row['product_code'] : ''),
                    'description' => (string)(isset($row['product_description']) ? $row['product_description'] : ''),
                    'uom' => (string)(isset($row['uom']) ? $row['uom'] : ''),
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'unit_discount' => $unitDiscount,
                    'line_discount' => $lineDiscount,
                    'gross_amount' => $gross,
                    'amount' => $amount
                ];

                $orders[$orderNo]['total_qty'] += $qty;
                $orders[$orderNo]['total_amount'] += $amount;
                $grandTotal += $amount;

                if (isset($row['void_status']) && (int)$row['void_status'] === 1) {
                    $orders[$orderNo]['void_status'] = 1;
                }
                $voidRemarks = trim((string)(isset($row['void_remarks']) ? $row['void_remarks'] : ''));
                if ($orders[$orderNo]['void_remarks'] === '' && $voidRemarks !== '') {
                    $orders[$orderNo]['void_remarks'] = $voidRemarks;
                }
                $voidAuthorizedBy = trim((string)(isset($row['void_authorized_by']) ? $row['void_authorized_by'] : ''));
                if ($orders[$orderNo]['void_authorized_by'] === '' && $voidAuthorizedBy !== '') {
                    $orders[$orderNo]['void_authorized_by'] = $voidAuthorizedBy;
                }
            }
            $result->free();
        }
        $stmt->close();
    }
}

$orderList = array_values($orders);
$reportDateLabel = date('F d, Y', strtotime($reportDate));

$reportMeta = [
    'report_date' => $reportDate,
    'generated_at' => $generatedAt,
    'records' => count($orderList),
    'grand_total' => $grandTotal
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
    .pos-receipt {
        width: 80mm;
        max-width: 80mm;
        margin: 0 auto 16px;
        padding: 10px 12px;
        border: 1px dashed #94a3b8;
        background: #fff;
        font-family: "Courier New", Courier, monospace;
        font-size: 11px;
        color: #0f172a;
    }
    .pos-receipt-text {
        white-space: pre;
        margin: 0;
        font-size: 11px;
        line-height: 1.35;
        font-weight: 600;
    }
    .pos-receipt:last-child {
        margin-bottom: 0;
    }
    .pos-receipt-spacer {
        width: 80mm;
        max-width: 80mm;
        margin: 0 auto 16px;
        height: 12px;
    }
    .pos-print-area {
        position: absolute;
        left: -9999px;
        top: 0;
    }
    .daily-transaction-table .dataTables_wrapper,
    .daily-transaction-table .dataTables_scroll,
    .daily-transaction-table .dataTables_scrollHead,
    .daily-transaction-table .dataTables_scrollBody,
    .daily-transaction-table .dataTables_scrollHeadInner,
    .daily-transaction-table .dataTables_scrollHeadInner table,
    .daily-transaction-table .dataTables_scrollBody table {
        width: 100% !important;
    }
    @media print {
        @page {
            size: 80mm auto;
            margin: 0;
        }
        html,
        body {
            margin: 0;
            padding: 0;
        }
        body * {
            visibility: hidden;
        }
        #daily-transaction-print-area,
        #daily-transaction-print-area * {
            visibility: visible;
        }
        #daily-transaction-print-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 80mm;
        }
        .pos-receipt {
            border: none;
            margin: 0;
            padding: 0;
        }
        .pos-receipt-spacer {
            display: none;
            margin: 0;
        }
    }
</style>
<?php
$styles = ob_get_clean();

ob_start();
?>
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-lg-6">
            <h3 class="mb-1">Daily Transaction</h3>
            <p class="text-muted mb-0">Orders recorded on <?php echo htmlspecialchars($reportDateLabel); ?>.</p>
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
                    <a class="btn btn-outline-secondary" href="print_daily_transaction.php?date=<?php echo urlencode($reportMeta['report_date']); ?>">
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
                    <span class="label">Total Orders</span>
                    <h4><?php echo number_format($reportMeta['records']); ?></h4>
                    <p class="text-muted mb-0">Orders captured for <?php echo htmlspecialchars($reportDateLabel); ?>.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-card">
                    <span class="label">Grand Total</span>
                    <h4><?php echo number_format($reportMeta['grand_total'], 2); ?></h4>
                    <p class="text-muted mb-0">Sum of all order amounts.</p>
                </div>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Daily Transactions</h5>
                <span class="text-muted small"><?php echo $reportMeta['records']; ?> order(s)</span>
            </div>
            <div class="card-body">
                <?php if (empty($orderList)): ?>
                    <p class="text-muted mb-0">No transactions were recorded for the selected date.</p>
                <?php else: ?>
                    <div class="table-responsive daily-transaction-table">
                        <table id="daily-transaction-table" class="table table-striped align-middle mb-0 w-100">
                            <thead>
                                <tr>
                                    <th>Order No</th>
                                    <th>Customer</th>
                                    <th class="text-end">Total Qty</th>
                                    <th class="text-end">Total Amount</th>
                                    <th>Date Created</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderList as $order): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">
                                                <?php echo htmlspecialchars('DR' . $order['order_no']); ?>
                                                <?php if (!empty($order['void_status'])): ?>
                                                    <span class="badge bg-danger ms-2">Voided</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($order['customer_name'] !== '' ? $order['customer_name'] : $order['customer_code']); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($order['customer_code']); ?></div>
                                        </td>
                                        <td class="text-end"><?php echo number_format($order['total_qty'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($order['date_created']); ?></td>
                                        <td class="text-center">
                                            <div class="d-inline-flex flex-wrap gap-2 justify-content-center">
                                                <button type="button" class="btn btn-sm btn-outline-primary pos-print-order" data-order-no="<?php echo htmlspecialchars($order['order_no']); ?>">
                                                    Print
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger pos-void-order" data-order-no="<?php echo htmlspecialchars($order['order_no']); ?>" <?php echo !empty($order['void_status']) ? 'disabled aria-disabled="true"' : ''; ?>>
                                                    Void
                                                </button>
                                            </div>
                                        </td>
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

<div id="daily-transaction-print-area" class="pos-print-area" aria-hidden="true"></div>

<div class="modal fade" id="void-order-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Void Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Order: <span id="void-order-label" class="fw-semibold"></span></p>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="void-reason">Reason for voiding</label>
                    <textarea class="form-control" id="void-reason" rows="3" maxlength="100" placeholder="Enter reason for voiding"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="void-admin-username">Admin Username</label>
                    <input type="text" class="form-control" id="void-admin-username" name="void_admin_user" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-form-type="other" placeholder="Admin username">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="void-admin-password">Admin Password</label>
                    <input type="password" class="form-control" id="void-admin-password" name="void_admin_pass" autocomplete="new-password" data-lpignore="true" data-1p-ignore="true" data-form-type="other" placeholder="Admin password">
                </div>
                <div class="alert alert-danger d-none" id="void-order-error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-danger" id="void-order-cancel-only">Cancel Order</button>
                <button type="button" class="btn btn-danger" id="void-order-confirm">Void and Reorder</button>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

$jsonFlags = 0;
if (defined('JSON_HEX_TAG')) {
    $jsonFlags |= JSON_HEX_TAG;
}
if (defined('JSON_HEX_AMP')) {
    $jsonFlags |= JSON_HEX_AMP;
}
if (defined('JSON_HEX_APOS')) {
    $jsonFlags |= JSON_HEX_APOS;
}
if (defined('JSON_HEX_QUOT')) {
    $jsonFlags |= JSON_HEX_QUOT;
}
$ordersJson = json_encode($orders, $jsonFlags);
if ($ordersJson === false) {
    $ordersJson = '{}';
}

$scriptsBuffer = function () use ($ordersJson, $baseUrl) {
    ob_start();
    ?>
    <script src="<?php echo $baseUrl; ?>/assets/libs/jquery/jquery-3.6.1.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/libs/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        window.dailyTransactionOrders = <?php echo $ordersJson; ?>;

        function initDailyTransactionTable() {
            if (!window.jQuery || !jQuery.fn || !jQuery.fn.DataTable) {
                return;
            }
            var table = jQuery('#daily-transaction-table');
            if (!table.length || jQuery.fn.DataTable.isDataTable(table)) {
                return;
            }
            var options = {
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
                    emptyTable: 'No transactions for this date.',
                    info: 'Showing _START_ to _END_ of _TOTAL_ orders',
                    infoEmpty: 'Showing 0 to 0 of 0 orders',
                    paginate: { previous: 'Previous', next: 'Next' }
                },
                initComplete: function () {
                    disableSearchAutofill();
                }
            };
            table.DataTable(options);
            table.on('draw.dt', disableSearchAutofill);
        }

        function disableSearchAutofill() {
            var filterInput = document.querySelector('#daily-transaction-table_filter input');
            if (!filterInput) {
                return;
            }
            filterInput.setAttribute('name', 'transaction_filter');
            filterInput.setAttribute('autocomplete', 'off');
            filterInput.setAttribute('autocorrect', 'off');
            filterInput.setAttribute('autocapitalize', 'off');
            filterInput.setAttribute('spellcheck', 'false');
            filterInput.setAttribute('data-lpignore', 'true');
            filterInput.setAttribute('data-1p-ignore', 'true');
            filterInput.setAttribute('data-form-type', 'other');
        }

        document.addEventListener('DOMContentLoaded', function () {
            initDailyTransactionTable();

            var printArea = document.getElementById('daily-transaction-print-area');

            function formatCurrency(value) {
                var amount = Number(value);
                if (Number.isNaN(amount)) {
                    amount = 0;
                }
                var sign = amount < 0 ? '-' : '';
                var fixed = Math.abs(amount).toFixed(2);
                var parts = fixed.split('.');
                var whole = parts[0];
                var decimal = parts[1] ? '.' + parts[1] : '';
                var withCommas = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                return 'PHP ' + sign + withCommas + decimal;
            }

            function formatQty(value) {
                var qty = Number(value);
                if (Number.isNaN(qty)) {
                    return '0';
                }
                return Number.isInteger(qty) ? String(qty) : qty.toFixed(2);
            }

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            var receiptTextWidth = 48;

            function repeatChar(char, count) {
                return new Array(count + 1).join(char);
            }

            function padRight(value, width) {
                var text = String(value || '');
                if (text.length >= width) {
                    return text.slice(0, width);
                }
                return text + repeatChar(' ', width - text.length);
            }

            function padLeft(value, width) {
                var text = String(value || '');
                if (text.length >= width) {
                    return text.slice(text.length - width);
                }
                return repeatChar(' ', width - text.length) + text;
            }

            function centerText(value, width) {
                var text = String(value || '');
                if (text.length >= width) {
                    return text.slice(0, width);
                }
                var padding = width - text.length;
                var left = Math.floor(padding / 2);
                var right = padding - left;
                return repeatChar(' ', left) + text + repeatChar(' ', right);
            }

            function wrapText(value, width) {
                var text = String(value || '').trim();
                if (!text) {
                    return [''];
                }
                var words = text.split(/\s+/);
                var lines = [];
                var current = '';

                words.forEach(function (word) {
                    if (word.length > width) {
                        if (current) {
                            lines.push(current);
                            current = '';
                        }
                        for (var i = 0; i < word.length; i += width) {
                            lines.push(word.slice(i, i + width));
                        }
                        return;
                    }

                    if (!current) {
                        current = word;
                        return;
                    }

                    if (current.length + 1 + word.length <= width) {
                        current += ' ' + word;
                    } else {
                        lines.push(current);
                        current = word;
                    }
                });

                if (current) {
                    lines.push(current);
                }

                return lines.length ? lines : [''];
            }

            function pushWrapped(lines, value, width) {
                wrapText(value, width).forEach(function (line) {
                    lines.push(line);
                });
            }

            function calculateOrderTotals(order) {
                var items = Array.isArray(order.items) ? order.items : [];
                var subtotal = items.reduce(function (sum, item) {
                    return sum + Number(item.gross_amount || 0);
                }, 0);

                var lineItemDiscountTotal = items.reduce(function (sum, item) {
                    return sum + Number(item.line_discount || 0);
                }, 0);

                var discountPercent = Number(order.discount || 0);
                if (Number.isNaN(discountPercent) || discountPercent < 0) {
                    discountPercent = 0;
                }
                if (discountPercent > 100) {
                    discountPercent = 100;
                }

                var discountedBase = subtotal - lineItemDiscountTotal;
                if (discountedBase < 0) {
                    discountedBase = 0;
                }
                var discountAmount = discountedBase * (discountPercent / 100);
                var additionalCharges = 0;
                var grandTotal = subtotal - lineItemDiscountTotal - discountAmount + additionalCharges;

                return {
                    subtotal: subtotal,
                    lineItemDiscountTotal: lineItemDiscountTotal,
                    discountPercent: discountPercent,
                    discountAmount: discountAmount,
                    additionalCharges: additionalCharges,
                    grandTotal: grandTotal
                };
            }

            function getPaymentSummary(order, totals) {
                var amountDue = totals.grandTotal;
                var amountReceived = Number(order.amt_received || 0);
                if (Number.isNaN(amountReceived) || amountReceived < 0) {
                    amountReceived = 0;
                }
                var change = Number(order.amt_change);
                if (Number.isNaN(change)) {
                    change = amountReceived - amountDue;
                }
                if (change < 0) {
                    change = 0;
                }
                return {
                    amountDue: amountDue,
                    amountReceived: amountReceived,
                    change: change
                };
            }

            function buildReceiptText(order, printedAtText, copyLabel) {
                var storeName = order.customer_name || '';
                var storeAddress = order.customer_address || '';
                var customerCode = order.customer_code || '';
                var deliveryDate = order.date_delivery || '';
                var deliveryTime = order.time_delivery || '';
                var instruction = order.delivery_instruction || '';
                var orderType = (deliveryDate || deliveryTime || instruction) ? 'delivery' : '';
                var totals = calculateOrderTotals(order);
                var payment = getPaymentSummary(order, totals);
                var drNumber = order.order_no ? 'DR' + order.order_no : 'DR-PENDING';
                var printedLabel = printedAtText || 'PENDING PRINT';
                var width = receiptTextWidth;
                var amountWidth = 20;
                var qtyWidth = 3;
                var uomWidth = 4;
                var lineWidth = 3;
                var amountGap = 2;
                var amountGapText = repeatChar(' ', amountGap);
                var colGap = 1;
                var colGapText = repeatChar(' ', colGap);
                var descWidth = width - lineWidth - qtyWidth - uomWidth - amountWidth - amountGap - (colGap * 2);
                var labelWidth = lineWidth + descWidth + uomWidth + (colGap * 2);
                var lines = [];

                lines.push(centerText('COLDDUST BENTEL ICE MANUFACTURING', width));
                if (order.void_status) {
                    lines.push(centerText('VOID DR', width));
                }
                lines.push(centerText('DELIVERY RECEIPT', width));
                lines.push(centerText('DATE/TIME: ' + printedLabel, width));
                lines.push(centerText(drNumber, width));
                lines.push('');

                if (storeName) {
                    pushWrapped(lines, 'Customer: ' + storeName, width);
                }
                if (storeAddress) {
                    pushWrapped(lines, 'Address: ' + storeAddress, width);
                }
                if (customerCode) {
                    pushWrapped(lines, 'Customer Code: ' + customerCode, width);
                }

                if (orderType) {
                    pushWrapped(lines, 'Order Type: Delivery', width);
                }

                if (orderType === 'delivery' && (deliveryDate || deliveryTime || instruction)) {
                    if (deliveryDate || deliveryTime) {
                        var schedule = deliveryDate;
                        if (deliveryTime) {
                            schedule = schedule ? (schedule + ' ' + deliveryTime) : deliveryTime;
                        }
                        pushWrapped(lines, 'Delivery: ' + schedule, width);
                    }
                    if (instruction) {
                        pushWrapped(lines, 'Instruction: ' + instruction, width);
                    }
                }

                lines.push(padRight('No', lineWidth) + padRight('Description', descWidth) + colGapText + padLeft('UOM', uomWidth) + colGapText + padLeft('Qty', qtyWidth) + amountGapText + padLeft('Amount', amountWidth));
                lines.push('');

                var totalQty = (order.items || []).reduce(function (sum, item) {
                    return sum + Number(item.qty || 0);
                }, 0);

                (order.items || []).forEach(function (item, index) {
                    var lineText = padRight(index + 1, lineWidth);
                    var qtyLabel = formatQty(item.qty);
                    var qtyText = padLeft(qtyLabel, qtyWidth);
                    var uomText = padLeft(item.uom || '', uomWidth);
                    var amountText = padLeft(formatCurrency(item.amount), amountWidth);
                    var descLines = wrapText(item.description, descWidth);

                    descLines.forEach(function (descLine, descIndex) {
                        if (descIndex === 0) {
                            lines.push(lineText + padRight(descLine, descWidth) + colGapText + uomText + colGapText + qtyText + amountGapText + amountText);
                        } else {
                            lines.push(padRight('', lineWidth) + padRight(descLine, descWidth) + colGapText + padLeft('', uomWidth) + colGapText + padLeft('', qtyWidth) + amountGapText + padLeft('', amountWidth));
                        }
                    });
                });

                lines.push(padRight('Total Qty', labelWidth) + padLeft(formatQty(totalQty), qtyWidth) + amountGapText + padLeft('', amountWidth));
                lines.push(padRight('Subtotal', width - amountWidth - amountGap) + amountGapText + padLeft(formatCurrency(totals.subtotal), amountWidth));
                lines.push(padRight('Line Discount', width - amountWidth - amountGap) + amountGapText + padLeft(formatCurrency(totals.lineItemDiscountTotal), amountWidth));
                lines.push(padRight('Discount (' + totals.discountPercent.toFixed(2) + '%)', width - amountWidth - amountGap) + amountGapText + padLeft(formatCurrency(totals.discountAmount), amountWidth));
                lines.push(padRight('Grand Total', width - amountWidth - amountGap) + amountGapText + padLeft(formatCurrency(totals.grandTotal), amountWidth));
                lines.push(padRight('Cash Amount Due', width - amountWidth - amountGap) + amountGapText + padLeft(formatCurrency(payment.amountDue), amountWidth));
                lines.push(padRight('Amount Received', width - amountWidth - amountGap) + amountGapText + padLeft(formatCurrency(payment.amountReceived), amountWidth));
                lines.push(padRight('Change', width - amountWidth - amountGap) + amountGapText + padLeft(formatCurrency(payment.change), amountWidth));
                lines.push('');
                lines.push('');
                lines.push('RECEIVED BY: _______________________________');
                lines.push(centerText('        Printed Name & Signature', width));
                lines.push('');
                lines.push('');
                lines.push(centerText(copyLabel, width));

                while (lines.length && String(lines[lines.length - 1]).trim() === '') {
                    lines.pop();
                }

                return lines.join('\n');
            }

            function renderPrintCopy(order, copyLabel) {
                if (!printArea) {
                    return;
                }
                var printedAtText = order.date_created || '';
                var copyText = buildReceiptText(order, printedAtText, copyLabel);
                printArea.innerHTML = '<div class="pos-receipt"><pre class="pos-receipt-text">' + escapeHtml(copyText) + '</pre></div>';
            }

            function handlePrintClick(orderNo) {
                var orderData = window.dailyTransactionOrders || {};
                var order = orderData[orderNo];
                if (!order) {
                    window.alert('Order not found.');
                    return;
                }
                renderPrintCopy(order, 'STORE COPY');
                window.print();
            }

            document.addEventListener('click', function (event) {
                var printButton = event.target ? event.target.closest('.pos-print-order') : null;
                if (printButton) {
                    var orderNo = printButton.getAttribute('data-order-no');
                    if (!orderNo) {
                        return;
                    }
                    handlePrintClick(orderNo);
                    return;
                }

                var voidButton = event.target ? event.target.closest('.pos-void-order') : null;
                if (!voidButton) {
                    return;
                }
                if (voidButton.disabled || voidButton.getAttribute('aria-disabled') === 'true') {
                    return;
                }
                var voidOrderNo = voidButton.getAttribute('data-order-no');
                if (!voidOrderNo) {
                    return;
                }
                if (!voidModalInstance) {
                    handleVoidPrompt(voidOrderNo);
                    return;
                }
                resetVoidModal(voidOrderNo);
                voidModalInstance.show();
            });

            var voidModalEl = document.getElementById('void-order-modal');
            var voidOrderLabel = document.getElementById('void-order-label');
            var voidReasonInput = document.getElementById('void-reason');
            var voidSupervisorInput = document.getElementById('void-admin-username');
            var voidPasswordInput = document.getElementById('void-admin-password');
            var voidError = document.getElementById('void-order-error');
            var voidConfirmButton = document.getElementById('void-order-confirm');
            var voidCancelOnlyButton = document.getElementById('void-order-cancel-only');
            var voidModalInstance = null;
            var pendingVoidOrderNo = '';

            if (voidModalEl && window.bootstrap && bootstrap.Modal) {
                voidModalInstance = new bootstrap.Modal(voidModalEl);
                voidModalEl.addEventListener('shown.bs.modal', function () {
                    if (voidSupervisorInput) {
                        voidSupervisorInput.focus();
                    }
                });
            }

            function showVoidError(message) {
                if (!voidError) {
                    window.alert(message);
                    return;
                }
                voidError.textContent = message;
                voidError.classList.remove('d-none');
            }

            function resetVoidModal(orderNo) {
                pendingVoidOrderNo = orderNo || '';
                if (voidOrderLabel) {
                    voidOrderLabel.textContent = orderNo ? ('DR' + orderNo) : '';
                }
                if (voidReasonInput) {
                    voidReasonInput.value = '';
                }
                if (voidSupervisorInput) {
                    voidSupervisorInput.value = '';
                }
                if (voidPasswordInput) {
                    voidPasswordInput.value = '';
                }
                if (voidError) {
                    voidError.textContent = '';
                    voidError.classList.add('d-none');
                }
            }

            function submitVoidRequest(orderNo, reason, supervisorUsername, supervisorPassword, redirectToPos) {
                return fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'void_order',
                        order_no: orderNo,
                        remarks: reason,
                        supervisor_username: supervisorUsername,
                        supervisor_password: supervisorPassword,
                        redirect: redirectToPos !== false
                    })
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, data: data };
                        });
                    });
            }

            function handleVoidPrompt(orderNo) {
                var reason = window.prompt('Reason for voiding (max 100 characters):');
                if (!reason) {
                    return;
                }
                var supervisorUsername = window.prompt('Admin username:');
                if (!supervisorUsername) {
                    return;
                }
                var supervisorPassword = window.prompt('Admin password:');
                if (!supervisorPassword) {
                    return;
                }
                submitVoidRequest(orderNo, reason, supervisorUsername, supervisorPassword, false)
                    .then(function (result) {
                        if (!result.ok || !result.data || !result.data.success) {
                            var message = result.data && result.data.message ? result.data.message : 'Unable to void order.';
                            throw new Error(message);
                        }
                        window.alert(result.data && result.data.message ? result.data.message : 'Order is now voided.');
                        window.location.reload();
                    })
                    .catch(function (error) {
                        window.alert(error && error.message ? error.message : 'Unable to void order.');
                    });
            }

            function handleVoidSubmit(redirectToPos) {
                var reason = voidReasonInput ? voidReasonInput.value.trim() : '';
                var supervisorUsername = voidSupervisorInput ? voidSupervisorInput.value.trim() : '';
                var supervisorPassword = voidPasswordInput ? voidPasswordInput.value : '';

                if (!pendingVoidOrderNo) {
                    showVoidError('Select an order to void.');
                    return;
                }
                if (!reason) {
                    showVoidError('Enter a reason for voiding.');
                    return;
                }
                if (!supervisorUsername || !supervisorPassword) {
                    showVoidError('Admin credentials are required.');
                    return;
                }

                var activeButton = redirectToPos ? voidConfirmButton : voidCancelOnlyButton;
                var originalText = activeButton ? activeButton.textContent : '';
                if (activeButton) {
                    activeButton.textContent = 'Voiding...';
                    activeButton.disabled = true;
                }
                if (voidConfirmButton && voidCancelOnlyButton) {
                    voidConfirmButton.disabled = true;
                    voidCancelOnlyButton.disabled = true;
                }

                submitVoidRequest(pendingVoidOrderNo, reason, supervisorUsername, supervisorPassword, redirectToPos)
                    .then(function (result) {
                        if (!result.ok || !result.data || !result.data.success) {
                            var message = result.data && result.data.message ? result.data.message : 'Unable to void order.';
                            throw new Error(message);
                        }
                        if (redirectToPos) {
                            window.location.href = result.data.redirect_url || window.location.href;
                            return;
                        }
                        window.alert(result.data && result.data.message ? result.data.message : 'Order is now voided.');
                        window.location.reload();
                    })
                    .catch(function (error) {
                        showVoidError(error && error.message ? error.message : 'Unable to void order.');
                    })
                    .then(function () {
                        if (activeButton) {
                            activeButton.textContent = originalText;
                            activeButton.disabled = false;
                        }
                        if (voidConfirmButton && voidCancelOnlyButton) {
                            voidConfirmButton.disabled = false;
                            voidCancelOnlyButton.disabled = false;
                        }
                    });
            }

            if (voidConfirmButton) {
                voidConfirmButton.addEventListener('click', function () {
                    handleVoidSubmit(true);
                });
            }

            if (voidCancelOnlyButton) {
                voidCancelOnlyButton.addEventListener('click', function () {
                    handleVoidSubmit(false);
                });
            }
        });
    </script>
    <?php
    return ob_get_clean();
};
$scripts = $scriptsBuffer();

include 'layouts/base.php';
