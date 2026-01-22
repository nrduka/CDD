<?php
// Base URL setup
$rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));

session_start();

require_once __DIR__ . '/db2.php';

$errors = [];
$successMessage = '';

$formData = [
    'production_date' => '',
    'dpr_no' => '',
    'period' => '',
    'machine' => '',
    'operator_name' => '',
    'assistant1_name' => '',
    'assistant2_name' => '',
    'drop_no' => '',
    'time_dropped' => '',
    'finished_ice_qty' => '',
    'loose_ice_qty' => '',
    'remarks' => '',
    'umo' => '',
    'umu' => '',
];

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function sanitizeText($value)
{
    return trim((string)$value);
}

$machineOptions = [];
if (isset($mysqli) && $mysqli) {
    $result = $mysqli->query('SELECT id, machine_type, machine_description FROM ice_machine ORDER BY machine_description');
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $machineOptions[] = $row;
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

    if ($formData['production_date'] === '') {
        $errors[] = 'Production date is required.';
    } elseif (!DateTime::createFromFormat('Y-m-d', $formData['production_date'])) {
        $errors[] = 'Production date must be in YYYY-MM-DD format.';
    }

    if ($formData['dpr_no'] === '') {
        $errors[] = 'DPR no. is required.';
    }

    if ($formData['period'] === '') {
        $errors[] = 'Period is required.';
    }

    if ($formData['machine'] === '' || !ctype_digit($formData['machine'])) {
        $errors[] = 'Please select a machine.';
    }

    if ($formData['operator_name'] === '') {
        $errors[] = 'Operator name is required.';
    }

    if ($formData['drop_no'] === '' || !ctype_digit($formData['drop_no'])) {
        $errors[] = 'Drop no. must be a whole number.';
    }

    if ($formData['finished_ice_qty'] === '' || !ctype_digit($formData['finished_ice_qty'])) {
        $errors[] = 'Finished ice quantity must be a whole number.';
    }

    if ($formData['loose_ice_qty'] === '') {
        $errors[] = 'Loose ice quantity is required.';
    } elseif (!is_numeric($formData['loose_ice_qty'])) {
        $errors[] = 'Loose ice quantity must be a valid number.';
    }

    if ($formData['umo'] === '') {
        $errors[] = 'UMO is required.';
    }

    if ($formData['umu'] === '') {
        $errors[] = 'UMU is required.';
    }

    $machineIds = array_column($machineOptions, 'id');
    if ($formData['machine'] !== '' && $machineIds) {
        if (!in_array((int)$formData['machine'], array_map('intval', $machineIds), true)) {
            $errors[] = 'Selected machine is invalid.';
        }
    }

    if (!$errors) {
        $machineId = (int)$formData['machine'];
        $dropNo = (int)$formData['drop_no'];
        $finishedQty = (int)$formData['finished_ice_qty'];
        $looseQty = (float)$formData['loose_ice_qty'];
        $timeDropped = $formData['time_dropped'] !== '' ? $formData['time_dropped'] : null;
        $assistant1 = $formData['assistant1_name'] !== '' ? $formData['assistant1_name'] : null;
        $assistant2 = $formData['assistant2_name'] !== '' ? $formData['assistant2_name'] : null;
        $remarks = $formData['remarks'] !== '' ? $formData['remarks'] : null;

        $insertSql = 'INSERT INTO ice_production (
                production_date,
                dpr_no,
                period,
                machine,
                operator_name,
                assistant1_name,
                assistant2_name,
                drop_no,
                time_dropped,
                finished_ice_qty,
                loose_ice_qty,
                remarks,
                umo,
                umu
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        $stmt = $mysqli->prepare($insertSql);
        if (!$stmt) {
            $errors[] = 'Unable to prepare insert statement.';
        } else {
            $stmt->bind_param(
                'sssisssisidsss',
                $formData['production_date'],
                $formData['dpr_no'],
                $formData['period'],
                $machineId,
                $formData['operator_name'],
                $assistant1,
                $assistant2,
                $dropNo,
                $timeDropped,
                $finishedQty,
                $looseQty,
                $remarks,
                $formData['umo'],
                $formData['umu']
            );

            if (!$stmt->execute()) {
                $errors[] = 'Unable to save ice production entry: ' . ($stmt->error ?: 'Unknown database error.');
            } else {
                $successMessage = 'Ice production entry saved.';
                foreach ($formData as $key => $value) {
                    $formData[$key] = '';
                }
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
                    <?php elseif ($successMessage): ?>
                        <div class="alert alert-success">
                            <?php echo h($successMessage); ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="row g-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Production Date *</label>
                            <input type="date" name="production_date" class="form-control" value="<?php echo h($formData['production_date']); ?>" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">DPR No. *</label>
                            <input type="text" name="dpr_no" class="form-control" value="<?php echo h($formData['dpr_no']); ?>" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Period *</label>
                            <input type="text" name="period" class="form-control" value="<?php echo h($formData['period']); ?>" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Machine *</label>
                            <select name="machine" class="form-select" required>
                                <option value="">Select machine</option>
                                <?php foreach ($machineOptions as $machine): ?>
                                    <option value="<?php echo (int)$machine['id']; ?>" <?php echo $formData['machine'] === (string)$machine['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($machine['machine_description']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Operator *</label>
                            <input type="text" name="operator_name" class="form-control" value="<?php echo h($formData['operator_name']); ?>" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Assistant 1</label>
                            <input type="text" name="assistant1_name" class="form-control" value="<?php echo h($formData['assistant1_name']); ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Assistant 2</label>
                            <input type="text" name="assistant2_name" class="form-control" value="<?php echo h($formData['assistant2_name']); ?>">
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Drop No. *</label>
                            <input type="number" name="drop_no" class="form-control" min="1" step="1" value="<?php echo h($formData['drop_no']); ?>" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Time Dropped</label>
                            <input type="time" name="time_dropped" class="form-control" value="<?php echo h($formData['time_dropped']); ?>">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Finished Ice Qty *</label>
                            <input type="number" name="finished_ice_qty" class="form-control" min="0" step="1" value="<?php echo h($formData['finished_ice_qty']); ?>" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Loose Ice Qty *</label>
                            <input type="number" name="loose_ice_qty" class="form-control" min="0" step="0.01" value="<?php echo h($formData['loose_ice_qty']); ?>" required>
                        </div>

                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">UMO *</label>
                            <input type="text" name="umo" class="form-control" value="<?php echo h($formData['umo']); ?>" required>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">UMU *</label>
                            <input type="text" name="umu" class="form-control" value="<?php echo h($formData['umu']); ?>" required>
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
<?php $scripts = ob_get_clean(); ?>

<?php include 'layouts/base.php'; ?>
