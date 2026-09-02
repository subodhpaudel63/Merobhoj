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
          const itemsHtml = o.items.map(it => `<div class="d-flex justify-content-between align-items-center py-1" style="border-bottom: 1px dashed #eee;"><span>${it.menu_name} <span class="text-muted">Ã— ${it.quantity}</span></span><span class="text-muted" style="font-size:0.85rem;">Rs. ${Number(it.total_price).toFixed(2)}</span></div>`).join('');
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

  }
});




/* =================================================================
   MY ORDERS PAGE â€” LIVE BACKEND WIRING
   ------------------------------------------------------------------
   All order data on this page is fetched at runtime from the existing
   backend endpoint includes/orders_fetch.php (JSON; prepared
   statements; scoped to the logged-in user's email).
     - Status / steps come straight from the DB (orders.status)
     - Cancellations go through includes/order_cancel_customer.php
       (server-side ownership + transition validation)
     - "Reorder" reuses the existing cart endpoint
       includes/cart.php?action=add
   No hardcoded demo order data is used anywhere below.
   ================================================================= */

const MYORDER_STEP_ICONS   = ['ic-clipboard', 'ic-clipboard', 'ic-pot', 'ic-bag', 'ic-bike', 'ic-door'];
const STEP_ICONS           = MYORDER_STEP_ICONS; // kept for the mini stepper renderer
const MYORDER_STEP_LABELS  = ['Order Placed', 'Confirmed', 'Preparing', 'Ready', 'Out for Delivery', 'Delivered'];
const MYORDER_STATUS_STEP  = { Pending: 0, Confirmed: 1, Preparing: 2, Ready: 3, Delivering: 4, Completed: 5, Cancelled: -1 };
// Stepper index -> DB status, used to look up each step's time in the
// permanent per-status history returned by orders_fetch.php
const MYORDER_STEP_DB_STATUS = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivering', 'Completed'];
const MYORDER_STATUS_LABEL = {
  Pending: 'Order Placed', Confirmed: 'Confirmed', Preparing: 'Preparing',
  Ready: 'Ready', Delivering: 'Out for Delivery', Completed: 'Delivered', Cancelled: 'Cancelled'
};
const MYORDER_STATUS_SUB = {
  Pending:    'Waiting for the restaurant to confirm your order.',
  Confirmed:  'The restaurant has confirmed your order.',
  Preparing:  'Your food is being prepared right now.',
  Ready:      'Your order is ready and will be on its way shortly.',
  Delivering: 'Your order is on the way!',
  Completed:  'Your order has been delivered. Enjoy your meal!',
  Cancelled:  'This order has been cancelled.'
};
/* Must mirror the customer-cancellation rules in includes/order_validation.php
   (customers may cancel only while the order has not left the restaurant) */
const MYORDER_CANCELLABLE   = ['Pending', 'Confirmed', 'Preparing', 'Ready'];
const MYORDER_LIVE_STATUSES = ['Pending', 'Confirmed', 'Preparing', 'Ready', 'Delivering'];
const MYORDER_DEFAULT_THUMB = '../assets/images/NangloSet.png';
const MYORDER_POLL_MS       = 5000;

let CONFIG = null;   // order currently shown on the tracking page (read by myorder.php inline handlers)
let orders = [];     // orders list for page 2
let currentOrdersFilter = 'all';
let mapBooted = false;
let myorderDataSignature = null; // last fetched payload fingerprint (skip re-render when unchanged)
let myorderInFlight = false;     // prevents overlapping poll requests

