<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/process_no_shows.php';

header('Content-Type: application/json');

// Check if user is logged in
$user = getUserFromCookie();
if (!$user) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = trim($_POST['date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? $_POST['time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $people = (int)($_POST['people'] ?? 0);
    
    if (empty($date) || empty($start_time) || $people <= 0) {
        echo json_encode(['error' => 'Missing required fields']);
        exit;
    }

    if (empty($end_time)) {
        $end_time = date('H:i', strtotime($start_time) + 7200);
    }

    // ── Validate opening hours ───────────────────────────────────────────────
    if (preg_match('/^(\d{2}):(\d{2})/', $start_time, $m)) {
        $hour = (int)$m[1];
        if ($hour < RESTAURANT_OPEN_HOUR || $hour >= RESTAURANT_CLOSE_HOUR) {
            $openFmt  = date('g:i A', mktime(RESTAURANT_OPEN_HOUR, 0));
            $closeFmt = date('g:i A', mktime(RESTAURANT_CLOSE_HOUR, 0));
            echo json_encode([
                'success' => true,
                'tables'  => [],
                'message' => "Restaurant is open from $openFmt to $closeFmt only."
            ]);
            exit;
        }
    }

    // ── Process expired no-shows so their tables become available ─────────────
    processNoShows($conn);
    
    // A table is unavailable if it is booked for an overlapping time slot,
    // and the booking status is Pending, Confirmed, or Checked-in.
    $query = "
        SELECT rt.id, rt.table_name, rt.capacity 
        FROM restaurant_tables rt
        WHERE rt.capacity >= ? 
        AND rt.id NOT IN (
            SELECT table_id FROM bookings 
            WHERE booking_date = ? 
              AND status IN ('Pending', 'Confirmed', 'Checked-in')
              AND start_time < ? 
              AND end_time > ?
        )
        ORDER BY rt.capacity ASC, rt.id ASC
    ";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("isss", $people, $date, $end_time, $start_time);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $tables = [];
        while ($row = $result->fetch_assoc()) {
            $tables[] = [
                'id' => $row['id'],
                'name' => $row['table_name'],
                'capacity' => $row['capacity']
            ];
        }
        
        echo json_encode(['success' => true, 'tables' => $tables]);
        $stmt->close();
    } else {
        echo json_encode(['error' => 'Database error']);
    }
} else {
    echo json_encode(['error' => 'Invalid request method']);
}
$conn->close();
