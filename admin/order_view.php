<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

$order_id = intval($_GET['id'] ?? 0);
$order_number = trim((string)($_GET['order_number'] ?? ''));

// Retrieve order items details
$order_items = [];
if (!empty($order_number)) {
    $stmt = $conn->prepare("SELECT o.*, m.menu_image FROM orders o LEFT JOIN menu m ON o.menu_id = m.menu_id WHERE o.order_number = ? OR o.order_id = ? ORDER BY o.order_id ASC");
    if ($stmt) {
        $stmt->bind_param("si", $order_number, $order_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $order_items[] = $row; }
        $stmt->close();
    }
} else if ($order_id > 0) {
    $stmt = $conn->prepare("SELECT o.*, m.menu_image FROM orders o LEFT JOIN menu m ON o.menu_id = m.menu_id WHERE o.order_id = ? ORDER BY o.order_id ASC");
    if ($stmt) {
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $order_items[] = $row; }
        $stmt->close();
    }
}

if (empty($order_items)) {
    header('Location: orders_page.php');
    exit;
}

$order = $order_items[0];
$disp_order_num = !empty($order['order_number']) ? $order['order_number'] : ('ORD-' . sprintf("%04d", $order['order_id']));

// Handle Order Status Transitions from bottom buttons
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $new_status = $_POST['status'] ?? '';
    $allowed_status = ['Confirmed', 'Ongoing', 'Shipping', 'Delivering', 'Cancelled'];
    if (in_array($new_status, $allowed_status, true)) {
        if (!empty($order['order_number'])) {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_number = ? OR order_id = ?");
            if ($stmt) {
                $stmt->bind_param("ssi", $new_status, $order['order_number'], $order['order_id']);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $new_status, $order['order_id']);
                $stmt->execute();
                $stmt->close();
            }
        }
        $_SESSION['order_msg'] = "Order status updated to " . $new_status;
    }
    $redir = !empty($order['order_number']) ? ("order_view.php?order_number=" . urlencode($order['order_number'])) : ("order_view.php?id=" . $order['order_id']);
    header("Location: " . $redir);
    exit;
}

// Map database statuses to visual labels & badges
$status_label = 'New Order';
$status_badge_class = 'status-new-badge';
if ($order['status'] === 'Ongoing') { $status_label = 'Preparing'; $status_badge_class = 'status-prep-badge'; }
elseif ($order['status'] === 'Shipping') { $status_label = 'Out for Delivery'; $status_badge_class = 'status-out-badge'; }
elseif ($order['status'] === 'Delivering') { $status_label = 'Delivered'; $status_badge_class = 'status-del-badge'; }
elseif ($order['status'] === 'Cancelled') { $status_label = 'Cancelled'; $status_badge_class = 'status-can-badge'; }

// Payment status mapping
$payment_method = $order['payment_method'] ?? 'Cash on Delivery';
$payment_status = !empty($order['payment_status']) ? $order['payment_status'] : ($payment_method === 'Pay at Restaurant' ? 'Paid' : 'Unpaid');
$payment_badge_class = strtolower($payment_status) === 'paid' ? 'payment-online' : 'payment-cod';

// Item calculations
$subtotal = 0.0;
foreach ($order_items as $it) {
    $subtotal += (float)$it['total_price'];
}
$order_type = $order['order_type'] ?? 'Delivery';
$delivery_charge = $order_type === 'Dine In' ? 0.0 : 50.0;
$total_amount = $subtotal + $delivery_charge;

