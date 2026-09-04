<?php
// Dynamic sidebar navigation for Mero Bhoj Admin Panel
$current_page = basename($_SERVER['PHP_SELF']);

// Count Orders
$sidebar_orders_count = 0;
if (isset($conn)) {
    $orders_count_query = $conn->query("SELECT COUNT(DISTINCT IF(order_number != '', order_number, order_id)) as count FROM orders");
    if ($orders_count_query) {
        $sidebar_orders_count = (int)$orders_count_query->fetch_assoc()['count'];
    }
}
?>
<!-- Notification System Asset Injection -->
<link rel="stylesheet" href="../assets/css/notifications.css?v=<?= filemtime(__DIR__ . '/../assets/css/notifications.css') ?>">
<script src="../assets/js/notifications.js?v=<?= filemtime(__DIR__ . '/../assets/js/notifications.js') ?>" defer></script>

<aside class="admin-sidebar" id="admin_sidebar">
    <a href="./index.php" class="sidebar-logo">
        <h2>Mero <span>Bhoj</span></h2>
    </a>

    <nav class="sidebar-nav">
        <a href="./index.php" class="sidebar-link <?php echo ($current_page === 'index.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">grid_view</span>
            <h3>Dashboard</h3>
        </a>

        <a href="users.php" class="sidebar-link <?php echo ($current_page === 'users.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">person</span>
            <h3>Customers</h3>
        </a>

        <a href="orders_page.php" class="sidebar-link <?php echo ($current_page === 'orders_page.php' || $current_page === 'order_view.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">mail</span>
            <h3>Orders</h3>
            <?php if ($sidebar_orders_count > 0): ?>
                <span class="sidebar-badge"><?php echo $sidebar_orders_count; ?></span>
            <?php endif; ?>
        </a>

        <a href="menu.php" class="sidebar-link <?php echo ($current_page === 'menu.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">menu_book</span>
            <h3>Menu</h3>
        </a>

        <a href="bookings.php" class="sidebar-link <?php echo ($current_page === 'bookings.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">calendar_month</span>
            <h3>Bookings</h3>
        </a>

        <a href="table_qr.php" class="sidebar-link <?php echo ($current_page === 'table_qr.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">qr_code_2</span>
            <h3>Table QR</h3>
        </a>

        <a href="self_orders.php" class="sidebar-link <?php echo ($current_page === 'self_orders.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">fact_check</span>
            <h3>Self Orders</h3>
        </a>

        <a href="feedback.php" class="sidebar-link <?php echo ($current_page === 'feedback.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">chat_bubble</span>
            <h3>Feedback</h3>
        </a>

        <a href="finance.php" class="sidebar-link <?php echo ($current_page === 'finance.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">account_balance</span>
            <h3>Finance</h3>
        </a>

        <a href="#" class="sidebar-link">
            <span class="material-symbols-sharp">description</span>
            <h3>Reports</h3>
        </a>

        <a href="#" class="sidebar-link">
            <span class="material-symbols-sharp">settings</span>
            <h3>Settings</h3>
        </a>

        <a href="#" class="sidebar-link">
            <span class="material-symbols-sharp">add</span>
            <h3>Add Product</h3>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../includes/logout.php" class="sidebar-link">
            <span class="material-symbols-sharp">logout</span>
            <h3>Logout</h3>
        </a>
    </div>
</aside>
