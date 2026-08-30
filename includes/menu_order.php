<?php
session_start();
include_once "db.php";
require_once __DIR__ . '/auth_check.php';

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function respond_menu_order(array $payload, bool $isAjax): void {
    $message = $payload['message'] ?? ($payload['success'] ? 'Order placed successfully!' : 'Order failed. Please try again.');

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    $_SESSION['msg'] = [
        'type' => $payload['success'] ? 'success' : 'error',
        'text' => $message,
    ];

    if (!empty($payload['redirect'])) {
        header('Location: ' . $payload['redirect']);
    } elseif ($payload['success']) {
        header('Location: /Merobhoj/client/myorder.php');
    }
    exit;
}

// Check if user is logged in
$user = getUserFromCookie();

// If user is not logged in, redirect to login
if (!$user) {
    respond_menu_order([
        'success' => false,
        'message' => 'Please login to place an order.',
        'login_required' => true,
        'redirect' => '/Merobhoj/login.php?action=order_food',
    ], $isAjax);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $menu_id = isset($_POST['menu_id']) ? intval($_POST['menu_id']) : 0;
    $menu_name = isset($_POST['menu_name']) ? trim($_POST['menu_name']) : '';
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0.0;
    $total_price = isset($_POST['total_price']) ? floatval($_POST['total_price']) : 0.0;
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
    $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $order_type = isset($_POST['order_type']) ? trim($_POST['order_type']) : 'Delivery';
    $table_number = isset($_POST['table_number']) ? trim($_POST['table_number']) : null;
    $special_instructions = isset($_POST['special_instructions']) ? trim($_POST['special_instructions']) : null;
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'Cash on Delivery';

    // Basic validation
    if ($menu_id <= 0 || empty($menu_name) || $quantity <= 0 || $price <= 0 || $total_price <= 0 || empty($email) || empty($full_name) || !preg_match('/^[0-9]{10}$/', $mobile)) {
        respond_menu_order([
            'success' => false,
            'message' => 'Invalid order data. Please fill in all required fields correctly.',
        ], $isAjax);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond_menu_order([
            'success' => false,
            'message' => 'Please enter a valid email address.',
        ], $isAjax);
    }

    if ($order_type === 'Delivery') {
        if ($address === '') {
            respond_menu_order([
                'success' => false,
                'message' => 'Delivery address is required for delivery orders.',
            ], $isAjax);
        }
        $table_number = null;
    } elseif ($order_type === 'Dine In') {
        if ($table_number === '') {
            respond_menu_order([
                'success' => false,
                'message' => 'Table number is required for dine in orders.',
            ], $isAjax);
        }
        $address = '';
    } elseif ($order_type === 'Takeaway') {
        $address = '';
        $table_number = null;
    }

    // Insert into DB using only columns that exist in the orders table
    $order_number = 'ORD-' . date('Ymd') . '-' . sprintf('%04d', rand(1000, 9999));
    $stock_stmt = $conn->prepare("SELECT menu_status FROM menu WHERE menu_id = ? LIMIT 1");
    $stock_stmt->bind_param("i", $menu_id);
    $stock_stmt->execute();
    $stock_result = $stock_stmt->get_result();
    $stock_row = $stock_result ? $stock_result->fetch_assoc() : null;
    $stock_stmt->close();

    if (!$stock_row || (($stock_row['menu_status'] ?? 'In Stock') === 'Out of Stock')) {
        respond_menu_order([
            'success' => false,
            'message' => 'Sorry, this item is currently out of stock.',
        ], $isAjax);
    }

    $is_esewa = ($payment_method === 'eSewa');
    $payment_status = $is_esewa ? 'Pending' : 'Paid';

    $stmt = $conn->prepare("INSERT INTO orders (order_number, menu_id, menu_name, price, quantity, total_price, email, mobile, address, payment_method, payment_status, status, order_time, order_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), CURDATE())");
    $stmt->bind_param("sisdidsssss", $order_number, $menu_id, $menu_name, $price, $quantity, $total_price, $email, $mobile, $address, $payment_method, $payment_status);

    if ($stmt->execute()) {
        $insert_id = $stmt->insert_id;
        
        respond_menu_order([
            'success' => true,
            'message' => $is_esewa ? 'Order saved. Redirecting to eSewa...' : 'Order placed successfully!',
            'order_id' => $insert_id,
            'order_number' => $order_number,
            'payment_method' => $payment_method,
            'redirect' => '/Merobhoj/client/myorder.php',
        ], $isAjax);
    } else {
        respond_menu_order([
            'success' => false,
            'message' => 'Order failed. Please try again.',
        ], $isAjax);
    }
} else {
    // Invalid access
    respond_menu_order([
        'success' => false,
        'message' => 'Invalid access.',
    ], $isAjax);
}
?>
