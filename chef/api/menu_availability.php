<?php
/**
 * Menu availability for the kitchen.
 *   GET  ?action=list        → items grouped by category with current status
 *   POST {menu_id, status}    → set menu.menu_status (chef-controlled availability)
 * Setting an item Out of Stock notifies the admin via role_notifications.
 */
require_once __DIR__ . '/_api_guard.php';

const VALID_MENU_STATUS = ['In Stock', 'Low Stock', 'Out of Stock'];

if (api_is_post()) {
    $input = api_input();
    api_require_csrf($input);

    $menu_id = (int)($input['menu_id'] ?? 0);
    $status  = trim((string)($input['status'] ?? ''));

    if ($menu_id <= 0) {
        api_json(['success' => false, 'message' => 'Invalid menu item']);
    }
    if (!in_array($status, VALID_MENU_STATUS, true)) {
        api_json(['success' => false, 'message' => 'Invalid availability value']);
    }

    // Confirm the item exists (and grab its name for the notification).
    $look = $conn->prepare("SELECT menu_name FROM menu WHERE menu_id = ? LIMIT 1");
    $look->bind_param('i', $menu_id);
    $look->execute();
    $item = $look->get_result()->fetch_assoc();
    $look->close();

    if (!$item) {
        api_json(['success' => false, 'message' => 'Menu item not found']);
    }

    $upd = $conn->prepare("UPDATE menu SET menu_status = ? WHERE menu_id = ?");
    $upd->bind_param('si', $status, $menu_id);
    if (!$upd->execute()) {
        api_json(['success' => false, 'message' => 'Failed to update availability']);
    }
    $upd->close();

    // Alert the admin when the kitchen marks something unavailable.
    if ($status === 'Out of Stock') {
        $title = 'Item out of stock';
        $msg   = ($item['menu_name'] ?? 'An item') . ' was marked Out of Stock by the kitchen.';
        $rid   = (string)$menu_id;
        $url   = '/Merobhoj/admin/menu.php';
        $ntf = $conn->prepare("INSERT INTO role_notifications (role, type, title, message, resource_id, url)
                               VALUES ('admin', 'menu', ?, ?, ?, ?)");
        if ($ntf) {
            $ntf->bind_param('ssss', $title, $msg, $rid, $url);
            $ntf->execute();
            $ntf->close();
        }
    }

    api_json(['success' => true, 'message' => 'Availability updated', 'menu_id' => $menu_id, 'status' => $status]);
}

// GET list
$res = $conn->query("SELECT menu_id, menu_name, menu_price, menu_category, menu_status, menu_image
                     FROM menu ORDER BY menu_category, menu_name");
$grouped = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $cat = $row['menu_category'] ?: 'other';
        $grouped[$cat][] = [
            'menu_id'   => (int)$row['menu_id'],
            'name'      => $row['menu_name'],
            'price'     => (int)$row['menu_price'],
            'category'  => $cat,
            'status'    => $row['menu_status'],
            'image'     => $row['menu_image'] ? '../assets/img/menu/' . basename($row['menu_image']) : '',
        ];
    }
}

api_json(['success' => true, 'categories' => $grouped]);
