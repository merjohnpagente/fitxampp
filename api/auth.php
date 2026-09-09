<?php
// api/auth.php â€” login, logout, register, member signup, forgot (demo), session check
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// CORS for local testing (file:// needs fallback, but XAMPP will be http://)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($method === 'OPTIONS') { http_response_code(200); exit; }

$pdo = getDB();

// Helper: rate limit login (mirrors AttemptTracker 15m / 7 attempts)
function login_check_lock($pdo, $username) {
    $stmt = $pdo->prepare("SELECT count, last FROM login_attempts WHERE username=?");
    $stmt->execute([strtolower($username)]);
    $row = $stmt->fetch();
    if (!$row) return 0;
    $elapsed = (time() * 1000) - (int)$row['last'];
    if ($elapsed > 15*60*1000) return 0;
    return (int)$row['count'];
}
function login_register_fail($pdo, $username) {
    $now = time() * 1000;
    $stmt = $pdo->prepare("SELECT count, last FROM login_attempts WHERE username=?");
    $stmt->execute([strtolower($username)]);
    $row = $stmt->fetch();
    $count = 0;
    if ($row) {
        $elapsed = $now - (int)$row['last'];
        $count = ($elapsed < 15*60*1000) ? (int)$row['count'] : 0;
    }
    $count++;
    $pdo->prepare("INSERT INTO login_attempts (username, count, last) VALUES (?,?,?) ON DUPLICATE KEY UPDATE count=VALUES(count), last=VALUES(last)")
        ->execute([strtolower($username), $count, $now]);
    return $count;
}
function login_reset($pdo, $username) {
    $pdo->prepare("DELETE FROM login_attempts WHERE username=?")->execute([strtolower($username)]);
}

// ---- LOGIN ----
if ($action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if ($username === '' || $password === '') json_err('Please fill in all required fields.', 400);
    if (login_check_lock($pdo, $username) >= 7) json_err('Account locked. Too many failed attempts â€” try again in 15 minutes.', 423);

    // 1) try users table
    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username)=LOWER(?) LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user) {
        if ($user['status'] === 'locked') json_err('Account locked. Please contact the administrator.', 403);
        if ($user['status'] === 'pending') json_err('Your account is pending admin approval. Please wait.', 403);
        if (password_verify($password, $user['password_hash'])) {
            // upgrade old $2b$ vs $2y$ ok, but rehash if needed
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$newHash, $user['id']]);
            }
            login_reset($pdo, $username);
            // Decode JSON fields
            $user['specializations'] = $user['specializations'] ? json_decode($user['specializations'], true) : [];
            $user['available_days'] = $user['available_days'] ? json_decode($user['available_days'], true) : [];
            $_SESSION['user'] = $user;
            // log activity
            $pdo->prepare("INSERT INTO activity_log (id, action, category, detail, extra, by_name, by_username, by_role, at) VALUES (?,?,?,?,?,?,?,?,NOW())")
                ->execute([gen_uid('ACT'), 'Login', 'Auth', $user['name'], 'Role: '.$user['role'], $user['name'], $user['username'], $user['role']]);
            json_ok(['user' => $user]);
        } else {
            login_register_fail($pdo, $username);
            json_err('Invalid username or password.', 401);
        }
    }

    // 2) try members table
    $stmt = $pdo->prepare("SELECT * FROM members WHERE LOWER(username)=LOWER(?) LIMIT 1");
    $stmt->execute([$username]);
    $member = $stmt->fetch();
    if ($member) {
        if ($member['status'] === 'Archived') json_err('Your account has been archived. Please contact the front desk.', 403);
        if (!password_verify($password, $member['password_hash'])) {
            login_register_fail($pdo, $username);
            json_err('Invalid username or password.', 401);
        }
        if (password_needs_rehash($member['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE members SET password_hash=? WHERE id=?")->execute([$newHash, $member['id']]);
        }
        login_reset($pdo, $username);
        $sess = [
            'id' => $member['id'],
            'email' => $member['email'] ?? '',
            'username' => $member['username'],
            'name' => $member['name'],
            'contact' => $member['contact'],
            'role' => 'member',
            'memberId' => $member['id'],
            'status' => $member['status'],
            'plan_id' => $member['plan_id']
        ];
        $_SESSION['user'] = $sess;
        json_ok(['user' => $sess]);
    }

    login_register_fail($pdo, $username);
    json_err('Invalid username or password.', 401);
}

// ---- LOGOUT ----
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    json_ok(['msg' => 'Logged out']);
}

