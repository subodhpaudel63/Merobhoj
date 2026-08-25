<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

/**
 * Verify that the current user is logged in as an admin.
 * Redirects to admin login page if not authenticated or not an admin.
 */
function require_admin(): void
{
    // FIXED: Changed 'user_type' to 'admin_type' to prevent conflict with regular users
    if (!isset($_COOKIE['admin_type'])) {
        header('Location: /Merobhoj/admin/login.php');
        exit();
    }
    
    $userType = decrypt($_COOKIE['admin_type'], SECRET_KEY);
    
    if ($userType !== 'admin') {
        header('Location: /Merobhoj/admin/login.php');
        exit();
    }
    
    // Check if login time is still valid (using admin_login_time)
    if (isset($_COOKIE['admin_login_time'])) {
        $loginTime = decrypt($_COOKIE['admin_login_time'], SECRET_KEY);
        if ($loginTime && ctype_digit($loginTime)) {
            if (time() - (int) $loginTime > MAX_SESSION_TIME) {
                header('Location: /Merobhoj/admin/login.php?session_expired=1');
                exit();
            }
        }
    }
}