<?php
session_start();
require_once 'db.php'; // Your database connection file

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['menu_name']);
    $description = trim($_POST['menu_description']);
    $price = floatval($_POST['menu_price']);
    $category = trim($_POST['menu_category']);

    // Validate fields
    if (empty($name) || empty($description) || $price <= 0 || empty($category)) {
        $_SESSION['msg'] = [
            'type' => 'error',
            'text' => 'All fields are required and price must be greater than zero.'
        ];
        header("Location: /Merobhoj/admin/menu.php");
        exit;
    }

    // Handle image upload
    if (isset($_FILES['menu_image']) && $_FILES['menu_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['menu_image']['tmp_name'];
        $fileName = basename($_FILES['menu_image']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($fileExt, $allowedExts)) {
            $_SESSION['msg'] = [
                'type' => 'error',
                'text' => 'Invalid image format. Allowed: JPG, JPEG, PNG, WEBP.'
            ];
            header("Location: /Merobhoj/admin/menu.php");
            exit;
        }

        $newFileName = uniqid('menu_') . '.' . $fileExt;
        $uploadDir = '../assets/img/menu/';
        $uploadPath = $uploadDir . $newFileName;

        if (!move_uploaded_file($fileTmp, $uploadPath)) {
            $_SESSION['msg'] = [
                'type' => 'error',
                'text' => 'Failed to upload image.'
            ];
            header("Location: /Merobhoj/admin/menu.php");
            exit;
        }
    } else {
        $_SESSION['msg'] = [
            'type' => 'error',
            'text' => 'Image upload failed or no image selected.'
        ];
        header("Location: /Merobhoj/admin/menu.php");
        exit;
    }

    // Save to database
    $stmt = $conn->prepare("INSERT INTO menu (menu_name, menu_description, menu_price, menu_category, menu_image) VALUES (?, ?, ?, ?, ?)");
    $imagePath = 'assets/img/menu/' . $newFileName; // Relative path for frontend use
    $stmt->bind_param("ssdss", $name, $description, $price, $category, $imagePath);

    if ($stmt->execute()) {
        $_SESSION['msg'] = [
            'type' => 'success',
            'text' => 'Menu item added successfully!'
        ];
    } else {
        $_SESSION['msg'] = [
            'type' => 'error',
            'text' => 'Database error: ' . htmlspecialchars($stmt->error)
        ];
    }

    $stmt->close();
    header("Location: /Merobhoj/admin/menu.php");
    exit;
} else {
    header("HTTP/1.1 405 Method Not Allowed");
    echo "Method Not Allowed";
    exit;
}
