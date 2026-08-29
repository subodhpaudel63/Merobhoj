<?php
// Start session if not already started
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

$lastLoginColumnExists = false;
$colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
if ($colCheck && $colCheck->num_rows > 0) {
    $lastLoginColumnExists = true;
}

$users = [];
$userSelect = $lastLoginColumnExists
    ? "SELECT id, email, user_type, created_at, last_login FROM users ORDER BY id DESC"
    : "SELECT id, email, user_type, created_at FROM users ORDER BY id DESC";
$res = $conn->query($userSelect);
if ($res) { while ($row = $res->fetch_assoc()) { $users[] = $row; } }

$spendByEmail = [];
$spendRes = $conn->query("SELECT email, COALESCE(SUM(total_price), 0) AS total_spend FROM orders WHERE email IS NOT NULL AND email <> '' GROUP BY email");
if ($spendRes) {
    while ($row = $spendRes->fetch_assoc()) {
        $spendByEmail[strtolower(trim((string)$row['email']))] = (float)$row['total_spend'];
    }
}

$ordersByEmail = [];
$ordersRes = $conn->query("SELECT email, COUNT(*) AS total_orders FROM orders WHERE email IS NOT NULL AND email <> '' GROUP BY email");
if ($ordersRes) {
    while ($row = $ordersRes->fetch_assoc()) {
        $ordersByEmail[strtolower(trim((string)$row['email']))] = (int)$row['total_orders'];
    }
}

$newUsersCount = 0;
$returningUsersCount = 0;
$activeUsersCount = 0;
$sevenDaysAgo = new DateTimeImmutable('-7 days');
foreach ($users as $row) {
    $emailKey = strtolower(trim((string)($row['email'] ?? '')));
    $createdAt = !empty($row['created_at']) ? new DateTimeImmutable((string)$row['created_at']) : null;
    $ordersCount = $ordersByEmail[$emailKey] ?? 0;

    if ($createdAt && $createdAt >= $sevenDaysAgo) {
        $newUsersCount++;
    }
    if ($ordersCount > 1) {
        $returningUsersCount++;
    }
    if ($lastLoginColumnExists && !empty($row['last_login'])) {
        $activeUsersCount++;
    } elseif (!$lastLoginColumnExists && $ordersCount > 0) {
        $activeUsersCount++;
    }
}

// Calculate stats
$total_users = count($users);
$admin_users = 0;

foreach($users as $user) {
    if($user['user_type'] === 'admin') $admin_users++;
}
// All users are considered active since there's no is_active column
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Users Management - Mero Bhoj</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  <style>
    .users-page-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 1.75rem;
    }

    .users-page-header h1 {
      margin: 0;
    }

    .users-page-header p {
      margin: 0.35rem 0 0;
      color: var(--clr-dark-variant);
    }

    .users-summary {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1.25rem;
      margin-bottom: 1.75rem;
    }

    .users-summary-card {
      background: var(--clr-white);
      border-radius: var(--border-radius-2);
      padding: 1rem 1.1rem;
      box-shadow: var(--box-shadow);
      border: 1px solid rgba(148, 163, 184, 0.12);
    }

    .users-summary-card span {
      display: block;
      color: var(--clr-dark-variant);
      font-size: 0.82rem;
      font-weight: 600;
      margin-bottom: 0.45rem;
    }

    .users-summary-card strong {
      display: block;
      font-size: 1.45rem;
      color: var(--clr-dark);
    }

    .users-chart-card {
      background: var(--clr-white);
      border-radius: var(--border-radius-3);
      padding: 1.25rem;
      box-shadow: var(--box-shadow);
      margin-bottom: 1.75rem;
      display: grid;
      grid-template-columns: 220px 1fr;
      gap: 1.25rem;
      align-items: center;
    }

    .users-chart-wrap {
      position: relative;
      width: 220px;
      height: 220px;
      margin: 0 auto;
    }

    .users-chart-legend {
      display: grid;
      gap: 0.9rem;
    }

    .users-chart-item {
      display: flex;
      align-items: center;
      gap: 0.8rem;
    }

    .users-chart-dot {
      width: 12px;
      height: 12px;
      border-radius: 999px;
      flex: 0 0 auto;
    }

    .users-chart-item strong {
      display: block;
      color: var(--clr-dark);
    }

    .users-chart-item small {
      color: var(--clr-dark-variant);
    }

    .users-table-card {
      background: var(--clr-white);
      border-radius: var(--border-radius-3);
      padding: 1.25rem;
      box-shadow: var(--box-shadow);
      margin-bottom: 1.75rem;
    }

    .users-table-card h2 {
      margin-bottom: 1.25rem;
    }

    .add-user-form {
      gap: 1.25rem;
    }

    .form-group {
      margin-bottom: 0.25rem;
    }

    table {
      border-spacing: 0;
    }

    table th,
    table td {
      padding-top: 1rem;
      padding-bottom: 1rem;
    }

    @media screen and (max-width: 768px) {
      .users-chart-card {
        grid-template-columns: 1fr;
      }

      .users-chart-wrap {
        width: 180px;
        height: 180px;
      }
    }
  </style>
