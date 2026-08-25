<?php
header('Content-Type: application/json');
session_start();
include_once "db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);

    if ($user_id > 0) {
        // Check if this is the main admin account
        $check_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if ($user['email'] === 'subodhpaudel0000@gmail.com') {
                $response = [
                    'success' => false,
                    'message' => 'Cannot delete the main admin account.'
                ];
                
                // Check if this is an AJAX request
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    echo json_encode($response);
                    exit;
                } else {
                    $_SESSION['msg'] = [
                        'type' => 'error',
                        'text' => 'Cannot delete the main admin account.'
                    ];
                    $check_stmt->close();
                    header("Location: /Merobhoj/admin/users.php");
                    exit;
                }
            }
        }
        $check_stmt->close();

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            $response = [
                'success' => true,
                'message' => 'User deleted successfully.'
            ];
            
            // Check if this is an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode($response);
                exit;
            } else {
                $_SESSION['msg'] = [
                    'type' => 'success',
                    'text' => 'User deleted successfully.'
                ];
                header("Location: /Merobhoj/admin/users.php");
                exit;
            }
        } else {
            $response = [
                'success' => false,
                'message' => 'Failed to delete user. Error: ' . $stmt->error
            ];
            
            // Check if this is an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode($response);
                exit;
            } else {
                $_SESSION['msg'] = [
                    'type' => 'error',
                    'text' => 'Failed to delete user. Error: ' . $stmt->error
                ];
                header("Location: /Merobhoj/admin/users.php");
                exit;
            }
        }

        $stmt->close();
    } else {
        $response = [
            'success' => false,
            'message' => 'Invalid user ID.'
        ];
        
        // Check if this is an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode($response);
            exit;
        } else {
            $_SESSION['msg'] = [
                'type' => 'error',
                'text' => 'Invalid user ID.'
            ];
            header("Location: /Merobhoj/admin/users.php");
            exit;
        }
    }
} else {
    $response = [
        'success' => false,
        'message' => 'Invalid request method.'
    ];
    
    // Check if this is an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode($response);
        exit;
    } else {
        $_SESSION['msg'] = [
            'type' => 'error',
            'text' => 'Invalid request method.'
        ];
        header("Location: /Merobhoj/admin/users.php");
        exit;
    }
}
