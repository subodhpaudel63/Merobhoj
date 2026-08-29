// Booking page specific JavaScript functions

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
    const menuBar = document.getElementById('menu_bar');
    const sideBar = document.querySelector('aside');
    const closeBtn = document.getElementById('close_btn');

    if (menuBar && sideBar) {
        menuBar.addEventListener('click', function() {
            sideBar.style.display = 'block';
        });
    }

    if (closeBtn && sideBar) {
        closeBtn.addEventListener('click', function() {
            sideBar.style.display = 'none';
        });
    }

    // Keep the sidebar visible by default once the layout is back on
    // desktop widths. Without this, closing the drawer on mobile would
    // leave the sidebar hidden even after resizing the window wider.
    window.addEventListener('resize', function() {
        if (sideBar && window.innerWidth > 768) {
            sideBar.style.display = '';
        }
    });

    const themeToggler = document.querySelector('.theme-toggler');
    if (themeToggler) {
        const themeIcons = themeToggler.querySelectorAll('span');
        themeIcons.forEach(icon => {
            icon.addEventListener('click', function() {
                themeIcons.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                document.body.classList.toggle('dark-theme-variables');
            });
        });
    }

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

const MKJ_ORDER_POLL_INTERVAL_MS = 15000;
let mkjLatestOrderId = null;
let mkjOrderPollTimer = null;
let mkjOrderAlertBusy = false;
let mkjAudioContext = null;
let mkjAudioUnlocked = false;

function mkjPlayOrderSound() {
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return;
        const context = mkjAudioContext || new AudioContextClass();
        mkjAudioContext = context;
        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = 880;
        gain.gain.value = 0.0001;

        oscillator.connect(gain);
        gain.connect(context.destination);

        oscillator.start();
        gain.gain.exponentialRampToValueAtTime(0.18, context.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.55);
        oscillator.stop(context.currentTime + 0.56);

        oscillator.onended = () => {
            if (context.state !== 'closed') {
                context.close().catch(() => {});
            }
        };
    } catch (error) {
        console.warn('Unable to play order alert sound:', error);
    }
}

function mkjUnlockOrderAudio() {
    if (mkjAudioUnlocked) return;
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) return;

    try {
        mkjAudioContext = mkjAudioContext || new AudioContextClass();
        if (mkjAudioContext.state === 'suspended') {
            mkjAudioContext.resume().catch(() => {});
        }
        mkjAudioUnlocked = true;
    } catch (error) {
        console.warn('Unable to unlock order audio:', error);
    }
}

function mkjDismissOrderNotification() {
    const existing = document.getElementById('mkj-order-notification');
    if (existing) {
        existing.remove();
    }
}

function mkjShowOrderNotification(order) {
    mkjDismissOrderNotification();

    const overlay = document.createElement('div');
    overlay.className = 'order-notification-overlay';
    overlay.id = 'mkj-order-notification';
    overlay.innerHTML = `
        <div class="order-notification-card" role="dialog" aria-modal="true" aria-labelledby="mkj-order-title">
            <div class="order-notification-badge" aria-hidden="true">!</div>
            <h3 id="mkj-order-title">New Order Received</h3>
            <p>A new order has just arrived in the admin panel.</p>
            <p><strong>Order #:</strong> ${order.order_id}</p>
            <p><strong>Item:</strong> ${order.menu_name || 'Unknown item'}</p>
            <p><strong>Status:</strong> ${order.status || 'Pending'}</p>
            <p><strong>Total:</strong> Rs. ${Number(order.total_price || 0).toFixed(2)}</p>
            <div class="order-notification-actions">
                <button type="button" class="order-notification-secondary" id="mkj-order-later-btn">Later</button>
                <a class="order-notification-primary" href="/Merobhoj/admin/orders_page.php">View Orders</a>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    document.getElementById('mkj-order-later-btn')?.addEventListener('click', mkjDismissOrderNotification);
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            mkjDismissOrderNotification();
        }
    });
}

function mkjFetchLatestOrder() {
    if (mkjOrderAlertBusy) return;
    mkjOrderAlertBusy = true;

    fetch('get_order_notifications.php', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then((response) => response.json())
    .then((payload) => {
        if (!payload || payload.success !== true || !payload.order) return;

        const order = payload.order;
        if (mkjLatestOrderId !== null && Number(order.order_id) !== Number(mkjLatestOrderId)) {
            mkjShowOrderNotification(order);
            mkjPlayOrderSound();
        }
        mkjLatestOrderId = Number(order.order_id);
    })
    .catch((error) => {
        console.warn('Unable to poll for new orders:', error);
    })
    .finally(() => {
        mkjOrderAlertBusy = false;
    });
}

function mkjStartOrderPolling() {
    if (mkjOrderPollTimer) return;
    mkjFetchLatestOrder();
    mkjOrderPollTimer = setInterval(mkjFetchLatestOrder, MKJ_ORDER_POLL_INTERVAL_MS);
}

document.addEventListener('pointerdown', mkjUnlockOrderAudio, { once: true });
document.addEventListener('keydown', mkjUnlockOrderAudio, { once: true });
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        mkjFetchLatestOrder();
    }
});
window.addEventListener('load', mkjStartOrderPolling);