</head>
<body class="admin-page">
   <div class="container">
      <?php include_once __DIR__ . '/sidebar.php'; ?>
      <!-- --------------
        end asid
      -------------------- -->

      <!-- --------------
        start main part
      --------------- -->

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

           <div class="users-page-header">
             <div>
               <h1>Users Management</h1>
               <p>Manage accounts and track customer spending from one place.</p>
             </div>
           </div>

           <!-- Session Messages -->
           <?php if (isset($_SESSION['msg'])): ?>
             <script>
               document.addEventListener('DOMContentLoaded', function() {
               });
             </script>
             <?php unset($_SESSION['msg']); ?>
           <?php endif; ?>

           <div class="users-summary">
             <div class="users-summary-card">
               <span>Total Users</span>
               <strong><?php echo $total_users; ?></strong>
             </div>
             <div class="users-summary-card">
               <span>Admin Users</span>
               <strong><?php echo $admin_users; ?></strong>
             </div>
             <div class="users-summary-card">
               <span>Regular Users</span>
               <strong><?php echo $total_users - $admin_users; ?></strong>
             </div>
             <div class="users-summary-card">
               <span>Total Orders</span>
               <strong><?php echo array_sum($ordersByEmail); ?></strong>
             </div>
           </div>

           <div class="users-chart-card">
             <div class="users-chart-wrap">
               <?php
                 $chartTotal = max(1, $newUsersCount + $returningUsersCount + $activeUsersCount);
                 $circumference = 377;
                 $newStroke = ($newUsersCount / $chartTotal) * $circumference;
                 $returningStroke = ($returningUsersCount / $chartTotal) * $circumference;
                 $activeStroke = ($activeUsersCount / $chartTotal) * $circumference;
                 $offset = 0;
               ?>
               <svg viewBox="0 0 160 160" width="100%" height="100%">
                 <circle cx="80" cy="80" r="60" fill="transparent" stroke="#f1f5f9" stroke-width="14"></circle>
                 <?php if ($newStroke > 0): ?>
                   <circle cx="80" cy="80" r="60" fill="transparent" stroke="#f05a22" stroke-width="14" stroke-dasharray="<?php echo $newStroke; ?> <?php echo $circumference - $newStroke; ?>" stroke-dashoffset="-<?php echo $offset; ?>"></circle>
                   <?php $offset += $newStroke; ?>
                 <?php endif; ?>
                 <?php if ($returningStroke > 0): ?>
                   <circle cx="80" cy="80" r="60" fill="transparent" stroke="#7380ec" stroke-width="14" stroke-dasharray="<?php echo $returningStroke; ?> <?php echo $circumference - $returningStroke; ?>" stroke-dashoffset="-<?php echo $offset; ?>"></circle>
                   <?php $offset += $returningStroke; ?>
                 <?php endif; ?>
                 <?php if ($activeStroke > 0): ?>
                   <circle cx="80" cy="80" r="60" fill="transparent" stroke="#2ed573" stroke-width="14" stroke-dasharray="<?php echo $activeStroke; ?> <?php echo $circumference - $activeStroke; ?>" stroke-dashoffset="-<?php echo $offset; ?>"></circle>
                 <?php endif; ?>
               </svg>
               <div style="position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center;">
                 <strong style="font-size:1.5rem; color:var(--clr-dark);"><?php echo $chartTotal; ?></strong>
                 <small class="text-muted">Customer Activity</small>
               </div>
             </div>
             <div class="users-chart-legend">
               <div class="users-chart-item">
                 <span class="users-chart-dot" style="background:#f05a22;"></span>
                 <div>
                   <strong>New Users</strong>
                   <small><?php echo $newUsersCount; ?> joined in the last 7 days</small>
                 </div>
               </div>
               <div class="users-chart-item">
                 <span class="users-chart-dot" style="background:#7380ec;"></span>
                 <div>
                   <strong>Returning Users</strong>
                   <small><?php echo $returningUsersCount; ?> with more than 1 order</small>
                 </div>
               </div>
               <div class="users-chart-item">
                 <span class="users-chart-dot" style="background:#2ed573;"></span>
                 <div>
                   <strong>Active Users</strong>
                   <small><?php echo $activeUsersCount; ?> with a recent login or order</small>
                 </div>
               </div>
             </div>
           </div>

           <!-- Add User Form -->
           <div class="users-table-card">
             <h2>Add New User/Admin</h2>
             <div class="form-container">
               <form action="../includes/add_admin.php" method="post" class="add-user-form">
                 <div class="form-group">
                   <label for="email">Email</label>
                   <input type="email" id="email" name="email" required>
                 </div>
                 <div class="form-group">
                   <label for="password">Password</label>
                   <input type="password" id="password" name="password" required minlength="6">
                 </div>
                 <div class="form-group">
                   <label for="user_type">User Type</label>
                   <select id="user_type" name="user_type">
                     <option value="user">Regular User</option>
                     <option value="admin">Admin</option>
                   </select>
                 </div>
                 <div class="form-group">
                   <button type="submit" class="btn-primary">Add User</button>
                 </div>
               </form>
             </div>
           </div>

      <div class="users-table-card">
         <h2>All Users</h2>
         <table> 
             <thead>
              <tr>
                <th>User ID</th>
                <th>Email</th>
                <th>User Type</th>
                <th>Created</th>
                <th>Last Login</th>
                <th>Total Spend</th>
                <th>Total Orders</th>
                <th>Actions</th>
              </tr>
             </thead>
              <tbody>
                <?php if (!$users): ?>
                  <tr><td colspan="8" class="text-center text-muted">No users found.</td></tr>
                <?php else: foreach ($users as $user): ?>
                  <tr>
                    <td><?php echo intval($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                      <span class="badge <?php echo $user['user_type'] === 'admin' ? 'bg-danger' : 'bg-success'; ?>">
                        <?php echo ucfirst(htmlspecialchars($user['user_type'])); ?>
                      </span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td>
                      <?php if (!empty($user['last_login'])): ?>
                        <?php echo date('M d, Y h:i A', strtotime($user['last_login'])); ?>
                      <?php else: ?>
                        <span class="text-muted">Never</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php
                        $userEmailKey = strtolower(trim((string)($user['email'] ?? '')));
                        $totalSpend = $spendByEmail[$userEmailKey] ?? 0.0;
                      ?>
                      Rs. <?php echo number_format($totalSpend, 2); ?>
                    </td>
                    <td>
                      <?php
                        $totalOrders = $ordersByEmail[$userEmailKey] ?? 0;
                      ?>
                      <?php echo number_format((int)$totalOrders); ?>
                    </td>
                    <td>
                      <div class="d-flex gap-2" style="flex-wrap: wrap;">
                        <a href="orders_page.php?q=<?php echo urlencode($user['email']); ?>" class="btn-primary" style="background: #2ed573; text-decoration: none; padding: 0.5rem 1rem; border-radius: var(--border-radius-1); font-size: 0.85rem; display: inline-flex; align-items: center; justify-content: center; height: 32px; font-weight: 600; color: white;">View Orders</a>
                        
                        <?php if ($user['user_type'] === 'admin' && $user['email'] !== 'subodhpaudel0000@gmail.com'): ?>
                          <!-- Admin user - show delete button only (except main admin) -->
                          <form action="../includes/delete_user.php" method="post" class="action-form" onsubmit="return confirm('Delete this admin permanently? This action cannot be undone.');" style="margin:0;">
                            <input type="hidden" name="user_id" value="<?php echo intval($user['id']); ?>">
                            <button type="submit" class="btn-danger" style="height:32px; padding: 0 10px; display:inline-flex; align-items:center; justify-content:center;">Delete Admin</button>
                          </form>
                          <span class="admin-badge" style="height:32px; display:inline-flex; align-items:center; justify-content:center;">Admin User</span>
                        <?php elseif ($user['user_type'] !== 'admin'): ?>
                          <form action="../includes/delete_user.php" method="post" class="action-form" onsubmit="return confirm('Delete this user permanently?');" style="margin:0;">
                            <input type="hidden" name="user_id" value="<?php echo intval($user['id']); ?>">
                            <button type="submit" class="btn-danger" style="height:32px; padding: 0 10px; display:inline-flex; align-items:center; justify-content:center;">Delete</button>
                          </form>
                        <?php else: ?>
                          <!-- Main admin - no actions -->
                          <span class="admin-badge" style="height:32px; display:inline-flex; align-items:center; justify-content:center;">Main Admin</span>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
         </table>
       </div>

      </main>

   </div>
   <!-- end container -->

<script src="../assets/js/adminscript.js"></script>
</body>
</html>
