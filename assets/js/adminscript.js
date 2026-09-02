// Admin-side shared behaviors
// Used by: admin/login.php, admin/index.php, admin/menu.php, admin/orders_page.php, admin/bookings.php, admin/order_view.php, admin/analytics.php, admin/feedback.php, admin/users.php

function showBookingToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <span class="toast-message">${message}</span>
            <button class="toast-close">&times;</button>
        </div>
    `;

    const container = document.querySelector('.toast-container') || document.body;
    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 5000);

    const closeButton = toast.querySelector('.toast-close');
    if (closeButton) {
        closeButton.addEventListener('click', () => {
            toast.remove();
        });
    }
}

function updateBookingStatus(bookingId, newStatus, button) {
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Updating...';

    fetch('../includes/update_booking_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            id: parseInt(bookingId),
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showBookingToast('Booking status updated successfully!', 'success');
        } else {
            showBookingToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showBookingToast('Error updating booking status', 'error');
    })
    .finally(() => {
        button.disabled = false;
        button.textContent = originalText;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const updateButtons = document.querySelectorAll('.btn-booking-update');
    updateButtons.forEach(button => {
        button.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-booking-id');
            const select = this.closest('.booking-actions').querySelector('.booking-status-select');
            const newStatus = select.value;
            updateBookingStatus(bookingId, newStatus, this);
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const body = document.body;
    const sidebar = document.getElementById('admin_sidebar');
    const backdrop = document.getElementById('sidebar_backdrop');

    const isMobileView = () => window.innerWidth <= 768;

    // Central handler — exposed globally so the header button's inline
    // onclick="mkjToggleSidebar(event)" always works.
    window.mkjToggleSidebar = function(e) {
        if (e && e.__mkjSidebarHandled) return; // avoid double-toggle from inline + listener
        if (e) e.__mkjSidebarHandled = true;
        if (e && e.preventDefault) e.preventDefault();
        const btn = document.getElementById('menu_toggle');
        const icon = btn ? btn.querySelector('span') : null;

        if (isMobileView()) {
            body.classList.toggle('sidebar-open');
        } else {
            body.classList.toggle('sidebar-collapsed');
            if (sidebar) sidebar.classList.toggle('is-collapsed');
        }

        const hidden = body.classList.contains('sidebar-collapsed') || body.classList.contains('sidebar-open');
        if (btn) btn.setAttribute('aria-expanded', hidden ? 'false' : 'true');
        if (icon) icon.textContent = hidden ? 'menu' : 'menu_open';
    };

    const menuToggle = document.getElementById('menu_toggle');
    if (menuToggle) {
        // Direct binding (primary). Inline onclick in topbar.php is the fallback.
        menuToggle.addEventListener('click', window.mkjToggleSidebar);
    }

    if (backdrop) {
        backdrop.addEventListener('click', function() {
            body.classList.remove('sidebar-open');
            if (sidebar) sidebar.classList.remove('is-collapsed');
            const btn = document.getElementById('menu_toggle');
            if (btn) btn.setAttribute('aria-expanded', 'true');
            const icon = btn ? btn.querySelector('span') : null;
            if (icon) icon.textContent = 'menu_open';
        });
    }

    window.addEventListener('resize', function() {
        if (!isMobileView()) {
            body.classList.remove('sidebar-open');
            if (sidebar) sidebar.classList.remove('is-collapsed');
        }
    });

    // Theme toggle (with persistence)
    const themeToggle = document.getElementById('theme_toggle');
    const themeIcon = themeToggle ? themeToggle.querySelector('span') : null;

    function applyThemeIcon() {
        if (!themeIcon) return;
        themeIcon.textContent = document.body.classList.contains('dark-theme-variables')
            ? 'dark_mode'
            : 'light_mode';
    }

    if (localStorage.getItem('mkj_admin_theme') === 'dark') {
        document.body.classList.add('dark-theme-variables');
    }
    applyThemeIcon();

    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-theme-variables');
            localStorage.setItem(
                'mkj_admin_theme',
                document.body.classList.contains('dark-theme-variables') ? 'dark' : 'light'
            );
            applyThemeIcon();
        });
    }

    // Profile dropdown
    const profileBtn = document.getElementById('profile_menu_btn');
    const profileWrap = document.getElementById('profile_dropdown');

    if (profileBtn && profileWrap) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = profileWrap.classList.toggle('open');
            profileBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            profileWrap.querySelector('.admin-profile-menu').setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        });
    }

    // Date range picker
    const daterangePicker = document.getElementById('daterange_picker');
    const daterangeBtn = document.getElementById('daterange_btn');
    const fromInput = document.getElementById('daterange_from');
    const toInput = document.getElementById('daterange_to');
    const daterangeLabel = document.getElementById('daterange_label');

    const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    function updateDateRangeLabel() {
        if (!daterangeLabel || !fromInput || !toInput || !fromInput.value || !toInput.value) return;

        const from = new Date(fromInput.value + 'T00:00:00');
        const to = new Date(toInput.value + 'T00:00:00');
        if (isNaN(from.getTime()) || isNaN(to.getTime())) return;

        const short = (d) => `${MONTH_NAMES[d.getMonth()]} ${d.getDate()}`;

        daterangeLabel.textContent = from.getFullYear() === to.getFullYear()
            ? `${short(from)} \u2013 ${short(to)}, ${to.getFullYear()}`
            : `${short(from)}, ${from.getFullYear()} \u2013 ${short(to)}, ${to.getFullYear()}`;
    }

    if (daterangeBtn && daterangePicker) {
        daterangeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = daterangePicker.classList.toggle('open');
            daterangeBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            daterangePicker.querySelector('.daterange-panel').setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        });
    }

    if (fromInput) fromInput.addEventListener('change', updateDateRangeLabel);
    if (toInput) toInput.addEventListener('change', updateDateRangeLabel);

    const daterangeApply = document.getElementById('daterange_apply');
    if (daterangeApply) {
        daterangeApply.addEventListener('click', function() {
            updateDateRangeLabel();
            daterangePicker.classList.remove('open');
        });
    }

    // Close any open dropdown when clicking outside of it
    document.addEventListener('click', function(e) {
        if (profileWrap && !profileWrap.contains(e.target)) {
            profileWrap.classList.remove('open');
        }
        if (daterangePicker && !daterangePicker.contains(e.target)) {
            daterangePicker.classList.remove('open');
        }
    });

    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    const insightCards = document.querySelectorAll('.sales, .expenses, .income');
    insightCards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(card);
    });

    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const originalText = submitBtn.textContent;
                submitBtn.textContent = 'Processing...';
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }, 3000);
            }
        });
    });
});

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <span class="toast-message">${message}</span>
            <button class="toast-close">&times;</button>
        </div>
    `;

    const container = document.querySelector('.toast-container') || document.body;
    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 5000);

    const closeButton = toast.querySelector('.toast-close');
    if (closeButton) {
        closeButton.addEventListener('click', () => {
            toast.remove();
        });
    }
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

