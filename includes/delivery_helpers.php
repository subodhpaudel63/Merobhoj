<?php
/**
 * delivery_helpers.php — shared delivery logic for the rider panel, the
 * admin/staff dispatcher and the customer tracker.
 *
 * Design principle: `orders.status` is the ONE source of truth. The `deliveries`
 * table stores only assignment, OTP, fee and timestamps — never a status — so
 * the three surfaces can never disagree about where an order is.
 *
 * Requires $conn (includes/db.php) and includes/order_validation.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/order_validation.php';

/** Flat per-delivery fee credited to the rider on completion (NPR). */
const DELIVERY_FEE_DEFAULT = 50.00;

/** Wrong handover codes allowed before the delivery locks. Shared, because the
 *  rider endpoint enforces it and the dispatcher's `unlock` action reverses it. */
const MAX_OTP_ATTEMPTS = 5;

/** Statuses at which a Delivery order needs a delivery row to exist. */
const DELIVERY_LIVE_STATUSES = ['Ready', 'Delivering'];

/** Rider-facing states derived from orders.status + deliveries.rider_id. */
const DELIVERY_STATE_POOL      = 'unassigned';
const DELIVERY_STATE_ASSIGNED  = 'assigned';
const DELIVERY_STATE_PICKEDUP  = 'pickedup';
const DELIVERY_STATE_DELIVERED = 'completed';
const DELIVERY_STATE_CANCELLED = 'cancelled';
/** Sent out from the floor without a rider — only reachable for legacy rows and
 *  for a "Send Out" done straight from the orders queue. Dispatcher-only: the
 *  rider pool never lists these (it is Ready-and-unassigned by definition). */
const DELIVERY_STATE_NORIDER   = 'norider';

/**
 * A fresh 4-digit handover code, zero-padded (e.g. "0473").
 * Uses random_int() (CSPRNG) — the code is the only proof of handover.
 */
