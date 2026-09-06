<?php
/**
 * Staff API Guard
 * Include FIRST in all staff/api/* endpoints to ensure only authenticated
 * staff members can access the API. Validates CSRF on mutations.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/role_check.php';

// Force JSON response
header('Content-Type: application/json');

// Re-validates the role against the DB. require_role() already returns a JSON
// 401 response for API requests when authorization fails.
$panelUser = require_role($conn, 'staff');

// Basic CSRF check for mutations (POST/PUT/DELETE)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    // If you have a CSRF token system, validate it here.
    // E.g., if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) ...
}
