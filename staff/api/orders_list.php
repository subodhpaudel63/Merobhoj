<?php
require_once __DIR__ . '/_api_guard.php';

// Model on chef/api/kitchen_orders.php but WHERE status IN ('Pending','Confirmed','Preparing','Ready','Delivering')
// adding per-order total (SUM(price*quantity)), payment_method, mobile for FOH view.

$sql = "
    SELECT 
        order_number,
        order_type,
        table_number,
        full_name,
        mobile,
        payment_method,
        status,
        MIN(created_at) AS created_at,
        SUM(price * quantity) AS total_price,
        GROUP_CONCAT(CONCAT(menu_name, ' x', quantity) SEPARATOR ', ') AS items_summary
    FROM orders
    WHERE status IN ('Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivering')
    GROUP BY order_number, order_type, table_number, full_name, mobile, payment_method, status
    ORDER BY created_at DESC
";

$result = $conn->query($sql);
$orders = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

echo json_encode(['success' => true, 'orders' => $orders]);
