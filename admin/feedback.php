<?php
// Feedback data logic only — NO HTML OUTPUT.
// This file used to be a full standalone admin page; its HTML/JS was removed.
// It is now included by admin pages (e.g. admin/index.php) which render the
// feedback data using the variables defined below:
//   $feedback, $total_feedback, $average_rating,
//   $positive_feedback, $positive_percent, $latest_feedback
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

// Include guard so this file can be included by multiple pages safely
if (defined('MEROBHOJ_FEEDBACK_DATA_LOADED')) {
    return;
}
define('MEROBHOJ_FEEDBACK_DATA_LOADED', true);

// Fetch feedback from database
$feedback = [];
 
// Try to get feedback data with flexible column mapping
$sql = "SELECT * FROM feedback ORDER BY created_at DESC";
$res = $conn->query($sql);
 
// If that fails, try without ordering
if (!$res) {
    $sql = "SELECT * FROM feedback";
    $res = $conn->query($sql);
}
 
// Process results with flexible field mapping
if ($res) {
    while ($row = $res->fetch_assoc()) {
        // Map fields flexibly based on what's available in the table
        $mapped_row = [
            'id' => $row['feedback_id'] ?? $row['id'] ?? 0,
            'name' => $row['feedback_name'] ?? $row['name'] ?? $row['customer_name'] ?? 'Guest',
            'email' => $row['feedback_email'] ?? $row['email'] ?? $row['customer_email'] ?? 'N/A',
            'rating' => $row['feedback_rating'] ?? $row['rating'] ?? $row['stars'] ?? 0,
            'message' => $row['feedback_message'] ?? $row['message'] ?? $row['comment'] ?? $row['comments'] ?? '',
            'created_at' => $row['created_at'] ?? $row['date_created'] ?? $row['timestamp'] ?? 'N/A'
        ];
        $feedback[] = $mapped_row;
    }
}

// Calculate stats for the insight cards
$total_feedback = count($feedback);
$average_rating = 0;
$positive_feedback = 0;
$latest_feedback = array_slice($feedback, 0, 3); // For the sidebar activity

if ($total_feedback > 0) {
    $sum_rating = 0;
    foreach($feedback as $f) {
        $rating = isset($f['rating']) ? (int)$f['rating'] : 0;
        $sum_rating += $rating;
        if ($rating >= 4) $positive_feedback++;
    }
    $average_rating = round($sum_rating / $total_feedback, 1);
}

$positive_percent = $total_feedback > 0 ? round(($positive_feedback / $total_feedback) * 100) : 0;

