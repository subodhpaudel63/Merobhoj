<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

$today = date('Y-m-d');
$seven_days_ago = date('Y-m-d', strtotime('-6 days'));

// Calculate dashboard statistics
$total_revenue_query = $conn->query("SELECT SUM(total_price) as revenue FROM orders");
$total_revenue = (float)($total_revenue_query->fetch_assoc()['revenue'] ?? 0);

$delivering_orders_query = $conn->query("SELECT COUNT(*) as delivering FROM orders WHERE status = 'Delivering'");
$delivering_orders = $delivering_orders_query->fetch_assoc()['delivering'];

$today_revenue_query = $conn->query("SELECT COALESCE(SUM(total_price), 0) AS revenue, COUNT(DISTINCT order_number) AS orders FROM orders WHERE order_date = '$today' AND status <> 'Cancelled'");
$today_summary = $today_revenue_query ? $today_revenue_query->fetch_assoc() : ['revenue' => 0, 'orders' => 0];
$today_revenue = (float)$today_summary['revenue'];
$today_order_count = (int)$today_summary['orders'];
$average_bill = $today_order_count > 0 ? $today_revenue / $today_order_count : 0;

$staff_query = $conn->query("SELECT COUNT(*) AS total FROM users WHERE user_type IN ('admin', 'staff')");
$staff_on_duty = $staff_query ? (int)$staff_query->fetch_assoc()['total'] : 0;

$today_expenses = 0;
try {
    $expense_query = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE date = ?");
    $expense_query->bind_param("s", $today);
    $expense_query->execute();
    $today_expenses = (float)$expense_query->get_result()->fetch_assoc()['total'];
} catch (Exception $e) {
    $today_expenses = 0;
}

$daily_revenue = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_revenue[$date] = ['label' => date('D', strtotime($date)), 'revenue' => 0, 'orders' => 0];
}
$daily_query = $conn->query("SELECT order_date, SUM(total_price) AS revenue, COUNT(DISTINCT order_number) AS orders FROM orders WHERE order_date BETWEEN '$seven_days_ago' AND '$today' AND status <> 'Cancelled' GROUP BY order_date");
if ($daily_query) {
    while ($row = $daily_query->fetch_assoc()) {
        if (isset($daily_revenue[$row['order_date']])) {
            $daily_revenue[$row['order_date']]['revenue'] = (float)$row['revenue'];
            $daily_revenue[$row['order_date']]['orders'] = (int)$row['orders'];
        }
    }
}
$max_daily_revenue = max(1, ...array_column($daily_revenue, 'revenue'));

$top_items = [];
$top_items_query = $conn->query("SELECT menu_name, SUM(quantity) AS quantity, SUM(total_price) AS revenue FROM orders WHERE order_date = '$today' AND status <> 'Cancelled' GROUP BY menu_name ORDER BY quantity DESC LIMIT 5");
if ($top_items_query) {
    while ($row = $top_items_query->fetch_assoc()) {
        $top_items[] = $row;
    }
}

$active_orders_query = $conn->query("SELECT order_number, full_name, status, total_price FROM orders WHERE status IN ('Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivering') ORDER BY order_id DESC LIMIT 5");
$active_orders = [];
if ($active_orders_query) {
    while ($row = $active_orders_query->fetch_assoc()) {
        $active_orders[] = $row;
    }
}

$pending_self_query = $conn->query("SELECT COUNT(*) AS total FROM qr_requests WHERE status = 'pending'");
$pending_self_orders = $pending_self_query ? (int)$pending_self_query->fetch_assoc()['total'] : 0;