const myorderEscape = (v) => String(v ?? '').replace(/[&<>"']/g, (ch) => (
  { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));

const myorderMoney = (n) => 'NPR ' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const myorderImgSrc = (p) => {
  if (!p) return MYORDER_DEFAULT_THUMB;
  if (/^(https?:)?\/\//i.test(p) || p.startsWith('../') || p.startsWith('/')) return p;
  return '../' + String(p).replace(/^\/+/, '');
};

function myorderFormatDate(value) {
  if (!value) return 'â€”';
  const d = new Date(String(value).replace(' ', 'T'));
  return isNaN(d.getTime()) ? String(value) : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function myorderFormatTime(value) {
  if (!value) return 'â€”';
  // Accept "HH:MM:SS", "HH:MM", or full "YYYY-MM-DD HH:MM:SS" datetimes —
  // keep only the time portion so it always renders in 12-hour format
  const timePart = String(value).trim().split(' ').pop();
  const d = new Date('2000-01-01T' + timePart);
  return isNaN(d.getTime()) ? String(value) : d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

function myorderToast(type, text) {
  if (window.ToastNotifications && typeof window.ToastNotifications[type] === 'function') {
    window.ToastNotifications[type](text);
  } else {
    alert(text);
  }
}

/* ---- Map one grouped API order -> the card model the UI renders ---- */
function myorderMapOrder(g) {
  const status = g.status || 'Pending';
  const known = Object.prototype.hasOwnProperty.call(MYORDER_STATUS_STEP, status);
  const stepIdx = known ? MYORDER_STATUS_STEP[status] : 0;
  const bucket = status === 'Completed' ? 'delivered' : (status === 'Cancelled' ? 'cancelled' : 'ongoing');
  const date = myorderFormatDate(g.order_date);
  const time = myorderFormatTime(g.order_time);

  const items = (g.items || []).map((it) => ({
    name: it.menu_name,
    qty: Number(it.quantity) || 1,
    price: myorderMoney(it.price),
    total: Number(it.total_price) || 0,
    menuId: it.menu_id,
    img: myorderImgSrc(it.menu_image)
  }));

  const subtotal = items.reduce((sum, it) => sum + it.total, 0);
  const bill = {
    subtotal: myorderMoney(subtotal),
    deliveryFee: '--',
    tax: '--',
    total: myorderMoney(g.total_amount ?? subtotal)
  };

  const address = String(g.address || '').trim();

  // Permanent per-status history: each step shows the exact 12-hour time the
  // admin set that status (fetched from order_status_history). Fallbacks keep
  // orders without history entries sensible (placed time on the current step).
  const history = g.status_history || {};
  const statusTimeRaw = g.status_updated_at ? String(g.status_updated_at).trim().split(' ').pop() : '';
  const statusTime = statusTimeRaw ? myorderFormatTime(statusTimeRaw) : '';
  const doneCount = status === 'Cancelled' ? 1 : stepIdx + 1;
  const stepTimes = MYORDER_STEP_LABELS.map((_, i) => {
    const histTime = history[MYORDER_STEP_DB_STATUS[i]];
    if (histTime) return myorderFormatTime(histTime);
    if (i === 0) return time;
    if (i === doneCount - 1) return statusTime || time;
    return '--:--';
  });

  return {
    id: '#' + (g.order_number || ('ORD-' + String(g.order_id || '').padStart(4, '0'))),
    orderNumber: g.order_number || ('ORD-' + String(g.order_id || '').padStart(4, '0')),
    orderType: g.order_type || 'Delivery',
    name: 'Merobhoj',
    addr: address || 'No delivery address recorded',
    hasAddress: !!address,
    img: items.length ? items[0].img : MYORDER_DEFAULT_THUMB,
    date: date + ' | ' + time,
    placedDate: date,
    placedTime: time,
    items: items.length + (items.length === 1 ? ' Item' : ' Items'),
    itemsList: items,
    bill,
    amount: myorderMoney(g.total_amount ?? subtotal),
    status: bucket,
    statusLabel: known ? MYORDER_STATUS_LABEL[status] : status,
    statusSub: known ? MYORDER_STATUS_SUB[status] : ('Current status: ' + status),
    payment: g.payment_method || '--',
    steps: status === 'Cancelled' ? null : MYORDER_STEP_LABELS,
    stepTimes,
    doneCount,
    statusUpdatedAt: statusTime,
    trackable: status !== 'Cancelled',
    live: MYORDER_LIVE_STATUSES.includes(status),
    cancellable: MYORDER_CANCELLABLE.includes(status),
    rawStatus: status
  };
}

/* ---- Build CONFIG (tracking page model) for one order card ---- */
function myorderBuildConfig(o) {
  return {
    ORDER: o,
    RESTAURANT: { name: 'Merobhoj', address: 'Pokhara' },
    ROUTE: {
      homeQuery: o.addr,                    // the customer's real delivery address from the DB
      mapCenter: [28.2105, 83.9565],
      mapZoom: 14.3,
      rideDurationMs: 5 * 60 * 1000
    }
  };
}

/* ---- Pick which order the tracking page shows: ?order= param, else the
        most recent non-cancelled one, else the newest order ---- */
function myorderPickTracked() {
  const requested = (new URLSearchParams(window.location.search).get('order') || '').trim();
  const safe = /^[A-Za-z0-9-]{1,50}$/.test(requested) ? requested : '';
  if (safe) {
    const match = orders.find((o) => o.orderNumber === safe || o.id === '#' + safe);
    if (match) return match;
  }
  // Keep showing the order the user explicitly chose to track
  if (myorderSelectedOrderNumber) {
    const sel = orders.find((o) => o.orderNumber === myorderSelectedOrderNumber);
    if (sel) return sel;
  }
  return orders.find((o) => o.trackable) || orders[0] || null;
}

/* ---- Fill every static field on the tracking page from CONFIG ---- */
function renderTrackPage(cfg) {
  const o = cfg.ORDER;
  const thumb = document.getElementById('cfgRestaurantThumb');
  if (thumb) { thumb.src = o.img; thumb.alt = o.name; }
  const crumb = document.getElementById('cfgOrderCrumb');
  if (crumb) { crumb.textContent = 'Order ' + o.id; crumb.title = o.statusLabel; }
  document.getElementById('cfgOrderId').textContent = o.id;
  document.getElementById('cfgOrderIdRepeat').textContent = o.id;
  document.getElementById('cfgPlacedDate').textContent = o.placedDate;
  document.getElementById('cfgPlacedTime').textContent = o.placedTime;
  document.getElementById('cfgOrderTime').textContent = o.placedDate + ' | ' + o.placedTime;
  document.getElementById('cfgPaymentMethod').textContent = o.payment;
  document.getElementById('cfgTotalAmount').textContent = o.amount;

  // No rider/dispatch data exists in the database yet â€” hide the demo rider card
  const riderName = document.getElementById('cfgRiderName');
  const riderCard = riderName ? riderName.closest('.card') : null;
  if (riderCard) riderCard.style.display = 'none';

  const itemsHtml = (o.itemsList || []).map((it) => `
    <div class="item-row">
      <img src="${myorderEscape(it.img)}" alt="${myorderEscape(it.name)}">
      <div class="item-info"><div class="item-name">${myorderEscape(it.name)}</div><div class="item-qty">Qty: ${it.qty}</div></div>
      <div class="item-price">${it.price}</div>
    </div>`).join('') || '<div class="status-sub">No items recorded for this order.</div>';
  document.getElementById('cfgItemsList').innerHTML = itemsHtml;
}

/* ---- Drive the status-dependent parts of the tracking page
        (stepper, live banner, cancel button, ETA card, map) ---- */
function renderTrackStatus(cfg) {
  const o = cfg.ORDER;
  const cancelled = o.rawStatus === 'Cancelled';
  const lines = document.querySelector('#page-track .steplines');
  const row = document.querySelector('#page-track .stepper');

  if (row) {
    if (cancelled) {
      if (lines) lines.innerHTML = '';
      row.innerHTML = `<div class="step current"><div class="dot"><svg class="ic" style="width:22px;height:22px"><use href="#ic-trash"/></svg></div><div class="slabel">Order Cancelled</div><div class="stime">${myorderEscape(o.placedDate)}</div></div>`;
    } else {
      if (lines) {
        let segs = '';
        for (let i = 0; i < MYORDER_STEP_LABELS.length - 1; i++) {
          segs += `<div class="stepline-seg ${i < o.doneCount - 1 ? 'done' : 'pending'}"></div>`;
        }
        lines.innerHTML = segs;
      }
      row.innerHTML = MYORDER_STEP_LABELS.map((label, i) => {
        const cls = i < o.doneCount - 1 ? 'done' : (i === o.doneCount - 1 ? 'current' : 'pending');
          const icon = MYORDER_STEP_ICONS[i] || 'ic-clipboard';
        return `<div class="step ${cls}"><div class="dot"><svg class="ic" style="width:22px;height:22px"><use href="#${icon}"/></svg></div><div class="slabel">${label}</div><div class="stime">${o.stepTimes[i]}</div></div>`;
      }).join('');
    }
  }

  const banner = document.querySelector('#page-track .live-banner');
  if (banner) {
    if (o.live) {
      banner.style.display = '';
      const msg = banner.querySelector('.lb-left span:nth-of-type(2)');
      if (msg) msg.textContent = o.statusSub;
      const lu = document.getElementById('lastUpdated');
      if (lu) lu.textContent = o.statusUpdatedAt || new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    } else {
      banner.style.display = 'none';
    }
  }

  // Cancel button â€” shown only while the DB status permits cancellation
  const cancelCol = document.querySelector('#page-track .cancel-col');
  if (cancelCol) {
    cancelCol.style.display = o.cancellable ? '' : 'none';
    const note = cancelCol.querySelector('.cancel-note');
    if (note) note.textContent = 'You can cancel until your order is out for delivery.';
  }

  // ETA card & live map only make sense for an undelivered order with an address
  const showLogistics = o.hasAddress && !cancelled;
  const etaMin = document.getElementById('etaMin');
  const etaCard = etaMin ? etaMin.closest('.card') : null;
  if (etaCard) etaCard.style.display = showLogistics ? '' : 'none';
  const mapCard = document.querySelector('#page-track .map-card');
  if (mapCard) mapCard.style.display = showLogistics ? '' : 'none';
}

/* ---- Cancel the tracked order via the existing backend endpoint ---- */
async function cancelTrackedOrder() {
  const o = CONFIG && CONFIG.ORDER;
  if (!o || !o.cancellable) {
    myorderToast('error', 'This order can no longer be cancelled.');
    return;
  }
  if (!window.confirm('Are you sure you want to cancel order ' + o.id + '?')) return;
  try {
    const res = await fetch('../includes/order_cancel_customer.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order_number: o.orderNumber })
    });
    const data = await res.json();
    if (data.success) {
      myorderToast('success', data.message || 'Order cancelled successfully.');
      setTimeout(() => window.location.reload(), 900);
    } else {
      myorderToast('error', data.message || 'Failed to cancel the order.');
    }
  } catch (err) {
    console.error(err);
    myorderToast('error', 'An error occurred while cancelling the order.');
  }
}

/* ---- Re-order using the existing cart endpoint (includes/cart.php?action=add) ---- */
async function reorderOrder(o) {
  if (!o || !o.itemsList || !o.itemsList.length) {
    myorderToast('error', 'No items to reorder.');
    return;
  }
  try {
    for (const it of o.itemsList) {
      const body = new URLSearchParams();
      body.append('menu_id', it.menuId ?? 0);
      body.append('menu_name', it.name);
      body.append('price', it.qty ? (it.total / it.qty) : 0);
      body.append('quantity', it.qty);
      body.append('image', it.img);
      const res = await fetch('../includes/cart.php?action=add', {
        method: 'POST',
        credentials: 'same-origin',
        body
      });
      if (!res.ok) throw new Error('Cart request failed');
    }
    myorderToast('success', 'Items added to your cart.');
    setTimeout(() => { window.location.href = '../client/cart.php'; }, 800);
  } catch (err) {
    console.error(err);
    myorderToast('error', 'Could not add the items to your cart.');
  }
}

/* ---------------- NAVIGATION BETWEEN THE TWO "PAGES" ---------------- */
function showPage(name) {
  const trackEl = document.getElementById('page-track');
  const listEl = document.getElementById('page-list');
  const bkEl = document.getElementById('page-bookings');
  if (trackEl) trackEl.classList.toggle('active', name === 'track');
  if (listEl) listEl.classList.toggle('active', name === 'list');
  if (bkEl) bkEl.classList.toggle('active', name === 'bookings');
  window.scrollTo({ top: 0, behavior: 'smooth' });
  if (name === 'track' && trackEl) setTimeout(() => { if (map) { map.invalidateSize(); } }, 80);
}

/* ---------------- TRACK A SPECIFIC ORDER ----------------
   On the orders list page (myorder.php) tracking now opens its own
   standalone page (client/track_order.php?order=<orderNumber>) — like
   the original design. This helper performs that navigation and also
   records which order the user picked so any in-page reload keeps it. */
function openTrackPage(o) {
  if (!o || !o.orderNumber) return;
  myorderSelectedOrderNumber = o.orderNumber;
  window.location.href = 'track_order.php?order=' + encodeURIComponent(o.orderNumber);
}

/* ---------------- TRACKING STATE ----------------
   myorderSelectedOrderNumber: the order the user explicitly chose to track
   (kept so the order survives polling refreshes and page reloads). */
let myorderSelectedOrderNumber = null; // user-picked order, survives polling refreshes
let riderTimer = null;                 // rider animation interval handle

function resetMap() {
  if (riderTimer) { clearInterval(riderTimer); riderTimer = null; }
  if (map) { try { map.remove(); } catch (err) { /* already gone */ } }
  map = null; riderMarker = null; routeLine = null; routePath = [];
  distanceKm = 0; etaMinutes = 0;
  mapBooted = false;
}



/* "Track This Order" inside the details modal — opens the order in its own
   standalone tracking page (client/track_order.php), like the old design. */
function trackOrderFromModal() {
  const modal = document.getElementById('detailsModal');
  const id = modal && modal.dataset.orderId;
  const o = id && orders.find((x) => x.id === id);
  if (o) openTrackPage(o);
  else window.location.href = 'track_order.php'; // no order selected -> latest active order
}

/* ---------------- ORDERS LIST (page 2) ---------------- */
function miniStepper(o) {
  if (!o.steps) return '';
  let dots = '', lines = '';
  o.steps.forEach((s, i) => {
    const cls = i < o.doneCount - 1 ? 'done' : (i === o.doneCount - 1 ? 'current' : 'pending');
    const iconName = STEP_ICONS[i] || 'ic-clipboard';
    const iconHtml = `<svg class="ic" style="width:15px;height:15px"><use href="#${iconName}"/></svg>`;
    dots += `<div class="mini-step ${cls}"><div class="mini-dot">${iconHtml}</div><div class="ml">${myorderEscape(s)}</div><div class="mt">${myorderEscape(o.stepTimes[i])}</div></div>`;
    if (i < o.steps.length - 1) {
      lines += `<div class="mini-line-seg ${i < o.doneCount - 1 ? 'done' : 'pending'}"></div>`;
    }
  });
  return `<div class="mini-steps"><div class="mini-line-track">${lines}</div>${dots}</div>`;
}

function renderOrders(filter) {
  const wrap = document.getElementById('ordersWrap');
  if (!wrap) return;
  const list = orders.filter((o) => filter === 'all' ? true : o.status === filter);
  if (!list.length) {
    wrap.innerHTML = '<div class="card order-card"><div class="status-sub">No orders in this category yet.</div></div>';
    return;
  }
  wrap.innerHTML = list.map((o) => `
    <div class="card order-card ${o.live ? 'is-live' : ''}" data-order-id="${myorderEscape(o.id)}">
      <div class="order-top">
        <img class="order-thumb" src="${myorderEscape(o.img)}" alt="${myorderEscape(o.name)}">
        <div class="order-info">
          <div class="rname">${myorderEscape(o.name)}</div>
          <div class="raddr">${myorderEscape(o.addr)}</div>
          <div class="rmeta">Order ID: ${myorderEscape(o.id)}<br>${myorderEscape(o.date)}<br>${myorderEscape(o.items)}</div>
        </div>
        <div class="order-status-col" ${o.trackable ? 'data-action="track" title="View live tracking"' : ''}>
          <span class="status-pill ${o.status}">${myorderEscape(o.statusLabel)}</span>
          <div class="status-sub">${myorderEscape(o.statusSub)}</div>
          ${miniStepper(o)}
        </div>
        <div class="order-amount-col">
          <div class="amt-label">Total Amount</div>
          <div class="amt-val">${o.amount}</div>
          <div class="order-btn-col">
            <button class="obtn outline" data-action="details">View Details</button>
            ${o.trackable
              ? '<button class="obtn solid" data-action="track">Track Order</button>'
              : '<button class="obtn outline" data-action="reorder"><svg class="ic" style="width:13px;height:13px"><use href="#ic-reorder"/></svg> Reorder</button>'}
          </div>
        </div>
      </div>
      ${o.live ? `<div class="order-mini-live"><span class="lb-left-txt"><span class="live-pill" style="font-size:10px;"><span class="dotpulse"></span> LIVE</span> &nbsp;${myorderEscape(o.statusSub)}</span><span class="mf-sub" style="flex-shrink:0;">Last updated: just now</span></div>` : ''}
    </div>`).join('');
}

/* Event delegation â€” no inline handlers on dynamically generated cards */
const ordersWrapEl = document.getElementById('ordersWrap');
if (ordersWrapEl) {
  ordersWrapEl.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const card = btn.closest('.order-card');
    const o = orders.find((x) => x.id === (card && card.dataset.orderId));
    if (!o) return;
    if (btn.dataset.action === 'details') openOrderDetails(o.id);
        else if (btn.dataset.action === 'track') openTrackPage(o);
    else if (btn.dataset.action === 'reorder') reorderOrder(o);
  });
}

const ordersFilterBtn = document.querySelector('.filter-btn');
const ordersFilterMenu = document.getElementById('ordersFilterMenu');
const ordersFilterLabel = document.getElementById('filterLabel');
const myorderFilterLabelMap = {
  all: 'Filter',
  ongoing: 'Ongoing',
  delivered: 'Delivered',
  cancelled: 'Cancelled'
};

function myorderSyncFilterLabel(filter) {
  const label = myorderFilterLabelMap[filter] || 'Filter';
  if (ordersFilterLabel) ordersFilterLabel.textContent = label;
}

function myorderApplyFilter(filter) {
  currentOrdersFilter = filter;
  myorderSyncFilterLabel(filter);
  document.querySelectorAll('.tab').forEach((x) => x.classList.toggle('active', x.dataset.tab === filter));
  document.querySelectorAll('.filter-option').forEach((opt) => {
    opt.classList.toggle('selected', opt.dataset.filter === filter);
  });
  renderOrders(currentOrdersFilter);
  if (ordersFilterMenu) ordersFilterMenu.hidden = true;
  if (ordersFilterBtn) ordersFilterBtn.setAttribute('aria-expanded', 'false');
}

if (ordersFilterBtn && ordersFilterMenu) {
  ordersFilterBtn.addEventListener('click', (event) => {
    event.stopPropagation();
    const open = !ordersFilterMenu.hidden;
    ordersFilterMenu.hidden = open;
    ordersFilterBtn.setAttribute('aria-expanded', String(!open));
  });

  document.addEventListener('click', (event) => {
    if (!ordersFilterMenu.contains(event.target) && !ordersFilterBtn.contains(event.target)) {
      ordersFilterMenu.hidden = true;
      ordersFilterBtn.setAttribute('aria-expanded', 'false');
    }
  });

  ordersFilterMenu.querySelectorAll('.filter-option').forEach((option) => {
    option.addEventListener('click', () => myorderApplyFilter(option.dataset.filter));
  });
}

document.querySelectorAll('.tab').forEach((t) => {
  t.addEventListener('click', () => myorderApplyFilter(t.dataset.tab));
});

/* =================================================================
   VIEW DETAILS MODAL â€” looks an order up by id (the tracked CONFIG
   order first, then the orders list) and renders it into #detailsModal
   ================================================================= */
function findOrderById(id) {
  if (CONFIG && id === CONFIG.ORDER.id) {
    return {
      id: CONFIG.ORDER.id,
      date: CONFIG.ORDER.placedDate + ' | ' + CONFIG.ORDER.placedTime,
      name: CONFIG.ORDER.name, addr: CONFIG.ORDER.addr, img: CONFIG.ORDER.img,
      statusLabel: CONFIG.ORDER.statusLabel, statusSub: CONFIG.ORDER.statusSub, status: CONFIG.ORDER.status,
      itemsList: CONFIG.ORDER.itemsList, bill: CONFIG.ORDER.bill, payment: CONFIG.ORDER.payment
    };
  }
  return orders.find((o) => o.id === id);
}

function openOrderDetails(id) {
  const o = findOrderById(id);
  if (!o) return;

  // Remember which order this modal is showing so "Track This Order"
  // can switch the tracking page to it
  const modalEl = document.getElementById('detailsModal');
  if (modalEl) modalEl.dataset.orderId = id;

  document.getElementById('mOrderMeta').textContent = o.id + ' | ' + o.date;
  const resThumb = document.getElementById('mResThumb');
  if (resThumb) { resThumb.src = o.img; resThumb.alt = o.name; }
  document.getElementById('mResName').textContent = o.name;
  document.getElementById('mResAddr').textContent = o.addr;

  const pill = document.getElementById('mStatusPill');
  pill.textContent = o.statusLabel;
  pill.className = 'status-pill ' + (o.status || 'ongoing');
  document.getElementById('mStatusSub').textContent = o.statusSub || '';

  document.getElementById('mItemsList').innerHTML = (o.itemsList || []).map((it) => `
    <div class="item-row">
      <img src="${myorderEscape(it.img)}" alt="${myorderEscape(it.name)}">
      <div class="item-info"><div class="item-name">${myorderEscape(it.name)}</div><div class="item-qty">Qty: ${it.qty}</div></div>
      <div class="item-price">${it.price}</div>
    </div>`).join('') || '<div class="status-sub">No item details available.</div>';

  const b = o.bill || {};
  document.getElementById('mBillList').innerHTML = `
    <div class="modal-bill-row"><span>Subtotal</span><span>${b.subtotal || '--'}</span></div>
    <div class="modal-bill-row"><span>Delivery Fee</span><span>${b.deliveryFee || '--'}</span></div>
    <div class="modal-bill-row"><span>Tax</span><span>${b.tax || '--'}</span></div>
    <div class="modal-bill-row total"><span>Total</span><span>${b.total || '--'}</span></div>`;

  document.getElementById('mPayment').textContent = o.payment || '--';
  document.getElementById('mOrderTime').textContent = o.date;

  document.getElementById('detailsModal').classList.add('open');
}

function closeOrderDetails() {
  document.getElementById('detailsModal').classList.remove('open');
}

/* =================================================================
   LIVE MAP â€” real APIs, no hardcoded route:
   1) Nominatim Geocoding API  â†’ turns the order's delivery address into lat/lng
   2) OSRM Routing API         â†’ returns the actual road route + real
                                 distance & duration between those points
   Both are free, keyless, public OpenStreetMap-ecosystem APIs.
   ================================================================= */
const GEOCODE_API = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=';
const ROUTE_API   = 'https://router.project-osrm.org/route/v1/driving/';

let map, riderMarker, routeLine;
let routePath = [];
let distanceKm = 0;
let etaMinutes = 0;

async function geocode(query) {
  const res = await fetch(GEOCODE_API + encodeURIComponent(query), {
    headers: { 'Accept-Language': 'en' }
  });
  const data = await res.json();
  if (!data || !data[0]) throw new Error('No geocode result for ' + query);
  return { lat: parseFloat(data[0].lat), lng: parseFloat(data[0].lon) };
}

async function fetchRoute(from, to) {
  const url = `${ROUTE_API}${from.lng},${from.lat};${to.lng},${to.lat}?overview=full&geometries=geojson`;
  const res = await fetch(url);
  const data = await res.json();
  if (!data.routes || !data.routes[0]) throw new Error('No route found');
  const r = data.routes[0];
  return {
    coords: r.geometry.coordinates.map((c) => [c[1], c[0]]), // GeoJSON is [lng,lat] -> flip to [lat,lng]
    distanceKm: r.distance / 1000,
    durationMin: r.duration / 60
  };
}

// Restaurant location (fixed) and fallback path (only if live APIs are unreachable)
const fallbackRestaurant = [28.2110, 83.9520];
const fallbackHome       = [28.2145, 83.9605];
const fallbackPath = [fallbackRestaurant, [28.2088, 83.9548], [28.2065, 83.9575], [28.2100, 83.9600], [28.2128, 83.9598], fallbackHome];

async function initMap() {
  if (mapBooted || !CONFIG || !CONFIG.ORDER.hasAddress) return;
  if (typeof L === 'undefined' || !document.getElementById('map')) return;
  mapBooted = true;

  map = L.map('map', { zoomControl: true, attributionControl: true }).setView(CONFIG.ROUTE.mapCenter, CONFIG.ROUTE.mapZoom);
  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    maxZoom: 20,
    subdomains: 'abcd',
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
  }).addTo(map);
  L.control.scale({ position: 'bottomleft', imperial: false }).addTo(map);

  const restIcon = L.divIcon({ className: '', html: '<div style="background:#fff;border-radius:10px;padding:7px 11px;box-shadow:0 2px 8px rgba(0,0,0,.18);font:600 12px Inter,sans-serif;white-space:nowrap;display:flex;align-items:center;gap:6px;"><span style="width:22px;height:22px;border-radius:6px;background:#fdf0e2;color:#f2994a;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><svg style="width:14px;height:14px"><use href="#ic-bag"/></svg></span><span><b>' + CONFIG.RESTAURANT.name + '</b><br><span style="font-weight:400;color:#777;font-size:11px;">' + CONFIG.RESTAURANT.address + '</span></span></div>', iconSize: null, iconAnchor: [10, 50] });
  const homeIcon = L.divIcon({ className: '', html: '<div style="background:#fff;border-radius:10px;padding:7px 11px;box-shadow:0 2px 8px rgba(0,0,0,.18);font:600 12px Inter,sans-serif;white-space:nowrap;display:flex;align-items:center;gap:6px;"><span style="width:22px;height:22px;border-radius:6px;background:#fdeceb;color:#e0392b;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><svg style="width:14px;height:14px"><use href="#ic-pin"/></svg></span><span><b>Your Location</b><br><span style="font-weight:400;color:#777;font-size:11px;">' + myorderEscape(CONFIG.ROUTE.homeQuery.split(',')[0]) + '</span></span></div>', iconSize: null, iconAnchor: [10, 50] });
  const riderIcon = L.divIcon({ className: '', html: '<div style="position:relative;width:34px;height:34px;"><div style="position:absolute;top:50%;left:50%;width:34px;height:34px;border-radius:50%;background:rgba(23,138,76,0.25);transform:translate(-50%,-50%);animation:riderPulse 1.6s infinite;"></div><div style="position:relative;width:34px;height:34px;border-radius:50%;background:#178a4c;display:flex;align-items:center;justify-content:center;color:#fff;box-shadow:0 2px 6px rgba(0,0,0,.35);"><svg style="width:19px;height:19px"><use href="#ic-bike"/></svg></div></div>', iconSize: [34, 34], iconAnchor: [17, 17] });

  let restaurantPos, homePos;
  try {
    // Restaurant position is fixed; the customer point comes from the order's delivery address.
    restaurantPos = fallbackRestaurant;
    const g = await geocode(CONFIG.ROUTE.homeQuery);
    homePos = [g.lat, g.lng];

    const route = await fetchRoute({ lat: restaurantPos[0], lng: restaurantPos[1] }, { lat: homePos[0], lng: homePos[1] });
    routePath = route.coords;
    distanceKm = route.distanceKm;
    etaMinutes = Math.max(Math.round(route.durationMin), 1);
  } catch (err) {
    console.warn('Live routing API unavailable, using fallback route:', err);
    restaurantPos = fallbackRestaurant;
    homePos = fallbackHome;
    routePath = fallbackPath;
  }

  L.marker(restaurantPos, { icon: restIcon }).addTo(map);
  L.marker(homePos, { icon: homeIcon }).addTo(map);
  routeLine = L.polyline(routePath, { color: '#178a4c', weight: 4, opacity: 0.9 }).addTo(map);
  riderMarker = L.marker(routePath[0], { icon: riderIcon }).addTo(map);
  map.fitBounds(routeLine.getBounds(), { padding: [60, 60] });

  updateEtaUI();
  // The animated rider only makes sense while the order is actually out for delivery
  if (CONFIG.ORDER.rawStatus === 'Delivering') animateRider();
}

