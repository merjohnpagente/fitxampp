<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
$pdo=getDB();
$method=$_SERVER['REQUEST_METHOD'];
$input=json_decode(file_get_contents('php://input'),true)??[];

if($method==='GET'){
    $user=require_role(['admin']);
    $stmt=$pdo->query("SELECT id, name, username, email, contact, role, status, avatar, coach_name, specializations, available_days, available_from, available_to, bio, created_at FROM users ORDER BY created_at DESC");
    $rows=$stmt->fetchAll();
    foreach($rows as &$r){
        $r['specializations']=$r['specializations']?json_decode($r['specializations'],true):[];
        $r['available_days']=$r['available_days']?json_decode($r['available_days'],true):[];
    }
    json_ok(['users'=>$rows]);
}
if($method==='POST'){
    $user=require_role(['admin']);
    $action=$input['action']??'';
    if($action==='approve'){
        $id=sanitize_text($input['id']??''); $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$id]);
        json_ok(['msg'=>'Approved']);
    }
    if($action==='lock'){
        $id=sanitize_text($input['id']??''); $pdo->prepare("UPDATE users SET status='locked' WHERE id=?")->execute([$id]);
        json_ok(['msg'=>'Locked']);
    }
    if($action==='unlock'){
        $id=sanitize_text($input['id']??''); $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$id]);
        json_ok(['msg'=>'Unlocked']);
    }
    if($action==='update'){
        $id=sanitize_text($input['id']??''); if($id==='') json_err('Missing id',400);
        $allowed=['name','contact','role','status','coach_name','available_from','available_to','bio'];
        $sets=[]; $params=[];
        foreach($allowed as $f){ if(isset($input[$f])){ $sets[]="$f=?"; $params[] = sanitize_text($input[$f]); } }
        if(isset($input['specializations'])){ $sets[]="specializations=?"; $params[]=json_encode($input['specializations']); }
        if(isset($input['available_days'])){ $sets[]="available_days=?"; $params[]=json_encode($input['available_days']); }
        if(empty($sets)) json_err('Nothing to update',400);
        $params[]=$id;
        $pdo->prepare("UPDATE users SET ".implode(',',$sets)." WHERE id=?")->execute($params);
        json_ok(['msg'=>'Updated']);
    }
    if($action==='delete'){
        $id=sanitize_text($input['id']??''); $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
        json_ok(['msg'=>'Deleted']);
    }
    if($action==='reset_password'){
        $id=sanitize_text($input['id']??''); $new=sanitize_text($input['password']??'');
        if(strlen($new)<6) json_err('Password too short',400);
        $hash=password_hash($new,PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash,$id]);
        json_ok(['msg'=>'Password reset']);
    }
    json_err('Unknown users action',400);
}
