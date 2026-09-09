<?php
// api/members.php â€” list, get, create, update, archive, check-in
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// GET: list or single
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT m.*, p.name as plan_name, p.price as plan_price FROM members m LEFT JOIN plans p ON m.plan_id=p.id WHERE m.id=?");
        $stmt->execute([$_GET['id']]);
        $row = $stmt->fetch();
        if (!$row) json_err('Member not found', 404);
        json_ok(['member' => $row]);
    }
    // list with filters
    $where = "1=1";
    $params = [];
    if (!empty($_GET['status'])) { $where .= " AND m.status=?"; $params[] = $_GET['status']; }
    if (!empty($_GET['search'])) {
        $where .= " AND (m.name LIKE ? OR m.id LIKE ? OR m.contact LIKE ?)";
        $s = '%' . $_GET['search'] . '%';
        $params[] = $s; $params[] = $s; $params[] = $s;
    }
    if (!empty($_GET['exclude_archived'])) { $where .= " AND m.status != 'Archived'"; }
    $sql = "SELECT m.*, p.name as plan_name, p.price as plan_price FROM members m LEFT JOIN plans p ON m.plan_id=p.id WHERE $where ORDER BY m.created_at DESC, m.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_ok(['members' => $stmt->fetchAll()]);
}

// POST: create or update or action
if ($method === 'POST') {
    $action = $input['action'] ?? $_GET['action'] ?? 'create';
    $user = $_SESSION['user'] ?? null;

    if ($action === 'create') {
        if (!$user || !in_array($user['role'] ?? '', ['admin','staff'], true)) json_err('Forbidden', 403);
        $name = sanitize_text($input['name'] ?? '');
        $contact = sanitize_text($input['contact'] ?? '');
        $planId = sanitize_text($input['planId'] ?? $input['plan_id'] ?? '');
        if ($name===''||$contact===''||$planId==='') json_err('Missing required fields', 400);
        $stmt = $pdo->prepare("SELECT * FROM plans WHERE id=?");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();
        if (!$plan) json_err('Invalid plan', 400);

        $id = gen_member_id($pdo);
        $start = $input['startDate'] ?? $input['start_date'] ?? date('Y-m-d');
        $duration = (int)$plan['duration'];
        $expiry = date('Y-m-d', strtotime($start . " +{$duration} months"));

        $stmt = $pdo->prepare("INSERT INTO members (id, name, contact, age, sex, dob, plan_id, start_date, expiry_date, plan_start, address, ec_name, ec_num, notes, status, created_at, created_by, created_by_username, created_by_role, bg_check_status, avatar) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $id, $name, $contact,
            sanitize_text($input['age'] ?? ''), sanitize_text($input['sex'] ?? ''), $input['dob'] ?? null,
            $planId, $start, $expiry, $start,
            sanitize_text($input['address'] ?? ''), sanitize_text($input['ecName'] ?? $input['ec_name'] ?? ''), sanitize_text($input['ecNum'] ?? $input['ec_num'] ?? ''), sanitize_text($input['notes'] ?? ''),
            'Active', date('Y-m-d'), $user['name'], $user['username'], $user['role'], 'Cleared',
            $input['avatar'] ?? null
        ]);
        // auto payment record for initial enrollment?
        // activity log
        $pdo->prepare("INSERT INTO activity_log (id, action, category, detail, extra, by_name, by_username, by_role, at) VALUES (?,?,?,?,?,?,?,?,NOW())")
            ->execute([gen_uid('ACT'), 'Added', 'Member', $name, 'ID: '.$id.' | Plan: '.$plan['name'], $user['name'], $user['username'], $user['role']]);
        json_ok(['id' => $id, 'member' => ['id'=>$id]]);
    }

    if ($action === 'update') {
        if (!$user || !in_array($user['role'] ?? '', ['admin','staff'], true)) json_err('Forbidden', 403);
        $id = sanitize_text($input['id'] ?? '');
        if ($id==='') json_err('Missing id', 400);
        $fields = ['name','contact','age','sex','address','ec_name','ec_num','notes','status','plan_id','start_date','expiry_date','avatar','bg_check_status','bg_check_notes'];
        // map JS camelCase to snake
        $map = ['ecName'=>'ec_name','ecNum'=>'ec_num','planId'=>'plan_id','startDate'=>'start_date','expiryDate'=>'expiry_date','bgCheckStatus'=>'bg_check_status','bgCheckNotes'=>'bg_check_notes'];
        foreach ($map as $js=>$db) { if (isset($input[$js])) $input[$db] = $input[$js]; }
        $sets = []; $params = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $input)) { $sets[] = "$f=?"; $params[] = sanitize_text($input[$f]); }
        }
        if (empty($sets)) json_err('Nothing to update', 400);
        $params[] = $id;
        $pdo->prepare("UPDATE members SET ".implode(', ', $sets)." WHERE id=?")->execute($params);
        json_ok(['msg'=>'Updated']);
    }

    if ($action === 'archive') {
        if (!$user || !in_array($user['role'] ?? '', ['admin','staff'], true)) json_err('Forbidden', 403);
        $id = sanitize_text($input['id'] ?? '');
        $pdo->prepare("UPDATE members SET status='Archived' WHERE id=?")->execute([$id]);
        json_ok(['msg'=>'Archived']);
    }

    if ($action === 'checkin') {
        if (!$user) json_err('Not authenticated', 401);
        $memberId = sanitize_text($input['memberId'] ?? $input['member_id'] ?? '');
        if ($memberId==='') json_err('Missing memberId', 400);
        // verify member exists and not archived/pending
        $stmt = $pdo->prepare("SELECT * FROM members WHERE id=?");
        $stmt->execute([$memberId]);
        $m = $stmt->fetch();
        if (!$m) json_err('Member not found', 404);
        if ($m['status']==='Archived') json_err('Member archived', 400);
        if ($m['status']==='pending_payment') json_err('Member pending payment', 400);
        // duplicate check 30min
        $stmt = $pdo->prepare("SELECT check_in_ts FROM attendance WHERE member_id=? ORDER BY check_in_ts DESC LIMIT 1");
        $stmt->execute([$memberId]);
        $last = $stmt->fetch();
        if ($last && $last['check_in_ts'] && (time()*1000 - (int)$last['check_in_ts'] < 30*60*1000)) {
            json_err('Duplicate scan: member already checked in within 30 minutes', 409);
        }
        $id = gen_uid('ATT');
        $now = time();
        $pdo->prepare("INSERT INTO attendance (id, member_id, member_name, date, time, check_in, check_in_ts, source, recorded_by, scanned_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
            ->execute([$id, $memberId, $m['name'], date('Y-m-d'), date('h:i A'), date('h:i A'), $now*1000, $input['source'] ?? 'staff', $user['name'] ?? 'System', $user['name'] ?? 'System']);
        json_ok(['id'=>$id]);
    }

    if ($action === 'confirm_payment') {
        // confirm pending_payment -> Active (front desk)
        if (!$user || !in_array($user['role'] ?? '', ['admin','staff'], true)) json_err('Forbidden', 403);
        $memberId = sanitize_text($input['memberId'] ?? $input['member_id'] ?? '');
        $planId = sanitize_text($input['planId'] ?? $input['plan_id'] ?? '');
        $amount = $input['amount'] ?? 0;
        $method = sanitize_text($input['method'] ?? 'Cash');
        $stmt = $pdo->prepare("SELECT * FROM members WHERE id=?");
        $stmt->execute([$memberId]);
        $m = $stmt->fetch();
        if (!$m) json_err('Member not found', 404);
        $stmt = $pdo->prepare("SELECT * FROM plans WHERE id=?"); $stmt->execute([$planId]); $plan = $stmt->fetch();
        if (!$plan) json_err('Invalid plan', 400);
        $start = date('Y-m-d');
        $expiry = date('Y-m-d', strtotime($start . " +".(int)$plan['duration']." months"));
        $pdo->prepare("UPDATE members SET plan_id=?, start_date=?, expiry_date=?, plan_start=?, status='Active' WHERE id=?")
            ->execute([$planId, $start, $expiry, $start, $memberId]);
        // payment
        $payId = gen_payment_id($pdo);
        $pdo->prepare("INSERT INTO payments (id, member_id, member_name, plan_id, plan_name, amount, date, new_expiry, method, recorded_by, recorded_by_username, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$payId, $memberId, $m['name'], $planId, $plan['name'], $amount, date('Y-m-d'), $expiry, $method, $user['name'], $user['username'], 'Paid', date('Y-m-d')]);
        // resolve notification
        $pdo->prepare("UPDATE notifications SET status='resolved' WHERE member_id=? AND status='open'")->execute([$memberId]);
        // activity
        $pdo->prepare("INSERT INTO activity_log (id, action, category, detail, extra, by_name, by_username, by_role, at) VALUES (?,?,?,?,?,?,?,?,NOW())")
            ->execute([gen_uid('ACT'), 'Confirmed Payment', 'Member', $m['name'], 'Plan: '.$plan['name'].' | Amount: â‚±'.$amount, $user['name'], $user['username'], $user['role']]);
        json_ok(['msg'=>'Payment confirmed', 'expiry'=>$expiry]);
    }

    json_err('Unknown members action', 400);
}

if ($method === 'DELETE') {
    $user = $_SESSION['user'] ?? null;
    if (!$user || $user['role'] !== 'admin') json_err('Forbidden', 403);
    $id = $_GET['id'] ?? $input['id'] ?? '';
    $pdo->prepare("DELETE FROM members WHERE id=?")->execute([$id]);
    json_ok(['msg'=>'Deleted']);
}