function haversine(a, b) {
  const R = 6371, toRad = (d) => d * Math.PI / 180;
  const dLat = toRad(b[0] - a[0]), dLon = toRad(b[1] - a[1]);
  const s = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a[0])) * Math.cos(toRad(b[0])) * Math.sin(dLon / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(s));
}

function updateEtaUI() {
  const set = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
  set('distVal', distanceKm.toFixed(1) + ' km');
  set('etaVal', etaMinutes + ' min');
  set('mfDist', distanceKm.toFixed(1) + ' km away');
  set('mfEta', etaMinutes + ' min');
  set('etaMin', etaMinutes + ' min');
  set('lastUpdated', new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }));
}

function cumulativeDistances(path) {
  const cum = [0];
  for (let i = 1; i < path.length; i++) {
    cum.push(cum[i - 1] + haversine(path[i - 1], path[i]));
  }
  return cum;
}

function pointAtFraction(path, cum, frac) {
  const target = cum[cum.length - 1] * frac;
  let i = 0;
  while (i < cum.length - 1 && cum[i + 1] < target) i++;
  const segLen = cum[i + 1] - cum[i];
  const t = segLen > 0 ? (target - cum[i]) / segLen : 0;
  const a = path[i], b = path[Math.min(i + 1, path.length - 1)];
  return {
    lat: a[0] + (b[0] - a[0]) * t,
    lng: a[1] + (b[1] - a[1]) * t,
    remainingKm: cum[cum.length - 1] - target
  };
}

