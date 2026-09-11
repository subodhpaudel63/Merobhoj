<?php
declare(strict_types=1);
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Finance - Mero Bhoj</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/admin2.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin2.css') ?>">
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  <style>
    :root {
      --fin-bg: #f8fafc;
      --fin-surface: #ffffff;
      --fin-border: #e2e8f0;
      --fin-text: #0f172a;
      --fin-text-muted: #64748b;
      --fin-primary: #f05a22;
      --fin-font: 'Outfit', sans-serif;
    }
    body.admin-page { background: #f8fafc; font-family: 'Outfit', sans-serif; color: #0f172a; }
    body.admin-page .admin-page-main { padding: 28px; max-width: 1600px; margin: 0 auto; }
    .fin-page { background: transparent; padding: 0; }
    
    .fin-page-header {
      margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 16px; background: #ffffff; padding: 20px 24px; border-radius: 16px;
      border: 1px solid var(--fin-border); box-shadow: 0 1px 3px rgba(15,23,42,.03);
    }
    .fin-page-header h1 { font-size: 22px; font-weight: 700; color: #0f172a; margin: 0; letter-spacing: -0.02em; display: flex; align-items: center; }
    .fin-page-header p { color: #64748b; font-size: 13px; margin: 4px 0 0; }

    .fin-tabs {
      display: flex; gap: 8px; background: #ffffff; padding: 8px; border-radius: 14px;
      border: 1px solid var(--fin-border); margin-bottom: 20px; box-shadow: 0 1px 3px rgba(15,23,42,.03);
      overflow-x: auto;
    }
    .fin-tab {
      display: flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px;
      border: none; background: transparent; color: #64748b; font-size: 13.5px; font-weight: 600;
      cursor: pointer; transition: all 0.2s ease; white-space: nowrap; font-family: 'Outfit', sans-serif;
    }
    .fin-tab:hover { color: #0f172a; background: #f1f5f9; }
    .fin-tab.is-active { background: #f05a22; color: #ffffff; box-shadow: 0 2px 6px rgba(240,90,34,0.25); }

    .fin-filterbar {
      background: #ffffff; padding: 16px 20px; border-radius: 14px; border: 1px solid var(--fin-border);
      display: flex; gap: 16px; align-items: center; flex-wrap: wrap; margin-bottom: 24px;
      box-shadow: 0 1px 3px rgba(15,23,42,.03);
    }
    .fin-field label { font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; letter-spacing: 0.03em; }
    .fin-select, .fin-input {
      padding: 8px 14px; border: 1px solid var(--fin-border); border-radius: 10px; font-size: 13px;
      background: #f8fafc; color: #0f172a; font-family: inherit; font-weight: 500; outline: none; transition: all 0.2s ease;
    }
    .fin-select:focus, .fin-input:focus { border-color: #f05a22; background: #fff; box-shadow: 0 0 0 3px rgba(240,90,34,0.12); }
    .fin-btn--primary {
      padding: 8px 18px; border: none; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer;
      background: #f05a22; color: #fff; font-family: inherit; transition: all 0.2s ease; box-shadow: 0 2px 4px rgba(240,90,34,0.25);
    }
    .fin-btn--primary:hover { background: #d94814; transform: translateY(-1px); }
    .fin-btn--ghost {
      padding: 8px 18px; border: 1px solid var(--fin-border); border-radius: 10px; font-size: 13px; font-weight: 600;
      cursor: pointer; background: #ffffff; color: #0f172a; font-family: inherit; transition: all 0.2s ease;
    }
    .fin-btn--ghost:hover { background: #f8fafc; border-color: #cbd5e1; }

    .fin-card {
      background: #ffffff; border: 1px solid var(--fin-border); border-radius: 16px; padding: 22px;
      box-shadow: 0 1px 3px rgba(15,23,42,.03); margin-bottom: 24px; transition: all 0.2s ease;
    }
    .fin-card:hover { box-shadow: 0 6px 16px -4px rgba(15,23,42,.06); }
    .fin-card__head h3 { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; }
    
    .fin-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
    .fin-table th {
      text-align: left; padding: 10px 12px; border-bottom: 2px solid var(--fin-border);
      color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: .05em; background: #f8fafc;
    }
    .fin-table th:first-child { border-top-left-radius: 8px; }
    .fin-table th:last-child { border-top-right-radius: 8px; }
    .fin-table td { padding: 11px 12px; border-bottom: 1px solid var(--fin-border); color: #0f172a; font-weight: 500; }
    .fin-table tbody tr:hover td { background: #f8fafc; }
    .fin-table .ar { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }

    .fin-metric-card {
      background: #ffffff; border: 1px solid var(--fin-border); border-radius: 16px; padding: 20px;
      box-shadow: 0 1px 3px rgba(15,23,42,.03); transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .fin-metric-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px -5px rgba(15,23,42,.08); border-color: #cbd5e1; }
    .fin-metric-card__value { font-size: 26px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em; }
    .fin-metric-card__label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
    .fin-metric-card__icon {
      width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
      background: #fff0eb; color: #f05a22; font-size: 18px;
    }
  </style>
</head>
<body class="admin-page">
   <?php include_once __DIR__ . '/topbar.php'; ?>
   <div class="container">
      <?php include_once __DIR__ . '/sidebar.php'; ?>
      <main class="admin-page-main">
<div class="fin-page" id="finPage">

  <div class="fin-page-header">
    <div>
      <h1><span class="material-symbols-sharp" style="color:#f05a22;vertical-align:middle;margin-right:8px;">account_balance</span> Financial Dashboard</h1>
      <p>Real-time financial performance, balance sheets, cash flow tracking, tax summaries &amp; ledger reporting</p>
    </div>
  </div>

  <!-- ================= FINANCE TABS ================= -->
  <nav class="fin-tabs" id="finTabs" role="tablist" aria-label="Finance sections">

    <button class="fin-tab is-active"
            data-tab="performance"
            role="tab"
            aria-selected="true">
      <span class="fin-tab__icon">
        <i class="fa-solid fa-chart-line"></i>
      </span>
      <span>Performance</span>
    </button>

    <button class="fin-tab"
            data-tab="balance-sheet"
            role="tab"
            aria-selected="false">
      <span class="fin-tab__icon">
        <i class="fa-solid fa-scale-balanced"></i>
      </span>
      <span>Balance Sheet</span>
    </button>

    <button class="fin-tab"
            data-tab="cash-flow"
            role="tab"
            aria-selected="false">
      <span class="fin-tab__icon">
        <i class="fa-solid fa-money-bill-transfer"></i>
      </span>
      <span>Cash Flow</span>
    </button>

    <button class="fin-tab"
            data-tab="tax-summary"
            role="tab"
            aria-selected="false">
      <span class="fin-tab__icon">
        <i class="fa-solid fa-file-invoice-dollar"></i>
      </span>
      <span>Tax Summary</span>
    </button>

    <button class="fin-tab"
            data-tab="insights"
            role="tab"
            aria-selected="false">
      <span class="fin-tab__icon">
        <i class="fa-solid fa-lightbulb"></i>
      </span>
      <span>Insights</span>
    </button>

    <button class="fin-tab"
            data-tab="reports"
            role="tab"
            aria-selected="false">
      <span class="fin-tab__icon">
        <i class="fa-solid fa-file-lines"></i>
      </span>
      <span>Reports</span>
    </button>

  </nav>


  <!-- ================= DATE / FISCAL FILTER ================= -->
  <div class="fin-filterbar">

    <div class="fin-field">
      <label for="finFiscalYear">Fiscal year</label>

      <select id="finFiscalYear" class="fin-select">
        <option value="" selected>
          All dates
        </option>
        <option value="2083-84">
          2083/84 Â· 2026/27
        </option>

        <option value="2082-83">
          2082/83 Â· 2025/26
        </option>

        <option value="2081-82">
          2081/82 Â· 2024/25
        </option>
      </select>
    </div>


    <div class="fin-field">
      <label for="finStartDate">Start date</label>

      <input type="date"
             id="finStartDate"
             class="fin-input">
    </div>


    <div class="fin-field">
      <label for="finEndDate">End date</label>

      <input type="date"
             id="finEndDate"
             class="fin-input">
    </div>


    <div class="fin-field fin-field--actions">

      <button id="finApplyBtn"
              class="fin-btn fin-btn--primary">
        <i class="fa-solid fa-filter"></i>
        <span>Apply</span>
      </button>

      <button id="finAllReportsBtn"
              class="fin-btn fin-btn--ghost">
        <i class="fa-solid fa-file-export"></i>
        <span>All Reports</span>
      </button>

    </div>

  </div>


  <!-- ================= PANELS ================= -->
  <div class="fin-panels">


    <!-- ========================================================
         PERFORMANCE
         ======================================================== -->
    <section class="fin-panel is-active"
             id="panel-performance"
             role="tabpanel">


      <!-- KPI CARDS -->
      <div class="fin-cards-grid" id="perfSummaryCards">


        <!-- Gross Revenue -->
        <div class="fin-metric-card">

          <div class="fin-metric-card__top">
            <span class="fin-metric-card__label">
              Gross Revenue
            </span>

            <span class="fin-metric-card__icon">
              <i class="fa-solid fa-sack-dollar"></i>
            </span>
          </div>

          <span class="fin-metric-card__value"
                data-field="grossRevenue">
            रु 0.00
          </span>

          <span class="fin-metric-card__hint">
            Before discounts
          </span>

        </div>


        <!-- Net Revenue -->
        <div class="fin-metric-card">

          <div class="fin-metric-card__top">
            <span class="fin-metric-card__label">
              Net Revenue
            </span>

            <span class="fin-metric-card__icon">
              <i class="fa-solid fa-chart-line"></i>
            </span>
          </div>

          <span class="fin-metric-card__value"
                data-field="netRevenue">
            रु 0.00
          </span>

          <span class="fin-metric-card__hint">
            After discounts and charges
          </span>

        </div>


        <!-- Net Profit -->
        <div class="fin-metric-card">

          <div class="fin-metric-card__top">
            <span class="fin-metric-card__label">
              Net Profit
            </span>

            <span class="fin-metric-card__icon">
              <i class="fa-solid fa-coins"></i>
            </span>
          </div>

          <span class="fin-metric-card__value"
                data-field="netProfit">
            रु 0.00
          </span>

          <span class="fin-metric-card__hint">
            Revenue less posted expenses
          </span>

        </div>


        <!-- Average Order -->
        <div class="fin-metric-card">

          <div class="fin-metric-card__top">
            <span class="fin-metric-card__label">
              Average Order
            </span>

            <span class="fin-metric-card__icon">
              <i class="fa-solid fa-cart-shopping"></i>
            </span>
          </div>

          <span class="fin-metric-card__value"
                data-field="averageOrder">
            रु 0.00
          </span>

          <span class="fin-metric-card__hint"
                data-field="orderCountHint">
            0 orders
          </span>

        </div>

      </div>


            <!-- DAILY REVENUE -->
      <div class="fin-card">

        <div class="fin-card__head">
          <h3>
            <i class="fa-solid fa-chart-column"></i>
            Daily Orders & Revenue
          </h3>
        </div>

        <div class="fin-chart-wrap">
          <canvas id="chartDailyRevenue"
                  height="220">
          </canvas>
        </div>

        <div class="fin-table-scroll">
          <table class="fin-table" id="tblDailyRevenue">
            <thead>
              <tr>
                <th class="al">Date</th>
                <th class="ar">Orders</th>
                <th class="ar">Revenue</th>
              </tr>
            </thead>
            <tbody>
              <!-- injected -->
            </tbody>
          </table>
        </div>

      </div>


      <!--<!-- REVENUE BY ORDER LIST -->
      <div class="fin-card">

        <div class="fin-card__head fin-card__head--split">

          <h3>
            <i class="fa-solid fa-receipt"></i>
            Revenue by Order
          </h3>

          <div class="fin-card__subtitle">
            <span id="orderCountDisplay">0 orders</span>
            <span class="fin-live-indicator" id="liveIndicator" title="Live updates enabled">
              <span class="fin-live-dot"></span>
              LIVE
            </span>
          </div>

        </div>

        <div class="fin-table-scroll">

          <table class="fin-table"
                 id="tblRevenueByOrder">

            <thead>

              <tr>
                <th class="al">Order #</th>
                <th class="al">Date</th>
                <th class="al">Customer</th>
                <th class="al">Items</th>
                <th class="ar">Amount</th>
                <th class="al">Status</th>
                <th class="al">Payment</th>
              </tr>

            </thead>

            <tbody>
              <!-- injected by JS -->
            </tbody>

          </table>

          <div class="fin-empty-state"
               id="orderListEmpty">
            <i class="fa-regular fa-folder-open"></i>
            <span>No orders found for the selected period.</span>
          </div>

        </div>

      </div>

    </section>


    <!-- ========================================================
         BALANCE SHEET
         ======================================================== -->
    <section class="fin-panel"
             id="panel-balance-sheet"
             role="tabpanel">

      <div class="fin-card">

        <div class="fin-card__head fin-card__head--stack">

          <h3>
            <i class="fa-solid fa-scale-balanced"></i>
            Balance Sheet Ledger
          </h3>

          <span class="fin-card__subtitle">
            Statement of financial position as at
            <span data-field="balanceSheetDate">â€”</span>
          </span>

        </div>

        <div class="fin-table-scroll">

          <table class="fin-table fin-table--accounting"
                 id="tblBalanceSheet">

            <thead>

              <tr>
                <th class="al">Accounting Group</th>
                <th class="ar">Value in (Assets)</th>
                <th class="ar">Value out (Liabilities)</th>
                <th class="ar">Net Balance</th>
              </tr>

            </thead>

            <tbody>
              <!-- injected -->
            </tbody>

          </table>

        </div>

      </div>

    </section>


    <!-- ========================================================
         CASH FLOW
         ======================================================== -->
    <section class="fin-panel"
             id="panel-cash-flow"
             role="tabpanel">


      <!-- CASH FLOW KPI -->
      <div class="fin-cards-grid fin-cards-grid--3">


        <!-- Cash In -->
        <div class="fin-metric-card">

          <div class="fin-metric-card__top">

            <span class="fin-metric-card__label">
              Total Cash In
            </span>

            <span class="fin-metric-card__icon">
              <i class="fa-solid fa-arrow-trend-up"></i>
            </span>

          </div>

          <span class="fin-metric-card__value fin-value--pos"
                data-field="totalCashIn">
            रु 0.00
          </span>

        </div>


        <!-- Cash Out -->
        <div class="fin-metric-card">

          <div class="fin-metric-card__top">

            <span class="fin-metric-card__label">
              Total Cash Out
            </span>

            <span class="fin-metric-card__icon">
              <i class="fa-solid fa-arrow-trend-down"></i>
            </span>

          </div>

          <span class="fin-metric-card__value fin-value--neg"
                data-field="totalCashOut">
            रु 0.00
          </span>

        </div>


        <!-- Net Movement -->
        <div class="fin-metric-card">

          <div class="fin-metric-card__top">

            <span class="fin-metric-card__label">
              Net Movement
            </span>

            <span class="fin-metric-card__icon">
              <i class="fa-solid fa-money-bill-transfer"></i>
            </span>

          </div>

          <span class="fin-metric-card__value"
                data-field="netMovement">
            रु 0.00
          </span>

        </div>

      </div>


      <!-- CASH MOVEMENT LEDGER -->
      <div class="fin-card">

        <div class="fin-card__head">

          <h3>
            <i class="fa-solid fa-money-bill-transfer"></i>
            Cash Movement Ledger
          </h3>

        </div>

        <div class="fin-table-scroll">

          <table class="fin-table"
                 id="tblCashMovement">

            <thead>

              <tr>
                <th class="al">Date</th>
                <th class="al">Voucher</th>
                <th class="al">Narration</th>
                <th class="ar">Cash In</th>
                <th class="ar">Cash Out</th>
                <th class="ar">Running Balance</th>
              </tr>

            </thead>

            <tbody>
              <!-- injected -->
            </tbody>

          </table>

        </div>

      </div>


      <!-- PAYMENT METHOD -->
      <div class="fin-row-grid fin-row-grid--2-1">


        <div class="fin-card">

          <div class="fin-card__head">

            <h3>
              <i class="fa-solid fa-credit-card"></i>
              Payment Method Reconciliation
            </h3>

          </div>

          <div class="fin-table-scroll">

            <table class="fin-table"
                   id="tblPaymentMethod">

              <thead>

                <tr>
                  <th class="al">Method</th>
                  <th class="ar">Transactions</th>
                  <th class="ar">Amount</th>
                  <th class="ar">Share</th>
                </tr>

              </thead>

              <tbody>
                <!-- injected -->
              </tbody>

            </table>

          </div>

        </div>


        <div class="fin-card">

          <div class="fin-card__head">

            <h3>
              <i class="fa-solid fa-chart-pie"></i>
              Payment Method Split
            </h3>

          </div>

          <div class="fin-chart-wrap fin-chart-wrap--donut">

            <canvas id="chartPaymentMethod"
                    height="220">
            </canvas>

          </div>

        </div>

      </div>

    </section>


    <!-- ========================================================
         TAX SUMMARY
         ======================================================== -->
    <section class="fin-panel"
             id="panel-tax-summary"
             role="tabpanel">


      <!-- TAX KPI -->
      <div class="fin-cards-grid"
           id="taxSummaryCards">


        <!-- Taxable Sales -->
        <div class="fin-metric-card">

          <div class="fin-metric-card__top">

            <span class="fin-metric-card__label">
              Taxable Sales
            </span>

            <span class="fin-metric-card__icon">
              <i class="fa-solid fa-receipt"></i>
            </span>

          </div>

          <span class="fin-metric-card__value"
                data-field="taxableSales">
            रु 0.00
          </span>

        </div>


        <!-- VAT Collected -->
        <div class="fin-metric-card">

          <div class="fin-metric-card__top">

            <span class="fin-metric-card__label">
              VAT Collected
            </span>

            <span class="fin-metric-card__icon">
              <i class="fa-solid fa-file-invoice-dollar"></i>
            </span>

          </div>

          <span class="fin-metric-card__value"
                data-field="vatCollected">
            रु 0.00
          </span>

        </div>


        <!-- Taxable Expenses -->
        <div class="fin-metric-card">

          <div class="fin-metric-card__top">

            <span class="fin-metric-card__label">
              Taxable Expenses
            </span>

            <span class="fin-metric-card__icon">
              <i class="fa-solid fa-money-check-dollar"></i>
            </span>

          </div>

          <span class="fin-metric-card__value"
                data-field="taxableExpenses">
            रु 0.00
          </span>

        </div>


        <!-- Input VAT -->
        <div class="fin-metric-card">

          <div class="fin-metric-card__top">

            <span class="fin-metric-card__label">
              Input VAT
            </span>

            <span class="fin-metric-card__icon">
              <i class="fa-solid fa-arrow-down"></i>
            </span>

          </div>

          <span class="fin-metric-card__value"
                data-field="inputVat">
            रु 0.00
          </span>

        </div>


        <!-- Net VAT -->
        <div class="fin-metric-card">

          <div class="fin-metric-card__top">

            <span class="fin-metric-card__label">
              Net VAT Payable
            </span>

            <span class="fin-metric-card__icon">
              <i class="fa-solid fa-calculator"></i>
            </span>

          </div>

          <span class="fin-metric-card__value"
                data-field="netVatPayable">
            रु 0.00
          </span>

        </div>

      </div>


      <!-- TAX TABLE + CHART -->
      <div class="fin-row-grid fin-row-grid--2-1">


        <div class="fin-card">

          <div class="fin-card__head">

            <h3>
              <i class="fa-solid fa-file-invoice-dollar"></i>
              Tax Summary
            </h3>

          </div>

          <div class="fin-table-scroll">

            <table class="fin-table"
                   id="tblTaxSummary">

              <thead>

                <tr>
                  <th class="al">Item</th>
                  <th class="ar">Base Amount</th>
                  <th class="ar">Tax Rate</th>
                  <th class="ar">Tax Amount</th>
                </tr>

              </thead>

              <tbody>
                <!-- injected -->
              </tbody>

            </table>

          </div>

        </div>


        <div class="fin-card">

          <div class="fin-card__head">

            <h3>
              <i class="fa-solid fa-chart-column"></i>
              Output vs Input VAT
            </h3>

          </div>

          <div class="fin-chart-wrap">

            <canvas id="chartTaxSummary"
                    height="220">
            </canvas>

          </div>

        </div>

      </div>

    </section>


    <!-- ========================================================
         INSIGHTS
         ======================================================== -->
    <section class="fin-panel"
             id="panel-insights"
             role="tabpanel">


      <!-- REVENUE CATEGORY -->
      <div class="fin-row-grid fin-row-grid--2-1">


        <div class="fin-card">

          <div class="fin-card__head">

            <h3>
              <i class="fa-solid fa-chart-pie"></i>
              Revenue by Category
            </h3>

          </div>

          <div class="fin-chart-wrap">

            <canvas id="chartCategoryRevenue"
                    height="220">
            </canvas>

          </div>

        </div>


        <!-- TOP CATEGORY -->
        <div class="fin-card">

          <div class="fin-card__head">

            <h3>
              <i class="fa-solid fa-trophy"></i>
              Top Category
            </h3>

          </div>

          <div class="fin-highlight"
               id="topCategoryHighlight">

            <span class="fin-highlight__label"
                  data-field="topCategoryName">
              Drinks
            </span>

            <span class="fin-highlight__value"
                  data-field="topCategoryRevenue">
              रु 0.00
            </span>

            <span class="fin-highlight__share"
                  data-field="topCategoryShare">
              100.0%
            </span>

          </div>

        </div>

      </div>


      <!-- CATEGORY BREAKDOWN -->
      <div class="fin-card">

        <div class="fin-card__head">

          <h3>
            <i class="fa-solid fa-layer-group"></i>
            Category Breakdown
          </h3>

        </div>

        <div class="fin-table-scroll">

          <table class="fin-table"
                 id="tblCategoryBreakdown">

            <thead>

              <tr>
                <th class="al">Category</th>
                <th class="ar">Revenue</th>
                <th class="ar">Qty</th>
                <th class="ar">Avg Unit</th>
                <th class="ar">Share</th>
              </tr>

            </thead>

            <tbody>
              <!-- injected -->
            </tbody>

          </table>

        </div>

      </div>


      <!-- ORDER TYPE -->
      <div class="fin-row-grid fin-row-grid--2-1">


        <div class="fin-card">

          <div class="fin-card__head">

            <h3>
              <i class="fa-solid fa-chart-line"></i>
              Order Type Performance
            </h3>

          </div>

          <div class="fin-table-scroll">

            <table class="fin-table"
                   id="tblOrderTypePerf">

              <thead>

                <tr>
                  <th class="al">Order Type</th>
                  <th class="ar">Orders</th>
                  <th class="ar">Revenue</th>
                  <th class="ar">Average Order</th>
                  <th class="ar">Share</th>
                </tr>

              </thead>

              <tbody>
                <!-- injected -->
              </tbody>

            </table>

          </div>

        </div>


        <div class="fin-card">

          <div class="fin-card__head">

            <h3>
              <i class="fa-solid fa-code-compare"></i>
              Order Type Comparison
            </h3>

          </div>

          <div class="fin-chart-wrap">

            <canvas id="chartOrderTypePerf"
                    height="220">
            </canvas>

          </div>

        </div>

      </div>

    </section>


    <!-- ========================================================
         REPORTS
         ======================================================== -->
    <section class="fin-panel"
             id="panel-reports"
             role="tabpanel">


      <!-- REPORT CARDS -->
      <div class="fin-row-grid fin-row-grid--2-2">


        <!-- PROFIT & LOSS -->
        <div class="fin-card fin-card--report">

          <div class="fin-card__head fin-card__head--compact">

            <h3>
              <i class="fa-solid fa-file-invoice"></i>
              Profit &amp; Loss Statement
            </h3>

          </div>

          <div class="fin-table-scroll">

            <table class="fin-table fin-table--accounting"
                   id="tblProfitLoss">

              <thead>

                <tr>
                  <th class="al">Account</th>
                  <th class="ar">Amount</th>
                </tr>

              </thead>

              <tbody>
                <!-- injected -->
              </tbody>

            </table>

          </div>

        </div>


        <!-- TRIAL BALANCE -->
        <div class="fin-card fin-card--report">

          <div class="fin-card__head fin-card__head--compact">

            <h3>
              <i class="fa-solid fa-scale-balanced"></i>
              Trial Balance
            </h3>

          </div>

          <div class="fin-table-scroll">

            <table class="fin-table fin-table--accounting"
                   id="tblTrialBalance">

              <thead>

                <tr>
                  <th class="al">Account</th>
                  <th class="ar">Debit</th>
                  <th class="ar">Credit</th>
                </tr>

              </thead>

              <tbody>
                <!-- injected -->
              </tbody>

            </table>

          </div>

        </div>

      </div>


      <!-- GENERAL LEDGER -->
      <div class="fin-card">

        <div class="fin-card__head fin-card__head--split">

          <h3>
            <i class="fa-solid fa-book-open"></i>
            General Ledger
          </h3>


          <div class="fin-gl-controls">

            <select id="finGlAccount"
                    class="fin-select">

              <option value="5000">
                5000 Â· Cost of Goods Sold
              </option>

              <option value="4000">
                4000 Â· Food and Beverage Sales
              </option>

              <option value="100">
                100 Â· Cash in Hand
              </option>

              <option value="2000">
                2000 Â· Accounts Payable
              </option>

            </select>


            <button id="finViewLedgerBtn"
                    class="fin-btn fin-btn--primary">

              <i class="fa-solid fa-book-open"></i>
              <span>View Ledger</span>

            </button>

          </div>

        </div>


        <div class="fin-table-scroll">

          <table class="fin-table"
                 id="tblGeneralLedger">

            <thead>

              <tr>
                <th class="al">Date</th>
                <th class="al">Voucher</th>
                <th class="al">Narration</th>
                <th class="ar">Debit</th>
                <th class="ar">Credit</th>
                <th class="ar">Balance</th>
              </tr>

            </thead>

            <tbody>
              <!-- injected -->
            </tbody>

          </table>


          <div class="fin-empty-state"
               id="glEmptyState">

            <i class="fa-regular fa-folder-open"></i>

            <span>
              No ledger transactions found for the selected
              account and period.
            </span>

          </div>

        </div>

      </div>

    </section>

  </div>

</div>

      </main>
   </div>

<!-- Chart.js -->
<script src="../assets/js/chart.umd.js?v=<?= filemtime(__DIR__ . '/../assets/js/chart.umd.js') ?>"></script>
<!-- Finance JavaScript -->
<script src="../assets/js/admin2.js?v=<?= filemtime(__DIR__ . '/../assets/js/admin2.js') ?>"></script>
<script src="../assets/js/adminscript.js"></script>
</body>
</html>
