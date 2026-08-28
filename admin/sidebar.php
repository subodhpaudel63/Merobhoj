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

// Count Bookings
$sidebar_bookings_count = 0;
if (isset($conn)) {
    $bookings_count_query = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status = 'Pending'");
    if ($bookings_count_query) {
        $sidebar_bookings_count = (int)$bookings_count_query->fetch_assoc()['count'];
    }
}
?>
<aside>
    <div class="top">
        <div class="logo">
            <h2>Masu <span class="danger"> ko jhol</span> </h2>
        </div>
        <div class="close" id="close_btn">
            <span class="material-symbols-sharp">close</span>
        </div>
    </div>
    <!-- end top -->
    <div class="sidebar">
        <a href="./index.php" class="<?php echo ($current_page === 'index.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">grid_view</span>
            <h3>Dashbord</h3>
        </a>
        <a href="users.php" class="<?php echo ($current_page === 'users.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">person_outline</span>
            <h3>costumers</h3>
        </a>
        <a href="analytics.php" class="<?php echo ($current_page === 'analytics.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">insights</span>
            <h3>Analytics</h3>
        </a>
        <a href="orders_page.php" class="<?php echo ($current_page === 'orders_page.php' || $current_page === 'order_view.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">mail_outline</span>
            <h3>Orders</h3>
            <?php if ($sidebar_orders_count > 0): ?>
                <span class="msg_count"><?php echo $sidebar_orders_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="menu.php" class="<?php echo ($current_page === 'menu.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">receipt_long</span>
            <h3>Menu</h3>
        </a>
        <a href="bookings.php" class="<?php echo ($current_page === 'bookings.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">calendar_month</span>
            <h3>Bookings</h3>
            <?php if ($sidebar_bookings_count > 0): ?>
                <span class="msg_count"><?php echo $sidebar_bookings_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="feedback.php" class="<?php echo ($current_page === 'feedback.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">Feedback</span>
            <h3>Feedback</h3>
        </a>
        <a href="#">
            <span class="material-symbols-sharp">settings</span>
            <h3>settings</h3>
        </a>
        <a href="#">
            <span class="material-symbols-sharp">add</span>
            <h3>Add Product</h3>
        </a>
        <a href="../includes/logout.php">
            <span class="material-symbols-sharp">logout</span>
            <h3>logout</h3>
        </a>
    </div>
</aside>
