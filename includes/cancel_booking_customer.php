<?php
/**
 * AJAX endpoint: allows a logged-in customer to cancel their OWN booking.
 * Only 'Pending' bookings can be cancelled by the customer; ownership and
 * cancellable status are enforced directly in the UPDATE statement.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';

header('Content-Type: application/json');

$currentUser = getUserFromCookie();
if (!$currentUser) {
    echo json_encode(['ok' => false, 'message' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$bookingId = (int)($input['id'] ?? 0);
if ($bookingId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid booking ID']);
    exit;
}

$email = $currentUser['email'];

// Ownership check (email = ?) and status guard (Pending only) in the query itself,
// so a customer can never cancel someone else's booking or an already-confirmed one.
$stmt = $conn->prepare(
    "UPDATE bookings SET status = 'Cancelled'
     WHERE id = ? AND email = ? AND status = 'Pending'"
);
$stmt->bind_param("is", $bookingId, $email);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($affected > 0) {
    echo json_encode(['ok' => true, 'message' => 'Booking cancelled successfully']);
} else {
    echo json_encode(['ok' => false, 'message' => 'This booking can no longer be cancelled. Only pending bookings can be cancelled.']);
}
