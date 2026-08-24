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
      PRIMARY KEY (`id`)
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

// ── Normalize statuses to Title Case ─────────────────────────────────────────
// Only runs once: if any lowercase 'pending' rows exist, migrate them all
$lcCheck = $conn->query("SELECT COUNT(*) FROM `bookings` WHERE `status` = 'pending'");
if ($lcCheck && $lcCheck->fetch_row()[0] > 0) {
    $conn->query("UPDATE `bookings` SET `status` = 'Pending'    WHERE `status` = 'pending'");
    $conn->query("UPDATE `bookings` SET `status` = 'Confirmed'  WHERE `status` = 'confirmed'");
    $conn->query("UPDATE `bookings` SET `status` = 'Cancelled'  WHERE `status` = 'cancelled'");
    $conn->query("UPDATE `bookings` SET `status` = 'Completed'  WHERE `status` = 'completed'");
}
?>
