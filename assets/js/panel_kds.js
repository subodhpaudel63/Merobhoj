/* ============================================================
   panel_kds.js — Kitchen Display System board controller.

   Runs only on the chef dashboard (needs #kdsBoard). Polls
   chef/api/kitchen_orders.php every 5s, renders four columns
   (NEW / ACCEPTED / COOKING / READY = Pending / Confirmed /
   Preparing / Ready), ticks live elapsed timers every second,
   escalates card tiers as orders age, and advances tickets via
   chef/api/kitchen_update_status.php (CSRF header).

   Optional voice announcements for brand-new tickets use
   window.speechSynthesis, gated on localStorage 'mkj_kds_voice'
   (the topbar toggle in panel_notifications.js flips it; OFF by
   default so it never auto-nags).

   Markup contract expected on the dashboard page:
     #kdsBoard                             board root
     [data-col-body="Pending|Confirmed|Preparing|Ready"]   column card wells
     [data-col-count="Pending|Confirmed|Preparing|Ready"]  per-column counters
     [data-kds-stat="new|cooking|ready|delayed"]           summary-tile values
     #kdsUpdated (optional)                "last synced" label
   Requires window.PANEL_CSRF for status POSTs.
   ============================================================ */
(function () {
    const board = document.getElementById('kdsBoard');
    if (!board) return; // not on the KDS page

    const CSRF        = window.PANEL_CSRF || '';
    const ORDERS_URL  = 'api/kitchen_orders.php';
    const UPDATE_URL  = 'api/kitchen_update_status.php';
    const POLL_MS     = 5000;
    const WARN_STAGE  = 8 * 60;   // seconds at current stage → amber
    const LATE_STAGE  = 20 * 60;  // seconds at current stage → red (matches server)
    const VOICE_KEY   = 'mkj_kds_voice';

    // status → column action + label. Ready has no forward chef action.
    const FLOW = {
        Pending:   { next: null,        label: '',              btn: '',                icon: '' },
        Confirmed: { next: 'Preparing', label: 'Start Cooking', btn: 'qrm-btn-primary', icon: 'skillet' },
        Preparing: { next: 'Ready',     label: 'Mark Ready',    btn: 'qrm-btn-success', icon: 'room_service' },
        Ready:     { next: null,        label: '',              btn: '',                icon: '' }
    };
    const COLUMNS = ['Pending', 'Confirmed', 'Preparing', 'Ready'];

    let prevKnown = null; // Set of order_numbers seen last poll (null = first load)

    /* ---- helpers ---------------------------------------------------- */
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function fmtElapsed(secs) {
        secs = Math.max(0, Math.floor(secs));
        const h = Math.floor(secs / 3600);
        const m = Math.floor((secs % 3600) / 60);
        const s = secs % 60;
        const mm = String(m).padStart(2, '0');
        const ss = String(s).padStart(2, '0');
        return h > 0 ? h + ':' + mm + ':' + ss : mm + ':' + ss;
    }

    function typeClass(t) {
        const k = String(t || '').toLowerCase().replace(/\s+/g, '');
        if (k === 'dinein') return 'type-dinein';
        if (k === 'takeaway') return 'type-takeaway';
        return 'type-delivery';
    }

    function tierFor(stageSecs) {
        if (stageSecs >= LATE_STAGE) return 'tier-late';
        if (stageSecs >= WARN_STAGE) return 'tier-warn';
        return 'tier-fresh';
    }

    function toast(msg, type) {
        if (typeof window.showToast === 'function') window.showToast(msg, type);
    }

    /* ---- rendering -------------------------------------------------- */
    function cardHTML(o) {
        const flow      = FLOW[o.status] || FLOW.Ready;
        const nowMs     = Date.now();
        const startMs   = nowMs - (Number(o.age_seconds) || 0) * 1000;
        const stageMs   = nowMs - (Number(o.stage_seconds) || 0) * 1000;
        const tier      = tierFor(Number(o.stage_seconds) || 0);
        const highPrio  = o.priority === 'high';

        const tags = [];
        tags.push(`<span class="kds-tag ${typeClass(o.order_type)}">${esc(o.order_type)}</span>`);
        if (o.table_number) tags.push(`<span class="kds-tag">Table ${esc(o.table_number)}</span>`);
        if (highPrio) tags.push(`<span class="kds-tag prio-high"><span class="material-symbols-sharp">priority_high</span>Priority</span>`);

        const items = (o.items || []).map(it =>
            `<li class="kds-item"><span class="kds-qty">${parseInt(it.quantity, 10) || 1}×</span>` +
            `<span class="kds-name">${esc(it.name)}</span></li>`
        ).join('');

        const note = o.instructions
            ? `<div class="kds-note"><span class="material-symbols-sharp">sticky_note_2</span><span>${esc(o.instructions)}</span></div>`
            : '';

        const action = flow.next
            ? `<button type="button" class="qrm-btn ${flow.btn} kds-advance"
                       data-order="${esc(o.order_number)}" data-next="${flow.next}">
                   <span class="material-symbols-sharp">${flow.icon}</span>${flow.label}
               </button>`
            : `<span class="kds-await"><span class="material-symbols-sharp">${o.status === 'Pending' ? 'hourglass_top' : 'restaurant'}</span>${o.status === 'Pending' ? 'Waiting for staff acceptance' : 'Awaiting handoff'}</span>`;

        return `<div class="kds-card ${tier} ${highPrio ? 'prio-high' : ''}"
                     data-order="${esc(o.order_number)}"
                     data-received="${startMs}" data-stage="${stageMs}">
            <div class="kds-card-top">
                <span class="kds-order-no">#${esc(o.order_number)}</span>
                <span class="kds-timer" data-received="${startMs}">${fmtElapsed(o.age_seconds)}</span>
            </div>
            <div class="kds-tags">${tags.join('')}</div>
            <div class="kds-customer">${esc(o.customer_name)}</div>
            <ul class="kds-items">${items}</ul>
            ${note}
            <div class="kds-card-actions">${action}</div>
        </div>`;
    }

    function render(orders) {
        // Bucket by status.
        const buckets = { Pending: [], Confirmed: [], Preparing: [], Ready: [] };
        orders.forEach(o => { if (buckets[o.status]) buckets[o.status].push(o); });

        COLUMNS.forEach(status => {
            const body  = board.querySelector(`[data-col-body="${status}"]`);
            const count = board.querySelector(`[data-col-count="${status}"]`);
            if (count) count.textContent = buckets[status].length;
            if (!body) return;
            if (!buckets[status].length) {
                body.innerHTML = '<div class="kds-col-empty">No orders</div>';
            } else {
                body.innerHTML = buckets[status].map(cardHTML).join('');
            }
        });

        // Summary tiles.
        setStat('new',     buckets.Pending.length);
        setStat('cooking', buckets.Preparing.length);
        setStat('ready',   buckets.Ready.length);
        setStat('delayed', orders.filter(o => o.is_delayed).length);

        const stamp = document.getElementById('kdsUpdated');
        if (stamp) stamp.textContent = 'Synced ' + new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    function setStat(key, val) {
        const el = board.querySelector(`[data-kds-stat="${key}"]`) ||
                   document.querySelector(`[data-kds-stat="${key}"]`);
        if (el) el.textContent = val;
    }

    /* ---- live timers + tier escalation (1s) ------------------------- */
    function tick() {
        const now = Date.now();
        board.querySelectorAll('.kds-card').forEach(card => {
            const recv = Number(card.getAttribute('data-received')) || now;
            const stg  = Number(card.getAttribute('data-stage')) || now;
            const timer = card.querySelector('.kds-timer');
            if (timer) timer.textContent = fmtElapsed((now - recv) / 1000);
            const tier = tierFor((now - stg) / 1000);
            if (!card.classList.contains(tier)) {
                card.classList.remove('tier-fresh', 'tier-warn', 'tier-late');
                card.classList.add(tier);
            }
        });
    }

    /* ---- data + actions --------------------------------------------- */
    function load() {
        fetch(ORDERS_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => {
                if (!d || !d.success) { toast((d && d.message) || 'Could not load orders', 'error'); return; }
                const orders = d.orders || [];
                render(orders);
                reconcileVoice(orders);
            })
            .catch(() => {/* transient; next poll retries */});
    }

    function advance(orderNumber, nextStatus, card, btn) {
        if (btn) btn.disabled = true;
        if (card) card.classList.add('is-busy');
        fetch(UPDATE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ order_number: orderNumber, status: nextStatus, csrf: CSRF })
        })
            .then(r => r.json())
            .then(d => {
                if (d && d.success) toast(d.message || ('Moved to ' + nextStatus), 'success');
                else toast((d && d.message) || 'Update failed', 'error');
            })
            .catch(() => toast('Network error', 'error'))
            .finally(load); // authoritative reconcile regardless of outcome
    }

    board.addEventListener('click', function (e) {
        const btn = e.target.closest('.kds-advance');
        if (!btn) return;
        advance(btn.getAttribute('data-order'), btn.getAttribute('data-next'),
                btn.closest('.kds-card'), btn);
    });

    /* Keyboard workflow shortcuts: 1 = start cooking, 2 = mark ready. */
    const SHORTCUTS = {
        '1': { column: 'Confirmed', next: 'Preparing' },
        '2': { column: 'Preparing', next: 'Ready' }
    };

    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey || e.metaKey || e.altKey || e.shiftKey) return;
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement && document.activeElement.tagName)) return;

        const shortcut = SHORTCUTS[e.key];
        if (!shortcut) return;

        const btn = board.querySelector(
            `[data-col-body="${shortcut.column}"] .kds-advance[data-next="${shortcut.next}"]:not(:disabled)`
        );
        if (!btn) {
            toast('No order is waiting for this action.', 'info');
            return;
        }

        e.preventDefault();
        advance(btn.getAttribute('data-order'), shortcut.next, btn.closest('.kds-card'), btn);
    });

    /* ---- voice announcements (opt-in) ------------------------------- */
    function voiceOn() {
        return localStorage.getItem(VOICE_KEY) === 'on' && 'speechSynthesis' in window;
    }

    function announce(o) {
        try {
            const count = (o.items || []).reduce((s, i) => s + (parseInt(i.quantity, 10) || 1), 0);
            let msg = 'New order. ' + count + (count === 1 ? ' item.' : ' items.');
            if (o.order_type === 'Dine In' && o.table_number) msg += ' Table ' + o.table_number + '.';
            const u = new SpeechSynthesisUtterance(msg);
            u.rate = 1; u.volume = 1;
            window.speechSynthesis.speak(u);
        } catch (err) {/* speech unavailable — silent */}
    }

    function reconcileVoice(orders) {
        const current = new Set(orders.map(o => o.order_number));
        if (prevKnown !== null && voiceOn()) {
            orders.forEach(o => {
                if (o.status === 'Pending' && !prevKnown.has(o.order_number)) announce(o);
            });
        }
        prevKnown = current;
    }

    /* ---- boot ------------------------------------------------------- */
    load();
    setInterval(load, POLL_MS);
    setInterval(tick, 1000);
})();
