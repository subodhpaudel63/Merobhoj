<?php
/**
 * Staff API - Get Order Details & Itemized Breakdown for POS Billing
 */
declare(strict_types=1);
require_once __DIR__ . '/_api_guard.php';

$orderNumber = trim($_GET['order_number'] ?? '');

if ($orderNumber === '') {
    // Return list of all active settleable orders (status in Pending, Confirmed, Preparing, Ready, Delivering)
    $sql = "
        SELECT 
            order_number,
            order_type,
            table_number,
            full_name,
            mobile,
            email,
            payment_method,
            payment_status,
            status,
            MIN(created_at) AS created_at,
            SUM(price * quantity) AS subtotal,
            COUNT(*) AS total_items,
            GROUP_CONCAT(CONCAT(menu_name, ' x', quantity) SEPARATOR ', ') AS items_summary
        FROM orders
        WHERE payment_status != 'Paid' AND status != 'Cancelled'
        GROUP BY order_number, order_type, table_number, full_name, mobile, email, payment_method, payment_status, status
        ORDER BY created_at DESC
    ";
    
    $res = $conn->query($sql);
    $orders = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    echo json_encode(['success' => true, 'orders' => $orders]);
    exit;
}

// Fetch specific order details & items
$stmt = $conn->prepare("
    SELECT 
        o.order_id,
        o.order_number,
        o.menu_id,
        o.menu_name,
        o.quantity,
        o.price,
        o.total_price,
        o.full_name,
        o.email,
        o.mobile,
        o.address,
        o.order_type,
        o.table_number,
        o.special_instructions,
        o.payment_method,
        o.payment_status,
        o.status,
        o.created_at,
        m.menu_image
    FROM orders o
    LEFT JOIN menu m ON o.menu_id = m.menu_id
    WHERE o.order_number = ?
");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database query preparation failed']);
    exit;
}

$stmt->bind_param('s', $orderNumber);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
$customer = [
    'full_name' => '',
    'email' => '',
    'mobile' => '',
    'address' => '',
    'order_type' => 'Dine In',
    'table_number' => '',
    'special_instructions' => '',
    'payment_method' => 'Cash',
    'payment_status' => 'Pending',
    'status' => 'Pending',
    'created_at' => '',
    'order_number' => $orderNumber
];

$subtotal = 0.0;

while ($row = $res->fetch_assoc()) {
    if (empty($customer['full_name'])) {
        $customer['full_name'] = $row['full_name'] ?: 'Guest Customer';
        $customer['email'] = $row['email'] ?: '';
        $customer['mobile'] = $row['mobile'] ?: '';
        $customer['address'] = $row['address'] ?: '';
        $customer['order_type'] = $row['order_type'] ?: 'Dine In';
        $customer['table_number'] = $row['table_number'] ?: '';
        $customer['special_instructions'] = $row['special_instructions'] ?: '';
        $customer['payment_method'] = $row['payment_method'] ?: 'Cash';
        $customer['payment_status'] = $row['payment_status'] ?: 'Pending';
        $customer['status'] = $row['status'] ?: 'Pending';
        $customer['created_at'] = $row['created_at'] ?: '';
    }

    $itemTotal = (float)$row['total_price'];
    $subtotal += $itemTotal;

    $items[] = [
        'order_id' => (int)$row['order_id'],
        'menu_id' => (int)$row['menu_id'],
        'menu_name' => $row['menu_name'],
        'quantity' => (int)$row['quantity'],
        'price' => (float)$row['price'],
        'total_price' => $itemTotal,
        'menu_image' => $row['menu_image'] ?: ''
    ];
}

$stmt->close();

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

// Check if a bill already exists for this order
$billStmt = $conn->prepare("SELECT * FROM bills WHERE order_number = ? LIMIT 1");
$existingBill = null;
if ($billStmt) {
    $billStmt->bind_param('s', $orderNumber);
    $billStmt->execute();
    $bRes = $billStmt->get_result();
    if ($bRes && $bRow = $bRes->fetch_assoc()) {
        $existingBill = $bRow;
    }
    $billStmt->close();
}

echo json_encode([
    'success' => true,
    'order' => $customer,
    'items' => $items,
    'subtotal' => $subtotal,
    'existing_bill' => $existingBill
]);
