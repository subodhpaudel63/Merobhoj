<?php
require_once __DIR__ . '/_api_guard.php';
require_once __DIR__ . '/../../includes/order_validation.php';

// Staff follows the same forward state machine as the main admin panel.
const STAFF_ALLOWED_TARGETS = ['Confirmed', 'Preparing', 'Ready', 'Delivering', 'Completed', 'Cancelled'];

$data = json_decode(file_get_contents('php://input'), true);
$orderNumber = trim((string)($data['order_number'] ?? ''));
$targetStatus = trim((string)($data['status'] ?? ''));

if (!$orderNumber || !$targetStatus) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!in_array($targetStatus, STAFF_ALLOWED_TARGETS, true)) {
    echo json_encode(['success' => false, 'message' => 'Staff is not authorized to set this status']);
    exit;
}

// Fetch current order status & type
$stmt = $conn->prepare("SELECT status, order_type FROM orders WHERE order_number = ? LIMIT 1");
$stmt->bind_param("s", $orderNumber);
$stmt->execute();
$currentOrder = $stmt->get_result()->fetch_assoc();

if (!$currentOrder) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$currentStatus = $currentOrder['status'];
$orderType = $currentOrder['order_type'];

// State Machine transition check
$validation = validate_order_transition($orderType, $currentStatus, $targetStatus, true);
if (!$validation['valid']) {
    echo json_encode([
        'success' => false,
        'message' => $validation['error'] ?? "Cannot transition order from {$currentStatus} to {$targetStatus}"
    ]);
    exit;
}

// Perform update
$conn->begin_transaction();
try {
    $upd = $conn->prepare("UPDATE orders SET status = ?, status_updated_at = NOW() WHERE order_number = ?");
    if (!$upd) {
        throw new RuntimeException('Unable to prepare order status update.');
    }
    $upd->bind_param("ss", $targetStatus, $orderNumber);
    if (!$upd->execute()) {
        throw new RuntimeException('Unable to update order status.');
    }
    $upd->close();

    // Keep the same per-status history format used by the admin panel.
    $hist = $conn->prepare(
        "INSERT INTO order_status_history (order_number, status)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE changed_at = NOW()"
    );
    if (!$hist) {
        throw new RuntimeException('Unable to prepare status history update.');
    }
    $hist->bind_param("ss", $orderNumber, $targetStatus);
    if (!$hist->execute()) {
        throw new RuntimeException('Unable to record order status history.');
    }
    $hist->close();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
