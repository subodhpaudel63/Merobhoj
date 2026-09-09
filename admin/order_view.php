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
    require_once __DIR__ . '/../includes/order_validation.php';
    $order_type = $order['order_type'] ?? 'Delivery';
    $val = validate_order_transition($order_type, $order['status'], $new_status, true);
    if ($val['valid']) {
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
    } else {
        $_SESSION['order_msg'] = "Error: " . $val['error'];
    }
    $redir = !empty($order['order_number']) ? ("order_view.php?order_number=" . urlencode($order['order_number'])) : ("order_view.php?id=" . $order['order_id']);
    header("Location: " . $redir);
    exit;
}

// Map database statuses to visual labels & badges
$status_label = htmlspecialchars($order['status']);
$status_badge_class = 'status-' . strtolower($order['status']);

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

         <!-- Header Section -->
         <div class="admin-page-heading">
             <div>
                 <h1>Order Details</h1>
                 <p>
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
                     <?php
                     $isDelivery = ($order_type === 'Delivery');
                     $cancelled  = ($order['status'] === 'Cancelled');
                     $completed  = ($order['status'] === 'Completed');
                     // Ordered progression
                     $flow_delivery   = ['Pending','Confirmed','Preparing','Ready','Delivering','Completed'];
                     $flow_nod        = ['Pending','Confirmed','Preparing','Ready','Completed'];
                     $flow = $isDelivery ? $flow_delivery : $flow_nod;
                     $currentIdx = array_search($order['status'], $flow);
                     $labels = [
                         'Pending'   => 'Order Pending',
                         'Confirmed' => 'Confirmed',
                         'Preparing' => 'Preparing',
                         'Ready'     => 'Ready',
                         'Delivering'=> 'Delivering',
                         'Completed' => 'Completed',
                     ];
                     $descriptions = [
                         'Pending'   => 'Order placed, waiting confirmation.',
                         'Confirmed' => 'Order confirmed by Admin.',
                         'Preparing' => 'Food is being prepared.',
                         'Ready'     => 'Order is ready.',
                         'Delivering'=> 'Order is on the way.',
                         'Completed' => 'Order delivered successfully.',
                     ];
                     ?>
                     <ul class="timeline">
                         <?php if ($cancelled): ?>
                         <li class="timeline-item active">
                             <span class="timeline-dot"></span>
                             <strong style="color: var(--clr-dark); display: block; font-size: 0.9rem;">Order Placed</strong>
                             <small class="text-muted" style="font-size: 0.75rem;"><?php echo date('d M Y, h:i A', strtotime($order['order_time'])); ?></small>
                         </li>
                         <li class="timeline-item active">
                             <span class="timeline-dot" style="border-color:#dc2626;background:#dc2626;"></span>
                             <strong style="color: #dc2626; display: block; font-size: 0.9rem;">Cancelled</strong>
                             <small class="text-muted" style="font-size: 0.75rem;">Order was cancelled.</small>
                         </li>
                         <?php else: ?>
                         <?php foreach ($flow as $idx => $step): ?>
                         <li class="timeline-item <?php echo ($currentIdx !== false && $idx <= $currentIdx) ? 'active' : ''; ?>">
                             <span class="timeline-dot"></span>
                             <strong style="color: var(--clr-dark); display: block; font-size: 0.9rem;"><?php echo $labels[$step]; ?></strong>
                             <small class="text-muted" style="font-size: 0.75rem;">
                                 <?php echo ($currentIdx !== false && $idx <= $currentIdx) ? $descriptions[$step] : '-'; ?>
                             </small>
                         </li>
                         <?php endforeach; ?>
                         <?php endif; ?>
                     </ul>
                 </div>
             </div>
         </div>

         <!-- Order Actions -->
         <?php if (!$cancelled && !$completed): ?>
         <div class="actions-card">
             <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--clr-dark); margin-bottom: 1.2rem; border-bottom: 1px solid var(--clr-border); padding-bottom: 0.5rem;">Order Actions</h3>
             <div class="actions-buttons-container">
                 <?php
                 require_once __DIR__ . '/../includes/order_validation.php';
                 // Build the ordered flow for next-step
                 $adminFlow = $isDelivery ? ['Pending','Confirmed','Preparing','Ready','Delivering','Completed'] : ['Pending','Confirmed','Preparing','Ready','Completed'];
                 $curIdx = array_search($order['status'], $adminFlow);
                 $nextStatus = ($curIdx !== false && isset($adminFlow[$curIdx + 1])) ? $adminFlow[$curIdx + 1] : null;
                 $btnLabels = ['Confirmed'=>'Confirm Order','Preparing'=>'Mark as Preparing','Ready'=>'Mark as Ready','Delivering'=>'Mark as Delivering','Completed'=>'Mark as Completed'];
                 $btnClasses = ['Confirmed'=>'btn-accept','Preparing'=>'btn-prepare','Ready'=>'btn-shipping','Delivering'=>'btn-shipping','Completed'=>'btn-deliver'];
                 ?>
                 <?php if ($nextStatus): ?>
                 <form action="<?php echo $actionUrl; ?>" method="post" style="display:inline-block;">
                     <input type="hidden" name="action" value="update_status">
                     <input type="hidden" name="status" value="<?php echo htmlspecialchars($nextStatus); ?>">
                     <button type="submit" class="action-btn <?php echo $btnClasses[$nextStatus] ?? 'btn-accept'; ?>"><?php echo $btnLabels[$nextStatus] ?? htmlspecialchars($nextStatus); ?></button>
                 </form>
                 <?php endif; ?>

                 <?php if (!in_array($order['status'], ['Completed', 'Cancelled'], true)): ?>
                 <form action="<?php echo $actionUrl; ?>" method="post" style="display:inline-block;">
                     <input type="hidden" name="action" value="update_status">
                     <input type="hidden" name="status" value="Cancelled">
                     <button type="submit" class="action-btn btn-cancel-ord">Cancel Order</button>
                 </form>
                 <?php endif; ?>
             </div>
         </div>
         <?php elseif ($completed): ?>
         <div class="actions-card">
             <p style="color: #15803D; font-weight: 600;"><i class="fa fa-circle-check"></i> This order has been completed. No further actions available.</p>
         </div>
         <?php elseif ($cancelled): ?>
         <div class="actions-card">
             <p style="color: #B91C1C; font-weight: 600;"><i class="fa fa-circle-xmark"></i> This order has been cancelled. No further actions available.</p>
         </div>
         <?php endif; ?>
      </main>
   </div>

   
   <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
  <script src="../assets/js/admin2.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin2.js') ?>"></script>
</body>
</html>
