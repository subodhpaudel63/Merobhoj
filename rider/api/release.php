<?php
/**
 * POST — hand a claimed delivery back to the pool.
 * Only permitted while the order is still at Ready (not yet picked up) AND the
 * delivery belongs to the logged-in rider (rider_id bound from the session).
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
if ((string)$delivery['status'] !== 'Ready') {
    api_json(['success' => false, 'message' => 'You have already picked this order up — it cannot be released.']);
}

$stmt = $conn->prepare("UPDATE deliveries SET rider_id = NULL, assigned_at = NULL
                        WHERE order_number = ? AND rider_id = ?");
if (!$stmt) {
    api_json(['success' => false, 'message' => 'Database error.']);
}
$stmt->bind_param('si', $orderNumber, $RIDER_ID);
$ok = $stmt->execute() && $stmt->affected_rows === 1;
$stmt->close();

if (!$ok) {
    api_json(['success' => false, 'message' => 'Could not release this delivery.']);
}

$riderName = trim((string)($panelUser['name'] ?? '')) ?: 'A rider';
notify_roles(
    $conn,
    ['admin', 'staff'],
    'delivery',
    'Delivery released',
    $riderName . ' released ' . $orderNumber . ' back to the pool',
    $orderNumber,
    null
);

api_json([
    'success'      => true,
    'message'      => 'Returned to the pool.',
    'order_number' => $orderNumber,
]);