function updateOrderStatus(orderId, newStatus) {
    console.log(`Updating order ${orderId} to status: ${newStatus}`);

    fetch('../includes/order_status_update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `order_id=${orderId}&status=${newStatus}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
        } else {
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function togglePw() {
    const input = document.getElementById('password');
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
}

function updateFilters() {
    const dateRange = document.getElementById('dateRange');
    const customDateFields = document.getElementById('customDateFields');
    if (!dateRange || !customDateFields) return;
    customDateFields.style.display = dateRange.value === 'custom' ? 'flex' : 'none';
}

function applyFilters() {
    const url = new URL(window.location.href);
    const dateRange = document.getElementById('dateRange');
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    if (dateRange) url.searchParams.set('date_range', dateRange.value);
    if (startDate) url.searchParams.set('start_date', startDate.value);
    if (endDate) url.searchParams.set('end_date', endDate.value);
    window.location.href = url.toString();
}

function exportCSV() {
    window.location.href = 'export_csv.php' + window.location.search;
}

function exportPDF() {
    window.print();
}

function setAnalyticsRange(range) {
    const url = new URL(window.location.href);
    url.searchParams.set('date_range', range);
    url.searchParams.delete('start_date');
    url.searchParams.delete('end_date');
    window.location.href = url.toString();
}

function showFullMessage(event, anchor) {
    if (event) event.preventDefault();
    const modal = document.getElementById('messageModal');
    if (!modal || !anchor) return;
    const name = anchor.getAttribute('data-name') || '';
    const message = anchor.getAttribute('data-message') || '';
    const title = modal.querySelector('#messageModalTitle');
    const body = modal.querySelector('#messageModalBody');
    if (title) title.textContent = `Booking Message from: ${name}`;
    if (body) body.textContent = message;
    modal.style.display = 'flex';
}

function closeMessageModal() {
    const modal = document.getElementById('messageModal');
    if (modal) modal.style.display = 'none';
}

function showFullAddress(event, anchor) {
    if (event) event.preventDefault();
    const modal = document.getElementById('addressModal');
    if (!modal || !anchor) return;
    const orderId = anchor.getAttribute('data-order-id') || '';
    const address = anchor.getAttribute('data-address') || '';
    const title = modal.querySelector('#addressModalTitle');
    const body = modal.querySelector('#addressModalBody');
    if (title) title.textContent = `Full Address for Order #${orderId}`;
    if (body) body.textContent = address;
    modal.style.display = 'flex';
}

function closeAddressModal() {
    const modal = document.getElementById('addressModal');
    if (modal) modal.style.display = 'none';
}

function openFeedbackModal(name, message, rating, date) {
    const modal = document.getElementById('feedbackModal');
    if (!modal) return;
    const title = modal.querySelector('#modalTitle');
    const modalDate = modal.querySelector('#modalDate');
    const stars = modal.querySelector('#modalStars');
    const body = modal.querySelector('#modalMsgBody');
    if (title) title.textContent = name || 'Guest';
    if (modalDate) modalDate.textContent = date || '';
    if (stars) stars.textContent = '★'.repeat(Number(rating || 0));
    if (body) body.textContent = message || '';
    modal.style.display = 'flex';
}

function closeModal() {
    const modal = document.getElementById('feedbackModal');
    if (modal) modal.style.display = 'none';
}

function deleteFeedback(id, name, button) {
    if (!confirm(`Delete feedback from ${name}?`)) return;
    if (button) button.disabled = true;
    fetch(`../includes/delete_feedback.php?id=${encodeURIComponent(id)}`, { method: 'POST' })
        .then(() => window.location.reload())
        .catch(() => {
            alert('Unable to delete feedback right now.');
            if (button) button.disabled = false;
        });
}

function initAnalyticsCharts() {
    const payload = window.analyticsPayload;
    if (!payload || typeof Chart === 'undefined') return;
    const charts = payload.charts || payload;

    const legacySalesCanvas = document.getElementById('salesChart');
    const legacyStatusCanvas = document.getElementById('orderStatusChart');
    const legacySalesDataEl = document.getElementById('analyticsSalesData');
    const legacyOrderStatsEl = document.getElementById('analyticsOrderStats');
    if (legacySalesCanvas && legacyStatusCanvas && legacySalesDataEl && legacyOrderStatsEl) {
        const salesData = JSON.parse(legacySalesDataEl.textContent || '[]');
        const orderStats = JSON.parse(legacyOrderStatsEl.textContent || '[]');

        new Chart(legacySalesCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: salesData.map((item) => item.date),
                datasets: [
                    {
                        label: 'Revenue (Rs)',
                        data: salesData.map((item) => item.revenue),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                    },
                    {
                        label: 'Orders',
                        data: salesData.map((item) => item.order_count),
                        borderColor: '#764ba2',
                        backgroundColor: 'rgba(118, 75, 162, 0.1)',
                        tension: 0.4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } },
            },
        });

        new Chart(legacyStatusCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: orderStats.map((item) => item.status),
                datasets: [{
                    data: orderStats.map((item) => item.count),
                    backgroundColor: ['#4CAF50', '#2196F3', '#FF9800', '#F44336'],
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
            },
        });
        return;
    }

    const revenueCanvas = document.getElementById('revenueChart');
    const ordersCanvas = document.getElementById('ordersChart');
    const paymentCanvas = document.getElementById('paymentChart');
    const paymentLegend = document.getElementById('paymentLegend');
    if (!revenueCanvas || !ordersCanvas || !paymentCanvas) return;

    const lineOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0f172a',
                titleColor: '#fff',
                bodyColor: '#fff',
                padding: 12,
                cornerRadius: 12,
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { color: '#64748b', maxRotation: 0, autoSkip: true },
            },
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(148, 163, 184, 0.18)' },
                ticks: { color: '#64748b' },
            },
        },
    };

    if (window.analyticsCharts && typeof window.analyticsCharts.destroy === 'function') {
        window.analyticsCharts.destroy();
    }
    window.analyticsCharts = window.analyticsCharts || {};

    window.analyticsCharts.revenue = new Chart(revenueCanvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: charts.labels,
            datasets: [{
                data: charts.revenue,
                borderColor: '#ff6a00',
                backgroundColor: 'rgba(255, 106, 0, 0.12)',
                fill: true,
                borderWidth: 3,
                pointRadius: 3,
                pointHoverRadius: 5,
                tension: 0.38,
            }],
        },
        options: {
            ...lineOptions,
            scales: {
                ...lineOptions.scales,
                y: {
                    ...lineOptions.scales.y,
                    ticks: {
                        color: '#64748b',
                        callback: (value) => `\u20B9${Number(value).toLocaleString()}`,
                    },
                },
            },
        },
    });

    window.analyticsCharts.orders = new Chart(ordersCanvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: charts.labels,
            datasets: [{
                data: charts.orders,
                borderColor: '#22a55f',
                backgroundColor: 'rgba(34, 165, 95, 0.12)',
                fill: true,
                borderWidth: 3,
                pointRadius: 3,
                pointHoverRadius: 5,
                tension: 0.38,
            }],
        },
        options: lineOptions,
    });

    const paymentColors = ['#5b9dd9', '#45c4b0', '#f5b335'];
    window.analyticsCharts.payment = new Chart(paymentCanvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: charts.paymentLabels,
            datasets: [{
                data: charts.paymentValues,
                backgroundColor: paymentColors,
                borderWidth: 0,
                hoverOffset: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const value = context.raw || 0;
                            const total = charts.paymentTotal || 1;
                            const percentage = Math.round((value / total) * 100);
                            return ` ${context.label}: ${percentage}% (\u20B9${Number(value).toLocaleString()})`;
                        },
                    },
                },
            },
        },
    });

    if (paymentLegend) {
        paymentLegend.innerHTML = charts.paymentLabels.map((label, index) => `
            <div class="payment-legend-item">
                <span class="payment-dot" style="background:${paymentColors[index]}"></span>
                <div>
                    <span class="payment-label">${label}</span>
                    <span class="payment-meta">${charts.paymentPercentages[index]}% (\u20B9${Number(charts.paymentValues[index]).toLocaleString()})</span>
                </div>
            </div>
        `).join('');
    }
}

