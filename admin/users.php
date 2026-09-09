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

// Badge color per user type (Bootstrap is not loaded on this page,
// so the type badges are styled inline)
$userTypeBadgeColors = [
    'admin'   => '#e84118',
    'manager' => '#7380ec',
    'chef'    => '#ffa502',
    'staff'   => '#00b894',
    'rider'   => '#0984e3',
    'user'    => '#2ed573',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Users Management · Mero Bhoj</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  <link rel="stylesheet" href="../assets/css/admin2.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin2.css') ?>">
  <style>
    .users-page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.75rem;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .users-page-header h1 {
      margin: 0;
      font-size: 1.6rem;
      font-weight: 800;
      color: var(--clr-dark);
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }
    .users-page-header p {
      margin: 0.25rem 0 0;
      color: var(--clr-dark-variant);
      font-size: 0.88rem;
    }

    /* Executive KPI Grid */
    .users-stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
      gap: 1.25rem;
      margin-bottom: 1.75rem;
    }
    .kpi-card {
      background: var(--clr-card-background, #ffffff);
      border-radius: var(--border-radius-2, 14px);
      padding: 1.25rem 1.4rem;
      box-shadow: 0 4px 18px rgba(0,0,0,0.03);
      border: 1px solid var(--clr-border, #e2e8f0);
      display: flex;
      align-items: center;
      gap: 1.1rem;
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .kpi-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    }
    .kpi-icon-box {
      width: 3.2rem;
      height: 3.2rem;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .kpi-icon-box span { font-size: 1.6rem; }
    .kpi-data h3 {
      font-size: 1.5rem;
      font-weight: 800;
      color: var(--clr-dark);
      margin: 0;
      line-height: 1.1;
    }
    .kpi-data p {
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--clr-dark-variant);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin: 0.3rem 0 0;
    }

    /* Modern Add User Card */
    .add-user-card {
      background: var(--clr-card-background, #ffffff);
      border-radius: var(--border-radius-3, 16px);
      padding: 1.4rem 1.6rem;
      box-shadow: 0 4px 18px rgba(0,0,0,0.03);
      border: 1px solid var(--clr-border, #e2e8f0);
      margin-bottom: 1.75rem;
    }
    .add-user-card h2 {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--clr-dark);
      margin: 0 0 1.1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .modern-form-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) 140px;
      gap: 1rem;
      align-items: end;
    }
    .m-input-group label {
      display: block;
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--clr-dark-variant);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 0.4rem;
    }
    .m-input-group input,
    .m-input-group select {
      width: 100%;
      padding: 0.65rem 0.85rem;
      border-radius: 8px;
      border: 1px solid var(--clr-border, #e2e8f0);
      background: var(--clr-card-background, #ffffff);
      color: var(--clr-dark);
      font-family: inherit;
      font-size: 0.88rem;
      outline: none;
      transition: border-color 0.2s;
    }
    .m-input-group input:focus,
    .m-input-group select:focus {
      border-color: #f05a22;
    }
    .btn-create-user {
      height: 40px;
      width: 100%;
      background: linear-gradient(135deg, #f05a22 0%, #d9430c 100%);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-weight: 700;
      font-size: 0.88rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.4rem;
      transition: opacity 0.2s, transform 0.2s;
    }
    .btn-create-user:hover {
      opacity: 0.94;
      transform: translateY(-1px);
    }

    /* Table Container & Filter Toolbar */
    .user-table-container {
      background: var(--clr-card-background, #ffffff);
      border-radius: var(--border-radius-3, 16px);
      padding: 1.4rem 1.6rem;
      box-shadow: 0 4px 18px rgba(0,0,0,0.03);
      border: 1px solid var(--clr-border, #e2e8f0);
    }
    .table-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.25rem;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .table-search-box {
      position: relative;
      min-width: 260px;
      flex-grow: 1;
      max-width: 400px;
    }
    .table-search-box span {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      font-size: 1.2rem;
    }
    .table-search-box input {
      width: 100%;
      padding: 0.55rem 0.85rem 0.55rem 2.4rem;
      border-radius: 8px;
      border: 1px solid var(--clr-border, #cbd5e1);
      font-family: inherit;
      font-size: 0.88rem;
      outline: none;
    }
    .table-search-box input:focus { border-color: #f05a22; }

    /* Modern Table Styling */
    .modern-table-wrap {
      overflow-x: auto;
    }
    table.modern-user-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
    }
    table.modern-user-table th {
      padding: 0.85rem 1rem;
      font-size: 0.72rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: var(--clr-dark-variant, #64748b);
      border-bottom: 2px solid var(--clr-border, #e2e8f0);
      background: var(--clr-card-background);
    }
    table.modern-user-table td {
      padding: 0.9rem 1rem;
      font-size: 0.88rem;
      color: var(--clr-dark, #1e293b);
      border-bottom: 1px solid var(--clr-border, #f1f5f9);
      vertical-align: middle;
    }
    table.modern-user-table tbody tr:hover {
      background: rgba(240, 90, 34, 0.02);
    }

    /* Role Pill Badges */
    .role-badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      padding: 0.25rem 0.7rem;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.4px;
    }

    /* Action Buttons */
    .tbl-action-btn {
      padding: 0.35rem 0.75rem;
      border-radius: 6px;
      font-size: 0.78rem;
      font-weight: 700;
      text-decoration: none;
      border: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      transition: opacity 0.2s;
    }
    .tbl-btn-orders { background: #dcfce7; color: #16a34a; }
    .tbl-btn-orders:hover { background: #bbf7d0; }
    .tbl-btn-delete { background: #fee2e2; color: #dc2626; }
    .tbl-btn-delete:hover { background: #fecaca; }
  </style>
</head>
<body class="admin-page">
   <?php include_once __DIR__ . '/topbar.php'; ?>
   <div class="container">
      <?php include_once __DIR__ . '/sidebar.php'; ?>

      <main class="admin-page-main">

           <!-- Header -->
           <div class="users-page-header">
             <div>
               <h1><span class="material-symbols-sharp" style="color:#f05a22;">group</span> Users & Staff Directory</h1>
               <p>Manage accounts, control administrative roles, and inspect customer ordering stats.</p>
             </div>
             <div style="display:flex; align-items:center; gap:0.6rem;">
               <button type="button" class="btn-create-user" style="height:38px; padding:0 1rem; width:auto;" onclick="openAddStaffModal()">
                 <span class="material-symbols-sharp" style="font-size:1.1rem;">person_add</span> + Add Staff / Account
               </button>
               <a href="staff.php" class="btn-create-user" style="height:38px; padding:0 1rem; width:auto; background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; text-decoration:none;">
                 <span class="material-symbols-sharp" style="font-size:1.1rem;">badge</span> Attendance & Shifts
               </a>
             </div>
           </div>

           <!-- Summary KPIs -->
           <div class="users-stats-grid">
             <div class="kpi-card">
               <div class="kpi-icon-box" style="background:#fff2e8; color:#f05a22;">
                 <span class="material-symbols-sharp">group</span>
               </div>
               <div class="kpi-data">
                 <h3><?= $total_users ?></h3>
                 <p>Total Registered</p>
               </div>
             </div>

             <div class="kpi-card">
               <div class="kpi-icon-box" style="background:#fee2e2; color:#dc2626;">
                 <span class="material-symbols-sharp">admin_panel_settings</span>
               </div>
               <div class="kpi-data">
                 <h3><?= $admin_users ?></h3>
                 <p>Administrators</p>
               </div>
             </div>

             <div class="kpi-card">
               <div class="kpi-icon-box" style="background:#dcfce7; color:#16a34a;">
                 <span class="material-symbols-sharp">person</span>
               </div>
               <div class="kpi-data">
                 <h3><?= $total_users - $admin_users ?></h3>
                 <p>Customers & Staff</p>
               </div>
             </div>

             <div class="kpi-card">
               <div class="kpi-icon-box" style="background:#e0f2fe; color:#0284c7;">
                 <span class="material-symbols-sharp">shopping_bag</span>
               </div>
               <div class="kpi-data">
                 <h3><?= array_sum($ordersByEmail) ?></h3>
                 <p>Total Orders</p>
               </div>
             </div>
           </div>

           <!-- Create User Form Card -->
           <div class="add-user-card">
             <h2><span class="material-symbols-sharp" style="color:#f05a22;">person_add</span> Create New Account</h2>
             <form action="../includes/add_admin.php" method="post" class="modern-form-row">
               <div class="m-input-group">
                 <label for="email">Email Address</label>
                 <input type="email" id="email" name="email" placeholder="user@example.com" required>
               </div>
               <div class="m-input-group">
                 <label for="password">Password</label>
                 <input type="password" id="password" name="password" placeholder="••••••••" required minlength="6">
               </div>
               <div class="m-input-group">
                 <label for="user_type">User Role</label>
                 <select id="user_type" name="user_type">
                   <option value="user">Regular Customer</option>
                   <option value="admin">Administrator</option>
                   <option value="manager">Manager</option>
                   <option value="chef">Chef</option>
                   <option value="staff">Floor Staff</option>
                   <option value="rider">Rider / Delivery</option>
                 </select>
               </div>
               <button type="submit" class="btn-create-user">
                 <span class="material-symbols-sharp" style="font-size:1.1rem;">add</span> Add Account
               </button>
             </form>
           </div>

           <!-- Users Table Card -->
           <div class="user-table-container">
             <div class="table-toolbar">
               <h2 style="font-size:1.1rem; font-weight:700; margin:0; color:var(--clr-dark);">All System Accounts</h2>
               <div class="table-search-box">
                 <span class="material-symbols-sharp">search</span>
                 <input type="text" id="userSearch" placeholder="Search by email or role..." oninput="filterUserTable()">
               </div>
             </div>

             <div class="modern-table-wrap">
               <table class="modern-user-table" id="usersTable">
                 <thead>
                   <tr>
                     <th>User ID</th>
                     <th>Account Email</th>
                     <th>Role</th>
                     <th>Joined Date</th>
                     <th>Last Login</th>
                     <th>Total Orders</th>
                     <th>Total Spend</th>
                     <th style="text-align:right;">Actions</th>
                   </tr>
                 </thead>
                 <tbody>
                   <?php if (!$users): ?>
                     <tr><td colspan="8" style="text-align:center; color:#94a3b8; padding:3rem;">No user accounts found.</td></tr>
                   <?php else: foreach ($users as $u): ?>
                     <?php
                       $eKey = strtolower(trim((string)($u['email'] ?? '')));
                       $tSpend = $spendByEmail[$eKey] ?? 0.0;
                       $tOrders = $ordersByEmail[$eKey] ?? 0;
                       $role = strtolower((string)($u['user_type'] ?? 'user'));
                       $bColor = $userTypeBadgeColors[$role] ?? '#64748b';
                     ?>
                     <tr class="user-row-item">
                       <td style="font-weight:700; color:#64748b;">#<?= intval($u['id']) ?></td>
                       <td style="font-weight:600;"><?= htmlspecialchars($u['email']) ?></td>
                       <td>
                         <span class="role-badge-pill" style="background: <?= $bColor ?>15; color: <?= $bColor ?>; border: 1px solid <?= $bColor ?>30;">
                           <span style="width:6px; height:6px; border-radius:50%; background:<?= $bColor ?>;"></span>
                           <?= ucfirst(htmlspecialchars($role)) ?>
                         </span>
                       </td>
                       <td style="color:#64748b; font-size:0.82rem;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                       <td style="color:#64748b; font-size:0.82rem;">
                         <?= !empty($u['last_login']) ? date('M d, Y g:i A', strtotime($u['last_login'])) : '<span style="color:#cbd5e1;">Never</span>' ?>
                       </td>
                       <td style="font-weight:700;"><?= number_format((int)$tOrders) ?></td>
                       <td style="font-weight:700; color:#16a34a;">NPR <?= number_format($tSpend, 2) ?></td>
                       <td style="text-align:right;">
                         <div style="display:inline-flex; gap:0.4rem; justify-content:flex-end;">
                           <a href="orders_page.php?q=<?= urlencode($u['email']) ?>" class="tbl-action-btn tbl-btn-orders" title="View order history">
                             <span class="material-symbols-sharp" style="font-size:1rem;">receipt_long</span> Orders
                           </a>

                           <?php if ($u['user_type'] === 'admin' && $u['email'] !== 'subodhpaudel0000@gmail.com'): ?>
                             <form action="../includes/delete_user.php" method="post" onsubmit="return confirmFormDelete(event, 'Delete Admin Account?', 'Are you sure you want to delete <?= htmlspecialchars($u['email']) ?>? This action cannot be undone.');" style="margin:0;">
                               <input type="hidden" name="user_id" value="<?= intval($u['id']) ?>">
                               <button type="submit" class="tbl-action-btn tbl-btn-delete">
                                 <span class="material-symbols-sharp" style="font-size:1rem;">delete</span> Delete
                               </button>
                             </form>
                           <?php elseif ($u['user_type'] !== 'admin'): ?>
                             <form action="../includes/delete_user.php" method="post" onsubmit="return confirmFormDelete(event, 'Delete User Account?', 'Are you sure you want to delete <?= htmlspecialchars($u['email']) ?>? This action cannot be undone.');" style="margin:0;">
                               <input type="hidden" name="user_id" value="<?= intval($u['id']) ?>">
                               <button type="submit" class="tbl-action-btn tbl-btn-delete">
                                 <span class="material-symbols-sharp" style="font-size:1rem;">delete</span> Delete
                               </button>
                             </form>
                           <?php else: ?>
                             <span style="font-size:0.75rem; font-weight:700; color:#94a3b8; padding:0.35rem 0.5rem;">Super Admin</span>
                           <?php endif; ?>
                         </div>
                       </td>
                     </tr>
                   <?php endforeach; endif; ?>
                 </tbody>
               </table>
             </div>
           </div>

      </main>
   </div>

   <!-- + Add New Staff / User Modal -->
   <div class="modal-overlay" id="addStaffModal">
     <div class="staff-modal-box" style="width:min(540px, 94vw); background:#fff; border-radius:16px; padding:1.6rem; margin:3rem auto; box-shadow:0 20px 40px rgba(0,0,0,0.15);">
       <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem;">
         <h3 style="margin:0; font-weight:800; color:#0f172a;"><span class="material-symbols-sharp" style="color:#f05a22; vertical-align:middle;">person_add</span> Add New Staff / User Account</h3>
         <button type="button" onclick="closeStaffModal('addStaffModal')" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:#64748b;">&times;</button>
       </div>

       <form action="../includes/add_admin.php" method="post" id="usersPageAddStaffForm">
         <div class="form-group" style="margin-bottom:1rem;">
           <label style="font-size:0.78rem; font-weight:700; display:block; margin-bottom:0.3rem;">Account Email *</label>
           <input type="email" name="email" placeholder="e.g. staff.member@merobhoj.com" required style="width:100%; padding:0.65rem; border-radius:6px; border:1px solid #cbd5e1; outline:none;">
         </div>

         <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
           <div class="form-group">
             <label style="font-size:0.78rem; font-weight:700; display:block; margin-bottom:0.3rem;">Account Role *</label>
             <select name="user_type" style="width:100%; padding:0.65rem; border-radius:6px; border:1px solid #cbd5e1; outline:none;">
               <option value="staff">Staff / Waiter</option>
               <option value="chef">Chef / Kitchen</option>
               <option value="rider">Rider / Delivery</option>
               <option value="manager">Manager</option>
               <option value="admin">Administrator</option>
               <option value="user">Regular Customer</option>
             </select>
           </div>
           <div class="form-group">
             <label style="font-size:0.78rem; font-weight:700; display:block; margin-bottom:0.3rem;">Phone Number</label>
             <input type="text" name="phone" placeholder="+977 9841-000000" style="width:100%; padding:0.65rem; border-radius:6px; border:1px solid #cbd5e1; outline:none;">
           </div>
         </div>

         <div class="form-group" style="margin-bottom:1.5rem;">
           <label style="font-size:0.78rem; font-weight:700; display:block; margin-bottom:0.3rem;">Password *</label>
           <input type="password" name="password" placeholder="••••••••" required minlength="6" style="width:100%; padding:0.65rem; border-radius:6px; border:1px solid #cbd5e1; outline:none;">
         </div>

         <div style="text-align:right; display:flex; gap:0.5rem; justify-content:flex-end;">
           <button type="button" class="tbl-action-btn" style="background:#f1f5f9; color:#475569;" onclick="closeStaffModal('addStaffModal')">Cancel</button>
           <button type="submit" class="btn-create-user" style="height:36px; width:auto; padding:0 1.2rem;"><span class="material-symbols-sharp" style="font-size:1rem;">check</span> Create Account</button>
         </div>
       </form>
     </div>
   </div>

   <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
   <script>
     function filterUserTable() {
       var q = (document.getElementById('userSearch').value || '').toLowerCase().trim();
       var rows = document.querySelectorAll('.user-row-item');
       rows.forEach(function(r) {
         var text = r.textContent.toLowerCase();
         r.style.display = text.indexOf(q) !== -1 ? '' : 'none';
       });
     }
   </script>
</body>
</html>

