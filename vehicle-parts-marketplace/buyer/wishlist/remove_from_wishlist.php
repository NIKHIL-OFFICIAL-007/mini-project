<?php
session_start();
include '../../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Login required";
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !in_array('buyer', explode(',', $user['role']))) {
    $_SESSION['error'] = "Buyer role required";
    header("Location: ../../buyer/wishlist.php");
    exit();
}

// Get part ID
$part_id = $_POST['part_id'] ?? null;

if (!$part_id || !is_numeric($part_id)) {
    $_SESSION['error'] = "Invalid part";
    header("Location: ../../buyer/wishlist.php");
    exit();
}

try {
    // Remove from wishlist
    $stmt = $pdo->prepare("DELETE FROM wishlists WHERE user_id = ? AND part_id = ?");
    $stmt->execute([$user_id, $part_id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Item removed from wishlist";
    } else {
        $_SESSION['error'] = "Item not found in wishlist";
    }
} catch (Exception $e) {
    error_log("Wishlist remove failed: " . $e->getMessage());
    $_SESSION['error'] = "Database error. Please try again.";
}

// Redirect back
header("Location: ../../buyer/wishlist.php");
exit();
?>