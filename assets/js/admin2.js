function togglePw() {
  const pwI = document.getElementById('pw');
  if (!pwI) return;
  const show = pwI.type === 'password';
  pwI.type = show ? 'text' : 'password';
  const eye = document.getElementById('eyeIco');
  if (eye) eye.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}

document.addEventListener('click', function (e) {
  const submitBtn = e.target.closest('#submitBtn');
  if (!submitBtn) return;
  const r = document.createElement('span');
  r.className = 'ripple';
  const sz = Math.max(submitBtn.offsetWidth, submitBtn.offsetHeight);
  const rc = submitBtn.getBoundingClientRect();
  const cx = e.clientX - rc.left;
  const cy = e.clientY - rc.top;
  r.style.width = r.style.height = sz + 'px';
  r.style.left = cx - sz / 2 + 'px';
  r.style.top = cy - sz / 2 + 'px';
  submitBtn.appendChild(r);
  setTimeout(() => r.remove(), 550);
});

document.addEventListener('DOMContentLoaded', function () {
  const msgEl = document.getElementById('admin-session-msg');
  if (msgEl && window.ToastNotifications) {
    const msg = msgEl.dataset.message || '';
    const type = msgEl.dataset.type || 'error';
    if (msg) {
      if (type === 'success') ToastNotifications.success(msg);
      else ToastNotifications.error(msg);
    }
  }

  const dashboardRefresh = () => {
    const revenueElement = document.querySelector('.total-revenue-display');
    const ordersElement = document.querySelector('.total-orders-display');
    if (!revenueElement && !ordersElement) return;
    fetch('get_dashboard_stats.php')
      .then(r => r.json())
      .then(data => {
        if (!data.success) return;
        if (revenueElement && data.stats?.total_revenue !== undefined) {
          revenueElement.textContent = 'Rs ' + Number(data.stats.total_revenue).toFixed(2);
        }
        if (ordersElement && data.stats?.total_orders !== undefined) {
          ordersElement.textContent = data.stats.total_orders;
        }
      })
      .catch(() => {});
  };
  if (document.querySelector('.total-revenue-display') || document.querySelector('.total-orders-display')) {
    setInterval(dashboardRefresh, 30000);
  }

  if (document.body.classList.contains('admin-page') && document.querySelector('.total-revenue-display')) {
    dashboardRefresh();
  }

  window.openModal = window.openModal || function () {};
  window.closeMenuModal = window.closeMenuModal || function () {};
  window.loadMenuItemData = window.loadMenuItemData || function () {};
  window.showDeleteConfirmation = window.showDeleteConfirmation || function () {};
  window.deleteMenuItem = window.deleteMenuItem || function () {};
  window.selectedIds = window.selectedIds || function () { return []; };
  window.updateSelectedCount = window.updateSelectedCount || function () {};
  window.bulkChangeStatus = window.bulkChangeStatus || function () {};
  window.deleteSelected = window.deleteSelected || function () {};
  window.toggleAdminOrderItems = window.toggleAdminOrderItems || function () {};
  window.handleOrderUpdate = window.handleOrderUpdate || function () {};
  window.handleOrderDelete = window.handleOrderDelete || function () {};
  window.showDeleteConfirmation = window.showDeleteConfirmation || function () {};
  window.closeDeleteConfirmation = window.closeDeleteConfirmation || function () {};
  window.confirmDelete = window.confirmDelete || function () {};
  window.showFullAddress = window.showFullAddress || function () {};
  window.closeAddressModal = window.closeAddressModal || function () {};
  window.switchView = window.switchView || function () {};
  window.clearAllFilters = window.clearAllFilters || function () {};
  window.applyBookingFilters = window.applyBookingFilters || function () {};
  window.updateInsights = window.updateInsights || function () {};
  window.renderList = window.renderList || function () {};
  window.renderCalendar = window.renderCalendar || function () {};
  window.renderAvailability = window.renderAvailability || function () {};
  window.prevPage = window.prevPage || function () {};
  window.nextPage = window.nextPage || function () {};
  window.gotoPage = window.gotoPage || function () {};
  window.changeLimit = window.changeLimit || function () {};
  window.updateGraceTimers = window.updateGraceTimers || function () {};
  window.updateFilters = window.updateFilters || function () {};
  window.applyFilters = window.applyFilters || function () {};
  window.exportCSV = window.exportCSV || function () {};
  window.exportPDF = window.exportPDF || function () {};
});



// finance start



