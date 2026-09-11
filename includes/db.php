<?php
$host = "localhost";
$user = "root";
$password = "";
$db = "alu";

 // This should match your actual database name

// Create connection using MySQLi
$conn = new mysqli($host, $user, $password, $db  );

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set MySQL session timezone to Nepal (+05:45)
$conn->query("SET time_zone = '+05:45'");

foreach ([
    'stock_quantity' => "ALTER TABLE menu ADD stock_quantity INT NOT NULL DEFAULT 40",
    'max_order_quantity' => "ALTER TABLE menu ADD max_order_quantity INT NOT NULL DEFAULT 10",
] as $column => $alter) {
    $result = $conn->query("SHOW COLUMNS FROM menu LIKE '$column'");
    if ($result && $result->num_rows === 0) { $conn->query($alter); }
}
$conn->query("UPDATE menu SET stock_quantity = 40 WHERE stock_quantity IS NULL OR stock_quantity < 0 OR stock_quantity > 40");
$conn->query("UPDATE menu SET max_order_quantity = 10 WHERE max_order_quantity IS NULL OR max_order_quantity < 1 OR max_order_quantity > 10");
$conn->query("UPDATE menu SET menu_status = CASE WHEN stock_quantity = 0 THEN 'Out of Stock' WHEN stock_quantity < 5 THEN 'Low Stock' ELSE 'In Stock' END");