$actionUrl = "order_view.php?" . (!empty($order['order_number']) ? ("order_number=" . urlencode($order['order_number'])) : ("id=" . $order['order_id']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Details - Mero Bhoj</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  
  <style>
    @media screen and (min-width: 1200px) {
      .container {
        grid-template-columns: 14rem auto !important;
      }
    }
    
    /* Layout grid for details view */
    .details-grid {
      display: grid;
      grid-template-columns: 1fr 360px;
      gap: 1.5rem;
      align-items: start;
    }
    @media screen and (max-width: 1024px) {
      .details-grid {
        grid-template-columns: 1fr;
      }
    }
    
    /* Summary blocks */
    .summary-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .summary-card {
      background: var(--clr-white);
      padding: 1.2rem;
      border-radius: var(--border-radius-3);
      box-shadow: var(--box-shadow);
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }
    
    /* Custom Timeline styles */
    .timeline-container {
      background: var(--clr-white);
      padding: 1.5rem;
      border-radius: var(--border-radius-3);
      box-shadow: var(--box-shadow);
    }
    .timeline {
      position: relative;
      padding-left: 2rem;
      list-style: none;
    }
    .timeline::before {
      content: '';
      position: absolute;
      left: 7px;
      top: 0;
      bottom: 0;
      width: 2px;
      background: var(--clr-border);
    }
    .timeline-item {
      position: relative;
      margin-bottom: 1.5rem;
    }
    .timeline-item:last-child {
      margin-bottom: 0;
    }
    .timeline-dot {
      position: absolute;
      left: -2rem;
      top: 3px;
      width: 16px;
      height: 16px;
      border-radius: 50%;
      background: var(--clr-white);
      border: 3px solid var(--clr-border);
      z-index: 1;
    }
    .timeline-item.active .timeline-dot {
      border-color: var(--clr-success);
      background: var(--clr-success);
    }
    
    /* Badges */
    .badge-payment {
      padding: 0.25rem 0.6rem;
      border-radius: 6px;
      font-size: 0.75rem;
      font-weight: 600;
      display: inline-block;
      width: fit-content;
    }
    .payment-cod { background: #fff4f0; color: #f05a22; border: 1px solid rgba(240, 90, 34, 0.15); }
    .payment-online { background: #eefdf5; color: #2ed573; border: 1px solid rgba(46, 213, 115, 0.15); }

    .badge-status {
      padding: 0.35rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      display: inline-block;
      text-align: center;
      width: fit-content;
    }
    .status-new-badge { background: #f1f5f9; color: #475569; }
    .status-prep-badge { background: #fffbeb; color: #d97706; }
    .status-out-badge { background: #eff6ff; color: #2563eb; }
    .status-del-badge { background: #f0fdf4; color: #16a34a; }
    .status-can-badge { background: #fef2f2; color: #dc2626; }
    
    /* Detailed content cards */
    .details-card {
      background: var(--clr-white);
      padding: 1.5rem;
      border-radius: var(--border-radius-3);
      box-shadow: var(--box-shadow);
      margin-bottom: 1.5rem;
    }
    
    .details-card h3 {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--clr-dark);
      margin-bottom: 1rem;
      border-bottom: 1px solid var(--clr-border);
      padding-bottom: 0.5rem;
    }
    
    /* Bottom Actions Row */
    .actions-card {
      background: var(--clr-white);
      padding: 1.5rem;
      border-radius: var(--border-radius-3);
      box-shadow: var(--box-shadow);
      margin-top: 1.5rem;
    }
    
    .actions-buttons-container {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }
    
    .action-btn {
      padding: 0.6rem 1.4rem;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      border: 1px solid transparent;
      transition: all 0.2s ease;
      font-size: 0.88rem;
    }
    .btn-accept { background: #2ed573; color: white; }
    .btn-accept:hover { background: #26b962; }
    .btn-prepare { background: #fffbeb; color: #d97706; border-color: #f59e0b; }
    .btn-prepare:hover { background: #fef3c7; }
    .btn-shipping { background: #eff6ff; color: #2563eb; border-color: #3b82f6; }
    .btn-shipping:hover { background: #dbeafe; }
    .btn-deliver { background: #f0fdf4; color: #16a34a; border-color: #22c55e; }
    .btn-deliver:hover { background: #dcfce7; }
    .btn-cancel-ord { background: #fef2f2; color: #dc2626; border-color: #ef4444; }
    .btn-cancel-ord:hover { background: #fee2e2; }
  </style>
</head>
<body>
   <div class="container">
      <?php include_once __DIR__ . '/sidebar.php'; ?>

      <main style="max-width: 100%;">
         <!-- Header Section -->
         <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
             <div>
                 <h1 style="font-size: 2.2rem; font-weight: 800; color: var(--clr-dark); margin: 0;">Order Details</h1>
                 <p style="color: var(--clr-dark-variant); font-size: 0.9rem; margin-top: 0.2rem;">
                     Orders <i class="fa fa-chevron-right" style="font-size: 0.75rem; margin: 0 0.3rem;"></i> Order <span style="color: var(--clr-primary); font-weight: 600;"><?php echo htmlspecialchars($disp_order_num); ?></span>
                 </p>
             </div>
             <a href="orders_page.php" class="action-btn" style="background: var(--clr-white); color: var(--clr-dark); border: 1px solid var(--clr-border); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: var(--box-shadow);">
                 <i class="fa fa-arrow-left"></i> Back to Orders
             </a>
         </div>

         <!-- Summary row -->
         <div class="summary-row">
             <div class="summary-card">
                 <small class="text-muted" style="font-weight: 500;">Order Status</small>
                 <span class="badge-status <?php echo $status_badge_class; ?>" style="margin-top: 0.2rem;"><?php echo $status_label; ?></span>
             </div>
             <div class="summary-card">
                 <small class="text-muted" style="font-weight: 500;">Payment Status</small>
                 <span class="badge-payment <?php echo $payment_badge_class; ?>" style="margin-top: 0.2rem;"><?php echo $payment_status; ?></span>
             </div>
              <div class="summary-card">
                  <small class="text-muted" style="font-weight: 500;">Order Type</small>
                  <span style="font-weight: 700; color: var(--clr-dark); display: flex; align-items: center; gap: 0.4rem; font-size: 1rem; margin-top: 0.2rem;">
                      <i class="fa fa-truck" style="color: var(--clr-primary);"></i>
                      <?php echo htmlspecialchars($order_type); ?>
                  </span>
              </div>
             <div class="summary-card">
                 <small class="text-muted" style="font-weight: 500;">Order Date & Time</small>
                 <span style="font-weight: 600; color: var(--clr-dark-variant); display: flex; align-items: center; gap: 0.4rem; font-size: 0.95rem; margin-top: 0.2rem;">
                     <i class="fa fa-calendar" style="color: var(--clr-dark-variant);"></i>
                     <?php echo date('d M Y, h:i A', strtotime($order['order_time'])); ?>
                 </span>
             </div>
         </div>

         <!-- Details Grid -->
         <div class="details-grid">
             <!-- Left pane: Items & Customer -->
             <div>
                 <!-- Items Ordered -->
                 <div class="details-card">
                     <h3>Items Ordered (<?php echo count($order_items); ?>)</h3>
                     <table style="width: 100%; border-collapse: collapse; text-align: left;">
                         <thead>
                             <tr style="border-bottom: 2px solid var(--clr-info-light); color: var(--clr-dark-variant); font-size: 0.85rem;">
                                 <th style="padding: 0.75rem 0.5rem;">Item</th>
                                 <th style="padding: 0.75rem 0.5rem; text-align: center;">Qty</th>
                                 <th style="padding: 0.75rem 0.5rem; text-align: right;">Price</th>
                                 <th style="padding: 0.75rem 0.5rem; text-align: right;">Total</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php foreach ($order_items as $item): ?>
                             <tr style="border-bottom: 1px solid var(--clr-info-light);">
                                 <td style="padding: 1rem 0.5rem; display: flex; align-items: center; gap: 0.75rem;">
                                     <img src="../<?php echo htmlspecialchars($item['menu_image'] ?: 'assets/images/placeholder.jpg'); ?>" style="width: 45px; height: 45px; border-radius: 8px; object-fit: cover; border: 1px solid var(--clr-border);">
                                     <strong style="color: var(--clr-dark);"><?php echo htmlspecialchars($item['menu_name']); ?></strong>
                                 </td>
                                 <td style="padding: 1rem 0.5rem; text-align: center; color: var(--clr-dark); font-weight: 600;"><?php echo intval($item['quantity']); ?></td>
                                 <td style="padding: 1rem 0.5rem; text-align: right; color: var(--clr-dark);">Rs. <?php echo number_format((float)$item['price'], 2); ?></td>
                                 <td style="padding: 1rem 0.5rem; text-align: right; color: var(--clr-dark); font-weight: 700;">Rs. <?php echo number_format((float)$item['total_price'], 2); ?></td>
                             </tr>
                             <?php endforeach; ?>
                             <tr>
                                 <td colspan="3" style="padding: 0.8rem 0.5rem; text-align: right; color: var(--clr-dark-variant);">Subtotal</td>
                                 <td style="padding: 0.8rem 0.5rem; text-align: right; color: var(--clr-dark); font-weight: 600;">Rs. <?php echo number_format($subtotal, 2); ?></td>
                             </tr>
                             <tr style="border-bottom: 2px solid var(--clr-info-light);">
                                 <td colspan="3" style="padding: 0.8rem 0.5rem; text-align: right; color: var(--clr-dark-variant);">Delivery Charge</td>
                                 <td style="padding: 0.8rem 0.5rem; text-align: right; color: var(--clr-success); font-weight: 600;">
                                     <?php echo $delivery_charge > 0 ? 'Rs. ' . number_format($delivery_charge, 2) : 'FREE'; ?>
                                 </td>
                             </tr>
                             <tr>
                                 <td colspan="3" style="padding: 1rem 0.5rem; text-align: right; font-size: 1.1rem; font-weight: 700; color: var(--clr-dark);">Total Amount</td>
                                 <td style="padding: 1rem 0.5rem; text-align: right; font-size: 1.25rem; font-weight: 800; color: var(--clr-primary);">Rs. <?php echo number_format($total_amount, 2); ?></td>
                             </tr>
                         </tbody>
                     </table>
                 </div>

                  <!-- Customer Information -->
                  <div class="details-card">
                      <h3>Customer Information</h3>
                      <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                          <div style="display: flex; align-items: start; gap: 1rem;">
                              <i class="fa fa-user" style="width: 16px; color: var(--clr-dark-variant); margin-top: 0.2rem;"></i>
                              <div>
                                  <small class="text-muted" style="display: block; font-size: 0.75rem;">Email</small>
                                  <strong style="color: var(--clr-dark);"><?php echo htmlspecialchars($order['email'] ?: 'N/A'); ?></strong>
                              </div>
                          </div>
                          <div style="display: flex; align-items: start; gap: 1rem;">
                              <i class="fa fa-phone" style="width: 16px; color: var(--clr-dark-variant); margin-top: 0.2rem;"></i>
                              <div>
                                  <small class="text-muted" style="display: block; font-size: 0.75rem;">Mobile Number</small>
                                  <strong style="color: var(--clr-dark);"><?php echo htmlspecialchars($order['mobile'] ?: 'N/A'); ?></strong>
                              </div>
                          </div>
                          <div style="display: flex; align-items: start; gap: 1rem;">
                              <i class="fa fa-map-marker-alt" style="width: 16px; color: var(--clr-dark-variant); margin-top: 0.2rem;"></i>
                              <div>
                                  <small class="text-muted" style="display: block; font-size: 0.75rem;">Delivery Address</small>
                                  <strong style="color: var(--clr-dark); line-height: 1.4;"><?php echo htmlspecialchars($order['address'] ?: 'N/A'); ?></strong>
                              </div>
                          </div>
                      </div>
                  </div>
             </div>

             <!-- Right pane: Additional Details & Timeline -->
             <div>
                  <!-- Additional Details -->
                  <div class="details-card" style="padding: 1.25rem;">
                      <h3>Order Additional Details</h3>
                      <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                          <tr style="border-bottom: 1px solid var(--clr-info-light);">
                              <td style="padding: 0.6rem 0; color: var(--clr-dark-variant);">Payment Method</td>
                              <td style="padding: 0.6rem 0; text-align: right; font-weight: 600; color: var(--clr-dark);"><?php echo htmlspecialchars($payment_method); ?></td>
                          </tr>
                          <tr>
                              <td style="padding: 0.6rem 0; color: var(--clr-dark-variant);">Estimated Delivery Time</td>
                              <td style="padding: 0.6rem 0; text-align: right; font-weight: 700; color: var(--clr-success);">30 - 45 mins</td>
                          </tr>
                      </table>
                  </div>

                 <!-- Timeline -->
                 <div class="timeline-container">
                     <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--clr-dark); margin-bottom: 1.5rem; border-bottom: 1px solid var(--clr-border); padding-bottom: 0.5rem;">Order Timeline</h3>
                     <ul class="timeline">
                         <li class="timeline-item active">
                             <span class="timeline-dot"></span>
                             <strong style="color: var(--clr-dark); display: block; font-size: 0.9rem;">Order Placed</strong>
                             <small class="text-muted" style="font-size: 0.75rem;"><?php echo date('d M Y, h:i A', strtotime($order['order_time'])); ?></small>
                         </li>
                         <li class="timeline-item <?php echo in_array($order['status'], ['Confirmed', 'Ongoing', 'Shipping', 'Delivering'], true) ? 'active' : ''; ?>">
                             <span class="timeline-dot"></span>
                             <strong style="color: var(--clr-dark); display: block; font-size: 0.9rem;">Accepted</strong>
                             <small class="text-muted" style="font-size: 0.75rem;">
                                 <?php echo in_array($order['status'], ['Confirmed', 'Ongoing', 'Shipping', 'Delivering'], true) ? 'Order confirmed by Admin' : '-'; ?>
                             </small>
                         </li>
                         <li class="timeline-item <?php echo in_array($order['status'], ['Ongoing', 'Shipping', 'Delivering'], true) ? 'active' : ''; ?>">
                             <span class="timeline-dot"></span>
                             <strong style="color: var(--clr-dark); display: block; font-size: 0.9rem;">Preparing</strong>
                             <small class="text-muted" style="font-size: 0.75rem;">
                                 <?php echo in_array($order['status'], ['Ongoing', 'Shipping', 'Delivering'], true) ? 'Food is being prepared' : '-'; ?>
                             </small>
                         </li>
                         <li class="timeline-item <?php echo in_array($order['status'], ['Shipping', 'Delivering'], true) ? 'active' : ''; ?>">
                             <span class="timeline-dot"></span>
                             <strong style="color: var(--clr-dark); display: block; font-size: 0.9rem;">Out for Delivery</strong>
                             <small class="text-muted" style="font-size: 0.75rem;">
                                 <?php echo in_array($order['status'], ['Shipping', 'Delivering'], true) ? 'Order dispatched for delivery' : '-'; ?>
                             </small>
                         </li>
                         <li class="timeline-item <?php echo $order['status'] === 'Delivering' ? 'active' : ''; ?>">
                             <span class="timeline-dot"></span>
                             <strong style="color: var(--clr-dark); display: block; font-size: 0.9rem;">Delivered</strong>
                             <small class="text-muted" style="font-size: 0.75rem;">
                                 <?php echo $order['status'] === 'Delivering' ? 'Order delivered successfully' : '-'; ?>
                             </small>
                         </li>
                     </ul>
                 </div>
             </div>
         </div>

         <!-- Order Actions -->
         <div class="actions-card">
             <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--clr-dark); margin-bottom: 1.2rem; border-bottom: 1px solid var(--clr-border); padding-bottom: 0.5rem;">Order Actions</h3>
             <div class="actions-buttons-container">
                 <form action="<?php echo $actionUrl; ?>" method="post" style="display:inline-block;">
                     <input type="hidden" name="action" value="update_status">
                     <input type="hidden" name="status" value="Confirmed">
                     <button type="submit" class="action-btn btn-accept" <?php echo $order['status'] === 'Confirmed' ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>Accept Order</button>
                 </form>

                 <form action="<?php echo $actionUrl; ?>" method="post" style="display:inline-block;">
                     <input type="hidden" name="action" value="update_status">
                     <input type="hidden" name="status" value="Ongoing">
                     <button type="submit" class="action-btn btn-prepare" <?php echo $order['status'] === 'Ongoing' ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>Mark as Preparing</button>
                 </form>

                 <form action="<?php echo $actionUrl; ?>" method="post" style="display:inline-block;">
                     <input type="hidden" name="action" value="update_status">
                     <input type="hidden" name="status" value="Shipping">
                     <button type="submit" class="action-btn btn-shipping" <?php echo $order['status'] === 'Shipping' ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>Out for Delivery</button>
                 </form>

                 <form action="<?php echo $actionUrl; ?>" method="post" style="display:inline-block;">
                     <input type="hidden" name="action" value="update_status">
                     <input type="hidden" name="status" value="Delivering">
                     <button type="submit" class="action-btn btn-deliver" <?php echo $order['status'] === 'Delivering' ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>Mark as Delivered</button>
                 </form>

                 <form action="<?php echo $actionUrl; ?>" method="post" style="display:inline-block;">
                     <input type="hidden" name="action" value="update_status">
                     <input type="hidden" name="status" value="Cancelled">
                     <button type="submit" class="action-btn btn-cancel-ord" <?php echo $order['status'] === 'Cancelled' ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>Cancel Order</button>
                 </form>
             </div>
         </div>
      </main>
   </div>

   <script src="../assets/js/adminscript.js"></script>
</body>
</html>
