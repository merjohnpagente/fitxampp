<?php
header('Content-Type: application/json');
ini_set('display_errors',0); ini_set('display_startup_errors',0);
$__db=null; foreach (['/../includes/db.php','/../db.php','/../../includes/db.php','/../includes/db.php'] as $p){ if(file_exists(__DIR__.$p)){ require_once __DIR__.$p; $__db=true; break; } } if(!$__db){ header('Content-Type: application/json'); http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Backend missing: includes/db.php not found at '. __DIR__ . '. Please copy latest GitHub files to htdocs (git pull)']); exit; }
$pdo=getDB();
$method=$_SERVER['REQUEST_METHOD'];

if($method==='GET'){
    $status=$_GET['status']??'open';
    $stmt=$pdo->prepare("SELECT n.*, m.name as member_name, m.contact, p.name as plan_name FROM notifications n LEFT JOIN members m ON n.member_id=m.id LEFT JOIN plans p ON n.plan_id=p.id WHERE n.status=? ORDER BY n.created_at DESC");
    $stmt->execute([$status]);
    json_ok(['notifications'=>$stmt->fetchAll()]);
}
if($method==='POST'){
    $input=json_decode(file_get_contents('php://input'),true)??[];
    $action=$input['action']??'resolve';
    if($action==='resolve'){
        $id=sanitize_text($input['id']??'');
        if($id!=='') $pdo->prepare("UPDATE notifications SET status='resolved' WHERE id=?")->execute([$id]);
        else {
            $mid=sanitize_text($input['memberId']??$input['member_id']??'');
            $pdo->prepare("UPDATE notifications SET status='resolved' WHERE member_id=?")->execute([$mid]);
        }
        json_ok(['msg'=>'Resolved']);
    }
    json_err('Unknown notifications action',400);
}


