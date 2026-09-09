<?php
$host = "localhost";
$user = "root";
$password = "";
$db = "alu";

 // This should match your actual database name

// Create connection using MySQLi
$conn = new mysqli($host, $user, $password, $db  );

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set MySQL session timezone to Nepal (+05:45)
$conn->query("SET time_zone = '+05:45'");

// Check and add order_number column if it does not exist in orders table
$checkCol = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'order_number'");
if ($checkCol && $checkCol->num_rows === 0) {
    $conn->query("ALTER TABLE `orders` ADD COLUMN `order_number` VARCHAR(50) DEFAULT NULL AFTER `order_id`");
    $conn->query("ALTER TABLE `orders` ADD INDEX `idx_order_number` (`order_number`)");
    $conn->query("UPDATE `orders` SET `order_number` = CONCAT('ORD-', LPAD(`order_id`, 4, '0')) WHERE `order_number` IS NULL OR `order_number` = ''");
}

// Add google_id column to users table if it does not exist
$googleCol = $conn->query("SHOW COLUMNS FROM `users` LIKE 'google_id'");
if ($googleCol && $googleCol->num_rows === 0) {
    $conn->query("ALTER TABLE `users` ADD COLUMN `google_id` VARCHAR(255) DEFAULT NULL AFTER `user_img`");
    $conn->query("ALTER TABLE `users` ADD UNIQUE KEY `google_id` (`google_id`)");
}