function animateRider() {
  const RIDE_DURATION_MS = CONFIG.ROUTE.rideDurationMs;
  const cum = cumulativeDistances(routePath);
  const startTime = Date.now();

  riderTimer = setInterval(() => {
    if (routePath.length < 2) return;
    const elapsed = Date.now() - startTime;
    const frac = Math.min(elapsed / RIDE_DURATION_MS, 1);
    const { lat, lng, remainingKm } = pointAtFraction(routePath, cum, frac);
    riderMarker.setLatLng([lat, lng]);

    distanceKm = Math.max(remainingKm, 0.02);
    etaMinutes = Math.max(Math.ceil((1 - frac) * (RIDE_DURATION_MS / 60000)), frac >= 1 ? 0 : 1);
    updateEtaUI();
  }, 300);
}

/* =================================================================
   DATA LOADING â€” pulls the logged-in user's orders from
   includes/orders_fetch.php and boots the whole page from it.
   ================================================================= */
function myorderRenderEmpty(errorMessage) {
  const wrap = document.getElementById('ordersWrap');
    if (wrap) wrap.innerHTML = `<div class="card order-card"><div class="status-sub">${myorderEscape(errorMessage || 'You have no orders yet. Browse the menu to place one!')}</div></div>`;
  // Only switch to the orders-list section when this page actually has one
  // (e.g. the standalone track_order.php has no #page-list and must stay visible).
  if (typeof showPage === 'function' && document.getElementById('page-list')) showPage('list');
}

