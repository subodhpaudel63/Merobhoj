<?php
ob_start();
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// ==========================
// VALIDATION SECTION
// ==========================

// Check if user is logged in
$currentUser = getUserFromCookie();

if (!$currentUser) {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Please login to view your orders.'];
    header('Location: /Merobhoj/login.php?action=view_orders');
    exit;
}

$userEmail = $currentUser['email'] ?? '';
if (empty($userEmail)) {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Invalid session. Please login again.'];
    header('Location: /Merobhoj/login.php');
    exit;
}

// Optional payment redirect message
if (($_GET['payment'] ?? '') === 'esewa_success') {
    $_SESSION['msg'] = ['type' => 'success', 'text' => 'eSewa payment successful! Your order has been confirmed.'];
}

// Profile image (from secure cookie)
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

// ==========================
// DATA: The redesigned My Orders UI is rendered client-side by
// assets/js/clientscript.js, which fetches this user's orders from the
// existing JSON endpoint includes/orders_fetch.php (prepared statements,
// scoped to the logged-in user's email) and cancellations go through
// includes/order_cancel_customer.php. No server-side rendering here.
// ==========================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
      integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" type="text/css" href="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <?php require_once __DIR__ . '/../config/bootstrap.php'; ?>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>" />
    <link rel="stylesheet" href="<?php echo asset('css/toast_styles.css'); ?>" />
    <link rel="stylesheet" href="../assets/css/clientstyles.css" />
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

    <!-- ============ HEADER (shared) ============ -->
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
            <li class="list-unstyled py-2"><a class="text-dark text-decoration-none text-uppercase p-4" href="./index.php">Home</a></li>
            <li class="list-unstyled py-2"><a class="text-dark text-decoration-none text-uppercase p-4" href="./aboutus.php">About</a></li>
            <li class="list-unstyled py-2"><a class="text-dark text-decoration-none text-uppercase p-4" href="./menu.php">Menu</a></li>
            <li class="list-unstyled py-2"><a class="text-dark text-decoration-none text-uppercase p-4" href="./myorder.php">My Order</a></li>
            <li class="list-unstyled py-2"><a class="text-dark text-decoration-none text-uppercase p-4" href="./booking.php">My Booking</a></li>
            <?php if (!$currentUser): ?>
              <li class="list-unstyled py-2"><a class="btn btn-gradient" href="<?php echo url('/login.php'); ?>">Login</a></li>
            <?php endif; ?>
            <li class="list-unstyled py-2"><a class="text-dark text-decoration-none text-uppercase p-4" href="./contactus.php">Contact</a></li>
          </ul>
        </div>
        <div class="icons d-flex align-items-center">
          <a class="text-decoration-none" id="searchBtn" href="#"><i class="fa fa-search me-3"></i></a>
          <a class="text-decoration-none" id="shoppingbutton" href="./cart.php"><i class="fa fa-shopping-bag me-3"></i></a>
          <?php if ($currentUser): ?>
            <div class="dropdown">
              <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" id="profileMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?php echo url($profileImg); ?>" alt="profile" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileMenu">
                <li><h6 class="dropdown-header"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></h6></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?php echo url('includes/logout.php'); ?>"><i class="fa fa-right-from-bracket me-2"></i>Logout</a></li>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="d-flex justify-content-around py-3 align-items-center d-lg-none">
        <div id="hamburger"><i class="fa fa-2x fa-bars me-3"></i></div>
        <div class="mobile-nav-logo">
          <div class="logo">
            <a href="./index.php"><i class="fa fa-utensils me-3"></i><h1 class="mb-0">Mero Bhoj</h1></a>
          </div>
        </div>
        <div class="mobile-nav-icons">
          <div class="icons">
            <a class="text-decoration-none" id="searchBtnMobile" href="#"><i class="fa fa-search me-3"></i></a>
            <a class="text-decoration-none" id="shoppingbuttonMobile" href="./cart.php"><i class="fa fa-shopping-bag me-3"></i></a>
          </div>
        </div>
        <div class="position-fixed w-75 bg-white h-100 top-0 start-0" id="mobile-menu">
          <div id="hamburger-cross" class="d-flex justify-content-end align-items-center py-2"><i class="fa fa-2x fa-times me-3"></i></div>
          <div class="menus">
            <ul class="d-flex flex-column ps-2 mb-0 mt-4">
              <li class="list-unstyled py-2"><a class="text-dark text-decoration-none text-uppercase p-4" href="./index.php">Home</a></li>
              <li class="list-unstyled py-2"><a class="text-dark text-decoration-none text-uppercase p-4" href="./aboutus.php">About</a></li>
              <li class="list-unstyled py-2"><a class="text-dark text-decoration-none text-uppercase p-4" href="./menu.php">Menu</a></li>
              <li class="list-unstyled py-2"><a class="text-dark text-decoration-none text-uppercase p-4" href="./myorder.php">My Order</a></li>
              <li class="list-unstyled py-2"><a class="text-dark text-decoration-none text-uppercase p-4" href="./booking.php">My Booking</a></li>
              <?php if (!$currentUser): ?>
                <li class="list-unstyled py-2"><a class="btn btn-gradient" href="<?php echo url('/login.php'); ?>">Login</a></li>
              <?php endif; ?>
              <li class="list-unstyled py-2"><a class="text-dark text-decoration-none text-uppercase p-4" href="./contactus.php">Contact</a></li>
            </ul>
          </div>
        </div>
      </div>
    </header>
    <!-- ============ END HEADER ============ -->

    <!-- Shared icon set matching the reference design's line-icon style -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
