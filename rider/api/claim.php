<?php
/**
 * POST — claim an unassigned delivery from the pool.
 *
 * The claim is a single conditional UPDATE (… WHERE order_number = ? AND
 * rider_id IS NULL) followed by an affected_rows check, so if two riders tap
 * Claim at the same instant exactly one of them wins.
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

ensure_delivery_rows($conn);

// The job must still be a Delivery order sitting at Ready.
$delivery = delivery_find($conn, $orderNumber);
if (!$delivery) {
    api_json(['success' => false, 'message' => 'Delivery not found.']);
}
if ((string)$delivery['order_type'] !== 'Delivery') {
    api_json(['success' => false, 'message' => 'That order is not a delivery.']);
}
if ((string)$delivery['status'] !== 'Ready') {
    api_json(['success' => false, 'message' => 'This order is no longer available to claim.']);
}
if ($delivery['rider_id'] !== null) {
    api_json([
        'success' => false,
        'message' => ((int)$delivery['rider_id'] === $RIDER_ID)
            ? 'You have already claimed this delivery.'
            : 'Another rider just claimed this delivery.',
    ]);
}

// Atomic claim.
$stmt = $conn->prepare("UPDATE deliveries SET rider_id = ?, assigned_at = NOW()
                        WHERE order_number = ? AND rider_id IS NULL");
if (!$stmt) {
    api_json(['success' => false, 'message' => 'Database error.']);
}
$stmt->bind_param('is', $RIDER_ID, $orderNumber);
$stmt->execute();
$won = $stmt->affected_rows === 1;
$stmt->close();

if (!$won) {
    api_json(['success' => false, 'message' => 'Another rider just claimed this delivery.']);
}

$riderName = trim((string)($panelUser['name'] ?? '')) ?: 'A rider';
notify_roles(
    $conn,
    ['admin', 'staff'],
    'delivery',
    'Delivery claimed',
    $riderName . ' claimed ' . $orderNumber,
    $orderNumber,
    null
);

api_json([
    'success'      => true,
    'message'      => 'Delivery claimed — collect it from the counter.',
    'order_number' => $orderNumber,
]);
