<?php
session_start();
include_once "db.php";

if (isset($_POST['upload'])) {
    $targetDir = "../assets/img/gallery/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    $file = $_FILES['image']['tmp_name'];
    $targetFile = $targetDir . basename($_FILES['image']['name']);

    if (move_uploaded_file($file, $targetFile)) {
        // Check if the gallery table exists before attempting insert
        $tableCheck = $conn->query("SHOW TABLES LIKE 'gallery'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            // Use prepared statement to avoid SQL injection
            $stmt = $conn->prepare("INSERT INTO gallery (file_path) VALUES (?)");
            if ($stmt) {
                $stmt->bind_param("s", $targetFile);
                $stmt->execute();
                $stmt->close();
            }
        }
        $_SESSION['msg'] = ['type' => 'success', 'text' => 'Image uploaded successfully!'];
        header("Location: /Merobhoj/admin/index.php");
        exit();
    } else {
        $_SESSION['msg'] = ['type' => 'error', 'text' => 'Failed to upload image.'];
        header("Location: /Merobhoj/admin/index.php");
        exit();
    }
}
