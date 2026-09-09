/* ============================================================
   MeroBhoj Floor Plan — loadFloorPlan + all floor-plan logic
   Works for both admin/floor.php and staff/floor.php.
   Each page sets window.FP_API_URL before calling loadFloorPlan().
   ============================================================ */
(function () {
  'use strict';

  /* ---------- state ---------- */
  var _allTables        = [];
  var _statusFilter     = 'all';
  var _activeTableId    = null;
  var _activeOrderNum   = null;
  var _activeOrderSub   = 0;
  var _selectedPM       = 'Cash';
  var _refreshTimer     = null;

  /* ---------- helpers ---------- */
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

  /* =========================================================
     LOAD + RENDER
     ========================================================= */
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

    /* update stats */
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

    canvas.innerHTML = tables.map(function (t, idx) {
      var st    = t.current_status || 'free';
      var cap   = Math.max(1, Number(t.capacity) || 4);
      var tName = esc(t.table_name || ('Table ' + t.id));
      var isRound = cap >= 6 || (tName.toLowerCase().startsWith('a1') || (t.id % 2 === 1 && cap > 4));

      // Build chair dots surrounding table matching EXACT seat capacity
      var chairDotsHtml = '';
      if (isRound) {
        // Round table: place `cap` number of chair dots evenly spaced in a circle around the table
        var radius = 58; // pixels radius for chair ring
        for (var i = 0; i < cap; i++) {
          var angle = (i / cap) * (2 * Math.PI) - (Math.PI / 2);
          var cx = 70 + radius * Math.cos(angle);
          var cy = 70 + radius * Math.sin(angle);
          chairDotsHtml += '<span class="rw-chair-dot" style="left:' + cx.toFixed(1) + 'px; top:' + cy.toFixed(1) + 'px;" title="Seat ' + (i+1) + '"></span>';
        }
      } else {
        // Square table: distribute exact `cap` chairs evenly across 4 sides (Top, Right, Bottom, Left)
        var sides = [[], [], [], []]; // 0: Top, 1: Right, 2: Bottom, 3: Left
        for (var i = 0; i < cap; i++) {
          sides[i % 4].push(i);
        }

        // Top chairs
        sides[0].forEach(function(sIdx, pos, arr) {
          var pct = ((pos + 1) / (arr.length + 1)) * 100;
          chairDotsHtml += '<span class="rw-chair-pill chair-top" style="left:' + pct.toFixed(1) + '%; transform:translateX(-50%);"></span>';
        });
        // Right chairs
        sides[1].forEach(function(sIdx, pos, arr) {
          var pct = ((pos + 1) / (arr.length + 1)) * 100;
          chairDotsHtml += '<span class="rw-chair-pill chair-right" style="top:' + pct.toFixed(1) + '%; transform:translateY(-50%);"></span>';
        });
        // Bottom chairs
        sides[2].forEach(function(sIdx, pos, arr) {
          var pct = ((pos + 1) / (arr.length + 1)) * 100;
          chairDotsHtml += '<span class="rw-chair-pill chair-bottom" style="left:' + pct.toFixed(1) + '%; transform:translateX(-50%);"></span>';
        });
        // Left chairs
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

  /* =========================================================
     FILTER
     ========================================================= */
  function setStatusFilter(status, btn) {
    _statusFilter = status;
    var pills = document.querySelectorAll('.fp-pill-filter');
    for (var i = 0; i < pills.length; i++) { pills[i].classList.remove('active'); }
    if (btn) btn.classList.add('active');
    renderFloor();
  }

  function filterTables() { renderFloor(); }

  /* =========================================================
     TABLE MODAL
     ========================================================= */
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

  /* =========================================================
     SETTLE MODAL
     ========================================================= */
  function openSettleModal(orderNum, subtotal) {
    _activeOrderNum = orderNum;
    _activeOrderSub = Number(subtotal) || 0;
    _selectedPM     = 'Cash';

    if (gid('recOrderNum'))  gid('recOrderNum').textContent  = orderNum;
    if (gid('recDateTime'))  gid('recDateTime').textContent  = new Date().toLocaleString();
    if (gid('discountInput')) gid('discountInput').value = 0;
    if (gid('recItemsBody')) gid('recItemsBody').innerHTML = '<tr><td colspan="3" style="color:#64748b;padding:8px 0;">See order above</td></tr>';

    /* table name */
    if (gid('recTableName') && _activeTableId) {
      var tObj = null;
      for (var i = 0; i < _allTables.length; i++) {
        if (Number(_allTables[i].id) === Number(_activeTableId)) { tObj = _allTables[i]; break; }
      }
      gid('recTableName').textContent = tObj ? tObj.table_name : 'Table';
    }

    recalculateSettleTotal();

    /* reset PM buttons */
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

  /* =========================================================
     RESERVE MODAL
     ========================================================= */
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

  /* =========================================================
     ADD TABLE (admin only)
     ========================================================= */
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

  /* =========================================================
     NEW ORDER
     ========================================================= */
  function openNewOrderForTable() {
    location.href = window.FP_IS_ADMIN ? 'orders_page.php' : 'new-order.php';
  }

  /* =========================================================
     CLOSE MODAL
     ========================================================= */
  function closeModal(id) {
    var m = gid(id);
    if (m) m.classList.remove('active');
  }

  /* =========================================================
     AUTO-REFRESH
     ========================================================= */
  function startAutoRefresh() {
    if (_refreshTimer) return;
    _refreshTimer = setInterval(function () {
      if (!document.hidden) loadFloorPlan();
    }, 30000);
  }

  /* =========================================================
     CLICK-OUTSIDE TO CLOSE
     ========================================================= */
  document.addEventListener('click', function (e) {
    var ids = ['tableModal', 'settleModal', 'reserveModal', 'addTableModal'];
    for (var i = 0; i < ids.length; i++) {
      var m = gid(ids[i]);
      if (m && e.target === m) closeModal(ids[i]);
    }
  });

  /* =========================================================
     EXPOSE TO GLOBAL
     ========================================================= */
  window.loadFloorPlan          = loadFloorPlan;
  window.setStatusFilter        = setStatusFilter;
  window.filterTables           = filterTables;
  window.openTableModal         = openTableModal;
  window.openSettleModal        = openSettleModal;
  window.recalculateSettleTotal = recalculateSettleTotal;
  window.selectPM               = selectPM;
  window.processSettle          = processSettle;
  window.openReserveModal       = openReserveModal;
  window.openAddTableModal      = openAddTableModal;
  window.openNewOrderForTable   = openNewOrderForTable;
  window.closeModal             = window.closeModal || closeModal;

  /* Start auto-refresh if on floor plan page */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      if (gid('floorCanvas')) startAutoRefresh();
    });
  } else {
    if (gid('floorCanvas')) startAutoRefresh();
  }

}());
