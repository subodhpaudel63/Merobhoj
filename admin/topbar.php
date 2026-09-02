<?php
// Shared top header bar for Mero Bhoj Admin Panel
// Includes: collapse/expand menu toggle, date range picker,
// theme toggle, notification bell (injected by notifications.js)
// and the admin profile dropdown.
?>
<header class="admin-topbar" id="admin_topbar">
    <div class="admin-topbar-left">
        <button type="button" id="menu_toggle" class="admin-menu-button" aria-label="Toggle navigation" aria-expanded="true" onclick="mkjToggleSidebar(event)">
            <span class="material-symbols-sharp">menu</span>
        </button>
    </div>

    <div class="admin-topbar-actions">
        <!-- Date Range Picker -->
        <div class="daterange-picker" id="daterange_picker">
            <button type="button" class="daterange-btn" id="daterange_btn" aria-haspopup="true" aria-expanded="false">
                <span class="material-symbols-sharp">calendar_today</span>
                <span id="daterange_label">Aug 21 &ndash; Aug 27, 2026</span>
                <span class="material-symbols-sharp daterange-chevron">expand_more</span>
            </button>
            <div class="daterange-panel" id="daterange_panel" aria-hidden="true">
                <div class="daterange-field">
                    <label for="daterange_from">From</label>
                    <input type="date" id="daterange_from" value="2026-08-21">
                </div>
                <div class="daterange-field">
                    <label for="daterange_to">To</label>
                    <input type="date" id="daterange_to" value="2026-08-27">
                </div>
                <button type="button" id="daterange_apply" class="daterange-apply">Apply</button>
            </div>
        </div>

        <!-- Theme Toggle -->
        <button type="button" class="theme-toggle-btn" id="theme_toggle" aria-label="Toggle dark mode">
            <span class="material-symbols-sharp">light_mode</span>
        </button>

        <!-- Notification bell is injected here by notifications.js -->

        <!-- Profile Dropdown -->
        <div class="admin-profile" id="profile_dropdown">
            <button type="button" class="admin-profile-btn" id="profile_menu_btn" aria-haspopup="true" aria-expanded="false">
                <div class="profile-photo">
                    <img src="../assets/img/usersprofiles/adminpic.jpg" alt="Admin profile">
                </div>
                <div class="admin-profile-copy">
                    <strong>Subodh Admin</strong>
                    <small>Administrator</small>
                </div>
                <span class="material-symbols-sharp profile-chevron">expand_more</span>
            </button>
            <div class="admin-profile-menu" aria-hidden="true">
                <a href="#"><span class="material-symbols-sharp">person</span> My Profile</a>
                <a href="#"><span class="material-symbols-sharp">settings</span> Settings</a>
                <a href="../includes/logout.php" class="profile-menu-logout"><span class="material-symbols-sharp">logout</span> Logout</a>
            </div>
        </div>
    </div>
</header>

<!-- Mobile sidebar backdrop -->
<div class="sidebar-backdrop" id="sidebar_backdrop"></div>