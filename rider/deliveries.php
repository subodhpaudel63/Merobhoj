<?php
/**
 * Rider — my deliveries: active jobs (with the same action card as the
 * dashboard) plus a history table of everything already delivered or cancelled.
 * Rendered by assets/js/panel_rider.js from rider/api/deliveries.php
 * (?scope=mine and ?scope=history) — both scopes are bound to the session rider.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'rider');
$pageTitle = 'My Deliveries';
$pageScripts = ['../assets/js/panel_rider.js'];

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
        <h1>My Deliveries</h1>
        <p>Everything assigned to you — in progress and completed.</p>
    </div>
    <span class="panel-live-badge"><span class="panel-live-dot"></span> Live</span>
</div>

<div id="riderDeliveriesPage" data-poll="8000">

    <div class="rider-section-head">
        <h2><span class="material-symbols-sharp">two_wheeler</span> In progress</h2>
    </div>
    <div class="rider-job-list" id="activeList">
        <div class="rider-empty">Loading…</div>
    </div>

    <div class="rider-section-head">
        <h2><span class="material-symbols-sharp">history</span> History</h2>
    </div>
    <div class="panel-table-wrap">
        <table class="panel-table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Address</th>
                    <th>Status</th>
                    <th>Completed</th>
                    <th style="text-align:right;">Fee</th>
                </tr>
            </thead>
            <tbody id="historyBody">
                <tr><td colspan="6" style="text-align:center; padding:1.5rem;">Loading…</td></tr>
            </tbody>
        </table>
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
