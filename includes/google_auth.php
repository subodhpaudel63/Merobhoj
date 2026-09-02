<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$idToken = trim((string)($input['id_token'] ?? ''));

if ($idToken === '') {
    echo json_encode(['success' => false, 'message' => 'Missing Google ID token']);
    exit();
}

$tokenInfoUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tokenInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development, it's sometimes needed if SSL certs are missing
$response = curl_exec($ch);
curl_close($ch);

if ($response === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to verify token with Google']);
    exit();
}

$payload = json_decode($response, true);
if (!$payload || isset($payload['error_description'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid Google token']);
    exit();
}

if (($payload['aud'] ?? null) !== GOOGLE_CLIENT_ID) {
    echo json_encode(['success' => false, 'message' => 'Google token is not valid for this app']);
    exit();
}

if (($payload['iss'] ?? '') !== 'https://accounts.google.com' && ($payload['iss'] ?? '') !== 'accounts.google.com') {
    echo json_encode(['success' => false, 'message' => 'Google token issuer is invalid']);
    exit();
}

$googleId   = trim((string)($payload['sub'] ?? ''));
$email      = trim((string)($payload['email'] ?? ''));
$name       = trim((string)($payload['name'] ?? ''));
$picture    = trim((string)($payload['picture'] ?? ''));

if ($email === '' || $googleId === '') {
    echo json_encode(['success' => false, 'message' => 'Incomplete Google profile data']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email from Google']);
    exit();
}

try {
    $conn->begin_transaction();

    // Try to find existing user by google_id
    $stmt = $conn->prepare('SELECT id, email, password, user_type, user_img FROM users WHERE google_id = ? LIMIT 1');
    $stmt->bind_param('s', $googleId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        // Try to find by email
        $stmt = $conn->prepare('SELECT id, email, password, user_type, user_img FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user) {
            // Link existing account with Google
            $stmt = $conn->prepare('UPDATE users SET google_id = ? WHERE id = ?');
            $stmt->bind_param('si', $googleId, $user['id']);
            $stmt->execute();
            $stmt->close();
        } else {
            // Create new user
            $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $userType = 'user';
            $defaultImg = '../assets/img/usersprofiles/profilepic.jpg';
            $userImg = $picture !== '' ? $picture : $defaultImg;

            $stmt = $conn->prepare(
                'INSERT INTO users (email, password, user_type, user_img, google_id) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('sssss', $email, $randomPassword, $userType, $userImg, $googleId);
            $stmt->execute();
            $userId = $stmt->insert_id;
            $stmt->close();

            $user = [
                'id' => $userId,
                'email' => $email,
                'password' => $randomPassword,
                'user_type' => $userType,
                'user_img' => $userImg,
            ];
        }
    }

    $conn->commit();

    // Set auth cookies using existing mechanism
    $now          = time();
    $cookieMaxAge = 86400;

    setcookie('email',      encrypt($user['email'],     SECRET_KEY), $now + $cookieMaxAge, '/', '', false, true);
    setcookie('user_type',  encrypt($user['user_type'], SECRET_KEY), $now + $cookieMaxAge, '/', '', false, true);
    setcookie('login_time', encrypt((string) $now,      SECRET_KEY), $now + $cookieMaxAge, '/', '', false, true);
    setcookie('user_img',   encrypt($user['user_img'] ?? '', SECRET_KEY), $now + $cookieMaxAge, '/', '', false, true);

    $lastLoginCol = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $lastLoginCol = true;
    }
    if ($lastLoginCol) {
        $updateLogin = $conn->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        if ($updateLogin) {
            $updateLogin->bind_param('i', $user['id']);
            $updateLogin->execute();
            $updateLogin->close();
        }
    }

    $_SESSION['msg'] = ['type' => 'success', 'text' => 'Signed in with Google successfully!'];

    $redirect = '/Merobhoj/client/index.php';
    if ($user['user_type'] === 'admin') {
        $redirect = '/Merobhoj/admin/index.php';
    }

    echo json_encode([
        'success'  => true,
        'message'  => 'Google sign-in successful',
        'redirect' => $redirect,
    ]);
    exit();
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit();
}
