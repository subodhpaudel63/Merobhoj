<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db.php";
require_once "auth_check.php";
require_once "order_validation.php";

// 1. Verify admin permissions (Rule 3)
if (!isset($_COOKIE['admin_type']) || decrypt($_COOKIE['admin_type'], SECRET_KEY) !== 'admin') {
    $_SESSION['msg'] = [
        'type' => 'error',
        'text' => 'Unauthorized access. Admin permissions required.'
    ];
    header("Location: /Merobhoj/admin/orders_page.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    error_log("Received order_id: $order_id, status: $status");

    if ($order_id > 0 && in_array($status, ORDER_STATUSES, true)) {
        // 2. Fetch order to verify details (Rule 1)
        $stmt_get = $conn->prepare("SELECT order_number, status, order_type FROM orders WHERE order_id = ? LIMIT 1");
        $stmt_get->bind_param("i", $order_id);
        $stmt_get->execute();
        $res = $stmt_get->get_result();
        $order = $res->fetch_assoc();
        $stmt_get->close();

        if (!$order) {
            error_log("Order $order_id not found.");
            $_SESSION['msg'] = [
                'type' => 'error',
                'text' => 'Order not found.'
            ];
        } else {
            // 3. Validate transition & type compatibility (Rules 4, 5, 6)
            $validation = validate_order_transition($order['order_type'] ?? 'Delivery', $order['status'], $status, true);
            if (!$validation['valid']) {
                error_log("Validation failed: " . $validation['error']);
                $_SESSION['msg'] = [
                    'type' => 'error',
                    'text' => $validation['error']
                ];
            } else {
                if (!empty($order['order_number'])) {
                    $stmt = $conn->prepare("UPDATE orders SET status = ?, status_updated_at = NOW() WHERE order_number = ?");
                    $stmt->bind_param("ss", $status, $order['order_number']);
                } else {
                    $stmt = $conn->prepare("UPDATE orders SET status = ?, status_updated_at = NOW() WHERE order_id = ?");
                    $stmt->bind_param("si", $status, $order_id);
                }

                if ($stmt->execute()) {
                    error_log("Update success for order $order_id to status $status");

                    // Permanent per-status history: record the exact time THIS
                    // status was set. Re-setting the same status refreshes its
                    // time (latest wins); every status keeps its own entry.
                    $historyKey = !empty($order['order_number'])
                        ? $order['order_number']
                        : 'ORD-' . str_pad((string)$order_id, 4, '0', STR_PAD_LEFT);
                    $hstmt = $conn->prepare("INSERT INTO order_status_history (order_number, status) VALUES (?, ?) ON DUPLICATE KEY UPDATE changed_at = NOW()");
                    if ($hstmt) {
                        $hstmt->bind_param('ss', $historyKey, $status);
                        $hstmt->execute();
                        $hstmt->close();
                    }

                    $_SESSION['msg'] = [
                        'type' => 'success',
                        'text' => 'Order status updated successfully.'
                    ];
                } else {
                    error_log("Update failed: " . $stmt->error);
                    $_SESSION['msg'] = [
                        'type' => 'error',
                        'text' => 'Failed to update order status. Error: ' . $stmt->error
                    ];
                }
                $stmt->close();
            }
        }
    } else {
        error_log("Invalid order ID or status. order_id: $order_id, status: $status");
        $_SESSION['msg'] = [
            'type' => 'error',
            'text' => 'Invalid order ID or status.'
        ];
    }
} else {
    $_SESSION['msg'] = [
        'type' => 'error',
        'text' => 'Invalid request method.'
    ];
}

// Redirect back to the admin orders page
header("Location: /Merobhoj/admin/orders_page.php");
exit;
