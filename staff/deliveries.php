<?php
/**
 * Staff — delivery dispatch.
 *
 * Same job as admin/deliveries.php, written in the staff panel's own house
 * style. Both post to includes/dispatch_api.php, so there is exactly one
 * assignment implementation behind the two screens.
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/delivery_helpers.php';   // MAX_OTP_ATTEMPTS
$panelUser = require_role($conn, 'staff');

$csrf      = panel_csrf_token();
$pageTitle = 'Delivery Dispatch';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> · Mero Bhoj</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  <link rel="stylesheet" href="../assets/css/admin2.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin2.css') ?>">
  <link rel="stylesheet" href="../assets/css/panel.css?v=<?= filemtime(__DIR__ . '/../assets/css/panel.css') ?>">
</head>
<body class="admin-page">
  <?php include __DIR__ . '/topbar.php'; ?>
  <div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-page-main">

<div class="panel-head" style="margin-bottom: 1.5rem;">
    <h2>Delivery Dispatch</h2>
    <p class="text-muted">Assign riders to ready deliveries and watch trips in progress. Auto-refreshes every 5s.</p>
</div>

<div class="panel-stats">
    <div class="panel-stat-card">
        <span class="material-symbols-sharp">pin_drop</span>
        <div>
            <h3 id="statPool">—</h3>
            <p>Unassigned</p>
        </div>
    </div>
    <div class="panel-stat-card">
        <span class="material-symbols-sharp">two_wheeler</span>
        <div>
            <h3 id="statActive">—</h3>
            <p>On the road</p>
        </div>
    </div>
    <div class="panel-stat-card">
        <span class="material-symbols-sharp">task_alt</span>
        <div>
            <h3 id="statDone">—</h3>
            <p>Delivered today</p>
        </div>
    </div>
    <div class="panel-stat-card">
        <span class="material-symbols-sharp">group</span>
        <div>
            <h3 id="statRiders">—</h3>
            <p>Riders</p>
        </div>
    </div>
</div>

<div class="rider-section-head">
    <h2><span class="material-symbols-sharp">pin_drop</span> Waiting for a rider</h2>
    <span class="rider-hint">Riders can also claim these themselves</span>
</div>
<div class="panel-table-wrap">
    <table class="panel-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Address</th>
                <th>State</th>
                <th>Waiting</th>
                <th>Total</th>
                <th style="min-width:230px;">Assign rider</th>
            </tr>
        </thead>
        <tbody id="poolBody">
            <tr><td colspan="7" style="text-align:center; padding: 2rem;">Loading deliveries...</td></tr>
        </tbody>
    </table>
</div>

<div class="rider-section-head">
    <h2><span class="material-symbols-sharp">two_wheeler</span> On the road</h2>
</div>
<div class="panel-table-wrap">
    <table class="panel-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Rider</th>
                <th>State</th>
                <th>GPS</th>
                <th>Elapsed</th>
                <th style="min-width:230px;">Actions</th>
            </tr>
        </thead>
        <tbody id="activeBody">
            <tr><td colspan="7" style="text-align:center; padding: 2rem;">Loading deliveries...</td></tr>
        </tbody>
    </table>
</div>

<div class="rider-section-head">
    <h2><span class="material-symbols-sharp">task_alt</span> Delivered today</h2>
</div>
<div class="panel-table-wrap">
    <table class="panel-table">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Rider</th>
                <th>Delivered</th>
                <th>Order value</th>
                <th>Fee</th>
            </tr>
        </thead>
        <tbody id="doneBody">
            <tr><td colspan="6" style="text-align:center; padding: 2rem;">Loading deliveries...</td></tr>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const CSRF = <?= json_encode($csrf) ?>;
    const API = '../includes/dispatch_api.php';
    const OTP_MAX = <?= json_encode(MAX_OTP_ATTEMPTS) ?>;
    let riders = [];

    loadDeliveries();
    setInterval(loadDeliveries, 5000);

    function loadDeliveries() {
        // dispatch_api.php has no /api/ segment, so the XHR header is what makes
        // require_role() answer with 401 JSON instead of an HTML redirect.
        fetch(API + '?action=list', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                riders = data.riders || [];
                renderStats(data.stats);
                renderPool(data.pool);
                renderActive(data.active);
                renderDone(data.completed);
            });
    }

    function renderStats(s) {
        document.getElementById('statPool').textContent = s.pool;
        document.getElementById('statActive').textContent = s.active;
        document.getElementById('statDone').textContent = s.completed_today;
        document.getElementById('statRiders').textContent = s.riders;
    }

    function renderPool(pool) {
        const tbody = document.getElementById('poolBody');
        if (!pool || pool.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem;">Nothing waiting — every ready delivery has a rider.</td></tr>';
            return;
        }
        tbody.innerHTML = pool.map(j => `
            <tr>
                <td><strong>${esc(j.order_number)}</strong></td>
                <td>${esc(j.customer_name)}<br><small class="text-muted">${esc(j.customer_phone)}</small></td>
                <td style="font-size:0.8rem; max-width:220px; white-space:normal;">${esc(j.address) || '—'}</td>
                <td><span class="panel-status st-${esc(j.state)}">${esc(j.state_label)}</span></td>
                <td>${ago(j.stage_seconds)}</td>
                <td>Rs. ${money(j.order_total)}</td>
                <td>${riderPicker(j.order_number, null)}</td>
            </tr>
        `).join('');
    }

    function renderActive(active) {
        const tbody = document.getElementById('activeBody');
        if (!active || active.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 2rem;">No deliveries in progress.</td></tr>';
            return;
        }
        tbody.innerHTML = active.map(j => {
            // Once the rider has the food (Delivering) the trip is theirs to finish.
            const editable = j.status === 'Ready';
            const locked   = j.otp_attempts >= OTP_MAX;
            const actions = (editable
                ? riderPicker(j.order_number, j.rider_id) +
                  `<button class="qrm-btn qrm-btn-danger" style="margin-top:0.4rem;" onclick="unassignRider('${esc(j.order_number)}')">Unassign</button>`
                : '<small class="text-muted">On the way — the rider closes this with the customer\'s code.</small>')
                + (locked
                    ? `<button class="qrm-btn qrm-btn-primary" style="margin-top:0.4rem;" onclick="unlockDelivery('${esc(j.order_number)}')">Unlock code</button>`
                    : '');
            return `
                <tr>
                    <td><strong>${esc(j.order_number)}</strong></td>
                    <td>${esc(j.customer_name)}<br><small class="text-muted">${esc(j.address)}</small></td>
                    <td>${esc(j.rider_name) || '—'}<br><small class="text-muted">${esc(j.rider_phone)}</small></td>
                    <td><span class="panel-status st-${esc(j.state)}">${esc(j.state_label)}</span>${
                        locked ? '<br><span class="panel-status st-norider" style="margin-top:0.3rem;display:inline-block;">Code locked</span>' : ''
                    }</td>
                    <td>${gpsPill(j.fix_age)}</td>
                    <td>${ago(j.stage_seconds)}</td>
                    <td>${actions}</td>
                </tr>
            `;
        }).join('');
    }

    function renderDone(done) {
        const tbody = document.getElementById('doneBody');
        if (!done || done.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 2rem;">No deliveries completed today yet.</td></tr>';
            return;
        }
        tbody.innerHTML = done.map(j => {
            const t = j.completed_at ? new Date(String(j.completed_at).replace(' ', 'T')) : null;
            const when = (t && !isNaN(t.getTime()))
                ? t.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
                : '—';
            return `
                <tr>
                    <td><strong>${esc(j.order_number)}</strong></td>
                    <td>${esc(j.customer_name)}</td>
                    <td>${esc(j.rider_name)}</td>
                    <td>${esc(when)}</td>
                    <td>Rs. ${money(j.order_total)}</td>
                    <td>Rs. ${money(j.delivery_fee)}</td>
                </tr>
            `;
        }).join('');
    }

    function riderPicker(orderNumber, selectedId) {
        const opts = ['<option value="">Choose a rider…</option>'].concat(riders.map(r => {
            const sel = (selectedId && Number(selectedId) === r.id) ? ' selected' : '';
            const load = r.active_jobs ? ` (${r.active_jobs} active)` : ' (free)';
            return `<option value="${r.id}"${sel}>${esc(r.name)}${load}</option>`;
        })).join('');
        return `
            <div style="display:flex; gap:0.4rem; align-items:center;">
                <select class="assign-select" data-order="${esc(orderNumber)}"
                        style="flex:1; padding:0.45rem; border:1px solid var(--clr-border); border-radius:var(--border-radius-1); font-family:inherit;">
                    ${opts}
                </select>
                <button class="qrm-btn qrm-btn-primary" onclick="assignRider('${esc(orderNumber)}')">Assign</button>
            </div>
        `;
    }

    function gpsPill(age) {
        if (age === null || age === undefined) return '<span class="panel-status st-cancelled">No fix</span>';
        if (age <= 90) return `<span class="panel-status st-ready">Live · ${ago(age)}</span>`;
        if (age <= 600) return `<span class="panel-status st-pending">Stale · ${ago(age)}</span>`;
        return `<span class="panel-status st-cancelled">Old · ${ago(age)}</span>`;
    }

    function post(body) {
        return fetch(API, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(Object.assign({ csrf: CSRF }, body))
        }).then(res => res.json());
    }

    window.assignRider = function (orderNumber) {
        const sel = document.querySelector('.assign-select[data-order="' + CSS.escape(orderNumber) + '"]');
        const riderId = sel ? sel.value : '';
        if (!riderId) { alert('Choose a rider first.'); return; }
        post({ action: 'assign', order_number: orderNumber, rider_id: riderId })
            .then(data => {
                if (!data.success) { alert(data.message || 'Assignment failed'); }
                loadDeliveries();
            });
    };

    window.unassignRider = function (orderNumber) {
        if (!confirm(`Return ${orderNumber} to the unassigned pool?`)) return;
        post({ action: 'unassign', order_number: orderNumber })
            .then(data => {
                if (!data.success) { alert(data.message || 'Unassign failed'); }
                loadDeliveries();
            });
    };

    // Five wrong handover codes lock a delivery. Confirm the code with the
    // customer by phone first — this is the only way to reopen a trip that is
    // already on the road.
    window.unlockDelivery = function (orderNumber) {
        if (!confirm(`Confirm the code with the customer over the phone, then unlock ${orderNumber}?`)) return;
        post({ action: 'unlock', order_number: orderNumber })
            .then(data => {
                if (!data.success) { alert(data.message || 'Unlock failed'); }
                loadDeliveries();
            });
    };

    function money(n) {
        return (Math.round((Number(n) || 0) * 100) / 100).toFixed(2);
    }

    function ago(seconds) {
        const s = Math.max(0, Math.floor(Number(seconds) || 0));
        if (s < 60) return s + 's';
        if (s < 3600) return Math.floor(s / 60) + 'm';
        return Math.floor(s / 3600) + 'h ' + Math.floor((s % 3600) / 60) + 'm';
    }

    function esc(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
});
</script>

    </main>
  </div>
  <div class="toast-container" id="toastContainer"></div>
  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
  <script src="../assets/js/panel_notifications.js?v=<?= filemtime(__DIR__ . '/../assets/js/panel_notifications.js') ?>"></script>
</body>
</html>
