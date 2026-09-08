<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'staff');

$pageTitle = 'Billing & POS Settlement';
$selectedOrderParam = trim($_GET['order_number'] ?? '');

// PHP helper to safely output strings into HTML/JS contexts
function esc(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Fetch receipt header/footer configuration
$receiptConfigRes = $conn->query("SELECT * FROM `receipt_settings` WHERE `id` = 1 LIMIT 1");
$receiptConfig = ($receiptConfigRes && $receiptConfigRes->num_rows > 0) ? $receiptConfigRes->fetch_assoc() : [
    'restaurant_name' => 'Mero Bhoj Restaurant',
    'address'         => 'Lakeside-6, Pokhara, Nepal',
    'phone'           => '+977 61-460000',
    'pan_vat'         => '600987123',
    'footer_text'     => 'Thank you! Visit again.'
];
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
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  <link rel="stylesheet" href="../assets/css/admin2.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin2.css') ?>">
  <link rel="stylesheet" href="../assets/css/panel.css?v=<?= filemtime(__DIR__ . '/../assets/css/panel.css') ?>">
  
  <style>
    /* POS Billing High-Density Styles */
    .pos-container {
      display: grid;
      grid-template-columns: 1fr 480px;
      gap: 1.5rem;
      align-items: start;
    }
    @media (max-width: 1100px) {
      .pos-container {
        grid-template-columns: 1fr;
      }
    }
    .pos-card {
      background: var(--color-white, #ffffff);
      border-radius: 12px;
      padding: 1.25rem;
      box-shadow: 0 4px 16px rgba(0,0,0,0.06);
      border: 1px solid rgba(0,0,0,0.08);
      margin-bottom: 1.25rem;
    }
    .order-card {
      border: 1.5px solid #e2e8f0;
      border-radius: 10px;
      padding: 1rem;
      margin-bottom: 0.75rem;
      cursor: pointer;
      transition: all 0.2s ease;
      background: #f8fafc;
    }
    .order-card:hover, .order-card.selected {
      border-color: #ff6b00;
      background: #fff8f0;
      box-shadow: 0 2px 10px rgba(255,107,0,0.15);
    }
    .order-card.selected {
      border-width: 2px;
    }
    .order-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.5rem;
    }
    .order-type-badge {
      font-size: 0.75rem;
      font-weight: 700;
      padding: 0.2rem 0.6rem;
      border-radius: 20px;
      text-transform: uppercase;
    }
    .type-dinein { background: #e0f2fe; color: #0369a1; }
    .type-self { background: #fef3c7; color: #b45309; }
    .type-delivery { background: #dcfce7; color: #15803d; }

    .calc-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.4rem 0;
      font-size: 0.95rem;
    }
    .calc-total {
      font-size: 1.25rem;
      font-weight: 800;
      color: #1e293b;
      border-top: 2px dashed #cbd5e1;
      padding-top: 0.75rem;
      margin-top: 0.5rem;
    }
    .grand-total-box {
      background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
      color: #00ff87;
      padding: 1rem 1.25rem;
      border-radius: 10px;
      margin: 1rem 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 12px rgba(15,23,42,0.3);
    }
    .grand-total-amount {
      font-size: 1.75rem;
      font-weight: 900;
      font-family: monospace;
    }
    .pay-btn-group {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 0.5rem;
      margin-bottom: 1rem;
    }
    .pay-method-btn {
      border: 1.5px solid #cbd5e1;
      background: #fff;
      padding: 0.6rem 0.5rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.85rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.35rem;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .pay-method-btn:hover, .pay-method-btn.active {
      border-color: #ff6b00;
      background: #fff8f0;
      color: #ff6b00;
    }
    .denom-btn {
      background: #f1f5f9;
      border: 1px solid #cbd5e1;
      padding: 0.3rem 0.6rem;
      border-radius: 6px;
      font-size: 0.8rem;
      font-weight: 600;
      cursor: pointer;
    }
    .denom-btn:hover {
      background: #e2e8f0;
    }
    
    /* Modal Styles */
    .pos-modal-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(15, 23, 42, 0.7);
      backdrop-filter: blur(4px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
    }
    .pos-modal-overlay.active { display: flex; }
    .pos-modal-card {
      background: #fff;
      border-radius: 14px;
      max-width: 480px;
      width: 90%;
      padding: 1.5rem;
      box-shadow: 0 20px 40px rgba(0,0,0,0.25);
    }

    /* Thermal Receipt Printable Area */
    #thermalReceiptModal .receipt-paper {
      width: 320px;
      max-width: 100%;
      margin: 0 auto;
      padding: 1.25rem;
      background: #fff;
      font-family: 'Courier New', Courier, monospace;
      font-size: 13px;
      color: #000;
      line-height: 1.3;
      border: 1px dashed #cbd5e1;
    }
    @media print {
      body * { visibility: hidden; }
      #thermalReceiptModal, #thermalReceiptModal * { visibility: visible; }
      #thermalReceiptModal {
        position: absolute;
        left: 0; top: 0; width: 100%; height: auto;
        background: #fff;
      }
      .no-print { display: none !important; }
      #thermalReceiptModal .receipt-paper {
        border: none !important;
        width: 100% !important;
        padding: 0 !important;
      }
    }
  </style>
</head>
<body class="admin-page">
  <?php include __DIR__ . '/topbar.php'; ?>
  <div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-page-main">

      <div class="panel-head" style="margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap:wrap; gap:1rem;">
        <div>
          <h2 style="display:flex; align-items:center; gap:0.5rem;">
            <span class="material-symbols-sharp" style="color:#ff6b00;">payments</span> POS Billing & Settlement
          </h2>
          <p class="text-muted">Select active order, apply discount/taxes, calculate change & settle invoice.</p>
        </div>
        <div style="display:flex; gap:0.5rem;">
          <button id="refreshOrdersBtn" class="qrm-btn" style="background:#f1f5f9; color:#475569;">
            <span class="material-symbols-sharp" style="font-size:18px;">refresh</span> Refresh
          </button>
        </div>
      </div>

      <div class="pos-container">
        <!-- LEFT PANEL: Active Orders Selection -->
        <div>
          <div class="pos-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
              <h3 style="font-size:1.1rem; font-weight:700;">Active Orders</h3>
              <div style="display:flex; gap:0.35rem;" id="filterTabs">
                <button class="denom-btn active-tab" data-filter="all">All</button>
                <button class="denom-btn" data-filter="Dine In">Dine-In</button>
                <button class="denom-btn" data-filter="Self Order">Self QR</button>
                <button class="denom-btn" data-filter="Delivery">Delivery</button>
              </div>
            </div>

            <div style="margin-bottom:1rem; position:relative;">
              <input type="text" id="orderSearchInput" placeholder="Search by Order #, Customer or Table..." class="form-control" style="padding-left:2.5rem; border-radius:8px; border:1px solid #cbd5e1; width:100%; height:40px;">
              <span class="material-symbols-sharp" style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); color:#94a3b8;">search</span>
            </div>

            <div id="ordersListContainer" style="max-height: 620px; overflow-y: auto; padding-right: 4px;">
              <div style="text-align:center; padding: 2.5rem; color:#94a3b8;">Loading active orders...</div>
            </div>
          </div>
        </div>

        <!-- RIGHT PANEL: Checkout Drawer & Live Calculator -->
        <div>
          <div class="pos-card" id="settlementDrawer">
            <div id="noOrderSelectedView" style="text-align:center; padding:4rem 1rem; color:#94a3b8;">
              <span class="material-symbols-sharp" style="font-size:64px; opacity:0.4;">receipt_long</span>
              <h3 style="margin-top:1rem; font-weight:600;">No Order Selected</h3>
              <p style="font-size:0.85rem; margin-top:0.35rem;">Click any active order from the left list to start billing settlement.</p>
            </div>

            <div id="activeSettlementView" style="display:none;">
              <!-- Order Info Header -->
              <div style="display:flex; justify-content:space-between; align-items:flex-start; padding-bottom:0.75rem; border-bottom:1px solid #e2e8f0; margin-bottom:0.75rem;">
                <div>
                  <div style="display:flex; align-items:center; gap:0.5rem;">
                    <h3 id="billOrderNumber" style="font-size:1.2rem; font-weight:800; color:#0f172a;">ORD-XXXX</h3>
                    <span id="billOrderBadge" class="order-type-badge type-dinein">Dine In</span>
                  </div>
                  <div style="font-size:0.85rem; color:#64748b; margin-top:0.2rem;" id="billCustomerDetails">
                    Guest Customer • Table 1
                  </div>
                </div>
                <div style="text-align:right;">
                  <span id="billOrderStatus" class="panel-status st-pending" style="font-size:0.75rem;">Pending</span>
                  <div style="font-size:0.75rem; color:#94a3b8; margin-top:0.25rem;" id="billTimeStr">Just now</div>
                </div>
              </div>

              <!-- Itemized List Table -->
              <div style="max-height:180px; overflow-y:auto; margin-bottom:1rem; border:1px solid #f1f5f9; border-radius:8px;">
                <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                  <thead style="background:#f8fafc; color:#64748b; text-align:left;">
                    <tr>
                      <th style="padding:0.4rem 0.6rem;">Item</th>
                      <th style="padding:0.4rem 0.4rem; text-align:center;">Qty</th>
                      <th style="padding:0.4rem 0.6rem; text-align:right;">Price</th>
                      <th style="padding:0.4rem 0.6rem; text-align:right;">Total</th>
                    </tr>
                  </thead>
                  <tbody id="billItemsTbody">
                    <!-- Dynamic Items -->
                  </tbody>
                </table>
              </div>

              <!-- Live Computation Panel -->
              <div style="background:#f8fafc; padding:0.75rem; border-radius:10px; margin-bottom:1rem;">
                <div class="calc-row">
                  <span style="color:#64748b;">Subtotal</span>
                  <span style="font-weight:700;" id="calcSubtotal">Rs. 0.00</span>
                </div>

                <!-- Discount Control -->
                <div class="calc-row" style="margin-top:0.35rem;">
                  <div style="display:flex; align-items:center; gap:0.35rem;">
                    <span style="color:#64748b;">Discount</span>
                    <select id="discountTypeSelect" style="padding:0.15rem 0.35rem; font-size:0.75rem; border-radius:4px; border:1px solid #cbd5e1;">
                      <option value="fixed">Flat (Rs.)</option>
                      <option value="percent">Percentage (%)</option>
                    </select>
                  </div>
                  <div style="display:flex; align-items:center; gap:0.25rem;">
                    <input type="number" id="discountInput" value="0" min="0" step="any" style="width:75px; text-align:right; padding:0.2rem 0.4rem; border-radius:4px; border:1px solid #cbd5e1; font-weight:600;">
                    <span id="discountAmountLabel" style="font-size:0.8rem; color:#dc2626; font-weight:600;">- Rs. 0.00</span>
                  </div>
                </div>

                <!-- PIN Notice if triggered -->
                <div id="pinNoticeBadge" style="display:none; background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; padding:0.35rem 0.5rem; border-radius:6px; font-size:0.75rem; margin-top:0.35rem; align-items:center; justify-content:space-between;">
                  <span>⚠️ Discount requires Manager PIN</span>
                  <button id="openPinModalBtn" style="background:#dc2626; color:#fff; border:none; padding:0.15rem 0.4rem; border-radius:4px; font-size:0.7rem; font-weight:700; cursor:pointer;">Verify PIN</button>
                </div>

                <!-- Service Charge Control -->
                <div class="calc-row" style="margin-top:0.35rem;">
                  <div style="display:flex; align-items:center; gap:0.35rem;">
                    <input type="checkbox" id="scToggle" style="cursor:pointer;">
                    <label for="scToggle" style="color:#64748b; cursor:pointer; font-size:0.85rem;">Service Charge (10%)</label>
                  </div>
                  <span style="font-weight:600;" id="calcScAmt">Rs. 0.00</span>
                </div>

                <!-- VAT Control -->
                <div class="calc-row" style="margin-top:0.35rem;">
                  <div style="display:flex; align-items:center; gap:0.35rem;">
                    <input type="checkbox" id="vatToggle" checked style="cursor:pointer;">
                    <label for="vatToggle" style="color:#64748b; cursor:pointer; font-size:0.85rem;">VAT (13%)</label>
                  </div>
                  <span style="font-weight:600;" id="calcVatAmt">Rs. 0.00</span>
                </div>
              </div>

              <!-- Grand Total Glowing Banner -->
              <div class="grand-total-box">
                <div>
                  <div style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#94a3b8;">Grand Total</div>
                  <div style="font-size:0.75rem; color:#64748b;">Nett Payable</div>
                </div>
                <div class="grand-total-amount" id="calcGrandTotal">Rs. 0.00</div>
              </div>

              <!-- Payment Method Selection -->
              <div style="margin-bottom:1rem;">
                <label style="font-size:0.8rem; font-weight:700; color:#475569; display:block; margin-bottom:0.5rem;">PAYMENT METHOD</label>
                <div class="pay-btn-group">
                  <button type="button" class="pay-method-btn active" data-method="Cash">
                    <span class="material-symbols-sharp">payments</span> Cash
                  </button>
                  <button type="button" class="pay-method-btn" data-method="Fonepay / eSewa QR">
                    <span class="material-symbols-sharp">qr_code_2</span> eSewa / Fonepay
                  </button>
                  <button type="button" class="pay-method-btn" data-method="Card">
                    <span class="material-symbols-sharp">credit_card</span> Card
                  </button>
                  <button type="button" class="pay-method-btn" data-method="Credit / Room Charge">
                    <span class="material-symbols-sharp">account_balance_wallet</span> Credit / Room
                  </button>
                </div>

                <!-- Cash Calculator Section -->
                <div id="cashCalcSection" style="background:#f1f5f9; padding:0.75rem; border-radius:8px;">
                  <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                    <span style="font-size:0.85rem; font-weight:600; color:#334155;">Tendered Amount:</span>
                    <input type="number" id="cashReceivedInput" value="0" min="0" step="any" style="width:110px; text-align:right; font-weight:700; padding:0.3rem 0.5rem; border-radius:6px; border:1px solid #cbd5e1; font-size:1rem;">
                  </div>
                  <div style="display:flex; gap:0.25rem; margin-bottom:0.5rem; flex-wrap:wrap;">
                    <button class="denom-btn" onclick="setDenom('exact')">Exact</button>
                    <button class="denom-btn" onclick="setDenom(100)">+100</button>
                    <button class="denom-btn" onclick="setDenom(500)">+500</button>
                    <button class="denom-btn" onclick="setDenom(1000)">+1000</button>
                  </div>
                  <div style="display:flex; justify-content:space-between; align-items:center; padding-top:0.4rem; border-top:1px solid #cbd5e1;">
                    <span style="font-weight:700; color:#475569;">Change Due:</span>
                    <span id="changeDueDisplay" style="font-size:1.1rem; font-weight:800; color:#059669;">Rs. 0.00</span>
                  </div>
                </div>

                <!-- Digital QR Section -->
                <div id="qrSection" style="display:none; text-align:center; background:#f0fdf4; padding:0.75rem; border-radius:8px; border:1px solid #bbf7d0;">
                  <div style="font-weight:700; color:#166534; margin-bottom:0.25rem;">Fonepay & eSewa QR Settlement</div>
                  <p style="font-size:0.75rem; color:#15803d; margin-bottom:0.5rem;">Present customer with merchant QR code to scan.</p>
                  <button type="button" id="showQrModalBtn" class="qrm-btn" style="background:#16a34a; color:#fff; font-size:0.8rem; padding:0.35rem 0.75rem;">
                    <span class="material-symbols-sharp" style="font-size:16px;">qr_code_2</span> Show Merchant QR
                  </button>
                </div>

                <!-- Card/Credit Reference Section -->
                <div id="cardSection" style="display:none; background:#f8fafc; padding:0.75rem; border-radius:8px; border:1px solid #cbd5e1;">
                  <label style="font-size:0.75rem; font-weight:600; color:#475569; display:block; margin-bottom:0.25rem;">Transaction Ref / Account Notes:</label>
                  <input type="text" id="txnRefInput" placeholder="Enter Approval Code, Card #, or Room Ref..." style="width:100%; padding:0.35rem 0.5rem; border-radius:6px; border:1px solid #cbd5e1; font-size:0.85rem;">
                </div>
              </div>

              <!-- Action Footer -->
              <div style="display:flex; gap:0.5rem;">
                <button type="button" id="settleAndPrintBtn" class="qrm-btn" style="flex:1; background:linear-gradient(135deg, #00d26a 0%, #00a854 100%); color:#fff; font-weight:800; font-size:1rem; padding:0.75rem; display:flex; justify-content:center; align-items:center; gap:0.5rem; border-radius:10px; box-shadow:0 4px 12px rgba(0,210,106,0.3);">
                  <span class="material-symbols-sharp">check_circle</span> Settle & Pay Invoice
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>

  <!-- MANAGER PIN MODAL -->
  <div class="pos-modal-overlay" id="pinModal">
    <div class="pos-modal-card">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h3 style="font-size:1.1rem; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:0.35rem;">
          <span class="material-symbols-sharp" style="color:#dc2626;">lock</span> Manager PIN Override
        </h3>
        <button onclick="closePinModal()" style="background:none; border:none; cursor:pointer;"><span class="material-symbols-sharp">close</span></button>
      </div>
      <p style="font-size:0.85rem; color:#64748b; margin-bottom:1rem;">
        Discounts exceeding 15% or Rs. 500 require authorization by a Shift Manager or Administrator.
      </p>
      <div style="margin-bottom:1rem;">
        <label style="font-size:0.8rem; font-weight:700; color:#475569; display:block; margin-bottom:0.25rem;">Enter Manager 4-Digit PIN</label>
        <input type="password" id="pinCodeInput" maxlength="10" placeholder="e.g. 1234" style="width:100%; padding:0.6rem; font-size:1.25rem; letter-spacing:0.2em; text-align:center; border-radius:8px; border:1px solid #cbd5e1;">
      </div>
      <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
        <button class="denom-btn" onclick="closePinModal()">Cancel</button>
        <button id="verifyPinBtn" class="qrm-btn" style="background:#dc2626; color:#fff; font-weight:700;">Verify & Apply</button>
      </div>
    </div>
  </div>

  <!-- FONEPAY / ESEWA DIGITAL QR MODAL -->
  <div class="pos-modal-overlay" id="qrModal">
    <div class="pos-modal-card" style="text-align:center;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
        <h3 style="font-size:1.1rem; font-weight:800; color:#0f172a;">Fonepay / eSewa Merchant QR</h3>
        <button onclick="closeQrModal()" style="background:none; border:none; cursor:pointer;"><span class="material-symbols-sharp">close</span></button>
      </div>
      <div style="background:#f8fafc; padding:1rem; border-radius:12px; margin-bottom:1rem; border:1px solid #e2e8f0;">
        <div style="font-weight:700; color:#1e293b; font-size:1.1rem;">Mero Bhoj Restaurant</div>
        <div style="font-size:0.8rem; color:#64748b;">Merchant ID: MB-8839201</div>
        <div style="font-size:1.3rem; font-weight:800; color:#ff6b00; margin:0.5rem 0;" id="qrPayableAmount">Rs. 0.00</div>
        
        <!-- Render Static / Dynamic Merchant QR -->
        <div style="background:#fff; padding:1rem; display:inline-block; border-radius:8px; border:1px solid #cbd5e1; margin:0.5rem 0;">
          <svg width="160" height="160" viewBox="0 0 100 100" fill="#1e293b">
            <path d="M0 0h30v30H0zM10 10h10v10H10zM40 0h10v10H40zM60 0h10v10H60zM70 0h30v30H70zM80 10h10v10H80zM0 40h10v10H0zM20 40h20v10H20zM50 40h20v10H50zM80 40h20v10H80zM0 70h30v30H0zM10 80h10v10H10zM40 60h20v10H40zM70 60h10v10H70zM90 60h10v10H90zM40 80h10v20H40zM60 70h30v10H60zM80 90h20v10H80z"/>
          </svg>
        </div>
        <div style="font-size:0.75rem; color:#64748b; margin-top:0.25rem;">Scan using any Banking App, eSewa, or Fonepay</div>
      </div>
      <button class="qrm-btn" style="background:#16a34a; color:#fff; width:100%; font-weight:700;" onclick="closeQrModal()">Done / Confirmed</button>
    </div>
  </div>

  <!-- THERMAL RECEIPT PRINT MODAL -->
  <div class="pos-modal-overlay" id="thermalReceiptModal">
    <div class="pos-modal-card no-print" style="max-width:380px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h3 style="font-size:1rem; font-weight:700;">Thermal Invoice Receipt</h3>
        <button onclick="closeReceiptModal()" style="background:none; border:none; cursor:pointer;"><span class="material-symbols-sharp">close</span></button>
      </div>

      <div class="receipt-paper" id="receiptPaper">
        <!-- Rendered dynamically -->
      </div>

      <div style="display:flex; gap:0.5rem; margin-top:1rem;" class="no-print">
        <button class="denom-btn" onclick="closeReceiptModal()" style="flex:1;">Close</button>
        <button class="qrm-btn" style="flex:2; background:#0f172a; color:#fff; font-weight:700; display:flex; justify-content:center; align-items:center; gap:0.35rem;" onclick="window.print()">
          <span class="material-symbols-sharp" style="font-size:18px;">print</span> Print Invoice
        </button>
      </div>
    </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let activeOrders = [];
    let currentFilter = 'all';
    let selectedOrder = null;
    let verifiedManagerPin = '';

    const paramOrderNumber = '<?= esc($selectedOrderParam) ?>';

    loadActiveOrders();

    document.getElementById('refreshOrdersBtn').addEventListener('click', loadActiveOrders);
    document.getElementById('orderSearchInput').addEventListener('input', renderOrdersList);

    // Filter tabs logic
    document.querySelectorAll('#filterTabs button').forEach(btn => {
        btn.addEventListener('click', (e) => {
            document.querySelectorAll('#filterTabs button').forEach(b => b.classList.remove('active-tab'));
            btn.classList.add('active-tab');
            currentFilter = btn.dataset.filter;
            renderOrdersList();
        });
    });

    function loadActiveOrders() {
        fetch('api/get_order_details.php')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                activeOrders = data.orders || [];
                renderOrdersList();

                if (paramOrderNumber && !selectedOrder) {
                    selectOrder(paramOrderNumber);
                }
            })
            .catch(err => console.error('Failed loading orders:', err));
    }

    function renderOrdersList() {
        const container = document.getElementById('ordersListContainer');
        const query = document.getElementById('orderSearchInput').value.toLowerCase().trim();

        let filtered = activeOrders.filter(o => {
            if (currentFilter !== 'all' && o.order_type !== currentFilter) return false;
            if (query !== '') {
                const matchNum = (o.order_number || '').toLowerCase().includes(query);
                const matchCust = (o.full_name || '').toLowerCase().includes(query);
                const matchMob = (o.mobile || '').toLowerCase().includes(query);
                const matchTbl = (o.table_number || '').toLowerCase().includes(query);
                return matchNum || matchCust || matchMob || matchTbl;
            }
            return true;
        });

        if (filtered.length === 0) {
            container.innerHTML = `<div style="text-align:center; padding:2rem; color:#94a3b8;">No active orders matching search criteria.</div>`;
            return;
        }

        container.innerHTML = filtered.map(o => {
            const isSelected = selectedOrder && selectedOrder.order.order_number === o.order_number;
            const badgeClass = o.order_type === 'Dine In' ? 'type-dinein' : (o.order_type === 'Self Order' ? 'type-self' : 'type-delivery');
            const subtotalVal = parseFloat(o.subtotal || 0).toFixed(2);

            return `
                <div class="order-card ${isSelected ? 'selected' : ''}" onclick="window.selectOrder('${esc(o.order_number)}')">
                    <div class="order-card-header">
                        <div>
                            <strong style="font-size:1rem; color:#0f172a;">${esc(o.order_number)}</strong>
                            <span class="order-type-badge ${badgeClass}" style="margin-left:0.4rem;">${esc(o.order_type)} ${o.table_number ? 'T#'+esc(o.table_number) : ''}</span>
                        </div>
                        <span style="font-weight:800; color:#1e293b;">Rs. ${subtotalVal}</span>
                    </div>
                    <div style="font-size:0.85rem; color:#475569; display:flex; justify-content:space-between; align-items:center;">
                        <span>👤 ${esc(o.full_name || 'Guest Customer')}</span>
                        <span style="font-size:0.75rem; color:#94a3b8;">${o.total_items} items</span>
                    </div>
                    <div style="font-size:0.75rem; color:#64748b; margin-top:0.35rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        ${esc(o.items_summary || '')}
                    </div>
                </div>
            `;
        }).join('');
    }

    window.selectOrder = function(orderNumber) {
        fetch(`api/get_order_details.php?order_number=${encodeURIComponent(orderNumber)}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message || 'Failed fetching order details');
                    return;
                }

                selectedOrder = data;
                verifiedManagerPin = '';
                document.getElementById('noOrderSelectedView').style.display = 'none';
                document.getElementById('activeSettlementView').style.display = 'block';

                document.getElementById('billOrderNumber').textContent = data.order.order_number;
                document.getElementById('billOrderBadge').textContent = `${data.order.order_type} ${data.order.table_number ? 'T#'+data.order.table_number : ''}`;
                document.getElementById('billCustomerDetails').textContent = `${data.order.full_name || 'Guest'} ${data.order.mobile ? '• '+data.order.mobile : ''}`;
                document.getElementById('billOrderStatus').textContent = data.order.status;

                // Render items table
                const tbody = document.getElementById('billItemsTbody');
                tbody.innerHTML = data.items.map(item => `
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:0.4rem 0.6rem; font-weight:600; color:#1e293b;">${esc(item.menu_name)}</td>
                        <td style="padding:0.4rem 0.4rem; text-align:center;">${item.quantity}</td>
                        <td style="padding:0.4rem 0.6rem; text-align:right; color:#64748b;">Rs. ${item.price.toFixed(2)}</td>
                        <td style="padding:0.4rem 0.6rem; text-align:right; font-weight:700; color:#0f172a;">Rs. ${item.total_price.toFixed(2)}</td>
                    </tr>
                `).join('');

                recalculate();
                renderOrdersList();
            });
    }

    // Live calculation listeners
    document.getElementById('discountTypeSelect').addEventListener('change', recalculate);
    document.getElementById('discountInput').addEventListener('input', recalculate);
    document.getElementById('scToggle').addEventListener('change', recalculate);
    document.getElementById('vatToggle').addEventListener('change', recalculate);
    document.getElementById('cashReceivedInput').addEventListener('input', updateCashChange);

    function recalculate() {
        if (!selectedOrder) return;

        const subtotal = selectedOrder.subtotal;
        document.getElementById('calcSubtotal').textContent = `Rs. ${subtotal.toFixed(2)}`;

        const discType = document.getElementById('discountTypeSelect').value;
        let discVal = parseFloat(document.getElementById('discountInput').value) || 0;
        if (discVal < 0) discVal = 0;

        let discAmt = 0;
        if (discType === 'percent') {
            discAmt = (subtotal * discVal) / 100;
        } else {
            discAmt = discVal;
        }
        if (discAmt > subtotal) discAmt = subtotal;

        document.getElementById('discountAmountLabel').textContent = `- Rs. ${discAmt.toFixed(2)}`;

        // Check PIN requirement
        let requiresPin = false;
        if (discType === 'percent' && discVal > 15) requiresPin = true;
        if (discType === 'fixed' && discAmt > 500) requiresPin = true;

        const pinNotice = document.getElementById('pinNoticeBadge');
        if (requiresPin && !verifiedManagerPin) {
            pinNotice.style.display = 'flex';
        } else {
            pinNotice.style.display = 'none';
        }

        const afterDisc = subtotal - discAmt;

        const scEnabled = document.getElementById('scToggle').checked;
        const scAmt = scEnabled ? (afterDisc * 0.10) : 0;
        document.getElementById('calcScAmt').textContent = `Rs. ${scAmt.toFixed(2)}`;

        const vatEnabled = document.getElementById('vatToggle').checked;
        const vatAmt = vatEnabled ? ((afterDisc + scAmt) * 0.13) : 0;
        document.getElementById('calcVatAmt').textContent = `Rs. ${vatAmt.toFixed(2)}`;

        const grandTotal = afterDisc + scAmt + vatAmt;
        document.getElementById('calcGrandTotal').textContent = `Rs. ${grandTotal.toFixed(2)}`;
        document.getElementById('qrPayableAmount').textContent = `Rs. ${grandTotal.toFixed(2)}`;

        updateCashChange();
    }

    function updateCashChange() {
        const grandTotalText = document.getElementById('calcGrandTotal').textContent.replace('Rs. ', '');
        const grandTotal = parseFloat(grandTotalText) || 0;
        const received = parseFloat(document.getElementById('cashReceivedInput').value) || 0;
        const changeDue = received - grandTotal;

        const display = document.getElementById('changeDueDisplay');
        if (changeDue >= 0) {
            display.textContent = `Rs. ${changeDue.toFixed(2)}`;
            display.style.color = '#059669';
        } else {
            display.textContent = `Rs. ${changeDue.toFixed(2)} (Short)`;
            display.style.color = '#dc2626';
        }
    }

    window.setDenom = function(val) {
        const grandTotalText = document.getElementById('calcGrandTotal').textContent.replace('Rs. ', '');
        const grandTotal = parseFloat(grandTotalText) || 0;

        if (val === 'exact') {
            document.getElementById('cashReceivedInput').value = grandTotal.toFixed(2);
        } else {
            const current = parseFloat(document.getElementById('cashReceivedInput').value) || 0;
            document.getElementById('cashReceivedInput').value = (current + val).toFixed(2);
        }
        updateCashChange();
    };

    // Payment method selector
    let selectedMethod = 'Cash';
    document.querySelectorAll('.pay-method-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.pay-method-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            selectedMethod = btn.dataset.method;

            document.getElementById('cashCalcSection').style.display = selectedMethod === 'Cash' ? 'block' : 'none';
            document.getElementById('qrSection').style.display = selectedMethod.includes('QR') ? 'block' : 'none';
            document.getElementById('cardSection').style.display = (selectedMethod === 'Card' || selectedMethod.includes('Credit')) ? 'block' : 'none';
        });
    });

    // Manager PIN handlers
    document.getElementById('openPinModalBtn').addEventListener('click', () => {
        document.getElementById('pinModal').classList.add('active');
        document.getElementById('pinCodeInput').focus();
    });

    window.closePinModal = function() {
        document.getElementById('pinModal').classList.remove('active');
    };

    document.getElementById('verifyPinBtn').addEventListener('click', () => {
        const pin = document.getElementById('pinCodeInput').value.trim();
        if (!pin) { alert('Please enter Manager PIN'); return; }
        verifiedManagerPin = pin;
        closePinModal();
        recalculate();
    });

    // Digital QR Handlers
    document.getElementById('showQrModalBtn').addEventListener('click', () => {
        document.getElementById('qrModal').classList.add('active');
    });

    window.closeQrModal = function() {
        document.getElementById('qrModal').classList.remove('active');
    };

    // Thermal Receipt Handlers
    window.closeReceiptModal = function() {
        document.getElementById('thermalReceiptModal').classList.remove('active');
    };

    // Settle & Pay Handler
    document.getElementById('settleAndPrintBtn').addEventListener('click', () => {
        if (!selectedOrder) return;

        const grandTotalText = document.getElementById('calcGrandTotal').textContent.replace('Rs. ', '');
        const grandTotal = parseFloat(grandTotalText) || 0;

        const subtotalText = document.getElementById('calcSubtotal').textContent.replace('Rs. ', '');
        const subtotal = parseFloat(subtotalText) || 0;

        const discType = document.getElementById('discountTypeSelect').value;
        const discVal = parseFloat(document.getElementById('discountInput').value) || 0;
        const discAmtText = document.getElementById('discountAmountLabel').textContent.replace('- Rs. ', '');
        const discAmt = parseFloat(discAmtText) || 0;

        const scEnabled = document.getElementById('scToggle').checked;
        const scAmtText = document.getElementById('calcScAmt').textContent.replace('Rs. ', '');
        const scAmt = parseFloat(scAmtText) || 0;

        const vatEnabled = document.getElementById('vatToggle').checked;
        const vatAmtText = document.getElementById('calcVatAmt').textContent.replace('Rs. ', '');
        const vatAmt = parseFloat(vatAmtText) || 0;

        const amountReceived = parseFloat(document.getElementById('cashReceivedInput').value) || 0;
        const changeDueText = document.getElementById('changeDueDisplay').textContent.replace('Rs. ', '').replace(' (Short)', '');
        const changeDue = parseFloat(changeDueText) || 0;

        const remarks = document.getElementById('txnRefInput').value.trim();

        if (selectedMethod === 'Cash' && amountReceived < grandTotal) {
            if (!confirm(`Tendered cash (Rs. ${amountReceived.toFixed(2)}) is less than Grand Total (Rs. ${grandTotal.toFixed(2)}). Proceed anyway?`)) {
                return;
            }
        }

        const payload = {
            order_number: selectedOrder.order.order_number,
            subtotal: subtotal,
            discount_type: discType,
            discount_value: discVal,
            discount_amount: discAmt,
            service_charge_rate: scEnabled ? 10 : 0,
            service_charge_amount: scAmt,
            vat_rate: vatEnabled ? 13 : 0,
            vat_amount: vatAmt,
            grand_total: grandTotal,
            payment_method: selectedMethod,
            amount_received: amountReceived,
            change_due: changeDue,
            manager_pin: verifiedManagerPin,
            remarks: remarks
        };

        const btn = document.getElementById('settleAndPrintBtn');
        btn.disabled = true;
        btn.textContent = 'Processing Settlement...';

        fetch('api/settle_bill.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-sharp">check_circle</span> Settle & Pay Invoice';

            if (!data.success) {
                if (data.requires_pin) {
                    document.getElementById('pinModal').classList.add('active');
                    document.getElementById('pinCodeInput').focus();
                } else {
                    alert(data.message || 'Settlement failed');
                }
                return;
            }

            // Render Thermal Receipt
            renderThermalReceipt(data.invoice);
            document.getElementById('thermalReceiptModal').classList.add('active');

            // Reset view
            selectedOrder = null;
            verifiedManagerPin = '';
            document.getElementById('noOrderSelectedView').style.display = 'block';
            document.getElementById('activeSettlementView').style.display = 'none';

            loadActiveOrders();
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<span class="material-symbols-sharp">check_circle</span> Settle & Pay Invoice';
            alert('Error connecting to settlement server.');
        });
    });

    const receiptConfig = <?= json_encode($receiptConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function renderThermalReceipt(inv) {
        const paper = document.getElementById('receiptPaper');
        paper.innerHTML = `
            <div style="text-align:center; margin-bottom:8px;">
                <strong style="font-size:16px; text-transform:uppercase;">${esc(receiptConfig.restaurant_name)}</strong><br>
                <span>${esc(receiptConfig.address)}</span><br>
                <span>Tel: ${esc(receiptConfig.phone)} | VAT: ${esc(receiptConfig.pan_vat)}</span><br>
                --------------------------------
            </div>
            <div>
                <strong>INVOICE #:</strong> ${inv.bill_number}<br>
                <strong>ORDER #:</strong> ${inv.order_number}<br>
                <strong>DATE:</strong> ${inv.date_time}<br>
                <strong>CASHIER:</strong> ${inv.cashier_name}<br>
                <strong>CUSTOMER:</strong> ${inv.customer_name}<br>
                --------------------------------
            </div>
            <table style="width:100%; border-collapse:collapse; margin:6px 0; font-size:12px;">
                <thead>
                    <tr style="border-bottom:1px dashed #000;">
                        <th style="text-align:left;">QTY ITEM</th>
                        <th style="text-align:right;">RATE</th>
                        <th style="text-align:right;">AMT</th>
                    </tr>
                </thead>
                <tbody>
                    ${inv.items.map(i => `
                        <tr>
                            <td>${i.qty} x ${esc(i.name)}</td>
                            <td style="text-align:right;">${i.price.toFixed(2)}</td>
                            <td style="text-align:right;">${i.total.toFixed(2)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            --------------------------------
            <div style="line-height:1.4;">
                <div style="display:flex; justify-content:space-between;"><span>SUBTOTAL:</span><span>Rs. ${inv.subtotal.toFixed(2)}</span></div>
                ${inv.discount_amount > 0 ? `<div style="display:flex; justify-content:space-between;"><span>DISCOUNT:</span><span>- Rs. ${inv.discount_amount.toFixed(2)}</span></div>` : ''}
                ${inv.service_charge_amount > 0 ? `<div style="display:flex; justify-content:space-between;"><span>SERVICE CHARGE (${inv.service_charge_rate}%):</span><span>Rs. ${inv.service_charge_amount.toFixed(2)}</span></div>` : ''}
                ${inv.vat_amount > 0 ? `<div style="display:flex; justify-content:space-between;"><span>VAT (${inv.vat_rate}%):</span><span>Rs. ${inv.vat_amount.toFixed(2)}</span></div>` : ''}
                <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:14px; border-top:1px dashed #000; border-bottom:1px dashed #000; padding:4px 0; margin-top:4px;">
                    <span>GRAND TOTAL:</span><span>Rs. ${inv.grand_total.toFixed(2)}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:4px;"><span>PAYMENT METHOD:</span><span>${inv.payment_method}</span></div>
                ${inv.payment_method === 'Cash' ? `
                    <div style="display:flex; justify-content:space-between;"><span>TENDERED:</span><span>Rs. ${inv.amount_received.toFixed(2)}</span></div>
                    <div style="display:flex; justify-content:space-between;"><span>CHANGE DUE:</span><span>Rs. ${inv.change_due.toFixed(2)}</span></div>
                ` : ''}
            </div>
            --------------------------------
            <div style="text-align:center; margin-top:8px; font-style:italic;">
                ${esc(receiptConfig.footer_text).replace(/\n/g, '<br>')}
            </div>
        `;
    }

    function esc(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
});
</script>
</body>
</html>
