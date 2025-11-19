<?php
session_start();
include '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Fetch user's current request status
    $stmt = $pdo->prepare("SELECT role_request, role_status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User not found");
    }

    // Only pending requests can be cancelled
    if (!$user['role_request'] || $user['role_status'] !== 'pending') {
        header("Location: my_requests.php?error=not_pending");
        exit();
    }

    // Cancel the request: clear role_request and set status to approved (back to current role)
    $pdo->prepare("UPDATE users SET role_request = NULL, role_status = 'approved' WHERE id = ?")
        ->execute([$user_id]);

    // Add notification
    $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, '❌ Your role request has been cancelled.', 'role_application')")
        ->execute([$user_id]);

    header("Location: my_requests.php?message=request_cancelled");
    exit();

} catch (Exception $e) {
    error_log("Failed to cancel request: " . $e->getMessage());
    header("Location: my_requests.php?error=cancel_failed");
    exit();
}
?>