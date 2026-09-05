<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'chef');

$pageTitle = 'Menu Availability';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> · Mero Bhoj Kitchen</title>
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

<div class="panel-page-header">
    <div>
        <h1>Menu Availability</h1>
        <p>Mark items available, low or out — this instantly updates the customer menu and blocks new orders for out-of-stock dishes.</p>
    </div>
</div>

<div id="availRoot">
    <div class="kds-col-empty">Loading menu…</div>
</div>

<script>
(function () {
    const CSRF = window.PANEL_CSRF || '';
    const API  = 'api/menu_availability.php';
    const root = document.getElementById('availRoot');
    const STATES = [
        { val: 'In Stock',     label: 'Available' },
        { val: 'Low Stock',    label: 'Low' },
        { val: 'Out of Stock', label: 'Out' }
    ];
    const CAT_LABELS = { starter: 'Starters', breakfast: 'Breakfast', lunch: 'Lunch', dinner: 'Dinner', other: 'Other' };

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function toast(m, t) { if (window.showToast) window.showToast(m, t); }

    function segHTML(item) {
        return STATES.map(s =>
            `<button type="button" data-val="${s.val}" class="${item.status === s.val ? 'active' : ''}">${s.label}</button>`
        ).join('');
    }

    function cardHTML(item) {
        const img = item.image
            ? `<img src="${esc(item.image)}" alt="${esc(item.name)}" onerror="this.style.display='none'">`
            : `<div style="width:3rem;height:3rem;border-radius:.5rem;display:grid;place-items:center;background:var(--clr-light);color:var(--clr-primary)"><span class="material-symbols-sharp">restaurant</span></div>`;
        return `<div class="avail-card" data-id="${item.menu_id}">
            ${img}
            <div class="avail-info">
                <div class="avail-name">${esc(item.name)}</div>
                <div class="avail-price">Rs. ${esc(item.price)}</div>
            </div>
            <div class="avail-seg">${segHTML(item)}</div>
        </div>`;
    }

    function render(categories) {
        const cats = Object.keys(categories || {});
        if (!cats.length) { root.innerHTML = '<div class="kds-col-empty">No menu items found.</div>'; return; }
        root.innerHTML = cats.map(cat => {
            const title = CAT_LABELS[cat] || (cat.charAt(0).toUpperCase() + cat.slice(1));
            const cards = categories[cat].map(cardHTML).join('');
            return `<div class="avail-group">
                <div class="avail-group-title"><span class="material-symbols-sharp">restaurant_menu</span> ${esc(title)}</div>
                <div class="avail-grid">${cards}</div>
            </div>`;
        }).join('');
    }

    function load() {
        fetch(API + '?action=list', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => { if (d && d.success) render(d.categories); else root.innerHTML = '<div class="kds-col-empty">Could not load menu.</div>'; })
            .catch(() => { root.innerHTML = '<div class="kds-col-empty">Could not load menu.</div>'; });
    }

    function setStatus(card, seg, btn, menuId, val) {
        const prevActive = seg.querySelector('button.active');
        seg.querySelectorAll('button').forEach(b => { b.disabled = true; b.classList.remove('active'); });
        btn.classList.add('active');
        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ menu_id: menuId, status: val, csrf: CSRF })
        })
            .then(r => r.json())
            .then(d => {
                if (d && d.success) { toast('Updated to ' + val, 'success'); }
                else {
                    toast((d && d.message) || 'Update failed', 'error');
                    btn.classList.remove('active');
                    if (prevActive) prevActive.classList.add('active');
                }
            })
            .catch(() => {
                toast('Network error', 'error');
                btn.classList.remove('active');
                if (prevActive) prevActive.classList.add('active');
            })
            .finally(() => { seg.querySelectorAll('button').forEach(b => { b.disabled = false; }); });
    }

    root.addEventListener('click', function (e) {
        const btn = e.target.closest('.avail-seg button');
        if (!btn || btn.classList.contains('active')) return;
        const card = btn.closest('.avail-card');
        const seg  = btn.closest('.avail-seg');
        setStatus(card, seg, btn, parseInt(card.getAttribute('data-id'), 10), btn.getAttribute('data-val'));
    });

    load();
})();
</script>

    </main>
  </div>
  <div class="toast-container" id="toastContainer"></div>
  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
  <script src="../assets/js/panel_notifications.js?v=<?= filemtime(__DIR__ . '/../assets/js/panel_notifications.js') ?>"></script>
</body>
</html>
