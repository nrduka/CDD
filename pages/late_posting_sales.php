<?php
ob_start();
// This code generates the base URL for the website by combining the protocol, domain name, and directory path.
$rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$role = isset($_SESSION['sess_userrole']) ? $_SESSION['sess_userrole'] : '';
$firstName = isset($_SESSION['sess_firstname']) ? $_SESSION['sess_firstname'] : '';
$lastName = isset($_SESSION['sess_lastname']) ? $_SESSION['sess_lastname'] : '';
$fullname = trim($firstName . ' ' . $lastName);

require_once __DIR__ . '/db2.php';

$isPosSubmission = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';

function send_pos_json_response($payload, $statusCode = 200)
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

function generate_pos_order_no($mysqli)
{
    $orderPrefix = date('y') . '-' . date('m') . '-';
    $sequenceSql = "INSERT INTO sales_order_sequence (prefix, last_number)
        VALUES (?, LAST_INSERT_ID(1))
        ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)";
    $stmt = $mysqli->prepare($sequenceSql);
    if (!$stmt) {
        return [null, 'Unable to prepare order number sequence.'];
    }
    $stmt->bind_param('s', $orderPrefix);
    if (!$stmt->execute()) {
        $stmt->close();
        return [null, 'Unable to generate order number.'];
    }
    $seriesNumber = (int)$mysqli->insert_id;
    $stmt->close();
    if ($seriesNumber <= 0) {
        return [null, 'Invalid order number generated.'];
    }
    return [$orderPrefix . sprintf('%04d', $seriesNumber), null];
}

function is_pos_duplicate_key_error($stmt, $mysqli)
{
    $errorNo = 0;
    if ($stmt && $stmt->errno) {
        $errorNo = $stmt->errno;
    } elseif ($mysqli && $mysqli->errno) {
        $errorNo = $mysqli->errno;
    }
    return $errorNo === 1062;
}

if ($isPosSubmission) {
    if (!isset($mysqli) || !$mysqli) {
        send_pos_json_response(['success' => false, 'message' => 'Database connection unavailable.'], 500);
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        send_pos_json_response(['success' => false, 'message' => 'Invalid order payload.'], 400);
    }

    $action = trim((string)(isset($payload['action']) ? $payload['action'] : ''));
    if ($action === 'reserve_order_no') {
        list($reservedOrderNo, $reserveError) = generate_pos_order_no($mysqli);
        if ($reserveError) {
            send_pos_json_response(['success' => false, 'message' => $reserveError], 500);
        }
        send_pos_json_response(['success' => true, 'order_no' => $reservedOrderNo]);
    }

    $customerCode = trim((string)(isset($payload['customer_code']) ? $payload['customer_code'] : ''));
    if ($customerCode === '') {
        send_pos_json_response(['success' => false, 'message' => 'Customer is required.'], 422);
    }

    $orderType = trim((string)(isset($payload['order_type']) ? $payload['order_type'] : ''));
    $deliveryInstruction = trim((string)(isset($payload['delivery_instruction']) ? $payload['delivery_instruction'] : ''));
    $discount = isset($payload['discount']) ? (float)$payload['discount'] : 0.0;
    if ($discount < 0) {
        $discount = 0.0;
    }
    if ($discount > 100) {
        $discount = 100.0;
    }

    $dateTimeOrderedRaw = trim((string)(isset($payload['date_time_ordered']) ? $payload['date_time_ordered'] : ''));
    if ($dateTimeOrderedRaw === '') {
        send_pos_json_response(['success' => false, 'message' => 'Order date and time is required.'], 422);
    }
    $dateTimeOrderedValue = DateTime::createFromFormat('Y-m-d H:i:s', $dateTimeOrderedRaw);
    if (!$dateTimeOrderedValue) {
        $dateTimeOrderedValue = DateTime::createFromFormat('Y-m-d\\TH:i', $dateTimeOrderedRaw);
    }
    if (!$dateTimeOrderedValue) {
        $dateTimeOrderedValue = DateTime::createFromFormat('Y-m-d\\TH:i:s', $dateTimeOrderedRaw);
    }
    if (!$dateTimeOrderedValue) {
        send_pos_json_response(['success' => false, 'message' => 'Invalid order date/time format.'], 422);
    }
    $dateTimeOrdered = $dateTimeOrderedValue->format('Y-m-d H:i:s');

    $dateDelivery = trim((string)(isset($payload['date_delivery']) ? $payload['date_delivery'] : ''));
    $timeDelivery = trim((string)(isset($payload['time_delivery']) ? $payload['time_delivery'] : ''));
    if ($orderType === 'store-pickup') {
        $dateDelivery = null;
        $timeDelivery = null;
        $deliveryInstruction = '';
    } else {
        $dateDelivery = $dateDelivery !== '' ? $dateDelivery : null;
        $timeDelivery = $timeDelivery !== '' ? $timeDelivery : null;
    }

    $printedAt = trim((string)(isset($payload['printed_at']) ? $payload['printed_at'] : ''));
    if ($printedAt !== '') {
        $printedAtValue = DateTime::createFromFormat('Y-m-d H:i:s', $printedAt);
        $printedAt = $printedAtValue ? $printedAtValue->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
    } else {
        $printedAt = date('Y-m-d H:i:s');
    }

    $items = isset($payload['items']) ? $payload['items'] : array();
    if (!is_array($items) || count($items) === 0) {
        send_pos_json_response(['success' => false, 'message' => 'Add at least one item before submitting.'], 422);
    }

    $orderNo = trim((string)(isset($payload['order_no']) ? $payload['order_no'] : ''));
    $maxInsertAttempts = 3;
    $amountReceived = isset($payload['amt_received']) ? (float)$payload['amt_received'] : 0.0;
    if ($amountReceived < 0) {
        $amountReceived = 0.0;
    }
    $amountChange = isset($payload['amt_change']) ? (float)$payload['amt_change'] : 0.0;
    if ($amountChange < 0) {
        $amountChange = 0.0;
    }

    $insertSql = "INSERT INTO sales (order_no, customer_code, delivery_instruction, lineitem, qty, product_code, product_description, lineitem_discount, discount, unit_price, amount, date_delivery, time_delivery, date_time_ordered, date_created, amt_received, amt_change)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $insertStmt = $mysqli->prepare($insertSql);
    if (!$insertStmt) {
        send_pos_json_response(['success' => false, 'message' => 'Unable to prepare order insert.'], 500);
    }

    $attempt = 0;
    while ($attempt < $maxInsertAttempts) {
        if ($orderNo === '') {
            list($orderNo, $orderNoError) = generate_pos_order_no($mysqli);
            if ($orderNoError) {
                $insertStmt->close();
                send_pos_json_response(['success' => false, 'message' => $orderNoError], 500);
            }
        }

        if (!$mysqli->begin_transaction()) {
            $insertStmt->close();
            send_pos_json_response(['success' => false, 'message' => 'Unable to start order transaction.'], 500);
        }

        $lineItemNumber = 0;
        $duplicateOrderNo = false;
        foreach ($items as $item) {
            $lineItemNumber++;
            if (!is_array($item)) {
                $mysqli->rollback();
                $insertStmt->close();
                send_pos_json_response(['success' => false, 'message' => 'Invalid line item data.'], 422);
            }

            $productCode = trim((string)(isset($item['product_code']) ? $item['product_code'] : ''));
            $productDescription = trim((string)(isset($item['product_description']) ? $item['product_description'] : ''));
            $quantity = isset($item['quantity']) ? (float)$item['quantity'] : 0.0;
            $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : 0.0;
            $lineitemDiscountPerUnit = isset($item['lineitem_discount']) ? (float)$item['lineitem_discount'] : 0.0;

            if ($productCode === '' || $productDescription === '' || $quantity <= 0) {
                $mysqli->rollback();
                $insertStmt->close();
                send_pos_json_response(['success' => false, 'message' => 'Line items must include product and quantity.'], 422);
            }

            if ($lineitemDiscountPerUnit < 0) {
                $lineitemDiscountPerUnit = 0.0;
            }

            $grossAmount = $quantity * $unitPrice;
            if ($lineitemDiscountPerUnit > $unitPrice) {
                $lineitemDiscountPerUnit = $unitPrice;
            }
            $lineitemDiscount = $lineitemDiscountPerUnit * $quantity;
            if ($lineitemDiscount > $grossAmount) {
                $lineitemDiscount = $grossAmount;
            }
            $amount = $grossAmount - $lineitemDiscount;

            $insertStmt->bind_param(
                'sssidssddddssssdd',
                $orderNo,
                $customerCode,
                $deliveryInstruction,
                $lineItemNumber,
                $quantity,
                $productCode,
                $productDescription,
                $lineitemDiscount,
                $discount,
                $unitPrice,
                $amount,
                $dateDelivery,
                $timeDelivery,
                $dateTimeOrdered,
                $printedAt,
                $amountReceived,
                $amountChange
            );

            if (!$insertStmt->execute()) {
                if (is_pos_duplicate_key_error($insertStmt, $mysqli)) {
                    $duplicateOrderNo = true;
                    break;
                }
                $errorDetail = $insertStmt->error ? $insertStmt->error : $mysqli->error;
                $mysqli->rollback();
                $insertStmt->close();
                send_pos_json_response(['success' => false, 'message' => 'Unable to save order line items. ' . $errorDetail], 500);
            }
        }

        if ($duplicateOrderNo) {
            $mysqli->rollback();
            $orderNo = '';
            $attempt++;
            continue;
        }

        $mysqli->commit();
        $insertStmt->close();
        send_pos_json_response(['success' => true, 'order_no' => $orderNo]);
    }

    $insertStmt->close();
    send_pos_json_response(['success' => false, 'message' => 'Unable to save order after retrying.'], 500);
}

