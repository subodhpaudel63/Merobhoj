<?php
/**
 * Ingredients / kitchen stock.
 *   GET                        → all ingredients (low/out first)
 *   POST {action:'add', name, unit?, quantity?, low_threshold?}
 *   POST {action:'update', id, name, unit, quantity, low_threshold}
 *   POST {action:'adjust', id, delta}      (relative +/- to quantity)
 *   POST {action:'delete', id}
 * Status (Available/Low/Out) is always derived from quantity vs low_threshold.
 */
require_once __DIR__ . '/_api_guard.php';
require_once __DIR__ . '/../../helpers/audit_logger.php';

/** Derive the stock status from quantities. */
function derive_stock_status(float $qty, float $low): string
{
    if ($qty <= 0)   return 'Out';
    if ($qty <= $low) return 'Low';
    return 'Available';
}

if (api_is_post()) {
    $input  = api_input();
    api_require_csrf($input);
    $action = trim((string)($input['action'] ?? ''));

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) api_json(['success' => false, 'message' => 'Invalid ingredient']);
        $snapshot = $conn->prepare("SELECT name, unit, quantity FROM ingredients WHERE id = ?");
        $snapshot->bind_param('i', $id);
        $snapshot->execute();
        $deletedItem = $snapshot->get_result()->fetch_assoc();
        $snapshot->close();
        $del = $conn->prepare("DELETE FROM ingredients WHERE id = ?");
        $del->bind_param('i', $id);
        $ok = $del->execute();
        $del->close();
        if ($ok && $deletedItem) {
            log_audit_action($conn, $panelUser['id'], $panelUser['user_type'], 'DELETE_ITEM', 'inventory', $id, $deletedItem);
        }
        api_json(['success' => (bool)$ok, 'message' => $ok ? 'Ingredient removed' : 'Delete failed']);
    }

    if ($action === 'adjust') {
        $id    = (int)($input['id'] ?? 0);
        $delta = (float)($input['delta'] ?? 0);
        if ($id <= 0) api_json(['success' => false, 'message' => 'Invalid ingredient']);

        $look = $conn->prepare("SELECT quantity, low_threshold FROM ingredients WHERE id = ? LIMIT 1");
        $look->bind_param('i', $id);
        $look->execute();
        $cur = $look->get_result()->fetch_assoc();
        $look->close();
        if (!$cur) api_json(['success' => false, 'message' => 'Ingredient not found']);

        $qty    = max(0, (float)$cur['quantity'] + $delta);
        $low    = (float)$cur['low_threshold'];
        $status = derive_stock_status($qty, $low);

        $upd = $conn->prepare("UPDATE ingredients SET quantity = ?, status = ? WHERE id = ?");
        $upd->bind_param('dsi', $qty, $status, $id);
        $ok = $upd->execute();
        $upd->close();
        api_json(['success' => (bool)$ok, 'message' => $ok ? 'Stock updated' : 'Update failed', 'quantity' => $qty, 'status' => $status]);
    }

    // add / update share these fields
    $name = trim((string)($input['name'] ?? ''));
    $unit = trim((string)($input['unit'] ?? 'unit')) ?: 'unit';
    $qty  = max(0, (float)($input['quantity'] ?? 0));
    $low  = max(0, (float)($input['low_threshold'] ?? 0));
    $status = derive_stock_status($qty, $low);

    if ($name === '') api_json(['success' => false, 'message' => 'Ingredient name is required']);

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) api_json(['success' => false, 'message' => 'Invalid ingredient']);
        $upd = $conn->prepare("UPDATE ingredients SET name = ?, unit = ?, quantity = ?, low_threshold = ?, status = ? WHERE id = ?");
        $upd->bind_param('ssddsi', $name, $unit, $qty, $low, $status, $id);
        $ok = $upd->execute();
        $upd->close();
        api_json(['success' => (bool)$ok, 'message' => $ok ? 'Ingredient updated' : 'Update failed']);
    }

    // default: add
    $ins = $conn->prepare("INSERT INTO ingredients (name, unit, quantity, low_threshold, status) VALUES (?, ?, ?, ?, ?)");
    $ins->bind_param('ssdds', $name, $unit, $qty, $low, $status);
    if (!$ins->execute()) api_json(['success' => false, 'message' => 'Failed to add ingredient']);
    $newId = $ins->insert_id;
    $ins->close();
    api_json(['success' => true, 'message' => 'Ingredient added', 'id' => $newId]);
}

// GET list — surface Out then Low then Available.
$rows = [];
$res = $conn->query("SELECT id, name, unit, quantity, low_threshold, status, updated_at
                     FROM ingredients
                     ORDER BY FIELD(status,'Out','Low','Available'), name");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id'            => (int)$row['id'],
            'name'          => $row['name'],
            'unit'          => $row['unit'],
            'quantity'      => (float)$row['quantity'],
            'low_threshold' => (float)$row['low_threshold'],
            'status'        => $row['status'],
            'updated_at'    => $row['updated_at'],
        ];
    }
}
api_json(['success' => true, 'ingredients' => $rows]);
