<?php
session_start();
include 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('seller', $roles) && !in_array('admin', $roles)) {
    header("Location: ../login.php");
    exit();
}

$review_id = $_POST['review_id'] ?? null;
if (!$review_id || !is_numeric($review_id)) {
    header("Location: manage_reviews.php");
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE reviews SET status = 'hidden' WHERE id = ?");
    $stmt->execute([$review_id]);
    
    $_SESSION['success'] = "Review hidden successfully.";
} catch (Exception $e) {
    error_log("Hide review failed: " . $e->getMessage());
    $_SESSION['error'] = "Failed to hide review.";
}

header("Location: manage_reviews.php");
exit();
?>