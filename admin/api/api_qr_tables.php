<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/role_check.php';
require_role($conn, 'admin');

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'list') {
        $stmt = $conn->prepare("SELECT id, table_name, capacity, qr_token FROM restaurant_tables ORDER BY id ASC");
        $stmt->execute();
        $result = $stmt->get_result();
        $tables = [];
        while ($row = $result->fetch_assoc()) {
            $tables[] = $row;
        }
        echo json_encode(['success' => true, 'tables' => $tables]);
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $postAction = $data['action'] ?? '';

    if ($postAction === 'regenerate') {
        $table_id = $data['table_id'] ?? 0;
        if ($table_id) {
            $newToken = bin2hex(random_bytes(16)); // 32 char hex
            $stmt = $conn->prepare("UPDATE restaurant_tables SET qr_token = ? WHERE id = ?");
            $stmt->bind_param("si", $newToken, $table_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'token' => $newToken]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid table ID']);
        }
        exit;
    }

    if ($postAction === 'create') {
        $tableName = trim((string)($data['table_name'] ?? ''));
        $capacity = (int)($data['capacity'] ?? 4);

        if (!$tableName || $capacity <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please fill in table name and capacity']);
            exit;
        }

        $newToken = bin2hex(random_bytes(16));
        $stmt = $conn->prepare("INSERT INTO restaurant_tables (table_name, capacity, qr_token) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $tableName, $capacity, $newToken);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error or table name exists']);
        }
        $stmt->close();
        exit;
    }

    if ($postAction === 'delete') {
        $table_id = (int)($data['table_id'] ?? 0);
        if (!$table_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid table ID']);
            exit;
        }

        // Check if table has active bookings
        $stmtB = $conn->prepare("
            SELECT COUNT(*) AS c 
            FROM bookings 
            WHERE table_id = ? AND status IN ('Pending', 'Confirmed', 'Checked-in')
        ");
        $stmtB->bind_param("i", $table_id);
        $stmtB->execute();
        $activeB = (int)$stmtB->get_result()->fetch_assoc()['c'];
        $stmtB->close();

        if ($activeB > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete table with active or pending reservations']);
            exit;
        }

        // Fetch table details to check orders
        $stmtT = $conn->prepare("SELECT table_name FROM restaurant_tables WHERE id = ?");
        $stmtT->bind_param("i", $table_id);
        $stmtT->execute();
        $tRow = $stmtT->get_result()->fetch_assoc();
        $stmtT->close();

        if ($tRow) {
            $tName = $tRow['table_name'];
            $tIdStr = (string)$table_id;
            preg_match('/\d+/', $tName, $m);
            $numPart = $m[0] ?? '';

            $stmtO = $conn->prepare("
                SELECT COUNT(*) AS c 
                FROM orders 
                WHERE order_type = 'Dine In' 
                  AND status IN ('Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivering')
                  AND (table_number = ? OR table_number = ? OR table_number = ?)
            ");
            $stmtO->bind_param("sss", $tName, $tIdStr, $numPart);
            $stmtO->execute();
            $activeO = (int)$stmtO->get_result()->fetch_assoc()['c'];
            $stmtO->close();

            if ($activeO > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete table with active orders']);
                exit;
            }
        }

        // Proceed to delete table
        $delStmt = $conn->prepare("DELETE FROM restaurant_tables WHERE id = ?");
        $delStmt->bind_param("i", $table_id);
        if ($delStmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete table']);
        }
        $delStmt->close();
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
