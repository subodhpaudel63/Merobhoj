<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('SITE_ROOT', str_replace('\\', '/', realpath(__DIR__ . '/..')));
define('BASE_URL', '/Merobhoj');

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


