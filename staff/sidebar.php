<?php
/**
 * Staff panel sidebar — mirrors admin/sidebar.php structure/classes/IDs so the
 * shared adminstyle.css layout and adminscript.js sidebar toggle apply as-is.
 */
$current_page = basename($_SERVER['PHP_SELF']);

// Live count of pending QR requests
$pending_qr_count = 0;
if (isset($conn)) {
    $c = $conn->query("SELECT COUNT(*) AS c FROM qr_requests WHERE status = 'pending'");
    if ($c) { $pending_qr_count = (int)$c->fetch_assoc()['c']; }
}
?>
<aside class="admin-sidebar" id="admin_sidebar">
    <a href="dashboard.php" class="sidebar-logo">
        <h2>Mero <span>Bhoj</span></h2>
    </a>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">dashboard</span>
            <h3>Dashboard</h3>
        </a>

        <a href="new-order.php" class="sidebar-link <?php echo ($current_page === 'new-order.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">add_circle</span>
            <h3>Walk-in Order</h3>
        </a>

        <a href="self-orders.php" class="sidebar-link <?php echo ($current_page === 'self-orders.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">qr_code_scanner</span>
            <h3>QR Requests</h3>
            <?php if ($pending_qr_count > 0): ?>
                <span class="sidebar-badge"><?php echo $pending_qr_count; ?></span>
            <?php endif; ?>
        </a>

        <a href="orders.php" class="sidebar-link <?php echo ($current_page === 'orders.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">receipt_long</span>
            <h3>Orders Queue</h3>
        </a>

        <a href="deliveries.php" class="sidebar-link <?php echo ($current_page === 'deliveries.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">local_shipping</span>
            <h3>Deliveries</h3>
        </a>

        <a href="bookings.php" class="sidebar-link <?php echo ($current_page === 'bookings.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">event_seat</span>
            <h3>Bookings</h3>
        </a>

        <a href="floor.php" class="sidebar-link <?php echo ($current_page === 'floor.php') ? 'active' : ''; ?>">
            <span class="material-symbols-sharp">grid_view</span>
            <h3>Floor Map</h3>
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
