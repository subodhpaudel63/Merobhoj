/* ============================================================
   MeroBhoj Staff Management AJAX & Interaction Logic (assets/js/staff.js)
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

  /* Single Modal Manager — closes any open modal before opening a new one */
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

  /* Table Search & Status Filtering */
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

  /* Export CSV Functionality */
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

  // Bind forms submission via AJAX
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

  /* Expose functions globally */
  window.handleCheckIn        = handleCheckIn;
  window.handleCheckOut       = handleCheckOut;
  window.openStaffDetails     = openStaffDetails;
  window.openSettingsModal    = openSettingsModal;
  window.openAddStaffModal    = openAddStaffModal;
  window.closeStaffModal      = closeModal;
  window.filterStaffDirectory = filterStaffDirectory;
  window.exportAttendanceCSV  = exportAttendanceCSV;

}());
