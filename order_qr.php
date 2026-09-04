<?php
require_once __DIR__ . '/includes/db.php';

$token = $_GET['token'] ?? '';
if (!$token) { http_response_code(400); die('<h2>Invalid QR Code</h2>'); }

$stmt = $conn->prepare("SELECT * FROM restaurant_tables WHERE qr_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$table = $stmt->get_result()->fetch_assoc();

if (!$table) { http_response_code(404); die('<h2>Invalid or expired QR Code.</h2>'); }

// Fetch menu grouped by category
$menu_query = $conn->query("SELECT * FROM menu WHERE menu_status != 'Out of Stock' ORDER BY menu_category, menu_name");
$menu_by_cat = [];
while ($row = $menu_query->fetch_assoc()) {
    $menu_by_cat[$row['menu_category']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order — <?= htmlspecialchars($table['table_name']) ?> | Mero Bhoj</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/Merobhoj/assets/css/style.css">

  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --red: #E53935;
      --red-dark: #C62828;
      --red-light: #FFEBEE;
      --green: #43A047;
      --green-light: #E8F5E9;
      --grey-bg: #F5F5F5;
      --white: #FFFFFF;
      --text: #1A1A1A;
      --text-2: #555555;
      --text-3: #999999;
      --border: #E0E0E0;
      --shadow: 0 1px 4px rgba(0,0,0,0.08);
      --shadow-md: 0 4px 16px rgba(0,0,0,0.12);
      --radius: 10px;
      --header-h: 56px;
      --footer-h: 64px;
    }

    html { scroll-behavior: smooth; font-size: 14px; }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--grey-bg);
      color: var(--text);
      min-height: 100vh;
      padding-bottom: calc(var(--footer-h) + 1rem);
    }

    /* ── HEADER ── */
    .mb-header {
      position: sticky;
      top: 0;
      z-index: 100;
      height: var(--header-h);
      background: var(--white);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      padding: 0 1.5rem;
      gap: 0.75rem;
    }

    .mb-logo {
      width: 36px; height: 36px;
      background: var(--red);
      border-radius: 8px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem; font-weight: 800; color: white;
      flex-shrink: 0;
    }

    .mb-brand { flex: 1; }
    .mb-brand h1 { font-size: 0.95rem; font-weight: 700; color: var(--text); }
    .mb-brand p  { font-size: 0.72rem; color: var(--text-3); }

    .mb-lang {
      font-size: 0.8rem; font-weight: 600; color: var(--text-2);
      border: 1px solid var(--border);
      border-radius: 6px; padding: 0.3rem 0.7rem;
      cursor: pointer; background: var(--white);
    }

    /* ── WRAPPER ── */
    .mb-wrap {
      max-width: 960px;
      margin: 0 auto;
      padding: 1rem 1.25rem;
    }

    /* ── STATUS BANNER ── */
    .mb-status-bar {
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 0.85rem 1.1rem;
      margin-bottom: 1rem;
      display: none;
    }
    .mb-status-bar.visible { display: block; }

    .mb-status-top {
      display: flex; align-items: center; justify-content: space-between;
    }

    .mb-status-badge {
      display: flex; align-items: center; gap: 0.45rem;
      font-weight: 700; font-size: 0.9rem; color: var(--text);
    }

    .mb-status-dot {
      width: 8px; height: 8px; border-radius: 50%;
      background: var(--green);
    }

    .mb-req-num {
      font-size: 0.8rem; font-weight: 600; color: var(--text-3);
    }

    .mb-status-sub {
      font-size: 0.75rem; color: var(--text-3); margin-top: 0.2rem;
    }

    /* ── CATEGORY LABEL ── */
    .mb-cat-label {
      font-size: 1rem; font-weight: 700; color: var(--text);
      padding: 1rem 0 0.65rem;
    }

    /* ── MENU GRID ── */
    .mb-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 0.75rem;
      margin-bottom: 0.5rem;
    }

    @media (max-width: 768px) { .mb-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 420px) { .mb-grid { grid-template-columns: repeat(1, 1fr); } }

    /* ── MENU CARD ── */
    .mb-card {
      background: var(--white);
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow);
      border: 1px solid transparent;
      transition: box-shadow 0.18s ease, border-color 0.18s ease;
      display: flex; flex-direction: column;
    }

    .mb-card:hover { box-shadow: var(--shadow-md); }
    .mb-card.in-cart { border-color: var(--red); }

    .mb-card-img {
      width: 100%; aspect-ratio: 4/3;
      object-fit: cover;
      background: #f0f0f0;
      display: block;
    }

    .mb-card-body {
      padding: 0.7rem 0.8rem 0.6rem;
      flex: 1; display: flex; flex-direction: column;
    }

    .mb-card-name {
      font-size: 0.88rem; font-weight: 700;
      color: var(--text); line-height: 1.3;
      margin-bottom: 0.25rem;
    }

    .mb-card-desc {
      font-size: 0.73rem; color: var(--text-3);
      line-height: 1.4;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      flex: 1;
      margin-bottom: 0.5rem;
    }

    .mb-card-footer {
      display: flex; align-items: center; justify-content: space-between;
    }

    .mb-card-price {
      font-size: 0.9rem; font-weight: 800; color: var(--red);
    }

    .mb-low-stock {
      font-size: 0.65rem; font-weight: 600;
      color: #E65100; background: #FFF3E0;
      border-radius: 4px; padding: 0.15rem 0.4rem;
    }

    /* Tap to add / qty */
    .mb-tap-add {
      font-size: 0.78rem; font-weight: 600; color: var(--red);
      cursor: pointer; background: none; border: none;
      padding: 0; transition: opacity 0.15s ease;
    }
    .mb-tap-add:hover { opacity: 0.75; }

    .mb-qty-row {
      display: flex; align-items: center; gap: 0.4rem;
    }

    .mb-qty-btn {
      width: 26px; height: 26px;
      border-radius: 50%; border: 1.5px solid var(--border);
      background: var(--white); cursor: pointer;
      font-size: 1rem; font-weight: 700; color: var(--text);
      display: flex; align-items: center; justify-content: center;
      transition: border-color 0.15s ease, color 0.15s ease;
      line-height: 1;
    }

    .mb-qty-btn:hover { border-color: var(--red); color: var(--red); }

    .mb-qty-num {
      font-size: 0.88rem; font-weight: 700;
      min-width: 18px; text-align: center; color: var(--text);
    }

    /* ── CUSTOMER DETAILS ── */
    .mb-details-section {
      background: var(--white);
      border-radius: var(--radius);
      padding: 1.25rem;
      margin-top: 1.25rem;
      box-shadow: var(--shadow);
    }

    .mb-section-title {
      font-size: 0.9rem; font-weight: 700;
      color: var(--text); margin-bottom: 1rem;
    }

    .mb-fields-row {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 0.75rem;
    }

    @media (max-width: 600px) { .mb-fields-row { grid-template-columns: 1fr; } }

    .mb-field label {
      display: block;
      font-size: 0.75rem; font-weight: 600; color: var(--text-2);
      margin-bottom: 0.35rem;
    }

    .mb-input {
      width: 100%;
      padding: 0.55rem 0.8rem;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 0.85rem; font-family: inherit;
      color: var(--text); background: var(--white);
      transition: border-color 0.15s ease;
      -webkit-appearance: none;
    }

    .mb-input:focus {
      outline: none;
      border-color: var(--red);
      box-shadow: 0 0 0 2px rgba(229,57,53,0.1);
    }

    .mb-input::placeholder { color: #BDBDBD; }

    /* Payment row */
    .mb-payment-row {
      margin-top: 0.85rem;
    }

    .mb-payment-row label {
      display: block;
      font-size: 0.75rem; font-weight: 600; color: var(--text-2);
      margin-bottom: 0.35rem;
    }

    .mb-select {
      padding: 0.55rem 2rem 0.55rem 0.8rem;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-size: 0.85rem; font-family: inherit;
      color: var(--text); background: var(--white);
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23999' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      cursor: pointer;
      min-width: 180px;
    }

    .mb-select:focus { outline: none; border-color: var(--red); }

    /* ── CART SUMMARY (inside details) ── */
    .mb-cart-summary {
      margin-top: 1.1rem;
      border-top: 1px solid var(--border);
      padding-top: 0.85rem;
      display: none;
    }

    .mb-cart-summary.visible { display: block; }

    .mb-cart-item-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.4rem 0;
      border-bottom: 1px solid #F5F5F5;
    }

    .mb-cart-item-name { font-size: 0.82rem; font-weight: 500; color: var(--text); }
    .mb-cart-item-price { font-size: 0.82rem; color: var(--text-2); }

    /* ── FOOTER BAR ── */
    .mb-footer {
      position: fixed;
      bottom: 0; left: 0; right: 0;
      height: var(--footer-h);
      background: var(--white);
      border-top: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 1.5rem;
      z-index: 100;
      box-shadow: 0 -2px 12px rgba(0,0,0,0.06);
    }

    .mb-footer-info p:first-child {
      font-size: 0.85rem; font-weight: 700; color: var(--text);
    }

    .mb-footer-info p:last-child {
      font-size: 0.78rem; color: var(--text-3); margin-top: 0.1rem;
    }

    .mb-send-btn {
      background: var(--red);
      color: white;
      border: none;
      border-radius: 8px;
      padding: 0.65rem 1.5rem;
      font-size: 0.88rem; font-weight: 700;
      cursor: pointer;
      display: flex; align-items: center; gap: 0.4rem;
      transition: background 0.2s ease, transform 0.15s ease;
      white-space: nowrap;
    }

    .mb-send-btn:hover:not(:disabled) { background: var(--red-dark); transform: translateY(-1px); }
    .mb-send-btn:disabled { background: #BDBDBD; cursor: not-allowed; transform: none; }

    /* ── STATUS OVERLAY ── */
    .mb-status-overlay {
      display: none;
      position: fixed; inset: 0;
      background: rgba(0,0,0,0.5);
      z-index: 200;
      align-items: center; justify-content: center;
    }

    .mb-status-overlay.active { display: flex; }

    .mb-status-card {
      background: var(--white);
      border-radius: 16px;
      padding: 2rem 2rem 1.75rem;
      max-width: 360px; width: 90%;
      text-align: center;
      box-shadow: var(--shadow-md);
    }

    .mb-status-icon {
      font-size: 3rem;
      margin-bottom: 1rem;
      display: block;
    }

    .mb-status-card h2 { font-size: 1.15rem; font-weight: 800; margin-bottom: 0.35rem; }
    .mb-status-card p  { font-size: 0.82rem; color: var(--text-2); }

    .mb-order-code {
      display: inline-flex; align-items: center; gap: 0.4rem;
      background: var(--green-light); color: var(--green);
      border: 1px solid #A5D6A7;
      border-radius: 50px; padding: 0.45rem 1rem;
      font-weight: 700; font-size: 0.88rem;
      margin-top: 0.85rem;
    }

    .mb-steps {
      display: flex; align-items: center; justify-content: center;
      gap: 0; margin-top: 1.5rem;
    }

    .mb-step { display: flex; flex-direction: column; align-items: center; gap: 0.3rem; }

    .mb-step-dot {
      width: 28px; height: 28px; border-radius: 50%;
      border: 2px solid var(--border);
      display: flex; align-items: center; justify-content: center;
      font-size: 0.72rem; font-weight: 700; color: var(--text-3);
    }

    .mb-step.done .mb-step-dot { background: var(--green); border-color: var(--green); color: white; }
    .mb-step.active .mb-step-dot { background: #FF9800; border-color: #FF9800; color: white; animation: pulse 1.5s infinite; }
    .mb-step.rejected .mb-step-dot { background: var(--red); border-color: var(--red); color: white; }

    .mb-step-lbl { font-size: 0.62rem; color: var(--text-3); font-weight: 500; }

    .mb-step-line { flex: 1; height: 2px; background: var(--border); margin: 0 0.35rem 1.2rem; max-width: 48px; }
    .mb-step-line.done { background: var(--green); }

    .mb-dismiss {
      margin-top: 1.25rem;
      background: none; border: 1px solid var(--border);
      border-radius: 6px; padding: 0.5rem 1.25rem;
      font-size: 0.82rem; font-weight: 600; color: var(--text-2);
      cursor: pointer;
    }

    .mb-dismiss:hover { background: var(--grey-bg); }

    /* Empty state */
    .mb-empty {
      text-align: center; padding: 3rem 1rem; color: var(--text-3);
    }

    @keyframes pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }
    @keyframes spin { from{transform:rotate(0)} to{transform:rotate(360deg)} }
  </style>
</head>
<body>

  <!-- Header -->
  <header class="mb-header">
    <div class="mb-logo">R</div>
    <div class="mb-brand">
      <h1>Mero Bhoj Restaurant</h1>
      <p>Secure table ordering · <?= htmlspecialchars($table['table_name']) ?></p>
    </div>
    <div class="mb-lang">English</div>
  </header>

  <div class="mb-wrap">

    <!-- Status banner (shown after order submission) -->
    <div class="mb-status-bar" id="statusBar">
      <div class="mb-status-top">
        <div class="mb-status-badge">
          <div class="mb-status-dot" id="statusDot"></div>
          <span id="statusBadgeText">Pending</span>
        </div>
        <span class="mb-req-num" id="reqNum"></span>
      </div>
      <div class="mb-status-sub" id="statusSubText">POS status: Pending · Payment: Unpaid</div>
    </div>

    <!-- Menu Categories -->
    <?php foreach ($menu_by_cat as $cat => $items): ?>
      <div class="mb-cat-label"><?= ucfirst(htmlspecialchars($cat)) ?></div>
      <div class="mb-grid">
        <?php foreach ($items as $item): ?>
          <div class="mb-card" id="card-<?= $item['menu_id'] ?>">
            <img class="mb-card-img"
                 src="/Merobhoj/<?= htmlspecialchars($item['menu_image']) ?>"
                 alt="<?= htmlspecialchars($item['menu_name']) ?>"
                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 150%22 fill=%22%23f0f0f0%22><rect width=%22200%22 height=%22150%22/><text x=%2250%%22 y=%2250%%22 font-size=%2228%22 text-anchor=%22middle%22 dy=%22.35em%22>🍽️</text></svg>'">
            <div class="mb-card-body">
              <div class="mb-card-name"><?= htmlspecialchars($item['menu_name']) ?></div>
              <div class="mb-card-desc"><?= htmlspecialchars($item['menu_description']) ?></div>
              <div class="mb-card-footer">
                <div>
                  <span class="mb-card-price">NPR <?= number_format($item['menu_price'], 2) ?></span>
                  <?php if ($item['menu_status'] === 'Low Stock'): ?>
                    <br><span class="mb-low-stock">Low Stock</span>
                  <?php endif; ?>
                </div>
                <div id="ctrl-<?= $item['menu_id'] ?>">
                  <button class="mb-tap-add"
                    onclick="addToCart(<?= $item['menu_id'] ?>, <?= htmlspecialchars(json_encode($item['menu_name'])) ?>, <?= $item['menu_price'] ?>)">
                    Tap to add
                  </button>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <?php if (empty($menu_by_cat)): ?>
      <div class="mb-empty">
        <div style="font-size:3rem;margin-bottom:0.5rem">🍽️</div>
        <p>Menu is currently unavailable.</p>
      </div>
    <?php endif; ?>

    <!-- Customer Details -->
    <div class="mb-details-section">
      <div class="mb-section-title">Customer details</div>

      <div class="mb-fields-row">
        <div class="mb-field">
          <label>Name *</label>
          <input type="text" class="mb-input" id="custName" placeholder="Your name"
                 required aria-required="true" aria-describedby="custNameError">
          <small class="mb-field-error" id="custNameError" role="alert"></small>
        </div>
        <div class="mb-field">
          <label>Phone *</label>
          <input type="tel" class="mb-input" id="custPhone" placeholder="98XXXXXXXX or +977 98XXXXXXXX"
                 required inputmode="tel" maxlength="16" pattern="(?:\+977[\s-]?)?9[678][0-9]{8}"
                 title="Enter a valid Nepali mobile number, for example 9812345678 or +977 9812345678."
                 aria-required="true" aria-describedby="custPhoneError">
          <small class="mb-field-error" id="custPhoneError" role="alert"></small>
        </div>
        <div class="mb-field">
          <label>Order note</label>
          <input type="text" class="mb-input" id="custNote" placeholder="Table or order note">
        </div>
      </div>

      <div class="mb-payment-row">
        <label>Payment</label>
        <select class="mb-select" id="paymentMethod">
          <option value="Cash">Pay at restaurant</option>
          <option value="eSewa">eSewa</option>
          <option value="Khalti">Khalti</option>
        </select>
      </div>

      <!-- Cart summary inside details -->
      <div class="mb-cart-summary" id="cartSummary">
        <div id="cartSummaryItems"></div>
      </div>
    </div>

  </div><!-- /mb-wrap -->

  <!-- Fixed Footer -->
  <footer class="mb-footer">
    <div class="mb-footer-info">
      <p id="footerItemsLabel">No items selected</p>
      <p id="footerTotal">NPR 0.00</p>
    </div>
    <button class="mb-send-btn" id="sendBtn" onclick="submitOrder()" disabled>
      Send for approval
    </button>
  </footer>

  <!-- Status Overlay (after submission) -->
  <div class="mb-status-overlay" id="statusOverlay">
    <div class="mb-status-card">
      <span class="mb-status-icon" id="ovIcon">⏳</span>
      <h2 id="ovTitle">Order Submitted!</h2>
      <p id="ovSubtitle">Waiting for kitchen approval...</p>
      <div id="ovCode"></div>

      <div class="mb-steps">
        <div class="mb-step done" id="step1">
          <div class="mb-step-dot">✓</div>
          <div class="mb-step-lbl">Submitted</div>
        </div>
        <div class="mb-step-line done" id="line1"></div>
        <div class="mb-step active" id="step2">
          <div class="mb-step-dot">2</div>
          <div class="mb-step-lbl">Review</div>
        </div>
        <div class="mb-step-line" id="line2"></div>
        <div class="mb-step" id="step3">
          <div class="mb-step-dot">3</div>
          <div class="mb-step-lbl">Kitchen</div>
        </div>
      </div>

      <button class="mb-dismiss" id="dismissBtn" style="display:none" onclick="resetOrder()">
        Place New Order
      </button>
    </div>
  </div>

  <script src="/Merobhoj/assets/js/script.js"></script>
  <script>
    const TOKEN = "<?= htmlspecialchars($token) ?>";
    const BASE_URL = '/Merobhoj';
    let cart = {};
    let activeRequestId = null;
    let pollTimer = null;

    // ── Cart Logic ──
    function addToCart(id, name, price) {
      if (cart[id]) { cart[id].quantity++; }
      else { cart[id] = { id, name, price, quantity: 1 }; }
      updateCtrl(id);
      updateFooter();
      renderCartSummary();
    }

    function updateQty(id, delta) {
      if (!cart[id]) return;
      cart[id].quantity += delta;
      if (cart[id].quantity <= 0) delete cart[id];
      updateCtrl(id);
      updateFooter();
      renderCartSummary();
    }

    function updateCtrl(id) {
      const ctrl = document.getElementById('ctrl-' + id);
      const card = document.getElementById('card-' + id);
      if (!ctrl) return;

      if (cart[id]) {
        ctrl.innerHTML = `
          <div class="mb-qty-row">
            <button type="button" class="mb-qty-btn" onclick="updateQty(${id},-1)">−</button>
            <span class="mb-qty-num">${cart[id].quantity}</span>
            <button type="button" class="mb-qty-btn" onclick="updateQty(${id},1)">+</button>
          </div>`;
        card && card.classList.add('in-cart');
      } else {
        const origCard = document.getElementById('card-' + id);
        const name = origCard?.querySelector('.mb-card-name')?.textContent?.trim() || '';
        const priceText = origCard?.querySelector('.mb-card-price')?.textContent?.replace(/[^0-9.]/g,'') || '0';
        const price = parseFloat(priceText);
        ctrl.innerHTML = `<button class="mb-tap-add" onclick="addToCart(${id},${JSON.stringify(name)},${price})">Tap to add</button>`;
        card && card.classList.remove('in-cart');
      }
    }

    function updateFooter() {
      const items = Object.values(cart);
      const count = items.reduce((s, i) => s + i.quantity, 0);
      const total = items.reduce((s, i) => s + i.price * i.quantity, 0);
      const btn   = document.getElementById('sendBtn');

      document.getElementById('footerItemsLabel').textContent =
        count === 0 ? 'No items selected' : `${count} item${count > 1 ? 's' : ''} selected`;
      document.getElementById('footerTotal').textContent = `NPR ${total.toFixed(2)}`;
      btn.disabled = count === 0;
    }

    function renderCartSummary() {
      const items = Object.values(cart);
      const summary = document.getElementById('cartSummary');
      const list    = document.getElementById('cartSummaryItems');

      if (!items.length) {
        summary.classList.remove('visible');
        return;
      }

      summary.classList.add('visible');
      let html = '';
      items.forEach(item => {
        html += `<div class="mb-cart-item-row">
          <span class="mb-cart-item-name">${esc(item.name)} × ${item.quantity}</span>
          <span class="mb-cart-item-price">NPR ${(item.price * item.quantity).toFixed(2)}</span>
        </div>`;
      });
      list.innerHTML = html;
    }

    // ── Submit ──
    function submitOrder() {
      const items = Object.values(cart);
      if (!items.length) return;
      if (!validateOrderDetails()) return;

      const btn = document.getElementById('sendBtn');
      btn.disabled = true;
      btn.innerHTML = '<span style="animation:spin 1s linear infinite;display:inline-block">⏳</span> Sending...';

      const payload = {
        action: 'submit',
        token: TOKEN,
        items,
        name: document.getElementById('custName').value.trim(),
        phone: document.getElementById('custPhone').value.trim(),
        note: document.getElementById('custNote').value.trim(),
        payment: document.getElementById('paymentMethod').value
      };

      fetch(BASE_URL + '/api_qr_requests_client.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          activeRequestId = res.request_id;
          showOverlay('pending');
          updateStatusBar('pending', res.request_id);
          pollStatus();
        } else {
          btn.disabled = false;
          btn.textContent = 'Send for approval';
          alert(res.message || 'Could not place order.');
        }
      })
      .catch(() => {
        btn.disabled = false;
        btn.textContent = 'Send for approval';
        alert('Network error. Please try again.');
      });
    }

    // ── Status Bar ──
    function updateStatusBar(status, reqId, code, reason) {
      const bar      = document.getElementById('statusBar');
      const dot      = document.getElementById('statusDot');
      const badge    = document.getElementById('statusBadgeText');
      const reqNum   = document.getElementById('reqNum');
      const subText  = document.getElementById('statusSubText');

      bar.classList.add('visible');
      reqNum.textContent = reqId ? `Request #${reqId}` : '';

      if (status === 'pending') {
        dot.style.background = '#FF9800';
        badge.textContent = '⏳ Pending · Waiting for approval';
        subText.textContent = 'POS status: Pending · Payment: Unpaid';
      } else if (status === 'approved') {
        dot.style.background = '#43A047';
        badge.textContent = `✅ Approved · Order ${code || ''}`;
        subText.textContent = 'POS status: Processing · Payment: Unpaid';
      } else if (status === 'rejected') {
        dot.style.background = '#E53935';
        badge.textContent = '❌ Rejected';
        subText.textContent = reason ? `Reason: ${esc(reason)}` : 'Your order was rejected. Please contact staff.';
      }
    }

    // ── Overlay ──
    function showOverlay(status, code, reason) {
      const overlay  = document.getElementById('statusOverlay');
      const icon     = document.getElementById('ovIcon');
      const title    = document.getElementById('ovTitle');
      const subtitle = document.getElementById('ovSubtitle');
      const codeCont = document.getElementById('ovCode');
      const dismiss  = document.getElementById('dismissBtn');

      overlay.classList.add('active');

      if (status === 'pending') {
        icon.textContent = '⏳';
        title.textContent = 'Order Submitted!';
        subtitle.textContent = 'Waiting for kitchen approval...';
        codeCont.innerHTML = '';
        dismiss.style.display = 'none';
        setStep('pending');
      } else if (status === 'approved') {
        icon.textContent = '✅';
        title.textContent = 'Order Approved!';
        subtitle.textContent = 'Your order is being prepared by the kitchen.';
        codeCont.innerHTML = code
          ? `<div class="mb-order-code">🧾 Order: <strong>${esc(code)}</strong></div>` : '';
        dismiss.style.display = 'inline-block';
        setStep('approved');
      } else if (status === 'rejected') {
        icon.textContent = '❌';
        title.textContent = 'Order Rejected';
        subtitle.textContent = reason ? `Reason: ${esc(reason)}` : 'Please contact staff for assistance.';
        codeCont.innerHTML = '';
        dismiss.style.display = 'inline-block';
        setStep('rejected');
      }
    }

    function setStep(status) {
      const s2 = document.getElementById('step2');
      const s3 = document.getElementById('step3');
      const l1 = document.getElementById('line1');
      const l2 = document.getElementById('line2');

      if (status === 'pending') {
        s2.className = 'mb-step active'; s3.className = 'mb-step';
        l1.className = 'mb-step-line done'; l2.className = 'mb-step-line';
      } else if (status === 'approved') {
        s2.className = 'mb-step done'; s3.className = 'mb-step done';
        l1.className = 'mb-step-line done'; l2.className = 'mb-step-line done';
      } else if (status === 'rejected') {
        s2.className = 'mb-step rejected'; s3.className = 'mb-step';
        l1.className = 'mb-step-line done'; l2.className = 'mb-step-line';
      }
    }

    // ── Polling ──
    function pollStatus() {
      if (!activeRequestId) return;
      clearTimeout(pollTimer);

      fetch(`${BASE_URL}/api_qr_requests_client.php?action=status&request_id=${activeRequestId}`)
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            showOverlay(data.status, data.order_code);
            updateStatusBar(data.status, activeRequestId, data.order_code);
            if (data.status === 'pending') {
              pollTimer = setTimeout(pollStatus, 3000);
            }
          }
        })
        .catch(() => { pollTimer = setTimeout(pollStatus, 5000); });
    }

    // ── Reset for new order ──
    function resetOrder() {
      cart = {};
      activeRequestId = null;
      clearTimeout(pollTimer);
      document.querySelectorAll('[id^="ctrl-"]').forEach(ctrl => {
        const id = ctrl.id.replace('ctrl-', '');
        updateCtrl(id);
      });
      updateFooter();
      renderCartSummary();
      document.getElementById('statusOverlay').classList.remove('active');
      document.getElementById('statusBar').classList.remove('visible');
      document.getElementById('sendBtn').textContent = 'Send for approval';
      document.getElementById('custName').value = '';
      document.getElementById('custPhone').value = '';
      document.getElementById('custNote').value = '';
      clearOrderValidation();
    }

    function esc(s) {
      return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
  </script>

</body>
</html>