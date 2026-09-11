<?php
declare(strict_types=1);

function log_audit_action(mysqli $conn, int $user_id, string $role, string $action, string $target_type = '', ?int $target_id = null, array $details_array = []): void
{
    $details = json_encode($details_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($details === false) {
        throw new RuntimeException('Unable to encode audit details.');
    }
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $stmt = $conn->prepare('INSERT INTO audit_logs (user_id, user_role, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare audit log.');
    }
    $stmt->bind_param('isssiss', $user_id, $role, $action, $target_type, $target_id, $details, $ip);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to write audit log.');
    }
    $stmt->close();
}
