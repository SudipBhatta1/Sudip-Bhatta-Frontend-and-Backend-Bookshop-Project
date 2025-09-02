<?php
// Database configuration
$host = 'localhost';
$dbname = 'bookshop';
$username = 'root';
$password = '';

// PDO options for better security and error handling
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, $options);
    
    // Set timezone
    $pdo->exec("SET time_zone = '+00:00'");
    
} catch(PDOException $e) {
    // Log error (in production, don't show detailed error)
    error_log("Database connection failed: " . $e->getMessage());
    die("Connection failed. Please check your database configuration.");
}

?>

