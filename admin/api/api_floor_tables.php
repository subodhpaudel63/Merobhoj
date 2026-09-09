<?php
/**
 * Admin Floor Plan Table Status API
 * Returns all restaurant_tables with live current_status (free/reserved/occupied)
 * and active_orders_count — same shape as staff/api/floor.php but auth'd as admin.
 */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/role_check.php';
require_role($conn, 'admin');

header('Content-Type: application/json');

$todayStr = date('Y-m-d');
$now      = date('Y-m-d H:i:s');

$tablesRes = $conn->query("SELECT * FROM restaurant_tables ORDER BY id");
$tables    = [];

if ($tablesRes) {
    $stmtBk = $conn->prepare("
        SELECT status, start_time, end_time, grace_end_at
        FROM bookings
        WHERE table_id = ?
          AND DATE(booking_date) = ?
          AND status IN ('Pending', 'Confirmed', 'Checked-in')
          AND ? BETWEEN CONCAT(booking_date, ' ', start_time)
                    AND IFNULL(grace_end_at, CONCAT(booking_date, ' ', end_time))
        LIMIT 1
    ");

    $stmtOrd = $conn->prepare("
        SELECT COUNT(DISTINCT order_number) AS active_count
        FROM orders
        WHERE order_type = 'Dine In'
          AND status IN ('Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivering')
          AND (table_number = ? OR table_number = ? OR table_number = ?)
    ");

    while ($t = $tablesRes->fetch_assoc()) {
        $tableId = (int)$t['id'];
        $bRes    = null;

        if ($stmtBk) {
            $stmtBk->bind_param("iss", $tableId, $todayStr, $now);
            $stmtBk->execute();
            $bRes = $stmtBk->get_result()->fetch_assoc();
        }

        $tblName = $t['table_name'];
        $tIdStr  = (string)$t['id'];
        preg_match('/\d+/', $tblName, $m);
        $numPart = $m[0] ?? '';

        $activeOrdersCount = 0;
        if ($stmtOrd) {
            $stmtOrd->bind_param("sss", $tblName, $tIdStr, $numPart);
            $stmtOrd->execute();
            $ordRes            = $stmtOrd->get_result()->fetch_assoc();
            $activeOrdersCount = (int)($ordRes['active_count'] ?? 0);
        }

        if ($activeOrdersCount > 0) {
            $status = 'occupied';
        } elseif ($bRes) {
            $status = $bRes['status'] === 'Checked-in' ? 'occupied' : 'reserved';
        } else {
            $status = 'free';
        }

        $t['current_status']      = $status;
        $t['active_orders_count'] = $activeOrdersCount;
        $tables[]                 = $t;
    }

    if ($stmtBk)  $stmtBk->close();
    if ($stmtOrd) $stmtOrd->close();
}

echo json_encode(['success' => true, 'tables' => $tables]);
