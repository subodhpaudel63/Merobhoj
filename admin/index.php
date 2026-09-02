<?php
// Start session if not already started
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

// Fetch dashboard data
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
$shipping_orders = $delivering_orders_query->fetch_assoc()['delivering'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard </title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
</head>
<body class="admin-page">
   <?php include_once __DIR__ . '/topbar.php'; ?>
   <div class="container">
      <?php include_once __DIR__ . '/sidebar.php'; ?>
      <!-- --------------
        end asid
      -------------------- -->

      <!-- --------------
        start main part
      --------------- -->

      <main class="admin-page-main">
           <h1>Dashbord</h1>

           <div class="date">
             <input type="date" >
           </div>

        <div class="insights">

           <!-- start seling -->
            <div class="sales">
               <span class="material-symbols-sharp">trending_up</span>
               <div class="middle">

                 <div class="left">
                   <h3>Total Sales</h3>
                   <h1 class="total-revenue-display">Rs <?php echo number_format($total_revenue, 2); ?></h1>
                 </div>
                  <div class="progress">
                      <svg>
                         <circle  r="30" cy="40" cx="40"></circle>
                      </svg>
                      <div class="number"><p>80%</p></div>
                  </div>

               </div>
               <small>Last 24 Hours</small>
            </div>
           <!-- end seling -->
              <!-- start expenses -->
              <div class="expenses">
                <span class="material-symbols-sharp">local_mall</span>
                <div class="middle">
 
                  <div class="left">
                    <h3>Total Orders</h3>
                    <h1 class="total-orders-display"><?php echo $total_orders; ?></h1>
                  </div>
                   <div class="progress">
                       <svg>
                          <circle  r="30" cy="40" cx="40"></circle>
                       </svg>
                       <div class="number"><p>80%</p></div>
                   </div>
 
                </div>
                <small>Last 24 Hours</small>
             </div>
            <!-- end seling -->
               <!-- start seling -->
               <div class="income">
                <span class="material-symbols-sharp">stacked_line_chart</span>
                <div class="middle">
 
                  <div class="left">
                    <h3>Confirmed Orders</h3>
                    <h1><?php echo $confirmed_orders; ?></h1>
                  </div>
                   <div class="progress">
                       <svg>
                          <circle  r="30" cy="40" cx="40"></circle>
                       </svg>
                       <div class="number"><p>80%</p></div>
                   </div>
 
                </div>
                <small>Last 24 Hours</small>
             </div>
            <!-- end seling -->

        </div>
       <!-- end insights -->
      <div class="recent_order">
         <h2>Recent Orders</h2>
         <table> 
             <thead>
              <tr>
                <th>Product Name</th>
                <th>Product Number</th>
                <th>Payments</th>
                <th>Status</th>
              </tr>
             </thead>
              <tbody>
                <?php if (empty($orders)): ?>
                  <tr>
                    <td colspan="4" class="text-center">No recent orders</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($orders as $order): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($order['menu_name']); ?></td>
                      <td>#<?php echo $order['order_id']; ?></td>
                      <td>Rs. <?php echo number_format($order['total_price'], 2); ?></td>
                      <td class="status-<?php echo strtolower($order['status']); ?>">
                        <?php echo $order['status']; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
         </table>
         <a href="#">Show All</a>
      </div>

      </main>
      <!------------------
         end main
        ------------------->

      <!----------------
        start right main 
      ---------------------->
    <div class="right">

<div class="top">
   <div class="theme-toggler">
     <span class="material-symbols-sharp active">light_mode</span>
     <span class="material-symbols-sharp">dark_mode</span>
   </div>
    <div class="profile">
       <div class="info">
           <p><b>Subodh Admin</b></p>
           <p>Administrator</p>
           <small class="text-muted">Online</small>
       </div>
       <div class="profile-photo">
         <img src="../assets/img/usersprofiles/adminpic.jpg" alt="Admin Profile"/>
       </div>
    </div>
</div>

  <div class="recent_updates">
     <h2>Recent Update</h2>
   <div class="updates">
      <div class="update">
         <div class="profile-photo">
            <img src="../assets/img/usersprofiles/profilepic.jpg" alt=""/>
         </div>
        <div class="message">
           <p><b>Subodh</b> Recived his order of USB</p>
        </div>
      </div>
      <div class="update">
        <div class="profile-photo">
        <img src="../assets/img/usersprofiles/profilepic.jpg" alt=""/>
        </div>
       <div class="message">
          <p><b>Hari</b> Recived his order of USB</p>
       </div>
     </div>
     <div class="update">
      <div class="profile-photo">
         <img src="../assets/img/usersprofiles/profilepic.jpg" alt=""/>
      </div>
     <div class="message">
        <p><b>Sita</b> Recived his order of USB</p>
     </div>
   </div>
  </div>
  </div>


   <div class="sales-analytics">
     <h2>Sales Analytics</h2>

      <div class="item onlion">
        <div class="icon">
          <span class="material-symbols-sharp">shopping_cart</span>
        </div>
        <div class="right_text">
          <div class="info">
            <h3>Onlion Orders</h3>
            <small class="text-muted">Last seen 2 Hours</small>
          </div>
          <h5 class="danger">-17%</h5>
          <h3>3849</h3>
        </div>
      </div>
      <div class="item onlion">
        <div class="icon">
          <span class="material-symbols-sharp">shopping_cart</span>
        </div>
        <div class="right_text">
          <div class="info">
            <h3>Onlion Orders</h3>
            <small class="text-muted">Last seen 2 Hours</small>
          </div>
          <h5 class="success">-17%</h5>
          <h3>3849</h3>
        </div>
      </div>
      <div class="item onlion">
        <div class="icon">
          <span class="material-symbols-sharp">shopping_cart</span>
        </div>
        <div class="right_text">
          <div class="info">
            <h3>Onlion Orders</h3>
            <small class="text-muted">Last seen 2 Hours</small>
          </div>
          <h5 class="danger">-17%</h5>
          <h3>3849</h3>
        </div>
      </div>
   
  

</div>

      <div class="item add_product">
            <div>
            <span class="material-symbols-sharp">add</span>
            </div>
     </div>
</div>


   </div>



   
   <script>
   // Real-time dashboard updates
   document.addEventListener('DOMContentLoaded', function() {
       // Update dashboard stats every 30 seconds
       setInterval(updateDashboardStats, 30000);
       
       function updateDashboardStats() {
           fetch('get_dashboard_stats.php')
           .then(response => response.json())
           .then(data => {
               if (data.success) {
                   // Update total revenue
                   const revenueElement = document.querySelector('.total-revenue-display');
                   if (revenueElement) {
                       const oldValue = parseFloat(revenueElement.textContent.replace('Rs ', '').replace(/,/g, ''));
                       const newValue = parseFloat(data.stats.total_revenue);
                       
                       if (oldValue !== newValue) {
                           revenueElement.textContent = 'Rs ' + newValue.toFixed(2);
                           revenueElement.classList.add('updating');
                           setTimeout(() => {
                               revenueElement.classList.remove('updating');
                           }, 1000);
                       }
                   }
                   
                   // Update total orders
                   const ordersElement = document.querySelector('.total-orders-display');
                   if (ordersElement) {
                       const oldValue = parseInt(ordersElement.textContent);
                       const newValue = parseInt(data.stats.total_orders);
                       
                       if (oldValue !== newValue) {
                           ordersElement.textContent = newValue;
                           ordersElement.classList.add('updating');
                           setTimeout(() => {
                               ordersElement.classList.remove('updating');
                           }, 1000);
                       }
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