$customers = [];
$products = [];
$customerError = null;
$productError = null;

$connectionAvailable = isset($mysqli) && $mysqli;

if (!$connectionAvailable) {
    $customerError = 'Database connection unavailable.';
    $productError = 'Database connection unavailable.';
} else {
$query = "SELECT id, customer_code, firstName, middleName, lastName, storeName, address, type FROM customer_register ORDER BY customer_source,customer_series";
    if ($result = $mysqli->query($query)) {
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        $result->free();
    } else {
        $customerError = 'Customer list unavailable.';
    }

    $query = "SELECT id, product_code, product_description, uom, unit_price FROM ice_product WHERE status != 1 ORDER BY product_code, product_type";
    if ($result = $mysqli->query($query)) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $result->free();
    } else {
        $productError = 'Product list unavailable.';
    }
}
?>




<!-- This code is useful for internal styles  -->
<?php ob_start(); ?>

<link href="<?php echo $baseUrl; ?>/assets/css/pos_terminal.css" rel="stylesheet">
<style>
    .pos-modal-open {
        overflow: hidden;
    }

    .pos-preview-modal {
        position: fixed;
        inset: 0;
        z-index: 1055;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pos-preview-modal.d-none {
        display: none;
    }

    .pos-preview-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
    }

    .pos-preview-dialog {
        position: relative;
        background: #fff;
        width: min(900px, 94vw);
        max-height: 92vh;
        border-radius: 16px;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.35);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        z-index: 1;
    }

    .pos-preview-header,
    .pos-preview-footer {
        padding: 12px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border-bottom: 1px solid #e5e7eb;
    }

    .pos-preview-footer {
        border-top: 1px solid #e5e7eb;
        border-bottom: none;
        justify-content: flex-end;
        background: #f8fafc;
    }

    .pos-preview-body {
        padding: 16px;
        overflow-y: auto;
        background: #f1f5f9;
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

    .pos-receipt-title {
        font-weight: 700;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.4px;
    }

    .pos-receipt-header {
        text-align: center;
        margin-bottom: 8px;
    }

    .pos-receipt-subtitle {
        font-weight: 700;
        margin-top: 4px;
    }

    .pos-receipt-meta {
        margin-top: 4px;
    }

    .pos-receipt-section {
        margin-top: 8px;
    }

    .pos-receipt-line {
        display: flex;
        justify-content: space-between;
        gap: 8px;
    }

    .pos-receipt-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
        font-size: 10px;
    }

    .pos-receipt-table th,
    .pos-receipt-table td {
        padding: 3px 0;
        border-bottom: 1px dashed #cbd5e1;
    }

    .pos-receipt-table th {
        text-align: left;
        font-weight: 700;
    }

    .pos-receipt-table td:last-child,
    .pos-receipt-table th:last-child {
        text-align: right;
    }

    .pos-receipt-copy {
        margin-top: 10px;
        text-align: center;
        font-weight: 700;
        letter-spacing: 0.6px;
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

        #pos-print-area,
        #pos-print-area * {
            visibility: visible;
        }

        #pos-print-area {
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

<?php $styles = ob_get_clean(); ?>
<!-- This code is useful for internal styles  -->

