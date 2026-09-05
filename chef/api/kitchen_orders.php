<?php
/**
 * GET — active kitchen tickets grouped by order_number.
 * Returns Pending / Confirmed / Preparing / Ready orders with items, type,
 * table, instructions, age (server-computed seconds) and derived priority.
 */
require_once __DIR__ . '/_api_guard.php';

const KITCHEN_STATUSES = ['Pending', 'Confirmed', 'Preparing', 'Ready'];
const DELAY_THRESHOLD_SECONDS = 20 * 60; // 20 min at current stage → escalate

$sql = "SELECT order_id, order_number, menu_name, quantity, order_type, table_number,
               special_instructions, full_name, email, status, created_at,
               UNIX_TIMESTAMP(status_updated_at) AS status_ts,
               UNIX_TIMESTAMP(created_at) AS created_ts
        FROM orders
        WHERE status IN ('Pending','Confirmed','Preparing','Ready')
        ORDER BY created_at ASC, order_id ASC";

$res = $conn->query($sql);
if (!$res) {
    api_json(['success' => false, 'message' => 'Query failed', 'orders' => []]);
}

$now    = time();
$groups = [];

while ($row = $res->fetch_assoc()) {
    $key = ($row['order_number'] !== null && $row['order_number'] !== '')
        ? $row['order_number']
        : 'ORD-' . str_pad((string)$row['order_id'], 4, '0', STR_PAD_LEFT);

    if (!isset($groups[$key])) {
        $createdTs = (int)($row['created_ts'] ?? $now);
        $stageTs   = (int)($row['status_ts'] ?? 0) ?: $createdTs; // when this stage began
        $groups[$key] = [
            'order_number'   => $key,
            'status'         => $row['status'],
            'order_type'     => $row['order_type'] ?: 'Delivery',
            'table_number'   => $row['table_number'],
            'customer_name'  => $row['full_name'] ?: ($row['email'] ?: 'Guest'),
            'instructions'   => '',
            'items'          => [],
            'received_ts'    => $createdTs,
            'stage_ts'       => $stageTs,
            'age_seconds'    => max(0, $now - $createdTs),
            'stage_seconds'  => max(0, $now - $stageTs),
        ];
    }

    $groups[$key]['items'][] = [
        'name'     => $row['menu_name'],
        'quantity' => (int)$row['quantity'],
    ];

    // First non-empty special instruction wins for the ticket.
    if ($groups[$key]['instructions'] === '' && !empty($row['special_instructions'])) {
        $groups[$key]['instructions'] = $row['special_instructions'];
    }
}

// Derive priority: late at current stage, or a dine-in guest waiting.
$orders = [];
foreach ($groups as $g) {
    $g['is_delayed'] = $g['stage_seconds'] >= DELAY_THRESHOLD_SECONDS;
    $g['priority']   = ($g['is_delayed'] || $g['order_type'] === 'Dine In') ? 'high' : 'normal';
    $orders[] = $g;
}

api_json([
    'success'    => true,
    'server_now' => $now,
    'orders'     => $orders,
]);
