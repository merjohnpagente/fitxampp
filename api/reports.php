<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
$pdo=getDB();

// GET: aggregated reports
$type=$_GET['type']??'summary';
if($type==='summary'){
    $members = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status!='Archived'")->fetchColumn();
    $revenue = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
    $walkinRev = (float)$pdo->query("SELECT COALESCE(SUM(fee),0) FROM walkins")->fetchColumn();
    $attendanceToday = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE date=CURDATE()")->fetchColumn();
    $expiring = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE status='Expiring Soon' AND status!='Archived'")->fetchColumn();
    json_ok(['summary'=>['members'=>$members,'revenue'=>$revenue,'walkinRevenue'=>$walkinRev,'attendanceToday'=>$attendanceToday,'expiring'=>$expiring]]);
}
if($type==='revenue'){
    $stmt=$pdo->query("SELECT date, SUM(amount) as total FROM payments GROUP BY date ORDER BY date ASC");
    json_ok(['revenue'=>$stmt->fetchAll()]);
}
if($type==='attendance'){
    $stmt=$pdo->query("SELECT date, COUNT(*) as cnt FROM attendance GROUP BY date ORDER BY date ASC LIMIT 30");
    json_ok(['attendance'=>$stmt->fetchAll()]);
}
if($type==='activity'){
    $stmt=$pdo->query("SELECT * FROM activity_log ORDER BY at DESC LIMIT 100");
    json_ok(['activity'=>$stmt->fetchAll()]);
}
json_err('Unknown report type',400);