// Build the dataset consumed by the feedback dashboard JS (adminscript.js).
// Field names match what the dashboard script expects:
//   review (message), isoDate/date/time (derived from created_at), responded.
$feedback_js = [];
foreach ($feedback as $f) {
    $ts = strtotime((string) $f['created_at']);
    $feedback_js[] = [
        'id'        => (int) $f['id'],
        'name'      => (string) $f['name'],
        'email'     => (string) $f['email'],
        'rating'    => max(1, min(5, (int) $f['rating'])),
        'review'    => (string) $f['message'],
        'isoDate'   => $ts ? date('Y-m-d', $ts) : '',
        'date'      => $ts ? date('M j, Y', $ts) : '',
        'time'      => $ts ? date('h:i A', $ts) : '',
        'responded' => false, // the feedback table has no response column yet
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Feedback</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <script src="https://unpkg.com/lucide@latest"></script>

  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
</head>
<body class="admin-page">
   <?php include_once __DIR__ . '/topbar.php'; ?>
   <div class="container">
      <?php include_once __DIR__ . '/sidebar.php'; ?>

      <main class="admin-page-main page">
  <div class="content">

    <div class="title-row">
      <div>
        <h1>Customer Feedback</h1>
        <p>Monitor customer reviews and improve service quality.</p>
      </div>
      <button class="add-review-btn" id="openReview">
        <i data-lucide="plus"></i>
        Add Review
      </button>
    </div>

    <div class="dashboard-grid">

      <div class="main-column">

        <section class="stats-grid">

          <article class="stat-card">
            <div class="stat-icon orange"><i data-lucide="message-square-text"></i></div>
            <div class="stat-copy">
              <span>Total Reviews</span>
              <strong id="totalReviews">1</strong>
              <small>All time reviews</small>
              <em id="reviewChange">▲ 100% <b>vs last month</b></em>
            </div>
          </article>

          <article class="stat-card">
            <div class="stat-icon orange"><i data-lucide="star"></i></div>
            <div class="stat-copy">
              <span>Average Rating</span>
              <strong id="averageRating">5.0 / 5</strong>
              <div class="stars" id="averageStars"></div>
              <small id="ratingBased">Based on 1 review</small>
            </div>
          </article>

          <article class="stat-card">
            <div class="stat-icon green"><i data-lucide="thumbs-up"></i></div>
            <div class="stat-copy">
              <span>Positive Ratio</span>
              <strong id="positiveRatio">100%</strong>
              <small id="positiveCount">(1 positive)</small>
              <em>▲ 100% <b>vs last month</b></em>
            </div>
          </article>

          <article class="stat-card">
            <div class="stat-icon purple"><i data-lucide="message-circle"></i></div>
            <div class="stat-copy">
              <span>Response Rate</span>
              <strong id="responseRate">0%</strong>
              <small id="responseText">No responses yet</small>
              <em class="neutral">— <b>vs last month</b></em>
            </div>
          </article>

        </section>

        <section class="filters card">

          <div class="search-box">
            <i data-lucide="search"></i>
            <input id="searchInput" type="text" placeholder="Search by name or email...">
            <button class="clear-search" id="clearSearch" title="Clear search" hidden>
              <i data-lucide="x"></i>
            </button>
          </div>

          <select id="ratingFilter" aria-label="Rating">
            <option value="all">All Ratings</option>
            <option value="5">5 Stars</option>
            <option value="4">4 Stars</option>
            <option value="3">3 Stars</option>
            <option value="2">2 Stars</option>
            <option value="1">1 Star</option>
          </select>

          <button class="date-btn" id="dateBtn">
            <i data-lucide="calendar-days"></i>
            <span id="dateBtnText">Select Date Range</span>
            <i data-lucide="chevron-down"></i>
          </button>

          <button class="filter-btn" id="filterBtn">
            <i data-lucide="sliders-horizontal"></i>
            Filter
          </button>

          <button class="reset-btn" id="resetBtn">
            Reset
          </button>

          <section class="date-popover" id="datePopover" hidden>
            <div class="popover-title">
              <strong>Select date range</strong>
              <button id="closeDatePopover"><i data-lucide="x"></i></button>
            </div>
            <div class="date-fields">
              <label>
                From
                <input type="date" id="dateFrom">
              </label>
              <label>
                To
                <input type="date" id="dateTo">
              </label>
            </div>
            <div class="popover-actions">
              <button id="clearDate">Clear</button>
              <button class="apply-btn" id="applyDate">Apply</button>
            </div>
          </section>

        </section>

        <section class="filter-summary" id="filterSummary" hidden>
          <span><i data-lucide="filter"></i><b>Active filters:</b> <span id="summaryText"></span></span>
          <button id="clearAllFilters">Clear all</button>
        </section>

        <section class="history card">
          <div class="section-heading">
            <h2>Review History</h2>
            <span id="resultCount"></span>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Customer</th>
                  <th>Email</th>
                  <th>Rating</th>
                  <th>Review</th>
                  <th>Date</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="reviewTable"></tbody>
            </table>
          </div>

          <div class="empty" id="emptyState" hidden>
            <i data-lucide="message-square-off"></i>
            <strong>No reviews found</strong>
            <span>Try changing your search or filters.</span>
          </div>

          <div class="history-footer">
            <span id="showingText">Showing 1 to 1 of 1 review</span>
            <div class="pagination">
              <button id="prevPage" disabled><i data-lucide="chevron-left"></i></button>
              <button class="active">1</button>
              <button id="nextPage" disabled><i data-lucide="chevron-right"></i></button>
            </div>
          </div>
        </section>

        <section class="tip">
          <div class="info"><i data-lucide="info"></i></div>
          <span>Responding to reviews shows customers you value their feedback and helps build trust.</span>
          <button id="writeResponse">
            <i data-lucide="message-square-reply"></i>
            Write a Response
          </button>
        </section>

      </div>

      <aside class="side-column">

        <section class="side-card">
          <h3>Review Overview</h3>
          <div class="overview">
            <div class="donut" id="donut">
              <div>
                <strong id="donutTotal">1</strong>
                <span>Total</span>
              </div>
            </div>

            <div class="legend">
              <div><i class="dot green-dot"></i><span>5 Star</span><b id="fiveCount">1 (100%)</b></div>
              <div><i class="dot yellow-dot"></i><span>4 Star</span><b id="fourCount">0 (0%)</b></div>
              <div><i class="dot orange-dot"></i><span>3 Star</span><b id="threeCount">0 (0%)</b></div>
              <div><i class="dot red-dot"></i><span>1-2 Star</span><b id="lowCount">0 (0%)</b></div>
            </div>
          </div>
        </section>

        <section class="side-card">
          <h3>Recent Activity</h3>
          <div id="activity"></div>
        </section>

        <section class="side-card distribution">
          <h3>Rating Distribution</h3>

          <div class="bar-row"><span>5 Star</span><div><i id="bar5"></i></div><b id="dist5">1 (100%)</b></div>
          <div class="bar-row"><span>4 Star</span><div><i id="bar4"></i></div><b id="dist4">0 (0%)</b></div>
          <div class="bar-row"><span>3 Star</span><div><i id="bar3"></i></div><b id="dist3">0 (0%)</b></div>
          <div class="bar-row"><span>2 Star</span><div><i id="bar2"></i></div><b id="dist2">0 (0%)</b></div>
          <div class="bar-row"><span>1 Star</span><div><i id="bar1"></i></div><b id="dist1">0 (0%)</b></div>
        </section>

      </aside>
    </div>
  </div>
</main>
   </div>

<div class="modal" id="reviewModal" hidden>
  <div class="modal-box">
    <button class="close" id="closeReview"><i data-lucide="x"></i></button>
    <h2>Add Customer Review</h2>
    <p>Create a review and watch the dashboard update instantly.</p>

    <form id="reviewForm">
      <label>Customer Name
        <input id="customerName" required placeholder="e.g. Sita Sharma">
      </label>

      <label>Email
        <input id="customerEmail" type="email" required placeholder="customer@email.com">
      </label>

      <label>Rating
        <select id="customerRating">
          <option value="5">★★★★★ 5 / 5</option>
          <option value="4">★★★★☆ 4 / 5</option>
          <option value="3">★★★☆☆ 3 / 5</option>
          <option value="2">★★☆☆☆ 2 / 5</option>
          <option value="1">★☆☆☆☆ 1 / 5</option>
        </select>
      </label>

      <label>Review
        <textarea id="customerReview" required placeholder="Write the customer's review..."></textarea>
      </label>

      <button class="submit-review" type="submit">
        <i data-lucide="plus"></i>
        Add Review
      </button>
    </form>
  </div>
</div>

<div class="modal" id="viewModal" hidden>
  <div class="modal-box view-box">
    <button class="close" id="closeView"><i data-lucide="x"></i></button>

    <div class="view-header">
      <div class="large-avatar" id="viewAvatar"></div>
      <div>
        <h2 id="viewName"></h2>
        <p id="viewEmail"></p>
      </div>
    </div>

    <div class="view-rating" id="viewRating"></div>

    <div class="review-detail">
      <span>Customer Review</span>
      <p id="viewReview"></p>
    </div>

    <div class="view-meta">
      <div><i data-lucide="calendar-days"></i><span><b>Date</b><small id="viewDate"></small></span></div>
      <div><i data-lucide="clock-3"></i><span><b>Time</b><small id="viewTime"></small></span></div>
    </div>

    <div class="view-actions">
      <button class="secondary-btn" id="closeViewBottom">Close</button>
      <button class="response-btn" id="viewRespond"><i data-lucide="message-square-reply"></i> Write Response</button>
    </div>
  </div>
</div>

<div class="modal" id="responseModal" hidden>
  <div class="modal-box">
    <button class="close" id="closeResponse"><i data-lucide="x"></i></button>
    <h2>Write a Response</h2>
    <p id="responseFor">Respond to the customer.</p>

    <textarea id="responseTextInput" placeholder="Thank you for your feedback..."></textarea>

    <button class="submit-review" id="sendResponse">
      <i data-lucide="send"></i>
      Send Response
    </button>
  </div>
</div>

<script>
  // Review data from the database, consumed by the feedback section of adminscript.js
  window.__FEEDBACK_DATA__ = <?= json_encode($feedback_js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

  // Render the lucide icons (the CSS targets the <svg> elements lucide generates)
  lucide.createIcons();
</script>
<script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
</body>
</html>