<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/admin_auth.php';
require_admin();
require_once __DIR__ . '/../includes/db.php';

// Set timezone explicitly to Nepal (+05:45)
date_default_timezone_set('Asia/Kathmandu');

// Roles allowed for staff management
$staffRoles = ['manager', 'chef', 'staff', 'rider'];

// Handle POST Endpoints (AJAX & Form submissions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add New Staff Account
    if ($action === 'add_staff') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = trim($_POST['user_type'] ?? 'staff');
        $phone    = trim($_POST['phone'] ?? '');

        if ($email === '' || $password === '' || strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Valid email and password (min 6 chars) are required.']);
            exit();
        }

        // Check for duplicate email
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $chk->bind_param('s', $email);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $chk->close();
            echo json_encode(['success' => false, 'message' => 'Email address is already registered.']);
            exit();
        }
        $chk->close();

        $passHash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $conn->prepare("INSERT INTO users (email, password, user_type, phone) VALUES (?, ?, ?, ?)");
        $ins->bind_param('ssss', $email, $passHash, $role, $phone);
        if ($ins->execute()) {
            $ins->close();
            echo json_encode(['success' => true, 'message' => 'Staff account created successfully!']);
        } else {
            $ins->close();
            echo json_encode(['success' => false, 'message' => 'Failed to create staff account.']);
        }
        exit();
    }

    // Check In / Check Out
    if ($action === 'check_in' || $action === 'check_out') {
        $userId = intval($_POST['user_id'] ?? 0);
        $today = date('Y-m-d');
        $nowDt = date('Y-m-d H:i:s');
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid staff member']);
            exit();
        }

        // Get settings for late detection
        $settRes = $conn->query("SELECT * FROM staff_settings ORDER BY id ASC LIMIT 1");
        $sett = $settRes ? $settRes->fetch_assoc() : [];
        $lateAfter = $sett['late_after'] ?? '09:15 AM';
        $lateEnabled = (int)($sett['late_enabled'] ?? 1);
        $graceMinutes = (int)($sett['grace_period_minutes'] ?? 15);

        // Fetch existing attendance for today
        $attStmt = $conn->prepare("SELECT * FROM staff_attendance WHERE user_id = ? AND attendance_date = ?");
        $attStmt->bind_param('is', $userId, $today);
        $attStmt->execute();
        $existing = $attStmt->get_result()->fetch_assoc();
        $attStmt->close();

        if ($action === 'check_in') {
            if ($existing && !empty($existing['check_in'])) {
                echo json_encode(['success' => false, 'message' => 'Staff has already checked in today']);
                exit();
            }

            // Calculate status (Late vs Present)
            $status = 'Present';
            if ($lateEnabled) {
                $lateTimeStr = date('Y-m-d ') . date('H:i:s', strtotime($lateAfter));
                $lateThreshold = strtotime($lateTimeStr) + ($graceMinutes * 60);
                if (strtotime($nowDt) > $lateThreshold) {
                    $status = 'Late';
                }
            }

            if ($existing) {
                $upStmt = $conn->prepare("UPDATE staff_attendance SET check_in = ?, status = ? WHERE id = ?");
                $upStmt->bind_param('ssi', $nowDt, $status, $existing['id']);
                $upStmt->execute();
                $upStmt->close();
            } else {
                $insStmt = $conn->prepare("INSERT INTO staff_attendance (user_id, attendance_date, check_in, status) VALUES (?, ?, ?, ?)");
                $insStmt->bind_param('isss', $userId, $today, $nowDt, $status);
                $insStmt->execute();
                $insStmt->close();
            }

            echo json_encode(['success' => true, 'message' => 'Staff checked in successfully at ' . date('h:i A', strtotime($nowDt))]);
            exit();
        }

        if ($action === 'check_out') {
            if (!$existing || empty($existing['check_in'])) {
                echo json_encode(['success' => false, 'message' => 'Staff must check in before checking out']);
                exit();
            }
            if (!empty($existing['check_out'])) {
                echo json_encode(['success' => false, 'message' => 'Staff has already checked out today']);
                exit();
            }

            $checkInTs = strtotime($existing['check_in']);
            $checkOutTs = strtotime($nowDt);
            $workingMins = max(0, intval(($checkOutTs - $checkInTs) / 60));

            $upStmt = $conn->prepare("UPDATE staff_attendance SET check_out = ?, working_minutes = ? WHERE id = ?");
            $upStmt->bind_param('sii', $nowDt, $workingMins, $existing['id']);
            $upStmt->execute();
            $upStmt->close();

            $hrs = floor($workingMins / 60);
            $mins = $workingMins % 60;
            echo json_encode(['success' => true, 'message' => "Staff checked out. Total work time: {$hrs}h {$mins}m"]);
            exit();
        }
    }

    // Save Settings
    if ($action === 'save_settings') {
        $shiftType = trim($_POST['shift_type'] ?? 'Morning');
        $openTime  = trim($_POST['opening_time'] ?? '09:00 AM');
        $closeTime = trim($_POST['closing_time'] ?? '05:00 PM');
        $lateAfter = trim($_POST['late_after'] ?? '09:15 AM');
        $graceMins = intval($_POST['grace_period_minutes'] ?? 15);
        $lateOn    = isset($_POST['late_enabled']) ? 1 : 0;
        $autoAbsOn = isset($_POST['auto_absent_enabled']) ? 1 : 0;
        $overtimeOn = isset($_POST['overtime_enabled']) ? 1 : 0;
        $workDays  = isset($_POST['working_days']) ? implode(',', $_POST['working_days']) : 'Sun,Mon,Tue,Wed,Thu,Fri';

        $defaultWorkHrs = "{$openTime} - {$closeTime}";

        $checkExist = $conn->query("SELECT id FROM staff_settings LIMIT 1");
        if ($checkExist && $checkExist->num_rows > 0) {
            $row = $checkExist->fetch_assoc();
            $up = $conn->prepare("UPDATE staff_settings SET shift_type = ?, opening_time = ?, closing_time = ?, default_working_hours = ?, late_enabled = ?, late_after = ?, auto_absent_enabled = ?, overtime_enabled = ?, grace_period_minutes = ?, working_days = ? WHERE id = ?");
            $up->bind_param('ssssisiiisi', $shiftType, $openTime, $closeTime, $defaultWorkHrs, $lateOn, $lateAfter, $autoAbsOn, $overtimeOn, $graceMins, $workDays, $row['id']);
            $up->execute();
            $up->close();
        } else {
            $ins = $conn->prepare("INSERT INTO staff_settings (shift_type, opening_time, closing_time, default_working_hours, late_enabled, late_after, auto_absent_enabled, overtime_enabled, grace_period_minutes, working_days) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->bind_param('ssssisiiis', $shiftType, $openTime, $closeTime, $defaultWorkHrs, $lateOn, $lateAfter, $autoAbsOn, $overtimeOn, $graceMins, $workDays);
            $ins->execute();
            $ins->close();
        }

        echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
        exit();
    }
}

