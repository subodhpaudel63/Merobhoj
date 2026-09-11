<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';   // encrypt(), SECRET_KEY, MAX_SESSION_TIME
require_once __DIR__ . '/role_check.php';   // set_panel_cookies(), PANEL_ROLES, panel_home_for_role()

/* Only accept POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /Merobhoj/admin/login.php');
    exit();
}

$isAdminLogin = ($_POST['login_source'] ?? '') === 'admin';
$loginRedirect = $isAdminLogin
    ? '/Merobhoj/admin/login.php'
    : '/Merobhoj/admin/login.php';
$portalRole = ($_POST['portal_role'] ?? '') === 'staff' ? 'staff' : 'owner';

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Email and password are required.'];
    header('Location: ' . $loginRedirect);
    exit();
}

/* Look up the account (any role — we gate on panel roles below) */
$stmt = $conn->prepare("SELECT id, name, email, password, user_type, user_img FROM users WHERE email = ? LIMIT 1");
if (!$stmt) {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Database error.'];
    header('Location: ' . $loginRedirect);
    exit();
}
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Invalid email or password.'];
    header('Location: ' . $loginRedirect);
    exit();
}

$role = (string)$user['user_type'];
if (!in_array($role, PANEL_ROLES, true)) {
    // A plain customer tried the staff portal.
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'This is a staff portal. Please use the customer login.'];
    header('Location: ' . $loginRedirect);
    exit();
}

/* The selected portal is an authorization boundary, not just a visual tab. */
$ownerRoles = ['admin', 'manager', 'chef'];
$staffRoles = ['staff', 'rider'];
$allowedRoles = $portalRole === 'owner' ? $ownerRoles : $staffRoles;
if ($isAdminLogin && !in_array($role, $allowedRoles, true)) {
    $_SESSION['msg'] = [
        'type' => 'error',
        'text' => 'These credentials do not belong to the selected portal.'
    ];
    header('Location: ' . $loginRedirect);
    exit();
}

/* Establish the native panel session (role is re-validated on every request) */
set_panel_cookies($user['email'], $role);
$_SESSION['panel_user_id'] = (int)$user['id'];
$_SESSION['panel_role']    = $role;

/* Admin & manager also receive the legacy admin cookies so the existing admin panel authorizes them */
if ($role === 'admin' || $role === 'manager') {
    $now    = time();
    $expiry = $now + MAX_SESSION_TIME;
    setcookie('admin_email',      encrypt($user['email'], SECRET_KEY), $expiry, '/', '', false, true);
    setcookie('admin_type',       encrypt('admin',        SECRET_KEY), $expiry, '/', '', false, true);
    setcookie('admin_login_time', encrypt((string)$now,   SECRET_KEY), $expiry, '/', '', false, true);
}

header('Location: ' . panel_home_for_role($role));
exit();
