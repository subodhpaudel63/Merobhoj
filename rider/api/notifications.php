<?php
/**
 * Role-scoped notifications for the rider panel (role_notifications WHERE role='rider').
 *   GET  ?action=count   → unread count
 *   GET  (?action=list)  → recent notifications
 *   POST {action:'read', id}
 *   POST {action:'read_all'}
 */
require_once __DIR__ . '/_api_guard.php';

// The rider panel always shows the 'rider' notification stream (admins viewing it too).
const NOTIFY_ROLE = 'rider';

if (api_is_post()) {
    $input  = api_input();
    api_require_csrf($input);
    $action = trim((string)($input['action'] ?? ''));

    if ($action === 'read_all') {
        $role = NOTIFY_ROLE;
        $stmt = $conn->prepare("UPDATE role_notifications SET is_read = 1 WHERE role = ? AND is_read = 0");
        $stmt->bind_param('s', $role);
        $ok = $stmt->execute();
        $stmt->close();
        api_json(['success' => (bool)$ok, 'message' => 'All marked read']);
    }

    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) api_json(['success' => false, 'message' => 'Invalid notification']);
    $role = NOTIFY_ROLE;
    $stmt = $conn->prepare("UPDATE role_notifications SET is_read = 1 WHERE id = ? AND role = ?");
    $stmt->bind_param('is', $id, $role);
    $ok = $stmt->execute();
    $stmt->close();
    api_json(['success' => (bool)$ok, 'message' => $ok ? 'Marked read' : 'Failed']);
}

$role   = NOTIFY_ROLE;
$action = $_GET['action'] ?? 'list';

if ($action === 'count') {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM role_notifications WHERE role = ? AND is_read = 0");
    $stmt->bind_param('s', $role);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    api_json(['success' => true, 'count' => $count]);
}

// list
$limit = (int)($_GET['limit'] ?? 30);
if ($limit < 1 || $limit > 100) $limit = 30;

$stmt = $conn->prepare("SELECT id, type, title, message, resource_id, url, is_read, created_at
                        FROM role_notifications
                        WHERE role = ?
                        ORDER BY is_read ASC, created_at DESC, id DESC
                        LIMIT ?");
$stmt->bind_param('si', $role, $limit);
$stmt->execute();
$res = $stmt->get_result();

$items  = [];
$unread = 0;
while ($row = $res->fetch_assoc()) {
    if ((int)$row['is_read'] === 0) $unread++;
    $items[] = [
        'id'         => (int)$row['id'],
        'type'       => $row['type'],
        'title'      => $row['title'],
        'message'    => $row['message'],
        'resource_id'=> $row['resource_id'],
        'url'        => $row['url'],
        'is_read'    => (int)$row['is_read'],
        'created_at' => $row['created_at'],
    ];
}
$stmt->close();

api_json(['success' => true, 'notifications' => $items, 'unread' => $unread]);
