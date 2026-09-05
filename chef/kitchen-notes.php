<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'chef');

$pageTitle = 'Kitchen Notes';
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
        <h1>Kitchen Notes</h1>
        <p>Shared reminders for the kitchen — 86'd items, prep notes, allergy alerts.</p>
    </div>
</div>

<div class="panel-card">
    <h2>Add a note</h2>
    <form id="noteForm">
        <div class="panel-field">
            <label for="noteText">Note</label>
            <textarea id="noteText" name="note" placeholder="e.g. Out of paneer — suggest chicken instead" required></textarea>
        </div>
        <div style="display:flex; gap:1rem; flex-wrap:wrap;">
            <div class="panel-field" style="flex:1; min-width:150px;">
                <label for="noteScope">Scope</label>
                <select id="noteScope" name="scope">
                    <option value="kitchen">Kitchen (general)</option>
                    <option value="order">Order</option>
                    <option value="table">Table</option>
                    <option value="menu">Menu item</option>
                </select>
            </div>
            <div class="panel-field" style="flex:1; min-width:150px;">
                <label for="noteRef">Reference <span style="text-transform:none;font-weight:400;">(optional — order #, table #…)</span></label>
                <input type="text" id="noteRef" name="ref_id" placeholder="e.g. Table 5">
            </div>
        </div>
        <button type="submit" class="qrm-btn qrm-btn-primary">
            <span class="material-symbols-sharp">add</span> Add note
        </button>
    </form>
</div>

<div id="noteList">
    <div class="kds-col-empty">Loading notes…</div>
</div>

<script>
(function () {
    const CSRF = window.PANEL_CSRF || '';
    const API  = 'api/kitchen_notes.php';
    const form = document.getElementById('noteForm');
    const list = document.getElementById('noteList');

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function toast(m, t) { if (window.showToast) window.showToast(m, t); }
    function when(ts) {
        const d = new Date(String(ts || '').replace(' ', 'T'));
        return isNaN(d.getTime()) ? '' : d.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function post(body) {
        return fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(Object.assign({ csrf: CSRF }, body))
        }).then(r => r.json());
    }

    function render(notes) {
        if (!notes || !notes.length) { list.innerHTML = '<div class="kds-col-empty">No notes yet.</div>'; return; }
        list.innerHTML = notes.map(n => `
            <div class="note-card" data-id="${n.id}">
                <div>
                    <span class="note-scope">${esc(n.scope)}${n.ref_id ? ' · ' + esc(n.ref_id) : ''}</span>
                    <div class="note-body">${esc(n.note)}</div>
                    <div class="note-meta">${esc(n.author)} · ${esc(when(n.created_at))}</div>
                </div>
                <button type="button" class="qrm-btn qrm-btn-danger note-del" data-id="${n.id}" aria-label="Delete note">
                    <span class="material-symbols-sharp">delete</span>
                </button>
            </div>`).join('');
    }

    function load() {
        fetch(API, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => { if (d && d.success) render(d.notes); else list.innerHTML = '<div class="kds-col-empty">Could not load notes.</div>'; })
            .catch(() => { list.innerHTML = '<div class="kds-col-empty">Could not load notes.</div>'; });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const note = document.getElementById('noteText').value.trim();
        if (!note) { toast('Note text is required', 'error'); return; }
        post({
            action: 'add',
            note: note,
            scope: document.getElementById('noteScope').value,
            ref_id: document.getElementById('noteRef').value.trim()
        }).then(d => {
            if (d && d.success) { form.reset(); toast('Note added', 'success'); load(); }
            else toast((d && d.message) || 'Failed to add note', 'error');
        }).catch(() => toast('Network error', 'error'));
    });

    list.addEventListener('click', function (e) {
        const btn = e.target.closest('.note-del');
        if (!btn) return;
        const id = btn.getAttribute('data-id');
        window.openDeleteConfirm({
            title: 'Delete note?',
            message: 'This note will be removed for everyone.',
            confirmText: 'Delete',
            onConfirm: function () {
                post({ action: 'delete', id: id }).then(d => {
                    if (d && d.success) { toast('Note deleted', 'success'); load(); }
                    else toast((d && d.message) || 'Delete failed', 'error');
                }).catch(() => toast('Network error', 'error'));
            }
        });
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
