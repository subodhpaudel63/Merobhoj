<?php
declare(strict_types=1);
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/role_check.php';
require_once __DIR__ . '/../../helpers/audit_logger.php';
$user = require_role($conn, ['admin', 'manager']);

function supplier_json(array $data): never { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function supplier_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    supplier_json(['success' => false, 'message' => $message]);
}
function supplier_audit(mysqli $conn, array $user, string $action, int $id, array $details): ?string
{
    try {
        log_audit_action($conn, (int)$user['id'], (string)$user['user_type'], $action, 'supplier', $id, $details);
        return null;
    } catch (Throwable $error) {
        error_log('Supplier audit log failed: ' . $error->getMessage());
        return 'Supplier saved, but the audit entry could not be written.';
    }
}
function supplier_input(): array {
    $data = $_POST; $raw = file_get_contents('php://input');
    if ($raw) { $json = json_decode($raw, true); if (is_array($json)) $data = array_merge($data, $json); }
    return $data;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $q = trim((string)($_GET['q'] ?? '')); $status = $_GET['status'] ?? '';
    $sql = "SELECT * FROM suppliers WHERE (? = '' OR name LIKE CONCAT('%', ?, '%') OR phone LIKE CONCAT('%', ?, '%') OR email LIKE CONCAT('%', ?, '%')) AND (? = '' OR status = ?) ORDER BY name";
    $stmt = $conn->prepare($sql);
    if (!$stmt) supplier_fail('Unable to prepare supplier search.', 500);
    $stmt->bind_param('ssssss', $q, $q, $q, $q, $status, $status);
    if (!$stmt->execute()) supplier_fail('Unable to load suppliers.', 500);
    $rows = []; $result = $stmt->get_result(); while ($row = $result->fetch_assoc()) $rows[] = $row; $stmt->close();
    supplier_json(['success' => true, 'suppliers' => $rows]);
}
$input = supplier_input(); $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf'] ?? null);
if (!verify_panel_csrf(is_string($token) ? $token : null)) { http_response_code(403); supplier_json(['success'=>false,'message'=>'Invalid CSRF token.']); }
$action = (string)($input['action'] ?? '');
$id = (int)($input['id'] ?? 0);
if ($action === 'deactivate') {
    if ($id <= 0) supplier_fail('Invalid supplier.');
    $stmt = $conn->prepare("UPDATE suppliers SET status = 'inactive' WHERE id = ?");
    if (!$stmt) supplier_fail('Unable to prepare supplier update.', 500);
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        error_log('Supplier deactivate failed: ' . $stmt->error);
        supplier_fail('Unable to deactivate supplier.', 500);
    }
    $stmt->close();
    $warning = supplier_audit($conn, $user, 'DEACTIVATE_SUPPLIER', $id, []);
    supplier_json(['success'=>true] + ($warning ? ['warning'=>$warning] : []));
}
$name = trim((string)($input['name'] ?? '')); $phone = trim((string)($input['phone'] ?? ''));
if ($name === '' || $phone === '') supplier_json(['success'=>false,'message'=>'Supplier name and phone are required.']);
$contact = trim((string)($input['contact_person'] ?? '')); $email = trim((string)($input['email'] ?? '')); $address = trim((string)($input['address'] ?? ''));
if ($action === 'update' && $id > 0) {
    $stmt = $conn->prepare('UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=? WHERE id=?');
    if (!$stmt) supplier_fail('Unable to prepare supplier update.', 500);
    $stmt->bind_param('sssssi', $name, $contact, $phone, $email, $address, $id);
    if (!$stmt->execute()) {
        error_log('Supplier update failed: ' . $stmt->error);
        supplier_fail('Unable to update supplier.', 500);
    }
    $stmt->close();
    $warning = supplier_audit($conn, $user, 'UPDATE_SUPPLIER', $id, ['name'=>$name]);
    supplier_json(['success'=>true] + ($warning ? ['warning'=>$warning] : []));
}
$stmt = $conn->prepare('INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)');
if (!$stmt) supplier_fail('Unable to prepare supplier save.', 500);
$stmt->bind_param('sssss', $name, $contact, $phone, $email, $address);
if (!$stmt->execute()) {
    error_log('Supplier insert failed: ' . $stmt->error);
    supplier_fail('Unable to save supplier.', 500);
}
$newId = $conn->insert_id;
$stmt->close();
$warning = supplier_audit($conn, $user, 'CREATE_SUPPLIER', (int)$newId, ['name'=>$name]);
supplier_json(['success'=>true,'id'=>$newId] + ($warning ? ['warning'=>$warning] : []));
