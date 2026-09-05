<?php
/**
 * Chef — Ingredients / kitchen stock.
 * Add, edit (reuses the add form), quick ±1 adjust, delete — via api/ingredients.php.
 * Status (Available / Low / Out) is always derived server-side from quantity.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'chef');
$pageTitle = 'Ingredients';

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
        <h1>Ingredients</h1>
        <p>Track kitchen stock. Items at or below their low threshold are flagged automatically.</p>
    </div>
</div>

<div class="panel-card">
    <h2 id="ingFormTitle">Add ingredient</h2>
    <form id="ingForm">
        <input type="hidden" id="ingId" value="">
        <div style="display:flex; gap:1rem; flex-wrap:wrap;">
            <div class="panel-field" style="flex:2; min-width:180px;">
                <label for="ingName">Name</label>
                <input type="text" id="ingName" placeholder="e.g. Paneer" required>
            </div>
            <div class="panel-field" style="flex:1; min-width:110px;">
                <label for="ingUnit">Unit</label>
                <input type="text" id="ingUnit" placeholder="kg, pcs, L" value="unit">
            </div>
            <div class="panel-field" style="flex:1; min-width:110px;">
                <label for="ingQty">Quantity</label>
                <input type="number" id="ingQty" step="0.01" min="0" value="0">
            </div>
            <div class="panel-field" style="flex:1; min-width:110px;">
                <label for="ingLow">Low at</label>
                <input type="number" id="ingLow" step="0.01" min="0" value="0">
            </div>
        </div>
        <div style="display:flex; gap:0.6rem;">
            <button type="submit" class="qrm-btn qrm-btn-primary" id="ingSubmit">
                <span class="material-symbols-sharp">add</span> Add ingredient
            </button>
            <button type="button" class="qrm-btn qrm-btn-secondary" id="ingCancel" style="display:none;">Cancel</button>
        </div>
    </form>
</div>

<div class="panel-table-wrap">
    <table class="panel-table">
        <thead>
            <tr>
                <th>Ingredient</th>
                <th>In stock</th>
                <th>Low at</th>
                <th>Status</th>
                <th>Adjust</th>
                <th></th>
            </tr>
        </thead>
        <tbody id="ingBody">
            <tr><td colspan="6" style="text-align:center;color:var(--clr-dark-variant);">Loading…</td></tr>
        </tbody>
    </table>
</div>

<script>
(function () {
    const CSRF = window.PANEL_CSRF || '';
    const API  = 'api/ingredients.php';
    const body = document.getElementById('ingBody');
    const form = document.getElementById('ingForm');
    const idEl = document.getElementById('ingId');
    const nameEl = document.getElementById('ingName');
    const unitEl = document.getElementById('ingUnit');
    const qtyEl = document.getElementById('ingQty');
    const lowEl = document.getElementById('ingLow');
    const submitBtn = document.getElementById('ingSubmit');
    const cancelBtn = document.getElementById('ingCancel');
    const formTitle = document.getElementById('ingFormTitle');

    const PILL = { Available: 's-available', Low: 's-low', Out: 's-out' };

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function toast(m, t) { if (window.showToast) window.showToast(m, t); }
    function num(n) { const v = parseFloat(n); return Number.isInteger(v) ? String(v) : v.toFixed(2); }

    function post(bodyObj) {
        return fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(Object.assign({ csrf: CSRF }, bodyObj))
        }).then(r => r.json());
    }

    function render(items) {
        if (!items || !items.length) {
            body.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--clr-dark-variant);">No ingredients yet.</td></tr>';
            return;
        }
        body.innerHTML = items.map(it => `
            <tr data-id="${it.id}" data-name="${esc(it.name)}" data-unit="${esc(it.unit)}" data-qty="${it.quantity}" data-low="${it.low_threshold}">
                <td><strong>${esc(it.name)}</strong></td>
                <td>${num(it.quantity)} ${esc(it.unit)}</td>
                <td>${num(it.low_threshold)}</td>
                <td><span class="stock-pill ${PILL[it.status] || ''}">${esc(it.status)}</span></td>
                <td>
                    <button type="button" class="qrm-btn qrm-btn-secondary ing-adj" data-delta="-1" aria-label="Decrease"><span class="material-symbols-sharp">remove</span></button>
                    <button type="button" class="qrm-btn qrm-btn-secondary ing-adj" data-delta="1" aria-label="Increase"><span class="material-symbols-sharp">add</span></button>
                </td>
                <td>
                    <button type="button" class="qrm-btn qrm-btn-secondary ing-edit" aria-label="Edit"><span class="material-symbols-sharp">edit</span></button>
                    <button type="button" class="qrm-btn qrm-btn-danger ing-del" aria-label="Delete"><span class="material-symbols-sharp">delete</span></button>
                </td>
            </tr>`).join('');
    }

    function load() {
        fetch(API, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => { if (d && d.success) render(d.ingredients); })
            .catch(() => { body.innerHTML = '<tr><td colspan="6" style="text-align:center;">Could not load ingredients.</td></tr>'; });
    }

    function resetForm() {
        idEl.value = '';
        form.reset();
        unitEl.value = 'unit';
        formTitle.textContent = 'Add ingredient';
        submitBtn.innerHTML = '<span class="material-symbols-sharp">add</span> Add ingredient';
        cancelBtn.style.display = 'none';
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const name = nameEl.value.trim();
        if (!name) { toast('Name is required', 'error'); return; }
        const payload = {
            action: idEl.value ? 'update' : 'add',
            name: name,
            unit: unitEl.value.trim() || 'unit',
            quantity: qtyEl.value || 0,
            low_threshold: lowEl.value || 0
        };
        if (idEl.value) payload.id = idEl.value;
        post(payload).then(d => {
            if (d && d.success) { toast(idEl.value ? 'Ingredient updated' : 'Ingredient added', 'success'); resetForm(); load(); }
            else toast((d && d.message) || 'Save failed', 'error');
        }).catch(() => toast('Network error', 'error'));
    });

    cancelBtn.addEventListener('click', resetForm);

    body.addEventListener('click', function (e) {
        const row = e.target.closest('tr[data-id]');
        if (!row) return;
        const id = row.getAttribute('data-id');

        if (e.target.closest('.ing-adj')) {
            const delta = parseFloat(e.target.closest('.ing-adj').getAttribute('data-delta'));
            post({ action: 'adjust', id: id, delta: delta }).then(d => {
                if (d && d.success) load(); else toast((d && d.message) || 'Update failed', 'error');
            }).catch(() => toast('Network error', 'error'));
            return;
        }

        if (e.target.closest('.ing-edit')) {
            idEl.value = id;
            nameEl.value = row.getAttribute('data-name');
            unitEl.value = row.getAttribute('data-unit');
            qtyEl.value = row.getAttribute('data-qty');
            lowEl.value = row.getAttribute('data-low');
            formTitle.textContent = 'Edit ingredient';
            submitBtn.innerHTML = '<span class="material-symbols-sharp">save</span> Update';
            cancelBtn.style.display = '';
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        if (e.target.closest('.ing-del')) {
            const name = row.getAttribute('data-name');
            window.openDeleteConfirm({
                title: 'Delete ingredient?',
                message: 'Remove "' + name + '" from stock tracking.',
                confirmText: 'Delete',
                onConfirm: function () {
                    post({ action: 'delete', id: id }).then(d => {
                        if (d && d.success) { toast('Ingredient removed', 'success'); load(); }
                        else toast((d && d.message) || 'Delete failed', 'error');
                    }).catch(() => toast('Network error', 'error'));
                }
            });
        }
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
