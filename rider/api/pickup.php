<?php
/**
 * POST — rider confirms pickup: Ready → Delivering.
 * Ownership is enforced against the session rider id, then the transition goes
 * through the shared apply_order_status() (validate_order_transition + UPDATE
 * every row of the order + order_status_history), so the customer tracker
 * advances to "Out for Delivery" exactly as it does for admin/staff moves.
 */
require_once __DIR__ . '/_api_guard.php';

if (!api_is_post()) {
    api_json(['success' => false, 'message' => 'POST required']);
}

$input = api_input();
api_require_csrf($input);

$orderNumber = trim((string)($input['order_number'] ?? ''));
if ($orderNumber === '') {
    api_json(['success' => false, 'message' => 'Missing order number.']);
}

$delivery = delivery_find($conn, $orderNumber);
if (!$delivery) {
    api_json(['success' => false, 'message' => 'Delivery not found.']);
}
if ($delivery['rider_id'] === null || (int)$delivery['rider_id'] !== $RIDER_ID) {
    http_response_code(403);
    api_json(['success' => false, 'message' => 'This delivery is not assigned to you.']);
}
if ((string)$delivery['status'] === 'Delivering') {
    api_json(['success' => false, 'message' => 'You have already picked this order up.']);
}
if ((string)$delivery['status'] !== 'Ready') {
    api_json(['success' => false, 'message' => 'This order is not ready for pickup.']);
}

$result = apply_order_status($conn, $orderNumber, 'Delivering');
if (!$result['success']) {
    api_json($result);
}

$stamp = $conn->prepare("UPDATE deliveries SET picked_up_at = NOW() WHERE order_number = ? AND rider_id = ?");
if ($stamp) {
    $stamp->bind_param('si', $orderNumber, $RIDER_ID);
    $stamp->execute();
    $stamp->close();
}

api_json([
    'success'      => true,
    'message'      => 'Picked up — you are on the way.',
    'order_number' => $orderNumber,
    'status'       => 'Delivering',
]);
