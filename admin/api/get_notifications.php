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

try {
    // Synchronize latest database entries into notifications table
    sync_notifications($conn);

    // Fetch unread count and latest 15 notifications
    $unreadCount = get_unread_notifications_count($conn);
    $recent = get_recent_notifications($conn, 15);

    echo json_encode([
        'success' => true,
        'unread_count' => $unreadCount,
        'recent' => $recent
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
exit;
