<?php
header('Content-Type: application/json');
session_start();
include_once "db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $role = $_POST['role'] ?? '';

    // Validate role
    $allowed_roles = ['user', 'admin', 'manager', 'chef', 'waiter', 'rider'];
    if (!in_array($role, $allowed_roles, true)) {
        $response = ['success' => false, 'message' => 'Invalid role.'];
        
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode($response);
            exit;
        } else {
            $_SESSION['msg'] = ['type' => 'error', 'text' => 'Invalid role.'];
            header("Location: /Merobhoj/admin/users.php");
            exit;
        }
    }

    if ($user_id > 0) {
        $stmt = $conn->prepare("UPDATE users SET user_type = ? WHERE id = ?");
        $stmt->bind_param("si", $role, $user_id);
        
        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'User role updated successfully.'];
            
            // Check if this is an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode($response);
                exit;
            } else {
                $_SESSION['msg'] = ['type' => 'success', 'text' => 'User role updated successfully.'];
                header("Location: /Merobhoj/admin/users.php");
                exit;
            }
        } else {
            $response = ['success' => false, 'message' => 'Failed to update user role.'];
            
            // Check if this is an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode($response);
                exit;
            } else {
                $_SESSION['msg'] = ['type' => 'error', 'text' => 'Failed to update user role.'];
                header("Location: /Merobhoj/admin/users.php");
                exit;
            }
        }
        $stmt->close();
    } else {
        $response = ['success' => false, 'message' => 'Invalid user ID.'];
        
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode($response);
            exit;
        } else {
            $_SESSION['msg'] = ['type' => 'error', 'text' => 'Invalid user ID.'];
            header("Location: /Merobhoj/admin/users.php");
            exit;
        }
    }
} else {
    $response = ['success' => false, 'message' => 'Invalid request method.'];
    
    // Check if this is an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode($response);
        exit;
    } else {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Invalid request method.'];
        header("Location: /Merobhoj/admin/users.php");
        exit;
    }
}
?>