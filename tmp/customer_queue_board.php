<?php
$rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Queue Board</title>
    <link id="style" href="<?php echo $baseUrl; ?>/assets/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $baseUrl; ?>/assets/css/icons.css" rel="stylesheet">
    <link href="<?php echo $baseUrl; ?>/assets/css/styles.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            background: #05210aff;
            color: #e2e8f0;
            font-family: "Inter", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background:
                radial-gradient(circle at top left, rgba(32, 178, 170, 0.18), transparent 60%),
                radial-gradient(circle at bottom right, rgba(30, 128, 255, 0.2), transparent 55%),
                #051821;
        }

        .board-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            grid-template-rows: minmax(0, 1fr) minmax(0, 1fr);
            height: 100vh;
            gap: 1.5rem;
            padding: 1.5rem;
            box-sizing: border-box;
        }

        .board-section {
            background: rgba(7, 24, 34, 0.88);
            border-radius: 20px;
            box-shadow: 0 30px 60px rgba(6, 25, 36, 0.4);
            position: relative;
            overflow: hidden;
        }

        .queue-section {
            padding: 2.25rem;
            display: flex;
            flex-direction: column;
            background: linear-gradient(160deg, rgba(23, 146, 129, 0.72), rgba(16, 71, 129, 0.95));
            border: 2px solid rgba(52, 199, 156, 0.35);
            position: relative;
        }

        .queue-section--onqueue {
            grid-column: 1 / 2;
            grid-row: 1 / 2;
        }

        .queue-section--serving {
            grid-column: 2 / 3;
            grid-row: 1 / 3;
            display: flex;
            flex-direction: column;
            background: linear-gradient(165deg, rgba(31, 92, 150, 0.82), rgba(57, 31, 144, 0.88));
            border-color: rgba(111, 168, 255, 0.35);
        }

        .queue-section--serving .queue-title span {
            color: rgba(210, 224, 255, 0.82);
        }

        .queue-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.4rem;
        }

        .queue-title {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .queue-title h1 {
            font-size: clamp(2.25rem, 3.8vw, 3.2rem);
            margin: 0;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #f8fafc;
            text-shadow: 0 4px 16px rgba(15, 23, 42, 0.45);
        }

        .queue-title span {
            font-size: clamp(1.05rem, 1.8vw, 1.3rem);
            color: rgba(214, 237, 234, 0.9);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 600;
        }

        .queue-audio-toggle {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
            z-index: 5;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            padding: 0;
            border-radius: 50%;
            border: 1px solid rgba(67, 217, 173, 0.4);
            background: linear-gradient(135deg, rgba(26, 163, 109, 0.8), rgba(30, 128, 255, 0.75));
            color: #f8fafc;
            box-shadow: 0 12px 24px rgba(6, 47, 71, 0.45);
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .queue-audio-toggle:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, rgba(29, 185, 121, 0.85), rgba(34, 149, 255, 0.82));
            box-shadow: 0 16px 34px rgba(10, 76, 110, 0.45);
        }

        .queue-audio-toggle.btn-muted {
            background: rgba(8, 33, 43, 0.55);
            color: rgba(226, 232, 240, 0.75);
            border-color: rgba(67, 217, 173, 0.2);
        }

        .queue-audio-toggle.btn-muted:hover {
            background: rgba(11, 50, 63, 0.65);
            box-shadow: 0 14px 26px rgba(11, 50, 63, 0.4);
        }

        .queue-audio-toggle i {
            font-size: 1.15rem;
        }

        .queue-section--onqueue {
            padding-left: 3.75rem;
        }

        .queue-section--onqueue .queue-header {
            padding-left: 0.5rem;
        }

        .queue-grid {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            overflow-y: auto;
            padding-right: 0;
            scrollbar-width: none;
        }

        .queue-grid::-webkit-scrollbar {
            display: none;
        }

        .queue-table {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        .queue-table--serving {
            padding-top: 0.25rem;
        }

        .queue-header-row {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
            gap: 1.5rem;
            padding: 0.9rem 1.4rem;
            background: rgba(7, 37, 49, 0.6);
            border-radius: 14px;
            font-size: 0.95rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(226, 241, 237, 0.75);
            font-weight: 600;
            border: 1px solid rgba(67, 217, 173, 0.35);
        }

        .queue-header-row--serving {
            display: none;
        }

        .queue-header-cell {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .queue-row {
            display: grid;
            grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
            gap: 1.5rem;
            align-items: center;
            background: linear-gradient(120deg, rgba(24, 158, 128, 0.78), rgba(33, 132, 227, 0.7));
            border-radius: 18px;
            padding: 1.35rem 1.8rem;
            border: 1px solid rgba(87, 222, 183, 0.35);
            box-shadow: 0 16px 40px rgba(8, 40, 58, 0.4);
            position: relative;
            overflow: hidden;
        }

        .queue-row::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 18px;
            border: 1px solid transparent;
            background: linear-gradient(130deg, rgba(183, 246, 216, 0.55), rgba(129, 215, 255, 0.25)) border-box;
            -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .queue-row:hover::after {
            opacity: 1;
        }

        .queue-row.is-new {
            box-shadow: 0 0 0 3px rgba(69, 209, 171, 0.45), 0 24px 64px rgba(7, 47, 69, 0.55);
        }

        .queue-cell {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            min-width: 0;
        }

        .queue-identity {
            text-align: left;
        }

        .queue-ticket {
            font-size: clamp(2.1rem, 3.6vw, 3rem);
            font-weight: 800;
            letter-spacing: 0.12em;
            color: #f8fafc;
            text-shadow: 0 12px 30px rgba(15, 23, 42, 0.55);
            white-space: nowrap;
        }

        .queue-row.is-new .queue-ticket {
            animation: queueBlink 1s steps(2, start) 6;
        }

        .queue-code {
            font-size: clamp(1rem, 2vw, 1.15rem);
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: rgba(226, 232, 240, 0.85);
        }

        .queue-destination {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            text-align: right;
        }

        .destination-label {
            font-size: clamp(1.4rem, 2.6vw, 2rem);
            font-weight: 700;
            letter-spacing: 0.1em;
            color: #f1f5f9;
            text-transform: uppercase;
        }

        .queue-meta {
            font-size: 0.95rem;
            letter-spacing: 0.08em;
            color: rgba(226, 241, 237, 0.72);
        }

        .queue-meta--source {
            font-size: 0.85rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: rgba(203, 213, 225, 0.8);
        }

        .queue-data-table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            background: rgba(10, 26, 51, 0.75);
            border: 1px solid rgba(94, 133, 209, 0.35);
            border-radius: 12px;
            overflow: hidden;
        }

        .queue-data-table thead th {
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-weight: 600;
            font-size: 0.85rem;
            color: rgba(214, 228, 255, 0.78);
            padding: 0.7rem 0.9rem;
            background: rgba(22, 48, 91, 0.72);
        }

        .queue-data-table tbody tr {
            transition: background 0.2s ease, box-shadow 0.2s ease;
        }

        .queue-data-table tbody tr:nth-child(even) {
            background: rgba(12, 31, 61, 0.78);
        }

        .queue-data-table tbody tr:nth-child(odd) {
            background: rgba(16, 39, 71, 0.82);
        }

        .queue-data-table tbody tr:hover {
            background: rgba(46, 95, 170, 0.88);
        }

        .queue-data-table td {
            padding: 0.75rem 0.9rem;
            vertical-align: middle;
            border-top: 1px solid rgba(94, 133, 209, 0.35);
        }

        .queue-table-cell {
            text-align: left;
        }

        .queue-table-cell--department {
            text-align: left;
        }

        .queue-table-cell--ticket {
            text-align: right;
        }

        .queue-data-table td:first-child {
            width: 55%;
        }

        .queue-data-table .queue-ticket {
            font-size: clamp(1.1rem, 2vw, 1.4rem);
            letter-spacing: 0.08em;
        }

        .queue-department {
            font-size: clamp(1.1rem, 2vw, 1.5rem);
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #f1f5f9;
        }

        .queue-ticket.is-new {
            animation: queueBlink 1s steps(2, start) 6;
        }

        .queue-data-table .queue-code {
            font-size: clamp(0.85rem, 1.6vw, 0.95rem);
            letter-spacing: 0.1em;
        }

        .queue-data-table .destination-label {
            font-size: clamp(1rem, 1.8vw, 1.3rem);
            letter-spacing: 0.08em;
            text-align: right;
        }

        .queue-data-table .queue-meta {
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            text-align: right;
        }

        .queue-data-table .queue-meta--source {
            font-size: 0.72rem;
        }
        .queue-grid--serving .queue-meta {
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        @keyframes queueBlink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.2; }
        }

        .queue-message {
            min-height: 1.5rem;
            text-align: center;
            font-size: 1.1rem;
            margin-top: 1rem;
            letter-spacing: 0.06em;
            color: rgba(226, 232, 240, 0.85);
        }

        .media-section {
            padding: 0;
            display: flex;
            flex-direction: column;
            background: linear-gradient(155deg, rgba(12, 52, 68, 0.95), rgba(16, 71, 129, 0.85));
            grid-column: 1 / 2;
            grid-row: 2 / 3;
            overflow: hidden;
        }

        .media-video {
            position: relative;
            flex: 1;
            background: #000;
        }

        .media-video iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 0;
        }

        .media-ticker {
            background: rgba(15, 23, 42, 0.9);
            padding: 1rem 0;
            overflow: hidden;
            border-top: 1px solid rgba(148, 163, 184, 0.2);
        }

        .media-track {
            display: inline-flex;
            gap: 3rem;
            align-items: center;
            animation: ticker 25s linear infinite;
            white-space: nowrap;
            padding-left: 100%;
        }

        .media-track span {
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-size: 1rem;
        }

        @keyframes ticker {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-50%);
            }
        }

        @media (max-width: 1200px) {
            .board-layout {
                grid-template-columns: 1fr;
                grid-template-rows: auto auto auto;
            }

            .queue-section,
            .media-section {
                grid-column: 1 / -1;
                grid-row: auto;
            }

            .queue-section,
            .media-section {
                min-height: 300px;
            }

            .queue-section--serving {
                grid-column: 1 / -1;
                grid-row: auto;
            }
        }

        @media (max-width: 768px) {
            .board-layout {
                padding: 1rem;
                gap: 1rem;
            }

            .queue-title h1 {
                font-size: 1.5rem;
            }

            .queue-header-row,
            .queue-row {
                grid-template-columns: 1fr;
            }

            .queue-destination {
                align-items: flex-start;
                text-align: left;
            }

            .queue-ticket {
                font-size: 2.4rem;
            }
        }
    </style>