<defs>
<symbol id="ic-clipboard" viewBox="0 0 24 24"><path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><rect x="5.5" y="5" width="13" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8.5 12.5l2.3 2.3L15.5 10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></symbol>
<symbol id="ic-pot" viewBox="0 0 24 24"><path d="M4 11h16v3a6 6 0 0 1-6 6H10a6 6 0 0 1-6-6v-3z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M2 11h20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M8 8c0-1.5 1-2 1-3.5M12 8c0-1.5 1-2 1-3.5M16 8c0-1.5 1-2 1-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></symbol>
<symbol id="ic-bag" viewBox="0 0 24 24"><path d="M6 8h12l1 12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L6 8z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 8V6a3 3 0 0 1 6 0v2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
<symbol id="ic-bike" viewBox="0 0 24 24"><circle cx="6" cy="17" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="18" cy="17" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M6 17l3-7h5l3 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 10h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M14 8h2.5l1.5 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="14" cy="7.3" r="1.3" fill="currentColor"/></symbol>
<symbol id="ic-door" viewBox="0 0 24 24"><rect x="5.5" y="3" width="13" height="18" rx="1.2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="14.3" cy="12" r="1.1" fill="currentColor"/></symbol>
<symbol id="ic-pin" viewBox="0 0 24 24"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="9.5" r="2.4" fill="none" stroke="currentColor" stroke-width="1.8"/></symbol>
<symbol id="ic-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3.5 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></symbol>
<symbol id="ic-headset" viewBox="0 0 24 24"><path d="M4 13v-1a8 8 0 0 1 16 0v1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><rect x="3" y="13" width="4" height="6" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.8"/><rect x="17" y="13" width="4" height="6" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M19 19v1a3 3 0 0 1-3 3h-2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
<symbol id="ic-phone" viewBox="0 0 24 24"><path d="M6.6 10.8c1.4 2.8 3.8 5.2 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25c1.1.35 2.3.55 3.6.55a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.6 21 3 13.4 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.55 3.6a1 1 0 0 1-.25 1L6.6 10.8z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></symbol>
<symbol id="ic-chat" viewBox="0 0 24 24"><path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-5 4V6a1 1 0 0 1 1-1z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></symbol>
<symbol id="ic-trash" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4.5A1.5 1.5 0 0 1 10.5 3h3A1.5 1.5 0 0 1 15 4.5V7M6.5 7l1 12.5A1.5 1.5 0 0 0 9 21h6a1.5 1.5 0 0 0 1.5-1.5L17.5 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" fill="none"/></symbol>
<symbol id="ic-filter" viewBox="0 0 24 24"><path d="M4 5h16l-6 7.5V19l-4 2v-8.5L4 5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></symbol>
<symbol id="ic-reorder" viewBox="0 0 24 24"><path d="M4 12a8 8 0 1 1 2.5 5.8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M4 17v-4h4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></symbol>
</defs>
</svg>



