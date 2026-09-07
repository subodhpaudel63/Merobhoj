<?php
/**
 * Rider panel sidebar — mirrors admin/sidebar.php structure/classes/IDs so the
 * shared adminstyle.css layout and adminscript.js sidebar toggle apply as-is.
 * The Dashboard badge counts jobs waiting in the unassigned pool.
 */
$current_page = basename($_SERVER['PHP_SELF']);

// Jobs sitting in the pool right now (Delivery orders Ready with no rider yet).
$rider_pool_count = 0;
if (isset($conn)) {
    $c = $conn->query("SELECT COUNT(*) AS c
                       FROM orders o
                       LEFT JOIN deliveries d ON d.order_number = o.order_number
                       WHERE o.order_type = 'Delivery'
                         AND o.status = 'Ready'
                         AND (d.rider_id IS NULL)
                         AND o.order_number IS NOT NULL AND o.order_number <> ''
                       GROUP BY o.order_number");
    if ($c) { $rider_pool_count = (int)$c->num_rows; }
}
?>
<aside class="admin-sidebar" id="admin_sidebar">
    <a href="dashboard.php" class="sidebar-logo">
        <h2>Mero <span>Bhoj</span></h2>
    </a>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">two_wheeler</span>
            <h3>Dashboard</h3>
            <?php if ($rider_pool_count > 0): ?>
                <span class="sidebar-badge"><?php echo $rider_pool_count; ?></span>
            <?php endif; ?>
        </a>

        <a href="deliveries.php" class="sidebar-link <?php echo ($current_page === 'deliveries.php' || $current_page === 'navigate.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">local_shipping</span>
            <h3>My Deliveries</h3>
        </a>

        <a href="earnings.php" class="sidebar-link <?php echo ($current_page === 'earnings.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">payments</span>
            <h3>Earnings</h3>
        </a>

        <a href="notifications.php" class="sidebar-link <?php echo ($current_page === 'notifications.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">notifications</span>
            <h3>Notifications</h3>
        </a>

        <a href="profile.php" class="sidebar-link <?php echo ($current_page === 'profile.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">person</span>
            <h3>Profile</h3>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../includes/panel_logout.php" class="sidebar-link">
            <span class="material-symbols-sharp">logout</span>
            <h3>Logout</h3>
        </a>
    </div>
</aside>
