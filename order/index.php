<?php
// c:\xampp\htdocs\Merobhoj\order\index.php
// This is placed in the /order/ directory so Apache can serve it directly.
// It reads the token from the URL path and delegates to order_qr.php.

session_start();
ini_set('memory_limit', '256M');

// Extract token from the URL path.
// URL format: /Merobhoj/order/{token}
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Remove base prefix
$base = '/Merobhoj/order/';
if (strpos($uri, $base) === 0) {
    $token = trim(substr($uri, strlen($base)), '/');
} else {
    $token = '';
}

// Sanitise – only allow hex tokens
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    http_response_code(400);
    die('<h2 style="font-family:sans-serif;text-align:center;margin-top:3rem">Invalid or expired QR code.</h2>');
}

$_GET['token'] = $token;

require __DIR__ . '/../order_qr.php';