(function () {
  "use strict";

  /* ---------------------------------------------------------
     0. Utilities
  --------------------------------------------------------- */
  function formatCurrency(amount) {
    const n = Number(amount) || 0;
    const sign = n < 0 ? "-" : "";
    return sign + "रु " + Math.abs(n).toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function formatCurrencyParen(amount) {
    // Accounting style: negative values in parentheses, e.g. (रु 0.00)
    const n = Number(amount) || 0;
    if (n === 0) return "रु 0.00";
    return n < 0 ? "(" + formatCurrency(Math.abs(n)) + ")" : formatCurrency(n);
  }

  function formatPercent(n) {
    return (Number(n) || 0).toFixed(1) + "%";
  }

  function el(tag, className, html) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (html !== undefined) node.innerHTML = html;
    return node;
  }

  function setField(root, name, value) {
    const node = root.querySelector('[data-field="' + name + '"]');
    if (node) node.textContent = value;
  }

  /* ---------------------------------------------------------
     1. Mock data source
     In production, replace this function's body with a fetch()
     to a PHP endpoint, e.g.:
       return fetch(`api/finance.php?start=${start}&end=${end}&fy=${fy}`)
         .then(r => r.json());
     The rest of the app only depends on the shape below, which
     mirrors what the orders / order_items / payments / expenses /
     accounting_ledger tables would supply.
  --------------------------------------------------------- */
  function fetchFinanceData(filters) {
    const params = new URLSearchParams({
      fiscalYear: filters.fiscalYear || '',
      startDate: filters.startDate || '',
      endDate: filters.endDate || ''
    });
    return fetch('finance_data.php?' + params.toString(), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(r => r.json())
      .then(data => {
        if (!data.success) {
          throw new Error(data.message || 'Failed to fetch finance data');
        }
        return data;
      });
  }

  /* ---------------------------------------------------------
     2. Reusable calculation functions
  --------------------------------------------------------- */
  function calcGrossRevenue(data) {
    return data.orders.reduce((sum, o) => sum + o.grossAmount, 0);
  }

  function calcDiscounts(data) {
    return data.orders.reduce((sum, o) => sum + (o.discount || 0), 0);
  }

  function calcNetRevenue(data) {
    return calcGrossRevenue(data) - calcDiscounts(data);
  }

  function calcExpenses(data) {
    return data.expenses.reduce((sum, e) => sum + e.amount, 0);
  }

  function calcNetProfit(data) {
    return calcNetRevenue(data) - calcExpenses(data);
  }

  function calcAverageOrder(data) {
    const count = data.orders.length;
    return count ? calcGrossRevenue(data) / count : 0;
  }

  function calcCategoryRevenue(data) {
    const map = {};
    data.orders.forEach(o => {
      (o.items || []).forEach(it => {
        const revenue = it.qty * it.unitPrice;
        if (!map[it.category]) map[it.category] = { revenue: 0, qty: 0 };
        map[it.category].revenue += revenue;
        map[it.category].qty += it.qty;
      });
    });
    const total = Object.values(map).reduce((s, c) => s + c.revenue, 0) || 1;
    return Object.entries(map).map(([category, v]) => ({
      category,
      revenue: v.revenue,
      qty: v.qty,
      avgUnit: v.qty ? v.revenue / v.qty : 0,
      share: (v.revenue / total) * 100
    })).sort((a, b) => b.revenue - a.revenue);
  }

  function calcOrderTypeBreakdown(data) {
    const map = {};
    data.orders.forEach(o => {
      if (!map[o.orderType]) map[o.orderType] = { revenue: 0, orders: 0 };
      map[o.orderType].revenue += o.grossAmount;
      map[o.orderType].orders += 1;
    });
    const total = Object.values(map).reduce((s, v) => s + v.revenue, 0) || 1;
    return Object.entries(map).map(([orderType, v]) => ({
      orderType,
      revenue: v.revenue,
      orders: v.orders,
      average: v.orders ? v.revenue / v.orders : 0,
      share: (v.revenue / total) * 100
    })).sort((a, b) => b.revenue - a.revenue);
  }

  function calcPaymentMethodTotals(data) {
    const map = {};
    data.cashVouchers.forEach(v => {
      const method = v.method || "Cash";
      if (!map[method]) map[method] = { amount: 0, transactions: 0 };
      map[method].amount += v.cashIn;
      map[method].transactions += 1;
    });
    const total = Object.values(map).reduce((s, v) => s + v.amount, 0) || 1;
    return Object.entries(map).map(([method, v]) => ({
      method,
      amount: v.amount,
      transactions: v.transactions,
      share: (v.amount / total) * 100
    })).sort((a, b) => b.amount - a.amount);
  }

  function calcDailyRevenueSeries(data) {
    const map = {};
    data.orders.forEach(o => {
      if (!map[o.date]) map[o.date] = 0;
      map[o.date] += o.grossAmount;
    });
    return Object.entries(map)
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([date, revenue]) => ({ date, revenue }));
  }

  function calcCashFlowTotals(data) {
    const cashIn = data.cashVouchers.reduce((s, v) => s + v.cashIn, 0);
    const cashOut = data.cashVouchers.reduce((s, v) => s + v.cashOut, 0);
    return { cashIn, cashOut, net: cashIn - cashOut };
  }

  function calcTaxTotals(data) {
    const vatCollected = data.taxSummary
      .filter(t => t.item === "Taxable Sales")
      .reduce((s, t) => s + t.tax, 0);
    const inputVat = data.taxSummary
      .filter(t => t.item === "Taxable Expenses")
      .reduce((s, t) => s + t.tax, 0);
    const taxableSales = data.taxSummary
      .filter(t => t.item === "Taxable Sales")
      .reduce((s, t) => s + t.base, 0);
    const taxableExpenses = data.taxSummary
      .filter(t => t.item === "Taxable Expenses")
      .reduce((s, t) => s + t.base, 0);
    return {
      taxableSales, vatCollected, taxableExpenses, inputVat,
      netVatPayable: vatCollected - inputVat
    };
  }

  function formatDisplayDate(dateStr) {
    const d = new Date(dateStr + "T00:00:00");
    if (isNaN(d)) return dateStr;
    return d.toLocaleDateString("en-US", { day: "2-digit", month: "short" });
  }

  function formatDisplayDateFull(dateStr) {
    const d = new Date(dateStr + "T00:00:00");
    if (isNaN(d)) return dateStr;
    return d.toLocaleDateString("en-US", { day: "2-digit", month: "short", year: "numeric" });
  }

  function weekdayName(dateStr) {
    const d = new Date(dateStr + "T00:00:00");
    if (isNaN(d)) return "";
    return d.toLocaleDateString("en-US", { weekday: "long" });
  }

  /* ---------------------------------------------------------
     3. Chart palette + registry (so charts can be destroyed
        and recreated cleanly when filters change)
  --------------------------------------------------------- */
  const PALETTE = ["#ef233c", "#1a2233", "#667085", "#16794a", "#c98a2c", "#4a6fa5"];
  const charts = {};

  function destroyChart(key) {
    if (charts[key]) {
      charts[key].destroy();
      delete charts[key];
    }
  }

  function isChartAvailable() {
    return typeof window.Chart !== "undefined";
  }

  /* ---------------------------------------------------------
     4. Renderers — one per panel section
  --------------------------------------------------------- */
  const root = document.getElementById("finPage");
  if (!root) return; // defensive: content area not present on this page

  function renderPerformance(data) {
    const grossRevenue = calcGrossRevenue(data);
    const netRevenue = calcNetRevenue(data);
    const discounts = calcDiscounts(data);
    const expenses = calcExpenses(data);
    const netProfit = calcNetProfit(data);
    const avgOrder = calcAverageOrder(data);
    const orderCount = data.orders.length;

    setField(root, "grossRevenue", formatCurrency(grossRevenue));
    setField(root, "netRevenue", formatCurrency(netRevenue));
    setField(root, "netProfit", formatCurrency(netProfit));
    setField(root, "averageOrder", formatCurrency(avgOrder));
    setField(root, "orderCountHint", orderCount + (orderCount === 1 ? " order" : " orders"));

    // Daily revenue chart
    const series = calcDailyRevenueSeries(data);
    const latest = series[series.length - 1];
    setField(root, "dailyRevenueDate", latest ? formatDisplayDate(latest.date) : "—");

    if (isChartAvailable()) {
      destroyChart("dailyRevenue");
      const ctx = document.getElementById("chartDailyRevenue");
      if (ctx) {
        charts.dailyRevenue = new Chart(ctx, {
          type: "bar",
          data: {
            labels: series.map(s => formatDisplayDate(s.date)),
            datasets: [{
              label: "Revenue",
              data: series.map(s => s.revenue),
              backgroundColor: "#ef233c",
              borderRadius: 4,
              maxBarThickness: 42
            }]
          },
          options: baseChartOptions({ currency: true })
        });
      }
    }

    // Order type donut
    const orderTypes = calcOrderTypeBreakdown(data);
    if (isChartAvailable()) {
      destroyChart("orderType");
      const ctx = document.getElementById("chartOrderType");
      if (ctx) {
        charts.orderType = new Chart(ctx, {
          type: "doughnut",
          data: {
            labels: orderTypes.map(o => o.orderType),
            datasets: [{
              data: orderTypes.map(o => o.revenue),
              backgroundColor: PALETTE,
              borderWidth: 0
            }]
          },
          options: { responsive: true, plugins: { legend: { display: false } }, cutout: "68%" }
        });
      }
    }
    const legendList = document.getElementById("orderTypeLegend");
    if (legendList) {
      legendList.innerHTML = "";
      orderTypes.forEach((o, i) => {
        const li = el("li", "", `<span><span class="swatch" style="background:${PALETTE[i % PALETTE.length]}"></span>${o.orderType}</span><span>${formatCurrency(o.revenue)} · ${formatPercent(o.share)}</span>`);
        legendList.appendChild(li);
      });
    }

    // Daily revenue table (simplified: Date, Orders, Revenue)
    const dailyBody = document.querySelector("#tblDailyRevenue tbody");
    if (dailyBody) {
      dailyBody.innerHTML = "";
      const dailyOrderCounts = {};
      data.orders.forEach(o => { dailyOrderCounts[o.date] = (dailyOrderCounts[o.date] || 0) + 1; });
      series.forEach(s => {
        const orders = dailyOrderCounts[s.date] || 0;
        const tr = el("tr");
        tr.appendChild(el("td", "al", formatDisplayDateFull(s.date)));
        tr.appendChild(el("td", "ar", String(orders)));
        tr.appendChild(el("td", "ar", formatCurrency(s.revenue)));
        dailyBody.appendChild(tr);
      });
    }

    // Revenue by Order list
    renderRevenueByOrder(data);
  }

  function ledgerRow(label, cashIn, cashOut, net, isTotal) {
    const tr = el("tr", isTotal ? "fin-row--total" : "");
    tr.appendChild(el("td", "al", label));
    tr.appendChild(el("td", "ar", cashIn));
    tr.appendChild(el("td", "ar", cashOut, ));
    const netTd = el("td", "ar", net);
    if (String(net).includes("(")) netTd.classList.add("fin-amt--neg");
    else if (net !== "—") netTd.classList.add("fin-amt--pos");
    tr.appendChild(netTd);
    return tr;
  }

  function baseChartOptions(opts) {
    opts = opts || {};
    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: opts.currency ? {
            label: (ctx) => formatCurrency(ctx.parsed.y ?? ctx.parsed)
          } : undefined
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: opts.currency ? { callback: (v) => "रु " + v } : undefined,
          grid: { color: "#eef0f3" }
        },
        x: { grid: { display: false } }
      }
    };
  }

  function renderBalanceSheet(data) {
    setField(root, "balanceSheetDate", data.meta.today);
    const netProfit = calcNetProfit(data);
    const cashInHand = calcCashFlowTotals(data).net;

    const rows = [
      { type: "group", label: "ASSETS & RESOURCES" },
      { type: "indent", label: "Cash in Hand", assets: cashInHand, liab: null, net: cashInHand },
      { type: "total", label: "Total Assets", assets: cashInHand, liab: null, net: cashInHand },
      { type: "group", label: "LIABILITIES & PAYABLES" },
      { type: "group", label: "OWNER'S EQUITY" },
      { type: "indent", label: "Current Period Profit / Loss", assets: netProfit, liab: 0, net: netProfit },
      { type: "total", label: "Total Business Net Worth", assets: netProfit, liab: 0, net: netProfit }
    ];

    const tbody = document.querySelector("#tblBalanceSheet tbody");
    if (!tbody) return;
    tbody.innerHTML = "";
    rows.forEach(r => {
      const tr = el("tr", "fin-row--" + r.type);
      tr.appendChild(el("td", "al", r.label));
      tr.appendChild(el("td", "ar", r.assets !== undefined && r.assets !== null ? formatCurrency(r.assets) : ""));
      tr.appendChild(el("td", "ar", r.liab !== undefined && r.liab !== null ? formatCurrency(r.liab) : (r.type === "group" ? "" : "—")));
      tr.appendChild(el("td", "ar", r.net !== undefined && r.net !== null ? formatCurrency(r.net) : ""));
      tbody.appendChild(tr);
    });
  }

  function renderCashFlow(data) {
    const totals = calcCashFlowTotals(data);
    setField(root, "totalCashIn", formatCurrency(totals.cashIn));
    setField(root, "totalCashOut", formatCurrency(totals.cashOut));
    setField(root, "netMovement", formatCurrency(totals.net));

    const tbody = document.querySelector("#tblCashMovement tbody");
    if (tbody) {
      tbody.innerHTML = "";
      let running = 0;
      data.cashVouchers.forEach(v => {
        running += v.cashIn - v.cashOut;
        const tr = el("tr");
        tr.appendChild(el("td", "al", formatDisplayDateFull(v.date)));
        tr.appendChild(el("td", "al", v.voucher));
        tr.appendChild(el("td", "al", v.narration));
        tr.appendChild(el("td", "ar", v.cashIn ? formatCurrency(v.cashIn) : "रु 0.00"));
        tr.appendChild(el("td", "ar", v.cashOut ? formatCurrency(v.cashOut) : "रु 0.00"));
        tr.appendChild(el("td", "ar", formatCurrency(running)));
        tbody.appendChild(tr);
      });
    }

    const methods = calcPaymentMethodTotals(data);
    const methodBody = document.querySelector("#tblPaymentMethod tbody");
    if (methodBody) {
      methodBody.innerHTML = "";
      methods.forEach(m => {
        const tr = el("tr");
        tr.appendChild(el("td", "al", m.method));
        tr.appendChild(el("td", "ar", String(m.transactions)));
        tr.appendChild(el("td", "ar", formatCurrency(m.amount)));
        tr.appendChild(el("td", "ar", formatPercent(m.share)));
        methodBody.appendChild(tr);
      });
    }

    if (isChartAvailable()) {
      destroyChart("paymentMethod");
      const ctx = document.getElementById("chartPaymentMethod");
      if (ctx) {
        charts.paymentMethod = new Chart(ctx, {
          type: "doughnut",
          data: {
            labels: methods.map(m => m.method),
            datasets: [{ data: methods.map(m => m.amount), backgroundColor: PALETTE, borderWidth: 0 }]
          },
          options: { responsive: true, plugins: { legend: { position: "bottom" } }, cutout: "62%" }
        });
      }
    }
  }

  function renderTaxSummary(data) {
    const totals = calcTaxTotals(data);
    setField(root, "taxableSales", formatCurrency(totals.taxableSales));
    setField(root, "vatCollected", formatCurrency(totals.vatCollected));
    setField(root, "taxableExpenses", formatCurrency(totals.taxableExpenses));
    setField(root, "inputVat", formatCurrency(totals.inputVat));
    setField(root, "netVatPayable", formatCurrency(totals.netVatPayable));

    const tbody = document.querySelector("#tblTaxSummary tbody");
    if (tbody) {
      tbody.innerHTML = "";
      data.taxSummary.forEach(t => {
        const tr = el("tr");
        tr.appendChild(el("td", "al", t.item));
        tr.appendChild(el("td", "ar", formatCurrency(t.base)));
        tr.appendChild(el("td", "ar", t.rate + "%"));
        tr.appendChild(el("td", "ar", formatCurrency(t.tax)));
        tbody.appendChild(tr);
      });
    }

    if (isChartAvailable()) {
      destroyChart("taxSummary");
      const ctx = document.getElementById("chartTaxSummary");
      if (ctx) {
        charts.taxSummary = new Chart(ctx, {
          type: "bar",
          data: {
            labels: ["Output VAT", "Input VAT"],
            datasets: [{
              data: [totals.vatCollected, totals.inputVat],
              backgroundColor: ["#ef233c", "#1a2233"],
              borderRadius: 4,
              maxBarThickness: 56
            }]
          },
          options: baseChartOptions({ currency: true })
        });
      }
    }
  }

  function renderInsights(data) {
    const categories = calcCategoryRevenue(data);
    const top = categories[0];
    if (top) {
      setField(root, "topCategoryName", top.category);
      setField(root, "topCategoryRevenue", formatCurrency(top.revenue));
      setField(root, "topCategoryShare", formatPercent(top.share));
    }

    if (isChartAvailable()) {
      destroyChart("categoryRevenue");
      const ctx = document.getElementById("chartCategoryRevenue");
      if (ctx) {
        charts.categoryRevenue = new Chart(ctx, {
          type: "bar",
          data: {
            labels: categories.map(c => c.category),
            datasets: [{
              data: categories.map(c => c.revenue),
              backgroundColor: "#ef233c",
              borderRadius: 4,
              maxBarThickness: 42
            }]
          },
          options: baseChartOptions({ currency: true })
        });
      }
    }

    const catBody = document.querySelector("#tblCategoryBreakdown tbody");
    if (catBody) {
      catBody.innerHTML = "";
      categories.forEach(c => {
        const tr = el("tr");
        tr.appendChild(el("td", "al", c.category));
        tr.appendChild(el("td", "ar", formatCurrency(c.revenue)));
        tr.appendChild(el("td", "ar", c.qty.toFixed(2)));
        tr.appendChild(el("td", "ar", formatCurrency(c.avgUnit)));
        tr.appendChild(el("td", "ar", formatPercent(c.share)));
        catBody.appendChild(tr);
      });
    }

    const orderTypes = calcOrderTypeBreakdown(data);
    const otBody = document.querySelector("#tblOrderTypePerf tbody");
    if (otBody) {
      otBody.innerHTML = "";
      orderTypes.forEach(o => {
        const tr = el("tr");
        tr.appendChild(el("td", "al", o.orderType));
        tr.appendChild(el("td", "ar", String(o.orders)));
        tr.appendChild(el("td", "ar", formatCurrency(o.revenue)));
        tr.appendChild(el("td", "ar", formatCurrency(o.average)));
        tr.appendChild(el("td", "ar", formatPercent(o.share)));
        otBody.appendChild(tr);
      });
    }

    if (isChartAvailable()) {
      destroyChart("orderTypePerf");
      const ctx = document.getElementById("chartOrderTypePerf");
      if (ctx) {
        charts.orderTypePerf = new Chart(ctx, {
          type: "bar",
          data: {
            labels: orderTypes.map(o => o.orderType),
            datasets: [{
              data: orderTypes.map(o => o.revenue),
              backgroundColor: "#1a2233",
              borderRadius: 4,
              maxBarThickness: 42
            }]
          },
          options: baseChartOptions({ currency: true })
        });
      }
    }
  }

  function renderReports(data) {
    const grossRevenue = calcGrossRevenue(data);
    const netProfit = calcNetProfit(data);

    const plBody = document.querySelector("#tblProfitLoss tbody");
    if (plBody) {
      plBody.innerHTML = "";
      plBody.appendChild(twoColRow("4000 · Food and Beverage Sales", formatCurrency(grossRevenue)));
      plBody.appendChild(twoColRow("Net Profit / Loss", formatCurrency(netProfit), true));
    }

    const tbBody = document.querySelector("#tblTrialBalance tbody");
    if (tbBody) {
      tbBody.innerHTML = "";
      tbBody.appendChild(threeColRow("100 · Cash in Hand", formatCurrency(grossRevenue), formatCurrency(0)));
      tbBody.appendChild(threeColRow("4000 · Food and Beverage Sales", formatCurrency(0), formatCurrency(grossRevenue)));
    }

    renderGeneralLedger(data, document.getElementById("finGlAccount").value);
  }

  function renderGeneralLedger(data, accountCode) {
    const tbody = document.querySelector("#tblGeneralLedger tbody");
    const emptyState = document.getElementById("glEmptyState");
    const table = document.getElementById("tblGeneralLedger");
    const entries = (data.generalLedger && data.generalLedger[accountCode]) || [];

    if (!tbody) return;
    tbody.innerHTML = "";

    if (!entries.length) {
      table.style.display = "none";
      emptyState.classList.add("is-visible");
      return;
    }
    table.style.display = "";
    emptyState.classList.remove("is-visible");

    let balance = 0;
    entries.forEach(entry => {
      balance += (entry.debit || 0) - (entry.credit || 0);
      const tr = el("tr");
      tr.appendChild(el("td", "al", formatDisplayDateFull(entry.date)));
      tr.appendChild(el("td", "al", entry.voucher));
      tr.appendChild(el("td", "al", entry.narration));
      tr.appendChild(el("td", "ar", entry.debit ? formatCurrency(entry.debit) : "—"));
      tr.appendChild(el("td", "ar", entry.credit ? formatCurrency(entry.credit) : "—"));
      tr.appendChild(el("td", "ar", formatCurrency(balance)));
      tbody.appendChild(tr);
    });
  }

  /* ---------------------------------------------------------
     Revenue by Order List + Real-time Updates
  --------------------------------------------------------- */
  function renderRevenueByOrder(data) {
    const tbody = document.querySelector("#tblRevenueByOrder tbody");
    const emptyState = document.getElementById("orderListEmpty");
    const countDisplay = document.getElementById("orderCountDisplay");
    const table = document.getElementById("tblRevenueByOrder");

    if (!tbody) return;

    tbody.innerHTML = "";

    if (!data.orders || data.orders.length === 0) {
      if (table) table.style.display = "none";
      if (emptyState) emptyState.classList.add("is-visible");
      if (countDisplay) countDisplay.textContent = "0 orders";
      return;
    }

    if (table) table.style.display = "";
    if (emptyState) emptyState.classList.remove("is-visible");
    if (countDisplay) countDisplay.textContent = data.orders.length + (data.orders.length === 1 ? " order" : " orders");

    // Sort orders by date descending (newest first)
    const sortedOrders = [...data.orders].sort((a, b) => {
      const dateA = new Date(a.date || "2000-01-01");
      const dateB = new Date(b.date || "2000-01-01");
      return dateB - dateA;
    });

    sortedOrders.forEach(order => {
      const tr = el("tr");

      // Order number
      const orderNum = order.id || "—";
      tr.appendChild(el("td", "al", `<strong>${orderNum}</strong>`));

      // Date
      const dateStr = order.date ? formatDisplayDate(order.date) : "—";
      tr.appendChild(el("td", "al", dateStr));

      // Customer (from items or email)
      const customer = order.items && order.items[0] ? (order.items[0].name || "—") : "—";
      tr.appendChild(el("td", "al", customer));

      // Items summary
      const items = order.items || [];
      const itemNames = items.map(i => i.name || "Item").join(", ");
      const itemsTd = el("td", "al", `<span class="fin-order-items" title="${itemNames}">${itemNames}</span>`);
      tr.appendChild(itemsTd);

      // Amount
      const amount = order.grossAmount || 0;
      tr.appendChild(el("td", "ar", `<span class="fin-order-amount">${formatCurrency(amount)}</span>`));

      // Status
      const status = order.status || "Pending";
      const statusClass = "fin-status-" + status.toLowerCase();
      tr.appendChild(el("td", "al", `<span class="fin-order-status ${statusClass}">${status}</span>`));

      // Payment method
      const payment = order.paymentMethod || "—";
      tr.appendChild(el("td", "al", payment));

      tbody.appendChild(tr);
    });
  }

  /* ---------------------------------------------------------
     Real-time Update Polling
  --------------------------------------------------------- */
  let liveUpdateInterval = null;
  let lastOrderCount = 0;
  let isLiveUpdatePaused = false;

  function startLiveUpdates(intervalMs) {
    if (liveUpdateInterval) clearInterval(liveUpdateInterval);
    liveUpdateInterval = setInterval(() => {
      if (isLiveUpdatePaused) return;
      refreshAll(getFilters()).then(data => {
        const liveIndicator = document.getElementById("liveIndicator");
        if (liveIndicator) {
          liveIndicator.classList.remove("is-paused");
        }
        // Check for new orders
        if (data.orders && data.orders.length > lastOrderCount) {
          lastOrderCount = data.orders.length;
        }
      }).catch(() => {
        const liveIndicator = document.getElementById("liveIndicator");
        if (liveIndicator) {
          liveIndicator.classList.add("is-paused");
        }
      });
    }, intervalMs || 30000); // Default 30 seconds
  }

  function stopLiveUpdates() {
    if (liveUpdateInterval) {
      clearInterval(liveUpdateInterval);
      liveUpdateInterval = null;
    }
  }

  function pauseLiveUpdates() {
    isLiveUpdatePaused = true;
    const liveIndicator = document.getElementById("liveIndicator");
    if (liveIndicator) liveIndicator.classList.add("is-paused");
  }

  function resumeLiveUpdates() {
    isLiveUpdatePaused = false;
    const liveIndicator = document.getElementById("liveIndicator");
    if (liveIndicator) liveIndicator.classList.remove("is-paused");
  }

  function twoColRow(label, amount, isTotal) {
    const tr = el("tr", isTotal ? "fin-row--total" : "");
    tr.appendChild(el("td", "al", label));
    tr.appendChild(el("td", "ar", amount));
    return tr;
  }

  function threeColRow(label, debit, credit) {
    const tr = el("tr");
    tr.appendChild(el("td", "al", label));
    tr.appendChild(el("td", "ar", debit));
    tr.appendChild(el("td", "ar", credit));
    return tr;
  }

  /* ---------------------------------------------------------
     5. Orchestration — fetch once, render all panels
  --------------------------------------------------------- */
  let currentData = null;

  function refreshAll(filters) {
    return fetchFinanceData(filters).then(data => {
      currentData = data;
      renderPerformance(data);
      renderBalanceSheet(data);
      renderCashFlow(data);
      renderTaxSummary(data);
      renderInsights(data);
      renderReports(data);
      return data;
    });
  }

  function getFilters() {
    const fy = document.getElementById("finFiscalYear");
    const sd = document.getElementById("finStartDate");
    const ed = document.getElementById("finEndDate");
    return {
      fiscalYear: fy ? fy.value : '',
      startDate: sd ? sd.value : '',
      endDate: ed ? ed.value : ''
    };
  }

  /* ---------------------------------------------------------
     6. Tab switching
  --------------------------------------------------------- */
  function initTabs() {
    const root = document.getElementById("financialDashboard");
    if (!root) return;
    const tabs = root.querySelectorAll(".fin-tab");
    tabs.forEach(tab => {
      tab.addEventListener("click", () => {
        tabs.forEach(t => {
          t.classList.remove("is-active");
          t.setAttribute("aria-selected", "false");
        });
        tab.classList.add("is-active");
        tab.setAttribute("aria-selected", "true");

        root.querySelectorAll(".fin-panel").forEach(p => p.classList.remove("is-active"));
        const target = document.getElementById("panel-" + tab.dataset.tab);
        if (target) target.classList.add("is-active");
      });
    });
  }

  /* ---------------------------------------------------------
     7. Filter bar + general ledger controls
  --------------------------------------------------------- */
  function initFilters() {
    const applyBtn = document.getElementById("finApplyBtn");
    if (applyBtn) {
      applyBtn.addEventListener("click", () => {
        refreshAll(getFilters());
      });
    }

    const allReportsBtn = document.getElementById("finAllReportsBtn");
    if (allReportsBtn) {
      allReportsBtn.addEventListener("click", () => {
        // Placeholder hook for a future "export / view all reports" action.
        allReportsBtn.blur();
      });
    }

    const glBtn = document.getElementById("finViewLedgerBtn");
    if (glBtn) {
      glBtn.addEventListener("click", () => {
        if (!currentData) return;
        const glAcc = document.getElementById("finGlAccount");
        renderGeneralLedger(currentData, glAcc ? glAcc.value : '');
      });
    }
  }

  function initFinance() {
    const root = document.getElementById("financialDashboard");
    if (!root) return;
    initTabs();
    initFilters();
    refreshAll(getFilters()).catch(() => {});
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initFinance);
  } else {
    initFinance();
  }
})();

