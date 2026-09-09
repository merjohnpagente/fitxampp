<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
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
