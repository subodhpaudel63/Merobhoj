<?php
require_once __DIR__ . '/_api_guard.php';

$tableId = (int)($_GET['table_id'] ?? 0);
$tableName = trim((string)($_GET['table_name'] ?? ''));

if (!$tableId && !$tableName) {
    echo json_encode(['success' => false, 'message' => 'Missing table identifier']);
    exit;
}

// Find table row
if ($tableId > 0) {
    $stmt = $conn->prepare("SELECT * FROM restaurant_tables WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $tableId);
} else {
    $stmt = $conn->prepare("SELECT * FROM restaurant_tables WHERE table_name = ? LIMIT 1");
    $stmt->bind_param("s", $tableName);
}
$stmt->execute();
$table = $stmt->get_result()->fetch_assoc();

if (!$table) {
    echo json_encode(['success' => false, 'message' => 'Table not found']);
    exit;
}

// Determine active orders for this table (Dine In)
$tblName = $table['table_name'];
$tIdStr = (string)$table['id'];
preg_match('/\d+/', $tblName, $m);
$numPart = $m[0] ?? '';

$sql = "
    SELECT 
        order_number,
        order_type,
        table_number,
        full_name,
        mobile,
        special_instructions,
        payment_method,
        status,
        MIN(created_at) AS created_at,
        SUM(price * quantity) AS total_price,
        COUNT(order_id) AS total_items
    FROM orders
    WHERE order_type = 'Dine In'
      AND status IN ('Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivering')
      AND (table_number = ? OR table_number = ? OR table_number = ?)
    GROUP BY order_number, order_type, table_number, full_name, mobile, special_instructions, payment_method, status
    ORDER BY created_at ASC
";
$stmtOrd = $conn->prepare($sql);
$stmtOrd->bind_param("sss", $tblName, $tIdStr, $numPart);
$stmtOrd->execute();
$resOrd = $stmtOrd->get_result();

$orders = [];
while ($row = $resOrd->fetch_assoc()) {
    $ordNum = $row['order_number'];
    // Fetch items for this order number
    $stmtItems = $conn->prepare("SELECT menu_name, quantity, price, total_price FROM orders WHERE order_number = ?");
    $stmtItems->bind_param("s", $ordNum);
    $stmtItems->execute();
    $resItems = $stmtItems->get_result();
    $items = [];
    while ($iRow = $resItems->fetch_assoc()) {
        $items[] = $iRow;
    }
    $stmtItems->close();
    $row['items'] = $items;
    $orders[] = $row;
}
$stmtOrd->close();

// Check reservation status
$todayStr = date('Y-m-d');
$now = date('Y-m-d H:i:s');
$stmtB = $conn->prepare("
    SELECT status, start_time, end_time, grace_end_at 
    FROM bookings 
    WHERE table_id = ? 
      AND DATE(booking_date) = ?
      AND status IN ('Pending', 'Confirmed', 'Checked-in')
      AND ? BETWEEN CONCAT(booking_date, ' ', start_time) AND IFNULL(grace_end_at, CONCAT(booking_date, ' ', end_time))
    LIMIT 1
");
$stmtB->bind_param("iss", $table['id'], $todayStr, $now);
$stmtB->execute();
$bRes = $stmtB->get_result()->fetch_assoc();
$stmtB->close();

$currentStatus = 'free';
if (!empty($orders)) {
    $currentStatus = 'occupied';
} else if ($bRes) {
    $currentStatus = ($bRes['status'] === 'Checked-in') ? 'occupied' : 'reserved';
}

echo json_encode([
    'success' => true,
    'table' => $table,
    'current_status' => $currentStatus,
    'orders' => $orders
]);
