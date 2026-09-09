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
$stmt = $conn->prepare("SELECT o.order_id, o.order_number, o.menu_id, o.menu_name, o.price, o.quantity, o.total_price,
                               o.status, o.status_updated_at, o.order_type, o.order_time, o.order_date, o.address, o.mobile,
                               o.payment_method, o.payment_status, m.menu_image
                        FROM orders o
                        LEFT JOIN menu m ON m.menu_id = o.menu_id
                        WHERE LOWER(TRIM(o.email)) = LOWER(TRIM(?))
                          AND (o.order_type IS NULL OR o.order_type <> 'Dine In')
                        ORDER BY o.created_at DESC, o.order_id DESC");
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
            'status_updated_at' => $row['status_updated_at'] ?? null,
            'order_type' => $row['order_type'] ?? 'Delivery',
            'order_time' => $row['order_time'],
            'order_date' => $row['order_date'],
            'address' => $row['address'] ?? '',
            'mobile' => $row['mobile'] ?? '',
            'payment_method' => $row['payment_method'] ?? null,
            'payment_status' => $row['payment_status'] ?? null,
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
        'total_price' => $itemTotal,
        'menu_image' => $row['menu_image'] ?? null
    ];
}
$stmt->close();

// Per-status update times (permanent history) for this user's orders
$historyMap = [];
$hstmt = $conn->prepare("SELECT h.order_number, h.status, h.changed_at
                         FROM order_status_history h
                         INNER JOIN orders o ON o.order_number = h.order_number
                         WHERE o.email = ?");
if ($hstmt) {
    $hstmt->bind_param('s', $email);
    $hstmt->execute();
    $hres = $hstmt->get_result();
    while ($hrow = $hres->fetch_assoc()) {
        $historyMap[$hrow['order_number']][$hrow['status']] = $hrow['changed_at'];
    }
    $hstmt->close();
}

$orders = array_values($grouped);
foreach ($orders as &$g) {
    $g['status_history'] = $historyMap[$g['order_number']] ?? [];
}
unset($g);

echo json_encode(['ok' => true, 'orders' => $orders]);