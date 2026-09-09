<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    $where="1=1"; $params=[];
    if (!empty($_GET['date'])) { $where.=" AND date=?"; $params[]=$_GET['date']; }
    $stmt=$pdo->prepare("SELECT * FROM walkins WHERE $where ORDER BY date DESC, id DESC");
    $stmt->execute($params);
    $rows=$stmt->fetchAll();
    // also return fee
    $fee = $pdo->query("SELECT fee FROM settings WHERE id='walkin'")->fetchColumn();
    json_ok(['walkins'=>$rows, 'fee'=> $fee ? (float)$fee : 100]);
}
if ($method === 'POST') {
    $user=$_SESSION['user']??null;
    if(!$user||!in_array($user['role']??'',['admin','staff'],true)) json_err('Forbidden',403);
    $name=sanitize_text($input['name']??'');
    $contact=sanitize_text($input['contact']??'');
    $fee = $pdo->query("SELECT fee FROM settings WHERE id='walkin'")->fetchColumn();
    $fee = $fee ? (float)$fee : 100;
    if(isset($input['fee'])) $fee=(float)$input['fee'];
    $date=$input['date']??date('Y-m-d');
    if($name==='') json_err('Name required',400);
    $id=gen_uid('WKI');
    $pdo->prepare("INSERT INTO walkins (id, name, contact, fee, date, time, recorded_by, recorded_by_username, notes) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([$id,$name,$contact,$fee,$date,date('h:i A'),$user['name'],$user['username'], sanitize_text($input['notes']??'')]);
    $pdo->prepare("INSERT INTO activity_log (id, action, category, detail, extra, by_name, by_username, by_role, at) VALUES (?,?,?,?,?,?,?,?,NOW())")
        ->execute([gen_uid('ACT'),'Walk-In','WalkIn',$name,'Fee: ₱'.$fee.' | Date: '.$date,$user['name'],$user['username'],$user['role']]);
    json_ok(['id'=>$id]);
}
if ($method === 'DELETE') {
    $user=$_SESSION['user']??null;
    if(!$user) json_err('Forbidden',403);
    $id=$_GET['id']??$input['id']??'';
    $pdo->prepare("DELETE FROM walkins WHERE id=?")->execute([$id]);
    json_ok(['msg'=>'Deleted']);
}
