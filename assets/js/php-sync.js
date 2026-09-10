// ============================================================
// FITCORE — PHP Sync Layer (runs AFTER script.js)
// Overrides localStorage with MySQL when USE_PHP is true
// ============================================================
(function(){
  // Only activate when api-bridge detected PHP mode
  if (!window.USE_PHP) {
    console.log('[PHP-SYNC] Skipped — demo mode (localStorage)');
    return;
  }

  // Wait until Repository etc are defined by script.js
  function patchWhenReady() {
    if (typeof Repository === 'undefined' || typeof DB === 'undefined' || !window.PHP_CACHE) {
      setTimeout(patchWhenReady, 50);
      return;
    }
    console.log('[PHP-SYNC] Patching Repository to use MySQL...');

    // Backup originals
    const origAll = Repository.prototype.all;
    const origSave = Repository.prototype.save;
    const origAdd = Repository.prototype.add;
    const origUpdate = Repository.prototype.update;
    const origRemove = Repository.prototype.remove;
    const origOne = Repository.prototype.one;

    // Helper: get cache key from storageKey
    function cacheForKey(key) {
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
      return map[key] || null;
    }

    // Helper: sync helpers
    async function syncToServer(entity, action, data) {
      const endpoints = {
        'gms_members': 'api/members.php',
        'gms_plans': 'api/plans.php',
        'gms_payments': 'api/payments.php',
        'gms_users': 'api/users.php',
        'gms_sessions': 'api/sessions.php',
        'gms_attendance': 'api/attendance.php',
        'gms_walkins': 'api/walkins.php',
        'gms_messages': 'api/messages.php',
        'gms_announcements': 'api/announcements.php',
        'gms_notifications': 'api/notifications.php'
      };
      const url = endpoints[entity];
      if (!url) return;
      try {
        await fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({action, ...data})
        });
      } catch(e){ console.warn('[PHP-SYNC] sync failed', entity, action, e); }
    }

    // Override: all() reads from PHP_CACHE when ready, else localStorage
    Repository.prototype.all = function() {
      const ck = cacheForKey(this.storageKey);
      if (window.PHP_READY && ck && window.PHP_CACHE[ck] !== undefined) {
        return window.PHP_CACHE[ck] || [];
      }
      return origAll.call(this);
    };

    Repository.prototype.one = function(id) {
      const ck = cacheForKey(this.storageKey);
      if (window.PHP_READY && ck && window.PHP_CACHE[ck] !== undefined) {
        return (window.PHP_CACHE[ck] || []).find(x=>x.id===id) || null;
      }
      return origOne.call(this);
    };

    // save() updates cache and tries to sync (for bulk ops like seed)
    Repository.prototype.save = function(arr) {
      const ck = cacheForKey(this.storageKey);
      if (window.USE_PHP && window.PHP_READY && ck) {
        window.PHP_CACHE[ck] = arr.slice();
        // also persist to localStorage as backup
        try { origSave.call(this, arr); } catch(e){}
        return arr;
      }
      return origSave.call(this, arr);
    };

    // add(): also POST to server
    Repository.prototype.add = function(item) {
      const ck = cacheForKey(this.storageKey);
      if (window.USE_PHP && window.PHP_READY && ck) {
        // update cache immediately
        window.PHP_CACHE[ck] = window.PHP_CACHE[ck] || [];
        window.PHP_CACHE[ck].push(item);
        // sync to server asynchronously (fire & forget, but also update localStorage)
        try { origAdd.call(this, item); } catch(e){}
        // Map to server action
        const actionMap = {
          'gms_members': {endpoint:'api/members.php', action:'create', payload: item},
          'gms_plans': {endpoint:'api/plans.php', action:'create', payload: item},
          'gms_users': {endpoint:'api/auth.php?action=register', action:'register', payload: item},
          'gms_payments': {endpoint:'api/payments.php', action:'create', payload: item}
        };
        // For members, use members endpoint
        if (this.storageKey === 'gms_members') {
          fetch('api/members.php', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'create', ...item})
          }).catch(()=>{});
        } else if (this.storageKey === 'gms_plans') {
          fetch('api/plans.php', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'create', ...item})
          }).catch(()=>{});
        } else if (this.storageKey === 'gms_walkins') {
          // Walk-ins: persist to MySQL so they don't disappear on refresh
          fetch('api/walkins.php', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({name: item.visitorName || item.name, contact: item.contact || '', fee: item.fee, date: item.date, notes: item.notes || ''})
          }).then(r=>r.json()).then(d=>{
            if(d.id && item.id !== d.id){
              // update cache with server-generated ID
              const idx = window.PHP_CACHE.walkins.findIndex(x=>x.id===item.id);
              if(idx>-1) window.PHP_CACHE.walkins[idx].id = d.id;
            }
          }).catch(()=>{});
        } else if (this.storageKey === 'gms_sessions') {
          // Trainer Schedule: persist to MySQL
          fetch('api/sessions.php', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'create', trainerId: item.trainerId || item.trainer_id, trainerName: item.trainerName || item.trainer_name, memberId: item.memberId || item.member_id, memberName: item.memberName || item.member_name, date: item.date, start: item.start, end: item.end, type: item.type || 'Personal Training', notes: item.notes || ''})
          }).then(r=>r.json()).then(d=>{
            if(d.id && item.id !== d.id){
              const idx = window.PHP_CACHE.sessions.findIndex(x=>x.id===item.id);
              if(idx>-1) window.PHP_CACHE.sessions[idx].id = d.id;
            }
          }).catch(()=>{});
        }
        return item;
      }
      return origAdd.call(this, item);
    };

    Repository.prototype.update = function(id, patch) {
      const ck = cacheForKey(this.storageKey);
      if (window.USE_PHP && window.PHP_READY && ck) {
        const arr = window.PHP_CACHE[ck] || [];
        const idx = arr.findIndex(x=>x.id===id);
        if(idx>-1){ arr[idx] = {...arr[idx], ...patch}; }
        try { origUpdate.call(this, id, patch); } catch(e){}
        // sync
        if (this.storageKey === 'gms_members') {
          fetch('api/members.php', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'update', id, ...patch})
          }).catch(()=>{});
        }
        if (this.storageKey === 'gms_users') {
          fetch('api/users.php', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'update', id, ...patch})
          }).catch(()=>{});
        }
        if (this.storageKey === 'gms_plans') {
          fetch('api/plans.php', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'update', id, ...patch})
          }).catch(()=>{});
        }
        return arr[idx];
      }
      return origUpdate.call(this, id, patch);
    };

    Repository.prototype.remove = function(id) {
      const ck = cacheForKey(this.storageKey);
      if (window.USE_PHP && window.PHP_READY && ck) {
        window.PHP_CACHE[ck] = (window.PHP_CACHE[ck]||[]).filter(x=>x.id!==id);
        try { origRemove.call(this, id); } catch(e){}
        // sync delete
        const delMap = {
          'gms_members': 'api/members.php?id='+encodeURIComponent(id),
          'gms_users': 'api/users.php',
          'gms_plans': 'api/plans.php?id='+encodeURIComponent(id),
          'gms_messages': 'api/messages.php?id='+encodeURIComponent(id)
        };
        if (this.storageKey === 'gms_members') {
          fetch('api/members.php', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({action:'archive', id})
          }).catch(()=>{});
        }
        return window.PHP_CACHE[ck];
      }
      return origRemove.call(this, id);
    };

    // Also patch direct DB.get/set to use cache when PHP mode
    const origDBGet = DB.get;
    const origDBSet = DB.set;
    DB.get = function(k){
      const ck = cacheForKey(k);
      if (window.USE_PHP && window.PHP_READY && ck && window.PHP_CACHE[ck] !== undefined) {
        return window.PHP_CACHE[ck] || [];
      }
      return origDBGet(k);
    };
    DB.set = function(k,v){
      const ck = cacheForKey(k);
      if (window.USE_PHP && window.PHP_READY && ck) {
        window.PHP_CACHE[ck] = v;
      }
      return origDBSet(k,v);
    };

    // Patch AuthService to use PHP login when available
    if (typeof Auth !== 'undefined' && Auth.login) {
      const origLogin = Auth.login.bind(Auth);
      Auth.login = async function(username, password) {
        if (!window.USE_PHP) return origLogin(username, password);
        try {
          const res = await fetch('api/auth.php?action=login', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({username, password})
          });
          const text = await res.text();
          let data; try{ data=JSON.parse(text); } catch(e){ const short=text.replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim().slice(0,180); return {ok:false, error: short || 'Server error (invalid JSON)'}; }
          if (!data.ok) return {ok:false, error: data.error || data.details || 'Login failed'};
          Auth.setSession(data.user);
          return {ok:true, user: data.user};
        } catch(e) {
          console.warn('[PHP-SYNC] PHP login failed, falling back', e);
          return origLogin(username, password);
        }
      };
      // Also patch register
      const origRegister = Auth.register.bind(Auth);
      Auth.register = async function(role, payload) {
        if (!window.USE_PHP) return origRegister(role, payload);
        try {
          const res = await fetch('api/auth.php?action=register', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({role, ...payload})
          });
          const text = await res.text();
          let data; try{ data=JSON.parse(text); } catch(e){ const short=text.replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim().slice(0,180); return {ok:false, error: short || 'Server error'}; }
          if (!data.ok) return {ok:false, error: data.error || data.details};
          // refresh users cache
          try {
            const u = await fetch('api/users.php', {credentials:'same-origin'}).then(r=>r.json());
            if(u.users) window.PHP_CACHE.users = u.users;
          } catch(e){}
          return {ok:true, user: data};
        } catch(e) {
          return origRegister(role, payload);
        }
      };
      // Patch getSession / logout to also use PHP
      const origGetSession = Auth.getSession.bind(Auth);
      Auth.getSession = function(){
        const s = origGetSession();
        if (s) return s;
        // try to restore from PHP session if local empty but PHP has session
        return s;
      };
      const origClear = Auth.clearSession.bind(Auth);
      Auth.clearSession = function(){
        origClear();
        if (window.USE_PHP) {
          fetch('api/auth.php?action=logout', {method:'POST', credentials:'same-origin'}).catch(()=>{});
        }
      };
    }

    // Patch member self-signup to use PHP endpoint
    if (typeof submitMemberSignup === 'function') {
      const origSubmit = submitMemberSignup;
      window.submitMemberSignup = async function(){
        if (!window.USE_PHP) return origSubmit();
        const err=document.getElementById('msError');
        const sel=document.getElementById('msPlanSelect');
        const planId = (typeof _memberSignupPlanId !== 'undefined' && _memberSignupPlanId) || (typeof _msPlanPicked !== 'undefined' && _msPlanPicked) || (sel?sel.value:'');
        if(!planId){ if(err){err.textContent='Please choose a plan first.';err.style.display='block';} return; }
        const v = (typeof msValidateCredentials==='function') ? msValidateCredentials(err) : null;
        if(!v) return;
        try {
          const res = await fetch('api/auth.php?action=member_signup', {
            method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({...v, planId})
          });
          const text = await res.text();
          let data; try{ data=JSON.parse(text); } catch(e){ const short=text.replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim().slice(0,220); if(err){err.textContent='Server error: '+short; err.style.display='block';} console.error('member_signup HTML:', text); return; }
          if(!data.ok){ if(err){err.textContent=data.error || data.details || 'Registration failed';err.style.display='block';} return; }
          // update cache
          const fresh = await fetch('api/members.php', {credentials:'same-origin'}).then(r=>r.json()).catch(()=>null);
          if(fresh && fresh.members) window.PHP_CACHE.members = fresh.members;
          const fresh2 = await fetch('api/notifications.php?status=open', {credentials:'same-origin'}).then(r=>r.json()).catch(()=>null);
          if(fresh2 && fresh2.notifications) window.PHP_CACHE.notifications = fresh2.notifications;
          document.getElementById('msStep3').style.display='none';
          const done=document.getElementById('msDone');
          const msg=document.getElementById('msDoneMsg');
          if(msg)msg.textContent='Account registered! Please pay at the front desk to activate. You can log in once staff confirms payment.';
          if(done)done.style.display='block';
          if(typeof toast==='function') toast('Registered — pending payment (MySQL)');
        } catch(e){
          if(err){err.textContent='Registration failed: '+e.message;err.style.display='block';}
        }
      };
    }

    // Patch doRegister for staff/trainer
    if (typeof doRegister === 'function') {
      const origDoReg = doRegister;
      window.doRegister = async function(){
        if (!window.USE_PHP) return origDoReg();
        // reuse Auth.register patch — just call original which now uses PHP
        return origDoReg();
      };
    }

    // Patch attendance / walkin / payment helpers to force server sync
    // Payments: after adding, refresh payments cache
    const origPaymentsAdd = Payments.add.bind(Payments);
    Payments.add = function(item){
      const res = origPaymentsAdd(item);
      if(window.USE_PHP){
        fetch('api/payments.php', {
          method:'POST', credentials:'same-origin',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify({...item, action:'create', memberId: item.memberId, planId: item.planId, amount: item.amount, method: item.method||'Cash', date: item.date})
        }).catch(()=>{});
      }
      return res;
    };

    // Patch seedData to skip localStorage seeding when PHP mode (MySQL already seeded)
    if (typeof seedData === 'function') {
      const origSeed = seedData;
      window.seedData = function(){
        if (window.USE_PHP) {
          console.log('[PHP-SYNC] Skipping localStorage seed — using MySQL');
          return;
        }
        return origSeed();
      };
    }

    // When PHP cache loads, refresh UI + restore PHP session if any
    window.addEventListener('php-ready', async ()=>{
      console.log('[PHP-SYNC] PHP ready, refreshing UI');
      if (typeof updateHeroMemberCount === 'function') try{ updateHeroMemberCount(); }catch(e){}
      if (typeof renderExplorePlans === 'function') try{ renderExplorePlans(); }catch(e){}
      if (typeof renderTrainers === 'function') try{ renderTrainers(); }catch(e){}
      // If already logged in, refresh current panel
      if (typeof _lastPanel !== 'undefined' && _lastPanel && typeof renderPanel === 'function') {
        try{ renderPanel(_lastPanel); }catch(e){}
      }
      // Restore PHP session if local session empty but server has one
      try {
        const sess = (typeof getSession==='function') ? getSession() : null;
        if (!sess || !sess.username) {
          const r = await fetch('api/auth.php?action=session', {credentials:'same-origin'});
          const data = await r.json();
          if (data.ok && data.user) {
            if (typeof setSession==='function') setSession(data.user);
            if (typeof currentUser !== 'undefined') window.currentUser = data.user;
            if (typeof loadApp==='function') loadApp();
          }
        }
      } catch(e){ /* no server session */ }
    });

    // If already ready, trigger
    if (window.PHP_READY) {
      window.dispatchEvent(new CustomEvent('php-ready'));
    }

    console.log('[PHP-SYNC] Patch complete');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', patchWhenReady);
  } else {
    patchWhenReady();
  }
})();
