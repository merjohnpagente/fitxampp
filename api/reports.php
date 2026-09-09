<?php
header('Content-Type: application/json');
ini_set('display_errors',0); ini_set('display_startup_errors',0);
$__db=null; foreach (['/../includes/db.php','/../db.php','/../../includes/db.php','/../includes/db.php'] as $p){ if(file_exists(__DIR__.$p)){ require_once __DIR__.$p; $__db=true; break; } } if(!$__db){ header('Content-Type: application/json'); http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Backend missing: includes/db.php not found at '. __DIR__ . '. Please copy latest GitHub files to htdocs (git pull)']); exit; }
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


