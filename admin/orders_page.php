<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$allowedSort = [
    'newest' => 'order_id DESC',
    'oldest' => 'order_id ASC',
    'price_high' => 'total_price DESC',
    'price_low' => 'total_price ASC',
];
$orderBySql = $allowedSort[$sort] ?? $allowedSort['newest'];

$where = [];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = "(o.menu_name LIKE ? OR o.email LIKE ? OR o.mobile LIKE ? OR o.address LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
    $types .= 'ssss';
}
if ($statusFilter !== '') {
    $statusMap = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'ready' => 'Ready',
        'delivering' => 'Delivering',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled'
    ];
    $dbStatus = $statusMap[$statusFilter] ?? $statusFilter;
    if (in_array($dbStatus, ['Pending' , 'Confirmed' , 'Preparing' , 'Ready' , 'Delivering' , 'Completed' , 'Cancelled'], true)) {
        $where[] = "o.status = ?";
        $params[] = $dbStatus;
        $types .= 's';
    }
}

$sql = "SELECT o.order_id, o.order_number, o.menu_id, o.menu_name, o.email, o.mobile, o.address, o.quantity, o.price, o.total_price, o.payment_method, o.payment_status, o.status, o.order_type, o.order_time, o.order_date, m.menu_image FROM orders o LEFT JOIN menu m ON o.menu_id = m.menu_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= " ORDER BY o.$orderBySql";

$stmt = $conn->prepare($sql);
if ($stmt && $params) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
} else {
    $res = $conn->query($sql);
}

$rawOrders = [];
if ($res) { while ($row = $res->fetch_assoc()) { $rawOrders[] = $row; } }

// Group raw order rows by order_number (or order_id fallback)
$grouped = [];
foreach ($rawOrders as $row) {
    $num = !empty($row['order_number']) ? $row['order_number'] : ('ORD-' . str_pad((string)$row['order_id'], 4, '0', STR_PAD_LEFT));
    if (!isset($grouped[$num])) {
        $grouped[$num] = [
            'order_number' => $num,
            'order_id' => (int)$row['order_id'],
            'email' => $row['email'],
            'mobile' => $row['mobile'],
            'address' => $row['address'],
            'status' => $row['status'],
            'order_type' => $row['order_type'] ?? 'Delivery',
            'payment_method' => $row['payment_method'],
            'payment_status' => $row['payment_status'],
            'order_time' => $row['order_time'],
            'order_date' => $row['order_date'],
            'total_amount' => 0.0,
            'items' => []
        ];
    }
    $grouped[$num]['total_amount'] += (float)$row['total_price'];
    $grouped[$num]['items'][] = [
        'order_id' => (int)$row['order_id'],
        'menu_id' => (int)$row['menu_id'],
        'menu_name' => $row['menu_name'],
        'quantity' => (int)$row['quantity'],
        'price' => (float)$row['price'],
        'total_price' => (float)$row['total_price'],
        'menu_image' => $row['menu_image']
    ];
}
$orders = array_values($grouped);

$all_orders_count = 0;
$total_revenue = 0.0;
$completed_count = 0;
$delivering_count = 0;
$preparing_count = 0;
$cancelled_count = 0;
$out_delivery_count = 0;
$delivered_count = 0;

$stats_res = $conn->query("SELECT COALESCE(NULLIF(order_number, ''), CONCAT('ORD-', LPAD(order_id, 4, '0'))) as ord_num, status, total_price FROM orders");
if ($stats_res) {
    $seenOrders = [];
    while ($row = $stats_res->fetch_assoc()) {
        $num = $row['ord_num'];
        $total_revenue += (float)$row['total_price'];
        if (!isset($seenOrders[$num])) {
            $seenOrders[$num] = $row['status'];
            $all_orders_count++;
            if ($row['status'] === 'Completed')  { $completed_count++; $delivered_count++; }
            elseif ($row['status'] === 'Delivering') { $delivering_count++; $out_delivery_count++; }
            elseif ($row['status'] === 'Preparing') $preparing_count++;
            elseif ($row['status'] === 'Cancelled') $cancelled_count++;
        }
    }
}

