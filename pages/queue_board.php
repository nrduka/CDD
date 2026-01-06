<!-- Base URL setup -->
<?php
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>

<?php
session_start();
date_default_timezone_set('Asia/Manila');

$role = isset($_SESSION['sess_userrole']) ? strtoupper($_SESSION['sess_userrole']) : '';
$assignedDepartmentsLabel = isset($_SESSION['sess_department']) ? trim((string)$_SESSION['sess_department']) : '';
$hasDepartmentAssignments = $assignedDepartmentsLabel !== '';
$allowedRoles = ['REGISTRATION', 'COUNTER', 'ADMIN', 'CASHIER', 'DEPARTMENT'];
if (!in_array($role, $allowedRoles, true) && !$hasDepartmentAssignments) {
    header('Location: index.php');
    exit;
}
$isAdmin = ($role === 'ADMIN');
$registrationRoles = ['ADMIN', 'REGISTRATION', 'COUNTER'];
$hasRegistrationWorkflows = in_array($role, $registrationRoles, true);
$requiresRegistrationAssignment = in_array($role, ['REGISTRATION', 'COUNTER'], true);
$hasFullDepartmentAccess = in_array($role, ['ADMIN', 'REGISTRATION', 'COUNTER'], true);
$showRegistrationPanel = $hasRegistrationWorkflows;
$departmentOnlyRoles = ['DEPARTMENT', 'CASHIER'];
$showDepartmentPanel = $isAdmin
    || in_array($role, $departmentOnlyRoles, true)
    || (!$showRegistrationPanel && $hasDepartmentAssignments);
$showCombinedBoard = $showRegistrationPanel && $showDepartmentPanel && $isAdmin;

$cardTitle = 'Queue Board';
$cardDescription = 'Monitor queue activity for your role.';
if ($showCombinedBoard) {
    $cardTitle = 'Queue Board Overview';
    $cardDescription = 'Review both registration and department queues.';
} elseif ($showRegistrationPanel && !$showDepartmentPanel) {
    $cardTitle = 'Registration Queue Board';
    $cardDescription = 'Monitor and call patients from the queue.';
} elseif ($showDepartmentPanel && !$showRegistrationPanel) {
    $cardTitle = 'Department Queue Board';
    $cardDescription = 'Monitor routed customers for your assigned departments.';
}

$departmentBadgeText = '';
if ($showDepartmentPanel) {
    if ($isAdmin) {
        $departmentBadgeText = 'All Departments';
    } elseif ($assignedDepartmentsLabel !== '') {
        $departmentBadgeText = $assignedDepartmentsLabel;
    } else {
        $departmentBadgeText = 'Department Access';
    }
}

require_once __DIR__ . '/db2.php';
require_once __DIR__ . '/queue_board_assets.php';

$assignedRegistration = null;
$registrationUsername = null;

