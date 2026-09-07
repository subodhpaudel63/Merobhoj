<?php
/**
 * Shared panel top header — Rider.
 * Mirrors admin/topbar.php markup/IDs so assets/js/adminscript.js wires up the
 * sidebar toggle, theme toggle and profile dropdown with no extra JS.
 * Differs from the chef topbar in one way: the KDS voice toggle is replaced by
 * a GPS pill (#riderGpsToggle) handled by assets/js/panel_rider.js. It starts
 * OFF so the browser location prompt is never fired unprompted.
 */
if (!isset($panelUser) || !is_array($panelUser)) {
    $panelUser = current_panel_user($conn) ?? ['name' => 'Rider', 'email' => '', 'user_type' => 'rider', 'user_img' => ''];
}

$panelName  = trim((string)($panelUser['name'] ?? '')) ?: 'Delivery Rider';
$panelRole  = (string)($panelUser['user_type'] ?? 'rider');
$roleLabels = ['chef' => 'Kitchen Chef', 'staff' => 'Floor Staff', 'rider' => 'Delivery Rider', 'admin' => 'Administrator', 'manager' => 'Manager'];
$roleLabel  = $roleLabels[$panelRole] ?? ucfirst($panelRole);

// Resolve avatar: use user_img if the file really exists, else fall back to initials.
// Accept both stored forms (root-relative "assets/…" and legacy "../assets/…").
$avatarUrl = '';
$imgRel = preg_replace('#^(?:\.\.?/)+#', '', (string)($panelUser['user_img'] ?? ''));
$imgRel = ltrim((string)$imgRel, '/');
if ($imgRel !== '' && file_exists(__DIR__ . '/../' . $imgRel)) {
    $avatarUrl = '../' . $imgRel;
}
$parts = preg_split('/\s+/', $panelName);
$initials = strtoupper(substr($parts[0] ?? 'R', 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
?>
<script>
    window.PANEL_CSRF = <?= json_encode(panel_csrf_token()) ?>;
    window.PANEL_ROLE = <?= json_encode($panelRole) ?>;
</script>

<header class="admin-topbar" id="admin_topbar">
    <div class="admin-topbar-left">
        <button type="button" id="menu_toggle" class="admin-menu-button" aria-label="Toggle navigation" aria-expanded="true">
            <span class="material-symbols-sharp">menu</span>
        </button>
    </div>

    <div class="admin-topbar-actions">
        <!-- GPS broadcast toggle — OFF until the rider taps it (no surprise prompts) -->
        <button type="button" class="rider-gps-pill is-off" id="riderGpsToggle" aria-pressed="false" title="Share my live location while delivering">
            <span class="material-symbols-sharp">location_off</span>
            <span class="rider-gps-label">GPS off</span>
        </button>

        <!-- Theme Toggle -->
        <button type="button" class="theme-toggle-btn" id="theme_toggle" aria-label="Toggle dark mode">
            <span class="material-symbols-sharp">light_mode</span>
        </button>

        <!-- Role-scoped Notification Bell -->
        <div class="panel-bell-wrap" id="panelBell" data-endpoint="api/notifications.php">
            <button type="button" class="panel-bell-btn" id="panelBellBtn" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
                <span class="material-symbols-sharp">notifications</span>
                <span class="panel-bell-badge" id="panelBellBadge">0</span>
            </button>
            <div class="panel-bell-menu" id="panelBellMenu" aria-hidden="true">
                <div class="panel-bell-head">
                    <strong>Notifications</strong>
                    <button type="button" class="panel-bell-markall" id="panelBellMarkAll">Mark all read</button>
                </div>
                <div id="panelBellList">
                    <div class="panel-bell-empty">Loading…</div>
                </div>
            </div>
        </div>

        <!-- Profile Dropdown -->
        <div class="admin-profile" id="profile_dropdown">
            <button type="button" class="admin-profile-btn" id="profile_menu_btn" aria-haspopup="true" aria-expanded="false">
                <div class="profile-photo">
                    <?php if ($avatarUrl !== ''): ?>
                        <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($panelName) ?>">
                    <?php else: ?>
                        <span style="display:flex;align-items:center;justify-content:center;width:2.8rem;height:2.8rem;border-radius:50%;background:var(--clr-primary);color:#fff;font-weight:700;font-size:1rem;">
                            <?= htmlspecialchars($initials) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="admin-profile-copy">
                    <strong><?= htmlspecialchars($panelName) ?></strong>
                    <small><?= htmlspecialchars($roleLabel) ?></small>
                </div>
                <span class="material-symbols-sharp profile-chevron">expand_more</span>
            </button>
            <div class="admin-profile-menu" aria-hidden="true">
                <a href="profile.php"><span class="material-symbols-sharp">person</span> My Profile</a>
                <a href="earnings.php"><span class="material-symbols-sharp">payments</span> Earnings</a>
                <a href="notifications.php"><span class="material-symbols-sharp">notifications</span> Notifications</a>
                <a href="../includes/panel_logout.php" class="profile-menu-logout"><span class="material-symbols-sharp">logout</span> Logout</a>
            </div>
        </div>
    </div>
</header>

<!-- Mobile sidebar backdrop -->
<div class="sidebar-backdrop" id="sidebar_backdrop"></div>
