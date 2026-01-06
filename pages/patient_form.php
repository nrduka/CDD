<!-- Base URL setup -->
<?php
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>

<?php
session_start();

require_once __DIR__ . '/database-config.php';

$errors = [];
$successMessage = '';
$schemaWarnings = [];

$sexOptions = ['MALE' => 'Male', 'FEMALE' => 'Female'];
$civilStatusOptions = ['SINGLE' => 'Single', 'MARRIED' => 'Married', 'WIDOWED' => 'Widowed', 'SEPARATED' => 'Separated', 'DIVORCED' => 'Divorced'];
$purposeOptions = ['APE' => 'Annual Physical Exam (APE)', 'PEME' => 'Pre-Employment Medical Exam (PEME)', 'OTHERS' => 'Others'];
$idTypeOptions = ['NATIONAL ID' => 'National ID', 'DRIVER\'S LICENSE' => "Driver's License", 'PASSPORT' => 'Passport', 'SSS' => 'SSS / UMID', 'PHILHEALTH' => 'PhilHealth', 'COMPANY ID' => 'Company ID', 'OTHERS' => 'Others'];
$formData = [
    'last_name' => '',
    'first_name' => '',
    'middle_name' => '',
    'birthday' => '',
    'sex' => '',
    'civil_status' => '',
    'email' => '',
    'phone_number' => '',
    'current_address' => '',
    'permanent_address' => '',
    'address_preference' => 'current',
    'region' => '',
    'province' => '',
    'city' => '',
    'barangay' => '',
    'id_type' => '',
    'id_type_other' => '',
    'id_number' => '',
    'occupation' => '',
    'purpose_of_exam' => 'APE',
    'purpose_other' => '',
    'employer_name' => '',
    'or_number' => '',
    'amount' => '',
];

$locationSelections = [
    'region_id' => '',
    'province_id' => '',
    'city_id' => '',
    'barangay_id' => '',
];

function loadPatientInfoColumns(mysqli $conn)
{
    $columns = [];
    $result = $conn->query('SHOW COLUMNS FROM patient_info');
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = $row;
        }
        $result->free();
    }
    return $columns;
}

function ensurePatientInfoColumns(mysqli $conn, array &$existing, array $definitions, array &$warnings)
{
    foreach ($definitions as $column => $definition) {
        if (isset($existing[$column])) {
            continue;
        }
        $sql = "ALTER TABLE patient_info ADD COLUMN `{$column}` {$definition}";
        if (!$conn->query($sql)) {
            $warnings[] = "Unable to add column {$column}: " . $conn->error;
        } else {
            $existing[$column] = ['Field' => $column];
        }
    }
}

$regionOptions = fetchRegionOptions($conn);
$provinceOptions = fetchProvinceOptions($conn, $locationSelections['region_id']);
$cityOptions = fetchCityOptions($conn, $locationSelections['province_id']);
$barangayOptions = fetchBarangayOptions($conn, $locationSelections['city_id']);

if (!$regionOptions) {
    $schemaWarnings[] = 'No Philippine region data found. Please import philippine_addresses.sql before using the form.';
}

function generateRetrievalCode(mysqli $conn, $length = 11)
{
    $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $maxIndex = strlen($characters) - 1;
    $code = '';
    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $index = function_exists('random_int') ? random_int(0, $maxIndex) : mt_rand(0, $maxIndex);
            $code .= $characters[$index];
        }
        $stmt = $conn->prepare('SELECT COUNT(*) FROM patient_info WHERE retrieval_code = ?');
        if (!$stmt) {
            break;
        }
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
    } while (!empty($count));

    return $code;
}

function sanitizeText($value)
{
    return trim((string)$value);
}

function formatMoney($value)
{
    if ($value === null) {
        return '';
    }
    $normalized = preg_replace('/[^0-9.]/', '', str_replace(',', '', $value));
    return $normalized;
}

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fetchRegionOptions(mysqli $conn)
{
    $rows = [];
    $result = $conn->query('SELECT id, name FROM philippine_regions ORDER BY name');
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    return $rows;
}