/* ============================================================
   MeroBhoj Floor Plan & POS Settlement Logic (Staff & Admin)
   ============================================================ */

/** HTML-escape helper used by floor plan & table modal renderers. */
function esc(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

let allTables = [];
let currentFilter = 'all';
let activeSelectedTable = null;
let activeSelectedOrder = null;
let selectedPaymentMethod = 'Cash';

function initFloorPlan() {
  if (document.getElementById('floorCanvas')) {
    loadFloorPlan();
    if (!window._floorPlanInterval) {
      window._floorPlanInterval = setInterval(loadFloorPlan, 8000);
    }
  }

  // Form listener for reserving available table
  const resForm = document.getElementById('reserveTableForm');
  if (resForm) {
    resForm.removeEventListener('submit', handleReserveSubmit);
    resForm.addEventListener('submit', handleReserveSubmit);
  }

  // Form listener for adding a new table
  const addForm = document.getElementById('addTableForm');
  if (addForm) {
    addForm.removeEventListener('submit', handleAddTableSubmit);
    addForm.addEventListener('submit', handleAddTableSubmit);
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initFloorPlan);
} else {
  initFloorPlan();
}

function loadFloorPlan() {
  const isStaff = window.location.pathname.includes('/staff/');
  const apiUrl = isStaff ? 'api/floor.php' : '../staff/api/floor.php';

  fetch(apiUrl)
    .then(r => r.json())
    .then(data => {
      if (!data.success) {
        const canvas = document.getElementById('floorCanvas');
        if (canvas) canvas.innerHTML = `<p class="fp-canvas-empty">${esc(data.message || 'Failed to load floor plan.')}</p>`;
        return;
      }
      allTables = data.tables || [];
      renderFloorPlan();
    })
    .catch(err => {
      console.error('Error loading floor plan:', err);
      const canvas = document.getElementById('floorCanvas');
      if (canvas) canvas.innerHTML = '<p class="fp-canvas-empty">Error loading floor plan. Please try refreshing.</p>';
    });
}

function setStatusFilter(filter, el) {
  currentFilter = filter;
  document.querySelectorAll('.fp-pill-filter').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
  renderFloorPlan();
}

function filterTables() {
  renderFloorPlan();
}

function renderFloorPlan() {
  const searchInput = document.getElementById('searchInput');
  const search = searchInput ? searchInput.value.toLowerCase().trim() : '';
  const canvas = document.getElementById('floorCanvas');
  if (!canvas) return;

  let filtered = allTables.filter(t => {
    const tableNameStr = String(t.table_name || '');
    const matchesSearch = tableNameStr.toLowerCase().includes(search);
    const st = t.current_status || 'free';
    let matchesFilter = true;
    if (currentFilter !== 'all') {
      matchesFilter = (st === currentFilter);
    }
    return matchesSearch && matchesFilter;
  });

  // Update Top Stats
  const elTotal = document.getElementById('statTotal');
  const elAvail = document.getElementById('statAvailable');
  const elOcc = document.getElementById('statOccupied');
  const elRes = document.getElementById('statReservations');
  const elAreaCount = document.getElementById('areaCount');

  if (elTotal) elTotal.innerText = allTables.length;
  if (elAvail) elAvail.innerText = allTables.filter(t => (t.current_status || 'free') === 'free').length;
  if (elOcc) elOcc.innerText = allTables.filter(t => t.current_status === 'occupied').length;
  if (elRes) elRes.innerText = allTables.filter(t => t.current_status === 'reserved').length;
  if (elAreaCount) elAreaCount.innerText = filtered.length;

  if (filtered.length === 0) {
    canvas.innerHTML = '<p class="fp-canvas-empty">No tables found.</p>';
    return;
  }

  canvas.innerHTML = filtered.map((t, idx) => {
    const st = t.current_status || 'free';
    const cap = parseInt(t.capacity) || 4;
    const isRound = (idx % 3 === 0);
    const shapeClass = isRound ? 'table-shape-circle' : 'table-shape-rect';
    const rawName = String(t.table_name || `Table ${t.id}`);
    const shortName = rawName.replace(/Table\s*/i, 'A');
    const chairsHtml = renderChairs(cap, isRound);

    return `
      <div class="table-wrapper" onclick="openTableDetails(${t.id}, '${esc(rawName)}')">
        ${chairsHtml}
        <div class="table-body ${shapeClass} is-${st}">
          <div class="t-title">${esc(shortName)}</div>
          <div class="t-status-lbl">${st.toUpperCase()}</div>
        </div>
      </div>
    `;
  }).join('');
}

function renderChairs(capacity, isRound) {
  let chairs = '';
  const total = Math.min(capacity, 20);

  if (isRound) {
    const radius = 82;
    for (let i = 0; i < total; i++) {
      const angle = (i / total) * (2 * Math.PI);
      const x = Math.round(70 + radius * Math.cos(angle) - 8);
      const y = Math.round(70 + radius * Math.sin(angle) - 8);
      chairs += `<div class="chair-dot" style="left:${x}px; top:${y}px;"></div>`;
    }
  } else {
    const sideCount = Math.ceil(total / 2);
    for (let i = 0; i < sideCount; i++) {
      const offsetX = 25 + (i * (100 / sideCount));
      chairs += `<div class="chair-dot" style="top:-12px; left:${offsetX}px;"></div>`;
      chairs += `<div class="chair-dot" style="bottom:-12px; left:${offsetX}px;"></div>`;
    }
  }
  return chairs;
}

/* Modal Handlers */
function openTableDetails(tableId, tableName) {
  activeSelectedTable = { id: tableId, name: tableName };
  const title = tableName.replace(/Table\s*/i, 'Table A');
  const isStaff = window.location.pathname.includes('/staff/');
  const detailsApiUrl = isStaff ? `api/table_details.php?table_id=${tableId}` : `../staff/api/table_details.php?table_id=${tableId}`;

  document.getElementById('modalTableTitle').innerText = title;

  fetch(detailsApiUrl)
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      const modalBody = document.getElementById('modalTableBody');
      const statusBadge = document.getElementById('modalTableStatusBadge');
      const st = data.current_status || 'free';

      statusBadge.innerText = st.toUpperCase();
      statusBadge.className = `panel-status st-${st === 'free' ? 'checkedin' : (st === 'reserved' ? 'pending' : 'cancelled')}`;
      document.getElementById('modalTableSubtitle').innerText = `${data.table.capacity} Seats · ${data.orders ? data.orders.length : 0} Active Orders`;

      const deleteBtnHtml = !isStaff ? `
        <div class="modal-card-footer-toolbar" style="margin-top:1.5rem; padding-top:1rem; border-top:1px solid var(--clr-border, #e2e8f0); display:flex; justify-content:flex-end;">
          <button class="btn-outline btn-icon-danger" onclick="deleteTableWithValidation(${tableId}, '${esc(tableName)}')">
            <span class="material-symbols-sharp">delete</span> Delete Table
          </button>
        </div>
      ` : '';

      if (!data.orders || data.orders.length === 0) {
        modalBody.innerHTML = `
          <div class="fp-modal-empty">
            <span class="material-symbols-sharp fp-empty-icon">table_restaurant</span>
            <p style="margin-top:0.5rem; font-weight:600; color:var(--clr-dark, #1e293b);">${esc(tableName)} is currently ${st.toUpperCase()}.</p>
            <div class="modal-action-row" style="margin-top:1.2rem; display:flex; gap:0.75rem; justify-content:center;">
              <button class="btn-red" style="flex:1;" onclick="openNewOrderForTable()">
                <span class="material-symbols-sharp">add_circle</span> Start Order
              </button>
              <button class="btn-outline" style="flex:1;" onclick="openReserveModal(${tableId}, '${esc(tableName)}')">
                <span class="material-symbols-sharp">event_seat</span> Reserve Table
              </button>
            </div>
          </div>
          ${deleteBtnHtml}
        `;
      } else {
        const ordersHtml = data.orders.map(o => {
          const summary = o.items ? o.items.map(i => esc(i.menu_name)).join(', ') : 'Order items';
          return `
            <div class="split-order-card">
              <div class="soc-head">
                <span class="soc-title">Split Order #${esc(o.order_number)}</span>
                <span class="soc-price">NPR ${parseFloat(o.total_price).toFixed(0)}</span>
              </div>
              <div class="soc-meta">${o.items ? o.items.length : 0} items &bull; ${esc(o.full_name || 'Guest')}</div>
              <div class="soc-summary-box">
                ${summary}
              </div>
              <div class="soc-actions">
                <button class="btn-outline" style="flex:1;" onclick="viewOrder('${esc(o.order_number)}')">View</button>
                <button class="btn-red" style="flex:1;" onclick="openSettleModal('${esc(o.order_number)}')">Settle</button>
              </div>
            </div>
          `;
        }).join('');

        modalBody.innerHTML = `
          ${ordersHtml}
          ${deleteBtnHtml}
        `;
      }

      document.getElementById('tableModal').classList.add('active');
    });
}

