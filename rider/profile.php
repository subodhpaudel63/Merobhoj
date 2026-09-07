<?php
/**
 * Rider — profile: view details, update name/phone/avatar, change password.
 * Posts to the shared includes/panel_profile_update.php. The phone number is
 * the one the customer sees on their tracking page while the order is out.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'rider');
$pageTitle = 'My Profile';

// Flash message (set by panel_profile_update.php)
$flash = $_SESSION['msg'] ?? null;
unset($_SESSION['msg']);

$name  = trim((string)($panelUser['name'] ?? '')) ?: 'Delivery Rider';
$email = (string)($panelUser['email'] ?? '');

// current_panel_user() does not select phone — read it for this page only.
$phone = '';
$pq = $conn->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
if ($pq) {
    $pid = (int)($panelUser['id'] ?? 0);
    $pq->bind_param('i', $pid);
    $pq->execute();
    $phone = (string)($pq->get_result()->fetch_assoc()['phone'] ?? '');
    $pq->close();
}

$imgRel = preg_replace('#^(?:\.\.?/)+#', '', (string)($panelUser['user_img'] ?? ''));
$imgRel = ltrim((string)$imgRel, '/');
$avatarUrl = ($imgRel !== '' && file_exists(__DIR__ . '/../' . $imgRel)) ? '../' . $imgRel : '';
$parts = preg_split('/\s+/', $name);
$initials = strtoupper(substr($parts[0] ?? 'R', 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

$csrf   = panel_csrf_token();
$return = '/Merobhoj/rider/profile.php';

$pageTitle = $pageTitle ?? 'Delivery Rider';
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


<div class="panel-page-header">
    <div>
        <h1>My Profile</h1>
        <p>Manage your account details and password.</p>
    </div>
</div>

<?php if ($flash && is_array($flash)): ?>
    <div class="panel-card" style="border-left:4px solid <?= ($flash['type'] ?? '') === 'success' ? 'var(--clr-success)' : 'var(--clr-danger)' ?>; display:flex; align-items:center; gap:0.6rem;">
        <span class="material-symbols-sharp" style="color:<?= ($flash['type'] ?? '') === 'success' ? 'var(--clr-success)' : 'var(--clr-danger)' ?>;">
            <?= ($flash['type'] ?? '') === 'success' ? 'check_circle' : 'error' ?>
        </span>
        <span><?= htmlspecialchars((string)($flash['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:1.5rem; align-items:start;">

    <!-- Profile / avatar -->
    <div class="panel-card">
        <h2>Account</h2>
        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.2rem;">
            <?php if ($avatarUrl !== ''): ?>
                <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($name) ?>" style="width:4rem;height:4rem;border-radius:50%;object-fit:cover;">
            <?php else: ?>
                <span style="display:grid;place-items:center;width:4rem;height:4rem;border-radius:50%;background:var(--clr-primary);color:#fff;font-weight:700;font-size:1.3rem;"><?= htmlspecialchars($initials) ?></span>
            <?php endif; ?>
            <div>
                <div style="font-weight:700;color:var(--clr-dark);font-size:1.05rem;"><?= htmlspecialchars($name) ?></div>
                <div style="color:var(--clr-dark-variant);font-size:0.85rem;"><?= htmlspecialchars($email) ?></div>
                <span class="panel-status st-delivering" style="margin-top:0.3rem;">Delivery Rider</span>
            </div>
        </div>

        <form action="../includes/panel_profile_update.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="profile">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($return) ?>">
            <div class="panel-field">
                <label for="pfName">Display name</label>
                <input type="text" id="pfName" name="name" value="<?= htmlspecialchars($name) ?>" required maxlength="100">
            </div>
            <div class="panel-field">
                <label for="pfPhone">Phone <span style="text-transform:none;font-weight:400;">(shown to the customer while you deliver)</span></label>
                <input type="tel" id="pfPhone" name="phone" value="<?= htmlspecialchars($phone) ?>" maxlength="20" placeholder="98XXXXXXXX">
            </div>
            <div class="panel-field">
                <label for="pfAvatar">Profile photo <span style="text-transform:none;font-weight:400;">(optional, JPG/PNG/WebP)</span></label>
                <input type="file" id="pfAvatar" name="avatar" accept="image/png,image/jpeg,image/webp">
            </div>
            <button type="submit" class="qrm-btn qrm-btn-primary"><span class="material-symbols-sharp">save</span> Save profile</button>
        </form>
    </div>

    <!-- Password -->
    <div class="panel-card">
        <h2>Change password</h2>
        <form action="../includes/panel_profile_update.php" method="POST" id="pwForm">
            <input type="hidden" name="action" value="password">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($return) ?>">
            <div class="panel-field">
                <label for="curPw">Current password</label>
                <input type="password" id="curPw" name="current_password" required>
            </div>
            <div class="panel-field">
                <label for="newPw">New password</label>
                <input type="password" id="newPw" name="new_password" required minlength="6">
            </div>
            <div class="panel-field">
                <label for="confPw">Confirm new password</label>
                <input type="password" id="confPw" name="confirm_password" required minlength="6">
            </div>
            <button type="submit" class="qrm-btn qrm-btn-primary"><span class="material-symbols-sharp">lock_reset</span> Update password</button>
        </form>
    </div>
</div>

<script>
// Client-side guard: new password must match confirmation before posting.
document.getElementById('pwForm').addEventListener('submit', function (e) {
    const n = document.getElementById('newPw').value;
    const c = document.getElementById('confPw').value;
    if (n !== c) {
        e.preventDefault();
        if (window.showToast) window.showToast('New passwords do not match', 'error');
    }
});
</script>

    </main>
  </div>
  <div class="toast-container" id="toastContainer"></div>
  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
  <script src="../assets/js/panel_notifications.js?v=<?= filemtime(__DIR__ . '/../assets/js/panel_notifications.js') ?>"></script>
<?php foreach (($pageScripts ?? []) as $src): ?>
  <script src="<?= htmlspecialchars($src) ?>?v=<?= @filemtime(__DIR__ . '/' . ltrim($src, '/')) ?: time() ?>"></script>
<?php endforeach; ?>
</body>
</html>