// Table booking upgrade schema check
$conn->query("
    CREATE TABLE IF NOT EXISTS `restaurant_tables` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `table_name` varchar(50) NOT NULL,
      `capacity` int(11) NOT NULL,
      `area` varchar(50) DEFAULT 'Ground',
      `shape` varchar(20) DEFAULT 'rectangle',
      `shape_config` text DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

$areaCol = $conn->query("SHOW COLUMNS FROM `restaurant_tables` LIKE 'area'");
if ($areaCol && $areaCol->num_rows === 0) {
    $conn->query("ALTER TABLE `restaurant_tables` ADD COLUMN `area` VARCHAR(50) DEFAULT 'Ground' AFTER `capacity`");
}
$shapeCol = $conn->query("SHOW COLUMNS FROM `restaurant_tables` LIKE 'shape'");
if ($shapeCol && $shapeCol->num_rows === 0) {
    $conn->query("ALTER TABLE `restaurant_tables` ADD COLUMN `shape` VARCHAR(20) DEFAULT 'rectangle' AFTER `area`");
}
$shapeConfigCol = $conn->query("SHOW COLUMNS FROM `restaurant_tables` LIKE 'shape_config'");
if ($shapeConfigCol && $shapeConfigCol->num_rows === 0) {
    $conn->query("ALTER TABLE `restaurant_tables` ADD COLUMN `shape_config` TEXT DEFAULT NULL AFTER `shape`");
}

// Admin notifications table schema check
$conn->query("
    CREATE TABLE IF NOT EXISTS `admin_notifications` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `type` VARCHAR(20) NOT NULL,
      `resource_id` VARCHAR(50) NOT NULL,
      `is_read` TINYINT(1) DEFAULT 0,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY `type_resource` (`type`, `resource_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");


$tableCount = $conn->query("SELECT COUNT(*) FROM `restaurant_tables`");
if ($tableCount && $tableCount->fetch_row()[0] == 0) {
    $conn->query("
        INSERT INTO `restaurant_tables` (`table_name`, `capacity`) VALUES
        ('Table 1', 4),
        ('Table 2', 4),
        ('Table 3', 6),
        ('Table 4', 6),
        ('Table 5', 6),
        ('Table 6', 6),
        ('Table 7', 8);
    ");
}

// ── QR table-ordering: qr_token on tables + qr_requests queue ─────────────────
// The QR flow (order_qr.php + admin/api/api_qr_requests*.php + the staff Self
// Orders panel) reads/writes restaurant_tables.qr_token and the qr_requests
// queue, but no prior migration provisioned them. Idempotent (SHOW COLUMNS /
// CREATE TABLE IF NOT EXISTS) so an already-populated live DB is untouched.
$qrTokenCol = $conn->query("SHOW COLUMNS FROM `restaurant_tables` LIKE 'qr_token'");
if ($qrTokenCol && $qrTokenCol->num_rows === 0) {
    $conn->query("ALTER TABLE `restaurant_tables` ADD COLUMN `qr_token` VARCHAR(64) DEFAULT NULL AFTER `capacity`");
    $conn->query("ALTER TABLE `restaurant_tables` ADD UNIQUE KEY `uniq_qr_token` (`qr_token`)");
    // Backfill a stable random token for every existing table so its QR resolves.
    $tblRes = $conn->query("SELECT `id` FROM `restaurant_tables` WHERE `qr_token` IS NULL OR `qr_token` = ''");
    if ($tblRes) {
        $tokUpd = $conn->prepare("UPDATE `restaurant_tables` SET `qr_token` = ? WHERE `id` = ?");
        if ($tokUpd) {
            while ($tRow = $tblRes->fetch_assoc()) {
                $tok = bin2hex(random_bytes(16));
                $tokUpd->bind_param('si', $tok, $tRow['id']);
                $tokUpd->execute();
            }
            $tokUpd->close();
        }
    }
}

$conn->query("
    CREATE TABLE IF NOT EXISTS `qr_requests` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `table_id` INT(11) NOT NULL,
      `customer_name` VARCHAR(120) DEFAULT NULL,
      `phone` VARCHAR(30) DEFAULT NULL,
      `note` VARCHAR(500) DEFAULT NULL,
      `payment_method` VARCHAR(50) DEFAULT 'Cash',
      `items_json` TEXT,
      `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
      `order_code` VARCHAR(50) DEFAULT NULL,
      `rejection_reason` VARCHAR(255) DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_status_time` (`status`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

$bookingTableIdCol = $conn->query("SHOW COLUMNS FROM `bookings` LIKE 'table_id'");
if ($bookingTableIdCol && $bookingTableIdCol->num_rows === 0) {
    $conn->query("ALTER TABLE `bookings` ADD COLUMN `table_id` int(11) NOT NULL AFTER `phone`");
}

// ── Menu stock status upgrade ────────────────────────────────────────────────
$menuStatusCol = $conn->query("SHOW COLUMNS FROM `menu` LIKE 'menu_status'");
if ($menuStatusCol && $menuStatusCol->num_rows === 0) {
    $conn->query("ALTER TABLE `menu` ADD COLUMN `menu_status` ENUM('In Stock','Low Stock','Out of Stock') NOT NULL DEFAULT 'In Stock' AFTER `menu_category`");
    $conn->query("UPDATE `menu` SET `menu_status` = 'In Stock' WHERE `menu_status` IS NULL OR `menu_status` = ''");
}

// ── Booking system upgrade: grace_end_at column ──────────────────────────────
$graceCol = $conn->query("SHOW COLUMNS FROM `bookings` LIKE 'grace_end_at'");
if ($graceCol && $graceCol->num_rows === 0) {
    $conn->query("ALTER TABLE `bookings` ADD COLUMN `grace_end_at` DATETIME DEFAULT NULL AFTER `status`");
}

// ── Booking system upgrade: start_time and end_time columns ──────────────────
$startTimeCol = $conn->query("SHOW COLUMNS FROM `bookings` LIKE 'start_time'");
if ($startTimeCol && $startTimeCol->num_rows === 0) {
    $conn->query("ALTER TABLE `bookings` ADD COLUMN `start_time` TIME DEFAULT NULL AFTER `booking_time`");
    $conn->query("UPDATE `bookings` SET `start_time` = `booking_time` WHERE `start_time` IS NULL");
}
$endTimeCol = $conn->query("SHOW COLUMNS FROM `bookings` LIKE 'end_time'");
if ($endTimeCol && $endTimeCol->num_rows === 0) {
    $conn->query("ALTER TABLE `bookings` ADD COLUMN `end_time` TIME DEFAULT NULL AFTER `start_time`");
    $conn->query("UPDATE `bookings` SET `end_time` = ADDTIME(`booking_time`, '02:00:00') WHERE `end_time` IS NULL");
}


// ── Normalize statuses to Title Case ─────────────────────────────────────────
// Only runs once: if any lowercase 'pending' rows exist, migrate them all
$lcCheck = $conn->query("SELECT COUNT(*) FROM `bookings` WHERE `status` = 'pending'");
if ($lcCheck && $lcCheck->fetch_row()[0] > 0) {
    $conn->query("UPDATE `bookings` SET `status` = 'Pending'    WHERE `status` = 'pending'");
    $conn->query("UPDATE `bookings` SET `status` = 'Confirmed'  WHERE `status` = 'confirmed'");
    $conn->query("UPDATE `bookings` SET `status` = 'Cancelled'  WHERE `status` = 'cancelled'");
    $conn->query("UPDATE `bookings` SET `status` = 'Completed'  WHERE `status` = 'completed'");
}

// ── Order status update timestamp (12-hour display on client) ────────────────
$statusUpdatedAtCol = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'status_updated_at'");
if ($statusUpdatedAtCol && $statusUpdatedAtCol->num_rows === 0) {
    $conn->query("ALTER TABLE `orders` ADD COLUMN `status_updated_at` DATETIME DEFAULT NULL AFTER `status`");
    // Backfill: existing orders were last touched when they were created
    $conn->query("UPDATE `orders` SET `status_updated_at` = `created_at` WHERE `status_updated_at` IS NULL");
}

// ── Order status history (permanent per-status update times shown to client) ─
$conn->query("
    CREATE TABLE IF NOT EXISTS `order_status_history` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `order_number` varchar(50) NOT NULL,
      `status` varchar(50) NOT NULL,
      `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_order_status` (`order_number`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");
// One-time seed: fill ONLY when the table is still empty, so the real
// per-status timestamps written by the admin endpoints are never overwritten
// with approximate backfill values on later requests
$histCount = $conn->query("SELECT COUNT(*) AS c FROM `order_status_history`");
if ($histCount && (int)$histCount->fetch_assoc()['c'] === 0) {
    $conn->query("INSERT IGNORE INTO `order_status_history` (`order_number`, `status`, `changed_at`)
                  SELECT o.order_number, o.status, COALESCE(o.status_updated_at, o.created_at)
                  FROM `orders` o
                  WHERE o.order_number IS NOT NULL AND o.order_number <> ''");
}

// ── Payment method and status columns for orders ─────────────────────────────
$paymentMethodCol = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'payment_method'");
if ($paymentMethodCol && $paymentMethodCol->num_rows === 0) {
    $conn->query("ALTER TABLE `orders` ADD COLUMN `payment_method` VARCHAR(50) DEFAULT NULL AFTER `status`");
}
$paymentStatusCol = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'payment_status'");
if ($paymentStatusCol && $paymentStatusCol->num_rows === 0) {
    $conn->query("ALTER TABLE `orders` ADD COLUMN `payment_status` ENUM('Pending','Paid','Failed') NOT NULL DEFAULT 'Pending' AFTER `payment_method`");
}
$transactionUuidCol = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'transaction_uuid'");
if ($transactionUuidCol && $transactionUuidCol->num_rows === 0) {
    $conn->query("ALTER TABLE `orders` ADD COLUMN `transaction_uuid` VARCHAR(100) DEFAULT NULL AFTER `payment_status`");
}
$transactionCodeCol = $conn->query("SHOW COLUMNS FROM `orders` LIKE 'transaction_code'");
if ($transactionCodeCol && $transactionCodeCol->num_rows === 0) {
    $conn->query("ALTER TABLE `orders` ADD COLUMN `transaction_code` VARCHAR(100) DEFAULT NULL AFTER `transaction_uuid`");
}
// Only runs once: if any lowercase 'pending' rows exist, migrate them all
$lcCheck = $conn->query("SELECT COUNT(*) FROM `bookings` WHERE `status` = 'pending'");
if ($lcCheck && $lcCheck->fetch_row()[0] > 0) {
    $conn->query("UPDATE `bookings` SET `status` = 'Pending'    WHERE `status` = 'pending'");
    $conn->query("UPDATE `bookings` SET `status` = 'Confirmed'  WHERE `status` = 'confirmed'");
    $conn->query("UPDATE `bookings` SET `status` = 'Cancelled'  WHERE `status` = 'cancelled'");
    $conn->query("UPDATE `bookings` SET `status` = 'Completed'  WHERE `status` = 'completed'");
}

// ═══════════════════════════════════════════════════════════════════════════
//  Multi-role foundation (Phase 1) — roles, kitchen tables, role notifications
//  All idempotent, same guarded-migration style as the blocks above.
// ═══════════════════════════════════════════════════════════════════════════

// ── Widen users.user_type so panel roles can exist ───────────────────────────
// customer = 'user' (unchanged). We standardize on 'staff' (never legacy 'waiter').
// Idempotent: only touches the column when it is missing 'staff' or still carries
// 'waiter'. When correcting, we first widen to a superset (so remapping legacy
// 'waiter' rows is always valid), migrate those rows, then settle on the final enum.
$utCol = $conn->query("SHOW COLUMNS FROM `users` LIKE 'user_type'");
if ($utCol && ($utRow = $utCol->fetch_assoc())) {
    $utType     = (string)$utRow['Type'];
    $needsStaff = stripos($utType, "'staff'")  === false;
    $hasWaiter  = stripos($utType, "'waiter'") !== false;
    if ($needsStaff || $hasWaiter) {
        // Superset (both staff and waiter) so the waiter→staff UPDATE never fails.
        $conn->query("ALTER TABLE `users` MODIFY `user_type` ENUM('user','admin','manager','staff','chef','waiter','rider') NOT NULL DEFAULT 'user'");
        $conn->query("UPDATE `users` SET `user_type` = 'staff' WHERE `user_type` = 'waiter'");
        // Final canonical enum (drops 'waiter').
        $conn->query("ALTER TABLE `users` MODIFY `user_type` ENUM('user','admin','manager','staff','chef','rider') NOT NULL DEFAULT 'user'");
    }
}

// ── Role-scoped notifications (chef now; staff/rider reuse later) ─────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS `role_notifications` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `role` VARCHAR(20) NOT NULL,
      `type` VARCHAR(30) NOT NULL DEFAULT 'general',
      `title` VARCHAR(150) NOT NULL,
      `message` VARCHAR(255) DEFAULT NULL,
      `resource_id` VARCHAR(50) DEFAULT NULL,
      `url` VARCHAR(255) DEFAULT NULL,
      `is_read` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_role_read_time` (`role`, `is_read`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// ── Kitchen notes (chef pin-board: kitchen / order / table / menu scoped) ─────
$conn->query("
    CREATE TABLE IF NOT EXISTS `kitchen_notes` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `note` TEXT NOT NULL,
      `scope` ENUM('kitchen','order','table','menu') NOT NULL DEFAULT 'kitchen',
      `ref_id` VARCHAR(50) DEFAULT NULL,
      `created_by` INT(11) DEFAULT NULL,
      `created_by_name` VARCHAR(100) DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_scope_time` (`scope`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// ── Ingredients / kitchen stock ───────────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS `ingredients` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `name` VARCHAR(120) NOT NULL,
      `unit` VARCHAR(20) NOT NULL DEFAULT 'unit',
      `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      `low_threshold` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      `status` ENUM('Available','Low','Out') NOT NULL DEFAULT 'Available',
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// ── One-time demo panel accounts (DEV ONLY — change these passwords) ──────────
//   chef@merobhoj.com / Chef@123 · staff@merobhoj.com / Staff@123 · rider@merobhoj.com / Rider@123
// Guarded by the seed emails themselves (not the role set) so pre-existing
// panel accounts under other emails don't suppress a still-missing seed row;
// fires only while fewer than all three exist, so password_hash() stays rare.
$panelSeedCheck = $conn->query("SELECT COUNT(*) AS c FROM `users` WHERE `email` IN ('chef@merobhoj.com','staff@merobhoj.com','rider@merobhoj.com')");
if ($panelSeedCheck && (int)$panelSeedCheck->fetch_assoc()['c'] < 3) {
    $seedUsers = [
        ['Kitchen Chef',   'chef@merobhoj.com',  'Chef@123',  'chef'],
        ['Floor Staff',    'staff@merobhoj.com', 'Staff@123', 'staff'],
        ['Delivery Rider', 'rider@merobhoj.com', 'Rider@123', 'rider'],
    ];
    $seedStmt = $conn->prepare("INSERT INTO `users` (`name`, `email`, `password`, `user_type`) VALUES (?, ?, ?, ?)");
    if ($seedStmt) {
        foreach ($seedUsers as [$sName, $sEmail, $sPass, $sRole]) {
            // email is UNIQUE — skip if a row already claimed it
            $exists = $conn->prepare("SELECT id FROM `users` WHERE `email` = ? LIMIT 1");
            $exists->bind_param('s', $sEmail);
            $exists->execute();
            $existsRes = $exists->get_result();
            $already = $existsRes && $existsRes->num_rows > 0;
            $exists->close();
            if ($already) { continue; }
            $hash = password_hash($sPass, PASSWORD_DEFAULT);
            $seedStmt->bind_param('ssss', $sName, $sEmail, $hash, $sRole);
            $seedStmt->execute();
        }
        $seedStmt->close();
    }
}

// ═══════════════════════════════════════════════════════════════════════════
//  Rider / Delivery (Phase 3) — dispatch, OTP handover, live GPS
//  All idempotent. `orders` is NOT altered: delivery rows are created lazily
//  on read by ensure_delivery_rows() in includes/delivery_helpers.php, so no
//  existing status endpoint needs to change.
// ═══════════════════════════════════════════════════════════════════════════

// ── Deliveries: assignment + OTP + fee + timestamps ONLY ─────────────────────
// Deliberately has NO status column — every rider-facing state is derived from
// orders.status + rider_id (see delivery_state()), so the rider view, the
// front-of-house view and the customer tracker can never drift apart.
$conn->query("
    CREATE TABLE IF NOT EXISTS `deliveries` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `order_number` VARCHAR(50) NOT NULL,
      `rider_id` INT(11) DEFAULT NULL,
      `otp` CHAR(4) NOT NULL,
      `otp_attempts` TINYINT(4) NOT NULL DEFAULT 0,
      `delivery_fee` DECIMAL(10,2) NOT NULL DEFAULT 50.00,
      `dest_lat` DECIMAL(10,7) DEFAULT NULL,
      `dest_lng` DECIMAL(10,7) DEFAULT NULL,
      `assigned_at` DATETIME DEFAULT NULL,
      `picked_up_at` DATETIME DEFAULT NULL,
      `completed_at` DATETIME DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_order_number` (`order_number`),
      KEY `idx_rider` (`rider_id`),
      KEY `idx_completed` (`completed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// ── Latest GPS fix per rider (upserted, NOT a growing breadcrumb log) ─────────
// One row per rider keeps the table bounded; updated_at gives the customer map
// the fix age it needs to decide real-GPS vs simulated movement.
$conn->query("
    CREATE TABLE IF NOT EXISTS `rider_locations` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `rider_id` INT(11) NOT NULL,
      `lat` DECIMAL(10,7) NOT NULL,
      `lng` DECIMAL(10,7) NOT NULL,
      `accuracy_m` INT(11) DEFAULT NULL,
      `heading` DECIMAL(6,2) DEFAULT NULL,
      `speed_kmh` DECIMAL(6,2) DEFAULT NULL,
      `order_number` VARCHAR(50) DEFAULT NULL,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_rider` (`rider_id`),
      KEY `idx_order` (`order_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// ── users.phone — lets a rider publish a contact number to the customer ──────
// SHOW COLUMNS then ALTER is check-then-act: under concurrent requests several
// workers can all see the column missing and all issue the ALTER. One wins and
// the rest get "Duplicate column name" — which, with mysqli in exception mode,
// is a fatal on every page that includes this file. Losing that race is a
// success for our purposes (the column exists), so swallow only that error.
$phoneCol = $conn->query("SHOW COLUMNS FROM `users` LIKE 'phone'");
if ($phoneCol && $phoneCol->num_rows === 0) {
    try {
        $conn->query("ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL AFTER `email`");
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() !== 1060) {   // 1060 = ER_DUP_FIELDNAME
            throw $e;
        }
    }
}

// ═══════════════════════════════════════════════════════════════════════════
//  Expenses — money going out (admin/expenses.php)
// ═══════════════════════════════════════════════════════════════════════════

// admin/finance_data.php has always queried this table for
// `date`, `category`, `amount`, `description`, guarded by a try/catch because
// the table never existed — which is why the Finance page reported zero
// expenses, zero input VAT and an overstated net profit. Those four column
// names are therefore load-bearing: renaming any of them silently breaks the
// Tax Summary and the 5000 · Cost of Goods Sold ledger account.
//
// Deliberately has NO status/approval column: an expense here is a recorded
// fact, so every row in a date range counts toward the P&L and finance_data.php
// needs no filtering of its own.
$conn->query("
    CREATE TABLE IF NOT EXISTS `expenses` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `date` DATE NOT NULL,
      `category` VARCHAR(50) NOT NULL DEFAULT 'Miscellaneous',
      `title` VARCHAR(150) NOT NULL,
      `description` TEXT DEFAULT NULL,
      `amount` DECIMAL(10,2) NOT NULL,
      `vendor` VARCHAR(120) DEFAULT NULL,
      `payment_method` VARCHAR(40) NOT NULL DEFAULT 'Cash',
      `reference_no` VARCHAR(60) DEFAULT NULL,
      `recorded_by` INT(11) DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_date` (`date`),
      KEY `idx_category` (`category`),
      KEY `idx_recorded_by` (`recorded_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

// ═══════════════════════════════════════════════════════════════════════════
//  Staff Management & Attendance (admin/staff.php)
// ═══════════════════════════════════════════════════════════════════════════
$conn->query("
    CREATE TABLE IF NOT EXISTS `staff_attendance` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `user_id` INT(11) NOT NULL,
      `attendance_date` DATE NOT NULL,
      `check_in` DATETIME DEFAULT NULL,
      `check_out` DATETIME DEFAULT NULL,
      `status` ENUM('Present', 'Absent', 'Late', 'On Leave') NOT NULL DEFAULT 'Present',
      `working_minutes` INT(11) NOT NULL DEFAULT 0,
      `notes` TEXT DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_user_date` (`user_id`, `attendance_date`),
      KEY `idx_att_date` (`attendance_date`),
      KEY `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

$conn->query("
    CREATE TABLE IF NOT EXISTS `staff_settings` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `shift_type` VARCHAR(30) NOT NULL DEFAULT 'Morning',
      `opening_time` VARCHAR(10) NOT NULL DEFAULT '09:00 AM',
      `closing_time` VARCHAR(10) NOT NULL DEFAULT '05:00 PM',
      `default_working_hours` VARCHAR(50) NOT NULL DEFAULT '09:00 AM - 05:00 PM',
      `late_enabled` TINYINT(1) NOT NULL DEFAULT 1,
      `late_after` VARCHAR(10) NOT NULL DEFAULT '09:15 AM',
      `auto_absent_enabled` TINYINT(1) NOT NULL DEFAULT 0,
      `overtime_enabled` TINYINT(1) NOT NULL DEFAULT 1,
      `minimum_working_minutes` INT(11) NOT NULL DEFAULT 240,
      `grace_period_minutes` INT(11) NOT NULL DEFAULT 15,
      `working_days` VARCHAR(255) NOT NULL DEFAULT 'Sun,Mon,Tue,Wed,Thu,Fri',
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

$initSettings = $conn->query("SELECT COUNT(*) as cnt FROM `staff_settings`");
if ($initSettings && (int)$initSettings->fetch_assoc()['cnt'] === 0) {
    $conn->query("INSERT INTO `staff_settings` (`shift_type`, `opening_time`, `closing_time`, `default_working_hours`, `late_enabled`, `late_after`, `auto_absent_enabled`, `overtime_enabled`, `minimum_working_minutes`, `grace_period_minutes`, `working_days`) VALUES ('Morning', '09:00 AM', '05:00 PM', '09:00 AM - 05:00 PM', 1, '09:15 AM', 0, 1, 240, 15, 'Sun,Mon,Tue,Wed,Thu,Fri')");
}
