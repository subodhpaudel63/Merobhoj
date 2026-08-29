<?php require_once __DIR__ . '/config/bootstrap.php'; ?>
<style>
    body { background:#fff; }

    /* ── Header scroll behavior (same as index.php) ──
       White at the top, brand orange once the page is scrolled. */
    header {
        background-color: #fff;
        transition: background-color 0.3s ease;
    }
    header.scrolled .text-dark {
        color: #fff !important;
    }
    header.scrolled #mobile-menu .text-dark {
        color: #343a40 !important; /* keep mobile dropdown text dark on its white background */
    }
    header.scrolled .menus > ul > li::after {
        background-color: #fff;
    }
    .menu .card { border-radius: 16px; overflow: hidden; border: 0; background: #fff; box-shadow: 0 8px 24px rgba(0,0,0,.08); transition: transform .2s ease, box-shadow .2s ease; }
    .menu .card:hover { transform: translateY(-4px); box-shadow: 0 16px 36px rgba(0,0,0,.12); }
    .menu .card-img-top { height: 220px; object-fit: cover; }
    .menu .card-title { font-weight: 800; color: #d32f2f; }
    .menu .card-text { color:#6c757d; }
    .menu .price { color:#212529; font-weight:700; }
    .btn-orange { background:#ff6a00; color:#fff; border-radius:10px; border:1px solid #ff6a00; white-space:nowrap; font-weight:600; }
    .btn-orange:hover { background:#e65f00; color:#fff; border-color:#e65f00; }
    .btn.btn-orange { background:#ff6a00 !important; border-color:#ff6a00 !important; color:#fff !important; }
    .btn.btn-orange:hover { background:#e65f00 !important; border-color:#e65f00 !important; color:#fff !important; }
    
    /* Toast styling for better visibility */
    .toast-success {
        background-color: #0f5132 !important; /* Dark green */
        border-color: #0f5132 !important;
        color: white !important;
    }
    
    .toast-error {
        background-color: #842029 !important; /* Dark red */
        border-color: #842029 !important;
        color: white !important;
    }
    
    /* Custom button styles */
    .nav-button {
        background-color: #ff0000;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 30px;
        font-weight: 500;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
        margin-left: 10px;
    }
    
    .nav-button:hover {
        background-color: #cc0000;
        color: white;
        text-decoration: none;
    }
    
    .nav-button-outline {
        background-color: transparent;
        color: #ff0000;
        border: 2px solid #ff0000;
        padding: 6px 18px;
        border-radius: 30px;
        font-weight: 500;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
        margin-left: 10px;
    }
    
    .nav-button-outline:hover {
        background-color: #ff0000;
        color: white;
        text-decoration: none;
    }
    
    /* Header Contact Section */
    .header-contact-bar {
        background: linear-gradient(135deg, #ff6a00, #d32f2f);
        color: white;
        padding: 8px 0;
        font-size: 0.9rem;
    }
    
    .header-contact-info {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 15px;
    }
    
    .header-contact-item {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .header-contact-item i {
        font-size: 1.1rem;
    }
    
    .header-contact-item a {
        color: white;
        text-decoration: none;
        font-weight: 500;
        transition: opacity 0.3s ease;
    }
    
    .header-contact-item a:hover {
        opacity: 0.8;
    }
    
    @media (max-width: 768px) {
        .header-contact-info {
            flex-direction: column;
            gap: 5px;
        }
    }
    
    /* Modal styling */
    .login-modal .modal-content {
        border-radius: 15px;
        border: none;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    
    .login-modal .modal-header {
        background: linear-gradient(135deg, #ff6a00, #d32f2f);
        color: white;
        border-top-left-radius: 15px;
        border-top-right-radius: 15px;
        border: none;
    }
    
    .login-modal .btn-login {
        background: linear-gradient(135deg, #ff6a00, #d32f2f);
        border: none;
        color: white;
        padding: 10px;
        font-weight: 600;
    }
    
    .login-modal .btn-login:hover {
        background: linear-gradient(135deg, #e65f00, #c62828);
        color: white;
    }
    </style>
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

    <!-- Theme color (brand orange) — used by mobile browsers for UI tint -->
    <meta name="theme-color" content="#ff6a00">
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>" />
    
    <header>
      <div class="container header my-3 d-none d-lg-flex">
        <div class="logo">
          <a href="./index.php">
            <i class="fa fa-utensils me-3 text-dark"></i>
            <h1 class="mb-0 text-dark">Mero Bhoj</h1>
          </a>
        </div>
        <div class="menus">
          <ul class="d-flex mb-0">
            <li class="list-unstyled py-2">
              <a
                class="text-decoration-none text-uppercase p-4 text-dark"
                href="./index.php"
                >Home</a
              >
            </li>
            <li class="list-unstyled py-2">
              <a
                class="text-decoration-none text-uppercase p-4 text-dark"
                href="./aboutus.php"
                >About</a
              >
            </li>
           
            <li class="list-unstyled py-2">
              <a
                class="text-decoration-none text-uppercase p-4 text-dark"
                href="./menu.php"
                >Menu</a
              >
            </li>
            <li class="list-unstyled py-2">
              <a
                class="text-decoration-none text-uppercase p-4 text-dark"
                href="./contactus.php"
                >Contact</a
              >
            </li>
          </ul>
        </div>
        <div class="icons d-flex align-items-center">
          <a class="text-decoration-none" id="searchBtn" href="#">
            <i class="fa fa-search me-3 text-dark"></i>
          </a>
          <a class="text-decoration-none" id="shoppingbutton" href="./client/cart.php">
            <i class="fa fa-shopping-bag me-3 text-dark"></i>
            <?php if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
              <span class="position-absolute translate-middle badge rounded-pill bg-danger" style="top: 10px; right: 10px; font-size: 0.7rem; padding: 0.25rem 0.5rem;">
                <?php echo count($_SESSION['cart']); ?>
              </span>
            <?php endif; ?>
          </a>
          <!-- Login and Signup buttons -->
          <div class="d-flex align-items-center">
            <a href="./login.php" class="nav-button">Login</a>
            <a href="./register.php" class="nav-button-outline">Sign Up</a>
          </div>
        </div>
      </div>

     
      

      <div
        class="d-flex justify-content-around py-3 align-items-center d-lg-none"
      >
        <div id="hamburger">
          <i class="fa fa-2x fa-bars me-3 text-dark"></i>
        </div>
        <div class="mobile-nav-logo">
          <div class="logo">
            <a href="./index.php">
              <i class="fa fa-utensils me-3 text-dark"></i>
              <h1 class="mb-0 text-dark">Mero Bhoj</h1>
            </a>
          </div>
        </div>
        <div class="mobile-nav-icons">
          <div class="icons">
            <a class="text-decoration-none" id="searchBtnMobile" href="#">
              <i class="fa fa-search me-3 text-dark"></i>
            </a>
            <a class="text-decoration-none" id="shoppingbuttonMobile" href="./client/cart.php">
              <i class="fa fa-shopping-bag me-3 text-dark"></i>
              <?php if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
                <span class="position-absolute translate-middle badge rounded-pill bg-danger" style="top: 10px; right: 10px; font-size: 0.7rem; padding: 0.25rem 0.5rem;">
                  <?php echo count($_SESSION['cart']); ?>
                </span>
              <?php endif; ?>
            </a>
          </div>
        </div>
        <div
          class="position-fixed w-75 bg-white h-100 top-0 start-0"
          id="mobile-menu"
        >
          <div
            id="hamburger-cross"
            class="d-flex justify-content-end align-items-center py-2"
          >
            <i class="fa fa-2x fa-times me-3"></i>
          </div>
          <div class="menus">
            <ul class="d-flex flex-column ps-2 mb-0 mt-4">
              <li class="list-unstyled py-2">
                <a
                  class="text-dark text-decoration-none text-uppercase p-4"
                  href="./index.php"
                  >Home</a
                >
              </li>
              <li class="list-unstyled py-2">
                <a
                  class="text-dark text-decoration-none text-uppercase p-4"
                  href="./aboutus.php"
                  >About</a
                >
              </li>
              
              </li>
              <li class="list-unstyled py-2">
                <a
                  class="text-dark text-decoration-none text-uppercase p-4"
                  href="./menu.php"
                  >Menu</a
                >
              </li>
              <li class="list-unstyled py-2">
                <a
                  class="text-dark text-decoration-none text-uppercase p-4"
                  href="./contactus.php"
                  >Contact</a
                >
              </li>
            </ul>
          </div>
        </div>
      </div>
    </header>
