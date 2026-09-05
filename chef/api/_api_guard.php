<?php
/**
 * Shared guard + helpers for every chef API endpoint.
 * Include FIRST. Enforces JSON output, chef role (admin override), and gives
 * CSRF + input helpers. Keeps display_errors off so JSON is never corrupted.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/role_check.php';

// 401 JSON (require_role detects the /api/ path) if not chef or admin.
$panelUser = require_role($conn, 'chef');

/** Send a JSON response and stop. */
function api_json(array $payload): void
{
    echo json_encode($payload);
    exit;
}

/** Merge JSON body + form POST into one associative array. */
function api_input(): array
{
    $data = $_POST;
    $raw  = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }
    return $data;
}

/** Enforce a valid panel CSRF token for state-changing requests (403 on fail). */
function api_require_csrf(array $input): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf'] ?? null);
    if (!verify_panel_csrf(is_string($token) ? $token : null)) {
        http_response_code(403);
        api_json(['success' => false, 'message' => 'Invalid or missing CSRF token.']);
    }
}

/** True for POST/PUT/DELETE-style mutating requests. */
function api_is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}
