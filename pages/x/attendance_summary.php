<?php
// File: pages/attendance_summary.php

session_start();
date_default_timezone_set('Asia/Manila');

// PHP5‐compatible defaults
$role      = isset($_SESSION['sess_userrole'])   ? $_SESSION['sess_userrole']   : '';
$firstname = isset($_SESSION['sess_firstname'])  ? $_SESSION['sess_firstname']  : '';
$lastname  = isset($_SESSION['sess_lastname'])   ? $_SESSION['sess_lastname']   : '';
$fullname  = trim($firstname . ' ' . $lastname);

// Optional empno for filtering
$empno     = isset($_GET['empno'])               ? $_GET['empno']               : '';

// Build base URL for JS redirects
$baseUrl   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https://' : 'http://')
           . $_SERVER['HTTP_HOST']
           . str_replace('/pages','',dirname($_SERVER['SCRIPT_NAME']));
?>

<?php ob_start(); ?>
  <!-- Bootstrap & DataTables CSS (if you keep DataTables) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons for the calendar icon -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <!-- Bootstrap Datepicker CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/css/bootstrap-datepicker.min.css">
  <style>
    /* Float the calendar outside any overflow and gap below input */
    .datepicker.dropdown-menu {
      container: body;
      margin-top: .25rem !important;
      z-index: 3000 !important;
    }
  </style>
<?php $styles = ob_get_clean(); ?>


<?php ob_start(); ?>
  <div class="container py-4">
    <h1 class="h4 mb-4">Attendance Summary</h1>

    <form id="attendance-form" class="row g-3 align-items-end">
      <!-- Start Date -->
      <div class="col-md-3">
        <label for="startDate" class="form-label">Start Date</label>
        <div class="input-group">
          <input type="text"
                 class="form-control datepicker"
                 id="startDate"
                 name="startDate"
                 placeholder="dd/mm/yyyy"
                 required>
          <span class="input-group-text">
            <i class="bi bi-calendar-event"></i>
          </span>
        </div>
      </div>

      <!-- End Date -->
      <div class="col-md-3">
        <label for="endDate" class="form-label">End Date</label>
        <div class="input-group">
          <input type="text"
                 class="form-control datepicker"
                 id="endDate"
                 name="endDate"
                 placeholder="dd/mm/yyyy"
                 required>
          <span class="input-group-text">
            <i class="bi bi-calendar-event"></i>
          </span>
        </div>
      </div>

      <!-- Print Report Button -->
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-success">
          Print Report
        </button>
      </div>
    </form>
  </div>
<?php $content = ob_get_clean(); ?>


<?php ob_start(); ?>
  <!-- jQuery & Bootstrap JS -->
  <script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Bootstrap Datepicker -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap-datepicker@1.9.0/dist/js/bootstrap-datepicker.min.js"></script>

  <script>
  const BASE_URL = '<?php echo $baseUrl; ?>';
  const EMP_NO   = '<?php echo addslashes($empno); ?>';

  $(function(){
    // 1) Initialize datepickers, append to body so they're never clipped
    $('.datepicker').datepicker({
      format: 'dd/mm/yyyy',
      autoclose: true,
      todayHighlight: true,
      container: 'body'
    });

    // 2) On form submit → redirect to print script with YYYY-MM-DD parameters
    $('#attendance-form').on('submit', function(e){
      e.preventDefault();
      var from = $('#startDate').val(),
          to   = $('#endDate').val();
      if (!from || !to) {
        return alert('Please select both dates.');
      }

      // Convert dd/mm/yyyy → yyyy-mm-dd
      function toISO(s) {
        var p = s.split('/');
        return p[2] + '-' + p[1] + '-' + p[0];
      }

      var url = BASE_URL + '/pages/print_employee_attendance_period.php'
              + '?from=' + encodeURIComponent(toISO(from))
              + '&to='   + encodeURIComponent(toISO(to));
      if (EMP_NO) {
        url += '&empno=' + encodeURIComponent(EMP_NO);
      }
      // navigate to print script
      window.location.href = url;
    });
  });
  </script>
<?php $scripts = ob_get_clean(); ?>


<?php include 'layouts/base.php'; ?>
