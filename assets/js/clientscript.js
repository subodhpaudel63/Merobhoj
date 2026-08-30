document.addEventListener('DOMContentLoaded', function () {
  const sessionType = document.querySelector('meta[name="mkj-session-type"]')?.content;
  const sessionText = document.querySelector('meta[name="mkj-session-text"]')?.content;
  if (sessionType && sessionText && window.ToastNotifications) {
    if (sessionType === 'success') {
      ToastNotifications.success(sessionText);
    } else {
      ToastNotifications.error(sessionText);
    }
  }

  const reservationDate = document.getElementById('reservationDate');
  if (reservationDate) {
    const today = new Date();
    reservationDate.min = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
  }

  const normalizeTime12To24 = (value) => {
    if (!value || typeof value !== 'string') return null;
    const trimmed = value.trim();
    if (!trimmed) return null;

    const match = trimmed.match(/^([1-9]|1[0-2]|0?[0-9]):([0-5][0-9])\s*([AaPp][Mm])$/);
    if (!match) {
      const militaryMatch = trimmed.match(/^([0-9]|1[0-9]|2[0-3]):([0-5][0-9])$/);
      if (!militaryMatch) return null;
      return `${String(parseInt(militaryMatch[1], 10)).padStart(2, '0')}:${militaryMatch[2]}`;
    }

    let hours = parseInt(match[1], 10);
    const minutes = match[2];
    const suffix = match[3].toUpperCase();
    if (suffix === 'AM' && hours === 12) hours = 0;
    if (suffix === 'PM' && hours !== 12) hours += 12;
    return `${String(hours).padStart(2, '0')}:${minutes}`;
  };

  const initBookingForm = (bookingForm) => {
    if (!bookingForm) return;

    const dateInput = bookingForm.querySelector('input[name="date"], input#reservationDate');
    const startTimeInput = bookingForm.querySelector('[name="start_time"]');
    const endTimeInput = bookingForm.querySelector('[name="end_time"]');
    const peopleInput = bookingForm.querySelector('input[name="people"]');
    const tableSelect = bookingForm.querySelector('#tableSelect');
    const currentPath = window.location.pathname || '/';
    const availabilityApiUrl = currentPath.includes('/client/')
      ? '../includes/get_available_tables.php'
      : (currentPath.includes('/Merobhoj/') ? './includes/get_available_tables.php' : '/Merobhoj/includes/get_available_tables.php');

    const getAllTimeSlots = () => {
      const slots = [];
      for (let hour = 7; hour <= 23; hour++) {
        for (const minute of [0, 30]) {
          if (hour === 23 && minute > 0) continue;
          slots.push(`${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`);
        }
      }
      return slots;
    };

    const applyTimeSelectOptions = () => {
      if (!startTimeInput || !endTimeInput) return;
      const allSlots = getAllTimeSlots();
      const buildOptions = (select, selectedValue, slots) => {
        const currentValue = selectedValue || '';
        const options = ['<option value="">Select Time</option>'];
        slots.forEach(slot => {
          const label = new Date(`2024-01-01T${slot}:00`).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
          const selected = currentValue === slot ? ' selected' : '';
          options.push(`<option value="${slot}"${selected}>${label}</option>`);
        });
        select.innerHTML = options.join('');
      };

      const startValue = normalizeTime12To24(startTimeInput.value || '');
      const endValue = normalizeTime12To24(endTimeInput.value || '');

      if (startTimeInput.tagName === 'SELECT') {
        buildOptions(startTimeInput, startValue || '', allSlots);
      }

      let endSlots = allSlots.filter(slot => slot > (startValue || '00:00'));
      if (endSlots.length === 0) {
        endSlots = allSlots.filter(slot => slot > '07:00');
      }
      if (endTimeInput.tagName === 'SELECT') {
        buildOptions(endTimeInput, endValue || '', endSlots);
      }
    };

    const updateTables = () => {
      const date = dateInput?.value || '';
      const startTime = normalizeTime12To24(startTimeInput?.value || '');
      const endTime = normalizeTime12To24(endTimeInput?.value || '');
      const people = parseInt(peopleInput?.value || '0', 10);

      if (date && startTime && people > 0) {
        if (tableSelect) {
          tableSelect.innerHTML = '<option value="" style="color: white; background-color: #212529;">Loading available tables...</option>';
        }
        const fd = new FormData();
        fd.append('date', date);
        fd.append('start_time', startTime);
        if (endTime) fd.append('end_time', endTime);
        fd.append('people', people);
        fetch(availabilityApiUrl, { method: 'POST', body: fd })
          .then(r => r.json())
          .then(data => {
            if (!tableSelect) return;
            tableSelect.innerHTML = '<option value="" style="color: white; background-color: #212529;">Select a Table</option>';
            if (data.success && data.tables.length > 0) {
              data.tables.forEach(table => {
                const o = document.createElement('option');
                o.value = table.id;
                o.textContent = `${table.name} - ${table.capacity} Seats`;
                o.style.color = 'white';
                o.style.backgroundColor = '#212529';
                tableSelect.appendChild(o);
              });
            } else if (data.success && data.tables.length === 0) {
              tableSelect.innerHTML = '<option value="" style="color: white; background-color: #212529;">No tables available for this time and group size</option>';
            } else {
              tableSelect.innerHTML = '<option value="" style="color: white; background-color: #212529;">Error loading tables</option>';
            }
          })
          .catch(() => {
            if (tableSelect) {
              tableSelect.innerHTML = '<option value="" style="color: white; background-color: #212529;">Error loading tables</option>';
            }
          });
      } else if (tableSelect) {
        tableSelect.innerHTML = '<option value="" style="color: white; background-color: #212529;">Select a Date, Time, and People first</option>';
      }
    };

    const normalizeAndValidateBookingTimes = () => {
      const startNormalized = normalizeTime12To24(startTimeInput?.value || '');
      const endNormalized = normalizeTime12To24(endTimeInput?.value || '');
      if (!startNormalized || !endNormalized) {
        return { valid: false, message: 'Please select a valid start and end time.' };
      }

      const startHour = parseInt(startNormalized.split(':')[0], 10);
      const endHour = parseInt(endNormalized.split(':')[0], 10);
      const startMin = parseInt(startNormalized.split(':')[1], 10);
      const endMin = parseInt(endNormalized.split(':')[1], 10);

      if (startHour < 7 || startHour >= 23 || (startHour === 23 && startMin > 0)) {
        return { valid: false, message: 'Start time must be between 7:00 AM and 10:59 PM.' };
      }
      if (endHour < 7 || endHour > 23 || (endHour === 23 && endMin > 0) || endNormalized <= startNormalized) {
        return { valid: false, message: 'End time must be later than start time and no later than 11:00 PM.' };
      }

      if (startTimeInput) startTimeInput.value = startNormalized;
      if (endTimeInput) endTimeInput.value = endNormalized;
      return { valid: true };
    };

    if (startTimeInput && endTimeInput) {
      startTimeInput.addEventListener('change', () => {
        applyTimeSelectOptions();
        requestAnimationFrame(updateTables);
      });
      endTimeInput.addEventListener('change', () => requestAnimationFrame(updateTables));
      startTimeInput.addEventListener('input', () => {
        applyTimeSelectOptions();
        requestAnimationFrame(updateTables);
      });
      endTimeInput.addEventListener('input', () => requestAnimationFrame(updateTables));
    }

    dateInput?.addEventListener('change', () => requestAnimationFrame(updateTables));
    dateInput?.addEventListener('input', () => requestAnimationFrame(updateTables));
    peopleInput?.addEventListener('change', () => requestAnimationFrame(updateTables));
    peopleInput?.addEventListener('input', () => requestAnimationFrame(updateTables));
    peopleInput?.addEventListener('keyup', () => requestAnimationFrame(updateTables));

    bookingForm.addEventListener('submit', function (e) {
      const phoneField = bookingForm.querySelector('input[name="phone"]');
      const phone = phoneField ? phoneField.value.replace(/[\s\-]/g, '') : '';
      if (phone && !/^(\+?977)?9[6-8]\d{8}$/.test(phone)) {
        e.preventDefault(); alert('Please enter a valid Nepal phone number (e.g., 98XXXXXXXX or +977-98XXXXXXXX).'); return;
      }

      const timeValidation = normalizeAndValidateBookingTimes();
      if (!timeValidation.valid) {
        e.preventDefault();
        alert(timeValidation.message);
        return;
      }

      if (peopleInput && parseInt(peopleInput.value, 10) > 8) {
        e.preventDefault(); alert('The maximum capacity for a single table is 8 people.');
      }
    });

    applyTimeSelectOptions();
    requestAnimationFrame(updateTables);
  };

  document.querySelectorAll('#bookingForm').forEach(initBookingForm);

  const menuSearchInput = document.getElementById('menuSearchInput');
  const menuSearchClear = document.getElementById('menuSearchClear');
  if (menuSearchInput) {
    menuSearchInput.addEventListener('input', function () {
      const q = this.value.trim().toLowerCase();
      menuSearchClear?.classList.toggle('visible', q.length > 0);
      document.querySelectorAll('#menuTabContent .col').forEach(col => {
        const name = col.querySelector('.card-title')?.textContent.toLowerCase() || '';
        const desc = col.querySelector('.card-text')?.textContent.toLowerCase() || '';
        col.classList.toggle('search-hidden', q.length > 0 && !name.includes(q) && !desc.includes(q));
      });
      document.querySelectorAll('#menuTabContent .tab-pane').forEach(p => {
        p.style.cssText = q.length > 0 ? 'display:block;opacity:1;transform:none;pointer-events:auto;' : '';
      });
      const tab = document.getElementById('menuTab');
      if (tab) tab.style.opacity = q.length > 0 ? '0.5' : '1';
    });
    menuSearchClear?.addEventListener('click', () => {
      menuSearchInput.value = '';
      menuSearchInput.dispatchEvent(new Event('input'));
      menuSearchInput.focus();
    });
  }

  document.getElementById('menuTab')?.addEventListener('shown.bs.tab', e => {
    const pane = document.querySelector(e.target.getAttribute('data-bs-target'));
    pane?.querySelectorAll('.col').forEach(col => {
      col.style.animation = 'none';
      col.offsetHeight;
      col.style.animation = '';
    });
  });

  const wishlist = JSON.parse(localStorage.getItem('mkj_wishlist') || '[]');
  document.querySelectorAll('.wishlist-btn').forEach(btn => btn.classList.toggle('liked', wishlist.includes(btn.dataset.id)));
  document.addEventListener('click', e => {
    const btn = e.target.closest('.wishlist-btn');
    if (!btn) return;
    const id = btn.dataset.id;
    const idx = wishlist.indexOf(id);
    idx === -1 ? wishlist.push(id) : wishlist.splice(idx, 1);
    localStorage.setItem('mkj_wishlist', JSON.stringify(wishlist));
    btn.classList.toggle('liked', wishlist.includes(id));
  });

  const buyModal = document.getElementById('buyModal');
  if (buyModal) {
    const modalPrice = document.getElementById('modal-price');
    const modalTotal = document.getElementById('modal-total-price');
    const inputMenuId = document.getElementById('input-menu-id');
    const inputMenuName = document.getElementById('input-menu-name');
    const inputPrice = document.getElementById('input-price');
    const inputTotal = document.getElementById('input-total-price');
    const quantityInput = document.getElementById('quantity');
    const orderTypeRadios = document.querySelectorAll('input[name="order_type"]');
    const addressWrapper = document.getElementById('address_wrapper');
    const addressField = document.getElementById('address');
    const addressLabel = document.getElementById('address_label');
    const tableWrapper = document.getElementById('table_number_wrapper');
    const tableField = document.getElementById('table_number');
    const tableLabel = document.getElementById('table_number_label');
    const recalc = () => {
      const price = parseFloat(modalPrice.textContent) || 0;
      const qty = Math.max(1, parseInt(quantityInput.value) || 1);
      quantityInput.value = qty;
      const total = (price * qty).toFixed(2);
      modalTotal.textContent = total;
      inputPrice.value = price.toFixed(2);
      inputTotal.value = total;
      document.getElementById('summary-qty') && (document.getElementById('summary-qty').textContent = qty);
      document.getElementById('summary-subtotal') && (document.getElementById('summary-subtotal').textContent = total);
      document.getElementById('summary-total') && (document.getElementById('summary-total').textContent = total);
    };
    const syncOrderTypeFields = () => {
      const selected = document.querySelector('input[name="order_type"]:checked')?.value || 'Delivery';
      const isDelivery = selected === 'Delivery';
      const isDineIn = selected === 'Dine In';
      if (addressWrapper) { addressWrapper.style.display = isDelivery ? '' : 'none'; addressWrapper.hidden = !isDelivery; }
      if (addressLabel) addressLabel.textContent = isDelivery ? 'Delivery Address *' : 'Address';
      if (addressField) { addressField.required = isDelivery; if (!isDelivery) addressField.value = ''; }
      if (tableWrapper) { tableWrapper.style.display = isDineIn ? '' : 'none'; tableWrapper.hidden = !isDineIn; }
      if (tableLabel) tableLabel.textContent = isDineIn ? 'Table Number *' : 'Table Number (for Dine In)';
      if (tableField) { tableField.required = isDineIn; if (!isDineIn) tableField.value = ''; }
    };
    document.getElementById('qty-minus')?.addEventListener('click', () => { quantityInput.value = Math.max(1, (parseInt(quantityInput.value) || 1) - 1); recalc(); });
    document.getElementById('qty-plus')?.addEventListener('click', () => { quantityInput.value = (parseInt(quantityInput.value) || 1) + 1; recalc(); });
    quantityInput?.addEventListener('input', recalc);
    buyModal.addEventListener('show.bs.modal', e => {
      const btn = e.relatedTarget;
      inputMenuId.value = btn.getAttribute('data-id');
      inputMenuName.value = btn.getAttribute('data-name');
      document.getElementById('modal-name').textContent = btn.getAttribute('data-name');
      document.getElementById('modal-description').textContent = btn.getAttribute('data-description');
      document.getElementById('modal-image').src = btn.getAttribute('data-image');
      document.getElementById('summary-name').textContent = btn.getAttribute('data-name');
      document.getElementById('summary-image').src = btn.getAttribute('data-image');
      modalPrice.textContent = parseFloat(btn.getAttribute('data-price')).toFixed(2);
      quantityInput.value = 1;
      recalc();
      syncOrderTypeFields();
    });
    orderTypeRadios.forEach(r => r.addEventListener('change', syncOrderTypeFields));
    syncOrderTypeFields();
  }

  if (document.getElementById('orders-body')) {
    let previousOrders = {};
    const POLL_MS = 3000;
    const fetchOrders = async () => {
      try {
        const res = await fetch('../includes/orders_fetch.php', { credentials: 'same-origin' });
        const data = await res.json();
        const tbody = document.getElementById('orders-body');
        if (!data.ok) { tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">Please login to view your orders.</td></tr>`; return; }
        if (!data.orders || data.orders.length === 0) { tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No orders found.</td></tr>`; previousOrders = {}; return; }
        const currentOrders = {};
        data.orders.forEach(order => currentOrders[order.order_number || order.order_id] = order.status);
        tbody.innerHTML = data.orders.map(o => {
          const orderRef = o.order_number || ('ORD-' + String(o.order_id).padStart(4, '0'));
          const previousStatus = previousOrders[orderRef];
          const statusChanged = previousStatus && previousStatus !== o.status;
          const itemsHtml = o.items.map(it => `<div class="d-flex justify-content-between align-items-center py-1" style="border-bottom: 1px dashed #eee;"><span>${it.menu_name} <span class="text-muted">× ${it.quantity}</span></span><span class="text-muted" style="font-size:0.85rem;">Rs. ${Number(it.total_price).toFixed(2)}</span></div>`).join('');
          let actionHtml = '';
          if (o.status === 'Delivered' || o.status === 'Completed') actionHtml = `<small class="text-success mt-1 fw-semibold" style="font-size:0.78rem; display:block;">Order delivered successfully.</small>`;
          else if (o.status === 'Preparing' || o.status === 'Out for delivery') actionHtml = `<small class="text-danger mt-1 fw-semibold" style="font-size:0.78rem; display:block;">Your order is already on the way and can no longer be cancelled.</small>`;
          else actionHtml = `<button class="btn btn-sm btn-danger mt-2 fw-bold" style="font-size: 0.75rem; padding: 2px 8px; border-radius: 4px;" onclick="cancelOrder('${orderRef}')">Cancel Order</button>`;
          return `<tr class="${statusChanged ? 'status-updated' : ''}"><td style="font-weight: 700; color: #0d47a1;">${orderRef}</td><td style="min-width: 250px;">${itemsHtml}</td><td style="font-weight: 700; font-size: 1.05rem;">Rs. ${Number(o.total_amount).toFixed(2)}</td><td><div class="status-container"><span class="status-badge ${o.status === 'Pending' ? 'status-pending' : o.status === 'Confirmed' ? 'status-confirmed' : o.status === 'Preparing' ? 'status-preparing' : o.status === 'Ready' ? 'status-ready' : o.status === 'Delivered' || o.status === 'Completed' ? 'status-completed' : 'status-cancelled'}">${o.status}</span>${actionHtml}</div></td></tr>`;
        }).join('');
        previousOrders = currentOrders;
      } catch { document.getElementById('orders-body').innerHTML = `<tr><td colspan="4" class="text-center text-danger">Error loading orders.</td></tr>`; }
    };
    fetchOrders();
    setInterval(fetchOrders, POLL_MS);

    let serverOffset = 0;
    const initTimeOffset = (serverTimeStr) => {
      if (!serverTimeStr) return;
      serverOffset = new Date(serverTimeStr.replace(' ', 'T')).getTime() - new Date().getTime();
    };
    const getServerTime = () => new Date(new Date().getTime() + serverOffset);
    const fetchBookings = async () => {
      try {
        const res = await fetch('../includes/bookings_fetch.php');
        const data = await res.json();
        const tbody = document.getElementById('bookings-body');
        if (!data.ok) { tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">Please login to view your bookings.</td></tr>`; return; }
        if (data.server_now) initTimeOffset(data.server_now);
        if (!data.bookings || data.bookings.length === 0) { tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No table bookings found.</td></tr>`; return; }
        tbody.innerHTML = data.bookings.map(b => {
          const tableLabel = b.table_number || b.table_name || ('Table ' + (b.table_id || 'N/A'));
          const statusClass = `status-${b.status.toLowerCase()}`;
          const timerHtml = b.status === 'Confirmed' && b.grace_end_at ? `<div class="countdown-container" data-grace-end="${b.grace_end_at}">--:--</div>` : `<span class="text-muted">-</span>`;
          return `<tr><td><strong style="color: #0d47a1;">${tableLabel}</strong><br><small class="text-muted">Capacity: ${b.capacity}</small></td><td><strong>${b.formatted_date}</strong><br><span class="text-muted">${b.formatted_time}</span></td><td style="font-weight: 700;">${b.people}</td><td><div class="status-container"><span class="status-badge ${statusClass}">${b.status}</span><div style="margin-top: 5px;">${timerHtml}</div></div></td></tr>`;
        }).join('');
      } catch (e) { console.error(e); }
    };
    const updateCountdowns = () => {
      document.querySelectorAll('.countdown-container').forEach(container => {
        const graceEndStr = container.getAttribute('data-grace-end');
        if (!graceEndStr) return;
        const graceEnd = new Date(graceEndStr.replace(' ', 'T'));
        const bookingTime = new Date(graceEnd.getTime() - (20 * 60 * 1000));
        const now = getServerTime();
        if (now < bookingTime) container.innerHTML = `<span style="color:#6b7280;font-size:12px;">Starts at ${bookingTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>`;
        else if (now >= graceEnd) container.innerHTML = '<span class="grace-expired">Grace period expired</span>';
        else {
          const diff = graceEnd - now, mins = Math.floor(diff / 60000), secs = Math.floor((diff % 60000) / 1000);
          const timeStr = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
          let colorClass = 'grace-timer'; if (mins < 5) colorClass += ' grace-danger'; else if (mins < 10) colorClass += ' grace-warning';
          container.innerHTML = `<div style="font-size:11px;color:#d97706;">⚠️ Booking started</div><div class="${colorClass}">⏳ ${timeStr} left</div>`;
        }
      });
    };
    fetchBookings();
    setInterval(fetchBookings, POLL_MS);
    setInterval(updateCountdowns, 1000);
  }
});
