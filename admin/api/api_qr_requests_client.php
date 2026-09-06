<?php
session_start();
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $postAction = $data['action'] ?? '';

    if ($postAction === 'submit') {
        $token = $data['token'] ?? '';
        $items = $data['items'] ?? [];
        $name = $data['name'] ?? '';
        $phone = $data['phone'] ?? '';
        $note = $data['note'] ?? '';
        $payment = $data['payment'] ?? 'Cash';
        
        if (!$token || empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Missing data']);
            exit;
        }

        // Enforce the same Nepali mobile format on the server.
        $normalizedPhone = preg_replace('/[\s-]/', '', trim($phone));
        if (!is_string($normalizedPhone) || !preg_match('/^(?:\+977)?9[678][0-9]{8}$/', $normalizedPhone)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a valid Nepali mobile number.']);
            exit;
        }

        // Get table ID
        $stmt = $conn->prepare("SELECT id FROM restaurant_tables WHERE qr_token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $table = $stmt->get_result()->fetch_assoc();
        
        if (!$table) {
            echo json_encode(['success' => false, 'message' => 'Invalid QR token']);
            exit;
        }

        $tableId = $table['id'];
        $itemsJson = json_encode($items);

        // Re-check availability server-side (mirrors includes/cart.php): a chef
        // marking an item Out of Stock must also block QR table orders, and we
        // never trust the client's price/stock. Also computes the order total.
        $total = 0;
        $stockCheck = $conn->prepare("SELECT menu_name, menu_status FROM menu WHERE menu_id = ? LIMIT 1");
        foreach ($items as $item) {
            $menuId = (int)($item['id'] ?? 0);
            $qty    = (int)($item['quantity'] ?? 0);
            $price  = (float)($item['price'] ?? 0);
            if ($menuId <= 0 || $qty <= 0) {
                echo json_encode(['success' => false, 'message' => 'Your order contains an invalid item. Please refresh and try again.']);
                if ($stockCheck) $stockCheck->close();
                exit;
            }
            $stockCheck->bind_param("i", $menuId);
            $stockCheck->execute();
            $stockRow = $stockCheck->get_result()->fetch_assoc();
            if (!$stockRow || ($stockRow['menu_status'] ?? 'In Stock') === 'Out of Stock') {
                $label = $stockRow['menu_name'] ?? ('Item #' . $menuId);
                echo json_encode(['success' => false, 'message' => $label . ' is currently unavailable. Please remove it and try again.']);
                $stockCheck->close();
                exit;
            }
            $total += $price * $qty;
        }
        if ($stockCheck) $stockCheck->close();

        $stmt = $conn->prepare("INSERT INTO qr_requests (table_id, customer_name, phone, note, payment_method, items_json, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param("isssssd", $tableId, $name, $phone, $note, $payment, $itemsJson, $total);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'request_id' => $stmt->insert_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'status') {
        $requestId = $_GET['request_id'] ?? 0;
        if ($requestId) {
            $stmt = $conn->prepare("SELECT status, order_code, rejection_reason FROM qr_requests WHERE id = ?");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            if ($res) {
                echo json_encode(['success' => true, 'status' => $res['status'], 'order_code' => $res['order_code'], 'rejection_reason' => $res['rejection_reason']]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Not found']);
            }
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
