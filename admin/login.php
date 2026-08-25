<?php
session_start();
if (isset($_COOKIE['admin_type'])) {
    require_once __DIR__ . '/../includes/auth_check.php';
    $userType = decrypt($_COOKIE['admin_type'], SECRET_KEY);
    if ($userType === 'admin') {
        header('Location: /Merobhoj/admin/index.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | Mero Bhoj</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

:root {
  --orange: #ff512f;
  --gold:   #ffb347;
  --dark:   #1a1a2e;
  --text:   #2d2d2d;
  --muted:  #888;
  --border: #e8e8e8;
}

html, body {
  height: 100%;
  font-family: 'Poppins', sans-serif;
  overflow: hidden;
  background: linear-gradient(135deg, #1e1e1e, #2c3e50, #8e2de2, #ff512f);
  background-size: 400% 400%;
  animation: gradientMove 12s ease infinite;
  display: flex;
  align-items: center;
  justify-content: center;
}
@keyframes gradientMove {
  0%   { background-position: 0% 50%; }
  50%  { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}

/* ── FLOATING FOOD OBJECTS ── */
.floater {
  position: fixed;
  pointer-events: none;
  z-index: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  animation: floatUp var(--ft, 6s) ease-in-out infinite var(--delay, 0s);
  opacity: 0.78;
}
@keyframes floatUp {
  0%,100% { transform: translateY(0) rotate(var(--r0, 0deg)); }
  50%      { transform: translateY(-22px) rotate(var(--r1, 8deg)); }
}

/* bowl */
.f-bowl {
  width: 72px; height: 72px;
  background: white;
  box-shadow: 0 6px 24px rgba(0,0,0,0.15);
}
/* plate */
.f-plate {
  width: 64px; height: 64px;
  background: white;
  border: 4px solid var(--gold);
  box-shadow: 0 6px 20px rgba(0,0,0,0.13);
}
/* spice jar */
.f-jar {
  width: 46px; height: 64px;
  background: white;
  border-radius: 10px;
  box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}
/* pepper */
.f-pepper {
  width: 58px; height: 58px;
  background: white;
  box-shadow: 0 6px 20px rgba(0,0,0,0.13);
}
/* ladle */
.f-ladle {
  width: 52px; height: 76px;
  background: white;
  border-radius: 50% 50% 20px 20px;
  box-shadow: 0 6px 20px rgba(0,0,0,0.13);
}

.floater i {
  font-size: var(--fs, 28px);
  color: var(--fc, #ff512f);
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
}

/* ── CARD ── */
.card {
  position: relative; z-index: 2;
  width: 400px;
  background: #ffffff;
  border-radius: 24px;
  padding: 40px 40px 36px;
  box-shadow:
    0 20px 60px rgba(0,0,0,0.35),
    0 0 0 1px rgba(255,255,255,0.8);
  animation: cardIn .7s cubic-bezier(.22,1,.36,1) both;
}
@keyframes cardIn {
  from { opacity:0; transform: translateY(28px) scale(.96); }
  to   { opacity:1; transform: translateY(0) scale(1); }
}

/* orange top stripe */
.card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; height: 5px;
  background: linear-gradient(90deg, #ff512f, #ffb347, #ff512f);
  background-size: 200% 100%;
  animation: stripeMove 3s linear infinite;
  border-radius: 24px 24px 0 0;
}
@keyframes stripeMove {
  0%   { background-position: 0% 0%; }
  100% { background-position: 200% 0%; }
}

/* ── LOGO ── */
.logo-area {
  text-align: center;
  margin-bottom: 20px;
}
.logo-row {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: linear-gradient(135deg, #fff3e0, #ffe0b2);
  border: 2px solid #ffcc80;
  border-radius: 50px;
  padding: 10px 22px;
  box-shadow: 0 4px 16px rgba(255,165,0,0.2);
  margin-bottom: 6px;
}
.logo-row i {
  font-size: 24px;
  color: var(--orange);
  filter: drop-shadow(0 2px 4px rgba(255,81,47,0.3));
}
.logo-row span {
  font-size: 18px;
  font-weight: 700;
  color: #1a1a1a;
  letter-spacing: 0.3px;
}
.logo-sub {
  font-size: 10px;
  font-weight: 400;
  letter-spacing: 3px;
  text-transform: uppercase;
  color: var(--muted);
}

/* ── DIVIDER ── */
.divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 4px 0 20px;
}
.divider span { flex:1; height:1px; background: var(--border); }
.divider-dot {
  width: 6px; height: 6px;
  background: var(--gold);
  border-radius: 50%;
  opacity: .7;
}

/* ── WELCOME ── */
.welcome { text-align: center; margin-bottom: 22px; }
.welcome h3 { font-size: 15px; font-weight: 600; color: var(--text); }
.welcome p  { font-size: 12px; color: var(--muted); margin-top: 3px; }

/* ── FIELDS ── */
.form-group { position: relative; margin-bottom: 18px; }
.form-group label {
  display: block;
  font-size: 10.5px; font-weight: 600;
  letter-spacing: 1.5px; text-transform: uppercase;
  color: var(--muted); margin-bottom: 7px;
  transition: color .2s;
}
.form-group:focus-within label { color: var(--orange); }

.input-wrap {
  position: relative;
  border-radius: 12px;
  transition: transform .22s cubic-bezier(.22,1,.36,1), box-shadow .22s;
}
.input-wrap:focus-within {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(255,81,47,0.15), 0 0 0 2px var(--orange);
}

.form-group input {
  width: 100%;
  padding: 13px 42px 13px 15px;
  background: #f9f9f9;
  border: 1.5px solid var(--border);
  border-radius: 12px;
  color: var(--text);
  font-family: 'Poppins', sans-serif;
  font-size: 13.5px;
  outline: none;
  transition: background .22s, border-color .22s;
}
.form-group input::placeholder { color: #bbb; }
.form-group input:focus {
  background: #fff;
  border-color: transparent;
}

.ficon {
  position: absolute; right: 13px; top: 50%;
  transform: translateY(-50%);
  font-size: 13px; color: #ccc;
  transition: color .22s; pointer-events: none;
}
.input-wrap:focus-within .ficon { color: var(--orange); }

.toggle-pw {
  position: absolute; right: 13px; top: 50%;
  transform: translateY(-50%);
  background: none; border: none; outline: none;
  cursor: pointer; font-size: 13px;
  color: #ccc; padding: 0;
  transition: color .22s;
}
.input-wrap:focus-within .toggle-pw { color: var(--orange); }

/* ── BUTTON ── */
.login-btn {
  width: 100%; margin-top: 8px; padding: 14px;
  border: none; border-radius: 30px;
  background: linear-gradient(to right, #ff512f, #ffb347);
  color: #fff;
  font-family: 'Poppins', sans-serif;
  font-size: 14px; font-weight: 600;
  cursor: pointer; position: relative; overflow: hidden;
  box-shadow: 0 8px 24px rgba(255,81,47,0.35);
  transition: transform .2s, box-shadow .2s;
}
.login-btn::before {
  content: '';
  position: absolute; top:0; left:-100%; width:55%; height:100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,.28), transparent);
  transition: left .5s;
}
.login-btn:hover::before { left: 160%; }
.login-btn:hover { transform: translateY(-3px); box-shadow: 0 14px 32px rgba(255,81,47,0.45); }
.login-btn:active { transform: translateY(0); }

.ripple {
  position: absolute; border-radius: 50%;
  background: rgba(255,255,255,0.3);
  transform: scale(0); pointer-events: none;
  animation: rip .55s ease-out forwards;
}
@keyframes rip { to { transform:scale(4); opacity:0; } }

/* ── FOOTER ── */
.footer-text {
  text-align: center; margin-top: 20px;
  font-size: 11px; color: #bbb; letter-spacing: .3px;
}

/* ── ALERTS ── */
.alert {
  display: flex; align-items: center; gap: 8px;
  margin-bottom: 16px; padding: 10px 14px;
  border-radius: 10px; font-size: 12.5px;
  animation: aIn .3s ease both;
}
@keyframes aIn { from{opacity:0;transform:translateY(-5px)} to{opacity:1;transform:none} }
.alert-success { background:#e8f5e9; border:1px solid #a5d6a7; color:#2e7d32; }
.alert-danger  { background:#fff3e0; border:1px solid #ffcc80; color:#e65100; }
</style>
</head>
<body>

<!-- ── FLOATING FOOD OBJECTS ── -->
<div class="floater f-bowl" style="top:8%;left:5%;--ft:7s;--delay:0s;--r0:-6deg;--r1:6deg;">
  <i class="fas fa-bowl-food" style="--fs:30px;--fc:#ff512f;"></i>
</div>
<div class="floater f-plate" style="top:6%;right:6%;--ft:8s;--delay:-2s;--r0:4deg;--r1:-5deg;">
  <i class="fas fa-utensils" style="--fs:26px;--fc:#ffb347;"></i>
</div>
<div class="floater f-pepper" style="top:42%;left:3%;--ft:9s;--delay:-4s;--r0:8deg;--r1:-4deg;">
  <i class="fas fa-pepper-hot" style="--fs:26px;--fc:#e53935;"></i>
</div>
<div class="floater f-jar" style="top:38%;right:4%;--ft:6.5s;--delay:-1s;--r0:-5deg;--r1:7deg;">
  <i class="fas fa-jar" style="--fs:24px;--fc:#8e24aa;"></i>
</div>
<div class="floater f-bowl" style="bottom:10%;left:7%;--ft:10s;--delay:-3s;--r0:-8deg;--r1:5deg;">
  <i class="fas fa-fire-flame-curved" style="--fs:28px;--fc:#ff7043;"></i>
</div>
<div class="floater f-plate" style="bottom:8%;right:7%;--ft:7.5s;--delay:-5s;--r0:6deg;--r1:-8deg;">
  <i class="fas fa-drumstick-bite" style="--fs:26px;--fc:#f4a300;"></i>
</div>
<div class="floater f-ladle" style="top:14%;left:22%;--ft:11s;--delay:-6s;--r0:-3deg;--r1:6deg;">
  <i class="fas fa-blender" style="--fs:22px;--fc:#0097a7;"></i>
</div>
<div class="floater f-pepper" style="bottom:16%;right:22%;--ft:8.5s;--delay:-2.5s;--r0:5deg;--r1:-6deg;">
  <i class="fas fa-lemon" style="--fs:26px;--fc:#f9a825;"></i>
</div>

<!-- ── CARD ── -->
<div class="card">

  <!-- LOGO -->
  <div class="logo-area">
    <div class="logo-row">
      <i class="fas fa-utensils"></i>
      <span>Mero Bhoj</span>
    </div>
    <div class="logo-sub">Admin Panel</div>
  </div>

  <div class="divider">
    <span></span><div class="divider-dot"></div><div class="divider-dot"></div><div class="divider-dot"></div><span></span>
  </div>

  <div class="welcome">
    <h3>Welcome Back, Admin </h3>
    <p>Admin Portal</p>
  </div>

  <!-- Toast container will be added here -->
  <?php if (isset($_SESSION['msg'])): ?>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        
        const msg = <?php echo json_encode($_SESSION['msg']); ?>;
        if (msg) {
        }
        // Clear the session message
        <?php unset($_SESSION['msg']); ?>
      });
    </script>
  <?php endif; ?>

  <form action="admin_login_process.php" method="POST" id="loginForm">

    <div class="form-group">
      <label for="email">Admin Email</label>
      <div class="input-wrap">
        <input type="email" id="email" name="email" placeholder="admin@gmail.com" required>
        <i class="fas fa-envelope ficon"></i>
      </div>
    </div>

    <div class="form-group">
      <label for="pw">Password</label>
      <div class="input-wrap">
        <input type="password" id="pw" name="password" placeholder="••••••••" required>
        <button type="button" class="toggle-pw" onclick="togglePw()">
          <i class="fas fa-eye" id="eyeIco"></i>
        </button>
      </div>
    </div>

    <button type="submit" class="login-btn" id="submitBtn">
      Login to Dashboard
    </button>

    <div class="footer-text">© 2026 Mero Bhoj | Admin Panel</div>
  </form>
</div>

<script>
/* password toggle only */
function togglePw() {
  const pwI = document.getElementById('pw');
  const show = pwI.type === 'password';
  pwI.type = show ? 'text' : 'password';
  document.getElementById('eyeIco').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}

/* ripple effect */
document.getElementById('submitBtn').addEventListener('click', function(e) {
  const r = document.createElement('span');
  r.className = 'ripple';
  const sz = Math.max(this.offsetWidth, this.offsetHeight);
  const rc = this.getBoundingClientRect();
  const cx = e.clientX - rc.left;
  const cy = e.clientY - rc.top;
  
  r.style.width = r.style.height = sz + 'px';
  r.style.left = cx - sz/2 + 'px';
  r.style.top = cy - sz/2 + 'px';
  
  this.appendChild(r);
  
  setTimeout(() => {
    r.remove();
  }, 550);
});
</script>
