<?php
header('Content-Type: application/json');
ini_set('display_errors',0); ini_set('display_startup_errors',0);
$__db=null; foreach (['/../includes/db.php','/../db.php','/../../includes/db.php','/../includes/db.php'] as $p){ if(file_exists(__DIR__.$p)){ require_once __DIR__.$p; $__db=true; break; } } if(!$__db){ header('Content-Type: application/json'); http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Backend missing: includes/db.php not found at '. __DIR__ . '. Please copy latest GitHub files to htdocs (git pull)']); exit; }
$pdo=getDB();
$method=$_SERVER['REQUEST_METHOD'];
$input=json_decode(file_get_contents('php://input'),true)??[];

if($method==='GET'){
    $stmt=$pdo->query("SELECT * FROM announcements ORDER BY date DESC, created_at DESC");
    json_ok(['announcements'=>$stmt->fetchAll()]);
}
if($method==='POST'){
    $user=$_SESSION['user']??null;
    if(!$user||!in_array($user['role']??'',['admin','staff'],true)) json_err('Forbidden',403);
    $action=$input['action']??'create';
    if($action==='create'){
        $title=sanitize_text($input['title']??''); $text=sanitize_text($input['text']??'');
        if($title==='') json_err('Title required',400);
        $id=gen_uid('ANN');
        $pdo->prepare("INSERT INTO announcements (id, type, date, title, text, created_by, created_at, time) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$id, sanitize_text($input['type']??'General'), $input['date']??date('Y-m-d'), $title, $text, $user['name'], date('Y-m-d'), date('h:i A')]);
        json_ok(['id'=>$id]);
    }
    if($action==='delete'){
        $id=sanitize_text($input['id']??''); $pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([$id]); json_ok(['msg'=>'Deleted']);
    }
    json_err('Unknown announcements action',400);
}
if($method==='DELETE'){
    $id=$_GET['id']??''; $pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([$id]); json_ok(['msg'=>'Deleted']);
}


