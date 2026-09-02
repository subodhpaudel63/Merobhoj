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
</head>

<body class="auth-page">
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
                  <div class="field-animate d-grid mt-3 justify-content-center">
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
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <?php endif; ?>

  <script type="application/json" id="gisConfig"><?php $gcfg = array(); $gcfg['useFallback'] = GOOGLE_USE_FALLBACK; $gcfg['clientId'] = GOOGLE_CLIENT_ID; echo json_encode($gcfg); ?></script>

  <?php include_once __DIR__ . '/footer.php'; ?>

</body>
</html>
