<?php
session_start();

// Customer and panel authentication share the PHP session transport, so do
// not destroy the session here. Panel state must survive a customer logout.
unset($_SESSION['msg']);

// Remove your custom cookies
setcookie('email', '', time() - 3600, '/');
setcookie('user_type', '', time() - 3600, '/');
setcookie('login_time', '', time() - 3600, '/');

// Optionally unset from $_COOKIE superglobal (not required, but can help)
unset($_COOKIE['email'], $_COOKIE['user_type'], $_COOKIE['login_time']);

// Check if the user came from admin area
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
if (strpos($referrer, '/admin/') !== false) {
    // Redirect to admin login page
    header("Location: /Merobhoj/admin/login.php?logged_out=1");
} else {
    // Redirect to main login page
    header("Location: /Merobhoj/login.php?logged_out=1");
}
exit;
?>