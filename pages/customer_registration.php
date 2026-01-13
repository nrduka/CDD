<?php
// Base URL setup
$rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));

session_start();

require_once __DIR__ . '/db2.php';

$errors = [];
$successMessage = '';
$formData = [
    'customer_code' => '',
    'firstName' => '',
    'middleName' => '',
    'lastName' => '',
    'address' => '',
    'storeName' => '',
    'type' => '',
];

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formData as $key => $value) {
        $formData[$key] = isset($_POST[$key]) ? trim((string)$_POST[$key]) : '';
    }

    if (!isset($mysqli) || !$mysqli) {
        $errors[] = 'Database connection unavailable.';
    }

    if ($formData['customer_code'] === '') {
        $errors[] = 'Customer code is required.';
    }

    if (!$errors) {
        $insertSql = 'INSERT INTO customer_register (customer_code, firstName, middleName, lastName, address, storeName, type)
            VALUES (?, ?, ?, ?, ?, ?, ?)';
        $stmt = $mysqli->prepare($insertSql);
        if (!$stmt) {
            $errors[] = 'Unable to prepare customer insert.';
        } else {
            $stmt->bind_param(
                'sssssss',
                $formData['customer_code'],
                $formData['firstName'],
                $formData['middleName'],
                $formData['lastName'],
                $formData['address'],
                $formData['storeName'],
                $formData['type']
            );

            if (!$stmt->execute()) {
                $errors[] = 'Unable to save customer: ' . ($stmt->error ?: 'Unknown database error.');
            } else {
                $successMessage = 'Customer saved successfully.';
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
    .customer-registration-card {
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
            <p class="fw-semibold fs-18 mb-0">CUSTOMER REGISTRATION</p>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card customer-registration-card">
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
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Customer Code *</label>
                            <input type="text" name="customer_code" class="form-control" value="<?php echo h($formData['customer_code']); ?>" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Store Name</label>
                            <input type="text" name="storeName" class="form-control" value="<?php echo h($formData['storeName']); ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Type</label>
                            <input type="text" name="type" class="form-control" value="<?php echo h($formData['type']); ?>">
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">First Name</label>
                            <input type="text" name="firstName" class="form-control" value="<?php echo h($formData['firstName']); ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Middle Name</label>
                            <input type="text" name="middleName" class="form-control" value="<?php echo h($formData['middleName']); ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold">Last Name</label>
                            <input type="text" name="lastName" class="form-control" value="<?php echo h($formData['lastName']); ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Address</label>
                            <textarea name="address" rows="2" class="form-control"><?php echo h($formData['address']); ?></textarea>
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Save Customer</button>
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