function viewOrder(orderNum) {
  const isStaff = window.location.pathname.includes('/staff/');
  window.location.href = isStaff ? `orders.php?q=${orderNum}` : `orders_page.php?q=${orderNum}`;
}

function openNewOrderForTable() {
  const isStaff = window.location.pathname.includes('/staff/');
  if (activeSelectedTable) {
    window.location.href = isStaff ? `new-order.php?table=${encodeURIComponent(activeSelectedTable.name)}` : `orders_page.php?table=${encodeURIComponent(activeSelectedTable.name)}`;
  } else {
    window.location.href = isStaff ? 'new-order.php' : 'orders_page.php';
  }
}

function openReserveModal(tableId, tableName) {
  closeModal('tableModal');
  document.getElementById('reserveModalTitle').innerText = `Reserve ${tableName}`;
  document.getElementById('resTableId').value = tableId;
  document.getElementById('resGuestName').value = '';
  document.getElementById('resGuestPhone').value = '';
  document.getElementById('resGuestEmail').value = '';
  document.getElementById('resGuests').value = 2;
  document.getElementById('resDate').value = new Date().toISOString().split('T')[0];
  document.getElementById('resTime').value = '18:00';
  document.getElementById('resEndTime').value = '';
  document.getElementById('resMessage').value = '';
  document.getElementById('reserveModal').classList.add('active');
}

