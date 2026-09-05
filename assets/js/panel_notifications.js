/* ============================================================
   panel_notifications.js — generic role-scoped notification bell.
   Wires the #panelBell widget in the panel topbar to a JSON endpoint
   declared via data-endpoint (e.g. api/notifications.php). Reused by
   chef now, staff/rider later — nothing here is chef-specific.
   Requires window.PANEL_CSRF for mark-read POSTs.
   ============================================================ */
(function () {
    const bell = document.getElementById('panelBell');
    if (!bell) return;

    const endpoint = bell.getAttribute('data-endpoint');
    if (!endpoint) return;

    const btn     = document.getElementById('panelBellBtn');
    const badge   = document.getElementById('panelBellBadge');
    const menu    = document.getElementById('panelBellMenu');
    const list    = document.getElementById('panelBellList');
    const markAll = document.getElementById('panelBellMarkAll');

    const CSRF = window.PANEL_CSRF || '';
    let loadedOnce = false;

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function timeAgo(ts) {
        const then = new Date((ts || '').replace(' ', 'T'));
        if (isNaN(then.getTime())) return '';
        const secs = Math.floor((Date.now() - then.getTime()) / 1000);
        if (secs < 60) return 'just now';
        if (secs < 3600) return Math.floor(secs / 60) + 'm ago';
        if (secs < 86400) return Math.floor(secs / 3600) + 'h ago';
        return then.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    const iconFor = { menu: 'restaurant_menu', order: 'receipt_long', general: 'notifications' };

    function setBadge(n) {
        if (!badge) return;
        if (n > 0) {
            badge.textContent = n > 99 ? '99+' : n;
            badge.classList.add('show');
        } else {
            badge.classList.remove('show');
        }
    }

    function refreshCount() {
        fetch(endpoint + '?action=count', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => { if (d && d.success) setBadge(d.count || 0); })
            .catch(() => {});
    }

    function renderList(items) {
        if (!list) return;
        if (!items || !items.length) {
            list.innerHTML = '<div class="panel-bell-empty">You\'re all caught up 🎉</div>';
            return;
        }
        list.innerHTML = items.map(n => {
            const icon = iconFor[n.type] || 'notifications';
            return `<div class="panel-bell-item ${n.is_read ? '' : 'unread'}"
                         data-id="${n.id}" data-url="${esc(n.url || '')}">
                <div class="pbi-icon"><span class="material-symbols-sharp">${icon}</span></div>
                <div class="pbi-body">
                    <div class="pbi-title">${esc(n.title)}</div>
                    ${n.message ? `<div class="pbi-msg">${esc(n.message)}</div>` : ''}
                    <div class="pbi-time">${timeAgo(n.created_at)}</div>
                </div>
            </div>`;
        }).join('');
    }

    function loadList() {
        if (!list) return;
        fetch(endpoint + '?action=list', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => {
                if (d && d.success) {
                    renderList(d.notifications);
                    setBadge(d.unread || 0);
                }
            })
            .catch(() => { list.innerHTML = '<div class="panel-bell-empty">Could not load notifications.</div>'; });
    }

    function post(body) {
        return fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body)
        }).then(r => r.json());
    }

    function markRead(id) {
        return post({ action: 'read', id: id, csrf: CSRF });
    }

    // Toggle dropdown
    if (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const open = bell.classList.toggle('open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (menu) menu.setAttribute('aria-hidden', open ? 'false' : 'true');
            if (open) { loadList(); loadedOnce = true; }
        });
    }

    // Click a notification → mark read, then navigate if it has a url
    if (list) {
        list.addEventListener('click', function (e) {
            const item = e.target.closest('.panel-bell-item');
            if (!item) return;
            const id = item.getAttribute('data-id');
            const url = item.getAttribute('data-url');
            item.classList.remove('unread');
            markRead(id).finally(() => {
                refreshCount();
                if (url) window.location.href = url;
            });
        });
    }

    if (markAll) {
        markAll.addEventListener('click', function (e) {
            e.stopPropagation();
            post({ action: 'read_all', csrf: CSRF }).then(() => { loadList(); refreshCount(); });
        });
    }

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (bell && !bell.contains(e.target)) {
            bell.classList.remove('open');
            if (btn) btn.setAttribute('aria-expanded', 'false');
            if (menu) menu.setAttribute('aria-hidden', 'true');
        }
    });

    // Initial + periodic count refresh
    refreshCount();
    setInterval(refreshCount, 20000);
})();

/* ---- Voice-announcement toggle (topbar, all panel pages) ------------
   Persists an on/off preference in localStorage; the KDS board reads it.
   Kept here (always-loaded) so the toggle reflects state on every page. */
(function () {
    const toggle = document.getElementById('kdsVoiceToggle');
    if (!toggle) return;
    const KEY = 'mkj_kds_voice';
    const icon = toggle.querySelector('.material-symbols-sharp');

    function paint() {
        const on = localStorage.getItem(KEY) === 'on';
        toggle.classList.toggle('is-on', on);
        if (icon) icon.textContent = on ? 'volume_up' : 'volume_off';
        toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
    }

    toggle.addEventListener('click', function () {
        const on = localStorage.getItem(KEY) === 'on';
        localStorage.setItem(KEY, on ? 'off' : 'on');
        paint();
        // Confirm audibly when turning on (also primes the speech engine).
        if (localStorage.getItem(KEY) === 'on' && 'speechSynthesis' in window) {
            try { window.speechSynthesis.speak(new SpeechSynthesisUtterance('Voice alerts on')); } catch (e) {}
        }
    });

    paint();
})();
