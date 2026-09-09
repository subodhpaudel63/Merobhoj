<?php
/**
 * panel_profile_update.php — shared profile/password handler for ALL panel roles.
 *
 * Two POST actions (both CSRF-protected, both scoped to the logged-in user's own
 * row — a user can never edit another account):
 *   action=profile   → update display name + optional avatar upload
 *   action=password  → verify current password, set a new hashed password
 *
 * On completion sets $_SESSION['msg'] = ['type'=>…, 'text'=>…] and redirects to a
 * validated same-app path (the posting page), else the role home.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/role_check.php';

/* Must be an authenticated panel user (cookie re-validated against the DB). */
$panelUser = current_panel_user($conn);
if ($panelUser === null) {
    header('Location: /Merobhoj/admin/login.php');
    exit();
}

$userId   = (int)$panelUser['id'];
$roleHome = panel_home_for_role((string)$panelUser['user_type']);

/* ---- Resolve a safe redirect target (must be a local /Merobhoj/ path) ---- */
$redirect = (string)($_POST['redirect'] ?? '');
$isSafe = $redirect !== ''
    && $redirect[0] === '/'
    && strncmp($redirect, '//', 2) !== 0
    && strpos($redirect, '\\') === false
    && strpos($redirect, '..') === false
    && strpos($redirect, ':') === false
    && strncmp($redirect, '/Merobhoj/', 10) === 0;
$back = $isSafe ? $redirect : $roleHome;

function panel_profile_redirect(string $type, string $text, string $back): void
{
    $_SESSION['msg'] = ['type' => $type, 'text' => $text];
    header('Location: ' . $back);
    exit();
}

/* ---- Method + CSRF ------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    panel_profile_redirect('error', 'Invalid request.', $back);
}
if (!verify_panel_csrf((string)($_POST['csrf'] ?? ''))) {
    panel_profile_redirect('error', 'Security token expired — please try again.', $back);
}

$action = (string)($_POST['action'] ?? '');

/* ========================= UPDATE PROFILE ================================= */
if ($action === 'profile') {
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        panel_profile_redirect('error', 'Display name cannot be empty.', $back);
    }
    if (mb_strlen($name) > 100) {
        $name = mb_substr($name, 0, 100);
    }

    /* Optional contact number — only touched when the form actually posts it,
       so the chef/staff profile forms (which don't) keep working unchanged. */
    $phone = null;
    if (array_key_exists('phone', $_POST)) {
        $phone = trim((string)$_POST['phone']);
        if ($phone !== '') {
            if (!preg_match('/^[0-9+][0-9 +\-()]{4,19}$/', $phone)) {
                panel_profile_redirect('error', 'Enter a valid phone number (digits, spaces, + or -).', $back);
            }
        } else {
            $phone = null; // blank clears it
        }
    }

    $newImgPath = null;

    // Optional avatar upload.
    if (isset($_FILES['avatar']) && ($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['avatar'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            panel_profile_redirect('error', 'Photo upload failed. Please try again.', $back);
        }
        if ($f['size'] > 3 * 1024 * 1024) {
            panel_profile_redirect('error', 'Photo must be 3 MB or smaller.', $back);
        }

        // Validate by real MIME, not the client-supplied name.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? finfo_file($finfo, $f['tmp_name']) : '';
        if ($finfo) finfo_close($finfo);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            panel_profile_redirect('error', 'Photo must be a JPG, PNG or WebP image.', $back);
        }

        $ext     = $allowed[$mime];
        $dir     = __DIR__ . '/../assets/img/usersprofiles';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $fname   = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest    = $dir . '/' . $fname;

        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            panel_profile_redirect('error', 'Could not save the uploaded photo.', $back);
        }
        // Store root-relative (matches the working avatar convention).
        $newImgPath = 'assets/img/usersprofiles/' . $fname;
    }

    // Column names are hard-coded literals; only the values are bound.
    $fields = ['name = ?'];
    $types  = 's';
    $values = [$name];

    if (array_key_exists('phone', $_POST)) {
        $fields[] = 'phone = ?';
        $types   .= 's';
        $values[] = $phone;
    }
    if ($newImgPath !== null) {
        $fields[] = 'user_img = ?';
        $types   .= 's';
        $values[] = $newImgPath;
    }

    $types   .= 'i';
    $values[] = $userId;

    $stmt = $conn->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param($types, ...$values);
    }

    if (!$stmt || !$stmt->execute()) {
        if ($stmt) $stmt->close();
        panel_profile_redirect('error', 'Could not update your profile.', $back);
    }
    $stmt->close();
    panel_profile_redirect('success', 'Profile updated.', $back);
}

/* ========================= CHANGE PASSWORD =============================== */
if ($action === 'password') {
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        panel_profile_redirect('error', 'All password fields are required.', $back);
    }
    if (strlen($new) < 6) {
        panel_profile_redirect('error', 'New password must be at least 6 characters.', $back);
    }
    if ($new !== $confirm) {
        panel_profile_redirect('error', 'New passwords do not match.', $back);
    }

    // Fetch the current hash (by id — ownership guaranteed).
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($current, (string)$row['password'])) {
        panel_profile_redirect('error', 'Your current password is incorrect.', $back);
    }
    if (password_verify($new, (string)$row['password'])) {
        panel_profile_redirect('error', 'New password must be different from the current one.', $back);
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $upd  = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $upd->bind_param('si', $hash, $userId);
    if (!$upd->execute()) {
        $upd->close();
        panel_profile_redirect('error', 'Could not update your password.', $back);
    }
    $upd->close();
    panel_profile_redirect('success', 'Password changed successfully.', $back);
}

panel_profile_redirect('error', 'Unknown action.', $back);