function handleReserveSubmit(e) {
  e.preventDefault();
  const isStaff = window.location.pathname.includes('/staff/');
  const apiUrl = isStaff ? 'api/booking_create.php' : '../staff/api/booking_create.php';

  const payload = {
    table_id: parseInt(document.getElementById('resTableId').value),
    name: document.getElementById('resGuestName').value,
    phone: document.getElementById('resGuestPhone').value,
    email: document.getElementById('resGuestEmail').value,
    guests: parseInt(document.getElementById('resGuests').value),
    booking_date: document.getElementById('resDate').value,
    booking_time: document.getElementById('resTime').value,
    end_time: document.getElementById('resEndTime').value,
    message: document.getElementById('resMessage').value
  };

  fetch(apiUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      closeModal('reserveModal');
      loadFloorPlan();
      showToastNotification('Table reserved successfully', 'success');
    } else {
      alert(data.message || 'Failed to create reservation');
    }
  })
  .catch(err => {
    alert('Network error. Please try again.');
  });
}

function openAddTableModal() {
  const isStaff = window.location.pathname.includes("/staff/");
  if (isStaff) {
    alert("Staff members are not authorized to add tables");
    return;
  }
  var nameEl = document.getElementById("addTableName");
  var capEl = document.getElementById("addTableCap");
  var modalEl = document.getElementById("addTableModal");
  if (nameEl) nameEl.value = "";
  if (capEl) capEl.value = 4;
  if (modalEl) modalEl.classList.add("active");
}

function handleAddTableSubmit(e) {
  e.preventDefault();
  const isStaff = window.location.pathname.includes("/staff/");
  if (isStaff) {
    alert("Staff members are not authorized to add tables");
    return;
  }
  var apiUrl = "api/api_qr_tables.php";
  var tableName = document.getElementById("addTableName").value.trim();
  var capacity = parseInt(document.getElementById("addTableCap").value);

  if (!tableName) {
    alert("Please enter a table name");
    return;
  }
  if (isNaN(capacity) || capacity < 1) {
    alert("Please enter a valid capacity");
    return;
  }

  var payload = {
    action: "create",
    table_name: tableName,
    capacity: capacity
  };

  fetch(apiUrl, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload)
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.success) {
      closeModal("addTableModal");
      loadFloorPlan();
      showToastNotification("Table added successfully", "success");
    } else {
      alert(data.message || "Failed to add table");
    }
  })
  .catch(function(err) {
    alert("Network error. Please try again.");
  });
}

/* Delete Table with Validation */
function deleteTableWithValidation(tableId, tableName) {
  const isStaff = window.location.pathname.includes('/staff/');
  if (isStaff) {
    alert('Staff members are not authorized to delete tables');
    return;
  }
  const deleteApiUrl = 'api/api_qr_tables.php';

  const doDelete = () => {
    fetch(deleteApiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', table_id: tableId })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        closeModal('tableModal');
        loadFloorPlan();
        showToastNotification(`${tableName} deleted successfully`, 'success');
      } else {
        alert(data.message || 'Cannot delete table');
      }
    })
    .catch(() => alert('Failed to connect to server'));
  };

  if (typeof window.openDeleteConfirm === 'function') {
    openDeleteConfirm({
      title: 'Delete Table?',
      message: 'Are you sure you want to delete ' + tableName + '? Table data and QR mapping will be removed.',
      confirmText: 'Delete Table',
      type: 'danger',
      onConfirm: function () {
        doDelete();
      }
    });
    return;
  }

  if (!confirm(`Are you sure you want to delete ${tableName}?`)) return;
  doDelete();
}

/* Settlement Flow */
function openSettleModal(orderNumber) {
  closeModal('tableModal');
  const isStaff = window.location.pathname.includes('/staff/');
  const listApi = isStaff ? 'api/orders_list.php' : '../staff/api/orders_list.php';

  fetch(listApi)
    .then(r => r.json())
    .then(data => {
      const order = (data.orders || []).find(o => o.order_number === orderNumber);
      if (!order && activeSelectedTable) {
        const detailsApi = isStaff ? `api/table_details.php?table_id=${activeSelectedTable.id}` : `../staff/api/table_details.php?table_id=${activeSelectedTable.id}`;
        fetch(detailsApi)
          .then(r => r.json())
          .then(td => {
            const ord = (td.orders || []).find(o => o.order_number === orderNumber);
            setupSettleUI(ord, activeSelectedTable.name);
          });
      } else {
        setupSettleUI(order, activeSelectedTable ? activeSelectedTable.name : order.table_number);
      }
    });
}

function setupSettleUI(order, tableName) {
  if (!order) return;
  activeSelectedOrder = order;

  const shortTable = (tableName || order.table_number || 'A1').replace(/Table\s*/i, 'A');
  document.getElementById('recOrderNum').innerText = order.order_number;
  document.getElementById('recTableName').innerText = shortTable;

  const d = new Date();
  document.getElementById('recDateTime').innerText = d.toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true });

  const itemsBody = document.getElementById('recItemsBody');
  const items = order.items || [];
  if (items.length > 0) {
    itemsBody.innerHTML = items.map(i => `
      <tr>
        <td>${esc(i.menu_name)}</td>
        <td class="num">${i.quantity}</td>
        <td class="num">${parseFloat(i.total_price || (i.price * i.quantity)).toFixed(0)}</td>
      </tr>
    `).join('');
  } else {
    itemsBody.innerHTML = `
      <tr>
        <td colspan="3">${esc(order.items_summary || 'Dine-In Food Order')}</td>
      </tr>
    `;
  }

  document.getElementById('discountInput').value = 0;
  recalculateSettleTotal();
  document.getElementById('settleModal').classList.add('active');
}

function recalculateSettleTotal() {
  if (!activeSelectedOrder) return;
  const subtotal = parseFloat(activeSelectedOrder.total_price) || 0;
  const discount = parseFloat(document.getElementById('discountInput').value) || 0;
  const finalTotal = Math.max(0, subtotal - discount);

  document.getElementById('recSubtotal').innerText = `NPR ${subtotal.toFixed(0)}`;
  document.getElementById('recDiscount').innerText = `NPR ${discount.toFixed(0)}`;
  document.getElementById('recTotal').innerText = `NPR ${finalTotal.toFixed(0)}`;
}

function selectPM(method, btn) {
  selectedPaymentMethod = method;
  document.querySelectorAll('.pm-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

function processSettle(shouldPrint) {
  if (!activeSelectedOrder) return;
  const discount = parseFloat(document.getElementById('discountInput').value) || 0;
  const isStaff = window.location.pathname.includes('/staff/');
  const settleApi = isStaff ? 'api/settle_order.php' : '../staff/api/settle_order.php';

  fetch(settleApi, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      order_number: activeSelectedOrder.order_number,
      discount_amount: discount,
      payment_method: selectedPaymentMethod
    })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      if (shouldPrint) {
        window.print();
      }
      closeModal('settleModal');
      loadFloorPlan();
      showToastNotification('Order settled successfully', 'success');
    } else {
      alert(data.message || 'Settlement failed');
    }
  });
}

function closeModal(modalId) {
  const el = document.getElementById(modalId);
  if (el) el.classList.remove('active');
}

function showToastNotification(msg, type) {
  if (typeof showToast === 'function') {
    showToast(msg, type);
  }
}

function esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/* ============================================================
   MENU MANAGEMENT PAGE (admin/menu.php)
   Extracted from the page's inline <script> block. Guarded so it
   only activates on the menu page (see the #menuModal check).
   The helper functions are exposed on window for the inline
   onclick handlers in menu.php; on other pages the no-op stubs
   assigned in the DOMContentLoaded handler above remain active.
   ============================================================ */
