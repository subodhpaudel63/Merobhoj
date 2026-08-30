<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/process_no_shows.php';

// Process no-shows before loading
processNoShows($conn);

$tz = new DateTimeZone(RESTAURANT_TIMEZONE);
$serverNow = (new DateTime('now', $tz))->format('Y-m-d H:i:s');

// ── Admin Create Booking POST Handler ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_booking') {
    header('Content-Type: application/json');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $people = (int)($_POST['people'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $table_id = (int)($_POST['table_id'] ?? 0);
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($phone) || empty($date) || empty($start_time) || empty($end_time) || $people <= 0 || $table_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }
    
    // Check overlap
    $stmt = $conn->prepare("
        SELECT id FROM bookings 
        WHERE table_id = ? AND booking_date = ? 
          AND status IN ('Pending', 'Confirmed', 'Checked-in') 
          AND start_time < ? 
          AND end_time > ?
    ");
    $start_time_db = $start_time . ':00';
    $end_time_db = $end_time . ':00';
    $stmt->bind_param("isss", $table_id, $date, $end_time_db, $start_time_db);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'The selected table is already booked during this time interval.']);
        $stmt->close();
        exit;
    }
    $stmt->close();
    
    // Compute grace_end_at
    $bookingDT = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $start_time_db, $tz);
    $graceEndAt = null;
    if ($bookingDT) {
        $bookingDT->modify('+' . NO_SHOW_GRACE_MINUTES . ' minutes');
        $graceEndAt = $bookingDT->format('Y-m-d H:i:s');
    }
    
    // Insert booking
    $stmt = $conn->prepare("
        INSERT INTO bookings (name, email, phone, table_id, booking_date, booking_time, start_time, end_time, people, message, status, grace_end_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Confirmed', ?)
    ");
    $stmt->bind_param("sssissssiss", $name, $email, $phone, $table_id, $date, $start_time_db, $start_time_db, $end_time_db, $people, $message, $graceEndAt);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Booking created successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create booking: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// ── Fetch Tables List ────────────────────────────────────────────────────────
$tables = [];
$res_t = $conn->query("SELECT * FROM restaurant_tables ORDER BY capacity ASC, id ASC");
if ($res_t) {
    while ($row = $res_t->fetch_assoc()) {
        $tables[] = $row;
    }
}

// ── Fetch Bookings List ──────────────────────────────────────────────────────
$bookings = [];
$res_b = $conn->query("
    SELECT b.*, t.table_name, t.capacity 
    FROM bookings b 
    LEFT JOIN restaurant_tables t ON b.table_id = t.id 
    ORDER BY b.booking_date DESC, b.booking_time ASC
");
if ($res_b) {
    while ($row = $res_b->fetch_assoc()) {
        $bookings[] = $row;
    }
}

// Format bookings for JavaScript
$bookings_json = [];
foreach ($bookings as $b) {
    $dateObj = DateTime::createFromFormat('Y-m-d', $b['booking_date'], $tz);
    $formattedDate = $dateObj ? $dateObj->format('M j, Y') : $b['booking_date'];
    $dayOfWeek = $dateObj ? $dateObj->format('l') : '';
    
    $startTimeStr = $b['start_time'] ?? $b['booking_time'] ?? '00:00:00';
    $endTimeStr = $b['end_time'] ?? date('H:i:s', strtotime($startTimeStr) + 7200);
    
    $startTimeObj = DateTime::createFromFormat('H:i:s', $startTimeStr, $tz);
    $formattedStartTime = $startTimeObj ? $startTimeObj->format('h:i A') : date('h:i A', strtotime($startTimeStr));
    
    $endTimeObj = DateTime::createFromFormat('H:i:s', $endTimeStr, $tz);
    $formattedEndTime = $endTimeObj ? $endTimeObj->format('h:i A') : date('h:i A', strtotime($endTimeStr));
    
    $bookings_json[] = [
        'id' => (int)$b['id'],
        'name' => $b['name'],
        'email' => $b['email'],
        'phone' => $b['phone'],
        'booking_date' => $b['booking_date'],
        'formatted_date' => $formattedDate,
        'day_of_week' => $dayOfWeek,
        'start_time' => substr($startTimeStr, 0, 5),
        'formatted_start_time' => $formattedStartTime,
        'end_time' => substr($endTimeStr, 0, 5),
        'formatted_end_time' => $formattedEndTime,
        'table_id' => (int)$b['table_id'],
        'table_name' => $b['table_name'] ?? 'Table ' . $b['table_id'],
        'capacity' => (int)($b['capacity'] ?? 0),
        'people' => (int)$b['people'],
        'message' => $b['message'] ?? '',
        'status' => $b['status'],
        'grace_end_at' => $b['grace_end_at']
    ];
}

// ── Calculate Past 7 Days Sparkline Points ───────────────────────────────────
$spark_points = [];
$total_spark = [];
$pending_spark = [];
$active_spark = [];
$avail_spark = [];

for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    
    // Total
    $q1 = $conn->query("SELECT COUNT(*) FROM bookings WHERE booking_date = '$d'");
    $total_spark[] = $q1 ? (int)$q1->fetch_row()[0] : 0;
    
    // Pending
    $q2 = $conn->query("SELECT COUNT(*) FROM bookings WHERE booking_date = '$d' AND status = 'Pending'");
    $pending_spark[] = $q2 ? (int)$q2->fetch_row()[0] : 0;
    
    // Today's Active
    $q3 = $conn->query("SELECT COUNT(*) FROM bookings WHERE booking_date = '$d' AND status IN ('Confirmed', 'Checked-in')");
    $active_spark[] = $q3 ? (int)$q3->fetch_row()[0] : 0;
    
    // Available
    $q4 = $conn->query("SELECT COUNT(DISTINCT table_id) FROM bookings WHERE booking_date = '$d' AND status IN ('Pending', 'Confirmed', 'Checked-in')");
    $booked_cnt = $q4 ? (int)$q4->fetch_row()[0] : 0;
    $avail_spark[] = max(0, count($tables) - $booked_cnt);
}

function generateSparklinePath(array $data): string {
    $max = max(1, max($data));
    $width = 90;
    $height = 40;
    $step = $width / 6;
    $pts = [];
    foreach ($data as $idx => $val) {
        $x = $idx * $step;
        $y = $height - ($val / $max * ($height - 10)) - 5;
        $pts[] = "$x,$y";
    }
    return "M " . implode(" L ", $pts);
}

$total_path = generateSparklinePath($total_spark);
$pending_path = generateSparklinePath($pending_spark);
$active_path = generateSparklinePath($active_spark);
$avail_path = generateSparklinePath($avail_spark);

if (isset($_GET['ajax']) && $_GET['ajax'] === 'bookings') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'bookings' => $bookings_json,
        'tables' => $tables,
        'timestamp' => time(),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Table Bookings - Mero Bhoj</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  <link rel="stylesheet" href="../assets/css/admin_bookings.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin_bookings.css') ?>">
</head>
<body class="admin-page">
   <div class="container">
      <!-- Left Sidebar Nav Bar -->
      <?php include_once __DIR__ . '/sidebar.php'; ?>

      <!-- Main Content Area -->
      <main class="admin-page-main">
          <div class="admin-topbar" aria-label="Admin toolbar">
              <button type="button" id="menu_bar" class="admin-menu-button" aria-label="Open navigation">
                  <span class="material-symbols-sharp">menu</span>
              </button>
              <div class="admin-topbar-actions">
                  <div class="theme-toggler" aria-label="Change color theme">
                      <span class="material-symbols-sharp active">light_mode</span>
                      <span class="material-symbols-sharp">dark_mode</span>
                  </div>
                  <div class="admin-profile">
                      <div class="admin-profile-copy">
                          <strong>Subodh Admin</strong>
                          <small>Administrator</small>
                      </div>
                      <div class="profile-photo">
                          <img src="../assets/img/usersprofiles/adminpic.jpg" alt="Admin profile">
                      </div>
                  </div>
              </div>
          </div>

          <!-- Title section -->
          <div class="bookings-title-section">
              <div>
                  <h1>Table Booking Management</h1>
                  <p>Manage reservations, table availability, customer check-ins, and booking schedules.</p>
              </div>
              <button class="btn-new-booking" onclick="openNewBookingModal()">
                  <span class="material-symbols-sharp" style="font-size: 1.25rem;">add</span>
                  <span>New Booking</span>
              </button>
          </div>

          <!-- Insight Cards -->
          <div class="bookings-stats-grid">
              <!-- Total Bookings -->
              <div class="bookings-stat-card">
                  <div class="bookings-stat-info">
                      <div class="bookings-stat-icon-wrapper icon-total">
                          <span class="material-symbols-sharp">calendar_today</span>
                      </div>
                      <span class="bookings-stat-label">Total Bookings</span>
                      <span class="bookings-stat-value" id="stat-total-count">0</span>
                      <span class="bookings-stat-desc">All time reservations</span>
                  </div>
                  <svg class="bookings-stat-sparkline" viewBox="0 0 90 40">
                      <path d="<?= $total_path ?>" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
              </div>

              <!-- Pending Bookings -->
              <div class="bookings-stat-card">
                  <div class="bookings-stat-info">
                      <div class="bookings-stat-icon-wrapper icon-pending">
                          <span class="material-symbols-sharp">schedule</span>
                      </div>
                      <span class="bookings-stat-label">Pending Bookings</span>
                      <span class="bookings-stat-value" id="stat-pending-count">0</span>
                      <span class="bookings-stat-desc">Awaiting confirmation</span>
                  </div>
                  <svg class="bookings-stat-sparkline" viewBox="0 0 90 40">
                      <path d="<?= $pending_path ?>" fill="none" stroke="#f97316" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
              </div>

              <!-- Active Bookings -->
              <div class="bookings-stat-card">
                  <div class="bookings-stat-info">
                      <div class="bookings-stat-icon-wrapper icon-active">
                          <span class="material-symbols-sharp">check_circle</span>
                      </div>
                      <span class="bookings-stat-label">Today's Active Bookings</span>
                      <span class="bookings-stat-value" id="stat-active-count">0</span>
                      <span class="bookings-stat-desc">Check-ins & upcoming</span>
                  </div>
                  <svg class="bookings-stat-sparkline" viewBox="0 0 90 40">
                      <path d="<?= $active_path ?>" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
              </div>

              <!-- Available Tables -->
              <div class="bookings-stat-card">
                  <div class="bookings-stat-info">
                      <div class="bookings-stat-icon-wrapper icon-available">
                          <span class="material-symbols-sharp">chair</span>
                      </div>
                      <span class="bookings-stat-label">Available Tables</span>
                      <span class="bookings-stat-value" id="stat-available-count">0</span>
                      <span class="bookings-stat-desc" id="stat-available-desc">For selected time slot</span>
                  </div>
                  <svg class="bookings-stat-sparkline" viewBox="0 0 90 40">
                      <path d="<?= $avail_path ?>" fill="none" stroke="#9333ea" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                  </svg>
              </div>
          </div>

          <!-- Filters Row -->
          <div class="bookings-filters-container">
              <div class="bookings-search-wrapper">
                  <span class="material-symbols-sharp bookings-search-icon" style="font-size: 1.2rem;">search</span>
                  <input type="text" class="bookings-search-input" id="filter-search" placeholder="Search by name, phone or booking ID...">
              </div>
              <div>
                  <input type="date" class="bookings-filter-date" id="filter-date">
              </div>
              <div>
                  <select class="bookings-filter-select" id="filter-status">
                      <option value="">All Status</option>
                      <option value="Pending">Pending</option>
                      <option value="Confirmed">Confirmed</option>
                      <option value="Checked-in">Checked-in</option>
                      <option value="Completed">Completed</option>
                      <option value="Cancelled">Cancelled</option>
                      <option value="No-show">No-show</option>
                  </select>
              </div>
              <div>
                  <select class="bookings-filter-select" id="filter-table">
                      <option value="">All Tables</option>
                      <?php foreach ($tables as $t): ?>
                          <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['table_name']) ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>
              <div>
                  <button class="btn-clear-filters" onclick="clearAllFilters()">
                      <span class="material-symbols-sharp" style="font-size: 1.1rem;">close</span>
                      <span>Clear Filters</span>
                  </button>
              </div>
          </div>

          <!-- Section header & Toggles -->
          <div class="bookings-section-header">
              <div class="bookings-section-title">
                  <span>All Bookings</span>
                  <span class="bookings-section-badge" id="bookings-count-badge">0</span>
              </div>
              <div class="bookings-view-switchers">
                  <button class="btn-view-switch active" id="btn-view-list" onclick="switchView('list')">
                      <span class="material-symbols-sharp" style="font-size: 1.15rem;">format_list_bulleted</span>
                      <span>List View</span>
                  </button>
                  <button class="btn-view-switch" id="btn-view-calendar" onclick="switchView('calendar')">
                      <span class="material-symbols-sharp" style="font-size: 1.15rem;">calendar_month</span>
                      <span>Calendar View</span>
                  </button>
              </div>
          </div>

          <!-- Bookings Table container -->
          <div id="bookings-list-view" class="bookings-table-card">
              <table class="bookings-data-table">
                  <thead>
                      <tr>
                          <th>Booking ID</th>
                          <th>Customer</th>
                          <th>Booking Date</th>
                          <th>Start Time</th>
                          <th>End Time</th>
                          <th>Table</th>
                          <th>Guests</th>
                          <th>Status</th>
                          <th>Grace Timer</th>
                          <th style="text-align: right; padding-right: 2rem;">Actions</th>
                      </tr>
                  </thead>
                  <tbody id="bookings-table-body">
                      <!-- Rendered by JS -->
                  </tbody>
              </table>
              
              <!-- Pagination controls -->
              <div class="bookings-pagination-bar">
                  <div class="pagination-info" id="pagination-text">
                      Showing 1 to 5 of 128 bookings
                  </div>
                  <div class="pagination-controls">
                      <button class="btn-pagination" id="btn-page-prev" onclick="prevPage()">&lt;</button>
                      <div style="display: flex; gap: 0.25rem;" id="pagination-pages">
                          <!-- Page buttons by JS -->
                      </div>
                      <button class="btn-pagination" id="btn-page-next" onclick="nextPage()">&gt;</button>
                      
                      <select class="pagination-limit-select" id="pagination-limit" onchange="changeLimit()" style="margin-left: 1rem;">
                          <option value="5">5 / page</option>
                          <option value="10">10 / page</option>
                          <option value="25">25 / page</option>
                          <option value="50">50 / page</option>
                      </select>
                  </div>
              </div>
          </div>

          <!-- Calendar View container -->
          <div id="bookings-calendar-view" class="calendar-view-container" style="display: none;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                  <h3 id="calendar-month-year" style="font-size: 1.15rem; font-weight: 700; margin: 0; color: #0f172a;">August 2026</h3>
                  <div style="display: flex; gap: 0.5rem;">
                      <button class="btn-pagination" onclick="navigateCalendar(-1)">&lt;</button>
                      <button class="btn-pagination" onclick="navigateCalendar(1)">&gt;</button>
                  </div>
              </div>
              <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; text-align: center; font-weight: 600; font-size: 0.82rem; color: #64748b; text-transform: uppercase; margin-bottom: 8px;">
                  <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
              </div>
              <div id="calendar-days-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; min-height: 400px;">
                  <!-- Rendered by JS -->
              </div>
          </div>

          <!-- Table Availability Overview -->
          <div class="availability-overview-card">
              <div class="availability-header-row">
                  <div class="availability-title-group">
                      <h2>Table Availability Overview</h2>
                      <p id="availability-subtitle">Aug 21, 2026 (Thursday)</p>
                  </div>
                  <div style="display: flex; align-items: center; gap: 2rem;">
                      <div class="availability-legend">
                          <div class="legend-item">
                              <span class="legend-dot available"></span>
                              <span>Available</span>
                          </div>
                          <div class="legend-item">
                              <span class="legend-dot booked"></span>
                              <span>Booked</span>
                          </div>
                          <div class="legend-item">
                              <span class="legend-dot pending"></span>
                              <span>Pending</span>
                          </div>
                      </div>
                      
                      <!-- Hour carousel -->
                      <div class="time-carousel-nav">
                          <button class="btn-carousel-arrow" onclick="shiftHours(-1)">&lt;</button>
                          <div style="display: flex; gap: 0.25rem;" id="carousel-hours-container">
                              <!-- Hourly cells rendered by JS -->
                          </div>
                          <button class="btn-carousel-arrow" onclick="shiftHours(1)">&gt;</button>
                      </div>
                  </div>
              </div>

              <!-- Grid -->
              <div class="availability-grid-container">
                  <table class="availability-table">
                      <thead>
                          <tr id="availability-table-header">
                              <th style="width: 20%;">Tables</th>
                              <!-- Hours columns header -->
                          </tr>
                      </thead>
                      <tbody id="availability-table-body">
                          <!-- Rows rendered by JS -->
                      </tbody>
                  </table>
              </div>
          </div>
      </main>

   </div>

   <!-- MODAL: View Details -->
   <div class="bookings-modal-backdrop" id="viewDetailsModal" style="display: none;">
       <div class="bookings-modal-container">
           <div class="bookings-modal-header">
               <h3 class="bookings-modal-title" id="view-modal-title">Booking Details</h3>
               <button class="btn-close-modal" onclick="closeDetailsModal()">
                   <span class="material-symbols-sharp">close</span>
               </button>
           </div>
           <div class="bookings-modal-body">
               <table class="details-table">
                   <tr>
                       <td class="details-label">Booking ID</td>
                       <td class="details-val" id="view-details-id">-</td>
                   </tr>
                   <tr>
                       <td class="details-label">Name</td>
                       <td class="details-val" id="view-details-name">-</td>
                   </tr>
                   <tr>
                       <td class="details-label">Email</td>
                       <td class="details-val" id="view-details-email">-</td>
                   </tr>
                   <tr>
                       <td class="details-label">Phone</td>
                       <td class="details-val" id="view-details-phone">-</td>
                   </tr>
                   <tr>
                       <td class="details-label">Date & Time</td>
                       <td class="details-val" id="view-details-datetime">-</td>
                   </tr>
                   <tr>
                       <td class="details-label">Duration</td>
                       <td class="details-val" id="view-details-duration">-</td>
                   </tr>
                   <tr>
                       <td class="details-label">Table Selected</td>
                       <td class="details-val" id="view-details-table">-</td>
                   </tr>
                   <tr>
                       <td class="details-label">Number of Guests</td>
                       <td class="details-val" id="view-details-guests">-</td>
                   </tr>
                   <tr>
                       <td class="details-label">Status</td>
                       <td class="details-val" id="view-details-status">-</td>
                   </tr>
                   <tr>
                       <td class="details-label">Special Notes</td>
                       <td class="details-val" id="view-details-notes" style="white-space: pre-wrap;">-</td>
                   </tr>
               </table>
           </div>
           <div class="bookings-modal-footer">
               <div style="display: flex; gap: 0.5rem; width: 100%; justify-content: space-between;" id="view-modal-actions-container">
                   <!-- Action buttons injected dynamically -->
               </div>
           </div>
       </div>
   </div>

   <!-- MODAL: Add New Booking -->
   <div class="bookings-modal-backdrop" id="newBookingModal" style="display: none;">
       <div class="bookings-modal-container">
           <div class="bookings-modal-header">
               <h3 class="bookings-modal-title">Create New Booking</h3>
               <button class="btn-close-modal" onclick="closeNewBookingModal()">
                   <span class="material-symbols-sharp">close</span>
               </button>
           </div>
           <form id="new-booking-form" onsubmit="handleCreateBooking(event)">
               <div class="bookings-modal-body">
                   <div class="modal-form-group">
                       <label class="modal-form-label">Customer Name</label>
                       <input type="text" class="modal-form-input" name="name" required placeholder="Subodh Gurung">
                   </div>
                   <div class="modal-form-row">
                       <div class="modal-form-group">
                           <label class="modal-form-label">Email</label>
                           <input type="email" class="modal-form-input" name="email" required placeholder="subodh@example.com">
                       </div>
                       <div class="modal-form-group">
                           <label class="modal-form-label">Phone</label>
                           <input type="text" class="modal-form-input" name="phone" required placeholder="98XXXXXXXX">
                       </div>
                   </div>
                   <div class="modal-form-row">
                       <div class="modal-form-group">
                           <label class="modal-form-label">Booking Date</label>
                           <input type="date" class="modal-form-input" name="date" required id="new-booking-date">
                       </div>
                       <div class="modal-form-group">
                           <label class="modal-form-label">Number of Guests</label>
                           <input type="number" class="modal-form-input" name="people" required min="1" max="8" value="2">
                       </div>
                   </div>
                   <div class="modal-form-row">
                       <div class="modal-form-group">
                           <label class="modal-form-label">Start Time</label>
                           <input type="time" class="modal-form-input" name="start_time" required id="new-booking-start">
                       </div>
                       <div class="modal-form-group">
                           <label class="modal-form-label">End Time</label>
                           <input type="time" class="modal-form-input" name="end_time" required id="new-booking-end">
                       </div>
                   </div>
                   <div class="modal-form-group">
                       <label class="modal-form-label">Select Table</label>
                       <select class="modal-form-select" name="table_id" id="new-booking-table-select" required>
                           <option value="">Select a Table</option>
                           <?php foreach ($tables as $t): ?>
                               <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['table_name']) ?> - Cap: <?= $t['capacity'] ?></option>
                           <?php endforeach; ?>
                       </select>
                   </div>
                   <div class="modal-form-group">
                       <label class="modal-form-label">Special Notes / Message</label>
                       <textarea class="modal-form-textarea" name="message" rows="2" placeholder="Any special table request..."></textarea>
                   </div>
               </div>
               <div class="bookings-modal-footer">
                   <button type="button" class="btn-modal-cancel" onclick="closeNewBookingModal()">Cancel</button>
                   <button type="submit" class="btn-modal-submit">Create Booking</button>
               </div>
           </form>
       </div>
   </div>

   <script>
   // Global variables from PHP
   const serverNowStr = "<?= $serverNow ?>";
   const clientTimeOffset = new Date(serverNowStr.replace(' ', 'T')).getTime() - new Date().getTime();
   
   const bookings = <?= json_encode($bookings_json) ?>;
   const tables = <?= json_encode($tables) ?>;
   
   // App State
   let currentView = 'list';
   let filteredBookings = [...bookings];
   let activeDate = "<?= date('Y-m-d') ?>";
   let activeStatus = '';
   let activeTable = '';
   let activeSearch = '';
   
   // Pagination State
   let currentPage = 1;
   let limitPerPage = 5;
   
   // Availability Hour Carousel State
   const hoursList = [
       { id: 17, label: '5 PM', val: '17:00' },
       { id: 18, label: '6 PM', val: '18:00' },
       { id: 19, label: '7 PM', val: '19:00' },
       { id: 20, label: '8 PM', val: '20:00' },
       { id: 21, label: '9 PM', val: '21:00' },
       { id: 22, label: '10 PM', val: '22:00' }
   ];
   let activeHourId = 19; // 7 PM active by default

   // Initialize Inputs
   document.addEventListener('DOMContentLoaded', () => {
       document.getElementById('filter-date').value = activeDate;
       
       // Setup filter listeners
       document.getElementById('filter-search').addEventListener('input', (e) => {
           activeSearch = e.target.value;
           applyBookingFilters();
       });
       document.getElementById('filter-date').addEventListener('change', (e) => {
           activeDate = e.target.value;
           applyBookingFilters();
       });
       document.getElementById('filter-status').addEventListener('change', (e) => {
           activeStatus = e.target.value;
           applyBookingFilters();
       });
       document.getElementById('filter-table').addEventListener('change', (e) => {
           activeTable = e.target.value;
           applyBookingFilters();
       });
       
       // Modal dates validation
       document.getElementById('new-booking-date').setAttribute('min', new Date().toISOString().split('T')[0]);
       
       // Auto load dropdown update
       const startIn = document.getElementById('new-booking-start');
       const endIn = document.getElementById('new-booking-end');
       const dateIn = document.getElementById('new-booking-date');
       const newTableSelect = document.getElementById('new-booking-table-select');
       
       function fetchTablesForForm() {
           const date = dateIn.value;
           const start = startIn.value;
           const end = endIn.value;
           if (date && start && end) {
               newTableSelect.innerHTML = '<option value="">Loading available tables...</option>';
               const fd = new FormData();
               fd.append('date', date);
               fd.append('start_time', start);
               fd.append('end_time', end);
               fd.append('people', '1'); // general availability
               
               fetch('../includes/get_available_tables.php', {
                   method: 'POST',
                   body: fd
               })
               .then(r => r.json())
               .then(data => {
                   newTableSelect.innerHTML = '<option value="">Select a Table</option>';
                   if (data.success && data.tables.length > 0) {
                       data.tables.forEach(t => {
                           newTableSelect.innerHTML += `<option value="${t.id}">${t.name} - Cap: ${t.capacity}</option>`;
                       });
                   } else {
                       newTableSelect.innerHTML = '<option value="">No tables available for this range</option>';
                   }
               });
           }
       }
       
       startIn.addEventListener('change', fetchTablesForForm);
       endIn.addEventListener('change', fetchTablesForForm);
       dateIn.addEventListener('change', fetchTablesForForm);
       
       // Initial Render
       applyBookingFilters();
       renderAvailability();
       
       // Grace timers
       setInterval(updateGraceTimers, 1000);
       setInterval(refreshBookingsRealtime, 15000);
        
       // Window click to close dropdowns
       window.addEventListener('click', (e) => {
           if (!e.target.closest('.dots-menu-container')) {
               document.querySelectorAll('.dots-dropdown-menu').forEach(m => m.classList.remove('show'));
           }
       });
   });

   // getServerTime
   function getServerTime() {
       return new Date(new Date().getTime() + clientTimeOffset);
   }

   // switchView
   function switchView(view) {
       currentView = view;
       document.getElementById('btn-view-list').classList.toggle('active', view === 'list');
       document.getElementById('btn-view-calendar').classList.toggle('active', view === 'calendar');
       
       document.getElementById('bookings-list-view').style.display = view === 'list' ? 'block' : 'none';
       document.getElementById('bookings-calendar-view').style.display = view === 'calendar' ? 'block' : 'none';
       
       if (view === 'calendar') {
           renderCalendar();
       }
   }

   // clearAllFilters
   function clearAllFilters() {
       activeSearch = '';
       activeDate = '';
       activeStatus = '';
       activeTable = '';
       
       document.getElementById('filter-search').value = '';
       document.getElementById('filter-date').value = '';
       document.getElementById('filter-status').value = '';
       document.getElementById('filter-table').value = '';
       
       applyBookingFilters();
   }

   // applyBookingFilters
   function applyBookingFilters() {
       filteredBookings = bookings.filter(b => {
           // Search
           if (activeSearch) {
               const query = activeSearch.toLowerCase();
               const matchesSearch = 
                   b.name.toLowerCase().includes(query) || 
                   b.phone.includes(query) || 
                   b.email.toLowerCase().includes(query) ||
                   ('#BK-' + b.id.toString().padStart(4, '0')).toLowerCase().includes(query) ||
                   b.id.toString().includes(query);
               if (!matchesSearch) return false;
           }
           // Date
           if (activeDate && b.booking_date !== activeDate) {
               return false;
           }
           // Status
           if (activeStatus && b.status !== activeStatus) {
               return false;
           }
           // Table
           if (activeTable && b.table_id !== parseInt(activeTable)) {
               return false;
           }
           return true;
       });
       
       currentPage = 1;
       renderList();
       updateInsights();
       
       if (currentView === 'calendar') {
           renderCalendar();
       }
   }

   function formatAvailabilityNumber(value) {
       if (value < 0) {
           return '-' + Math.abs(value).toString().padStart(2, '0');
       }
       return value.toString().padStart(2, '0');
   }

   // updateInsights
   function updateInsights() {
       // Total Bookings
       document.getElementById('stat-total-count').textContent = bookings.length;
        
       // Pending Bookings
       const pending = bookings.filter(b => b.status === 'Pending').length;
       document.getElementById('stat-pending-count').textContent = pending;
        
       // Today's Active Bookings
       const todayStr = new Date().toISOString().split('T')[0];
       const activeToday = bookings.filter(b => b.booking_date === todayStr && (b.status === 'Confirmed' || b.status === 'Checked-in')).length;
       document.getElementById('stat-active-count').textContent = activeToday;
        
       // Available Tables for the Selected Carousel Hour & Selected Date
       const actDate = activeDate || todayStr;
       const activeHourStr = hoursList.find(h => h.id === activeHourId).val;
        
       // Count occupied tables at this hour
       const occupiedTables = new Set();
       bookings.forEach(b => {
           if (b.booking_date === actDate && ['Pending', 'Confirmed', 'Checked-in'].includes(b.status)) {
               if (b.start_time <= activeHourStr && b.end_time > activeHourStr) {
                   occupiedTables.add(b.table_id);
               }
           }
       });
        
       const availableCount = tables.length - occupiedTables.size;
       const availableNode = document.getElementById('stat-available-count');
       availableNode.textContent = formatAvailabilityNumber(availableCount);
       availableNode.style.color = availableCount < 0 ? '#dc2626' : '#0f172a';
        
       const hourLabel = hoursList.find(h => h.id === activeHourId).label;
       const dateLabel = actDate ? new Date(actDate).toLocaleDateString([], { month: 'short', day: 'numeric' }) : 'Today';
       document.getElementById('stat-available-desc').textContent = `${hourLabel} slot on ${dateLabel}`;
   }

   // renderList
   function renderList() {
       const badge = document.getElementById('bookings-count-badge');
       badge.textContent = filteredBookings.length;
       
       const tbody = document.getElementById('bookings-table-body');
       tbody.innerHTML = '';
       
       if (filteredBookings.length === 0) {
           tbody.innerHTML = `<tr><td colspan="10" style="text-align:center;color:#64748b;padding:3rem 0;">No bookings match the selected filters.</td></tr>`;
           document.getElementById('pagination-text').textContent = 'Showing 0 to 0 of 0 bookings';
           renderPaginationButtons(1, 1);
           return;
       }
       
       // Paginate
       const totalItems = filteredBookings.length;
       const totalPages = Math.ceil(totalItems / limitPerPage);
       if (currentPage > totalPages) currentPage = totalPages;
       if (currentPage < 1) currentPage = 1;
       
       const startIdx = (currentPage - 1) * limitPerPage;
       const endIdx = Math.min(startIdx + limitPerPage, totalItems);
       const paginatedList = filteredBookings.slice(startIdx, endIdx);
       
       document.getElementById('pagination-text').textContent = `Showing ${startIdx + 1} to ${endIdx} of ${totalItems} bookings`;
       
       const colors = ['blue', 'green', 'purple', 'yellow', 'orange'];
       
       paginatedList.forEach(b => {
           const bookingIdFmt = '#BK-' + b.id.toString().padStart(4, '0');
           const initial = b.name.charAt(0);
           const colorClass = 'customer-avatar-' + colors[b.id % colors.length];
           
           // Status badge class
           const statusClass = b.status.toLowerCase();
           
           tbody.innerHTML += `
               <tr id="booking-row-${b.id}">
                   <td><span class="booking-id-text">${bookingIdFmt}</span></td>
                   <td>
                       <div class="customer-cell">
                           <div class="customer-avatar ${colorClass}">${initial}</div>
                           <div class="customer-info-box">
                               <span class="customer-name">${escapeHtml(b.name)}</span>
                               <span class="customer-phone">${escapeHtml(b.phone)}</span>
                           </div>
                       </div>
                   </td>
                   <td>
                       <div class="booking-date-cell">${b.formatted_date}</div>
                       <div class="booking-day-text">${b.day_of_week}</div>
                   </td>
                   <td style="font-weight: 500;">${b.formatted_start_time}</td>
                   <td style="font-weight: 500;">${b.formatted_end_time}</td>
                   <td>
                       <div class="table-badge">
                           T${b.table_id}
                           <span>${escapeHtml(b.table_name)}</span>
                       </div>
                   </td>
                   <td>
                       <div class="guest-count-cell">
                           <span class="material-symbols-sharp" style="font-size:1.1rem;color:#64748b;">person</span>
                           <span>${b.people}</span>
                       </div>
                   </td>
                   <td>
                       <span class="status-pill ${statusClass}">${b.status}</span>
                   </td>
                   <td>
                       <div class="grace-timer-cell" id="timer-badge-${b.id}" data-status="${b.status}" data-grace-end="${b.grace_end_at || ''}" data-start-time="${b.booking_date}T${b.start_time}:00">
                           -
                       </div>
                   </td>
                   <td style="text-align: right; padding-right: 2rem;">
                       <div class="action-buttons-cell" style="justify-content: flex-end;">
                           <button class="btn-icon-action" onclick="openDetailsModal(${b.id})" title="View Details">
                               <span class="material-symbols-sharp" style="font-size: 1.15rem;">visibility</span>
                           </button>
                           
                           <div class="dots-menu-container">
                               <button class="btn-icon-action" onclick="toggleDotsMenu(${b.id})" title="More Actions">
                                   <span class="material-symbols-sharp" style="font-size: 1.15rem;">more_vert</span>
                               </button>
                               <div class="dots-dropdown-menu" id="dots-menu-${b.id}">
                                   ${renderDropdownActions(b)}
                               </div>
                           </div>
                       </div>
                   </td>
               </tr>
           `;
       });
       
       renderPaginationButtons(currentPage, totalPages);
       updateGraceTimers();
   }

   // renderDropdownActions
   function renderDropdownActions(booking) {
       let actions = '';
       const status = booking.status;
       const id = booking.id;
       
       if (status === 'Pending') {
           actions += `<button class="dots-menu-item" onclick="updateStatus(${id}, 'Confirmed')"><span class="material-symbols-sharp" style="font-size:1.1rem;color:#16a34a;">check</span>Confirm</button>`;
           actions += `<button class="dots-menu-item" onclick="updateStatus(${id}, 'Cancelled')"><span class="material-symbols-sharp" style="font-size:1.1rem;color:#dc2626;">close</span>Cancel</button>`;
       } else if (status === 'Confirmed') {
           actions += `<button class="dots-menu-item" onclick="updateStatus(${id}, 'Checked-in')"><span class="material-symbols-sharp" style="font-size:1.1rem;color:#2563eb;">login</span>Check-in</button>`;
           actions += `<button class="dots-menu-item" onclick="updateStatus(${id}, 'Cancelled')"><span class="material-symbols-sharp" style="font-size:1.1rem;color:#dc2626;">close</span>Cancel</button>`;
       } else if (status === 'Checked-in') {
           actions += `<button class="dots-menu-item" onclick="updateStatus(${id}, 'Completed')"><span class="material-symbols-sharp" style="font-size:1.1rem;color:#16a34a;">done_all</span>Complete</button>`;
       }
       
       actions += `<button class="dots-menu-item danger" onclick="handleDelete(${id})"><span class="material-symbols-sharp" style="font-size:1.1rem;">delete</span>Delete</button>`;
       return actions;
   }

   // toggleDotsMenu
   function toggleDotsMenu(id) {
       event.stopPropagation();
       const menu = document.getElementById(`dots-menu-${id}`);
       const wasOpen = menu.classList.contains('show');
       document.querySelectorAll('.dots-dropdown-menu').forEach(m => m.classList.remove('show'));
       if (!wasOpen) {
           menu.classList.add('show');
       }
   }

   // renderPaginationButtons
   function renderPaginationButtons(activePage, totalPages) {
       document.getElementById('btn-page-prev').disabled = (activePage === 1);
       document.getElementById('btn-page-next').disabled = (activePage === totalPages);
       
       const container = document.getElementById('pagination-pages');
       container.innerHTML = '';
       
       for (let i = 1; i <= totalPages; i++) {
           const isActive = (i === activePage);
           container.innerHTML += `
               <button class="btn-pagination ${isActive ? 'active' : ''}" onclick="gotoPage(${i})">${i}</button>
           `;
       }
   }

   function gotoPage(p) {
       currentPage = p;
       renderList();
   }
   function prevPage() {
       if (currentPage > 1) {
           currentPage--;
           renderList();
       }
   }
   function nextPage() {
       currentPage++;
       renderList();
   }
   function changeLimit() {
       limitPerPage = parseInt(document.getElementById('pagination-limit').value);
       currentPage = 1;
       renderList();
   }

   // updateGraceTimers
   function updateGraceTimers() {
       const now = getServerTime();
       
       document.querySelectorAll('.grace-timer-cell').forEach(container => {
           const status = container.getAttribute('data-status');
           const graceEndStr = container.getAttribute('data-grace-end');
           const startStr = container.getAttribute('data-start-time');
           
           if (status === 'Checked-in') {
               container.innerHTML = `<span class="timer-checkedin">✓ Checked In</span>`;
           } else if (status === 'Completed') {
               container.innerHTML = `<span class="timer-completed">✓ Completed</span>`;
           } else if (status === 'Cancelled') {
               container.innerHTML = `<span style="color:#ef4444;">Cancelled</span>`;
           } else if (status === 'No-show') {
               container.innerHTML = `<span class="timer-noshow">⚠️ No Show</span>`;
           } else if (status === 'Pending') {
               container.innerHTML = `<span style="color:#d97706;">Awaiting Conf.</span>`;
           } else if (status === 'Confirmed' && graceEndStr) {
               const graceEnd = new Date(graceEndStr.replace(' ', 'T'));
               const bookingTime = new Date(startStr);
               
               if (now < bookingTime) {
                   const diff = bookingTime - now;
                   const mins = Math.floor(diff / 60000);
                   if (mins < 60) {
                       container.innerHTML = `<span class="timer-starts">Starts in ${mins} min</span>`;
                   } else {
                       const hrs = Math.ceil(mins / 60);
                       container.innerHTML = `<span class="timer-starts">Starts in ${hrs} hr</span>`;
                   }
               } else if (now >= graceEnd) {
                   container.innerHTML = '<span class="timer-noshow">Expired</span>';
               } else {
                   const diff = graceEnd - now;
                   const mins = Math.floor(diff / 60000);
                   const secs = Math.floor((diff % 60000) / 1000);
                   const timeStr = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                   
                   let styleStr = 'color: #d97706; font-weight:600;';
                   if (mins < 5) styleStr = 'color: #ef4444; font-weight:700;';
                   container.innerHTML = `<span style="${styleStr}">⏳ ${timeStr} left</span>`;
               }
           } else {
               container.innerHTML = `<span class="text-muted">-</span>`;
           }
       });
   }

   // updateStatus
   function updateStatus(id, newStatus) {
       if (!confirm(`Change booking #${id} status to ${newStatus}?`)) return;
       
       fetch('update_booking_status_ajax.php', {
           method: 'POST',
           headers: { 'Content-Type': 'application/json' },
           body: JSON.stringify({ id: id, status: newStatus })
       })
       .then(r => r.json())
       .then(data => {
           if (data.success) {
               // Update client array
               const idx = bookings.findIndex(b => b.id === id);
               if (idx !== -1) {
                   bookings[idx].status = newStatus;
                   if (newStatus === 'Checked-in') bookings[idx].grace_end_at = null;
                   
                   // Reload list
                   applyBookingFilters();
                   renderAvailability();
               }
           } else {
               alert('Error: ' + data.message);
           }
       })
       .catch(e => {
           console.error(e);
           alert('Network error updating status.');
       });
   }

   // handleDelete
   function handleDelete(id) {
       if(!confirm(`Are you sure you want to delete booking #${id}?`)) return;
       fetch('../includes/delete_booking.php', {
           method: 'POST',
           headers: { 'Content-Type': 'application/json' },
           body: JSON.stringify({ id: id })
       })
       .then(r => r.json())
       .then(data => {
           if (data.success) {
               const idx = bookings.findIndex(b => b.id === id);
               if (idx !== -1) {
                   bookings.splice(idx, 1);
                   applyBookingFilters();
                   renderAvailability();
               }
           } else {
               alert('Error deleting: ' + data.message);
           }
       });
   }

   // Modal Details
   function openDetailsModal(id) {
       const b = bookings.find(x => x.id === id);
       if (!b) return;
       
       const bookingIdFmt = '#BK-' + b.id.toString().padStart(4, '0');
       document.getElementById('view-modal-title').textContent = `Booking Details ${bookingIdFmt}`;
       document.getElementById('view-details-id').textContent = bookingIdFmt;
       document.getElementById('view-details-name').textContent = b.name;
       document.getElementById('view-details-email').textContent = b.email;
       document.getElementById('view-details-phone').textContent = b.phone;
       document.getElementById('view-details-datetime').textContent = `${b.formatted_date} (${b.day_of_week}) at ${b.formatted_start_time}`;
       document.getElementById('view-details-duration').textContent = `${b.formatted_start_time} - ${b.formatted_end_time}`;
       document.getElementById('view-details-table').textContent = `${b.table_name} (Capacity: ${b.capacity} Seats)`;
       document.getElementById('view-details-guests').textContent = `${b.people} Guests`;
       document.getElementById('view-details-status').innerHTML = `<span class="status-pill ${b.status.toLowerCase()}">${b.status}</span>`;
       document.getElementById('view-details-notes').textContent = b.message || 'No special requests/notes.';
       
       // Setup actions container inside details footer
       const footerActions = document.getElementById('view-modal-actions-container');
       footerActions.innerHTML = '';
       
       // Left side action buttons
       let actionBtn = '';
       if (b.status === 'Pending') {
           actionBtn += `<button onclick="updateStatus(${b.id}, 'Confirmed'); closeDetailsModal();" class="btn-modal-submit" style="background-color:#16a34a;">Confirm</button>`;
       } else if (b.status === 'Confirmed') {
           actionBtn += `<button onclick="updateStatus(${b.id}, 'Checked-in'); closeDetailsModal();" class="btn-modal-submit" style="background-color:#2563eb;">Check-in</button>`;
       } else if (b.status === 'Checked-in') {
           actionBtn += `<button onclick="updateStatus(${b.id}, 'Completed'); closeDetailsModal();" class="btn-modal-submit" style="background-color:#16a34a;">Complete</button>`;
       }
       
       footerActions.innerHTML = `
           <div>${actionBtn}</div>
           <button class="btn-modal-cancel" onclick="closeDetailsModal()">Close</button>
       `;
       
       document.getElementById('viewDetailsModal').style.display = 'flex';
   }

   function closeDetailsModal() {
       document.getElementById('viewDetailsModal').style.display = 'none';
   }

   function openTableAvailabilityModal(tableId, dateStr, startTime) {
       const endTime = addMinutesToTime(startTime, 60);
       openNewBookingModal();
       document.getElementById('new-booking-date').value = dateStr;
       document.getElementById('new-booking-start').value = startTime;
       document.getElementById('new-booking-end').value = endTime;
       const tableSelect = document.getElementById('new-booking-table-select');
       tableSelect.value = String(tableId);
       setTimeout(() => {
           const event = new Event('change', { bubbles: true });
           document.getElementById('new-booking-start').dispatchEvent(event);
       }, 50);
   }

   function addMinutesToTime(timeValue, minutes) {
       const [hours, mins] = timeValue.split(':').map(Number);
       const date = new Date();
       date.setHours(hours, mins + minutes, 0, 0);
       return date.toTimeString().slice(0, 5);
   }

   // Modal New Booking
   function openNewBookingModal() {
       // set default date to selected filter date, or today
       document.getElementById('new-booking-date').value = activeDate || new Date().toISOString().split('T')[0];
       document.getElementById('newBookingModal').style.display = 'flex';
   }

   function closeNewBookingModal() {
       document.getElementById('newBookingModal').style.display = 'none';
       document.getElementById('new-booking-form').reset();
   }

   function handleCreateBooking(e) {
       e.preventDefault();
       const form = document.getElementById('new-booking-form');
       const fd = new FormData(form);
       fd.append('action', 'create_booking');
       
       fetch('bookings.php', {
           method: 'POST',
           body: fd
       })
       .then(r => r.json())
       .then(data => {
           if (data.success) {
               alert(data.message);
               window.location.reload();
           } else {
               alert(data.message);
           }
       })
       .catch(err => {
           console.error(err);
           alert('Network error creating booking.');
       });
   }

   // ── Calendar View Rendering Logic ─────────────────────────────────────────
   let calMonth = new Date().getMonth(); // 0-11
   let calYear = new Date().getFullYear();
   
   function renderCalendar() {
       const header = document.getElementById('calendar-month-year');
       const grid = document.getElementById('calendar-days-grid');
       
       const tempDate = new Date(calYear, calMonth, 1);
       header.textContent = tempDate.toLocaleDateString([], { month: 'long', year: 'numeric' });
       
       grid.innerHTML = '';
       
       const firstDayIndex = new Date(calYear, calMonth, 1).getDay(); // day of week of 1st
       const lastDayDate = new Date(calYear, calMonth + 1, 0).getDate(); // total days in month
       
       // empty slots for previous month padding
       for (let i = 0; i < firstDayIndex; i++) {
           grid.innerHTML += `<div style="background-color:#f8fafc; border: 1px solid #e2e8f0; border-radius:8px; opacity:0.3;"></div>`;
       }
       
       // days of month
       const todayStr = new Date().toISOString().split('T')[0];
       
       for (let d = 1; d <= lastDayDate; d++) {
           const currMonthStr = (calMonth + 1).toString().padStart(2, '0');
           const currDayStr = d.toString().padStart(2, '0');
           const fullDateStr = `${calYear}-${currMonthStr}-${currDayStr}`;
           
           // filter bookings for this day
           const dayBookings = bookings.filter(b => b.booking_date === fullDateStr);
           
           let bookingIndicators = '';
           if (dayBookings.length > 0) {
               // Limit to 3 dots, show a number if more
               const displayBookings = dayBookings.slice(0, 3);
               displayBookings.forEach(db => {
                   let indicatorColor = '#c2410c'; // default orange
                   if (db.status === 'Checked-in') indicatorColor = '#16a34a';
                   else if (db.status === 'Pending') indicatorColor = '#fb923c';
                   else if (db.status === 'Completed') indicatorColor = '#64748b';
                   else if (db.status === 'Cancelled') indicatorColor = '#ef4444';
                   
                   bookingIndicators += `
                       <div style="background-color:${indicatorColor}; color:white; font-size:10px; padding:2px 4px; border-radius:4px; font-weight:600; text-align:left; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; width:100%; margin-top:2px;" title="${db.name}">
                           ${db.start_time} - ${escapeHtml(db.name)}
                       </div>
                   `;
               });
               if (dayBookings.length > 3) {
                   bookingIndicators += `<div style="font-size:10px; color:#64748b; font-weight:bold; margin-top:2px;">+ ${dayBookings.length - 3} more</div>`;
               }
           }
           
           const isToday = (fullDateStr === todayStr);
           const isSelectedDate = (fullDateStr === activeDate);
           
           let cellStyle = 'background-color: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px; min-height: 80px; cursor: pointer; transition: all 0.2s; display: flex; flex-direction: column;';
           if (isToday) cellStyle += ' border: 2px solid #2563eb; background-color:#eff6ff;';
           else if (isSelectedDate) cellStyle += ' border: 2px solid #c2410c; background-color:#fff7ed;';
           
           grid.innerHTML += `
               <div style="${cellStyle}" onclick="selectDateFromCalendar('${fullDateStr}')">
                   <div style="display:flex; justify-content:space-between; align-items:center;">
                       <span style="font-weight:700; font-size:0.9rem; color:${isToday ? '#2563eb' : '#1e293b'}">${d}</span>
                       ${dayBookings.length > 0 ? `<span style="background-color:#f1f5f9; color:#475569; font-size:10px; font-weight:bold; padding:1px 5px; border-radius:10px;">${dayBookings.length}</span>` : ''}
                   </div>
                   <div style="flex-grow:1; display:flex; flex-direction:column; justify-content:flex-start; margin-top:4px;">
                       ${bookingIndicators}
                   </div>
               </div>
           `;
       }
   }

   function navigateCalendar(dir) {
       calMonth += dir;
       if (calMonth > 11) {
           calMonth = 0;
           calYear++;
       } else if (calMonth < 0) {
           calMonth = 11;
           calYear--;
       }
       renderCalendar();
   }

   function selectDateFromCalendar(dateStr) {
       activeDate = dateStr;
       document.getElementById('filter-date').value = dateStr;
       applyBookingFilters();
       switchView('list');
   }


   // ── Table Availability Overview Rendering Logic ────────────────────────────
   function refreshBookingsRealtime() {
       fetch(`bookings.php?ajax=bookings&_=${Date.now()}`, { credentials: 'same-origin' })
           .then(response => response.json())
           .then(data => {
               if (!data || !data.success || !Array.isArray(data.bookings)) {
                   return;
               }

               bookings.splice(0, bookings.length, ...data.bookings);
               tables.splice(0, tables.length, ...data.tables);

               applyBookingFilters();
               renderAvailability();
               updateInsights();
               if (currentView === 'calendar') {
                   renderCalendar();
               }
           })
           .catch(() => {
               // Do nothing; scheduler will retry
           });
   }

   function renderAvailability() {
       const actDate = activeDate || new Date().toISOString().split('T')[0];
       const dateObj = new Date(actDate);
       const dateLabel = dateObj.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
       const dayOfWeekLabel = dateObj.toLocaleDateString([], { weekday: 'long' });
        
       document.getElementById('availability-subtitle').textContent = `${dateLabel} (${dayOfWeekLabel})`;
        
       // Render Hour Carousel items
       const carouselContainer = document.getElementById('carousel-hours-container');
       carouselContainer.innerHTML = '';
        
       hoursList.forEach(h => {
           const isActive = (h.id === activeHourId);
           carouselContainer.innerHTML += `
               <div class="carousel-time-item ${isActive ? 'active' : ''}" onclick="selectHourSlot(${h.id})">
                   ${h.label}
               </div>
           `;
       });
        
       // Render Table Headers
       const tableHeader = document.getElementById('availability-table-header');
       tableHeader.innerHTML = `<th style="width: 20%; background-color:#f8fafc; color:#475569;">Tables</th>`;
       hoursList.forEach(h => {
           const isColActive = (h.id === activeHourId);
           let styleStr = 'padding: 0.75rem 1rem;';
           if (isColActive) styleStr += ' border-bottom: 2px solid #c2410c; background-color: rgba(194,65,12,0.03); color:#c2410c;';
           tableHeader.innerHTML += `<th style="${styleStr}">${h.label}</th>`;
       });
        
       // Render Grid Body
       const tableBody = document.getElementById('availability-table-body');
       tableBody.innerHTML = '';
        
       tables.forEach(table => {
           let rowHtml = `
               <tr>
                   <td class="availability-table-name">
                       <span class="material-symbols-sharp" style="font-size:1.2rem;color:#64748b;">chair</span>
                       <span>${escapeHtml(table.table_name)}</span>
                   </td>
           `;
            
           hoursList.forEach(h => {
               const hourStr = h.val;
               const isColActive = (h.id === activeHourId);
                
               // Check if there is an overlapping active booking
               let matchBooking = null;
               bookings.forEach(b => {
                   if (b.table_id === table.id && b.booking_date === actDate && ['Pending', 'Confirmed', 'Checked-in'].includes(b.status)) {
                       if (b.start_time <= hourStr && b.end_time > hourStr) {
                           matchBooking = b;
                       }
                   }
               });
                
               let cardClass = 'available';
               let icon = 'check_circle';
               let label = 'Available';
               let onClick = `onclick="openTableAvailabilityModal(${table.id}, '${actDate}', '${hourStr}')"`;
               let titleText = `title="Available table - ${escapeHtml(table.table_name)} at ${h.label}"`;
                
               if (matchBooking) {
                   if (matchBooking.status === 'Pending') {
                       cardClass = 'pending';
                       icon = 'pending';
                       label = 'Pending';
                   } else {
                       cardClass = 'booked';
                       icon = 'person';
                       label = 'Booked';
                   }
                   onClick = `onclick="openDetailsModal(${matchBooking.id})"`;
                   titleText = `title="Booking #${matchBooking.id} - ${escapeHtml(matchBooking.name)}"`;
               }
                
               let activeClass = isColActive ? 'active-col' : '';
                
               rowHtml += `
                   <td>
                       <div class="availability-slot-card ${cardClass} ${activeClass}" 
                            ${onClick} style="cursor:pointer;" ${titleText}>
                           <span class="material-symbols-sharp" style="font-size: 1rem;">${icon}</span>
                           <span>${label}</span>
                       </div>
                   </td>
               `;
           });
            
           rowHtml += `</tr>`;
           tableBody.innerHTML += rowHtml;
       });
   }

   function selectHourSlot(hourId) {
       activeHourId = hourId;
       renderAvailability();
       updateInsights();
   }

   function shiftHours(dir) {
       const currIndex = hoursList.findIndex(h => h.id === activeHourId);
       let nextIndex = currIndex + dir;
       if (nextIndex < 0) nextIndex = hoursList.length - 1;
       if (nextIndex >= hoursList.length) nextIndex = 0;
       
       selectHourSlot(hoursList[nextIndex].id);
   }

   // escapeHtml helper
   function escapeHtml(str) {
       if (!str) return '';
       return str
           .replace(/&/g, "&amp;")
           .replace(/</g, "&lt;")
           .replace(/>/g, "&gt;")
           .replace(/"/g, "&quot;")
           .replace(/'/g, "&#039;");
   }
   </script>
   <script src="../assets/js/adminscript.js"></script>
</body>
</html>
