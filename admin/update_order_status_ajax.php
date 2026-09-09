<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/order_validation.php';
require_once __DIR__ . '/../helpers/audit_logger.php';

$response = ['success' => false, 'message' => 'Invalid request'];

// 1. Verify admin permissions (Rule 3)
if (!isset($_COOKIE['admin_type']) || decrypt($_COOKIE['admin_type'], SECRET_KEY) !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Admin permissions required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!empty($raw) && json_last_error() === JSON_ERROR_NONE) {
        $order_id = intval($input['order_id'] ?? 0);
        $order_number = trim((string)($input['order_number'] ?? ''));
        $status = $input['status'] ?? '';
    } else {
        $order_id = intval($_POST['order_id'] ?? 0);
        $order_number = trim((string)($_POST['order_number'] ?? ''));
        $status = $_POST['status'] ?? '';
    }

    if ($order_id <= 0 && $order_number === '') {
        $response = ['success' => false, 'message' => 'Missing order identifier'];
    } elseif (!in_array($status, ORDER_STATUSES, true)) {
        $response = ['success' => false, 'message' => 'Invalid order status selected'];
    } else {
        // 2. The order exists (Rule 1)
        $existingOrder = null;
        if ($order_number !== '') {
            $lookup = $conn->prepare("SELECT order_id, order_number, status, order_type FROM orders WHERE order_number = ? LIMIT 1");
            if ($lookup) {
                $lookup->bind_param("s", $order_number);
                $lookup->execute();
                $result = $lookup->get_result();
                $existingOrder = $result ? $result->fetch_assoc() : null;
                $lookup->close();
            }
        } else {
            $lookup = $conn->prepare("SELECT order_id, order_number, status, order_type FROM orders WHERE order_id = ? LIMIT 1");
            if ($lookup) {
                $lookup->bind_param("i", $order_id);
                $lookup->execute();
                $result = $lookup->get_result();
                $existingOrder = $result ? $result->fetch_assoc() : null;
                $lookup->close();
            }
        }

        if (!$existingOrder) {
            $response = ['success' => false, 'message' => 'Order not found'];
        } else {
            // Resolve the history key the same way the client/fetch do
            $historyKey = !empty($existingOrder['order_number'])
                ? $existingOrder['order_number']
                : (!empty($order_number) ? $order_number : 'ORD-' . str_pad((string)$existingOrder['order_id'], 4, '0', STR_PAD_LEFT));

            // 3. Validate transition & type compatibility (Rules 4, 5, 6)
            $validation = validate_order_transition($existingOrder['order_type'] ?? 'Delivery', $existingOrder['status'], $status, true);
            if (!$validation['valid']) {
                $response = ['success' => false, 'message' => $validation['error']];
            } else {
                // Update all items sharing this order number, or just this item if order_number is missing
                if (!empty($order_number)) {
                    $stmt = $conn->prepare("UPDATE orders SET status = ?, status_updated_at = NOW() WHERE order_number = ?");
                    if ($stmt) {
                        $stmt->bind_param("ss", $status, $order_number);
                    }
                } else {
                    $stmt = $conn->prepare("UPDATE orders SET status = ?, status_updated_at = NOW() WHERE order_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("si", $status, $existingOrder['order_id']);
                    }
                }
                
                if (!$stmt) {
                    $response = ['success' => false, 'message' => 'Failed to prepare update statement'];
                } else {
                    if ($stmt->execute()) {
                        if (strtolower((string)$status) === 'cancelled' || strtolower((string)$status) === 'canceled') {
                            $auditUserId = (int)($_SESSION['admin_id'] ?? 0);
                            if ($auditUserId > 0) {
                                log_audit_action($conn, $auditUserId, 'admin', 'VOID_ORDER', 'order', (int)$existingOrder['order_id'], ['old_status' => $existingOrder['status'], 'reason' => 'Order status changed to cancelled']);
                            }
                        }
                        // Permanent per-status history: record the exact real time
                        // THIS status was set. Re-setting the same status refreshes
                        // only its own entry; every status keeps its own time.
                        $hstmt = $conn->prepare("INSERT INTO order_status_history (order_number, status) VALUES (?, ?) ON DUPLICATE KEY UPDATE changed_at = NOW()");
                        if ($hstmt) {
                            $hstmt->bind_param('ss', $historyKey, $status);
                            $hstmt->execute();
                            $hstmt->close();
                        }
                        $response = [
                            'success' => true,
                            'message' => 'Order updated successfully'
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Database execute failed'
                        ];
                    }
                    $stmt->close();
                }
            }
        }
    }
}

echo json_encode($response);
exit;
