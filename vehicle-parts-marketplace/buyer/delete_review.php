<?php
session_start();
include '../includes/config.php';

// ✅ Check if user is logged in and has buyer role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$roles = explode(',', $_SESSION['role']);
if (!in_array('buyer', $roles)) {
    header("Location: ../login.php");
    exit();
}

$review_id = $_GET['id'] ?? null;
$part_id = $_GET['part_id'] ?? null;

if (!$review_id || !$part_id) {
    header("Location: ../browse_parts.php");
    exit();
}

try {
    // ✅ Hard delete: Remove the review permanently from the database
    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ? AND buyer_id = ?");
    $stmt->execute([$review_id, $user_id]);

    // Check if any row was actually deleted (to avoid false success)
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "Your review has been successfully deleted.";
    } else {
        $_SESSION['error'] = "Review not found or you don't have permission to delete it.";
    }

} catch (Exception $e) {
    error_log("Failed to delete review: " . $e->getMessage());
    $_SESSION['error'] = "Failed to delete review. Please try again.";
}

// Redirect back to the part page
header("Location: view_part.php?id=" . urlencode($part_id));
exit();
?>