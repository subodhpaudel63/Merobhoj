<?php
/**
 * Admin — expense log.
 *
 * Records money going out: date, category, amount, vendor, payment method and an
 * optional bill reference. Deliberately a plain log with no approval workflow, so
 * every row inside a date range counts toward the books — which is what makes
 * admin/finance_data.php's expense query (and therefore the Finance page's
 * Taxable Expenses, Input VAT, Net Profit and 5000 · Cost of Goods Sold ledger)
 * finally report real figures.
 *
 * The stat cards, the category breakdown and the table are all driven by one
 * request against the same filter, so they can never disagree about a range.
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';

$csrf = panel_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Expenses - Mero Bhoj</title>
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
          <h1>Expenses</h1>
          <p>Every rupee going out. Whatever you record here flows straight into the Finance page.</p>
        </div>
        <div class="exp-header-actions">
          <button type="button" class="qrm-btn qrm-btn-secondary" id="exportBtn">
            <span class="material-symbols-sharp">download</span> Export CSV
          </button>
          <button type="button" class="qrm-btn qrm-btn-primary" id="addBtn">
            <span class="material-symbols-sharp">add</span> Add expense
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="exp-filterbar">
        <div class="exp-quick" id="quickRanges">
          <button type="button" class="exp-quick-btn" data-range="today">Today</button>
          <button type="button" class="exp-quick-btn" data-range="week">This week</button>
          <button type="button" class="exp-quick-btn is-active" data-range="month">This month</button>
          <button type="button" class="exp-quick-btn" data-range="lastmonth">Last month</button>
          <button type="button" class="exp-quick-btn" data-range="year">This year</button>
        </div>

        <div class="exp-filter-fields">
          <div class="exp-field">
            <label for="fFrom">From</label>
            <input type="date" id="fFrom">
          </div>
          <div class="exp-field">
            <label for="fTo">To</label>
            <input type="date" id="fTo">
          </div>
          <div class="exp-field">
            <label for="fCategory">Category</label>
            <select id="fCategory"><option value="">All categories</option></select>
          </div>
          <div class="exp-field">
            <label for="fMethod">Paid by</label>
            <select id="fMethod"><option value="">All methods</option></select>
          </div>
          <div class="exp-field exp-field-grow">
            <label for="fSearch">Search</label>
            <input type="search" id="fSearch" placeholder="Description, vendor or bill no.">
          </div>
          <div class="exp-field exp-field-actions">
            <button type="button" class="qrm-btn qrm-btn-primary" id="applyBtn">
              <span class="material-symbols-sharp">filter_alt</span> Apply
            </button>
            <button type="button" class="qrm-btn qrm-btn-secondary" id="resetBtn">
              <span class="material-symbols-sharp">restart_alt</span> Reset
            </button>
          </div>
        </div>
      </div>

      <!-- Stats for the active filter -->
      <div class="qrm-stats-row">
        <div class="qrm-stat-card">
          <span class="material-symbols-sharp qrm-stat-icon" style="color:var(--clr-danger)">payments</span>
          <div>
            <div class="qrm-stat-value" id="statTotal">—</div>
            <div class="qrm-stat-label">Total spent</div>
          </div>
        </div>
        <div class="qrm-stat-card">
          <span class="material-symbols-sharp qrm-stat-icon" style="color:var(--clr-primary)">receipt_long</span>
          <div>
            <div class="qrm-stat-value" id="statCount">—</div>
            <div class="qrm-stat-label">Entries</div>
          </div>
        </div>
        <div class="qrm-stat-card">
          <span class="material-symbols-sharp qrm-stat-icon" style="color:var(--clr-warnig)">category</span>
          <div>
            <div class="qrm-stat-value exp-stat-sm" id="statTopCat">—</div>
            <div class="qrm-stat-label" id="statTopCatLabel">Biggest category</div>
          </div>
        </div>
        <div class="qrm-stat-card">
          <span class="material-symbols-sharp qrm-stat-icon" style="color:var(--clr-info-dark, #475569)">timeline</span>
          <div>
            <div class="qrm-stat-value exp-stat-sm" id="statDaily">—</div>
            <div class="qrm-stat-label" id="statDailyLabel">Average per day</div>
          </div>
        </div>
      </div>

      <!-- Where the money went -->
      <div class="rider-section-head">
        <h2><span class="material-symbols-sharp">donut_small</span> Where the money went</h2>
        <span class="rider-hint" id="breakdownHint"></span>
      </div>
      <div class="exp-breakdown" id="breakdown">
        <div class="exp-empty">Loading…</div>
      </div>

      <!-- The log -->
      <div class="rider-section-head">
        <h2><span class="material-symbols-sharp">list_alt</span> Expense log</h2>
        <span class="rider-hint" id="logHint"></span>
      </div>
      <div class="panel-table-wrap">
        <table class="panel-table">
          <thead>
            <tr>
              <th>Date</th><th>Description</th><th>Category</th><th>Vendor</th>
              <th>Paid by</th><th>Bill no.</th><th style="text-align:right;">Amount</th>
              <th>Recorded by</th><th style="width:100px;">Actions</th>
            </tr>
          </thead>
          <tbody id="logBody">
            <tr><td colspan="9" style="text-align:center;padding:1.5rem;">Loading…</td></tr>
          </tbody>
          <tfoot id="logFoot" hidden>
            <tr class="exp-total-row">
              <td colspan="6">Total for this range</td>
              <td style="text-align:right;" id="footTotal">—</td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>

    </main>
  </div>

  <!-- Add / edit -->
  <div class="modal" id="expModal" hidden>
    <div class="modal-box exp-modal-box">
      <button type="button" class="close" id="modalClose" aria-label="Close">
        <span class="material-symbols-sharp">close</span>
      </button>
      <h2 id="modalTitle">Add expense</h2>
      <p id="modalSub">Record something the restaurant paid for.</p>

      <form id="expForm" novalidate>
        <input type="hidden" id="fId">
        <div class="exp-form-grid">
          <label class="exp-col-full">Description
            <input id="fTitle" maxlength="150" required placeholder="e.g. Vegetable crate from Kalimati">
          </label>

          <label>Date
            <input type="date" id="fDate" required>
          </label>

          <label>Amount (Rs.)
            <input type="number" id="fAmount" min="0.01" step="0.01" required placeholder="0.00">
          </label>

          <label>Category
            <select id="fCat" required></select>
          </label>

          <label>Paid by
            <select id="fPay" required></select>
          </label>

          <label>Vendor <span class="exp-opt">optional</span>
            <input id="fVendor" maxlength="120" placeholder="Who was paid">
          </label>

          <label>Bill / invoice no. <span class="exp-opt">optional</span>
            <input id="fRef" maxlength="60" placeholder="e.g. INV-2049">
          </label>

          <label class="exp-col-full">Note <span class="exp-opt">optional</span>
            <textarea id="fDesc" maxlength="2000" placeholder="Anything worth remembering about this expense"></textarea>
          </label>
        </div>

        <div class="exp-modal-actions">
          <button type="button" class="qrm-btn qrm-btn-secondary" id="modalCancel">Cancel</button>
          <button type="submit" class="qrm-btn qrm-btn-primary" id="modalSave">
            <span class="material-symbols-sharp">check</span> Save expense
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="toast-container" id="toastContainer"></div>

  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
  <script>
  (function () {
    const CSRF = <?= json_encode($csrf) ?>;
    const API  = 'api/api_expenses.php';

    let rows = [];          // last loaded page of expenses, reused by edit + CSV
    let categories = [];
    let methods    = [];

    /* ---------- helpers ---------- */
    function esc(s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    // Always two decimals, everywhere — so a stat card and the table footer
    // showing the same figure are visibly identical.
    function money(n) {
      return 'Rs. ' + (Number(n) || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2, maximumFractionDigits: 2
      });
    }
    function toast(m, t) {
      if (window.showToast) { window.showToast(m, t || 'info'); }
      else if (t === 'error') { window.alert(m); }
    }
    function slug(s) {
      return String(s || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    }
    function ymd(d) {
      return d.getFullYear() + '-' +
             String(d.getMonth() + 1).padStart(2, '0') + '-' +
             String(d.getDate()).padStart(2, '0');
    }
    function prettyDate(s) {
      const d = new Date(String(s) + 'T00:00:00');
      if (isNaN(d.getTime())) return esc(s);
      return d.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
    }
    function catPill(c) {
      return '<span class="exp-cat cat-' + slug(c) + '">' + esc(c) + '</span>';
    }

    /* ---------- quick ranges ---------- */
    function rangeFor(key) {
      const now = new Date();
      const y = now.getFullYear(), m = now.getMonth();
      switch (key) {
        case 'today':
          return [ymd(now), ymd(now)];
        case 'week': {
          // Week starts Sunday, matching the rest of the panel's date pickers.
          const start = new Date(now);
          start.setDate(now.getDate() - now.getDay());
          return [ymd(start), ymd(now)];
        }
        case 'lastmonth':
          return [ymd(new Date(y, m - 1, 1)), ymd(new Date(y, m, 0))];
        case 'year':
          return [ymd(new Date(y, 0, 1)), ymd(new Date(y, 11, 31))];
        case 'month':
        default:
          return [ymd(new Date(y, m, 1)), ymd(new Date(y, m + 1, 0))];
      }
    }
    function applyQuickRange(key) {
      const [from, to] = rangeFor(key);
      document.getElementById('fFrom').value = from;
      document.getElementById('fTo').value   = to;
      document.querySelectorAll('.exp-quick-btn').forEach(function (b) {
        b.classList.toggle('is-active', b.getAttribute('data-range') === key);
      });
    }
    // A hand-edited date no longer matches any preset, so clear the highlight.
    function clearQuickHighlight() {
      document.querySelectorAll('.exp-quick-btn').forEach(function (b) {
        b.classList.remove('is-active');
      });
    }

    /* ---------- render ---------- */
    function fillSelect(el, values, placeholder, selected) {
      el.innerHTML = (placeholder ? '<option value="">' + esc(placeholder) + '</option>' : '') +
        values.map(function (v) {
          return '<option value="' + esc(v) + '"' + (v === selected ? ' selected' : '') + '>' + esc(v) + '</option>';
        }).join('');
    }

    function renderStats(s) {
      document.getElementById('statTotal').textContent = money(s.total);
      document.getElementById('statCount').textContent = s.count;

      const top = s.top_category;
      document.getElementById('statTopCat').textContent = top ? top.category : '—';
      document.getElementById('statTopCatLabel').textContent = top
        ? 'Biggest category · ' + money(top.amount)
        : 'Biggest category';

      document.getElementById('statDaily').textContent = money(s.daily_avg);
      document.getElementById('statDailyLabel').textContent =
        'Average per day · ' + s.days + (s.days === 1 ? ' day' : ' days');
    }

    function renderBreakdown(list, total) {
      const el = document.getElementById('breakdown');
      const hint = document.getElementById('breakdownHint');

      if (!list.length) {
        el.innerHTML = '<div class="exp-empty">No spending in this range yet.</div>';
        hint.textContent = '';
        return;
      }
      hint.textContent = list.length + (list.length === 1 ? ' category' : ' categories') +
                         ' · ' + money(total);

      const max = list[0].amount || 1;   // scale bars against the biggest slice
      el.innerHTML = list.map(function (b) {
        const width = Math.max(2, Math.round(b.amount / max * 100));
        return '<div class="exp-bar-row">' +
            '<div class="exp-bar-label">' + catPill(b.category) +
              '<small>' + b.entries + (b.entries === 1 ? ' entry' : ' entries') + '</small>' +
            '</div>' +
            '<div class="exp-bar-track">' +
              '<div class="exp-bar-fill cat-' + slug(b.category) + '" style="width:' + width + '%"></div>' +
            '</div>' +
            '<div class="exp-bar-value">' + money(b.amount) +
              '<small>' + b.share.toFixed(1) + '%</small>' +
            '</div>' +
          '</div>';
      }).join('');
    }

    function renderTable(list, total) {
      const body = document.getElementById('logBody');
      const foot = document.getElementById('logFoot');
      document.getElementById('logHint').textContent =
        list.length ? list.length + (list.length === 1 ? ' entry' : ' entries') : '';

      if (!list.length) {
        body.innerHTML = '<tr><td colspan="9"><div class="exp-empty">' +
          'Nothing recorded for this filter. Use <strong>Add expense</strong> to log one.' +
          '</div></td></tr>';
        foot.hidden = true;
        return;
      }

      body.innerHTML = list.map(function (r) {
        return '<tr>' +
          '<td>' + prettyDate(r.date) + '</td>' +
          '<td><strong>' + esc(r.title) + '</strong>' +
            (r.description ? '<br><small>' + esc(r.description) + '</small>' : '') + '</td>' +
          '<td>' + catPill(r.category) + '</td>' +
          '<td>' + (r.vendor ? esc(r.vendor) : '—') + '</td>' +
          '<td>' + esc(r.payment_method) + '</td>' +
          '<td>' + (r.reference_no ? esc(r.reference_no) : '—') + '</td>' +
          '<td style="text-align:right;"><strong>' + money(r.amount) + '</strong></td>' +
          '<td>' + (r.recorded_by_name ? esc(r.recorded_by_name) : '—') + '</td>' +
          '<td>' +
            '<button class="qrm-btn qrm-btn-secondary" data-act="edit" data-id="' + r.id + '" title="Edit">' +
              '<span class="material-symbols-sharp">edit</span></button>' +
            '<button class="qrm-btn qrm-btn-danger" data-act="delete" data-id="' + r.id + '" title="Delete">' +
              '<span class="material-symbols-sharp">delete</span></button>' +
          '</td>' +
        '</tr>';
      }).join('');

      document.getElementById('footTotal').textContent = money(total);
      foot.hidden = false;
    }

    /* ---------- load ---------- */
    function load() {
      const params = new URLSearchParams({
        action:   'list',
        from:     document.getElementById('fFrom').value,
        to:       document.getElementById('fTo').value,
        category: document.getElementById('fCategory').value,
        method:   document.getElementById('fMethod').value,
        q:        document.getElementById('fSearch').value.trim()
      });

      fetch(API + '?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d || !d.success) { toast((d && d.message) || 'Could not load expenses.', 'error'); return; }

          rows = d.rows || [];
          // The allow-lists come back with every list call; only build the
          // selects the first time so the user's current choice survives.
          if (!categories.length) {
            categories = d.categories || [];
            methods    = d.payment_methods || [];
            fillSelect(document.getElementById('fCategory'), categories, 'All categories',
                       document.getElementById('fCategory').value);
            fillSelect(document.getElementById('fMethod'), methods, 'All methods',
                       document.getElementById('fMethod').value);
          }

          renderStats(d.stats);
          renderBreakdown(d.breakdown || [], d.stats.total);
          renderTable(rows, d.stats.total);
        })
        .catch(function () { toast('Network error — could not load expenses.', 'error'); });
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

    /* ---------- modal ---------- */
    const modal = document.getElementById('expModal');

    function openModal(row) {
      // Selects are built from the server allow-lists, so an edit can never
      // introduce a category the API would reject.
      fillSelect(document.getElementById('fCat'), categories, '', row ? row.category : 'Ingredients');
      fillSelect(document.getElementById('fPay'), methods,    '', row ? row.payment_method : 'Cash');

      document.getElementById('fId').value     = row ? row.id : '';
      document.getElementById('fTitle').value  = row ? row.title : '';
      document.getElementById('fDate').value   = row ? row.date : ymd(new Date());
      document.getElementById('fAmount').value = row ? row.amount : '';
      document.getElementById('fVendor').value = row ? (row.vendor || '') : '';
      document.getElementById('fRef').value    = row ? (row.reference_no || '') : '';
      document.getElementById('fDesc').value   = row ? (row.description || '') : '';

      document.getElementById('modalTitle').textContent = row ? 'Edit expense' : 'Add expense';
      document.getElementById('modalSub').textContent   = row
        ? 'Changes show up on the Finance page straight away.'
        : 'Record something the restaurant paid for.';

      modal.hidden = false;
      document.getElementById('fTitle').focus();
    }
    function closeModal() { modal.hidden = true; }

    document.getElementById('addBtn').addEventListener('click', function () { openModal(null); });
    document.getElementById('modalClose').addEventListener('click', closeModal);
    document.getElementById('modalCancel').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hidden) closeModal();
    });

    document.getElementById('expForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const id     = document.getElementById('fId').value;
      const amount = document.getElementById('fAmount').value;
      const title  = document.getElementById('fTitle').value.trim();

      // Mirror the server's rules so the obvious mistakes never need a round trip.
      if (!title) { toast('Give the expense a short description.', 'error'); return; }
      if (!document.getElementById('fDate').value) { toast('Pick a date.', 'error'); return; }
      if (!(Number(amount) > 0)) { toast('Amount must be greater than zero.', 'error'); return; }

      const btn = document.getElementById('modalSave');
      btn.disabled = true;

      post({
        action:         id ? 'update' : 'create',
        id:             id,
        date:           document.getElementById('fDate').value,
        title:          title,
        amount:         amount,
        category:       document.getElementById('fCat').value,
        payment_method: document.getElementById('fPay').value,
        vendor:         document.getElementById('fVendor').value.trim(),
        reference_no:   document.getElementById('fRef').value.trim(),
        description:    document.getElementById('fDesc').value.trim()
      }).then(function (d) {
        toast((d && d.message) || 'Done', d && d.success ? 'success' : 'error');
        if (d && d.success) { closeModal(); load(); }
      }).catch(function () {
        toast('Network error — try again.', 'error');
      }).finally(function () { btn.disabled = false; });
    });

    /* ---------- row actions ---------- */
    document.getElementById('logBody').addEventListener('click', function (e) {
      const btn = e.target.closest('[data-act]');
      if (!btn) return;

      const id  = Number(btn.getAttribute('data-id'));
      const act = btn.getAttribute('data-act');
      const row = rows.find(function (r) { return r.id === id; });

      if (act === 'edit') {
        if (row) openModal(row);
        return;
      }
      if (act === 'delete') {
        const label = row ? row.title : 'this expense';
        if (!window.confirm('Delete "' + label + '"? This also removes it from the Finance page.')) return;

        btn.disabled = true;
        post({ action: 'delete', id: id }).then(function (d) {
          toast((d && d.message) || 'Done', d && d.success ? 'success' : 'error');
          load();
        }).catch(function () {
          toast('Network error — try again.', 'error');
        }).finally(function () { btn.disabled = false; });
      }
    });

    /* ---------- filters ---------- */
    document.getElementById('applyBtn').addEventListener('click', load);
    document.getElementById('resetBtn').addEventListener('click', function () {
      document.getElementById('fCategory').value = '';
      document.getElementById('fMethod').value   = '';
      document.getElementById('fSearch').value   = '';
      applyQuickRange('month');
      load();
    });
    document.getElementById('quickRanges').addEventListener('click', function (e) {
      const btn = e.target.closest('.exp-quick-btn');
      if (!btn) return;
      applyQuickRange(btn.getAttribute('data-range'));
      load();
    });
    ['fFrom', 'fTo'].forEach(function (id) {
      document.getElementById(id).addEventListener('change', clearQuickHighlight);
    });
    ['fCategory', 'fMethod'].forEach(function (id) {
      document.getElementById(id).addEventListener('change', load);
    });
    document.getElementById('fSearch').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); load(); }
    });

    /* ---------- CSV export (built from what is already on screen) ---------- */
    document.getElementById('exportBtn').addEventListener('click', function () {
      if (!rows.length) { toast('Nothing to export for this filter.', 'error'); return; }

      const head = ['Date', 'Description', 'Category', 'Vendor', 'Paid by',
                    'Bill no.', 'Amount', 'Recorded by', 'Note'];
      // Quote every field and double any inner quote — descriptions and notes
      // routinely contain commas.
      const cell = function (v) { return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"'; };

      const csv = [head.map(cell).join(',')].concat(rows.map(function (r) {
        return [r.date, r.title, r.category, r.vendor || '', r.payment_method,
                r.reference_no || '', Number(r.amount).toFixed(2),
                r.recorded_by_name || '', r.description || ''].map(cell).join(',');
      })).join('\r\n');

      const from = document.getElementById('fFrom').value;
      const to   = document.getElementById('fTo').value;
      // The BOM keeps Excel from mangling any non-ASCII vendor names.
      const url  = URL.createObjectURL(new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' }));
      const a    = document.createElement('a');
      a.href = url;
      a.download = 'expenses_' + from + '_to_' + to + '.csv';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    });

    /* ---------- go ---------- */
    applyQuickRange('month');
    load();
  })();
  </script>
</body>
</html>
