<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';
$pageTitle = 'Analytics Dashboard';
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
  <link rel="stylesheet" href="../assets/css/admin2.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin2.css') ?>">
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  <link rel="stylesheet" href="../assets/css/panel.css?v=<?= filemtime(__DIR__ . '/../assets/css/panel.css') ?>">
  <style>
    :root {
      --analytics-bg: #f8fafc;
      --analytics-card: #ffffff;
      --analytics-border: #e2e8f0;
      --analytics-text: #0f172a;
      --analytics-muted: #64748b;
      --analytics-primary: #f05a22;
      --analytics-success: #10b981;
      --analytics-warning: #f59e0b;
      --analytics-danger: #ef4444;
      --analytics-info: #06b6d4;
      --analytics-accent: #8b5cf6;
    }
    body.analytics-page { background: var(--analytics-bg); font-family: 'Outfit', sans-serif; color: var(--analytics-text); }
    body.analytics-page .admin-page-main { padding: 28px; max-width: 1600px; margin: 0 auto; }
    
    .analytics-header {
      margin-bottom: 28px; display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 16px; background: #ffffff; padding: 20px 24px; border-radius: 16px;
      border: 1px solid var(--analytics-border); box-shadow: 0 1px 3px rgba(15,23,42,.03);
    }
    .analytics-header h1 { font-size: 22px; font-weight: 700; color: var(--analytics-text); margin: 0; letter-spacing: -0.02em; display: flex; align-items: center; gap: 10px; }
    .analytics-header p { color: var(--analytics-muted); font-size: 13px; margin: 3px 0 0; }
    
    .analytics-filters { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
    .analytics-filters label { font-size: 12px; color: var(--analytics-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
    .analytics-filters select, .analytics-filters input {
      padding: 8px 14px; border: 1px solid var(--analytics-border); border-radius: 10px;
      font-size: 13px; background: #f8fafc; color: var(--analytics-text); font-family: inherit; font-weight: 500;
      outline: none; transition: all .2s ease;
    }
    .analytics-filters select:focus, .analytics-filters input:focus { border-color: var(--analytics-primary); background: #fff; box-shadow: 0 0 0 3px rgba(240,90,34,0.12); }
    .analytics-filters button {
      padding: 8px 18px; border: none; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer;
      background: var(--analytics-primary); color: #fff; font-family: inherit; transition: all .2s ease;
      box-shadow: 0 2px 4px rgba(240,90,34,0.25);
    }
    .analytics-filters button:hover { background: #d94814; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(240,90,34,0.35); }
    
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 18px; margin-bottom: 28px; }
    .kpi-card {
      background: var(--analytics-card); border: 1px solid var(--analytics-border); border-radius: 16px; padding: 20px;
      box-shadow: 0 1px 3px rgba(15,23,42,.03); transition: all .25s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden;
    }
    .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 12px 24px -6px rgba(15,23,42,.08); border-color: #cbd5e1; }
    .kpi-card .kpi-icon {
      width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
      margin-bottom: 14px; font-size: 22px; transition: transform 0.2s ease;
    }
    .kpi-card:hover .kpi-icon { transform: scale(1.08); }
    .kpi-card .kpi-label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--analytics-muted); margin-bottom: 6px; }
    .kpi-card .kpi-value { font-size: 26px; font-weight: 800; color: var(--analytics-text); letter-spacing: -0.02em; }
    .kpi-card .kpi-sub { font-size: 12.5px; color: var(--analytics-muted); margin-top: 4px; font-weight: 500; }
    
    .kpi-icon.primary { background: #fff0eb; color: var(--analytics-primary); }
    .kpi-icon.success { background: #ecfdf5; color: var(--analytics-success); }
    .kpi-icon.warning { background: #fffbeb; color: var(--analytics-warning); }
    .kpi-icon.danger { background: #fef2f2; color: var(--analytics-danger); }
    .kpi-icon.info { background: #ecfeff; color: var(--analytics-info); }
    .kpi-icon.accent { background: #f5f3ff; color: var(--analytics-accent); }
    
    .chart-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(460px, 1fr)); gap: 22px; margin-bottom: 28px; }
    .chart-card {
      background: var(--analytics-card); border: 1px solid var(--analytics-border); border-radius: 16px; padding: 22px;
      box-shadow: 0 1px 3px rgba(15,23,42,.03); transition: all 0.2s ease;
    }
    .chart-card:hover { box-shadow: 0 8px 20px -4px rgba(15,23,42,.06); }
    @media (max-width: 768px) { .chart-grid { grid-template-columns: 1fr; } }
    .chart-card-full { grid-column: 1 / -1; }
    .chart-card h3 { font-size: 16px; font-weight: 700; color: var(--analytics-text); margin: 0 0 4px; letter-spacing: -0.01em; }
    .chart-card p.sub { font-size: 12.5px; color: var(--analytics-muted); margin: 0 0 16px; font-weight: 400; }
    .chart-container { position: relative; width: 100%; height: 280px; }
    .chart-container canvas { width: 100% !important; height: 100% !important; }

    .analytics-table-card {
      background: var(--analytics-card); border: 1px solid var(--analytics-border); border-radius: 16px; padding: 22px;
      box-shadow: 0 1px 3px rgba(15,23,42,.03); margin-bottom: 28px;
    }
    .analytics-table-card h3 { font-size: 16px; font-weight: 700; margin: 0 0 4px; color: var(--analytics-text); }
    .analytics-table-card p.sub { font-size: 12.5px; color: var(--analytics-muted); margin: 0 0 16px; }
    .at-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
    .at-table th {
      text-align: left; padding: 10px 12px; border-bottom: 2px solid var(--analytics-border);
      color: var(--analytics-muted); font-weight: 600; text-transform: uppercase; font-size: 11px;
      letter-spacing: .05em; background: #f8fafc;
    }
    .at-table th:first-child { border-top-left-radius: 8px; }
    .at-table th:last-child { border-top-right-radius: 8px; }
    .at-table td { padding: 11px 12px; border-bottom: 1px solid var(--analytics-border); color: var(--analytics-text); font-weight: 500; }
    .at-table tbody tr:hover td { background: #f8fafc; }
    .at-table .num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }

    .insights-card {
      background: var(--analytics-card); border: 1px solid var(--analytics-border); border-radius: 16px; padding: 22px;
      margin-bottom: 28px; box-shadow: 0 1px 3px rgba(15,23,42,.03);
    }
    .insights-card h3 { font-size: 16px; font-weight: 700; margin: 0 0 16px; color: var(--analytics-text); display: flex; align-items: center; gap: 8px; }
    .insight-item { display: flex; gap: 14px; padding: 14px 0; border-bottom: 1px solid var(--analytics-border); align-items: flex-start; }
    .insight-item:last-child { border-bottom: none; }
    .insight-item .i-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 20px; }
    .insight-item .i-content { flex: 1; }
    .insight-item .i-title { font-size: 14px; font-weight: 600; color: var(--analytics-text); margin-bottom: 3px; }
    .insight-item .i-text { font-size: 13px; color: var(--analytics-muted); line-height: 1.5; }
    .bg-d { background: #fef2f2; color: var(--analytics-danger); }
    .bg-s { background: #ecfdf5; color: var(--analytics-success); }
    .bg-w { background: #fffbeb; color: var(--analytics-warning); }
    .bg-p { background: #eff6ff; color: var(--analytics-primary); }

    .loading-overlay {
      position: fixed; inset: 0; background: rgba(255,255,255,.85); backdrop-filter: blur(4px);
      display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 600;
      color: var(--analytics-text); z-index: 100; gap: 10px;
    }
  </style>
</head>
<body class="admin-page analytics-page">
  <?php include_once __DIR__ . '/topbar.php'; ?>
  <div class="container">
    <?php include_once __DIR__ . '/sidebar.php'; ?>
    <main class="admin-page-main">

      <div class="analytics-header">
        <div>
          <h1><span class="material-symbols-sharp" style="color:var(--analytics-primary);">monitoring</span> Analytics Dashboard</h1>
          <p>Real-time performance insights, sales trends &amp; operational metrics for Mero Bhoj</p>
        </div>
        <div class="analytics-filters">
          <label for="rangeSelect">Range:</label>
          <select id="rangeSelect">
            <option value="7days">Last 7 days</option>
            <option value="30days" selected>Last 30 days</option>
            <option value="month">This month</option>
            <option value="all">All time</option>
          </select>
          <label for="startDate">From:</label>
          <input type="date" id="startDate">
          <label for="endDate">To:</label>
          <input type="date" id="endDate">
          <button id="applyFilters"><span class="material-symbols-sharp" style="font-size:16px;vertical-align:middle;margin-right:2px;">filter_alt</span> Apply</button>
        </div>
      </div>

      <div class="loading-overlay" id="loadingOverlay">
        <span class="material-symbols-sharp" style="animation:spin 1s linear infinite;">sync</span> Loading analytics data...
      </div>

      <section id="kpiSection" class="kpi-grid"></section>

      <section class="chart-grid">
        <div class="chart-card chart-card-full">
          <h3>Daily Revenue Trend</h3>
          <p class="sub">Gross revenue trajectories over the selected timeframe</p>
          <div class="chart-container">
            <canvas id="revenueTrendChart"></canvas>
          </div>
        </div>
        <div class="chart-card">
          <h3>Orders by Hour (Peak Hours)</h3>
          <p class="sub">Distribution of customer orders across 24 hours</p>
          <div class="chart-container">
            <canvas id="peakHoursChart"></canvas>
          </div>
        </div>
        <div class="chart-card">
          <h3>Revenue by Meal Period</h3>
          <p class="sub">Breakfast, Lunch, Snacks &amp; Dinner revenue share</p>
          <div class="chart-container">
            <canvas id="mealPeriodsChart"></canvas>
          </div>
        </div>
      </section>

      <section class="chart-grid">
        <div class="chart-card">
          <h3>Order Type Breakdown</h3>
          <p class="sub">Dine-in vs Delivery vs QR order share</p>
          <div class="chart-container">
            <canvas id="orderTypesChart"></canvas>
          </div>
        </div>
        <div class="chart-card">
          <h3>Payment Method Mix</h3>
          <p class="sub">Revenue distribution by payment option</p>
          <div class="chart-container">
            <canvas id="paymentMethodsChart"></canvas>
          </div>
        </div>
        <div class="chart-card">
          <h3>Top Selling Items</h3>
          <p class="sub">Highest earning menu items by revenue</p>
          <div class="chart-container">
            <canvas id="topItemsChart"></canvas>
          </div>
        </div>
      </section>

      <section class="chart-grid">
        <div class="chart-card chart-card-full">
          <h3>Table Turnover &amp; Revenue</h3>
          <p class="sub">Avg <span id="avgTurnStr" style="font-weight:700;color:var(--analytics-text);">0</span> turns/table/day · Seat utilization <span id="avgUtilStr" style="font-weight:700;color:var(--analytics-text);">0</span>%</p>
          <div class="chart-container">
            <canvas id="tableTurnoverChart"></canvas>
          </div>
        </div>
      </section>

      <section class="insights-card">
        <h3><span class="material-symbols-sharp" style="color:var(--analytics-accent);">auto_awesome</span> Operational &amp; Management Insights</h3>
        <div id="insightsList"></div>
      </section>

      <section class="chart-grid">
        <div class="chart-card">
          <h3>Reservation Health</h3>
          <p class="sub">Table booking fulfillment status</p>
          <div class="chart-container">
            <canvas id="reservationHealthChart"></canvas>
          </div>
        </div>
        <div class="chart-card">
          <h3>Day-of-Week Revenue</h3>
          <p class="sub">Weekly revenue pattern distribution</p>
          <div class="chart-container">
            <canvas id="dayOfWeekChart"></canvas>
          </div>
        </div>
      </section>

      <section class="chart-grid">
        <div class="analytics-table-card">
          <h3>Top Menu Items</h3>
          <p class="sub">Ranked by overall sales revenue</p>
          <div id="topItemsTable"></div>
        </div>
        <div class="analytics-table-card">
          <h3>Table Turnover Detail</h3>
          <p class="sub">Per-table performance metrics</p>
          <div id="tableTurnoverTable"></div>
        </div>
      </section>

    </main>
  </div>

  <style>
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
  </style>

  <script src="../assets/js/chart.umd.js?v=<?= filemtime(__DIR__ . '/../assets/js/chart.umd.js') ?>"></script>
  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
  <script>
  (function () {
    "use strict";
    var apiUrl = '../admin/api/analytics_data.php';
    var rangeSelect = document.getElementById('rangeSelect');
    var startDateInput = document.getElementById('startDate');
    var endDateInput = document.getElementById('endDate');
    var applyBtn = document.getElementById('applyFilters');
    var loadingOverlay = document.getElementById('loadingOverlay');
    var kpiSection = document.getElementById('kpiSection');

    // Chart instance registry for clean re-rendering
    var chartInstances = {};

    var presetRanges = {
      '7days': function () { var e = new Date(); var s = new Date(); s.setDate(e.getDate() - 6); return [fmt(s), fmt(e)]; },
      '30days': function () { var e = new Date(); var s = new Date(); s.setDate(e.getDate() - 29); return [fmt(s), fmt(e)]; },
      'month': function () { var e = new Date(); var s = new Date(); s.setDate(1); return [fmt(s), fmt(e)]; },
      'all': function () { return ['', '']; }
    };

    function fmt(d) {
      var m = '' + (d.getMonth() + 1); var day = '' + d.getDate();
      m = m.length < 2 ? '0' + m : m; day = day.length < 2 ? '0' + day : day;
      return d.getFullYear() + '-' + m + '-' + day;
    }

    function loadAnalytics() {
      loadingOverlay.style.display = 'flex';
      if (typeof Chart === 'undefined') {
        loadingOverlay.textContent = 'Chart.js failed to load (assets/js/chart.umd.js). Check that the file exists.';
        return;
      }
      var params = new URLSearchParams();
      params.set('range', rangeSelect.value);
      if (startDateInput.value) params.set('startDate', startDateInput.value);
      if (endDateInput.value) params.set('endDate', endDateInput.value);

      fetch(apiUrl + '?' + params.toString())
        .then(function (r) { return r.json(); })
        .then(function (data) { renderDashboard(data); })
        .catch(function (err) { console.error(err); loadingOverlay.textContent = 'Error loading data: ' + (err && err.message ? err.message : err); })
        .finally(function () { loadingOverlay.style.display = 'none'; });
    }

    var iconMapKpi = {
      'primary': 'payments',
      'success': 'shopping_bag',
      'warning': 'trending_up',
      'info': 'fastfood',
      'accent': 'event_seat',
      'danger': 'group'
    };

    function kpiCard(label, value, suffix, prefix, iconKey, sub) {
      var displayVal = value;
      if (prefix === 'Rs') displayVal = 'Rs ' + Number(value).toLocaleString();
      if (suffix) displayVal = displayVal + suffix;
      var icon = iconMapKpi[iconKey] || 'analytics';

      return '<div class="kpi-card">' +
               '<div class="kpi-icon ' + iconKey + '"><span class="material-symbols-sharp">' + icon + '</span></div>' +
               '<div class="kpi-label">' + label + '</div>' +
               '<div class="kpi-value">' + displayVal + '</div>' +
               (sub ? '<div class="kpi-sub">' + sub + '</div>' : '') +
             '</div>';
    }

    function makeChart(canvasId, cfg) {
      if (chartInstances[canvasId]) {
        chartInstances[canvasId].destroy();
      }
      var canvas = document.getElementById(canvasId);
      if (!canvas) return null;
      var ctx = canvas.getContext('2d');

      var defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 750, easing: 'easeOutQuart' },
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              font: { family: "'Outfit', sans-serif", size: 12, weight: 500 },
              padding: 14,
              usePointStyle: true,
              pointStyle: 'circle'
            }
          },
          tooltip: {
            backgroundColor: '#0f172a',
            titleFont: { family: "'Outfit', sans-serif", size: 13, weight: 600 },
            bodyFont: { family: "'Outfit', sans-serif", size: 12 },
            padding: 10,
            cornerRadius: 8,
            boxPadding: 4
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { font: { family: "'Outfit', sans-serif", size: 11 }, color: '#64748b' }
          },
          y: {
            grid: { color: '#f1f5f9' },
            ticks: { font: { family: "'Outfit', sans-serif", size: 11 }, color: '#64748b' }
          }
        }
      };

      // Deep merge options
      var mergedOptions = Object.assign({}, defaultOptions, cfg.opts || {});
      if (cfg.opts && cfg.opts.scales) {
        mergedOptions.scales = Object.assign({}, defaultOptions.scales, cfg.opts.scales);
      }
      if (cfg.opts && cfg.opts.plugins) {
        mergedOptions.plugins = Object.assign({}, defaultOptions.plugins, cfg.opts.plugins);
      }

      chartInstances[canvasId] = new Chart(ctx, {
        type: cfg.type,
        data: cfg.data,
        options: mergedOptions
      });

      return chartInstances[canvasId];
    }

    function renderDashboard(data) {
      if (!data.success) { loadingOverlay.textContent = 'Error: ' + (data.error || 'Unknown'); return; }
      var k = data.kpis;
      kpiSection.innerHTML = [
        kpiCard('Revenue', k.grossRevenue, '', 'Rs', 'primary', null),
        kpiCard('Orders', k.totalOrders, '', null, 'success', 'Avg Rs ' + k.avgOrderValue),
        kpiCard('Avg Order Value', k.avgOrderValue, '', 'Rs', 'warning', k.totalOrders + ' total orders'),
        kpiCard('Items Sold', k.totalItemsSold, '', null, 'info', 'Customers: ' + k.uniqueCustomers),
        kpiCard('Avg Table Turnover', k.avgTableTurnover, ' turns/day', null, 'accent', 'Avg dine: ' + k.avgDiningDurationMins + ' mins'),
        kpiCard('Table Utilization', k.tableUtilizationPct + '%', '', null, 'primary', k.totalTableTurns + ' turns recorded'),
        kpiCard('Bookings', k.totalBookings, '', null, 'success', k.totalGuests + ' total guests'),
        kpiCard('Peak Hour', k.peakHour, '', null, 'warning', k.peakHourOrders + ' orders at peak')
      ].join('');

      buildCharts(data);
      renderTables(data);
      renderInsights(data.insights);
    }

    function buildCharts(data) {
      // 1. Revenue Trend Line Chart with Smooth Gradient Fill
      var revCanvas = document.getElementById('revenueTrendChart');
      var revGrad = null;
      if (revCanvas) {
        var ctxRev = revCanvas.getContext('2d');
        revGrad = ctxRev.createLinearGradient(0, 0, 0, 260);
        revGrad.addColorStop(0, 'rgba(240, 90, 34, 0.35)');
        revGrad.addColorStop(1, 'rgba(240, 90, 34, 0.00)');
      }

      makeChart('revenueTrendChart', {
        type: 'line',
        data: {
          labels: data.salesTrend.map(function (d) { return d.label; }),
          datasets: [{
            label: 'Revenue (Rs)',
            data: data.salesTrend.map(function (d) { return d.revenue; }),
            borderColor: '#f05a22',
            borderWidth: 3,
            backgroundColor: revGrad || 'rgba(240,90,34,0.1)',
            tension: 0.4,
            fill: true,
            pointRadius: 4,
            pointBackgroundColor: '#ffffff',
            pointBorderColor: '#f05a22',
            pointBorderWidth: 2,
            pointHoverRadius: 7,
            pointHoverBackgroundColor: '#f05a22'
          }]
        },
        opts: {
          scales: {
            y: { ticks: { callback: function (v) { return 'Rs ' + Number(v).toLocaleString(); } } }
          }
        }
      });

      // 2. Peak Hours Bar Chart
      makeChart('peakHoursChart', {
        type: 'bar',
        data: {
          labels: data.peakHours.map(function (d) { return d.label; }),
          datasets: [{
            label: 'Orders',
            data: data.peakHours.map(function (d) { return d.orders; }),
            backgroundColor: '#f05a22',
            borderRadius: 6,
            maxBarThickness: 32
          }]
        },
        opts: { scales: { y: { ticks: { precision: 0 } } } }
      });

      // 3. Meal Periods Bar Chart
      makeChart('mealPeriodsChart', {
        type: 'bar',
        data: {
          labels: data.mealPeriods.map(function (d) { return d.label; }),
          datasets: [{
            label: 'Orders',
            data: data.mealPeriods.map(function (d) { return d.orders; }),
            backgroundColor: ['#f59e0b', '#10b981', '#f05a22', '#8b5cf6'],
            borderRadius: 8,
            maxBarThickness: 44
          }]
        },
        opts: { scales: { y: { ticks: { precision: 0 } } } }
      });

      // 4. Order Types Doughnut Chart
      makeChart('orderTypesChart', {
        type: 'doughnut',
        data: {
          labels: data.orderTypes.map(function (d) { return d.label; }),
          datasets: [{
            data: data.orderTypes.map(function (d) { return d.count; }),
            backgroundColor: ['#f05a22', '#10b981', '#f59e0b', '#8b5cf6'],
            borderWidth: 3,
            borderColor: '#ffffff',
            hoverOffset: 6
          }]
        },
        opts: { cutout: '70%', scales: { x: { display: false }, y: { display: false } } }
      });

      // 5. Payment Methods Doughnut Chart
      makeChart('paymentMethodsChart', {
        type: 'doughnut',
        data: {
          labels: data.paymentMethods.map(function (d) { return d.method; }),
          datasets: [{
            data: data.paymentMethods.map(function (d) { return d.orders; }),
            backgroundColor: ['#10b981', '#ef4444', '#f59e0b', '#06b6d4', '#8b5cf6', '#ec4899'],
            borderWidth: 3,
            borderColor: '#ffffff',
            hoverOffset: 6
          }]
        },
        opts: { cutout: '70%', scales: { x: { display: false }, y: { display: false } } }
      });

      // 6. Top Items Horizontal Bar Chart
      makeChart('topItemsChart', {
        type: 'bar',
        data: {
          labels: data.topItems.map(function (d) { return d.name; }),
          datasets: [{
            label: 'Revenue (Rs)',
            data: data.topItems.map(function (d) { return d.revenue; }),
            backgroundColor: '#10b981',
            borderRadius: 6,
            maxBarThickness: 28
          }]
        },
        opts: {
          indexAxis: 'y',
          scales: {
            x: { ticks: { callback: function (v) { return 'Rs ' + Number(v).toLocaleString(); } } },
            y: { grid: { display: false } }
          }
        }
      });

      // 7. Table Turnover Combined Bar Chart
      makeChart('tableTurnoverChart', {
        type: 'bar',
        data: {
          labels: data.tableTurnover.map(function (t) { return t.name; }),
          datasets: [
            { label: 'Total Turns', data: data.tableTurnover.map(function (t) { return t.total_turns; }), backgroundColor: '#8b5cf6', borderRadius: 6, maxBarThickness: 28 },
            { label: 'Revenue (Rs)', data: data.tableTurnover.map(function (t) { return t.revenue; }), backgroundColor: 'rgba(37,99,235,0.7)', borderRadius: 6, maxBarThickness: 28 }
          ]
        }
      });

      // 8. Reservation Health Doughnut Chart
      var bh = data.bookingHealth;
      makeChart('reservationHealthChart', {
        type: 'doughnut',
        data: {
          labels: ['Completed', 'Confirmed', 'Cancelled', 'No-show'],
          datasets: [{
            data: [bh.completed, bh.confirmed, bh.cancelled, bh.noshow],
            backgroundColor: ['#10b981', '#3b82f6', '#ef4444', '#f59e0b'],
            borderWidth: 3,
            borderColor: '#ffffff',
            hoverOffset: 6
          }]
        },
        opts: { cutout: '70%', scales: { x: { display: false }, y: { display: false } } }
      });

      // 9. Day of Week Revenue Bar Chart
      makeChart('dayOfWeekChart', {
        type: 'bar',
        data: {
          labels: Object.keys(data.dayOfWeek).map(function (k) { return k.substring(0, 3); }),
          datasets: [{
            label: 'Revenue (Rs)',
            data: Object.keys(data.dayOfWeek).map(function (k) { return data.dayOfWeek[k].revenue; }),
            backgroundColor: '#6366f1',
            borderRadius: 8,
            maxBarThickness: 36
          }]
        },
        opts: {
          scales: {
            y: { ticks: { callback: function (v) { return 'Rs ' + Number(v).toLocaleString(); } } }
          }
        }
      });

      document.getElementById('avgTurnStr').textContent = data.kpis.avgTableTurnover;
      document.getElementById('avgUtilStr').textContent = data.kpis.tableUtilizationPct;
    }

    function renderTables(data) {
      renderTable(document.getElementById('topItemsTable'), data.topItems, ['rank', 'name', 'quantity', 'revenue', 'share_pct'], ['#', 'Item', 'Qty', 'Revenue', 'Share'], 'Rs');
      renderTable(document.getElementById('tableTurnoverTable'), data.tableTurnover, ['name', 'total_turns', 'revenue', 'turnover_rate_per_day', 'utilization_pct'], ['Table', 'Turns', 'Revenue', 'Turns/Day', 'Utilization'], 'Rs');
    }

    function renderTable(container, rows, cols, headers, currencyFmt) {
      if (!rows || !rows.length) {
        container.innerHTML = '<p style="font-size:13px;color:var(--analytics-muted);margin:0">No data available for this period.</p>';
        return;
      }
      var thead = headers.map(function (h) { return '<th>' + h + '</th>'; }).join('');
      var html = '<table class="at-table"><thead><tr>' + thead + '</tr></thead><tbody>';
      rows.forEach(function (r) {
        html += '<tr>' + cols.map(function (c) {
          var v = r[c];
          var txt;
          if (c === 'revenue') txt = 'Rs ' + Number(v || 0).toLocaleString();
          else if (c === 'share_pct' || c === 'utilization_pct') txt = Number(v || 0).toFixed(1) + '%';
          else if (c === 'turnover_rate_per_day' || c === 'avg_turn_rev') txt = Number(v || 0).toFixed(2);
          else txt = (v === null || v === undefined) ? '' : v;
          var isNum = (c !== 'name');
          return '<td' + (isNum ? ' class="num"' : '') + '>' + txt + '</td>';
        }).join('') + '</tr>';
      });
      html += '</tbody></table>';
      container.innerHTML = html;
    }

    function renderInsights(insights) {
      if (!insights || !insights.length) {
        document.getElementById('insightsList').innerHTML = '<p style="font-size:13px;color:var(--analytics-muted);margin:0">No insights generated for this period.</p>';
        return;
      }
      var iconMap = { peak_hours: 'schedule', table_turnover: 'table_restaurant', meal_periods: 'restaurant', top_performer: 'stars' };
      var colorMap = { danger: 'bg-d', success: 'bg-s', warning: 'bg-w', primary: 'bg-p' };
      document.getElementById('insightsList').innerHTML = insights.map(function (ins) {
        return '<div class="insight-item">' +
                 '<div class="i-icon ' + (colorMap[ins.color] || 'bg-p') + '"><span class="material-symbols-sharp">' + (iconMap[ins.type] || 'info') + '</span></div>' +
                 '<div class="i-content">' +
                   '<div class="i-title">' + ins.title + '</div>' +
                   '<div class="i-text">' + ins.text + '</div>' +
                 '</div>' +
               '</div>';
      }).join('');
    }

    var today = new Date();
    startDateInput.value = presetRanges['30days']()[0];
    endDateInput.value = fmt(today);
    applyBtn.addEventListener('click', loadAnalytics);
    rangeSelect.addEventListener('change', function () {
      var p = presetRanges[this.value];
      if (p) { var vals = p(); startDateInput.value = vals[0]; endDateInput.value = vals[1]; }
    });
    loadAnalytics();
  })();
  </script>
</body>
</html>
