<?php
/**
 * POST — chef advances an order through KITCHEN stages ONLY.
 * Allowed targets are hard-limited to Confirmed / Preparing / Ready regardless
 * of input; the transition is still validated by validate_order_transition().
 * Writes mirror admin/update_order_status_ajax.php exactly (all rows sharing the
 * order_number + upsert into order_status_history) so the customer tracker and
 * admin panel stay consistent.
 */
require_once __DIR__ . '/_api_guard.php';
require_once __DIR__ . '/../../includes/order_validation.php';

if (!api_is_post()) {
    api_json(['success' => false, 'message' => 'POST required']);
}

$input = api_input();
api_require_csrf($input);

// Chef may only move tickets into these stages.
const CHEF_ALLOWED_TARGETS = ['Confirmed', 'Preparing', 'Ready'];

$order_id     = (int)($input['order_id'] ?? 0);
$order_number = trim((string)($input['order_number'] ?? ''));
$status       = trim((string)($input['status'] ?? ''));

if ($order_id <= 0 && $order_number === '') {
    api_json(['success' => false, 'message' => 'Missing order identifier']);
}
if (!in_array($status, CHEF_ALLOWED_TARGETS, true)) {
    api_json(['success' => false, 'message' => 'Chefs can only set Confirmed, Preparing or Ready.']);
}

// Load the current order (by number preferred, else id).
$existingOrder = null;
if ($order_number !== '') {
    $lookup = $conn->prepare("SELECT order_id, order_number, status, order_type FROM orders WHERE order_number = ? LIMIT 1");
    $lookup->bind_param('s', $order_number);
} else {
    $lookup = $conn->prepare("SELECT order_id, order_number, status, order_type FROM orders WHERE order_id = ? LIMIT 1");
    $lookup->bind_param('i', $order_id);
}
if ($lookup) {
    $lookup->execute();
    $r = $lookup->get_result();
    $existingOrder = $r ? $r->fetch_assoc() : null;
    $lookup->close();
}

if (!$existingOrder) {
    api_json(['success' => false, 'message' => 'Order not found']);
}

$historyKey = !empty($existingOrder['order_number'])
    ? $existingOrder['order_number']
    : ($order_number !== '' ? $order_number : 'ORD-' . str_pad((string)$existingOrder['order_id'], 4, '0', STR_PAD_LEFT));

// Validate the transition (chef treated as staff for forward moves).
$validation = validate_order_transition($existingOrder['order_type'] ?? 'Delivery', (string)$existingOrder['status'], $status, true);
if (!$validation['valid']) {
    api_json(['success' => false, 'message' => $validation['error']]);
}

// Update every row of the order, then record the exact per-status time.
if (!empty($existingOrder['order_number'])) {
    $stmt = $conn->prepare("UPDATE orders SET status = ?, status_updated_at = NOW() WHERE order_number = ?");
    $stmt->bind_param('ss', $status, $existingOrder['order_number']);
} else {
    $stmt = $conn->prepare("UPDATE orders SET status = ?, status_updated_at = NOW() WHERE order_id = ?");
    $stmt->bind_param('si', $status, $existingOrder['order_id']);
}

if (!$stmt || !$stmt->execute()) {
    api_json(['success' => false, 'message' => 'Database update failed']);
}
$stmt->close();

$hstmt = $conn->prepare("INSERT INTO order_status_history (order_number, status) VALUES (?, ?) ON DUPLICATE KEY UPDATE changed_at = NOW()");
if ($hstmt) {
    $hstmt->bind_param('ss', $historyKey, $status);
    $hstmt->execute();
    $hstmt->close();
}

api_json([
    'success'      => true,
    'message'      => 'Order moved to ' . $status,
    'order_number' => $historyKey,
    'status'       => $status,
]);
