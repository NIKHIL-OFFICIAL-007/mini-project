<?php
session_start();
include '../includes/config.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('admin', $roles)) {
    header("Location: ../login.php");
    exit();
}

// Get part ID from URL
$part_id = $_GET['id'] ?? 0;

if ($part_id > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE parts SET status = 'hidden' WHERE id = ?");
        $stmt->execute([$part_id]);
        
        // Log the action
        error_log("Admin {$_SESSION['user_id']} hid part {$part_id}");
    } catch (Exception $e) {
        error_log("Failed to hide part: " . $e->getMessage());
    }
}

// Redirect back to manage parts page
header("Location: manage_parts.php");
exit();
?>