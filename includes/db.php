<?php
// ============================================================
// FITCORE — PDO Database Connection
// ============================================================
require_once __DIR__ . '/config.php';

function getDB() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Friendly error for XAMPP beginners
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false,
            'error' => 'Database connection failed. Did you import database.sql in phpMyAdmin and start MySQL?',
            'details' => $e->getMessage()
        ]);
        exit;
    }
    return $pdo;
}

// Helper: JSON response
function json_ok($data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}
function json_err($msg, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// Helper: require login + role
function require_login() {
    if (empty($_SESSION['user'])) {
        json_err('Not authenticated. Please log in.', 401);
    }
    return $_SESSION['user'];
}
function require_role($roles) {
    $u = require_login();
    $roles = (array)$roles;
    if (!in_array($u['role'], $roles, true)) {
        json_err('Forbidden: insufficient role.', 403);
    }
    return $u;
}

// Helper: sanitize like JS sanitizeText (strip < > control chars)
function sanitize_text($v) {
    $v = (string)$v;
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    $v = str_replace(['<','>'], '', $v);
    return trim($v);
}

// Helper: generate UID like JS uid()
function gen_uid($prefix='') {
    return $prefix . substr(md5(uniqid(mt_rand(), true)), 0, 8) . substr(md5(mt_rand()), 0, 4);
}
function gen_member_id($pdo) {
    $n = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status != 'Archived'")->fetchColumn();
    return 'MEM-' . str_pad($n + 1, 4, '0', STR_PAD_LEFT);
}
function gen_payment_id($pdo) {
    $n = (int)$pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();
    return 'PAY-' . str_pad($n + 1, 4, '0', STR_PAD_LEFT);
}

// Helper: QR secret (from settings or fallback)
function get_qr_secret($pdo) {
    $row = $pdo->query("SELECT secret FROM settings WHERE id='qr' LIMIT 1")->fetch();
    if ($row && !empty($row['secret'])) return $row['secret'];
    // create if missing
    $secret = substr(hash('sha256', 'fc-secret-' . time() . '-' . mt_rand()), 0, 32);
    $pdo->prepare("INSERT INTO settings (id, secret) VALUES ('qr', ?) ON DUPLICATE KEY UPDATE secret=VALUES(secret)")->execute([$secret]);
    return $secret;
}
function qr_sig($memberId, $dateStr, $nonce, $secret) {
    return substr(hash('sha256', $memberId . '|' . $dateStr . '|' . $nonce . '|' . $secret), 0, 16);
}