function fetchProvinceOptions(mysqli $conn, $regionId)
{
    $rows = [];
    $regionId = (int)$regionId;
    if ($regionId <= 0) {
        return $rows;
    }
    $result = $conn->query('SELECT id, name FROM philippine_provinces WHERE region_id = ' . $regionId . ' ORDER BY name');
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    return $rows;
}

function fetchCityOptions(mysqli $conn, $provinceId)
{
    $rows = [];
    $provinceId = (int)$provinceId;
    if ($provinceId <= 0) {
        return $rows;
    }
    $result = $conn->query('SELECT id, name FROM philippine_cities WHERE province_id = ' . $provinceId . ' ORDER BY name');
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    return $rows;
}

function fetchBarangayOptions(mysqli $conn, $cityId)
{
    $rows = [];
    $cityId = (int)$cityId;
    if ($cityId <= 0) {
        return $rows;
    }
    $result = $conn->query('SELECT id, name FROM philippine_barangays WHERE city_id = ' . $cityId . ' ORDER BY name');
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
    }
    return $rows;
}

function findRegionRecord(mysqli $conn, $regionId)
{
    $regionId = (int)$regionId;
    if ($regionId <= 0) {
        return null;
    }
    $result = $conn->query('SELECT id, name FROM philippine_regions WHERE id = ' . $regionId . ' LIMIT 1');
    if ($result instanceof mysqli_result) {
        $record = $result->fetch_assoc();
        $result->free();
        return $record ?: null;
    }
    return null;
}

function findProvinceRecord(mysqli $conn, $provinceId, $regionId)
{
    $provinceId = (int)$provinceId;
    $regionId = (int)$regionId;
    if ($provinceId <= 0 || $regionId <= 0) {
        return null;
    }
    $result = $conn->query('SELECT id, name, region_id FROM philippine_provinces WHERE id = ' . $provinceId . ' AND region_id = ' . $regionId . ' LIMIT 1');
    if ($result instanceof mysqli_result) {
        $record = $result->fetch_assoc();
        $result->free();
        return $record ?: null;
    }
    return null;
}

function findCityRecord(mysqli $conn, $cityId, $provinceId)
{
    $cityId = (int)$cityId;
    $provinceId = (int)$provinceId;
    if ($cityId <= 0 || $provinceId <= 0) {
        return null;
    }
    $result = $conn->query('SELECT id, name, province_id FROM philippine_cities WHERE id = ' . $cityId . ' AND province_id = ' . $provinceId . ' LIMIT 1');
    if ($result instanceof mysqli_result) {
        $record = $result->fetch_assoc();
        $result->free();
        return $record ?: null;
    }
    return null;
}

function findBarangayRecord(mysqli $conn, $barangayId, $cityId)
{
    $barangayId = (int)$barangayId;
    $cityId = (int)$cityId;
    if ($barangayId <= 0 || $cityId <= 0) {
        return null;
    }
    $result = $conn->query('SELECT id, name, city_id FROM philippine_barangays WHERE id = ' . $barangayId . ' AND city_id = ' . $cityId . ' LIMIT 1');
    if ($result instanceof mysqli_result) {
        $record = $result->fetch_assoc();
        $result->free();
        return $record ?: null;
    }
    return null;
}

$columnDefinitions = [
    'lastname' => "VARCHAR(100) NOT NULL DEFAULT ''",
    'email' => "VARCHAR(150) NOT NULL DEFAULT ''",
    'civil_status' => "VARCHAR(50) NOT NULL DEFAULT ''",
    'permanent_address' => "VARCHAR(255) NOT NULL DEFAULT ''",
    'address_preference' => "VARCHAR(20) NOT NULL DEFAULT 'current'",
    'id_type' => "VARCHAR(100) NOT NULL DEFAULT ''",
    'id_number' => "VARCHAR(100) NOT NULL DEFAULT ''",
    'employer_name' => "VARCHAR(150) NOT NULL DEFAULT ''",
    'purpose_of_exam' => "VARCHAR(150) NOT NULL DEFAULT ''",
    'or_number' => "VARCHAR(100) NOT NULL DEFAULT ''",
    'amount' => "DECIMAL(10,2) NOT NULL DEFAULT 0.00"
];

