<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Include database - this file is in /admin/ folder
require_once __DIR__ . '/../includes/db.php';

// Handle both form data and JSON requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $order_id = 0;
    $order_number = '';
    if ($input) {
        $order_id = intval($input['order_id'] ?? 0);
        $order_number = trim((string)($input['order_number'] ?? ''));
    } else {
        $order_id = intval($_POST['order_id'] ?? 0);
        $order_number = trim((string)($_POST['order_number'] ?? ''));
    }
    
    // Resolve the order number so the permanent status history can be purged too
    if (empty($order_number) && $order_id > 0) {
        $r = $conn->prepare("SELECT order_number FROM orders WHERE order_id = ? LIMIT 1");
        if ($r) {
            $r->bind_param('i', $order_id);
            $r->execute();
            $row = $r->get_result()->fetch_assoc();
            $order_number = $row['order_number'] ?? '';
            $r->close();
        }
    }

    if (!empty($order_number)) {
        $stmt = $conn->prepare("DELETE FROM orders WHERE order_number = ? OR order_id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $order_number, $order_id);
        }
    } else if ($order_id > 0) {
        $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $order_id);
        }
    } else {
        $stmt = null;
    }

    if (!$stmt) {
        $response = [
            'success' => false,
            'message' => 'Invalid order identifier or database error: ' . $conn->error
        ];
    } else {
        if ($stmt->execute()) {
            // Purge the permanent status history for the deleted order
            if ($order_number !== '') {
                $h = $conn->prepare("DELETE FROM order_status_history WHERE order_number = ?");
                if ($h) {
                    $h->bind_param('s', $order_number);
                    $h->execute();
                    $h->close();
                }
            }
            $response = [
                'success' => true,
                'message' => 'Order deleted successfully'
            ];
        } else {
            $response = [
                'success' => false,
                'message' => 'Failed to delete: ' . $stmt->error
            ];
        }
        $stmt->close();
    }
} else {
    $response = [
        'success' => false,
        'message' => 'Invalid request method'
    ];
}

echo json_encode($response);
exit;
