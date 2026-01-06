<?php
function queue_board_styles()
{
    global $baseUrl;
    $stylePath = __DIR__ . '/../assets/css/queue_board.css';
    $styleVersion = is_file($stylePath) ? (string)filemtime($stylePath) : '';
    $href = rtrim((string)$baseUrl, '/') . '/assets/css/queue_board.css' . ($styleVersion !== '' ? ('?v=' . rawurlencode($styleVersion)) : '');
    return '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
}

function queue_board_scripts($role)
{
    global $baseUrl;
    $safeRole = htmlspecialchars((string)$role, ENT_QUOTES, 'UTF-8');
    $scriptPath = __DIR__ . '/../assets/js/queue_board.js';
    $scriptVersion = is_file($scriptPath) ? (string)filemtime($scriptPath) : '';
    $scriptSrc = rtrim((string)$baseUrl, '/') . '/assets/js/queue_board.js' . ($scriptVersion !== '' ? ('?v=' . rawurlencode($scriptVersion)) : '');
    $config = '<script>window.QueueBoardConfig = { role: "' . $safeRole . '" };</script>';
    return $config . "\n" . '<script src="' . htmlspecialchars($scriptSrc, ENT_QUOTES, 'UTF-8') . '"></script>';
}
