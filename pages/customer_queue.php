<!-- Base URL setup copied from devices.php -->
<?php
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>

<?php 
session_start();
?>

<?php ob_start(); ?>
    <!-- Optional: page-specific styles -->
    <style>
        .queue-buttons .btn {
            padding: 2rem 1.5rem;
            font-size: 1.25rem;
        }
        .queue-display {
            text-align: center;
        }
        .queue-number {
            font-size: 4rem;
            font-weight: 800;
            letter-spacing: 0.05em;
        }
        .queue-code {
            font-size: 1.5rem;
            font-weight: 700;
        }
        #queue-qr {
            min-height: 190px;
        }
        .queue-code-display {
            display: inline-block;
            font-family: "Arial Black", "Arial", "Helvetica", sans-serif;
            font-size: 2.55rem;
            font-weight: 900;
            letter-spacing: 0.12em;
            padding: 0.75rem 1.5rem;
            border: 2px solid #000;
            background: #fff;
            color: #000;
            text-transform: uppercase;
        }
        .write-line {
            border-bottom: 2px solid #000;
            height: 2.25rem;
            margin: 0 auto;
            max-width: 260px;
            position: relative;
        }
        .write-line::after {
            content: attr(data-placeholder);
            position: absolute;
            inset: 0;
            font-family: "Arial", "Helvetica", sans-serif;
            font-size: 0.85rem;
            color: rgba(15, 23, 42, 0.35);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            display: flex;
            align-items: flex-end;
            justify-content: flex-start;
            padding-bottom: 0.2rem;
        }
        .queue-display .label {
            font-family: "Arial", "Helvetica", sans-serif;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .printing-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.75);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }
        .printing-overlay.active {
            display: flex;
        }
        .printing-overlay .printing-box {
            background: #0f172a;
            color: #f1f5f9;
            padding: 1.75rem 2.5rem;
            border-radius: 14px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.45);
            text-align: center;
            min-width: 240px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .printing-overlay .printing-box small {
            display: block;
            margin-top: 0.75rem;
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            opacity: 0.75;
        }
        @media print {
            body {
                margin: 0;
                background: #fff !important;
            }
            body * {
                visibility: hidden;
            }
            #queue-ticket,
            #queue-ticket * {
                visibility: visible;
            }
            #queue-ticket {
                position: absolute;
                inset: 0;
                margin: 0;
                width: 100%;
                border: none !important;
                box-shadow: none !important;
            }
        }
    </style>
<?php $styles = ob_get_clean(); ?>

<?php ob_start(); ?>

<?php
// Use cdd DB connection
require_once __DIR__ . '/db2.php'; // exposes $mysqli

// Ensure connection is available
if (!isset($mysqli) || !$mysqli) {
    die('Database connection not available.');
}

if (!function_exists('cdd_generate_code_segment')) {
    function cdd_generate_code_segment($length = 3)
    {
        $length = max(1, (int)$length);
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $result = '';
        $maxIndex = strlen($chars) - 1;
        $useRandomInt = function_exists('random_int');
        for ($i = 0; $i < $length; $i++) {
            try {
                $index = $useRandomInt ? random_int(0, $maxIndex) : mt_rand(0, $maxIndex);
            } catch (Exception $ex) {
                $index = mt_rand(0, $maxIndex);
            }
            $result .= $chars[$index];
        }
        return $result;
    }
}

$allowed = [
    'P' => 'Priority (Seniors/PWD/Pregnant)',
    'R' => 'Regular'
];

