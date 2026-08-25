<?php
session_start();
include_once "db.php";  // Adjust path as necessary

// Get POST variables safely
$menu_id = isset($_POST['menu_id']) ? intval($_POST['menu_id']) : 0;
$menu_name = $_POST['menu_name'] ?? '';
$menu_description = $_POST['menu_description'] ?? '';
$menu_price = $_POST['menu_price'] ?? '';
$menu_category = $_POST['menu_category'] ?? '';
$existing_image = $_POST['existing_image'] ?? ''; // e.g. 'menu_12345.jpg'

// Validate required fields
if (empty($menu_name) || empty($menu_price) || empty($menu_category)) {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Menu name, price, and category are required.'];
    header("Location: /Merobhoj/admin/menu.php");
    exit();
}

// Directory to store images
$upload_dir = '../assets/img/menu/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Start with existing image by default
$new_image_filename = $existing_image;

// Handle image upload
if (isset($_FILES['menu_image']) && $_FILES['menu_image']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_tmp = $_FILES['menu_image']['tmp_name'];
    $file_type = mime_content_type($file_tmp);

    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Invalid image type. Allowed: JPG, PNG, GIF.'];
        header("Location: /Merobhoj/admin/menu.php");
        exit();
    }

    $file_ext = pathinfo($_FILES['menu_image']['name'], PATHINFO_EXTENSION);
    $new_filename = uniqid('menu_', true) . '.' . $file_ext;
    $destination = $upload_dir . $new_filename;

    if (move_uploaded_file($file_tmp, $destination)) {
        // Delete old image if different
        if ($existing_image && file_exists($upload_dir . $existing_image) && $existing_image !== $new_filename) {
            unlink($upload_dir . $existing_image);
        }
        $new_image_filename = $new_filename;
    } else {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Failed to upload the new image.'];
        header("Location: /Merobhoj/admin/menu.php");
        exit();
    }
}

// Prepare SQL based on whether image changed
if ($new_image_filename === $existing_image) {
    $stmt = $conn->prepare("UPDATE menu SET menu_name = ?, menu_description = ?, menu_price = ?, menu_category = ? WHERE menu_id = ?");
    $stmt->bind_param("ssdsi", $menu_name, $menu_description, $menu_price, $menu_category, $menu_id);
} else {
    $stmt = $conn->prepare("UPDATE menu SET menu_name = ?, menu_description = ?, menu_price = ?, menu_category = ?, menu_image = ? WHERE menu_id = ?");
    $stmt->bind_param("ssdssi", $menu_name, $menu_description, $menu_price, $menu_category, $new_image_filename, $menu_id);
}

// Execute and handle result
if ($stmt->execute()) {
    $_SESSION['msg'] = ['type' => 'success', 'text' => 'Menu item updated successfully.'];
} else {
    $_SESSION['msg'] = ['type' => 'error', 'text' => 'Database error: ' . $stmt->error];
}

$stmt->close();
$conn->close();

header("Location: /Merobhoj/admin/menu.php");
exit();
