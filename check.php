<?php
// check.php — XAMPP diagnostic page
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>FITCORE — XAMPP Check</title>";
echo "<style>body{font-family:system-ui,Segoe UI,Arial;background:#0a0a0a;color:#fff;padding:32px} .ok{color:#7ffa88} .err{color:#ef4444} .card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:14px;padding:18px;margin:12px 0} a{color:#7ffa88}</style></head><body>";
echo "<h1>FITCORE — XAMPP Diagnostic</h1>";

$checks = [];

// PHP version
$checks[] = ['PHP Version', phpversion(), version_compare(phpversion(), '7.4', '>=') ? 'ok' : 'err'];

// PDO
$checks[] = ['PDO MySQL', extension_loaded('pdo_mysql') ? 'Enabled' : 'Missing', extension_loaded('pdo_mysql') ? 'ok' : 'err'];

// DB connection
try {
    $pdo = getDB();
    $checks[] = ['MySQL Connection', DB_HOST . ' / ' . DB_NAME, 'ok'];
    // tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $checks[] = ['Tables Found', count($tables) . ' (' . implode(', ', $tables) . ')', count($tables) >= 10 ? 'ok' : 'err'];
    // counts
    $u = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $m = $pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();
    $p = $pdo->query("SELECT COUNT(*) FROM plans")->fetchColumn();
    $checks[] = ['Seed Data', "users=$u, members=$m, plans=$p", ($u>=3 && $m>=6 && $p>=3) ? 'ok' : 'err'];
    // sample login test (hash verify)
    $stmt = $pdo->prepare("SELECT username, password_hash FROM users WHERE username='admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    $hashOk = $admin && password_verify('admin123', $admin['password_hash']) ? 'ok' : 'err';
    $checks[] = ['Admin Login (admin/admin123)', $admin ? 'hash verify: '.($hashOk==='ok'?'PASS':'FAIL') : 'admin not found', $hashOk];
} catch (Exception $e) {
    $checks[] = ['MySQL Connection', $e->getMessage(), 'err'];
}

foreach ($checks as $c) {
    $cls = $c[2] === 'ok' ? 'ok' : 'err';
    echo "<div class='card'><strong>{$c[0]}:</strong> <span class='$cls'>{$c[1]}</span></div>";
}

echo "<div class='card'><h3>Next Steps</h3>";
echo "<ol>";
echo "<li>If any check is <span class='err'>red</span>, fix it: start MySQL in XAMPP, import <code>database.sql</code> in <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a>.</li>";
echo "<li>Project folder must be at <code>C:/xampp/htdocs/fitcore/</code> (or adjust APP_URL in config.php).</li>";
echo "<li>Visit <a href='index.html'>index.html</a> via <code>http://localhost/fitcore/</code> — you should see “● MySQL Connected” badge at bottom-right.</li>";
echo "<li>Default logins: <code>admin / admin123</code> (Admin), <code>staff / staff123</code> (Staff), <code>trainer / trainer123</code> (Trainer), <code>nicole / member123</code> (Member).</li>";
echo "<li>If you open via <code>file://</code> double-click, it still works offline (localStorage demo mode).</li>";
echo "</ol></div>";

echo "<p><a href='index.html'>→ Go to FITCORE</a> &nbsp; | &nbsp; <a href='sql/database.sql' download>Download database.sql</a></p>";
echo "</body></html>";
