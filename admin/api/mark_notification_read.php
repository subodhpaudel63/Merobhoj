<?php
declare(strict_types=1);

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

// Support both form-data and JSON input
$input = json_decode(file_get_contents('php://input'), true);
$type = trim((string)($_POST['type'] ?? $input['type'] ?? ''));
$resourceId = trim((string)($_POST['resource_id'] ?? $input['resource_id'] ?? ''));

if ($type === '' || $resourceId === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing notification type or resource ID.'
    ]);
    exit;
}

try {
    $success = mark_notification_as_read($conn, $type, $resourceId);
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Notification not found or already read.'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
exit;
