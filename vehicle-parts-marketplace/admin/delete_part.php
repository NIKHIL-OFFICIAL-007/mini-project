<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has admin role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('admin', $roles)) {
    header("Location: ../login.php");
    exit();
}

// Get part ID
$part_id = $_GET['id'] ?? null;
if (!$part_id) {
    header("Location: manage_parts.php");
    exit();
}

// Delete part
try {
    $stmt = $pdo->prepare("DELETE FROM parts WHERE id = ?");
    $stmt->execute([$part_id]);

    header("Location: manage_parts.php?message=part_deleted");
    exit();
} catch (Exception $e) {
    error_log("Failed to delete part: " . $e->getMessage());
    header("Location: manage_parts.php?error=delete_failed");
    exit();
}
?>