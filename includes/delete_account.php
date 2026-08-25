<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR])) {
        error_log("DELETE‑FATAL: {$e['message']} in {$e['file']} on line {$e['line']}");
    }
});
error_log("DELETE‑DEBUG: script started");

$user = requireLogin();
if ($user['userType'] !== 'user') {
    header('Location: /Merobhoj/');
    exit();
}

if (empty($_POST['confirm_phrase']) || trim($_POST['confirm_phrase']) !== 'DELETE') {
    header('Location: /Merobhoj/client/index.php?error=confirm');
    exit();
}

$email = $user['email'] ?? '';
if (!$email) {
    header('Location: /Merobhoj/client/index.php?error=noemail');
    exit();
}

// Delete avatar if custom
$avatar = $user['user_img'] ?? '';
$defaultAvatar = 'profilepic.jpg';
if ($avatar && basename($avatar) !== $defaultAvatar) {
    $avatarPath = realpath(__DIR__ . '/../' . ltrim($avatar, '/\\'));
    if ($avatarPath && file_exists($avatarPath)) {
        @unlink($avatarPath);
    }
}

// Tables you want to check (users must be last)
$tables = ['orders', 'feedback', 'bookings', 'users'];

try {
    $conn->begin_transaction();

    foreach ($tables as $table) {
        // Check if table has an email column
        $colCheck = $conn->prepare("
            SELECT COUNT(*) 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = 'email'
        ");
        $colCheck->bind_param('s', $table);
        $colCheck->execute();
        $colCheck->bind_result($hasEmail);
        $colCheck->fetch();
        $colCheck->close();

        if (!$hasEmail) {
            error_log("DELETE‑SKIP: `$table` has no 'email' column");
            continue;
        }

        $stmt = $conn->prepare("DELETE FROM `$table` WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    error_log("DELETE‑DEBUG: Account and related data deleted for $email");
} catch (Throwable $e) {
    $conn->rollback();
    $err = urlencode($e->getMessage());
    error_log("DELETE‑ERROR: " . $e->getMessage());
    header("Location: /Merobhoj/client/index.php?error=deletion&details=$err");
    exit();
}

// Session and cookie cleanup
session_destroy();
setcookie(session_name(), '', time() - 3600, '/');

foreach ($_COOKIE as $name => $value) {
    setcookie($name, '', time() - 3600, '/');
}

header('Location: /Merobhoj/login.php?account=deleted');
exit();
