<?php
ob_start();
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Check if user is logged in
$currentUser = getUserFromCookie();

// If user is not logged in, redirect to login with appropriate action
if (!$currentUser) {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Please login to order food.'];
    header('Location: /Masu%20Ko%20Jhol%28full%29/login.php?action=order_food');
    exit;
}

try {
  $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
} catch (PDOException $e) {
  die("Database connection failed: " . $e->getMessage());
}

// Logged-in user and profile image (from secure cookies)
$profileImg = 'assets/images/profile.jpg';
if (isset($_COOKIE['user_img'])) {
  $dec = decrypt($_COOKIE['user_img'], SECRET_KEY);
  if ($dec && is_string($dec)) {
    $candidate = ltrim($dec, '/');
    if (file_exists(__DIR__ . '/../' . $candidate)) {
      $profileImg = $candidate;
    }
  }
}

// Fetch distinct categories
$catSql = "SELECT DISTINCT menu_category FROM menu";
$catResult = $pdo->query($catSql);
$categories = [];
if ($catResult) {
    while ($cat = $catResult->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = $cat['menu_category'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Mero Bhoj | Menu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
      integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <link rel="stylesheet" type="text/css" href="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css"/>
    <?php require_once __DIR__ . '/../config/bootstrap.php'; ?>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>" />
    <link rel="stylesheet" href="<?php echo asset('css/order_ui.css'); ?>" />
    <!-- Include toast styles -->
    <link rel="stylesheet" href="<?php echo asset('css/toast_styles.css'); ?>" />
    <style>
    /* ── Base ── */
    :root {
      --orange:      #ff6a00;
      --orange-dark: #e55a00;
      --orange-glow: rgba(255,106,0,.22);
      --ease-out:    cubic-bezier(.25,.8,.25,1);
      --ease-spring: cubic-bezier(.34,1.56,.64,1);
    }
    body { background: #f8f8f6; }

    /* ── Section title subtle underline ── */
    .section-title h2 {
      position: relative;
      display: inline-block;
    }
    .section-title h2::after {
      content: '';
      position: absolute;
      bottom: -6px; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--orange), transparent);
      border-radius: 2px;
      transform: scaleX(0);
      transform-origin: left;
      animation: titleLine .6s var(--ease-out) .3s forwards;
    }
    @keyframes titleLine {
      to { transform: scaleX(1); }
    }

    /* ── Live Search Bar ── */
    .menu-search-wrap {
      display: flex;
      justify-content: center;
      margin: 0 0 28px;
      padding: 0 16px;
    }
    .menu-search-bar {
      width: 100%; max-width: 480px;
      display: flex; align-items: center;
      background: #fff;
      border-radius: 50px;
      box-shadow: 0 4px 20px rgba(0,0,0,.09);
      padding: 7px 7px 7px 18px;
      gap: 8px;
      border: 2px solid transparent;
      transition: border-color .25s ease, box-shadow .25s ease;
    }
    .menu-search-bar:focus-within {
      border-color: var(--orange);
      box-shadow: 0 4px 20px var(--orange-glow);
    }
    .menu-search-bar i { color: #bbb; font-size: .9rem; flex-shrink: 0; }
    .menu-search-bar input {
      border: none; outline: none; background: transparent;
      flex: 1; font-size: .93rem; color: #333;
    }
    .menu-search-bar input::placeholder { color: #c0c0c0; }
    .menu-search-clear {
      display: none; border: none; border-radius: 50px;
      background: #f0f0f0; color: #888;
      padding: 7px 14px; font-size: .8rem; font-weight: 600;
      cursor: pointer; flex-shrink: 0;
      transition: background .2s, color .2s;
    }
    .menu-search-clear.visible { display: block; }
    .menu-search-clear:hover { background: var(--orange); color: #fff; }

    /* ── Tab bar ── */
    .menu-tabs-wrapper {
      display: flex;
      justify-content: center;
      margin-bottom: 32px;
    }
    #menuTab {
      border-bottom: none;
      gap: 4px;
      flex-wrap: wrap;
      padding: 5px;
      background: #fff;
      border-radius: 50px;
      display: inline-flex !important;
      box-shadow: 0 3px 16px rgba(0,0,0,.08);
    }
    #menuTab .nav-item { margin: 0; }
    #menuTab .nav-link {
      border: none;
      border-radius: 50px;
      padding: 10px 24px;
      font-weight: 600;
      font-size: .92rem;
      color: #666;
      background: transparent;
      transition: background .28s var(--ease-out),
                  color .28s var(--ease-out),
                  box-shadow .28s var(--ease-out),
                  transform .2s var(--ease-out);
      white-space: nowrap;
    }
    #menuTab .nav-link:hover {
      background: rgba(255,106,0,.08);
      color: var(--orange);
      transform: translateY(-1px);
    }
    #menuTab .nav-link.active {
      background: var(--orange);
      color: #fff !important;
      box-shadow: 0 5px 18px var(--orange-glow);
      transform: translateY(-1px);
    }

    /* ── Tab content transition ── */
    .tab-content { position: relative; }
    .tab-pane {
      transition: opacity .38s var(--ease-out), transform .38s var(--ease-out);
      opacity: 0;
      transform: translateY(14px);
      pointer-events: none;
    }
    .tab-pane.show.active {
      opacity: 1;
      transform: translateY(0);
      pointer-events: auto;
    }

    /* ── Cards ── */
    .menu .card {
      border-radius: 18px;
      overflow: hidden;
      border: 0;
      background: #fff;
      box-shadow: 0 4px 18px rgba(0,0,0,.07);
      transition: transform .32s var(--ease-out),
                  box-shadow .32s var(--ease-out);
      position: relative;
    }
    /* Subtle orange top-border slide-in on hover */
    .menu .card::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, var(--orange), #ff8c00);
      transform: scaleX(0);
      transform-origin: left;
      transition: transform .32s var(--ease-out);
      z-index: 2;
    }
    .menu .card:hover::before { transform: scaleX(1); }
    .menu .card:hover {
      transform: translateY(-6px);
      box-shadow: 0 20px 40px rgba(0,0,0,.11);
    }

    /* ── Card image ── */
    .card-img-wrapper {
      position: relative; overflow: hidden; height: 220px;
    }
    .menu .card-img-top {
      width: 100%; height: 100%; object-fit: cover;
      transition: transform .45s var(--ease-out);
    }
    .menu .card:hover .card-img-top { transform: scale(1.06); }

    /* ── Card text (original colors kept) ── */
    .menu .card-title { font-weight: 800; color: #d32f2f; }
    .menu .card-text  { color: #6c757d; font-size: .87rem; line-height: 1.55; }
    .menu .price      { color: #212529; font-weight: 700; font-size: 1.05rem; }

    .mkj-stock-badge {
      display: inline-flex;
      align-items: center;
      width: fit-content;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: .72rem;
      font-weight: 700;
      margin-bottom: 10px;
    }
    .mkj-stock-in-stock { background: #e8f8ed; color: #15803d; }
    .mkj-stock-low-stock { background: #fff4df; color: #d97706; }
    .mkj-stock-out-of-stock { background: #fee2e2; color: #dc2626; }

    /* ── Wishlist heart (subtle, non-intrusive) ── */
    .wishlist-btn {
      background: none; border: none; padding: 0;
      color: #e0e0e0; font-size: 1rem; cursor: pointer; line-height: 1;
      transition: color .22s ease, transform .22s var(--ease-spring);
    }
    .wishlist-btn.liked { color: #e53935; }
    .wishlist-btn:hover { transform: scale(1.25); color: #e53935; }

    /* ── Buttons ── */
    .btn-orange {
      background: var(--orange);
      color: #fff;
      border-radius: 10px;
      border: 1px solid var(--orange);
      white-space: nowrap;
      font-weight: 600;
      transition: background .22s ease,
                  transform .18s var(--ease-out),
                  box-shadow .22s ease;
      position: relative; overflow: hidden;
    }
    .btn-orange:hover {
      background: var(--orange-dark);
      color: #fff;
      border-color: var(--orange-dark);
      transform: translateY(-2px);
      box-shadow: 0 6px 18px var(--orange-glow);
    }
    .btn-orange:active { transform: translateY(0); }
    .btn.btn-orange         { background: var(--orange)      !important; border-color: var(--orange)      !important; color: #fff !important; }
    .btn.btn-orange:hover   { background: var(--orange-dark) !important; border-color: var(--orange-dark) !important; }

    /* ── Staggered card entrance ── */
    @keyframes cardRise {
      from { opacity: 0; transform: translateY(22px) scale(.97); }
      to   { opacity: 1; transform: translateY(0)    scale(1); }
    }
    .tab-pane.show.active .col {
      animation: cardRise .45s var(--ease-out) both;
    }
    .tab-pane.show.active .col:nth-child(1)  { animation-delay: .03s; }
    .tab-pane.show.active .col:nth-child(2)  { animation-delay: .08s; }
    .tab-pane.show.active .col:nth-child(3)  { animation-delay: .13s; }
    .tab-pane.show.active .col:nth-child(4)  { animation-delay: .18s; }
    .tab-pane.show.active .col:nth-child(5)  { animation-delay: .23s; }
    .tab-pane.show.active .col:nth-child(6)  { animation-delay: .28s; }
    .tab-pane.show.active .col:nth-child(7)  { animation-delay: .33s; }
    .tab-pane.show.active .col:nth-child(n+8){ animation-delay: .38s; }
    .col.search-hidden { display: none !important; }

    /* ── Qty stepper in modal ── */
    .qty-stepper {
      display: flex; align-items: center;
      border: 2px solid #e8e8e8; border-radius: 12px; overflow: hidden;
    }
    .qty-stepper button {
      background: #f5f5f5; border: none;
      width: 38px; height: 38px;
      font-size: 1.15rem; font-weight: 700; color: #555;
      cursor: pointer; display: flex; align-items: center; justify-content: center;
      transition: background .18s, color .18s;
    }
    .qty-stepper button:hover { background: var(--orange); color: #fff; }
    .qty-stepper input {
      width: 50px; text-align: center;
      border: none; outline: none; background: transparent;
      font-size: .95rem; font-weight: 700; color: #212529;
    }

    /* ── Modal polish ── */
    #buyModal .modal-content { border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 24px 60px rgba(0,0,0,.18); }
    #buyModal .modal-header  { border-bottom: 1px solid #f0f0f0; }
    #buyModal .modal-footer  { border-top: 1px solid #f0f0f0; }
    #buyModal #modal-image   { border-radius: 14px; width: 100%; height: 200px; object-fit: cover; }
    #buyModal .btn-success   { border-radius: 10px; font-weight: 700; padding: 10px 24px; transition: transform .2s, box-shadow .2s; }
    #buyModal .btn-success:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(46,125,50,.35); }

    /* ── Button row: both buttons equal size & perfectly aligned ── */
    .card-body .d-flex.gap-2.mt-3 {
      align-items: stretch;
    }
    .card-body .d-flex.gap-2.mt-3 form,
    .card-body .d-flex.gap-2.mt-3 > .btn {
      flex: 1;
      min-width: 0;
      display: flex;
    }
    .card-body .d-flex.gap-2.mt-3 form .btn {
      width: 100%;
      justify-content: center;
      align-items: center;
    }
    .card-body .d-flex.gap-2.mt-3 > .btn {
      justify-content: center;
      align-items: center;
      white-space: nowrap;
    }

    /* ── Navbar padding ── */
    .main-content { padding-top: 100px; }
    @media (max-width: 991px) {
      .main-content { padding-top: 80px; }
      .card.h-100.p-2 { padding: .5rem !important; }
      #menuTab .nav-link { padding: 9px 16px; font-size: .85rem; }
    }
    </style>
</head>
<body>
    

    <div class="loader">
      <i class="fas fa-utensils loader-icone"></i>
      <p>Mero Bhoj</p>
      <div class="loader-ellipses">
        <span></span>
        <span></span>
        <span></span>
      </div>
    </div>
    
    <header>
      <div class="container header my-3 d-none d-lg-flex">
        <div class="logo">
          <a href="./index.php">
            <i class="fa fa-utensils me-3"></i>
            <h1 class="mb-0">Mero Bhoj</h1>
          </a>
        </div>
        <div class="menus">
          <ul class="d-flex mb-0">
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./index.php"
                >Home</a
              >
            </li>
            <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./aboutus.php"
                  >About</a
                >
            </li>
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./menu.php"
                >Menu</a
              >
            </li>
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./myorder.php"
                >My Order</a>
            </li>
            <?php if (!$currentUser): ?>
              <li class="list-unstyled py-2">
                <a class="btn btn-gradient" href="<?php echo url('/login.php'); ?>">Login</a>
              </li>
            <?php endif; ?>
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./contactus.php"
                >Contact</a
              >
            </li>
          </ul>
        </div>

        <div class="icons d-flex align-items-center">
          <a class="text-decoration-none" id="searchBtn" href="#"><i class="fa fa-search me-3"></i></a>
          <a class="text-decoration-none" id="shoppingbutton" href="./cart.php">
            <i class="fa fa-shopping-bag me-3"></i>
          </a>
          <?php if ($currentUser): ?>
            <div class="dropdown">
              <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" id="profileMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?php echo url($profileImg); ?>" alt="profile" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileMenu">
                <li><h6 class="dropdown-header"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></h6></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="./update_password.php"><i class="fa fa-key me-2"></i>Update Password</a></li>
                <li><a class="dropdown-item" href="<?php echo url('./includes/logout.php'); ?>"><i class="fa fa-right-from-bracket me-2"></i>Logout</a></li>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="d-flex justify-content-around py-3 align-items-center d-lg-none">
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
            <a class="text-decoration-none" id="shoppingbuttonMobile" href="./cart.php">
              <i class="fa fa-shopping-bag me-3 text-dark"></i>
            </a>
          </div>
        </div>
        <div class="position-fixed w-75 bg-white h-100 top-0 start-0" id="mobile-menu">
          <div id="hamburger-cross" class="d-flex justify-content-end align-items-center py-2">
            <i class="fa fa-2x fa-times me-3"></i>
          </div>
          <div class="menus">
            <ul class="d-flex flex-column ps-2 mb-0 mt-4">
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./index.php"
                  >Home</a
                >
              </li>
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./aboutus.php"
                  >About</a
                >
              </li>
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./menu.php"
                  >Menu</a
                >
              </li>
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./myorder.php"
                  >My Order</a
                >
              </li>
              <?php if (!$currentUser): ?>
                <li class="list-unstyled py-2">
                  <a class="btn btn-gradient" href="<?php echo url('/login.php'); ?>">Login</a>
                </li>
              <?php endif; ?>
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./contactus.php"
                  >Contact</a
                >
              </li>
            </ul>
          </div>
        </div>
      </div>
    </header>

<section id="menu" class="menu section main-content">
    <div class="container section-title py-4">
        <h2>Our Menu</h2>
        <p><span>Check Our</span> <span class="description-title">Yummy Menu</span></p>
    </div>

    <!-- Search bar -->
    <div class="menu-search-wrap">
      <div class="menu-search-bar">
        <i class="fa fa-search"></i>
        <input type="text" id="menuSearchInput" placeholder="Search menu…" autocomplete="off">
        <button class="menu-search-clear" id="menuSearchClear" type="button">✕ Clear</button>
      </div>
    </div>

    <?php
    // Ensure we have exactly these categories in order
    $wantedCats = ['starter','breakfast','lunch','dinner'];
    ?>
    <div class="menu-tabs-wrapper">
    <ul class="nav nav-tabs" id="menuTab" role="tablist">
        <?php foreach ($wantedCats as $index => $cat): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $index === 0 ? 'active' : '' ?>" id="tab-<?= md5($cat) ?>" data-bs-toggle="tab" data-bs-target="#content-<?= md5($cat) ?>" type="button" role="tab" aria-controls="content-<?= md5($cat) ?>" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                    <?= htmlspecialchars(ucfirst($cat)) ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>
    </div>

    <div class="tab-content mt-5" id="menuTabContent">
        <?php foreach ($wantedCats as $index => $cat): ?>
            <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" id="content-<?= md5($cat) ?>" role="tabpanel" aria-labelledby="tab-<?= md5($cat) ?>">
                <div class="container">
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                        <?php
                        $stmt = $pdo->prepare("SELECT menu_id, menu_name, menu_description, menu_price, menu_image, menu_status FROM menu WHERE menu_category = ?");
                        $stmt->execute([$cat]);
                        $itemsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if (!empty($itemsResult)):
                          foreach ($itemsResult as $item):
                            $raw = isset($item['menu_image']) ? trim((string)$item['menu_image']) : '';
                            // Ensure client-relative path
                            $img = $raw !== '' ? ('../' . ltrim($raw, '/')) : '../assets/images/menu/menu-item-1.png';
                        ?>
                        <div class="col">
                            <div class="card h-100 p-2">
                                <div class="card-img-wrapper">
                                    <img src="<?= htmlspecialchars($img) ?>" class="card-img-top" alt="<?= htmlspecialchars($item['menu_name']) ?>">
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h5 class="card-title mb-0"><?= htmlspecialchars($item['menu_name']) ?></h5>
                                        <button class="wishlist-btn ms-2 flex-shrink-0" title="Wishlist" data-id="<?= intval($item['menu_id']) ?>"><i class="fa fa-heart"></i></button>
                                    </div>
                                    <span class="mkj-stock-badge mkj-stock-<?php echo strtolower(str_replace(' ', '-', $item['menu_status'] ?? 'In Stock')); ?>">
                                        <?= htmlspecialchars($item['menu_status'] ?? 'In Stock') ?>
                                    </span>
                                    <p class="card-text flex-grow-1"><?= htmlspecialchars($item['menu_description']) ?></p>
                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <span class="price">रु<?= number_format((float)$item['menu_price'], 2) ?></span>
                                    </div>
                                    <div class="d-flex gap-2 mt-3">
                                        <form action="../includes/cart.php?action=add" method="post" style="flex:1;min-width:0;">
                                            <input type="hidden" name="menu_id" value="<?= intval($item['menu_id']) ?>">
                                            <input type="hidden" name="menu_name" value="<?= htmlspecialchars($item['menu_name']) ?>">
                                            <input type="hidden" name="price" value="<?= htmlspecialchars($item['menu_price']) ?>">
                                            <input type="hidden" name="image" value="<?= htmlspecialchars($img) ?>">
                                            <button type="submit" class="btn btn-orange w-100 <?php echo (!$currentUser || ($item['menu_status'] ?? '') === 'Out of Stock') ? 'require-login' : ''; ?>" <?php echo (!$currentUser || ($item['menu_status'] ?? '') === 'Out of Stock') ? 'data-action="add_to_cart"' : ''; ?> <?php echo ($item['menu_status'] ?? '') === 'Out of Stock' ? 'disabled' : ''; ?>>
                                                <?= ($item['menu_status'] ?? '') === 'Out of Stock' ? 'Out of Stock' : 'Add to Cart' ?>
                                            </button>
                                        </form>
                                        <button type="button"
                                            style="flex:1;min-width:0;"
                                            class="btn btn-orange <?php echo (!$currentUser || ($item['menu_status'] ?? '') === 'Out of Stock') ? 'require-login' : ''; ?>"
                                            <?php echo (!$currentUser || ($item['menu_status'] ?? '') === 'Out of Stock') ? 'data-action="buy_now"' : ''; ?>
                                            data-bs-toggle="<?php echo ($currentUser && ($item['menu_status'] ?? '') !== 'Out of Stock') ? 'modal' : ''; ?>"
                                            data-bs-target="<?php echo ($currentUser && ($item['menu_status'] ?? '') !== 'Out of Stock') ? '#buyModal' : ''; ?>"
                                            data-id="<?= intval($item['menu_id']) ?>"
                                            data-name="<?= htmlspecialchars($item['menu_name']) ?>"
                                            data-description="<?= htmlspecialchars($item['menu_description']) ?>"
                                            data-price="<?= htmlspecialchars($item['menu_price']) ?>"
                                            data-image="<?= htmlspecialchars($img) ?>"
                                            <?php echo ($item['menu_status'] ?? '') === 'Out of Stock' ? 'disabled' : ''; ?>>
                                            <?= ($item['menu_status'] ?? '') === 'Out of Stock' ? 'Out of Stock' : 'Buy Now' ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; else: ?>
                            <div class="col">
                                <div class="alert alert-warning text-center w-100">No items in <?= htmlspecialchars($cat) ?>.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<!-- Modal -->
<div class="modal fade mkj-order-modal" id="buyModal" tabindex="-1" aria-labelledby="buyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content mkj-order-modal-content shadow">
            <form action="../includes/menu_order.php" method="post" class="needs-validation" novalidate>
                <div class="mkj-order-layout">
                    <div class="mkj-order-main">
                        <div class="mkj-order-close-wrap">
                            <button type="button" class="mkj-order-close" data-bs-dismiss="modal" aria-label="Close">
                                <i class="fa fa-xmark"></i>
                            </button>
                        </div>

                        <div class="mkj-order-product">
                            <img id="modal-image" src="" alt="" class="mkj-order-product-image" />
                            <div class="mkj-order-product-copy">
                                <h2 id="modal-name" class="mkj-order-product-title"></h2>
                                <p id="modal-description" class="mkj-order-product-description"></p>
                                <p class="mkj-order-price">रु<span id="modal-price"></span></p>
                            </div>
                        </div>

                        <hr class="mkj-order-divider">

                        <div class="mkj-order-section">
                            <label class="mkj-order-label">Quantity</label>
                            <div class="mkj-stepper">
                                <button type="button" id="qty-minus" class="mkj-stepper-btn">−</button>
                                <input type="number" id="quantity" name="quantity" min="1" value="1" required class="mkj-stepper-input">
                                <button type="button" id="qty-plus" class="mkj-stepper-btn">+</button>
                            </div>
                            <div class="invalid-feedback">Please enter valid quantity</div>
                        </div>

                        <hr class="mkj-order-divider">

                        <input type="hidden" name="menu_id" id="input-menu-id" />
                        <input type="hidden" name="menu_name" id="input-menu-name" />
                        <input type="hidden" name="price" id="input-price" />
                        <input type="hidden" name="total_price" id="input-total-price" />

                        <div class="mkj-form-grid">
                            <div class="mkj-field">
                                <label for="full_name" class="mkj-order-label">Full Name *</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="fa fa-user text-muted"></i></span>
                                    <input type="text" id="full_name" name="full_name" class="form-control mkj-control border-start-0 ps-0" value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>" required placeholder="Enter your full name">
                                </div>
                            </div>
                            <div class="mkj-field">
                                <label for="mobile" class="mkj-order-label">Mobile Number *</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="fa fa-phone text-muted"></i></span>
                                    <input type="tel" id="mobile" name="mobile" class="form-control mkj-control border-start-0 ps-0" pattern="[0-9]{10}" maxlength="10" required placeholder="Enter your mobile number">
                                </div>
                                <div class="invalid-feedback">Please enter valid number.</div>
                            </div>
                            <div class="mkj-field">
                                <label for="email" class="mkj-order-label">Email *</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="fa fa-envelope text-muted"></i></span>
                                    <input type="email" id="email" name="email" class="form-control mkj-control border-start-0 ps-0" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" placeholder="Enter your email" required>
                                </div>
                            </div>
                            <div class="mkj-field">
                                <label class="mkj-order-label">Order Type *</label>
                                <div class="d-flex gap-2 flex-wrap mkj-radio-group">
                                    <label class="mkj-custom-radio-btn">
                                        <input type="radio" name="order_type" value="Delivery" class="order-type-radio" checked>
                                        <span class="mkj-radio-indicator"></span>
                                        <i class="fa fa-motorcycle mkj-radio-icon"></i>
                                        <span class="mkj-radio-label">Delivery</span>
                                    </label>

                                    <label class="mkj-custom-radio-btn">
                                        <input type="radio" name="order_type" value="Takeaway" class="order-type-radio">
                                        <span class="mkj-radio-indicator"></span>
                                        <i class="fa fa-shopping-bag mkj-radio-icon"></i>
                                        <span class="mkj-radio-label">Takeaway</span>
                                    </label>

                                    <label class="mkj-custom-radio-btn">
                                        <input type="radio" name="order_type" value="Dine In" class="order-type-radio">
                                        <span class="mkj-radio-indicator"></span>
                                        <i class="fa fa-chair mkj-radio-icon"></i>
                                        <span class="mkj-radio-label">Dine In</span>
                                    </label>
                                </div>
                            </div>
                            <div class="mkj-field" id="table_number_wrapper" style="display:none;" hidden>
                                <label for="table_number" class="mkj-order-label" id="table_number_label">Table Number (for Dine In)</label>
                                <input type="text" id="table_number" name="table_number" class="form-control mkj-control" placeholder="Enter table number">
                            </div>
                            <div class="mkj-field" id="address_wrapper">
                                <label for="address" class="mkj-order-label" id="address_label">Delivery Address *</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 align-items-start pt-2"><i class="fa fa-location-dot text-muted"></i></span>
                                    <textarea id="address" name="address" class="form-control mkj-control border-start-0 ps-0 mkj-control-textarea" rows="3" required placeholder="Enter your complete address"></textarea>
                                </div>
                                <div class="invalid-feedback">Please enter valid address</div>
                            </div>
                            <div class="mkj-field">
                                <label for="special_instructions" class="mkj-order-label">Special Instructions (optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 align-items-start pt-2"><i class="fa fa-pen text-muted"></i></span>
                                    <textarea id="special_instructions" name="special_instructions" class="form-control mkj-control border-start-0 ps-0 mkj-control-textarea" rows="2" placeholder="Any special instructions for your order?"></textarea>
                                </div>
                            </div>
                            <div class="mkj-field">
                                <label class="mkj-order-label">Payment Method *</label>
                                <div class="d-flex gap-2 flex-wrap mkj-radio-group">
                                    <label class="mkj-custom-radio-btn">
                                        <input type="radio" name="payment_method" value="Cash on Delivery" checked>
                                        <span class="mkj-radio-indicator"></span>
                                        <i class="fa fa-money-bill mkj-radio-icon"></i>
                                        <span class="mkj-radio-label">Cash on Delivery</span>
                                    </label>

                                    <label class="mkj-custom-radio-btn">
                                        <input type="radio" name="payment_method" value="Pay at Restaurant">
                                        <span class="mkj-radio-indicator"></span>
                                        <i class="fa fa-store mkj-radio-icon"></i>
                                        <span class="mkj-radio-label">Pay at Restaurant</span>
                                    </label>
                                </div>
                            </div>
                            <div class="mkj-field mt-2">
                                <div class="form-check mkj-custom-checkbox">
                                    <input class="form-check-input" type="checkbox" id="confirm_details" required>
                                    <label class="form-check-label text-muted" for="confirm_details">
                                        I confirm that my order details are correct.
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside class="mkj-order-summary">
                        <h3 class="mkj-summary-title">Order Summary</h3>
                        <div class="mkj-summary-item">
                            <img id="summary-image" src="" alt="" class="mkj-summary-image">
                            <div class="mkj-summary-copy">
                                <div class="mkj-summary-row">
                                    <strong id="summary-name">Item</strong>
                                    <strong class="mkj-summary-price">रु<span id="modal-total-price"></span></strong>
                                </div>
                                <div class="mkj-summary-qty">Qty: <span id="summary-qty">1</span></div>
                            </div>
                        </div>
                        <div class="mkj-summary-box">
                            <div class="mkj-summary-line">
                                <span>Subtotal</span>
                                <strong>रु<span id="summary-subtotal">0.00</span></strong>
                            </div>
                            <div class="mkj-summary-line">
                                <span>Delivery Charge</span>
                                <strong class="text-success">FREE</strong>
                            </div>
                            <div class="mkj-summary-line mkj-summary-total">
                                <span>Total</span>
                                <strong class="mkj-summary-price">रु<span id="summary-total">0.00</span></strong>
                            </div>
                        </div>
                        <div class="mkj-summary-note mkj-note-green">
                            <i class="fa-regular fa-clock"></i>
                            <div>
                                <strong>Estimated Delivery Time</strong>
                                <div>30 - 40 mins</div>
                            </div>
                        </div>
                        <div class="mkj-summary-note mkj-note-amber">
                            <i class="fa-regular fa-circle-info"></i>
                            <div>
                                <strong>Note</strong>
                                <div>You will receive order updates on your mobile number.</div>
                            </div>
                        </div>
                        <ul class="mkj-benefits">
                            <li><i class="fa-regular fa-shield"></i><span>Safe &amp; Secure Order</span></li>
                            <li><i class="fa-regular fa-circle-check"></i><span>Quality Food Guaranteed</span></li>
                            <li><i class="fa-solid fa-headset"></i><span>24/7 Customer Support</span></li>
                        </ul>
                    </aside>
                </div>

                <div class="mkj-order-footer">
                    <button type="submit" class="btn mkj-confirm-btn" id="confirm-buy">
                        <i class="fa fa-bag-shopping"></i>
                        <span>Confirm Purchase</span>
                    </button>
                    <button type="button" class="btn mkj-cancel-btn" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Login Required Modal -->
<div class="modal fade login-modal" id="loginRequiredModal" tabindex="-1" aria-labelledby="loginRequiredModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="loginRequiredModalLabel">Login Required</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="bi bi-shield-lock text-danger" style="font-size: 3rem;"></i>
                </div>
                <h5 class="mb-3">Please login to continue</h5>
                <p class="text-muted">You need to be logged in to add items to cart or purchase items.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                <a href="/Masu%20Ko%20Jhol%28full%29/login.php" class="btn btn-login">Login Now</a>
            </div>
        </div>
    </div>
</div>
<?php include_once __DIR__ . '/../includes/cart_drawer.php'; ?>
<?php include_once __DIR__ . '/../footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script type="text/javascript" src="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4"
      crossorigin="anonymous"
    ></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <?php require_once __DIR__ . '/../config/bootstrap.php'; ?>
    <script src="<?php echo asset('js/script.js'); ?>"></script>
    <script src="<?php echo asset('js/order_ui.js'); ?>"></script>
    <script src="<?php echo asset('js/toast_notifications.js'); ?>"></script>
    <script>
      // Menu page bootstrap and Buy Now toast handling.
      document.addEventListener('DOMContentLoaded', () => {
        <?php if (isset($_SESSION['msg'])): $m = $_SESSION['msg']; unset($_SESSION['msg']); ?>
          window.MKJ_SESSION_MSG = {
            type: '<?php echo $m['type']; ?>',
            text: <?php echo json_encode(htmlspecialchars($m['text'])); ?>
          };
          mkjShowToastFromSession();
        <?php endif; ?>

        /* ── Toasts ── */
        document.querySelectorAll('.toast').forEach(t =>
            new bootstrap.Toast(t, { delay: 5000 }).show()
        );

        /* ── Login-required modal ── */
        const loginModal = new bootstrap.Modal(document.getElementById('loginRequiredModal'));
        document.querySelectorAll('.require-login').forEach(btn =>
            btn.addEventListener('click', e => {
                e.preventDefault();
                if (window.ToastNotifications) {
                    ToastNotifications.warning('Please login to add items to cart or buy now.', { title: 'Login required' });
                }
                loginModal.show();
            })
        );

        /* ── Live search ── */
        const searchInput = document.getElementById('menuSearchInput');
        const searchClear = document.getElementById('menuSearchClear');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const q = this.value.trim().toLowerCase();
                searchClear.classList.toggle('visible', q.length > 0);
                document.querySelectorAll('#menuTabContent .col').forEach(col => {
                    const name = col.querySelector('.card-title')?.textContent.toLowerCase() || '';
                    const desc = col.querySelector('.card-text')?.textContent.toLowerCase() || '';
                    col.classList.toggle('search-hidden', q.length > 0 && !name.includes(q) && !desc.includes(q));
                });
                // Show all panes while searching so results aren't hidden behind tabs
                document.querySelectorAll('#menuTabContent .tab-pane').forEach(p => {
                    if (q.length > 0) {
                        p.style.cssText = 'display:block;opacity:1;transform:none;pointer-events:auto;';
                    } else {
                        p.style.cssText = '';
                    }
                });
                document.getElementById('menuTab').style.opacity = q.length > 0 ? '0.5' : '1';
            });
            searchClear.addEventListener('click', () => {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
                searchInput.focus();
            });
        }

        /* ── Tab switch — re-trigger card entrance ── */
        const menuTabEl = document.getElementById('menuTab');
        if (menuTabEl) {
            menuTabEl.addEventListener('shown.bs.tab', e => {
                const pane = document.querySelector(e.target.getAttribute('data-bs-target'));
                if (!pane) return;
                pane.querySelectorAll('.col').forEach(col => {
                    col.style.animation = 'none';
                    col.offsetHeight; // force reflow
                    col.style.animation = '';
                });
            });
        }

        /* ── Wishlist heart ── */
        const wishlist = JSON.parse(localStorage.getItem('mkj_wishlist') || '[]');
        function refreshHearts() {
            document.querySelectorAll('.wishlist-btn').forEach(btn => {
                btn.classList.toggle('liked', wishlist.includes(btn.dataset.id));
            });
        }
        refreshHearts();
        document.addEventListener('click', e => {
            const btn = e.target.closest('.wishlist-btn');
            if (!btn) return;
            const id = btn.dataset.id;
            const idx = wishlist.indexOf(id);
            idx === -1 ? wishlist.push(id) : wishlist.splice(idx, 1);
            localStorage.setItem('mkj_wishlist', JSON.stringify(wishlist));
            btn.classList.toggle('liked', wishlist.includes(id));
            btn.style.transform = 'scale(1.5)';
            setTimeout(() => btn.style.transform = '', 250);
        });

        /* ── Buy Now modal ── */
        const buyModal      = document.getElementById('buyModal');
        const modalPrice    = document.getElementById('modal-price');
        const modalTotal    = document.getElementById('modal-total-price');
        const inputMenuId   = document.getElementById('input-menu-id');
          const inputMenuName = document.getElementById('input-menu-name');
          const inputPrice    = document.getElementById('input-price');
          const inputTotal    = document.getElementById('input-total-price');
          const quantityInput = document.getElementById('quantity');
          const orderTypeRadios = document.querySelectorAll('input[name="order_type"]');
          const addressWrapper = document.getElementById('address_wrapper');
          const addressField = document.getElementById('address');
          const addressLabel = document.getElementById('address_label');
          const tableWrapper = document.getElementById('table_number_wrapper');
          const tableField = document.getElementById('table_number');
          const tableLabel = document.getElementById('table_number_label');

          function recalc() {
              const price = parseFloat(modalPrice.textContent) || 0;
              const qty   = Math.max(1, parseInt(quantityInput.value) || 1);
              quantityInput.value = qty;
              const total = (price * qty).toFixed(2);
            modalTotal.textContent = total;
            inputPrice.value = price.toFixed(2);
            inputTotal.value = total;
            const summaryQty = document.getElementById('summary-qty');
            const summarySubtotal = document.getElementById('summary-subtotal');
            const summaryTotal = document.getElementById('summary-total');
            if (summaryQty) summaryQty.textContent = qty;
              if (summarySubtotal) summarySubtotal.textContent = total;
              if (summaryTotal) summaryTotal.textContent = total;
          }

          function syncOrderTypeFields() {
              const selected = document.querySelector('input[name="order_type"]:checked')?.value || 'Delivery';
              const isDelivery = selected === 'Delivery';
              const isDineIn = selected === 'Dine In';

              if (addressWrapper) {
                  addressWrapper.style.display = isDelivery ? '' : 'none';
                  addressWrapper.hidden = !isDelivery;
              }
              if (addressLabel) {
                  addressLabel.textContent = isDelivery ? 'Delivery Address *' : 'Address';
              }
              if (addressField) {
                  addressField.required = isDelivery;
                  if (!isDelivery) addressField.value = '';
              }

              if (tableWrapper) {
                  tableWrapper.style.display = isDineIn ? '' : 'none';
                  tableWrapper.hidden = !isDineIn;
              }
              if (tableLabel) {
                  tableLabel.textContent = isDineIn ? 'Table Number *' : 'Table Number (for Dine In)';
              }
              if (tableField) {
                  tableField.required = isDineIn;
                  if (!isDineIn) tableField.value = '';
              }
          }

        document.getElementById('qty-minus')?.addEventListener('click', () => {
            quantityInput.value = Math.max(1, (parseInt(quantityInput.value) || 1) - 1);
            recalc();
        });
        document.getElementById('qty-plus')?.addEventListener('click', () => {
            quantityInput.value = (parseInt(quantityInput.value) || 1) + 1;
            recalc();
        });
        quantityInput?.addEventListener('input', recalc);

          buyModal?.addEventListener('show.bs.modal', e => {
              const btn = e.relatedTarget;
              inputMenuId.value              = btn.getAttribute('data-id');
              inputMenuName.value            = btn.getAttribute('data-name');
            document.getElementById('modal-name').textContent        = btn.getAttribute('data-name');
            document.getElementById('modal-description').textContent = btn.getAttribute('data-description');
            document.getElementById('modal-image').src               = btn.getAttribute('data-image');
            document.getElementById('summary-name').textContent      = btn.getAttribute('data-name');
              document.getElementById('summary-image').src             = btn.getAttribute('data-image');
              modalPrice.textContent = parseFloat(btn.getAttribute('data-price')).toFixed(2);
              quantityInput.value    = 1;
              recalc();
              syncOrderTypeFields();
          });

          orderTypeRadios.forEach(radio => radio.addEventListener('change', syncOrderTypeFields));
          syncOrderTypeFields();

        buyModal?.querySelector('form')?.addEventListener('submit', e => {
            const form = e.currentTarget;
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                form.classList.add('was-validated');
                if (window.ToastNotifications) {
                    ToastNotifications.warning('Please fill all Buy Now details correctly.', { title: 'Order details needed' });
                }
                return;
            }
            e.preventDefault();

            const submitBtn = form.querySelector('#confirm-buy');
            const originalText = submitBtn ? submitBtn.textContent : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Placing order...';
            }

            const payload = new FormData(form);
            fetch('../includes/menu_order.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload
            })
            .then(async response => {
                const data = await response.json().catch(() => null);
                if (!response.ok || !data) {
                    throw new Error('Unable to place order.');
                }
                return data;
            })
            .then(data => {
                if (data.login_required && data.redirect) {
                    if (window.ToastNotifications) {
                        ToastNotifications.warning(data.message || 'Please login to place an order.', { title: 'Login required' });
                    }
                    window.location.href = data.redirect;
                    return;
                }

                if (data.success) {
                    if (window.ToastNotifications) {
                        ToastNotifications.success(data.message || 'Order placed successfully!', { title: 'Order placed' });
                    }
                    const modalEl = document.getElementById('buyModal');
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.hide();
                    form.reset();

                    // Move the customer to My Orders after the success toast has had a moment to appear.
                    window.setTimeout(() => {
                        window.location.href = './myorder.php';
                    }, 1200);
                } else if (window.ToastNotifications) {
                    ToastNotifications.error(data.message || 'Order failed. Please try again.', { title: 'Order failed' });
                }
            })
            .catch(() => {
                if (window.ToastNotifications) {
                    ToastNotifications.error('Order failed. Please try again.', { title: 'Order failed' });
                }
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            });
        });
    });
</script>
</body>
</html>
