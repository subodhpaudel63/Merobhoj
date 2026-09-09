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
