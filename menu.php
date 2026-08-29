<?php
ob_start();
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_check.php';

// Check if user is logged in
$currentUser = getUserFromCookie();

// Guests can browse — ordering is protected via the login-required modal.

try {
  $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
} catch (PDOException $e) {
  die("Database connection failed: " . $e->getMessage());
}

// Profile image — only for logged-in users
$profileImg = 'assets/images/profile.jpg';
if ($currentUser && isset($_COOKIE['user_img'])) {
  $dec = decrypt($_COOKIE['user_img'], SECRET_KEY);
  if ($dec && is_string($dec)) {
    $candidate = ltrim($dec, '/');
    if (file_exists(__DIR__ . '/' . $candidate)) {
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
    <?php require_once __DIR__ . '/config/bootstrap.php'; ?>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>" />
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
    .section-title h2 { position: relative; display: inline-block; }
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
    @keyframes titleLine { to { transform: scaleX(1); } }

    /* ── Live Search Bar ── */
    .menu-search-wrap { display: flex; justify-content: center; margin: 0 0 28px; padding: 0 16px; }
    .menu-search-bar {
      width: 100%; max-width: 480px;
      display: flex; align-items: center;
      background: #fff; border-radius: 50px;
      box-shadow: 0 4px 20px rgba(0,0,0,.09);
      padding: 7px 7px 7px 18px; gap: 8px;
      border: 2px solid transparent;
      transition: border-color .25s ease, box-shadow .25s ease;
    }
    .menu-search-bar:focus-within { border-color: var(--orange); box-shadow: 0 4px 20px var(--orange-glow); }
    .menu-search-bar i { color: #bbb; font-size: .9rem; flex-shrink: 0; }
    .menu-search-bar input { border: none; outline: none; background: transparent; flex: 1; font-size: .93rem; color: #333; }
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
    .menu-tabs-wrapper { display: flex; justify-content: center; margin-bottom: 32px; }
    #menuTab {
      border-bottom: none; gap: 4px; flex-wrap: wrap; padding: 5px;
      background: #fff; border-radius: 50px; display: inline-flex !important;
      box-shadow: 0 3px 16px rgba(0,0,0,.08);
    }
    #menuTab .nav-item { margin: 0; }
    #menuTab .nav-link {
      border: none; border-radius: 50px; padding: 10px 24px;
      font-weight: 600; font-size: .92rem; color: #666; background: transparent;
      transition: background .28s var(--ease-out), color .28s var(--ease-out),
                  box-shadow .28s var(--ease-out), transform .2s var(--ease-out);
      white-space: nowrap;
    }
    #menuTab .nav-link:hover { background: rgba(255,106,0,.08); color: var(--orange); transform: translateY(-1px); }
    #menuTab .nav-link.active { background: var(--orange); color: #fff !important; box-shadow: 0 5px 18px var(--orange-glow); transform: translateY(-1px); }

    /* ── Tab content transition ── */
    .tab-content { position: relative; }
    .tab-pane { transition: opacity .38s var(--ease-out), transform .38s var(--ease-out); opacity: 0; transform: translateY(14px); pointer-events: none; }
    .tab-pane.show.active { opacity: 1; transform: translateY(0); pointer-events: auto; }

    /* ── Cards ── */
    .menu .card {
      border-radius: 18px; overflow: hidden; border: 0; background: #fff;
      box-shadow: 0 4px 18px rgba(0,0,0,.07);
      transition: transform .32s var(--ease-out), box-shadow .32s var(--ease-out);
      position: relative;
    }
    .menu .card::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, var(--orange), #ff8c00);
      transform: scaleX(0); transform-origin: left;
      transition: transform .32s var(--ease-out); z-index: 2;
    }
    .menu .card:hover::before { transform: scaleX(1); }
    .menu .card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(0,0,0,.11); }
    .card-img-wrapper { position: relative; overflow: hidden; height: 220px; }
    .menu .card-img-top { width: 100%; height: 100%; object-fit: cover; transition: transform .45s var(--ease-out); }
    .menu .card:hover .card-img-top { transform: scale(1.06); }
    .menu .card-title { font-weight: 800; color: #d32f2f; }
    .menu .card-text  { color: #6c757d; font-size: .87rem; line-height: 1.55; }
    .menu .price      { color: #212529; font-weight: 700; font-size: 1.05rem; }

    /* ── Wishlist heart ── */
    .wishlist-btn { background: none; border: none; padding: 0; color: #e0e0e0; font-size: 1rem; cursor: pointer; line-height: 1; transition: color .22s ease, transform .22s var(--ease-spring); }
    .wishlist-btn.liked { color: #e53935; }
    .wishlist-btn:hover { transform: scale(1.25); color: #e53935; }

    /* ── Buttons ── */
    .btn-orange { background: var(--orange); color: #fff; border-radius: 10px; border: 1px solid var(--orange); white-space: nowrap; font-weight: 600; transition: background .22s ease, transform .18s var(--ease-out), box-shadow .22s ease; position: relative; overflow: hidden; }
    .btn-orange:hover { background: var(--orange-dark); color: #fff; border-color: var(--orange-dark); transform: translateY(-2px); box-shadow: 0 6px 18px var(--orange-glow); }
    .btn-orange:active { transform: translateY(0); }
    .btn.btn-orange       { background: var(--orange)      !important; border-color: var(--orange)      !important; color: #fff !important; }
    .btn.btn-orange:hover { background: var(--orange-dark) !important; border-color: var(--orange-dark) !important; }

    /* ── Staggered card entrance ── */
    @keyframes cardRise { from { opacity: 0; transform: translateY(22px) scale(.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
    .tab-pane.show.active .col { animation: cardRise .45s var(--ease-out) both; }
    .tab-pane.show.active .col:nth-child(1)  { animation-delay: .03s; }
    .tab-pane.show.active .col:nth-child(2)  { animation-delay: .08s; }
    .tab-pane.show.active .col:nth-child(3)  { animation-delay: .13s; }
    .tab-pane.show.active .col:nth-child(4)  { animation-delay: .18s; }
    .tab-pane.show.active .col:nth-child(5)  { animation-delay: .23s; }
    .tab-pane.show.active .col:nth-child(6)  { animation-delay: .28s; }
    .tab-pane.show.active .col:nth-child(7)  { animation-delay: .33s; }
    .tab-pane.show.active .col:nth-child(n+8){ animation-delay: .38s; }
    .col.search-hidden { display: none !important; }

    /* ── Qty stepper ── */
    .qty-stepper { display: flex; align-items: center; border: 2px solid #e8e8e8; border-radius: 12px; overflow: hidden; }
    .qty-stepper button { background: #f5f5f5; border: none; width: 38px; height: 38px; font-size: 1.15rem; font-weight: 700; color: #555; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .18s, color .18s; }
    .qty-stepper button:hover { background: var(--orange); color: #fff; }
    .qty-stepper input { width: 50px; text-align: center; border: none; outline: none; background: transparent; font-size: .95rem; font-weight: 700; color: #212529; }

    /* ── Modal polish ── */
    #buyModal .modal-content { border-radius: 20px; overflow: hidden; border: none; box-shadow: 0 24px 60px rgba(0,0,0,.18); }
    #buyModal .modal-header  { border-bottom: 1px solid #f0f0f0; }
    #buyModal .modal-footer  { border-top: 1px solid #f0f0f0; }
    #buyModal #modal-image   { border-radius: 14px; width: 100%; height: 200px; object-fit: cover; }
    #buyModal .btn-success   { border-radius: 10px; font-weight: 700; padding: 10px 24px; transition: transform .2s, box-shadow .2s; }
    #buyModal .btn-success:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(46,125,50,.35); }

    /* ── Button row ── */
    .card-body .d-flex.gap-2.mt-3 { align-items: stretch; }
    .card-body .d-flex.gap-2.mt-3 form, .card-body .d-flex.gap-2.mt-3 > .btn { flex: 1; min-width: 0; display: flex; }
    .card-body .d-flex.gap-2.mt-3 form .btn { width: 100%; justify-content: center; align-items: center; }
    .card-body .d-flex.gap-2.mt-3 > .btn { justify-content: center; align-items: center; white-space: nowrap; }

    /* ── Navbar padding ── */
    .main-content { padding-top: 100px; }
    @media (max-width: 991px) {
      .main-content { padding-top: 80px; }
      .card.h-100.p-2 { padding: .5rem !important; }
      #menuTab .nav-link { padding: 9px 16px; font-size: .85rem; }
    }

    /* ── Nav Login / Sign Up buttons ── */
    .nav-button {
      background-color: #ff0000; color: white; border: none;
      padding: 8px 20px; border-radius: 30px; font-weight: 500;
      transition: all 0.3s ease; text-decoration: none;
      display: inline-block; margin-left: 10px;
    }
    .nav-button:hover { background-color: #cc0000; color: white; text-decoration: none; }
    .nav-button-outline {
      background-color: transparent; color: #ff0000; border: 2px solid #ff0000;
      padding: 6px 18px; border-radius: 30px; font-weight: 500;
      transition: all 0.3s ease; text-decoration: none;
      display: inline-block; margin-left: 10px;
    }
    .nav-button-outline:hover { background-color: #ff0000; color: white; text-decoration: none; }

    /* ── Login Required Modal ── */
    .login-modal .modal-content { border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    .login-modal .modal-header { background: linear-gradient(135deg, #ff6a00, #d32f2f); color: white; border-top-left-radius: 15px; border-top-right-radius: 15px; border: none; }
    .login-modal .btn-login { background: linear-gradient(135deg, #ff6a00, #d32f2f); border: none; color: white; padding: 10px; font-weight: 600; }
    .login-modal .btn-login:hover { background: linear-gradient(135deg, #e65f00, #c62828); color: white; }
    </style>
</head>
<body>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:2000;">
      <?php if (isset($_SESSION['msg'])): $m=$_SESSION['msg']; unset($_SESSION['msg']); ?>
        <div class="toast show align-items-center border-0 <?php echo $m['type']==='success'?'text-bg-success':'text-bg-danger'; ?>" role="alert" aria-live="assertive" aria-atomic="true" style="<?php echo $m['type']==='success'?'background:#0f5132;':''; ?>" data-bs-delay="5000">
          <div class="d-flex">
            <div class="toast-body small fw-semibold"><?php echo htmlspecialchars($m['text']); ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="loader">
      <i class="fas fa-utensils loader-icone"></i>
      <p>Mero Bhoj</p>
      <div class="loader-ellipses">
        <span></span>
        <span></span>
        <span></span>
      </div>
    </div>

    <?php include 'header.php'; ?>

    <!-- <header>
      
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
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./index.php">Home</a>
            </li>
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./aboutus.php">About</a>
            </li>
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./menu.php">Menu</a>
            </li>
            
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./contactus.php">Contact</a>
            </li>
          </ul>
        </div>
        <div class="icons d-flex align-items-center">
          <a class="text-decoration-none" id="searchBtn" href="#"><i class="fa fa-search me-3"></i></a>
          <a class="text-decoration-none <?php echo !$currentUser ? 'require-login' : ''; ?>"
             id="shoppingbutton"
             href="<?php echo $currentUser ? './cart.php' : '#'; ?>">
            <i class="fa fa-shopping-bag me-3"></i>
          </a>
          
          <a href="./login.php" class="nav-button">Login</a>
          <a href="./register.php" class="nav-button-outline">Sign Up</a>
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
            <a class="text-decoration-none <?php echo !$currentUser ? 'require-login' : ''; ?>"
               id="shoppingbuttonMobile"
               href="<?php echo $currentUser ? './cart.php' : '#'; ?>">
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
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./index.php">Home</a>
              </li>
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./aboutus.php">About</a>
              </li>
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./menu.php">Menu</a>
              </li>
              
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./contactus.php">Contact</a>
              </li>
              
              <?php if (!$currentUser): ?>
                <li class="list-unstyled py-2 ps-3">
                  <a href="./login.php" class="nav-button">Login</a>
                  <a href="./register.php" class="nav-button-outline">Sign Up</a>
                </li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
      </div>
    </header> -->

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

    <?php $wantedCats = ['starter','breakfast','lunch','dinner']; ?>
    <div class="menu-tabs-wrapper">
    <ul class="nav nav-tabs" id="menuTab" role="tablist">
        <?php foreach ($wantedCats as $index => $cat): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $index === 0 ? 'active' : '' ?>"
                    id="tab-<?= md5($cat) ?>"
                    data-bs-toggle="tab"
                    data-bs-target="#content-<?= md5($cat) ?>"
                    type="button" role="tab"
                    aria-controls="content-<?= md5($cat) ?>"
                    aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                    <?= htmlspecialchars(ucfirst($cat)) ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>
    </div>

    <div class="tab-content mt-5" id="menuTabContent">
        <?php foreach ($wantedCats as $index => $cat): ?>
            <div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>"
                 id="content-<?= md5($cat) ?>"
                 role="tabpanel"
                 aria-labelledby="tab-<?= md5($cat) ?>">
                <div class="container">
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                        <?php
                        $stmt = $pdo->prepare("SELECT menu_id, menu_name, menu_description, menu_price, menu_image FROM menu WHERE menu_category = ?");
                        $stmt->execute([$cat]);
                        $itemsResult = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if (!empty($itemsResult)):
                          foreach ($itemsResult as $item):
                            $raw = isset($item['menu_image']) ? trim((string)$item['menu_image']) : '';
                            $img = $raw !== '' ? ('./' . ltrim($raw, '/')) : './assets/images/menu/menu-item-1.png';
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
                                    <p class="card-text flex-grow-1"><?= htmlspecialchars($item['menu_description']) ?></p>
                                    <div class="d-flex justify-content-between align-items-center mt-1">
                                        <span class="price">रु<?= number_format((float)$item['menu_price'], 2) ?></span>
                                    </div>
                                    <div class="d-flex gap-2 mt-3">
                                        <?php if ($currentUser): ?>
                                        <!-- LOGGED IN: real form + real modal trigger -->
                                        <form action="./includes/cart.php?action=add" method="post" style="flex:1;min-width:0;">
                                            <input type="hidden" name="menu_id" value="<?= intval($item['menu_id']) ?>">
                                            <input type="hidden" name="menu_name" value="<?= htmlspecialchars($item['menu_name']) ?>">
                                            <input type="hidden" name="price" value="<?= htmlspecialchars($item['menu_price']) ?>">
                                            <input type="hidden" name="image" value="<?= htmlspecialchars($img) ?>">
                                            <button type="submit" class="btn btn-orange w-100">Add to Cart</button>
                                        </form>
                                        <button type="button"
                                            style="flex:1;min-width:0;"
                                            class="btn btn-orange"
                                            data-bs-toggle="modal"
                                            data-bs-target="#buyModal"
                                            data-id="<?= intval($item['menu_id']) ?>"
                                            data-name="<?= htmlspecialchars($item['menu_name']) ?>"
                                            data-description="<?= htmlspecialchars($item['menu_description']) ?>"
                                            data-price="<?= htmlspecialchars($item['menu_price']) ?>"
                                            data-image="<?= htmlspecialchars($img) ?>">
                                            Buy Now
                                        </button>
                                        <?php else: ?>
                                        <!-- GUEST: plain buttons, no form, no modal — only show login modal -->
                                        <button type="button" class="btn btn-orange w-100 guest-block" style="flex:1;min-width:0;">Add to Cart</button>
                                        <button type="button" class="btn btn-orange guest-block" style="flex:1;min-width:0;">Buy Now</button>
                                        <?php endif; ?>
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

<!-- Buy Now Modal -->
<div class="modal fade" id="buyModal" tabindex="-1" aria-labelledby="buyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow">
            <form action="./includes/menu_order.php" method="post" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="buyModalLabel">Item Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-4">
                        <div class="col-md-5 text-center">
                            <img id="modal-image" src="" alt="" class="img-fluid" style="border-radius:14px;width:100%;height:200px;object-fit:cover;" />
                        </div>
                        <div class="col-md-7">
                            <h4 id="modal-name" class="text-primary fw-bold"></h4>
                            <p id="modal-description" class="text-muted"></p>
                            <p><strong>Price: रु<span id="modal-price"></span></strong></p>
                            <p><strong>Total: रु<span id="modal-total-price"></span></strong></p>
                            <input type="hidden" name="menu_id" id="input-menu-id" />
                            <input type="hidden" name="menu_name" id="input-menu-name" />
                            <input type="hidden" name="price" id="input-price" />
                            <input type="hidden" name="total_price" id="input-total-price" />
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo $currentUser['email'] ?? '' ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="quantity" class="form-label">Quantity</label>
                                <div class="qty-stepper">
                                    <button type="button" id="qty-minus">−</button>
                                    <input type="number" id="quantity" name="quantity" min="1" value="1" required>
                                    <button type="button" id="qty-plus">+</button>
                                </div>
                                <div class="invalid-feedback">Please enter valid quantity</div>
                            </div>
                            <div class="mb-3">
                                <label for="mobile" class="form-label">Mobile Number</label>
                                <input type="tel" id="mobile" name="mobile" class="form-control" pattern="[0-9]{10}" maxlength="10" required>
                                <div class="invalid-feedback">Please enter valid number.</div>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Delivery Address</label>
                                <textarea id="address" name="address" class="form-control" rows="3" required></textarea>
                                <div class="invalid-feedback">Please enter valid address</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="submit" class="btn btn-success" id="confirm-buy">Confirm Purchase</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
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
                <a href="./login.php" class="btn btn-login">Login Now</a>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script type="text/javascript" src="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
<script src="https://unpkg.com/aos@next/dist/aos.js"></script>
<?php require_once __DIR__ . '/config/bootstrap.php'; ?>
<script src="<?php echo asset('js/script.js'); ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {

        /* ── Toasts ── */
        document.querySelectorAll('.toast').forEach(t =>
            new bootstrap.Toast(t, { delay: 5000 }).show()
        );

        /* ── Login Required modal for guests ── */
        const loginModal = new bootstrap.Modal(document.getElementById('loginRequiredModal'));
        document.querySelectorAll('.guest-block').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                loginModal.show();
            });
        });

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
                    col.offsetHeight;
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

        function recalc() {
            const price = parseFloat(modalPrice.textContent) || 0;
            const qty   = Math.max(1, parseInt(quantityInput.value) || 1);
            quantityInput.value = qty;
            const total = (price * qty).toFixed(2);
            modalTotal.textContent = total;
            inputPrice.value = price.toFixed(2);
            inputTotal.value = total;
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
            modalPrice.textContent = parseFloat(btn.getAttribute('data-price')).toFixed(2);
            quantityInput.value    = 1;
            recalc();
        });
    });
</script>
</body>
</html>