async function myorderFetchData() {
  const res = await fetch('../includes/orders_fetch.php', { credentials: 'same-origin' });
  const data = await res.json();
  if (!data.ok) throw new Error(data.error || 'Not logged in');
  return data.orders || [];
}

function myorderApplyData(rawOrders) {
  // Skip the re-render entirely when nothing changed — keeps the fast
  // polling loop cheap and prevents flicker on unchanged data
  const signature = JSON.stringify(rawOrders);
  const unchanged = myorderDataSignature !== null && myorderDataSignature === signature && orders.length > 0;
  myorderDataSignature = signature;

  orders = rawOrders.map(myorderMapOrder);
  if (unchanged) {
    // Nothing changed — keep the "Last updated" clock fresh only when the
    // tracked order has no recorded status-update time
    const lu = document.getElementById('lastUpdated');
    if (lu && !(CONFIG && CONFIG.ORDER.statusUpdatedAt)) {
      lu.textContent = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    return;
  }

  renderOrders(currentOrdersFilter);

  const tracked = myorderPickTracked();
  if (!tracked) {
    myorderRenderEmpty();
    return;
  }
  const previousStatus = CONFIG ? CONFIG.ORDER.rawStatus : null;
  CONFIG = myorderBuildConfig(tracked);
  renderTrackPage(CONFIG);
  renderTrackStatus(CONFIG);
  // Boot the map once; afterwards just refresh the UI on status changes
  if (!mapBooted) initMap();
  else if (previousStatus && previousStatus !== tracked.rawStatus) {
    const when = tracked.statusUpdatedAt
      ? new Date('2000-01-01T' + tracked.statusUpdatedAt).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
      : '';
    myorderToast('success', 'Order ' + tracked.id + ' is now ' + tracked.statusLabel + (when ? ' — updated at ' + when : '') + '.');
  }
}

async function myorderInit() {
  try {
    const rawOrders = await myorderFetchData();
    if (!rawOrders.length) {
      myorderRenderEmpty();
      return;
    }
    await myorderApplyData(rawOrders);

    // Fast polling keeps the tracking page in sync with the DB.
    // Skips ticks while the tab is hidden or a request is already running,
    // and the signature check inside myorderApplyData makes each tick cheap.
    async function myorderPollTick() {
      if (myorderInFlight || document.hidden) return;
      myorderInFlight = true;
      try {
        const fresh = await myorderFetchData();
        if (fresh.length) {
          myorderApplyData(fresh);
        } else if (orders.length) {
          myorderDataSignature = null;
          orders = [];
          myorderRenderEmpty();
        }
      } catch (err) { /* keep showing the last good data on transient errors */ }
      finally { myorderInFlight = false; }
    }
    setInterval(myorderPollTick, MYORDER_POLL_MS);

    // Instant refresh the moment the user returns to the tab or refocuses,
    // so a status the admin just changed is shown without waiting a full cycle
    document.addEventListener('visibilitychange', () => { if (!document.hidden) myorderPollTick(); });
    window.addEventListener('focus', myorderPollTick);
  } catch (err) {
    console.error(err);
    myorderRenderEmpty('Could not load your orders. Please refresh the page or login again.');
  }
}

// Wire the static Cancel Order button (no inline handlers needed)
const myorderCancelBtn = document.querySelector('#page-track .cancel-btn');
if (myorderCancelBtn) myorderCancelBtn.addEventListener('click', cancelTrackedOrder);

myorderInit();





/* =================================================================
   TABLE BOOKINGS PAGE (client/booking.php)
   All data is loaded at runtime from the backend endpoint
   includes/bookings_fetch.php (JSON, prepared statements, scoped to
   the logged-in user's email). CSS classes are namespaced with
   "bk-" so they can never clash with other client pages.
   ================================================================= */

const BK_STATUS_BUCKET = {
    'Pending':    'upcoming',
    'Confirmed':  'confirmed',
    'Checked-in': 'confirmed',
    'Completed':  'completed',
    'Cancelled':  'cancelled',
    'No-show':    'cancelled'
};

const bkTimerEl      = document.getElementById('timer');
const bkTimerSub     = document.getElementById('timerSub');
const bkTabs         = [...document.querySelectorAll('.bk-tab')];
const bkFilterWrap   = document.getElementById('filterWrap');
const bkFilterBtn    = document.getElementById('filterBtn');
const bkFilterLabel  = document.getElementById('filterLabel');
const bkFilterOptions = [...document.querySelectorAll('.bk-filter-option')];
const bkRowsBody     = document.getElementById('rows');
const bkShown        = document.getElementById('shown');
const viewModal      = document.getElementById('viewModal');
const editModal      = document.getElementById('editModal');

const bkFilterNames = {
    all: 'Filter',
    upcoming: 'Upcoming',
    confirmed: 'Confirmed',
    completed: 'Completed',
    cancelled: 'Cancelled'
};

let bkRows = [];
let bkBookings = [];
let bkCurrentFilter = 'all';
let bkServerNow = null;

/* ===== Escape helper for any backend-provided text ===== */
function bkEscape(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

/* ===== Grace Timer (from the Checked-in booking's grace_end_at) ===== */
let graceSeconds = null;

function renderGraceTimer() {
    if (!bkTimerEl) return; // page without the grace-timer card
    if (graceSeconds === null || graceSeconds <= 0) {
        bkTimerEl.textContent = '--:--';
        if (bkTimerSub) bkTimerSub.textContent = 'No active timer';
        return;
    }
    const m = String(Math.floor(graceSeconds / 60)).padStart(2, '0');
    const s = String(graceSeconds % 60).padStart(2, '0');
    bkTimerEl.textContent = `${m}:${s}`;
}

function computeGraceSeconds() {
    graceSeconds = null;
    if (bkTimerSub) bkTimerSub.textContent = 'No active timer';

    const parseDb = s => new Date(String(s).replace(' ', 'T'));
    const now = bkServerNow ? parseDb(bkServerNow) : new Date();

    for (const b of bkBookings) {
        if (b.status === 'Checked-in' && b.grace_end_at) {
            const diff = Math.floor((parseDb(b.grace_end_at) - now) / 1000);
            if (diff > 0) {
                graceSeconds = diff;
                if (bkTimerSub) {
                    bkTimerSub.textContent = 'Check-in grace for ' + (b.table_name || 'your table')
                        + (b.grace_deadline_display ? ' until ' + b.grace_deadline_display : '');
                }
                break;
            }
        }
    }
    renderGraceTimer();
}
setInterval(() => {
    if (graceSeconds !== null && graceSeconds > 0) {
        graceSeconds--;
        renderGraceTimer();
    }
}, 1000);

/* ===== Row rendering ===== */
const bkPeopleIcon = `<svg class="bk-people-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3"></circle><path d="M3.5 20c.7-3.4 2.6-5 5.5-5s4.8 1.6 5.5 5"></path><path d="M16.5 11.5a3 3 0 1 0 0-6"></path><path d="M15.2 15.2c2.6.3 4.3 1.9 5.3 4.8"></path></svg>`;
const bkCalIcon = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M8 3v4M16 3v4M3.5 10h17"/></svg>`;
const bkClockIcon = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2.5"/></svg>`;
const bkTableIcon = `<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 28h30"/><path d="M20 25h24"/><path d="M23 28v13"/><path d="M41 28v13"/><path d="M14 41h9M41 41h9"/><path d="M13 21h10M41 21h10"/><path d="M16 21v9M48 21v9"/><path d="M25 28v-7M39 28v-7"/><path d="M32 21v7"/></svg>`;

function bkGuestLabel(people) {
    return `${people} ${Number(people) === 1 ? 'Person' : 'People'}`;
}

function bkBuildRow(b) {
    const bucket = BK_STATUS_BUCKET[b.status] || 'upcoming';
    const pillClass = { upcoming: 'bk-up', confirmed: 'bk-ok', completed: 'bk-done', cancelled: 'bk-bad' }[bucket] || 'bk-up';
    const cancellable = b.status === 'Pending';

    const tr = document.createElement('tr');
    tr.dataset.status = bucket;
    tr.dataset.id = b.id;
    tr._booking = b;

    const tableLabel = bkEscape(b.table_name || 'Table');
    const areaLabel = b.capacity ? `${b.capacity} seats` : '—';
    const bookedOn = b.created_at ? bkEscape(b.created_at) : '';

    tr.innerHTML = `
        <td>
            <div class="bk-detail">
                <div class="bk-table-pic">${bkTableIcon}</div>
                <div>
                    <div class="bk-idline">
                        <span class="bk-id">#BK-${String(b.id).padStart(4, '0')}</span>
                        <span class="bk-pill ${pillClass}">${bkEscape(b.status)}</span>
                    </div>
                    <div class="bk-restaurant">Mero Bhoj Restaurant</div>
                    ${bookedOn ? `<div class="bk-booked">Booked on ${bookedOn}</div>` : ''}
                </div>
            </div>
        </td>
        <td>
            <div class="bk-icon-line">${bkCalIcon}<span class="bk-primary">${bkEscape(b.formatted_date || b.booking_date)}</span></div>
            <div class="bk-icon-line">${bkClockIcon}<span class="bk-primary">${bkEscape(b.formatted_time || b.booking_time)}</span></div>
        </td>
        <td>
            <div class="bk-guest">
                <div class="bk-guest-line bk-primary">${bkPeopleIcon}<span>${bkEscape(bkGuestLabel(b.people))}</span></div>
            </div>
        </td>
        <td>
            <div class="bk-table-name">♜ ${tableLabel}</div>
            <div class="bk-area">${bkEscape(areaLabel)}</div>
        </td>
        <td>
            <div class="bk-booking-status">
                <span class="bk-status-text bk-status-${bucket}">${bkEscape(b.status)}</span>
            </div>
        </td>
        <td>
            <div class="bk-money">—</div>
            <div class="bk-pay">Pay at restaurant</div>
        </td>
        <td>
            <div class="bk-actions">
                <button class="bk-view" type="button">View Details</button>
                <button class="bk-cancel" type="button" ${cancellable ? '' : 'disabled style="opacity:.55;cursor:not-allowed"'}>Cancel</button>
            </div>
        </td>`;
    return tr;
}

function bkRenderRows() {
    if (!bkRowsBody) return;
    bkRowsBody.innerHTML = '';
    for (const b of bkBookings) {
        bkRowsBody.appendChild(bkBuildRow(b));
    }
    bkRows = [...bkRowsBody.querySelectorAll('tr')];
}


/* ===== Filtering / counters ===== */
let bkLastViewedRow = null;

function bkVisibleRows(filter) {
    return bkRows.filter(row => filter === 'all' || row.dataset.status === filter);
}

function bkUpdateCounters() {
    const counts = { all: bkRows.length, upcoming: 0, confirmed: 0, completed: 0, cancelled: 0 };
    bkRows.forEach(row => {
        const k = row.dataset.status;
        if (k in counts) counts[k]++;
    });
    document.querySelectorAll('[data-count]').forEach(badge => {
        const key = badge.dataset.count;
        if (key in counts) badge.textContent = counts[key];
    });
}

function bkRenderEmptyState() {
    const existing = document.getElementById('emptyRow');
    if (existing) existing.remove();

    if (bkRowsBody && bkVisibleRows(bkCurrentFilter).length === 0) {
        const tr = document.createElement('tr');
        tr.id = 'emptyRow';
        tr.innerHTML = `<td colspan="7" class="bk-empty-bookings"><strong>No bookings found</strong><span>Bookings from your database will appear here.</span></td>`;
        bkRowsBody.appendChild(tr);
    }
}

function bkApplyFilter(filter) {
    bkCurrentFilter = filter;

    bkRows.forEach(row => {
        row.style.display = (filter === 'all' || row.dataset.status === filter) ? '' : 'none';
    });

    const visible = bkVisibleRows(filter);
    if (bkShown) bkShown.textContent = visible.length ? `1 to ${visible.length} of ${visible.length}` : '0 of 0';

    bkTabs.forEach(tab => tab.classList.toggle('active', tab.dataset.filter === filter));
    bkFilterOptions.forEach(option => option.classList.toggle('selected', option.dataset.filterChoice === filter));

    if (bkFilterLabel) bkFilterLabel.textContent = bkFilterNames[filter] || 'Filter';
    if (bkFilterWrap) bkFilterWrap.classList.remove('open');
    if (bkFilterBtn) bkFilterBtn.setAttribute('aria-expanded', 'false');

    bkRenderEmptyState();
}

/* ===== Upcoming booking card ===== */
function bkRenderUpcoming() {
    const textEl = document.getElementById('upcomingBookingText');
    const tableEl = document.getElementById('upcomingBookingTable');
    if (!textEl || !tableEl) return;

    const today = bkServerNow ? String(bkServerNow).slice(0, 10) : new Date().toISOString().slice(0, 10);
    const upcoming = bkBookings
        .filter(b => ['Pending', 'Confirmed', 'Checked-in'].includes(b.status) && b.booking_date >= today)
        .sort((a, b) => `${a.booking_date} ${a.booking_time}`.localeCompare(`${b.booking_date} ${b.booking_time}`))[0];

    if (upcoming) {
        textEl.textContent = `${upcoming.formatted_date || upcoming.booking_date} · ${upcoming.formatted_time || upcoming.booking_time}`;
        tableEl.textContent = `${upcoming.table_name || 'Table'} · ${bkGuestLabel(upcoming.people)}`;
    } else {
        textEl.textContent = 'No upcoming booking';
        tableEl.textContent = 'No table scheduled';
    }
}

/* ===== Modal system ===== */
function openModal(el) {
    if (!el) return;
    el.classList.add('show');
    el.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';
}
function closeModal(el) {
    if (!el) return;
    el.classList.remove('show');
    el.style.display = 'none';
    document.body.style.overflow = '';
    document.documentElement.style.overflow = '';
}

function bkFillViewModal(b) {
    document.getElementById('viewId').textContent = `#BK-${String(b.id).padStart(4, '0')}`;
    document.getElementById('viewRestaurant').textContent = 'Mero Bhoj Restaurant';
    document.getElementById('viewName').textContent = b.name || '—';
    document.getElementById('viewStatus').textContent = b.status || '—';
    document.getElementById('viewDate').textContent = b.formatted_date || b.booking_date || '—';
    document.getElementById('viewTime').textContent = b.formatted_time || b.booking_time || '—';
    document.getElementById('viewGuests').textContent = bkGuestLabel(b.people);
    document.getElementById('viewTable').textContent = b.table_name || '—';
    document.getElementById('viewAmount').textContent = 'Pay at restaurant';
    document.getElementById('viewBooked').textContent = b.created_at || '—';
}

function bkFillEditModal(b) {
    const set = (id, value) => { const el = document.getElementById(id); if (el) el.value = value ?? ''; };
    set('editName', b.name);
    set('editDate', b.booking_date);
    set('editTime', (b.booking_time || '').slice(0, 5));
    set('editMessage', b.message);

    const guests = document.getElementById('editGuests');
    const guestValue = bkGuestLabel(b.people);
    if (guests && [...guests.options].some(o => o.value === guestValue)) guests.value = guestValue;

    const table = document.getElementById('editTable');
    if (table && b.table_name && [...table.options].some(o => o.value === b.table_name)) {
        table.value = b.table_name;
    }
}


/* ===== Cancel booking (backend) ===== */
async function bkCancelBooking(row, button) {
    const b = row._booking;
    if (!b || button.disabled) return;

    if (!window.confirm('Are you sure you want to cancel this booking?')) return;

    button.disabled = true;
    button.textContent = 'Cancelling…';

    try {
        const res = await fetch('../includes/cancel_booking_customer.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: b.id })
        });
        const data = await res.json();

        if (!data.ok) throw new Error(data.message || 'Could not cancel the booking.');

        // Update the row in place
        row.dataset.status = 'cancelled';
        b.status = 'Cancelled';

        const pill = row.querySelector('.bk-pill');
        if (pill) {
            pill.textContent = 'Cancelled';
            pill.className = 'bk-pill bk-bad';
        }
        const status = row.querySelector('.bk-status-text');
        if (status) {
            status.textContent = 'Cancelled';
            status.className = 'bk-status-text bk-status-cancelled';
        }
        button.textContent = 'Cancelled';
        button.style.opacity = '.65';

        bkUpdateCounters();
        bkApplyFilter(bkCurrentFilter);
        if (window.showToast) {
            window.showToast('success', 'Booking cancelled successfully.');
        } else {
            alert('Booking cancelled successfully.');
        }
    } catch (err) {
        button.disabled = false;
        button.textContent = 'Cancel';
        if (window.showToast) {
            window.showToast('error', err.message || 'Could not cancel the booking.');
        } else {
            alert(err.message || 'Could not cancel the booking.');
        }
    }
}

/* ===== Global click handling (modals + row actions) ===== */
document.addEventListener('click', function (e) {
    const view = e.target.closest('.bk-view');
    const cancel = e.target.closest('.bk-cancel');
    const close = e.target.closest('[data-close]');
    const edit = e.target.closest('#viewEditBtn');
    const save = e.target.closest('#saveEdit');

    if (view) {
        e.preventDefault();
        const row = view.closest('tr');
        if (!row || !row._booking) return;
        bkLastViewedRow = row;
        bkFillViewModal(row._booking);
        openModal(viewModal);
        return;
    }

    if (cancel) {
        e.preventDefault();
        const row = cancel.closest('tr');
        if (row && row._booking) bkCancelBooking(row, cancel);
        return;
    }

    if (close) {
        e.preventDefault();
        closeModal(document.getElementById(close.dataset.close));
        return;
    }

    if (edit) {
        e.preventDefault();
        if (!bkLastViewedRow || !bkLastViewedRow._booking) return;
        bkFillEditModal(bkLastViewedRow._booking);
        closeModal(viewModal);
        openModal(editModal);
        return;
    }


    if (save) {
        e.preventDefault();
        if (!bkLastViewedRow || !bkLastViewedRow._booking) return;
        const b = bkLastViewedRow._booking;

        const nameEl = document.getElementById('editName');
        const guestsEl = document.getElementById('editGuests');
        const newName = nameEl ? nameEl.value.trim() : '';
        const newGuests = guestsEl ? guestsEl.value : '';

        if (newName) b.name = newName;
        const guestMatch = newGuests.match(/^(\d+)/);
        if (guestMatch) b.people = parseInt(guestMatch[1], 10);

        // Reflect the change in the row (guests cell)
        const guestEl = bkLastViewedRow.querySelector('td:nth-child(3) .bk-primary');
        if (guestEl) guestEl.innerHTML = `${bkPeopleIcon}<span>${bkEscape(bkGuestLabel(b.people))}</span>`;

        const msg = document.getElementById('saveMsg');
        if (msg) {
            msg.classList.add('show');
            setTimeout(() => {
                msg.classList.remove('show');
                closeModal(editModal);
            }, 900);
        } else {
            closeModal(editModal);
        }
        return;
    }

    if (e.target === viewModal) closeModal(viewModal);
    if (e.target === editModal) closeModal(editModal);
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeModal(viewModal);
        closeModal(editModal);
        if (bkFilterWrap) bkFilterWrap.classList.remove('open');
        if (bkFilterBtn) bkFilterBtn.setAttribute('aria-expanded', 'false');
    }
});

