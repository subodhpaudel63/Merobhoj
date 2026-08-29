<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';
require_once 'auth_check.php';

header('Content-Type: application/json');

// Check if user is logged in using the auth system
$user = getUserFromCookie();
if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit();
}

$email = $user['email'];
$stmt = $conn->prepare("SELECT order_id, order_number, menu_id, menu_name, price, quantity, total_price, status, order_type, order_time, order_date FROM orders WHERE email = ? ORDER BY order_id DESC");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$grouped = [];
while ($row = $result->fetch_assoc()) {
    $orderNum = !empty($row['order_number']) ? $row['order_number'] : ('ORD-' . str_pad((string)$row['order_id'], 4, '0', STR_PAD_LEFT));
    if (!isset($grouped[$orderNum])) {
        $grouped[$orderNum] = [
            'order_number' => $orderNum,
            'order_id' => $row['order_id'],
            'status' => $row['status'],
            'order_type' => $row['order_type'] ?? 'Delivery',
            'order_time' => $row['order_time'],
            'order_date' => $row['order_date'],
            'total_amount' => 0.0,
            'items' => []
        ];
    }
    $itemTotal = (float)$row['total_price'];
    $grouped[$orderNum]['total_amount'] += $itemTotal;
    $grouped[$orderNum]['items'][] = [
        'order_id' => $row['order_id'],
        'menu_id' => $row['menu_id'],
        'menu_name' => $row['menu_name'],
        'price' => (float)$row['price'],
        'quantity' => (int)$row['quantity'],
        'total_price' => $itemTotal
    ];
}
$stmt->close();

$orders = array_values($grouped);

echo json_encode(['ok' => true, 'orders' => $orders]);