if ($hasRegistrationWorkflows && isset($_SESSION['sess_user_id'], $mysqli) && $mysqli) {
    $userId = (int)$_SESSION['sess_user_id'];
    $stmt = $mysqli->prepare("SELECT u.username, ra.assigned_registration 
        FROM user u 
        LEFT JOIN registration_attendant ra ON ra.username = u.username 
        WHERE u.id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($username, $registrationNumber);
    if ($stmt->fetch()) {
        $assignedRegistration = $registrationNumber;
        $registrationUsername = $username;
    }
    $stmt->close();
}
?>

<?php $styles = queue_board_styles(); ?>

<?php ob_start(); ?>

<div class="container-fluid queue-board-container">
    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header justify-content-between align-items-center">
                    <div>
                        <div class="card-title mb-1">
                            <?php echo htmlspecialchars($cardTitle); ?>
                        </div>
                        <div class="text-muted small">
                            <?php echo htmlspecialchars($cardDescription); ?>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($showRegistrationPanel): ?>
                            <span class="badge bg-primary" id="registration-label">
                                <?php echo $assignedRegistration ? 'Registration ' . htmlspecialchars($assignedRegistration) : 'Registration not assigned'; ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($showDepartmentPanel && $departmentBadgeText !== ''): ?>
                            <span class="badge bg-secondary">
                                <?php echo htmlspecialchars($departmentBadgeText); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div id="queue-board-message" class="mb-3"></div>

                    <?php if ($showRegistrationPanel && !$assignedRegistration && $requiresRegistrationAssignment): ?>
                        <div class="alert alert-warning" role="alert">
                            Your account is not linked to a registration station. Please contact the administrator to assign one.
                        </div>
                    <?php elseif ($showRegistrationPanel): ?>
                        <div class="row g-4 align-items-start">
                            <div class="col-xl-4 col-lg-5">
                                <label for="waiting-list" class="form-label fw-semibold">Waiting Queue</label>
                                <select id="waiting-list" class="form-select queue-select" multiple></select>
                            </div>
                            <div class="col-xl-3 col-lg-3 d-flex flex-column justify-content-center align-items-center gap-3 queue-actions">
                                <button type="button" class="btn btn-primary btn-wave w-100" id="call-button" disabled>
                                    <i class="ri-arrow-right-line me-2"></i>Call Selected
                                </button>
                                <button type="button" class="btn btn-success btn-wave w-100" id="complete-button" disabled>
                                    <i class="ri-check-line me-2"></i>Complete
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-wave w-100" id="return-button" disabled>
                                    <i class="ri-arrow-left-line me-2"></i>Return to Queue
                                </button>
                                <button type="button" class="btn btn-light btn-wave w-100" id="refresh-button">
                                    <i class="ri-refresh-line me-2"></i>Refresh
                                </button>
                            </div>
                            <div class="col-xl-5 col-lg-4">
                                <div class="queue-serving-columns">
                                    <div class="queue-panel">
                                        <label class="form-label fw-semibold">Currently Serving</label>
                                        <div id="active-list" class="queue-active-list"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row now-serving-panel">
                            <div class="col-12">
                                <div class="queue-panel">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-semibold mb-0">Now Serving</label>
                                        <span class="text-muted small">Customers routed to departments.</span>
                                    </div>
                                    <div class="now-serving-table">
                                        <div class="now-serving-header">
                                            <span>Ticket</span>
                                            <span>Destinations</span>
                                            <span>Status</span>
                                            <span class="text-end">Action</span>
                                        </div>
                                        <div id="now-serving-body" class="now-serving-body"></div>
                                    </div>
                                    <div class="now-serving-pagination">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="now-serving-prev">Previous</button>
                                        <span class="text-muted small" id="now-serving-page-label">Page 1 of 1</span>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="now-serving-next">Next</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="card custom-card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <div class="card-title mb-0">Finished Patients</div>
                                        <span class="text-muted small">Completed in all departments.</span>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="now-serving-table">
                                            <div class="now-serving-header">
                                                <span>Ticket</span>
                                                <span>Retrieval Code</span>
                                                <span>Completed</span>
                                                <span></span>
                                            </div>
                                            <div id="finished-body" class="now-serving-body"></div>
                                        </div>
                                        <div id="finished-empty" class="text-muted small text-center py-3">No finished patients.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($showDepartmentPanel): ?>
                        <div class="dept-queues-wrapper">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h5 class="mb-0">Department Queues</h5>
                                <span class="text-muted small">Monitor routed customers and trigger announcements per department.</span>
                            </div>
                            <div id="department-queues" class="row g-3"></div>
                            <div id="department-queues-empty" class="text-muted small py-3 d-none">No departments available for your role.</div>
                        </div>
                    <?php endif; ?>

                    <?php if (!$showRegistrationPanel && !$showDepartmentPanel): ?>
                        <div class="alert alert-warning mb-0" role="alert">
                            You do not have permission to view any queue panels on this page.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade patient-tag-modal" id="patient-tag-modal" tabindex="-1" aria-labelledby="patient-tag-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="patient-tag-modal-label">Tag Patient Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="text-muted small">Ticket</div>
                    <div id="patient-tag-ticket" class="fw-semibold"></div>
                </div>
                <div class="mb-3">
                    <div class="text-muted small">Current Retrieval Code</div>
                    <div id="patient-tag-current-code" class="fw-semibold text-primary"></div>
                </div>
                <div class="mb-3">
                    <label for="patient-tag-search" class="form-label">Search patient</label>
                    <input type="text" class="form-control" id="patient-tag-search" placeholder="Search by name or retrieval code">
                    <div class="form-text">Type at least 2 characters to find a patient record.</div>
                </div>
                <div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="patient-upload-open">
                        <i class="ri-upload-cloud-line me-1"></i>Upload Dashlabs profile
                    </button>
                    <span class="text-muted small">Import a patient profile without leaving this page.</span>
                </div>
                <div id="patient-tag-loading" class="text-center py-2 d-none">
                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    Searching patients...
                </div>
                <div id="patient-tag-empty" class="text-muted fst-italic">Start typing to search for a patient.</div>
                <ul id="patient-tag-results" class="list-group list-group-flush mt-2"></ul>
                <div class="mb-3" id="patient-tag-selected-wrapper">
                    <div class="text-muted small">Selected patient</div>
                    <div id="patient-tag-selected" class="fw-semibold text-primary">None selected</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Select services</label>
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" id="patient-tag-service-search" placeholder="Search by service description">
                        <button class="btn btn-outline-secondary" type="button" id="patient-tag-service-clear">Clear</button>
                    </div>
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <select id="patient-tag-services" class="form-select" size="6" aria-label="Service list"></select>
                        <button class="btn btn-primary" type="button" id="patient-tag-service-add">Add</button>
                    </div>
                    <div id="patient-tag-services-empty" class="text-muted fst-italic d-none">No services available.</div>
                    <div class="d-flex align-items-center justify-content-between small fw-semibold mb-1">
                        <span>Selected services</span>
                        <span class="text-primary" id="patient-tag-services-total">Total: 0.00</span>
                    </div>
                    <div id="patient-tag-services-list" class="list-group mb-2" style="max-height: 200px; overflow-y: auto;"></div>
                    <button class="btn btn-outline-danger btn-sm" type="button" id="patient-tag-service-remove">Remove selected</button>
                    <div class="form-text mt-2">Added services will be routed to their departments and cashier after tagging.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="patient-tag-submit">Save and Tag</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="patient-upload-modal" tabindex="-1" aria-labelledby="patient-upload-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="patient-upload-modal-label">Upload Dashlabs Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="patient-upload-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="patient-upload-warning" class="alert alert-warning d-none" role="alert"></div>
                <div class="card-upload-helper mb-3">
                    <input type="file" class="d-none" id="patient-upload-file" accept=".html,.htm">
                    <div class="file-label">
                        <i class="bx bx-cloud-upload fs-1 text-primary"></i>
                        <h6 class="fw-semibold mb-1">Drop the Dashlabs export here</h6>
                        <p class="text-muted mb-1 small">Upload the HTML export to add/update patient profiles.</p>
                        <button type="button" class="btn btn-primary btn-sm mt-2" id="patient-upload-trigger">Choose file</button>
                        <div class="text-muted small mt-1" id="patient-upload-filename">No file selected</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <button class="btn btn-primary btn-sm" type="button" id="patient-upload-preview">Generate Preview</button>
                    <button class="btn btn-success btn-sm" type="button" id="patient-upload-import" disabled>Import Profiles</button>
                    <button class="btn btn-outline-secondary btn-sm" type="button" id="patient-upload-reset">Clear</button>
                    <div class="spinner-border spinner-border-sm text-primary d-none" role="status" id="patient-upload-loading">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div id="patient-upload-preview-empty" class="text-muted fst-italic">Upload a Dashlabs export to see a preview.</div>
                <div id="patient-upload-preview-wrapper" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered upload-preview-table mb-0">
                            <thead>
                                <tr>
                                    <th>Retrieval Code</th>
                                    <th>First name</th>
                                    <th>Last name</th>
                                    <th>Birthday</th>
                                    <th>Sex</th>
                                    <th>Email</th>
                                    <th>Branches</th>
                                </tr>
                            </thead>
                            <tbody id="patient-upload-preview-body"></tbody>
                        </table>
                    </div>
                    <div class="text-muted small mt-2" id="patient-upload-preview-count"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cashier-payment-modal" tabindex="-1" aria-labelledby="cashier-payment-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cashier-payment-modal-label">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="cashier-payment-alert" class="alert alert-danger d-none" role="alert"></div>
                <div class="mb-2 d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        <div class="text-muted small">Ticket</div>
                        <div id="cashier-payment-queue" class="fw-semibold"></div>
                    </div>
                    <div>
                        <div class="text-muted small">Patient</div>
                        <div id="cashier-payment-patient" class="fw-semibold"></div>
                    </div>
                    <div>
                        <div class="text-muted small">Retrieval Code</div>
                        <div id="cashier-payment-retrieval" class="fw-semibold text-primary"></div>
                    </div>
                </div>
                <div class="table-responsive mb-3">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="cashier-payment-services">
                            <tr><td colspan="2" class="text-muted text-center">No services found.</td></tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="text-end" id="cashier-payment-total">0.00</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="mb-3">
                    <label for="cashier-payment-receipt-no" class="form-label">Receipt No.</label>
                    <input type="text" class="form-control" id="cashier-payment-receipt-no" placeholder="Enter receipt number">
                    <div class="form-text">Manual receipt number for now.</div>
                </div>
                <div class="mb-3">
                    <label for="cashier-payment-received" class="form-label">Amount Received</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="cashier-payment-received" placeholder="Enter amount received">
                    <div class="form-text">Any amount is accepted for now.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="cashier-payment-submit">Submit Payment</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="charge-slip-modal" tabindex="-1" aria-labelledby="charge-slip-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="charge-slip-modal-label">Charge Slip Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="charge-slip-alert" class="alert alert-danger d-none" role="alert"></div>
                <div id="charge-slip-loading" class="charge-slip-loading text-muted small d-none">Preparing charge slip...</div>
                <div class="charge-slip-preview">
                    <div class="charge-slip-receipt" id="charge-slip-receipt">
                        <div class="text-muted text-center small">No details loaded.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="charge-slip-cancel" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="charge-slip-print">Print Slip</button>
            </div>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>

<?php $scripts = queue_board_scripts($role); ?>

<?php include 'layouts/base.php'; ?>
