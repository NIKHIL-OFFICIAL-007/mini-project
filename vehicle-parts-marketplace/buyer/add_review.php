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

$part_id = $_POST['part_id'] ?? null;
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

// Validate input
if (!$part_id || !$rating || $rating < 1 || $rating > 5) {
    $_SESSION['error'] = "Invalid rating or part.";
    header("Location: view_part.php?id=" . urlencode($part_id));
    exit();
}

try {
    // Check if part exists and is active
    $stmt = $pdo->prepare("SELECT id FROM parts WHERE id = ? AND status = 'active'");
    $stmt->execute([$part_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Part not found or inactive.");
    }

    // Prevent duplicate review
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE buyer_id = ? AND part_id = ?");
    $stmt->execute([$user_id, $part_id]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "You have already reviewed this part.";
        header("Location: view_part.php?id=" . urlencode($part_id));
        exit();
    }

    // Insert review
    $stmt = $pdo->prepare("
        INSERT INTO reviews (part_id, buyer_id, rating, comment) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$part_id, $user_id, $rating, $comment]);

    $_SESSION['success'] = "Thank you for your review!";
    header("Location: view_part.php?id=" . urlencode($part_id));
    exit();

} catch (Exception $e) {
    error_log("Review submission failed: " . $e->getMessage());
    $_SESSION['error'] = "Failed to submit review. Please try again.";
    header("Location: view_part.php?id=" . urlencode($part_id));
    exit();
}
?>