<div class="layout">
        <div class="main">
    <section class="page active" id="page-list">
      <div class="page-head">
        <div>
          <h1>My Orders</h1>
          <div class="breadcrumb">
            <a href="#" onclick="return false;">Home</a> &gt; <span class="active-crumb">My Orders</span>
          </div>
        </div>
      </div>

      <div class="tabs-head">
        <div class="tabs-row">
          <div class="tab active" data-tab="all">All Orders</div>
          <div class="tab" data-tab="ongoing">Ongoing</div>
          <div class="tab" data-tab="delivered">Delivered</div>
          <div class="tab" data-tab="cancelled">Cancelled</div>
        </div>
        <div class="order-filter-wrap">
          <button type="button" class="filter-btn" aria-expanded="false" aria-controls="ordersFilterMenu">
            <svg class="ic" style="width:15px;height:15px"><use href="#ic-filter"/></svg>
            <span id="filterLabel">Filter</span>
          </button>
          <div class="order-filter-menu" id="ordersFilterMenu" hidden>
            <button type="button" class="filter-option selected" data-filter="all">
              <span>All Orders</span>
              <span class="filter-check">✓</span>
            </button>
            <button type="button" class="filter-option" data-filter="ongoing">
              <span>Ongoing</span>
              <span class="filter-check">✓</span>
            </button>
            <button type="button" class="filter-option" data-filter="delivered">
              <span>Delivered</span>
              <span class="filter-check">✓</span>
            </button>
            <button type="button" class="filter-option" data-filter="cancelled">
              <span>Cancelled</span>
              <span class="filter-check">✓</span>
            </button>
          </div>
        </div>
      </div>

      <div id="ordersWrap"></div>

      <div class="list-footer">Can't find your order? <a href="tel:+9779748759699" title="Call customer support: +977 9748759699">Contact Support</a></div>
    </section>
     </div>
</div>

<!-- =================================================================
     VIEW DETAILS MODAL — shared by "View Details" (orders list) and
     "View Bill / Order Details" (tracking page). openOrderDetails(id)
     looks the order up (CONFIG.ORDER for the live one, or the `orders`
     list) and fills this in — see the JS section for that function.
     ================================================================= -->
<div class="modal-overlay" id="detailsModal" onclick="if(event.target===this) closeOrderDetails();">
  <div class="modal-box">
    <div class="modal-head">
      <div>
        <h2>Order Details</h2>
        <div class="modal-sub" id="mOrderMeta">#ORD-1048 &nbsp;|&nbsp; May 30, 2025</div>
      </div>
      <button class="modal-close" onclick="closeOrderDetails()">✕</button>
    </div>
    <div class="modal-body">
      <h3>Restaurant</h3>
      <div class="modal-restaurant">
        <img id="mResThumb" src="" alt="">
        <div>
          <div class="mr-name" id="mResName"></div>
          <div class="mr-addr" id="mResAddr"></div>
        </div>
      </div>

      <h3>Status</h3>
      <div class="modal-status-row">
        <span class="status-pill" id="mStatusPill">Out for Delivery</span>
        <span class="status-sub" id="mStatusSub" style="margin:0;"></span>
      </div>

      <h3>Items</h3>
      <div id="mItemsList"></div>

      <h3>Bill Summary</h3>
      <div id="mBillList"></div>

      <h3>Payment</h3>
      <div class="modal-bill-row"><span>Payment Method</span><span id="mPayment">Online Payment</span></div>
      <div class="modal-bill-row"><span>Order Time</span><span id="mOrderTime"></span></div>
    </div>
    <div class="modal-footer">
      <button class="mf-outline" onclick="closeOrderDetails()">Close</button>
      <button class="mf-solid" onclick="closeOrderDetails(); trackOrderFromModal();">Track This Order</button>
    </div>
  </div>