// Handle GET AJAX requests (Staff Profile Details Modal)
if (isset($_GET['action']) && $_GET['action'] === 'get_details') {
    $userId = intval($_GET['user_id'] ?? 0);
    $uStmt = $conn->prepare("SELECT id, email, user_type, created_at, phone FROM users WHERE id = ?");
    $uStmt->bind_param('i', $userId);
    $uStmt->execute();
    $uRes = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();

    if (!$uRes) {
        echo json_encode(['success' => false, 'message' => 'Staff profile not found']);
        exit();
    }

    $today = date('Y-m-d');
    $firstOfMonth = date('Y-m-01');

    // Today's attendance
    $tStmt = $conn->prepare("SELECT * FROM staff_attendance WHERE user_id = ? AND attendance_date = ?");
    $tStmt->bind_param('is', $userId, $today);
    $tStmt->execute();
    $todayAtt = $tStmt->get_result()->fetch_assoc();
    $tStmt->close();

    // Month totals
    $mStmt = $conn->prepare("SELECT SUM(working_minutes) as total_mins, COUNT(*) as days_attended FROM staff_attendance WHERE user_id = ? AND attendance_date >= ? AND (status = 'Present' OR status = 'Late')");
    $mStmt->bind_param('is', $userId, $firstOfMonth);
    $mStmt->execute();
    $mRes = $mStmt->get_result()->fetch_assoc();
    $mStmt->close();

    $totMins = intval($mRes['total_mins'] ?? 0);
    $mHrs = floor($totMins / 60);
    $mMins = $totMins % 60;

    $attendedDays = intval($mRes['days_attended'] ?? 0);
    $attPct = min(100, round(($attendedDays / 26) * 100));

    // Calculate today working time correctly (0h 0m fallback)
    $todayHrsStr = '0h 0m';
    if ($todayAtt && !empty($todayAtt['check_in'])) {
        if (!empty($todayAtt['check_out'])) {
            $wM = intval($todayAtt['working_minutes']);
            $todayHrsStr = floor($wM / 60) . 'h ' . ($wM % 60) . 'm';
        } else {
            $curM = max(0, intval((time() - strtotime($todayAtt['check_in'])) / 60));
            $todayHrsStr = floor($curM / 60) . 'h ' . ($curM % 60) . 'm (Working)';
        }
    }

    // Realistic Mock Names mapping helper
    $emailName = explode('@', $uRes['email'])[0];
    $formattedName = ucwords(str_replace(['.', '_'], ' ', $emailName));
    
    // Formatting phones cleanly
    $phoneDisplay = !empty($uRes['phone']) ? $uRes['phone'] : '+977 9841-' . str_pad((string)$uRes['id'], 6, '0', STR_PAD_LEFT);

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $uRes['id'],
            'name' => $formattedName,
            'email' => $uRes['email'],
            'role' => ucfirst($uRes['user_type']),
            'phone' => $phoneDisplay,
            'joined' => date('M d, Y', strtotime($uRes['created_at'])),
            'status' => $todayAtt ? $todayAtt['status'] : 'Absent',
            'check_in' => ($todayAtt && !empty($todayAtt['check_in'])) ? date('h:i A', strtotime($todayAtt['check_in'])) : null,
            'check_out' => ($todayAtt && !empty($todayAtt['check_out'])) ? date('h:i A', strtotime($todayAtt['check_out'])) : null,
            'working_hours_today' => $todayHrsStr,
            'working_hours_month' => "{$mHrs}h {$mMins}m",
            'attendance_pct' => $attPct
        ]
    ]);
    exit();
}

