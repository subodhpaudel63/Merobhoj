// Admin-side shared behaviors
// Used by: admin/login.php, admin/index.php, admin/menu.php, admin/orders_page.php, admin/bookings.php, admin/order_view.php, admin/feedback.php, admin/users.php

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

    // Central handler â€” exposed globally so the header button's inline
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




/* ============================================================
   Scoped in an IIFE and guarded so it only runs on the feedback
   page (this file is shared by the other admin pages).
   Review data is injected by feedback.php into
   window.__FEEDBACK_DATA__ (fetched from the feedback table) â€”
   no dummy/seed data lives here.
   Delete  -> ../includes/delete_feedback.php  (POST feedback_id)
   Add     -> ../includes/feedback_form.php    (POST name/email/rating/comments)
   Note: the response feature is kept in memory only â€” the
   feedback table has no response column yet.
   ============================================================ */
(function () {
    // Feedback page only â€” skip everywhere else
    if (!document.getElementById('reviewTable')) return;

    const reviews = Array.isArray(window.__FEEDBACK_DATA__)
        ? window.__FEEDBACK_DATA__
        : [];

    let selectedReviewId = null;

    const $ = id => document.getElementById(id);

    function pct(n, total) {
        return total ? Math.round((n / total) * 100) : 0;
    }

    function stars(rating) {
        return "â˜…".repeat(rating) + "â˜†".repeat(5 - rating);
    }

    function initials(name) {
        return name.split(/\s+/).map(x => x[0]).join("").slice(0, 2).toUpperCase();
    }

    function formatDate(iso) {
        if (!iso) return "";
        const [y, m, d] = iso.split("-").map(Number);
        return new Date(y, m - 1, d).toLocaleDateString("en-US", {
            month: "short", day: "numeric", year: "numeric"
        });
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    /* Newest review first: DB rows arrive newest-first (created_at DESC)
       and reviews added through the modal get a much larger timestamp id. */
    function getLatestReview() {
        return reviews.reduce(
            (latest, review) => (!latest || review.id > latest.id ? review : latest),
            null
        );
    }

    function getFilteredReviews() {
        const search = $("searchInput").value.trim().toLowerCase();
        const rating = $("ratingFilter").value;
        const from = $("dateFrom").value;
        const to = $("dateTo").value;

        return reviews.filter(r => {
            const textMatch = !search ||
                `${r.name} ${r.email} ${r.review}`.toLowerCase().includes(search);

            const ratingMatch = rating === "all" || String(r.rating) === rating;

            const fromMatch = !from || r.isoDate >= from;
            const toMatch = !to || r.isoDate <= to;

            return textMatch && ratingMatch && fromMatch && toMatch;
        });
    }

    function updateFilterSummary() {
        const parts = [];
        const search = $("searchInput").value.trim();
        const rating = $("ratingFilter").value;
        const from = $("dateFrom").value;
        const to = $("dateTo").value;

        if (search) parts.push(`Search: "${search}"`);
        if (rating !== "all") parts.push(`${rating} star${rating === "1" ? "" : "s"}`);
        if (from || to) parts.push(`${from ? formatDate(from) : "Any"} - ${to ? formatDate(to) : "Any"}`);

        $("filterSummary").hidden = parts.length === 0;
        $("summaryText").textContent = parts.join(" â€¢ ");

        $("clearSearch").hidden = !search;

        $("dateBtnText").textContent =
            from || to
                ? `${from ? formatDate(from) : "Any"} - ${to ? formatDate(to) : "Any"}`
                : "Select Date Range";
    }

    function render() {
        const filtered = getFilteredReviews();
        const total = reviews.length;

        const avg = total
            ? reviews.reduce((sum, r) => sum + r.rating, 0) / total
            : 0;

        const positive = reviews.filter(r => r.rating >= 4).length;
        const responded = reviews.filter(r => r.responded).length;

        $("totalReviews").textContent = total;
        $("averageRating").textContent = `${avg.toFixed(1)} / 5`;
        $("averageStars").textContent = stars(Math.round(avg));
        $("ratingBased").textContent = `Based on ${total} review${total === 1 ? "" : "s"}`;
        $("positiveRatio").textContent = `${pct(positive, total)}%`;
        $("positiveCount").textContent = `(${positive} positive)`;
        $("responseRate").textContent = `${pct(responded, total)}%`;
        $("responseText").textContent = responded
            ? `${responded} response${responded === 1 ? "" : "s"} sent`
            : "No responses yet";

        const counts = [1, 2, 3, 4, 5].map(
            n => reviews.filter(r => r.rating === n).length
        );

        const [one, two, three, four, five] = counts;
        const low = one + two;

        $("donutTotal").textContent = total;
        $("fiveCount").textContent = `${five} (${pct(five, total)}%)`;
        $("fourCount").textContent = `${four} (${pct(four, total)}%)`;
        $("threeCount").textContent = `${three} (${pct(three, total)}%)`;
        $("lowCount").textContent = `${low} (${pct(low, total)}%)`;

        for (let n = 1; n <= 5; n++) {
            const count = counts[n - 1];
            $("bar" + n).style.width = pct(count, total) + "%";
            $("dist" + n).textContent = `${count} (${pct(count, total)}%)`;
        }

        if (total) {
            let start = 0;
            const colors = ["#e83e54", "#e83e54", "#ff641b", "#fbb12a", "#32c36c"];
            const pieces = [];

            counts.forEach((count, index) => {
                const end = start + (count / total) * 360;
                if (count) pieces.push(`${colors[index]} ${start}deg ${end}deg`);
                start = end;
            });

            $("donut").style.background =
                pieces.length
                    ? `conic-gradient(${pieces.join(", ")})`
                    : "#edf0f4";
        } else {
            $("donut").style.background = "#edf0f4";
        }

        const tbody = $("reviewTable");
        tbody.innerHTML = "";

        filtered.forEach(review => {
            const row = document.createElement("tr");

            row.innerHTML = `
                <td>
                    <div class="customer">
                        <div class="avatar">${initials(review.name)}</div>
                        <div>${escapeHtml(review.name)}</div>
                    </div>
                </td>

                <td class="email">${escapeHtml(review.email)}</td>

                <td class="rating-cell">
                    <span class="stars">${stars(review.rating)}</span>
                    <span class="rating-num">${review.rating} / 5</span>
                </td>

                <td class="review-text">${escapeHtml(review.review)}</td>

                <td class="date-cell">
                    ${formatDate(review.isoDate)}
                    <br>
                    <span class="email">${review.time}</span>
                </td>

                <td>
                    <div class="action">
                        <button class="view-action" data-id="${review.id}">
                            <i data-lucide="eye"></i>
                            View
                        </button>
                        <button class="delete" data-delete="${review.id}">
                            <i data-lucide="trash-2"></i>
                            Delete
                        </button>
                    </div>
                </td>
            `;

            tbody.appendChild(row);
        });

        $("emptyState").hidden = filtered.length !== 0;
        $("resultCount").textContent =
            `${filtered.length} result${filtered.length === 1 ? "" : "s"}`;

        $("showingText").textContent = filtered.length
            ? `Showing 1 to ${filtered.length} of ${total} review${total === 1 ? "" : "s"}`
            : "No reviews to show";

        const latest = getLatestReview();

        $("activity").innerHTML = latest
            ? `
                <div class="activity-item">
                    <div class="activity-avatar">${initials(latest.name)}</div>
                    <div class="activity-copy">
                        <strong>${escapeHtml(latest.name)}</strong>
                        <p>gave a ${latest.rating}-star rating.</p>
                        <span class="activity-rating">${latest.rating} â˜…</span>
                    </div>
                    <span class="activity-time">${latest.time}</span>
                </div>
            `
            : `<p>No activity yet.</p>`;

        updateFilterSummary();

        lucide.createIcons();
    }

    /* Search */
    $("searchInput").addEventListener("input", render);

    $("clearSearch").addEventListener("click", () => {
        $("searchInput").value = "";
        render();
    });

    /* Rating */
    $("ratingFilter").addEventListener("change", render);

    /* Date picker */
    $("dateBtn").addEventListener("click", () => {
        $("datePopover").hidden = !$("datePopover").hidden;
    });

    $("closeDatePopover").addEventListener("click", () => {
        $("datePopover").hidden = true;
    });

    $("applyDate").addEventListener("click", () => {
        const from = $("dateFrom").value;
        const to = $("dateTo").value;

        if (from && to && from > to) {
            alert("The start date cannot be after the end date.");
            return;
        }

        $("datePopover").hidden = true;
        render();
    });

    $("clearDate").addEventListener("click", () => {
        $("dateFrom").value = "";
        $("dateTo").value = "";
        $("datePopover").hidden = true;
        render();
    });

    /* Filter button */
    $("filterBtn").addEventListener("click", () => {
        const filtered = getFilteredReviews();
        $("filterBtn").classList.toggle("active-filter", filtered.length !== reviews.length);
        render();
    });

    /* Reset */
    $("resetBtn").addEventListener("click", () => {
        $("searchInput").value = "";
        $("ratingFilter").value = "all";
        $("dateFrom").value = "";
        $("dateTo").value = "";
        $("datePopover").hidden = true;
        render();
    });

    $("clearAllFilters").addEventListener("click", () => {
        $("resetBtn").click();
    });

    /* Add review modal */
    $("openReview").addEventListener("click", () => {
        $("reviewModal").hidden = false;
        $("customerName").focus();
    });

    $("closeReview").addEventListener("click", () => {
        $("reviewModal").hidden = true;
    });

    $("reviewModal").addEventListener("click", e => {
        if (e.target === $("reviewModal")) $("reviewModal").hidden = true;
    });

    /* Add review â€” saved through the existing feedback form endpoint */
    $("reviewForm").addEventListener("submit", e => {
        e.preventDefault();

        const submitBtn = e.target.querySelector(".submit-review");
        if (submitBtn) submitBtn.disabled = true;

        const payload = new FormData();
        payload.append("name", $("customerName").value.trim());
        payload.append("email", $("customerEmail").value.trim());
        payload.append("rating", $("customerRating").value);
        payload.append("comments", $("customerReview").value.trim());

        fetch("../includes/feedback_form.php", { method: "POST", body: payload })
            .then(res => res.json())
            .then(result => {
                if (!result || result.status !== "success") {
                    alert((result && result.message) || "Unable to save the review.");
                    return;
                }

                const now = new Date();
                const iso = now.toISOString().slice(0, 10);

                reviews.push({
                    id: now.getTime(), // temporary id until the page is reloaded
                    name: $("customerName").value.trim(),
                    email: $("customerEmail").value.trim(),
                    rating: Number($("customerRating").value),
                    review: $("customerReview").value.trim(),
                    isoDate: iso,
                    date: formatDate(iso),
                    time: now.toLocaleTimeString("en-US", {
                        hour: "2-digit",
                        minute: "2-digit"
                    }),
                    responded: false
                });

                $("reviewForm").reset();
                $("reviewModal").hidden = true;
                render();
            })
            .catch(() => alert("Unable to save the review right now."))
            .finally(() => {
                if (submitBtn) submitBtn.disabled = false;
            });
    });

    /* View modal */
    function openView(id) {
        const review = reviews.find(r => r.id === id);
        if (!review) return;

        selectedReviewId = id;

        $("viewAvatar").textContent = initials(review.name);
        $("viewName").textContent = review.name;
        $("viewEmail").textContent = review.email;

        $("viewRating").innerHTML = `
            <span>${stars(review.rating)}</span>
            <span class="rating-label">${review.rating}.0 / 5</span>
        `;

        $("viewReview").textContent = review.review;
        $("viewDate").textContent = formatDate(review.isoDate);
        $("viewTime").textContent = review.time;

        $("viewModal").hidden = false;
        lucide.createIcons();
    }

    document.addEventListener("click", e => {
        const viewButton = e.target.closest(".view-action");
        const deleteButton = e.target.closest(".delete");

        if (viewButton) {
            openView(Number(viewButton.dataset.id));
        }

        if (deleteButton) {
            deleteReview(Number(deleteButton.dataset.delete));
        }
    });

    function closeView() {
        $("viewModal").hidden = true;
    }

    $("closeView").addEventListener("click", closeView);
    $("closeViewBottom").addEventListener("click", closeView);

    $("viewModal").addEventListener("click", e => {
        if (e.target === $("viewModal")) closeView();
    });

    /* Delete â€” removed from the database through delete_feedback.php */
    function deleteReview(id) {
        const review = reviews.find(r => r.id === id);

        if (!review) return;

        openDeleteConfirm({
            title: 'Delete Review?',
            message: `The review from ${review.name} will be permanently deleted. This action cannot be undone.`,
            onConfirm: function () { performReviewDelete(id); }
        });
    }

    function performReviewDelete(id) {
        const payload = new FormData();
        payload.append("feedback_id", id);

        fetch("../includes/delete_feedback.php", { method: "POST", body: payload })
            .then(res => res.json())
            .then(result => {
                if (!result || !result.success) {
                    alert((result && result.message) || "Unable to delete the review.");
                    return;
                }

                const index = reviews.findIndex(r => r.id === id);
                if (index !== -1) reviews.splice(index, 1);

                if (selectedReviewId === id) {
                    closeView();
                    selectedReviewId = null;
                }

                render();
            })
            .catch(() => alert("Unable to delete the review right now."));
    }

    /* Response â€” in memory only (no response column in the feedback table yet) */
    function openResponse(id) {
        const review = reviews.find(r => r.id === id);
        if (!review) return;

        selectedReviewId = id;
        $("responseFor").textContent = `Responding to ${review.name}`;
        $("responseTextInput").value = "";
        $("viewModal").hidden = true;
        $("responseModal").hidden = false;
        $("responseTextInput").focus();
    }

    $("writeResponse").addEventListener("click", () => {
        const latest = getLatestReview();

        if (!latest) {
            alert("Add a review first.");
            return;
        }

        openResponse(latest.id);
    });

    $("viewRespond").addEventListener("click", () => {
        if (selectedReviewId) openResponse(selectedReviewId);
    });

    $("closeResponse").addEventListener("click", () => {
        $("responseModal").hidden = true;
    });

    $("responseModal").addEventListener("click", e => {
        if (e.target === $("responseModal")) {
            $("responseModal").hidden = true;
        }
    });

    $("sendResponse").addEventListener("click", () => {
        const text = $("responseTextInput").value.trim();

        if (!text) {
            alert("Please write a response first.");
            return;
        }

        const review = reviews.find(r => r.id === selectedReviewId);

        if (review) {
            review.responded = true;
            review.response = text;
        }

        $("responseModal").hidden = true;
        render();
    });

    /* Close popovers/modals with Escape */
    document.addEventListener("keydown", e => {
        if (e.key !== "Escape") return;

        $("datePopover").hidden = true;
        $("reviewModal").hidden = true;
        $("viewModal").hidden = true;
        $("responseModal").hidden = true;
    });

    lucide.createIcons();
    render();
})();

/* ============================================================
   SHARED DELETE CONFIRMATION MODAL â€” same UI as the orders page
   (delete-confirm-modal). Used by every admin-panel delete action.
   Styles live in assets/css/adminstyle.css.

   openDeleteConfirm({
       title: 'Delete Order?',
       message: 'It will be permanently deleted.',
       confirmText: 'Delete',              // optional
       onConfirm: function () { ... }      // runs when Delete is clicked
   });

   For plain POST forms use:
       <form onsubmit="return confirmFormDelete(event, 'Title', 'Message')">
   ============================================================ */
window.openDeleteConfirm = function (options) {
    const opts = options || {};
    const type = opts.type || 'danger'; // 'danger' or 'success'

    const existing = document.getElementById('deleteConfirmModal');
    if (existing) existing.remove();

    const iconColor = type === 'success' ? '#10b981' : '#dc2626';
    const btnClass = type === 'success' ? 'btn-success-confirm' : 'btn-delete-confirm';
    
    // Icon SVG based on type
    const svgIcon = type === 'success' 
        ? `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="${iconColor}" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>`
        : `<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="${iconColor}" stroke-width="2"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"/></svg>`;

    const modalHtml = `
        <div id="deleteConfirmModal" class="delete-confirm-overlay">
            <div class="delete-confirm-modal">
                <div class="icon-wrapper">
                    ${svgIcon}
                </div>
                <h3></h3>
                <p></p>
                ${opts.showInput ? `<textarea id="confirmInput" class="mb-input" placeholder="${opts.inputPlaceholder || ''}" style="width:100%; margin-bottom:1rem; padding:0.5rem; border:1px solid #ccc; border-radius:4px; font-family:inherit; resize:vertical; min-height:60px;"></textarea>` : ''}
                <div class="delete-confirm-buttons">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="button" class="${btnClass}"></button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', modalHtml);

    const modal = document.getElementById('deleteConfirmModal');
    modal.querySelector('h3').textContent = opts.title || 'Delete?';
    modal.querySelector('p').textContent = opts.message
        || 'This item will be permanently deleted. This action cannot be undone.';

    const confirmBtn = modal.querySelector(`.${btnClass}`);
    confirmBtn.textContent = opts.confirmText || 'Delete';
    if (type === 'success') {
        confirmBtn.style.background = 'var(--clr-success, #2ed573)';
        confirmBtn.style.color = 'white';
        confirmBtn.style.border = 'none';
        confirmBtn.style.padding = '0.6rem 1.2rem';
        confirmBtn.style.borderRadius = 'var(--border-radius-1, 6px)';
        confirmBtn.style.cursor = 'pointer';
        confirmBtn.style.fontWeight = '600';
    }
    
    confirmBtn.addEventListener('click', function () {
        const inputVal = opts.showInput ? document.getElementById('confirmInput').value.trim() : '';
        if (opts.showInput && opts.requireInput && !inputVal) {
            alert('Please provide a reason.');
            return;
        }
        closeDeleteConfirm();
        if (typeof opts.onConfirm === 'function') opts.onConfirm(inputVal);
    });

    const cancelBtn = modal.querySelector('.btn-cancel');
    cancelBtn.addEventListener('click', function () {
        closeDeleteConfirm();
    });

    modal.addEventListener('click', function (e) {
        if (e.target === this) closeDeleteConfirm();
    });
};

window.closeDeleteConfirm = function () {
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) {
        modal.style.animation = 'fadeOut 0.3s ease-in-out';
        setTimeout(() => modal.remove(), 300);
    }
};

/* Confirmation wrapper for plain POST forms */
window.confirmFormDelete = function (event, title, message) {
    event.preventDefault();
    const form = event.target;
    openDeleteConfirm({
        title: title,
        message: message,
        onConfirm: function () { form.submit(); }
    });
    return false;
};

/* Close the delete confirmation with Escape */
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeDeleteConfirm();
});


