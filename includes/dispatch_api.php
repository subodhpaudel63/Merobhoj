<?php
/**
 * dispatch_api.php — shared delivery dispatcher for admin / manager / staff.
 *
 * One controller behind both admin/deliveries.php and staff/deliveries.php so
 * the two dispatch screens can never drift apart.
 *
 *   GET  ?action=list                          → pool + active + completed today + riders
 *   POST {action:'assign',   order_number, rider_id}
 *   POST {action:'unassign', order_number}
 *
 * Assignment never touches orders.status — the rider drives that through
 * pickup/complete. This endpoint only decides WHO carries the order.
 *
 * NOTE for callers: this path has no "/api/" segment, so require_role()'s API
 * detection relies on the X-Requested-With: XMLHttpRequest header. Send it, or
 * an unauthenticated request gets an HTML redirect instead of 401 JSON.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/role_check.php';
require_once __DIR__ . '/delivery_helpers.php';

// Front-of-house + management only (admin is the universal override).
$panelUser = require_role($conn, ['admin', 'manager', 'staff']);

/** Send JSON and stop. */
function dispatch_json(array $payload): void
{
    echo json_encode($payload);
    exit;
}

/** Merge JSON body + form POST. */
function dispatch_input(): array
{
    $data = $_POST;
    $raw  = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }
    return $data;
}

/** 403 unless a valid panel CSRF token is present. */
function dispatch_require_csrf(array $input): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf'] ?? null);
    if (!verify_panel_csrf(is_string($token) ? $token : null)) {
        http_response_code(403);
        dispatch_json(['success' => false, 'message' => 'Invalid or missing CSRF token.']);
    }
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

