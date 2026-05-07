<?php
// Base URL setup
$rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));

session_start();

require_once __DIR__ . '/db2.php';

$errors = [];
$successMessage = '';

$formData = [
    'datetime_started' => '',
    'datetime_ended' => '',
    'machine' => '',
    'finished_ice_qty' => '',
    'loose_ice_qty' => '',
    'remarks' => '',
    'product_type' => '',
];

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sanitizeText($value)
{
    return trim((string)$value);
}

function generate_dpr_code($mysqli, $productionDate)
{
    $dateValue = DateTime::createFromFormat('Y-m-d', $productionDate);
    if (!$dateValue) {
        return [null, 'Invalid production date for DPR code.'];
    }

    $prefix = 'DPR-' . $dateValue->format('y-m-d') . '-';
    $sequenceSql = "INSERT INTO ice_production_sequence (prefix, last_number)
        VALUES (?, LAST_INSERT_ID(1))
        ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)";
    $stmt = $mysqli->prepare($sequenceSql);
    if (!$stmt) {
        return [null, 'Unable to prepare DPR sequence.'];
    }
    $stmt->bind_param('s', $prefix);
    if (!$stmt->execute()) {
        $stmt->close();
        return [null, 'Unable to generate DPR code.'];
    }
    $seriesNumber = (int)$mysqli->insert_id;
    $stmt->close();
    if ($seriesNumber <= 0) {
        return [null, 'Invalid DPR code generated.'];
    }
    return [$prefix . sprintf('%03d', $seriesNumber), null];
}

function is_dpr_duplicate_key_error($stmt, $mysqli)
{
    $errorNo = 0;
    if ($stmt && $stmt->errno) {
        $errorNo = $stmt->errno;
    } elseif ($mysqli && $mysqli->errno) {
        $errorNo = $mysqli->errno;
    }
    return $errorNo === 1062;
}

$machineOptions = [];
$machineProductTypes = [];
if (isset($mysqli) && $mysqli) {
    $result = $mysqli->query('SELECT id, machine_type, machine_description, product_type FROM ice_machine ORDER BY machine_description');
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $machineOptions[] = $row;
            if (isset($row['product_type'])) {
                $machineProductTypes[(int)$row['id']] = (string)$row['product_type'];
            }
        }
        $result->free();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $key => $value) {
        $formData[$key] = sanitizeText(isset($_POST[$key]) ? $_POST[$key] : '');
    }

    if (!isset($mysqli) || !$mysqli) {
        $errors[] = 'Database connection unavailable.';
    }

    $startedAt = null;
    $endedAt = null;

    if ($formData['datetime_started'] === '') {
        $errors[] = 'Date/time started is required.';
    } else {
        $startedAt = DateTime::createFromFormat('Y-m-d\TH:i', $formData['datetime_started']);
        if (!$startedAt || $startedAt->format('Y-m-d\TH:i') !== $formData['datetime_started']) {
            $errors[] = 'Date/time started must be a valid date and time.';
        }
    }

    if ($formData['datetime_ended'] === '') {
        $errors[] = 'Date/time end is required.';
    } else {
        $endedAt = DateTime::createFromFormat('Y-m-d\TH:i', $formData['datetime_ended']);
        if (!$endedAt || $endedAt->format('Y-m-d\TH:i') !== $formData['datetime_ended']) {
            $errors[] = 'Date/time end must be a valid date and time.';
        }
    }

    if ($startedAt && $endedAt && $endedAt < $startedAt) {
        $errors[] = 'Date/time end cannot be earlier than date/time started.';
    }

    if ($formData['machine'] === '' || !ctype_digit($formData['machine'])) {
        $errors[] = 'Please select a machine.';
    }

    if ($formData['finished_ice_qty'] === '') {
        $errors[] = 'Finished ice quantity is required.';
    } elseif (!is_numeric($formData['finished_ice_qty'])) {
        $errors[] = 'Finished ice quantity must be a valid number.';
    }

    if ($formData['loose_ice_qty'] === '') {
        $errors[] = 'Loose ice quantity is required.';
    } elseif (!is_numeric($formData['loose_ice_qty'])) {
        $errors[] = 'Loose ice quantity must be a valid number.';
    }

    $machineIds = array_column($machineOptions, 'id');
    if ($formData['machine'] !== '' && $machineIds) {
        if (!in_array((int)$formData['machine'], array_map('intval', $machineIds), true)) {
            $errors[] = 'Selected machine is invalid.';
        }
    }
    if ($formData['machine'] !== '' && isset($machineProductTypes[(int)$formData['machine']])) {
        $formData['product_type'] = $machineProductTypes[(int)$formData['machine']];
    }

    if (!$errors) {
        $datetime_started = $startedAt->format('Y-m-d H:i:s');
        $datetime_ended = $endedAt->format('Y-m-d H:i:s');
        $date_production = $startedAt->format('Y-m-d');
        $machine = (int)$formData['machine'];
        $finished_ice_qty = (float)$formData['finished_ice_qty'];
        $loose_ice_qty = (float)$formData['loose_ice_qty'];
        $operator_id = '';
        $assistant_id = null;
        $time_dropped = null;
        $drop_no = 0;
        $remarks = $formData['remarks'] !== '' ? $formData['remarks'] : null;
        $product_type = isset($machineProductTypes[$machine]) ? $machineProductTypes[$machine] : '';
        if ($product_type === '') {
            $errors[] = 'Product type is not configured for the selected machine.';
        }
    }

    if (!$errors) {
        $production_code = '';

        $insertSql = 'INSERT INTO ice_production (
                dpr_code,
                date_production,
                production_code,
                machine,
                operator_id,
                assistant_id,
                drop_no,
                time_dropped,
                datetime_started,
                datetime_ended,
                finished_ice_qty,
                loose_ice_qty,
                remarks,
                product_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        $stmt = $mysqli->prepare($insertSql);
        if (!$stmt) {
            $errors[] = 'Unable to prepare insert statement.';
        } else {
            $maxInsertAttempts = 3;
            $attempt = 0;
            while ($attempt < $maxInsertAttempts) {
                list($dpr_code, $dprError) = generate_dpr_code($mysqli, $date_production);
                if ($dprError) {
                    $errors[] = $dprError;
                    break;
                }

                $bindOk = $stmt->bind_param(
                    'sssississsddss',
                    $dpr_code,
                    $date_production,
                    $production_code,
                    $machine,
                    $operator_id,
                    $assistant_id,
                    $drop_no,
                    $time_dropped,
                    $datetime_started,
                    $datetime_ended,
                    $finished_ice_qty,
                    $loose_ice_qty,
                    $remarks,
                    $product_type
                );
                if (!$bindOk) {
                    $errors[] = 'Unable to bind parameters: ' . ($stmt->error ?: 'Unknown bind error.');
                    break;
                }
                if ($stmt->execute()) {
                    $successMessage = 'Ice production entry saved.';
                    foreach ($formData as $key => $value) {
                        $formData[$key] = is_array($value) ? [] : '';
                    }
                    break;
                }
                if (is_dpr_duplicate_key_error($stmt, $mysqli)) {
                    $attempt++;
                    continue;
                }
                $errors[] = 'Unable to save ice production entry: ' . ($stmt->error ?: 'Unknown database error.');
                break;
            }
            $stmt->close();
        }
    }
}
?>

