<?php
/**
 * Rider — dashboard (the centrepiece).
 * Stat tiles + "My active delivery" (Picked Up / OTP handover) + the pool of
 * unassigned jobs a rider can claim. Everything below the shell is rendered and
 * polled by assets/js/panel_rider.js against rider/api/deliveries.php.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'rider');
$pageTitle = 'Dashboard';
$pageScripts = ['../assets/js/panel_rider.js'];

$pageTitle = $pageTitle ?? 'Delivery Rider';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> · Mero Bhoj Rider</title>
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
        <h1>Hi, <?= htmlspecialchars(explode(' ', trim((string)($panelUser['name'] ?? 'Rider')))[0]) ?> 👋</h1>
        <p>Claim a delivery, pick it up, and hand it over with the customer's code.</p>
    </div>
    <span class="panel-live-badge"><span class="panel-live-dot"></span> Live</span>
</div>

<div class="panel-stats">
    <div class="panel-stat-card">
        <div class="panel-stat-icon"><span class="material-symbols-sharp">inventory_2</span></div>
        <div class="panel-stat-info">
            <h3 data-stat="pool">0</h3>
            <p>In pool</p>
        </div>
    </div>
    <div class="panel-stat-card">
        <div class="panel-stat-icon"><span class="material-symbols-sharp">two_wheeler</span></div>
        <div class="panel-stat-info">
            <h3 data-stat="active">0</h3>
            <p>My active</p>
        </div>
    </div>
    <div class="panel-stat-card">
        <div class="panel-stat-icon"><span class="material-symbols-sharp">task_alt</span></div>
        <div class="panel-stat-info">
            <h3 data-stat="done">0</h3>
            <p>Delivered today</p>
        </div>
    </div>
    <div class="panel-stat-card">
        <div class="panel-stat-icon"><span class="material-symbols-sharp">payments</span></div>
        <div class="panel-stat-info">
            <h3 data-stat="earned">Rs. 0</h3>
            <p>Earned today</p>
        </div>
    </div>
</div>

<div id="riderDashboard" data-poll="5000">

    <div class="rider-section-head">
        <h2><span class="material-symbols-sharp">local_shipping</span> My deliveries</h2>
    </div>
    <div class="rider-job-list" id="mineList">
        <div class="rider-empty">Loading…</div>
    </div>

    <div class="rider-section-head">
        <h2><span class="material-symbols-sharp">pin_drop</span> Available to claim</h2>
        <span class="rider-hint">First to claim gets the job</span>
    </div>
    <div class="rider-job-list" id="poolList">
        <div class="rider-empty">Loading…</div>
    </div>

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
