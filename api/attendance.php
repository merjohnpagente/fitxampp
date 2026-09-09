<?php
header('Content-Type: application/json');
ini_set('display_errors',0); ini_set('display_startup_errors',0);
$__db=null; foreach (['/../includes/db.php','/../db.php','/../../includes/db.php','/../includes/db.php'] as $p){ if(file_exists(__DIR__.$p)){ require_once __DIR__.$p; $__db=true; break; } } if(!$__db){ header('Content-Type: application/json'); http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Backend missing: includes/db.php not found at '. __DIR__ . '. Please copy latest GitHub files to htdocs (git pull)']); exit; }
$pdo=getDB();
$method=$_SERVER['REQUEST_METHOD'];
$input=json_decode(file_get_contents('php://input'),true)??[];

if($method==='GET'){
    $where="1=1"; $params=[];
    if(!empty($_GET['member_id'])){ $where.=" AND member_id=?"; $params[]=$_GET['member_id']; }
    if(!empty($_GET['date'])){ $where.=" AND date=?"; $params[]=$_GET['date']; }
    $stmt=$pdo->prepare("SELECT * FROM attendance WHERE $where ORDER BY date DESC, check_in_ts DESC");
    $stmt->execute($params);
    json_ok(['attendance'=>$stmt->fetchAll()]);
}
if($method==='POST'){
    $user=$_SESSION['user']??null;
    if(!$user) json_err('Not authenticated',401);
    $action=$input['action']??'checkin';
    if($action==='checkin'){
        $memberId=sanitize_text($input['memberId']??$input['member_id']??'');
        $source=sanitize_text($input['source']??'staff');
        if($memberId==='') json_err('Missing memberId',400);
        $stmt=$pdo->prepare("SELECT * FROM members WHERE id=?"); $stmt->execute([$memberId]); $m=$stmt->fetch();
        if(!$m) json_err('Member not found',404);
        if($m['status']==='Archived') json_err('Member archived',400);
        if($m['status']==='pending_payment') json_err('Member pending payment',400);
        // duplicate 30min
        $stmt=$pdo->prepare("SELECT check_in_ts FROM attendance WHERE member_id=? ORDER BY check_in_ts DESC LIMIT 1");
        $stmt->execute([$memberId]); $last=$stmt->fetch();
        if($last && $last['check_in_ts'] && (time()*1000 - (int)$last['check_in_ts'] < 30*60*1000)){
            json_err('Duplicate scan: already checked in within 30 minutes',409);
        }
        // QR token verification if provided
        if(!empty($input['qrToken'])){
            $token=sanitize_text($input['qrToken']);
            $parts=explode('.',$token);
            if(count($parts)!==4||$parts[0]!=='FCG') json_err('Invalid QR token',400);
            $mid=$parts[1]; $dateNum=$parts[2]; $sig=$parts[3];
            if(!preg_match('/^\d{8}$/',$dateNum)) json_err('Invalid QR date',400);
            $dateStr=substr($dateNum,0,4).'-'.substr($dateNum,4,2).'-'.substr($dateNum,6,2);
            $secret=get_qr_secret($pdo);
            // need member nonce
            $stmt=$pdo->prepare("SELECT qr_nonce FROM members WHERE id=?"); $stmt->execute([$mid]); $nonce=$stmt->fetchColumn();
            if(!$nonce){ $nonce=substr(hash('sha256',$mid.'|'.time().'-'.rand()),0,8); $pdo->prepare("UPDATE members SET qr_nonce=? WHERE id=?")->execute([$nonce,$mid]); }
            $expected=substr(hash('sha256',$mid.'|'.$dateStr.'|'.$nonce.'|'.$secret),0,16);
            if($sig!==$expected) json_err('QR signature mismatch',400);
            if($mid!==$memberId) json_err('QR member mismatch',400);
            if($dateStr !== date('Y-m-d')) json_err('QR expired (not today)',400);
        }
        $id=gen_uid('ATT');
        $now=time();
        $pdo->prepare("INSERT INTO attendance (id, member_id, member_name, date, time, check_in, check_in_ts, source, recorded_by, scanned_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$id,$memberId,$m['name'],date('Y-m-d'),date('h:i A'),date('h:i A'),$now*1000,$source,$user['name']??'System',$user['name']??'System']);
        json_ok(['id'=>$id]);
    }
    if($action==='checkout'){
        $id=sanitize_text($input['id']??'');
        $now=time();
        $pdo->prepare("UPDATE attendance SET check_out=?, check_out_ts=? WHERE id=?")->execute([date('h:i A'),$now*1000,$id]);
        json_ok(['msg'=>'Checked out']);
    }
    json_err('Unknown attendance action',400);
}


