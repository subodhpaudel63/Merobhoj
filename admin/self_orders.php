<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Self Orders Queue - Mero Bhoj</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
</head>
<body class="admin-page">
  <?php include_once __DIR__ . '/topbar.php'; ?>
  <div class="container">
    <?php include_once __DIR__ . '/sidebar.php'; ?>

    <main class="admin-page-main">
      <!-- Page Header -->
      <div class="qrm-page-header">
        <div>
          <h1>Self Orders Queue</h1>
          <p>Review and approve customer table orders. Auto-refreshes every 5 seconds.</p>
        </div>
        <div class="so-live-badge">
          <span class="so-live-dot"></span>
          <span>Live</span>
        </div>
      </div>

      <!-- Stats Row -->
      <div class="qrm-stats-row">
        <div class="qrm-stat-card">
          <span class="material-symbols-sharp qrm-stat-icon" style="color:var(--clr-warnig)">hourglass_empty</span>
          <div>
            <div class="qrm-stat-value" id="statPending">—</div>
            <div class="qrm-stat-label">Pending</div>
          </div>
        </div>
        <div class="qrm-stat-card">
          <span class="material-symbols-sharp qrm-stat-icon" style="color:var(--clr-success)">check_circle</span>
          <div>
            <div class="qrm-stat-value" id="statApproved">—</div>
            <div class="qrm-stat-label">Approved Today</div>
          </div>
        </div>
        <div class="qrm-stat-card">
          <span class="material-symbols-sharp qrm-stat-icon" style="color:var(--clr-danger)">cancel</span>
          <div>
            <div class="qrm-stat-value" id="statRejected">—</div>
            <div class="qrm-stat-label">Rejected Today</div>
          </div>
        </div>
      </div>

      <!-- Filter tabs -->
      <div class="so-tabs" id="soTabs">
        <button class="so-tab active" data-filter="all">All</button>
        <button class="so-tab" data-filter="pending">
          Pending <span class="so-tab-badge" id="pendingCount">0</span>
        </button>
        <button class="so-tab" data-filter="approved">Approved</button>
        <button class="so-tab" data-filter="rejected">Rejected</button>
      </div>

      <!-- Orders Grid -->
      <div class="so-grid" id="queueContainer">
        <div class="qrm-loading">
          <span class="material-symbols-sharp qrm-spinner">progress_activity</span>
          <p>Loading orders...</p>
        </div>
      </div>

      <!-- Empty State -->
      <div class="so-empty" id="soEmpty" style="display:none">
        <span class="material-symbols-sharp">receipt_long</span>
        <h3>No orders yet</h3>
        <p>Customer table orders will appear here.</p>
      </div>
    </main>
  </div>

  <!-- Toast Container -->
  <div class="toast-container" id="toastContainer"></div>

  <script>
    let allRequests = [];
    let activeFilter = 'all';

    function loadRequests() {
      fetch('api_qr_requests.php?action=list')
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            allRequests = data.requests;
            updateStats(allRequests);
            applyFilter();
          }
        });
    }

    function updateStats(requests) {
      const pending = requests.filter(r => r.status === 'pending').length;
      const approved = requests.filter(r => r.status === 'approved').length;
      const rejected = requests.filter(r => r.status === 'rejected').length;
      document.getElementById('statPending').textContent = pending;
      document.getElementById('statApproved').textContent = approved;
      document.getElementById('statRejected').textContent = rejected;
      document.getElementById('pendingCount').textContent = pending;
      document.getElementById('pendingCount').style.display = pending > 0 ? 'inline-flex' : 'none';
    }

    function applyFilter() {
      const filtered = activeFilter === 'all'
        ? allRequests
        : allRequests.filter(r => r.status === activeFilter);
      renderRequests(filtered);
    }

    function renderRequests(requests) {
      const container = document.getElementById('queueContainer');
      const empty = document.getElementById('soEmpty');
      container.innerHTML = '';

      if (!requests.length) {
        empty.style.display = 'flex';
        return;
      }
      empty.style.display = 'none';

      requests.forEach(req => {
        container.appendChild(buildOrderCard(req));
      });
    }

    function buildOrderCard(req) {
      const card = document.createElement('div');
      card.className = `so-card so-${req.status}`;
      card.id = 'order-card-' + req.id;

      const statusIcons = { pending: 'hourglass_empty', approved: 'check_circle', rejected: 'cancel' };
      const statusLabels = { pending: 'Pending', approved: 'Approved', rejected: 'Rejected' };

      let itemsHtml = '';
      req.items.forEach(item => {
        itemsHtml += `<li class="so-item-row">
          <span class="so-item-name">${esc(item.name)}</span>
          <span class="so-item-detail">x${item.quantity}</span>
          <span class="so-item-price">Rs. ${(item.price * item.quantity).toLocaleString()}</span>
        </li>`;
      });

      const buttons = req.status === 'pending' ? `
        <div class="so-action-row">
          <button class="qrm-btn qrm-btn-success so-approve-btn" onclick="updateStatus(${req.id},'approve',this)">
            <span class="material-symbols-sharp">thumb_up</span> Approve
          </button>
          <button class="qrm-btn qrm-btn-danger so-reject-btn" onclick="updateStatus(${req.id},'reject',this)">
            <span class="material-symbols-sharp">thumb_down</span> Reject
          </button>
        </div>` : '';

      const orderCodeHtml = req.order_code
        ? `<div class="so-order-code"><span class="material-symbols-sharp">receipt</span> ${esc(req.order_code)}</div>`
        : '';
        
      const rejectReasonHtml = req.rejection_reason && req.status === 'rejected'
        ? `<div class="so-note" style="background: rgba(255,71,87,0.1); color: var(--clr-danger);"><span class="material-symbols-sharp">info</span> Reason: ${esc(req.rejection_reason)}</div>`
        : '';

      card.innerHTML = `
        <div class="so-card-header">
          <div class="so-table-tag">
            <span class="material-symbols-sharp">table_restaurant</span>
            ${esc(req.table_name)}
          </div>
          <span class="so-status-badge so-badge-${req.status}">
            <span class="material-symbols-sharp">${statusIcons[req.status]}</span>
            ${statusLabels[req.status]}
          </span>
        </div>

        <div class="so-customer-info">
          <div class="so-avatar">${getInitials(req.customer_name || '?')}</div>
          <div>
            <div class="so-customer-name">${esc(req.customer_name || 'Anonymous')}</div>
            <div class="so-customer-phone">
              ${req.phone ? `<span class="material-symbols-sharp" style="font-size:0.9rem">phone</span> ${esc(req.phone)}` : 'No phone'}
            </div>
          </div>
        </div>

        ${req.note ? `<div class="so-note"><span class="material-symbols-sharp">sticky_note_2</span> ${esc(req.note)}</div>` : ''}

        <ul class="so-items-list">${itemsHtml}</ul>

        <div class="so-total-row">
          <span>Total</span>
          <span class="so-total-amount">Rs. ${parseFloat(req.total_price).toLocaleString()}</span>
        </div>

        <div class="so-meta-row">
          <span class="so-payment-tag">
            <span class="material-symbols-sharp">payments</span>
            ${esc(req.payment_method || 'Cash')}
          </span>
          <span class="so-time">${formatTime(req.created_at)}</span>
        </div>

        ${orderCodeHtml}
        ${rejectReasonHtml}
        ${buttons}
      `;
      return card;
    }

    function updateStatus(id, action, btn) {
      if (action === 'approve') {
        openDeleteConfirm({
          type: 'success',
          title: 'Approve Order?',
          message: 'This will send the order to the kitchen and print an order slip.',
          confirmText: 'Approve',
          onConfirm: () => { processStatusUpdate(id, 'approve', null, btn); }
        });
      } else if (action === 'reject') {
        openDeleteConfirm({
          type: 'danger',
          title: 'Reject Order?',
          message: 'Please provide a reason for rejecting this order:',
          confirmText: 'Reject',
          showInput: true,
          requireInput: true,
          inputPlaceholder: 'Reason for rejection (e.g. out of stock, closed)...',
          onConfirm: (reason) => { processStatusUpdate(id, 'reject', reason, btn); }
        });
      }
    }

    function processStatusUpdate(id, action, reason, btn) {
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="material-symbols-sharp qrm-spin">progress_activity</span>';
      const card = document.getElementById('order-card-' + id);
      if (card) card.style.opacity = '0.6';

      fetch('api_qr_requests.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, request_id: id, reason })
      }).then(r => r.json()).then(data => {
        if (data.success) {
          showQrToast(action === 'approve' ? 'Order approved & sent to kitchen!' : 'Order rejected.', action === 'approve' ? 'success' : 'warning');
          loadRequests();
        } else {
          showQrToast('Error: ' + (data.message || 'Unknown error'), 'error');
          btn.disabled = false;
          btn.innerHTML = original;
          if (card) card.style.opacity = '1';
        }
      }).catch(() => {
        showQrToast('Network error', 'error');
        btn.disabled = false;
        btn.innerHTML = original;
        if (card) card.style.opacity = '1';
      });
    }

    // Tab filtering
    document.getElementById('soTabs').addEventListener('click', e => {
      const tab = e.target.closest('.so-tab');
      if (!tab) return;
      document.querySelectorAll('.so-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      activeFilter = tab.dataset.filter;
      applyFilter();
    });

    function getInitials(name) {
      return name.split(/\s+/).map(w => w[0] || '').join('').slice(0, 2).toUpperCase() || '?';
    }

    function formatTime(ts) {
      return new Date(ts).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }

    function showQrToast(msg, type = 'info') {
      const c = document.getElementById('toastContainer');
      const t = document.createElement('div');
      t.className = `toast toast-${type}`;
      t.innerHTML = `<div class="toast-content"><span class="toast-message">${msg}</span><button class="toast-close">&times;</button></div>`;
      c.appendChild(t);
      t.querySelector('.toast-close').onclick = () => t.remove();
      setTimeout(() => t.remove(), 4000);
    }

    function esc(s) {
      return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    document.addEventListener('DOMContentLoaded', loadRequests);
  </script>

  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
</body>
</html>
