<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Mero Bhoj | Register</title>

    <!-- Favicons -->
    <link href="assets/img/logo.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Inter:wght@100;200;300;400;500;600;700;800;900&family=Amatic+SC:wght@400;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/aos/aos.css" rel="stylesheet">
    <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
    <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">

    <?php require_once __DIR__ . '/config/bootstrap.php'; ?>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>" />

    <style>
      /* ─────────────────────────────────────────
         ORIGINAL NAVBAR — UNTOUCHED
      ───────────────────────────────────────── */
      .navbar-custom {
        background-color: #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,.1);
        padding: 15px 0;
      }
      .logo-text {
        font-family: 'Amatic SC', sans-serif;
        font-weight: 700;
        font-size: 28px;
        color: #000;
        text-decoration: none;
      }
      .logo-text i { margin-right: 10px; color: #000; }
      .nav-button {
        background-color: #ff0000;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 30px;
        font-weight: 500;
        transition: all 0.3s ease;
      }
      .nav-button:hover { background-color: #cc0000; color: white; }

      /* ─────────────────────────────────────────
         PAGE BACKGROUND
      ───────────────────────────────────────── */
      body {
        background: #ffffff;
        overflow-x: hidden;
      }
      .page-bg-tint {
        position: fixed; inset: 0;
        background:
          radial-gradient(ellipse 60% 50% at 20% 40%, rgba(255,60,0,.04) 0%, transparent 70%),
          radial-gradient(ellipse 50% 60% at 80% 70%, rgba(255,160,0,.04) 0%, transparent 70%);
        pointer-events: none; z-index: 0;
      }

      /* ─────────────────────────────────────────
         CARD ENTRY
      ───────────────────────────────────────── */
      .auth-section {
        position: relative; z-index: 1;
        padding: 90px 0 80px;
      }

      @media (max-width: 991px) {
        .auth-section {
          padding-top: 50px;
        }
      }
      .auth-card-wrap {
        opacity: 0;
        transform: translateY(32px) scale(.97);
        animation: cardReveal .75s cubic-bezier(.22,1,.36,1) .12s forwards;
      }
      @keyframes cardReveal {
        to { opacity: 1; transform: translateY(0) scale(1); }
      }

      .auth-card {
        background: #fff;
        border-radius: 20px;
        border: none;
        box-shadow:
          0 2px 8px rgba(0,0,0,.06),
          0 16px 48px rgba(0,0,0,.10),
          0 0 0 1px rgba(0,0,0,.04);
        overflow: hidden;
        position: relative;
      }

      /* ── Top-only moving red line ── */
      .auth-card::before {
        content: '';
        position: absolute;
        top: 0; left: -60%; right: auto;
        width: 60%; height: 3px;
        background: linear-gradient(90deg,
          transparent 0%,
          rgba(255,80,0,.5) 20%,
          #ff0000 50%,
          rgba(255,80,0,.5) 80%,
          transparent 100%
        );
        animation: topLineSweep 2s cubic-bezier(.4,0,.6,1) infinite;
        z-index: 2;
        border-radius: 0 0 4px 4px;
      }
      @keyframes topLineSweep {
        0%   { left: -60%; }
        100% { left: 100%; }
      }

      @keyframes fadeUp {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: translateY(0); }
      }

      .auth-title { opacity:0; animation: fadeUp .6s cubic-bezier(.22,1,.36,1) .35s forwards; }
      .auth-sub   { opacity:0; animation: fadeUp .6s cubic-bezier(.22,1,.36,1) .43s forwards; }

      .field-animate { opacity:0; }
      .field-animate:nth-child(1) { animation: fadeUp .55s cubic-bezier(.22,1,.36,1) .50s forwards; }
      .field-animate:nth-child(2) { animation: fadeUp .55s cubic-bezier(.22,1,.36,1) .60s forwards; }
      .field-animate:nth-child(3) { animation: fadeUp .55s cubic-bezier(.22,1,.36,1) .70s forwards; }
      .field-animate:nth-child(4) { animation: fadeUp .55s cubic-bezier(.22,1,.36,1) .78s forwards; }
      .field-animate:nth-child(5) { animation: fadeUp .55s cubic-bezier(.22,1,.36,1) .84s forwards; }
      .field-animate:nth-child(6) { animation: fadeUp .55s cubic-bezier(.22,1,.36,1) .92s forwards; }
      #g_id_onload { opacity: 1 !important; transform: none !important; }

      /* ─────────────────────────────────────────
         INPUT GROUPS — smooth transitions
      ───────────────────────────────────────── */
      .input-group .input-group-text {
        background: #f8f8f8;
        border: 1.5px solid #e5e5e5;
        border-right: none;
        color: #aaa;
        transition: border-color .3s, background .3s, color .3s;
      }
      .input-group .form-control {
        border: 1.5px solid #e5e5e5;
        border-left: none;
        background: #fafafa;
        transition: border-color .3s, background .3s, box-shadow .3s;
        box-shadow: none !important;
        outline: none;
      }
      .input-group .form-control::placeholder { color: #ccc; }

      .input-group:focus-within .input-group-text {
        border-color: #ff0000;
        background: #fff5f5;
        color: #ff0000;
      }
      .input-group:focus-within .form-control {
        border-color: #ff0000;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(255,0,0,.08) !important;
      }

      /* Eye toggle */
      .input-group .btn-eye {
        background: #f8f8f8;
        border: 1.5px solid #e5e5e5;
        border-left: none;
        color: #aaa;
        padding: 0 12px;
        transition: border-color .3s, color .3s, background .3s;
        cursor: pointer;
      }
      .input-group:focus-within .btn-eye {
        border-color: #ff0000;
        background: #fff5f5;
        color: #ff0000;
      }

      /* Invalid */
      .input-group.is-invalid-group .input-group-text,
      .input-group.is-invalid-group .btn-eye {
        border-color: #dc3545 !important;
        background: #fff8f8;
        color: #dc3545;
      }
      .input-group.is-invalid-group .form-control {
        border-color: #dc3545 !important;
        animation: shake .4s ease;
      }
      @keyframes shake {
        0%,100% { transform: translateX(0); }
        20%     { transform: translateX(-7px); }
        40%     { transform: translateX(7px); }
        60%     { transform: translateX(-4px); }
        80%     { transform: translateX(4px); }
      }

      /* ─────────────────────────────────────────
         PASSWORD STRENGTH BAR
      ───────────────────────────────────────── */
      .strength-bar-wrap {
        height: 4px;
        background: #f0f0f0;
        border-radius: 4px;
        margin-top: 8px;
        overflow: hidden;
      }
      .strength-bar-fill {
        height: 100%;
        border-radius: 4px;
        width: 0;
        transition: width .45s cubic-bezier(.22,1,.36,1), background-color .45s ease;
      }
      .strength-label {
        font-size: .75rem;
        margin-top: 5px;
        font-weight: 500;
        min-height: 1.1em;
        transition: color .4s ease;
      }

      /* ─────────────────────────────────────────
         MATCH PILL
      ───────────────────────────────────────── */
      .match-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: .75rem;
        font-weight: 500;
        padding: 3px 10px;
        border-radius: 20px;
        margin-top: 7px;
        opacity: 0;
        transform: translateY(-4px);
        transition: opacity .3s ease, transform .3s ease, background .3s, color .3s;
      }
      .match-pill.visible { opacity: 1; transform: translateY(0); }
      .match-pill.ok   { background: #d4f7e4; color: #0a7a3a; }
      .match-pill.fail { background: #fde8e6; color: #c0392b; }

      /* ─────────────────────────────────────────
         SUBMIT BUTTON
      ───────────────────────────────────────── */
      .btn-danger {
        background: linear-gradient(135deg, #ff0000 0%, #cc2200 100%) !important;
        border: none !important;
        border-radius: 10px !important;
        font-weight: 600 !important;
        letter-spacing: .3px;
        position: relative;
        overflow: hidden;
        transition: transform .25s cubic-bezier(.22,1,.36,1), box-shadow .25s ease !important;
        box-shadow: 0 4px 18px rgba(255,0,0,.28) !important;
      }
      .btn-danger::before {
        content: '';
        position: absolute;
        top: 0; left: -120%; width: 70%; height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.2), transparent);
        transform: skewX(-20deg);
        transition: left .55s ease;
      }
      .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 32px rgba(255,0,0,.4) !important;
        color: #fff !important;
      }
      .btn-danger:hover::before { left: 160%; }
      .btn-danger:active { transform: scale(.97) translateY(0); }

      .btn-ripple {
        position: absolute; border-radius: 50%;
        background: rgba(255,255,255,.35);
        transform: scale(0);
        animation: btnRipple .55s linear;
        pointer-events: none;
      }
      @keyframes btnRipple { to { transform: scale(5); opacity: 0; } }

      /* ─────────────────────────────────────────
         TOASTS — animated
      ───────────────────────────────────────── */
      .toast-container { z-index: 9999; }
      .toast {
        border-radius: 14px !important;
        border: none !important;
        box-shadow: 0 8px 32px rgba(0,0,0,.18), 0 2px 8px rgba(0,0,0,.12) !important;
        font-family: 'Inter', sans-serif;
        font-size: .875rem;
        min-width: 300px;
        overflow: hidden;
        backdrop-filter: blur(8px);
      }
      @keyframes toastSlideIn {
        from { opacity:0; transform: translateX(80px) scale(.95); }
        to   { opacity:1; transform: translateX(0) scale(1); }
      }
      .toast.show { animation: toastSlideIn .45s cubic-bezier(.22,1,.36,1) forwards; }

      .toast-success {
        background: linear-gradient(135deg, #0a7a3a, #0d5c2e) !important;
        color: #d4f7e4 !important;
      }
      .toast-success .toast-icon { color: #6effa8; font-size: 1.2rem; }
      .toast-success .btn-close  { filter: invert(1) brightness(1.5); }

      .toast-error {
        background: linear-gradient(135deg, #c0392b, #8e1a12) !important;
        color: #fde8e6 !important;
      }
      .toast-error .toast-icon { color: #ffaaaa; font-size: 1.2rem; }
      .toast-error .btn-close  { filter: invert(1) brightness(1.5); }

      .toast-progress {
        position: absolute;
        bottom: 0; left: 0;
        height: 3px;
        background: rgba(255,255,255,.4);
        border-radius: 0 0 0 14px;
        animation: toastProgress 5s linear forwards;
        width: 100%;
      }
      @keyframes toastProgress {
        from { width: 100%; }
        to   { width: 0%; }
      }

      /* ─────────────────────────────────────────
         FLOATING FOOD EMOJIS
      ───────────────────────────────────────── */
      .food-float {
        position: fixed;
        opacity: 0;
        pointer-events: none;
        z-index: 0;
        user-select: none;
        will-change: transform, opacity;
      }
      @keyframes drift1 {
        0%   { opacity:0;    transform:translateY(0)     rotate(0deg)   scale(.8); }
        8%   { opacity:.13; }
        85%  { opacity:.09; }
        100% { opacity:0;    transform:translateY(-105vh) rotate(25deg)  scale(1.05); }
      }
      @keyframes drift2 {
        0%   { opacity:0;    transform:translateY(0)     rotate(0deg)   scale(.9); }
        8%   { opacity:.11; }
        85%  { opacity:.08; }
        100% { opacity:0;    transform:translateY(-108vh) rotate(-20deg) scale(1.1); }
      }
      @keyframes drift3 {
        0%   { opacity:0;    transform:translateY(0)     rotate(0deg)   scale(.75); }
        8%   { opacity:.15; }
        85%  { opacity:.09; }
        100% { opacity:0;    transform:translateY(-102vh) rotate(30deg)  scale(1.0); }
      }
    </style>
</head>

<body>
  <?php include_once __DIR__ . '/header.php'; ?>

  <!-- Food floaters -->
  <div id="food-floaters"></div>
  <div class="page-bg-tint"></div>



  <!-- ══════════════════════════════════════════
       TOAST NOTIFICATIONS
  ══════════════════════════════════════════ -->
  <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:9999;">
    <?php if (isset($_SESSION['msg'])): $m = $_SESSION['msg']; unset($_SESSION['msg']); ?>
      <div class="toast show align-items-center <?php echo $m['type']==='success' ? 'toast-success' : 'toast-error'; ?>"
           role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex align-items-center px-3 py-2 gap-2" style="position:relative;">
          <span class="toast-icon">
            <?php echo $m['type']==='success' ? '<i class="bi bi-check-circle-fill"></i>' : '<i class="bi bi-x-circle-fill"></i>'; ?>
          </span>
          <div class="toast-body small fw-semibold flex-grow-1 px-0">
            <?php echo htmlspecialchars($m['text']); ?>
          </div>
          <button type="button" class="btn-close ms-2 flex-shrink-0" data-bs-dismiss="toast" aria-label="Close"></button>
          <div class="toast-progress"></div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- ══════════════════════════════════════════
       MAIN CONTENT
  ══════════════════════════════════════════ -->
  <div class="auth-section">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
          <div class="auth-card-wrap">
            <div class="auth-card card border-0 shadow">
              <div class="card-body p-4 px-5">

                <h2 class="text-center mb-4 auth-title">Register Your Account</h2>

                <form action="./includes/register.php" method="post" id="regForm" novalidate>

                  <!-- Email -->
                  <div class="mb-3 field-animate">
                    <label for="email" class="form-label">Email address</label>
                    <div class="input-group" id="emailGroup">
                      <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                      <input type="email" name="email" id="email" class="form-control"
                             placeholder="name@example.com" required autocomplete="email">
                    </div>
                    <div class="invalid-msg text-danger small mt-1" style="display:none;">
                      Please enter a valid email address.
                    </div>
                  </div>

                  <!-- Password -->
                  <div class="mb-3 field-animate">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group" id="passGroup">
                      <span class="input-group-text"><i class="bi bi-lock"></i></span>
                      <input type="password" name="password" id="password" class="form-control"
                             placeholder="Password" required autocomplete="new-password">
                      <button type="button" class="btn-eye" id="eyePass" aria-label="Toggle">
                        <i class="bi bi-eye-slash"></i>
                      </button>
                    </div>
                    <!-- Strength bar -->
                    <div class="strength-bar-wrap">
                      <div class="strength-bar-fill" id="strFill"></div>
                    </div>
                    <div class="strength-label text-muted" id="strLabel"></div>
                  </div>

                  <!-- Confirm Password -->
                  <div class="mb-3 field-animate">
                    <label for="confirmPassword" class="form-label">Confirm Password</label>
                    <div class="input-group" id="confGroup">
                      <span class="input-group-text"><i class="bi bi-shield-lock"></i></span>
                      <input type="password" name="confirmPassword" id="confirmPassword" class="form-control"
                             placeholder="Confirm Password" required autocomplete="new-password">
                      <button type="button" class="btn-eye" id="eyeConf" aria-label="Toggle">
                        <i class="bi bi-eye-slash"></i>
                      </button>
                    </div>
                    <div class="match-pill" id="matchPill"></div>
                  </div>

                  <!-- Submit -->
                  <div class="d-grid mt-3 field-animate">
                    <button type="submit" class="btn btn-danger" id="regBtn">Register</button>
                  </div>

                  <!-- Divider -->
                  <div class="text-center mt-3 field-animate">
                    <div class="d-flex align-items-center" style="gap:10px;">
                      <hr style="flex:1;border-color:#e5e5e5;">
                      <small class="text-muted">OR</small>
                      <hr style="flex:1;border-color:#e5e5e5;">
                    </div>
                  </div>

                  <!-- Google Sign-In -->
                  <div class="field-animate d-grid mt-3">
                    <div id="g_id_onload" style="display:flex;justify-content:center;">
                      <?php if (GOOGLE_USE_FALLBACK): ?>
                      <button type="button" id="google-fallback-btn" onclick="handleGoogleFallback()"
                        style="display:inline-flex;align-items:center;gap:10px;padding:10px 24px;border:1px solid #dadce0;border-radius:6px;background:#fff;color:#3c4043;font-size:15px;font-family:Roboto,sans-serif;cursor:pointer;transition:box-shadow .2s;width:100%;justify-content:center;"
                        onmouseover="this.style.boxShadow='0 1px 3px rgba(60,64,67,0.3)'" onmouseout="this.style.boxShadow='none'">
                        <svg width="20" height="20" viewBox="0 0 48 48" style="flex-shrink:0;">
                          <path fill="#EA4335" d="M24 9.5c3.3 0 6.2 1.1 8.5 3l6.4-6.4C34.9 2.7 29.9 0 24 0 14.6 0 6.6 5.4 2.6 13.4l7.5 5.8C12.2 13.3 17.8 9.5 24 9.5z"/>
                          <path fill="#4285F4" d="M46.5 24.5c0-1.6-.1-3.2-.4-4.7H24v9.4h12.7c-.6 3.2-2.4 5.9-5.1 7.7l7.5 5.8c4.4-4.1 7-10.2 7-18.2z"/>
                          <path fill="#FBBC05" d="M10.1 28.2c-1-2.9-1-6 0-8.9L2.6 13.4C.7 17.6 0 21.6 0 24.5s.7 6.9 2.6 11.1l7.5-5.8z"/>
                          <path fill="#34A853" d="M24 48c6.5 0 12-2.1 16-5.7l-7.5-5.8c-2.4 1.6-5.5 2.6-8.5 2.6-6.2 0-11.8-3.8-13.7-9.4l-7.5 5.8C6.6 42.6 14.6 48 24 48z"/>
                        </svg>
                        Continue with Google
                      </button>
                      <?php endif; ?>
                    </div>
                  </div>

                </form>

                <!-- Login link -->
                <div class="text-center mt-3 field-animate">
                  <p>Already have an account? <a href="login.php" class="text-danger">Login here</a></p>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <?php if (!GOOGLE_USE_FALLBACK): ?>
  <!-- Google Identity Services -->
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <?php endif; ?>

  <script>
    console.log('Register page loaded, GOOGLE_USE_FALLBACK:', <?php echo GOOGLE_USE_FALLBACK ? 'true' : 'false'; ?>);

    function handleGoogleCredentialResponse(response) {
        console.log('Google credential response received');
        fetch('includes/google_auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id_token: response.credential })
        })
        .then(function(res) { 
            console.log('Auth response status:', res.status);
            return res.json(); 
        })
        .then(function(data) {
            console.log('Auth response data:', data);
            if (data.success) {
                window.location.href = data.redirect || '/Merobhoj/client/index.php';
            } else {
                alert(data.message || 'Google sign-in failed');
            }
        })
        .catch(function(err) {
            console.error('Network error during Google sign-in:', err);
            alert('Network error during Google sign-in');
        });
    }

    function handleGoogleFallback() {
        alert('Google Sign-In is not configured yet.\n\nPlease set GOOGLE_CLIENT_ID in config/bootstrap.php');
    }

    function renderGoogleButton() {
        console.log('renderGoogleButton called, google object:', typeof google);
        
        <?php if (!GOOGLE_USE_FALLBACK): ?>
        if (typeof google === 'undefined' || !google.accounts) {
            console.log('Google accounts not ready, retrying in 200ms...');
            setTimeout(renderGoogleButton, 200);
            return;
        }
        try {
            console.log('Initializing Google Sign-In with client_id:', '<?php echo htmlspecialchars(GOOGLE_CLIENT_ID, ENT_QUOTES); ?>');
            google.accounts.id.initialize({
                client_id: '<?php echo htmlspecialchars(GOOGLE_CLIENT_ID, ENT_QUOTES); ?>',
                callback: handleGoogleCredentialResponse,
                auto_select: false,
                cancel_on_tap_outside: false,
            });
            console.log('Rendering Google button...');
            google.accounts.id.renderButton(
                document.getElementById('g_id_onload'),
                { theme: 'outline', size: 'large', width: '100%' }
            );
            console.log('Google button rendered successfully');
        } catch (e) {
            console.error('GIS render failed:', e);
        }
        <?php else: ?>
        console.log('Using fallback button (GOOGLE_USE_FALLBACK is true)');
        <?php endif; ?>
    }

    // Try to render immediately and also on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderGoogleButton);
    } else {
        renderGoogleButton();
    }
  </script>

  <script>
    /* ── TOASTS ── */
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('.toast').forEach(function (t) {
        new bootstrap.Toast(t, { delay: 5000 }).show();
      });
    });

    /* ── EYE TOGGLES ── */
    function mkEye(btnId, inputId) {
      document.getElementById(btnId).addEventListener('click', function () {
        const inp = document.getElementById(inputId);
        const ico = this.querySelector('i');
        if (inp.type === 'password') {
          inp.type = 'text';
          ico.classList.replace('bi-eye-slash','bi-eye');
        } else {
          inp.type = 'password';
          ico.classList.replace('bi-eye','bi-eye-slash');
        }
      });
    }
    mkEye('eyePass', 'password');
    mkEye('eyeConf', 'confirmPassword');

    /* ── RIPPLE ── */
    document.getElementById('regBtn').addEventListener('click', function (e) {
      const r = document.createElement('span');
      const rect = this.getBoundingClientRect();
      const sz = Math.max(rect.width, rect.height);
      r.className = 'btn-ripple';
      r.style.cssText = `width:${sz}px;height:${sz}px;left:${e.clientX-rect.left-sz/2}px;top:${e.clientY-rect.top-sz/2}px`;
      this.appendChild(r);
      r.addEventListener('animationend', () => r.remove());
    });

    /* ── STRENGTH METER ── */
    const LEVELS = [
      { pct:'0%',   bg:'transparent', txt:'',              col:'#aaa' },
      { pct:'20%',  bg:'#e74c3c',     txt:'Too weak',      col:'#e74c3c' },
      { pct:'40%',  bg:'#e67e22',     txt:'Weak',          col:'#e67e22' },
      { pct:'62%',  bg:'#f1c40f',     txt:'Fair',          col:'#c9a200' },
      { pct:'82%',  bg:'#27ae60',     txt:'Strong',        col:'#27ae60' },
      { pct:'100%', bg:'#16a085',     txt:'✦ Very strong', col:'#16a085' },
    ];

    function scorePass(pw) {
      let s = 0;
      if (pw.length >= 3)               s++;
      if (pw.length >= 8)               s++;
      if (/[A-Z]/.test(pw))             s++;
      if (/[0-9]/.test(pw))             s++;
      if (/[^A-Za-z0-9]/.test(pw))     s++;
      return Math.min(s, 5);
    }

    const pwInp   = document.getElementById('password');
    const cpInp   = document.getElementById('confirmPassword');
    const strFill = document.getElementById('strFill');
    const strLbl  = document.getElementById('strLabel');
    const matchPill = document.getElementById('matchPill');

    pwInp.addEventListener('input', function () {
      const lv = LEVELS[scorePass(this.value)];
      strFill.style.width = lv.pct;
      strFill.style.backgroundColor = lv.bg;
      strLbl.textContent = lv.txt;
      strLbl.style.color = lv.col;
      if (cpInp.value) updateMatch();
      document.getElementById('passGroup').classList.remove('is-invalid-group');
      document.getElementById('passGroup').parentElement.querySelector('.invalid-msg') &&
        (document.getElementById('passGroup').parentElement.querySelector('.invalid-msg').style.display = 'none');
    });

    function updateMatch() {
      const pw = pwInp.value, cp = cpInp.value;
      if (!cp) { matchPill.classList.remove('visible','ok','fail'); return; }
      matchPill.classList.add('visible');
      if (pw === cp) {
        matchPill.className = 'match-pill visible ok';
        matchPill.innerHTML = '<i class="bi bi-check-circle-fill"></i> Passwords match';
      } else {
        matchPill.className = 'match-pill visible fail';
        matchPill.innerHTML = '<i class="bi bi-x-circle-fill"></i> Passwords don\'t match';
      }
    }

    cpInp.addEventListener('input', function () {
      updateMatch();
      document.getElementById('confGroup').classList.remove('is-invalid-group');
    });

    /* ── VALIDATION ── */
    function shakeGroup(groupId, msgText) {
      const grp = document.getElementById(groupId);
      grp.classList.add('is-invalid-group');
      const inp = grp.querySelector('input');
      inp.style.animation = 'none';
      void inp.offsetWidth;
      inp.style.animation = '';
      const msg = grp.parentElement.querySelector('.invalid-msg');
      if (msg) { msg.textContent = msgText; msg.style.display = 'block'; }
      inp.focus();
    }

    document.getElementById('email').addEventListener('input', function() {
      document.getElementById('emailGroup').classList.remove('is-invalid-group');
      const m = document.getElementById('emailGroup').parentElement.querySelector('.invalid-msg');
      if (m) m.style.display = 'none';
    });

    document.getElementById('regForm').addEventListener('submit', function (e) {
      let ok = true;
      const em = document.getElementById('email');
      const pw = pwInp, cp = cpInp;
      const hasSym = /[^A-Za-z0-9]/.test(pw.value);
      const hasNum = /[0-9]/.test(pw.value);

      if (!em.value.trim() || !em.checkValidity()) {
        shakeGroup('emailGroup','Please enter a valid email address.');
        ok = false;
      }
      if (pw.value.length < 3 || !hasSym || !hasNum) {
        shakeGroup('passGroup','Min 3 chars, 1 symbol & 1 number required.');
        ok = false;
      }
      if (pw.value !== cp.value) {
        shakeGroup('confGroup','Passwords must match.');
        ok = false;
      }
      if (!ok) e.preventDefault();
    });

    /* ── FLOATING FOOD EMOJIS ── */
    (function () {
      const foods = ['🍖','🍗','🌶️','🥘','🍲','🧅','🧄','🥩','🫕','🍛','🌿','🥣'];
      const container = document.getElementById('food-floaters');
      const drifts = ['drift1','drift2','drift3'];

      foods.forEach(function (emoji, i) {
        const el = document.createElement('div');
        el.className = 'food-float';
        el.textContent = emoji;
        const left     = 4 + (i * 8.1) % 92;
        const duration = 11 + (i * 2.3) % 9;
        const delay    = (i * 1.7) % 12;
        const drift    = drifts[i % 3];
        el.style.cssText = [
          `left:${left}%`,
          `bottom:-80px`,
          `animation:${drift} ${duration}s ${delay}s ease-in infinite`,
          `font-size:${1.2 + (i % 3) * .35}rem`,
        ].join(';');
        container.appendChild(el);
      });
    })();
  </script>

  <?php include_once __DIR__ . '/footer.php'; ?>
  <script src="./assets/js/main.js"></script>

</body>
</html>
