<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
$pdo=getDB();
$method=$_SERVER['REQUEST_METHOD'];
$input=json_decode(file_get_contents('php://input'),true)??[];

if($method==='GET'){
    $walkin = $pdo->query("SELECT fee FROM settings WHERE id='walkin'")->fetchColumn();
    $qr = $pdo->query("SELECT secret FROM settings WHERE id='qr'")->fetchColumn();
    json_ok(['walkin_fee'=> $walkin ? (float)$walkin : 100, 'qr_secret'=> $qr]);
}
if($method==='POST'){
    $user=$_SESSION['user']??null;
    if(!$user||!in_array($user['role']??'',['admin','staff'],true)) json_err('Forbidden',403);
    if(isset($input['walkin_fee'])){
        $fee=(float)$input['walkin_fee'];
        $pdo->prepare("INSERT INTO settings (id, fee) VALUES ('walkin',?) ON DUPLICATE KEY UPDATE fee=VALUES(fee)")->execute([$fee]);
    }
    json_ok(['msg'=>'Saved']);
}
