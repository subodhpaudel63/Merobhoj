<?php
/**
 * GET — delivery jobs for the logged-in rider.
 *
 *   ?scope=dashboard (default) → pool + mine + stats
 *   ?scope=pool                → unassigned jobs anyone may claim
 *   ?scope=mine                → my active jobs (awaiting pickup / on the way)
 *   ?scope=history             → my finished jobs (delivered / cancelled)
 *
 * The OTP is NEVER included in any rider-facing payload — the whole point of
 * the code is that only the customer can show it. The customer's phone number
 * is withheld until the job actually belongs to this rider.
 */
require_once __DIR__ . '/_api_guard.php';

// Provision delivery rows for any Delivery order that has reached Ready/Delivering.
ensure_delivery_rows($conn);

$scope = (string)($_GET['scope'] ?? 'dashboard');
if (!in_array($scope, ['dashboard', 'pool', 'mine', 'history'], true)) {
    $scope = 'dashboard';
}

$now = time();

/** Common projection — one row per order line item, grouped in PHP. */
const DELIVERY_SELECT = "SELECT o.order_number, o.status, o.order_type, o.full_name, o.email,
                                o.mobile, o.address, o.special_instructions,
                                o.menu_name, o.quantity, o.price,
                                o.payment_method, o.payment_status,
                                UNIX_TIMESTAMP(o.created_at) AS created_ts,
                                UNIX_TIMESTAMP(o.status_updated_at) AS status_ts,
                                d.rider_id, d.delivery_fee, d.dest_lat, d.dest_lng,
                                d.assigned_at, d.picked_up_at, d.completed_at
                         FROM orders o
                         JOIN deliveries d ON d.order_number = o.order_number
                         WHERE o.order_type = 'Delivery' ";

/**
 * Group flat order rows into one job per order_number.
 * $mine controls whether contact details are exposed.
 */
function delivery_group(mysqli_result $res, int $riderId, int $now): array
{
    $jobs = [];
    while ($row = $res->fetch_assoc()) {
        $key = (string)$row['order_number'];
        $rid = ($row['rider_id'] === null) ? null : (int)$row['rider_id'];

        if (!isset($jobs[$key])) {
            $isMine    = ($rid !== null && $rid === $riderId);
            $createdTs = (int)($row['created_ts'] ?? $now);
            $stageTs   = (int)($row['status_ts'] ?? 0) ?: $createdTs;
            $state     = delivery_state((string)$row['status'], $rid);

            $jobs[$key] = [
                'order_number'   => $key,
                'status'         => (string)$row['status'],
                'state'          => $state,
                'state_label'    => delivery_state_label($state),
                'is_mine'        => $isMine,
                'customer_name'  => ($row['full_name'] !== null && $row['full_name'] !== '')
                                    ? (string)$row['full_name'] : 'Customer',
                // Contact details only once the job is this rider's.
                'customer_phone' => $isMine ? (string)($row['mobile'] ?? '') : '',
                'address'        => (string)($row['address'] ?? ''),
                'instructions'   => '',
                'items'          => [],
                'order_total'    => 0,
                'delivery_fee'   => (float)$row['delivery_fee'],
                'payment_method' => (string)($row['payment_method'] ?? ''),
                'payment_status' => (string)($row['payment_status'] ?? ''),
                'dest_lat'       => $row['dest_lat'] !== null ? (float)$row['dest_lat'] : null,
                'dest_lng'       => $row['dest_lng'] !== null ? (float)$row['dest_lng'] : null,
                'assigned_at'    => $row['assigned_at'],
                'picked_up_at'   => $row['picked_up_at'],
                'completed_at'   => $row['completed_at'],
                'received_ts'    => $createdTs,
                'stage_ts'       => $stageTs,
                'age_seconds'    => max(0, $now - $createdTs),
                'stage_seconds'  => max(0, $now - $stageTs),
            ];
        }

        $jobs[$key]['items'][] = [
            'name'     => (string)$row['menu_name'],
            'quantity' => (int)$row['quantity'],
        ];
        $jobs[$key]['order_total'] += ((int)$row['price'] * (int)$row['quantity']);

        if ($jobs[$key]['instructions'] === '' && !empty($row['special_instructions'])) {
            $jobs[$key]['instructions'] = (string)$row['special_instructions'];
        }
    }
    return array_values($jobs);
}

