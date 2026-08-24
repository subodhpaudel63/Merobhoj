<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../includes/db.php';

$response = ['success' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!empty($raw) && json_last_error() === JSON_ERROR_NONE) {
        $order_id = intval($input['order_id'] ?? 0);
        $order_number = trim((string)($input['order_number'] ?? ''));
        $status = $input['status'] ?? '';
        $admin_note = isset($input['admin_note']) ? (string)$input['admin_note'] : null;
    } else {
        $order_id = intval($_POST['order_id'] ?? 0);
        $order_number = trim((string)($_POST['order_number'] ?? ''));
        $status = $_POST['status'] ?? '';
        $admin_note = isset($_POST['admin_note']) ? (string)$_POST['admin_note'] : null;
    }

    $allowed_status = ['Confirmed', 'Shipping', 'Ongoing', 'Delivering', 'Cancelled'];

    if ($order_id <= 0 && $order_number === '') {
        $response = ['success' => false, 'message' => 'Missing order identifier'];
    } elseif (!in_array($status, $allowed_status, true)) {
        $response = ['success' => false, 'message' => 'Invalid order status selected'];
    } else {
        $lookup = null;
        if ($order_number !== '') {
            $lookup = $conn->prepare("SELECT order_id, status FROM orders WHERE order_number = ? OR order_id = ? LIMIT 1");
            if ($lookup) {
                $lookup->bind_param("si", $order_number, $order_id);
            }
        } else {
            $lookup = $conn->prepare("SELECT order_id, status FROM orders WHERE order_id = ? LIMIT 1");
            if ($lookup) {
                $lookup->bind_param("i", $order_id);
            }
        }

        if (!$lookup) {
            $response = ['success' => false, 'message' => 'Failed to validate order'];
        } else {
            $lookup->execute();
            $result = $lookup->get_result();
            $existingOrder = $result ? $result->fetch_assoc() : null;
            $lookup->close();

            if (!$existingOrder) {
                $response = ['success' => false, 'message' => 'Order not found'];
            } elseif (($existingOrder['status'] ?? '') === $status) {
                $response = ['success' => false, 'message' => 'Order already has this status'];
            } else {
                if ($order_number !== '') {
                    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_number = ?");
                    if ($stmt) {
                        $stmt->bind_param("ss", $status, $order_number);
                    }
                } else {
                    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("si", $status, $existingOrder['order_id']);
                    }
                }
                
                if (!$stmt) {
                    $response = ['success' => false, 'message' => 'Failed to prepare update statement'];
                } else {

                    if ($stmt->execute()) {
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
