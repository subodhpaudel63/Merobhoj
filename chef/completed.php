<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'chef');

$pageTitle = 'Completed Today';
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
<?php
/** Group today's Ready/Completed rows by order_number. */
$sql = "SELECT order_id, order_number, menu_name, quantity, order_type, table_number,
               full_name, email, status, created_at
        FROM orders
        WHERE status IN ('Ready','Completed') AND DATE(created_at) = CURDATE()
        ORDER BY created_at DESC, order_id DESC";
$res = $conn->query($sql);

$groups = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $key = ($row['order_number'] !== null && $row['order_number'] !== '')
            ? $row['order_number']
            : 'ORD-' . str_pad((string)$row['order_id'], 4, '0', STR_PAD_LEFT);
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'order_number' => $key,
                'status'       => $row['status'],
                'order_type'   => $row['order_type'] ?: 'Delivery',
                'table_number' => $row['table_number'],
                'customer'     => $row['full_name'] ?: ($row['email'] ?: 'Guest'),
                'created_at'   => $row['created_at'],
                'items'        => [],
            ];
        }
        $groups[$key]['items'][] = ['name' => $row['menu_name'], 'quantity' => (int)$row['quantity']];
    }
}

$readyCount = 0; $doneCount = 0;
foreach ($groups as $g) { $g['status'] === 'Ready' ? $readyCount++ : $doneCount++; }

function chef_esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>

<div class="panel-page-header">
    <div>
        <h1>Completed Today</h1>
        <p><?= count($groups) ?> order<?= count($groups) === 1 ? '' : 's' ?> finished today · <?= $readyCount ?> ready, <?= $doneCount ?> completed.</p>
    </div>
    <a href="dashboard.php" class="qrm-btn qrm-btn-primary"><span class="material-symbols-sharp">arrow_back</span> Back to board</a>
</div>

<div class="panel-table-wrap">
    <table class="panel-table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Time</th>
                <th>Type</th>
                <th>Customer / Table</th>
                <th>Items</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($groups)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--clr-dark-variant);padding:2rem;">Nothing completed yet today.</td></tr>
        <?php else: foreach ($groups as $g):
            $time  = $g['created_at'] ? date('g:i A', strtotime($g['created_at'])) : '';
            $who   = $g['order_type'] === 'Dine In' && $g['table_number']
                        ? 'Table ' . chef_esc((string)$g['table_number'])
                        : chef_esc($g['customer']);
            $items = array_map(fn($it) => $it['quantity'] . '× ' . chef_esc($it['name']), $g['items']);
            $stCls = $g['status'] === 'Ready' ? 'st-ready' : 'st-completed';
        ?>
            <tr>
                <td><strong>#<?= chef_esc($g['order_number']) ?></strong></td>
                <td><?= chef_esc($time) ?></td>
                <td><?= chef_esc($g['order_type']) ?></td>
                <td><?= $who ?></td>
                <td style="white-space:normal;"><?= implode(', ', $items) ?></td>
                <td><span class="panel-status <?= $stCls ?>"><?= chef_esc($g['status']) ?></span></td>
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
</body>
</html>
