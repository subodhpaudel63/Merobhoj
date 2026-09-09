<?php
declare(strict_types=1);

/**
 * Panel authentication + server-side role authorization (Phase 1 foundation).
 *
 * Security model (spec §1, §34):
 *   - The role stored in the encrypted cookie is NEVER trusted on its own.
 *   - current_panel_user() re-queries the users table on every request and
 *     confirms the cookie's role claim still equals the authoritative DB role.
 *   - require_role() enforces the allow-list; 'admin' is a universal override.
 *   - No user can reach another role's dashboard by typing its URL: every
 *     protected page/API calls require_role() before rendering or acting.
 *
 * Reuses encrypt()/decrypt()/SECRET_KEY/MAX_SESSION_TIME from auth_check.php.
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Roles allowed to hold a panel session (never plain 'user' / customer). */
const PANEL_ROLES = ['admin', 'manager', 'staff', 'chef', 'rider'];

/** Set the encrypted panel auth cookies after a successful panel login. */
function set_panel_cookies(string $email, string $role): void
{
    global $conn;
    if ($conn) {
        $upStmt = $conn->prepare('UPDATE users SET last_login = NOW() WHERE email = ?');
        if ($upStmt) {
            $upStmt->bind_param('s', $email);
            $upStmt->execute();
            $upStmt->close();
        }
    }
    $now    = time();
    $expiry = $now + MAX_SESSION_TIME;
    $prefix = 'panel_' . $role . '_';
    setcookie($prefix . 'email',      encrypt($email,       SECRET_KEY), $expiry, '/', '', false, true);
    setcookie($prefix . 'role',       encrypt($role,        SECRET_KEY), $expiry, '/', '', false, true);
    setcookie($prefix . 'login_time', encrypt((string)$now, SECRET_KEY), $expiry, '/', '', false, true);
}

/** Remove the panel auth cookies (used by panel logout). */
function clear_panel_cookies(?string $role = null): void
{
    $past = time() - 3600;
    $roles = $role !== null && in_array($role, PANEL_ROLES, true) ? [$role] : PANEL_ROLES;
    foreach ($roles as $panelRole) {
        $prefix = 'panel_' . $panelRole . '_';
        foreach (['email', 'role', 'login_time'] as $suffix) {
            $c = $prefix . $suffix;
            setcookie($c, '', $past, '/', '', false, true);
            unset($_COOKIE[$c]);
        }
    }
    // Remove the pre-namespacing cookies once, without affecting role cookies.
    foreach (['panel_email', 'panel_role', 'panel_login_time'] as $c) {
        setcookie($c, '', $past, '/', '', false, true);
        unset($_COOKIE[$c]);
    }
}

function _panel_request_role(): ?string
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    foreach (PANEL_ROLES as $role) {
        if (strpos($uri, '/' . $role . '/') !== false) {
            return $role;
        }
    }
    return null;
}

/** Resolve a panel user from the native panel_* cookies, re-validated in DB. */
function _panel_user_from_panel_cookies(mysqli $conn, ?array $allowedRoles = null): ?array
{
    $requestRole = _panel_request_role();
    $roles = [];
    if ($requestRole !== null) {
        $roles[] = $requestRole;
    }
    $fallback = $allowedRoles !== null ? $allowedRoles : PANEL_ROLES;
    foreach ($fallback as $r) {
        if (!in_array($r, $roles, true)) {
            $roles[] = $r;
        }
    }
    foreach ($roles as $candidateRole) {
        $prefix = 'panel_' . $candidateRole . '_';
        if (!isset($_COOKIE[$prefix . 'email'], $_COOKIE[$prefix . 'role'], $_COOKIE[$prefix . 'login_time'])) {
            continue;
        }
        $user = _resolve_namespaced_panel_cookie_user($conn, $prefix);
        if ($user !== null) {
            return $user;
        }
    }
    return null;
}

