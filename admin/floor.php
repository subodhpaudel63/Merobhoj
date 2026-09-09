<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

$pageTitle = 'Floor Plan';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> · Mero Bhoj</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  <link rel="stylesheet" href="../assets/css/admin2.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin2.css') ?>">
  <link rel="stylesheet" href="../assets/css/panel.css?v=<?= filemtime(__DIR__ . '/../assets/css/panel.css') ?>">
</head>
<body class="admin-page floor-plan-page"
      data-fp-api-url="../admin/api/api_floor_tables.php"
      data-fp-table-api-url="../staff/api/table_details.php"
      data-fp-settle-api-url="../staff/api/settle_bill.php"
      data-fp-book-api-url="../staff/api/booking_create.php"
      data-fp-add-table-url="../admin/api/api_qr_tables.php"
      data-fp-is-admin="true">
  <?php include __DIR__ . '/topbar.php'; ?>
  <div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-page-main">

      <!-- Header Row -->
      <div class="fp-top-bar">
        <div class="fp-tabs">
          <button class="fp-tab-btn active"><span class="material-symbols-sharp">grid_view</span> Floor Plan</button>
          <button class="fp-tab-btn" onclick="location.href='bookings.php'"><span class="material-symbols-sharp">event_seat</span> Reservations</button>
        </div>

        <div class="fp-stats-group">
          <div class="fp-stat-box">
            <div class="lbl">Tables</div>
            <div class="val" id="statTotal">0</div>
          </div>
          <div class="fp-stat-box">
            <div class="lbl">Available</div>
            <div class="val fp-stat-val-free" id="statAvailable">0</div>
          </div>
          <div class="fp-stat-box">
            <div class="lbl">Occupied</div>
            <div class="val fp-stat-val-occupied" id="statOccupied">0</div>
          </div>
          <div class="fp-stat-box">
            <div class="lbl">Reservations</div>
            <div class="val fp-stat-val-reserved" id="statReservations">0</div>
          </div>
        </div>
      </div>

      <!-- Filters & Search -->
      <div class="fp-filter-bar">
        <div class="fp-search-box">
          <span class="material-symbols-sharp">search</span>
          <input type="text" id="searchInput" placeholder="Search tables..." oninput="filterTables()">
        </div>

        <div class="fp-status-pills">
          <button class="fp-pill-filter p-all active" onclick="setStatusFilter('all', this)">All</button>
          <button class="fp-pill-filter p-available" onclick="setStatusFilter('free', this)">Available</button>
          <button class="fp-pill-filter p-occupied" onclick="setStatusFilter('occupied', this)">Occupied</button>
          <button class="fp-pill-filter p-reserved" onclick="setStatusFilter('reserved', this)">Reserved</button>
          <button class="fp-pill-filter p-dirty" onclick="setStatusFilter('dirty', this)">Dirty</button>
        </div>

        <div class="fp-header-btns">
          <button class="btn-outline" onclick="openAddTableModal()">
            <span class="material-symbols-sharp">add</span> Add Table
          </button>
          <a href="orders_page.php" class="btn-red">
            <span class="material-symbols-sharp">add</span> New Order
          </a>
        </div>
      </div>

      <!-- Floor Plan Canvas -->
      <div class="fp-area-heading">
        Ground <span class="count-badge" id="areaCount">0</span>
      </div>

      <div class="fp-canvas" id="floorCanvas">
        <p class="fp-canvas-loading">Loading MeroBhoj Floor Plan...</p>
      </div>

    </main>
  </div>

  <!-- Table Details Modal -->
  <div class="modal-overlay" id="tableModal">
    <div class="modal-box">
      <div class="modal-header">
        <div>
          <h3 id="modalTableTitle">Table A1</h3>
          <small id="modalTableSubtitle" class="modal-subtitle">20 Seats</small>
        </div>
        <span class="panel-status st-cancelled" id="modalTableStatusBadge">OCCUPIED</span>
        <button class="modal-close-btn" onclick="closeModal('tableModal')">&times;</button>
      </div>
      <div class="modal-body" id="modalTableBody">
        <!-- Orders or Available Options injected here -->
      </div>
      <div class="modal-footer modal-footer-space">
        <button class="btn-outline modal-btn-red-icon" onclick="openNewOrderForTable()">
          <span class="material-symbols-sharp">add</span> New Order
        </button>
        <button class="btn-outline" onclick="closeModal('tableModal')">Close</button>
      </div>
    </div>
  </div>

  <!-- Settle Payment Modal -->
  <div class="modal-overlay" id="settleModal">
    <div class="modal-box settle-modal-box">
      <div class="modal-header">
        <h3>POS SETTLEMENT</h3>
        <button class="modal-close-btn" onclick="closeModal('settleModal')">&times;</button>
      </div>
      <div class="modal-body">
        <div class="settle-grid">
          
          <!-- Bill Printable Preview -->
          <div>
            <div class="receipt-preview" id="printableReceipt">
              <div class="r-head">
                <h4>MERO BHOJ</h4>
                <div class="r-subhead">POS SETTLEMENT</div>
                <div class="r-badge">BILL</div>
              </div>
              <div class="r-meta">
                <div>Order: <strong id="recOrderNum">ORD-0000</strong></div>
                <div>Table: <strong id="recTableName">A1</strong></div>
                <div id="recDateTime">Sep 6, 2026, 11:14 AM</div>
              </div>

              <table class="receipt-table">
                <thead>
                  <tr class="r-border-bottom">
                    <th>Item</th>
                    <th class="num">Qty</th>
                    <th class="num">Total</th>
                  </tr>
                </thead>
                <tbody id="recItemsBody">
                  <!-- Receipt items -->
                </tbody>
              </table>

              <div class="r-summary-block">
                <div class="r-flex-between">
                  <span>Subtotal</span>
                  <span id="recSubtotal">NPR 0</span>
                </div>
                <div class="r-flex-between r-text-muted">
                  <span>Discount</span>
                  <span id="recDiscount">NPR 0</span>
                </div>
              </div>

              <div class="receipt-total-line r-margin-top">
                <span>Total</span>
                <span id="recTotal" class="r-total-val">NPR 0</span>
              </div>
              <div class="r-flex-between r-margin-top">
                <span>Tendered</span>
                <span id="recTendered">NPR 0</span>
              </div>
              <div class="r-flex-between">
                <span>Change Due</span>
                <span id="recChange">NPR 0</span>
              </div>
            </div>
          </div>

          <!-- Settlement Controls -->
          <div>
            <div class="form-group">
              <label>ADD DISCOUNT</label>
              <div class="r-flex-gap">
                <input type="number" id="discountInput" value="0" min="0" oninput="recalculateSettleTotal()" placeholder="0">
              </div>
            </div>

            <div class="form-group r-margin-top-lg">
              <label>PAYMENT METHOD</label>
              <div class="pm-grid">
                <button class="pm-btn active" onclick="selectPM('Cash', this)"><span class="material-symbols-sharp">payments</span> Cash</button>
                <button class="pm-btn" onclick="selectPM('Card', this)"><span class="material-symbols-sharp">credit_card</span> Card</button>
                <button class="pm-btn" onclick="selectPM('Digital', this)"><span class="material-symbols-sharp">qr_code_scanner</span> Digital</button>
                <button class="pm-btn" onclick="selectPM('Credit', this)"><span class="material-symbols-sharp">person</span> Credit</button>
                <button class="pm-btn" onclick="selectPM('In-House', this)"><span class="material-symbols-sharp">home</span> In-House</button>
              </div>
            </div>

            <div class="settle-actions-col">
              <label class="settle-tendered-label">Tendered amount
                <input type="number" id="posAmountReceived" min="0" step="0.01" value="0" oninput="recalculateSettleTotal()">
              </label>
              <button class="btn-red btn-settle-full" onclick="processSettle(true)">
                <span class="material-symbols-sharp">print</span> Print & Settle
              </button>
              <button class="btn-outline btn-settle-full" onclick="processSettle(false)">
                Quick Settle
              </button>
            </div>

          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- Reserve Modal for Available Table -->
  <div class="modal-overlay" id="reserveModal">
    <div class="modal-box">
      <div class="modal-header">
        <h3 id="reserveModalTitle">Reserve Table</h3>
        <button class="modal-close-btn" onclick="closeModal('reserveModal')">&times;</button>
      </div>
      <div class="modal-body">
        <form id="reserveTableForm">
          <input type="hidden" id="resTableId">
          <div class="form-group">
            <label>Guest Name *</label>
            <input type="text" id="resGuestName" placeholder="Enter guest name" required>
          </div>
          <div class="form-group">
            <label>Phone Number *</label>
            <input type="text" id="resGuestPhone" placeholder="Enter mobile number" required>
          </div>
          <div class="form-group">
            <label>Email (Optional)</label>
            <input type="email" id="resGuestEmail" placeholder="Enter email address">
          </div>
          <div class="form-group">
            <label>Number of Guests *</label>
            <input type="number" id="resGuests" value="2" min="1" max="30" required>
          </div>
          <div class="form-row reservation-fields">
            <div class="form-group reservation-field">
              <label>Booking Date *</label>
              <input type="date" id="resDate" required>
            </div>
            <div class="form-group reservation-field">
              <label>Start Time *</label>
              <input type="time" id="resTime" required>
            </div>
          </div>
          <div class="form-group">
            <label>End Time (Optional)</label>
            <input type="time" id="resEndTime" placeholder="Auto-calculated if empty">
            <small class="form-help-text">Leave empty for default 2-hour slot</small>
          </div>
          <div class="form-group">
            <label>Special Requests / Notes</label>
            <textarea id="resMessage" rows="2" placeholder="Any special requirements..."></textarea>
          </div>
          <div class="modal-footer modal-footer-right">
            <button type="button" class="btn-outline" onclick="closeModal('reserveModal')">Cancel</button>
            <button type="submit" class="btn-red">Save Reservation</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <!-- Add Table Modal -->
  <div class="modal-overlay" id="addTableModal">
    <div class="modal-box">
      <div class="modal-header">
        <h3>Add New Table</h3>
        <button class="modal-close-btn" onclick="closeModal('addTableModal')">&times;</button>
      </div>
      <div class="modal-body">
        <form id="addTableForm">
          <div class="form-group">
            <label>Table Name *</label>
            <input type="text" id="addTableName" placeholder="e.g. Table 8 or A5" required>
          </div>
          <div class="form-group">
            <label>Capacity (Seats) *</label>
            <input type="number" id="addTableCap" value="4" min="1" max="50" required>
          </div>
          <div class="modal-footer modal-footer-right">
            <button type="button" class="btn-outline" onclick="closeModal('addTableModal')">Cancel</button>
            <button type="submit" class="btn-red">Add Table</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="toast-container" id="toastContainer"></div>

  <script src="../assets/js/adminscript.js?v=<?= time() ?>"></script>
  <script src="../assets/js/admin2.js?v=<?= time() ?>"></script>
  <script src="../assets/js/panel_notifications.js?v=<?= time() ?>"></script>
  <script src="../assets/js/floor_plan.js?v=<?= time() ?>"></script>
</body>
</html>