$payment_mix = [];
$payment_query = $conn->query("SELECT COALESCE(NULLIF(payment_method, ''), 'Cash') AS method, SUM(total_price) AS total FROM orders WHERE order_date = '$today' AND payment_status = 'Paid' AND status <> 'Cancelled' GROUP BY method ORDER BY total DESC");
if ($payment_query) {
    while ($row = $payment_query->fetch_assoc()) {
        $payment_mix[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - Mero Bhoj</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="../assets/css/admin2.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin2.css') ?>">
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
</head>
<body class="admin-page">
  <?php include_once __DIR__ . '/topbar.php'; ?>
  <div class="container">
    <?php include_once __DIR__ . '/sidebar.php'; ?>
    
    <main class="admin-page-main">
      <h1>Dashboard</h1>
      
      <div class="dashboard-summary-grid">
        <div class="dashboard-summary-card"><span class="material-symbols-sharp">payments</span><div><small>Total revenue</small><strong>Rs <?php echo number_format($total_revenue, 2); ?></strong></div></div>
        <div class="dashboard-summary-card"><span class="material-symbols-sharp">receipt_long</span><div><small>Average bill value</small><strong>Rs <?php echo number_format($average_bill, 2); ?></strong></div></div>
        <div class="dashboard-summary-card"><span class="material-symbols-sharp">badge</span><div><small>Staff on duty</small><strong><?php echo $staff_on_duty; ?></strong></div></div>
        <div class="dashboard-summary-card"><span class="material-symbols-sharp">account_balance_wallet</span><div><small>Today's expenses</small><strong>Rs <?php echo number_format($today_expenses, 2); ?></strong></div></div>
      </div>

      <div class="dashboard-section-grid">
        <section class="dashboard-panel dashboard-chart-panel">
          <div class="dashboard-panel-heading">
            <div>
              <h2>7 Days Revenue &amp; Orders</h2>
              <p>Revenue and order count for the last seven days.</p>
            </div>
            <a class="dashboard-view-all" href="finance.php">View all</a>
          </div>
          <div class="revenue-bars" aria-label="Seven day revenue chart">
            <?php foreach ($daily_revenue as $day): ?>
              <div class="revenue-bar-item">
                <span class="revenue-bar-value">Rs <?php echo number_format($day['revenue'], 0); ?></span>
                <div class="revenue-bar-track"><div class="revenue-bar" style="height:<?php echo max(4, ($day['revenue'] / $max_daily_revenue) * 100); ?>%"></div></div>
                <small><?php echo htmlspecialchars($day['label']); ?> · <?php echo $day['orders']; ?> orders</small>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <section class="dashboard-panel">
          <div class="dashboard-panel-heading">
            <div><h2>Today's Top Items</h2><p>Best-selling items by quantity.</p></div>
            <a class="dashboard-view-all" href="orders_page.php">View all</a>
          </div>
          <?php if ($top_items): foreach ($top_items as $item): ?>
            <div class="dashboard-list-row"><span><?php echo htmlspecialchars($item['menu_name']); ?></span><strong><?php echo (int)$item['quantity']; ?> sold</strong></div>
          <?php endforeach; else: ?><p class="dashboard-empty">No items sold today.</p><?php endif; ?>
        </section>

        <section class="dashboard-panel">
          <div class="dashboard-panel-heading">
            <div><h2>Active Orders</h2><p>Orders currently in progress.</p></div>
            <a class="dashboard-view-all" href="orders_page.php">View all</a>
          </div>
          <?php if ($active_orders): foreach ($active_orders as $order): ?>
            <div class="dashboard-list-row"><span>#<?php echo htmlspecialchars($order['order_number'] ?: 'Order'); ?><small><?php echo htmlspecialchars($order['full_name'] ?: 'Customer'); ?></small></span><strong class="status-<?php echo strtolower($order['status']); ?>"><?php echo htmlspecialchars($order['status']); ?></strong></div>
          <?php endforeach; else: ?><p class="dashboard-empty">No active orders.</p><?php endif; ?>
        </section>

        <section class="dashboard-panel">
          <div class="dashboard-panel-heading"><div><h2>Payment methods</h2><p>Today's settled payment mix.</p></div><a class="dashboard-view-all" href="finance.php">View all</a></div>
          <?php if ($payment_mix): foreach ($payment_mix as $payment): ?>
            <div class="dashboard-list-row"><span><?php echo htmlspecialchars($payment['method']); ?></span><strong>Rs <?php echo number_format((float)$payment['total'], 2); ?></strong></div>
          <?php endforeach; else: ?><p class="dashboard-empty">No settled payments today.</p><?php endif; ?>
        </section>
      </div>

      <section class="dashboard-panel dashboard-operations">
        <div class="dashboard-panel-heading"><div><h2>Alerts &amp; Operations</h2><p>Items that may need your attention.</p></div><a class="dashboard-view-all" href="self_orders.php">View all</a></div>
        <div class="operations-grid">
          <a href="orders_page.php?status=delivering" class="operation-alert"><span class="material-symbols-sharp">local_shipping</span><span><strong><?php echo $delivering_orders; ?></strong>Pending deliveries</span></a>
          <a href="self_orders.php?filter=pending" class="operation-alert"><span class="material-symbols-sharp">qr_code_scanner</span><span><strong><?php echo $pending_self_orders; ?></strong>Pending self orders</span></a>
        </div>
      </section>

    </main>
  </div>
</body>
  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
</html>