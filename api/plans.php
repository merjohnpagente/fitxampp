<?php
header('Content-Type: application/json');
ini_set('display_errors',0); ini_set('display_startup_errors',0);
$__db=null; foreach (['/../includes/db.php','/../db.php','/../../includes/db.php','/../includes/db.php'] as $p){ if(file_exists(__DIR__.$p)){ require_once __DIR__.$p; $__db=true; break; } } if(!$__db){ header('Content-Type: application/json'); http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Backend missing: includes/db.php not found at '. __DIR__ . '. Please copy latest GitHub files to htdocs (git pull)']); exit; }
$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM plans ORDER BY price ASC");
    json_ok(['plans' => $stmt->fetchAll()]);
}
if ($method === 'POST') {
    $user = $_SESSION['user'] ?? null;
    if (!$user || !in_array($user['role'] ?? '', ['admin','staff'], true)) json_err('Forbidden',403);
    $action = $input['action'] ?? 'create';
    if ($action === 'create' || $action === 'add') {
        $id = $input['id'] ?? gen_uid('pl');
        $name = sanitize_text($input['name'] ?? '');
        $price = (float)($input['price'] ?? 0);
        $duration = (int)($input['duration'] ?? 1);
        $sessions = sanitize_text($input['sessions'] ?? '8');
        $benefits = sanitize_text($input['benefits'] ?? $input['perks'] ?? '');
        if ($name===''||$price<=0) json_err('Name and price required',400);
        $pdo->prepare("INSERT INTO plans (id, name, price, duration, sessions, benefits, status) VALUES (?,?,?,?,?,?,?)")
            ->execute([$id, $name, $price, $duration, $sessions, $benefits, 'Active']);
        json_ok(['id'=>$id]);
    }
    if ($action === 'update') {
        $id = sanitize_text($input['id'] ?? '');
        if ($id==='') json_err('Missing id',400);
        $fields = ['name','price','duration','sessions','benefits','status'];
        $map = ['perks'=>'benefits'];
        foreach($map as $k=>$v) if(isset($input[$k])) $input[$v]=$input[$k];
        $sets=[]; $params=[];
        foreach($fields as $f){ if(isset($input[$f])){ $sets[]="$f=?"; $params[]=$f==='price'?(float)$input[$f]:sanitize_text($input[$f]); } }
        if(empty($sets)) json_err('Nothing to update',400);
        $params[]=$id;
        $pdo->prepare("UPDATE plans SET ".implode(',',$sets)." WHERE id=?")->execute($params);
        json_ok(['msg'=>'Updated']);
    }
    if ($action === 'delete' || $action === 'archive') {
        $id = sanitize_text($input['id'] ?? '');
        $pdo->prepare("UPDATE plans SET status='Inactive' WHERE id=?")->execute([$id]);
        json_ok(['msg'=>'Archived']);
    }
    json_err('Unknown plans action',400);
}
if ($method === 'DELETE') {
    $user = $_SESSION['user'] ?? null;
    if (!$user || $user['role']!=='admin') json_err('Forbidden',403);
    $id = $_GET['id'] ?? '';
    $pdo->prepare("DELETE FROM plans WHERE id=?")->execute([$id]);
    json_ok(['msg'=>'Deleted']);
}


