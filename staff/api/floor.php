<?php
require_once __DIR__ . '/_api_guard.php';

// GET: restaurant_tables + per-table current status derived from overlap query in includes/get_available_tables.php
// (active booking Pending/Confirmed/Checked-in overlapping NOW -> occupied/reserved, else free)

$todayStr = date('Y-m-d');
$now = date('Y-m-d H:i:s');

$tablesRes = $conn->query("SELECT * FROM restaurant_tables ORDER BY id");
$tables = [];

if ($tablesRes) {
    while ($t = $tablesRes->fetch_assoc()) {
        $tableId = $t['id'];

        // Query active overlapping bookings
        $stmt = $conn->prepare("
            SELECT status, start_time, end_time, grace_end_at 
            FROM bookings 
            WHERE table_id = ? 
              AND DATE(booking_date) = ?
              AND status IN ('Pending', 'Confirmed', 'Checked-in')
              AND ? BETWEEN CONCAT(booking_date, ' ', start_time) AND IFNULL(grace_end_at, CONCAT(booking_date, ' ', end_time))
            LIMIT 1
        ");
        $stmt->bind_param("iss", $tableId, $todayStr, $now);
        $stmt->execute();
        $bRes = $stmt->get_result()->fetch_assoc();

        // Check for active Dine In orders for this table
        $tblName = $t['table_name'];
        $tIdStr = (string)$t['id'];
        preg_match('/\d+/', $tblName, $m);
        $numPart = $m[0] ?? '';

        $stmtOrd = $conn->prepare("
            SELECT COUNT(DISTINCT order_number) AS active_count
            FROM orders
            WHERE order_type = 'Dine In'
              AND status IN ('Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivering')
              AND (table_number = ? OR table_number = ? OR table_number = ?)
        ");
        $stmtOrd->bind_param("sss", $tblName, $tIdStr, $numPart);
        $stmtOrd->execute();
        $ordRes = $stmtOrd->get_result()->fetch_assoc();
        $activeOrdersCount = (int)($ordRes['active_count'] ?? 0);
        $stmtOrd->close();

        if ($activeOrdersCount > 0) {
            $status = 'occupied';
        } else if ($bRes) {
            if ($bRes['status'] === 'Checked-in') {
                $status = 'occupied';
            } else {
                $status = 'reserved';
            }
        } else {
            $status = 'free';
        }

        $t['current_status'] = $status;
        $t['active_orders_count'] = $activeOrdersCount;
        $tables[] = $t;
    }
}

echo json_encode(['success' => true, 'tables' => $tables]);