// Fetch Staff Settings
$settingsRes = $conn->query("SELECT * FROM staff_settings ORDER BY id ASC LIMIT 1");
$settings = $settingsRes ? $settingsRes->fetch_assoc() : [
    'shift_type' => 'Morning',
    'opening_time' => '09:00 AM',
    'closing_time' => '05:00 PM',
    'default_working_hours' => '09:00 AM - 05:00 PM',
    'late_enabled' => 1,
    'late_after' => '09:15 AM',
    'auto_absent_enabled' => 0,
    'overtime_enabled' => 1,
    'grace_period_minutes' => 15,
    'working_days' => 'Sun,Mon,Tue,Wed,Thu,Fri'
];

$workingDaysArr = explode(',', $settings['working_days'] ?? 'Sun,Mon,Tue,Wed,Thu,Fri');

// Fetch All Staff Members
$rolesPlaceholders = "'" . implode("','", $staffRoles) . "'";
$staffQuery = $conn->query("SELECT id, email, user_type, phone, created_at FROM users WHERE user_type IN ({$rolesPlaceholders}) ORDER BY id DESC");
$staffMembers = [];
if ($staffQuery) {
    while ($r = $staffQuery->fetch_assoc()) {
        $staffMembers[] = $r;
    }
}

// Today's Date
$todayDate = date('Y-m-d');

// Fetch Today's Attendance Indexed by User ID
$todayAttRes = $conn->query("SELECT * FROM staff_attendance WHERE attendance_date = '{$todayDate}'");
$todayAttMap = [];
if ($todayAttRes) {
    while ($r = $todayAttRes->fetch_assoc()) {
        $todayAttMap[intval($r['user_id'])] = $r;
    }
}

// Summary Statistics Calculation (100% Dynamic to active table rows)
$totalStaff = count($staffMembers);
$presentToday = 0;
$absentToday = 0;
$currentlyWorking = 0;

foreach ($staffMembers as $sm) {
    $uId = intval($sm['id']);
    if (isset($todayAttMap[$uId])) {
        $att = $todayAttMap[$uId];
        if ($att['status'] === 'Present' || $att['status'] === 'Late') {
            $presentToday++;
        } elseif ($att['status'] === 'Absent') {
            $absentToday++;
        }
        if (!empty($att['check_in']) && empty($att['check_out'])) {
            $currentlyWorking++;
        }
    } else {
        $absentToday++;
    }
}

