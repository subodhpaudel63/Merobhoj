<?php
require_once __DIR__ . '/_api_guard.php';

$data = json_decode(file_get_contents('php://input'), true);

$id = (int)($data['id'] ?? 0);
$newStatus = trim((string)($data['status'] ?? ''));

if ($id <= 0 || !$newStatus) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$validTransitions = [
    'Pending'    => ['Confirmed', 'Cancelled'],
    'Confirmed'  => ['Checked-in', 'Cancelled', 'No-show'],
    'Checked-in' => ['Completed', 'Cancelled'],
    'Completed'  => [],
    'Cancelled'  => [],
    'No-show'    => []
];

$stmt = $conn->prepare("SELECT status, booking_date, end_time FROM bookings WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();

if (!$current) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}

$curStatus = $current['status'];
$allowed = $validTransitions[$curStatus] ?? [];

if (!in_array($newStatus, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => "Cannot transition from {$curStatus} to {$newStatus}"]);
    exit;
}

// Compute grace_end_at on Confirmed
$graceEnd = null;
if ($newStatus === 'Confirmed') {
    // 15 minutes grace window default
    $graceEnd = date('Y-m-d H:i:s', strtotime($current['booking_date'] . ' ' . $current['end_time']) + 900);
}

$upd = $conn->prepare("UPDATE bookings SET status = ?, grace_end_at = ? WHERE id = ?");
$upd->bind_param("ssi", $newStatus, $graceEnd, $id);

if ($upd->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'DB update failed']);
}
