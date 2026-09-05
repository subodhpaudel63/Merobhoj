<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'chef');

$pageTitle   = 'Kitchen Board';
$pageScripts = ['../assets/js/panel_kds.js'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> · Mero Bhoj Kitchen</title>
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
        <h1>Kitchen Board</h1>
        <p>Live tickets update automatically · <span id="kdsUpdated">syncing…</span></p>
        <small class="kds-shortcut-hint">Shortcuts: <kbd>1</kbd> Accept · <kbd>2</kbd> Cooking · <kbd>3</kbd> Ready</small>
    </div>
    <span class="panel-live-badge"><span class="panel-live-dot"></span> Live</span>
</div>

<!-- Summary tiles (values filled by panel_kds.js) -->
<div class="kds-stats">
    <div class="kds-stat is-new">
        <div class="kds-stat-icon"><span class="material-symbols-sharp">notifications_active</span></div>
        <div>
            <div class="kds-stat-value" data-kds-stat="new">0</div>
            <div class="kds-stat-label">New orders</div>
        </div>
    </div>
    <div class="kds-stat is-cooking">
        <div class="kds-stat-icon"><span class="material-symbols-sharp">skillet</span></div>
        <div>
            <div class="kds-stat-value" data-kds-stat="cooking">0</div>
            <div class="kds-stat-label">Cooking</div>
        </div>
    </div>
    <div class="kds-stat is-ready">
        <div class="kds-stat-icon"><span class="material-symbols-sharp">room_service</span></div>
        <div>
            <div class="kds-stat-value" data-kds-stat="ready">0</div>
            <div class="kds-stat-label">Ready</div>
        </div>
    </div>
    <div class="kds-stat is-delayed">
        <div class="kds-stat-icon"><span class="material-symbols-sharp">timer</span></div>
        <div>
            <div class="kds-stat-value" data-kds-stat="delayed">0</div>
            <div class="kds-stat-label">Delayed</div>
        </div>
    </div>
</div>

<!-- Four-column board: NEW → ACCEPTED → COOKING → READY -->
<div class="kds-board" id="kdsBoard">
    <div class="kds-col col-new">
        <div class="kds-col-head">
            <span>New</span>
            <span class="kds-col-count" data-col-count="Pending">0</span>
        </div>
        <div class="kds-col-body" data-col-body="Pending">
            <div class="kds-col-empty">No orders</div>
        </div>
    </div>

    <div class="kds-col col-accepted">
        <div class="kds-col-head">
            <span>Accepted</span>
            <span class="kds-col-count" data-col-count="Confirmed">0</span>
        </div>
        <div class="kds-col-body" data-col-body="Confirmed">
            <div class="kds-col-empty">No orders</div>
        </div>
    </div>

    <div class="kds-col col-cooking">
        <div class="kds-col-head">
            <span>Cooking</span>
            <span class="kds-col-count" data-col-count="Preparing">0</span>
        </div>
        <div class="kds-col-body" data-col-body="Preparing">
            <div class="kds-col-empty">No orders</div>
        </div>
    </div>

    <div class="kds-col col-ready">
        <div class="kds-col-head">
            <span>Ready</span>
            <span class="kds-col-count" data-col-count="Ready">0</span>
        </div>
        <div class="kds-col-body" data-col-body="Ready">
            <div class="kds-col-empty">No orders</div>
        </div>
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