(function () {
    'use strict';

    // Menu page only — admin2.js is shared with the finance and floor pages,
    // where #menuModal does not exist and everything below is a no-op.
    if (!document.getElementById('menuModal')) return;

    function initMenuPage() {
    // Modal functionality
           window.openModal = function openModal(action, menuId = null, category = null) {
               const modal = document.getElementById('menuModal');
               const form = document.getElementById('menuForm');
               const modalTitle = document.getElementById('modalTitle');
               const formAction = document.getElementById('formAction');
               const menuIdInput = document.getElementById('menuId');
           
               // Reset form
               form.reset();
               document.getElementById('imagePreview').style.display = 'none';
               document.getElementById('existing_image').value = '';
           
               if (action === 'create') {
                   modalTitle.textContent = 'Add New Menu Item';
                   formAction.value = 'create';
                   menuIdInput.value = '';
                   if (category) {
                       document.getElementById('menu_category').value = category;
                   }
               } else if (action === 'edit' && menuId) {
                   modalTitle.textContent = 'Edit Menu Item';
                   formAction.value = 'update';
                   menuIdInput.value = menuId;
               
                   // Load existing data
                   loadMenuItemData(menuId);
               }
           
               modal.classList.add('show');
           }
       
           window.closeMenuModal = function closeMenuModal() {
               document.getElementById('menuModal').classList.remove('show');
           }
       
           window.loadMenuItemData = function loadMenuItemData(menuId) {
               // Show loading state
               const nameField = document.getElementById('menu_name');
               const descField = document.getElementById('menu_description');
               const priceField = document.getElementById('menu_price');
               const categoryField = document.getElementById('menu_category');
           
               nameField.value = 'Loading...';
               descField.value = 'Loading...';
               priceField.value = '';
               categoryField.value = '';
           
               fetch('menu_ajax.php', {
                   method: 'POST',
                   headers: {
                       'Content-Type': 'application/x-www-form-urlencoded',
                   },
                   body: 'action=get_item&menu_id=' + menuId
               })
               .then(response => {
                   if (!response.ok) {
                       throw new Error('Network response was not ok');
                   }
                   return response.json();
               })
               .then(data => {
                   if (data.success) {
                       const item = data.data;
                       document.getElementById('menu_name').value = item.menu_name || '';
                       document.getElementById('menu_description').value = item.menu_description || '';
                       document.getElementById('menu_price').value = item.menu_price || '';
                       document.getElementById('menu_category').value = item.menu_category || '';
                       document.getElementById('menu_status').value = item.menu_status || 'In Stock';
                       document.getElementById('existing_image').value = item.menu_image || '';
                   
                       // Show existing image preview
                       if (item.menu_image) {
                           const preview = document.getElementById('imagePreview');
                           const img = preview.querySelector('img');
                           img.src = '../' + item.menu_image;
                           preview.style.display = 'block';
                       }
                   } else {
                       alert('Error loading menu item: ' + data.message);
                       // Reset fields on error
                       document.getElementById('menu_name').value = '';
                       document.getElementById('menu_description').value = '';
                       document.getElementById('menu_price').value = '';
                       document.getElementById('menu_category').value = '';
                       document.getElementById('menu_status').value = 'In Stock';
                   }
               })
               .catch(error => {
                   console.error('Error:', error);
                   alert('Error loading menu item: ' + error.message);
                   // Reset fields on error
                   document.getElementById('menu_name').value = '';
                   document.getElementById('menu_description').value = '';
                   document.getElementById('menu_price').value = '';
                   document.getElementById('menu_category').value = '';
                   document.getElementById('menu_status').value = 'In Stock';
               });
           }
       
           // Create confirmation modal for delete operations
            // (delegates to the shared delete-confirm-modal helper in adminscript.js)
            window.showDeleteConfirmation = function showDeleteConfirmation(itemName, onDeleteCallback) {
                openDeleteConfirm({
                    title: 'Confirm Deletion',
                    message: `Are you sure you want to delete ${itemName}? This action cannot be undone.`,
                    onConfirm: onDeleteCallback
                });
            }

           window.deleteMenuItem = function deleteMenuItem(menuId, itemName) {
               showDeleteConfirmation(itemName, function() {
                   fetch('menu_ajax.php', {
                       method: 'POST',
                       headers: {
                           'Content-Type': 'application/x-www-form-urlencoded',
                       },
                       body: 'action=delete&menu_id=' + menuId
                   })
                   .then(response => response.json())
                   .then(data => {
                       if (data.success) {
                           setTimeout(() => {
                               location.reload(); // Refresh to show updated data
                           }, 1500);
                       }
                   })
                   .catch(error => {
                       console.error('Error:', error);
                   
                   });
               });
           }

           window.selectedIds = function selectedIds() {
               return Array.from(document.querySelectorAll('.item-checkbox:checked')).map(input => input.value);
           }

           window.updateSelectedCount = function updateSelectedCount() {
               const count = selectedIds().length;
               document.getElementById('selectedCount').textContent = `${count} item${count === 1 ? '' : 's'} selected`;
           }

           document.querySelectorAll('.item-checkbox').forEach(input => input.addEventListener('change', updateSelectedCount));
           document.getElementById('selectAll').addEventListener('change', function () {
               document.querySelectorAll('.item-checkbox').forEach(input => { input.checked = this.checked; });
               updateSelectedCount();
           });

           window.bulkChangeStatus = function bulkChangeStatus() {
               const ids = selectedIds();
               if (!ids.length) {
                   alert('Select at least one menu item first.');
                   return;
               }
               const status = prompt('Set stock status: In Stock, Low Stock, or Out of Stock', 'In Stock');
               if (!status) return;

               fetch('menu_ajax.php', {
                   method: 'POST',
                   headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                   body: new URLSearchParams({ action: 'bulk_status', menu_ids: ids.join(','), menu_status: status.trim() })
               })
               .then(response => response.json())
               .then(data => {
                   if (!data.success) throw new Error(data.message || 'Unable to update stock status.');
                   location.reload();
               })
               .catch(error => alert(error.message));
           }

           window.deleteSelected = function deleteSelected() {
               const ids = selectedIds();
               if (!ids.length) {
                   alert('Select at least one menu item first.');
                   return;
               }
               openDeleteConfirm({
                    title: `Delete ${ids.length} Menu Item${ids.length === 1 ? '' : 's'}`,
                    message: 'The selected menu item(s) will be permanently deleted. This action cannot be undone.',
                    onConfirm: function () { performBulkMenuDelete(ids); }
                });
            }

            window.performBulkMenuDelete = function performBulkMenuDelete(ids) {
               Promise.all(ids.map(menuId => fetch('menu_ajax.php', {
                   method: 'POST',
                   headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                   body: `action=delete&menu_id=${encodeURIComponent(menuId)}`
               }).then(response => response.json())))
               .then(results => {
                   if (results.some(result => !result.success)) throw new Error('One or more items could not be deleted.');
                   location.reload();
               })
               .catch(error => alert(error.message));
           }
       
           // Image preview functionality
           document.getElementById('menu_image').addEventListener('change', function(e) {
               const file = e.target.files[0];
               if (file) {
                   const reader = new FileReader();
                   reader.onload = function(e) {
                       const preview = document.getElementById('imagePreview');
                       const img = preview.querySelector('img');
                       img.src = e.target.result;
                       preview.style.display = 'block';
                   };
                   reader.readAsDataURL(file);
               }
           });
       
           // Handle form submission
           document.getElementById('menuForm').addEventListener('submit', function(e) {
               e.preventDefault();
           
               const formData = new FormData(this);
               const submitBtn = this.querySelector('button[type="submit"]');
               const originalText = submitBtn.textContent;
           
               // Show loading state
               submitBtn.textContent = 'Saving...';
               submitBtn.disabled = true;
           
               fetch('menu_ajax.php', {
                   method: 'POST',
                   body: formData
               })
               .then(response => response.json())
               .then(data => {
                   if (data.success) {
                       setTimeout(() => {
                           closeMenuModal();
                           location.reload(); // Refresh to show updated data
                       }, 1500);
                   }
               })
               .catch(error => {
                   console.error('Error:', error);
               
               })
               .finally(() => {
                   // Reset button
                   submitBtn.textContent = originalText;
                   submitBtn.disabled = false;
               });
           });
       
           // Close modal when clicking outside
           document.getElementById('menuModal').addEventListener('click', function(e) {
               if (e.target === this) {
                   closeMenuModal();
               }
           });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMenuPage);
    } else {
        initMenuPage();
    }
})();

/* ============================================================
   ORDERS PAGE (admin/orders_page.php)
   Extracted from the page's inline <script> block. Guarded so it
   only activates on the orders pages (body.admin-orders-page);
   the helper functions are exposed on window for the inline
   onclick handlers in orders_page.php.
   ============================================================ */
