<?php
require_once __DIR__ . '/_api_guard.php';
require_once __DIR__ . '/../../includes/process_no_shows.php';

// Process No-Shows before returning list
processNoShows($conn);

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $sql = "
        SELECT b.*, t.table_name, t.capacity 
        FROM bookings b 
        LEFT JOIN restaurant_tables t ON b.table_id = t.id 
        ORDER BY b.booking_date DESC, b.booking_time DESC 
        LIMIT 100
    ";
    $result = $conn->query($sql);
    $bookings = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }
    }
    
    // Also return available tables for create booking dropdown
    $tRes = $conn->query("SELECT * FROM restaurant_tables ORDER BY id");
    $tables = [];
    if ($tRes) {
        while ($t = $tRes->fetch_assoc()) {
            $tables[] = $t;
        }
    }

    echo json_encode(['success' => true, 'bookings' => $bookings, 'tables' => $tables]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
