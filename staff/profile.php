<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'staff');

$pageTitle = 'My Profile';

$flash = $_SESSION['msg'] ?? null;
unset($_SESSION['msg']);

$name  = trim((string)($panelUser['name'] ?? '')) ?: 'Floor Staff';
$email = (string)($panelUser['email'] ?? '');

$imgRel = preg_replace('#^(?:\.\.?/)+#', '', (string)($panelUser['user_img'] ?? ''));
$imgRel = ltrim((string)$imgRel, '/');
$avatarUrl = ($imgRel !== '' && file_exists(__DIR__ . '/../' . $imgRel)) ? '../' . $imgRel : '';
$parts = preg_split('/\s+/', $name);
$initials = strtoupper(substr($parts[0] ?? 'S', 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

$csrf   = function_exists('panel_csrf_token') ? panel_csrf_token() : '';
$return = '/Merobhoj/staff/profile.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> · Mero Bhoj</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  <link rel="stylesheet" href="../assets/css/admin2.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin2.css') ?>">
  <link rel="stylesheet" href="../assets/css/panel.css?v=<?= filemtime(__DIR__ . '/../assets/css/panel.css') ?>">
</head>
<body class="admin-page">
  <?php include __DIR__ . '/topbar.php'; ?>
  <div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-page-main">

<div class="panel-head" style="margin-bottom: 1.5rem;">
    <h2>My Profile</h2>
    <p class="text-muted">Manage your account details and password.</p>
</div>

<?php if ($flash && is_array($flash)): ?>
    <div style="padding:1rem; border-radius:var(--card-border-radius); background:var(--clr-card-background); border-left:4px solid <?= ($flash['type'] ?? '') === 'success' ? 'var(--clr-success)' : 'var(--clr-danger)' ?>; margin-bottom:1.5rem;">
        <span><?= htmlspecialchars((string)($flash['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem; align-items:start;">

    <!-- Account Details -->
    <div style="background:var(--clr-card-background); padding:1.5rem; border-radius:var(--card-border-radius); border:1px solid var(--clr-border);">
        <h3>Account</h3>
        <div style="display:flex; align-items:center; gap:1rem; margin:1.2rem 0;">
            <?php if ($avatarUrl !== ''): ?>
                <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($name) ?>" style="width:4rem;height:4rem;border-radius:50%;object-fit:cover;">
            <?php else: ?>
                <span style="display:grid;place-items:center;width:4rem;height:4rem;border-radius:50%;background:var(--clr-primary);color:#fff;font-weight:700;font-size:1.3rem;"><?= htmlspecialchars($initials) ?></span>
            <?php endif; ?>
            <div>
                <div style="font-weight:700;color:var(--clr-dark);font-size:1.05rem;"><?= htmlspecialchars($name) ?></div>
                <div style="color:var(--clr-dark-variant);font-size:0.85rem;"><?= htmlspecialchars($email) ?></div>
                <span class="panel-status st-confirmed" style="margin-top:0.3rem;">Floor Staff</span>
            </div>
        </div>

        <form action="../includes/panel_profile_update.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="profile">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($return) ?>">
            <div class="form-group">
                <label for="pfName">Display Name</label>
                <input type="text" id="pfName" name="name" value="<?= htmlspecialchars($name) ?>" required maxlength="100">
            </div>
            <div class="form-group">
                <label for="pfAvatar">Profile Photo <small class="text-muted">(JPG/PNG/WebP)</small></label>
                <input type="file" id="pfAvatar" name="avatar" accept="image/png,image/jpeg,image/webp">
            </div>
            <button type="submit" class="qrm-btn qrm-btn-primary">Save Profile</button>
        </form>
    </div>

    <!-- Password Update -->
    <div style="background:var(--clr-card-background); padding:1.5rem; border-radius:var(--card-border-radius); border:1px solid var(--clr-border);">
        <h3>Change Password</h3>
        <form action="../includes/panel_profile_update.php" method="POST" id="pwForm">
            <input type="hidden" name="action" value="password">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($return) ?>">
            <div class="form-group">
                <label for="curPw">Current Password</label>
                <input type="password" id="curPw" name="current_password" required>
            </div>
            <div class="form-group">
                <label for="newPw">New Password</label>
                <input type="password" id="newPw" name="new_password" required minlength="6">
            </div>
            <div class="form-group">
                <label for="confPw">Confirm New Password</label>
                <input type="password" id="confPw" name="confirm_password" required minlength="6">
            </div>
            <button type="submit" class="qrm-btn qrm-btn-primary">Update Password</button>
        </form>
    </div>
</div>

<script>
document.getElementById('pwForm').addEventListener('submit', function (e) {
    const n = document.getElementById('newPw').value;
    const c = document.getElementById('confPw').value;
    if (n !== c) {
        e.preventDefault();
        alert('New passwords do not match');
    }
});
</script>

    </main>
  </div>
  <div class="toast-container" id="toastContainer"></div>
  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
  <script src="../assets/js/panel_notifications.js?v=<?= filemtime(__DIR__ . '/../assets/js/panel_notifications.js') ?>"></script>
</body>
</html>
