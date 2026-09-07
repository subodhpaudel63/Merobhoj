<?php
/**
 * POST — the rider's browser pushes its latest GPS fix here.
 *
 * rider_id is taken from the session ($RIDER_ID), never from the request body,
 * so rider A can never publish a position for rider B. A fix is only accepted
 * while this rider actually has a delivery out (status Delivering), and the row
 * is upserted (one row per rider) rather than appended, so the table stays small.
 */
require_once __DIR__ . '/_api_guard.php';

if (!api_is_post()) {
    api_json(['success' => false, 'message' => 'POST required']);
}

$input = api_input();
api_require_csrf($input);

/* ------------------------------- validation ------------------------------- */
if (!isset($input['lat'], $input['lng']) || !is_numeric($input['lat']) || !is_numeric($input['lng'])) {
    api_json(['success' => false, 'message' => 'Invalid coordinates.']);
}
$lat = (float)$input['lat'];
$lng = (float)$input['lng'];
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    api_json(['success' => false, 'message' => 'Coordinates out of range.']);
}

$accuracy = (isset($input['accuracy']) && is_numeric($input['accuracy']))
    ? max(0, min(100000, (int)round((float)$input['accuracy'])))
    : null;
$heading = (isset($input['heading']) && is_numeric($input['heading']))
    ? max(0.0, min(360.0, (float)$input['heading']))
    : null;
$speed = (isset($input['speed_kmh']) && is_numeric($input['speed_kmh']))
    ? max(0.0, min(400.0, (float)$input['speed_kmh']))
    : null;

/* ------------------------- must own an active job ------------------------- */
// Only a rider who is actually out on a delivery may broadcast. The order number
// is resolved from the DB, so a spoofed one in the body is simply ignored.
$stmt = $conn->prepare("SELECT d.order_number
                        FROM deliveries d
                        JOIN orders o ON o.order_number = d.order_number
                        WHERE d.rider_id = ? AND o.status = 'Delivering'
                        ORDER BY d.picked_up_at DESC
                        LIMIT 1");
if (!$stmt) {
    api_json(['success' => false, 'message' => 'Database error.']);
}
$stmt->bind_param('i', $RIDER_ID);
$stmt->execute();
$res         = $stmt->get_result();
$active      = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$active) {
    http_response_code(403);
    api_json([
        'success'  => false,
        'inactive' => true,
        'message'  => 'No active delivery — location sharing is not needed right now.',
    ]);
}
$orderNumber = (string)$active['order_number'];

/* --------------------------------- upsert --------------------------------- */
$up = $conn->prepare("INSERT INTO rider_locations
                          (rider_id, lat, lng, accuracy_m, heading, speed_kmh, order_number)
                      VALUES (?, ?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE
                          lat = VALUES(lat), lng = VALUES(lng),
                          accuracy_m = VALUES(accuracy_m), heading = VALUES(heading),
                          speed_kmh = VALUES(speed_kmh), order_number = VALUES(order_number),
                          updated_at = CURRENT_TIMESTAMP");
if (!$up) {
    api_json(['success' => false, 'message' => 'Database error.']);
}
$up->bind_param('iddidds', $RIDER_ID, $lat, $lng, $accuracy, $heading, $speed, $orderNumber);
$ok = $up->execute();
$up->close();

api_json([
    'success'      => (bool)$ok,
    'order_number' => $orderNumber,
    'message'      => $ok ? 'Location updated.' : 'Could not save location.',
]);