/* ---------------------------------- pool ---------------------------------- */
function delivery_pool(mysqli $conn, int $riderId, int $now): array
{
    $sql = DELIVERY_SELECT . "AND d.rider_id IS NULL AND o.status = 'Ready'
                              ORDER BY o.created_at ASC, o.order_id ASC";
    $res = $conn->query($sql);
    return $res ? delivery_group($res, $riderId, $now) : [];
}

/* ---------------------------------- mine ---------------------------------- */
function delivery_mine(mysqli $conn, int $riderId, int $now): array
{
    $sql = DELIVERY_SELECT . "AND d.rider_id = ? AND o.status IN ('Ready','Delivering')
                              ORDER BY o.created_at ASC, o.order_id ASC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param('i', $riderId);
    $stmt->execute();
    $res  = $stmt->get_result();
    $jobs = $res ? delivery_group($res, $riderId, $now) : [];
    $stmt->close();
    return $jobs;
}

/* -------------------------------- history --------------------------------- */
function delivery_history(mysqli $conn, int $riderId, int $now, int $limit = 60): array
{
    $sql = DELIVERY_SELECT . "AND d.rider_id = ? AND o.status IN ('Completed','Cancelled')
                              ORDER BY d.completed_at DESC, o.order_id DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param('i', $riderId);
    $stmt->execute();
    $res  = $stmt->get_result();
    $jobs = $res ? delivery_group($res, $riderId, $now) : [];
    $stmt->close();
    return array_slice($jobs, 0, $limit);
}

/* --------------------------------- stats ---------------------------------- */
function delivery_stats(mysqli $conn, int $riderId): array
{
    $stats = ['pool' => 0, 'active' => 0, 'completed_today' => 0, 'earned_today' => 0.0];

    $p = $conn->query("SELECT COUNT(DISTINCT o.order_number) AS c
                       FROM orders o
                       JOIN deliveries d ON d.order_number = o.order_number
                       WHERE o.order_type = 'Delivery' AND o.status = 'Ready' AND d.rider_id IS NULL");
    if ($p) $stats['pool'] = (int)$p->fetch_assoc()['c'];

    $a = $conn->prepare("SELECT COUNT(DISTINCT o.order_number) AS c
                         FROM orders o
                         JOIN deliveries d ON d.order_number = o.order_number
                         WHERE d.rider_id = ? AND o.status IN ('Ready','Delivering')");
    if ($a) {
        $a->bind_param('i', $riderId);
        $a->execute();
        $stats['active'] = (int)($a->get_result()->fetch_assoc()['c'] ?? 0);
        $a->close();
    }

    $t = $conn->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(delivery_fee), 0) AS fee
                         FROM deliveries
                         WHERE rider_id = ? AND completed_at IS NOT NULL AND DATE(completed_at) = CURDATE()");
    if ($t) {
        $t->bind_param('i', $riderId);
        $t->execute();
        $row = $t->get_result()->fetch_assoc();
        $stats['completed_today'] = (int)($row['c'] ?? 0);
        $stats['earned_today']    = (float)($row['fee'] ?? 0);
        $t->close();
    }

    return $stats;
}

/* -------------------------------- dispatch -------------------------------- */
$payload = [
    'success'    => true,
    'scope'      => $scope,
    'server_now' => $now,
    'stats'      => delivery_stats($conn, $RIDER_ID),
];

if ($scope === 'dashboard') {
    $payload['pool'] = delivery_pool($conn, $RIDER_ID, $now);
    $payload['mine'] = delivery_mine($conn, $RIDER_ID, $now);
} elseif ($scope === 'pool') {
    $payload['deliveries'] = delivery_pool($conn, $RIDER_ID, $now);
} elseif ($scope === 'mine') {
    $payload['deliveries'] = delivery_mine($conn, $RIDER_ID, $now);
} else {
    $payload['deliveries'] = delivery_history($conn, $RIDER_ID, $now);
}

api_json($payload);
