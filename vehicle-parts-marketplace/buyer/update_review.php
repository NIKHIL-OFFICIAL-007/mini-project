<?php
session_start();
include 'includes/config.php';

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

$review_id = $_POST['review_id'] ?? null;
$rating = (int)$_POST['rating'] ?? 0;
$comment = trim($_POST['comment'] ?? '');

if (!$review_id || !$rating || $rating < 1 || $rating > 5) {
    $_SESSION['error'] = "Invalid rating or review ID.";
    header("Location: view_part.php?id=" . urlencode($_GET['part_id'] ?? 0));
    exit();
}

try {
    // First, verify this review belongs to the current user
    $stmt = $pdo->prepare("SELECT buyer_id FROM reviews WHERE id = ? AND status = 'active'");
    $stmt->execute([$review_id]);
    $review = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$review || $review['buyer_id'] !== $user_id) {
        $_SESSION['error'] = "You don't have permission to update this review.";
        header("Location: view_part.php?id=" . urlencode($_GET['part_id'] ?? 0));
        exit();
    }
    
    // Update the review
    $stmt = $pdo->prepare("UPDATE reviews SET rating = ?, comment = ? WHERE id = ?");
    $stmt->execute([$rating, $comment, $review_id]);

    $_SESSION['success'] = "Your review has been updated successfully!";
} catch (Exception $e) {
    error_log("Failed to update review: " . $e->getMessage());
    $_SESSION['error'] = "Failed to update review. Please try again.";
}

// Redirect back to the part page using the part_id from the form
$part_id = $_POST['part_id'] ?? ($_GET['part_id'] ?? 0);
header("Location: view_part.php?id=" . urlencode($part_id));
exit();
?>