</head>
<body>
    <div class="board-layout">
        <section class="board-section queue-section queue-section--onqueue">
            <div class="queue-header">
                <div class="queue-title">
                    <h1>On Queue</h1>
                    <span>Tickets currently being called</span>
                </div>
            </div>
            <button id="enable-audio" class="queue-audio-toggle" type="button" title="Mute audio">
                <i class="ri-volume-up-line"></i>
            </button>
            <div class="queue-table">
                <div class="queue-header-row">
                    <div class="queue-header-cell">Customer Ticket</div>
                    <div class="queue-header-cell">Proceed To</div>
                </div>
                <div id="onqueue-display" class="queue-grid"></div>
            </div>
            <div id="onqueue-message" class="queue-message text-muted"></div>
        </section>
        <section class="board-section queue-section queue-section--serving">
            <div class="queue-header">
                <div class="queue-title">
                    <h1>Now Serving</h1>
                    <span>Customers currently with departments</span>
                </div>
            </div>
            <div class="queue-table queue-table--serving">
                <table class="queue-data-table">
                    <thead>
                        <tr>
                            <th scope="col">Department</th>
                            <th scope="col">Customer Ticket</th>
                        </tr>
                    </thead>
                    <tbody id="serving-display"></tbody>
                </table>
            </div>
            <div id="serving-message" class="queue-message text-muted"></div>
        </section>
        <section class="board-section media-section">
            <div class="media-video">
                <div id="queue-video-player"></div>
            </div>
            <div class="media-ticker">
                <div class="media-track">
                    <span>Stay hydrated while waiting.</span>
                    <span>Visit our pharmacy for exclusive promotions today.</span>
                    <span>Thank you for choosing Sierr Makiling Health Corporation.</span>
                    <span>Stay hydrated while waiting.</span>
                    <span>Visit our pharmacy for exclusive promotions today.</span>
                    <span>Thank you for choosing Sierr Makiling Health Corporation.</span>
                </div>
            </div>
        </section>
    </div>

    <script src="<?php echo $baseUrl; ?>/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://www.youtube.com/iframe_api"></script>
    <script>
        (function () {
            const playlistId = 'PLYxfQIwvSn3HQV1fVLUHYe1I_qrQz-PFu';
            let playerInstance = null;

            window.onYouTubeIframeAPIReady = function () {
                const container = document.getElementById('queue-video-player');
                if (!container || !window.YT) {
                    return;
                }

                const origin = window.location.origin && window.location.origin !== 'null'
                    ? window.location.origin
                    : undefined;

                const playerVars = {
                    autoplay: 1,
                    controls: 1,
                    mute: 1,
                    rel: 0,
                    modestbranding: 1,
                    playsinline: 1,
                    fs: 0,
                    loop: 1,
                    listType: 'playlist',
                    list: playlistId,
                    playlist: playlistId
                };

                if (origin) {
                    playerVars.origin = origin;
                }

                playerInstance = new YT.Player(container, {
                    width: '100%',
                    height: '100%',
                    playerVars: playerVars,
                    events: {
                        onReady: function (event) {
                            event.target.mute();
                            event.target.loadPlaylist({
                                listType: 'playlist',
                                list: playlistId,
                                index: 0,
                                startSeconds: 0
                            });
                            event.target.setLoop(true);
                            event.target.playVideo();
                        }
                    }
                });
            };
        })();
    </script>
    <script>
        (function () {
            const onQueueDisplay = document.getElementById('onqueue-display');
            const onQueueMessage = document.getElementById('onqueue-message');
            const servingDisplay = document.getElementById('serving-display');
            const servingMessage = document.getElementById('serving-message');
            const enableAudioBtn = document.getElementById('enable-audio');
            const doorbellAudioUrl = <?php echo json_encode($baseUrl . '/pages/audio/doorbell.mp3'); ?>;

            let refreshTimer = null;
            let audioEnabled = true;
            let announcements = {};
            let renderedState = {};
            let speechSupported = 'speechSynthesis' in window;
            let audioContext = null;
            let doorbellBuffer = null;
            let doorbellLoading = null;

            function getAudioContext() {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) {
                    return null;
                }
                if (!audioContext) {
                    audioContext = new AudioCtx();
                }
                return audioContext;
            }

            async function ensureAudioContext() {
                const ctx = getAudioContext();
                if (!ctx) {
                    return null;
                }
                if (ctx.state === 'suspended') {
                    try {
                        await ctx.resume();
                    } catch (err) {
                        return null;
                    }
                }
                return ctx;
            }

            async function loadDoorbellBuffer(ctx) {
                if (!doorbellAudioUrl) {
                    return null;
                }
                if (doorbellBuffer) {
                    return doorbellBuffer;
                }
                if (doorbellLoading) {
                    return doorbellLoading;
                }
                doorbellLoading = (async function () {
                    try {
                        const response = await fetch(doorbellAudioUrl, { cache: 'no-store' });
                        if (!response.ok) {
                            throw new Error('Failed to fetch doorbell audio');
                        }
                        const arrayBuffer = await response.arrayBuffer();
                        return await new Promise((resolve, reject) => {
                            ctx.decodeAudioData(arrayBuffer, resolve, reject);
                        });
                    } catch (error) {
                        console.error('Doorbell audio load error:', error);
                        return null;
                    }
                })();
                doorbellBuffer = await doorbellLoading;
                if (!doorbellBuffer) {
                    doorbellLoading = null;
                }
                return doorbellBuffer;
            }

            function playAttentionChime() {
                if (!audioEnabled) {
                    return Promise.resolve();
                }
                const ctx = getAudioContext();
                if (!ctx) {
                    return Promise.resolve();
                }
                return new Promise(async (resolve) => {
                    try {
                        await ensureAudioContext();
                        const buffer = await loadDoorbellBuffer(ctx);
                        if (!buffer) {
                            resolve();
                            return;
                        }
                        const source = ctx.createBufferSource();
                        const gainNode = ctx.createGain();
                        gainNode.gain.setValueAtTime(0.65, ctx.currentTime);
                        source.buffer = buffer;
                        source.connect(gainNode);
                        gainNode.connect(ctx.destination);
                        source.start();
                        source.onended = () => {
                            source.disconnect();
                            gainNode.disconnect();
                            resolve();
                        };
                        setTimeout(resolve, buffer.duration * 1000 + 120);
                    } catch (error) {
                        resolve();
                    }
                });
            }

            function setMessage(target, text, type) {
                if (!target) { return; }
                target.textContent = text || '';
                target.className = 'queue-message';
                if (text) {
                    target.classList.add(type === 'error' ? 'text-danger' : 'text-muted');
                }
            }

            function setBoardStatus(text, type) {
                setMessage(onQueueMessage, text, type);
                setMessage(servingMessage, text, type);
            }

        function formatQueueLabel(entry) {
            const category = (entry.category || '').toString().trim().toUpperCase();
            const rawNumber = entry.queue_number;
            const numberValue = typeof rawNumber === 'number'
                ? rawNumber
                : parseInt(rawNumber, 10);
            if (!Number.isNaN(numberValue)) {
                const paddedNumber = String(Math.max(numberValue, 0)).padStart(2, '0');
                return category ? category + paddedNumber : paddedNumber;
            }
            if (category) {
                return category;
            }
            const fallback = (entry.unique_code || '').toString().trim();
            return fallback || 'QUEUE';
        }

        function formatTimeLabel(timestamp) {
            if (!timestamp) {
                return '';
            }
            const parsed = new Date(timestamp);
            if (Number.isNaN(parsed.getTime())) {
                return '';
            }
                return parsed.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            }

            function updateAudioToggle() {
                if (!enableAudioBtn) {
                    return;
                }
                if (audioEnabled) {
                    enableAudioBtn.innerHTML = '<i class="ri-volume-mute-line"></i>';
                    enableAudioBtn.setAttribute('title', 'Mute audio');
                    enableAudioBtn.classList.remove('btn-muted');
                } else {
                    enableAudioBtn.innerHTML = '<i class="ri-volume-up-line"></i>';
                    enableAudioBtn.setAttribute('title', 'Enable audio');
                    enableAudioBtn.classList.add('btn-muted');
                }
            }

            function render(entries) {
                if (onQueueDisplay) {
                    onQueueDisplay.innerHTML = '';
                }
                if (servingDisplay) {
                    servingDisplay.innerHTML = '';
                }

                if (!entries.length) {
                    renderedState = {};
                    setMessage(onQueueMessage, 'Waiting for the next customer to be called...', 'info');
                    setMessage(servingMessage, 'No routed customers being served.', 'info');
                    return;
                }

                setMessage(onQueueMessage, '', 'info');
                setMessage(servingMessage, '', 'info');

                const previousState = renderedState;
                renderedState = {};

                const sortedEntries = entries.slice().sort(function (a, b) {
                    const aTime = a.called_at ? new Date(a.called_at).getTime() : 0;
                    const bTime = b.called_at ? new Date(b.called_at).getTime() : 0;
                    if (bTime === aTime) {
                        return String((a.type || '') + a.call_id).localeCompare(String((b.type || '') + b.call_id));
                    }
                    return bTime - aTime;
                });

                const registrationEntries = [];
                const registrationsRendered = new Set();
                const departmentEntries = [];

                sortedEntries.forEach(function (entry) {
                    const type = String(entry.type || 'registration').toLowerCase();
                    if (type === 'department') {
                        departmentEntries.push(entry);
                    } else {
                        const registrationKey = entry.registration_number != null && entry.registration_number !== ''
                            ? String(entry.registration_number)
                            : '__unassigned';
                        if (!registrationsRendered.has(registrationKey)) {
                            registrationsRendered.add(registrationKey);
                            registrationEntries.push(entry);
                        }
                    }
                });

                registrationEntries.forEach(function (entry) {
                    appendRegistrationCard(entry, onQueueDisplay, previousState);
                });

                departmentEntries.forEach(function (entry) {
                    appendDepartmentRow(entry, servingDisplay, previousState);
                });

                if (!registrationEntries.length) {
                    setMessage(onQueueMessage, 'Waiting for the next customer to be called...', 'info');
                }
                if (!departmentEntries.length) {
                    setMessage(servingMessage, 'No routed customers being served.', 'info');
                }
            }

            function appendRegistrationCard(entry, container, previousState) {
                if (!container) {
                    return;
                }
                const entryKey = (entry.type || 'registration') + ':' + String(entry.call_id);
                const calledAt = entry.called_at ? new Date(entry.called_at).getTime() : 0;
                renderedState[entryKey] = calledAt;

                const row = document.createElement('div');
                row.className = 'queue-row queue-row--registration';
                const wasRendered = Object.prototype.hasOwnProperty.call(previousState, entryKey);
                if (!wasRendered || previousState[entryKey] !== calledAt) {
                    row.classList.add('is-new');
                }

                const identityCell = document.createElement('div');
                identityCell.className = 'queue-cell queue-identity';

                const ticket = document.createElement('div');
                ticket.className = 'queue-ticket';
                ticket.textContent = formatQueueLabel(entry);
                identityCell.appendChild(ticket);

                if (entry.unique_code) {
                    const code = document.createElement('div');
                    code.className = 'queue-code';
                    code.textContent = entry.unique_code;
                    identityCell.appendChild(code);
                }

                const destinationCell = document.createElement('div');
                destinationCell.className = 'queue-cell queue-destination';

                const destination = document.createElement('div');
                destination.className = 'destination-label';
                const registrationValue = (entry.registration_number !== undefined && entry.registration_number !== null && entry.registration_number !== '')
                    ? entry.registration_number
                    : '';
                destination.textContent = registrationValue ? ('Registration ' + registrationValue) : 'Registration';
                destinationCell.appendChild(destination);

                const calledLabel = formatTimeLabel(entry.called_at);
                if (calledLabel) {
                    const meta = document.createElement('div');
                    meta.className = 'queue-meta';
                    meta.textContent = 'Called ' + calledLabel;
                    destinationCell.appendChild(meta);
                }

                row.appendChild(identityCell);
                row.appendChild(destinationCell);

                container.appendChild(row);
            }

            function appendDepartmentRow(entry, container, previousState) {
                if (!container) {
                    return;
                }
                const entryKey = (entry.type || 'department') + ':' + String(entry.call_id);
                const calledAt = entry.called_at ? new Date(entry.called_at).getTime() : 0;
                renderedState[entryKey] = calledAt;

                const wasRendered = Object.prototype.hasOwnProperty.call(previousState, entryKey);
                const isNew = !wasRendered || previousState[entryKey] !== calledAt;
                const row = document.createElement('tr');
                row.className = 'queue-table-row';

                const departmentCell = document.createElement('td');
                departmentCell.className = 'queue-table-cell queue-table-cell--department';
                const deptName = (entry.department_name || 'Department').trim();
                const departmentLabel = document.createElement('div');
                departmentLabel.className = 'queue-department';
                departmentLabel.textContent = deptName || 'Department';
                departmentCell.appendChild(departmentLabel);

                if (entry.registration_number) {
                    const sourceMeta = document.createElement('div');
                    sourceMeta.className = 'queue-meta queue-meta--source';
                    sourceMeta.textContent = 'From Registration ' + entry.registration_number;
                    departmentCell.appendChild(sourceMeta);
                }

                const calledLabel = formatTimeLabel(entry.called_at);
                if (calledLabel) {
                    const meta = document.createElement('div');
                    meta.className = 'queue-meta';
                    meta.textContent = 'Serving since ' + calledLabel;
                    departmentCell.appendChild(meta);
                }

                const ticketCell = document.createElement('td');
                ticketCell.className = 'queue-table-cell queue-table-cell--ticket';

                const ticket = document.createElement('div');
                ticket.className = 'queue-ticket';
                ticket.textContent = formatQueueLabel(entry);
                if (isNew) {
                    ticket.classList.add('is-new');
                }
                ticketCell.appendChild(ticket);

                if (entry.unique_code) {
                    const code = document.createElement('div');
                    code.className = 'queue-code';
                    code.textContent = entry.unique_code;
                    ticketCell.appendChild(code);
                }

                row.appendChild(departmentCell);
                row.appendChild(ticketCell);
                container.appendChild(row);
            }

            async function speakAnnouncement(entry) {
                if (!audioEnabled) {
                    return;
                }

                await playAttentionChime();

                if (!speechSupported) {
                    return;
                }

                const queuePhrase = formatQueueLabel(entry).replace('#', ' ');
                let message;
                if (entry.type === 'department') {
                    let deptName = (entry.department_name || 'department').trim();
                    if (!/department$/i.test(deptName)) {
                        deptName += ' department';
                    }
                    message = 'Customer number ' + queuePhrase + ', please proceed to the ' + deptName + '.';
                } else {
                    const hasRegistrationNumber = entry.registration_number !== undefined && entry.registration_number !== null && entry.registration_number !== '';
                    if (hasRegistrationNumber) {
                        message = 'Customer number ' + queuePhrase + ', please proceed to registration ' + entry.registration_number + '.';
                    } else {
                        message = 'Customer number ' + queuePhrase + ', please proceed to the registration area.';
                    }
                }

                const utterance = new SpeechSynthesisUtterance(message);
                utterance.lang = 'en-US';
                utterance.rate = 0.95;
                utterance.pitch = 1;
                window.speechSynthesis.cancel();
                window.speechSynthesis.speak(utterance);
            }

            async function processAnnouncements(entries) {
                const currentIds = {};
                const pendingAnnouncements = [];

                entries.forEach(function (entry) {
                const entryKey = (entry.type || 'registration') + ':' + String(entry.call_id);
                    currentIds[entryKey] = true;
                    const calledAt = new Date(entry.called_at).getTime();
                    const previous = announcements[entryKey] || 0;
                    if (calledAt > previous) {
                        announcements[entryKey] = calledAt;
                        pendingAnnouncements.push(entry);
                    }
                });

                for (const pending of pendingAnnouncements) {
                    await speakAnnouncement(pending);
                }

                Object.keys(announcements).forEach(function (key) {
                    if (!currentIds[key]) {
                        delete announcements[key];
                    }
                });
            }

            async function fetchBoard() {
                try {
                    const response = await fetch('queue_board_public_data.php?_=' + Date.now(), { cache: 'no-store' });
                    const data = await response.json();
                    if (!data.success) {
                        setBoardStatus(data.message || 'Unable to load queue board.', 'error');
                        return;
                    }
                    render(data.entries || []);
                    await processAnnouncements(data.entries || []);
                } catch (error) {
                    setBoardStatus('Connection lost. Retrying...', 'error');
                }
            }

            if (enableAudioBtn) {
                updateAudioToggle();
                ensureAudioContext();
                if (speechSupported) {
                    const warmup = new SpeechSynthesisUtterance('Audio enabled.');
                    warmup.volume = 0;
                    window.speechSynthesis.cancel();
                    window.speechSynthesis.speak(warmup);
                }

                enableAudioBtn.addEventListener('click', function () {
                    audioEnabled = !audioEnabled;
                    updateAudioToggle();
                    if (audioEnabled) {
                        ensureAudioContext();
                        if (speechSupported) {
                            const warm = new SpeechSynthesisUtterance('Audio enabled.');
                            warm.volume = 0;
                            window.speechSynthesis.cancel();
                            window.speechSynthesis.speak(warm);
                        }
                    } else if (speechSupported) {
                        window.speechSynthesis.cancel();
                    }
                });
            }

            fetchBoard();
            refreshTimer = setInterval(fetchBoard, 4000);

            window.addEventListener('beforeunload', function () {
                if (refreshTimer) {
                    clearInterval(refreshTimer);
                }
                if (speechSupported) {
                    window.speechSynthesis.cancel();
                }
                if (audioContext && typeof audioContext.close === 'function') {
                    audioContext.close();
                    audioContext = null;
                    doorbellBuffer = null;
                    doorbellLoading = null;
                }
            });
        })();
    </script>
</body>
</html>
