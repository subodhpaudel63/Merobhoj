<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'staff');

$pageTitle = 'Dashboard';

// --- Server-Side Dashboard Data (No polling) ---

// 1. Tiles stats
// Pending QR Requests
$resQR = $conn->query("SELECT COUNT(*) FROM qr_requests WHERE status = 'pending'");
$pendingQR = $resQR ? $resQR->fetch_row()[0] : 0;

// Active Orders (Distinct orders in Pending..Delivering)
$resActive = $conn->query("SELECT COUNT(DISTINCT order_number) FROM orders WHERE status IN ('Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivering')");
$activeOrders = $resActive ? $resActive->fetch_row()[0] : 0;

// Today's Bookings (Pending/Confirmed/Checked-in)
$todayStr = date('Y-m-d');
$resBookings = $conn->query("SELECT COUNT(*) FROM bookings WHERE DATE(booking_date) = '$todayStr' AND status IN ('Pending', 'Confirmed', 'Checked-in')");
$todaysBookings = $resBookings ? $resBookings->fetch_row()[0] : 0;

// Tables: occupied/total
// We derive occupied exactly like the floor logic: active bookings overlapping NOW
$resTables = $conn->query("SELECT COUNT(*) FROM restaurant_tables");
$totalTables = $resTables ? $resTables->fetch_row()[0] : 0;

$now = date('Y-m-d H:i:s');
$occupiedRes = $conn->query("
    SELECT COUNT(DISTINCT table_id) 
    FROM bookings 
    WHERE DATE(booking_date) = '$todayStr' 
      AND status IN ('Pending', 'Confirmed', 'Checked-in')
      AND '$now' BETWEEN 
          CONCAT(booking_date, ' ', start_time) 
          AND IFNULL(grace_end_at, CONCAT(booking_date, ' ', end_time))
");
$occupiedTables = $occupiedRes ? $occupiedRes->fetch_row()[0] : 0;

// 2. Recent Lists
// Latest Pending QR Requests
$recentQR = [];
$rQR = $conn->query("SELECT r.*, t.table_name FROM qr_requests r JOIN restaurant_tables t ON r.table_id = t.id WHERE r.status = 'pending' ORDER BY r.created_at DESC LIMIT 5");
if ($rQR) {
    while($row = $rQR->fetch_assoc()) {
        $recentQR[] = $row;
    }
}

// Recent Active Orders
$recentOrders = [];
$rOrd = $conn->query("SELECT order_number, order_type, table_number, mobile, status, MIN(created_at) as created_at FROM orders WHERE status IN ('Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivering') GROUP BY order_number, order_type, table_number, mobile, status ORDER BY created_at DESC LIMIT 5");
if ($rOrd) {
    while($row = $rOrd->fetch_assoc()) {
        $recentOrders[] = $row;
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
<div class="qrm-page-header">
    <div>
        <h1>Staff Dashboard</h1>
        <p>Real-time FOH overview for table requests, active orders, and floor occupancy.</p>
    </div>
</div>

<!-- Tiles -->
<div class="dashboard-summary-grid" style="margin-bottom: 2rem;">
    <div class="dashboard-summary-card">
        <span class="material-symbols-sharp">qr_code_scanner</span>
        <div>
            <small>Pending QR Requests</small>
            <strong><?= $pendingQR ?></strong>
        </div>
    </div>
    <div class="dashboard-summary-card">
        <span class="material-symbols-sharp">receipt_long</span>
        <div>
            <small>Active Orders</small>
            <strong><?= $activeOrders ?></strong>
        </div>
    </div>
    <div class="dashboard-summary-card">
        <span class="material-symbols-sharp">event_seat</span>
        <div>
            <small>Today's Bookings</small>
            <strong><?= $todaysBookings ?></strong>
        </div>
    </div>
    <div class="dashboard-summary-card">
        <span class="material-symbols-sharp">grid_view</span>
        <div>
            <small>Tables Occupied</small>
            <strong><?= $occupiedTables ?> / <?= $totalTables ?></strong>
        </div>
    </div>
</div>

<!-- Recent Lists Section -->
<div class="dashboard-section-grid">
    <!-- Panel 1: Recent QR Requests -->
    <section class="dashboard-panel">
        <div class="dashboard-panel-heading">
            <div>
                <h2>Recent QR Requests</h2>
                <p>Latest unapproved self-orders</p>
            </div>
            <a class="dashboard-view-all" href="self-orders.php">View queue</a>
        </div>
        <div style="padding: 1rem;">
            <?php if (empty($recentQR)): ?>
                <p class="text-muted" style="text-align: center; padding: 1.5rem;">No pending QR requests.</p>
            <?php else: ?>
                <?php foreach ($recentQR as $qr): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 0; border-bottom: 1px solid var(--clr-border);">
                    <div>
                        <strong><?= htmlspecialchars($qr['table_name']) ?></strong> — <?= htmlspecialchars($qr['customer_name'] ?? 'Guest') ?>
                        <div class="text-muted" style="font-size: 0.8rem;"><?= date('h:i A', strtotime($qr['created_at'])) ?> · <?= htmlspecialchars($qr['phone'] ?? 'N/A') ?></div>
                    </div>
                    <a href="self-orders.php" class="qrm-btn qrm-btn-primary" style="font-size: 0.78rem;">Review</a>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Panel 2: Recent Active Orders -->
    <section class="dashboard-panel">
        <div class="dashboard-panel-heading">
            <div>
                <h2>Recent Active Orders</h2>
                <p>Latest ongoing kitchen & delivery orders</p>
            </div>
            <a class="dashboard-view-all" href="orders.php">View queue</a>
        </div>
        <div style="padding: 1rem;">
            <?php if (empty($recentOrders)): ?>
                <p class="text-muted" style="text-align: center; padding: 1.5rem;">No active orders.</p>
            <?php else: ?>
                <?php foreach ($recentOrders as $ord): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 0; border-bottom: 1px solid var(--clr-border);">
                    <div>
                        <strong><?= htmlspecialchars($ord['order_number']) ?></strong> (<?= htmlspecialchars($ord['order_type']) ?>)
                        <div class="text-muted" style="font-size: 0.8rem;"><?= date('h:i A', strtotime($ord['created_at'])) ?> <?= $ord['order_type'] === 'Dine In' ? '· Table ' . htmlspecialchars($ord['table_number']) : '' ?></div>
                    </div>
                    <div>
                        <span class="panel-status st-<?= strtolower(str_replace(' ', '', $ord['status'])) ?>"><?= htmlspecialchars($ord['status']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

    </main>
  </div>
  <div class="toast-container" id="toastContainer"></div>
  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
  <script src="../assets/js/panel_notifications.js?v=<?= filemtime(__DIR__ . '/../assets/js/panel_notifications.js') ?>"></script>
</body>
</html>
