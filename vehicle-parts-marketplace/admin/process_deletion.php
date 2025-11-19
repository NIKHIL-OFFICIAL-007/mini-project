<?php
session_start();
include 'includes/config.php';

// Admin check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    exit('Forbidden');
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('admin', $roles)) {
    http_response_code(403);
    exit('Admin access required');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: user_requests.php?error=invalid_method");
    exit;
}

$action = $_POST['action'] ?? '';
$request_id = (int)($_POST['request_id'] ?? 0);
$user_id = (int)($_POST['user_id'] ?? 0);

if (!in_array($action, ['approve', 'reject']) || $request_id <= 0 || $user_id <= 0) {
    header("Location: user_requests.php?error=invalid_request");
    exit;
}

try {
    $pdo->beginTransaction();

    // First, get user details for logging/archiving
    $stmt = $pdo->prepare("SELECT name, email, role, profile_picture FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_details = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_details) {
        throw new Exception("User not found");
    }

    if ($action === 'approve') {
        // Archive user data before deletion (optional but recommended)
        $stmt = $pdo->prepare("
            INSERT INTO deleted_users_archive 
            (original_user_id, name, email, role, deletion_date, deleted_by_admin_id) 
            VALUES (?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $user_id, 
            $user_details['name'], 
            $user_details['email'], 
            $user_details['role'],
            $_SESSION['user_id']
        ]);

        // Delete profile picture if exists and not default
        if ($user_details['profile_picture'] && $user_details['profile_picture'] !== 'default.png') {
            $path = '../uploads/profiles/' . $user_details['profile_picture'];
            if (file_exists($path)) {
                unlink($path);
            }
        }

        // Delete user from main table
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);

        // Update deletion request status
        $stmt = $pdo->prepare("UPDATE account_deletion_requests SET status = 'approved', processed_at = NOW() WHERE id = ?");
        $stmt->execute([$request_id]);

        $message = 'deletion_approved';

    } else { // reject
        // Update deletion request status
        $stmt = $pdo->prepare("UPDATE account_deletion_requests SET status = 'rejected', processed_at = NOW() WHERE id = ?");
        $stmt->execute([$request_id]);

        // Add notification to user
        $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, 'Your account deletion request was rejected. Your account remains active.', 'info')")
            ->execute([$user_id]);

        $message = 'deletion_rejected';
    }

    $pdo->commit();
    header("Location: user_requests.php?message=" . $message);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Deletion processing failed: " . $e->getMessage());
    header("Location: user_requests.php?error=processing_failed");
    exit;
}
?>