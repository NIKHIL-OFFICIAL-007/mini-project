<?php
// Database configuration
$host = 'localhost';
$dbname = 'vehicle_parts_db';
$username = 'root';
$password = '';

// Only define getPDO() if it doesn't exist
if (!function_exists('getPDO')) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Database connection failed: " . htmlspecialchars($e->getMessage()));
    }

    // Make PDO available globally
    function getPDO() {
        global $pdo;
        return $pdo;
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>