// ---- SESSION CHECK ----
if ($action === 'session') {
    if (empty($_SESSION['user'])) json_err('No session', 401);
    // re-validate live status
    $u = $_SESSION['user'];
    if (($u['role'] ?? '') === 'member') {
        $stmt = $pdo->prepare("SELECT status, name, email, contact FROM members WHERE id=?");
        $stmt->execute([$u['id']]);
        $live = $stmt->fetch();
        if ($live) {
            $u['status'] = $live['status'];
            $u['name'] = $live['name'];
            $_SESSION['user'] = $u;
            // if pending/archived, tell frontend to show gate
            json_ok(['user' => $u, 'live_status' => $live['status']]);
        }
    } else if (!empty($u['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$u['id']]);
        $live = $stmt->fetch();
        if (!$live || $live['status'] === 'locked' || $live['status'] === 'pending') {
            $_SESSION = []; session_destroy();
            json_err('Session invalidated', 401);
        }
        $live['specializations'] = $live['specializations'] ? json_decode($live['specializations'], true) : [];
        $live['available_days'] = $live['available_days'] ? json_decode($live['available_days'], true) : [];
        $_SESSION['user'] = $live;
        json_ok(['user' => $live]);
    }
    json_ok(['user' => $_SESSION['user']]);
}

// ---- REGISTER (staff / trainer) ----
if ($action === 'register') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $role = $input['role'] ?? '';
    if (!in_array($role, ['staff','trainer'], true)) json_err('Invalid role', 400);
    $name = sanitize_text($input['name'] ?? '');
    $contact = sanitize_text($input['contact'] ?? '');
    $username = sanitize_text($input['username'] ?? '');
    $password = $input['password'] ?? '';
    if ($name===''||$contact===''||$username===''||$password==='') json_err('Please fill in all required fields.', 400);
    if (strlen($password) < 6) json_err('Password must be at least 6 characters.', 400);

    // unique check
    $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(username)=LOWER(?) UNION SELECT id FROM members WHERE LOWER(username)=LOWER(?)");
    $stmt->execute([$username, $username]);
    if ($stmt->fetch()) json_err('Username already taken. Please choose a different username.', 409);

    $status = 'pending'; // staff/trainer needs admin approval
    $id = gen_uid('u');
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $coachName = sanitize_text($input['coachName'] ?? $input['coach_name'] ?? '');
    $specs = $input['specializations'] ?? [];
    $days = $input['availableDays'] ?? $input['available_days'] ?? [];
    $from = sanitize_text($input['availableFrom'] ?? $input['available_from'] ?? '');
    $to = sanitize_text($input['availableTo'] ?? $input['available_to'] ?? '');
    $bio = sanitize_text($input['bio'] ?? '');

    $stmt = $pdo->prepare("INSERT INTO users (id, name, username, password_hash, role, status, contact, created_at, coach_name, specializations, available_days, available_from, available_to, bio) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $id, $name, $username, $hash, $role, $status, $contact, date('Y-m-d'),
        $coachName ?: null,
        $specs ? json_encode(array_values($specs)) : null,
        $days ? json_encode(array_values($days)) : null,
        $from ?: null, $to ?: null, $bio ?: null
    ]);

    $pdo->prepare("INSERT INTO activity_log (id, action, category, detail, extra, by_name, by_username, by_role, at) VALUES (?,?,?,?,?,?,?,?,NOW())")
        ->execute([gen_uid('ACT'), 'Signup', $role==='trainer'?'Trainer':'Staff', $name, 'ID: '.$id.' | Awaiting admin approval', $name, $username, $role]);

    json_ok(['msg' => 'Account submitted for admin approval', 'id' => $id]);
}

