<?php
require_once __DIR__ . '/_api_guard.php';

$data = json_decode(file_get_contents('php://input'), true);

$orderType = trim((string)($data['order_type'] ?? 'Dine In'));
$tableNumber = trim((string)($data['table_number'] ?? ''));
$fullName = trim((string)($data['full_name'] ?? 'Walk-in'));
$phone = trim((string)($data['phone'] ?? ''));
$paymentMethod = trim((string)($data['payment_method'] ?? 'Cash'));
$note = trim((string)($data['note'] ?? ''));
$items = $data['items'] ?? [];

if (empty($items) || !is_array($items)) {
    echo json_encode(['success' => false, 'message' => 'No items in order']);
    exit;
}

if ($orderType === 'Dine In' && !$tableNumber) {
    echo json_encode(['success' => false, 'message' => 'Please select a table for Dine In orders']);
    exit;
}

// Validate each item, ensure menu_id exists, menu_status != 'Out of Stock', qty > 0, and compute server price
$validatedItems = [];
foreach ($items as $item) {
    $menuId = (int)($item['menu_id'] ?? 0);
    $qty = (int)($item['quantity'] ?? 0);
    if ($menuId <= 0 || $qty <= 0) continue;

    $stmt = $conn->prepare("SELECT * FROM menu WHERE menu_id = ? LIMIT 1");
    $stmt->bind_param("i", $menuId);
    $stmt->execute();
    $mRow = $stmt->get_result()->fetch_assoc();

    if (!$mRow) {
        echo json_encode(['success' => false, 'message' => 'Invalid item in order']);
        exit;
    }

    if ($mRow['menu_status'] === 'Out of Stock') {
        echo json_encode(['success' => false, 'message' => "Item '{$mRow['menu_name']}' is currently Out of Stock"]);
        exit;
    }

    $itemPrice = (float)($mRow['menu_price'] ?? $mRow['price'] ?? 0);

    $validatedItems[] = [
        'id' => $mRow['menu_id'],
        'name' => $mRow['menu_name'],
        'price' => $itemPrice,
        'quantity' => $qty
    ];
}

if (empty($validatedItems)) {
    echo json_encode(['success' => false, 'message' => 'No valid items to place order']);
    exit;
}

$orderCode = 'ORD-' . date('Ymd') . '-' . rand(1000, 9999);

$conn->begin_transaction();
try {
    $stmtInsert = $conn->prepare("INSERT INTO orders (order_number, menu_id, email, full_name, order_type, table_number, special_instructions, payment_method, menu_name, quantity, price, total_price, mobile, address, status) VALUES (?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', 'Pending')");
    if (!$stmtInsert) {
        throw new Exception('Could not prepare walk-in order statement');
    }

    foreach ($validatedItems as $vItem) {
        $itemTotal = $vItem['price'] * $vItem['quantity'];
        $stmtInsert->bind_param("sissssssidds",
            $orderCode,
            $vItem['id'],
            $fullName,
            $orderType,
            $tableNumber,
            $note,
            $paymentMethod,
            $vItem['name'],
            $vItem['quantity'],
            $vItem['price'],
            $itemTotal,
            $phone
        );
        if (!$stmtInsert->execute()) {
            throw new Exception('Failed to insert order item: ' . $stmtInsert->error);
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'order_number' => $orderCode]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
