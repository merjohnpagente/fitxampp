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

// Session
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
