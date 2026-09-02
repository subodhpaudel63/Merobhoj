<?php
/**
 * AJAX endpoint: returns the logged-in user's table bookings as JSON.
 * Used by client/myorder.php for the "Table Bookings" tab.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/process_no_shows.php';

header('Content-Type: application/json');

// Check if user is logged in
$currentUser = getUserFromCookie();
if (!$currentUser) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$email = $currentUser['email'];

// Process any expired no-shows first
processNoShows($conn);

$tz = new DateTimeZone(RESTAURANT_TIMEZONE);
$serverNow = (new DateTime('now', $tz))->format('Y-m-d H:i:s');

// Fetch this user's bookings with table info
$stmt = $conn->prepare(
    "SELECT b.id, b.name, b.email, b.phone, b.table_id, b.booking_date, b.booking_time, 
            b.people, b.message, b.status, b.grace_end_at, b.created_at,
            t.table_name, t.capacity
     FROM bookings b
     LEFT JOIN restaurant_tables t ON b.table_id = t.id
     WHERE b.email = ?
     ORDER BY b.booking_date DESC, b.booking_time DESC"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

$bookings = [];
while ($row = $result->fetch_assoc()) {
    // Format date and time for display
    $dateObj = DateTime::createFromFormat('Y-m-d', $row['booking_date'], $tz);
    $timeObj = DateTime::createFromFormat('H:i:s', $row['booking_time'], $tz);

    $formattedDate = $dateObj ? $dateObj->format('F j, Y') : $row['booking_date'];
    $formattedTime = $timeObj ? $timeObj->format('g:i A') : $row['booking_time'];

    // Compute grace deadline display time (booking_time + grace minutes)
    $graceDeadlineFormatted = '';
    if ($row['grace_end_at']) {
        $graceObj = DateTime::createFromFormat('Y-m-d H:i:s', $row['grace_end_at'], $tz);
        if ($graceObj) {
            $graceDeadlineFormatted = $graceObj->format('g:i A');
        }
    }

    $tableLabel = $row['table_name'] ?? '';
    if ($tableLabel === '') {
        $tableLabel = 'Table ' . ((int)($row['table_id'] ?? 0) > 0 ? (int)$row['table_id'] : 'N/A');
    }

    $bookings[] = [
        'id'                     => (int)$row['id'],
        'name'                   => $row['name'],
        'booking_date'           => $row['booking_date'],
        'booking_time'           => $row['booking_time'],
        'formatted_date'         => $formattedDate,
        'formatted_time'         => $formattedTime,
        'people'                 => (int)$row['people'],
        'table_id'               => (int)($row['table_id'] ?? 0),
        'table_name'             => $tableLabel,
        'table_number'           => $tableLabel,
        'capacity'               => (int)($row['capacity'] ?? 0),
        'status'                 => $row['status'],
        'grace_end_at'           => $row['grace_end_at'],
        'grace_deadline_display' => $graceDeadlineFormatted,
        'message'                => $row['message'],
        'created_at'             => $row['created_at'],
    ];
}
$stmt->close();

echo json_encode([
    'ok'         => true,
    'bookings'   => $bookings,
    'server_now' => $serverNow,
]);
