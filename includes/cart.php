<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';

$action = $_GET['action'] ?? 'view';
$user = getUserFromCookie();
$isAjax = ($_GET['ajax'] ?? '') === '1'
    || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

function cart_response(array $payload): void
{
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

if (in_array($action, ['add','update','remove','clear','checkout'], true) && !$user) {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Please login to manage your cart.'];
    header('Location: /Merobhoj/login.php');
    exit;
}

if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $menuId = intval($_POST['menu_id'] ?? 0);
    $name   = trim($_POST['menu_name'] ?? '');
    $price  = floatval($_POST['price'] ?? 0);
    $image  = trim($_POST['image'] ?? '');
    $qty    = max(1, intval($_POST['quantity'] ?? 1));

    $stockStmt = $conn->prepare("SELECT menu_name, menu_price, stock_quantity, max_order_quantity FROM menu WHERE menu_id = ? LIMIT 1");
    $stockStmt->bind_param("i", $menuId);
    $stockStmt->execute();
    $stockResult = $stockStmt->get_result();
    $stockRow = $stockResult ? $stockResult->fetch_assoc() : null;
    $stockStmt->close();

    if ($menuId > 0 && $stockRow && $qty <= min(10, (int)$stockRow['max_order_quantity'], (int)$stockRow['stock_quantity'])) {
        $name = $stockRow['menu_name']; $price = (float)$stockRow['menu_price'];
        if (!isset($_SESSION['cart'][$menuId])) {
            $_SESSION['cart'][$menuId] = ['menu_id' => $menuId, 'menu_name' => $name, 'name' => $name, 'price' => $price, 'quantity' => $qty, 'image' => $image, 'total' => $price * $qty];
        } else {
            $newQty = $_SESSION['cart'][$menuId]['quantity'] + $qty;
            if ($newQty > min(10, (int)$stockRow['max_order_quantity'], (int)$stockRow['stock_quantity'])) {
                if ($isAjax) cart_response(['success' => false, 'message' => 'Quantity exceeds available stock or the maximum of 10.']);
                $_SESSION['msg'] = ['type' => 'error', 'text' => 'Quantity exceeds available stock or the maximum of 10.']; header('Location: ../client/cart.php'); exit;
            }
            $_SESSION['cart'][$menuId]['quantity'] = $newQty;
            $_SESSION['cart'][$menuId]['total'] = $_SESSION['cart'][$menuId]['price'] * $_SESSION['cart'][$menuId]['quantity'];
        }
        $_SESSION['msg'] = ['type' => 'success', 'text' => 'Item added to cart.'];
        if ($isAjax) {
            // The drawer needs the authoritative session contents immediately;
            // do not make it infer success from a redirected HTML page.
            $_SESSION['cart'] = array_values($_SESSION['cart']);
            cart_response([
                'success' => true,
                'message' => 'Item added to cart.',
                'data' => $_SESSION['cart'],
                'count' => count($_SESSION['cart']),
            ]);
        }
        header('Location: ../client/cart.php');
        exit;
    }
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Invalid item or item is out of stock.'];
    if ($isAjax) {
        cart_response(['success' => false, 'message' => 'Invalid item or item is out of stock.']);
    }
    header('Location: ../client/cart.php');
    exit;
}

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (($_POST['quantities'] ?? []) as $id => $q) {
        $id = intval($id); $q = max(0, intval($q));
        if (isset($_SESSION['cart'][$id])) {
            if ($q === 0) unset($_SESSION['cart'][$id]);
            else { $_SESSION['cart'][$id]['quantity'] = $q; $_SESSION['cart'][$id]['total'] = $_SESSION['cart'][$id]['price'] * $q; }
        }
    }
    header('Location: ../client/cart.php');
    exit;
}

if ($action === 'remove') {
    $id = intval($_GET['menu_id'] ?? 0);
    if (isset($_SESSION['cart'][$id])) unset($_SESSION['cart'][$id]);
    $_SESSION['msg'] = ['type' => 'success', 'text' => 'Item removed.'];
    header('Location: ../client/cart.php');
    exit;
}

if ($action === 'clear') {
    $_SESSION['cart'] = [];
    $_SESSION['msg'] = ['type' => 'success', 'text' => 'Cart cleared.'];
    header('Location: ../client/cart.php');
    exit;
}

if ($action === 'checkout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($user['id']) ? intval($user['id']) : null;
    $email = trim((string)($user['email'] ?? ''));
    $mobile = trim($_POST['mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');
    if ($email === '' || !preg_match('/^[0-9]{10}$/', $mobile) || $address === '' || empty($_SESSION['cart'])) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Fill details and ensure cart has items.'];
        header('Location: ../client/cart.php');
        exit;
    }
    $order_number = 'ORD-' . date('Ymd') . '-' . sprintf('%04d', rand(1000, 9999));
    $conn->begin_transaction();

    if ($userId) {
        $stmt = $conn->prepare("INSERT INTO orders (order_number, menu_id, menu_name, price, quantity, total_price, email, user_id, mobile, address, status, order_time, order_date, status_updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), CURDATE(), NOW())");
    } else {
        $stmt = $conn->prepare("INSERT INTO orders (order_number, menu_id, menu_name, price, quantity, total_price, email, mobile, address, status, order_time, order_date, status_updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), CURDATE(), NOW())");
    }

    $allOk = true;
    if ($stmt) {
        foreach ($_SESSION['cart'] as $item) {
            $checkStmt = $conn->prepare("SELECT menu_status FROM menu WHERE menu_id = ? LIMIT 1");
            $checkStmt->bind_param("i", $item['menu_id']);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $checkRow = $checkResult ? $checkResult->fetch_assoc() : null;
            $checkStmt->close();
            if (!$checkRow || ($checkRow['menu_status'] ?? 'In Stock') === 'Out of Stock') { $allOk = false; break; }
            $total = isset($item['total']) ? floatval($item['total']) : (floatval($item['price']) * intval($item['quantity']));
            $name = $item['menu_name'] ?? $item['name'];
            if ($userId) { $stmt->bind_param("sisdidsiss", $order_number, $item['menu_id'], $name, $item['price'], $item['quantity'], $total, $email, $userId, $mobile, $address); }
            else { $stmt->bind_param("sisdidsss", $order_number, $item['menu_id'], $name, $item['price'], $item['quantity'], $total, $email, $mobile, $address); }
            if (!$stmt->execute()) { $allOk = false; break; }
        }
        $stmt->close();
    } else { $allOk = false; }

    if ($allOk) {
        $conn->commit();
        $_SESSION['cart'] = [];
        $_SESSION['msg'] = ['type' => 'success', 'text' => 'Checkout complete.'];
        $hstmt = $conn->prepare("INSERT IGNORE INTO order_status_history (order_number, status) VALUES (?, 'Pending')");
        if ($hstmt) { $hstmt->bind_param('s', $order_number); $hstmt->execute(); $hstmt->close(); }
    } else {
        $conn->rollback();
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Error placing order.'];
    }
    header('Location: ../client/cart.php');
    exit;
}

header('Location: ../client/cart.php');
exit;
?>
