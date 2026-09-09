<?php
// ============================================================
// FITCORE GYM — XAMPP Config
// ============================================================
// 1. Edit these if your MySQL credentials differ (default XAMPP is root / no password)
// 2. This file is included by db.php and all api/*.php
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'fitcore_gym');
define('DB_USER', 'root');
define('DB_PASS', '');              // XAMPP default: empty password
define('DB_CHARSET', 'utf8mb4');

// App
define('APP_NAME', 'FITCORE GYM');
define('APP_URL', 'http://localhost/fitcore'); // adjust if folder name differs

// Security — change in production
define('QR_SECRET_FALLBACK', 'fitcore-qr-secret-2026');

// --- Ensure API always returns JSON, never HTML <br /> errors ---
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Global handler: any uncaught error/exception returns JSON instead of HTML <br />
set_exception_handler(function($e){
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'error'=>'Server error: '.$e->getMessage()]);
    exit;
});
set_error_handler(function($errno,$errstr,$errfile,$errline){
    // Convert warnings/notices to exception so they become JSON
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
