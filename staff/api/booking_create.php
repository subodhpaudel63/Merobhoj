<?php
require_once __DIR__ . '/_api_guard.php';

$data = json_decode(file_get_contents('php://input'), true);

$name = trim((string)($data['name'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$phone = trim((string)($data['phone'] ?? ''));
$tableId = (int)($data['table_id'] ?? 0);
$guests = (int)($data['guests'] ?? 1);
$date = trim((string)($data['booking_date'] ?? ''));
$time = trim((string)($data['booking_time'] ?? ''));

if (!$name || !$phone || $tableId <= 0 || !$date || !$time) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

$startTime = $time;
$endTime = date('H:i:s', strtotime($time) + 7200); // 2 hr default duration

// Check overlapping bookings for table
$chk = $conn->prepare("
    SELECT id FROM bookings 
    WHERE table_id = ? 
      AND booking_date = ? 
      AND status IN ('Pending', 'Confirmed', 'Checked-in')
      AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?))
");
$chk->bind_param("isssss", $tableId, $date, $endTime, $startTime, $startTime, $endTime);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Table is already booked during this time window']);
    exit;
}

// Calculate grace_end_at (+20 mins)
$graceEndAt = date('Y-m-d H:i:s', strtotime($date . ' ' . $startTime) + (20 * 60));

// Insert Confirmed booking
$ins = $conn->prepare("
    INSERT INTO bookings (name, email, phone, table_id, people, booking_date, booking_time, start_time, end_time, status, grace_end_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Confirmed', ?)
");
$ins->bind_param("sssiisssss", $name, $email, $phone, $tableId, $guests, $date, $time, $startTime, $endTime, $graceEndAt);

if ($ins->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create booking: ' . $conn->error]);
}
