<?php
/**
 * POST — rider completes a delivery by entering the customer's 4-digit code:
 * Delivering → Completed.
 *
 * The code is the proof of handover. It is generated server-side, shown only on
 * the owning customer's tracking page, and never included in any rider-facing
 * payload. Comparison uses hash_equals(); after MAX_OTP_ATTEMPTS failures the
 * delivery locks and admin/staff must reassign it.
 */
require_once __DIR__ . '/_api_guard.php';

if (!api_is_post()) {
    api_json(['success' => false, 'message' => 'POST required']);
}

$input = api_input();
api_require_csrf($input);

// MAX_OTP_ATTEMPTS comes from includes/delivery_helpers.php — the dispatcher's
// unlock action needs the same number, so it lives in one place.

$orderNumber = trim((string)($input['order_number'] ?? ''));
$otpInput    = preg_replace('/\D/', '', (string)($input['otp' ] ?? ''));

if ($orderNumber === '') {
    api_json(['success' => false, 'message' => 'Missing order number.']);
}
if (strlen((string)$otpInput) !== 4) {
    api_json(['success' => false, 'message' => 'Enter the 4-digit code from the customer.']);
}

// Ownership + OTP read in one statement, scoped to THIS rider's session id.
$stmt = $conn->prepare("SELECT d.otp, d.otp_attempts, o.status
                        FROM deliveries d
                        JOIN orders o ON o.order_number = d.order_number
                        WHERE d.order_number = ? AND d.rider_id = ?
                        LIMIT 1");
if (!$stmt) {
    api_json(['success' => false, 'message' => 'Database error.']);
}
$stmt->bind_param('si', $orderNumber, $RIDER_ID);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    http_response_code(403);
    api_json(['success' => false, 'message' => 'This delivery is not assigned to you.']);
}
if ((string)$row['status'] !== 'Delivering') {
    api_json(['success' => false, 'message' => 'Mark the order as picked up first.']);
}

$attempts = (int)$row['otp_attempts'];
if ($attempts >= MAX_OTP_ATTEMPTS) {
    api_json([
        'success' => false,
        'locked'  => true,
        'message' => 'Too many wrong codes. Ask the restaurant to unlock this delivery.',
    ]);
}

if (!hash_equals((string)$row['otp'], (string)$otpInput)) {
    $bump = $conn->prepare("UPDATE deliveries SET otp_attempts = otp_attempts + 1 WHERE order_number = ? AND rider_id = ?");
    if ($bump) {
        $bump->bind_param('si', $orderNumber, $RIDER_ID);
        $bump->execute();
        $bump->close();
    }
    $left = max(0, MAX_OTP_ATTEMPTS - ($attempts + 1));
    api_json([
        'success'          => false,
        'message'          => $left > 0
            ? 'Wrong code. ' . $left . ' ' . ($left === 1 ? 'try' : 'tries') . ' left.'
            : 'Wrong code. This delivery is now locked — ask the restaurant to unlock it.',
        'attempts_left'    => $left,
        'locked'           => $left === 0,
    ]);
}

// Correct code → close the order out through the shared writer.
$result = apply_order_status($conn, $orderNumber, 'Completed');
if (!$result['success']) {
    api_json($result);
}

$fee = 0.0;
$stamp = $conn->prepare("UPDATE deliveries SET completed_at = NOW() WHERE order_number = ? AND rider_id = ?");
if ($stamp) {
    $stamp->bind_param('si', $orderNumber, $RIDER_ID);
    $stamp->execute();
    $stamp->close();
}
$feeQ = $conn->prepare("SELECT delivery_fee FROM deliveries WHERE order_number = ? AND rider_id = ? LIMIT 1");
if ($feeQ) {
    $feeQ->bind_param('si', $orderNumber, $RIDER_ID);
    $feeQ->execute();
    $fee = (float)($feeQ->get_result()->fetch_assoc()['delivery_fee'] ?? 0);
    $feeQ->close();
}

// Stop broadcasting this rider's position for the finished job.
$clr = $conn->prepare("UPDATE rider_locations SET order_number = NULL WHERE rider_id = ? AND order_number = ?");
if ($clr) {
    $clr->bind_param('is', $RIDER_ID, $orderNumber);
    $clr->execute();
    $clr->close();
}

$riderName = trim((string)($panelUser['name'] ?? '')) ?: 'A rider';
notify_roles(
    $conn,
    ['admin', 'staff'],
    'delivery',
    'Order delivered',
    $orderNumber . ' delivered by ' . $riderName,
    $orderNumber,
    null
);

api_json([
    'success'      => true,
    'message'      => 'Delivered — nice work!',
    'order_number' => $orderNumber,
    'status'       => 'Completed',
    'delivery_fee' => $fee,
]);
