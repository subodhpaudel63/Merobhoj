<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/role_check.php';
$panelUser = require_role($conn, 'staff');

$pageTitle = 'Reservations & Bookings';
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
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
  <link rel="stylesheet" href="../assets/css/admin2.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin2.css') ?>">
  <link rel="stylesheet" href="../assets/css/panel.css?v=<?= filemtime(__DIR__ . '/../assets/css/panel.css') ?>">
</head>
<body class="admin-page">
  <?php include __DIR__ . '/topbar.php'; ?>
  <div class="container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="admin-page-main">

<div class="panel-head" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2>Reservations & Bookings</h2>
        <p class="text-muted">Manage table reservations, edit bookings, and perform state transitions.</p>
    </div>
    <button class="qrm-btn qrm-btn-primary" onclick="openCreateModal()">+ Create Booking</button>
</div>

<div class="panel-table-wrap">
    <table class="panel-table" id="bookingsTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Guest Name</th>
                <th>Contact Info</th>
                <th>Table</th>
                <th>Guests</th>
                <th>Booking Date & Time</th>
                <th>Start / End Time</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="bookingsListBody">
            <tr>
                <td colspan="9" style="text-align:center; padding: 2rem;">Loading bookings...</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Booking Modal (Create & Edit) -->
<div id="bookingModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--clr-card-background); padding:2rem; border-radius:var(--card-border-radius); width:460px; max-width:92vw; max-height:90vh; overflow-y:auto;">
        <h3 id="modalHeading">New Reservation</h3>
        <form id="bookingForm" style="margin-top:1rem;">
            <input type="hidden" id="bId" value="">
            <div class="form-group">
                <label>Guest Name *</label>
                <input type="text" id="bName" placeholder="Enter guest name" required>
            </div>
            <div class="form-group">
                <label>Phone Number *</label>
                <input type="text" id="bPhone" placeholder="Enter 10-digit mobile number" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" id="bEmail" placeholder="guest@example.com">
            </div>
            <div class="form-group">
                <label>Assigned Table *</label>
                <select id="bTable" required></select>
            </div>
            <div class="form-group">
                <label>Number of Guests *</label>
                <input type="number" id="bGuests" value="2" min="1" max="30" required>
            </div>
            <div class="form-group">
                <label>Booking Date *</label>
                <input type="date" id="bDate" required>
            </div>
            <div class="form-group">
                <label>Booking Time *</label>
                <input type="time" id="bTime" required>
            </div>
            <div class="form-group" id="statusGroup" style="display:none;">
                <label>Status</label>
                <select id="bStatus">
                    <option value="Pending">Pending</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Checked-in">Checked-in</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="No-show">No-show</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1.5rem;">
                <button type="button" class="qrm-btn qrm-btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="qrm-btn qrm-btn-primary" id="saveBtn">Save Reservation</button>
            </div>
        </form>
    </div>
</div>

<script>
let availableTables = [];
let allBookings = [];

