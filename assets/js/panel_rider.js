/**
 * panel_rider.js — controller for the rider panel.
 *
 * Loaded on every /rider page by _foot.php. Each module is a no-op unless its
 * root element exists, so one file serves the dashboard, the deliveries list and
 * the navigate map:
 *   1. GPS pill (#riderGpsToggle, topbar)      → throttled pushes to api/location_push.php
 *   2. Job lists (#riderDashboard / #riderDeliveriesPage) → api/deliveries.php + claim/release/pickup/complete
 *   3. Navigate map (#riderNavigate)           → Leaflet + OSRM route, live self-marker
 *
 * Nothing sensitive lives here: the handover code is never sent to a rider, and
 * every ownership decision is re-made server-side from the session.
 */
(function () {
  'use strict';

  /* ============================ shared helpers ============================ */

  var CSRF = window.PANEL_CSRF || '';

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function toast(msg, type) {
    if (window.showToast) window.showToast(msg, type || 'info');
  }

  function getJSON(url) {
    return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
  }

  function postJSON(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': CSRF,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(Object.assign({ csrf: CSRF }, body || {}))
    }).then(function (r) { return r.json().catch(function () { return { success: false, message: 'Server error' }; }); });
  }

  /** Great-circle distance in kilometres. */
  function haversine(a, b) {
    var R = 6371, toRad = function (d) { return d * Math.PI / 180; };
    var dLat = toRad(b.lat - a.lat), dLng = toRad(b.lng - a.lng);
    var s = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(s)));
  }

  function money(n) {
    return 'Rs. ' + Math.round(Number(n) || 0).toLocaleString('en-US');
  }

  function ago(seconds) {
    var s = Math.max(0, Math.floor(Number(seconds) || 0));
    if (s < 60) return s + 's';
    if (s < 3600) return Math.floor(s / 60) + 'm';
    return Math.floor(s / 3600) + 'h ' + Math.floor((s % 3600) / 60) + 'm';
  }

  /* ============================ 1. GPS broadcast =========================== */

  var GPS_KEY      = 'mkj_rider_gps';   // 'on' | 'off' (off by default)
  var PUSH_EVERY_MS = 10000;            // at most one push per 10 s …
  var PUSH_EVERY_M  = 25;               // … or as soon as we move 25 m
  var watchId = null, lastPushAt = 0, lastPushPos = null, lastFix = null;

  var gpsToggle = document.getElementById('riderGpsToggle');

  function gpsOn() { return localStorage.getItem(GPS_KEY) === 'on'; }

  function paintGps(stateText) {
    if (!gpsToggle) return;
    var on    = gpsOn();
    var icon  = gpsToggle.querySelector('.material-symbols-sharp');
    var label = gpsToggle.querySelector('.rider-gps-label');
    gpsToggle.classList.toggle('is-live', on);
    gpsToggle.classList.toggle('is-off', !on);
    gpsToggle.setAttribute('aria-pressed', on ? 'true' : 'false');
    if (icon)  icon.textContent  = on ? 'my_location' : 'location_off';
    if (label) label.textContent = stateText || (on ? 'GPS on' : 'GPS off');
  }

  function setFixReadout(text) {
    var el = document.getElementById('navFix');
    if (el) el.textContent = text;
  }

  function pushFix(pos) {
    var here = { lat: pos.coords.latitude, lng: pos.coords.longitude };
    lastFix = here;

    var now     = Date.now();
    var moved   = lastPushPos ? haversine(lastPushPos, here) * 1000 : Infinity;
    var elapsed = now - lastPushAt;
    if (elapsed < PUSH_EVERY_MS && moved < PUSH_EVERY_M) return;

    lastPushAt  = now;
    lastPushPos = here;

    postJSON('api/location_push.php', {
      lat: here.lat,
      lng: here.lng,
      accuracy: pos.coords.accuracy,
      heading: (pos.coords.heading == null || isNaN(pos.coords.heading)) ? null : pos.coords.heading,
      speed_kmh: (pos.coords.speed == null || isNaN(pos.coords.speed)) ? null : pos.coords.speed * 3.6
    }).then(function (d) {
      if (d && d.success) {
        paintGps('Sharing');
        setFixReadout('Live');
      } else if (d && d.inactive) {
        // Nothing out for delivery — keep the watch alive but say so.
        paintGps('Standby');
        setFixReadout('Standby');
      }
    }).catch(function () { /* offline — the next fix retries */ });
  }

  function onGpsError(err) {
    if (err && err.code === 1) {           // permission denied
      localStorage.setItem(GPS_KEY, 'off');
      stopGps();
      toast('Location permission denied — the customer will see the estimated position instead.', 'error');
    } else {
      setFixReadout('No fix');
    }
  }

  function startGps() {
    if (watchId !== null) return;
    if (!('geolocation' in navigator)) {
      toast('This device cannot share a location.', 'error');
      localStorage.setItem(GPS_KEY, 'off');
      paintGps();
      return;
    }
    watchId = navigator.geolocation.watchPosition(function (pos) {
      pushFix(pos);
      if (window.__riderOnFix) window.__riderOnFix({ lat: pos.coords.latitude, lng: pos.coords.longitude });
    }, onGpsError, { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 });
    paintGps('Locating…');
    setFixReadout('Locating…');
  }

  function stopGps() {
    if (watchId !== null) {
      navigator.geolocation.clearWatch(watchId);
      watchId = null;
    }
    lastPushPos = null;
    paintGps();
    setFixReadout('Off');
  }

  if (gpsToggle) {
    gpsToggle.addEventListener('click', function () {
      var turningOn = !gpsOn();
      localStorage.setItem(GPS_KEY, turningOn ? 'on' : 'off');
      paintGps();
      // The browser permission prompt only ever fires from this explicit tap.
      if (turningOn) startGps(); else stopGps();
    });
    paintGps();
    if (gpsOn()) startGps();
  }

  /* ============================ 2. Job rendering =========================== */

  var STATE_ICON = {
    unassigned: 'pin_drop',
    assigned:   'inventory_2',
    pickedup:   'two_wheeler',
    completed:  'task_alt',
    cancelled:  'cancel'
  };

  function line(icon, html, cls) {
    return '<p class="rider-line' + (cls ? ' ' + cls : '') + '">' +
             '<span class="material-symbols-sharp">' + icon + '</span><span>' + html + '</span>' +
           '</p>';
  }

  function itemsText(items) {
    if (!items || !items.length) return 'No items';
    return items.map(function (i) { return esc(i.name) + ' ×' + (i.quantity || 1); }).join(', ');
  }

  /** Buttons/OTP form for a job, derived from its server-computed state. */
  function actionsHtml(job, opts) {
    var o = esc(job.order_number);
    var onNavigate = opts && opts.navigate;

    if (job.state === 'unassigned') {
      return '<button type="button" class="qrm-btn qrm-btn-primary rider-big-btn" data-act="claim" data-order="' + o + '">' +
               '<span class="material-symbols-sharp">back_hand</span> Claim delivery</button>';
    }

    if (job.state === 'assigned') {
      return '<button type="button" class="qrm-btn qrm-btn-success rider-big-btn" data-act="pickup" data-order="' + o + '">' +
               '<span class="material-symbols-sharp">shopping_bag</span> I picked it up</button>' +
             (onNavigate ? '' :
               '<a class="qrm-btn qrm-btn-secondary rider-big-btn" href="navigate.php?order=' + encodeURIComponent(job.order_number) + '">' +
               '<span class="material-symbols-sharp">map</span> Navigate</a>') +
             '<button type="button" class="qrm-btn qrm-btn-secondary rider-small-btn" data-act="release" data-order="' + o + '">' +
               '<span class="material-symbols-sharp">undo</span> Release</button>';
    }

    if (job.state === 'pickedup') {
      return '<div class="rider-otp" data-order="' + o + '">' +
               '<label>Ask the customer for their 4-digit code</label>' +
               '<div class="rider-otp-boxes">' +
                 '<input class="rider-otp-box" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Digit 1">' +
                 '<input class="rider-otp-box" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Digit 2">' +
                 '<input class="rider-otp-box" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Digit 3">' +
                 '<input class="rider-otp-box" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" aria-label="Digit 4">' +
               '</div>' +
               '<button type="button" class="qrm-btn qrm-btn-success rider-big-btn" data-act="complete" data-order="' + o + '">' +
                 '<span class="material-symbols-sharp">check_circle</span> Confirm delivery</button>' +
             '</div>' +
             (onNavigate ? '' :
               '<a class="qrm-btn qrm-btn-secondary rider-big-btn" href="navigate.php?order=' + encodeURIComponent(job.order_number) + '">' +
               '<span class="material-symbols-sharp">map</span> Navigate</a>');
    }

    return '';
  }

  function jobCard(job) {
    var mine = !!job.is_mine;
    var html = '<article class="rider-job-card' + (mine ? ' is-mine' : '') + '" data-order="' + esc(job.order_number) + '">' +
      '<div class="rider-job-head">' +
        '<div>' +
          '<strong>' + esc(job.order_number) + '</strong>' +
          '<span class="panel-status st-' + esc(job.state) + '">' +
            '<span class="material-symbols-sharp">' + (STATE_ICON[job.state] || 'local_shipping') + '</span> ' +
            esc(job.state_label) +
          '</span>' +
        '</div>' +
        '<span class="rider-fee">' + money(job.delivery_fee) + '</span>' +
      '</div>' +
      '<div class="rider-job-body">' +
        line('person', esc(job.customer_name)) +
        (job.customer_phone ? line('call', '<a href="tel:' + esc(job.customer_phone) + '">' + esc(job.customer_phone) + '</a>') : '') +
        line('home_pin', esc(job.address || 'No address on file')) +
        (job.instructions ? line('sticky_note_2', esc(job.instructions), 'rider-note') : '') +
        line('shopping_bag', itemsText(job.items)) +
        line('payments', money(job.order_total) + ' · ' + esc(job.payment_method || 'Cash') +
                         ' (' + esc(job.payment_status || 'Unpaid') + ')') +
        line('schedule', 'Ordered ' + ago(job.age_seconds) + ' ago') +
      '</div>' +
      '<div class="rider-actions">' + actionsHtml(job, {}) + '</div>' +
    '</article>';
    return html;
  }

  /** Re-render only when the meaningful shape changed, so a half-typed OTP survives polling. */
  function renderList(el, jobs, emptyText) {
    if (!el) return;
    var sig = JSON.stringify((jobs || []).map(function (j) {
      return [j.order_number, j.state, j.customer_phone ? 1 : 0];
    }));
    if (el.dataset.sig === sig) return;
    el.dataset.sig = sig;
    el.innerHTML = (jobs && jobs.length)
      ? jobs.map(jobCard).join('')
      : '<div class="rider-empty">' + esc(emptyText) + '</div>';
  }

  function paintStats(stats) {
    if (!stats) return;
    var map = {
      pool:   stats.pool,
      active: stats.active,
      done:   stats.completed_today,
      earned: money(stats.earned_today)
    };
    Object.keys(map).forEach(function (k) {
      document.querySelectorAll('[data-stat="' + k + '"]').forEach(function (el) {
        el.textContent = map[k];
      });
    });
    // Keep the sidebar pool badge honest without a page reload.
    var badge = document.querySelector('.sidebar-badge');
    if (badge && typeof stats.pool === 'number') {
      badge.textContent = stats.pool;
      badge.style.display = stats.pool > 0 ? '' : 'none';
    }
  }

  /* ---- dashboard ---- */
  var dash = document.getElementById('riderDashboard');
  if (dash) {
    var mineList = document.getElementById('mineList');
    var poolList = document.getElementById('poolList');

    window.__riderRefresh = function () {
      return getJSON('api/deliveries.php?scope=dashboard').then(function (d) {
        if (!d || !d.success) return;
        paintStats(d.stats);
        renderList(mineList, d.mine, 'Nothing assigned to you right now.');
        renderList(poolList, d.pool, 'No deliveries waiting — new ones appear here automatically.');
      }).catch(function () { /* keep the last good view */ });
    };

    window.__riderRefresh();
    setInterval(window.__riderRefresh, parseInt(dash.dataset.poll, 10) || 5000);
  }

  /* ---- my deliveries ---- */
  var delivPage = document.getElementById('riderDeliveriesPage');
  if (delivPage) {
    var activeList  = document.getElementById('activeList');
    var historyBody = document.getElementById('historyBody');

    function renderHistory(jobs) {
      if (!historyBody) return;
      if (!jobs || !jobs.length) {
        historyBody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:1.5rem;">No completed deliveries yet.</td></tr>';
        return;
      }
      historyBody.innerHTML = jobs.map(function (j) {
        var done = j.completed_at ? new Date(String(j.completed_at).replace(' ', 'T')) : null;
        return '<tr>' +
          '<td><strong>' + esc(j.order_number) + '</strong></td>' +
          '<td>' + esc(j.customer_name) + '</td>' +
          '<td>' + esc(j.address || '—') + '</td>' +
          '<td><span class="panel-status st-' + esc(j.state) + '">' + esc(j.state_label) + '</span></td>' +
          '<td>' + (done && !isNaN(done.getTime()) ? esc(done.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })) : '—') + '</td>' +
          '<td style="text-align:right;">' + money(j.delivery_fee) + '</td>' +
        '</tr>';
      }).join('');
    }

    window.__riderRefresh = function () {
      return Promise.all([
        getJSON('api/deliveries.php?scope=mine'),
        getJSON('api/deliveries.php?scope=history')
      ]).then(function (res) {
        if (res[0] && res[0].success) {
          paintStats(res[0].stats);
          renderList(activeList, res[0].deliveries, 'Nothing in progress. Claim a job from the dashboard.');
        }
        if (res[1] && res[1].success) renderHistory(res[1].deliveries);
      }).catch(function () { /* keep the last good view */ });
    };

    window.__riderRefresh();
    setInterval(window.__riderRefresh, parseInt(delivPage.dataset.poll, 10) || 8000);
  }

  /* ---- OTP boxes: auto-advance, digits only ---- */
  document.addEventListener('input', function (e) {
    var box = e.target.closest ? e.target.closest('.rider-otp-box') : null;
    if (!box) return;
    box.value = box.value.replace(/\D/g, '').slice(0, 1);
    if (box.value) {
      var next = box.nextElementSibling;
      if (next && next.classList.contains('rider-otp-box')) next.focus();
    }
  });
  document.addEventListener('keydown', function (e) {
    var box = e.target.closest ? e.target.closest('.rider-otp-box') : null;
    if (!box) return;
    if (e.key === 'Backspace' && !box.value) {
      var prev = box.previousElementSibling;
      if (prev && prev.classList.contains('rider-otp-box')) { prev.focus(); prev.value = ''; e.preventDefault(); }
    }
  });

  /* ---- actions (delegated, works on every rider surface) ---- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-act]') : null;
    if (!btn) return;

    var act   = btn.getAttribute('data-act');
    var order = btn.getAttribute('data-order');
    if (!order) return;

    var body = { order_number: order };
    var url;

    if (act === 'claim')        url = 'api/claim.php';
    else if (act === 'release') url = 'api/release.php';
    else if (act === 'pickup')  url = 'api/pickup.php';
    else if (act === 'complete') {
      url = 'api/complete.php';
      var wrap = btn.closest('.rider-otp');
      var code = wrap
        ? Array.prototype.map.call(wrap.querySelectorAll('.rider-otp-box'), function (i) { return i.value.trim(); }).join('')
        : '';
      if (code.length !== 4) {
        toast('Enter the 4-digit code the customer is showing you.', 'error');
        return;
      }
      body.otp = code;
    } else {
      return;
    }

    if (act === 'release' && !window.confirm('Return this delivery to the pool?')) return;

    btn.disabled = true;
    postJSON(url, body).then(function (d) {
      if (d && d.success) {
        toast(d.message || 'Done', 'success');
        if (act === 'complete' && document.getElementById('riderNavigate')) {
          window.location.href = 'dashboard.php';
          return;
        }
        if (window.__riderRefresh) window.__riderRefresh();
        if (window.__riderNavRefresh) window.__riderNavRefresh();
      } else {
        toast((d && d.message) || 'Action failed', 'error');
        if (window.__riderRefresh) window.__riderRefresh();
        if (window.__riderNavRefresh) window.__riderNavRefresh();
      }
    }).catch(function () {
      toast('Network error — try again.', 'error');
    }).finally(function () {
      btn.disabled = false;
    });
  });

  /* ============================ 3. Navigate map ============================ */

  var nav = document.getElementById('riderNavigate');
  if (nav && window.L) {
    var GEOCODE_API = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=';
    var ROUTE_API   = 'https://router.project-osrm.org/route/v1/driving/';
    var CENTER      = [28.2105, 83.9565];          // Pokhara — same centre the customer map uses
    var ORDER       = nav.dataset.order;
    var ADDRESS     = nav.dataset.address || '';
    var CACHE_KEY   = 'mkj_dest_' + ORDER;

    var map = L.map('riderMap', { zoomControl: true }).setView(CENTER, 14);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
      maxZoom: 20,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
    }).addTo(map);

    var restMarker = L.marker(CENTER, { title: 'Mero Bhoj' }).addTo(map).bindPopup('Mero Bhoj');
    var destMarker = null, meMarker = null, routeLine = null;
    var lastRouteAt = 0;

    function setReadout(km, mins) {
      var d = document.getElementById('navDist');
      var e = document.getElementById('navEta');
      if (d) d.textContent = (km == null) ? '—' : (km < 1 ? Math.round(km * 1000) + ' m' : km.toFixed(1) + ' km');
      if (e) e.textContent = (mins == null) ? '—' : Math.max(1, Math.round(mins)) + ' min';
    }

    function drawRoute(from, to) {
      var now = Date.now();
      if (now - lastRouteAt < 20000) return;      // OSRM is a public demo server — be gentle
      lastRouteAt = now;

      fetch(ROUTE_API + from.lng + ',' + from.lat + ';' + to.lng + ',' + to.lat + '?overview=full&geometries=geojson')
        .then(function (r) { return r.json(); })
        .then(function (d) {
          var route = d && d.routes && d.routes[0];
          if (!route) return;
          var pts = route.geometry.coordinates.map(function (c) { return [c[1], c[0]]; });
          if (routeLine) map.removeLayer(routeLine);
          routeLine = L.polyline(pts, { color: '#f05a22', weight: 5, opacity: 0.85 }).addTo(map);
          setReadout(route.distance / 1000, route.duration / 60);
        })
        .catch(function () { /* fall back to straight-line below */ });
    }

    function placeDest(lat, lng) {
      var to = { lat: lat, lng: lng };
      if (destMarker) map.removeLayer(destMarker);
      destMarker = L.marker([lat, lng], { title: 'Drop-off' }).addTo(map).bindPopup('Drop-off');
      try { localStorage.setItem(CACHE_KEY, JSON.stringify(to)); } catch (err) {}
      var group = [restMarker, destMarker];
      if (meMarker) group.push(meMarker);
      map.fitBounds(L.featureGroup(group).getBounds().pad(0.25));
      drawRoute(meMarker ? meMarker.getLatLng() : { lat: CENTER[0], lng: CENTER[1] }, to);
    }

    // Destination: DB coords → cached geocode → Nominatim lookup of the address.
    var dLat = parseFloat(nav.dataset.destLat), dLng = parseFloat(nav.dataset.destLng);
    if (!isNaN(dLat) && !isNaN(dLng)) {
      placeDest(dLat, dLng);
    } else {
      var cached = null;
      try { cached = JSON.parse(localStorage.getItem(CACHE_KEY) || 'null'); } catch (err) {}
      if (cached && typeof cached.lat === 'number') {
        placeDest(cached.lat, cached.lng);
      } else if (ADDRESS) {
        fetch(GEOCODE_API + encodeURIComponent(ADDRESS + ', Pokhara, Nepal'), { headers: { 'Accept-Language': 'en' } })
          .then(function (r) { return r.json(); })
          .then(function (d) {
            if (d && d.length) placeDest(parseFloat(d[0].lat), parseFloat(d[0].lon));
            else toast('Could not pin that address — use the customer\'s phone number.', 'info');
          })
          .catch(function () { /* map still usable without the pin */ });
      }
    }

    // Live self-marker, fed by the GPS module above.
    window.__riderOnFix = function (pos) {
      if (!meMarker) {
        meMarker = L.circleMarker([pos.lat, pos.lng], {
          radius: 8, color: '#fff', weight: 3, fillColor: '#f05a22', fillOpacity: 1
        }).addTo(map).bindPopup('You');
        map.setView([pos.lat, pos.lng], 15);
      } else {
        meMarker.setLatLng([pos.lat, pos.lng]);
      }
      if (destMarker) {
        var to = destMarker.getLatLng();
        drawRoute(pos, { lat: to.lat, lng: to.lng });
        if (!routeLine) setReadout(haversine(pos, { lat: to.lat, lng: to.lng }), null);
      }
    };
    if (lastFix) window.__riderOnFix(lastFix);

    // Action panel for this one order.
    var navActions = document.getElementById('navActions');
    window.__riderNavRefresh = function () {
      return getJSON('api/deliveries.php?scope=mine').then(function (d) {
        if (!d || !d.success || !navActions) return;
        var job = (d.deliveries || []).filter(function (j) { return j.order_number === ORDER; })[0];
        if (!job) { window.location.href = 'deliveries.php'; return; }
        paintStats(d.stats);
        var sig = job.state;
        if (navActions.dataset.sig === sig) return;
        navActions.dataset.sig = sig;
        navActions.innerHTML = actionsHtml(job, { navigate: true });
      }).catch(function () { /* keep the last good view */ });
    };
    window.__riderNavRefresh();
    setInterval(window.__riderNavRefresh, 8000);

    // Leaflet needs a nudge once the panel layout settles.
    setTimeout(function () { map.invalidateSize(); }, 300);
  }
})();
