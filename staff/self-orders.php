<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'staff');

$pageTitle = 'QR Self-Orders Queue';
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
    <h2>QR Self-Orders Queue</h2>
    <p class="text-muted">Review and approve or reject table-side QR requests from dine-in guests.</p>
</div>

<div class="panel-table-wrap">
    <table class="panel-table" id="qrTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Table</th>
                <th>Guest Name</th>
                <th>Phone</th>
                <th>Items</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Time</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="qrRequestsBody">
            <tr>
                <td colspan="10" style="text-align:center; padding: 2rem;">Loading QR requests...</td>
            </tr>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadQRRequests();

    function loadQRRequests() {
        fetch('api/qr_requests.php?action=list')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                const tbody = document.getElementById('qrRequestsBody');
                if (!data.requests || data.requests.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding: 2rem;">No QR requests found.</td></tr>';
                    return;
                }
                
                tbody.innerHTML = data.requests.map(r => {
                    const itemsStr = (r.items || []).map(i => `${i.name} x${i.quantity}`).join('<br>');
                    const statusClass = r.status === 'pending' ? 'st-pending' : (r.status === 'approved' ? 'st-checkedin' : 'st-cancelled');
                    let actions = '-';
                    if (r.status === 'pending') {
                        actions = `
                            <button class="qrm-btn qrm-btn-success" onclick="approveRequest(${r.id})">Approve</button>
                            <button class="qrm-btn qrm-btn-danger" onclick="rejectRequest(${r.id})">Reject</button>
                        `;
                    }
                    return `
                        <tr>
                            <td>#${r.id}</td>
                            <td><strong>${esc(r.table_name)}</strong></td>
                            <td>${esc(r.customer_name || 'Guest')}</td>
                            <td>${esc(r.phone || '-')}</td>
                            <td style="font-size:0.8rem;">${itemsStr}</td>
                            <td>Rs. ${parseFloat(r.total_price).toFixed(2)}</td>
                            <td>${esc(r.payment_method)}</td>
                            <td><span class="panel-status ${statusClass}">${esc(r.status)}</span></td>
                            <td>${new Date(r.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</td>
                            <td>${actions}</td>
                        </tr>
                    `;
                }).join('');
            });
    }

    window.approveRequest = function(id) {
        if (!confirm('Approve this QR order and send to kitchen?')) return;
        fetch('api/qr_requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'approve', request_id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadQRRequests();
            } else {
                alert(data.message || 'Approval failed');
            }
        });
    };

    window.rejectRequest = function(id) {
        const reason = prompt('Enter rejection reason:');
        if (reason === null) return;
        fetch('api/qr_requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reject', request_id: id, reason: reason })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadQRRequests();
            } else {
                alert(data.message || 'Rejection failed');
            }
        });
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
