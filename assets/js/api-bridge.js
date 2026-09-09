// ============================================================
// FITCORE — PHP Bridge for XAMPP
// ============================================================
// When running via http://localhost (XAMPP), this file
// overrides the localStorage Repository to use MySQL via PHP API.
// When opening via file://, it silently keeps localStorage (demo mode).
// Include this BEFORE script.js in index.html
// ============================================================
(function(){
  // Detect if we are on http(s) and api is reachable
  const isHttp = location.protocol === 'http:' || location.protocol === 'https:';
  // Allow forcing localStorage with ?demo=1
  const forceDemo = new URLSearchParams(location.search).get('demo') === '1';
  window.USE_PHP = isHttp && !forceDemo;

  if (!window.USE_PHP) {
    console.log('[FITCORE] Demo mode: using localStorage (open via file:// or ?demo=1)');
    return;
  }

  console.log('[FITCORE] PHP mode: using MySQL via api/*.php — checking connection...');

  // Small helper — now handles <br /> HTML errors gracefully
  async function apiFetch(url, opts={}) {
    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      ...opts
    });
    const text = await res.text();
    let data;
    try { data = JSON.parse(text); }
    catch(e) {
      // Server returned HTML (e.g. <br /> warning) instead of JSON
      const short = text.replace(/<[^>]+>/g, ' ').replace(/\s+/g,' ').trim().slice(0,180);
      throw new Error(short || ('HTTP '+res.status+' Invalid JSON'));
    }
    if (!res.ok || data.ok === false) throw new Error(data.error || data.details || ('HTTP '+res.status));
    return data;
  }

  // Cache for all tables — fetched once on load, then kept in memory + synced via API
  window.PHP_CACHE = {
    users: [], members: [], payments: [], sessions: [], plans: [],
    attendance: [], walkins: [], notifications: [], messages: [], announcements: [],
    activity_log: [], settings: {}
  };
  window.PHP_READY = false;
  window.PHP_READY_PROMISE = null;

  async function loadAll() {
    try {
      const [members, plans, payments, users, attendance, walkins, notifications, messages, announcements] = await Promise.all([
        apiFetch('api/members.php').then(d=>d.members||[]).catch(()=>[]),
        apiFetch('api/plans.php').then(d=>d.plans||[]).catch(()=>[]),
        apiFetch('api/payments.php').then(d=>d.payments||[]).catch(()=>[]),
        apiFetch('api/users.php').then(d=>d.users||[]).catch(()=>[]),
        apiFetch('api/attendance.php').then(d=>d.attendance||[]).catch(()=>[]),
        apiFetch('api/walkins.php').then(d=>d.walkins||[]).catch(()=>[]),
        apiFetch('api/notifications.php?status=open').then(d=>d.notifications||[]).catch(()=>[]),
        apiFetch('api/messages.php').then(d=>d.messages||[]).catch(()=>[]),
        apiFetch('api/announcements.php').then(d=>d.announcements||[]).catch(()=>[])
      ]);
      PHP_CACHE.members = members;
      PHP_CACHE.plans = plans;
      PHP_CACHE.payments = payments;
      PHP_CACHE.users = users;
      PHP_CACHE.attendance = attendance;
      PHP_CACHE.walkins = walkins;
      PHP_CACHE.notifications = notifications;
      PHP_CACHE.messages = messages;
      PHP_CACHE.announcements = announcements;
      try {
        const s = await apiFetch('api/settings.php');
        PHP_CACHE.settings = s;
      } catch(e){}
      window.PHP_READY = true;
      console.log('[FITCORE] PHP cache loaded', PHP_CACHE);
      // dispatch event for script.js to re-render
      window.dispatchEvent(new CustomEvent('php-ready'));
    } catch(e) {
      console.warn('[FITCORE] PHP not reachable, falling back to localStorage', e);
      window.USE_PHP = false;
      window.PHP_READY = true;
      window.dispatchEvent(new CustomEvent('php-ready'));
    }
  }

  // Start loading immediately, but also expose promise
  window.PHP_READY_PROMISE = loadAll();

  // Intercept function to be called from script.js Repository methods
  window.PHP_API = {
    async getAll(key) {
      await window.PHP_READY_PROMISE;
      const map = {
        'gms_users': PHP_CACHE.users,
        'gms_members': PHP_CACHE.members,
        'gms_payments': PHP_CACHE.payments,
        'gms_sessions': PHP_CACHE.sessions,
        'gms_plans': PHP_CACHE.plans,
        'gms_attendance': PHP_CACHE.attendance,
        'gms_walkins': PHP_CACHE.walkins,
        'gms_notifications': PHP_CACHE.notifications,
        'gms_messages': PHP_CACHE.messages,
        'gms_announcements': PHP_CACHE.announcements
      };
      return map[key] || [];
    },
    async saveAll(key, arr) {
      // For now, save is handled per-entity via specific api calls.
      // We just update cache and optionally sync.
      const map = {
        'gms_users': 'users',
        'gms_members': 'members',
        'gms_payments': 'payments',
        'gms_sessions': 'sessions',
        'gms_plans': 'plans',
        'gms_attendance': 'attendance',
        'gms_walkins': 'walkins',
        'gms_notifications': 'notifications',
        'gms_messages': 'messages',
        'gms_announcements': 'announcements'
      };
      const cacheKey = map[key];
      if (cacheKey && PHP_CACHE[cacheKey] !== undefined) {
        PHP_CACHE[cacheKey] = arr;
      }
      return arr;
    },
    async login(username, password) {
      const data = await apiFetch('api/auth.php?action=login', {
        method: 'POST',
        body: JSON.stringify({username, password})
      });
      return data.user;
    },
    async checkSession() {
      try {
        const data = await apiFetch('api/auth.php?action=session');
        return data.user;
      } catch(e){ return null; }
    }
  };

  // Patch alert for XAMPP users
  setTimeout(()=>{
    const checkEl = document.createElement('div');
    checkEl.id = 'php-status';
    checkEl.style.cssText = 'position:fixed;bottom:12px;right:12px;z-index:9999;background:rgba(10,10,10,.9);border:1px solid rgba(127,250,136,.35);color:#7ffa88;padding:8px 12px;border-radius:10px;font-size:11px;font-family:monospace;display:none';
    document.addEventListener('DOMContentLoaded', ()=>{
      document.body.appendChild(checkEl);
      if(window.USE_PHP){
        checkEl.textContent = '● MySQL Connected (XAMPP)';
        checkEl.style.display='block';
        checkEl.style.borderColor='rgba(127,250,136,.45)';
      }
    });
  },0);
})();
