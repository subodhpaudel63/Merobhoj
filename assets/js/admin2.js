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
    return {
      fiscalYear: document.getElementById("finFiscalYear").value,
      startDate: document.getElementById("finStartDate").value,
      endDate: document.getElementById("finEndDate").value
    };
  }

  /* ---------------------------------------------------------
     6. Tab switching
  --------------------------------------------------------- */
  function initTabs() {
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
        renderGeneralLedger(currentData, document.getElementById("finGlAccount").value);
      });
    }
  }

  /* ---------------------------------------------------------
     8. Init
  --------------------------------------------------------- */
  document.addEventListener("DOMContentLoaded", function () {
    initTabs();
    initFilters();
    refreshAll(getFilters()).then(data => {
      // Start live updates after initial load
      if (data && data.orders) {
        lastOrderCount = data.orders.length;
      }
      startLiveUpdates(30000); // Poll every 30 seconds
    });
  });

  // If the script loads after DOMContentLoaded already fired (e.g. injected
  // into an existing admin page via AJAX), run init immediately.
  if (document.readyState !== "loading") {
    initTabs();
    initFilters();
    refreshAll(getFilters()).then(data => {
      // Start live updates after initial load
      if (data && data.orders) {
        lastOrderCount = data.orders.length;
      }
      startLiveUpdates(30000); // Poll every 30 seconds
    });
  }

  // Pause live updates when tab is hidden to save resources
  document.addEventListener("visibilitychange", function () {
    if (document.hidden) {
      pauseLiveUpdates();
    } else {
      resumeLiveUpdates();
    }
  });
})();