document.addEventListener('DOMContentLoaded', () => {
    loadBookings();
    setInterval(loadBookings, 8000);

    function loadBookings() {
        fetch('api/bookings.php?action=list')
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                availableTables = data.tables || [];
                allBookings = data.bookings || [];
                populateTableSelect();

                const tbody = document.getElementById('bookingsListBody');
                if (!allBookings || allBookings.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding: 2rem;">No bookings found.</td></tr>';
                    return;
                }

                tbody.innerHTML = allBookings.map(b => {
                    const statusClass = 'st-' + b.status.toLowerCase().replace('-', '');
                    const actions = getBookingActions(b);
                    const emailDisplay = b.email ? `<small class="text-muted">${esc(b.email)}</small><br>` : '';
                    const peopleCount = b.people || b.number_of_guests || 1;
                    const startTimeStr = b.start_time ? b.start_time.substring(0, 5) : b.booking_time;
                    const endTimeStr = b.end_time ? b.end_time.substring(0, 5) : '-';

                    return `
                        <tr>
                            <td>#${b.id}</td>
                            <td><strong>${esc(b.name)}</strong></td>
                            <td>${esc(b.phone)}<br>${emailDisplay}</td>
                            <td><strong>${esc(b.table_name || 'Table ' + b.table_id)}</strong></td>
                            <td>${peopleCount} Persons</td>
                            <td>${esc(b.booking_date)} @ ${esc(b.booking_time)}</td>
                            <td><small>${esc(startTimeStr)} - ${esc(endTimeStr)}</small></td>
                            <td><span class="panel-status ${statusClass}">${esc(b.status)}</span></td>
                            <td>${actions}</td>
                        </tr>
                    `;
                }).join('');
            });
    }

    function getBookingActions(b) {
        const s = b.status;
        let btns = [];
        if (s === 'Pending') {
            btns.push(`<button class="qrm-btn qrm-btn-success" onclick="updateBStatus(${b.id}, 'Confirmed')">Confirm</button>`);
            btns.push(`<button class="qrm-btn qrm-btn-danger" onclick="updateBStatus(${b.id}, 'Cancelled')">Cancel</button>`);
        } else if (s === 'Confirmed') {
            btns.push(`<button class="qrm-btn qrm-btn-primary" onclick="updateBStatus(${b.id}, 'Checked-in')">Check-in</button>`);
            btns.push(`<button class="qrm-btn qrm-btn-danger" onclick="updateBStatus(${b.id}, 'Cancelled')">Cancel</button>`);
        } else if (s === 'Checked-in') {
            btns.push(`<button class="qrm-btn qrm-btn-success" onclick="updateBStatus(${b.id}, 'Completed')">Complete</button>`);
        }
        
        // Edit button
        btns.push(`<button class="qrm-btn qrm-btn-secondary" onclick="openEditModal(${b.id})">Edit</button>`);
        btns.push(`<button class="qrm-btn qrm-btn-danger" onclick="deleteBooking(${b.id})">Delete</button>`);
        return btns.join(' ');
    }

    window.updateBStatus = function(id, status) {
        fetch('api/booking_update_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, status: status })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadBookings();
            } else {
                alert(data.message || 'Update failed');
            }
        });
    };

    window.deleteBooking = function(id) {
        if (!confirm('Are you sure you want to delete this booking?')) return;
        fetch('api/booking_delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                loadBookings();
            } else {
                alert(data.message || 'Delete failed');
            }
        });
    };

    function populateTableSelect() {
        const select = document.getElementById('bTable');
        select.innerHTML = availableTables.map(t => `<option value="${t.id}">${esc(t.table_name)} (Cap: ${t.capacity})</option>`).join('');
    }

    window.openCreateModal = function() {
        document.getElementById('modalHeading').innerText = 'New Reservation';
        document.getElementById('bId').value = '';
        document.getElementById('bName').value = '';
        document.getElementById('bPhone').value = '';
        document.getElementById('bEmail').value = '';
        document.getElementById('bGuests').value = 2;
        document.getElementById('bDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('bTime').value = '18:00';
        document.getElementById('statusGroup').style.display = 'none';
        document.getElementById('bookingModal').style.display = 'flex';
    };

    window.openEditModal = function(id) {
        const b = allBookings.find(item => parseInt(item.id) === parseInt(id));
        if (!b) return;

        document.getElementById('modalHeading').innerText = `Edit Booking #${b.id}`;
        document.getElementById('bId').value = b.id;
        document.getElementById('bName').value = b.name || '';
        document.getElementById('bPhone').value = b.phone || '';
        document.getElementById('bEmail').value = b.email || '';
        document.getElementById('bTable').value = b.table_id || '';
        document.getElementById('bGuests').value = b.people || b.number_of_guests || 1;
        document.getElementById('bDate').value = b.booking_date || '';
        document.getElementById('bTime').value = (b.booking_time || '12:00').substring(0, 5);
        document.getElementById('bStatus').value = b.status || 'Pending';
        document.getElementById('statusGroup').style.display = 'block';

        document.getElementById('bookingModal').style.display = 'flex';
    };

    window.closeModal = function() {
        document.getElementById('bookingModal').style.display = 'none';
    };

    document.getElementById('bookingForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const bId = document.getElementById('bId').value;
        const isEdit = bId !== '';

        const payload = {
            id: bId ? parseInt(bId) : undefined,
            name: document.getElementById('bName').value,
            phone: document.getElementById('bPhone').value,
            email: document.getElementById('bEmail').value,
            table_id: parseInt(document.getElementById('bTable').value),
            guests: parseInt(document.getElementById('bGuests').value),
            booking_date: document.getElementById('bDate').value,
            booking_time: document.getElementById('bTime').value,
            status: isEdit ? document.getElementById('bStatus').value : undefined
        };

        const targetUrl = isEdit ? 'api/booking_update.php' : 'api/booking_create.php';

        fetch(targetUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                closeModal();
                loadBookings();
            } else {
                alert(data.message || 'Failed to save booking');
            }
        });
    });

    function esc(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
});
</script>

    </main>
  </div>
  <div class="toast-container" id="toastContainer"></div>
  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
  <script src="../assets/js/panel_notifications.js?v=<?= filemtime(__DIR__ . '/../assets/js/panel_notifications.js') ?>"></script>
</body>
</html>
