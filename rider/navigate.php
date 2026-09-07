<?php
/**
 * Rider — turn-by-turn view for ONE delivery.
 *
 * Ownership is enforced here, server-side: the order must exist, be a Delivery,
 * be assigned to the logged-in rider and still be active. Anything else bounces
 * back to the deliveries list — a rider cannot open another rider's job by
 * editing the ?order= parameter.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'rider');
require_once __DIR__ . '/../includes/delivery_helpers.php';

$riderId     = (int)($panelUser['id'] ?? 0);
$orderNumber = trim((string)($_GET['order'] ?? ''));

$job = null;
if ($orderNumber !== '') {
    $stmt = $conn->prepare("SELECT o.order_number, o.status, o.full_name, o.mobile, o.address,
                                   o.special_instructions, o.payment_method, o.payment_status,
                                   d.delivery_fee, d.dest_lat, d.dest_lng,
                                   SUM(o.price * o.quantity) AS order_total
                            FROM orders o
                            JOIN deliveries d ON d.order_number = o.order_number
                            WHERE o.order_number = ? AND d.rider_id = ?
                              AND o.order_type = 'Delivery'
                              AND o.status IN ('Ready','Delivering')
                            GROUP BY o.order_number
                            LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('si', $orderNumber, $riderId);
        $stmt->execute();
        $res = $stmt->get_result();
        $job = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
}

if (!$job) {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'That delivery is not available to you.'];
    header('Location: /Merobhoj/rider/deliveries.php');
    exit();
}

// Line items for the job card.
$items = [];
$iq = $conn->prepare("SELECT menu_name, quantity FROM orders WHERE order_number = ? ORDER BY order_id ASC");
if ($iq) {
    $iq->bind_param('s', $orderNumber);
    $iq->execute();
    $ir = $iq->get_result();
    while ($ir && ($row = $ir->fetch_assoc())) {
        $items[] = $row;
    }
    $iq->close();
}

$state      = delivery_state((string)$job['status'], $riderId);
$stateLabel = delivery_state_label($state);
$address    = (string)($job['address'] ?? '');
$mapsUrl    = 'https://www.openstreetmap.org/search?query=' . rawurlencode($address !== '' ? $address . ', Pokhara, Nepal' : 'Pokhara, Nepal');

$pageTitle = 'Navigate · ' . $orderNumber;
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
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body class="admin-page">
  <?php include __DIR__ . '/topbar.php'; ?>
  <div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-page-main">


<div class="panel-page-header">
    <div>
        <h1>Navigate</h1>
        <p><?= htmlspecialchars($orderNumber) ?> · <?= htmlspecialchars((string)($job['full_name'] ?: 'Customer')) ?></p>
    </div>
    <a href="deliveries.php" class="qrm-btn qrm-btn-secondary">
        <span class="material-symbols-sharp">arrow_back</span> Back
    </a>
</div>

<div id="riderNavigate"
     data-order="<?= htmlspecialchars($orderNumber) ?>"
     data-status="<?= htmlspecialchars((string)$job['status']) ?>"
     data-address="<?= htmlspecialchars($address) ?>"
     data-dest-lat="<?= $job['dest_lat'] !== null ? htmlspecialchars((string)$job['dest_lat']) : '' ?>"
     data-dest-lng="<?= $job['dest_lng'] !== null ? htmlspecialchars((string)$job['dest_lng']) : '' ?>">

    <div class="rider-nav-layout">

        <div class="rider-map-wrap">
            <div id="riderMap"></div>
            <div class="rider-map-readout">
                <div>
                    <small>Distance</small>
                    <strong id="navDist">—</strong>
                </div>
                <div>
                    <small>ETA</small>
                    <strong id="navEta">—</strong>
                </div>
                <div>
                    <small>GPS</small>
                    <strong id="navFix">Off</strong>
                </div>
            </div>
        </div>

        <div class="rider-nav-side">
            <div class="rider-job-card is-mine">
                <div class="rider-job-head">
                    <div>
                        <strong><?= htmlspecialchars($orderNumber) ?></strong>
                        <span class="panel-status st-<?= htmlspecialchars($state) ?>"><?= htmlspecialchars($stateLabel) ?></span>
                    </div>
                    <span class="rider-fee">Rs. <?= number_format((float)$job['delivery_fee'], 0) ?></span>
                </div>
                <div class="rider-job-body">
                    <p class="rider-line">
                        <span class="material-symbols-sharp">person</span>
                        <?= htmlspecialchars((string)($job['full_name'] ?: 'Customer')) ?>
                    </p>
                    <?php if (!empty($job['mobile'])): ?>
                        <p class="rider-line">
                            <span class="material-symbols-sharp">call</span>
                            <a href="tel:<?= htmlspecialchars((string)$job['mobile']) ?>"><?= htmlspecialchars((string)$job['mobile']) ?></a>
                        </p>
                    <?php endif; ?>
                    <p class="rider-line">
                        <span class="material-symbols-sharp">home_pin</span>
                        <?= htmlspecialchars($address !== '' ? $address : 'No address on file') ?>
                    </p>
                    <?php if (!empty($job['special_instructions'])): ?>
                        <p class="rider-line rider-note">
                            <span class="material-symbols-sharp">sticky_note_2</span>
                            <?= htmlspecialchars((string)$job['special_instructions']) ?>
                        </p>
                    <?php endif; ?>
                    <p class="rider-line">
                        <span class="material-symbols-sharp">shopping_bag</span>
                        <?php
                        $labels = [];
                        foreach ($items as $it) {
                            $labels[] = htmlspecialchars((string)$it['menu_name']) . ' ×' . (int)$it['quantity'];
                        }
                        echo implode(', ', $labels) ?: 'No items';
                        ?>
                    </p>
                    <p class="rider-line">
                        <span class="material-symbols-sharp">payments</span>
                        Rs. <?= number_format((float)$job['order_total'], 0) ?>
                        · <?= htmlspecialchars((string)($job['payment_method'] ?: 'Cash')) ?>
                        (<?= htmlspecialchars((string)($job['payment_status'] ?: 'Unpaid')) ?>)
                    </p>
                </div>

                <!-- Picked Up / OTP handover, rendered by panel_rider.js -->
                <div class="rider-actions" id="navActions"></div>

                <a class="qrm-btn qrm-btn-secondary rider-big-btn" href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank" rel="noopener">
                    <span class="material-symbols-sharp">open_in_new</span> Open map search
                </a>
            </div>
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
