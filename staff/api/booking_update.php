<?php
require_once __DIR__ . '/_api_guard.php';

$data = json_decode(file_get_contents('php://input'), true);

$id = (int)($data['id'] ?? 0);
$name = trim((string)($data['name'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$phone = trim((string)($data['phone'] ?? ''));
$tableId = (int)($data['table_id'] ?? 0);
$guests = (int)($data['guests'] ?? 1);
$date = trim((string)($data['booking_date'] ?? ''));
$time = trim((string)($data['booking_time'] ?? ''));
$endTimeInput = trim((string)($data['end_time'] ?? ''));
$message = trim((string)($data['message'] ?? ''));
$status = trim((string)($data['status'] ?? ''));

// Restaurant settings (mirrors config/bootstrap.php; the staff API guard
// does not load it, so fall back to the same values)
if (!defined('RESTAURANT_TIMEZONE'))   { define('RESTAURANT_TIMEZONE', 'Asia/Kathmandu'); }
if (!defined('RESTAURANT_OPEN_HOUR'))  { define('RESTAURANT_OPEN_HOUR', 7);  }  // 7:00 AM
if (!defined('RESTAURANT_CLOSE_HOUR')) { define('RESTAURANT_CLOSE_HOUR', 23); }  // 11:00 PM

if ($id <= 0 || !$name || !$phone || $tableId <= 0 || !$date || !$time) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

// ── Normalize times (accept HH:MM, HH:MM:SS or 12-hour with AM/PM) ──────────
$normTime = static function (string $t): string {
    if (preg_match('/\b(?:AM|PM)\b/i', $t)) {
        $t = date('H:i', strtotime($t));
    }
    return preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $t) ? date('H:i:s', strtotime($t)) : '';
};

$startTime = $normTime($time);
if ($startTime === '') {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid start time']);
    exit;
}

if ($endTimeInput !== '') {
    $endTime = $normTime($endTimeInput);
    if ($endTime === '') {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid end time']);
        exit;
    }
} else {
    // Default 2-hour slot, clamped to closing time
    $defaultEnd = strtotime($startTime) + 7200;
    $closeTs = strtotime(sprintf('%02d:00:00', RESTAURANT_CLOSE_HOUR));
    if ($defaultEnd > $closeTs) { $defaultEnd = $closeTs; }
    $endTime = date('H:i:s', $defaultEnd);
}

if (strtotime($startTime) >= strtotime($endTime)) {
    echo json_encode(['success' => false, 'message' => 'End time must be after the start time']);
    exit;
}

$startHour = (int)substr($startTime, 0, 2);
$endHour   = (int)substr($endTime, 0, 2);
$endMin    = (int)substr($endTime, 3, 2);

if ($startHour < RESTAURANT_OPEN_HOUR || $startHour >= RESTAURANT_CLOSE_HOUR) {
    echo json_encode(['success' => false, 'message' => 'Start time must be within opening hours (7:00 AM - 11:00 PM)']);
    exit;
}
if ($endHour < RESTAURANT_OPEN_HOUR || $endHour > RESTAURANT_CLOSE_HOUR || ($endHour === RESTAURANT_CLOSE_HOUR && $endMin > 0)) {
    echo json_encode(['success' => false, 'message' => 'End time must be within opening hours (7:00 AM - 11:00 PM)']);
    exit;
}

// Date must be a valid calendar date. Past dates are allowed here so
// historical bookings can still be corrected by staff.
$bookingDate = DateTime::createFromFormat('Y-m-d', $date);
if (!$bookingDate || $bookingDate->format('Y-m-d') !== $date) {
    echo json_encode(['success' => false, 'message' => 'Please choose a valid booking date']);
    exit;
}

if ($guests < 1 || $guests > 30) {
    echo json_encode(['success' => false, 'message' => 'Number of guests must be between 1 and 30']);
    exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit;
}
if (strlen($message) > 500) {
    $message = substr($message, 0, 500);
}

// ── Validate the table exists and can seat the party ────────────────────────
$tbl = $conn->prepare("SELECT capacity FROM restaurant_tables WHERE id = ?");
$tbl->bind_param("i", $tableId);
$tbl->execute();
$tblRes = $tbl->get_result();
if ($tblRes->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Selected table does not exist']);
    exit;
}
$capacity = (int)$tblRes->fetch_assoc()['capacity'];
$tbl->close();
if ($guests > $capacity) {
    echo json_encode(['success' => false, 'message' => "Selected table seats {$capacity} guests. Please choose a bigger table"]);
    exit;
}

$graceEndAt = date('Y-m-d H:i:s', strtotime($date . ' ' . $startTime) + (20 * 60));

// Check overlap excluding current booking
$chk = $conn->prepare("
    SELECT id FROM bookings 
    WHERE table_id = ? 
      AND booking_date = ? 
      AND id != ?
      AND status IN ('Pending', 'Confirmed', 'Checked-in')
      AND start_time < ?
      AND end_time > ?
");
$chk->bind_param("iisss", $tableId, $date, $id, $endTime, $startTime);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Selected table is already booked during this time window']);
    exit;
}

if ($status) {
    $upd = $conn->prepare("
        UPDATE bookings 
        SET name = ?, email = ?, phone = ?, table_id = ?, people = ?, booking_date = ?, booking_time = ?, start_time = ?, end_time = ?, message = ?, grace_end_at = ?, status = ?
        WHERE id = ?
    ");
    $upd->bind_param("sssiisssssssi", $name, $email, $phone, $tableId, $guests, $date, $startTime, $startTime, $endTime, $message, $graceEndAt, $status, $id);
} else {
    $upd = $conn->prepare("
        UPDATE bookings 
        SET name = ?, email = ?, phone = ?, table_id = ?, people = ?, booking_date = ?, booking_time = ?, start_time = ?, end_time = ?, message = ?, grace_end_at = ?
        WHERE id = ?
    ");
    $upd->bind_param("sssiissssssi", $name, $email, $phone, $tableId, $guests, $date, $startTime, $startTime, $endTime, $message, $graceEndAt, $id);
}

if ($upd->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update booking']);
}
