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
                                        <td><?php echo htmlspecialchars('DR' . $order['order_no']); ?></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($order['customer_name'] !== '' ? $order['customer_name'] : $order['customer_code']); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($order['customer_code']); ?></div>
                                        </td>
                                        <td class="text-end"><?php echo number_format($order['total_qty'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($order['date_created']); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-primary pos-print-order" data-order-no="<?php echo htmlspecialchars($order['order_no']); ?>">
                                                Print
                                            </button>
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
                }
            };
            table.DataTable(options);
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

            var printButtons = document.querySelectorAll('.pos-print-order');
            printButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    var orderNo = button.getAttribute('data-order-no');
                    if (!orderNo) {
                        return;
                    }
                    handlePrintClick(orderNo);
                });
            });
        });
    </script>
    <?php
    return ob_get_clean();
};
$scripts = $scriptsBuffer();

include 'layouts/base.php';
