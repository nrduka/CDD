<!-- This code generates the base URL for the website by combining the protocol, domain name, and directory path -->
<?php
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>


<?php 
session_start();
$role = $_SESSION['sess_userrole'];
$fullname = $_SESSION['sess_firstname'] . " " . $_SESSION['sess_lastname'];

require_once __DIR__ . '/db2.php';

$customers = [];
$products = [];
$customerError = null;
$productError = null;

$connectionAvailable = isset($mysqli) && $mysqli;

if (!$connectionAvailable) {
    $customerError = 'Database connection unavailable.';
    $productError = 'Database connection unavailable.';
} else {
$query = "SELECT id, customer_code, firstName, middleName, lastName, storeName, address, type FROM customer_register ORDER BY storeName, lastName, firstName";
    if ($result = $mysqli->query($query)) {
        while ($row = $result->fetch_assoc()) {
            $customers[] = $row;
        }
        $result->free();
    } else {
        $customerError = 'Customer list unavailable.';
    }

    $query = "SELECT id, product_code, product_description, uom, unit_price FROM ice_product ORDER BY product_description, product_code";
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

<?php $styles = ob_get_clean(); ?>
<!-- This code is useful for internal styles  -->

<!-- This code is useful for content -->
<?php ob_start(); ?>

                <div class="container-fluid">

                    <!-- Start::page-header -->
                    <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
                        <div>
                            <p class="fw-semibold fs-18 mb-0">POINT-OF-SALES</p>
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

                                                            $detailParts = [];
                                                            if ($code !== '' && $displayName !== $code) {
                                                                $detailParts[] = $code;
                                                            }
                                                            if ($type !== '') {
                                                                $detailParts[] = $type;
                                                            }

                                                            $label = $displayName;
                                                            if (!empty($detailParts)) {
                                                                $label .= ' - ' . implode(' | ', $detailParts);
                                                            }
                                                        ?>
                                                        <option value="<?php echo htmlspecialchars((string)$customer['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-store="<?php echo htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-address="<?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                    </div>

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
                                        <div class="col-12 col-md-5 col-lg-4">
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
                                        <div class="col-12 col-md-3 col-lg-2">
                                            <label class="form-label fw-semibold">Quantity</label>
                                            <input type="number" class="form-control" id="pos-quantity" value="1" min="1" disabled>
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
                                                        <th>Product Code</th>
                                                        <th>Description</th>
                                                        <th>UOM</th>
                                                        <th class="text-end">Qty</th>
                                                        <th class="text-end">Unit Price</th>
                                                        <th class="text-end">Amount</th>
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

                    if (productSelect) {
                        productSelect.disabled = !enabled;
                    }
                    if (qtyInput) {
                        qtyInput.disabled = !enabled;
                    }
                    if (addButton) {
                        addButton.disabled = !enabled;
                    }
                    if (discountInput) {
                        discountInput.disabled = !enabled;
                    }
                    if (submitButton) {
                        submitButton.disabled = !enabled;
                    }

                    updateDeliveryVisibility();
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
                var priceDisplay = document.getElementById('pos-unit-price');
                var addButton = document.getElementById('pos-add-item');
                var lineItemsBody = document.getElementById('pos-lineitems-body');
                var emptyState = document.getElementById('pos-empty-state');
                var subtotalEl = document.getElementById('pos-subtotal');
                var discountInput = document.getElementById('pos-discount');
                var additionalChargesEl = document.getElementById('pos-additional-charges');
                var grandTotalEl = document.getElementById('pos-grand-total');
                var submitButton = document.getElementById('pos-submit-order');

                if (customerSelect) {
                    customerSelect.addEventListener('change', function () {
                        updateCustomerDetails();
                        setCustomerDependentEnabled(isCustomerSelected());
                    });
                }

                updateDeliveryVisibility();
                updateCustomerDetails();
                setCustomerDependentEnabled(isCustomerSelected());

                var lineItems = [];

                function formatCurrency(value) {
                    var amount = Number(value);
                    if (Number.isNaN(amount)) {
                        amount = 0;
                    }
                    return 'PHP ' + amount.toFixed(2);
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
                        return sum + item.amount;
                    }, 0);

                    var discountPercent = discountInput ? parseFloat(discountInput.value || '0') : 0;
                    if (Number.isNaN(discountPercent) || discountPercent < 0) {
                        discountPercent = 0;
                    }
                    if (discountPercent > 100) {
                        discountPercent = 100;
                    }

                    var discountAmount = subtotal * (discountPercent / 100);
                    var additionalCharges = 0;
                    var grandTotal = subtotal - discountAmount + additionalCharges;

                    if (subtotalEl) {
                        subtotalEl.textContent = formatCurrency(subtotal);
                    }
                    if (additionalChargesEl) {
                        additionalChargesEl.textContent = formatCurrency(additionalCharges);
                    }
                    if (grandTotalEl) {
                        grandTotalEl.textContent = formatCurrency(grandTotal);
                    }
                }

                function updateEmptyState() {
                    if (!emptyState) {
                        return;
                    }
                    emptyState.classList.toggle('d-none', lineItems.length > 0);
                }

                function addLineItemRow(item) {
                    if (!lineItemsBody) {
                        return;
                    }

                    var row = document.createElement('tr');

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

                    var amountCell = document.createElement('td');
                    amountCell.className = 'text-end';
                    amountCell.textContent = formatCurrency(item.amount);
                    row.appendChild(amountCell);

                    lineItemsBody.appendChild(row);
                }

                function resetProductInputs() {
                    if (productSelect) {
                        productSelect.selectedIndex = 0;
                    }
                    if (qtyInput) {
                        qtyInput.value = '1';
                    }
                    updateUnitPrice();
                }

                if (productSelect) {
                    productSelect.addEventListener('change', updateUnitPrice);
                    updateUnitPrice();
                }

                if (discountInput) {
                    discountInput.addEventListener('input', updateTotals);
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

                        var amount = qty * product.unitPrice;
                        var item = {
                            code: product.code,
                            description: product.description,
                            uom: product.uom,
                            qty: qty,
                            unitPrice: product.unitPrice,
                            amount: amount
                        };

                        lineItems.push(item);
                        addLineItemRow(item);
                        updateEmptyState();
                        updateTotals();
                        resetProductInputs();
                    });
                }

                updateEmptyState();
                updateTotals();
            });
        </script>

<?php $scripts = ob_get_clean(); ?>
<!-- This code is useful for internal scripts  -->

<!-- This code use for render base file -->
<?php include 'layouts/base.php'; ?>
<!-- This code use for render base file -->
