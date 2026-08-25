<?php
/*
 * signup.php  –  register a new normal user
 * POST: email, password
 */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/db.php';      // sets $conn (mysqli)
require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /Merobhoj/register.php');
    exit();
}

/* --------- validate input --------- */
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Invalid input.'];
    header('Location: /Merobhoj/register.php');
    exit();
}

/* --------- email already exists? --------- */
$stmt = $conn->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows) {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Email already registered.'];
    $stmt->close();
    header('Location: /Merobhoj/register.php');
    exit();
}
$stmt->close();

/* --------- insert new user --------- */
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$userType     = 'user';

$insert = $conn->prepare(
    'INSERT INTO users (email, password, user_type) VALUES (?, ?, ?)'
);
$insert->bind_param('sss', $email, $hashed_password, $userType);

if (!$insert->execute()) {
    error_log('Signup error: ' . $insert->error);
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Registration failed.'];
    $insert->close();
    header('Location: /Merobhoj/register.php');
    exit();
}
$insert->close();

/* --------- success: set session & cookies --------- */
$_SESSION['msg']       = ['type' => 'success', 'text' => 'Registration successful!'];
$_SESSION['email']     = $email;
$_SESSION['user_type'] = $userType;

$now   = time();
$life  = 86400; // 24 hours

setcookie('email',      encrypt($email,        SECRET_KEY), $now + $life, '/', '', false, true);
setcookie('user_type',  encrypt($userType,     SECRET_KEY), $now + $life, '/', '', false, true);
setcookie('login_time', encrypt((string)$now,  SECRET_KEY), $now + $life, '/', '', false, true);

/* --------- redirect to client index.php --------- */
header('Location: /Merobhoj/client/index.php');
exit();