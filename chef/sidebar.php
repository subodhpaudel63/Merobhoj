<?php
/**
 * Chef panel sidebar — mirrors admin/sidebar.php structure/classes/IDs so the
 * shared adminstyle.css layout and adminscript.js sidebar toggle apply as-is.
 */
$current_page = basename($_SERVER['PHP_SELF']);

// Live count of active kitchen tickets (Pending/Confirmed/Preparing/Ready).
$kds_active_count = 0;
if (isset($conn)) {
    $c = $conn->query("SELECT COUNT(DISTINCT IF(order_number <> '', order_number, order_id)) AS c
                       FROM orders
                       WHERE status IN ('Pending','Confirmed','Preparing','Ready')");
    if ($c) { $kds_active_count = (int)$c->fetch_assoc()['c']; }
}
?>
<aside class="admin-sidebar" id="admin_sidebar">
    <a href="dashboard.php" class="sidebar-logo">
        <h2>Mero <span>Bhoj</span></h2>
    </a>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">soup_kitchen</span>
            <h3>Kitchen Board</h3>
            <?php if ($kds_active_count > 0): ?>
                <span class="sidebar-badge"><?php echo $kds_active_count; ?></span>
            <?php endif; ?>
        </a>

        <a href="completed.php" class="sidebar-link <?php echo ($current_page === 'completed.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">done_all</span>
            <h3>Completed</h3>
        </a>

        <a href="menu-availability.php" class="sidebar-link <?php echo ($current_page === 'menu-availability.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">restaurant_menu</span>
            <h3>Menu Availability</h3>
        </a>

        <a href="kitchen-notes.php" class="sidebar-link <?php echo ($current_page === 'kitchen-notes.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">sticky_note_2</span>
            <h3>Kitchen Notes</h3>
        </a>

        <a href="ingredients.php" class="sidebar-link <?php echo ($current_page === 'ingredients.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">egg_alt</span>
            <h3>Ingredients</h3>
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