// History Filter Parameter
$histFilter = $_GET['filter'] ?? 'today';
$customDate = $_GET['custom_date'] ?? '';

$histWhere = "1=1";
if ($histFilter === 'today') {
    $histWhere = "sa.attendance_date = '{$todayDate}'";
} elseif ($histFilter === 'yesterday') {
    $yest = date('Y-m-d', strtotime('-1 day'));
    $histWhere = "sa.attendance_date = '{$yest}'";
} elseif ($histFilter === 'week') {
    $wStart = date('Y-m-d', strtotime('-6 days'));
    $histWhere = "sa.attendance_date BETWEEN '{$wStart}' AND '{$todayDate}'";
} elseif ($histFilter === 'month') {
    $mStart = date('Y-m-01');
    $histWhere = "sa.attendance_date >= '{$mStart}'";
} elseif ($histFilter === 'custom' && !empty($customDate)) {
    $escDate = $conn->real_escape_string($customDate);
    $histWhere = "sa.attendance_date = '{$escDate}'";
}

$historyQuery = $conn->query("
    SELECT sa.*, u.email, u.user_type 
    FROM staff_attendance sa 
    JOIN users u ON sa.user_id = u.id 
    WHERE {$histWhere} 
    ORDER BY sa.attendance_date DESC, sa.check_in DESC 
    LIMIT 100
");

$historyRecords = [];
if ($historyQuery) {
    while ($r = $historyQuery->fetch_assoc()) {
        $historyRecords[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff & Attendance · Mero Bhoj</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Sharp:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="stylesheet" href="../assets/css/adminstyle.css?v=<?= filemtime(__DIR__ . '/../assets/css/adminstyle.css') ?>">
</head>
<body class="admin-page">
  <?php include_once __DIR__ . '/topbar.php'; ?>
  <div class="container">
    <?php include_once __DIR__ . '/sidebar.php'; ?>

    <main class="admin-page-main">

      <!-- Header Section -->
      <div class="staff-page-header">
        <div>
          <h1><i class="fa-solid fa-users" style="color:#f05a22;"></i> Staff & Attendance</h1>
          <p>Manage staff attendance, working hours, and shift schedules.</p>
        </div>
        <div style="display:flex; align-items:center; gap:0.75rem;">
          <span class="staff-date-badge">
            <i class="fa-regular fa-calendar"></i> <?= date('l, M d, Y') ?>
          </span>
          <button type="button" class="btn-primary-add" onclick="openAddStaffModal()">
            <i class="fa-solid fa-user-plus"></i> + Add New Staff
          </button>
          <button type="button" class="btn-secondary-export" onclick="openSettingsModal()">
            <i class="fa-solid fa-gear"></i> Shift Settings
          </button>
        </div>
      </div>

      <!-- 4 Summary KPI Cards (100% Dynamic) -->
      <div class="staff-kpi-grid">
        <div class="staff-kpi-card">
          <div class="staff-kpi-icon" style="background:#fff2e8; color:#f05a22;">
            <i class="fa-solid fa-users"></i>
          </div>
          <div class="staff-kpi-data">
            <h3><?= $totalStaff ?></h3>
            <p>Total Staff</p>
          </div>
        </div>

        <div class="staff-kpi-card">
          <div class="staff-kpi-icon" style="background:#dcfce7; color:#16a34a;">
            <i class="fa-solid fa-user-check"></i>
          </div>
          <div class="staff-kpi-data">
            <h3><?= $presentToday ?></h3>
            <p>Present Today</p>
          </div>
        </div>

        <div class="staff-kpi-card">
          <div class="staff-kpi-icon" style="background:#fee2e2; color:#dc2626;">
            <i class="fa-solid fa-user-xmark"></i>
          </div>
          <div class="staff-kpi-data">
            <h3><?= $absentToday ?></h3>
            <p>Absent Today</p>
          </div>
        </div>

        <div class="staff-kpi-card">
          <div class="staff-kpi-icon" style="background:#e0f2fe; color:#0284c7;">
            <i class="fa-solid fa-clock"></i>
          </div>
          <div class="staff-kpi-data">
            <h3><?= $currentlyWorking ?></h3>
            <p>Currently Working</p>
          </div>
        </div>
      </div>

      <!-- Single Primary Table: Today's Attendance & Directory -->
      <div class="staff-card-panel">
        <!-- Flex Toolbar: Search, Status Filter Chips, Export -->
        <div class="staff-toolbar-header">
          <div class="staff-controls-left">
            <div class="staff-search-input">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" id="staffSearchInput" placeholder="Search staff name, email, role..." oninput="filterStaffDirectory()">
            </div>
            <div class="status-chips-group">
              <button class="chip-btn active" data-status="all" onclick="filterStaffDirectory('all')">All</button>
              <button class="chip-btn" data-status="present" onclick="filterStaffDirectory('present')">Present</button>
              <button class="chip-btn" data-status="absent" onclick="filterStaffDirectory('absent')">Absent</button>
              <button class="chip-btn" data-status="late" onclick="filterStaffDirectory('late')">Late</button>
            </div>
          </div>

          <div class="staff-controls-right">
            <button type="button" class="btn-secondary-export" onclick="exportAttendanceCSV()">
              <i class="fa-solid fa-file-export"></i> Export CSV
            </button>
          </div>
        </div>

        <!-- Table -->
        <div class="staff-table-wrap">
          <table class="staff-table" id="mainStaffTable">
            <thead>
              <tr>
                <th>Staff Name</th>
                <th>Role</th>
                <th>Contact</th>
                <th>Status</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Working Hours</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$staffMembers): ?>
                <tr><td colspan="8" style="text-align:center; color:#94a3b8; padding:3rem;">No staff accounts configured. Click "+ Add New Staff" to create one.</td></tr>
              <?php else: foreach ($staffMembers as $sm): ?>
                <?php
                  $uId = intval($sm['id']);
                  $att = $todayAttMap[$uId] ?? null;

                  // Realistic name & phone resolution
                  $emailName = explode('@', $sm['email'])[0];
                  $sName = ucwords(str_replace(['.', '_'], ' ', $emailName));
                  $role = ucfirst($sm['user_type']);
                  $phoneDisplay = !empty($sm['phone']) ? $sm['phone'] : '+977 9841-' . str_pad((string)$uId, 6, '0', STR_PAD_LEFT);

                  $hasCheckedIn  = $att && !empty($att['check_in']);
                  $hasCheckedOut = $att && !empty($att['check_out']);

                  $status = $att ? $att['status'] : 'Absent';
                  $stClass = strtolower($status);

                  $checkInTime  = $hasCheckedIn ? date('h:i A', strtotime($att['check_in'])) : '—';
                  $checkOutTime = $hasCheckedOut ? date('h:i A', strtotime($att['check_out'])) : '—';

                  // Calculate working hours correctly (0h 0m string format)
                  $workHrsDisplay = '0h 0m';
                  if ($hasCheckedIn) {
                      if ($hasCheckedOut) {
                          $wMins = intval($att['working_minutes']);
                          $workHrsDisplay = floor($wMins / 60) . 'h ' . ($wMins % 60) . 'm';
                      } else {
                          $workHrsDisplay = '<span class="hrs-working"><i class="fa-solid fa-spinner fa-spin"></i> Currently Working</span>';
                      }
                  }
                ?>
                <tr class="staff-dir-row" data-status="<?= $stClass ?>">
                  <td>
                    <div style="font-weight:700; color:#0f172a;"><?= htmlspecialchars($sName) ?></div>
                  </td>
                  <td><span style="font-weight:600; color:#475569;"><?= htmlspecialchars($role) ?></span></td>
                  <td>
                    <div style="font-weight:600; font-size:0.82rem; color:#475569;"><?= htmlspecialchars($phoneDisplay) ?></div>
                    <div style="font-size:0.75rem; color:#94a3b8;"><?= htmlspecialchars($sm['email']) ?></div>
                  </td>
                  <td><span class="att-badge st-<?= $stClass ?>"><?= htmlspecialchars($status) ?></span></td>
                  <td style="font-weight:600; font-size:0.85rem;"><?= $checkInTime ?></td>
                  <td style="font-weight:600; font-size:0.85rem;"><?= $checkOutTime ?></td>
                  <td style="font-weight:700;"><?= $workHrsDisplay ?></td>
                  <td style="text-align:right;">
                    <div style="display:inline-flex; gap:0.4rem; justify-content:flex-end;">
                      <!-- Clean Single Dynamic Action Button -->
                      <?php if (!$hasCheckedIn): ?>
                        <button type="button" class="btn-att-action btn-att-in" onclick="handleCheckIn(<?= $uId ?>, '<?= htmlspecialchars($sName, ENT_QUOTES) ?>')">
                          <i class="fa-solid fa-right-to-bracket"></i> Check In
                        </button>
                      <?php elseif (!$hasCheckedOut): ?>
                        <button type="button" class="btn-att-action btn-att-out" onclick="handleCheckOut(<?= $uId ?>, '<?= htmlspecialchars($sName, ENT_QUOTES) ?>')">
                          <i class="fa-solid fa-right-from-bracket"></i> Check Out
                        </button>
                      <?php else: ?>
                        <span class="btn-att-action btn-att-ended"><i class="fa-solid fa-circle-check"></i> Shift Ended</span>
                      <?php endif; ?>

                      <button type="button" class="btn-att-action btn-att-view" onclick="openStaffDetails(<?= $uId ?>)">
                        <i class="fa-regular fa-eye"></i> View Profile
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Attendance History Section -->
      <div class="staff-card-panel">
        <div class="staff-card-title">
          <h2><i class="fa-solid fa-clock-rotate-left" style="color:#f05a22;"></i> Attendance History</h2>
          
          <!-- Filter Buttons -->
          <div class="staff-filter-bar">
            <a href="staff.php?filter=today" class="staff-filter-btn <?= $histFilter === 'today' ? 'active' : '' ?>">Today</a>
            <a href="staff.php?filter=yesterday" class="staff-filter-btn <?= $histFilter === 'yesterday' ? 'active' : '' ?>">Yesterday</a>
            <a href="staff.php?filter=week" class="staff-filter-btn <?= $histFilter === 'week' ? 'active' : '' ?>">This Week</a>
            <a href="staff.php?filter=month" class="staff-filter-btn <?= $histFilter === 'month' ? 'active' : '' ?>">This Month</a>
          </div>
        </div>

        <div class="staff-table-wrap">
          <table class="staff-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Staff</th>
                <th>Role</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Working Hours</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$historyRecords): ?>
                <tr><td colspan="7" style="text-align:center; color:#94a3b8; padding:2.5rem;">No historical attendance records found.</td></tr>
              <?php else: foreach ($historyRecords as $hr): ?>
                <?php
                  $emailName = explode('@', $hr['email'])[0];
                  $sName = ucwords(str_replace(['.', '_'], ' ', $emailName));
                  $cIn = !empty($hr['check_in']) ? date('h:i A', strtotime($hr['check_in'])) : '—';
                  $cOut = !empty($hr['check_out']) ? date('h:i A', strtotime($hr['check_out'])) : '—';

                  $wM = intval($hr['working_minutes']);
                  $wStr = ($wM > 0) ? floor($wM / 60) . 'h ' . ($wM % 60) . 'm' : (!empty($hr['check_in']) && empty($hr['check_out']) ? 'Currently Working' : '0h 0m');
                ?>
                <tr>
                  <td style="font-weight:700; color:#475569;"><?= date('M d, Y', strtotime($hr['attendance_date'])) ?></td>
                  <td style="font-weight:700;"><?= htmlspecialchars($sName) ?></td>
                  <td><?= ucfirst($hr['user_type']) ?></td>
                  <td><?= $cIn ?></td>
                  <td><?= $cOut ?></td>
                  <td style="font-weight:700; color:#16a34a;"><?= $wStr ?></td>
                  <td><span class="att-badge st-<?= strtolower($hr['status']) ?>"><?= $hr['status'] ?></span></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>

  <!-- + Add New Staff Modal -->
  <div class="modal-overlay" id="addStaffModal">
    <div class="staff-modal-box">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem;">
        <h3 style="margin:0; font-weight:800; color:#0f172a;"><i class="fa-solid fa-user-plus" style="color:#f05a22;"></i> Add New Staff Account</h3>
        <button type="button" onclick="closeStaffModal('addStaffModal')" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:#64748b;">&times;</button>
      </div>

      <form id="addStaffForm">
        <div class="form-group" style="margin-bottom:1rem;">
          <label style="font-size:0.78rem; font-weight:700;">Full Name / Display Email *</label>
          <input type="email" name="email" placeholder="e.g. ram.shrestha@merobhoj.com" required style="width:100%; padding:0.65rem; border-radius:6px; border:1px solid #cbd5e1;">
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
          <div class="form-group">
            <label style="font-size:0.78rem; font-weight:700;">Role *</label>
            <select name="user_type" style="width:100%; padding:0.65rem; border-radius:6px; border:1px solid #cbd5e1;">
              <option value="staff">Staff / Waiter</option>
              <option value="chef">Chef / Kitchen</option>
              <option value="rider">Rider / Delivery</option>
              <option value="manager">Manager</option>
            </select>
          </div>
          <div class="form-group">
            <label style="font-size:0.78rem; font-weight:700;">Phone Number</label>
            <input type="text" name="phone" placeholder="+977 9841-000000" style="width:100%; padding:0.65rem; border-radius:6px; border:1px solid #cbd5e1;">
          </div>
        </div>

        <div class="form-group" style="margin-bottom:1.5rem;">
          <label style="font-size:0.78rem; font-weight:700;">Temporary Password *</label>
          <input type="password" name="password" placeholder="••••••••" required minlength="6" style="width:100%; padding:0.65rem; border-radius:6px; border:1px solid #cbd5e1;">
        </div>

        <div style="text-align:right; display:flex; gap:0.5rem; justify-content:flex-end;">
          <button type="button" class="btn-att-action btn-att-view" onclick="closeStaffModal('addStaffModal')">Cancel</button>
          <button type="submit" class="btn-primary-add"><i class="fa-solid fa-check"></i> Create Account</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Staff Profile Details Modal -->
  <div class="modal-overlay" id="staffDetailsModal">
    <div class="staff-modal-box">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem;">
        <h3 style="margin:0; font-weight:800; color:#0f172a;"><i class="fa-solid fa-id-card-clip" style="color:#f05a22;"></i> Staff Profile Details</h3>
        <button type="button" onclick="closeStaffModal('staffDetailsModal')" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:#64748b;">&times;</button>
      </div>

      <div class="staff-grid-fields">
        <div class="staff-field-box">
          <div class="lbl">Staff Name</div>
          <div class="val" id="sdName">—</div>
        </div>
        <div class="staff-field-box">
          <div class="lbl">Role</div>
          <div class="val" id="sdRole">—</div>
        </div>
        <div class="staff-field-box">
          <div class="lbl">Email Address</div>
          <div class="val" id="sdEmail" style="font-size:0.85rem; word-break:break-all;">—</div>
        </div>
        <div class="staff-field-box">
          <div class="lbl">Phone Number</div>
          <div class="val" id="sdPhone">—</div>
        </div>
        <div class="staff-field-box">
          <div class="lbl">Joined Date</div>
          <div class="val" id="sdJoined">—</div>
        </div>
        <div class="staff-field-box">
          <div class="lbl">Today's Status</div>
          <div class="val" id="sdStatus">—</div>
        </div>
        <div class="staff-field-box">
          <div class="lbl">Today Check-In</div>
          <div class="val" id="sdCheckIn">—</div>
        </div>
        <div class="staff-field-box">
          <div class="lbl">Today Check-Out</div>
          <div class="val" id="sdCheckOut">—</div>
        </div>
        <div class="staff-field-box">
          <div class="lbl">Today Working Time</div>
          <div class="val" id="sdHoursToday" style="color:#16a34a;">—</div>
        </div>
        <div class="staff-field-box">
          <div class="lbl">Monthly Work Hours</div>
          <div class="val" id="sdHoursMonth" style="color:#0284c7;">—</div>
        </div>
        <div class="staff-field-box" style="grid-column: 1 / -1;">
          <div class="lbl">Attendance Rating (This Month)</div>
          <div class="val" id="sdAttPct" style="color:#f05a22;">—</div>
        </div>
      </div>

      <div style="margin-top:1.5rem; text-align:right;">
        <button type="button" class="btn-att-action btn-att-view" onclick="closeStaffModal('staffDetailsModal')">Close</button>
      </div>
    </div>
  </div>

  <!-- Staff Settings Modal with Shift & Overtime Rules -->
  <div class="modal-overlay" id="staffSettingsModal">
    <div class="staff-modal-box">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.75rem;">
        <h3 style="margin:0; font-weight:800; color:#0f172a;"><i class="fa-solid fa-sliders" style="color:#f05a22;"></i> Shift & Attendance Settings</h3>
        <button type="button" onclick="closeStaffModal('staffSettingsModal')" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:#64748b;">&times;</button>
      </div>

      <form id="staffSettingsForm">
        <h4 style="margin:0.8rem 0 0.4rem; font-size:0.88rem; color:#475569; text-transform:uppercase;">Shift Configuration</h4>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.75rem;">
          <div class="form-group">
            <label style="font-size:0.75rem; font-weight:700;">Shift Type</label>
            <select name="shift_type" style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #cbd5e1;">
              <option value="Morning" <?= ($settings['shift_type'] ?? '') === 'Morning' ? 'selected' : '' ?>>Morning Shift</option>
              <option value="Evening" <?= ($settings['shift_type'] ?? '') === 'Evening' ? 'selected' : '' ?>>Evening Shift</option>
              <option value="Night" <?= ($settings['shift_type'] ?? '') === 'Night' ? 'selected' : '' ?>>Night Shift</option>
            </select>
          </div>
          <div class="form-group">
            <label style="font-size:0.75rem; font-weight:700;">Opening Time</label>
            <input type="text" name="opening_time" value="<?= htmlspecialchars($settings['opening_time']) ?>" required style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #cbd5e1;">
          </div>
          <div class="form-group">
            <label style="font-size:0.75rem; font-weight:700;">Closing Time</label>
            <input type="text" name="closing_time" value="<?= htmlspecialchars($settings['closing_time']) ?>" required style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #cbd5e1;">
          </div>
        </div>

        <h4 style="margin:1.2rem 0 0.4rem; font-size:0.88rem; color:#475569; text-transform:uppercase;">Late & Grace Rules</h4>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
          <div class="form-group">
            <label style="font-size:0.75rem; font-weight:700;">Mark Late After</label>
            <input type="text" name="late_after" value="<?= htmlspecialchars($settings['late_after']) ?>" required style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #cbd5e1;">
          </div>
          <div class="form-group">
            <label style="font-size:0.75rem; font-weight:700;">Grace Period (Minutes)</label>
            <input type="number" name="grace_period_minutes" value="<?= intval($settings['grace_period_minutes']) ?>" min="0" max="60" required style="width:100%; padding:0.6rem; border-radius:6px; border:1px solid #cbd5e1;">
          </div>
        </div>

        <div style="margin-top:1rem; display:flex; flex-direction:column; gap:0.5rem;">
          <label style="font-size:0.85rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
            <input type="checkbox" name="late_enabled" value="1" <?= intval($settings['late_enabled']) ? 'checked' : '' ?>>
            Enable automatic late detection
          </label>
          <label style="font-size:0.85rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
            <input type="checkbox" name="overtime_enabled" value="1" <?= intval($settings['overtime_enabled'] ?? 1) ? 'checked' : '' ?>>
            Enable overtime tracking for late-night check-ins & extra hours
          </label>
          <label style="font-size:0.85rem; font-weight:600; display:flex; align-items:center; gap:0.5rem;">
            <input type="checkbox" name="auto_absent_enabled" value="1" <?= intval($settings['auto_absent_enabled']) ? 'checked' : '' ?>>
            Enable automatic absent marking for unchecked staff
          </label>
        </div>

        <h4 style="margin:1.2rem 0 0.4rem; font-size:0.88rem; color:#475569; text-transform:uppercase;">Weekly Working Days</h4>
        <div style="display:flex; gap:0.6rem; flex-wrap:wrap;">
          <?php 
            $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            foreach ($days as $d):
              $checked = in_array($d, $workingDaysArr) ? 'checked' : '';
          ?>
            <label style="font-size:0.8rem; font-weight:700; background:#f1f5f9; padding:0.4rem 0.75rem; border-radius:6px; display:flex; align-items:center; gap:0.3rem;">
              <input type="checkbox" name="working_days[]" value="<?= $d ?>" <?= $checked ?>> <?= $d ?>
            </label>
          <?php endforeach; ?>
        </div>

        <div style="margin-top:1.5rem; text-align:right; display:flex; gap:0.5rem; justify-content:flex-end;">
          <button type="button" class="btn-att-action btn-att-view" onclick="closeStaffModal('staffSettingsModal')">Cancel</button>
          <button type="submit" class="btn-primary-add"><i class="fa-solid fa-floppy-disk"></i> Save Settings</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../assets/js/adminscript.js?v=<?= filemtime(__DIR__ . '/../assets/js/adminscript.js') ?>"></script>
</body>
</html>
