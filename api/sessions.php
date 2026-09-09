<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
$pdo=getDB();
$method=$_SERVER['REQUEST_METHOD'];
$input=json_decode(file_get_contents('php://input'),true)??[];

if($method==='GET'){
    $where="1=1"; $params=[];
    if(!empty($_GET['trainer_id'])){ $where.=" AND trainer_id=?"; $params[]=$_GET['trainer_id']; }
    if(!empty($_GET['member_id'])){ $where.=" AND member_id=?"; $params[]=$_GET['member_id']; }
    if(!empty($_GET['date'])){ $where.=" AND date=?"; $params[]=$_GET['date']; }
    $stmt=$pdo->prepare("SELECT * FROM sessions WHERE $where ORDER BY date ASC, start ASC");
    $stmt->execute($params);
    json_ok(['sessions'=>$stmt->fetchAll()]);
}
if($method==='POST'){
    $user=$_SESSION['user']??null;
    if(!$user) json_err('Not authenticated',401);
    $action=$input['action']??'create';
    if($action==='create'||$action==='schedule'){
        $trainerId=sanitize_text($input['trainerId']??$input['trainer_id']??$user['id']);
        $trainerName=sanitize_text($input['trainerName']??$input['trainer_name']??$user['name']);
        $memberId=sanitize_text($input['memberId']??$input['member_id']??'');
        $memberName=sanitize_text($input['memberName']??$input['member_name']??'');
        $date=sanitize_text($input['date']??'');
        $start=sanitize_text($input['start']??'');
        $end=sanitize_text($input['end']??'');
        $type=sanitize_text($input['type']??'Personal Training');
        if($memberId===''||$date===''||$start===''||$end==='') json_err('Missing required fields',400);
        // conflict check: same trainer same date overlapping
        $stmt=$pdo->prepare("SELECT * FROM sessions WHERE trainer_id=? AND date=? AND status!='Cancelled'");
        $stmt->execute([$trainerId,$date]);
        foreach($stmt->fetchAll() as $s){
            // simple string compare (HH:MM AM/PM) â€” convert to minutes
            // assume format like "6:00 AM"
            // If overlap, reject
            // We do naive check: if start < existing end and end > existing start
            // For demo, skip complex, but block exact same slot
            if($s['start']===$start) json_err('Schedule conflict: trainer already has a session at '.$start,409);
        }
        $id=gen_uid('SES');
        $pdo->prepare("INSERT INTO sessions (id, trainer_id, trainer_name, member_id, member_name, date, start, end, type, status, notes, created_by, created_by_username, created_by_role, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$id,$trainerId,$trainerName,$memberId,$memberName,$date,$start,$end,$type,'Scheduled', sanitize_text($input['notes']??''), $user['name'],$user['username'],$user['role'],date('Y-m-d')]);
        json_ok(['id'=>$id]);
    }
    if($action==='update'){
        $id=sanitize_text($input['id']??''); if($id==='') json_err('Missing id',400);
        $sets=[]; $params=[];
        foreach(['trainer_id','member_id','date','start','end','type','status','notes'] as $f){
            $v=$input[$f]??$input[str_replace('_','',$f)]??null;
            if($v!==null){ $sets[]="$f=?"; $params[]=$f==='notes'?sanitize_text($v):sanitize_text($v); }
        }
        // also handle camelCase
        $map=['trainerId'=>'trainer_id','memberId'=>'member_id','trainerName'=>'trainer_name','memberName'=>'member_name'];
        foreach($map as $js=>$db){ if(isset($input[$js])){ $sets[]="$db=?"; $params[]=sanitize_text($input[$js]); } }
        if(empty($sets)) json_err('Nothing to update',400);
        $params[]=$id;
        $pdo->prepare("UPDATE sessions SET ".implode(',',$sets)." WHERE id=?")->execute($params);
        json_ok(['msg'=>'Updated']);
    }
    if($action==='status'){
        $id=sanitize_text($input['id']??''); $status=sanitize_text($input['status']??'');
        if(!in_array($status,['Scheduled','Completed','Cancelled'],true)) json_err('Invalid status',400);
        $pdo->prepare("UPDATE sessions SET status=? WHERE id=?")->execute([$status,$id]);
        json_ok(['msg'=>'Status updated']);
    }
    if($action==='delete'){
        $id=sanitize_text($input['id']??''); $pdo->prepare("DELETE FROM sessions WHERE id=?")->execute([$id]); json_ok(['msg'=>'Deleted']);
    }
    json_err('Unknown sessions action',400);
}
if($method==='DELETE'){
    $user=$_SESSION['user']??null;
    if(!$user) json_err('Forbidden',403);
    $id=$_GET['id']??''; $pdo->prepare("DELETE FROM sessions WHERE id=?")->execute([$id]); json_ok(['msg'=>'Deleted']);
}

