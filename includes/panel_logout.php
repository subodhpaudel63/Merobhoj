<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/role_check.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear only the role represented by the current panel URL.
$requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
$activeRole = null;
foreach (PANEL_ROLES as $candidateRole) {
    if (strpos($requestUri, '/' . $candidateRole . '/') !== false) {
        $activeRole = $candidateRole;
        break;
    }
}
$cookieRole = $activeRole;
if ($cookieRole === null && strpos($requestUri, '/admin/') !== false) {
    $cookieRole = 'admin';
}
if ($cookieRole !== null) {
    clear_panel_cookies($cookieRole);
}

// Also clear the legacy admin cookies (admin/manager panel sessions set these).
$past = time() - 3600;
if ($activeRole === 'admin' || $activeRole === 'manager' || $activeRole === null) {
    foreach (['admin_email', 'admin_type', 'admin_login_time'] as $c) {
        setcookie($c, '', $past, '/', '', false, true);
        unset($_COOKIE[$c]);
    }
}

// Drop panel-specific session state (leave any unrelated session data intact).
unset($_SESSION['panel_user_id'], $_SESSION['panel_role'], $_SESSION['panel_csrf']);

$_SESSION['msg'] = ['type' => 'success', 'text' => 'You have been logged out.'];
header('Location: /Merobhoj/admin/login.php');
exit();
