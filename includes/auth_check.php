<?php
declare(strict_types=1);




function encrypt(string $data, string $key): string {
    $iv = openssl_random_pseudo_bytes(16);
    $cipher = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $cipher);
}

// ── AES‑256‑CBC decrypt ───────────────────────────────────────────────────────
function decrypt(string $data, string $key): string|false
{
    $decoded = base64_decode($data, true);
    if ($decoded === false || strlen($decoded) < 17) {
        return false;                           // malformed input
    }
    $iv         = substr($decoded, 0, 16);
    $ciphertext = substr($decoded, 16);
    return openssl_decrypt($ciphertext, 'AES-256-CBC', $key, 0, $iv);
}

// ── Config ────────────────────────────────────────────────────────────────────
const MAX_SESSION_TIME = 86_400;                    // 24 h
const SECRET_KEY       = 'mysecretkey';

/**
 * Verify auth cookies & session lifetime.
 * Redirects to /login on failure; otherwise returns
 *     ['email' => string, 'userType' => 'admin'|'user']
 */
function requireLogin(): array     
{
    // 1️⃣ Cookies present?
    if (!isset($_COOKIE['email'], $_COOKIE['user_type'], $_COOKIE['login_time'])) {
    header('Location: /Merobhoj/login.php');
        exit();
    }

    // 2️⃣ Decrypt
    $email     = decrypt($_COOKIE['email'],     SECRET_KEY);
    $userType  = decrypt($_COOKIE['user_type'], SECRET_KEY);
    $loginTime = decrypt($_COOKIE['login_time'], SECRET_KEY);

    // 3️⃣ Validate
    if (!$email || !$userType || !$loginTime || !ctype_digit($loginTime)) {
        header('Location: /Merobhoj/login.php');
        exit();
    }

    // 4️⃣ Max session age
    if (time() - (int) $loginTime > MAX_SESSION_TIME) {
    header('Location: /Merobhoj/login.php?session_expired=1');
        exit();
    }

    return ['email' => $email, 'userType' => $userType];
}

/**
 * Call once, right after a successful login, to send the user to
 * their dashboard.
 */
function routeAfterLogin(string $userType): void    // ← use "void"
{
    switch ($userType) {
        case 'admin':
            header('Location: /Merobhoj/admin/index.php');
            break;
        case 'user':
            header('Location: /Merobhoj/client/index.php');
            break;
        default:
            header('Location: /Merobhoj/login.php');
            break;
    }
    exit();
}

/**
 * Returns user info from cookies if logged in, or null if not. Never redirects.
 * @return array|null
 */
function getUserFromCookie(): ?array
{
    if (!isset($_COOKIE['email'], $_COOKIE['user_type'], $_COOKIE['login_time'])) {
        return null;
    }
    $email     = decrypt($_COOKIE['email'],     SECRET_KEY);
    $userType  = decrypt($_COOKIE['user_type'], SECRET_KEY);
    $loginTime = decrypt($_COOKIE['login_time'], SECRET_KEY);
    if (!$email || !$userType || !$loginTime || !ctype_digit($loginTime)) {
        return null;
    }
    if (time() - (int) $loginTime > MAX_SESSION_TIME) {
        return null;
    }
    return ['email' => $email, 'userType' => $userType];
}