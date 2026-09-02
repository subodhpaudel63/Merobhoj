<?php
session_start();

$showExpiredAlert = false;
if (isset($_GET['session_expired']) && $_GET['session_expired'] == 1) {
    $showExpiredAlert = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  

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
</head>

<body class="auth-page">
  <?php include_once __DIR__ . '/header.php'; ?>

  <!-- Floating food emojis (JS-generated, CSS-animated) -->
  <div id="food-floaters"></div>
  <!-- Soft bg tint -->
  <div class="page-bg-tint"></div>



  <!-- ══════════════════════════════════════════
       TOAST NOTIFICATIONS — animated
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
            <div class="auth-card card">
              <div class="card-body px-5 py-4">

                <!-- Logo — matching exact navbar style -->
                <div class="auth-logo d-flex align-items-center justify-content-center gap-2 my-3">
                  <i class="fas fa-utensils" style="font-size:2.2rem;color:#1a1a1a;"></i>
                  <span style="font-family:'Amatic SC',cursive;font-weight:700;font-size:2.2rem;color:#1a1a1a;line-height:1;"></span>
                </div>
                

                <h2 class="text-center mb-2 auth-title">Login to Your Account</h2>
                 <h6 class="text-center mb-2 auth-title">Welcome back to Merobhoj</h6>
                

                <form action="includes/login.php" method="post" id="loginForm" novalidate>

                  <!-- Email -->
                  <div class="mb-3 field-animate">
                    <label for="useremail" class="form-label">Email address</label>
                    <div class="input-group" id="emailGroup">
                      <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                      <input type="email" class="form-control" id="useremail" name="email"
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
                      <input type="password" class="form-control" id="password" name="password"
                             placeholder="Enter your password" required minlength="6"
                             autocomplete="current-password">
                      <span class="input-group-text" style="cursor:pointer;border-left:none;"
                            onclick="togglePw('password','eyeIcon')">
                        <i class="bi bi-eye-slash" id="eyeIcon"></i>
                      </span>
                    </div>
                    <div class="invalid-msg text-danger small mt-1" style="display:none;">
                      Password must be at least 6 characters.
                    </div>
                  </div>

                  <!-- Submit -->
                  <div class="d-grid mb-3 field-animate">
                    <button type="submit" class="btn btn-gradient w-100 py-2" id="loginButton">
                      <span class="me-2">Login</span>
                      <i class="bi bi-box-arrow-in-right"></i>
                    </button>
                  </div>

                  <!-- Divider -->
                  <div class="text-center my-3 field-animate">
                    <div class="d-flex align-items-center" style="gap:10px;">
                      <hr style="flex:1;border-color:#e5e5e5;">
                      <small class="text-muted">OR</small>
                      <hr style="flex:1;border-color:#e5e5e5;">
                    </div>
                  </div>

                  <!-- Google Sign-In -->
                  <div class="field-animate mb-3" style="display:flex;justify-content:center;width:100%;">
                    <div id="g_id_onload"
                         data-client_id="<?php echo GOOGLE_CLIENT_ID; ?>"
                         data-context="signin"
                         data-ux_mode="popup"
                         data-auto_prompt="false"
                         style="display:flex;justify-content:center;width:100%;max-width:300px;">
                    </div>
                    <div class="g_id_signin"
                         data-type="standard"
                         data-shape="rectangular"
                         data-theme="outline"
                         data-text="continue_with"
                         data-size="large"
                         data-logo_alignment="left"
                         data-locale="en"
                         style="display:flex;justify-content:center;width:100%;max-width:300px;">
                    </div>
                  </div>
                 
    

                  <!-- Register link -->
                  <div class="text-center field-animate">
                    <p class="mb-0" style="font-size:clamp(.95rem,2vw,1.1rem);">
                      Don't have an account?
                      <a href="register.php" class="text-danger" style="font-size:inherit;">Register here</a>
                    </p>
                  </div>

                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include_once __DIR__ . '/footer.php'; ?>

  <!-- Scroll Top -->
  <a href="#" id="scroll-top" class="scroll-top d-flex align-items-center justify-content-center">
    <i class="bi bi-arrow-up-short"></i>
  </a>

  <!-- Preloader -->
  <div id="preloader"></div>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <?php if (!GOOGLE_USE_FALLBACK): ?>
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <?php endif; ?>

  <script type="application/json" id="gisConfig"><?php $gcfg = array(); $gcfg['useFallback'] = GOOGLE_USE_FALLBACK; $gcfg['clientId'] = GOOGLE_CLIENT_ID; echo json_encode($gcfg); ?></script>
  <script src="<?php echo asset('js/script.js'); ?>"></script>

</body>
</html>
