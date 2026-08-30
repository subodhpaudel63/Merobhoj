<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/process_no_shows.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Check if user is logged in
$user = getUserFromCookie();

// If user is not logged in, redirect to login
if (!$user) {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Please login to book a table.'];
    header("Location: /Merobhoj/login.php?action=book_table");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Prefer the currently logged-in user's email so the booking is attached to the account
    // that is viewing the reservation form. This prevents bookings from being hidden in
    // the "My Bookings" list when a different email is typed in the form.
    $name       = trim($_POST['name'] ?? '');
    $email      = trim($user['email'] ?? ($_POST['email'] ?? ''));
    $phone      = trim($_POST['phone'] ?? '');
    $date       = trim($_POST['date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? $_POST['time'] ?? '');
    $end_time   = trim($_POST['end_time'] ?? '');
    if (preg_match('/\b(?:AM|PM)\b/i', $start_time)) {
        $start_time = date('H:i', strtotime($start_time));
    }
    if (!empty($end_time) && preg_match('/\b(?:AM|PM)\b/i', $end_time)) {
        $end_time = date('H:i', strtotime($end_time));
    }
    $people     = (int)($_POST['people'] ?? 0);
    $message    = trim($_POST['message'] ?? '');
    $table_id   = (int)($_POST['table_id'] ?? 0);

    // Debug: Log received data
    error_log("Booking data received: " . print_r($_POST, true));

    // ── Validate Name ────────────────────────────────────────────────────────
    if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Please enter a valid name (2-100 characters).'];
        redirect_user();
        exit;
    }

    // ── Validate Email ───────────────────────────────────────────────────────
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Please enter a valid email address.'];
        redirect_user();
        exit;
    }

    // ── Validate Nepal Phone ─────────────────────────────────────────────────
    // Nepal mobile: +977 or 977 followed by 9[6-8]XXXXXXXX (10 digits)
    $phoneClean = preg_replace('/[\s\-]/', '', $phone);
    if (empty($phoneClean) || !preg_match('/^(\+?977)?9[6-8]\d{8}$/', $phoneClean)) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Please enter a valid Nepal phone number (e.g., 98XXXXXXXX or +977-98XXXXXXXX).'];
        redirect_user();
        exit;
    }

    // ── Validate Date ────────────────────────────────────────────────────────
    $tz = new DateTimeZone(RESTAURANT_TIMEZONE);
    $bookingDate = DateTime::createFromFormat('Y-m-d', $date, $tz);
    $today = new DateTime('today', $tz);
    $dateErrors = DateTime::getLastErrors();
    if (!$bookingDate || $dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Please choose a valid booking date.'];
        redirect_user();
        exit;
    }

    if ($bookingDate < $today) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'You cannot book a table for a past date.'];
        redirect_user();
        exit;
    }

    // ── Validate Time ────────────────────────────────────────────────────────
    if (!preg_match('/^\d{2}:\d{2}$/', $start_time)) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Please enter a valid start time.'];
        redirect_user();
        exit;
    }

    if (empty($end_time)) {
        $end_time = date('H:i', strtotime($start_time) + 7200);
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $end_time)) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Please enter a valid end time.'];
        redirect_user();
        exit;
    }

    if ($start_time >= $end_time) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'End time must be after start time.'];
        redirect_user();
        exit;
    }

    // Prevent midnight rollover bookings; restaurant reservations are same-day only.
    $endTimeParts = explode(':', $end_time);
    $endHour = (int)$endTimeParts[0];
    $endMin = (int)$endTimeParts[1];
    if ($endHour < RESTAURANT_OPEN_HOUR || $endHour > RESTAURANT_CLOSE_HOUR || ($endHour === RESTAURANT_CLOSE_HOUR && $endMin > 0)) {
        $openFormatted = date('g:i A', mktime(RESTAURANT_OPEN_HOUR, 0));
        $closeFormatted = date('g:i A', mktime(RESTAURANT_CLOSE_HOUR, 0));
        $_SESSION['msg'] = ['type' => 'error', 'text' => "We are open from $openFormatted to $closeFormatted. Please choose an end time within these hours."];
        redirect_user();
        exit;
    }

    $timeParts = explode(':', $start_time);
    $hour = (int)$timeParts[0];

    if ($hour < RESTAURANT_OPEN_HOUR || $hour >= RESTAURANT_CLOSE_HOUR) {
        $openFormatted = date('g:i A', mktime(RESTAURANT_OPEN_HOUR, 0));
        $closeFormatted = date('g:i A', mktime(RESTAURANT_CLOSE_HOUR, 0));
        $_SESSION['msg'] = ['type' => 'error', 'text' => "We are open from $openFormatted to $closeFormatted. Please choose a start time within these hours."];
        redirect_user();
        exit;
    }

    // If booking is today, check if the start time hasn't already passed
    $now = new DateTime('now', $tz);
    $bookingDateTime = DateTime::createFromFormat('Y-m-d H:i', "$date $start_time", $tz);
    if ($bookingDate->format('Y-m-d') === $today->format('Y-m-d') && $bookingDateTime <= $now) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'You cannot book a table for a time that has already passed.'];
        redirect_user();
        exit;
    }

    // ── Validate People ──────────────────────────────────────────────────────
    if ($people < 1 || $people > 8) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Number of people must be between 1 and 8.'];
        redirect_user();
        exit;
    }

    // ── Validate Table ───────────────────────────────────────────────────────
    if ($table_id <= 0) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Please select a table.'];
        redirect_user();
        exit;
    }

    // ── Process any expired no-shows before checking availability ────────────
    processNoShows($conn);

    // Begin transaction for concurrency protection
    $conn->begin_transaction();
    
    try {
        // Check if table exists and has capacity
        $stmt = $conn->prepare("SELECT capacity FROM restaurant_tables WHERE id = ?");
        $stmt->bind_param("i", $table_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Selected table does not exist.");
        }
        $tableData = $result->fetch_assoc();
        $stmt->close();
        
        if ($people > $tableData['capacity']) {
            throw new Exception("The selected table cannot accommodate this number of people.");
        }
        
        // Check availability strictly for the given date/time with a lock.
        // Include 'Checked-in' since that table is still occupied.
        $stmt = $conn->prepare(
            "SELECT id FROM bookings 
             WHERE table_id = ? AND booking_date = ? 
             AND status IN ('Pending', 'Confirmed', 'Checked-in') 
             AND start_time < ? 
             AND end_time > ? 
             FOR UPDATE"
        );
        $stmt->bind_param("isss", $table_id, $date, $end_time, $start_time);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception("Sorry, this table was just booked by another customer. Please select another table or time.");
        }
        $stmt->close();
        
        // Insert booking with 'Pending' status (Title Case)
        $timeForDb = $start_time . ':00'; // Ensure HH:MM:SS format
        $endTimeForDb = $end_time . ':00';
        $stmt = $conn->prepare(
            "INSERT INTO bookings (name, email, phone, table_id, booking_date, booking_time, start_time, end_time, people, message, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')"
        );
        $stmt->bind_param("sssissssis", $name, $email, $phone, $table_id, $date, $timeForDb, $timeForDb, $endTimeForDb, $people, $message);
        
        if ($stmt->execute()) {
            $conn->commit();
            $_SESSION['msg'] = ['type' => 'success', 'text' => 'Your table booking has been submitted successfully.'];
            error_log("Booking successful for: " . $name . " (" . $email . ")");
            $stmt->close();
               $conn->close();

    header('Location: /Merobhoj/client/myorder.php'); // redirection to myorder.php after sucessfull booking.
        } else {
            throw new Exception("Booking failed: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['msg'] = ['type' => 'error', 'text' => $e->getMessage()];
        error_log("Booking failed: " . $e->getMessage());
    }

    $conn->close();
} else {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Invalid request method'];
}

redirect_user();

// Function to redirect user
function redirect_user()
{
    // Check if the referrer is from client directory
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '/client/') !== false) {
        header("Location: ../client/index.php");
    } else {
        header("Location: ../index.php");
    }
    exit;
}
?>
