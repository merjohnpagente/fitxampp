<?php
header('Content-Type: application/json');
ini_set('display_errors',0); ini_set('display_startup_errors',0);
$__db=null; foreach (['/../includes/db.php','/../db.php','/../../includes/db.php','/../includes/db.php'] as $p){ if(file_exists(__DIR__.$p)){ require_once __DIR__.$p; $__db=true; break; } } if(!$__db){ header('Content-Type: application/json'); http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Backend missing: includes/db.php not found at '. __DIR__ . '. Please copy latest GitHub files to htdocs (git pull)']); exit; }
$pdo=getDB();
$method=$_SERVER['REQUEST_METHOD'];
$input=json_decode(file_get_contents('php://input'),true)??[];

if($method==='GET'){
    $memberId=$_GET['member_id']??$_GET['memberId']??'';
    if($memberId!==''){
        $stmt=$pdo->prepare("SELECT * FROM messages WHERE member_id=? ORDER BY ts ASC");
        $stmt->execute([$memberId]);
        json_ok(['messages'=>$stmt->fetchAll()]);
    }
    // all threads
    $stmt=$pdo->query("SELECT * FROM messages ORDER BY ts DESC");
    $all=$stmt->fetchAll();
    json_ok(['messages'=>$all]);
}
if($method==='POST'){
    $action=$input['action']??'send';
    if($action==='send'){
        $memberId=sanitize_text($input['memberId']??$input['member_id']??'');
        $text=sanitize_text($input['text']??'');
        $direction=sanitize_text($input['direction']??'in');
        if($memberId===''||$text==='') json_err('Missing fields',400);
        $stmt=$pdo->prepare("SELECT name FROM members WHERE id=?"); $stmt->execute([$memberId]); $m=$stmt->fetch();
        $memberName=$m['name']??'Member';
        $id=gen_uid('MSG');
        $pdo->prepare("INSERT INTO messages (id, member_id, member_name, text, time, date, ts, direction, read_flag, read_by_member) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$id,$memberId,$memberName,$text,date('h:i A'),date('Y-m-d'),time()*1000,$direction, $direction==='in'?0:1, $direction==='out'?0:1]);
        json_ok(['id'=>$id]);
    }
    if($action==='mark_read'){
        $memberId=sanitize_text($input['memberId']??$input['member_id']??'');
        $pdo->prepare("UPDATE messages SET read_flag=1 WHERE member_id=? AND direction='in'")->execute([$memberId]);
        $pdo->prepare("UPDATE messages SET read_by_member=1 WHERE member_id=? AND direction='out'")->execute([$memberId]);
        json_ok(['msg'=>'Marked read']);
    }
    if($action==='delete'){
        $id=sanitize_text($input['id']??''); $pdo->prepare("DELETE FROM messages WHERE id=?")->execute([$id]); json_ok(['msg'=>'Deleted']);
    }
    json_err('Unknown messages action',400);
}
if($method==='DELETE'){
    $id=$_GET['id']??''; $pdo->prepare("DELETE FROM messages WHERE id=?")->execute([$id]); json_ok(['msg'=>'Deleted']);
}


