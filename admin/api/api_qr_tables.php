<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/admin_auth.php'; // Ensure only admins can access

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
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
