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

    // Shared handler for every admin, chef, staff, and rider panel.
    window.mkjToggleSidebar = function(e) {
        if (e && e.preventDefault) e.preventDefault();
        const btn = document.getElementById('menu_toggle');
        const icon = btn ? btn.querySelector('span') : null;

        if (isMobileView()) {
            body.classList.remove('sidebar-collapsed');
            if (sidebar) sidebar.classList.remove('is-collapsed');
            body.classList.toggle('sidebar-open');
        } else {
            body.classList.remove('sidebar-open');
            body.classList.toggle('sidebar-collapsed');
            if (sidebar) sidebar.classList.toggle('is-collapsed');
        }

        const hidden = body.classList.contains('sidebar-collapsed') || body.classList.contains('sidebar-open');
        if (btn) btn.setAttribute('aria-expanded', hidden ? 'false' : 'true');
        if (icon) icon.textContent = hidden ? 'menu' : 'menu_open';
    };

    document.addEventListener('click', function(e) {
        const menuToggle = e.target.closest('#menu_toggle');
        if (menuToggle) window.mkjToggleSidebar(e);
    });

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
        if (isMobileView()) {
            body.classList.remove('sidebar-collapsed');
            if (sidebar) sidebar.classList.remove('is-collapsed');
        } else {
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
                            <span class="material-symbols-sharp">visibility</span>
                            View
                        </button>
                        <button class="delete" data-delete="${review.id}">
                            <span class="material-symbols-sharp">delete</span>
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
                        <span class="activity-rating">${latest.rating} ★</span>
                    </div>
                    <span class="activity-time">${latest.time}</span>
                </div>
            `
            : `<p>No activity yet.</p>`;

        updateFilterSummary();
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

/* Promise-based confirmation wrapper */
if (typeof window.confirmAction !== 'function') {
    window.confirmAction = function (options) {
        return new Promise(function (resolve) {
            if (typeof window.openDeleteConfirm !== 'function') {
                const fallbackOpts = options || {};
                resolve(window.confirm((fallbackOpts.title ? fallbackOpts.title + '\n\n' : '') + (fallbackOpts.message || 'Are you sure?')));
                return;
            }

            let settled = false;
            const opts = Object.assign({}, options || {});
            const userOnConfirm = opts.onConfirm;
            opts.onConfirm = function (inputVal) {
                settled = true;
                resolve(true);
                if (typeof userOnConfirm === 'function') userOnConfirm(inputVal);
            };

            openDeleteConfirm(opts);

            const observer = new MutationObserver(function () {
                if (settled) {
                    observer.disconnect();
                    return;
                }
                if (!document.getElementById('deleteConfirmModal')) {
                    settled = true;
                    observer.disconnect();
                    resolve(false);
                }
            });
            observer.observe(document.body, { childList: true });
        });
    };
}

/* ============================================================
   STAFF MANAGEMENT LOGIC (from staff.js)
   ============================================================ */
(function () {
  'use strict';

  function toast(msg, type) {
    if (window.ToastNotifications) {
      if (type === 'success') window.ToastNotifications.success(msg);
      else window.ToastNotifications.error(msg);
    } else {
      alert(msg);
    }
  }

  function openModalExclusive(modalId) {
    var modals = ['staffDetailsModal', 'staffSettingsModal', 'addStaffModal'];
    modals.forEach(function (id) {
      var m = document.getElementById(id);
      if (m) m.classList.remove('active');
    });
    var target = document.getElementById(modalId);
    if (target) target.classList.add('active');
  }

  function closeModal(modalId) {
    var target = document.getElementById(modalId);
    if (target) target.classList.remove('active');
  }

  function handleCheckIn(userId, userName) {
    if (typeof window.openDeleteConfirm === 'function') {
      openDeleteConfirm({
        title: 'Check In Staff?',
        message: 'Mark check-in timestamp for ' + userName + ' at current time?',
        confirmText: 'Check In',
        type: 'success',
        onConfirm: function () {
          processAttendance(userId, 'check_in');
        }
      });
    } else if (confirm('Check in ' + userName + '?')) {
      processAttendance(userId, 'check_in');
    }
  }

  function handleCheckOut(userId, userName) {
    if (typeof window.openDeleteConfirm === 'function') {
      openDeleteConfirm({
        title: 'Check Out Staff?',
        message: 'Record check-out & calculate total working hours for ' + userName + '?',
        confirmText: 'Check Out',
        type: 'success',
        onConfirm: function () {
          processAttendance(userId, 'check_out');
        }
      });
    } else if (confirm('Check out ' + userName + '?')) {
      processAttendance(userId, 'check_out');
    }
  }

  function processAttendance(userId, action, dateStr) {
    fetch('staff.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: new URLSearchParams({
        action: action,
        user_id: userId,
        date: dateStr || ''
      })
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (res.success) {
        toast(res.message || 'Attendance updated!', 'success');
        setTimeout(function () { location.reload(); }, 500);
      } else {
        toast(res.message || 'Action failed', 'error');
      }
    })
    .catch(function (err) {
      toast('Network error updating attendance', 'error');
      console.error(err);
    });
  }

  function openStaffDetails(userId) {
    fetch('staff.php?action=get_details&user_id=' + Number(userId), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (!res.success) { toast(res.message || 'Failed to load details', 'error'); return; }
      var d = res.data;
      var setVal = function(id, val) {
        var el = document.getElementById(id);
        if (el) el.textContent = val || '—';
      };

      setVal('sdName', d.name);
      setVal('sdEmail', d.email);
      setVal('sdRole', d.role);
      setVal('sdPhone', d.phone || 'N/A');
      setVal('sdJoined', d.joined);
      setVal('sdStatus', d.status);
      setVal('sdCheckIn', d.check_in || 'Not checked in');
      setVal('sdCheckOut', d.check_out || 'Not checked out');
      setVal('sdHoursToday', d.working_hours_today);
      setVal('sdHoursMonth', d.working_hours_month);
      setVal('sdAttPct', d.attendance_pct + '%');

      openModalExclusive('staffDetailsModal');
    })
    .catch(function (err) {
      toast('Error fetching staff profile', 'error');
    });
  }

  function openSettingsModal() {
    openModalExclusive('staffSettingsModal');
  }

  function openAddStaffModal() {
    openModalExclusive('addStaffModal');
  }

  function filterStaffDirectory(statusFilter) {
    var searchVal = (document.getElementById('staffSearchInput') || {}).value || '';
    searchVal = searchVal.toLowerCase().trim();

    if (statusFilter !== undefined && statusFilter !== null) {
      window._activeStatusChip = statusFilter;
      var chips = document.querySelectorAll('.chip-btn');
      chips.forEach(function (c) {
        c.classList.remove('active');
        if (c.getAttribute('data-status') === statusFilter) c.classList.add('active');
      });
    }

    var activeChip = window._activeStatusChip || 'all';
    var rows = document.querySelectorAll('.staff-dir-row');

    rows.forEach(function (row) {
      var rowStatus = row.getAttribute('data-status') || '';
      var rowText = row.textContent.toLowerCase();

      var matchesSearch = !searchVal || rowText.indexOf(searchVal) !== -1;
      var matchesStatus = activeChip === 'all' || rowStatus === activeChip;

      row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
    });
  }

  function exportAttendanceCSV() {
    var table = document.getElementById('mainStaffTable');
    if (!table) return;

    var rows = Array.from(table.querySelectorAll('tr'));
    var csvContent = rows.map(function (row) {
      var cols = Array.from(row.querySelectorAll('th, td'));
      return cols.map(function (col) {
        var text = col.innerText.replace(/(\r\n|\n|\r)/gm, ' ').replace(/"/g, '""').trim();
        return '"' + text + '"';
      }).join(',');
    }).join('\n');

    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', 'staff_attendance_' + new Date().toISOString().slice(0, 10) + '.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    toast('Attendance records exported to CSV!', 'success');
  }

  document.addEventListener('DOMContentLoaded', function () {
    var setForm = document.getElementById('staffSettingsForm');
    if (setForm) {
      setForm.onsubmit = function (e) {
        e.preventDefault();
        var formData = new FormData(setForm);
        formData.append('action', 'save_settings');

        fetch('staff.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.success) {
            toast('Staff settings updated successfully!', 'success');
            closeModal('staffSettingsModal');
            setTimeout(function () { location.reload(); }, 600);
          } else {
            toast(res.message || 'Failed to update settings', 'error');
          }
        })
        .catch(function (err) {
          toast('Error saving settings', 'error');
        });
      };
    }

    var addForm = document.getElementById('addStaffForm');
    if (addForm) {
      addForm.onsubmit = function (e) {
        e.preventDefault();
        var formData = new FormData(addForm);
        formData.append('action', 'add_staff');

        fetch('staff.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.success) {
            toast('New staff account created successfully!', 'success');
            closeModal('addStaffModal');
            setTimeout(function () { location.reload(); }, 600);
          } else {
            toast(res.message || 'Failed to add staff', 'error');
          }
        })
        .catch(function (err) {
          toast('Error adding staff member', 'error');
        });
      };
    }
  });

  window.handleCheckIn        = window.handleCheckIn || handleCheckIn;
  window.handleCheckOut       = window.handleCheckOut || handleCheckOut;
  window.openStaffDetails     = window.openStaffDetails || openStaffDetails;
  window.openSettingsModal    = window.openSettingsModal || openSettingsModal;
  window.openAddStaffModal    = window.openAddStaffModal || openAddStaffModal;
  window.closeStaffModal      = window.closeStaffModal || closeModal;
  window.filterStaffDirectory = window.filterStaffDirectory || filterStaffDirectory;
  window.exportAttendanceCSV  = window.exportAttendanceCSV || exportAttendanceCSV;
}());

/* ============================================================
   FLOOR STAFF & FLOOR PLAN LOGIC (from floor_plan.js)
   ============================================================ */
(function () {
  'use strict';

  var _allTables        = [];
  var _statusFilter     = 'all';
  var _activeTableId    = null;
  var _activeOrderNum   = null;
  var _activeOrderSub   = 0;
  var _selectedPM       = 'Cash';
  var _refreshTimer     = null;

  function esc(str) {
    var d = document.createElement('div');
    d.textContent = String(str || '');
    return d.innerHTML;
  }

  function fmt(n) {
    return 'NPR ' + (Number(n) || 0).toLocaleString('en-IN', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function toast(msg, type) {
    if (window.ToastNotifications) {
      if (type === 'success') window.ToastNotifications.success(msg);
      else window.ToastNotifications.error(msg);
    } else {
      alert(msg);
    }
  }

  function statusLabel(s) {
    if (s === 'occupied') return 'OCCUPIED';
    if (s === 'reserved') return 'RESERVED';
    if (s === 'dirty')    return 'DIRTY';
    return 'AVAILABLE';
  }

  function statusClass(s) {
    if (s === 'occupied') return 'st-cancelled';
    if (s === 'reserved') return 'st-pending';
    if (s === 'dirty')    return 'st-dirty';
    return 'st-confirmed';
  }

  function gid(id) { return document.getElementById(id); }

  function loadFloorPlan() {
    var canvas = gid('floorCanvas');
    if (!canvas) return;

    canvas.innerHTML = '<p class="fp-canvas-loading">Loading MeroBhoj Floor Plan...</p>';
    var apiUrl = window.FP_API_URL || '../staff/api/floor.php';

    fetch(apiUrl, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) {
      if (!r.ok) { throw new Error('HTTP ' + r.status); }
      return r.json();
    })
    .then(function (data) {
      if (!data.success) {
        canvas.innerHTML = '<p class="fp-canvas-loading" style="color:#ef4444;">Error: ' +
          esc(data.message || 'Failed to load tables') + '</p>';
        return;
      }
      _allTables = Array.isArray(data.tables) ? data.tables : [];
      renderFloor();
    })
    .catch(function (err) {
      canvas.innerHTML = '<p class="fp-canvas-loading" style="color:#ef4444;">Could not load floor plan. Please refresh.</p>';
      console.error('[FloorPlan] load error:', err);
    });
  }

  function renderFloor() {
    var canvas = gid('floorCanvas');
    if (!canvas) return;

    var search = (gid('searchInput') || { value: '' }).value.toLowerCase().trim();
    var tables = _allTables.slice();

    if (_statusFilter !== 'all') {
      tables = tables.filter(function (t) {
        return (t.current_status || 'free') === _statusFilter;
      });
    }
    if (search) {
      tables = tables.filter(function (t) {
        return (t.table_name || '').toLowerCase().indexOf(search) !== -1 ||
               String(t.capacity || '').indexOf(search) !== -1;
      });
    }

    var total    = _allTables.length;
    var free     = 0, occupied = 0, reserved = 0;
    _allTables.forEach(function (t) {
      var s = t.current_status || 'free';
      if (s === 'free')     free++;
      if (s === 'occupied') occupied++;
      if (s === 'reserved') reserved++;
    });

    if (gid('statTotal'))        gid('statTotal').textContent        = total;
    if (gid('statAvailable'))    gid('statAvailable').textContent    = free;
    if (gid('statOccupied'))     gid('statOccupied').textContent     = occupied;
    if (gid('statReservations')) gid('statReservations').textContent = reserved;
    if (gid('areaCount'))        gid('areaCount').textContent        = tables.length;

    if (tables.length === 0) {
      canvas.innerHTML = '<p class="fp-canvas-loading">No tables found. Add a table to get started.</p>';
      return;
    }

    canvas.innerHTML = tables.map(function (t) {
      var st    = t.current_status || 'free';
      var cap   = Math.max(1, Number(t.capacity) || 4);
      var tName = esc(t.table_name || ('Table ' + t.id));
      var isRound = cap >= 6 || (tName.toLowerCase().startsWith('a1') || (t.id % 2 === 1 && cap > 4));

      var chairDotsHtml = '';
      if (isRound) {
        var radius = 58;
        for (var i = 0; i < cap; i++) {
          var angle = (i / cap) * (2 * Math.PI) - (Math.PI / 2);
          var cx = 70 + radius * Math.cos(angle);
          var cy = 70 + radius * Math.sin(angle);
          chairDotsHtml += '<span class="rw-chair-dot" style="left:' + cx.toFixed(1) + 'px; top:' + cy.toFixed(1) + 'px;" title="Seat ' + (i+1) + '"></span>';
        }
      } else {
        var sides = [[], [], [], []];
        for (var i = 0; i < cap; i++) { sides[i % 4].push(i); }

        sides[0].forEach(function(sIdx, pos, arr) {
          var pct = ((pos + 1) / (arr.length + 1)) * 100;
          chairDotsHtml += '<span class="rw-chair-pill chair-top" style="left:' + pct.toFixed(1) + '%; transform:translateX(-50%);"></span>';
        });
        sides[1].forEach(function(sIdx, pos, arr) {
          var pct = ((pos + 1) / (arr.length + 1)) * 100;
          chairDotsHtml += '<span class="rw-chair-pill chair-right" style="top:' + pct.toFixed(1) + '%; transform:translateY(-50%);"></span>';
        });
        sides[2].forEach(function(sIdx, pos, arr) {
          var pct = ((pos + 1) / (arr.length + 1)) * 100;
          chairDotsHtml += '<span class="rw-chair-pill chair-bottom" style="left:' + pct.toFixed(1) + '%; transform:translateX(-50%);"></span>';
        });
        sides[3].forEach(function(sIdx, pos, arr) {
          var pct = ((pos + 1) / (arr.length + 1)) * 100;
          chairDotsHtml += '<span class="rw-chair-pill chair-left" style="top:' + pct.toFixed(1) + '%; transform:translateY(-50%);"></span>';
        });
      }

      return '<div class="rw-table-wrapper" onclick="openTableModal(' + Number(t.id) + ')">' +
               '<div class="rw-table-graphic ' + (isRound ? 'is-round' : 'is-square') + ' rw-st-' + esc(st) + '">' +
                 chairDotsHtml +
                 '<div class="rw-table-center">' +
                   '<div class="rw-table-title">' + tName + '</div>' +
                   '<div class="rw-table-status">' + statusLabel(st) + '</div>' +
                 '</div>' +
               '</div>' +
             '</div>';
    }).join('');
  }

  function setStatusFilter(status, btn) {
    _statusFilter = status;
    var pills = document.querySelectorAll('.fp-pill-filter');
    for (var i = 0; i < pills.length; i++) { pills[i].classList.remove('active'); }
    if (btn) btn.classList.add('active');
    renderFloor();
  }

  function filterTables() { renderFloor(); }

  function openTableModal(tableId) {
    _activeTableId = tableId;
    var apiUrl = window.FP_TABLE_API_URL || '../staff/api/table_details.php';

    fetch(apiUrl + '?table_id=' + Number(tableId), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.success) { toast(data.message || 'Failed to load table', 'error'); return; }
      renderTableModal(data);
      var m = gid('tableModal');
      if (m) m.classList.add('active');
    })
    .catch(function (err) {
      toast('Error loading table details', 'error');
      console.error('[FloorPlan] table details error:', err);
    });
  }

  function renderTableModal(data) {
    var t      = data.table || {};
    var st     = data.current_status || 'free';
    var orders = Array.isArray(data.orders) ? data.orders : [];

    if (gid('modalTableTitle'))      gid('modalTableTitle').textContent = t.table_name || ('Table ' + t.id);
    if (gid('modalTableSubtitle'))   gid('modalTableSubtitle').textContent = (t.capacity || 0) + ' Seats';
    if (gid('modalTableStatusBadge')) {
      gid('modalTableStatusBadge').textContent = statusLabel(st);
      gid('modalTableStatusBadge').className   = 'panel-status ' + statusClass(st);
    }

    var bodyEl = gid('modalTableBody');
    if (!bodyEl) return;

    if (orders.length === 0 && st === 'free') {
      bodyEl.innerHTML =
        '<div class="fp-modal-empty">' +
          '<span class="material-symbols-sharp" style="font-size:48px;color:#94a3b8;">table_restaurant</span>' +
          '<p style="color:#64748b;margin-top:8px;">Table is available</p>' +
          '<button class="btn-red" style="margin-top:12px;" ' +
            'onclick="openReserveModal(' + Number(t.id) + ',\'' + esc(t.table_name) + '\')">Reserve This Table</button>' +
        '</div>';
    } else if (orders.length === 0) {
      bodyEl.innerHTML =
        '<div class="fp-modal-empty">' +
          '<span class="material-symbols-sharp" style="font-size:48px;color:#f59e0b;">event_seat</span>' +
          '<p style="color:#64748b;margin-top:8px;">Table is ' + statusLabel(st).toLowerCase() + '</p>' +
        '</div>';
    } else {
      var html = orders.map(function (ord) {
        var items = Array.isArray(ord.items) ? ord.items : [];
        var rows  = items.map(function (item) {
          return '<tr>' +
            '<td>' + esc(item.menu_name) + '</td>' +
            '<td class="num">×' + Number(item.quantity) + '</td>' +
            '<td class="num">' + fmt(Number(item.price) * Number(item.quantity)) + '</td>' +
          '</tr>';
        }).join('');

        return '<div class="fp-order-block">' +
          '<div class="fp-order-header">' +
            '<strong>' + esc(ord.order_number) + '</strong>' +
            '<span class="panel-status">' + esc(ord.status) + '</span>' +
          '</div>' +
          '<table class="receipt-table" style="margin-top:8px;">' +
            '<thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Total</th></tr></thead>' +
            '<tbody>' + rows + '</tbody>' +
          '</table>' +
          '<div style="text-align:right;margin-top:6px;font-weight:600;">' + fmt(ord.total_price) + '</div>' +
          '<button class="btn-red" style="margin-top:10px;width:100%;" ' +
            'onclick="openSettleModal(\'' + esc(ord.order_number) + '\',' + Number(ord.total_price) + ')">' +
            '<span class="material-symbols-sharp">payments</span> Settle This Order' +
          '</button>' +
        '</div>';
      });
      bodyEl.innerHTML = html.join('<hr style="border:none;border-top:1px solid #e2e8f0;margin:12px 0;">');
    }
  }

  function openSettleModal(orderNum, subtotal) {
    _activeOrderNum = orderNum;
    _activeOrderSub = Number(subtotal) || 0;
    _selectedPM     = 'Cash';

    if (gid('recOrderNum'))   gid('recOrderNum').textContent  = orderNum;
    if (gid('recDateTime'))   gid('recDateTime').textContent  = new Date().toLocaleString();
    if (gid('discountInput')) gid('discountInput').value = 0;
    if (gid('recItemsBody'))  gid('recItemsBody').innerHTML = '<tr><td colspan="3" style="color:#64748b;padding:8px 0;">See order above</td></tr>';

    if (gid('recTableName') && _activeTableId) {
      var tObj = null;
      for (var i = 0; i < _allTables.length; i++) {
        if (Number(_allTables[i].id) === Number(_activeTableId)) { tObj = _allTables[i]; break; }
      }
      gid('recTableName').textContent = tObj ? tObj.table_name : 'Table';
    }

    recalculateSettleTotal();

    var pmBtns = document.querySelectorAll('.pm-btn');
    for (var j = 0; j < pmBtns.length; j++) { pmBtns[j].classList.remove('active'); }
    var firstPm = document.querySelector('.pm-btn');
    if (firstPm) firstPm.classList.add('active');

    closeModal('tableModal');
    var sm = gid('settleModal');
    if (sm) sm.classList.add('active');
  }

  function recalculateSettleTotal() {
    var disc  = Number((gid('discountInput') || { value: 0 }).value) || 0;
    var total = Math.max(0, _activeOrderSub - disc);
    if (gid('recSubtotal'))  gid('recSubtotal').textContent  = fmt(_activeOrderSub);
    if (gid('recDiscount'))  gid('recDiscount').textContent  = disc > 0 ? '-' + fmt(disc) : fmt(0);
    if (gid('recTotal'))     gid('recTotal').textContent     = fmt(total);
  }

  function selectPM(method, btn) {
    _selectedPM = method;
    var pmBtns = document.querySelectorAll('.pm-btn');
    for (var i = 0; i < pmBtns.length; i++) { pmBtns[i].classList.remove('active'); }
    if (btn) btn.classList.add('active');
  }

  function processSettle(doPrint) {
    if (!_activeOrderNum) { toast('No order selected', 'error'); return; }
    var disc  = Number((gid('discountInput') || { value: 0 }).value) || 0;
    var total = Math.max(0, _activeOrderSub - disc);
    var url   = window.FP_SETTLE_API_URL || '../staff/api/settle_bill.php';

    fetch(url, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body:    JSON.stringify({
        order_number:          _activeOrderNum,
        subtotal:              _activeOrderSub,
        discount_type:         'fixed',
        discount_value:        disc,
        discount_amount:       disc,
        service_charge_rate:   0,
        service_charge_amount: 0,
        vat_rate:              0,
        vat_amount:            0,
        grand_total:           total,
        payment_method:        _selectedPM,
        amount_received:       total,
        change_due:            0,
        remarks:               ''
      })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.success) { toast(data.message || 'Settlement failed', 'error'); return; }
      toast('Order settled successfully!', 'success');
      if (doPrint) { window.print(); }
      closeModal('settleModal');
      setTimeout(loadFloorPlan, 600);
    })
    .catch(function (err) {
      toast('Error processing settlement', 'error');
      console.error('[FloorPlan] settle error:', err);
    });
  }

  function openReserveModal(tableId, tableName) {
    var titleEl = gid('reserveModalTitle');
    if (titleEl) titleEl.textContent = 'Reserve ' + (tableName || ('Table ' + tableId));

    var idEl = gid('resTableId');
    if (idEl) idEl.value = tableId;

    var dateEl = gid('resDate');
    if (dateEl && !dateEl.value) {
      dateEl.value = new Date().toISOString().slice(0, 10);
    }

    closeModal('tableModal');
    var rm = gid('reserveModal');
    if (rm) rm.classList.add('active');

    var form = gid('reserveTableForm');
    if (form) {
      form.onsubmit = function (e) {
        e.preventDefault();
        var url  = window.FP_BOOK_API_URL || '../staff/api/booking_create.php';
        var body = {
          table_id:     Number((gid('resTableId') || {}).value),
          guest_name:   ((gid('resGuestName') || {}).value || '').trim(),
          phone:        ((gid('resGuestPhone') || {}).value || '').trim(),
          guests:       Number((gid('resGuests') || { value: 2 }).value) || 2,
          booking_date: ((gid('resDate') || {}).value || ''),
          start_time:   ((gid('resTime') || {}).value || ''),
          end_time:     ((gid('resEndTime') || {}).value || ''),
          message:      ((gid('resMessage') || {}).value || '').trim()
        };
        if (!body.guest_name || !body.phone || !body.booking_date || !body.start_time) {
          toast('Please fill all required fields', 'error');
          return;
        }
        fetch(url, {
          method:  'POST',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body:    JSON.stringify(body)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) { toast(data.message || 'Reservation failed', 'error'); return; }
          toast('Reservation created!', 'success');
          closeModal('reserveModal');
          form.reset();
          setTimeout(loadFloorPlan, 600);
        })
        .catch(function (err) {
          toast('Error creating reservation', 'error');
          console.error('[FloorPlan] reserve error:', err);
        });
      };
    }
  }

  function openAddTableModal() {
    var modal = gid('addTableModal');
    if (modal) modal.classList.add('active');

    var form = gid('addTableForm');
    if (form) {
      form.onsubmit = function (e) {
        e.preventDefault();
        var url   = window.FP_ADD_TABLE_URL || '../admin/api/api_qr_tables.php';
        var tName = ((gid('addTableName') || {}).value || '').trim();
        var tCap  = Number((gid('addTableCap') || { value: 4 }).value) || 4;
        if (!tName) { toast('Table name is required', 'error'); return; }

        fetch(url, {
          method:  'POST',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body:    JSON.stringify({ action: 'create', table_name: tName, capacity: tCap })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data.success) { toast(data.message || 'Failed to add table', 'error'); return; }
          toast('Table added!', 'success');
          closeModal('addTableModal');
          form.reset();
          setTimeout(loadFloorPlan, 400);
        })
        .catch(function (err) {
          toast('Error adding table', 'error');
          console.error('[FloorPlan] add table error:', err);
        });
      };
    }
  }

  function openNewOrderForTable() {
    location.href = window.FP_IS_ADMIN ? 'orders_page.php' : 'new-order.php';
  }

  function closeModal(id) {
    var m = gid(id);
    if (m) m.classList.remove('active');
  }

  function startAutoRefresh() {
    if (_refreshTimer) return;
    _refreshTimer = setInterval(function () {
      if (!document.hidden) loadFloorPlan();
    }, 30000);
  }

  document.addEventListener('click', function (e) {
    var ids = ['tableModal', 'settleModal', 'reserveModal', 'addTableModal'];
    for (var i = 0; i < ids.length; i++) {
      var m = gid(ids[i]);
      if (m && e.target === m) closeModal(ids[i]);
    }
  });

  window.loadFloorPlan          = window.loadFloorPlan || loadFloorPlan;
  window.setStatusFilter        = window.setStatusFilter || setStatusFilter;
  window.filterTables           = window.filterTables || filterTables;
  window.openTableModal         = window.openTableModal || openTableModal;
  window.openSettleModal        = window.openSettleModal || openSettleModal;
  window.recalculateSettleTotal = window.recalculateSettleTotal || recalculateSettleTotal;
  window.selectPM               = window.selectPM || selectPM;
  window.processSettle          = window.processSettle || processSettle;
  window.openReserveModal       = window.openReserveModal || openReserveModal;
  window.openAddTableModal      = window.openAddTableModal || openAddTableModal;
  window.openNewOrderForTable   = window.openNewOrderForTable || openNewOrderForTable;
  window.closeModal             = window.closeModal || closeModal;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      if (gid('floorCanvas')) startAutoRefresh();
    });
  } else {
    if (gid('floorCanvas')) startAutoRefresh();
  }
}());

