<?php
session_start();
include_once __DIR__ . '/../includes/db.php';
include_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/bootstrap.php';

// Check if user is logged in
$user = getUserFromCookie();

// Profile image (from secure cookies)
$profileImg = 'assets/images/profile.jpg';
if (isset($_COOKIE['user_img'])) {
  $dec = decrypt($_COOKIE['user_img'], SECRET_KEY);
  if ($dec && is_string($dec)) {
    $candidate = ltrim($dec, '/');
    if (file_exists(__DIR__ . '/../' . $candidate)) {
      $profileImg = $candidate;
    }
  }
}

if (!$user) {
    header('Location: ../login.php');
    exit;
}

// Ensure cart is properly indexed with numeric keys
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = array_values($_SESSION['cart']);
}

// Handle AJAX requests for cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    switch ($_POST['ajax_action']) {
        case 'get_cart':
            $response['success'] = true;
            $response['data'] = $_SESSION['cart'] ?? [];
            $response['count'] = count($_SESSION['cart'] ?? []);
            break;
            
        case 'update_quantity':
            if (isset($_SESSION['cart'])) {
                $index = intval($_POST['index']);
                $quantity = intval($_POST['quantity']);
                
                if (isset($_SESSION['cart'][$index]) && $quantity > 0) {
                    $_SESSION['cart'][$index]['quantity'] = $quantity;
                    $_SESSION['cart'][$index]['total'] = $_SESSION['cart'][$index]['price'] * $quantity;
                    $response['success'] = true;
                    $response['message'] = 'Quantity updated';
                } else {
                    $response['message'] = "Item not found at index $index or invalid quantity $quantity";
                }
            } else {
                $response['message'] = "Cart session not found";
            }
            break;
            
        case 'remove_item':
            if (isset($_SESSION['cart'])) {
                $index = intval($_POST['index']);
                if (isset($_SESSION['cart'][$index])) {
                    unset($_SESSION['cart'][$index]);
                    $_SESSION['cart'] = array_values($_SESSION['cart']);
                    $response['success'] = true;
                    $response['message'] = 'Item removed from cart';
                    $response['count'] = count($_SESSION['cart']);
                } else {
                    $response['message'] = "Item not found at index $index";
                }
            } else {
                $response['message'] = "Cart session not found";
            }
            break;
            
        case 'clear_cart':
            $_SESSION['cart'] = [];
            $response['success'] = true;
            $response['message'] = 'Cart cleared';
            break;
            
            case 'checkout':
            if (!empty($_SESSION['cart'])) {
                $email = trim($_POST['email'] ?? '');
                $full_name = trim($_POST['full_name'] ?? '');
                $mobile = trim($_POST['mobile'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $order_type = trim($_POST['order_type'] ?? 'Delivery');
                $table_number = trim($_POST['table_number'] ?? '');
                $special_instructions = trim($_POST['special_instructions'] ?? '');
                $payment_method = trim($_POST['payment_method'] ?? 'Cash on Delivery');
                
                if (empty($email) || empty($full_name) || empty($mobile)) {
                    $response['message'] = 'Missing required checkout information';
                    break;
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $response['message'] = 'Please enter a valid email address';
                    break;
                }
                
                if (!preg_match('/^\d{10}$/', $mobile)) {
                    $response['message'] = 'Invalid mobile number format';
                    break;
                }

              // Only Delivery and Takeaway can be placed through the customer cart.
// Dine In orders are created exclusively via the table QR self-order flow.
if (!in_array($order_type, ['Delivery', 'Takeaway'], true)) {
    $response['message'] = 'Invalid order type. Dine-in orders must be placed via table QR ordering.';
    break;
}

if ($order_type === 'Delivery') {
    if ($address === '') {
        $response['message'] = 'Delivery address is required for delivery orders';
        break;
    }
    $table_number = '';
} elseif ($order_type === 'Takeaway') {
    $address = '';
    $table_number = '';
}
                
                // Generate a single unique order_number for this entire checkout
                $order_number = 'ORD-' . date('Ymd') . '-' . sprintf('%04d', rand(1000, 9999));
                
                // Determine payment status
                $is_esewa = ($payment_method === 'eSewa');
                $payment_status = $is_esewa ? 'Pending' : 'Paid';
                
                $conn->begin_transaction();
                
                $success_count = 0;
                $error_occurred = false;
                $last_insert_id = 0;
                
                $stmt = $conn->prepare(
                    "INSERT INTO orders (order_number, menu_id, email, menu_name, quantity, price, total_price, mobile, address, payment_method, payment_status, status, order_time, order_date, status_updated_at) "
                    . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), CURDATE(), NOW())"
                );
                
                if (!$stmt) {
                    error_log('Prepare statement failed: ' . $conn->error);
                    $conn->rollback();
                    $response['message'] = 'Error placing order: Database error occurred';
                    break;
                }
                
                foreach ($_SESSION['cart'] as $item) {
                    if (!isset($item['menu_id']) || !isset($item['name']) || !isset($item['quantity']) || 
                        !isset($item['price']) || !isset($item['total'])) {
                        error_log('Invalid item data in cart: ' . print_r($item, true));
                        continue;
                    }
                    
                    $result = $stmt->bind_param("sissidsssss", 
                        $order_number,
                        $item['menu_id'],
                        $email,
                        $item['name'],
                        $item['quantity'],
                        $item['price'],
                        $item['total'],
                        $mobile,
                        $address,
                        $payment_method,
                        $payment_status
                    );
                    
                    if (!$result) {
                        error_log('Bind param failed: ' . $stmt->error);
                        $error_occurred = true;
                        break;
                    }
                    
                    if ($stmt->execute()) {
                        $success_count++;
                        if ($last_insert_id === 0) {
                            $last_insert_id = $conn->insert_id;
                        }
                    } else {
                        error_log('Execute failed: ' . $stmt->error);
                        $error_occurred = true;
                        break;
                    }
                }
                $stmt->close();
                
                if ($success_count > 0 && !$error_occurred) {
                    $conn->commit();
                    $_SESSION['cart'] = [];

                    // Permanent status history: the order starts at Pending
                    // with this moment as its timestamp
                    $hstmt = $conn->prepare("INSERT IGNORE INTO order_status_history (order_number, status) VALUES (?, 'Pending')");
                    if ($hstmt) {
                        $hstmt->bind_param('s', $order_number);
                        $hstmt->execute();
                        $hstmt->close();
                    }

                    $response['success'] = true;
                    
                    if ($is_esewa) {
                        // For eSewa: keep cart until payment confirmed, redirect to esewa/pay.php
                        $response['message'] = 'Order saved. Redirecting to eSewa...';
                        $response['payment_method'] = 'eSewa';
                        $response['order_id'] = $last_insert_id;
                        $response['order_number'] = $order_number;
                        $response['redirect'] = null;
                    } else {
                        // For cash/pay-at-restaurant: clear cart and go to myorder
                        $_SESSION['cart'] = [];
                        $response['message'] = 'Order placed successfully!';
                        $response['redirect'] = '/Merobhoj/client/myorder.php';
                    }
                } else {
                    $conn->rollback();
                    $response['message'] = 'Error placing order' . ($error_occurred ? ': Database error occurred' : '');
                }
            } else {
                $response['message'] = 'Cart is empty';
            }
            break;
    }
    
    echo json_encode($response);
    exit;
}