function gen_delivery_otp(): string
{
    return str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Lazily create a `deliveries` row for every Delivery order that has reached a
 * live delivery stage but has no row yet.
 *
 * Doing this on read (rather than hooking every status endpoint) is what keeps
 * Phase 3 additive: the chef's endpoints, the staff endpoints and the legacy
 * admin endpoint all stay untouched. INSERT IGNORE + the UNIQUE order_number
 * key make it safe to call on every request and from concurrent requests.
 */
function ensure_delivery_rows(mysqli $conn): void
{
    $sql = "SELECT DISTINCT o.order_number
            FROM orders o
            LEFT JOIN deliveries d ON d.order_number = o.order_number
            WHERE o.order_type = 'Delivery'
              AND o.status IN ('Ready','Delivering')
              AND o.order_number IS NOT NULL AND o.order_number <> ''
              AND d.id IS NULL";
    $res = $conn->query($sql);
    if (!$res || $res->num_rows === 0) {
        return;
    }

    $ins = $conn->prepare("INSERT IGNORE INTO deliveries (order_number, otp, delivery_fee) VALUES (?, ?, ?)");
    if (!$ins) {
        return;
    }
    $fee = DELIVERY_FEE_DEFAULT;
    while ($row = $res->fetch_assoc()) {
        $orderNumber = (string)$row['order_number'];
        $otp         = gen_delivery_otp();
        $ins->bind_param('ssd', $orderNumber, $otp, $fee);
        $ins->execute();
    }
    $ins->close();
}

/**
 * The single derivation of a rider-facing state. Never stored, always computed.
 */
function delivery_state(string $orderStatus, ?int $riderId): string
{
    if ($orderStatus === 'Cancelled') return DELIVERY_STATE_CANCELLED;
    if ($orderStatus === 'Completed') return DELIVERY_STATE_DELIVERED;
    if ($orderStatus === 'Delivering') {
        return ($riderId !== null && $riderId > 0) ? DELIVERY_STATE_PICKEDUP : DELIVERY_STATE_NORIDER;
    }
    // Ready (or anything earlier): assigned to someone, or still in the pool.
    return ($riderId !== null && $riderId > 0) ? DELIVERY_STATE_ASSIGNED : DELIVERY_STATE_POOL;
}

/** Human label for a derived state (used in tables and pills). */
function delivery_state_label(string $state): string
{
    switch ($state) {
        case DELIVERY_STATE_POOL:      return 'Unassigned';
        case DELIVERY_STATE_ASSIGNED:  return 'Awaiting pickup';
        case DELIVERY_STATE_PICKEDUP:  return 'On the way';
        case DELIVERY_STATE_NORIDER:   return 'Sent out — no rider';
        case DELIVERY_STATE_DELIVERED: return 'Delivered';
        case DELIVERY_STATE_CANCELLED: return 'Cancelled';
    }
    return ucfirst($state);
}

/**
 * The shared order-status writer, lifted from admin/update_order_status_ajax.php
 * so the rider APIs and the dispatcher share one implementation:
 *   lookup → validate_order_transition() → UPDATE every row of the order
 *   → upsert order_status_history.
 *
 * @return array{success:bool,message:string,order_number?:string,status?:string,order_type?:string}
 */
function apply_order_status(mysqli $conn, string $orderNumber, string $newStatus): array
{
    if ($orderNumber === '') {
        return ['success' => false, 'message' => 'Missing order number.'];
    }
    if (!in_array($newStatus, ORDER_STATUSES, true)) {
        return ['success' => false, 'message' => 'Invalid status.'];
    }

    $lookup = $conn->prepare("SELECT order_id, order_number, status, order_type FROM orders WHERE order_number = ? LIMIT 1");
    if (!$lookup) {
        return ['success' => false, 'message' => 'Database error.'];
    }
    $lookup->bind_param('s', $orderNumber);
    $lookup->execute();
    $r       = $lookup->get_result();
    $current = $r ? $r->fetch_assoc() : null;
    $lookup->close();

    if (!$current) {
        return ['success' => false, 'message' => 'Order not found.'];
    }

    $orderType = (string)($current['order_type'] ?: 'Delivery');
    $validation = validate_order_transition($orderType, (string)$current['status'], $newStatus, true);
    if (!$validation['valid']) {
        return ['success' => false, 'message' => (string)$validation['error']];
    }

    $upd = $conn->prepare("UPDATE orders SET status = ?, status_updated_at = NOW() WHERE order_number = ?");
    if (!$upd) {
        return ['success' => false, 'message' => 'Database error.'];
    }
    $upd->bind_param('ss', $newStatus, $orderNumber);
    if (!$upd->execute()) {
        $upd->close();
        return ['success' => false, 'message' => 'Could not update the order.'];
    }
    $upd->close();

    // Permanent per-status timestamp the customer stepper reads.
    $hist = $conn->prepare("INSERT INTO order_status_history (order_number, status) VALUES (?, ?) ON DUPLICATE KEY UPDATE changed_at = NOW()");
    if ($hist) {
        $hist->bind_param('ss', $orderNumber, $newStatus);
        $hist->execute();
        $hist->close();
    }

    return [
        'success'      => true,
        'message'      => 'Order moved to ' . $newStatus,
        'order_number' => $orderNumber,
        'status'       => $newStatus,
        'order_type'   => $orderType,
    ];
}

/**
 * Fan a notification out to one or more panel roles (the same
 * `role_notifications` table the panel bell already polls).
 */
function notify_roles(
    mysqli $conn,
    array $roles,
    string $type,
    string $title,
    string $message = '',
    ?string $resourceId = null,
    ?string $url = null
): void {
    $stmt = $conn->prepare("INSERT INTO role_notifications (role, type, title, message, resource_id, url) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return;
    }
    $type    = substr($type, 0, 30);
    $title   = substr($title, 0, 150);
    $message = substr($message, 0, 255);
    foreach ($roles as $role) {
        $role = substr((string)$role, 0, 20);
        if ($role === '') continue;
        $stmt->bind_param('ssssss', $role, $type, $title, $message, $resourceId, $url);
        $stmt->execute();
    }
    $stmt->close();
}

/**
 * Load one delivery joined to its order, or null.
 * Callers that need ownership must still bind rider_id themselves.
 */
function delivery_find(mysqli $conn, string $orderNumber): ?array
{
    $stmt = $conn->prepare("SELECT d.id, d.order_number, d.rider_id, d.otp_attempts, d.delivery_fee,
                                   d.assigned_at, d.picked_up_at, d.completed_at,
                                   o.status, o.order_type
                            FROM deliveries d
                            JOIN orders o ON o.order_number = d.order_number
                            WHERE d.order_number = ?
                            LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $orderNumber);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}