$patientInfoColumns = loadPatientInfoColumns($conn);
if (!$patientInfoColumns) {
    $errors[] = 'Unable to read patient_info table definition. Please check the database connection and schema.';
}
ensurePatientInfoColumns($conn, $patientInfoColumns, $columnDefinitions, $schemaWarnings);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($formData) as $field) {
        if ($field === 'amount') {
            $formData[$field] = sanitizeText(formatMoney(isset($_POST[$field]) ? $_POST[$field] : ''));
        } else {
            $formData[$field] = sanitizeText(isset($_POST[$field]) ? $_POST[$field] : '');
        }
    }

    foreach ($locationSelections as $field => $value) {
        $locationSelections[$field] = sanitizeText(isset($_POST[$field]) ? $_POST[$field] : '');
    }

    $formData['sex'] = strtoupper($formData['sex']);
    $formData['civil_status'] = strtoupper($formData['civil_status']);
    $formData['id_type'] = strtoupper($formData['id_type']);
    $formData['purpose_of_exam'] = strtoupper($formData['purpose_of_exam']);
    $formData['address_preference'] = in_array($formData['address_preference'], ['current', 'permanent'], true) ? $formData['address_preference'] : 'current';

    if ($formData['last_name'] === '') {
        $errors[] = 'Last name is required.';
    }
    if ($formData['first_name'] === '') {
        $errors[] = 'First name is required.';
    }
    if ($formData['birthday'] === '') {
        $errors[] = 'Birthday is required.';
    } elseif (!DateTime::createFromFormat('Y-m-d', $formData['birthday'])) {
        $errors[] = 'Birthday must be in YYYY-MM-DD format.';
    }
    if ($formData['sex'] === '' || !isset($sexOptions[$formData['sex']])) {
        $errors[] = 'Please select a valid sex option.';
    }
    if ($formData['civil_status'] === '' || !isset($civilStatusOptions[$formData['civil_status']])) {
        $errors[] = 'Civil status is required.';
    }
    if ($formData['phone_number'] === '') {
        $errors[] = 'Phone number is required.';
    }
    if ($formData['current_address'] === '' && $formData['permanent_address'] === '') {
        $errors[] = 'Provide at least one full address (current or permanent).';
    }
    if ($formData['purpose_of_exam'] === 'OTHERS' && $formData['purpose_other'] === '') {
        $errors[] = 'Please specify the purpose of exam.';
    }
    if ($formData['id_type'] === 'OTHERS' && $formData['id_type_other'] === '') {
        $errors[] = 'Please specify the ID type.';
    }
    if ($formData['email'] !== '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($formData['amount'] !== '') {
        if (!is_numeric($formData['amount'])) {
            $errors[] = 'Amount must be a valid number.';
        }
    }

    $regionRecord = null;
    $provinceRecord = null;
    $cityRecord = null;
    $barangayRecord = null;

    if ($locationSelections['region_id'] === '' || !ctype_digit($locationSelections['region_id'])) {
        $errors[] = 'Region is required.';
    } else {
        $regionRecord = findRegionRecord($conn, $locationSelections['region_id']);
        if (!$regionRecord) {
            $errors[] = 'Please select a valid region.';
        } else {
            $formData['region'] = $regionRecord['name'];
        }
    }

    if ($locationSelections['province_id'] === '' || !ctype_digit($locationSelections['province_id'])) {
        $errors[] = 'Province is required.';
    } elseif ($regionRecord) {
        $provinceRecord = findProvinceRecord($conn, $locationSelections['province_id'], $regionRecord['id']);
        if (!$provinceRecord) {
            $errors[] = 'Please select a province within the chosen region.';
        } else {
            $formData['province'] = $provinceRecord['name'];
        }
    }

    if ($locationSelections['city_id'] === '' || !ctype_digit($locationSelections['city_id'])) {
        $errors[] = 'City / Municipality is required.';
    } elseif ($provinceRecord) {
        $cityRecord = findCityRecord($conn, $locationSelections['city_id'], $provinceRecord['id']);
        if (!$cityRecord) {
            $errors[] = 'Please select a city/municipality within the chosen province.';
        } else {
            $formData['city'] = $cityRecord['name'];
        }
    }

    if ($locationSelections['barangay_id'] === '' || !ctype_digit($locationSelections['barangay_id'])) {
        $errors[] = 'Barangay is required.';
    } elseif ($cityRecord) {
        $barangayRecord = findBarangayRecord($conn, $locationSelections['barangay_id'], $cityRecord['id']);
        if (!$barangayRecord) {
            $errors[] = 'Please select a barangay within the chosen city/municipality.';
        } else {
            $formData['barangay'] = $barangayRecord['name'];
        }
    }

    if (!$errors) {
        $patientPayload = [
            'retrieval_code' => generateRetrievalCode($conn),
            'firstname' => strtoupper($formData['first_name']),
            'middlename' => strtoupper($formData['middle_name']),
            'lastname' => strtoupper($formData['last_name']),
            'dob' => $formData['birthday'],
            'sex' => $formData['sex'],
            'civil_status' => $formData['civil_status'],
            'email' => strtolower($formData['email']),
            'phone_no' => $formData['phone_number'],
            'address' => $formData['current_address'] !== '' ? $formData['current_address'] : $formData['permanent_address'],
            'permanent_address' => $formData['permanent_address'],
            'address_preference' => $formData['address_preference'],
            'region' => $formData['region'],
            'province' => $formData['province'],
            'city' => $formData['city'],
            'barangay' => $formData['barangay'],
            'id_type' => $formData['id_type'] === 'OTHERS' ? strtoupper($formData['id_type_other']) : $formData['id_type'],
            'id_number' => $formData['id_number'],
            'occupation' => $formData['occupation'],
            'employer_name' => $formData['employer_name'],
            'purpose_of_exam' => $formData['purpose_of_exam'] === 'OTHERS' ? $formData['purpose_other'] : $formData['purpose_of_exam'],
            'or_number' => $formData['or_number'],
            'amount' => $formData['amount'] === '' ? 0 : number_format((float)$formData['amount'], 2, '.', '')
        ];

        $insertColumns = [];
        $placeholders = [];
        $types = '';
        $values = [];
        foreach ($patientPayload as $column => $value) {
            if (!isset($patientInfoColumns[$column])) {
                continue;
            }
            $insertColumns[] = "`{$column}`";
            $placeholders[] = '?';
            if ($column === 'amount') {
                $types .= 'd';
                $values[] = (float)$value;
            } else {
                $types .= 's';
                $values[] = $value;
            }
        }

        if (!$insertColumns) {
            $errors[] = 'No matching columns were found in patient_info to store this form.';
        } else {
            $insertSql = sprintf('INSERT INTO patient_info (%s) VALUES (%s)', implode(', ', $insertColumns), implode(', ', $placeholders));
            $stmt = $conn->prepare($insertSql);
            if (!$stmt) {
                $errors[] = 'Unable to prepare insert statement: ' . $conn->error;
            } else {
                $stmt->bind_param($types, ...$values);
                if ($stmt->execute()) {
                    $successMessage = sprintf('Patient registered successfully. Retrieval code: %s', $patientPayload['retrieval_code']);
                    $formData = [
                        'last_name' => '',
                        'first_name' => '',
                        'middle_name' => '',
                        'birthday' => '',
                        'sex' => '',
                        'civil_status' => '',
                        'email' => '',
                        'phone_number' => '',
                        'current_address' => '',
                        'permanent_address' => '',
                        'address_preference' => 'current',
                        'region' => '',
                        'province' => '',
                        'city' => '',
                        'barangay' => '',
                        'id_type' => '',
                        'id_type_other' => '',
                        'id_number' => '',
                        'occupation' => '',
                        'purpose_of_exam' => 'APE',
                        'purpose_other' => '',
                        'employer_name' => '',
                        'or_number' => '',
                        'amount' => '',
                    ];
                    $locationSelections = [
                        'region_id' => '',
                        'province_id' => '',
                        'city_id' => '',
                        'barangay_id' => '',
                    ];
                } else {
                    if ($conn->errno === 1062) {
                        $errors[] = 'Duplicate retrieval code detected. Please submit again.';
                    } else {
                        $errors[] = 'Unable to save patient record: ' . $stmt->error;
                    }
                }
                $stmt->close();
            }
        }
    }
}
?>

