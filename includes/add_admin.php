<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
    $user_type = $_POST['user_type'] ?? 'user';

    // Validate input
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Invalid email address.'];
        header("Location: /Merobhoj/admin/users.php");
        exit;
    }
    
    if (strlen(trim($_POST['password'])) < 6) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Password must be at least 6 characters long.'];
        header("Location: /Merobhoj/admin/users.php");
        exit;
    }

    // Validate user type
    $allowed_user_types = ['user', 'admin', 'manager', 'chef', 'waiter', 'rider'];
    if (!in_array($user_type, $allowed_user_types, true)) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Invalid user type.'];
        header("Location: /Merobhoj/admin/users.php");
        exit;
    }

    // Check if email already exists
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Email already exists. Please use a different email.'];
        $check_stmt->close();
        $conn->close();
        header("Location: /Merobhoj/admin/users.php");
        exit;
    }
    $check_stmt->close();

    $stmt = $conn->prepare("INSERT INTO users (email, password, user_type) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $password, $user_type);
    
    if ($stmt->execute()) {
        $_SESSION['msg'] = ['type' => 'success', 'text' => ucfirst($user_type) . ' added successfully!'];
    } else {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Failed to add user: ' . $stmt->error];
    }

    $stmt->close();
    $conn->close();

    header("Location: /Merobhoj/admin/users.php");
    exit;
}

// If not POST request, redirect back
header("Location: /Merobhoj/admin/users.php");
exit;
?>