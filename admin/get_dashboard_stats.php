<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

// Calculate dashboard statistics
$total_revenue_query = $conn->query("SELECT SUM(total_price) as revenue FROM orders");
$total_revenue = (float)($total_revenue_query->fetch_assoc()['revenue'] ?? 0);

$total_orders_query = $conn->query("SELECT COUNT(*) as total FROM orders");
$total_orders = $total_orders_query->fetch_assoc()['total'];

$confirmed_orders_query = $conn->query("SELECT COUNT(*) as confirmed FROM orders WHERE status = 'Confirmed'");
$confirmed_orders = $confirmed_orders_query->fetch_assoc()['confirmed'];

$delivering_orders_query = $conn->query("SELECT COUNT(*) as delivering FROM orders WHERE status = 'Delivering'");
$shipping_orders = $delivering_orders_query->fetch_assoc()['delivering'];

$response = [
    'success' => true,
    'stats' => [
        'total_revenue' => $total_revenue,
        'total_orders' => $total_orders,
        'confirmed_orders' => $confirmed_orders,
        'shipping_orders' => $shipping_orders
    ]
];

echo json_encode($response);
exit;