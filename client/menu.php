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
    header('Location: /Merobhoj/login.php?action=order_food');
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
    <link rel="stylesheet" href="../assets/css/clientstyles.css" />
    <!-- Include toast styles -->
    <link rel="stylesheet" href="<?php echo asset('css/toast_styles.css'); ?>" />
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

                                    <label class="mkj-custom-radio-btn">
    <input type="radio" name="payment_method" value="eSewa">

    <span class="mkj-radio-indicator"></span>
    
<img src="../assets/img/esewa/esewalogo.png"
     alt="eSewa"
     class="mkj-radio-esewa-logo">
    

    

    <span class="mkj-radio-label">Pay with eSewa</span>
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
                <a href="/Merobhoj/login.php" class="btn btn-login">Login Now</a>
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
    <script src="./script.js"></script>
    <script src="<?php echo asset('js/toast_notifications.js'); ?>"></script>
    <script src="<?php echo asset('js/toast_notifications.js'); ?>"></script>
  </body>
</html>
