<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'staff');

$pageTitle = 'Walk-in Order Builder';

// Fetch menu grid (where menu_status != 'Out of Stock')
$menuItems = [];
$resMenu = $conn->query("SELECT * FROM menu WHERE menu_status != 'Out of Stock' ORDER BY menu_category, menu_name");
if ($resMenu) {
    while ($row = $resMenu->fetch_assoc()) {
        $menuItems[] = $row;
    }
}

// Fetch tables for dine-in
$tables = [];
$resTables = $conn->query("SELECT * FROM restaurant_tables ORDER BY id");
if ($resTables) {
    while ($row = $resTables->fetch_assoc()) {
        $tables[] = $row;
    }
}
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
    <h2>Walk-in Order Builder</h2>
    <p class="text-muted">Quickly construct and dispatch counter or table-side walk-in orders.</p>
</div>

<div class="builder-layout">
    <!-- Left: Menu Grid -->
    <div>
        <div class="menu-grid">
            <?php foreach ($menuItems as $m): ?>
                <?php
                $mPrice = (float)($m['menu_price'] ?? $m['price'] ?? 0);
                $mImg = !empty($m['menu_image']) ? (strpos($m['menu_image'], 'assets/') === 0 ? '../' . $m['menu_image'] : '../assets/img/' . $m['menu_image']) : '../assets/img/default.jpg';
                $mNameJs = htmlspecialchars(addslashes($m['menu_name']), ENT_QUOTES);
                ?>
                <div class="menu-card" onclick="addToCart(<?= (int)$m['menu_id'] ?>, '<?= $mNameJs ?>', <?= $mPrice ?>)">
                    <img src="<?= htmlspecialchars($mImg) ?>" alt="<?= htmlspecialchars($m['menu_name']) ?>" class="m-img" onerror="this.src='../assets/img/default.jpg'">
                    <div class="m-name"><?= htmlspecialchars($m['menu_name']) ?></div>
                    <div class="m-price">Rs. <?= number_format($mPrice, 2) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right: Order Panel / Cart -->
    <div class="cart-panel">
        <h3>Order Items</h3>
        <div class="cart-items" id="cartItems">
            <p style="text-align: center; color: var(--clr-info-dark); margin-top: 1rem;">Click menu items to add.</p>
        </div>

        <div style="border-top: 1px solid var(--clr-border); padding-top: 1rem; margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 1.1rem; color: var(--clr-dark);">
                <span>Total:</span>
                <span id="cartTotal">Rs. 0.00</span>
            </div>
        </div>

        <form id="walkinForm">
            <div class="form-group">
                <label>Order Type</label>
                <select id="orderType" onchange="toggleTableSelect()">
                    <option value="Dine In">Dine In</option>
                    <option value="Takeaway">Takeaway</option>
                </select>
            </div>

            <div class="form-group" id="tableSelectGroup">
                <label>Table Select</label>
                <select id="tableNumber">
                    <option value="">-- Select Table --</option>
                    <?php foreach ($tables as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['table_name']) ?> (Cap: <?= $t['capacity'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Customer Name (Optional)</label>
                <input type="text" id="fullName" placeholder="Walk-in Guest">
            </div>

            <div class="form-group">
                <label>Phone Number (Optional)</label>
                <input type="text" id="phone" placeholder="98XXXXXXXX">
            </div>

            <div class="form-group">
                <label>Payment Method</label>
                <select id="paymentMethod">
                    <option value="Cash">Cash</option>
                    <option value="Fonepay / QR">Fonepay / QR</option>
                    <option value="Card">Card</option>
                </select>
            </div>

            <div class="form-group">
                <label>Notes / Instructions</label>
                <input type="text" id="orderNote" placeholder="Less spicy, extra napkin...">
            </div>

            <button type="submit" class="qrm-btn qrm-btn-primary" style="width: 100%; margin-top: 0.5rem;">Dispatch Order</button>
        </form>
    </div>
</div>

<script>
let cart = [];

function addToCart(id, name, price) {
    const existing = cart.find(i => i.menu_id === id);
    if (existing) {
        existing.quantity++;
    } else {
        cart.push({ menu_id: id, name: name, price: price, quantity: 1 });
    }
    renderCart();
}

function updateQty(id, delta) {
    const item = cart.find(i => i.menu_id === id);
    if (!item) return;
    item.quantity += delta;
    if (item.quantity <= 0) {
        cart = cart.filter(i => i.menu_id !== id);
    }
    renderCart();
}

function renderCart() {
    const container = document.getElementById('cartItems');
    if (cart.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: var(--clr-info-dark); margin-top: 1rem;">Click menu items to add.</p>';
        document.getElementById('cartTotal').textContent = 'Rs. 0.00';
        return;
    }

    let total = 0;
    container.innerHTML = cart.map(i => {
        const itemTotal = i.price * i.quantity;
        total += itemTotal;
        return `
            <div class="cart-item">
                <div class="ci-info">${esc(i.name)}</div>
                <div class="ci-controls">
                    <button type="button" class="stepper-btn" onclick="updateQty(${i.menu_id}, -1)">-</button>
                    <span class="stepper-val">${i.quantity}</span>
                    <button type="button" class="stepper-btn" onclick="updateQty(${i.menu_id}, 1)">+</button>
                </div>
                <div class="ci-price">Rs. ${itemTotal.toFixed(0)}</div>
            </div>
        `;
    }).join('');

    document.getElementById('cartTotal').textContent = `Rs. ${total.toFixed(2)}`;
}

function toggleTableSelect() {
    const type = document.getElementById('orderType').value;
    const group = document.getElementById('tableSelectGroup');
    group.style.display = type === 'Dine In' ? 'block' : 'none';
}

document.getElementById('walkinForm').addEventListener('submit', (e) => {
    e.preventDefault();
    if (cart.length === 0) {
        alert('Please add at least one item to the order.');
        return;
    }

    const type = document.getElementById('orderType').value;
    const table = document.getElementById('tableNumber').value;
    if (type === 'Dine In' && !table) {
        alert('Please select a table.');
        return;
    }

    const payload = {
        order_type: type,
        table_number: table,
        full_name: document.getElementById('fullName').value || 'Walk-in',
        phone: document.getElementById('phone').value,
        payment_method: document.getElementById('paymentMethod').value,
        note: document.getElementById('orderNote').value,
        items: cart
    };

    fetch('api/create_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(`Order ${data.order_number} created successfully!`);
            window.location.href = 'orders.php';
        } else {
            alert(data.message || 'Failed to create order');
        }
    });
});

function esc(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

    </main>
  </div>
  <div class="toast-container" id="toastContainer"></div>
  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
  <script src="../assets/js/panel_notifications.js?v=<?= filemtime(__DIR__ . '/../assets/js/panel_notifications.js') ?>"></script>
</body>
</html>
