<?php
/**
 * VaultOTP
 * Author: ItNexBD
 * Website: https://itnexbd.com
 * Source: https://github.com/NecharUddin
 */

declare(strict_types=1);

const VAULTOTP_VERSION = 'v-1.0';
const SESSION_TIMEOUT = 900;
const MAX_LOGIN_ATTEMPTS = 8;
const LOGIN_WINDOW = 900;

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    require_once $local;
} elseif (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'setup.php') {
    header('Location: setup.php');
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $secure,
        'httponly' => true, 'samesite' => 'Strict'
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
