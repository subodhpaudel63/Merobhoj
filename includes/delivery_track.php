<?php
/**
 * delivery_track.php — customer-facing live delivery feed.
 *
 * GET ?order_id=213
 *   → { ok, order_number, status, state, has_rider, rider:{name,phone,avatar},
 *       otp, fix:{lat,lng,accuracy_m,age_s}|null }
 *
 * The tracking key is the order_id from the customer's order record. The
 * endpoint still requires an authenticated customer session, but does not use
 * the email as part of the tracking lookup.
 *
 * The OTP is returned ONLY here, ONLY to the owning customer, and ONLY while the
 * order is Ready or Delivering. includes/orders_fetch.php is deliberately left
 * untouched so the code never leaks into the general orders feed, and no
 * rider-facing payload ever contains it.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/delivery_helpers.php';

header('Content-Type: application/json');

/** How old a GPS fix may be before we stop calling it live (seconds). */
const TRACK_FIX_MAX_AGE = 90;

$user = getUserFromCookie();
if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$orderId = filter_var($_GET['order_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if ($orderId === false || $orderId === null) {
    echo json_encode(['ok' => false, 'error' => 'Missing order id']);
    exit;
}

/* Make sure the delivery row (and therefore the handover code) exists, so the
   customer sees their code even if no rider or dispatcher has opened a page yet. */
ensure_delivery_rows($conn);

/* Ownership + current state in one query. The rider row and the last fix are
   LEFT JOINed, so an unassigned or GPS-less delivery still returns cleanly. */
$stmt = $conn->prepare(
    "SELECT o.order_id, o.order_number, o.status, o.order_type,
            d.rider_id, d.otp,
            u.name AS rider_name, u.phone AS rider_phone, u.user_img AS rider_img,
            rl.lat, rl.lng, rl.accuracy_m,
            TIMESTAMPDIFF(SECOND, rl.updated_at, NOW()) AS fix_age
     FROM orders o
     LEFT JOIN deliveries d       ON d.order_number = o.order_number
     LEFT JOIN users u            ON u.id = d.rider_id
     LEFT JOIN rider_locations rl ON rl.rider_id = d.rider_id
                                 AND rl.order_number = o.order_number
     WHERE o.order_id = ? AND o.user_id = ?
     LIMIT 1"
);
if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'Database error']);
    exit;
}
$userId = intval($user['id'] ?? 0);
if (!$userId) {
    echo json_encode(['ok' => false, 'error' => 'Invalid user session']);
    exit;
}
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    // Same answer for "does not exist" and "not yours" — no enumeration.
    echo json_encode(['ok' => false, 'error' => 'Order not found']);
    exit;
}

$status   = (string)$row['status'];
$riderId  = ($row['rider_id'] === null) ? null : (int)$row['rider_id'];
$hasRider = ($riderId !== null && $riderId > 0);
$isLive   = in_array($status, ['Ready', 'Delivering'], true);

/* Rider card — only while the order is actually out with someone. */
$rider = null;
if ($hasRider && $isLive) {
    $img = preg_replace('#^(?:\.\.?/)+#', '', (string)($row['rider_img'] ?? ''));
    $img = ltrim((string)$img, '/');
    $rider = [
        'name'   => (string)($row['rider_name'] ?: 'Your rider'),
        'phone'  => (string)($row['rider_phone'] ?? ''),
        'avatar' => ($img !== '' && file_exists(__DIR__ . '/../' . $img)) ? '../' . $img : '',
    ];
}

/* Handover code — the customer shows it, the rider types it. */
$otp = ($isLive && $row['otp'] !== null) ? (string)$row['otp'] : null;

/* Last known position, only if it is fresh enough to plot. */
$fix = null;
if ($status === 'Delivering' && $row['lat'] !== null && $row['fix_age'] !== null) {
    $age = (int)$row['fix_age'];
    if ($age >= 0 && $age <= TRACK_FIX_MAX_AGE) {
        $fix = [
            'lat'        => (float)$row['lat'],
            'lng'        => (float)$row['lng'],
            'accuracy_m' => $row['accuracy_m'] !== null ? (int)$row['accuracy_m'] : null,
            'age_s'      => $age,
        ];
    }
}

echo json_encode([
    'ok'           => true,
    'order_id'     => (int)$row['order_id'],
    'order_number' => (string)$row['order_number'],
    'status'       => $status,
    'order_type'   => (string)$row['order_type'],
    'has_rider'    => ($rider !== null),
    'rider'        => $rider,
    'otp'          => $otp,
    'fix'          => $fix,
]);