<?php ob_start(); ?>
<style>
    .ice-production-card {
        border-radius: 16px;
        border: 1px solid #dbe3f2;
        box-shadow: 0 14px 30px rgba(15, 39, 80, 0.12);
    }
</style>
<?php $styles = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
        <div>
            <p class="fw-semibold fs-18 mb-0">ICE PRODUCTION REGISTER</p>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card ice-production-card">
                <div class="card-body">
                    <?php if ($errors): ?>
                        <div class="alert alert-danger">
                            <?php foreach ($errors as $error): ?>
                                <div><?php echo h($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="row g-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Date/Time Started *</label>
                            <input type="datetime-local" name="datetime_started" class="form-control" value="<?php echo h($formData['datetime_started']); ?>" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Date/Time End *</label>
                            <input type="datetime-local" name="datetime_ended" class="form-control" value="<?php echo h($formData['datetime_ended']); ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold">Machine *</label>
                            <select name="machine" class="form-select" required>
                                <option value="">Select machine</option>
                                <?php foreach ($machineOptions as $machine): ?>
                                    <option value="<?php echo (int)$machine['id']; ?>"
                                            data-product-type="<?php echo h($machine['product_type']); ?>"
                                            <?php echo $formData['machine'] === (string)$machine['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($machine['machine_description']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Finished Ice Qty *</label>
                            <div class="input-group">
                                <input type="number" name="finished_ice_qty" class="form-control" min="0" step="0.01" value="<?php echo h($formData['finished_ice_qty']); ?>" required>
                                <span class="input-group-text">KG</span>
                            </div>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Loose Ice Qty *</label>
                            <div class="input-group">
                                <input type="number" name="loose_ice_qty" class="form-control" min="0" step="0.01" value="<?php echo h($formData['loose_ice_qty']); ?>" required>
                                <span class="input-group-text">KG</span>
                            </div>
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Product Type</label>
                            <input type="text" name="product_type" class="form-control" value="<?php echo h($formData['product_type']); ?>" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Remarks</label>
                            <textarea name="remarks" rows="2" class="form-control"><?php echo h($formData['remarks']); ?></textarea>
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Save Entry</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>

<?php ob_start(); ?>
<script>
    function updateProductTypeFromMachine() {
        const machineSelect = document.querySelector('select[name="machine"]');
        const productTypeInput = document.querySelector('input[name="product_type"]');
        if (!machineSelect || !productTypeInput) return;
        const option = machineSelect.options[machineSelect.selectedIndex];
        const productType = option && option.dataset ? option.dataset.productType || '' : '';
        productTypeInput.value = productType;
    }

    document.addEventListener('DOMContentLoaded', () => {
        const machineSelect = document.querySelector('select[name="machine"]');
        if (machineSelect) {
            machineSelect.addEventListener('change', updateProductTypeFromMachine);
        }
        updateProductTypeFromMachine();
        <?php if ($successMessage): ?>
        alert('<?php echo h($successMessage); ?>');
        <?php endif; ?>
    });
</script>
<?php $scripts = ob_get_clean(); ?>

<?php include 'layouts/base.php'; ?>