<!-- This code is useful for content -->
<?php ob_start(); ?>

                <div class="container-fluid">

                    <!-- Start::page-header -->
                    <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
                        <div>
                            <p class="fw-semibold fs-18 mb-0">LATE POSTING OF SALES</p>
                        </div>
                    </div>
                    <!-- End::page-header -->

                    <!-- Start::row -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card pos-terminal-card">
                                <div class="card-header pos-terminal-header">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bx bx-store-alt fs-20"></i>
                                            <span class="fw-semibold"></span>
                                        </div>
                                        <button type="button" class="btn btn-link pos-terminal-close" aria-label="Close">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body pos-terminal-body">
                                    <div class="row align-items-center g-3">
                                        <div class="col-12 col-md-3 col-lg-2">
                                            <label class="form-label fw-semibold mb-0">Customer:</label>
                                        </div>
                                        <div class="col-12 col-md-7 col-lg-5">
                                            <select class="form-select" id="pos-customer" name="customer_id">
                                                <option selected disabled>Select Customer</option>
                                                <?php if ($customerError): ?>
                                                    <option disabled><?php echo htmlspecialchars($customerError, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php elseif (empty($customers)): ?>
                                                    <option disabled>No customers found</option>
                                                <?php else: ?>
                                                    <?php foreach ($customers as $customer): ?>
                                                        <?php
                                                            $code = trim((string)$customer['customer_code']);
                                                            $storeName = trim((string)$customer['storeName']);
                                                            $address = trim((string)$customer['address']);
                                                            $firstName = trim((string)$customer['firstName']);
                                                            $middleName = trim((string)$customer['middleName']);
                                                            $lastName = trim((string)$customer['lastName']);
                                                            $type = trim((string)$customer['type']);

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

                                                            $displayName = $storeName !== '' ? $storeName : implode(' ', $nameParts);
                                                            if ($displayName == '' && $code !== '') {
                                                                $displayName = $code;
                                                            }

                                                            if ($code !== '' && $displayName !== '' && $displayName !== $code) {
                                                                $label = $code . ' | ' . $displayName;
                                                            } elseif ($displayName !== '') {
                                                                $label = $displayName;
                                                            } else {
                                                                $label = $code;
                                                            }
                                                        ?>
                                                        <option value="<?php echo htmlspecialchars((string)$customer['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-code="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-store="<?php echo htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-address="<?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>
                                        <br>

                                    <div class="row align-items-start g-3 pos-terminal-customer-details">
                                        <div class="col-12 col-md-3 col-lg-2">
                                            <label class="form-label fw-semibold mb-0">Store Details:</label>
                                        </div>
                                        <div class="col-12 col-md-4 col-lg-3">
                                            <input type="text" class="form-control" id="pos-store-name" name="store_name" placeholder="Store Name" readonly>
                                        </div>
                                        <div class="col-12 col-md-5 col-lg-5">
                                            <textarea class="form-control" id="pos-store-address" name="store_address" rows="2" placeholder="Address" readonly></textarea>
                                        </div>
                                    </div>

                                    <div class="row align-items-center g-3">
                                        <div class="col-12 col-md-3 col-lg-2">
                                            <label class="form-label fw-semibold mb-0">Order Date &amp; Time:</label>
                                        </div>
                                        <div class="col-12 col-md-4 col-lg-3">
                                            <input type="datetime-local" class="form-control" id="date_time_ordered" name="date_time_ordered" disabled>
                                        </div>
                                    </div>

                                    <div class="row align-items-center g-3">
                                        <div class="col-12 col-md-3 col-lg-2">
                                            <label class="form-label fw-semibold mb-0">Order Type Options:</label>
                                        </div>
                                        <div class="col-12 col-md-7 col-lg-5">
                                            <div class="d-flex flex-wrap gap-3 pos-terminal-order-type">
                                                <div class="form-check">
                                                    <input class="form-check-input order-type-option" type="checkbox" id="order-type-pickup" name="order_type" value="store-pickup" disabled>
                                                    <label class="form-check-label" for="order-type-pickup">Store Pickup</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input order-type-option" type="checkbox" id="order-type-delivery" name="order_type" value="delivery" disabled>
                                                    <label class="form-check-label" for="order-type-delivery">Delivery</label>
                                                </div>
                                                <span class="pos-terminal-order-note">Please choose one</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row align-items-center g-3 pos-terminal-delivery-fields d-none">
                                        <div class="col-12 col-md-3 col-lg-2">
                                            <label class="form-label fw-semibold mb-0">Delivery Schedule:</label>
                                        </div>
                                        <div class="col-12 col-md-4 col-lg-3">
                                            <input type="date" class="form-control" id="date_delivery" name="date_delivery" placeholder="YYYY-MM-DD" disabled>
                                        </div>
                                        <div class="col-12 col-md-3 col-lg-2">
                                            <input type="time" class="form-control" id="time_deliver" name="time_deliver" placeholder="1:00 AM" disabled>
                                        </div>
                                    </div>

                                    <div class="row align-items-center g-3 pos-terminal-delivery-fields d-none">
                                        <div class="col-12 col-md-3 col-lg-2">
                                            <label class="form-label fw-semibold mb-0">Delivery Instruction:</label>
                                        </div>
                                        <div class="col-12 col-md-7 col-lg-5">
                                            <textarea class="form-control" id="delivery_instruction" name="delivery_instruction" rows="2" placeholder="Add delivery instructions" disabled></textarea>
                                        </div>
                                    </div>

                                    <div class="pos-terminal-divider"></div>

                                    <div class="row align-items-center g-3">
                                        <div class="col-12 col-md-4 col-lg-4">
                                            <label class="form-label fw-semibold">Product</label>
                                            <select class="form-select" id="pos-product" name="product_id" disabled>
                                                <option selected disabled>Select Product</option>
                                                <?php if ($productError): ?>
                                                    <option disabled><?php echo htmlspecialchars($productError, ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php elseif (empty($products)): ?>
                                                    <option disabled>No products found</option>
                                                <?php else: ?>
                                                    <?php foreach ($products as $product): ?>
                                                        <?php
                                                            $productCode = trim((string)$product['product_code']);
                                                            $description = trim((string)$product['product_description']);
                                                            $uom = trim((string)$product['uom']);
                                                            $unitPrice = isset($product['unit_price']) ? (float)$product['unit_price'] : 0.0;

                                                            $labelParts = [];
                                                            if ($productCode !== '') {
                                                                $labelParts[] = $productCode;
                                                            }
                                                            if ($description !== '') {
                                                                $labelParts[] = $description;
                                                            }
                                                            $labelParts[] = 'PHP ' . number_format($unitPrice, 2);
                                                            $label = implode(' - ', $labelParts);
                                                            if ($uom !== '') {
                                                                $label .= ' (' . $uom . ')';
                                                            }
                                                        ?>
                                                        <option value="<?php echo htmlspecialchars((string)$product['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-code="<?php echo htmlspecialchars($productCode, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-description="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-uom="<?php echo htmlspecialchars($uom, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-price="<?php echo htmlspecialchars((string)$unitPrice, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-2 col-lg-2">
                                            <label class="form-label fw-semibold">Quantity</label>
                                            <input type="number" class="form-control" id="pos-quantity" value="1" min="1" disabled>
                                        </div>
                                        <div class="col-12 col-md-2 col-lg-2">
                                            <label class="form-label fw-semibold">Line Discount (per unit)</label>
                                            <input type="number" class="form-control" id="pos-lineitem-discount" value="0" min="0" step="0.01" disabled>
                                        </div>
                                        <div class="col-12 col-md-2 col-lg-2">
                                            <label class="form-label fw-semibold">Price</label>
                                            <div class="pos-terminal-price" id="pos-unit-price">PHP 0.00</div>
                                        </div>
                                        <div class="col-12 col-md-2 col-lg-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-primary w-100" id="pos-add-item" disabled>Add Item</button>
                                        </div>
                                    </div>

                                    <div class="pos-terminal-divider"></div>

                                    <div class="pos-terminal-items">
                                        <div class="table-responsive">
                                            <table class="table table-bordered align-middle mb-0" id="pos-lineitems-table">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="text-center">No</th>
                                                        <th>Product Code</th>
                                                        <th>Description</th>
                                                        <th>UOM</th>
                                                        <th class="text-end">Qty</th>
                                                        <th class="text-end">Unit Price</th>
                                                        <th class="text-end">Line Discount</th>
                                                        <th class="text-end">Amount</th>
                                                        <th class="text-center">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="pos-lineitems-body"></tbody>
                                            </table>
                                        </div>
                                        <div class="pos-terminal-empty text-muted" id="pos-empty-state">
                                            Items will appear here once added.
                                        </div>
                                    </div>

                                    <div class="pos-terminal-divider"></div>

                                    <div class="pos-terminal-summary">
                                        <div class="pos-terminal-summary-row">
                                            <span class="fw-semibold">Subtotal:</span>
                                            <span class="fw-semibold" id="pos-subtotal">PHP 0.00</span>
                                        </div>
                                        <div class="pos-terminal-summary-row">
                                            <span class="fw-semibold">Line Item Discount:</span>
                                            <span class="fw-semibold" id="pos-lineitem-discount-total">PHP 0.00</span>
                                        </div>
                                        <div class="pos-terminal-summary-row">
                                            <span class="fw-semibold">Discount:</span>
                                            <div class="input-group pos-terminal-discount">
                                                <input type="number" class="form-control" id="pos-discount" value="0" min="0" max="100" disabled>
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <div class="pos-terminal-summary-row">
                                            <span class="fw-semibold">Additional Charges:</span>
                                            <span class="fw-semibold" id="pos-additional-charges">PHP 0.00</span>
                                        </div>
                                    <div class="pos-terminal-summary-row pos-terminal-grand">
                                        <span>Grand Total:</span>
                                        <span id="pos-grand-total">PHP 0.00</span>
                                    </div>
                                    <div class="pos-terminal-summary-row">
                                        <span class="fw-semibold">Cash Amount Due:</span>
                                        <span class="fw-semibold" id="pos-amount-due">PHP 0.00</span>
                                    </div>
                                    <div class="pos-terminal-summary-row pos-terminal-received">
                                        <span class="fw-semibold">Amount Received:</span>
                                        <input type="number" class="form-control pos-terminal-cash-input" id="pos-amount-received" value="0" min="0" step="0.01" disabled>
                                    </div>
                                    <div class="pos-terminal-summary-row pos-terminal-change">
                                        <span class="fw-semibold">Change:</span>
                                        <span class="fw-semibold" id="pos-change">PHP 0.00</span>
                                    </div>
                                </div>

                                    <div class="text-center mt-4">
                                        <button type="button" class="btn btn-primary btn-lg px-5" id="pos-submit-order" disabled>Submit Order</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- End::row -->

                </div>

                <div class="pos-preview-modal d-none" id="pos-preview-modal" aria-hidden="true">
                    <div class="pos-preview-backdrop" data-pos-preview-close="true"></div>
                    <div class="pos-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="pos-preview-title">
                        <div class="pos-preview-header">
                            <h5 class="mb-0" id="pos-preview-title">Receipt Preview</h5>
                            <button type="button" class="btn btn-link text-muted" id="pos-preview-close" aria-label="Close">
                                <i class="ri-close-line"></i>
                            </button>
                        </div>
                        <div class="pos-preview-body">
                            <div id="pos-receipt-preview"></div>
                        </div>
                        <div class="pos-preview-footer">
                            <button type="button" class="btn btn-light" id="pos-preview-cancel">Cancel</button>
                            <button type="button" class="btn btn-outline-primary" id="pos-preview-print-store">Print Store Copy</button>
                            <button type="button" class="btn btn-primary" id="pos-preview-print-customer">Print Customer Copy</button>
                        </div>
                    </div>
                </div>
                <div id="pos-print-area" class="pos-print-area" aria-hidden="true"></div>

<?php $content = ob_get_clean(); ?>
<!-- This code is useful for content -->

<!-- This code is useful for internal scripts  -->
<?php ob_start(); ?>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var orderOptions = Array.prototype.slice.call(document.querySelectorAll('.order-type-option'));
                var deliveryOption = document.getElementById('order-type-delivery');
                var deliveryFields = Array.prototype.slice.call(document.querySelectorAll('.pos-terminal-delivery-fields'));

                function updateDeliveryVisibility() {
                    if (!deliveryFields.length || !deliveryOption) {
                        return;
                    }

                    var showDelivery = deliveryOption.checked;
                    deliveryFields.forEach(function (fieldRow) {
                        fieldRow.classList.toggle('d-none', !showDelivery);
                        var deliveryInputs = fieldRow.querySelectorAll('input, textarea');
                        deliveryInputs.forEach(function (input) {
                            input.disabled = !showDelivery;
                        });
                    });
                }

                var customerSelect = document.getElementById('pos-customer');
                var storeNameInput = document.getElementById('pos-store-name');
                var storeAddressInput = document.getElementById('pos-store-address');

                function isCustomerSelected() {
                    if (!customerSelect) {
                        return false;
                    }

                    var option = customerSelect.options[customerSelect.selectedIndex];
                    return !!(option && option.value);
                }

                function updateCustomerDetails() {
                    if (!customerSelect) {
                        return;
                    }

                    var option = customerSelect.options[customerSelect.selectedIndex];
                    var storeName = option && option.dataset ? (option.dataset.store || '') : '';
                    var address = option && option.dataset ? (option.dataset.address || '') : '';

                    if (storeNameInput) {
                        storeNameInput.value = storeName;
                    }
                    if (storeAddressInput) {
                        storeAddressInput.value = address;
                    }
                }

                function setCustomerDependentEnabled(enabled) {
                    orderOptions.forEach(function (option) {
                        option.disabled = !enabled;
                        if (!enabled) {
                            option.checked = false;
                        }
                    });

                    if (orderDateTimeInput) {
                        orderDateTimeInput.disabled = !enabled;
                        if (enabled && orderDateTimeInput.value === '') {
                            orderDateTimeInput.value = formatLocalDateTimeValue(new Date());
                        }
                        if (!enabled) {
                            orderDateTimeInput.value = '';
                        }
                    }

                    if (productSelect) {
                        productSelect.disabled = !enabled;
                    }
                    if (qtyInput) {
                        qtyInput.disabled = !enabled;
                    }
                    if (lineItemDiscountInput) {
                        lineItemDiscountInput.disabled = !enabled;
                    }
                    if (addButton) {
                        addButton.disabled = !enabled;
                    }
                    if (discountInput) {
                        discountInput.disabled = !enabled;
                    }
                    if (amountReceivedInput) {
                        amountReceivedInput.disabled = !enabled;
                    }

                    updateDeliveryVisibility();
                    updateSubmitEnabled();
                }

                orderOptions.forEach(function (option) {
                    option.addEventListener('change', function () {
                        if (this.checked) {
                            orderOptions.forEach(function (other) {
                                if (other !== option) {
                                    other.checked = false;
                                }
                            });
                        }
                        updateDeliveryVisibility();
                    });
                });

                var productSelect = document.getElementById('pos-product');
                var qtyInput = document.getElementById('pos-quantity');
                var lineItemDiscountInput = document.getElementById('pos-lineitem-discount');
                var priceDisplay = document.getElementById('pos-unit-price');
                var addButton = document.getElementById('pos-add-item');
                var lineItemsBody = document.getElementById('pos-lineitems-body');
                var emptyState = document.getElementById('pos-empty-state');
                var subtotalEl = document.getElementById('pos-subtotal');
                var lineItemDiscountTotalEl = document.getElementById('pos-lineitem-discount-total');
                var discountInput = document.getElementById('pos-discount');
                var additionalChargesEl = document.getElementById('pos-additional-charges');
                var grandTotalEl = document.getElementById('pos-grand-total');
                var amountDueEl = document.getElementById('pos-amount-due');
                var amountReceivedInput = document.getElementById('pos-amount-received');
                var changeEl = document.getElementById('pos-change');
                var submitButton = document.getElementById('pos-submit-order');
                var dateDeliveryInput = document.getElementById('date_delivery');
                var timeDeliveryInput = document.getElementById('time_deliver');
                var deliveryInstructionInput = document.getElementById('delivery_instruction');
                var orderDateTimeInput = document.getElementById('date_time_ordered');
                var lineItems = [];
                var previewModal = document.getElementById('pos-preview-modal');
                var previewReceipt = document.getElementById('pos-receipt-preview');
                var previewCloseButton = document.getElementById('pos-preview-close');
                var previewCancelButton = document.getElementById('pos-preview-cancel');
                var previewPrintStoreButton = document.getElementById('pos-preview-print-store');
                var previewPrintCustomerButton = document.getElementById('pos-preview-print-customer');
                var printArea = document.getElementById('pos-print-area');
                var previewBusy = false;
                var reservedOrderNo = '';
                var reservingOrderNo = false;
                var savedOrderNo = '';
                var savedPrintedAt = '';
                var orderSaved = false;
                var savedPaymentSummary = null;

                if (customerSelect) {
                    customerSelect.addEventListener('change', function () {
                        updateCustomerDetails();
                        setCustomerDependentEnabled(isCustomerSelected());
                    });
                }

                updateDeliveryVisibility();
                updateCustomerDetails();
                setCustomerDependentEnabled(isCustomerSelected());

                function updateSubmitEnabled() {
                    if (!submitButton) {
                        return;
                    }

                    var canSubmit = isCustomerSelected() && lineItems && lineItems.length > 0;
                    submitButton.disabled = !canSubmit;
                }

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

                function getAmountReceivedValue() {
                    if (!amountReceivedInput) {
                        return 0;
                    }
                    var received = parseFloat(amountReceivedInput.value || '0');
                    if (Number.isNaN(received) || received < 0) {
                        received = 0;
                    }
                    return received;
                }

                function getPaymentSummary(totals) {
                    var amountDue = totals ? totals.grandTotal : 0;
                    var amountReceived = getAmountReceivedValue();
                    var change = amountReceived - amountDue;
                    if (change < 0) {
                        change = 0;
                    }
                    return {
                        amountDue: amountDue,
                        amountReceived: amountReceived,
                        change: change
                    };
                }

                function escapeHtml(value) {
                    return String(value || '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                }

                function formatQty(value) {
                    var qty = Number(value);
                    if (Number.isNaN(qty)) {
                        return '0';
                    }
                    return Number.isInteger(qty) ? String(qty) : qty.toFixed(2);
                }

                function pad2(value) {
                    return String(value).padStart(2, '0');
                }

                function formatTimestamp(dateObj) {
                    return dateObj.getFullYear() + '-' + pad2(dateObj.getMonth() + 1) + '-' + pad2(dateObj.getDate())
                        + ' ' + pad2(dateObj.getHours()) + ':' + pad2(dateObj.getMinutes()) + ':' + pad2(dateObj.getSeconds());
                }

                function formatLocalDateTimeValue(dateObj) {
                    return dateObj.getFullYear() + '-' + pad2(dateObj.getMonth() + 1) + '-' + pad2(dateObj.getDate())
                        + 'T' + pad2(dateObj.getHours()) + ':' + pad2(dateObj.getMinutes());
                }

                function getOrderedAtValue() {
                    if (!orderDateTimeInput) {
                        return '';
                    }
                    var rawValue = orderDateTimeInput.value || '';
                    if (!rawValue) {
                        return '';
                    }
                    var dateObj = new Date(rawValue);
                    if (Number.isNaN(dateObj.getTime())) {
                        return '';
                    }
                    return formatTimestamp(dateObj);
                }

                function calculateReceiptTotals() {
                    var subtotal = lineItems.reduce(function (sum, item) {
                        return sum + item.grossAmount;
                    }, 0);

                    var lineItemDiscountTotal = lineItems.reduce(function (sum, item) {
                        return sum + item.lineDiscount;
                    }, 0);

                    var discountPercent = discountInput ? parseFloat(discountInput.value || '0') : 0;
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

                function buildReceiptText(orderNo, printedAtText, copyLabel, paymentSummary) {
                    var storeName = storeNameInput ? storeNameInput.value.trim() : '';
                    var storeAddress = storeAddressInput ? storeAddressInput.value.trim() : '';
                    var customerCode = getCustomerCode();
                    var orderType = getOrderType();
                    var deliveryDate = dateDeliveryInput ? dateDeliveryInput.value : '';
                    var deliveryTime = timeDeliveryInput ? timeDeliveryInput.value : '';
                    var instruction = deliveryInstructionInput ? deliveryInstructionInput.value.trim() : '';
                    var totals = calculateReceiptTotals();
                    var payment = paymentSummary || getPaymentSummary(totals);
                    var drNumber = orderNo ? 'DR' + orderNo : 'DR-PENDING';
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
                        var orderLabel = orderType === 'delivery' ? 'Delivery' : 'Store Pickup';
                        pushWrapped(lines, 'Order Type: ' + orderLabel, width);
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

                    var totalQty = lineItems.reduce(function (sum, item) {
                        return sum + Number(item.qty || 0);
                    }, 0);

                    lineItems.forEach(function (item, index) {
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
                    if (copyLabel === 'STORE COPY') {
                        lines.push('');
                        lines.push('');

                        lines.push('RECEIVED BY: _______________________________');
                        lines.push(centerText('        Printed Name & Signature', width));
                        lines.push('');
                        lines.push('');
                    }
                    lines.push(centerText(copyLabel, width));

                    while (lines.length && String(lines[lines.length - 1]).trim() === '') {
                        lines.pop();
                    }

                    return lines.join('\n');
                }

                function renderReceiptCopies(orderNo, printedAtText, paymentSummary) {
                    if (!previewReceipt || !printArea) {
                        return;
                    }
                    var storeText = buildReceiptText(orderNo, printedAtText, 'STORE COPY', paymentSummary);
                    var customerText = buildReceiptText(orderNo, printedAtText, 'CUSTOMER COPY', paymentSummary);
                    var html = '<div class="pos-receipt"><pre class="pos-receipt-text">' + escapeHtml(storeText) + '</pre></div>'
                        + '<div class="pos-receipt-spacer"></div>'
                        + '<div class="pos-receipt"><pre class="pos-receipt-text">' + escapeHtml(customerText) + '</pre></div>';
                    previewReceipt.innerHTML = html;
                }

                function renderPrintCopy(copyLabel, orderNo, printedAtText, paymentSummary) {
                    if (!printArea) {
                        return;
                    }
                    var copyText = buildReceiptText(orderNo, printedAtText, copyLabel, paymentSummary);
                    printArea.innerHTML = '<div class="pos-receipt"><pre class="pos-receipt-text">' + escapeHtml(copyText) + '</pre></div>';
                }

                function updatePrintButtons() {
                    if (previewPrintStoreButton) {
                        var storeLabel = orderSaved ? 'Reprint Store Copy' : 'Print Store Copy';
                        if (previewBusy && !orderSaved) {
                            storeLabel = 'Saving...';
                        } else if (previewBusy && orderSaved) {
                            storeLabel = 'Printing...';
                        }
                        previewPrintStoreButton.textContent = storeLabel;
                        previewPrintStoreButton.disabled = previewBusy;
                    }
                    if (previewPrintCustomerButton) {
                        previewPrintCustomerButton.disabled = !orderSaved || previewBusy;
                    }
                }

                function requestReservedOrderNo() {
                    if (reservingOrderNo || reservedOrderNo) {
                        if (reservedOrderNo) {
                            renderReceiptCopies(reservedOrderNo, getOrderedAtValue());
                        }
                        return;
                    }

                    reservingOrderNo = true;
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ action: 'reserve_order_no' })
                    })
                        .then(function (response) {
                            return response.json().then(function (data) {
                                return {
                                    ok: response.ok,
                                    data: data
                                };
                            });
                        })
                        .then(function (result) {
                            if (!result.ok || !result.data || !result.data.success) {
                                var message = result.data && result.data.message ? result.data.message : 'Unable to reserve order number.';
                                throw new Error(message);
                            }
                            reservedOrderNo = result.data.order_no || '';
                            if (reservedOrderNo) {
                                renderReceiptCopies(reservedOrderNo, getOrderedAtValue());
                            }
                        })
                        .catch(function (error) {
                            window.alert(error && error.message ? error.message : 'Unable to reserve order number.');
                        })
                        .then(function () {
                            reservingOrderNo = false;
                        });
                }

                function openPreviewModal() {
                    if (!previewModal) {
                        return;
                    }
                    var orderedAtText = getOrderedAtValue();
                    renderReceiptCopies(reservedOrderNo, orderedAtText, savedPaymentSummary);
                    previewModal.classList.remove('d-none');
                    previewModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('pos-modal-open');
                    updatePrintButtons();
                    requestReservedOrderNo();
                }

                function closePreviewModal(forceClose) {
                    if (!previewModal || (previewBusy && !forceClose)) {
                        return;
                    }
                    previewModal.classList.add('d-none');
                    previewModal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('pos-modal-open');
                }

                function getSelectedProduct() {
                    if (!productSelect) {
                        return null;
                    }

                    var option = productSelect.options[productSelect.selectedIndex];
                    if (!option || !option.dataset || typeof option.dataset.price === 'undefined') {
                        return null;
                    }

                    return {
                        code: option.dataset.code || '',
                        description: option.dataset.description || '',
                        uom: option.dataset.uom || '',
                        unitPrice: parseFloat(option.dataset.price || '0')
                    };
                }

                function updateUnitPrice() {
                    var product = getSelectedProduct();
                    var price = product ? product.unitPrice : 0;
                    if (priceDisplay) {
                        priceDisplay.textContent = formatCurrency(price);
                    }
                }

                function updateTotals() {
                    var subtotal = lineItems.reduce(function (sum, item) {
                        return sum + item.grossAmount;
                    }, 0);

                    var lineItemDiscountTotal = lineItems.reduce(function (sum, item) {
                        return sum + item.lineDiscount;
                    }, 0);

                    var discountPercent = discountInput ? parseFloat(discountInput.value || '0') : 0;
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
                    var amountReceived = getAmountReceivedValue();
                    var changeAmount = amountReceived - grandTotal;
                    if (changeAmount < 0) {
                        changeAmount = 0;
                    }

                    if (subtotalEl) {
                        subtotalEl.textContent = formatCurrency(subtotal);
                    }
                    if (lineItemDiscountTotalEl) {
                        lineItemDiscountTotalEl.textContent = formatCurrency(lineItemDiscountTotal);
                    }
                    if (additionalChargesEl) {
                        additionalChargesEl.textContent = formatCurrency(additionalCharges);
                    }
                    if (grandTotalEl) {
                        grandTotalEl.textContent = formatCurrency(grandTotal);
                    }
                    if (amountDueEl) {
                        amountDueEl.textContent = formatCurrency(grandTotal);
                    }
                    if (changeEl) {
                        changeEl.textContent = formatCurrency(changeAmount);
                    }
                }

                function updateEmptyState() {
                    if (!emptyState) {
                        return;
                    }
                    emptyState.classList.toggle('d-none', lineItems.length > 0);
                }

                function renderLineItems() {
                    if (!lineItemsBody) {
                        return;
                    }
                    lineItemsBody.innerHTML = '';
                    lineItems.forEach(function (item, index) {
                        addLineItemRow(item, index + 1, index);
                    });
                }

                function removeLineItem(itemIndex) {
                    if (!lineItems || itemIndex < 0 || itemIndex >= lineItems.length) {
                        return;
                    }
                    lineItems.splice(itemIndex, 1);
                    renderLineItems();
                    updateEmptyState();
                    updateTotals();
                    updateSubmitEnabled();
                }

                function addLineItemRow(item, lineNumber, itemIndex) {
                    if (!lineItemsBody) {
                        return;
                    }

                    var row = document.createElement('tr');

                    var lineCell = document.createElement('td');
                    lineCell.className = 'text-center';
                    lineCell.textContent = lineNumber;
                    row.appendChild(lineCell);

                    var codeCell = document.createElement('td');
                    codeCell.textContent = item.code;
                    row.appendChild(codeCell);

                    var descCell = document.createElement('td');
                    descCell.textContent = item.description;
                    row.appendChild(descCell);

                    var uomCell = document.createElement('td');
                    uomCell.textContent = item.uom;
                    row.appendChild(uomCell);

                    var qtyCell = document.createElement('td');
                    qtyCell.className = 'text-end';
                    qtyCell.textContent = item.qty;
                    row.appendChild(qtyCell);

                    var unitPriceCell = document.createElement('td');
                    unitPriceCell.className = 'text-end';
                    unitPriceCell.textContent = formatCurrency(item.unitPrice);
                    row.appendChild(unitPriceCell);

                    var discountCell = document.createElement('td');
                    discountCell.className = 'text-end';
                    discountCell.textContent = formatCurrency(item.lineDiscount);
                    row.appendChild(discountCell);

                    var amountCell = document.createElement('td');
                    amountCell.className = 'text-end';
                    amountCell.textContent = formatCurrency(item.amount);
                    row.appendChild(amountCell);

                    var actionCell = document.createElement('td');
                    actionCell.className = 'text-center';
                    var removeButton = document.createElement('button');
                    removeButton.type = 'button';
                    removeButton.className = 'btn btn-sm btn-outline-danger pos-lineitem-remove';
                    removeButton.textContent = 'Remove';
                    removeButton.setAttribute('aria-label', 'Remove line item');
                    removeButton.addEventListener('click', function () {
                        removeLineItem(itemIndex);
                    });
                    actionCell.appendChild(removeButton);
                    row.appendChild(actionCell);

                    lineItemsBody.appendChild(row);
                }

                function resetProductInputs() {
                    if (productSelect) {
                        productSelect.selectedIndex = 0;
                    }
                    if (qtyInput) {
                        qtyInput.value = '1';
                    }
                    if (lineItemDiscountInput) {
                        lineItemDiscountInput.value = '0';
                    }
                    updateUnitPrice();
                }

                if (productSelect) {
                    productSelect.addEventListener('change', updateUnitPrice);
                    updateUnitPrice();
                }

                if (discountInput) {
                    ['input', 'change'].forEach(function (eventName) {
                        discountInput.addEventListener(eventName, updateTotals);
                    });
                }

                if (amountReceivedInput) {
                    ['input', 'change'].forEach(function (eventName) {
                        amountReceivedInput.addEventListener(eventName, updateTotals);
                    });
                }

                if (addButton) {
                    addButton.addEventListener('click', function () {
                        if (!isCustomerSelected()) {
                            window.alert('Select a customer before adding items.');
                            return;
                        }

                        var product = getSelectedProduct();
                        var qty = qtyInput ? parseFloat(qtyInput.value || '0') : 0;

                        if (!product) {
                            window.alert('Select a product before adding.');
                            return;
                        }

                        if (Number.isNaN(qty) || qty <= 0) {
                            window.alert('Enter a valid quantity.');
                            return;
                        }

                        var grossAmount = qty * product.unitPrice;
                        var unitDiscount = lineItemDiscountInput ? parseFloat(lineItemDiscountInput.value || '0') : 0;
                        if (Number.isNaN(unitDiscount) || unitDiscount < 0) {
                            unitDiscount = 0;
                        }
                        if (unitDiscount > product.unitPrice) {
                            window.alert('Line discount cannot exceed the unit price.');
                            return;
                        }

                        var lineDiscount = unitDiscount * qty;
                        var amount = grossAmount - lineDiscount;
                        var item = {
                            code: product.code,
                            description: product.description,
                            uom: product.uom,
                            qty: qty,
                            unitPrice: product.unitPrice,
                            unitDiscount: unitDiscount,
                            lineDiscount: lineDiscount,
                            grossAmount: grossAmount,
                            amount: amount
                        };

                        lineItems.push(item);
                        renderLineItems();
                        updateEmptyState();
                        updateTotals();
                        updateSubmitEnabled();
                        resetProductInputs();
                    });
                }

                updateEmptyState();
                updateTotals();
                updateSubmitEnabled();

                function getOrderType() {
                    var selected = '';
                    orderOptions.forEach(function (option) {
                        if (option.checked) {
                            selected = option.value;
                        }
                    });
                    return selected;
                }

                function getCustomerCode() {
                    if (!customerSelect) {
                        return '';
                    }

                    var option = customerSelect.options[customerSelect.selectedIndex];
                    return option && option.dataset ? (option.dataset.code || '') : '';
                }

                function buildOrderPayload(printedAtText, orderNo, paymentSummary, orderedAtText) {
                    var discountValue = discountInput ? parseFloat(discountInput.value || '0') : 0;
                    if (Number.isNaN(discountValue)) {
                        discountValue = 0;
                    }
                    var totals = calculateReceiptTotals();
                    var payment = paymentSummary || getPaymentSummary(totals);

                    return {
                        order_no: orderNo || '',
                        customer_id: customerSelect ? customerSelect.value : '',
                        customer_code: getCustomerCode(),
                        order_type: getOrderType(),
                        delivery_instruction: deliveryInstructionInput ? deliveryInstructionInput.value.trim() : '',
                        date_delivery: dateDeliveryInput ? dateDeliveryInput.value : '',
                        time_delivery: timeDeliveryInput ? timeDeliveryInput.value : '',
                        date_time_ordered: orderedAtText || '',
                        discount: discountValue,
                        items: lineItems.map(function (item) {
                            return {
                                product_code: item.code,
                                product_description: item.description,
                                quantity: item.qty,
                                unit_price: item.unitPrice,
                                lineitem_discount: item.unitDiscount
                            };
                        }),
                        printed_at: printedAtText || '',
                        amt_received: payment.amountReceived,
                        amt_change: payment.change
                    };
                }

                function resetOrderForm() {
                    lineItems = [];
                    reservedOrderNo = '';
                    savedOrderNo = '';
                    savedPrintedAt = '';
                    orderSaved = false;
                    previewBusy = false;
                    savedPaymentSummary = null;
                    if (amountReceivedInput) {
                        amountReceivedInput.value = '0';
                    }
                    updatePrintButtons();
                    if (lineItemsBody) {
                        lineItemsBody.innerHTML = '';
                    }
                    if (discountInput) {
                        discountInput.value = '0';
                    }
                    if (deliveryInstructionInput) {
                        deliveryInstructionInput.value = '';
                    }
                    if (dateDeliveryInput) {
                        dateDeliveryInput.value = '';
                    }
                    if (timeDeliveryInput) {
                        timeDeliveryInput.value = '';
                    }
                    if (orderDateTimeInput) {
                        orderDateTimeInput.value = '';
                    }
                    updateEmptyState();
                    updateTotals();
                    updateSubmitEnabled();
                    resetProductInputs();
                }

                if (submitButton) {
                    submitButton.addEventListener('click', function () {
                        if (!isCustomerSelected()) {
                            window.alert('Select a customer before submitting.');
                            return;
                        }

                        if (lineItems.length === 0) {
                            window.alert('Add at least one item before submitting.');
                            return;
                        }
                        if (!getOrderedAtValue()) {
                            window.alert('Enter the order date and time.');
                            return;
                        }
                        openPreviewModal();
                    });
                }

                if (previewCloseButton) {
                    previewCloseButton.addEventListener('click', closePreviewModal);
                }

                if (previewCancelButton) {
                    previewCancelButton.addEventListener('click', closePreviewModal);
                }

                if (previewModal) {
                    previewModal.addEventListener('click', function (event) {
                        if (event.target && event.target.classList.contains('pos-preview-backdrop')) {
                            closePreviewModal();
                        }
                    });
                }

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && previewModal && !previewModal.classList.contains('d-none')) {
                        closePreviewModal();
                    }
                });

                if (previewPrintStoreButton) {
                    previewPrintStoreButton.addEventListener('click', function () {
                        if (previewBusy) {
                            return;
                        }

                        if (!isCustomerSelected()) {
                            window.alert('Select a customer before printing.');
                            return;
                        }

                        if (lineItems.length === 0) {
                            window.alert('Add at least one item before printing.');
                            return;
                        }

                        if (orderSaved) {
                            previewBusy = true;
                            updatePrintButtons();
                            renderPrintCopy('STORE COPY', savedOrderNo, savedPrintedAt, savedPaymentSummary);
                            window.print();
                            previewBusy = false;
                            updatePrintButtons();
                            return;
                        }

                        var orderedAtText = getOrderedAtValue();
                        if (!orderedAtText) {
                            window.alert('Enter the order date and time.');
                            return;
                        }

                        var printedAtText = formatTimestamp(new Date());
                        var totals = calculateReceiptTotals();
                        var paymentSummary = getPaymentSummary(totals);
                        var payload = buildOrderPayload(printedAtText, reservedOrderNo, paymentSummary, orderedAtText);

                        previewBusy = true;
                        updatePrintButtons();

                        fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        })
                            .then(function (response) {
                                return response.json().then(function (data) {
                                    return {
                                        ok: response.ok,
                                        data: data
                                    };
                                });
                            })
                            .then(function (result) {
                                if (!result.ok || !result.data || !result.data.success) {
                                    var message = result.data && result.data.message ? result.data.message : 'Unable to submit order.';
                                    throw new Error(message);
                                }

                                savedOrderNo = result.data.order_no || reservedOrderNo;
                                savedPrintedAt = orderedAtText;
                                savedPaymentSummary = paymentSummary;
                                orderSaved = true;
                                renderReceiptCopies(savedOrderNo, savedPrintedAt, savedPaymentSummary);
                                renderPrintCopy('STORE COPY', savedOrderNo, savedPrintedAt, savedPaymentSummary);
                                window.print();
                            })
                            .catch(function (error) {
                                window.alert(error && error.message ? error.message : 'Unable to submit order.');
                            })
                            .then(function () {
                                previewBusy = false;
                                updatePrintButtons();
                            });
                    });
                }

                if (previewPrintCustomerButton) {
                    previewPrintCustomerButton.addEventListener('click', function () {
                        if (previewBusy) {
                            return;
                        }

                        if (!orderSaved) {
                            window.alert('Print the store copy first.');
                            return;
                        }

                        previewBusy = true;
                        updatePrintButtons();
                        renderPrintCopy('CUSTOMER COPY', savedOrderNo, savedPrintedAt, savedPaymentSummary);
                        window.print();
                        previewBusy = false;
                        updatePrintButtons();
                        resetOrderForm();
                        closePreviewModal(true);
                    });
                }
            });
        </script>

<?php $scripts = ob_get_clean(); ?>
<!-- This code is useful for internal scripts  -->

<!-- This code use for render base file -->
<?php include 'layouts/base.php'; ?>
<!-- This code use for render base file -->
