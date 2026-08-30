/**
 * Centralized Notification System for Mero Bhoj Admin Panel
 * Handles polling, header badge updates, dropdown panel rendering, and popup modals.
 */

(function () {
    'use strict';

    // Prevent duplicate initialization
    if (window.MeroBhojNotifications) {
        return;
    }

    const POLLING_INTERVAL = 10000; // Poll every 10 seconds
    const POPUP_SEEN_KEY = 'mkj_popup_seen_list';

    // Get list of already shown popups from localStorage to avoid repeats
    let popupSeenList = [];
    try {
        popupSeenList = JSON.parse(localStorage.getItem(POPUP_SEEN_KEY) || '[]');
    } catch (e) {
        popupSeenList = [];
    }

    function savePopupSeenList() {
        localStorage.setItem(POPUP_SEEN_KEY, JSON.stringify(popupSeenList));
    }

    /**
     * Initializer function
     */
    function init() {
        injectBellIcon();
        setupEventListeners();
        pollNotifications();
        setInterval(pollNotifications, POLLING_INTERVAL);
    }

    /**
     * Injects the bell icon into the admin topbar dynamically
     */
    function injectBellIcon() {
        const topbarActions = document.querySelector('.admin-topbar-actions');
        if (!topbarActions) return;

        // Check if container already exists to prevent duplicate insertion
        if (document.querySelector('.notification-bell-container')) return;

        const bellContainer = document.createElement('div');
        bellContainer.className = 'notification-bell-container';
        bellContainer.innerHTML = `
            <button type="button" class="notification-bell-btn" id="notification-bell-btn" aria-label="Notifications" aria-expanded="false">
                <span class="material-symbols-sharp">notifications</span>
                <span class="notification-badge hidden" id="notification-badge">0</span>
            </button>
            <div class="notification-dropdown-panel" id="notification-dropdown" aria-hidden="true">
                <div class="notification-dropdown-header">
                    <h4>Notifications</h4>
                    <button type="button" class="mark-all-read-btn" id="mark-all-read-btn">Mark All Read</button>
                </div>
                <div class="notification-list" id="notification-list">
                    <div class="notification-empty">
                        <span class="material-symbols-sharp">notifications_off</span>
                        <p>No notifications yet</p>
                    </div>
                </div>
            </div>
        `;

        // Insert before profile card, or at the start of actions
        const profile = topbarActions.querySelector('.admin-profile');
        if (profile) {
            topbarActions.insertBefore(bellContainer, profile);
        } else {
            topbarActions.appendChild(bellContainer);
        }
    }

    /**
     * Registers DOM event listeners using proper delegation and standard handlers
     */
    function setupEventListeners() {
        const bellBtn = document.getElementById('notification-bell-btn');
        const dropdown = document.getElementById('notification-dropdown');
        const markAllBtn = document.getElementById('mark-all-read-btn');
        const listContainer = document.getElementById('notification-list');

        if (bellBtn && dropdown) {
            bellBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = bellContainer().classList.toggle('open');
                bellBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                dropdown.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            const container = bellContainer();
            if (container && !container.contains(e.target)) {
                container.classList.remove('open');
                if (bellBtn) bellBtn.setAttribute('aria-expanded', 'false');
                if (dropdown) dropdown.setAttribute('aria-hidden', 'true');
            }
        });

        // Mark all as read button click handler
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                markAllNotificationsRead();
            });
        }

        // Event delegation for notification item clicks in the list
        if (listContainer) {
            listContainer.addEventListener('click', function (e) {
                const item = e.target.closest('.notification-item');
                if (!item) return;

                e.preventDefault();
                const type = item.dataset.type;
                const resourceId = item.dataset.resourceId;
                const redirectUrl = item.dataset.url;

                markReadAndRedirect(type, resourceId, redirectUrl);
            });
        }
    }

    function bellContainer() {
        return document.querySelector('.notification-bell-container');
    }

    /**
     * Polls the notifications endpoint to get latest updates
     */
    async function pollNotifications() {
        try {
            // Find base API URL path (relative to admin dashboard root)
            const response = await fetch('api/get_notifications.php', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok) return;

            const data = await response.json();
            if (data.success) {
                updateBadge(data.unread_count);
                renderDropdownList(data.recent);
                checkForNewPopup(data.recent);
            }
        } catch (error) {
            console.warn('Notification polling failed:', error);
        }
    }

    /**
     * Updates the bell badge unread count
     */
    function updateBadge(count) {
        const badge = document.getElementById('notification-badge');
        if (!badge) return;

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.remove('hidden');
            badge.classList.add('pulse-badge');
        } else {
            badge.classList.add('hidden');
            badge.classList.remove('pulse-badge');
        }
    }

    /**
     * Renders notifications inside the dropdown list
     */
    function renderDropdownList(items) {
        const listContainer = document.getElementById('notification-list');
        if (!listContainer) return;

        if (!items || items.length === 0) {
            listContainer.innerHTML = `
                <div class="notification-empty">
                    <span class="material-symbols-sharp">notifications_off</span>
                    <p>No notifications yet</p>
                </div>
            `;
            return;
        }

        let html = '';
        items.forEach(item => {
            const unreadClass = item.is_read ? '' : 'unread';
            const icon = getNotificationIconName(item.type);
            const timeDiff = formatTimeDifference(item.created_at);

            html += `
                <div class="notification-item ${unreadClass}" 
                     data-type="${item.type}" 
                     data-resource-id="${item.resource_id}" 
                     data-url="${item.url}">
                    <div class="notification-item-icon-wrapper notif-icon-${item.type}">
                        <span class="material-symbols-sharp">${icon}</span>
                    </div>
                    <div class="notification-item-content">
                        <h5>${escapeHtml(item.title)}</h5>
                        <p>${escapeHtml(item.description)}</p>
                        <span class="notification-item-time">${timeDiff}</span>
                    </div>
                    ${!item.is_read ? '<span class="notification-dot-indicator"></span>' : ''}
                </div>
            `;
        });

        listContainer.innerHTML = html;
    }

    /**
     * Checks if there's any new unread notification that we haven't popped up yet
     */
    function checkForNewPopup(items) {
        if (!items || items.length === 0) return;

        // Find the most recent unread item
        const unreadItems = items.filter(item => !item.is_read);
        if (unreadItems.length === 0) return;

        // Show the newest one first
        const newest = unreadItems[0];
        const uniqueKey = `${newest.type}_${newest.resource_id}`;

        if (!popupSeenList.includes(uniqueKey)) {
            popupSeenList.push(uniqueKey);
            // Cap history to prevent unbounded growth in localStorage
            if (popupSeenList.length > 100) {
                popupSeenList.shift();
            }
            savePopupSeenList();
            showPopupModal(newest);
        }
    }

    /**
     * Marks a single notification as read and redirects
     */
    async function markReadAndRedirect(type, resourceId, redirectUrl) {
        try {
            await fetch('api/mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: type, resource_id: resourceId })
            });
        } catch (error) {
            console.error('Failed to mark notification read:', error);
        }
        window.location.href = redirectUrl;
    }

    /**
     * Marks all visible notifications read
     */
    async function markAllNotificationsRead() {
        const items = document.querySelectorAll('.notification-item.unread');
        if (items.length === 0) return;

        const promises = Array.from(items).map(item => {
            const type = item.dataset.type;
            const resourceId = item.dataset.resourceId;
            return fetch('api/mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: type, resource_id: resourceId })
            });
        });

        try {
            await Promise.all(promises);
            pollNotifications();
        } catch (error) {
            console.error('Failed to mark all read:', error);
        }
    }

    /**
     * Renders and opens the popup modal alert based on the notification type
     */
    function showPopupModal(notif) {
        // Remove any existing active popup to avoid overlapping
        closeActivePopup();

        const overlay = document.createElement('div');
        overlay.className = 'notif-modal-overlay';
        overlay.id = 'notif-modal-popup';

        const data = notif.data;
        if (!data) return;

        let innerContent = '';

        if (notif.type === 'order') {
            const timeFormatted = data.order_time ? formatTimeOnly(data.order_time) : 'Just now';
            const itemsList = data.items.map(item => `
                <div class="preview-item-row">
                    <div class="preview-item-info">
                        ${item.menu_image ? `<img class="preview-item-img" src="${item.menu_image}" alt="${escapeHtml(item.menu_name)}">` : `<div class="preview-item-noimg"><span class="material-symbols-sharp">restaurant</span></div>`}
                        <span class="preview-item-name">${escapeHtml(item.menu_name)}</span>
                    </div>
                    <span class="preview-item-qty">Qty: ${item.quantity}</span>
                    <span class="preview-item-price">Rs. ${item.price}</span>
                </div>
            `).join('');

            innerContent = `
                <div class="notif-modal-card notif-card-order animate-card-in">
                    <button type="button" class="notif-modal-close-btn" aria-label="Close" id="popup-close-btn">
                        <span class="material-symbols-sharp">close</span>
                    </button>
                    <div class="notif-modal-hero">
                        <div class="notif-hero-circle order-theme">
                            <span class="material-symbols-sharp bell-shake">notifications</span>
                            <span class="circle-badge">1</span>
                        </div>
                        <h3>New Order Received!</h3>
                        <p>You have received a new order.</p>
                    </div>
                    
                    <div class="notif-info-grid">
                        <div class="info-cell">
                            <span class="info-label">Order ID</span>
                            <span class="info-value font-highlight">#${escapeHtml(data.order_number)}</span>
                        </div>
                        <div class="info-cell">
                            <span class="info-label">Time</span>
                            <span class="info-value">${timeFormatted}</span>
                        </div>
                        <div class="info-cell">
                            <span class="info-label">Type & Table</span>
                            <span class="info-value type-badge-orange">${escapeHtml(data.order_type)}</span>
                        </div>
                        <div class="info-cell">
                            <span class="info-label">Customer</span>
                            <span class="info-value">${escapeHtml(data.customer || 'Guest')}</span>
                        </div>
                    </div>

                    <div class="notif-panel-summary bg-green-light">
                        <span class="material-symbols-sharp text-green">shopping_bag</span>
                        <div>
                            <span class="summary-title">${data.items.length} ${data.items.length === 1 ? 'Item' : 'Items'} Total Amount</span>
                            <span class="summary-amount text-green">Rs. ${numberFormat(data.total_amount)}</span>
                        </div>
                    </div>

                    <div class="notif-panel-summary bg-orange-light">
                        <span class="material-symbols-sharp text-orange">description</span>
                        <div>
                            <span class="summary-title">Special Instructions</span>
                            <span class="summary-text">${escapeHtml(data.address || 'No extra instructions')}</span>
                        </div>
                    </div>

                    <div class="quick-preview-header">
                        <span>Quick Preview</span>
                    </div>

                    <div class="quick-preview-list">
                        ${itemsList}
                    </div>

                    <div class="notif-modal-footer">
                        <button type="button" class="btn-dismiss" id="popup-dismiss-btn">Dismiss</button>
                        <button type="button" class="btn-primary btn-orange" id="popup-view-btn">
                            View Order <span class="material-symbols-sharp">chevron_right</span>
                        </button>
                    </div>

                    <div class="bottom-footer-note">
                        <span class="material-symbols-sharp">bolt</span>
                        New orders are automatically assigned to the kitchen.
                    </div>
                </div>
            `;
        } else if (notif.type === 'booking') {
            innerContent = `
                <div class="notif-modal-card notif-card-booking animate-card-in">
                    <button type="button" class="notif-modal-close-btn" aria-label="Close" id="popup-close-btn">
                        <span class="material-symbols-sharp">close</span>
                    </button>
                    <div class="notif-modal-hero">
                        <div class="notif-hero-circle booking-theme">
                            <span class="material-symbols-sharp bell-shake">table_restaurant</span>
                            <span class="circle-badge">!</span>
                        </div>
                        <h3>New Table Booking!</h3>
                        <p>You have received a new table booking.</p>
                    </div>

                    <div class="notif-info-grid">
                        <div class="info-cell">
                            <span class="info-label">Booking ID</span>
                            <span class="info-value font-highlight">${escapeHtml(data.booking_id)}</span>
                        </div>
                        <div class="info-cell">
                            <span class="info-label">Time</span>
                            <span class="info-value">${escapeHtml(data.booking_time)}</span>
                        </div>
                        <div class="info-cell">
                            <span class="info-label">People</span>
                            <span class="info-value">${data.people} Guests</span>
                        </div>
                        <div class="info-cell">
                            <span class="info-label">Table</span>
                            <span class="info-value">${escapeHtml(data.table)}</span>
                        </div>
                    </div>

                    <div class="details-panel-card">
                        <div class="details-card-row">
                            <span class="material-symbols-sharp text-purple">person</span>
                            <div class="details-card-col">
                                <span class="details-label">Customer Name</span>
                                <span class="details-value">${escapeHtml(data.customer || 'Guest')}</span>
                            </div>
                        </div>
                        <div class="details-card-row">
                            <span class="material-symbols-sharp text-purple">phone</span>
                            <div class="details-card-col flex-row-spread">
                                <div>
                                    <span class="details-label">Phone</span>
                                    <span class="details-value">${escapeHtml(data.phone || 'N/A')}</span>
                                </div>
                                <span class="material-symbols-sharp text-purple-light">call</span>
                            </div>
                        </div>
                        <div class="details-card-row">
                            <span class="material-symbols-sharp text-purple">calendar_month</span>
                            <div class="details-card-col">
                                <span class="details-label">Date</span>
                                <span class="details-value">${escapeHtml(data.booking_date)}</span>
                            </div>
                        </div>
                        <div class="details-card-row">
                            <span class="material-symbols-sharp text-purple">rate_review</span>
                            <div class="details-card-col">
                                <span class="details-label">Special Request</span>
                                <span class="details-value italic-value">${escapeHtml(data.message || 'No special request')}</span>
                            </div>
                        </div>
                    </div>

                    <div class="purple-notice-banner">
                        <span class="material-symbols-sharp">sparkles</span>
                        Please confirm the booking to avoid any conflicts.
                    </div>

                    <div class="notif-modal-footer">
                        <button type="button" class="btn-dismiss" id="popup-dismiss-btn">Dismiss</button>
                        <button type="button" class="btn-primary btn-purple" id="popup-view-btn">
                            View Booking <span class="material-symbols-sharp">chevron_right</span>
                        </button>
                    </div>
                </div>
            `;
        } else if (notif.type === 'feedback') {
            const stars = '⭐'.repeat(data.rating) + '☆'.repeat(5 - data.rating);

            innerContent = `
                <div class="notif-modal-card notif-card-feedback animate-card-in">
                    <button type="button" class="notif-modal-close-btn" aria-label="Close" id="popup-close-btn">
                        <span class="material-symbols-sharp">close</span>
                    </button>
                    <div class="notif-modal-hero">
                        <div class="notif-hero-circle feedback-theme">
                            <span class="material-symbols-sharp bell-shake">rate_review</span>
                        </div>
                        <h3>New Feedback</h3>
                        <p>You have received customer feedback.</p>
                    </div>

                    <div class="notif-info-grid">
                        <div class="info-cell">
                            <span class="info-label">Customer Name</span>
                            <span class="info-value font-highlight">${escapeHtml(data.customer || 'Guest')}</span>
                        </div>
                        <div class="info-cell">
                            <span class="info-label">Rating</span>
                            <span class="info-value text-gold font-lg">${stars}</span>
                        </div>
                        <div class="info-cell">
                            <span class="info-label">Time Submitted</span>
                            <span class="info-value">${formatTimeDifference(data.created_at)}</span>
                        </div>
                    </div>

                    <div class="feedback-message-card">
                        <span class="quote-mark">“</span>
                        <p class="feedback-text-content">${escapeHtml(data.message)}</p>
                        <span class="quote-mark text-right">”</span>
                    </div>

                    <div class="notif-modal-footer margin-top-lg">
                        <button type="button" class="btn-dismiss" id="popup-dismiss-btn">Dismiss</button>
                        <button type="button" class="btn-primary btn-blue" id="popup-view-btn">
                            View Feedback <span class="material-symbols-sharp">chevron_right</span>
                        </button>
                    </div>
                </div>
            `;
        }

        overlay.innerHTML = innerContent;
        document.body.appendChild(overlay);

        // Add event listeners inside the modal alert popup
        const dismissBtn = overlay.querySelector('#popup-dismiss-btn');
        const closeIconBtn = overlay.querySelector('#popup-close-btn');
        const viewBtn = overlay.querySelector('#popup-view-btn');

        const dismissAction = function () {
            // Dismiss just fades out and marks it read in background so it doesn't pop up again
            closeActivePopup();
            fetch('api/mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: notif.type, resource_id: notif.resource_id })
            }).then(() => pollNotifications());
        };

        if (dismissBtn) dismissBtn.addEventListener('click', dismissAction);
        if (closeIconBtn) closeIconBtn.addEventListener('click', dismissAction);

        if (viewBtn) {
            viewBtn.addEventListener('click', function () {
                closeActivePopup();
                markReadAndRedirect(notif.type, notif.resource_id, notif.url);
            });
        }
    }

    /**
     * Closes the active popup alert with animation
     */
    function closeActivePopup() {
        const existing = document.getElementById('notif-modal-popup');
        if (!existing) return;

        const card = existing.querySelector('.notif-modal-card');
        if (card) {
            card.classList.remove('animate-card-in');
            card.classList.add('animate-card-out');
            existing.classList.add('fade-out');

            setTimeout(() => {
                existing.remove();
            }, 300);
        } else {
            existing.remove();
        }
    }

    /* Helper Utilities */

    function getNotificationIconName(type) {
        switch (type) {
            case 'order': return 'shopping_bag';
            case 'booking': return 'calendar_month';
            case 'feedback': return 'rate_review';
            default: return 'notifications';
        }
    }

    function formatTimeDifference(dateStr) {
        if (!dateStr) return 'Just now';
        const date = new Date(dateStr.replace(/-/g, '/')); // Handle Safari compatibility
        const diffMs = Date.now() - date.getTime();
        const diffMins = Math.floor(diffMs / 60000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;

        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return `${diffHours}h ago`;

        const diffDays = Math.floor(diffHours / 24);
        return `${diffDays}d ago`;
    }

    function formatTimeOnly(timeStr) {
        if (!timeStr) return '';
        // If MySQL datetime is passed, extract time part
        const parts = timeStr.split(' ');
        const timePart = parts.length > 1 ? parts[1] : parts[0];
        const subparts = timePart.split(':');
        if (subparts.length < 2) return timeStr;

        let hour = parseInt(subparts[0], 10);
        const min = subparts[1];
        const ampm = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12;
        hour = hour ? hour : 12; // 0 should be 12

        return `${hour}:${min} ${ampm}`;
    }

    function numberFormat(val) {
        const num = parseFloat(val);
        return isNaN(num) ? '0.00' : num.toFixed(2);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Run on DOM load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose APIs globally for manual debugging/triggers if needed
    window.MeroBhojNotifications = {
        init: init,
        poll: pollNotifications,
        dismissAll: markAllNotificationsRead,
        triggerPopup: showPopupModal
    };

})();
