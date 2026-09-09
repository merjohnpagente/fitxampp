# FITCORE GYM — XAMPP + PHP + MySQL Setup

> **Keep `index.html` as the only frontend file** — this system is a single-page SPA (HTML/CSS/JS) that now syncs to MySQL when running via XAMPP, while still working offline via `localStorage` when opened as `file://`.

---

## 1. What You Got

```
fitcore/
├── index.html          ← KEEP — main SPA (now XAMPP-aware)
├── style.css
├── script.js           ← business logic (unchanged)
├── api-bridge.js       ← NEW — detects http:// vs file:// and loads MySQL cache
├── php-sync.js         ← NEW — patches Repository/Auth to sync with PHP when online
├── vendor/             ← qrcode.min.js, jsQR
├── assets/pngegg.png
├── config.php          ← NEW — DB credentials
├── db.php              ← NEW — PDO connection
├── database.sql        ← NEW — import this in phpMyAdmin
├── check.php           ← NEW — diagnostic page
└── api/
    ├── auth.php        ← login / register / member signup / forgot
    ├── members.php
    ├── plans.php
    ├── payments.php
    ├── attendance.php
    ├── walkins.php
    ├── sessions.php
    ├── users.php
    ├── messages.php
    ├── notifications.php
    ├── announcements.php
    ├── reports.php
    └── settings.php
```

**Mode auto-detect:**
- `http://localhost/fitcore/` → **MySQL mode** (green badge “● MySQL Connected”)
- `file:///…/index.html` double-click → **Demo mode** (localStorage, no server needed)

---

## 2. XAMPP Install (Windows)

### A. Install & Start
1. Install [XAMPP](https://www.apachefriends.org/) (PHP 8.1+).
2. Open **XAMPP Control Panel** → Start **Apache** + **MySQL** (both green).

### B. Copy Project
1. Copy this whole folder to:
   ```
   C:\xampp\htdocs\fitcore\
   ```
   (If your folder is already on Desktop, copy it — XAMPP only serves from `htdocs`.)

### C. Create Database
1. Open **http://localhost/phpmyadmin**
2. Click **New** → Database name: `fitcore_gym` → Collation: `utf8mb4_unicode_ci` → Create
3. Click **Import** → Choose file → `C:\xampp\htdocs\fitcore\database.sql` → Go
4. Verify: left sidebar shows `fitcore_gym` with 12 tables (`users`, `members`, `plans`, etc.)

### D. Verify
1. Open **http://localhost/fitcore/check.php**
   - All checks should be green. If red, check MySQL is running and `database.sql` was imported.
2. Open **http://localhost/fitcore/index.html** (or `http://localhost/fitcore/`)
   - Bottom-right shows **● MySQL Connected**.
   - Landing → Log In → try:
     - `admin / admin123` → Admin dashboard
     - `staff / staff123` → Staff
     - `trainer / trainer123` → Trainer
     - `nicole / member123` → Member (pending_payment gate if not yet confirmed)
   - Register a new Member via **Explore → Choose Plan → Register** — it inserts into MySQL and appears in **Payment Queue** for staff.

### E. Config (if needed)
Edit `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'fitcore_gym');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP default no password; set if you changed it
```

---

## 3. How It Works (just index.html)

- **No separate `dashboard.php`**: `index.html` stays SPA. On load it:
  1. `api-bridge.js` detects `http://` → fetches all tables via `api/members.php`, `plans.php`, etc. into `window.PHP_CACHE`.
  2. `php-sync.js` patches `Repository`, `DB`, `Auth` so every `Members.add()`, `Payments.add()`, etc. also `fetch()` to PHP.
  3. If MySQL unreachable, it silently falls back to `localStorage` (so `file://` demo never breaks).

- **Why not overwrite `script.js` heavily?** To keep your “just index.html” requirement — your existing `script.js` (420kB, all render/logic) is untouched; sync is additive.

- **Passwords**: Stored as `password_hash(...PASSWORD_DEFAULT)` (bcrypt). Seed hashes in `database.sql` are for `admin123` etc. Login uses `password_verify`.

---

## 4. Database Schema (12 tables)

`users` (staff/trainer/admin), `members`, `plans`, `payments`, `sessions`, `attendance`, `walkins`, `notifications`, `messages`, `announcements`, `activity_log`, `settings`, `login_attempts`

See `database.sql` for full DDL + seed (5 members + 1 pending, 3 plans, 5 payments).

---

## 5. Offline Demo vs MySQL

- **Offline (file://)**: Uses `localStorage` + `seedData()` in `script.js:513`. Data persists per browser, resets if you clear site data.
- **Online (http://localhost)**: Uses MySQL. Data persists across browsers/devices, survives refresh, supports real multi-user (staff confirming member payments etc.).

Force demo mode even on localhost: `http://localhost/fitcore/index.html?demo=1`

---

## 6. Troubleshooting

| Symptom | Fix |
|---|---|
| `Database connection failed` | Import `database.sql`; start MySQL; check `config.php` credentials |
| No “● MySQL Connected” badge | You opened via `file://` — use `http://localhost/fitcore/` |
| Login “Invalid username” but admin exists | Confirm `fitcore_gym` selected in phpMyAdmin; `check.php` should show users=3 |
| Member still “pending_payment” | Log in as `staff` → Payment Queue → Confirm Payment |
| 404 on `api/*.php` | Ensure folder is `C:/xampp/htdocs/fitcore/` and Apache is running |

---

## 7. Submission / Backup

- **SQL dump**: `database.sql` is the single file your professor imports.
- **Project zip**: Zip the whole `fitcore` folder (includes `index.html` + `api/` + `database.sql`) — ready to submit.
- **No `node_modules` needed** — delete it before zipping; only `vendor/qrcode.min.js` is required.

---

## 8. Security Notes (for documentation)

- All PHP uses PDO prepared statements (no SQLi)
- Passwords via `password_hash`/`password_verify`
- `sanitize_text()` strips `< >` control chars (mirrors JS `sanitizeText`)
- Session via `$_SESSION` + `session.cookie_httponly`
- Role checks `require_role(['admin','staff'])` per endpoint

---

**You’re done** — keep `index.html` as your single frontend, run via XAMPP for MySQL, double-click for offline demo.
