<?php
/**
 * Shared panel top header — Staff.
 * Mirrors admin/topbar.php markup/IDs so assets/js/adminscript.js wires up
 * the sidebar toggle, theme toggle and profile dropdown.
 */
if (!isset($panelUser) || !is_array($panelUser)) {
    require_once __DIR__ . '/../includes/role_check.php';
    $resolvedPanelUser = (isset($conn) && $conn instanceof mysqli && function_exists('current_panel_user'))
        ? current_panel_user($conn)
        : null;
    $panelUser = $resolvedPanelUser ?? [
        'name' => 'Staff',
        'email' => '',
        'user_type' => 'staff',
        'user_img' => ''
    ];
}

$panelName  = trim((string)($panelUser['name'] ?? '')) ?: 'Floor Staff';
$panelRole  = (string)($panelUser['user_type'] ?? 'staff');
$roleLabels = ['chef' => 'Kitchen Chef', 'staff' => 'Floor Staff', 'rider' => 'Delivery Rider', 'admin' => 'Administrator', 'manager' => 'Manager'];
$roleLabel  = $roleLabels[$panelRole] ?? 'Floor Staff';

$avatarUrl = '';
$imgRel = preg_replace('#^(?:\.\.?/)+#', '', (string)($panelUser['user_img'] ?? ''));
$imgRel = ltrim((string)$imgRel, '/');
if ($imgRel !== '' && file_exists(__DIR__ . '/../' . $imgRel)) {
    $avatarUrl = '../' . $imgRel;
}
$parts = preg_split('/\s+/', $panelName);
$initials = strtoupper(substr($parts[0] ?? 'S', 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
?>
<script>
    window.PANEL_CSRF = <?= json_encode(function_exists('panel_csrf_token') ? panel_csrf_token() : '') ?>;
    window.PANEL_ROLE = <?= json_encode($panelRole) ?>;
</script>

<header class="admin-topbar" id="admin_topbar">
    <div class="admin-topbar-left">
        <button type="button" id="menu_toggle" class="admin-menu-button" aria-label="Toggle navigation" aria-expanded="true">
            <span class="material-symbols-sharp">menu</span>
        </button>
    </div>

    <div class="admin-topbar-actions">
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
                <a href="notifications.php"><span class="material-symbols-sharp">notifications</span> Notifications</a>
                <a href="../includes/panel_logout.php" class="profile-menu-logout"><span class="material-symbols-sharp">logout</span> Logout</a>
            </div>
        </div>
    </div>
</header>

<!-- Mobile sidebar backdrop -->
<div class="sidebar-backdrop" id="sidebar_backdrop"></div>
