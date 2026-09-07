<?php
/**
 * Rider — earnings: today / this week / all-time delivery fees plus a
 * per-delivery breakdown. Every query is bound to the session rider id, so a
 * rider can only ever see their own numbers.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'rider');
$pageTitle = 'Earnings';

$riderId = (int)($panelUser['id'] ?? 0);

/** Sum + count of completed deliveries for this rider under an optional date filter. */
function rider_earn(mysqli $conn, int $riderId, string $whereExtra): array
{
    $sql = "SELECT COUNT(*) AS c, COALESCE(SUM(delivery_fee), 0) AS fee
            FROM deliveries
            WHERE rider_id = ? AND completed_at IS NOT NULL" . $whereExtra;
    $stmt = $conn->prepare($sql);
    if (!$stmt) return ['c' => 0, 'fee' => 0.0];
    $stmt->bind_param('i', $riderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ['c' => (int)($row['c'] ?? 0), 'fee' => (float)($row['fee'] ?? 0)];
}

$today   = rider_earn($conn, $riderId, " AND DATE(completed_at) = CURDATE()");
$week    = rider_earn($conn, $riderId, " AND YEARWEEK(completed_at, 1) = YEARWEEK(CURDATE(), 1)");
$alltime = rider_earn($conn, $riderId, "");

// Recent completed deliveries.
$rows = [];
$q = $conn->prepare("SELECT d.order_number, d.delivery_fee, d.completed_at,
                            MAX(o.full_name) AS full_name, MAX(o.address) AS address,
                            SUM(o.price * o.quantity) AS order_total
                     FROM deliveries d
                     JOIN orders o ON o.order_number = d.order_number
                     WHERE d.rider_id = ? AND d.completed_at IS NOT NULL
                     GROUP BY d.order_number, d.delivery_fee, d.completed_at
                     ORDER BY d.completed_at DESC
                     LIMIT 50");
if ($q) {
    $q->bind_param('i', $riderId);
    $q->execute();
    $r = $q->get_result();
    while ($r && ($row = $r->fetch_assoc())) {
        $rows[] = $row;
    }
    $q->close();
}

$pageTitle = $pageTitle ?? 'Delivery Rider';
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


<div class="panel-page-header">
    <div>
        <h1>Earnings</h1>
        <p>Delivery fees you have earned. Paid out by the restaurant.</p>
    </div>
</div>

<div class="rider-earn-grid">
    <div class="panel-stat-card">
        <div class="panel-stat-icon"><span class="material-symbols-sharp">today</span></div>
        <div class="panel-stat-info">
            <h3>Rs. <?= number_format($today['fee'], 0) ?></h3>
            <p>Today · <?= $today['c'] ?> <?= $today['c'] === 1 ? 'delivery' : 'deliveries' ?></p>
        </div>
    </div>
    <div class="panel-stat-card">
        <div class="panel-stat-icon"><span class="material-symbols-sharp">date_range</span></div>
        <div class="panel-stat-info">
            <h3>Rs. <?= number_format($week['fee'], 0) ?></h3>
            <p>This week · <?= $week['c'] ?> <?= $week['c'] === 1 ? 'delivery' : 'deliveries' ?></p>
        </div>
    </div>
    <div class="panel-stat-card">
        <div class="panel-stat-icon"><span class="material-symbols-sharp">savings</span></div>
        <div class="panel-stat-info">
            <h3>Rs. <?= number_format($alltime['fee'], 0) ?></h3>
            <p>All time · <?= $alltime['c'] ?> <?= $alltime['c'] === 1 ? 'delivery' : 'deliveries' ?></p>
        </div>
    </div>
</div>

<div class="panel-table-wrap">
    <table class="panel-table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Address</th>
                <th>Delivered</th>
                <th style="text-align:right;">Order value</th>
                <th style="text-align:right;">My fee</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="6" style="text-align:center; padding:1.5rem;">No completed deliveries yet.</td></tr>
        <?php else: foreach ($rows as $row): ?>
            <tr>
                <td><strong><?= htmlspecialchars((string)$row['order_number']) ?></strong></td>
                <td><?= htmlspecialchars((string)($row['full_name'] ?: 'Customer')) ?></td>
                <td><?= htmlspecialchars((string)($row['address'] ?: '—')) ?></td>
                <td><?= htmlspecialchars(date('M j, g:i A', strtotime((string)$row['completed_at']))) ?></td>
                <td style="text-align:right;">Rs. <?= number_format((float)$row['order_total'], 0) ?></td>
                <td style="text-align:right;"><strong>Rs. <?= number_format((float)$row['delivery_fee'], 0) ?></strong></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

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
