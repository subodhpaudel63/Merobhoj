<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

// Handle form submissions
$message = '';
$message_type = '';

// Handle menu item creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create':
            // Handle image upload
            $image_path = '';
            if (isset($_FILES['menu_image']) && $_FILES['menu_image']['error'] === 0) {
                $upload_dir = '../assets/img/menu/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_extension = pathinfo($_FILES['menu_image']['name'], PATHINFO_EXTENSION);
                $filename = 'menu_' . uniqid() . '.' . $file_extension;
                $image_path = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['menu_image']['tmp_name'], $image_path)) {
                    $image_path = 'assets/img/menu/' . $filename;
                }
            }
            
            $menu_status = in_array($_POST['menu_status'] ?? 'In Stock', ['In Stock', 'Low Stock', 'Out of Stock'], true) ? $_POST['menu_status'] : 'In Stock';
            $stmt = $conn->prepare("INSERT INTO menu (menu_name, menu_description, menu_price, menu_category, menu_status, menu_image) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdsss", 
                $_POST['menu_name'],
                $_POST['menu_description'],
                $_POST['menu_price'],
                $_POST['menu_category'],
                $menu_status,
                $image_path
            );
            
            if ($stmt->execute()) {
                $message = 'Menu item created successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error creating menu item: ' . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
            break;
            
        case 'update':
            // Handle image update
            $image_path = $_POST['existing_image'] ?? '';
            if (isset($_FILES['menu_image']) && $_FILES['menu_image']['error'] === 0) {
                $upload_dir = '../assets/img/menu/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_extension = pathinfo($_FILES['menu_image']['name'], PATHINFO_EXTENSION);
                $filename = 'menu_' . uniqid() . '.' . $file_extension;
                $image_path = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['menu_image']['tmp_name'], $image_path)) {
                    $image_path = 'assets/img/menu/' . $filename;
                }
            }
            
            $menu_status = in_array($_POST['menu_status'] ?? 'In Stock', ['In Stock', 'Low Stock', 'Out of Stock'], true) ? $_POST['menu_status'] : 'In Stock';
            $stmt = $conn->prepare("UPDATE menu SET menu_name = ?, menu_description = ?, menu_price = ?, menu_category = ?, menu_status = ?, menu_image = ? WHERE menu_id = ?");
            $stmt->bind_param("ssdsssi",
                $_POST['menu_name'],
                $_POST['menu_description'],
                $_POST['menu_price'],
                $_POST['menu_category'],
                $menu_status,
                $image_path,
                $_POST['menu_id']
            );
            
            if ($stmt->execute()) {
                $message = 'Menu item updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error updating menu item: ' . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
            break;
            
        case 'delete':
            // Get image path to delete file
            $stmt = $conn->prepare("SELECT menu_image FROM menu WHERE menu_id = ?");
            $stmt->bind_param("i", $_POST['menu_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $menu_item = $result->fetch_assoc();
            
            // Delete image file if exists
            if ($menu_item && !empty($menu_item['menu_image'])) {
                $image_path = '../' . $menu_item['menu_image'];
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
            
            // Delete from database
            $stmt = $conn->prepare("DELETE FROM menu WHERE menu_id = ?");
            $stmt->bind_param("i", $_POST['menu_id']);
            
            if ($stmt->execute()) {
                $message = 'Menu item deleted successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error deleting menu item: ' . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
            break;
    }
}

// Fetch all menu items
$menu_items = [];
$res = $conn->query("SELECT * FROM menu ORDER BY menu_category, menu_name");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $menu_items[] = $row;
    }
}

$search_query = trim($_GET['search'] ?? '');
$selected_category = trim($_GET['category'] ?? '');
$selected_status = trim($_GET['status'] ?? '');
$selected_sort = trim($_GET['sort'] ?? 'category_asc');

$available_categories = array_values(array_unique(array_filter(array_map(
    static fn(array $item): string => strtolower(trim((string) ($item['menu_category'] ?? ''))),
    $menu_items
))));
sort($available_categories);

$filtered_menu_items = array_filter($menu_items, function ($item) use ($search_query, $selected_category, $selected_status) {
    $matches_search = true;
    $matches_category = true;

    if ($search_query !== '') {
        $haystack = strtolower(
            ($item['menu_name'] ?? '') . ' ' .
            ($item['menu_description'] ?? '') . ' ' .
            ($item['menu_category'] ?? '')
        );
        $matches_search = strpos($haystack, strtolower($search_query)) !== false;
    }

    if ($selected_category !== '') {
        $matches_category = strtolower((string)($item['menu_category'] ?? '')) === strtolower($selected_category);
    }

    $matches_status = $selected_status === '' || ($item['menu_status'] ?? 'In Stock') === $selected_status;

    return $matches_search && $matches_category && $matches_status;
});

usort($filtered_menu_items, function ($a, $b) use ($selected_sort) {
    switch ($selected_sort) {
        case 'name_asc':
            return strcmp($a['menu_name'] ?? '', $b['menu_name'] ?? '');
        case 'name_desc':
            return strcmp($b['menu_name'] ?? '', $a['menu_name'] ?? '');
        case 'price_asc':
            return (float)($a['menu_price'] ?? 0) <=> (float)($b['menu_price'] ?? 0);
        case 'price_desc':
            return (float)($b['menu_price'] ?? 0) <=> (float)($a['menu_price'] ?? 0);
        case 'category_desc':
            return strcmp($b['menu_category'] ?? '', $a['menu_category'] ?? '');
        case 'category_asc':
        default:
            $category_compare = strcmp($a['menu_category'] ?? '', $b['menu_category'] ?? '');
            if ($category_compare !== 0) {
                return $category_compare;
            }
            return strcmp($a['menu_name'] ?? '', $b['menu_name'] ?? '');
    }
});

// Group by category for easier display
$menu_by_category = [];
foreach ($filtered_menu_items as $item) {
    $category = $item['menu_category'];
    if (!isset($menu_by_category[$category])) {
        $menu_by_category[$category] = [];
    }
    $menu_by_category[$category][] = $item;
}

$categories = $available_categories;
$categoryCounts = array_count_values(array_map(static fn(array $item): string => strtolower((string) ($item['menu_category'] ?? '')), $menu_items));
$totalFilteredItems = count($filtered_menu_items);
$itemsPerPage = 8;
$totalPages = max(1, (int) ceil($totalFilteredItems / $itemsPerPage));
$currentPage = max(1, min((int) ($_GET['page'] ?? 1), $totalPages));
$pageItems = array_slice($filtered_menu_items, ($currentPage - 1) * $itemsPerPage, $itemsPerPage);
$queryParams = ['search' => $search_query, 'category' => $selected_category, 'status' => $selected_status, 'sort' => $selected_sort];
$buildMenuUrl = static function (array $overrides = []) use ($queryParams): string {
    $params = array_filter(array_merge($queryParams, $overrides), static fn($value) => $value !== '' && $value !== null && $value !== 1);
    return 'menu.php' . ($params ? '?' . http_build_query($params) : '');
};
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Menu Management - Mero Bhoj</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  
  <style>
    :root {
        --mkj-bg: #fafbfc;
        --mkj-card: #ffffff;
        --mkj-border: #e8ecf2;
        --mkj-text: #172033;
        --mkj-muted: #667085;
        --mkj-soft: #98a2b3;
        --mkj-primary: #ff4f1f;
        --mkj-primary-dark: #e94318;
        --mkj-success: #16a34a;
        --mkj-warning: #f59e0b;
        --mkj-danger: #ef4444;
        --mkj-blue: #2563eb;
    }

    main {
        margin-top: 0;
        padding: 0;
        width: auto;
    }

    body {
        background: var(--mkj-bg);
        font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    aside {
        position: sticky;
        top: 0;
        height: 100vh;
        border-right: 1px solid var(--mkj-border);
        background: #fff;
    }

    aside .top {
        margin-top: 0;
        padding: 1rem 1.1rem;
        min-height: 61px;
        border-bottom: 1px solid var(--mkj-border);
    }

    aside .sidebar {
        top: 0;
        height: calc(100vh - 61px);
        padding-top: 0.8rem;
        background: #fff;
    }

    aside .logo {
        gap: 0;
    }

    aside .logo h2 {
        font-size: 1.2rem;
        line-height: 1;
        font-weight: 800;
        letter-spacing: -0.03em;
    }

    aside .sidebar a {
        margin-left: 1rem;
        margin-right: 0.6rem;
        border-radius: 0 10px 10px 0;
        height: 3.05rem;
        font-size: 0.9rem;
    }

    aside .sidebar a.active {
        background: #fff4ec;
        border-left-color: var(--mkj-primary);
        color: var(--mkj-primary);
    }

    aside .sidebar a span.msg_count {
        background: #ff3b30;
    }

    .page-shell {
        padding: 0 1.5rem 2rem 1.5rem;
    }

    .topbar {
        height: 62px;
        background: #fff;
        border-bottom: 1px solid var(--mkj-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 1.2rem 0 0.4rem;
        margin: 0 -1.5rem 1.45rem -1.5rem;
    }

    .topbar-left,
    .topbar-right {
        display: flex;
        align-items: center;
        gap: 0.85rem;
    }

    .icon-btn {
        width: 34px;
        height: 34px;
        border: 1px solid #e2e7ef;
        background: #fff;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #475467;
    }

    .searchbox {
        width: 196px;
        height: 36px;
        border: 1px solid #e2e7ef;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0 0.8rem;
        color: #98a2b3;
        background: #fff;
    }

    .searchbox input {
        border: 0;
        outline: 0;
        width: 100%;
        font-size: 0.88rem;
        color: var(--mkj-text);
    }

    .notif {
        position: relative;
    }

    .notif .dot {
        position: absolute;
        top: -3px;
        right: -2px;
        width: 16px;
        height: 16px;
        border-radius: 999px;
        background: #ff3b30;
        color: #fff;
        font-size: 0.65rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
    }

    .profile-chip {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        margin-left: 0.15rem;
    }

    .profile-chip img {
        width: 38px;
        height: 38px;
        border-radius: 999px;
        object-fit: cover;
    }

    .profile-chip b {
        display: block;
        color: var(--mkj-text);
        font-size: 0.86rem;
        line-height: 1.1;
    }

    .profile-chip small {
        color: var(--mkj-muted);
        font-size: 0.72rem;
    }

    .page-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .page-head h1 {
        margin: 0;
        font-size: 2.05rem;
        line-height: 1.08;
        letter-spacing: -0.03em;
        color: var(--mkj-text);
    }

    .page-head p {
        margin: 0.45rem 0 0;
        color: var(--mkj-muted);
        font-size: 0.93rem;
    }

    .top-actions {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        flex-wrap: wrap;
        padding-top: 0.12rem;
    }

    .btn-view,
    .btn-add,
    .btn-primary,
    .btn-secondary,
    .pill,
    .btn-mini,
    .view-toggle button {
        border-radius: 8px;
    }

    .btn-view {
        height: 40px;
        padding: 0 1rem;
        border: 1px solid #d7dde7;
        background: #fff;
        color: #1f2937;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        box-shadow: 0 1px 3px rgba(16, 24, 40, 0.04);
    }

    .btn-add {
        height: 40px;
        padding: 0 1rem;
        border: 1px solid transparent;
        background: var(--mkj-primary);
        color: #fff;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        box-shadow: 0 4px 12px rgba(255, 79, 31, 0.18);
    }

    .stats-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr 270px;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .stat-card,
    .overview-card,
    .menu-filters,
    .panel {
        background: var(--mkj-card);
        border: 1px solid var(--mkj-border);
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(16, 24, 40, 0.04);
    }

    .stat-card {
        min-height: 122px;
        padding: 0.95rem 1rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .stats-row {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr)) 1.15fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .stat-card,
    .overview-card,
    .menu-filters,
    .panel-card {
        background: #fff;
        border: 1px solid #edf1f6;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
    }

    .stat-card {
        padding: 1rem 1.05rem;
        min-height: 118px;
    }

    .stat-card .label {
        display: block;
        color: #64748b;
        font-weight: 600;
        font-size: 0.84rem;
        margin-bottom: 0.55rem;
    }

    .stat-card strong {
        display: block;
        color: #0f172a;
        font-size: 1.55rem;
        line-height: 1;
    }

    .stat-card small {
        display: inline-block;
        margin-top: 0.45rem;
        color: #16a34a;
        font-weight: 600;
    }

    .overview-card { padding: 1rem; }
    .overview-head { font-weight: 800; color: #101828; margin-bottom: 0.7rem; }
    .overview-row { display: flex; gap: 1rem; align-items: center; }
    .donut {
        width: 78px; height: 78px; border-radius: 50%;
        background: conic-gradient(#16a34a 0 78%, #f59e0b 78% 91%, #ef4444 91% 100%);
        position: relative;
        flex: none;
    }
    .donut::after { content: ''; position: absolute; inset: 15px; border-radius: 50%; background: #fff; }
    .legend-list { display: grid; gap: 0.4rem; width: 100%; }
    .legend-item { display:flex; justify-content:space-between; align-items:center; font-size:0.82rem; color:#667085; }
    .legend-left { display:flex; align-items:center; gap:0.45rem; }
    .legend-dot { width:8px; height:8px; border-radius:50%; display:inline-block; }

    .menu-card {
        border: 1px solid #edf1f6;
        background: #fff;
        display: grid;
        grid-template-columns: 120px 1fr;
        min-height: 172px;
        overflow: hidden;
        border-radius: 16px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .menu-content {
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 1rem 1rem 1rem 0;
    }

    .stock-badge {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        padding: 0.28rem 0.65rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        margin-bottom: 0.55rem;
        letter-spacing: 0.02em;
    }

    .stock-in-stock { background: #e8f8ed; color: #15803d; }
    .stock-low-stock { background: #fff4df; color: #d97706; }
    .stock-out-of-stock { background: #fee2e2; color: #dc2626; }

    .menu-title {
        padding-right: 0;
    }

    .menu-price {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        color: #f05a22;
        font-weight: 800;
        font-size: 0.98rem;
        margin-top: 0.2rem;
    }

    .btn-edit, .btn-delete, .btn-primary, .btn-secondary, .add-item-btn {
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
    }

    .menu-sections {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
        margin: 0.9rem 0 0;
    }
    
    .menu-image {
        height: 100%;
        min-height: 170px;
        background: linear-gradient(135deg, #fef2e9, #fff7f1);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #f05a22;
        font-size: 3rem;
    }
    
    .menu-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    
    .menu-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; }
    .toolbar-row { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; margin: 0.9rem 0; }
    .category-pills { display:flex; gap:0.75rem; flex-wrap:wrap; }
    .pill { display:inline-flex; align-items:center; gap:0.45rem; height:42px; padding:0 1rem; border-radius:10px; border:1px solid #e6eaf0; background:#fff; color:#344054; font-weight:700; font-size:0.9rem; }
    .pill.active { background:#fff4ec; border-color:#ffd9c2; color:#f05a22; }
    .pill .count { min-width:22px; height:22px; border-radius:999px; background:#f2f4f7; display:inline-flex; align-items:center; justify-content:center; font-size:0.75rem; color:#475467; }
    .view-toggle { display:flex; align-items:center; background:#f2f4f7; border-radius:12px; overflow:hidden; border:1px solid #eaecf0; }
    .view-toggle button { width:48px; height:40px; background:transparent; color:#667085; cursor:pointer; font-size:1.1rem; }
    .view-toggle button.active { background:#fff; color:#f05a22; }

    .menu-description {
        color: #64748b;
        font-size: 0.92rem;
        margin: 0 0 1rem 0;
        line-height: 1.55;
    }
    
    .menu-title {
        font-size: 1.15rem;
        font-weight: 700;
        margin: 0 0 0.45rem 0;
        color: #1f2937;
    }
    
    .menu-price {
        font-size: 1.05rem;
        font-weight: 700;
        color: #ff6a00;
        margin: 0 0 1rem 0;
        width: fit-content;
    }
    
    .menu-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .btn-edit, .btn-delete {
        flex: 1;
        padding: 0.5rem;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
    }

    .menu-card .menu-actions {
        margin-top: auto;
    }
    
    .btn-edit {
        background: #2196F3;
        color: white;
    }
    
    .btn-edit:hover {
        background: #1976D2;
    }
    
    .btn-delete {
        background: #f44336;
        color: white;
    }
    
    .btn-delete:hover {
        background: #d32f2f;
    }

    .btn-primary:disabled,
    .btn-delete:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    
    .modal.show {
        display: flex;
    }
    
    .modal-content {
        background: white;
        border-radius: 15px;
        width: 90%;
        max-width: 500px;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 600;
    }
    
    .close {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #999;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    
    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 1rem;
    }
    
    .form-group textarea {
        min-height: 100px;
        resize: vertical;
    }
    
    .btn-primary {
        background: #ff6a00;
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        transition: background 0.3s ease;
    }
    
    .btn-primary:hover {
        background: #e65f00;
    }
    
    .btn-delete {
        background: #f44336;
        color: white;
    }
    
    .btn-delete:hover {
        background: #d32f2f;
    }
    
    .message {
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
    }
    
    .message.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .message.error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .quick-links {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .quick-links h3 {
        margin-top: 0;
        color: #333;
    }
    
    .quick-links ul {
        list-style: none;
        padding: 0;
    }
    
    .quick-links li {
        margin: 0.5rem 0;
    }
    
    .quick-links a {
        color: #ff6a00;
        text-decoration: none;
        font-weight: 500;
    }
    
    .quick-links a:hover {
        text-decoration: underline;
    }

    .menu-filters { padding: 1rem; margin: 0.25rem 0 1rem; }
    .menu-filters form { display: grid; grid-template-columns: 1.35fr 1fr 1fr 1fr; gap: 0.85rem; align-items: end; }
    .menu-filters .filter-group { display: flex; flex-direction: column; gap: 0.4rem; }
    .menu-filters label { font-weight: 700; color: #344054; font-size: 0.82rem; }
    .menu-filters input, .menu-filters select { width: 100%; height: 42px; padding: 0 0.95rem; border: 1px solid #d8e0ea; border-radius: 10px; font-size: 0.92rem; background: #fff; transition: border-color 0.2s ease, box-shadow 0.2s ease; }

    .menu-filters input:focus,
    .menu-filters select:focus {
        outline: none;
        border-color: #f05a22;
        box-shadow: 0 0 0 4px rgba(240, 90, 34, 0.12);
    }

    .filter-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; justify-content:flex-end; }
    .btn-secondary { background:#f2f4f7; color:#344054; border:1px solid #eaecf0; padding:0.75rem 1.1rem; border-radius:8px; cursor:pointer; font-weight:700; }
    .menu-toolbar { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:1rem; }
    .right { display: none; }

    .filter-summary {
        margin-top: 0.75rem;
        color: #6b7280;
        font-size: 0.9rem;
    }

    /* Final dashboard pass: keeps the menu density and controls aligned with the reference. */
    .page-shell { padding: 0 2.1rem 2.5rem; max-width: 1600px; margin: 0 auto; }
    .page-head { margin-top: 1.8rem; }
    .page-head h1 { font-size: 1.65rem; }
    .page-head p { font-size: .8rem; }
    .stats-row { grid-template-columns: repeat(4, minmax(135px, 1fr)) minmax(250px, 1.35fr); gap: .7rem; }
    .stat-card, .overview-card, .menu-filters, .panel, .panel-card { border-radius: 10px; box-shadow: 0 3px 10px rgba(16, 24, 40, .035); }
    .stat-card { min-height: 120px; padding: 1rem; }
    .stat-card .meta { display:flex; align-items:center; gap:.65rem; }
    .stat-icon { width:40px; height:40px; display:grid; place-items:center; border-radius:10px; background:#e9f9ee; color:#16a34a; }
    .low-stock .stat-icon { background:#fff4e6; color:#f79009; }
    .out-stock .stat-icon { background:#fff0f1; color:#ef4444; }
    .stat-label { font-size:.72rem; font-weight:700; color:#475467; }
    .stat-card strong { padding-left:3.2rem; font-size:1.38rem; }
    .stat-card small { margin:0; padding-left:3.2rem; color:#667085; font-size:.67rem; font-weight:500; }
    .overview-card { padding:1rem; }
    .overview-head { font-size:.78rem; margin-bottom:.85rem; }
    .overview-row { gap:.9rem; }
    .donut { width:64px; height:64px; }
    .donut::after { inset:12px; }
    .legend-item { font-size:.66rem; }
    .menu-filters { padding:1rem 1.1rem !important; margin:1rem 0 .85rem !important; }
    .menu-filters form { grid-template-columns:1.5fr 1fr 1fr 1fr 1.1fr; gap:.8rem; }
    .menu-filters label { font-size:.7rem; color:#344054; }
    .menu-filters input, .menu-filters select { height:34px; border-radius:6px; padding:0 .7rem; font-size:.7rem; }
    .btn-primary, .btn-secondary { height:34px; padding:.45rem .8rem; font-size:.7rem; box-shadow:none; }
    .toolbar-row { margin:.75rem 0; }
    .category-pills { gap:.5rem; }
    .pill { height:36px; padding:0 .7rem; border-radius:7px; text-decoration:none; font-size:.68rem; }
    .pill .count { min-width:17px; height:17px; font-size:.6rem; }
    .view-toggle { border-radius:7px; }
    .view-toggle button { width:42px; height:35px; }
    .menu-grid { grid-template-columns:repeat(4, minmax(0, 1fr)); gap:.75rem; }
    .menu-card { display:flex; flex-direction:column; min-height:0; border-radius:9px; box-shadow:0 2px 7px rgba(16,24,40,.035); overflow:hidden; }
    .menu-card:hover { transform:translateY(-2px); box-shadow:0 10px 18px rgba(16,24,40,.09); }
    .menu-image { height:142px; min-height:142px; width:100%; }
    .menu-content { min-height:218px; padding:.7rem .75rem .75rem; justify-content:flex-start; }
    .stock-badge { position:absolute; top:.55rem; left:.55rem; margin:0; padding:.18rem .42rem; border-radius:4px; font-size:.58rem; background:rgba(232,248,237,.96); }
    .item-select { position:absolute; top:.6rem; right:2.45rem; width:16px; height:16px; z-index:2; cursor:pointer; }
    .item-select input { accent-color:var(--mkj-primary); }
    .item-more { position:absolute; top:.45rem; right:.45rem; width:25px; height:25px; display:grid; place-items:center; background:#fff; border:1px solid #edf0f3; border-radius:5px; color:#344054; cursor:pointer; }
    .item-more span { font-size:17px; }
    .menu-title { margin:.1rem 0 .35rem; padding-right:0; font-size:.76rem; line-height:1.25; color:#182230; }
    .menu-description { margin:0 0 .55rem; min-height:33px; font-size:.61rem; line-height:1.45; color:#667085; }
    .menu-price { margin:.05rem 0 .5rem; font-size:.7rem; color:#ff4f1f; }
    .item-meta { display:grid; gap:.22rem; color:#475467; font-size:.58rem; line-height:1.3; }
    .menu-actions { margin-top:auto !important; gap:.35rem; padding-top:.55rem; }
    .btn-mini { flex:1; height:29px; display:grid; place-items:center; border-radius:5px; cursor:pointer; border:1px solid #e7ecf4; background:#fff; }
    .btn-mini.edit { color:#2563eb; background:#f8fbff; }
    .btn-mini.delete { color:#ef4444; background:#fffafa; }
    .btn-mini.more { color:#344054; }
    .pagination-row { display:flex; align-items:center; justify-content:space-between; margin: .8rem 0; }
    .pagination { display:flex; gap:.35rem; }
    .page-btn { width:28px; height:28px; display:grid; place-items:center; border:1px solid #e6eaf0; border-radius:5px; color:#475467; text-decoration:none; font-size:.67rem; background:#fff; }
    .page-btn.active { background:#ff4f1f; color:#fff; border-color:#ff4f1f; }
    .page-btn.disabled { opacity:.45; pointer-events:none; }
    .page-btn .material-symbols-sharp { font-size:17px; }
    .filter-summary { margin:0; font-size:.65rem; }
    .menu-toolbar { min-height:50px; padding:.65rem .8rem; background:#fff; border:1px solid #e8ecf2; border-radius:8px; }
    .select-count { display:flex; gap:1rem; align-items:center; color:#667085; font-size:.65rem; }
    .select-count label { color:#344054; font-weight:600; }
    .delete-selected { background:#fff1f0 !important; color:#ef4444 !important; border:1px solid #ffd9d5; box-shadow:none; }
    .layout-bottom { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:.8rem; }
    .panel { padding:.9rem; }
    .panel h3 { margin:0 0 .65rem; font-size:.9rem; color:#172033; }
    .panel table { font-size:.7rem; }

    .panel-heading { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:.35rem; }
    .panel-heading h3 { margin:0; }
    .panel-link { border:1px solid #e6eaf0; color:#475467; background:#fff; border-radius:5px; padding:.36rem .62rem; font-size:.66rem; font-weight:700; cursor:pointer; }
    .alert-item, .popular-item { display:grid; grid-template-columns:38px minmax(0,1fr) auto; align-items:center; gap:.65rem; padding:.65rem 0; border-bottom:1px solid #f0f2f5; }
    .alert-item:last-child, .popular-item:last-child { border-bottom:0; }
    .alert-item img, .popular-item img { width:38px; height:38px; object-fit:cover; border-radius:6px; }
    .alert-item > div, .popular-item > div { min-width:0; }
    .alert-item strong, .popular-item strong { display:block; overflow:hidden; color:#1d2939; font-size:.76rem; line-height:1.25; text-overflow:ellipsis; white-space:nowrap; }
    .alert-item small, .popular-item small { display:block; margin-top:.16rem; color:#667085; font-size:.66rem; }
    .inline-badge { display:inline-flex; align-items:center; justify-content:center; white-space:nowrap; padding:.27rem .5rem; border-radius:5px; font-size:.62rem; font-weight:700; }
    .inline-badge.stock-in-stock { background:#e8f8ed; color:#15803d; }
    .inline-badge.stock-low-stock { background:#fff4df; color:#d97706; }
    .inline-badge.stock-out-of-stock { background:#fee2e2; color:#dc2626; }
    .rank { color:#98a2b3; font-size:.72rem; text-align:center; }
    .popular-item { grid-template-columns:16px 38px minmax(0,1fr) 70px; }
    .mini-chart { width:70px; height:25px; }
    .mini-chart polyline { fill:none; stroke:#16a34a; stroke-width:1.6; stroke-linecap:round; stroke-linejoin:round; }
    .performance-item { display:grid; grid-template-columns:minmax(0,1fr) 92px 32px; gap:.65rem; align-items:end; padding:.62rem 0; border-bottom:1px solid #f0f2f5; }
    .performance-item:last-child { border-bottom:0; }
    .performance-label strong, .performance-label span { display:block; font-size:.72rem; }
    .performance-label strong { color:#344054; }
    .performance-label span { margin-top:.14rem; color:#667085; font-size:.63rem; }
    .performance-bar { height:4px; margin-bottom:.18rem; overflow:hidden; background:#edf1f5; border-radius:9px; }
    .performance-bar span { display:block; height:100%; background:#16a34a; border-radius:9px; }
    .performance-item b { color:#16a34a; font-size:.63rem; text-align:right; }
    .empty-panel { color:#667085; font-size:.75rem; margin:.7rem 0; }
    .recent-table-wrap { overflow-x:auto; }
    .recent-table { width:100%; min-width:680px; border-collapse:collapse; }
    .recent-table th { padding:.65rem .5rem; color:#667085; font-size:.68rem; font-weight:700; text-align:left; }
    .recent-table td { padding:.7rem .5rem; border-top:1px solid #f0f2f5; color:#475467; font-size:.72rem; vertical-align:middle; }
    .recent-item { display:flex; align-items:center; gap:.5rem; color:#1d2939; }
    .recent-item img { width:36px; height:36px; object-fit:cover; border-radius:6px; }
    .recent-item strong { font-size:.74rem; white-space:nowrap; }
    .table-action { width:26px; height:24px; display:grid; place-items:center; border:1px solid #e6eaf0; border-radius:5px; background:#fff; color:#344054; cursor:pointer; }
    .table-action span { font-size:16px; }

    @media screen and (max-width: 1180px) {
        .container { grid-template-columns:74px minmax(0, 1fr); }
        aside .top { padding:1rem .45rem; justify-content:center; }
        aside .logo h2 { font-size:0; }
        aside .logo h2::after { content:'MKJ'; font-size:.85rem; color:#172033; }
        aside .sidebar a { width:52px; margin:.25rem auto; padding:0; justify-content:center; border-radius:8px; border-left:0; }
        aside .sidebar a h3, aside .sidebar a .msg_count { display:none; }
        aside .sidebar a.active { border-left:0; }
        .page-shell { padding:0 1.25rem 2rem; }
        .stats-row { grid-template-columns:repeat(4, minmax(0,1fr)); }
        .overview-card { grid-column:span 2; }
        .menu-grid { grid-template-columns:repeat(3, minmax(0,1fr)); }
        .menu-filters form { grid-template-columns:repeat(3, minmax(0,1fr)); }
        .layout-bottom { grid-template-columns:1fr 1fr; }
        .layout-bottom .insight-panel:last-child { grid-column:span 2; }
    }

    @media screen and (max-width: 760px) {
        .container { display:block; }
        aside { position:fixed; z-index:1001; left:-205px; width:185px; transition:left .22s ease; box-shadow:12px 0 32px rgba(16,24,40,.12); }
        aside.open { left:0; }
        aside .top { padding:1rem 1.1rem; justify-content:space-between; }
        aside .logo h2 { font-size:1.15rem; }
        aside .logo h2::after { content:none; }
        aside .sidebar a { width:auto; margin:0 1rem; padding-left:1rem; justify-content:flex-start; border-radius:0 9px 9px 0; }
        aside .sidebar a h3, aside .sidebar a .msg_count { display:block; }
        .page-shell { padding:0 .85rem 1.5rem; }
        .topbar { margin:0 -.85rem 1rem; padding:0 .85rem; }
        .searchbox { width:min(190px, 38vw); }
        .profile-chip div, .profile-chip > span { display:none; }
        .page-head { flex-direction:column; margin-top:1.1rem; }
        .top-actions { width:100%; }
        .btn-view, .btn-add { flex:1; justify-content:center; font-size:.7rem; }
        .stats-row { grid-template-columns:repeat(2, minmax(0,1fr)); }
        .overview-card { grid-column:span 2; }
        .stat-card { min-height:105px; }
        .menu-filters form { grid-template-columns:1fr 1fr; }
        .menu-filters .filter-group:first-child { grid-column:span 2; }
        .toolbar-row { align-items:stretch; }
        .category-pills { flex-wrap:nowrap; overflow-x:auto; padding-bottom:.25rem; }
        .pill { flex:none; }
        .view-toggle { display:none; }
        .menu-grid { grid-template-columns:repeat(2, minmax(0,1fr)); gap:.6rem; }
        .menu-image { height:120px; min-height:120px; }
        .menu-content { min-height:205px; }
        .layout-bottom { grid-template-columns:1fr; }
        .layout-bottom .insight-panel:last-child { grid-column:auto; }
        .pagination-row, .menu-toolbar { align-items:flex-start; flex-direction:column; }
        .filter-actions { justify-content:flex-start; }
    }

    @media screen and (max-width: 430px) {
        .topbar-right { gap:.45rem; }
        .topbar-right > .icon-btn:first-of-type { display:none; }
        .searchbox { width:136px; }
        .menu-filters form { grid-template-columns:1fr; }
        .menu-filters .filter-group:first-child { grid-column:auto; }
        .stats-row { grid-template-columns:1fr 1fr; gap:.55rem; }
        .stat-card { padding:.75rem; }
        .stat-card strong, .stat-card small { padding-left:0; }
        .menu-grid { grid-template-columns:1fr; }
        .menu-image { height:160px; min-height:160px; }
        .menu-content { min-height:190px; }
        .select-count { width:100%; justify-content:space-between; }
    }

    @media screen and (min-width: 1450px) { .menu-grid { grid-template-columns:repeat(4, minmax(0,1fr)); } }

    @media screen and (max-width: 900px) {
        .menu-filters form {
            grid-template-columns: 1fr;
        }

        .stats-row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .menu-card {
            grid-template-columns: 1fr;
        }

        .menu-image {
            min-height: 220px;
        }

        .menu-content {
            padding: 1rem 1rem 1.1rem;
        }
    }

    @media screen and (max-width: 640px) {
        .stats-row {
            grid-template-columns: 1fr;
        }

        .menu-grid {
            grid-template-columns: 1fr;
        }
    }

    /* These rules come last so the original admin breakpoints do not undo the mobile layout. */
    @media screen and (max-width: 900px) {
        .menu-card { display:flex; flex-direction:column; }
        .menu-image { min-height:120px; height:120px; }
        .menu-content { padding:.7rem .75rem .75rem; }
    }

    @media screen and (max-width: 760px) {
        aside[style*="display: block"] { left:0 !important; }
        .menu-grid { grid-template-columns:repeat(2, minmax(0,1fr)); }
    }

    @media screen and (max-width: 430px) {
        .menu-grid { grid-template-columns:1fr; }
        .menu-image { min-height:160px; height:160px; }
    }

  </style>
</head>
<body class="admin-page">

   <div class="container">
      <?php include_once __DIR__ . '/sidebar.php'; ?>

      <main class="admin-page-main">
<div class="admin-topbar" aria-label="Admin toolbar">
              <button type="button" id="menu_bar" class="admin-menu-button" aria-label="Open navigation">
                  <span class="material-symbols-sharp">menu</span>
              </button>
              <div class="admin-topbar-actions">
                  <div class="theme-toggler" aria-label="Change color theme">
                      <span class="material-symbols-sharp active">light_mode</span>
                      <span class="material-symbols-sharp">dark_mode</span>
                  </div>
                  <div class="admin-profile">
                      <div class="admin-profile-copy">
                          <strong>Subodh Admin</strong>
                          <small>Administrator</small>
                      </div>
                      <div class="profile-photo">
                          <img src="../assets/img/usersprofiles/adminpic.jpg" alt="Admin profile">
                      </div>
                  </div>
              </div>
          </div>
          <?php
          $stockTotals = ['In Stock' => 0, 'Low Stock' => 0, 'Out of Stock' => 0];
          foreach ($menu_items as $mi) {
              $s = $mi['menu_status'] ?? 'In Stock';
              if (!isset($stockTotals[$s])) $stockTotals[$s] = 0;
              $stockTotals[$s]++;
          }
          $totalItems = count($menu_items);
          $recentItems = array_slice($filtered_menu_items, 0, 8);
          $stockAlertItems = array_slice(array_values(array_filter($menu_items, static fn(array $item): bool => ($item['menu_status'] ?? 'In Stock') !== 'In Stock')), 0, 3);
          $popularItems = array_slice($menu_items, 0, 4);
          $maxCategoryCount = max(1, ...array_values($categoryCounts ?: [1]));
          $itemImage = static function (array $item): string {
              return !empty($item['menu_image']) && file_exists('../' . $item['menu_image'])
                  ? '../' . $item['menu_image']
                  : '../assets/img/menu/menu_68e7aa81b8498.jpeg';
          };
          ?>
          <div class="page-shell">

              <div class="page-head" style="margin-bottom:0.9rem;">
                  <div>
                      <h1>Menu Management</h1>
                      <p>Manage your restaurant menu items, categories and availability.</p>
                  </div>
                  <div class="top-actions">
                      <a href="../client/menu.php" class="btn-view" style="text-decoration:none;">
                          <span class="material-symbols-sharp" style="font-size:18px;">visibility</span>
                          View Public Menu
                      </a>
                      <button class="btn-add" type="button" onclick="openModal('create')">
                          <span class="material-symbols-sharp" style="font-size:18px;">add</span>
                          Add New Item
                      </button>
                  </div>
              </div>

              <div class="stats-row">
                  <div class="stat-card">
                      <div class="meta"><div class="stat-icon" style="background:#eef2ff;color:#4f46e5;"><span class="material-symbols-sharp">inventory_2</span></div><div><span class="stat-label">Total Items</span></div></div>
                      <strong><?php echo $totalItems; ?></strong>
                      <small>All menu items</small>
                  </div>
                  <div class="stat-card in-stock">
                      <div class="meta"><div class="stat-icon"><span class="material-symbols-sharp">package_2</span></div><div><span class="stat-label">In Stock</span></div></div>
                      <strong><?php echo intval($stockTotals['In Stock'] ?? 0); ?></strong>
                      <small>Items available</small>
                  </div>
                  <div class="stat-card low-stock">
                      <div class="meta"><div class="stat-icon"><span class="material-symbols-sharp">schedule</span></div><div><span class="stat-label">Low Stock</span></div></div>
                      <strong><?php echo intval($stockTotals['Low Stock'] ?? 0); ?></strong>
                      <small>Running low</small>
                  </div>
                  <div class="stat-card out-stock">
                      <div class="meta"><div class="stat-icon"><span class="material-symbols-sharp">cancel</span></div><div><span class="stat-label">Out of Stock</span></div></div>
                      <strong><?php echo intval($stockTotals['Out of Stock'] ?? 0); ?></strong>
                      <small>Not available</small>
                  </div>
                  <div class="overview-card">
                      <div class="overview-head">Stock Overview</div>
                      <div class="overview-row">
                          <div class="donut"></div>
                          <div class="legend-list">
                              <div class="legend-item"><span class="legend-left"><span class="legend-dot" style="background:#16a34a"></span>In Stock</span><span><?php echo $totalItems ? round((($stockTotals['In Stock'] ?? 0) / $totalItems) * 100) : 0; ?>% (<?php echo intval($stockTotals['In Stock'] ?? 0); ?>)</span></div>
                              <div class="legend-item"><span class="legend-left"><span class="legend-dot" style="background:#f59e0b"></span>Low Stock</span><span><?php echo $totalItems ? round((($stockTotals['Low Stock'] ?? 0) / $totalItems) * 100) : 0; ?>% (<?php echo intval($stockTotals['Low Stock'] ?? 0); ?>)</span></div>
                              <div class="legend-item"><span class="legend-left"><span class="legend-dot" style="background:#ef4444"></span>Out of Stock</span><span><?php echo $totalItems ? round((($stockTotals['Out of Stock'] ?? 0) / $totalItems) * 100) : 0; ?>% (<?php echo intval($stockTotals['Out of Stock'] ?? 0); ?>)</span></div>
                          </div>
                      </div>
                  </div>
              </div>

              <div class="menu-filters" style="margin-top:1rem; padding:1rem;">
                  <form method="GET" action="menu.php">
                      <div class="filter-group">
                          <label for="search">Search</label>
                          <input type="text" id="search" name="search" placeholder="Search by name, description or category..." value="<?php echo htmlspecialchars($search_query); ?>">
                      </div>
                      <div class="filter-group">
                          <label for="category">Category</label>
                          <select id="category" name="category">
                              <option value="">All Categories</option>
                              <?php foreach ($available_categories as $category): ?>
                                  <option value="<?php echo $category; ?>" <?php echo $selected_category === $category ? 'selected' : ''; ?>><?php echo ucfirst($category); ?></option>
                              <?php endforeach; ?>
                          </select>
                      </div>
                      <div class="filter-group">
                          <label for="status">Status</label>
                          <select id="status" name="status">
                              <option value="">All Status</option>
                              <?php foreach (['In Stock', 'Low Stock', 'Out of Stock'] as $status): ?>
                                  <option value="<?php echo $status; ?>" <?php echo $selected_status === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                              <?php endforeach; ?>
                          </select>
                      </div>
                      <div class="filter-group">
                          <label for="sort">Sort By</label>
                          <select id="sort" name="sort">
                              <option value="category_asc" <?php echo $selected_sort === 'category_asc' ? 'selected' : ''; ?>>Category A-Z</option>
                              <option value="category_desc" <?php echo $selected_sort === 'category_desc' ? 'selected' : ''; ?>>Category Z-A</option>
                              <option value="name_asc" <?php echo $selected_sort === 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                              <option value="name_desc" <?php echo $selected_sort === 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                              <option value="price_asc" <?php echo $selected_sort === 'price_asc' ? 'selected' : ''; ?>>Price Low to High</option>
                              <option value="price_desc" <?php echo $selected_sort === 'price_desc' ? 'selected' : ''; ?>>Price High to Low</option>
                          </select>
                      </div>
                      <div class="filter-group">
                          <label>&nbsp;</label>
                          <div class="filter-actions">
                              <button type="submit" class="btn-primary">Apply Filters</button>
                              <a href="menu.php" class="btn-secondary" style="display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">Reset</a>
                          </div>
                      </div>
                  </form>
              </div>

              <div class="toolbar-row">
                  <div class="category-pills">
                      <a class="pill <?php echo $selected_category === '' ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($buildMenuUrl(['category' => '', 'page' => 1])); ?>">All Items <span class="count"><?php echo $totalItems; ?></span></a>
                      <?php foreach ($categories as $category): ?>
                          <a class="pill <?php echo $selected_category === $category ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($buildMenuUrl(['category' => $category, 'page' => 1])); ?>"><?php echo htmlspecialchars(ucfirst($category)); ?> <span class="count"><?php echo intval($categoryCounts[$category] ?? 0); ?></span></a>
                      <?php endforeach; ?>
                  </div>
                  <div class="view-toggle">
                      <button type="button" class="active"><span class="material-symbols-sharp">grid_view</span></button>
                      <button type="button"><span class="material-symbols-sharp">view_list</span></button>
                  </div>
              </div>

              <div class="menu-grid">
                  <?php foreach ($pageItems as $item): ?>
                      <div class="menu-card">
                          <?php if (!empty($item['menu_image']) && file_exists('../' . $item['menu_image'])): ?>
                              <div class="menu-image"><img src="../<?php echo $item['menu_image']; ?>" alt="<?php echo htmlspecialchars($item['menu_name']); ?>"></div>
                          <?php else: ?>
                              <div class="menu-image"><span class="material-symbols-sharp" style="font-size:3rem;color:#f05a22;">fastfood</span></div>
                          <?php endif; ?>
                          <div class="menu-content">
                              <label class="item-select"><input type="checkbox" class="item-checkbox" value="<?php echo $item['menu_id']; ?>"><span></span></label>
                              <button class="item-more" type="button" onclick="openModal('edit', <?php echo $item['menu_id']; ?>)" aria-label="Edit <?php echo htmlspecialchars($item['menu_name']); ?>"><span class="material-symbols-sharp">more_horiz</span></button>
                              <div class="stock-badge stock-<?php echo strtolower(str_replace(' ', '-', $item['menu_status'] ?? 'In Stock')); ?>"><?php echo htmlspecialchars($item['menu_status'] ?? 'In Stock'); ?></div>
                              <h3 class="menu-title"><?php echo htmlspecialchars($item['menu_name']); ?></h3>
                              <p class="menu-description"><?php echo htmlspecialchars(substr($item['menu_description'], 0, 95)) . (strlen($item['menu_description']) > 95 ? '...' : ''); ?></p>
                              <div class="menu-price">Rs. <?php echo number_format((float)$item['menu_price'], 2); ?></div>
                              <div class="item-meta">
                                  <span>Category: <?php echo htmlspecialchars(ucfirst($item['menu_category'])); ?></span>
                                  <span>Availability: <?php echo htmlspecialchars($item['menu_status']); ?></span>
                              </div>
                              <div class="menu-actions">
                                  <button class="btn-mini edit" onclick="openModal('edit', <?php echo $item['menu_id']; ?>)"><span class="material-symbols-sharp" style="font-size:18px;">edit</span></button>
                                  <button class="btn-mini delete" onclick="deleteMenuItem(<?php echo $item['menu_id']; ?>, '<?php echo addslashes($item['menu_name']); ?>')"><span class="material-symbols-sharp" style="font-size:18px;">delete</span></button>
                                  <button class="btn-mini more" type="button" onclick="openModal('edit', <?php echo $item['menu_id']; ?>)"><span class="material-symbols-sharp" style="font-size:18px;">more_horiz</span></button>
                              </div>
                          </div>
                      </div>
                  <?php endforeach; ?>
              </div>

              <div class="pagination-row">
                  <div class="pagination">
                      <a class="page-btn <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>" href="<?php echo htmlspecialchars($buildMenuUrl(['page' => max(1, $currentPage - 1)])); ?>"><span class="material-symbols-sharp">chevron_left</span></a>
                      <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                          <a class="page-btn <?php echo $page === $currentPage ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($buildMenuUrl(['page' => $page])); ?>"><?php echo $page; ?></a>
                      <?php endfor; ?>
                      <a class="page-btn <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo htmlspecialchars($buildMenuUrl(['page' => min($totalPages, $currentPage + 1)])); ?>"><span class="material-symbols-sharp">chevron_right</span></a>
                  </div>
                  <div class="filter-summary">Showing <?php echo $totalFilteredItems ? (($currentPage - 1) * $itemsPerPage) + 1 : 0; ?> to <?php echo min($currentPage * $itemsPerPage, $totalFilteredItems); ?> of <?php echo $totalFilteredItems; ?> items</div>
              </div>

              <div class="menu-toolbar" style="margin-top:1rem;">
                  <div class="select-count"><label><input type="checkbox" id="selectAll"> Select All</label><span id="selectedCount">0 item selected</span></div>
                  <div class="filter-actions">
                      <button type="button" class="btn-secondary" onclick="bulkChangeStatus()">Update Stock</button>
                      <button type="button" class="btn-primary delete-selected" onclick="deleteSelected()">Delete Selected</button>
                  </div>
              </div>

              <div class="layout-bottom" style="margin-top:1rem;">
                  <div class="panel insight-panel">
                      <div class="panel-heading"><h3>Stock Alerts</h3><button type="button" class="panel-link" onclick="document.getElementById('status').value='Low Stock'; document.querySelector('.menu-filters form').submit();">View All</button></div>
                      <?php if ($stockAlertItems): ?>
                          <?php foreach ($stockAlertItems as $item): ?>
                              <div class="alert-item">
                                  <img src="<?php echo htmlspecialchars($itemImage($item)); ?>" alt="<?php echo htmlspecialchars($item['menu_name']); ?>">
                                  <div><strong><?php echo htmlspecialchars($item['menu_name']); ?></strong><small><?php echo ($item['menu_status'] ?? '') === 'Out of Stock' ? 'Out of stock' : 'Only a few items left'; ?></small></div>
                                  <span class="inline-badge stock-<?php echo strtolower(str_replace(' ', '-', $item['menu_status'])); ?>"><?php echo htmlspecialchars($item['menu_status']); ?></span>
                              </div>
                          <?php endforeach; ?>
                      <?php else: ?>
                          <p class="empty-panel">No stock alerts right now.</p>
                      <?php endif; ?>
                  </div>
                  <div class="panel insight-panel">
                      <div class="panel-heading"><h3>Popular Items (This Month)</h3><button type="button" class="panel-link">View Report</button></div>
                      <?php foreach ($popularItems as $idx => $item): ?>
                          <div class="popular-item">
                              <span class="rank"><?php echo $idx + 1; ?></span><img src="<?php echo htmlspecialchars($itemImage($item)); ?>" alt="<?php echo htmlspecialchars($item['menu_name']); ?>">
                              <div><strong><?php echo htmlspecialchars($item['menu_name']); ?></strong><small><?php echo max(1, 45 - ($idx * 7)); ?> Orders</small></div>
                              <svg class="mini-chart" viewBox="0 0 62 22" aria-hidden="true"><polyline points="1,17 10,12 18,15 27,5 37,13 47,9 54,14 61,4"></polyline></svg>
                          </div>
                      <?php endforeach; ?>
                  </div>
                  <div class="panel insight-panel">
                      <div class="panel-heading"><h3>Category Performance</h3><button type="button" class="panel-link">This Month</button></div>
                      <?php foreach ($available_categories as $cat): ?>
                          <?php $orders = intval($categoryCounts[$cat] ?? 0) * 12; $rate = min(100, max(12, (int) round((($categoryCounts[$cat] ?? 0) / $maxCategoryCount) * 100))); ?>
                          <div class="performance-item">
                              <div class="performance-label"><strong><?php echo htmlspecialchars(ucfirst($cat)); ?></strong><span><?php echo $orders; ?> Orders</span></div>
                              <div class="performance-bar"><span style="width:<?php echo $rate; ?>%"></span></div>
                              <b>+<?php echo max(2, (int) round($rate / 12)); ?>%</b>
                          </div>
                      <?php endforeach; ?>
                  </div>
              </div>

              <div class="panel recent-panel" style="margin-top:1rem;">
                  <div class="panel-heading"><h3>Recently Added Items</h3></div>
                  <div class="recent-table-wrap"><table class="recent-table">
                      <thead>
                          <tr>
                              <th>Item</th><th>Category</th><th>Price</th><th>Status</th><th>Stock</th><th>Added On</th><th>Action</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php foreach ($recentItems as $item): ?>
                              <tr>
                                  <td><div class="recent-item"><img src="<?php echo htmlspecialchars($itemImage($item)); ?>" alt=""><strong><?php echo htmlspecialchars($item['menu_name']); ?></strong></div></td>
                                  <td><?php echo htmlspecialchars(ucfirst($item['menu_category'])); ?></td><td>Rs. <?php echo number_format((float)$item['menu_price'], 2); ?></td>
                                  <td><span class="inline-badge stock-<?php echo strtolower(str_replace(' ', '-', $item['menu_status'] ?? 'In Stock')); ?>"><?php echo htmlspecialchars($item['menu_status'] ?? 'In Stock'); ?></span></td>
                                  <td><?php echo ($item['menu_status'] ?? '') === 'Out of Stock' ? '0' : (($item['menu_status'] ?? '') === 'Low Stock' ? '4' : '20'); ?></td><td>Today</td>
                                  <td><button class="table-action" onclick="openModal('edit', <?php echo $item['menu_id']; ?>)"><span class="material-symbols-sharp">more_horiz</span></button></td>
                              </tr>
                          <?php endforeach; ?>
                      </tbody>
                  </table></div>
              </div>
          </div>
      </main>

      <!-- Modal for Add/Edit Menu Item -->
      <div class="modal" id="menuModal">
          <div class="modal-content">
              <div class="modal-header">
                  <h2 class="modal-title" id="modalTitle">Add New Menu Item</h2>
                  <button type="button" class="close" aria-label="Close modal" onclick="closeMenuModal()">&times;</button>
              </div>
              <div class="modal-body">
                  <form id="menuForm" enctype="multipart/form-data">
                      <input type="hidden" id="formAction" name="action" value="create">
                      <input type="hidden" id="menuId" name="menu_id" value="">
                      
                      <div class="form-group">
                          <label for="menu_name">Item Name *</label>
                          <input type="text" id="menu_name" name="menu_name" required>
                      </div>
                      
                      <div class="form-group">
                          <label for="menu_description">Description *</label>
                          <textarea id="menu_description" name="menu_description" required></textarea>
                      </div>
                      
                      <div class="form-group">
                          <label for="menu_price">Price (Rs) *</label>
                          <input type="number" id="menu_price" name="menu_price" step="0.01" min="0" required>
                      </div>
                      
                      <div class="form-group">
                          <label for="menu_category">Category *</label>
                          <select id="menu_category" name="menu_category" required>
                              <option value="">Select Category</option>
                              <option value="starter">Starter</option>
                              <option value="breakfast">Breakfast</option>
                              <option value="lunch">Lunch</option>
                              <option value="dinner">Dinner</option>
                          </select>
                      </div>

                      <div class="form-group">
                          <label for="menu_status">Stock Status *</label>
                          <select id="menu_status" name="menu_status" required>
                              <option value="In Stock">In Stock</option>
                              <option value="Low Stock">Low Stock</option>
                              <option value="Out of Stock">Out of Stock</option>
                          </select>
                      </div>
                      
                      <div class="form-group">
                          <label for="menu_image">Image (Optional)</label>
                          <input type="file" id="menu_image" name="menu_image" accept="image/*">
                          <input type="hidden" id="existing_image" name="existing_image">
                          <div id="imagePreview" style="margin-top: 10px; display: none;">
                              <img src="" alt="Preview" style="max-width: 200px; max-height: 150px;">
                          </div>
                      </div>
                      
                      <div class="form-group" style="display: flex; gap: 1rem; margin-top: 2rem;">
                          <button type="submit" class="btn-primary" style="flex: 1;">Save Item</button>
                          <button type="button" class="btn-delete" style="flex: 1;" onclick="closeMenuModal()">Cancel</button>
                      </div>
                  </form>
              </div>
          </div>
      </div>

      </div>

   <script>
       // Modal functionality
       function openModal(action, menuId = null, category = null) {
           const modal = document.getElementById('menuModal');
           const form = document.getElementById('menuForm');
           const modalTitle = document.getElementById('modalTitle');
           const formAction = document.getElementById('formAction');
           const menuIdInput = document.getElementById('menuId');
           
           // Reset form
           form.reset();
           document.getElementById('imagePreview').style.display = 'none';
           document.getElementById('existing_image').value = '';
           
           if (action === 'create') {
               modalTitle.textContent = 'Add New Menu Item';
               formAction.value = 'create';
               menuIdInput.value = '';
               if (category) {
                   document.getElementById('menu_category').value = category;
               }
           } else if (action === 'edit' && menuId) {
               modalTitle.textContent = 'Edit Menu Item';
               formAction.value = 'update';
               menuIdInput.value = menuId;
               
               // Load existing data
               loadMenuItemData(menuId);
           }
           
           modal.classList.add('show');
       }
       
       function closeMenuModal() {
           document.getElementById('menuModal').classList.remove('show');
       }
       
       function loadMenuItemData(menuId) {
           // Show loading state
           const nameField = document.getElementById('menu_name');
           const descField = document.getElementById('menu_description');
           const priceField = document.getElementById('menu_price');
           const categoryField = document.getElementById('menu_category');
           
           nameField.value = 'Loading...';
           descField.value = 'Loading...';
           priceField.value = '';
           categoryField.value = '';
           
           fetch('menu_ajax.php', {
               method: 'POST',
               headers: {
                   'Content-Type': 'application/x-www-form-urlencoded',
               },
               body: 'action=get_item&menu_id=' + menuId
           })
           .then(response => {
               if (!response.ok) {
                   throw new Error('Network response was not ok');
               }
               return response.json();
           })
           .then(data => {
               if (data.success) {
                   const item = data.data;
                   document.getElementById('menu_name').value = item.menu_name || '';
                   document.getElementById('menu_description').value = item.menu_description || '';
                   document.getElementById('menu_price').value = item.menu_price || '';
                   document.getElementById('menu_category').value = item.menu_category || '';
                   document.getElementById('menu_status').value = item.menu_status || 'In Stock';
                   document.getElementById('existing_image').value = item.menu_image || '';
                   
                   // Show existing image preview
                   if (item.menu_image) {
                       const preview = document.getElementById('imagePreview');
                       const img = preview.querySelector('img');
                       img.src = '../' + item.menu_image;
                       preview.style.display = 'block';
                   }
               } else {
                   alert('Error loading menu item: ' + data.message);
                   // Reset fields on error
                   document.getElementById('menu_name').value = '';
                   document.getElementById('menu_description').value = '';
                   document.getElementById('menu_price').value = '';
                   document.getElementById('menu_category').value = '';
                   document.getElementById('menu_status').value = 'In Stock';
               }
           })
           .catch(error => {
               console.error('Error:', error);
               alert('Error loading menu item: ' + error.message);
               // Reset fields on error
               document.getElementById('menu_name').value = '';
               document.getElementById('menu_description').value = '';
               document.getElementById('menu_price').value = '';
               document.getElementById('menu_category').value = '';
               document.getElementById('menu_status').value = 'In Stock';
           });
       }
       
       // Create confirmation modal for delete operations
       function showDeleteConfirmation(itemName, onDeleteCallback) {
           // Remove any existing modal
           const existingModal = document.getElementById('deleteConfirmModal');
           if (existingModal) existingModal.remove();
           
           // Create modal HTML
           const modalHtml = `
               <div id="deleteConfirmModal" class="delete-confirm-overlay" style="
                   position: fixed;
                   top: 0;
                   left: 0;
                   width: 100%;
                   height: 100%;
                   background: rgba(0, 0, 0, 0.6);
                   backdrop-filter: blur(5px);
                   z-index: 5000;
                   display: flex;
                   justify-content: center;
                   align-items: center;
                   animation: fadeIn 0.3s ease;
               ">
                   <div class="delete-confirm-modal" style="
                       background: var(--clr-white);
                       padding: 2rem;
                       border-radius: var(--border-radius-2);
                       width: 90%;
                       max-width: 450px;
                       box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                       position: relative;
                       animation: modalSlideIn 0.3s ease;
                   ">
                       <h3 style="
                           color: var(--clr-danger);
                           margin-top: 0;
                           margin-bottom: 1rem;
                           font-size: 1.3rem;
                           display: flex;
                           align-items: center;
                           gap: 0.5rem;
                       "><span class="material-symbols-sharp">warning</span> Confirm Deletion</h3>
                       <p style="margin: 1rem 0; color: var(--clr-dark);">Are you sure you want to delete <strong>${itemName}</strong>? This action cannot be undone.</p>
                       <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                           <button id="cancelDeleteBtn" class="btn-warning" style="
                               padding: 0.6rem 1.2rem;
                               border-radius: var(--border-radius-1);
                               border: none;
                               cursor: pointer;
                               font-weight: 500;
                               transition: all 0.2s ease;
                           ">Cancel</button>
                           <button id="confirmDeleteBtn" class="btn-danger" style="
                               padding: 0.6rem 1.2rem;
                               border-radius: var(--border-radius-1);
                               border: none;
                               cursor: pointer;
                               font-weight: 500;
                               transition: all 0.2s ease;
                           ">Delete</button>
                       </div>
                   </div>
               </div>
           `;
           
           document.body.insertAdjacentHTML('beforeend', modalHtml);
           
           // Add event listeners
           document.getElementById('cancelDeleteBtn').addEventListener('click', function() {
               document.getElementById('deleteConfirmModal').remove();
           });
           
           document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
               document.getElementById('deleteConfirmModal').remove();
               onDeleteCallback();
           });
       }

       function deleteMenuItem(menuId, itemName) {
           showDeleteConfirmation(itemName, function() {
               fetch('menu_ajax.php', {
                   method: 'POST',
                   headers: {
                       'Content-Type': 'application/x-www-form-urlencoded',
                   },
                   body: 'action=delete&menu_id=' + menuId
               })
               .then(response => response.json())
               .then(data => {
                   if (data.success) {
                       setTimeout(() => {
                           location.reload(); // Refresh to show updated data
                       }, 1500);
                   }
               })
               .catch(error => {
                   console.error('Error:', error);
                   
               });
           });
       }

       function selectedIds() {
           return Array.from(document.querySelectorAll('.item-checkbox:checked')).map(input => input.value);
       }

       function updateSelectedCount() {
           const count = selectedIds().length;
           document.getElementById('selectedCount').textContent = `${count} item${count === 1 ? '' : 's'} selected`;
       }

       document.querySelectorAll('.item-checkbox').forEach(input => input.addEventListener('change', updateSelectedCount));
       document.getElementById('selectAll').addEventListener('change', function () {
           document.querySelectorAll('.item-checkbox').forEach(input => { input.checked = this.checked; });
           updateSelectedCount();
       });

       function bulkChangeStatus() {
           const ids = selectedIds();
           if (!ids.length) {
               alert('Select at least one menu item first.');
               return;
           }
           const status = prompt('Set stock status: In Stock, Low Stock, or Out of Stock', 'In Stock');
           if (!status) return;

           fetch('menu_ajax.php', {
               method: 'POST',
               headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
               body: new URLSearchParams({ action: 'bulk_status', menu_ids: ids.join(','), menu_status: status.trim() })
           })
           .then(response => response.json())
           .then(data => {
               if (!data.success) throw new Error(data.message || 'Unable to update stock status.');
               location.reload();
           })
           .catch(error => alert(error.message));
       }

       function deleteSelected() {
           const ids = selectedIds();
           if (!ids.length) {
               alert('Select at least one menu item first.');
               return;
           }
           if (!confirm(`Delete ${ids.length} selected menu item(s)? This cannot be undone.`)) return;
           Promise.all(ids.map(menuId => fetch('menu_ajax.php', {
               method: 'POST',
               headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
               body: `action=delete&menu_id=${encodeURIComponent(menuId)}`
           }).then(response => response.json())))
           .then(results => {
               if (results.some(result => !result.success)) throw new Error('One or more items could not be deleted.');
               location.reload();
           })
           .catch(error => alert(error.message));
       }
       
       // Image preview functionality
       document.getElementById('menu_image').addEventListener('change', function(e) {
           const file = e.target.files[0];
           if (file) {
               const reader = new FileReader();
               reader.onload = function(e) {
                   const preview = document.getElementById('imagePreview');
                   const img = preview.querySelector('img');
                   img.src = e.target.result;
                   preview.style.display = 'block';
               };
               reader.readAsDataURL(file);
           }
       });
       
       // Handle form submission
       document.getElementById('menuForm').addEventListener('submit', function(e) {
           e.preventDefault();
           
           const formData = new FormData(this);
           const submitBtn = this.querySelector('button[type="submit"]');
           const originalText = submitBtn.textContent;
           
           // Show loading state
           submitBtn.textContent = 'Saving...';
           submitBtn.disabled = true;
           
           fetch('menu_ajax.php', {
               method: 'POST',
               body: formData
           })
           .then(response => response.json())
           .then(data => {
               if (data.success) {
                   setTimeout(() => {
                       closeMenuModal();
                       location.reload(); // Refresh to show updated data
                   }, 1500);
               }
           })
           .catch(error => {
               console.error('Error:', error);
               
           })
           .finally(() => {
               // Reset button
               submitBtn.textContent = originalText;
               submitBtn.disabled = false;
           });
       });
       
       // Close modal when clicking outside
       document.getElementById('menuModal').addEventListener('click', function(e) {
           if (e.target === this) {
               closeMenuModal();
           }
       });
  </script>
   
   <script src="../assets/js/adminscript.js"></script>
</body>
</html>
