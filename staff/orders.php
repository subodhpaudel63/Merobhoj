<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'staff');

$pageTitle = 'Live Orders Queue';
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

<div class="panel-head" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2>Live Orders Queue</h2>
        <p class="text-muted">Manage active FOH order flow and state transitions. Auto-refreshes every 5s.</p>
    </div>
    <a href="new-order.php" class="qrm-btn qrm-btn-primary">+ Walk-in Order</a>
</div>

<div class="panel-table-wrap">
    <table class="panel-table" id="ordersTable">
        <thead>
            <tr>
                <th>Order #</th>
                <th>Type / Table</th>
                <th>Customer</th>
                <th>Summary</th>
                <th>Total</th>
                <th>Status</th>
                <th>Age</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="ordersListBody">
            <tr>
                <td colspan="8" style="text-align:center; padding: 2rem;">Loading orders...</td>
            </tr>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadOrders();
    setInterval(loadOrders, 5000);

    function loadOrders() {
        fetch('api/orders_list.php')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                const tbody = document.getElementById('ordersListBody');
                if (!data.orders || data.orders.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; padding: 2rem;">No active orders.</td></tr>';
                    return;
                }
                
                tbody.innerHTML = data.orders.map(o => {
                    const statusClass = 'st-' + o.status.toLowerCase();
                    const ageMins = Math.floor((new Date() - new Date(o.created_at)) / 60000);
                    const ageStr = ageMins < 1 ? 'Just now' : `${ageMins}m ago`;
                    
                    const actions = getActionButtons(o);

                    return `
                        <tr>
                            <td><strong>${esc(o.order_number)}</strong></td>
                            <td>${esc(o.order_type)} ${o.order_type === 'Dine In' ? '- T' + esc(o.table_number) : ''}</td>
                            <td>${esc(o.full_name || 'Guest')}<br><small class="text-muted">${esc(o.mobile || '')}</small></td>
                            <td style="font-size:0.8rem; max-width:250px; white-space:normal;">${esc(o.items_summary)}</td>
                            <td>Rs. ${parseFloat(o.total_price).toFixed(2)}</td>
                            <td><span class="panel-status ${statusClass}">${esc(o.status)}</span></td>
                            <td>${ageStr}</td>
                            <td>${actions}</td>
                        </tr>
                    `;
                }).join('');
            });
    }

    function getActionButtons(o) {
        const type = o.order_type;
        const status = o.status;
        let btns = [];

        // Pending -> [Accept] / [Cancel]
        if (status === 'Pending') {
            btns.push(`<button class="qrm-btn qrm-btn-success" onclick="updateStatus('${o.order_number}', 'Confirmed')">Accept</button>`);
            btns.push(`<button class="qrm-btn qrm-btn-danger" onclick="updateStatus('${o.order_number}', 'Cancelled')">Cancel</button>`);
        }
        // Confirmed -> Preparing, Preparing -> Ready, and cancellation
        else if (status === 'Confirmed') {
            btns.push(`<button class="qrm-btn qrm-btn-primary" onclick="updateStatus('${o.order_number}', 'Preparing')">Send to Kitchen</button>`);
            btns.push(`<button class="qrm-btn qrm-btn-danger" onclick="updateStatus('${o.order_number}', 'Cancelled')">Cancel</button>`);
        }
        else if (status === 'Preparing') {
            btns.push(`<button class="qrm-btn qrm-btn-primary" onclick="updateStatus('${o.order_number}', 'Ready')">Mark Ready</button>`);
            btns.push(`<button class="qrm-btn qrm-btn-danger" onclick="updateStatus('${o.order_number}', 'Cancelled')">Cancel</button>`);
        }
        // Ready -> Dine In / Takeaway [Serve] / Delivery [Send out], + [Cancel]
        else if (status === 'Ready') {
            if (type === 'Delivery') {
                btns.push(`<button class="qrm-btn qrm-btn-primary" onclick="updateStatus('${o.order_number}', 'Delivering')">Send Out</button>`);
            } else {
                btns.push(`<button class="qrm-btn qrm-btn-success" onclick="updateStatus('${o.order_number}', 'Completed')">Serve</button>`);
            }
            btns.push(`<button class="qrm-btn qrm-btn-danger" onclick="updateStatus('${o.order_number}', 'Cancelled')">Cancel</button>`);
        }
        // Delivering -> [Complete] / [Cancel]
        else if (status === 'Delivering') {
            btns.push(`<button class="qrm-btn qrm-btn-success" onclick="updateStatus('${o.order_number}', 'Completed')">Complete</button>`);
            btns.push(`<button class="qrm-btn qrm-btn-danger" onclick="updateStatus('${o.order_number}', 'Cancelled')">Cancel</button>`);
        }

        // Add Settle / Pay button for active unpaid orders
        btns.push(`<a href="billing.php?order_number=${encodeURIComponent(o.order_number)}" class="qrm-btn" style="background:#00d26a; color:#fff; font-weight:600; padding:0.35rem 0.75rem; display:inline-flex; align-items:center; gap:0.25rem; text-decoration:none;"><span class="material-symbols-sharp" style="font-size:16px;">payments</span> Settle</a>`);

        return btns.join(' ');
    }

    window.updateStatus = function(orderNumber, targetStatus) {
        var doUpdate = function() {
            fetch('api/order_update_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_number: orderNumber, status: targetStatus })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadOrders();
                } else {
                    alert(data.message || 'Status update failed');
                }
            });
        };

        if (typeof window.openDeleteConfirm === 'function') {
            openDeleteConfirm({
                title: 'Update Order Status?',
                message: `Are you sure you want to mark ${orderNumber} as ${targetStatus}?`,
                confirmText: 'Update Status',
                type: targetStatus === 'Cancelled' ? 'danger' : 'success',
                onConfirm: doUpdate
            });
            return;
        }
        if (!confirm(`Are you sure you want to mark ${orderNumber} as ${targetStatus}?`)) return;
        doUpdate();
    };

    function esc(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
});
</script>

    </main>
  </div>
  <div class="toast-container" id="toastContainer"></div>
  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
  <script src="../assets/js/panel_notifications.js?v=<?= filemtime(__DIR__ . '/../assets/js/panel_notifications.js') ?>"></script>
</body>
</html>