$cart = array_values($_SESSION['cart'] ?? []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Cart | Mero Bhoj</title>
  <link rel="stylesheet" href="../assets/css/style.css" />
  <link rel="stylesheet" href="../assets/css/toast_styles.css" />
  <link rel="stylesheet" href="../assets/css/clientstyles.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" />
</head>
<body>
  <div class="loader">
    <i class="fas fa-utensils loader-icone"></i>
    <p>Mero Bhoj</p>
    <div class="loader-ellipses">
      <span></span>
      <span></span>
      <span></span>
    </div>
  </div>

  <header>
    <div class="container header my-3 d-none d-lg-flex">
      <div class="logo">
        <a href="./index.php">
          <i class="fa fa-utensils me-3"></i>
          <h1 class="mb-0">Mero Bhoj</h1>
        </a>
      </div>
      <div class="menus">
        <ul class="d-flex mb-0">
          <li class="list-unstyled py-2"><a class="text-decoration-none text-uppercase p-4" href="./index.php">Home</a></li>
          <li class="list-unstyled py-2"><a class="text-decoration-none text-uppercase p-4" href="./aboutus.php">About</a></li>
          <li class="list-unstyled py-2"><a class="text-decoration-none text-uppercase p-4" href="./menu.php">Menu</a></li>
          <li class="list-unstyled py-2"><a class="text-decoration-none text-uppercase p-4" href="./myorder.php">My Order</a></li>
          <li class="list-unstyled py-2"><a class="text-decoration-none text-uppercase p-4" href="./booking.php">My Booking</a></li>
          <li class="list-unstyled py-2"><a class="text-decoration-none text-uppercase p-4" href="./contactus.php">Contact</a></li>
        </ul>
      </div>
      <div class="icons d-flex align-items-center">
        <a class="text-decoration-none" id="searchBtn" href="#"><i class="fa fa-search me-3"></i></a>
        <a class="text-decoration-none" id="shoppingbutton" href="./cart.php"><i class="fa fa-shopping-bag me-3"></i></a>
        <div class="dropdown">
          <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" id="profileMenu" data-bs-toggle="dropdown" aria-expanded="false">
            <img src="<?php echo url($profileImg); ?>" alt="profile" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileMenu">
            <li><h6 class="dropdown-header"><?php echo htmlspecialchars($user['email'] ?? ''); ?></h6></li>
            <li><hr class="dropdown-divider"></li>
            
            <li><a class="dropdown-item" href="<?php echo url('includes/logout.php'); ?>"><i class="fa fa-right-from-bracket me-2"></i>Logout</a></li>
          </ul>
        </div>
      </div>
    </div>
  </header>

  <main class="cart-page-shell">
    <div>
      <h1>Your cart is ready in a drawer</h1>
      <p>Review items, adjust quantity, clear the cart, or checkout without leaving the page.</p>
      <div class="cart-page-actions">
        <a class="btn btn-outline-secondary px-4" href="menu.php">Browse Menu</a>
        <a class="btn btn-cart-open px-4" id="cartPageOpenButton" href="cart.php">Open Cart</a>
      </div>
    </div>
  </main>

  <?php include_once __DIR__ . '/../includes/cart_drawer.php'; ?>
  <?php include_once __DIR__ . '/../footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?php echo asset('js/script.js'); ?>"></script>
  <script src="<?php echo asset('js/clientscript.js'); ?>"></script>
  <script src="../assets/js/toast_notifications.js"></script>
</body>
</html>
