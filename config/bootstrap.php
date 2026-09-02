<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// ── Restaurant Settings ──────────────────────────────────────────────────────
date_default_timezone_set('Asia/Kathmandu');
define('RESTAURANT_TIMEZONE', 'Asia/Kathmandu');
define('RESTAURANT_OPEN_HOUR', 7);    // 7:00 AM
define('RESTAURANT_CLOSE_HOUR', 23);  // 11:00 PM
define('NO_SHOW_GRACE_MINUTES', 20);
define('BOOKING_STATUSES', ['Pending', 'Confirmed', 'Checked-in', 'Completed', 'Cancelled', 'No-show']);

// ── Site Paths ───────────────────────────────────────────────────────────────
define('SITE_ROOT', str_replace('\\', '/', realpath(__DIR__ . '/..')));
define('BASE_URL', '/Merobhoj');

// TODO: Replace with your actual Google OAuth Client ID from
// https://console.cloud.google.com/apis/credentials
define('GOOGLE_CLIENT_ID', '509413212738-dtagt4qc4cbj5c4v9aabar78jrl3h3o4.apps.googleusercontent.com');

// Use the official Google Identity Services button when a valid client ID is configured.
define('GOOGLE_USE_FALLBACK', false);

function url(string $path = ''): string {
    $path = ltrim($path, '/');
    return BASE_URL . '/' . $path;
}

function asset(string $path): string {
    $filePath = SITE_ROOT . '/assets/' . ltrim($path, '/');
    $version = file_exists($filePath) ? filemtime($filePath) : time();
    return url('assets/' . ltrim($path, '/')) . '?v=' . $version;
}

function include_path(string $rel): string {
    return SITE_ROOT . '/' . ltrim($rel, '/');
}
