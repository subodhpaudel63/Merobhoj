<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once 'db.php';
require_once 'auth_check.php';
require_once 'order_validation.php';

$user = getUserFromCookie();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Please login to perform this action.']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
$order_number = trim((string)($input['order_number'] ?? ''));

if ($order_number === '') {
    echo json_encode(['success' => false, 'message' => 'Missing order number.']);
    exit;
}

// 1. Check order exists and verify owner
$stmt = $conn->prepare("SELECT email, status, order_type FROM orders WHERE order_number = ? LIMIT 1");
$stmt->bind_param("s", $order_number);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found.']);
    exit;
}

if ($order['email'] !== $user['email']) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized action. You do not own this order.']);
    exit;
}

// 2. Validate transition
$val = validate_order_transition($order['order_type'] ?? 'Delivery', $order['status'], 'Cancelled', false);
if (!$val['valid']) {
    echo json_encode(['success' => false, 'message' => $val['error']]);
    exit;
}

// 3. Perform cancellation for all items sharing this order_number
$stmt = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE order_number = ?");
$stmt->bind_param("s", $order_number);
if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Order cancelled successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to cancel the order.']);
}
$stmt->close();
exit;