// ---- MEMBER SELF-SIGNUP ----
if ($action === 'member_signup') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $name = sanitize_text($input['name'] ?? '');
    $username = sanitize_text($input['username'] ?? $input['uname'] ?? '');
    $contact = sanitize_text($input['contact'] ?? '');
    $email = sanitize_text($input['email'] ?? '');
    $password = $input['password'] ?? $input['pass'] ?? '';
    $planId = sanitize_text($input['planId'] ?? $input['plan_id'] ?? '');
    $dob = $input['dob'] ?? null;
    $sex = sanitize_text($input['sex'] ?? '');
    $age = sanitize_text($input['age'] ?? '');

    if ($name===''||$username===''||$contact===''||$email===''||$password===''||$planId==='') json_err('Please fill in all required fields.', 400);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_err('Please enter a valid email address.', 400);
    if (strlen($password) < 6) json_err('Password must be at least 6 characters.', 400);
    if (!preg_match('/^[a-zA-Z0-9._]{3,20}$/', $username)) json_err('Username must be 3â€“20 characters (letters, numbers, dots, underscores only).', 400);

    // unique
    $stmt = $pdo->prepare("SELECT id FROM members WHERE LOWER(username)=LOWER(?) UNION SELECT id FROM users WHERE LOWER(username)=LOWER(?)");
    $stmt->execute([$username, $username]);
    if ($stmt->fetch()) json_err('Username already taken. Please choose another.', 409);
    $stmt = $pdo->prepare("SELECT id FROM members WHERE LOWER(email)=LOWER(?)");
    $stmt->execute([$email]);
    if ($stmt->fetch()) json_err('Email already registered. Please log in instead.', 409);
    $stmt = $pdo->prepare("SELECT id FROM members WHERE contact=?");
    $stmt->execute([$contact]);
    if ($stmt->fetch()) json_err('Phone number already registered.', 409);

    // plan exists?
    $stmt = $pdo->prepare("SELECT id, name FROM plans WHERE id=? AND status='Active'");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch();
    if (!$plan) json_err('Please choose a valid plan.', 400);

    $id = gen_member_id($pdo);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO members (id, name, username, contact, email, password_hash, plan_id, status, created_at, created_by, created_by_username, created_by_role, bg_check_status, dob, sex, age) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$id, $name, $username, $contact, $email, $hash, $planId, 'pending_payment', date('Y-m-d'), 'Self', $username, 'member', 'Pending', $dob ?: null, $sex ?: null, $age ?: null]);

    // notification
    $pdo->prepare("INSERT INTO notifications (id, member_id, plan_id, type, status, created_at) VALUES (?,?,?,?,?,?)")
        ->execute([gen_uid('NTF-'), $id, $planId, 'pending_payment', 'open', date('Y-m-d')]);

    // activity log
    $pdo->prepare("INSERT INTO activity_log (id, action, category, detail, extra, by_name, by_username, by_role, at) VALUES (?,?,?,?,?,?,?,?,NOW())")
        ->execute([gen_uid('ACT'), 'Signup', 'Member', $name, 'ID: '.$id.' | Plan: '.$plan['name'].' | Awaiting front-desk payment', $name, $username, 'member']);

    json_ok(['msg' => 'Member registered â€” pending payment', 'id' => $id]);
}

// ---- FORGOT PASSWORD (demo: verify username+contact/email) ----
if ($action === 'forgot_verify') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $username = sanitize_text($input['username'] ?? '');
    $method = $input['method'] ?? 'email';
    $email = sanitize_text($input['email'] ?? '');
    $phone = sanitize_text($input['phone'] ?? '');
    if ($username==='') json_err('Please enter your username.', 400);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username)=LOWER(?) LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    $type = 'user';
    if (!$user) {
        $stmt = $pdo->prepare("SELECT * FROM members WHERE LOWER(username)=LOWER(?) LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        $type = 'member';
    }
    if (!$user) json_err('Username not found. Please check the spelling.', 404);

    if ($method === 'email') {
        if (strtolower($user['email'] ?? '') !== strtolower($email)) json_err('Gmail does not match our records for this username.', 400);
    } else {
        // phone: compare last 10 digits
        $stored = preg_replace('/\D/', '', $user['contact'] ?? '');
        $provided = preg_replace('/\D/', '', $phone);
        if (substr($stored, -10) !== substr($provided, -10)) json_err('Phone number does not match our records for this username.', 400);
    }

    // Generate 6-digit code, store in session (demo, not SMS/email)
    $code = str_pad((string)rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['fp_code'] = $code;
    $_SESSION['fp_exp'] = time() + 5*60;
    $_SESSION['fp_user'] = $username;
    $_SESSION['fp_type'] = $type;
    $_SESSION['fp_id'] = $user['id'];

    json_ok(['msg' => 'Verification code generated (demo)', 'demo_code' => $code, 'expires_in' => 300]);
}

if ($action === 'forgot_reset') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $code = sanitize_text($input['code'] ?? '');
    $newPass = $input['new_password'] ?? $input['password'] ?? '';
    if ($code===''||$newPass==='') json_err('Please enter code and new password.', 400);
    if (strlen($newPass) < 6) json_err('Password must be at least 6 characters.', 400);
    if (empty($_SESSION['fp_code']) || time() > ($_SESSION['fp_exp'] ?? 0)) json_err('Code expired. Please request a new one.', 400);
    if ($code !== $_SESSION['fp_code']) json_err('Invalid code. Please check and try again.', 400);

    $id = $_SESSION['fp_id'];
    $type = $_SESSION['fp_type'];
    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    if ($type === 'user') {
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $id]);
    } else {
        $pdo->prepare("UPDATE members SET password_hash=? WHERE id=?")->execute([$hash, $id]);
    }
    // clear
    unset($_SESSION['fp_code'], $_SESSION['fp_exp'], $_SESSION['fp_user'], $_SESSION['fp_type'], $_SESSION['fp_id']);
    // reset login attempts
    $username = $input['username'] ?? '';
    if ($username) $pdo->prepare("DELETE FROM login_attempts WHERE username=?")->execute([strtolower($username)]);
    json_ok(['msg' => 'Password reset successful']);
}

json_err('Unknown auth action: ' . $action, 400);