const editForm = document.getElementById('editForm');
if (editForm) editForm.addEventListener('submit', e => e.preventDefault());

// Hide the dialogs up-front (pages without modals are simply skipped)
if (viewModal) viewModal.style.display = 'none';
if (editModal) editModal.style.display = 'none';

/* ===== Filter UI wiring ===== */
bkTabs.forEach(tab => tab.addEventListener('click', () => bkApplyFilter(tab.dataset.filter)));

if (bkFilterBtn) {
    bkFilterBtn.addEventListener('click', e => {
        e.stopPropagation();
        const isOpen = bkFilterWrap.classList.toggle('open');
        bkFilterBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
}
bkFilterOptions.forEach(option => {
    option.addEventListener('click', e => {
        e.stopPropagation();
        bkApplyFilter(option.dataset.filterChoice);
    });
});
document.addEventListener('click', e => {
    if (bkFilterWrap && !bkFilterWrap.contains(e.target)) {
        bkFilterWrap.classList.remove('open');
        if (bkFilterBtn) bkFilterBtn.setAttribute('aria-expanded', 'false');
    }
});

/* ===== Backend data loading ===== */
function bkRenderLoadError(message) {
    if (!bkRowsBody) return;
    bkRowsBody.innerHTML = `<tr id="emptyRow"><td colspan="7" class="bk-empty-bookings"><strong>${bkEscape(message)}</strong><span>Try refreshing the page or login again.</span></td></tr>`;
    bkRows = [];
}

async function bkLoadBookings() {
    if (!bkRowsBody) return; // not on the bookings page

    try {
        const res = await fetch('../includes/bookings_fetch.php', { credentials: 'same-origin' });
        const data = await res.json();

        if (!data.ok) {
            bkRenderLoadError(data.error || 'Could not load your bookings.');
            return;
        }

        bkBookings = data.bookings || [];
        bkServerNow = data.server_now || null;

        bkRenderRows();
        bkUpdateCounters();
        bkApplyFilter(bkCurrentFilter);
        bkRenderUpcoming();
        computeGraceSeconds();
    } catch (err) {
        console.error(err);
        bkRenderLoadError('Could not load your bookings. Please refresh the page.');
    }
}

bkLoadBookings();


