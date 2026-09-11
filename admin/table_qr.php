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
  <title>Table QR Management - Mero Bhoj</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  <!-- QRCode.js CDN -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body class="admin-page">
  <?php include_once __DIR__ . '/topbar.php'; ?>
  <div class="container">
    <?php include_once __DIR__ . '/sidebar.php'; ?>

    <main class="admin-page-main">
      <!-- Page Header -->
      <div class="qrm-page-header">
        <div>
          <h1>Table QR Management</h1>
          <p>Generate QR codes for tables. Customers scan to order directly.</p>
        </div>
        <button class="qrm-btn qrm-btn-primary" id="generateAllBtn">
          <span class="material-symbols-sharp">qr_code_2</span>
          Generate All QRs
        </button>
      </div>

      <!-- Stats Row -->
      <div class="qrm-stats-row" id="qrmStatsRow">
        <div class="qrm-stat-card">
          <span class="material-symbols-sharp qrm-stat-icon" style="color:var(--clr-primary)">table_restaurant</span>
          <div>
            <div class="qrm-stat-value" id="statTotal">—</div>
            <div class="qrm-stat-label">Total Tables</div>
          </div>
        </div>
        <div class="qrm-stat-card">
          <span class="material-symbols-sharp qrm-stat-icon" style="color:var(--clr-success)">qr_code</span>
          <div>
            <div class="qrm-stat-value" id="statActive">—</div>
            <div class="qrm-stat-label">Active QRs</div>
          </div>
        </div>
        <div class="qrm-stat-card">
          <span class="material-symbols-sharp qrm-stat-icon" style="color:var(--clr-warnig)">link_off</span>
          <div>
            <div class="qrm-stat-value" id="statInactive">—</div>
            <div class="qrm-stat-label">No QR Yet</div>
          </div>
        </div>
      </div>

      <!-- Table Cards Grid -->
      <div class="qrm-grid" id="tablesContainer">
        <div class="qrm-loading">
          <span class="material-symbols-sharp qrm-spinner">progress_activity</span>
          <p>Loading tables...</p>
        </div>
      </div>
    </main>
  </div>

  <!-- Toast Container -->
  <div class="toast-container" id="toastContainer"></div>

  <script>
    const BASE_ORDER_URL = window.location.origin + '/Merobhoj/order/';

    function loadTables() {
      const container = document.getElementById('tablesContainer');
      container.classList.add('is-refreshing');
      return fetch('api/api_qr_tables.php?action=list')
        .then(r => r.json())
        .then(data => {
          if (data.success) renderTables(data.tables);
          else showQrToast(data.message || 'Failed to load tables.', 'error');
        })
        .catch(() => showQrToast('Failed to load tables.', 'error'))
        .finally(() => container.classList.remove('is-refreshing'));
    }

    function renderTables(tables) {
      const container = document.getElementById('tablesContainer');
      const fragment = document.createDocumentFragment();
      const qrQueue = [];

      let active = 0, inactive = 0;
      tables.forEach(table => {
        if (table.qr_token) active++; else inactive++;
        fragment.appendChild(buildTableCard(table));
        if (table.qr_token) qrQueue.push(table);
      });
      container.replaceChildren(fragment);
      renderQrQueue(qrQueue);

      document.getElementById('statTotal').textContent = tables.length;
      document.getElementById('statActive').textContent = active;
      document.getElementById('statInactive').textContent = inactive;
    }

    // QRCode.js is synchronous. Render one code per idle frame so the page
    // becomes interactive immediately, even with a large table list.
    function renderQrQueue(queue) {
      const renderNext = () => {
        const table = queue.shift();
        if (!table) return;
        const el = document.getElementById('qr-canvas-' + table.id);
        if (el && !el.hasChildNodes()) {
          new QRCode(el, {
            text: BASE_ORDER_URL + table.qr_token,
            width: 140, height: 140,
            colorDark: '#1e293b', colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
          });
        }
        if (queue.length) {
          if (window.requestIdleCallback) {
            window.requestIdleCallback(renderNext, { timeout: 120 });
          } else {
            window.requestAnimationFrame(renderNext);
          }
        }
      };
      if (queue.length) {
        if (window.requestIdleCallback) {
          window.requestIdleCallback(renderNext, { timeout: 120 });
        } else {
          window.requestAnimationFrame(renderNext);
        }
      }
    }

    function buildTableCard(table) {
      const card = document.createElement('div');
      card.className = 'qrm-card' + (table.qr_token ? ' has-qr' : ' no-qr');
      card.id = 'card-' + table.id;
      const url = table.qr_token ? BASE_ORDER_URL + table.qr_token : '';

      card.innerHTML = `
        <div class="qrm-card-header">
          <div class="qrm-table-info">
            <span class="material-symbols-sharp qrm-table-icon">table_restaurant</span>
            <div>
              <h3 class="qrm-table-name">${esc(table.table_name)}</h3>
              <span class="qrm-table-cap">Capacity: ${table.capacity}</span>
            </div>
          </div>
          <span class="qrm-badge ${table.qr_token ? 'badge-active' : 'badge-none'}">
            ${table.qr_token ? 'Active' : 'No QR'}
          </span>
        </div>

        <div class="qrm-qr-area">
          ${table.qr_token
            ? `<div class="qrm-qr-wrapper"><div id="qr-canvas-${table.id}" class="qrm-qr-canvas"></div></div>
               <p class="qrm-qr-url">${url}</p>`
            : `<div class="qrm-no-qr-placeholder">
                <span class="material-symbols-sharp">qr_code_2</span>
                <p>No QR code yet</p>
               </div>`
          }
        </div>

        <div class="qrm-card-actions">
          ${table.qr_token ? `
            <button class="qrm-btn qrm-btn-sm qrm-btn-success" onclick="copyLink('${url}', this)">
              <span class="material-symbols-sharp">content_copy</span> Copy
            </button>
            <button class="qrm-btn qrm-btn-sm qrm-btn-danger" onclick="printQr(${table.id})">
              <span class="material-symbols-sharp">print</span> Print
            </button>
            <button class="qrm-btn qrm-btn-sm qrm-btn-warning" onclick="regenerate(${table.id}, this)">
              <span class="material-symbols-sharp">refresh</span> Regenerate
            </button>
          ` : `
            <button class="qrm-btn qrm-btn-sm qrm-btn-primary" style="width:100%" onclick="regenerate(${table.id}, this)">
              <span class="material-symbols-sharp">add_circle</span> Generate QR
            </button>
          `}
        </div>
      `;
      return card;
    }

    function printQr(tableId) {
      const qr = document.getElementById('qr-canvas-' + tableId);
      const tableName = document.querySelector('#card-' + tableId + ' .qrm-table-name')?.textContent || 'Table';
      const image = qr?.querySelector('img')?.src || qr?.querySelector('canvas')?.toDataURL();
      if (!image) {
        showQrToast('QR code is still loading.', 'info');
        return;
      }
      const printWindow = window.open('', '_blank', 'width=500,height=600');
      if (!printWindow) return;
      printWindow.document.write(`<title>${esc(tableName)} QR Code</title><style>body{font-family:Arial;text-align:center;padding:32px}img{width:280px;height:280px}h1{font-size:24px}</style><h1>${esc(tableName)}</h1><img src="${image}" alt="QR code for ${esc(tableName)}">`);
      printWindow.document.close();
      printWindow.focus();
      printWindow.onload = () => { printWindow.print(); printWindow.close(); };
    }

    function regenerate(tableId, btn) {
      if (!confirm('Regenerating will invalidate the current QR code. Proceed?')) return;
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="material-symbols-sharp qrm-spin">progress_activity</span>';
      fetch('api/api_qr_tables.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'regenerate', table_id: tableId })
      }).then(r => r.json()).then(data => {
        if (data.success) {
          showQrToast('QR code regenerated!', 'success');
          loadTables();
        } else {
          showQrToast(data.message || 'Error', 'error');
          btn.disabled = false;
          btn.innerHTML = original;
        }
      }).catch(() => {
        showQrToast('Network error', 'error');
        btn.disabled = false;
        btn.innerHTML = original;
      });
    }

    function copyLink(link, btn) {
      navigator.clipboard.writeText(link).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '<span class="material-symbols-sharp">check_circle</span> Copied!';
        btn.classList.add('qrm-btn-copied');
        showQrToast('Link copied to clipboard!', 'success');
        setTimeout(() => { btn.innerHTML = original; btn.classList.remove('qrm-btn-copied'); }, 2000);
      }).catch(() => showQrToast('Could not copy link', 'error'));
    }

    document.getElementById('generateAllBtn').addEventListener('click', () => {
      if (!confirm('Generate QR codes for all tables that don\'t have one yet?')) return;
      const button = document.getElementById('generateAllBtn');
      const original = button.innerHTML;
      button.disabled = true;
      button.innerHTML = '<span class="material-symbols-sharp qrm-spin">progress_activity</span> Generating...';
      fetch('api/api_qr_tables.php?action=list')
        .then(r => r.json()).then(data => {
          if (!data.success) {
            showQrToast(data.message || 'Could not read tables.', 'error');
            return;
          }
          const noQr = data.tables.filter(t => !t.qr_token);
          if (!noQr.length) { showQrToast('All tables already have QR codes!', 'info'); return; }
          Promise.all(noQr.map(t =>
            fetch('api/api_qr_tables.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ action: 'regenerate', table_id: t.id })
            }).then(r => r.json())
          )).then(() => {
            showQrToast(`Generated QR for ${noQr.length} table(s)!`, 'success');
            loadTables();
          }).catch(() => showQrToast('Some QR codes could not be generated.', 'error'));
        })
        .catch(() => showQrToast('Network error while generating QR codes.', 'error'))
        .finally(() => {
          button.disabled = false;
          button.innerHTML = original;
        });
    });

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
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    document.addEventListener('DOMContentLoaded', loadTables);
  </script>

  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
</body>
</html>