(function () {
    'use strict';

    // Orders pages only — admin2.js is shared with finance/floor/menu pages.
    if (!document.body.classList.contains('admin-orders-page')) return;

    // The global helpers below are called from inline onclick handlers in
    // orders_page.php (toggle/handleOrderUpdate/handleOrderDelete/...). They
    // overwrite the no-op stubs assigned in the main admin2.js DOMContentLoaded
    // handler. showDeleteConfirmation/closeDeleteConfirmation deliberately
    // reuse the shared delete-confirm modal from adminscript.js.
    window.toggleAdminOrderItems = function toggleAdminOrderItems(orderId) {
        const list = document.getElementById('items-list-' + orderId);
        const btn = document.getElementById('btn-toggle-' + orderId);
        if (!list) return;
        if (list.style.display === 'none') {
            list.style.display = 'block';
            if (btn) btn.innerHTML = '<i class="fa fa-chevron-up"></i> Hide items';
        } else {
            list.style.display = 'none';
            if (btn) btn.innerHTML = '<i class="fa fa-chevron-down"></i> View items';
        }
    }

    // AJAX Order Status Update
    window.handleOrderUpdate = function handleOrderUpdate(orderNumber, orderId) {
        const statusSelect = document.getElementById('status-' + orderId);
        const currentStatus = statusSelect ? statusSelect.getAttribute('data-current-status') : '';
        const newStatus = statusSelect.value;

        const allowedStatuses = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivering', 'Completed', 'Cancelled'];
        if (!statusSelect || !Number.isInteger(Number(orderId)) || Number(orderId) <= 0) {
            alert('Invalid order selection.');
            return;
        }

        if (!allowedStatuses.includes(newStatus)) {
            alert('Please choose a valid order status.');
            statusSelect.value = currentStatus || 'Pending';
            return;
        }

        if (newStatus === currentStatus) {
            return;
        }

        statusSelect.disabled = true;
    
        fetch('update_order_status_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                order_number: orderNumber,
                order_id: parseInt(orderId),
                status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusSelect.value = newStatus;
                statusSelect.setAttribute('data-current-status', newStatus);
                statusSelect.className = 'booking-status-select status-' + newStatus.toLowerCase();
            } else {
                alert(data.message || 'Unable to update the order status.');
                statusSelect.value = currentStatus || statusSelect.value;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Something went wrong while updating the order.');
            statusSelect.value = currentStatus || statusSelect.value;
        })
        .finally(() => {
            statusSelect.disabled = false;
        });
    }

    window.handleOrderDelete = function handleOrderDelete(orderNumber, orderId) {
        if (window.event) {
            window.event.preventDefault();
            window.event.stopPropagation();
        }
        showDeleteConfirmation(orderNumber, orderId);
    }

    window.showDeleteConfirmation = function showDeleteConfirmation(orderNumber, orderId) {
        openDeleteConfirm({
            title: 'Delete Order?',
            message: `Order ${orderNumber} will be permanently deleted. This action cannot be undone.`,
            onConfirm: function () {
                confirmDelete(orderNumber, orderId);
            }
        });
    }
    window.toggleAdminOrderItems = function toggleAdminOrderItems(orderId) {
        const list = document.getElementById('items-list-' + orderId);
        const btn = document.getElementById('btn-toggle-' + orderId);
        if (!list) return;
        if (list.style.display === 'none') {
            list.style.display = 'block';
            if (btn) btn.innerHTML = '<i class="fa fa-chevron-up"></i> Hide items';
        } else {
            list.style.display = 'none';
            if (btn) btn.innerHTML = '<i class="fa fa-chevron-down"></i> View items';
        }
    }

    // AJAX Order Status Update
    window.handleOrderUpdate = function handleOrderUpdate(orderNumber, orderId) {
        const statusSelect = document.getElementById('status-' + orderId);
        const currentStatus = statusSelect ? statusSelect.getAttribute('data-current-status') : '';
        const newStatus = statusSelect.value;

        const allowedStatuses = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivering', 'Completed', 'Cancelled'];
        if (!statusSelect || !Number.isInteger(Number(orderId)) || Number(orderId) <= 0) {
            alert('Invalid order selection.');
            return;
        }

        if (!allowedStatuses.includes(newStatus)) {
            alert('Please choose a valid order status.');
            statusSelect.value = currentStatus || 'Pending';
            return;
        }

        if (newStatus === currentStatus) {
            return;
        }

        statusSelect.disabled = true;
    
        fetch('update_order_status_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                order_number: orderNumber,
                order_id: parseInt(orderId),
                status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusSelect.value = newStatus;
                statusSelect.setAttribute('data-current-status', newStatus);
                statusSelect.className = 'booking-status-select status-' + newStatus.toLowerCase();
            } else {
                alert(data.message || 'Unable to update the order status.');
                statusSelect.value = currentStatus || statusSelect.value;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Something went wrong while updating the order.');
            statusSelect.value = currentStatus || statusSelect.value;
        })
        .finally(() => {
            statusSelect.disabled = false;
        });
    }

    window.handleOrderDelete = function handleOrderDelete(orderNumber, orderId) {
        if (window.event) {
            window.event.preventDefault();
            window.event.stopPropagation();
        }
        showDeleteConfirmation(orderNumber, orderId);
    }

    window.showDeleteConfirmation = function showDeleteConfirmation(orderNumber, orderId) {
        openDeleteConfirm({
            title: 'Delete Order?',
            message: `Order ${orderNumber} will be permanently deleted. This action cannot be undone.`,
            onConfirm: function () {
                confirmDelete(orderNumber, orderId);
            }
        });
    }

    window.closeDeleteConfirmation = function closeDeleteConfirmation() {
        closeDeleteConfirm();
    }

    window.confirmDelete = function confirmDelete(orderNumber, orderId) {
        closeDeleteConfirmation();
    
        fetch('../includes/delete_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                order_number: orderNumber,
                order_id: parseInt(orderId)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const row = document.getElementById('order-row-' + orderId);
                if (row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(100px)';
                    setTimeout(() => row.remove(), 300);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    };

    window.toggleAdminOrderItems = function toggleAdminOrderItems(orderId) {
        const list = document.getElementById('items-list-' + orderId);
        const btn = document.getElementById('btn-toggle-' + orderId);
        if (!list) return;
        if (list.style.display === 'none') {
            list.style.display = 'block';
            if (btn) btn.innerHTML = '<i class="fa fa-chevron-up"></i> Hide items';
        } else {
            list.style.display = 'none';
            if (btn) btn.innerHTML = '<i class="fa fa-chevron-down"></i> View items';
        }
    }

    // AJAX Order Status Update
    window.handleOrderUpdate = function handleOrderUpdate(orderNumber, orderId) {
        const statusSelect = document.getElementById('status-' + orderId);
        const currentStatus = statusSelect ? statusSelect.getAttribute('data-current-status') : '';
        const newStatus = statusSelect.value;

        const allowedStatuses = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivering', 'Completed', 'Cancelled'];
        if (!statusSelect || !Number.isInteger(Number(orderId)) || Number(orderId) <= 0) {
            alert('Invalid order selection.');
            return;
        }

        if (!allowedStatuses.includes(newStatus)) {
            alert('Please choose a valid order status.');
            statusSelect.value = currentStatus || 'Pending';
            return;
        }

        if (newStatus === currentStatus) {
            return;
        }

        statusSelect.disabled = true;
    
        fetch('update_order_status_ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                order_number: orderNumber,
                order_id: parseInt(orderId),
                status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusSelect.value = newStatus;
                statusSelect.setAttribute('data-current-status', newStatus);
                statusSelect.className = 'booking-status-select status-' + newStatus.toLowerCase();
            } else {
                alert(data.message || 'Unable to update the order status.');
                statusSelect.value = currentStatus || statusSelect.value;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Something went wrong while updating the order.');
            statusSelect.value = currentStatus || statusSelect.value;
        })
        .finally(() => {
            statusSelect.disabled = false;
        });
    }

    window.handleOrderDelete = function handleOrderDelete(orderNumber, orderId) {
        if (window.event) {
            window.event.preventDefault();
            window.event.stopPropagation();
        }
        showDeleteConfirmation(orderNumber, orderId);
    }

    window.showDeleteConfirmation = function showDeleteConfirmation(orderNumber, orderId) {
        openDeleteConfirm({
            title: 'Delete Order?',
            message: `Order ${orderNumber} will be permanently deleted. This action cannot be undone.`,
            onConfirm: function () {
                confirmDelete(orderNumber, orderId);
            }
        });
    }

    window.closeDeleteConfirmation = function closeDeleteConfirmation() {
        closeDeleteConfirm();
    }

    window.confirmDelete = function confirmDelete(orderNumber, orderId) {
        closeDeleteConfirmation();
    
        fetch('../includes/delete_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                order_number: orderNumber,
                order_id: parseInt(orderId)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const row = document.getElementById('order-row-' + orderId);
                if (row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateX(100px)';
                    setTimeout(() => row.remove(), 300);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    };

    window.closeAddressModal = function closeAddressModal() {
        const modal = document.getElementById('addressModal');
        if (modal) {
            modal.remove();
        }
    }

    // Close modal when clicking outside the content
    document.addEventListener('click', function(event) {
        const modalOverlay = document.getElementById('addressModal');
        if (modalOverlay && event.target === modalOverlay) {
            closeAddressModal();
        }
    });

    // Printer & Thermal Receipt Modal Functions
    window.openPrintModal = function openPrintModal() {
        const modal = document.getElementById('printModal');
        if (modal) modal.classList.add('active');
    };

    window.closePrintModal = function closePrintModal() {
        const modal = document.getElementById('printModal');
        if (modal) modal.classList.remove('active');
    };

    window.triggerThermalPrint = function triggerThermalPrint() {
        window.print();
    };

})();

/* ============================================================
   MeroBhoj Floor Plan — loadFloorPlan + table card logic
   Works for both admin/floor.php and staff/floor.php.
   Each page sets window.FP_API_URL before calling loadFloorPlan().
   ============================================================ */
(function () {
  'use strict';

  /* ---------- state ---------- */
  let _allTables   = [];
  let _statusFilter = 'all';
  let _activeTableId = null;   // table currently open in modal
  let _activeOrderNum = null;  // order being settled
  let _activeOrderSubtotal = 0;
  let _selectedPM = 'Cash';

  /* ---------- helpers ---------- */
  function esc(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
  }
  function fmt(n) { return 'NPR ' + (Number(n) || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
  function toast(msg, type) {
    if (window.ToastNotifications) {
      if (type === 'success') ToastNotifications.success(msg);
      else ToastNotifications.error(msg);
    }
  }
  function statusLabel(s) {
    switch(s) {
      case 'occupied': return 'OCCUPIED';
      case 'reserved': return 'RESERVED';
      case 'dirty':    return 'DIRTY';
      default:         return 'AVAILABLE';
    }
  }
  function statusClass(s) {
    switch(s) {
      case 'occupied': return 'st-cancelled';
      case 'reserved': return 'st-pending';
      case 'dirty':    return 'st-dirty';
      default:         return 'st-confirmed';
    }
  }

  /* ---------- load + render ---------- */
  function loadFloorPlan() {
    const canvas = document.getElementById('floorCanvas');
    if (!canvas) return;

    const apiUrl = window.FP_API_URL || '../staff/api/floor.php';

    fetch(apiUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function(r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function(data) {
        if (!data.success) {
          canvas.innerHTML = '<p class="fp-canvas-loading" style="color:#ef4444;">Failed to load floor plan: ' + esc(data.message || 'Unknown error') + '</p>';
          return;
        }
        _allTables = data.tables || [];
        renderFloor();
      })
      .catch(function(err) {
        canvas.innerHTML = '<p class="fp-canvas-loading" style="color:#ef4444;">Could not load floor plan. Please refresh the page.</p>';
        console.error('loadFloorPlan error:', err);
      });
  }

  function renderFloor() {
    const canvas = document.getElementById('floorCanvas');
    if (!canvas) return;

    const search = (document.getElementById('searchInput') || {value:''}).value.toLowerCase();
    let tables = _allTables;

    if (_statusFilter !== 'all') {
      tables = tables.filter(function(t) { return t.current_status === _statusFilter; });
    }
    if (search) {
      tables = tables.filter(function(t) {
        return (t.table_name || '').toLowerCase().includes(search) ||
               String(t.capacity || '').includes(search);
      });
    }

    /* stats */
    const total   = _allTables.length;
    const free     = _allTables.filter(function(t){ return t.current_status === 'free'; }).length;
    const occupied = _allTables.filter(function(t){ return t.current_status === 'occupied'; }).length;
    const reserved = _allTables.filter(function(t){ return t.current_status === 'reserved'; }).length;

    var el = function(id){ return document.getElementById(id); };
    if (el('statTotal'))        el('statTotal').textContent        = total;
    if (el('statAvailable'))    el('statAvailable').textContent    = free;
    if (el('statOccupied'))     el('statOccupied').textContent     = occupied;
    if (el('statReservations')) el('statReservations').textContent = reserved;
    if (el('areaCount'))        el('areaCount').textContent        = tables.length;

    if (tables.length === 0) {
      canvas.innerHTML = '<p class="fp-canvas-loading">No tables match your filter.</p>';
      return;
    }

    canvas.innerHTML = tables.map(function(t) {
      var st  = t.current_status || 'free';
      var cnt = Number(t.active_orders_count) || 0;
      var cap = Number(t.capacity) || 0;
      var tName = esc(t.table_name || ('Table ' + t.id));

      return '<div class="fp-table-card fp-st-' + st + '" ' +
             'data-id="' + t.id + '" data-status="' + st + '" ' +
             'onclick="openTableModal(' + t.id + ')">' +
               '<div class="fp-table-name">' + tName + '</div>' +
               '<div class="fp-table-icon"><span class="material-symbols-sharp">table_restaurant</span></div>' +
               '<div class="fp-table-cap">' + cap + ' seats</div>' +
               (cnt > 0 ? '<div class="fp-table-orders">' + cnt + ' order' + (cnt > 1 ? 's' : '') + '</div>' : '') +
               '<div class="fp-table-badge">' + statusLabel(st) + '</div>' +
             '</div>';
    }).join('');
  }

  /* ---------- filter ---------- */
  function setStatusFilter(status, btn) {
    _statusFilter = status;
    document.querySelectorAll('.fp-pill-filter').forEach(function(b){ b.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    renderFloor();
  }

  function filterTables() {
    renderFloor();
  }

  /* ---------- table modal ---------- */
  function openTableModal(tableId) {
    _activeTableId = tableId;
    const tableApiUrl = window.FP_TABLE_API_URL || '../staff/api/table_details.php';

    fetch(tableApiUrl + '?table_id=' + tableId, { headers: {'X-Requested-With': 'XMLHttpRequest'} })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data.success) { toast(data.message || 'Failed to load table', 'error'); return; }
        var t   = data.table;
        var st  = data.current_status || 'free';
        var orders = data.orders || [];

        var titleEl = document.getElementById('modalTableTitle');
        var subEl   = document.getElementById('modalTableSubtitle');
        var badgeEl = document.getElementById('modalTableStatusBadge');
        var bodyEl  = document.getElementById('modalTableBody');

        if (titleEl) titleEl.textContent  = t.table_name || ('Table ' + t.id);
        if (subEl)   subEl.textContent    = (t.capacity || 0) + ' Seats';
        if (badgeEl) { badgeEl.textContent = statusLabel(st); badgeEl.className = 'panel-status ' + statusClass(st); }

        if (bodyEl) {
          if (orders.length === 0 && st === 'free') {
            bodyEl.innerHTML = '<div class="fp-modal-empty">' +
              '<span class="material-symbols-sharp" style="font-size:48px;color:#94a3b8;">table_restaurant</span>' +
              '<p style="color:#64748b;margin-top:8px;">Table is available</p>' +
              '<button class="btn-red" style="margin-top:12px;" onclick="openReserveModal(' + tableId + ',\'' + esc(t.table_name) + '\')">Reserve This Table</button>' +
            '</div>';
          } else if (orders.length === 0 && st === 'reserved') {
            bodyEl.innerHTML = '<div class="fp-modal-empty">' +
              '<span class="material-symbols-sharp" style="font-size:48px;color:#f59e0b;">event_seat</span>' +
              '<p style="color:#64748b;margin-top:8px;">Table is reserved</p>' +
            '</div>';
          } else {
            bodyEl.innerHTML = orders.map(function(ord) {
              var itemsHtml = (ord.items || []).map(function(item) {
                return '<tr><td>' + esc(item.menu_name) + '</td><td class="num">×' + item.quantity + '</td><td class="num">' + fmt(item.price * item.quantity) + '</td></tr>';
              }).join('');
              return '<div class="fp-order-block">' +
                '<div class="fp-order-header">' +
                  '<strong>' + esc(ord.order_number) + '</strong>' +
                  '<span class="panel-status st-' + ord.status.toLowerCase() + '">' + esc(ord.status) + '</span>' +
                '</div>' +
                '<table class="receipt-table" style="margin-top:8px;">' +
                  '<thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Total</th></tr></thead>' +
                  '<tbody>' + itemsHtml + '</tbody>' +
                '</table>' +
                '<div style="text-align:right;margin-top:6px;font-weight:600;">' + fmt(ord.total_price) + '</div>' +
                '<button class="btn-red" style="margin-top:10px;width:100%;" ' +
                  'onclick="openSettleModal(\'' + esc(ord.order_number) + '\',' + ord.total_price + ')">' +
                  '<span class="material-symbols-sharp">payments</span> Settle This Order' +
                '</button>' +
              '</div>';
            }).join('<hr style="border:none;border-top:1px solid #e2e8f0;margin:12px 0;">');
          }
        }

        var modal = document.getElementById('tableModal');
        if (modal) modal.classList.add('active');
      })
      .catch(function(err){ toast('Error loading table details', 'error'); console.error(err); });
  }

  /* ---------- settle modal ---------- */
  function openSettleModal(orderNum, subtotal) {
    _activeOrderNum      = orderNum;
    _activeOrderSubtotal = Number(subtotal) || 0;
    _selectedPM          = 'Cash';

    var el = function(id){ return document.getElementById(id); };
    if (el('recOrderNum'))  el('recOrderNum').textContent  = orderNum;
    if (el('recTableName') && _activeTableId) {
      var t = _allTables.find(function(x){ return x.id == _activeTableId; });
      el('recTableName').textContent = t ? t.table_name : 'Table';
    }
    if (el('recDateTime'))  el('recDateTime').textContent  = new Date().toLocaleString();
    if (el('discountInput')) el('discountInput').value = 0;

    /* build receipt items */
    /* (already calculated in order block — just show total in receipt) */
    if (el('recItemsBody')) el('recItemsBody').innerHTML = '<tr><td colspan="3" style="padding:8px 0;color:#64748b;">See order details above</td></tr>';

    recalculateSettleTotal();

    /* reset payment method buttons */
    document.querySelectorAll('.pm-btn').forEach(function(b){ b.classList.remove('active'); });
    var cashBtn = document.querySelector('.pm-btn');
    if (cashBtn) cashBtn.classList.add('active');

    /* close table modal, open settle modal */
    var tm = document.getElementById('tableModal');
    if (tm) tm.classList.remove('active');
    var sm = document.getElementById('settleModal');
    if (sm) sm.classList.add('active');
  }

  function recalculateSettleTotal() {
    var disc = Number((document.getElementById('discountInput') || {value:0}).value) || 0;
    var total = Math.max(0, _activeOrderSubtotal - disc);
    var recDisc = document.getElementById('recDiscount');
    var recSub  = document.getElementById('recSubtotal');
    var recTot  = document.getElementById('recTotal');
    if (recSub)  recSub.textContent  = fmt(_activeOrderSubtotal);
    if (recDisc) recDisc.textContent = disc > 0 ? '-' + fmt(disc) : fmt(0);
    if (recTot)  recTot.textContent  = fmt(total);
  }

  function selectPM(method, btn) {
    _selectedPM = method;
    document.querySelectorAll('.pm-btn').forEach(function(b){ b.classList.remove('active'); });
    if (btn) btn.classList.add('active');
  }

  function processSettle(doPrint) {
    if (!_activeOrderNum) { toast('No order selected', 'error'); return; }
    var disc = Number((document.getElementById('discountInput') || {value:0}).value) || 0;
    var total = Math.max(0, _activeOrderSubtotal - disc);
    var settleUrl = window.FP_SETTLE_API_URL || '../staff/api/settle_bill.php';

    fetch(settleUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({
        order_number:           _activeOrderNum,
        subtotal:               _activeOrderSubtotal,
        discount_type:          'fixed',
        discount_value:         disc,
        discount_amount:        disc,
        service_charge_rate:    0,
        service_charge_amount:  0,
        vat_rate:               0,
        vat_amount:             0,
        grand_total:            total,
        payment_method:         _selectedPM,
        amount_received:        total,
        change_due:             0,
        remarks:                ''
      })
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if (!data.success) { toast(data.message || 'Settlement failed', 'error'); return; }
      toast('Order settled successfully!', 'success');
      if (doPrint) { window.print(); }
      closeModal('settleModal');
      setTimeout(loadFloorPlan, 600);
    })
    .catch(function(err){ toast('Error processing settlement', 'error'); console.error(err); });
  }

  /* ---------- reserve modal ---------- */
  function openReserveModal(tableId, tableName) {
    var titleEl = document.getElementById('reserveModalTitle');
    if (titleEl) titleEl.textContent = 'Reserve ' + (tableName || ('Table ' + tableId));
    var idEl = document.getElementById('resTableId');
    if (idEl) idEl.value = tableId;

    /* set default date to today */
    var dateEl = document.getElementById('resDate');
    if (dateEl && !dateEl.value) { dateEl.value = new Date().toISOString().slice(0,10); }

    var tm = document.getElementById('tableModal');
    if (tm) tm.classList.remove('active');
    var rm = document.getElementById('reserveModal');
    if (rm) rm.classList.add('active');

    var form = document.getElementById('reserveTableForm');
    if (form) {
      form.onsubmit = function(e) {
        e.preventDefault();
        var bookUrl = window.FP_BOOK_API_URL || '../staff/api/booking_create.php';
        var payload = {
          table_id:     Number(document.getElementById('resTableId').value),
          guest_name:   (document.getElementById('resGuestName') || {value:''}).value.trim(),
          phone:        (document.getElementById('resGuestPhone') || {value:''}).value.trim(),
          guests:       Number((document.getElementById('resGuests') || {value:2}).value) || 2,
          booking_date: (document.getElementById('resDate') || {value:''}).value,
          start_time:   (document.getElementById('resTime') || {value:''}).value,
          end_time:     (document.getElementById('resEndTime') || {value:''}).value || '',
          message:      (document.getElementById('resMessage') || {value:''}).value.trim()
        };
        if (!payload.guest_name || !payload.phone || !payload.booking_date || !payload.start_time) {
          toast('Please fill required fields', 'error'); return;
        }
        fetch(bookUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify(payload)
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
          if (!data.success) { toast(data.message || 'Reservation failed', 'error'); return; }
          toast('Reservation created!', 'success');
          closeModal('reserveModal');
          form.reset();
          setTimeout(loadFloorPlan, 600);
        })
        .catch(function(err){ toast('Error creating reservation', 'error'); console.error(err); });
      };
    }
  }

  /* ---------- add table (admin only) ---------- */
  function openAddTableModal() {
    var modal = document.getElementById('addTableModal');
    if (modal) modal.classList.add('active');
    var form = document.getElementById('addTableForm');
    if (form) {
      form.onsubmit = function(e) {
        e.preventDefault();
        var addUrl = window.FP_ADD_TABLE_URL || '../admin/api/api_qr_tables.php';
        var tName = (document.getElementById('addTableName') || {value:''}).value.trim();
        var tCap  = Number((document.getElementById('addTableCap') || {value:4}).value) || 4;
        if (!tName) { toast('Table name is required', 'error'); return; }
        fetch(addUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({ action: 'create', table_name: tName, capacity: tCap })
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
          if (!data.success) { toast(data.message || 'Failed to add table', 'error'); return; }
          toast('Table added!', 'success');
          closeModal('addTableModal');
          form.reset();
          setTimeout(loadFloorPlan, 400);
        })
        .catch(function(err){ toast('Error adding table', 'error'); console.error(err); });
      };
    }
  }

  /* ---------- new order for table ---------- */
  function openNewOrderForTable() {
    if (window.FP_IS_ADMIN) {
      location.href = 'orders_page.php';
    } else {
      location.href = 'new-order.php';
    }
  }

  /* ---------- generic modal close ---------- */
  function closeModal(id) {
    var m = document.getElementById(id);
    if (m) m.classList.remove('active');
  }

  /* ---------- auto-refresh ---------- */
  var _refreshInterval = null;
  function startAutoRefresh() {
    if (_refreshInterval) return;
    _refreshInterval = setInterval(function() {
      if (!document.hidden) loadFloorPlan();
    }, 30000);
  }

  /* ---------- expose to global scope ---------- */
  window.loadFloorPlan       = loadFloorPlan;
  window.setStatusFilter     = window.setStatusFilter || setStatusFilter;
  window.filterTables        = window.filterTables    || filterTables;
  window.openTableModal      = openTableModal;
  window.openSettleModal     = openSettleModal;
  window.recalculateSettleTotal = recalculateSettleTotal;
  window.selectPM            = selectPM;
  window.processSettle       = processSettle;
  window.openReserveModal    = openReserveModal;
  window.openAddTableModal   = openAddTableModal;
  window.openNewOrderForTable = openNewOrderForTable;
  window.closeModal          = window.closeModal || closeModal;

  /* click-outside to close modals */
  document.addEventListener('click', function(e) {
    ['tableModal','settleModal','reserveModal','addTableModal'].forEach(function(id) {
      var m = document.getElementById(id);
      if (m && e.target === m) closeModal(id);
    });
  });

  /* start auto-refresh when floor canvas is present */
  document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('floorCanvas')) {
      startAutoRefresh();
    }
  });

}());