/* ================================ MUTATIONS ================================ */
if ($isPost) {
    $input = dispatch_input();
    dispatch_require_csrf($input);

    $action      = trim((string)($input['action'] ?? ''));
    $orderNumber = trim((string)($input['order_number'] ?? ''));

    if ($orderNumber === '') {
        dispatch_json(['success' => false, 'message' => 'Missing order number.']);
    }

    ensure_delivery_rows($conn);

    $delivery = delivery_find($conn, $orderNumber);
    if (!$delivery) {
        dispatch_json(['success' => false, 'message' => 'Delivery not found.']);
    }
    if ((string)$delivery['order_type'] !== 'Delivery') {
        dispatch_json(['success' => false, 'message' => 'That order is not a delivery.']);
    }
    if (!in_array((string)$delivery['status'], DELIVERY_LIVE_STATUSES, true)) {
        dispatch_json(['success' => false, 'message' => 'This delivery is already closed.']);
    }

    /* ---- assign / reassign ---- */
    if ($action === 'assign') {
        $riderId = (int)($input['rider_id'] ?? 0);
        if ($riderId <= 0) {
            dispatch_json(['success' => false, 'message' => 'Choose a rider.']);
        }

        // The target must really be a rider account.
        $chk = $conn->prepare("SELECT name FROM users WHERE id = ? AND user_type = 'rider' LIMIT 1");
        if (!$chk) dispatch_json(['success' => false, 'message' => 'Database error.']);
        $chk->bind_param('i', $riderId);
        $chk->execute();
        $rider = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$rider) {
            dispatch_json(['success' => false, 'message' => 'That user is not a rider.']);
        }
        // A rider already carrying the food keeps it. But an order that reached
        // Delivering with NO rider (legacy rows, or a manual "Send Out" from the
        // orders queue) can still be handed to someone, otherwise it could never
        // be tracked or closed with the handover code.
        if ((string)$delivery['status'] === 'Delivering' && $delivery['rider_id'] !== null) {
            dispatch_json(['success' => false, 'message' => 'This order is already on the way — it cannot be reassigned mid-trip.']);
        }

        // Reassigning also clears any OTP lockout the previous rider caused.
        $upd = $conn->prepare("UPDATE deliveries
                               SET rider_id = ?, assigned_at = NOW(), otp_attempts = 0
                               WHERE order_number = ?");
        if (!$upd) dispatch_json(['success' => false, 'message' => 'Database error.']);
        $upd->bind_param('is', $riderId, $orderNumber);
        $ok = $upd->execute();
        $upd->close();

        if (!$ok) {
            dispatch_json(['success' => false, 'message' => 'Could not assign this delivery.']);
        }

        notify_roles(
            $conn,
            ['rider'],
            'delivery',
            'New delivery assigned',
            $orderNumber . ' was assigned to ' . (string)$rider['name'],
            $orderNumber,
            '/Merobhoj/rider/dashboard.php'
        );

        dispatch_json([
            'success'      => true,
            'message'      => 'Assigned to ' . (string)$rider['name'] . '.',
            'order_number' => $orderNumber,
        ]);
    }

    /* ---- unassign (back to the pool) ---- */
    if ($action === 'unassign') {
        if ($delivery['rider_id'] === null) {
            dispatch_json(['success' => false, 'message' => 'This delivery is already in the pool.']);
        }
        if ((string)$delivery['status'] === 'Delivering') {
            dispatch_json(['success' => false, 'message' => 'This order is already on the way — it cannot be unassigned.']);
        }

        $upd = $conn->prepare("UPDATE deliveries
                               SET rider_id = NULL, assigned_at = NULL, otp_attempts = 0
                               WHERE order_number = ?");
        if (!$upd) dispatch_json(['success' => false, 'message' => 'Database error.']);
        $upd->bind_param('s', $orderNumber);
        $ok = $upd->execute();
        $upd->close();

        dispatch_json([
            'success'      => (bool)$ok,
            'message'      => $ok ? 'Returned to the pool.' : 'Could not unassign this delivery.',
            'order_number' => $orderNumber,
        ]);
    }

    /* ---- unlock a code-locked handover ---- */
    if ($action === 'unlock') {
        // Five wrong codes lock a delivery. Reassignment cannot rescue one that is
        // already Delivering (the food is with the rider), so this is the only way
        // back — a deliberate, staff-side control rather than an automatic reset.
        if ((int)$delivery['otp_attempts'] < MAX_OTP_ATTEMPTS) {
            dispatch_json(['success' => false, 'message' => 'This delivery is not locked.']);
        }

        $upd = $conn->prepare("UPDATE deliveries SET otp_attempts = 0 WHERE order_number = ?");
        if (!$upd) dispatch_json(['success' => false, 'message' => 'Database error.']);
        $upd->bind_param('s', $orderNumber);
        $ok = $upd->execute();
        $upd->close();

        if ($ok && $delivery['rider_id'] !== null) {
            notify_roles(
                $conn,
                ['rider'],
                'delivery',
                'Handover code unlocked',
                $orderNumber . ' can be completed again — re-check the code with the customer.',
                $orderNumber,
                '/Merobhoj/rider/dashboard.php'
            );
        }

        dispatch_json([
            'success'      => (bool)$ok,
            'message'      => $ok ? 'Handover code unlocked.' : 'Could not unlock this delivery.',
            'order_number' => $orderNumber,
        ]);
    }

    dispatch_json(['success' => false, 'message' => 'Unknown action.']);
}

/* ================================== LIST =================================== */
ensure_delivery_rows($conn);

$now = time();

/**
 * All live deliveries (Ready + Delivering), one row per order, with the rider
 * and the age of that rider's last GPS fix.
 */
$sql = "SELECT o.order_number, o.status, o.full_name, o.mobile, o.address,
               UNIX_TIMESTAMP(o.created_at)        AS created_ts,
               UNIX_TIMESTAMP(o.status_updated_at) AS status_ts,
               SUM(o.price * o.quantity)           AS order_total,
               d.rider_id, d.delivery_fee, d.otp_attempts,
               d.assigned_at, d.picked_up_at, d.completed_at,
               u.name AS rider_name, u.phone AS rider_phone,
               TIMESTAMPDIFF(SECOND, rl.updated_at, NOW()) AS fix_age
        FROM orders o
        JOIN deliveries d      ON d.order_number = o.order_number
        LEFT JOIN users u      ON u.id = d.rider_id
        LEFT JOIN rider_locations rl ON rl.rider_id = d.rider_id
        WHERE o.order_type = 'Delivery' AND o.status IN ('Ready','Delivering')
        GROUP BY o.order_number
        ORDER BY o.created_at ASC";

$pool = [];
$active = [];
$res = $conn->query($sql);
while ($res && ($row = $res->fetch_assoc())) {
    $riderId = ($row['rider_id'] === null) ? null : (int)$row['rider_id'];
    $state   = delivery_state((string)$row['status'], $riderId);

    $job = [
        'order_number'  => (string)$row['order_number'],
        'status'        => (string)$row['status'],
        'state'         => $state,
        'state_label'   => delivery_state_label($state),
        'customer_name' => (string)($row['full_name'] ?: 'Customer'),
        'customer_phone'=> (string)($row['mobile'] ?? ''),
        'address'       => (string)($row['address'] ?? ''),
        'order_total'   => (float)$row['order_total'],
        'delivery_fee'  => (float)$row['delivery_fee'],
        'otp_attempts'  => (int)$row['otp_attempts'],
        'rider_id'      => $riderId,
        'rider_name'    => $row['rider_name'] !== null ? (string)$row['rider_name'] : '',
        'rider_phone'   => $row['rider_phone'] !== null ? (string)$row['rider_phone'] : '',
        'fix_age'       => ($riderId !== null && $row['fix_age'] !== null) ? (int)$row['fix_age'] : null,
        'assigned_at'   => $row['assigned_at'],
        'picked_up_at'  => $row['picked_up_at'],
        'age_seconds'   => max(0, $now - (int)($row['created_ts'] ?? $now)),
        'stage_seconds' => max(0, $now - (int)(($row['status_ts'] ?: $row['created_ts']) ?? $now)),
    ];

    if ($riderId === null) $pool[] = $job;
    else                   $active[] = $job;
}

/* Completed today */
$completed = [];
$cres = $conn->query("SELECT d.order_number, d.delivery_fee, d.completed_at,
                             MAX(o.full_name) AS full_name, MAX(o.address) AS address,
                             SUM(o.price * o.quantity) AS order_total,
                             u.name AS rider_name
                      FROM deliveries d
                      JOIN orders o ON o.order_number = d.order_number
                      LEFT JOIN users u ON u.id = d.rider_id
                      WHERE d.completed_at IS NOT NULL AND DATE(d.completed_at) = CURDATE()
                      GROUP BY d.order_number, d.delivery_fee, d.completed_at, u.name
                      ORDER BY d.completed_at DESC");
while ($cres && ($row = $cres->fetch_assoc())) {
    $completed[] = [
        'order_number' => (string)$row['order_number'],
        'customer_name'=> (string)($row['full_name'] ?: 'Customer'),
        'address'      => (string)($row['address'] ?? ''),
        'rider_name'   => $row['rider_name'] !== null ? (string)$row['rider_name'] : '—',
        'order_total'  => (float)$row['order_total'],
        'delivery_fee' => (float)$row['delivery_fee'],
        'completed_at' => $row['completed_at'],
    ];
}

/* Rider roster for the assign dropdown, with their current load. */
$riders = [];
$rres = $conn->query("SELECT u.id, u.name, u.phone,
                             (SELECT COUNT(DISTINCT d.order_number)
                              FROM deliveries d
                              JOIN orders o ON o.order_number = d.order_number
                              WHERE d.rider_id = u.id AND o.status IN ('Ready','Delivering')) AS active_jobs
                      FROM users u
                      WHERE u.user_type = 'rider'
                      ORDER BY u.name ASC");
while ($rres && ($row = $rres->fetch_assoc())) {
    $riders[] = [
        'id'          => (int)$row['id'],
        'name'        => (string)$row['name'],
        'phone'       => (string)($row['phone'] ?? ''),
        'active_jobs' => (int)($row['active_jobs'] ?? 0),
    ];
}

dispatch_json([
    'success'    => true,
    'server_now' => $now,
    'pool'       => $pool,
    'active'     => $active,
    'completed'  => $completed,
    'riders'     => $riders,
    'stats'      => [
        'pool'            => count($pool),
        'active'          => count($active),
        'completed_today' => count($completed),
        'riders'          => count($riders),
    ],
]);
