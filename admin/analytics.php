<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

// Get date range parameters
$date_range = $_GET['date_range'] ?? 'week';
$custom_start = $_GET['start_date'] ?? '';
$custom_end = $_GET['end_date'] ?? '';

// Set date range
switch ($date_range) {
    case 'today':
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d');
        break;
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
        break;
    case 'month':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = date('Y-m-d');
        break;
    case 'custom':
        $start_date = $custom_start ?: date('Y-m-d', strtotime('-7 days'));
        $end_date = $custom_end ?: date('Y-m-d');
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $end_date = date('Y-m-d');
}

// Fetch analytics data
$sales_data = [];
$order_stats = [];
$menu_stats = [];
$booking_stats = [];
$customer_stats = [];

$sales_query = $conn->prepare("
    SELECT DATE(order_date) as date,
           COUNT(*) as order_count,
           SUM(total_price) as revenue
    FROM orders
    WHERE order_date BETWEEN ? AND ?
    GROUP BY DATE(order_date)
    ORDER BY DATE(order_date)
");
$sales_query->bind_param("ss", $start_date, $end_date);
$sales_query->execute();
$sales_result = $sales_query->get_result();
while ($row = $sales_result->fetch_assoc()) {
    $row['revenue'] = (float)$row['revenue'];
    $row['order_count'] = (int)$row['order_count'];
    $sales_data[] = $row;
}

$order_stats_query = $conn->prepare("
    SELECT status, COUNT(*) as count, SUM(total_price) as revenue
    FROM orders
    WHERE order_date BETWEEN ? AND ?
    GROUP BY status
");
$order_stats_query->bind_param("ss", $start_date, $end_date);
$order_stats_query->execute();
$order_stats_result = $order_stats_query->get_result();
while ($row = $order_stats_result->fetch_assoc()) {
    $row['count'] = (int)$row['count'];
    $row['revenue'] = (float)$row['revenue'];
    $order_stats[] = $row;
}

$menu_query = $conn->prepare("
    SELECT menu_name,
           COUNT(*) as order_count,
           SUM(quantity) as total_quantity,
           SUM(total_price) as revenue
    FROM orders
    WHERE order_date BETWEEN ? AND ?
    GROUP BY menu_id, menu_name
    ORDER BY order_count DESC
");
$menu_query->bind_param("ss", $start_date, $end_date);
$menu_query->execute();
$menu_result = $menu_query->get_result();
while ($row = $menu_result->fetch_assoc()) {
    $row['revenue'] = (float)$row['revenue'];
    $row['order_count'] = (int)$row['order_count'];
    $row['total_quantity'] = (int)$row['total_quantity'];
    $menu_stats[] = $row;
}

$booking_query = $conn->prepare("
    SELECT status, COUNT(*) as count
    FROM bookings
    WHERE booking_date BETWEEN ? AND ?
    GROUP BY status
");
$booking_query->bind_param("ss", $start_date, $end_date);
$booking_query->execute();
$booking_result = $booking_query->get_result();
while ($row = $booking_result->fetch_assoc()) {
    $row['count'] = (int)$row['count'];
    $booking_stats[] = $row;
}

$customer_query = $conn->prepare("
    SELECT email, COUNT(*) as order_count
    FROM orders
    WHERE order_date BETWEEN ? AND ?
    GROUP BY email
    ORDER BY order_count DESC
");
$customer_query->bind_param("ss", $start_date, $end_date);
$customer_query->execute();
$customer_result = $customer_query->get_result();
while ($row = $customer_result->fetch_assoc()) {
    $customer_stats[] = $row;
}

$total_revenue = array_sum(array_column($sales_data, 'revenue'));
$total_orders = array_sum(array_column($sales_data, 'order_count'));
$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics Dashboard - Masu Ko Jhol</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="../assets/css/adminstyle.css">
  <style>
    .analytics-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.5rem;
      margin-top: 2rem;
    }
    .analytics-card {
      background: var(--clr-white);
      border-radius: var(--card-border-radius);
      padding: var(--card-padding);
      box-shadow: var(--box-shadow);
      transition: all 0.3s ease;
    }
    .analytics-card:hover { box-shadow: none; transform: translateY(-5px); }
    .chart-container { height: 300px; margin: 1rem 0; }
    .filter-section {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 2rem;
      padding: 1rem;
      background: var(--clr-white);
      border-radius: var(--border-radius-2);
      box-shadow: var(--box-shadow);
    }
    .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
    .filter-group label { font-weight: 600; color: var(--clr-dark); }
    .filter-group select, .filter-group input {
      padding: 0.5rem;
      border: 1px solid var(--clr-info-light);
      border-radius: var(--border-radius-1);
    }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin: 1rem 0;
    }
    .stat-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 1.5rem;
      border-radius: var(--border-radius-2);
      text-align: center;
    }
    .stat-card h3 { font-size: 2rem; margin: 0.5rem 0; color: white; }
    .stat-card p { margin: 0; color: rgba(255,255,255,0.9); }
    .export-buttons { display: flex; gap: 1rem; margin: 1rem 0; }
    .btn-export {
      padding: 0.5rem 1rem;
      border: none;
      border-radius: var(--border-radius-1);
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    .btn-csv { background: #28a745; color: white; }
    .btn-pdf { background: #dc3545; color: white; }
    .btn-export:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
    .table-responsive { overflow-x: auto; margin: 1rem 0; }
    .analytics-table { width: 100%; border-collapse: collapse; }
    .analytics-table th, .analytics-table td { padding: 0.8rem; border-bottom: 1px solid var(--clr-info-light); text-align: left; }
  </style>
</head>
<body class="admin-page analytics-page">
  <div class="container">
    <?php include_once __DIR__ . '/sidebar.php'; ?>

    <main class="admin-page-main">
      <div class="admin-topbar" aria-label="Admin toolbar">
          <button type="button" id="menu_bar" class="admin-menu-button" aria-label="Open navigation">
              <span class="material-symbols-sharp">menu</span>
          </button>
          <div class="admin-topbar-actions">
              <div class="theme-toggler" aria-label="Change color theme">
                  <span class="material-symbols-sharp active">light_mode</span>
                  <span class="material-symbols-sharp">dark_mode</span>
              </div>
              <div class="admin-profile">
                  <div class="admin-profile-copy">
                      <strong>Subodh Admin</strong>
                      <small>Administrator</small>
                  </div>
                  <div class="profile-photo">
                      <img src="../assets/img/usersprofiles/adminpic.jpg" alt="Admin profile">
                  </div>
              </div>
          </div>
      </div>

      <div class="admin-page-heading">
        <div>
          <h1>Analytics</h1>
          <p>Overview of your business performance</p>
        </div>
      </div>

      <div class="filter-section">
        <div class="filter-group">
          <label>Date Range</label>
          <select id="dateRange" onchange="updateFilters()">
            <option value="today" <?php echo $date_range==='today'?'selected':''; ?>>Today</option>
            <option value="week" <?php echo $date_range==='week'?'selected':''; ?>>Week</option>
            <option value="month" <?php echo $date_range==='month'?'selected':''; ?>>Month</option>
            <option value="custom" <?php echo $date_range==='custom'?'selected':''; ?>>Custom</option>
          </select>
        </div>
        <div class="filter-group" id="customDateFields" style="display: <?php echo $date_range==='custom' ? 'flex' : 'none'; ?>; flex-direction:row; gap:1rem;">
          <div>
            <label>Start Date</label>
            <input type="date" id="startDate" value="<?php echo htmlspecialchars($custom_start); ?>">
          </div>
          <div>
            <label>End Date</label>
            <input type="date" id="endDate" value="<?php echo htmlspecialchars($custom_end); ?>">
          </div>
        </div>
        <div class="filter-group" style="justify-content:flex-end;">
          <label>&nbsp;</label>
          <button onclick="applyFilters()" class="btn-export">Apply Filters</button>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <span class="material-symbols-sharp">payments</span>
          <h3>Rs. <?php echo number_format($total_revenue, 2); ?></h3>
          <p>Total Revenue</p>
        </div>
        <div class="stat-card">
          <span class="material-symbols-sharp">shopping_cart</span>
          <h3><?php echo number_format($total_orders); ?></h3>
          <p>Total Orders</p>
        </div>
        <div class="stat-card">
          <span class="material-symbols-sharp">trending_up</span>
          <h3>Rs. <?php echo number_format($avg_order_value, 2); ?></h3>
          <p>Average Order Value</p>
        </div>
        <div class="stat-card">
          <span class="material-symbols-sharp">groups</span>
          <h3><?php echo count($customer_stats); ?></h3>
          <p>New Customers</p>
        </div>
      </div>

      <div class="export-buttons">
        <button class="btn-export btn-csv" onclick="exportCSV()">Export CSV</button>
        <button class="btn-export btn-pdf" onclick="exportPDF()">Export PDF</button>
      </div>

      <div class="analytics-container">
        <div class="analytics-card">
          <h2>Sales Trends</h2>
          <div class="chart-container"><canvas id="salesChart"></canvas></div>
          <script type="application/json" id="analyticsSalesData"><?php echo json_encode($sales_data); ?></script>
        </div>
        <div class="analytics-card">
          <h2>Order Status Distribution</h2>
          <div class="chart-container"><canvas id="orderStatusChart"></canvas></div>
          <script type="application/json" id="analyticsOrderStats"><?php echo json_encode($order_stats); ?></script>
        </div>
        <div class="analytics-card">
          <h2>Top Selling Items</h2>
          <div class="table-responsive">
            <table class="analytics-table">
              <thead>
                <tr><th>Item</th><th>Orders</th><th>Revenue</th></tr>
              </thead>
              <tbody>
                <?php foreach ($menu_stats as $item): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($item['menu_name']); ?></td>
                    <td><?php echo number_format($item['order_count']); ?></td>
                    <td>Rs. <?php echo number_format($item['revenue'], 2); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="../assets/js/adminscript.js"></script>
</body>
</html>
