<?php
/**
 * Chef — full notifications list (role_notifications WHERE role='chef').
 * Reuses api/notifications.php (list + mark read). The topbar bell shows the
 * same stream; this is the roomier full-page view.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'chef');
$pageTitle = 'Notifications';

$pageTitle = $pageTitle ?? 'Kitchen';
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
?>

<div class="panel-page-header">
    <div>
        <h1>Notifications</h1>
        <p>Kitchen alerts — order changes, stock warnings and admin messages.</p>
    </div>
    <button type="button" class="qrm-btn qrm-btn-primary" id="markAllBtn">
        <span class="material-symbols-sharp">done_all</span> Mark all read
    </button>
</div>

<div class="panel-table-wrap" style="overflow:visible;">
    <div id="notifyList">
        <div class="panel-bell-empty">Loading…</div>
    </div>
</div>

<script>
(function () {
    const CSRF = window.PANEL_CSRF || '';
    const API  = 'api/notifications.php';
    const list = document.getElementById('notifyList');
    const markAllBtn = document.getElementById('markAllBtn');
    const ICON = { menu: 'restaurant_menu', order: 'receipt_long', general: 'notifications' };

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function toast(m, t) { if (window.showToast) window.showToast(m, t); }
    function when(ts) {
        const d = new Date(String(ts || '').replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        const secs = Math.floor((Date.now() - d.getTime()) / 1000);
        if (secs < 60) return 'just now';
        if (secs < 3600) return Math.floor(secs / 60) + 'm ago';
        if (secs < 86400) return Math.floor(secs / 3600) + 'h ago';
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    function post(body) {
        return fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(Object.assign({ csrf: CSRF }, body))
        }).then(r => r.json());
    }

    function render(items) {
        if (!items || !items.length) {
            list.innerHTML = '<div class="panel-bell-empty">You\'re all caught up 🎉</div>';
            return;
        }
        list.innerHTML = items.map(n => {
            const icon = ICON[n.type] || 'notifications';
            return `<div class="panel-bell-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}" data-url="${esc(n.url || '')}">
                <div class="pbi-icon"><span class="material-symbols-sharp">${icon}</span></div>
                <div class="pbi-body">
                    <div class="pbi-title">${esc(n.title)}</div>
                    ${n.message ? `<div class="pbi-msg">${esc(n.message)}</div>` : ''}
                    <div class="pbi-time">${esc(when(n.created_at))}</div>
                </div>
            </div>`;
        }).join('');
    }

    function load() {
        fetch(API + '?action=list&limit=100', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => { if (d && d.success) render(d.notifications); else list.innerHTML = '<div class="panel-bell-empty">Could not load notifications.</div>'; })
            .catch(() => { list.innerHTML = '<div class="panel-bell-empty">Could not load notifications.</div>'; });
    }

    list.addEventListener('click', function (e) {
        const item = e.target.closest('.panel-bell-item');
        if (!item) return;
        const id = item.getAttribute('data-id');
        const url = item.getAttribute('data-url');
        item.classList.remove('unread');
        post({ action: 'read', id: id }).finally(() => { if (url) window.location.href = url; });
    });

    markAllBtn.addEventListener('click', function () {
        post({ action: 'read_all' }).then(() => { toast('All marked read', 'success'); load(); });
    });

    load();
})();
</script>

    </main>
  </div>
  <div class="toast-container" id="toastContainer"></div>
  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
  <script src="../assets/js/panel_notifications.js?v=<?= filemtime(__DIR__ . '/../assets/js/panel_notifications.js') ?>"></script>
<?php foreach (($pageScripts ?? []) as $src): ?>
  <script src="<?= htmlspecialchars($src) ?>?v=<?= @filemtime(__DIR__ . '/' . ltrim($src, '/')) ?: time() ?>"></script>
<?php endforeach; ?>
</body>
</html>