$recent_orders = [];
$recent_res = $conn->query("SELECT order_id, order_number, email, status, order_time FROM orders GROUP BY COALESCE(NULLIF(order_number, ''), CONCAT('ORD-', LPAD(order_id, 4, '0'))) ORDER BY order_id DESC LIMIT 3");
if ($recent_res) {
    while ($row = $recent_res->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Orders - Mero Bhoj</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  <link rel="stylesheet" href="../assets/css/admin2.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin2.css') ?>">
</head>
<body class="admin-orders-page">
   <?php include_once __DIR__ . '/topbar.php'; ?>
   <div class="container">
      <?php include_once __DIR__ . '/sidebar.php'; ?>

       <main class="admin-page-main">
            <div class="dashboard-header admin-page-heading">
                <div>
                    <h1>All Orders</h1>
                    <p>Track and manage all customer orders</p>
                </div>
            </div>

            <div class="orders-layout">
                <div class="orders-main-content">
                    <div class="orders-stats-row">
                        <div class="order-stat-card">
                            <div class="order-stat-icon stat-total"><i class="fa fa-shopping-bag"></i></div>
                            <div>
                                <small class="text-muted" style="font-size: 0.8rem; font-weight: 500;">Total Orders</small>
                                <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--clr-dark);"><?php echo $all_orders_count; ?></h2>
                                <small class="text-muted" style="font-size: 0.7rem;">All time</small>
                            </div>
                        </div>
                        <div class="order-stat-card">
                            <div class="order-stat-icon stat-delivered"><i class="fa fa-circle-check"></i></div>
                            <div>
                                <small class="text-muted" style="font-size: 0.8rem; font-weight: 500;">Completed</small>
                                <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--clr-dark);"><?php echo $completed_count; ?></h2>
                                <small class="text-muted" style="font-size: 0.7rem;">This month</small>
                            </div>
                        </div>
                        <div class="order-stat-card">
                            <div class="order-stat-icon stat-out"><i class="fa fa-truck"></i></div>
                            <div>
                                <small class="text-muted" style="font-size: 0.8rem; font-weight: 500;">Delivering</small>
                                <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--clr-dark);"><?php echo $delivering_count; ?></h2>
                                <small class="text-muted" style="font-size: 0.7rem;">Today</small>
                            </div>
                        </div>
                        <div class="order-stat-card">
                            <div class="order-stat-icon stat-preparing"><i class="fa fa-clock"></i></div>
                            <div>
                                <small class="text-muted" style="font-size: 0.8rem; font-weight: 500;">Preparing</small>
                                <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--clr-dark);"><?php echo $preparing_count; ?></h2>
                                <small class="text-muted" style="font-size: 0.7rem;">Today</small>
                            </div>
                        </div>
                        <div class="order-stat-card">
                            <div class="order-stat-icon stat-cancelled"><i class="fa fa-circle-xmark"></i></div>
                            <div>
                                <small class="text-muted" style="font-size: 0.8rem; font-weight: 500;">Cancelled</small>
                                <h2 style="font-size: 1.5rem; font-weight: 800; margin: 0; color: var(--clr-dark);"><?php echo $cancelled_count; ?></h2>
                                <small class="text-muted" style="font-size: 0.7rem;">This month</small>
                            </div>
                        </div>
                    </div>

                    <div class="order-tabs-row">
                        <div class="order-tabs">
                            <a href="orders_page.php" class="order-tab <?php echo $statusFilter===''?'active':''; ?>">All Orders</a>
                            <a href="orders_page.php?status=pending" class="order-tab <?php echo $statusFilter==='pending'?'active':''; ?>">Pending</a>
                            <a href="orders_page.php?status=confirmed" class="order-tab <?php echo $statusFilter==='confirmed'?'active':''; ?>">Confirmed</a>
                            <a href="orders_page.php?status=preparing" class="order-tab <?php echo $statusFilter==='preparing'?'active':''; ?>">Preparing</a>
                            <a href="orders_page.php?status=ready" class="order-tab <?php echo $statusFilter==='ready'?'active':''; ?>">Ready</a>
                            <a href="orders_page.php?status=delivering" class="order-tab <?php echo $statusFilter==='delivering'?'active':''; ?>">Delivering</a>
                            <a href="orders_page.php?status=completed" class="order-tab <?php echo $statusFilter==='completed'?'active':''; ?>">Completed</a>
                            <a href="orders_page.php?status=cancelled" class="order-tab <?php echo $statusFilter==='cancelled'?'active':''; ?>">Cancelled</a>
                        </div>
                        
                        <form method="GET" action="orders_page.php" style="display: flex; gap: 0.5rem; align-items: center;">
                            <?php if ($statusFilter): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                            <?php endif; ?>
                            <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search orders..." style="padding: 0.4rem 0.8rem; border-radius: 8px; border: 1px solid var(--clr-border); background: var(--clr-white); color: var(--clr-dark); font-size: 0.85rem; width: 200px;">
                            
                            <select name="sort" onchange="this.form.submit()" style="padding: 0.4rem 0.8rem; border-radius: 8px; border: 1px solid var(--clr-border); background: var(--clr-white); color: var(--clr-dark); font-size: 0.85rem; cursor: pointer;">
                                <option value="newest" <?php echo $sort==='newest'?'selected':''; ?>>Sort by: Latest</option>
                                <option value="oldest" <?php echo $sort==='oldest'?'selected':''; ?>>Sort by: Oldest</option>
                                <option value="price_high" <?php echo $sort==='price_high'?'selected':''; ?>>Price: High to Low</option>
                                <option value="price_low" <?php echo $sort==='price_low'?'selected':''; ?>>Price: Low to High</option>
                            </select>
                        </form>
                    </div>

                    <div class="recent_order" style="margin-top: 0; box-shadow: var(--box-shadow); background: var(--clr-white); padding: var(--card-padding); border-radius: var(--card-border-radius); overflow-x: auto;">
                        <table>
                            <thead>
                             <tr style="border-bottom: 2px solid var(--clr-info-light); color: var(--clr-dark-variant); font-size: 0.85rem;">
                               <th style="padding: 0.8rem 0.5rem;">Order Number</th>
                               <th style="padding: 0.8rem 0.5rem;">Customer</th>
                               <th style="padding: 0.8rem 0.5rem;">Purchased Items</th>
                               <th style="padding: 0.8rem 0.5rem;">Total Amount</th>
                               <th style="padding: 0.8rem 0.5rem;">Payment</th>
                               <th style="padding: 0.8rem 0.5rem;">Status</th>
                               <th style="padding: 0.8rem 0.5rem;">Order Time</th>
                               <th style="padding: 0.8rem 0.5rem; text-align: center;">Actions</th>
                             </tr>
                            </thead>
                             <tbody>
                               <?php if (!$orders): ?>
                                 <tr><td colspan="8" class="text-center text-muted" style="padding: 2rem;">No orders found.</td></tr>
                               <?php else: foreach ($orders as $o): 
                                   $pm = !empty($o['payment_method']) ? $o['payment_method'] : 'Cash on Delivery'; 
                                   $pmStatus = !empty($o['payment_status']) ? $o['payment_status'] : ($pm === 'Pay at Restaurant' ? 'Paid' : 'Unpaid');
                                   $pmClass = strtolower($pmStatus) === 'paid' ? 'payment-online' : 'payment-cod';
                                   
                                   $stBadgeClass = 'status-' . strtolower($o['status']);
                                   $visualStatus = htmlspecialchars($o['status']);
                                   
                                   $firstItem = $o['items'][0] ?? null;
                                   $itemsCount = count($o['items']);
                               ?>
                                 <tr id="order-row-<?php echo intval($o['order_id']); ?>" style="border-bottom: 1px solid var(--clr-info-light);">
                                    <td style="padding: 1rem 0.5rem; font-weight: 600; color: var(--clr-dark-variant);">
                                       <a href="order_view.php?order_number=<?php echo urlencode($o['order_number']); ?>" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center; font-weight:700;">
                                           <i class="fa fa-chevron-right" style="font-size: 0.7rem; margin-right: 0.4rem; color: var(--clr-primary);"></i>
                                           <?php echo htmlspecialchars($o['order_number']); ?>
                                       </a>
                                    </td>
                                    <td style="padding: 1rem 0.5rem;">
                                      <strong style="color: var(--clr-dark);"><?php echo htmlspecialchars($o['email'] ?: 'N/A'); ?></strong><br>
                                      <small class="text-muted" style="font-size:0.75rem;"><?php echo htmlspecialchars($o['mobile'] ?: ''); ?></small>
                                    </td>
                                   <td style="padding: 1rem 0.5rem; min-width: 200px;">
                                     <?php if ($firstItem): ?>
                                     <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.3rem;">
                                         <img src="../<?php echo htmlspecialchars($firstItem['menu_image'] ?: 'assets/images/placeholder.jpg'); ?>" style="width: 32px; height: 32px; border-radius: 6px; object-fit: cover; border: 1px solid var(--clr-border);">
                                         <div>
                                             <strong style="color: var(--clr-dark); font-size: 0.85rem;"><?php echo htmlspecialchars($firstItem['menu_name']); ?></strong>
                                             <span class="text-muted" style="font-size: 0.75rem;">× <?php echo intval($firstItem['quantity']); ?></span>
                                         </div>
                                     </div>
                                     <?php endif; ?>
                                     <?php if ($itemsCount > 1): ?>
                                     <button type="button" onclick="toggleAdminOrderItems(<?php echo intval($o['order_id']); ?>)" id="btn-toggle-<?php echo intval($o['order_id']); ?>" style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 12px; padding: 2px 8px; font-size: 0.75rem; cursor: pointer; color: #334155; font-weight:600;">
                                         <i class="fa fa-chevron-down"></i> View all <?php echo $itemsCount; ?> items
                                     </button>
                                     <div id="items-list-<?php echo intval($o['order_id']); ?>" style="display: none; margin-top: 0.5rem; background: #fafafa; padding: 8px; border-radius: 6px; border: 1px solid #eee;">
                                         <?php foreach ($o['items'] as $it): ?>
                                         <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; padding: 3px 0; border-bottom: 1px dashed #e2e8f0;">
                                             <span><strong><?php echo htmlspecialchars($it['menu_name']); ?></strong> × <?php echo intval($it['quantity']); ?></span>
                                             <span class="text-muted">Rs. <?php echo number_format((float)$it['total_price'], 2); ?></span>
                                         </div>
                                         <?php endforeach; ?>
                                     </div>
                                     <?php endif; ?>
                                   </td>
                                   <td style="padding: 1rem 0.5rem; font-weight: 700; color: var(--clr-dark);">Rs. <?php echo number_format((float)$o['total_amount'], 2); ?></td>
                                   <td style="padding: 1rem 0.5rem;"><span class="badge-payment <?php echo $pmClass; ?>"><?php echo $pm; ?></span></td>
                                   <td style="padding: 1rem 0.5rem;">
                                      <select name="status" class="booking-status-select <?php echo 'status-' . strtolower($o['status']); ?>" id="status-<?php echo intval($o['order_id']); ?>" data-current-status="<?php echo htmlspecialchars($o['status'], ENT_QUOTES); ?>" onchange="handleOrderUpdate('<?php echo htmlspecialchars($o['order_number'], ENT_QUOTES); ?>', <?php echo intval($o['order_id']); ?>)" style="padding: 0.25rem 0.5rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; cursor: pointer; border: 1px solid var(--clr-border); background: var(--clr-white);">
                                        <option value="Pending" <?php echo $o['status']==='Pending'?'selected':''; ?>>Pending</option>
                                        <option value="Confirmed" <?php echo $o['status']==='Confirmed'?'selected':''; ?>>Confirmed</option>
                                        <option value="Preparing" <?php echo $o['status']==='Preparing'?'selected':''; ?>>Preparing</option>
                                        <option value="Ready" <?php echo $o['status']==='Ready'?'selected':''; ?>>Ready</option>
                                        <?php if (($o['order_type'] ?? 'Delivery') === 'Delivery'): ?>
                                        <option value="Delivering" <?php echo $o['status']==='Delivering'?'selected':''; ?>>Delivering</option>
                                        <?php endif; ?>
                                        <option value="Completed" <?php echo $o['status']==='Completed'?'selected':''; ?>>Completed</option>
                                        <option value="Cancelled" <?php echo $o['status']==='Cancelled'?'selected':''; ?>>Cancelled</option>
                                      </select>
                                   </td>
                                   <td style="padding: 1rem 0.5rem; font-size: 0.8rem; color: var(--clr-dark-variant);"><?php echo date('d M Y h:i A', strtotime($o['order_time'])); ?></td>
                                   <td class="order-actions-cell" style="padding: 1rem 0.5rem; display:flex; gap:0.35rem; align-items:center; justify-content:center;">
                                     <button type="button" class="btn-booking-delete" title="Delete order" aria-label="Delete order" onclick="handleOrderDelete('<?php echo htmlspecialchars($o['order_number'], ENT_QUOTES); ?>', <?php echo intval($o['order_id']); ?>)"><i class="fa fa-trash"></i></button>
                                   </td>
                                </tr>
                              <?php endforeach; endif; ?>
                            </tbody>
                       </table>
                 </div>
             </div>

             <div class="tracking-sidebar">
                 <h2 style="font-size: 1.2rem; font-weight: 700; color: var(--clr-dark); margin-bottom: 1.5rem;">Live Order Tracking</h2>
                 
                 <div style="position: relative; width: 160px; height: 160px; margin: 0 auto 1.5rem;">
                     <?php
                     $total_mapped = $delivering_count + $preparing_count + $completed_count + $cancelled_count;
                     $p_out = $total_mapped > 0 ? ($delivering_count / $total_mapped) * 100 : 0;
                     $p_prep = $total_mapped > 0 ? ($preparing_count / $total_mapped) * 100 : 0;
                     $p_del = $total_mapped > 0 ? ($completed_count / $total_mapped) * 100 : 0;
                     $p_can = $total_mapped > 0 ? ($cancelled_count / $total_mapped) * 100 : 0;
                     
                     $circ = 377.0;
                     $stroke_out = ($p_out / 100) * $circ;
                     $stroke_prep = ($p_prep / 100) * $circ;
                     $stroke_del = ($p_del / 100) * $circ;
                     $stroke_can = ($p_can / 100) * $circ;
                     
                     $offset = 0;
                     ?>
                     <svg width="100%" height="100%" viewBox="0 0 160 160" style="transform: rotate(-90deg);">
                         <circle cx="80" cy="80" r="60" fill="transparent" stroke="#f1f5f9" stroke-width="12" />
                         <?php if ($stroke_out > 0): ?>
                             <circle cx="80" cy="80" r="60" fill="transparent" stroke="#7380ec" stroke-width="12" stroke-dasharray="<?php echo $stroke_out; ?> <?php echo $circ - $stroke_out; ?>" stroke-dashoffset="-<?php echo $offset; ?>" />
                             <?php $offset += $stroke_out; ?>
                         <?php endif; ?>
                         <?php if ($stroke_prep > 0): ?>
                             <circle cx="80" cy="80" r="60" fill="transparent" stroke="#ffa502" stroke-width="12" stroke-dasharray="<?php echo $stroke_prep; ?> <?php echo $circ - $stroke_prep; ?>" stroke-dashoffset="-<?php echo $offset; ?>" />
                             <?php $offset += $stroke_prep; ?>
                         <?php endif; ?>
                         <?php if ($stroke_del > 0): ?>
                             <circle cx="80" cy="80" r="60" fill="transparent" stroke="#2ed573" stroke-width="12" stroke-dasharray="<?php echo $stroke_del; ?> <?php echo $circ - $stroke_del; ?>" stroke-dashoffset="-<?php echo $offset; ?>" />
                             <?php $offset += $stroke_del; ?>
                         <?php endif; ?>
                         <?php if ($stroke_can > 0): ?>
                             <circle cx="80" cy="80" r="60" fill="transparent" stroke="#ff4757" stroke-width="12" stroke-dasharray="<?php echo $stroke_can; ?> <?php echo $circ - $stroke_can; ?>" stroke-dashoffset="-<?php echo $offset; ?>" />
                         <?php endif; ?>
                     </svg>
                     <div style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                         <span style="font-size: 1.5rem; font-weight: 800; color: var(--clr-dark);"><?php echo $all_orders_count; ?></span>
                         <span style="font-size: 0.75rem; color: var(--clr-dark-variant);">Total Orders</span>
                     </div>
                 </div>

                 <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 2rem;">
                     <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                         <span style="display: flex; align-items: center; gap: 0.5rem; color: var(--clr-dark-variant);">
                             <span style="width: 10px; height: 10px; border-radius: 50%; background: #7380ec;"></span> Delivering
                         </span>
                         <strong style="color: var(--clr-dark);"><?php echo $delivering_count; ?> (<?php echo $all_orders_count > 0 ? round(($delivering_count/$all_orders_count)*100) : 0; ?>%)</strong>
                     </div>
                     <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                         <span style="display: flex; align-items: center; gap: 0.5rem; color: var(--clr-dark-variant);">
                             <span style="width: 10px; height: 10px; border-radius: 50%; background: #ffa502;"></span> Preparing
                         </span>
                         <strong style="color: var(--clr-dark);"><?php echo $preparing_count; ?> (<?php echo $all_orders_count > 0 ? round(($preparing_count/$all_orders_count)*100) : 0; ?>%)</strong>
                     </div>
                     <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                         <span style="display: flex; align-items: center; gap: 0.5rem; color: var(--clr-dark-variant);">
                             <span style="width: 10px; height: 10px; border-radius: 50%; background: #2ed573;"></span> Completed
                         </span>
                         <strong style="color: var(--clr-dark);"><?php echo $completed_count; ?> (<?php echo $all_orders_count > 0 ? round(($completed_count/$all_orders_count)*100) : 0; ?>%)</strong>
                     </div>
                     <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem;">
                         <span style="display: flex; align-items: center; gap: 0.5rem; color: var(--clr-dark-variant);">
                             <span style="width: 10px; height: 10px; border-radius: 50%; background: #ff4757;"></span> Cancelled
                         </span>
                         <strong style="color: var(--clr-dark);"><?php echo $cancelled_count; ?> (<?php echo $all_orders_count > 0 ? round(($cancelled_count/$all_orders_count)*100) : 0; ?>%)</strong>
                     </div>
                 </div>

                 <h2 style="font-size: 1.1rem; font-weight: 700; color: var(--clr-dark); margin-bottom: 1rem; border-top: 1px solid var(--clr-border); padding-top: 1.5rem;">Recent Orders</h2>
                 <div class="recent-orders-list" style="display: flex; flex-direction: column; gap: 1rem;">
                     <?php foreach ($recent_orders as $ro):
                         $roBadgeClass = 'status-new-badge';
                         $roStatus = 'New';
                         if ($ro['status'] === 'Ongoing') { $roBadgeClass = 'status-prep-badge'; $roStatus = 'Preparing'; }
                         elseif ($ro['status'] === 'Shipping') { $roBadgeClass = 'status-out-badge'; $roStatus = 'Out for Delivery'; }
                         elseif ($ro['status'] === 'Delivering') { $roBadgeClass = 'status-del-badge'; $roStatus = 'Delivered'; }
                         elseif ($ro['status'] === 'Cancelled') { $roBadgeClass = 'status-can-badge'; $roStatus = 'Cancelled'; }
                     ?>
                     <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; background: var(--clr-color-background); padding: 0.8rem; border-radius: 8px; width: 100%;">
                         <div>
                             <strong style="font-size: 0.85rem; color: var(--clr-dark);">ORD-<?php echo sprintf("%04d", $ro['order_id']); ?></strong><br>
                             <small class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($ro['email'] ?: 'N/A'); ?></small>
                         </div>
                         <div style="text-align: right;">
                             <span class="badge-status <?php echo $roBadgeClass; ?>" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;"><?php echo $roStatus; ?></span><br>
                             <small class="text-muted" style="font-size: 0.7rem;"><?php echo date('h:i A', strtotime($ro['order_time'])); ?></small>
                         </div>
                     </div>
                     <?php endforeach; ?>
                 </div>
             </div>
         </div>
    </main>
</div>

<script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
<script src="../assets/js/admin2.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin2.js') ?>"></script>
</body>
</html>
