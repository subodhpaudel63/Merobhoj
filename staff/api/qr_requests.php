<?php
require_once __DIR__ . '/_api_guard.php';

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'list') {
        $stmt = $conn->prepare("SELECT r.*, t.table_name FROM qr_requests r JOIN restaurant_tables t ON r.table_id = t.id ORDER BY r.created_at DESC LIMIT 50");
        $stmt->execute();
        $result = $stmt->get_result();
        $requests = [];
        while ($row = $result->fetch_assoc()) {
            $row['items'] = json_decode($row['items_json'], true);
            $requests[] = $row;
        }
        echo json_encode(['success' => true, 'requests' => $requests]);
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $postAction = $data['action'] ?? '';
    $requestId = $data['request_id'] ?? 0;

    if (!$requestId) {
        echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
        exit;
    }

    if ($postAction === 'approve') {
        $stmt = $conn->prepare("SELECT * FROM qr_requests WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $req = $stmt->get_result()->fetch_assoc();

        if ($req) {
            $orderCode = 'ORD-' . date('Ymd') . '-' . rand(1000, 9999);
            $items = json_decode($req['items_json'], true);
            
            $conn->begin_transaction();
            try {
                $upd = $conn->prepare("UPDATE qr_requests SET status = 'approved', order_code = ? WHERE id = ?");
                $upd->bind_param("si", $orderCode, $requestId);
                $upd->execute();

                $stmtInsert = $conn->prepare("INSERT INTO orders (order_number, menu_id, email, full_name, order_type, table_number, special_instructions, payment_method, menu_name, quantity, price, total_price, mobile, address, status) VALUES (?, ?, '', ?, 'Dine In', ?, ?, ?, ?, ?, ?, ?, ?, '', 'Pending')");
                if (!$stmtInsert) {
                    throw new Exception('Could not prepare kitchen order insert.');
                }
                
                foreach ($items as $item) {
                    $total = $item['price'] * $item['quantity'];
                    $tableId = $req['table_id'];
                    $stmtInsert->bind_param("sissssssidds", 
                        $orderCode, 
                        $item['id'], 
                        $req['customer_name'], 
                        $tableId, 
                        $req['note'], 
                        $req['payment_method'], 
                        $item['name'], 
                        $item['quantity'], 
                        $item['price'], 
                        $total, 
                        $req['phone']
                    );
                    if (!$stmtInsert->execute()) {
                        throw new Exception('Could not insert item to kitchen queue: ' . $stmtInsert->error);
                    }
                }
                
                $conn->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Request not found or not pending']);
        }
        exit;
    }

    if ($postAction === 'reject') {
        $reason = trim((string)($data['reason'] ?? ''));
        if ($reason === '') { $reason = 'No reason provided'; }
        if (mb_strlen($reason) > 255) { $reason = mb_substr($reason, 0, 255); }
        $stmt = $conn->prepare("UPDATE qr_requests SET status = 'rejected', rejection_reason = ? WHERE id = ?");
        $stmt->bind_param("si", $reason, $requestId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error']);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
