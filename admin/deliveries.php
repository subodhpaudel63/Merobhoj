<?php
/**
 * Admin — delivery dispatcher.
 *
 * Three sections: the unassigned pool, active deliveries (rider + derived state
 * + GPS fix age) and everything completed today. Assignment happens through the
 * shared includes/dispatch_api.php, which admin, manager and staff all use, so
 * this page and staff/deliveries.php can never disagree.
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
require_once __DIR__ . '/../includes/delivery_helpers.php';   // MAX_OTP_ATTEMPTS

$csrf = panel_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Deliveries - Mero Bhoj</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  <link rel="stylesheet" href="../assets/css/admin2.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin2.css') ?>">
  <link rel="stylesheet" href="../assets/css/panel.css?v=<?= filemtime(__DIR__ . '/../assets/css/panel.css') ?>">
</head>
<body class="admin-page">
  <?php include_once __DIR__ . '/topbar.php'; ?>
  <div class="container">
    <?php include_once __DIR__ . '/sidebar.php'; ?>

    <main class="admin-page-main">

      <div class="qrm-page-header">
        <div>
          <h1>Deliveries</h1>
          <p>Assign riders, watch live trips, and review today's completed runs. Refreshes every 5 seconds.</p>
        </div>
        <div class="so-live-badge">
          <span class="so-live-dot"></span>
          <span>Live</span>
        </div>
      </div>

      <div class="qrm-stats-row">
        <div class="qrm-stat-card">
          <span class="material-symbols-sharp qrm-stat-icon" style="color:var(--clr-warnig)">pin_drop</span>
          <div>
            <div class="qrm-stat-value" id="statPool">—</div>
            <div class="qrm-stat-label">Unassigned</div>
          </div>
        </div>
        <div class="qrm-stat-card">
          <span class="material-symbols-sharp qrm-stat-icon" style="color:var(--clr-primary)">two_wheeler</span>
          <div>
            <div class="qrm-stat-value" id="statActive">—</div>
            <div class="qrm-stat-label">Active</div>
          </div>
        </div>
        <div class="qrm-stat-card">
          <span class="material-symbols-sharp qrm-stat-icon" style="color:var(--clr-success)">task_alt</span>
          <div>
            <div class="qrm-stat-value" id="statDone">—</div>
            <div class="qrm-stat-label">Completed Today</div>
          </div>
        </div>
        <div class="qrm-stat-card">
          <span class="material-symbols-sharp qrm-stat-icon" style="color:var(--clr-info-dark, #475569)">group</span>
          <div>
            <div class="qrm-stat-value" id="statRiders">—</div>
            <div class="qrm-stat-label">Riders</div>
          </div>
        </div>
      </div>

      <!-- Unassigned pool -->
      <div class="rider-section-head">
        <h2><span class="material-symbols-sharp">pin_drop</span> Waiting for a rider</h2>
        <span class="rider-hint">Any rider can also claim these from their app</span>
      </div>
      <div class="panel-table-wrap">
        <table class="panel-table">
          <thead>
            <tr>
              <th>Order</th><th>Customer</th><th>Address</th><th>State</th><th>Waiting</th>
              <th style="text-align:right;">Total</th><th style="min-width:230px;">Assign</th>
            </tr>
          </thead>
          <tbody id="poolBody">
            <tr><td colspan="7" style="text-align:center;padding:1.5rem;">Loading…</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Active -->
      <div class="rider-section-head">
        <h2><span class="material-symbols-sharp">two_wheeler</span> On the road</h2>
      </div>
      <div class="panel-table-wrap">
        <table class="panel-table">
          <thead>
            <tr>
              <th>Order</th><th>Customer</th><th>Rider</th><th>State</th>
              <th>GPS</th><th>Elapsed</th><th style="min-width:230px;">Actions</th>
            </tr>
          </thead>
          <tbody id="activeBody">
            <tr><td colspan="7" style="text-align:center;padding:1.5rem;">Loading…</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Completed today -->
      <div class="rider-section-head">
        <h2><span class="material-symbols-sharp">task_alt</span> Completed today</h2>
      </div>
      <div class="panel-table-wrap">
        <table class="panel-table">
          <thead>
            <tr>
              <th>Order</th><th>Customer</th><th>Rider</th><th>Delivered</th>
              <th style="text-align:right;">Order value</th><th style="text-align:right;">Fee</th>
            </tr>
          </thead>
          <tbody id="doneBody">
            <tr><td colspan="6" style="text-align:center;padding:1.5rem;">Loading…</td></tr>
          </tbody>
        </table>
      </div>

    </main>
  </div>

  <div class="toast-container" id="toastContainer"></div>

  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
  <script>
  (function () {
    const CSRF = <?= json_encode($csrf) ?>;
    const API  = '../includes/dispatch_api.php';
    const OTP_MAX = <?= json_encode(MAX_OTP_ATTEMPTS) ?>;
    let riders = [];

    function esc(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function money(n) { return 'Rs. ' + Math.round(Number(n) || 0).toLocaleString('en-US'); }
    function toast(m, t) { if (window.showToast) window.showToast(m, t || 'info'); }
    function ago(s) {
      s = Math.max(0, Math.floor(Number(s) || 0));
      if (s < 60) return s + 's';
      if (s < 3600) return Math.floor(s / 60) + 'm';
      return Math.floor(s / 3600) + 'h ' + Math.floor((s % 3600) / 60) + 'm';
    }
    function fixLabel(age) {
      if (age === null || age === undefined) return '<span class="panel-status st-cancelled">No fix</span>';
      if (age <= 90)  return '<span class="panel-status st-ready">Live · ' + ago(age) + '</span>';
      if (age <= 600) return '<span class="panel-status st-pending">Stale · ' + ago(age) + '</span>';
      return '<span class="panel-status st-cancelled">Old · ' + ago(age) + '</span>';
    }
    function riderSelect(orderNumber, selectedId) {
      const opts = ['<option value="">Choose a rider…</option>'].concat(riders.map(function (r) {
        const sel = (selectedId && Number(selectedId) === r.id) ? ' selected' : '';
        return '<option value="' + r.id + '"' + sel + '>' + esc(r.name) +
               (r.active_jobs ? ' (' + r.active_jobs + ' active)' : ' (free)') + '</option>';
      }));
      return '<div style="display:flex;gap:0.4rem;align-items:center;">' +
               '<select class="assign-select" data-order="' + esc(orderNumber) + '" style="flex:1;padding:0.45rem;border:1px solid var(--clr-border);border-radius:var(--border-radius-1);font-family:inherit;">' +
                 opts.join('') +
               '</select>' +
               '<button class="qrm-btn qrm-btn-primary" data-act="assign" data-order="' + esc(orderNumber) + '">' +
                 '<span class="material-symbols-sharp">person_add</span></button>' +
             '</div>';
    }

    function render(d) {
      document.getElementById('statPool').textContent   = d.stats.pool;
      document.getElementById('statActive').textContent = d.stats.active;
      document.getElementById('statDone').textContent   = d.stats.completed_today;
      document.getElementById('statRiders').textContent = d.stats.riders;

      const poolBody = document.getElementById('poolBody');
      poolBody.innerHTML = d.pool.length ? d.pool.map(function (j) {
        return '<tr>' +
          '<td><strong>' + esc(j.order_number) + '</strong></td>' +
          '<td>' + esc(j.customer_name) + '<br><small>' + esc(j.customer_phone || '') + '</small></td>' +
          '<td>' + esc(j.address || '—') + '</td>' +
          '<td><span class="panel-status st-' + esc(j.state) + '">' + esc(j.state_label) + '</span></td>' +
          '<td>' + ago(j.stage_seconds) + '</td>' +
          '<td style="text-align:right;">' + money(j.order_total) + '</td>' +
          '<td>' + riderSelect(j.order_number, null) + '</td>' +
        '</tr>';
      }).join('') : '<tr><td colspan="7" style="text-align:center;padding:1.5rem;">Nothing waiting — every ready delivery has a rider.</td></tr>';

      const activeBody = document.getElementById('activeBody');
      activeBody.innerHTML = d.active.length ? d.active.map(function (j) {
        const canReassign = j.status === 'Ready';
        const locked      = j.otp_attempts >= OTP_MAX;
        return '<tr>' +
          '<td><strong>' + esc(j.order_number) + '</strong></td>' +
          '<td>' + esc(j.customer_name) + '<br><small>' + esc(j.address || '') + '</small></td>' +
          '<td>' + esc(j.rider_name || '—') + (j.rider_phone ? '<br><small>' + esc(j.rider_phone) + '</small>' : '') + '</td>' +
          '<td><span class="panel-status st-' + esc(j.state) + '">' + esc(j.state_label) + '</span>' +
            (locked ? '<br><span class="panel-status st-norider" style="margin-top:0.3rem;display:inline-block;">Code locked</span>' : '') + '</td>' +
          '<td>' + fixLabel(j.fix_age) + '</td>' +
          '<td>' + ago(j.stage_seconds) + '</td>' +
          '<td>' + (canReassign
            ? riderSelect(j.order_number, j.rider_id) +
              '<button class="qrm-btn qrm-btn-danger" style="margin-top:0.4rem;" data-act="unassign" data-order="' + esc(j.order_number) + '">' +
                '<span class="material-symbols-sharp">person_remove</span> Unassign</button>'
            : '<small>On the way — the rider closes this with the customer\'s code.</small>') +
          (locked
            ? '<button class="qrm-btn qrm-btn-primary" style="margin-top:0.4rem;" data-act="unlock" data-order="' + esc(j.order_number) + '">' +
                '<span class="material-symbols-sharp">lock_open</span> Unlock code</button>'
            : '') +
          '</td>' +
        '</tr>';
      }).join('') : '<tr><td colspan="7" style="text-align:center;padding:1.5rem;">No deliveries in progress.</td></tr>';

      const doneBody = document.getElementById('doneBody');
      doneBody.innerHTML = d.completed.length ? d.completed.map(function (j) {
        const t = j.completed_at ? new Date(String(j.completed_at).replace(' ', 'T')) : null;
        return '<tr>' +
          '<td><strong>' + esc(j.order_number) + '</strong></td>' +
          '<td>' + esc(j.customer_name) + '</td>' +
          '<td>' + esc(j.rider_name) + '</td>' +
          '<td>' + (t && !isNaN(t.getTime()) ? esc(t.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })) : '—') + '</td>' +
          '<td style="text-align:right;">' + money(j.order_total) + '</td>' +
          '<td style="text-align:right;">' + money(j.delivery_fee) + '</td>' +
        '</tr>';
      }).join('') : '<tr><td colspan="6" style="text-align:center;padding:1.5rem;">No deliveries completed today yet.</td></tr>';
    }

    function load() {
      fetch(API + '?action=list', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d && d.success) { riders = d.riders || []; render(d); } })
        .catch(function () { /* keep the last good view */ });
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
      }).then(function (r) { return r.json(); });
    }

    document.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-act]');
      if (!btn) return;
      const act = btn.getAttribute('data-act');
      const order = btn.getAttribute('data-order');
      if (!order) return;

      let body = { action: act, order_number: order };
      if (act === 'assign') {
        const sel = document.querySelector('.assign-select[data-order="' + CSS.escape(order) + '"]');
        const riderId = sel ? sel.value : '';
        if (!riderId) { toast('Choose a rider first.', 'error'); return; }
        body.rider_id = riderId;
      } else if (act === 'unassign') {
        if (!window.confirm('Return ' + order + ' to the unassigned pool?')) return;
      } else if (act === 'unlock') {
        if (!window.confirm('Confirm the code with the customer over the phone, then unlock ' + order + '?')) return;
      } else {
        return;
      }

      btn.disabled = true;
      post(body).then(function (d) {
        toast((d && d.message) || 'Done', d && d.success ? 'success' : 'error');
        load();
      }).catch(function () {
        toast('Network error — try again.', 'error');
      }).finally(function () { btn.disabled = false; });
    });

    load();
    setInterval(load, 5000);
  })();
  </script>
</body>
</html>