</div>


    <!-- ============ PAGE 1 : ORDER TRACKING DETAIL ============ -->
        <section class="page" id="page-track">
      <div class="page-head">
        <div class="head-title-row">
          <img class="order-hero-thumb" id="cfgRestaurantThumb" src="https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=160&h=160&fit=crop" alt="Restaurant">
          <div>
            <h1>My Order</h1>
            <div class="breadcrumb">
              <a href="#" onclick="showPage('list')">Home</a> &gt;
              <a href="#" onclick="showPage('list')">My Orders</a> &gt;
              <a href="#" class="active-crumb" onclick="return false;" id="cfgOrderCrumb" title="Order status">Order</a>
            </div>
          </div>
        </div>
        <div class="head-right">
          <div class="oid">Order ID: <span id="cfgOrderId">#ORD-1048</span></div>
          <div class="placed">Placed on: <span id="cfgPlacedDate">May 30, 2025</span> &nbsp;|&nbsp; <span id="cfgPlacedTime">10:20 AM</span></div>
        </div>
        <div class="cancel-col">
          <button class="cancel-btn"><svg class="ic" style="width:15px;height:15px"><use href="#ic-trash"/></svg> Cancel Order</button>
          <div class="cancel-note">You can cancel until 10:25 AM</div>
        </div>
      </div>

      <div class="card stepper-card">
        <div class="steplines">
          <div class="stepline-seg done"></div>
          <div class="stepline-seg done"></div>
          <div class="stepline-seg done"></div>
          <div class="stepline-seg done"></div>
          <div class="stepline-seg pending"></div>
        </div>
        <div class="stepper">
          <div class="step done"><div class="dot"><svg class="ic" style="width:22px;height:22px"><use href="#ic-clipboard"/></svg></div><div class="slabel">Order Placed</div><div class="stime">10:20 AM</div></div>
          <div class="step done"><div class="dot"><svg class="ic" style="width:22px;height:22px"><use href="#ic-clipboard"/></svg></div><div class="slabel">Confirmed</div><div class="stime">10:21 AM</div></div>
          <div class="step done"><div class="dot"><svg class="ic" style="width:22px;height:22px"><use href="#ic-pot"/></svg></div><div class="slabel">Preparing</div><div class="stime">10:35 AM</div></div>
          <div class="step done"><div class="dot"><svg class="ic" style="width:22px;height:22px"><use href="#ic-bag"/></svg></div><div class="slabel">Ready</div><div class="stime">11:05 AM</div></div>
          <div class="step current"><div class="dot"><svg class="ic" style="width:22px;height:22px"><use href="#ic-bike"/></svg></div><div class="slabel">Out for Delivery</div><div class="stime">11:15 AM</div></div>
          <div class="step pending"><div class="dot"><svg class="ic" style="width:22px;height:22px"><use href="#ic-door"/></svg></div><div class="slabel">Delivered</div><div class="stime">--:--</div></div>
        </div>
        <div class="live-banner">
          <div class="lb-left">
            <span class="live-pill"><span class="dotpulse"></span> LIVE</span>
            <span>Your order is on the way! The rider is approaching your location.</span>
          </div>
          <div class="lb-time"><span class="live-dotgreen"></span> Last updated: <span id="lastUpdated">11:28 AM</span></div>
        </div>
      </div>

      <div class="grid2">
        <div class="left-col">
          <div class="card">
            <div class="eta-block">
              <div class="label">Estimated Delivery</div>
              <div class="big" id="etaMin">15 min</div>
              <div class="range">(11:43 AM - 11:48 AM)</div>
            </div>
            <div class="meta-row">
              <div class="meta-item">
                <div class="meta-icon"><svg class="ic" style="width:16px;height:16px"><use href="#ic-pin"/></svg></div>
                <div><span class="mval" id="distVal">3.4 km</span><span class="mlabel">Distance</span></div>
              </div>
              <div class="meta-item">
                <div class="meta-icon"><svg class="ic" style="width:16px;height:16px"><use href="#ic-clock"/></svg></div>
                <div><span class="mval" id="etaVal">20 min</span><span class="mlabel">ETA</span></div>
              </div>
            </div>
          </div>

          <div class="card rider-card">
            <h3>Rider Details</h3>
            <div class="rider-row">
              <img id="cfgRiderAvatar" src="https://i.pravatar.cc/80?img=51" alt="Rider">
              <div>
                <div class="rider-name" id="cfgRiderName">Ramesh Tamang</div>
                <div class="rider-rating">⭐ <span id="cfgRiderRating">4.8</span></div>
              </div>
            </div>
            <div class="rider-actions">
              <button class="rider-btn"><svg class="ic" style="width:15px;height:15px"><use href="#ic-phone"/></svg> <span id="cfgRiderPhone">+977 9812345678</span></button>
              <button class="rider-btn chat"><svg class="ic" style="width:17px;height:17px"><use href="#ic-chat"/></svg></button>
            </div>
          </div>

          <div class="card items-card">
            <h3>Items in this Order</h3>
            <!-- Rows below are rendered from CONFIG.ITEMS by renderTrackPage() -->
            <div id="cfgItemsList"></div>
          </div>

          <div class="card order-details">
            <h3>Order Details</h3>
            <div class="od-row"><span>Order ID</span><span id="cfgOrderIdRepeat">#ORD-1048</span></div>
            <div class="od-row"><span>Order Time</span><span id="cfgOrderTime">May 30, 2025 | 10:20 AM</span></div>
            <div class="od-row"><span>Payment Method</span><span id="cfgPaymentMethod">Online Payment</span></div>
            <div class="od-row total"><span>Total Amount</span><span id="cfgTotalAmount">NPR 780.00</span></div>
            <button class="view-bill-btn" onclick="openOrderDetails(CONFIG.ORDER.id)">View Bill / Order Details</button>
          </div>
        </div>

        <div class="right-col">
          <div class="card map-card">
            <div class="map-tag"><span class="liveblink"></span> Live Tracking</div>
            <div class="map-tag" style="left:auto;right:16px;font-weight:600;">🇳🇵 Pokhara, Nepal</div>
            <div id="map"></div>
            <div class="map-footer">
              <div class="mf-item"><div class="mf-icon"><svg class="ic" style="width:18px;height:18px"><use href="#ic-bike"/></svg></div><div><div class="mf-title">Rider is on the way</div><div class="mf-sub">Arriving soon</div></div></div>
              <div class="mf-item"><div class="mf-icon"><svg class="ic" style="width:18px;height:18px"><use href="#ic-pin"/></svg></div><div><div class="mf-title" id="mfDist">3.4 km away</div><div class="mf-sub">from your location</div></div></div>
              <div class="mf-item"><div class="mf-icon"><svg class="ic" style="width:18px;height:18px"><use href="#ic-clock"/></svg></div><div><div class="mf-title" id="mfEta">20 min</div><div class="mf-sub">estimated time</div></div></div>
            </div>
          </div>

          <div class="card help-card">
            <div class="help-left">
              <div class="help-icon"><svg class="ic" style="width:22px;height:22px"><use href="#ic-headset"/></svg></div>
              <div><div class="ht">Need help with your order?</div><div class="hs">Our support team is available 24/7.</div></div>
            </div>
            <div class="help-actions">
              <a class="help-btn outline" href="tel:+9779748759699" title="Call customer support: +977 9748759699"><svg class="ic" style="width:15px;height:15px"><use href="#ic-phone"/></svg> Call Support</a>
              <button class="help-btn solid"><svg class="ic" style="width:15px;height:15px"><use href="#ic-chat"/></svg> Live Chat</button>
            </div>
          </div>
        </div>
      </div>
    </section>
    
<?php include_once __DIR__ . '/../footer.php'; ?>


    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script type="text/javascript" src="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <?php require_once __DIR__ . '/../config/bootstrap.php'; ?>
    
    <script src="<?php echo asset('js/script.js'); ?>"></script>
    <script src="<?php echo asset('js/toast_notifications.js'); ?>"></script>
    <script src="<?php echo asset('js/clientscript.js'); ?>"></script>
</body>
</html>