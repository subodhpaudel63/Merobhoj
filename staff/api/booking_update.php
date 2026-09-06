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
$status = trim((string)($data['status'] ?? ''));

if ($id <= 0 || !$name || !$phone || $tableId <= 0 || !$date || !$time) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    exit;
}

$startTime = $time;
$endTime = date('H:i:s', strtotime($time) + 7200); // 2 hr default
$graceEndAt = date('Y-m-d H:i:s', strtotime($date . ' ' . $startTime) + (20 * 60));

// Check overlap excluding current booking
$chk = $conn->prepare("
    SELECT id FROM bookings 
    WHERE table_id = ? 
      AND booking_date = ? 
      AND id != ?
      AND status IN ('Pending', 'Confirmed', 'Checked-in')
      AND ((start_time < ? AND end_time > ?) OR (start_time < ? AND end_time > ?))
");
$chk->bind_param("isissss", $tableId, $date, $id, $endTime, $startTime, $startTime, $endTime);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Selected table is already booked during this time window']);
    exit;
}

if ($status) {
    $upd = $conn->prepare("
        UPDATE bookings 
        SET name = ?, email = ?, phone = ?, table_id = ?, people = ?, booking_date = ?, booking_time = ?, start_time = ?, end_time = ?, grace_end_at = ?, status = ?
        WHERE id = ?
    ");
    $upd->bind_param("sssiissssssi", $name, $email, $phone, $tableId, $guests, $date, $time, $startTime, $endTime, $graceEndAt, $status, $id);
} else {
    $upd = $conn->prepare("
        UPDATE bookings 
        SET name = ?, email = ?, phone = ?, table_id = ?, people = ?, booking_date = ?, booking_time = ?, start_time = ?, end_time = ?, grace_end_at = ?
        WHERE id = ?
    ");
    $upd->bind_param("sssiisssssi", $name, $email, $phone, $tableId, $guests, $date, $time, $startTime, $endTime, $graceEndAt, $id);
}

if ($upd->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update booking']);
}
