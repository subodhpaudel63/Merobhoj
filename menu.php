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
$catSql = "SELECT LOWER(TRIM(category_name)) AS menu_category FROM menu_categories WHERE TRIM(category_name) <> '' UNION SELECT DISTINCT LOWER(TRIM(menu_category)) FROM menu WHERE menu_category IS NOT NULL AND TRIM(menu_category) <> '' ORDER BY menu_category";
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
</head>
<body class="menu-page">
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

    <?php $wantedCats = $categories ?: ['starter','breakfast','lunch','dinner']; ?>
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
                        $stmt = $pdo->prepare("SELECT menu_id, menu_name, menu_description, menu_price, menu_image, menu_status, stock_quantity FROM menu WHERE LOWER(TRIM(menu_category)) = ?");
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
                                    <?php $soldOut = ($item['menu_status'] ?? 'In Stock') === 'Out of Stock' || (int)($item['stock_quantity'] ?? 1) === 0; ?>
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
                                            <button type="submit" class="btn btn-orange w-100" <?= $soldOut ? 'disabled' : '' ?>><?= $soldOut ? 'Out of Stock' : 'Add to Cart' ?></button>
                                        </form>
                                        <button type="button"
                                            style="flex:1;min-width:0;"
                                            class="btn btn-orange"
                                            data-bs-toggle="<?= $soldOut ? '' : 'modal' ?>"
                                            data-bs-target="#buyModal"
                                            data-id="<?= intval($item['menu_id']) ?>"
                                            data-name="<?= htmlspecialchars($item['menu_name']) ?>"
                                            data-description="<?= htmlspecialchars($item['menu_description']) ?>"
                                            data-price="<?= htmlspecialchars($item['menu_price']) ?>"
                                            data-image="<?= htmlspecialchars($img) ?>" <?= $soldOut ? 'disabled' : '' ?>>
                                            <?= $soldOut ? 'Out of Stock' : 'Buy Now' ?>
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
</body>
</html>
