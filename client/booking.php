<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// This page shows only the logged-in user's bookings; the data endpoint
// (includes/bookings_fetch.php) is auth-scoped, so the page must be too.
$currentUser = getUserFromCookie();
if (!$currentUser) {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Please login to view your bookings.'];
    header('Location: ' . url('login.php'));
    exit;
}

// Profile image for the header dropdown (from secure cookie), same as myorder.php
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

// Single source of truth for status filters — rendered once as tabs and once
// as dropdown options, so the two lists can never drift apart.
$bookingFilters = [
    'all'       => 'All Bookings',
    'upcoming'  => 'Upcoming',
    'confirmed' => 'Confirmed',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

// Guest options for the edit modal.
$guestOptions = range(1, 8);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Mero Bhoj - All Bookings</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<?php require_once __DIR__ . '/../config/bootstrap.php'; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>" />
    <link rel="stylesheet" href="<?php echo asset('css/toast_styles.css'); ?>" />
    <link rel="stylesheet" href="<?php echo asset('css/clientstyles.css'); ?>" />
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
              <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" id="profileMenu" data-bs-toggle="dropdown" aria-expanded="false" onclick="event.preventDefault(); this.nextElementSibling.classList.toggle('show');">
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
              <a class="text-decoration-none text-uppercase p-4" href="./booking.php"
                >MY Booking</a>
            </li>
              <?php if (!$currentUser): ?>
                <li class="list-unstyled py-2"><a class="btn btn-gradient" href="<?php echo url('/login.php'); ?>">Login</a></li>
              <?php endif; ?>
              <li class="list-unstyled py-2"><a class="text-dark text-decoration-none text-uppercase p-4" href="./contactus.php">Contact</a></li>
            </ul>
          </div>
        </div>
      </div>
    </header>

<main class="bookingpage bk-page">

<div class="bk-page-head">
    <div class="bk-page-title">
        <h1>All Bookings</h1>
        <div class="bk-breadcrumb">
            <span>Home</span><span class="bk-arrow">›</span><b>My Bookings</b>
        </div>
    </div>

    <div class="bk-head-cards">
        <!-- Upcoming Booking -->
        <div class="bk-head-card">
            <div class="bk-head-icon" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
                    <rect x="5" y="6" width="22" height="21" rx="2"/>
                    <path d="M10 3v6M22 3v6M5 12h22M10 17h3M17 17h3M10 22h3"/>
                </svg>
            </div>
            <div class="bk-head-info">
                <div class="bk-small">Upcoming Booking</div>
                <div class="bk-big" id="upcomingBookingText">No upcoming booking</div>
                <div class="bk-tiny" id="upcomingBookingTable">No table scheduled</div>
            </div>
        </div>

        <!-- Active Grace Timer -->
        <div class="bk-head-card bk-timer-card">
            <div class="bk-head-icon" aria-hidden="true">
                <svg viewBox="0 0 50 50" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true" focusable="false">
                    <circle cx="24" cy="25" r="16"/>
                    <path d="M24 16v10l7 5M24 5v5M39 11l4-4"/>
                </svg>
            </div>
            <div class="bk-timer-info">
                <div class="bk-timer-title">Active Grace Timer</div>
                <div class="bk-timer-number" id="timer" role="timer" aria-live="off">--:--</div>
                <div class="bk-timer-sub" id="timerSub" aria-live="polite">No active timer</div>
            </div>
        </div>
    </div>
</div>

<section class="bk-bookings-shell">
    <div class="bk-toolbar">
        <div class="bk-tabs">
            <?php foreach ($bookingFilters as $filterKey => $filterLabel): ?>
            <button class="bk-tab<?= $filterKey === 'all' ? ' active' : '' ?>" data-filter="<?= $filterKey ?>" type="button">
                <?= htmlspecialchars($filterLabel) ?> <span class="bk-badge" data-count="<?= $filterKey ?>">0</span>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="bk-filter-wrap" id="filterWrap">
            <button class="bk-filter" id="filterBtn" type="button" aria-expanded="false" aria-controls="filterMenu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
                    <path d="M4 5h16l-6.5 8v5l-3 1v-6L4 5Z"/>
                </svg>
                <span id="filterLabel">Filter</span>
                <span aria-hidden="true">⌄</span>
            </button>
            <div class="bk-filter-menu" id="filterMenu">
                <?php foreach ($bookingFilters as $filterKey => $filterLabel): ?>
                <button class="bk-filter-option<?= $filterKey === 'all' ? ' selected' : '' ?>" data-filter-choice="<?= $filterKey ?>" type="button">
                    <span><?= htmlspecialchars($filterLabel) ?></span><span class="bk-filter-check" aria-hidden="true">✓</span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="bk-table-wrap">
        <table class="bk-table">
            <colgroup><col><col><col><col><col><col><col></colgroup>
            <thead>
                <tr>
                    <th>Booking Details</th>
                    <th>Date &amp; Time</th>
                    <th>Guests</th>
                    <th>Table</th>
                    <th>Status</th>
                    <th>Amount</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody id="rows">
            <!-- Rows are loaded from the backend (includes/bookings_fetch.php) by clientscript.js -->
            </tbody>
        </table>
    </div>
</section>

<div class="bk-note">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" focusable="false">
        <circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7h.01"/>
    </svg>
    Showing <span id="shown">0 of 0</span> bookings
</div>

<!-- View Details Modal -->
<div class="bk-modal-backdrop" id="viewModal">
    <div class="bk-modal" role="dialog" aria-modal="true" aria-labelledby="viewModalTitle">
        <div class="bk-modal-head">
            <div>
                <div class="bk-modal-title" id="viewModalTitle">Booking Details</div>
                <div class="bk-modal-subtitle">Complete information about this booking</div>
            </div>
            <button class="bk-modal-close" data-close="viewModal" type="button" aria-label="Close booking details dialog">×</button>
        </div>
        <div class="bk-modal-body">
            <div class="bk-detail-banner">
                <div class="bk-table-pic" aria-hidden="true">
                    <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M17 28h30"/><path d="M20 25h24"/><path d="M23 28v13"/><path d="M41 28v13"/>
                        <path d="M14 41h9M41 41h9"/><path d="M13 21h10M41 21h10"/><path d="M16 21v9M48 21v9"/>
                        <path d="M25 28v-7M39 28v-7"/><path d="M32 21v7"/><path d="M27 36h10"/><path d="M20 41v5M44 41v5"/>
                    </svg>
                </div>
                <div>
                    <div class="bk-detail-banner-id" id="viewId">No booking selected</div>
                    <div class="bk-detail-banner-name" id="viewRestaurant">Mero Bhoj</div>
                </div>
            </div>

            <div class="bk-info-grid">
                <div class="bk-info-box"><div class="bk-info-label">Customer Name</div><div class="bk-info-value" id="viewName">—</div></div>
                <div class="bk-info-box"><div class="bk-info-label">Status</div><div class="bk-info-value" id="viewStatus">—</div></div>
                <div class="bk-info-box"><div class="bk-info-label">Date</div><div class="bk-info-value" id="viewDate">—</div></div>
                <div class="bk-info-box"><div class="bk-info-label">Time</div><div class="bk-info-value" id="viewTime">—</div></div>
                <div class="bk-info-box"><div class="bk-info-label">Guests</div><div class="bk-info-value" id="viewGuests">—</div></div>
                <div class="bk-info-box"><div class="bk-info-label">Table</div><div class="bk-info-value" id="viewTable">—</div></div>
                <div class="bk-info-box"><div class="bk-info-label">Payment</div><div class="bk-info-value" id="viewAmount">—</div></div>
                <div class="bk-info-box"><div class="bk-info-label">Booking Time</div><div class="bk-info-value" id="viewBooked">—</div></div>
            </div>
        </div>
        <div class="bk-modal-foot">
            <button class="bk-modal-btn" data-close="viewModal" type="button">Close</button>
            <button class="bk-modal-btn bk-primary" id="viewEditBtn" type="button">Edit Booking</button>
        </div>
    </div>
</div>


<!-- Edit Modal -->
<div class="bk-modal-backdrop" id="editModal">
    <div class="bk-modal" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
        <div class="bk-modal-head">
            <div>
                <div class="bk-modal-title" id="editModalTitle">Edit Booking</div>
                <div class="bk-modal-subtitle">Update your reservation details</div>
            </div>
            <button class="bk-modal-close" data-close="editModal" type="button" aria-label="Close edit booking dialog">×</button>
        </div>

        <div class="bk-modal-body">
            <form id="editForm">
                <div class="bk-form-grid">
                    <div class="bk-form-group">
                        <label class="bk-form-label" for="editName">Full Name</label>
                        <input class="bk-form-input" id="editName" name="name" autocomplete="name" value="">
                    </div>
                    <div class="bk-form-group">
                        <label class="bk-form-label" for="editPhone">Phone Number</label>
                        <input class="bk-form-input" id="editPhone" name="phone" type="tel" autocomplete="tel" value="">
                    </div>
                    <div class="bk-form-group">
                        <label class="bk-form-label" for="editDate">Date</label>
                        <input class="bk-form-input" type="date" id="editDate" name="booking_date" value="">
                    </div>
                    <div class="bk-form-group">
                        <label class="bk-form-label" for="editTime">Start Time</label>
                        <input class="bk-form-input" type="time" id="editTime" name="booking_time" value="">
                    </div>
                    <div class="bk-form-group">
                        <label class="bk-form-label" for="editGuests">Guests</label>
                        <select class="bk-form-select" id="editGuests" name="people">
                            <?php foreach ($guestOptions as $count): ?>
                            <option value="<?= $count ?> <?= $count === 1 ? 'Person' : 'People' ?>">
                                <?= $count ?> <?= $count === 1 ? 'Person' : 'People' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="bk-form-group">
                        <label class="bk-form-label" for="editTable">Table</label>
                        <select class="bk-form-select" id="editTable" name="table">
                            <option selected>Select table</option>
                            <option>Table 4 · Indoor Area</option>
                            <option>Table 5 · Outdoor Area</option>
                            <option>Table 7 · VIP Room</option>
                        </select>
                    </div>
                    <div class="bk-form-group bk-full">
                        <label class="bk-form-label" for="editMessage">Special Message</label>
                        <textarea class="bk-form-textarea" id="editMessage" name="message"></textarea>
                    </div>
                </div>
            </form>
        </div>

        <div class="bk-success-msg" id="saveMsg" role="status" aria-live="polite">✓ Booking details updated successfully.</div>

        <div class="bk-modal-foot">
            <button class="bk-modal-btn" data-close="editModal" type="button">Cancel</button>
            <button class="bk-modal-btn bk-primary" id="saveEdit" type="button">Save Changes</button>
        </div>
    </div>
</div>

</main>


<?php include_once __DIR__ . '/../includes/cart_drawer.php'; ?>
<?php include_once __DIR__ . '/../footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4" crossorigin="anonymous"></script>
<script src="<?php echo asset('js/script.js'); ?>"></script>
<script src="<?php echo asset('js/toast_notifications.js'); ?>"></script>
<script src="<?php echo asset('js/clientscript.js'); ?>" defer></script>
<script>
  document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (toggle) {
    if (window.bootstrap) bootstrap.Dropdown.getOrCreateInstance(toggle);
  });
</script>
</body>
</html>
