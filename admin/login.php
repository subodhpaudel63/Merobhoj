<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';

$msg = $_SESSION['msg'] ?? null;
unset($_SESSION['msg']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mero Bhoj - Restaurant Management Login</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --red: #f0181d; --black: #15171b; --muted: #757e90; --line: #dde1e7; }
    html, body { width: 100%; min-height: 100%; font-family: Arial, Helvetica, sans-serif; }
    body { background: #080808; overflow-x: hidden; }
    .page { min-height: 100vh; display: flex; background: #080808; }
    .brand-panel {
      width: 44%; min-height: 100vh;
      background-color: #080808;
      background-image: url("../assets/img/gallery/merobhoj-left-panel(1).png");
      background-position: center;
      background-size: contain;
      background-repeat: no-repeat;
    }
    .login-panel {
      width: 56%; min-height: 100vh; background: #fff; border-radius: 18px 0 0 18px;
      display: flex; justify-content: center; align-items: flex-start; padding: 96px 24px 48px; position: relative; z-index: 2;
      overflow-y: auto;
    }
    .login-wrap { width: min(100%, 400px); }
    .welcome-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 24px; }
    .welcome h1 { font-size: 32px; line-height: 1.05; font-weight: 800; letter-spacing: -1px; color: var(--black); }
    .welcome p { margin-top: 10px; font-size: 15px; line-height: 1.3; color: var(--muted); }
    .top-art { width: 72px; height: 56px; object-fit: contain; flex: 0 0 72px; }
    .role-switch {
      height: 44px; display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #dedfe3;
      border-radius: 10px; overflow: hidden; margin-bottom: 26px; background: #f8f9fb; padding: 4px;
    }
    .role {
      position: relative; border: 0; border-radius: 10px; background: transparent; display: flex; align-items: center;
      justify-content: center; gap: 9px; color: #69717f; font-size: 13px; font-weight: 700; cursor: pointer;
      transition: color .18s ease, background .18s ease, box-shadow .18s ease;
    }
    .role + .role { border-left: 0; }
    .role.active { color: var(--red); background: #fff; box-shadow: 0 2px 8px rgba(21, 23, 27, .1); }
    .role svg { width: 16px; height: 16px; stroke: currentColor; }
    .field { margin-bottom: 20px; }
    .field label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 700; color: #17191d; }
    .input-box {
      height: 43px; border: 1.5px solid var(--line); border-radius: 9px; display: flex;
      align-items: center; padding: 0 19px; transition: .2s; background: #fff;
    }
    .input-box:focus-within { border-color: #f59696; box-shadow: 0 0 0 4px rgba(240, 24, 29, .07); }
    .input-box > svg { width: 17px; height: 17px; color: var(--red); flex: 0 0 auto; }
    .input-box input { width: 100%; height: 100%; border: 0; outline: 0; background: transparent; padding: 0 12px; font-size: 14px; color: #222; }
    .input-box input::placeholder { color: #858c9a; }
    .password-toggle { border: 0; background: transparent; padding: 4px; cursor: pointer; color: #69717f; }
    .password-toggle svg { width: 16px; height: 16px; }
    .error { margin-top: 8px; color: var(--red); font-size: 13px; }
    .options { display: flex; align-items: center; justify-content: space-between; font-size: 15px; margin: 0 0 24px; }
    .remember { display: flex; align-items: center; gap: 12px; cursor: pointer; }
    .remember input { appearance: none; width: 25px; height: 25px; border: 2px solid #9aa1ac; border-radius: 5px; position: relative; }
    .remember input:checked { background: var(--red); border-color: var(--red); }
    .remember input:checked::after { content: "✓"; color: #fff; position: absolute; font-size: 17px; left: 4px; top: -1px; }
    .forgot, .account a { color: var(--red); text-decoration: none; font-weight: 500; }
    .sign-in {
      width: 100%; height: 43px; border: 0; border-radius: 8px; background: linear-gradient(180deg, #f71b20, #ec1116);
      color: #fff; font-size: 14px; font-weight: 800; cursor: pointer; box-shadow: 0 10px 22px rgba(240, 24, 29, .18);
    }
    .sign-in:disabled { opacity: .8; cursor: wait; }
    .divider { display: flex; align-items: center; gap: 14px; color: #202329; font-size: 15px; margin: 14px 0; }
    .divider::before, .divider::after { content: ""; height: 1px; background: #e0e2e7; flex: 1; }
    .divider span { width: 38px; height: 38px; border: 1px solid #e0e2e7; border-radius: 50%; display: grid; place-items: center; background: #fff; }
    .account { text-align: center; color: #7c8390; font-size: 15px; }
    .alert { margin-bottom: 14px; padding: 10px 14px; border-radius: 10px; font-size: 13px; }
    .alert-error { color: #b42318; background: #fff1f0; border: 1px solid #ffc7c3; }
    .alert-success { color: #18794e; background: #ecfdf3; border: 1px solid #a7f3d0; }
    @media (max-width: 1100px) {
      .brand-panel { width: 40%; } .login-panel { width: 60%; padding: 72px 24px 40px; }
      .welcome h1 { font-size: 30px; } .welcome p { font-size: 14px; }
    }
    @media (max-width: 760px) {
      .brand-panel { display: none; } .login-panel { width: 100%; min-height: 100vh; border-radius: 0; padding: 40px 22px 30px; align-items: flex-start; }
      .welcome-row { margin-bottom: 24px; } .welcome h1 { font-size: 30px; } .welcome p { font-size: 14px; margin-top: 8px; }
      .top-art { width: 62px; height: 48px; flex-basis: 62px; }
      .role-switch { height: 44px; margin-bottom: 24px; }
      .role { font-size: 13px; gap: 8px; } .role svg { width: 16px; height: 16px; } .field { margin-bottom: 18px; }
      .field label { font-size: 13px; margin-bottom: 8px; } .input-box { height: 43px; padding: 0 13px; }
      .input-box input { font-size: 14px; padding: 0 10px; } .options { font-size: 13px; margin-bottom: 22px; }
      .sign-in { height: 43px; font-size: 14px; }
    }
    @media (max-width: 420px) {
      .login-panel { padding-left: 17px; padding-right: 17px; } .welcome h1 { font-size: 28px; }
      .welcome p { font-size: 13px; } .top-art { width: 52px; height: 42px; flex-basis: 52px; } .role { font-size: 12px; }
    }
  </style>
</head>
<body>
<div class="page">
  <section class="brand-panel" aria-label="Mero Bhoj branding"></section>
  <main class="login-panel">
    <div class="login-wrap">
      <div class="welcome-row">
        <div class="welcome">
          <h1>Welcome back!</h1>
          <p>Sign in to your restaurant account</p>
        </div>
        <img class="top-art" src="../assets/img/gallery/merobhoj-top-art.png" alt="">
      </div>

      <div class="role-switch" role="tablist" aria-label="Account type">
        <button class="role active" type="button" data-role="owner" aria-selected="true" aria-pressed="true">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M7 10V7.5C7 5.57 8.57 4 10.5 4S14 5.57 14 7.5V10"/><path d="M5 10h14l-1 8H6l-1-8Z"/><path d="M9 14h6M8 10V8M16 10V8"/></svg>
          <span>Owner / Chef</span>
        </button>
        <button class="role" type="button" data-role="staff" aria-selected="false" aria-pressed="false">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path d="M3 15h12v4H3z"/><path d="M15 12h3l3 3v4h-6z"/><circle cx="7" cy="19" r="2"/><circle cx="18" cy="19" r="2"/><path d="M6 15V8h7v7M9 8V6h4"/></svg>
          <span>Staff / Rider</span>
        </button>
      </div>

      <?php if (is_array($msg)): ?>
        <div class="alert alert-<?= htmlspecialchars($msg['type'] === 'success' ? 'success' : 'error', ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars((string)($msg['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form id="loginForm" action="../includes/panel_login_process.php" method="POST" novalidate>
        <input type="hidden" name="login_source" value="admin">
        <input type="hidden" name="portal_role" id="portalRole" value="owner">
        <div class="field">
          <label for="identity">Email or Phone Number</label>
          <div class="input-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6.5 4.5h3l1.5 4-2 1.7a15 15 0 0 0 5.8 5.8l1.7-2 4 1.5v3c0 1.1-.9 2-2 2C11.3 20.5 3.5 12.7 3.5 5.5c0-1.1.9-2 2-2Z"/></svg>
            <input id="identity" name="email" type="email" autocomplete="username" placeholder="Enter your email" required autofocus>
          </div>
          <div class="error" id="identityError" hidden>Please enter your email address.</div>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <div class="input-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>
            <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
            <button class="password-toggle" id="togglePassword" type="button" aria-label="Show password">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/><path id="slash" d="M4 4l16 16"/></svg>
            </button>
          </div>
          <div class="error" id="passwordError" hidden>Please enter your password.</div>
        </div>
        <div class="options">
          <label class="remember"><input id="remember" type="checkbox"><span>Remember me</span></label>
          <a href="mailto:admin@merobhoj.com" class="forgot">Forgot password?</a>
        </div>
        <button class="sign-in" id="signIn" type="submit">Sign In</button>
      </form>
      <div class="divider"><span>or</span></div>
      <p class="account">Customer? <a href="../login.php">Go to customer login</a></p>
    </div>
  </main>
</div>
<script>
  const roles = document.querySelectorAll('.role');
  const password = document.getElementById('password');
  const form = document.getElementById('loginForm');
  const toggle = document.getElementById('togglePassword');
  const slash = document.getElementById('slash');
  const portalRole = document.getElementById('portalRole');
  roles.forEach((role) => role.addEventListener('click', () => {
  roles.forEach((item) => { item.classList.remove('active'); item.setAttribute('aria-selected', 'false'); item.setAttribute('aria-pressed', 'false'); });
    role.classList.add('active');
    role.setAttribute('aria-selected', 'true');
  role.setAttribute('aria-pressed', 'true');
  portalRole.value = role.dataset.role;
  }));
  toggle.addEventListener('click', () => {
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    slash.style.display = show ? 'none' : 'block';
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
  form.addEventListener('submit', (event) => {
    const identityError = document.getElementById('identityError');
    const passwordError = document.getElementById('passwordError');
    const invalidIdentity = !document.getElementById('identity').value.trim();
    const invalidPassword = !password.value.trim();
    identityError.hidden = !invalidIdentity;
    passwordError.hidden = !invalidPassword;
    if (invalidIdentity || invalidPassword) event.preventDefault();
    else document.getElementById('signIn').disabled = true;
  });
</script>
</body>
</html>
