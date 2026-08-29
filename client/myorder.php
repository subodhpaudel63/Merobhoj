<?php
ob_start();
session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Check if user is logged in
$currentUser = getUserFromCookie();

// If user is not logged in, redirect to login
if (!$currentUser) {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Please login to view your orders.'];
    header('Location: /Merobhoj/login.php?action=view_orders');
    exit;
}

// Show eSewa payment success message
if (($_GET['payment'] ?? '') === 'esewa_success') {
    $_SESSION['msg'] = ['type' => 'success', 'text' => 'eSewa payment successful! Your order has been confirmed.'];
}

try {
  $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  ]);
} catch (PDOException $e) {
  die("Database connection failed: " . $e->getMessage());
}

// Logged-in user and profile image (from secure cookies)
$profileImg = 'assets/images/profile.jpg';
if (isset($_COOKIE['user_img'])) {
  $dec = decrypt($_COOKIE['user_img'], SECRET_KEY);
  if ($dec && is_string($dec)) {
    $candidate = ltrim($dec, '/');
    if (file_exists(__DIR__ . '/../' . $candidate)) {
      $profileImg = $candidate;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
      integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
      crossorigin="anonymous"
      referrerpolicy="no-referrer"
    />
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" type="text/css" href="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css"/>
    <?php require_once __DIR__ . '/../config/bootstrap.php'; ?>
    <link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Include toast styles -->
    <link rel="stylesheet" href="<?php echo asset('css/toast_styles.css'); ?>" />
    <style>
        .status-badge { 
            border-radius: 999px; 
            padding: .25rem .6rem; 
            font-weight: 600; 
            font-size: 0.85rem; 
            display: inline-block;
            margin-bottom: 5px;
        }
        .status-pending {
    background: #FEF3C7;
    color: #B45309;
}

.status-confirmed {
    background: #DBEAFE;
    color: #1D4ED8;
}

.status-preparing {
    background: #FFEDD5;
    color: #C2410C;
}

.status-ready {
    background: #EDE9FE;
    color: #6D28D9;
}

.status-delivering {
    background: #CFFAFE;
    color: #0E7490;
}

.status-completed {
    background: #DCFCE7;
    color: #15803D;
}

.status-cancelled {
    background: #FEE2E2;
    color: #B91C1C;
}
        
        .order-date {
            font-size: 0.75rem;
            margin-top: 3px;
            display: block;
            clear: both;
        }
        
        .status-container {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        
        /* Order status timeline styling */
        .order-timeline {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            position: relative;
        }
        .order-timeline::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        .timeline-step {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #e0e0e0;
            z-index: 2;
            position: relative;
        }
        .timeline-step.active {
            background: #0d47a1;
        }
        .timeline-step.completed {
            background: #4caf50;
        }
        
        /* Status update animation */
        .status-updated {
            animation: statusChange 2s ease-in-out;
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.5);
        }
        
        @keyframes statusChange {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); box-shadow: 0 0 15px rgba(0, 123, 255, 0.8); }
            100% { transform: scale(1); }
        }
        
        /* Add padding to prevent content from being hidden under navbar */
        .main-content {
          padding-top: 100px;
        }
        
        @media (max-width: 991px) {
          .main-content {
            padding-top: 80px;
          }
        }

        /* Booking specific styles */
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-checked-in { background: #e0f2fe; color: #0284c7; }
        .status-no-show { background: #f3f4f6; color: #4b5563; }
        
        .grace-timer { font-size: 13px; font-weight: 500; }
        .grace-warning { color: #d97706; }
        .grace-danger { color: #dc2626; }
        .grace-expired { color: #ef4444; font-weight: bold; }
    </style>
    <script>
        const POLL_MS = 3000; // Poll every 3 seconds for faster updates
        let previousOrders = {};
        
        async function fetchOrders() {
            try {
                const res = await fetch('../includes/orders_fetch.php', { credentials: 'same-origin' });
                const data = await res.json();
                if (!data.ok) {
                    document.getElementById('orders-body').innerHTML = `<tr><td colspan="4" class="text-center text-muted">Please login to view your orders.</td></tr>`;
                    return;
                }
                const tbody = document.getElementById('orders-body');
                if (!data.orders || data.orders.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No orders found.</td></tr>`;
                    previousOrders = {};
                    return;
                }
                
                // Check for status changes and create visual feedback
                const currentOrders = {};
                data.orders.forEach(order => {
                    currentOrders[order.order_number || order.order_id] = order.status;
                });
                
                tbody.innerHTML = data.orders.map(o => {
                    const orderRef = o.order_number || ('ORD-' + String(o.order_id).padStart(4, '0'));
                    const previousStatus = previousOrders[orderRef];
                    const statusChanged = previousStatus && previousStatus !== o.status;
                    const statusClass = `status-${o.status.toLowerCase()}`;
                    
                    const itemsHtml = o.items.map(it => 
                        `<div class="d-flex justify-content-between align-items-center py-1" style="border-bottom: 1px dashed #eee;">
                            <span>${it.menu_name} <span class="text-muted">× ${it.quantity}</span></span>    
                            <span class="text-muted" style="font-size:0.85rem;">Rs. ${Number(it.total_price).toFixed(2)}</span>
                        </div>`
                    ).join('');
                    
                    let actionHtml = '';
                    if (o.status === 'Delivering') {
                        actionHtml = `<small class="text-danger mt-1 fw-semibold" style="font-size:0.78rem; display:block;">Your order is already on the way and can no longer be cancelled.</small>`;
                    } else if (['Pending', 'Confirmed', 'Preparing', 'Ready'].includes(o.status)) {
                        actionHtml = `<button class="btn btn-sm btn-danger mt-2 fw-bold" style="font-size: 0.75rem; padding: 2px 8px; border-radius: 4px;" onclick="cancelOrder('${orderRef}')">Cancel Order</button>`;
                    }
                    
                    return `
                    <tr>
                        <td style="font-weight: 700; color: #0d47a1;">${orderRef}</td>
                        <td style="min-width: 250px;">${itemsHtml}</td>
                        <td style="font-weight: 700; font-size: 1.05rem;">Rs. ${Number(o.total_amount).toFixed(2)}</td>
                        <td>
                            <div class="status-container">
                                <span class="status-badge ${statusClass}" data-status="${o.status}" data-order-id="${o.order_id}" ${statusChanged ? 'data-status-changed="true"' : ''}>${o.status}</span>
                                <span class="order-date text-muted">Ordered: ${new Date(o.order_date).toLocaleDateString()}</span>
                                ${actionHtml}
                            </div>
                        </td>
                    </tr>
                `;
                }).join('');
                
                // Update previous orders for next comparison
                previousOrders = currentOrders;
                
                // Add visual feedback for status changes
                document.querySelectorAll('[data-status-changed="true"]').forEach(badge => {
                    badge.classList.add('status-updated');
                    setTimeout(() => {
                        badge.classList.remove('status-updated');
                    }, 2000);
                });
                
                // Status classes are now handled during HTML generation
                // Only do cleanup if needed
                document.querySelectorAll('[data-status]').forEach(badge => {
                    const status = badge.getAttribute('data-status');
                    const statusLower = status.toLowerCase();
                    
                    // Ensure status badge class is present
                    if (!badge.classList.contains('status-badge')) {
                        badge.classList.add('status-badge');
                    }
                    
                    // Update status class if needed
                    const expectedClass = `status-${statusLower}`;
                    if (!badge.classList.contains(expectedClass)) {
                        // Remove old status classes
                        badge.className = badge.className.replace(/status-\w+/g, '');
                        // Add correct status class
                        badge.classList.add('status-badge', expectedClass);
                    }
                });
            } catch (e) {
                console.error(e);
            }
        }

        async function cancelOrder(orderRef) {
            if (confirm('Are you sure you want to cancel this order?')) {
                try {
                    const response = await fetch('../includes/order_cancel_customer.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ order_number: orderRef })
                    });
                    const result = await response.json();
                    if (result.success) {
                        alert('Order cancelled successfully.');
                        fetchOrders();
                    } else {
                        alert(result.message || 'Failed to cancel order.');
                    }
                } catch (e) {
                    console.error(e);
                    alert('An error occurred while cancelling the order.');
                }
            }
        }
    </script>
</head>
<body>
    

    <div class="loader">
      <i class="fas fa-utensils loader-icone"></i>
      <p>Mero Bhoj</p>
      <div class="loader-ellipses">
        <span></span>
        <span></span>
        <span></span>
      </div>
    </div>
    
    <header>
      <div class="container header my-3 d-none d-lg-flex">
        <div class="logo">
          <a href="./index.php">
            <i class="fa fa-utensils me-3"></i>
            <h1 class="mb-0">Mero Bhoj</h1>
          </a>
        </div>
        <div class="menus">
          <ul class="d-flex mb-0">
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./index.php"
                >Home</a
              >
            </li>
            <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./aboutus.php"
                  >About</a
                >
            </li>
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./menu.php"
                >Menu</a
              >
            </li>
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./myorder.php"
                >My Order</a> 
            </li>
            <?php if (!$currentUser): ?>
              <li class="list-unstyled py-2">
                <a class="btn btn-gradient" href="<?php echo url('/login.php'); ?>">Login</a>
              </li>
            <?php endif; ?>
            <li class="list-unstyled py-2">
              <a class="text-dark text-decoration-none text-uppercase p-4" href="./contactus.php"
                >Contact</a
              >
            </li>
          </ul>
        </div>

        <div class="icons d-flex align-items-center">
          <a class="text-decoration-none" id="searchBtn" href="#"><i class="fa fa-search me-3"></i></a>
          <a class="text-decoration-none" id="shoppingbutton" href="./cart.php"><i class="fa fa-shopping-bag me-3"></i></a>
          <?php if ($currentUser): ?>
            <div class="dropdown">
              <a class="d-flex align-items-center text-decoration-none dropdown-toggle" href="#" role="button" id="profileMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="<?php echo url($profileImg); ?>" alt="profile" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">
              </a>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileMenu">
                <li><h6 class="dropdown-header"><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></h6></li>
                <li><hr class="dropdown-divider"></li>
                
                <li><a class="dropdown-item" href="<?php echo url('includes/logout.php'); ?>"><i class="fa fa-right-from-bracket me-2"></i>Logout</a></li>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="d-flex justify-content-around py-3 align-items-center d-lg-none">
        <div id="hamburger">
          <i class="fa fa-2x fa-bars me-3"></i>
        </div>
        <div class="mobile-nav-logo">
          <div class="logo">
            <a href="./index.php">
              <i class="fa fa-utensils me-3"></i>
              <h1 class="mb-0">Mero Bhoj</h1>
            </a>
          </div>
        </div>
        <div class="mobile-nav-icons">
          <div class="icons">
            <a class="text-decoration-none" id="searchBtnMobile" href="#">
              <i class="fa fa-search me-3"></i>
            </a>
            <a class="text-decoration-none" id="shoppingbuttonMobile" href="./cart.php">
              <i class="fa fa-shopping-bag me-3"></i>
            </a>
          </div>
        </div>
        <div class="position-fixed w-75 bg-white h-100 top-0 start-0" id="mobile-menu">
          <div id="hamburger-cross" class="d-flex justify-content-end align-items-center py-2">
            <i class="fa fa-2x fa-times me-3"></i>
          </div>
          <div class="menus">
            <ul class="d-flex flex-column ps-2 mb-0 mt-4">
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./index.php"
                  >Home</a
                >
              </li>
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./aboutus.php"
                  >About</a
                >
              </li>
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./menu.php"
                  >Menu</a
                >
              </li>
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./myorder.php"
                  >My Order</a
                >
              </li>
              <?php if (!$currentUser): ?>
                <li class="list-unstyled py-2">
                  <a class="btn btn-gradient" href="<?php echo url('/login.php'); ?>">Login</a>
                </li>
              <?php endif; ?>
              <li class="list-unstyled py-2">
                <a class="text-dark text-decoration-none text-uppercase p-4" href="./contactus.php"
                  >Contact</a
                >
              </li>
            </ul>
          </div>
        </div>
      </div>
    </header>

    <main class="container py-5 main-content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">My Orders & Bookings</h2>
            <a href="./menu.php" class="btn btn-outline-secondary">Back to Menu</a>
        </div>
        
        <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold" id="food-tab" data-bs-toggle="tab" data-bs-target="#food" type="button" role="tab" aria-controls="food" aria-selected="true">Food Orders</button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="bookings-tab" data-bs-toggle="tab" data-bs-target="#bookings" type="button" role="tab" aria-controls="bookings" aria-selected="false">Table Bookings</button>
          </li>
        </ul>
        
        <div class="tab-content" id="myTabContent">
          <!-- Food Orders Tab -->
          <div class="tab-pane fade show active" id="food" role="tabpanel" aria-labelledby="food-tab">
            <div class="table-responsive bg-light p-3 rounded shadow-sm">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Purchased Items</th>
                            <th>Total Amount</th>
                            <th>Status & Date</th>
                        </tr>
                    </thead>
                    <tbody id="orders-body">
                        <tr><td colspan="4" class="text-center text-muted">Loading orders...</td></tr>
                    </tbody>
                </table>
            </div>
          </div>
          
          <!-- Table Bookings Tab -->
          <div class="tab-pane fade" id="bookings" role="tabpanel" aria-labelledby="bookings-tab">
            <div class="table-responsive bg-light p-3 rounded shadow-sm">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Table Info</th>
                            <th>Date & Time</th>
                            <th>People</th>
                            <th>Status & Timer</th>
                        </tr>
                    </thead>
                    <tbody id="bookings-body">
                        <tr><td colspan="4" class="text-center text-muted">Loading bookings...</td></tr>
                    </tbody>
                </table>
            </div>
          </div>
        </div>
    </main>
    <?php include_once __DIR__ . '/../includes/cart_drawer.php'; ?>
    <?php include_once __DIR__ . '/../footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script type="text/javascript" src="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
    <script
      src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"
      integrity="sha384-kenU1KFdBIe4zVF0s0G1M5b4hcpxyD9F7jL+jjXkk+Q2h455rYXK/7HAuoJl+0I4"
      crossorigin="anonymous"
    ></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <?php require_once __DIR__ . '/../config/bootstrap.php'; ?>
    <script src="<?php echo asset('js/script.js'); ?>"></script>
    <script src="<?php echo asset('js/toast_notifications.js'); ?>"></script>
    <script>
        let serverNow = "";
        let clientTimeOffset = 0;
        
        function initTimeOffset(serverTimeStr) {
            if (!serverTimeStr) return;
            const serverTime = new Date(serverTimeStr.replace(' ', 'T'));
            const clientTime = new Date();
            clientTimeOffset = serverTime.getTime() - clientTime.getTime();
        }

        function getServerTime() {
            return new Date(new Date().getTime() + clientTimeOffset);
        }

        async function fetchBookings() {
            try {
                const res = await fetch('../includes/bookings_fetch.php');
                const data = await res.json();
                
                if (!data.ok) {
                    document.getElementById('bookings-body').innerHTML = `<tr><td colspan="4" class="text-center text-muted">Please login to view your bookings.</td></tr>`;
                    return;
                }
                
                if (data.server_now) {
                    initTimeOffset(data.server_now);
                }
                
                const tbody = document.getElementById('bookings-body');
                if (!data.bookings || data.bookings.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">No table bookings found.</td></tr>`;
                    return;
                }
                
                tbody.innerHTML = data.bookings.map(b => {
                    const statusClass = `status-${b.status.toLowerCase()}`;
                    let timerHtml = `<span class="text-muted">-</span>`;
                    
                    if (b.status === 'Confirmed' && b.grace_end_at) {
                        timerHtml = `<div class="countdown-container" data-grace-end="${b.grace_end_at}">--:--</div>`;
                    }
                    
                    return `
                    <tr>
                        <td>
                            <strong style="color: #0d47a1;">${b.table_name}</strong><br>
                            <small class="text-muted">Capacity: ${b.capacity}</small>
                        </td>
                        <td>
                            <strong>${b.formatted_date}</strong><br>
                            <span class="text-muted">${b.formatted_time}</span>
                        </td>
                        <td style="font-weight: 700;">${b.people}</td>
                        <td>
                            <div class="status-container">
                                <span class="status-badge ${statusClass}">${b.status}</span>
                                <div style="margin-top: 5px;">${timerHtml}</div>
                            </div>
                        </td>
                    </tr>
                    `;
                }).join('');
                
            } catch (e) {
                console.error(e);
            }
        }
        
        function updateCountdowns() {
            const containers = document.querySelectorAll('.countdown-container');
            if (containers.length === 0) return;
            
            const now = getServerTime();

            containers.forEach(container => {
                const graceEndStr = container.getAttribute('data-grace-end');
                if (!graceEndStr) return;
                
                const graceEnd = new Date(graceEndStr.replace(' ', 'T'));
                // Assume booking time is grace_end - 20 mins
                const bookingTime = new Date(graceEnd.getTime() - (20 * 60 * 1000));

                if (now < bookingTime) {
                    container.innerHTML = `<span style="color:#6b7280;font-size:12px;">Starts at ${bookingTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>`;
                } else if (now >= graceEnd) {
                    container.innerHTML = '<span class="grace-expired">Grace period expired</span>';
                } else {
                    const diff = graceEnd - now;
                    const mins = Math.floor(diff / 60000);
                    const secs = Math.floor((diff % 60000) / 1000);
                    const timeStr = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                    
                    let colorClass = 'grace-timer';
                    if (mins < 5) colorClass += ' grace-danger';
                    else if (mins < 10) colorClass += ' grace-warning';
                    else colorClass += ' grace-timer';

                    container.innerHTML = `<div style="font-size:11px;color:#d97706;">⚠️ Booking started</div><div class="${colorClass}">⏳ ${timeStr} left</div>`;
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            fetchOrders();
            fetchBookings();
            setInterval(() => {
                fetchOrders();
                fetchBookings();
            }, POLL_MS);
            
            setInterval(updateCountdowns, 1000);
            
            <?php if (isset($_SESSION['msg'])): $m = $_SESSION['msg']; unset($_SESSION['msg']); ?>
              window.MKJ_SESSION_MSG = {
                type: '<?php echo $m['type']; ?>',
                text: <?php echo json_encode(htmlspecialchars($m['text'])); ?>
              };
              mkjShowToastFromSession();
            <?php endif; ?>
        });
    </script>
</body>
</html>