function _resolve_namespaced_panel_cookie_user(mysqli $conn, string $prefix): ?array
{
    if (!isset($_COOKIE[$prefix . 'email'], $_COOKIE[$prefix . 'role'], $_COOKIE[$prefix . 'login_time'])) {
        return null;
    }
    $email     = decrypt($_COOKIE[$prefix . 'email'],      SECRET_KEY);
    $role      = decrypt($_COOKIE[$prefix . 'role'],       SECRET_KEY);
    $loginTime = decrypt($_COOKIE[$prefix . 'login_time'], SECRET_KEY);

    if ($email === false || $role === false || $loginTime === false
        || $email === '' || !ctype_digit((string)$loginTime)) {
        return null;
    }
    if (time() - (int)$loginTime > MAX_SESSION_TIME) {
        return null; // expired
    }

    $stmt = $conn->prepare("SELECT id, name, email, user_type, user_img FROM users WHERE email = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res  = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        return null;
    }
    // The cookie's role claim must equal the authoritative DB role,
    // and that role must be a real panel role.
    if ((string)$user['user_type'] !== (string)$role) {
        return null;
    }
    if (!in_array($user['user_type'], PANEL_ROLES, true)) {
        return null;
    }

    $user['id'] = (int)$user['id'];
    return $user;
}

/** Resolve an admin who logged in through the classic admin login (legacy admin_* cookies). */
function _panel_user_from_legacy_admin(mysqli $conn): ?array
{
    if (!isset($_COOKIE['admin_type']) || decrypt($_COOKIE['admin_type'], SECRET_KEY) !== 'admin') {
        return null;
    }
    if (isset($_COOKIE['admin_login_time'])) {
        $lt = decrypt($_COOKIE['admin_login_time'], SECRET_KEY);
        if ($lt && ctype_digit((string)$lt) && (time() - (int)$lt) > MAX_SESSION_TIME) {
            return null; // expired
        }
    }
    $adminEmail = isset($_COOKIE['admin_email']) ? decrypt($_COOKIE['admin_email'], SECRET_KEY) : false;
    if (!$adminEmail) {
        return null;
    }
    $stmt = $conn->prepare("SELECT id, name, email, user_type, user_img FROM users WHERE email = ? AND user_type = 'admin' LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $adminEmail);
    $stmt->execute();
    $res  = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$user) {
        return null;
    }
    $user['id'] = (int)$user['id'];
    return $user;
}

/**
 * The currently logged-in panel user (native panel session first, then a
 * legacy admin session as fallback so an admin is always universal), or null.
 *
 * @return array{id:int,name:string,email:string,user_type:string,user_img:string}|null
 */
function current_panel_user(mysqli $conn, ?array $allowedRoles = null): ?array
{
    $user = _panel_user_from_panel_cookies($conn, $allowedRoles);
    if ($user !== null) {
        return $user;
    }
    return _panel_user_from_legacy_admin($conn);
}

/**
 * Require the current panel user to hold one of $roles ('admin' always passes).
 * On failure: 401 JSON for API/AJAX callers, redirect to panel login otherwise.
 *
 * @param string|string[] $roles
 * @return array The authenticated user record.
 */
function require_role(mysqli $conn, string|array $roles): array
{
    $allowed = is_array($roles) ? $roles : [$roles];
    $user    = current_panel_user($conn, $allowed);

    if ($user !== null
        && (in_array($user['user_type'], $allowed, true) || $user['user_type'] === 'admin')) {
        return $user;
    }

    // Detect API/AJAX callers so we return JSON instead of an HTML redirect.
    $uri    = $_SERVER['REQUEST_URI'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xrw    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $isApi  = strpos($uri, '/api/') !== false
        || strtolower($xrw) === 'xmlhttprequest'
        || strpos($accept, 'application/json') !== false;

    if ($isApi) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in with the correct role.']);
        exit;
    }

    header('Location: /Merobhoj/admin/login.php');
    exit;
}

/** Lazily-created per-session CSRF token for panel mutations. */
function panel_csrf_token(): string
{
    if (empty($_SESSION['panel_csrf'])) {
        $_SESSION['panel_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['panel_csrf'];
}

/** Constant-time CSRF verification. */
function verify_panel_csrf(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['panel_csrf'])
        && hash_equals($_SESSION['panel_csrf'], $token);
}

/** Absolute dashboard URL for a given panel role (used by login + logout routing). */
function panel_home_for_role(string $role): string
{
    switch ($role) {
        case 'chef':    return '/Merobhoj/chef/dashboard.php';
        case 'staff':   return '/Merobhoj/staff/dashboard.php';
        case 'rider':   return '/Merobhoj/rider/dashboard.php';
        case 'admin':
        case 'manager': return '/Merobhoj/admin/index.php';
        default:        return '/Merobhoj/admin/login.php';
    }
}
