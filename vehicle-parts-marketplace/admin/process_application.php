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

$type = $_POST['type'] ?? '';
$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

if (!in_array($type, ['seller', 'support', 'admin', 'deletion']) || !in_array($action, ['approve', 'reject']) || $id <= 0) {
    header("Location: user_requests.php?error=invalid_request");
    exit;
}

try {
    $pdo->beginTransaction();

    if ($type === 'seller') {
        // Fetch user_id from seller_applications
        $stmt = $pdo->prepare("SELECT user_id FROM seller_applications WHERE id = ?");
        $stmt->execute([$id]);
        $user_id = $stmt->fetchColumn();

        if ($action === 'approve') {
            // Add 'seller' role to user
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $current_roles = $stmt->fetchColumn();
            $roles_arr = array_filter(array_map('trim', explode(',', $current_roles)));
            if (!in_array('seller', $roles_arr)) {
                $roles_arr[] = 'seller';
                $new_roles = implode(',', $roles_arr);
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_roles, $user_id]);
            }

            // Mark app as approved
            $stmt = $pdo->prepare("UPDATE seller_applications SET status = 'approved' WHERE id = ?");
            $stmt->execute([$id]);

            // Add notification
            $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, 'Your seller application has been approved!', 'success')")
                ->execute([$user_id]);

        } else { // reject
            $stmt = $pdo->prepare("UPDATE seller_applications SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$id]);

            $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, 'Your seller application was not approved.', 'warning')")
                ->execute([$user_id]);
        }

    } elseif ($type === 'support') {
        $stmt = $pdo->prepare("SELECT user_id FROM support_applications WHERE id = ?");
        $stmt->execute([$id]);
        $user_id = $stmt->fetchColumn();

        if ($action === 'approve') {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $current_roles = $stmt->fetchColumn();
            $roles_arr = array_filter(array_map('trim', explode(',', $current_roles)));
            if (!in_array('support', $roles_arr)) {
                $roles_arr[] = 'support';
                $new_roles = implode(',', $roles_arr);
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_roles, $user_id]);
            }

            $stmt = $pdo->prepare("UPDATE support_applications SET status = 'approved' WHERE id = ?");
            $stmt->execute([$id]);

            $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, 'You are now a support agent!', 'success')")
                ->execute([$user_id]);

        } else {
            $stmt = $pdo->prepare("UPDATE support_applications SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$id]);

            $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, 'Your support application was not approved.', 'warning')")
                ->execute([$user_id]);
        }

    } elseif ($type === 'admin') {
        $stmt = $pdo->prepare("SELECT user_id FROM admin_applications WHERE id = ?");
        $stmt->execute([$id]);
        $user_id = $stmt->fetchColumn();

        if ($action === 'approve') {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $current_roles = $stmt->fetchColumn();
            $roles_arr = array_filter(array_map('trim', explode(',', $current_roles)));
            if (!in_array('admin', $roles_arr)) {
                $roles_arr[] = 'admin';
                $new_roles = implode(',', $roles_arr);
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_roles, $user_id]);
            }

            $stmt = $pdo->prepare("UPDATE admin_applications SET status = 'approved' WHERE id = ?");
            $stmt->execute([$id]);

            $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, 'You have been granted admin access!', 'success')")
                ->execute([$user_id]);

        } else {
            $stmt = $pdo->prepare("UPDATE admin_applications SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$id]);

            $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, 'Your admin application was not approved.', 'warning')")
                ->execute([$user_id]);
        }

    } elseif ($type === 'deletion') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id <= 0) {
            throw new Exception("User ID required for deletion");
        }

        if ($action === 'approve') {
            // Optional: delete profile picture
            $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $pic = $stmt->fetchColumn();
            if ($pic && $pic !== 'default.png') {
                $path = '../uploads/profiles/' . $pic;
                if (file_exists($path)) unlink($path);
            }

            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);

            // Mark request as approved
            $stmt = $pdo->prepare("UPDATE account_deletion_requests SET status = 'approved', processed_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);

        } else {
            $stmt = $pdo->prepare("UPDATE account_deletion_requests SET status = 'rejected', processed_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, 'Your account deletion request was rejected.', 'info')")
                ->execute([$user_id]);
        }
    }

    $pdo->commit();

    // Redirect with success message
    $message = match($type) {
        'seller' => 'application_approved',
        'support' => 'support_application_approved',
        'admin' => 'admin_application_approved',
        'deletion' => $action === 'approve' ? 'deletion_approved' : 'deletion_rejected',
        default => 'application_approved'
    };

    if ($type !== 'deletion' || $action !== 'approve') {
        $message = str_replace('_approved', '_approved', $message);
    }

    header("Location: user_requests.php?message=" . $message);
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Application processing failed: " . $e->getMessage());
    header("Location: user_requests.php?error=processing_failed");
    exit;
}
?>