function updateAnalyticsWidgets(payload) {
    if (!payload) return;
    const charts = payload.charts || payload;
    const stats = payload.stats || payload;

    const statMap = [
        { selector: '.analytics-stat-card:nth-child(1) strong', value: `\u20B9${Number(stats.totalRevenue).toLocaleString()}` },
        { selector: '.analytics-stat-card:nth-child(2) strong', value: Number(stats.totalOrders).toLocaleString() },
        { selector: '.analytics-stat-card:nth-child(3) strong', value: `\u20B9${Number(stats.avgOrderValue).toLocaleString()}` },
        { selector: '.analytics-stat-card:nth-child(4) strong', value: Number(stats.newCustomers).toLocaleString() }
    ];

    statMap.forEach(({ selector, value }) => {
        const node = document.querySelector(selector);
        if (node) node.textContent = value;
    });

    const lastUpdated = document.getElementById('analyticsLastUpdated');
    if (lastUpdated) {
        lastUpdated.textContent = `Last updated ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
    }

    if (window.analyticsCharts?.revenue) {
        window.analyticsCharts.revenue.data.labels = charts.labels;
        window.analyticsCharts.revenue.data.datasets[0].data = charts.revenue;
        window.analyticsCharts.revenue.update();
    }
    if (window.analyticsCharts?.orders) {
        window.analyticsCharts.orders.data.labels = charts.labels;
        window.analyticsCharts.orders.data.datasets[0].data = charts.orders;
        window.analyticsCharts.orders.update();
    }
    if (window.analyticsCharts?.payment) {
        window.analyticsCharts.payment.data.labels = charts.paymentLabels;
        window.analyticsCharts.payment.data.datasets[0].data = charts.paymentValues;
        window.analyticsCharts.payment.update();
    }

    const paymentLegend = document.getElementById('paymentLegend');
    if (paymentLegend) {
        const paymentColors = ['#5b9dd9', '#45c4b0', '#f5b335'];
        paymentLegend.innerHTML = charts.paymentLabels.map((label, index) => `
            <div class="payment-legend-item">
                <span class="payment-dot" style="background:${paymentColors[index]}"></span>
                <div>
                    <span class="payment-label">${label}</span>
                    <span class="payment-meta">${charts.paymentPercentages[index]}% (\u20B9${Number(charts.paymentValues[index]).toLocaleString()})</span>
                </div>
            </div>
        `).join('');
    }
}

function refreshAnalyticsData() {
    const url = window.analyticsRefreshUrl || ('analytics_data.php' + window.location.search);
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => response.json())
        .then((payload) => {
            updateAnalyticsWidgets(payload);
        })
        .catch((error) => {
            console.warn('Unable to refresh analytics data:', error);
        });
}

document.addEventListener('DOMContentLoaded', function () {
    initAnalyticsCharts();
    setInterval(refreshAnalyticsData, 15000);
});

