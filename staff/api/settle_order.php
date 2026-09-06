<?php
require_once __DIR__ . '/_api_guard.php';

$data = json_decode(file_get_contents('php://input'), true);

$orderNumber = trim((string)($data['order_number'] ?? ''));
$discountAmount = (float)($data['discount_amount'] ?? 0);
$paymentMethod = trim((string)($data['payment_method'] ?? 'Cash'));

if (!$orderNumber) {
    echo json_encode(['success' => false, 'message' => 'Missing order number']);
    exit;
}

// Fetch order items
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_number = ?");
$stmt->bind_param("s", $orderNumber);
$stmt->execute();
$res = $stmt->get_result();
$orderItems = [];
while ($row = $res->fetch_assoc()) {
    $orderItems[] = $row;
}
$stmt->close();

if (empty($orderItems)) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$conn->begin_transaction();
try {
    // Settle order: set status to 'Completed', payment_status to 'Paid', payment_method
    $upd = $conn->prepare("UPDATE orders SET status = 'Completed', payment_status = 'Paid', payment_method = ?, status_updated_at = NOW() WHERE order_number = ?");
    if (!$upd) {
        throw new RuntimeException('Unable to prepare order settlement.');
    }
    $upd->bind_param("ss", $paymentMethod, $orderNumber);
    if (!$upd->execute()) {
        throw new RuntimeException('Unable to settle order.');
    }
    $upd->close();

    // Record history
    $hist = $conn->prepare("INSERT INTO order_status_history (order_number, status) VALUES (?, 'Completed') ON DUPLICATE KEY UPDATE changed_at = NOW()");
    if ($hist) {
        $hist->bind_param("s", $orderNumber);
        $hist->execute();
        $hist->close();
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Order settled successfully',
        'order_number' => $orderNumber
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