$resultData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category'])) {
    $postedCategory = isset($_POST['category']) ? $_POST['category'] : '';
    $cat = strtoupper(trim($postedCategory));
    if (!array_key_exists($cat, $allowed)) {
        $error = 'Invalid category selected.';
    } else {
        // Get next queue number for today and category
        $stmt = $mysqli->prepare("SELECT COALESCE(MAX(queue_number), 0) + 1 AS next_num FROM daily_queue WHERE queue_date = CURDATE() AND category = ?");
        $stmt->bind_param('s', $cat);
        $stmt->execute();
        $stmt->bind_result($nextNum);
        $stmt->fetch();
        $stmt->close();

        if (!$nextNum) { $nextNum = 1; }

        $ticketSequence = str_pad((string)$nextNum, 2, '0', STR_PAD_LEFT);
        $queueCode = $cat . $ticketSequence;

        $dateSegment = date('ymd');
        $qrCode = '';
        $exists = false;
        $attempts = 0;
        do {
            $prefixLength = $attempts >= 5 ? 5 : 3;
            $qrCode = cdd_generate_code_segment($prefixLength) . $dateSegment . $queueCode;
            $chk = $mysqli->prepare("SELECT 1 FROM daily_queue WHERE unique_code = ? LIMIT 1");
            $chk->bind_param('s', $qrCode);
            $chk->execute();
            $chk->store_result();
            $exists = $chk->num_rows > 0;
            $chk->close();
            $attempts++;
        } while ($exists && $attempts < 7);

        if ($exists) {
            $qrCode = cdd_generate_code_segment(8) . $dateSegment . $queueCode;
        }

        // Insert record
        $ins = $mysqli->prepare("INSERT INTO daily_queue (category, queue_number, unique_code, queue_date) VALUES (?, ?, ?, CURDATE())");
        $ins->bind_param('sis', $cat, $nextNum, $qrCode);
        if ($ins->execute()) {
            $resultData = [
                'category' => $allowed[$cat],
                'queue_number' => $nextNum,
                'ticket_number' => $queueCode,
                'unique_code' => $qrCode,
            ];
        } else {
            $error = 'Failed to save queue. Please try again.';
        }
        $ins->close();
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card" id="queue-ticket">
                <div class="card-header justify-content-between">
                    <div class="card-title">Patient Queue</div>
                </div>
                <div class="card-body">
                    <div id="printing-overlay" class="printing-overlay" aria-hidden="true">
                        <div class="printing-box">
                            Patient queue printing...
                            <small>Please wait</small>
                        </div>
                    </div>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($resultData === null): ?>
                        <p class="mb-4">Please select your queue category:</p>
                        <div class="row queue-buttons g-3">
                            <div class="col-md-6">
                                <form method="post">
                                    <input type="hidden" name="category" value="P">
                                    <button class="btn btn-primary w-100 btn-wave" type="submit">Priority (Senior/PWD/Pregnant)</button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <form method="post">
                                    <input type="hidden" name="category" value="R">
                                    <button class="btn btn-secondary w-100 btn-wave" type="submit">Regular</button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="queue-display py-4">
                            <div class="mb-2 text-muted">Category</div>
                            <div class="h3 mb-4"><?php echo htmlspecialchars($resultData['category']); ?></div>

                            <div class="mb-2 text-muted">Ticket Number</div>
                            <div class="queue-code-display mb-4"><?php echo htmlspecialchars($resultData['ticket_number']); ?></div>

                            <?php if (!empty($resultData['unique_code'])): ?>
                                <div class="mb-2 text-muted">QR Reference</div>
                                <div class="h6 mb-4 text-uppercase"><?php echo htmlspecialchars($resultData['unique_code']); ?></div>
                            <?php endif; ?>

                            <div class="mb-2 text-muted">Patient Name</div>
                            <div class="write-line mb-4" data-placeholder="Print name here"></div>

                            <div class="mb-2 text-muted">Fasting Time</div>
                            <div class="write-line mb-4" data-placeholder="Fasting time"></div>

                            <div class="mb-2 text-muted">Scan This QR</div>
                            <div id="queue-qr" class="d-flex justify-content-center mb-4"></div>

                            <div class="d-flex justify-content-center gap-2">
                                <button type="button" id="queue-print-btn" class="btn btn-outline-primary"><i class="ri-printer-line me-2"></i>Print</button>
                                <a href="customer_queue.php" class="btn btn-primary">Start Over</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>

<?php ob_start(); ?>
    <?php if ($resultData !== null): ?>
        <script src="<?php echo $baseUrl; ?>/assets/js/vendor/qrcode.min.js"></script>
        <script>
            (function () {
                var ticketData = <?php echo json_encode($resultData); ?>;
                var baseUrl = <?php echo json_encode($baseUrl); ?>;
                var container = document.getElementById('queue-qr');
                var printingOverlay = document.getElementById('printing-overlay');

                if (container) {
                    if (typeof QRCode === 'undefined') {
                        container.innerHTML = '<span class="text-muted">QR code unavailable.</span>';
                    } else {
                        container.innerHTML = '';
                        new QRCode(container, {
                            text: ticketData.unique_code || '',
                            width: 180,
                            height: 180,
                            correctLevel: QRCode.CorrectLevel.H
                        });
                    }
                }

                var printBtn = document.getElementById('queue-print-btn');
                if (printBtn) {
                    printBtn.addEventListener('click', function (event) {
                        event.preventDefault();
                        openThermalPrint(ticketData);
                    });
                }

                function openThermalPrint(data) {
                    showPrintingOverlay();
                    var width = 420;
                    var height = 640;
                    var dualScreenLeft = window.screenX !== undefined ? window.screenX : (window.screenLeft || 0);
                    var dualScreenTop = window.screenY !== undefined ? window.screenY : (window.screenTop || 0);
                    var availableWidth = window.outerWidth || document.documentElement.clientWidth || 800;
                    var availableHeight = window.outerHeight || document.documentElement.clientHeight || 600;
                    var calculatedLeft = dualScreenLeft + Math.max(0, (availableWidth - width) / 2);
                    var calculatedTop = dualScreenTop + Math.max(0, (availableHeight - height) / 2);
                    var features = [
                        'width=' + Math.round(width),
                        'height=' + Math.round(height),
                        'left=' + Math.round(calculatedLeft),
                        'top=' + Math.round(calculatedTop),
                        'resizable=no',
                        'scrollbars=no'
                    ].join(',');
                    var printWindow = window.open('', 'thermalTicket', features);
                    if (!printWindow) {
                        hidePrintingOverlay();
                        alert('Please allow pop-ups to print the ticket.');
                        return;
                    }
                    printWindow.opener = null;
                    var doc = printWindow.document;
                    doc.open('text/html');
                    doc.write(buildTicketHtml(['admin', 'customer'], data || {}));
                    doc.close();
                    try {
                        printWindow.focus();
                    } catch (focusError) {
                        // no-op: some browsers will block focusing the new print window
                    }

                    var cleanupTimer = window.setInterval(function () {
                        if (printWindow.closed) {
                            window.clearInterval(cleanupTimer);
                            hidePrintingOverlay();
                            window.location.href = 'customer_queue.php';
                        }
                    }, 400);
                }

                function showPrintingOverlay() {
                    if (printingOverlay) {
                        printingOverlay.classList.add('active');
                        printingOverlay.setAttribute('aria-hidden', 'false');
                    }
                }

                function hidePrintingOverlay() {
                    if (printingOverlay) {
                        printingOverlay.classList.remove('active');
                        printingOverlay.setAttribute('aria-hidden', 'true');
                    }
                }

                function buildTicketHtml(copyOrder, data) {
                    var sanitizeSequence = function (value) {
                        if (!value) {
                            return [];
                        }
                        if (Array.isArray(value)) {
                            return value;
                        }
                        return [value];
                    };

                    var normalizeCopies = function (sequence) {
                        var normalized = [];
                        for (var i = 0; i < sequence.length; i++) {
                            var current = sequence[i] === 'customer' ? 'customer' : 'admin';
                            normalized.push(current);
                        }
                        if (!normalized.length) {
                            normalized.push('admin', 'customer');
                        }
                        return normalized;
                    };

                    var copies = normalizeCopies(sanitizeSequence(copyOrder));
                    var payload = JSON.stringify({
                        copies: copies,
                        data: data || {}
                    });
                    var safePayload = payload.replace(/</g, '\\u003c');

                    var lines = [];
                    lines.push('<!DOCTYPE html>');
                    lines.push('<html>');
                    lines.push('<head>');
                    lines.push('<meta charset="utf-8">');
                    lines.push('<title>Queue Ticket</title>');
                    lines.push('<style>');
                    lines.push('@page { size: 80mm auto; margin: 4mm; }');
                    lines.push('html, body { margin: 0; padding: 0; font-family: "Arial", "Helvetica", sans-serif; background: #fff; color: #000; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }');
                    lines.push('body { font-size: 11pt; }');
                    lines.push('.ticket { width: 100%; text-align: center; padding: 6mm 4mm; box-sizing: border-box; }');
                    lines.push('.ticket h1 { font-family: "Arial Black", "Arial", sans-serif; font-size: 18pt; margin: 0 0 10pt; letter-spacing: 0.09em; text-transform: uppercase; }');
                    lines.push('.ticket .copy-label { font-size: 9pt; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; margin-bottom: 8pt; }');
                    lines.push('.ticket .label { font-size: 8.8pt; text-transform: uppercase; margin: 10pt 0 3pt; letter-spacing: 0.08em; }');
                    lines.push('.ticket .value { font-size: 14pt; font-weight: 700; margin: 0; letter-spacing: 0.04em; }');
                    lines.push('.ticket .code-block { display: inline-block; font-family: "Arial Black", "Arial", sans-serif; font-size: 24pt; font-weight: 900; letter-spacing: 0.18em; padding: 8pt 16pt; border: 2px solid #000; margin-top: 5pt; background: #fff; -webkit-print-color-adjust: exact; }');
                    lines.push('.ticket .write-line { position: relative; margin: 12pt auto 0; width: 85%; height: 18pt; border-bottom: 1.5px solid #000; }');
                    lines.push('.ticket .write-line::after { content: attr(data-placeholder); position: absolute; inset: 0; display: flex; align-items: flex-end; font-size: 8pt; color: rgba(0, 0, 0, 0.35); letter-spacing: 0.08em; text-transform: uppercase; padding-bottom: 2pt; }');
                    lines.push('.ticket .qr { margin: 16pt auto 0; width: 170px; height: 170px; }');
                    lines.push('.ticket .footnote { margin-top: 14pt; font-size: 8pt; letter-spacing: 0.06em; }');
                    lines.push('</style>');
                    lines.push('<script>window.ticketConfig = ' + safePayload + ';<\/script>');
                    lines.push('<script src="' + baseUrl + '/assets/js/vendor/qrcode.min.js"><\/script>');
                    lines.push('</head>');
                    lines.push('<body>');
                    lines.push('<div id="ticket-body"></div>');
                    lines.push('<script>');
                    lines.push('(function () {');
                    lines.push('    var config = window.ticketConfig || {};');
                    lines.push('    var data = config.data || {};');
                    lines.push('    var copies = Array.isArray(config.copies) ? config.copies.slice() : ["admin", "customer"];');
                    lines.push('    if (!copies.length) {');
                    lines.push('        copies = ["admin", "customer"];');
                    lines.push('    }');
                    lines.push('    copies = copies.map(function (item) {');
                    lines.push('        return item === "customer" ? "customer" : "admin";');
                    lines.push('    });');
                    lines.push('    var container = document.getElementById("ticket-body");');
                    lines.push('    var currentIndex = 0;');
                    lines.push('    var printingInProgress = false;');
                    lines.push('    var escapeHtml = function (value) {');
                    lines.push('        return String(value == null ? "" : value)');
                    lines.push('            .replace(/&/g, "&amp;")');
                    lines.push('            .replace(/</g, "&lt;")');
                    lines.push('            .replace(/>/g, "&gt;")');
                    lines.push('            .replace(/"/g, "&quot;")');
                    lines.push('            .replace(/\'/g, "&#39;");');
                    lines.push('    };');
                    lines.push('    function renderCopy(type) {');
                    lines.push('        var normalized = type === "customer" ? "customer" : "admin";');
                    lines.push('        var html = [];');
                    lines.push('        html.push(\'<div class="ticket ticket-\' + normalized + \'">\');');
                    lines.push('        html.push(\'<h1>Patient Queue</h1>\');');
                    lines.push('        html.push(\'<div class="copy-label">\' + (normalized === "admin" ? "Admin Copy" : "Customer Copy") + \'</div>\');');
                    lines.push('        if (normalized === "admin") {');
                    lines.push('            html.push(\'<div class="label">Category</div>\');');
                    lines.push('            html.push(\'<div class="value">\' + escapeHtml(data.category) + \'</div>\');');
                    lines.push('            html.push(\'<div class="label">Ticket Number</div>\');');
                    lines.push('            html.push(\'<div class="code-block">\' + escapeHtml(data.ticket_number) + \'</div>\');');
                    lines.push('            if (data.unique_code) {');
                    lines.push('                html.push(\'<div class="label">QR Reference</div>\');');
                    lines.push('                html.push(\'<div class="value">\' + escapeHtml(data.unique_code) + \'</div>\');');
                    lines.push('            }');
                    lines.push('            html.push(\'<div class="label">Patient Name</div>\');');
                    lines.push('            html.push(\'<div class="write-line" data-placeholder="Print name here"></div>\');');
                    lines.push('            html.push(\'<div class="label">Fasting Time</div>\');');
                    lines.push('            html.push(\'<div class="write-line" data-placeholder="Fasting time"></div>\');');
                    lines.push('            html.push(\'<div class="label">Scan This QR</div>\');');
                    lines.push('            html.push(\'<div class="qr" id="print-ticket-qr"></div>\');');
                    lines.push('            html.push(\'<div class="footnote">Please proceed to the waiting area.</div>\');');
                    lines.push('        } else {');
                    lines.push('            html.push(\'<div class="label">Ticket Number</div>\');');
                    lines.push('            html.push(\'<div class="code-block">\' + escapeHtml(data.ticket_number) + \'</div>\');');
                    lines.push('            if (data.unique_code) {');
                    lines.push('                html.push(\'<div class="label">QR Reference</div>\');');
                    lines.push('                html.push(\'<div class="value">\' + escapeHtml(data.unique_code) + \'</div>\');');
                    lines.push('            }');
                    lines.push('            html.push(\'<div class="label">Scan This QR</div>\');');
                    lines.push('            html.push(\'<div class="qr" id="print-ticket-qr"></div>\');');
                    lines.push('            html.push(\'<div class="footnote">Keep this ticket for your reference.</div>\');');
                    lines.push('        }');
                    lines.push('        html.push(\'</div>\');');
                    lines.push('        if (container) {');
                    lines.push('            container.innerHTML = html.join("");');
                    lines.push('        }');
                    lines.push('        document.title = normalized === "admin" ? "Queue Ticket - Admin Copy" : "Queue Ticket - Customer Copy";');
                    lines.push('        var qr = document.getElementById("print-ticket-qr");');
                    lines.push('        if (qr && typeof QRCode !== "undefined") {');
                    lines.push('            qr.innerHTML = "";');
                    lines.push('            new QRCode(qr, {');
                    lines.push('                text: data.unique_code || "",');
                    lines.push('                width: 180,');
                    lines.push('                height: 180,');
                    lines.push('                correctLevel: QRCode.CorrectLevel.M');
                    lines.push('            });');
                    lines.push('        } else if (qr) {');
                    lines.push('            qr.innerHTML = "<span>QR unavailable</span>";');
                    lines.push('        }');
                lines.push('    }');
                    lines.push('    function finishPrinting() {');
                    lines.push('        window.setTimeout(function () {');
                    lines.push('            try { window.close(); } catch (closeError) { /* ignore */ }');
                    lines.push('        }, 500);');
                    lines.push('    }');
                    lines.push('    function processCurrent() {');
                    lines.push('        if (currentIndex >= copies.length) {');
                    lines.push('            finishPrinting();');
                    lines.push('            return;');
                    lines.push('        }');
                    lines.push('        var currentType = copies[currentIndex];');
                    lines.push('        renderCopy(currentType);');
                    lines.push('        window.setTimeout(function () {');
                    lines.push('            printingInProgress = true;');
                    lines.push('            try { window.focus(); } catch (focusError) { /* ignore */ }');
                    lines.push('            window.print();');
                    lines.push('        }, 400);');
                    lines.push('    }');
                    lines.push('    function handlePrintComplete() {');
                    lines.push('        if (!printingInProgress) {');
                    lines.push('            return;');
                    lines.push('        }');
                    lines.push('        printingInProgress = false;');
                    lines.push('        currentIndex += 1;');
                    lines.push('        processCurrent();');
                    lines.push('    }');
                    lines.push('    window.addEventListener("afterprint", handlePrintComplete);');
                    lines.push('    window.addEventListener("focus", handlePrintComplete);');
                    lines.push('    processCurrent();');
                    lines.push('})();');
                    lines.push('<\/script>');
                    lines.push('</body>');
                    lines.push('</html>');

                    return lines.join('');
                }
            })();
        </script>
    <?php endif; ?>
<?php $scripts = ob_get_clean(); ?>

<?php include 'layouts/base.php'; ?>
