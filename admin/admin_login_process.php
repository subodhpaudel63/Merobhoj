<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/db.php';        // defines $conn (mysqli)
require_once __DIR__ . '/../includes/auth_check.php'; // defines encrypt(), SECRET_KEY

/* Only accept POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /Merobhoj/admin/login.php');
    exit();
}

/* Input validation */
$userEmail = trim($_POST['email'] ?? '');
$password  = $_POST['password'] ?? '';

if ($userEmail === '' || $password === '') {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Email and password are required.'];
    header('Location: /Merobhoj/admin/login.php');
    exit();
}

/* Authenticate against the users table using a prepared statement */
$stmt = $conn->prepare('SELECT id, email, password, user_type, user_img FROM users WHERE email = ? AND user_type = ? LIMIT 1');
if (!$stmt) {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Database error.'];
    header('Location: /Merobhoj/admin/login.php');
    exit();
}

$userType = 'admin';
$stmt->bind_param('ss', $userEmail, $userType);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

/* Verify credentials */
if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Invalid admin credentials.'];
    header('Location: /Merobhoj/admin/login.php');
    exit();
}

/* Successful admin login — set session & cookies */
$now          = time();
$cookieMaxAge = 86400; // 24h

$_SESSION['admin_email'] = $userEmail;
$_SESSION['admin_id']    = (int)$user['id'];
$_SESSION['user_type']   = 'admin';

// Set cookies with unique admin names to avoid conflict with regular user cookies
setcookie('admin_email',      encrypt($user['email'],      SECRET_KEY), $now + $cookieMaxAge, '/', '', false, true);
setcookie('admin_type',       encrypt('admin',             SECRET_KEY), $now + $cookieMaxAge, '/', '', false, true);
setcookie('admin_login_time', encrypt((string) $now,       SECRET_KEY), $now + $cookieMaxAge, '/', '', false, true);

$_SESSION['msg'] = ['type' => 'success', 'text' => 'Admin login successful!'];
header('Location: /Merobhoj/admin/index.php');
exit();
