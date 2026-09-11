<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

$conn->query("CREATE TABLE IF NOT EXISTS menu_categories (id INT AUTO_INCREMENT PRIMARY KEY, category_name VARCHAR(100) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

// Handle form submissions
$message = '';
$message_type = '';

// Handle menu item creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_category':
            $category_name = trim($_POST['category_name'] ?? '');
            if ($category_name !== '') {
                $stmt = $conn->prepare("INSERT IGNORE INTO menu_categories (category_name) VALUES (?)");
                $stmt->bind_param('s', $category_name);
                $stmt->execute();
                $stmt->close();
            }
            header('Location: menu.php');
            exit;
        case 'delete_category':
            $category_name = trim($_POST['category_name'] ?? '');
            if ($category_name !== '') {
                $check = $conn->prepare("SELECT COUNT(*) FROM menu WHERE LOWER(TRIM(menu_category)) = LOWER(TRIM(?))");
                $check->bind_param('s', $category_name);
                $check->execute();
                $check->bind_result($item_count);
                $check->fetch();
                $check->close();
                if ((int) $item_count === 0) {
                    $stmt = $conn->prepare("DELETE FROM menu_categories WHERE LOWER(TRIM(category_name)) = LOWER(TRIM(?))");
                    $stmt->bind_param('s', $category_name);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Delete the items in this category before deleting the category.'];
                }
            }
            header('Location: menu.php');
            exit;
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
            
            $menu_category = trim($_POST['menu_category_new'] ?? '') ?: trim($_POST['menu_category'] ?? '');
            $menu_status = in_array($_POST['menu_status'] ?? 'In Stock', ['In Stock', 'Low Stock', 'Out of Stock'], true) ? $_POST['menu_status'] : 'In Stock';
            $stmt = $conn->prepare("INSERT INTO menu (menu_name, menu_description, menu_price, menu_category, menu_status, menu_image) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdsss", 
                $_POST['menu_name'],
                $_POST['menu_description'],
                $_POST['menu_price'],
                $menu_category,
                $menu_status,
                $image_path
            );
            
            if ($stmt->execute()) {
                $conn->query("UPDATE menu SET menu_status = CASE WHEN stock_quantity = 0 THEN 'Out of Stock' WHEN stock_quantity < 5 THEN 'Low Stock' ELSE 'In Stock' END WHERE menu_id = " . (int)$conn->insert_id);
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
            
            $menu_category = trim($_POST['menu_category_new'] ?? '') ?: trim($_POST['menu_category'] ?? '');
            $menu_status = in_array($_POST['menu_status'] ?? 'In Stock', ['In Stock', 'Low Stock', 'Out of Stock'], true) ? $_POST['menu_status'] : 'In Stock';
            $stmt = $conn->prepare("UPDATE menu SET menu_name = ?, menu_description = ?, menu_price = ?, menu_category = ?, menu_status = ?, menu_image = ? WHERE menu_id = ?");
            $stmt->bind_param("ssdsssi",
                $_POST['menu_name'],
                $_POST['menu_description'],
                $_POST['menu_price'],
                $menu_category,
                $menu_status,
                $image_path,
                $_POST['menu_id']
            );
            
            if ($stmt->execute()) {
                $statusStmt = $conn->prepare("UPDATE menu SET menu_status = CASE WHEN stock_quantity = 0 THEN 'Out of Stock' WHEN stock_quantity < 5 THEN 'Low Stock' ELSE 'In Stock' END WHERE menu_id = ?");
                $statusStmt->bind_param('i', $_POST['menu_id']); $statusStmt->execute(); $statusStmt->close();
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
$category_options = $available_categories;
$categoryResult = $conn->query("SELECT category_name FROM menu_categories ORDER BY category_name");
if ($categoryResult) {
    while ($category = $categoryResult->fetch_assoc()) {
        $category_options[] = strtolower(trim($category['category_name']));
    }
}
$category_options = array_values(array_unique(array_filter($category_options)));
sort($category_options);

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
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  <link rel="stylesheet" href="../assets/css/admin2.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin2.css') ?>">

</head>
<body class="admin-page menu-page">
   <?php include_once __DIR__ . '/topbar.php'; ?>

   <div class="container">
      <?php include_once __DIR__ . '/sidebar.php'; ?>

      <main class="admin-page-main">
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

              <div class="page-head page-head-margin">
                  <div>
                      <h1>Menu Management</h1>
                      <p>Manage your restaurant menu items, categories and availability.</p>
                  </div>
                  <div class="top-actions">
                      <a href="../menu.php" class="btn-view" target="_blank">
                          <span class="material-symbols-sharp btn-icon-sm">language</span>
                          Root Menu
                      </a>
                      <a href="../client/menu.php" class="btn-view">
                          <span class="material-symbols-sharp btn-icon-sm">visibility</span>
                          Client Menu
                      </a>
                      <button class="btn-add" type="button" onclick="openModal('create')">
                          <span class="material-symbols-sharp btn-icon-sm">add</span>
                          Add New Item
                      </button>
                  </div>
              </div>

              <div class="menu-filters menu-filters-padded" style="margin-bottom: 1.25rem;">
                  <div class="panel-heading"><h3>Category Management</h3><span class="text-muted">Categories are available immediately when adding items.</span></div>
                  <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:end;">
                      <form method="post" style="display:flex;gap:.6rem;align-items:end;flex:1;min-width:280px;">
                          <input type="hidden" name="action" value="add_category">
                          <div class="filter-group" style="flex:1;"><label for="new_category_name">New category</label><input id="new_category_name" name="category_name" type="text" maxlength="100" placeholder="e.g. Drinks" required></div>
                          <button class="btn-add" type="submit"><span class="material-symbols-sharp btn-icon-sm">add</span>Add Category</button>
                      </form>
                      <form method="post" style="display:flex;gap:.6rem;align-items:end;flex:1;min-width:280px;">
                          <input type="hidden" name="action" value="delete_category">
                          <div class="filter-group" style="flex:1;"><label for="delete_category_name">Delete empty category</label><select id="delete_category_name" name="category_name" required><option value="">Select category</option><?php foreach ($category_options as $category): ?><option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars(ucfirst($category)); ?></option><?php endforeach; ?></select></div>
                          <button class="btn-delete" type="submit" onclick="return confirm('Delete this category? Categories with items cannot be deleted.');"><span class="material-symbols-sharp btn-icon-sm">delete</span>Delete</button>
                      </form>
                  </div>
              </div>

              <div class="stats-row">
                  <div class="stat-card">
                      <div class="meta"><div class="stat-icon stat-icon-total"><span class="material-symbols-sharp">inventory_2</span></div><div><span class="stat-label">Total Items</span></div></div>
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
                              <div class="legend-item"><span class="legend-left"><span class="legend-dot legend-dot-in-stock"></span>In Stock</span><span><?php echo $totalItems ? round((($stockTotals['In Stock'] ?? 0) / $totalItems) * 100) : 0; ?>% (<?php echo intval($stockTotals['In Stock'] ?? 0); ?>)</span></div>
                              <div class="legend-item"><span class="legend-left"><span class="legend-dot legend-dot-low-stock"></span>Low Stock</span><span><?php echo $totalItems ? round((($stockTotals['Low Stock'] ?? 0) / $totalItems) * 100) : 0; ?>% (<?php echo intval($stockTotals['Low Stock'] ?? 0); ?>)</span></div>
                              <div class="legend-item"><span class="legend-left"><span class="legend-dot legend-dot-out-stock"></span>Out of Stock</span><span><?php echo $totalItems ? round((($stockTotals['Out of Stock'] ?? 0) / $totalItems) * 100) : 0; ?>% (<?php echo intval($stockTotals['Out of Stock'] ?? 0); ?>)</span></div>
                          </div>
                      </div>
                  </div>
              </div>

              <div class="menu-filters menu-filters-padded">
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
                              <a href="menu.php" class="btn-secondary btn-reset-link">Reset</a>
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
                              <div class="menu-image"><img src="../<?php echo $item['menu_image']; ?>" alt="<?php echo htmlspecialchars($item['menu_name']); ?>" loading="lazy" decoding="async"></div>
                          <?php else: ?>
                              <div class="menu-image"><span class="material-symbols-sharp menu-image-placeholder">fastfood</span></div>
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
                                  <?php if ((int)($item['stock_quantity'] ?? 0) === 0): ?>
                                  <button class="btn-mini edit" type="button" title="Restock" onclick="restockMenuItem(<?php echo $item['menu_id']; ?>)"><span class="material-symbols-sharp btn-icon-sm">inventory</span></button>
                                  <?php endif; ?>
                                  <button class="btn-mini edit" onclick="openModal('edit', <?php echo $item['menu_id']; ?>)"><span class="material-symbols-sharp btn-icon-sm">edit</span></button>
                                  <button class="btn-mini delete" onclick="deleteMenuItem(<?php echo $item['menu_id']; ?>, '<?php echo addslashes($item['menu_name']); ?>')"><span class="material-symbols-sharp btn-icon-sm">delete</span></button>
                                  <button class="btn-mini more" type="button" onclick="openModal('edit', <?php echo $item['menu_id']; ?>)"><span class="material-symbols-sharp btn-icon-sm">more_horiz</span></button>
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

              <div class="menu-toolbar mt-1rem">
                  <div class="select-count"><label><input type="checkbox" id="selectAll"> Select All</label><span id="selectedCount">0 item selected</span></div>
                  <div class="filter-actions">
                      <button type="button" class="btn-primary delete-selected" onclick="deleteSelected()">Delete Selected</button>
                  </div>
              </div>

              <div class="layout-bottom mt-1rem">
                  <div class="panel insight-panel">
                      <div class="panel-heading"><h3>Stock Alerts</h3><button type="button" class="panel-link" onclick="document.getElementById('status').value='Low Stock'; document.querySelector('.menu-filters form').submit();">View All</button></div>
                      <?php if ($stockAlertItems): ?>
                          <?php foreach ($stockAlertItems as $item): ?>
                              <div class="alert-item">
                                  <img src="<?php echo htmlspecialchars($itemImage($item)); ?>" alt="<?php echo htmlspecialchars($item['menu_name']); ?>" loading="lazy" decoding="async">
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
                              <span class="rank"><?php echo $idx + 1; ?></span><img src="<?php echo htmlspecialchars($itemImage($item)); ?>" alt="<?php echo htmlspecialchars($item['menu_name']); ?>" loading="lazy" decoding="async">
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

              <div class="panel recent-panel mt-1rem">
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
                                  <td><div class="recent-item"><img src="<?php echo htmlspecialchars($itemImage($item)); ?>" alt="" loading="lazy" decoding="async"><strong><?php echo htmlspecialchars($item['menu_name']); ?></strong></div></td>
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
                          <select id="menu_category" name="menu_category">
                              <option value="">Select Category</option>
                              <?php foreach ($category_options as $category): ?>
                                  <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars(ucfirst($category)); ?></option>
                              <?php endforeach; ?>
                          </select>
                      </div>

                      <div class="form-group">
                          <label for="menu_image">Image (Optional)</label>
                          <input type="file" id="menu_image" name="menu_image" accept="image/*">
                          <input type="hidden" id="existing_image" name="existing_image">
                          <div id="imagePreview" class="image-preview-container">
                              <img src="" alt="Preview" class="image-preview-element">
                          </div>
                      </div>
                      
                      <div class="form-group modal-action-row">
                          <button type="submit" class="btn-primary btn-flex-1">Save Item</button>
                          <button type="button" class="btn-delete btn-flex-1" onclick="closeMenuModal()">Cancel</button>
                      </div>
                  </form>
              </div>
          </div>
      </div>

<script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
<script src="../assets/js/admin2.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin2.js') ?>"></script>
</body>
</html>