<?php ob_start(); ?>
<style>
    .dashlabs-form-wrapper {
        max-width: 1100px;
        margin: 0 auto;
    }
    .dashlabs-card {
        background: #ffffff;
        border-radius: 18px;
        box-shadow: 0 15px 60px rgba(20, 34, 70, 0.08);
        padding: 2.5rem;
        margin-bottom: 2rem;
    }
    .dashlabs-card h2 {
        font-weight: 700;
        font-size: 1.5rem;
        color: #0f172a;
        margin-bottom: 0.5rem;
    }
    .dashlabs-card p.section-subtitle {
        color: #64748b;
        margin-bottom: 1.8rem;
    }
    .dashlabs-section-title {
        font-size: 1rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: #475569;
        margin-bottom: 1.25rem;
    }
    .dashlabs-pill-switch {
        display: inline-flex;
        background: #f1f5f9;
        padding: 0.25rem;
        border-radius: 999px;
        margin-bottom: 0.75rem;
        border: 1px solid rgba(148, 163, 184, 0.4);
    }
    .dashlabs-pill-switch button {
        border: 0;
        background: transparent;
        color: #475569;
        padding: 0.5rem 1.25rem;
        border-radius: 99px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }
    .dashlabs-pill-switch button.active {
        background: #1665ff;
        color: #fff;
        box-shadow: 0 10px 25px rgba(22, 101, 255, 0.25);
    }
    .dashlabs-form .form-label {
        font-weight: 600;
        color: #0f172a;
    }
    .dashlabs-form .form-control,
    .dashlabs-form .form-select {
        border-radius: 12px;
        padding: 0.8rem 1rem;
    }
    .dashlabs-radio-pill {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .dashlabs-pill-option {
        padding: 0.6rem 1.1rem;
        background: #f8fafc;
        border-radius: 999px;
        border: 1px solid transparent;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }
    .dashlabs-pill-option input {
        display: none;
    }
    .dashlabs-pill-option .form-check-label {
        cursor: pointer;
        font-weight: 600;
        color: #475569;
        margin: 0;
    }
    .dashlabs-pill-option.active {
        border-color: #1665ff;
        background: #eef2ff;
        box-shadow: 0 6px 18px rgba(22, 101, 255, 0.15);
    }
    .dashlabs-pill-option.active .form-check-label {
        color: #1665ff;
    }
    .dashlabs-helper-text {
        color: #64748b;
        font-size: 0.85rem;
    }
    @media (max-width: 767.98px) {
        .dashlabs-card {
            padding: 1.5rem;
        }
    }
</style>
<?php $styles = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="container-fluid dashlabs-form-wrapper">
    <div class="dashlabs-card dashlabs-form">
        <h2>Patient Registration</h2>
        <p class="section-subtitle">Fill out the patient information form. All fields marked with * are required.</p>

        <?php if ($schemaWarnings): ?>
            <div class="alert alert-warning">
                <?php foreach ($schemaWarnings as $warning): ?>
                    <div><?php echo h($warning); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo h($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <?php echo h($successMessage); ?>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="dashlabs-section-title">Patient details</div>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Last name (required) *</label>
                    <input type="text" name="last_name" class="form-control" value="<?php echo h($formData['last_name']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">First name (required) *</label>
                    <input type="text" name="first_name" class="form-control" value="<?php echo h($formData['first_name']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Middle name</label>
                    <input type="text" name="middle_name" class="form-control" value="<?php echo h($formData['middle_name']); ?>">
                </div>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label">Birthday (required) *</label>
                    <input type="date" name="birthday" class="form-control" value="<?php echo h($formData['birthday']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="name@example.com" value="<?php echo h($formData['email']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sex (required)</label>
                    <div class="dashlabs-radio-pill">
                        <?php foreach ($sexOptions as $value => $label): ?>
                            <label class="dashlabs-pill-option <?php echo $formData['sex'] === $value ? 'active' : ''; ?>">
                                <input type="radio" name="sex" value="<?php echo h($value); ?>" <?php echo $formData['sex'] === $value ? 'checked' : ''; ?>>
                                <span class="form-check-label"><?php echo h($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="row g-3 mb-5">
                <div class="col-md-4">
                    <label class="form-label">Civil Status *</label>
                    <select name="civil_status" class="form-select" required>
                        <option value="">Select status</option>
                        <?php foreach ($civilStatusOptions as $value => $label): ?>
                            <option value="<?php echo h($value); ?>" <?php echo $formData['civil_status'] === $value ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Phone number (required) *</label>
                    <input type="text" name="phone_number" class="form-control" value="<?php echo h($formData['phone_number']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Occupation</label>
                    <input type="text" name="occupation" class="form-control" value="<?php echo h($formData['occupation']); ?>">
                </div>
            </div>

            <div class="dashlabs-section-title">Full address</div>
            <div class="dashlabs-pill-switch" data-address-switch>
                <button type="button" class="<?php echo $formData['address_preference'] === 'current' ? 'active' : ''; ?>" data-address-tab="current">Current</button>
                <button type="button" class="<?php echo $formData['address_preference'] === 'permanent' ? 'active' : ''; ?>" data-address-tab="permanent">Permanent</button>
            </div>
            <input type="hidden" name="address_preference" id="address_preference" value="<?php echo h($formData['address_preference']); ?>">
            <div class="tab-content mb-4" id="address-tab-wrapper">
                <div class="tab-pane fade <?php echo $formData['address_preference'] === 'current' ? 'show active' : ''; ?>" id="address-current">
                    <textarea name="current_address" class="form-control" rows="3" placeholder="House number, street, subdivision, etc."><?php echo h($formData['current_address']); ?></textarea>
                    <div class="dashlabs-helper-text mt-2">Current residential address</div>
                </div>
                <div class="tab-pane fade <?php echo $formData['address_preference'] === 'permanent' ? 'show active' : ''; ?>" id="address-permanent">
                    <textarea name="permanent_address" class="form-control" rows="3" placeholder="House number, street, subdivision, etc."><?php echo h($formData['permanent_address']); ?></textarea>
                    <div class="dashlabs-helper-text mt-2">Permanent address</div>
                </div>
            </div>

            <div class="row g-3 mb-5">
                <div class="col-md-3">
                    <label class="form-label">Region (required) *</label>
                    <select name="region_id" id="region_id" class="form-select" required>
                        <option value="">Select region</option>
                        <?php foreach ($regionOptions as $region): ?>
                            <option value="<?php echo (int)$region['id']; ?>" <?php echo $locationSelections['region_id'] === (string)$region['id'] ? 'selected' : ''; ?>>
                                <?php echo h($region['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Province (required) *</label>
                    <select name="province_id" id="province_id" class="form-select" <?php echo $locationSelections['region_id'] === '' ? 'disabled' : ''; ?> required>
                        <option value="">Select province</option>
                        <?php foreach ($provinceOptions as $province): ?>
                            <option value="<?php echo (int)$province['id']; ?>" <?php echo $locationSelections['province_id'] === (string)$province['id'] ? 'selected' : ''; ?>>
                                <?php echo h($province['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">City / Municipality (required) *</label>
                    <select name="city_id" id="city_id" class="form-select" <?php echo $locationSelections['province_id'] === '' ? 'disabled' : ''; ?> required>
                        <option value="">Select city / municipality</option>
                        <?php foreach ($cityOptions as $city): ?>
                            <option value="<?php echo (int)$city['id']; ?>" <?php echo $locationSelections['city_id'] === (string)$city['id'] ? 'selected' : ''; ?>>
                                <?php echo h($city['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Barangay (required) *</label>
                    <select name="barangay_id" id="barangay_id" class="form-select" <?php echo $locationSelections['city_id'] === '' ? 'disabled' : ''; ?> required>
                        <option value="">Select barangay</option>
                        <?php foreach ($barangayOptions as $barangay): ?>
                            <option value="<?php echo (int)$barangay['id']; ?>" <?php echo $locationSelections['barangay_id'] === (string)$barangay['id'] ? 'selected' : ''; ?>>
                                <?php echo h($barangay['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="dashlabs-section-title">Identification</div>
            <div class="row g-3 mb-5">
                <div class="col-md-4">
                    <label class="form-label">ID Type</label>
                    <select name="id_type" id="id_type" class="form-select">
                        <option value="">Select ID type</option>
                        <?php foreach ($idTypeOptions as $value => $label): ?>
                            <option value="<?php echo h($value); ?>" <?php echo $formData['id_type'] === $value ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4" id="id-type-other-group" style="display: <?php echo $formData['id_type'] === 'OTHERS' ? 'block' : 'none'; ?>;">
                    <label class="form-label">Specify ID Type</label>
                    <input type="text" name="id_type_other" class="form-control" value="<?php echo h($formData['id_type_other']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">ID Number</label>
                    <input type="text" name="id_number" class="form-control" value="<?php echo h($formData['id_number']); ?>">
                </div>
            </div>

            <div class="dashlabs-section-title">Purpose of exam</div>
            <div class="row g-3 mb-5">
                <div class="col-md-8">
                    <div class="dashlabs-radio-pill">
                        <?php foreach ($purposeOptions as $value => $label): ?>
                            <label class="dashlabs-pill-option <?php echo $formData['purpose_of_exam'] === $value ? 'active' : ''; ?>">
                                <input type="radio" name="purpose_of_exam" value="<?php echo h($value); ?>" <?php echo $formData['purpose_of_exam'] === $value ? 'checked' : ''; ?>>
                                <span class="form-check-label"><?php echo h($label); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-4" id="purpose-other-group" style="display: <?php echo $formData['purpose_of_exam'] === 'OTHERS' ? 'block' : 'none'; ?>;">
                    <label class="form-label">Please specify</label>
                    <input type="text" name="purpose_other" class="form-control" value="<?php echo h($formData['purpose_other']); ?>">
                </div>
            </div>

            <div class="dashlabs-section-title">Employer & billing</div>
            <div class="row g-3 mb-5">
                <div class="col-md-4">
                    <label class="form-label">Name of employer</label>
                    <input type="text" name="employer_name" class="form-control" value="<?php echo h($formData['employer_name']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">OR #</label>
                    <input type="text" name="or_number" class="form-control" value="<?php echo h($formData['or_number']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Amount</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="text" name="amount" id="amount" class="form-control" value="<?php echo h($formData['amount']); ?>" placeholder="0.00">
                    </div>
                </div>
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-primary btn-lg px-5">Submit form</button>
            </div>
        </form>
    </div>
</div>
<?php $content = ob_get_clean(); ?>

<?php ob_start(); ?>
<script>
    (function () {
        const switchGroup = document.querySelector('[data-address-switch]');
        const preferenceInput = document.getElementById('address_preference');
        const currentPane = document.getElementById('address-current');
        const permanentPane = document.getElementById('address-permanent');
        if (switchGroup && preferenceInput && currentPane && permanentPane) {
            switchGroup.querySelectorAll('button').forEach(function (button) {
                button.addEventListener('click', function () {
                    const target = this.getAttribute('data-address-tab');
                    if (!target) {
                        return;
                    }
                    preferenceInput.value = target;
                    switchGroup.querySelectorAll('button').forEach(function (btn) {
                        btn.classList.toggle('active', btn === button);
                    });
                    if (target === 'current') {
                        currentPane.classList.add('show', 'active');
                        permanentPane.classList.remove('show', 'active');
                    } else {
                        permanentPane.classList.add('show', 'active');
                        currentPane.classList.remove('show', 'active');
                    }
                });
            });
        }

        const purposeRadios = document.querySelectorAll('input[name="purpose_of_exam"]');
        const purposeOtherGroup = document.getElementById('purpose-other-group');
        function togglePurposeOther() {
            if (!purposeOtherGroup) return;
            const active = document.querySelector('input[name="purpose_of_exam"]:checked');
            if (active && active.value === 'OTHERS') {
                purposeOtherGroup.style.display = 'block';
            } else {
                purposeOtherGroup.style.display = 'none';
            }
        }
        purposeRadios.forEach(function (radio) {
            radio.addEventListener('change', togglePurposeOther);
        });
        togglePurposeOther();

        const idTypeSelect = document.getElementById('id_type');
        const idTypeOtherGroup = document.getElementById('id-type-other-group');
        function toggleIdTypeOther() {
            if (!idTypeOtherGroup) return;
            if (idTypeSelect && idTypeSelect.value === 'OTHERS') {
                idTypeOtherGroup.style.display = 'block';
            } else {
                idTypeOtherGroup.style.display = 'none';
            }
        }
        if (idTypeSelect) {
            idTypeSelect.addEventListener('change', toggleIdTypeOther);
            toggleIdTypeOther();
        }

        document.querySelectorAll('.dashlabs-radio-pill').forEach(function (group) {
            const syncActive = function () {
                group.querySelectorAll('.dashlabs-pill-option').forEach(function (label) {
                    label.classList.remove('active');
                });
                const checked = group.querySelector('input[type=\"radio\"]:checked');
                if (checked) {
                    const activeLabel = checked.closest('.dashlabs-pill-option');
                    if (activeLabel) {
                        activeLabel.classList.add('active');
                    }
                }
            };
            group.addEventListener('change', function (event) {
                if (event.target && event.target.matches('input[type=\"radio\"]')) {
                    syncActive();
                }
            });
            syncActive();
        });

        const regionSelect = document.getElementById('region_id');
        const provinceSelect = document.getElementById('province_id');
        const citySelect = document.getElementById('city_id');
        const barangaySelect = document.getElementById('barangay_id');

        function resetLocationSelect(select, placeholder) {
            if (!select) {
                return;
            }
            select.innerHTML = '';
            const option = document.createElement('option');
            option.value = '';
            option.textContent = placeholder;
            select.appendChild(option);
            select.disabled = true;
        }

        function populateLocationSelect(select, items, placeholder) {
            if (!select) {
                return;
            }
            select.innerHTML = '';
            const option = document.createElement('option');
            option.value = '';
            option.textContent = placeholder;
            select.appendChild(option);
            items.forEach(function (item) {
                const opt = document.createElement('option');
                opt.value = String(item.id);
                opt.textContent = item.name;
                select.appendChild(opt);
            });
            select.disabled = false;
        }

        function loadLocations(type, params) {
            const url = new URL('location_lookup.php', window.location.href);
            url.searchParams.set('type', type);
            Object.keys(params || {}).forEach(function (key) {
                url.searchParams.set(key, params[key]);
            });
            return fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (!data.success) {
                    throw new Error(data.message || 'Unable to load locations.');
                }
                return Array.isArray(data.items) ? data.items : [];
            });
        }

        if (regionSelect && provinceSelect && citySelect && barangaySelect) {
            regionSelect.addEventListener('change', function () {
                const regionId = this.value;
                resetLocationSelect(provinceSelect, 'Select province');
                resetLocationSelect(citySelect, 'Select city / municipality');
                resetLocationSelect(barangaySelect, 'Select barangay');
                if (!regionId) {
                    return;
                }
                loadLocations('provinces', { region_id: regionId })
                    .then(function (items) {
                        populateLocationSelect(provinceSelect, items, 'Select province');
                    })
                    .catch(function (error) {
                        console.error(error);
                        alert('Unable to load provinces. Please try again.');
                    });
            });

            provinceSelect.addEventListener('change', function () {
                const provinceId = this.value;
                resetLocationSelect(citySelect, 'Select city / municipality');
                resetLocationSelect(barangaySelect, 'Select barangay');
                if (!provinceId) {
                    return;
                }
                loadLocations('cities', { province_id: provinceId })
                    .then(function (items) {
                        populateLocationSelect(citySelect, items, 'Select city / municipality');
                    })
                    .catch(function (error) {
                        console.error(error);
                        alert('Unable to load cities/municipalities. Please try again.');
                    });
            });

            citySelect.addEventListener('change', function () {
                const cityId = this.value;
                resetLocationSelect(barangaySelect, 'Select barangay');
                if (!cityId) {
                    return;
                }
                loadLocations('barangays', { city_id: cityId })
                    .then(function (items) {
                        populateLocationSelect(barangaySelect, items, 'Select barangay');
                    })
                    .catch(function (error) {
                        console.error(error);
                        alert('Unable to load barangays. Please try again.');
                    });
            });
        }
    })();
</script>
<?php $scripts = ob_get_clean(); ?>

<?php include 'layouts/base.php'; ?>
