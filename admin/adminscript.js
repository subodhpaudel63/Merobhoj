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
