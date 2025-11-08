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
    // Fetch user's request
    $stmt = $pdo->prepare("SELECT role_request, role_status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: my_requests.php?error=invalid_request");
        exit();
    }

    // Only rejected or cancelled requests can be deleted
    if ($user['role_status'] === 'pending') {
        header("Location: my_requests.php?error=not_deletable");
        exit();
    }

    // "Delete" by clearing role_request (status stays as 'rejected' or 'cancelled')
    $pdo->prepare("UPDATE users SET role_request = NULL WHERE id = ?")
        ->execute([$user_id]);

    // Add notification
    $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, '🗑️ Your role request has been deleted.', 'role_application')")
        ->execute([$user_id]);

    header("Location: my_requests.php?message=request_deleted");
    exit();

} catch (Exception $e) {
    error_log("Failed to delete request: " . $e->getMessage());
    header("Location: my_requests.php?error=delete_failed");
    exit();
}
?>