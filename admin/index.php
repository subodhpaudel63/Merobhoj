<?php
declare(strict_types=1);
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

// Fetch recent orders
$orders_query = $conn->query("SELECT * FROM orders ORDER BY order_id DESC LIMIT 5");
$orders = [];
if ($orders_query) {
    while ($row = $orders_query->fetch_assoc()) {
        $row['total_price'] = (float)$row['total_price'];
        $orders[] = $row;
    }
}

// Calculate dashboard statistics
$total_revenue_query = $conn->query("SELECT SUM(total_price) as revenue FROM orders");
$total_revenue = (float)($total_revenue_query->fetch_assoc()['revenue'] ?? 0);

$total_orders_query = $conn->query("SELECT COUNT(*) as total FROM orders");
$total_orders = $total_orders_query->fetch_assoc()['total'];

$confirmed_orders_query = $conn->query("SELECT COUNT(*) as confirmed FROM orders WHERE status = 'Confirmed'");
$confirmed_orders = $confirmed_orders_query->fetch_assoc()['confirmed'];

$delivering_orders_query = $conn->query("SELECT COUNT(*) as delivering FROM orders WHERE status = 'Delivering'");
$delivering_orders = $delivering_orders_query->fetch_assoc()['delivering'];

$pending_orders_query = $conn->query("SELECT COUNT(*) as pending FROM orders WHERE status = 'Pending'");
$pending_orders = $pending_orders_query->fetch_assoc()['pending'];

$completed_orders_query = $conn->query("SELECT COUNT(*) as completed FROM orders WHERE status = 'Completed'");
$completed_orders = $completed_orders_query->fetch_assoc()['completed'];

$cancelled_orders_query = $conn->query("SELECT COUNT(*) as cancelled FROM orders WHERE status = 'Cancelled'");
$cancelled_orders = $cancelled_orders_query->fetch_assoc()['cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - Mero Bhoj</title>
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
      
      <!-- Date Picker -->
      <div class="date">
        <input type="date">
      </div>

      <!-- Insights Cards -->
      <div class="insights">
        <!-- Total Sales -->
        <div class="sales">
          <span class="material-symbols-sharp">trending_up</span>
          <div class="middle">
            <div class="left">
              <h3>Total Sales</h3>
              <h1 class="total-revenue-display">Rs <?php echo number_format($total_revenue, 2); ?></h1>
            </div>
            <div class="progress">
              <svg>
                <circle r="30" cy="40" cx="40"></circle>
              </svg>
              <div class="number"><p>80%</p></div>
            </div>
          </div>
          <small>Last 24 Hours</small>
        </div>

        <!-- Total Orders -->
        <div class="expenses">
          <span class="material-symbols-sharp">local_mall</span>
          <div class="middle">
            <div class="left">
              <h3>Total Orders</h3>
              <h1 class="total-orders-display"><?php echo $total_orders; ?></h1>
            </div>
            <div class="progress">
              <svg>
                <circle r="30" cy="40" cx="40"></circle>
              </svg>
              <div class="number"><p>62%</p></div>
            </div>
          </div>
          <small>Last 24 Hours</small>
        </div>

        <!-- Confirmed Orders -->
        <div class="income">
          <span class="material-symbols-sharp">inventory</span>
          <div class="middle">
            <div class="left">
              <h3>Confirmed</h3>
              <h1><?php echo $confirmed_orders; ?></h1>
            </div>
            <div class="progress">
              <svg>
                <circle r="30" cy="40" cx="40"></circle>
              </svg>
              <div class="number"><p>41%</p></div>
            </div>
          </div>
          <small>Last 24 Hours</small>
        </div>
      </div>

      <!-- Order Status Cards -->
      <div class="insights">
        <!-- Pending -->
        <div class="sales">
          <span class="material-symbols-sharp">pending</span>
          <div class="middle">
            <div class="left">
              <h3>Pending</h3>
              <h1><?php echo $pending_orders; ?></h1>
            </div>
          </div>
          <small>Orders</small>
        </div>

        <!-- Delivering -->
        <div class="expenses">
          <span class="material-symbols-sharp">local_shipping</span>
          <div class="middle">
            <div class="left">
              <h3>Delivering</h3>
              <h1><?php echo $delivering_orders; ?></h1>
            </div>
          </div>
          <small>Orders</small>
        </div>

        <!-- Completed -->
        <div class="income">
          <span class="material-symbols-sharp">check_circle</span>
          <div class="middle">
            <div class="left">
              <h3>Completed</h3>
              <h1><?php echo $completed_orders; ?></h1>
            </div>
          </div>
          <small>Orders</small>
        </div>

        <!-- Cancelled -->
        <div class="sales">
          <span class="material-symbols-sharp">cancel</span>
          <div class="middle">
            <div class="left">
              <h3>Cancelled</h3>
              <h1><?php echo $cancelled_orders; ?></h1>
            </div>
          </div>
          <small>Orders</small>
        </div>
      </div>

      <!-- Recent Orders -->
      <div class="recent-orders">
        <h2>Recent Orders</h2>
        <table>
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Customer</th>
              <th>Item</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Payment</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $order): ?>
            <tr>
              <td><?php echo htmlspecialchars($order['order_number'] ?? 'ORD-' . $order['order_id']); ?></td>
              <td><?php echo htmlspecialchars($order['full_name'] ?? 'N/A'); ?></td>
              <td><?php echo htmlspecialchars($order['menu_name'] ?? 'N/A'); ?></td>
              <td>Rs <?php echo number_format($order['total_price'], 2); ?></td>
              <td class="status-<?php echo strtolower($order['status'] ?? 'pending'); ?>"><?php echo htmlspecialchars($order['status'] ?? 'Pending'); ?></td>
              <td><?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <!-- Real-time dashboard updates -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      setInterval(updateDashboardStats, 30000);

      function updateDashboardStats() {
        fetch('get_dashboard_stats.php')
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              const revenueElement = document.querySelector('.total-revenue-display');
              if (revenueElement) {
                revenueElement.textContent = 'Rs ' + Number(data.stats.total_revenue).toFixed(2);
              }
              const ordersElement = document.querySelector('.total-orders-display');
              if (ordersElement) {
                ordersElement.textContent = data.stats.total_orders;
              }
            }
          })
          .catch(error => {
            console.error('Error updating dashboard:', error);
          });
      }
    });
  </script>
  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
</body>
</html>