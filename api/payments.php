<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    $where = "1=1"; $params=[];
    if (!empty($_GET['member_id'])) { $where.=" AND member_id=?"; $params[]=$_GET['member_id']; }
    if (!empty($_GET['search'])) { $where.=" AND (member_name LIKE ? OR plan_name LIKE ?)"; $s='%'.$_GET['search'].'%'; $params[]=$s; $params[]=$s; }
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE $where ORDER BY date DESC, created_at DESC");
    $stmt->execute($params);
    json_ok(['payments'=>$stmt->fetchAll()]);
}
if ($method === 'POST') {
    $user = $_SESSION['user'] ?? null;
    if (!$user || !in_array($user['role'] ?? '', ['admin','staff'], true)) json_err('Forbidden',403);
    $memberId = sanitize_text($input['memberId'] ?? $input['member_id'] ?? '');
    $planId = sanitize_text($input['planId'] ?? $input['plan_id'] ?? '');
    $amount = (float)($input['amount'] ?? 0);
    $methodPay = sanitize_text($input['method'] ?? 'Cash');
    $date = $input['date'] ?? date('Y-m-d');
    $notes = sanitize_text($input['notes'] ?? '');
    if ($memberId===''||$planId===''||$amount<=0) json_err('Missing required fields',400);
    $stmt=$pdo->prepare("SELECT name FROM members WHERE id=?"); $stmt->execute([$memberId]); $m=$stmt->fetch();
    if(!$m) json_err('Member not found',404);
    $stmt=$pdo->prepare("SELECT * FROM plans WHERE id=?"); $stmt->execute([$planId]); $plan=$stmt->fetch();
    if(!$plan) json_err('Plan not found',404);
    // compute new expiry: from current expiry if still active, else from payment date
    $stmt=$pdo->prepare("SELECT expiry_date FROM members WHERE id=?"); $stmt->execute([$memberId]); $cur=$stmt->fetchColumn();
    $base = $date;
    if ($cur && strtotime($cur) > strtotime($date)) $base = $cur;
    $newExpiry = date('Y-m-d', strtotime($base . " +".(int)$plan['duration']." months"));
    $id = gen_payment_id($pdo);
    $pdo->prepare("INSERT INTO payments (id, member_id, member_name, plan_id, plan_name, amount, date, new_expiry, method, notes, recorded_by, recorded_by_username, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$id, $memberId, $m['name'], $planId, $plan['name'], $amount, $date, $newExpiry, $methodPay, $notes, $user['name'], $user['username'], 'Paid', date('Y-m-d')]);
    // update member expiry
    $pdo->prepare("UPDATE members SET expiry_date=?, plan_id=?, status='Active' WHERE id=?")->execute([$newExpiry, $planId, $memberId]);
    $pdo->prepare("UPDATE notifications SET status='resolved' WHERE member_id=? AND status='open'")->execute([$memberId]);
    $pdo->prepare("INSERT INTO activity_log (id, action, category, detail, extra, by_name, by_username, by_role, at) VALUES (?,?,?,?,?,?,?,?,NOW())")
        ->execute([gen_uid('ACT'), 'Payment', 'Billing', $m['name'], 'Plan: '.$plan['name'].' | ₱'.$amount.' | Expiry: '.$newExpiry, $user['name'], $user['username'], $user['role']]);
    json_ok(['id'=>$id, 'new_expiry'=>$newExpiry]);
}
