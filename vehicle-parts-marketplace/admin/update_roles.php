<?php
session_start();
include '../includes/config.php';

// Admin check
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Access denied.');
}
$roles = explode(',', $_SESSION['role']);
if (!in_array('admin', $roles)) {
    http_response_code(403);
    exit('Access denied.');
}

$user_id = (int)($_POST['user_id'] ?? 0);
$new_roles = trim($_POST['roles'] ?? '');

if (!$user_id || !$new_roles) {
    http_response_code(400);
    exit('Invalid data.');
}

try {
    $allowed = ['buyer', 'seller', 'support', 'admin'];
    $roles_array = array_unique(array_filter(array_map('trim', explode(',', $new_roles)), fn($r) => in_array($r, $allowed)));
    
    if (empty($roles_array)) {
        http_response_code(400);
        exit('User must have at least one role.');
    }

    $pdo->prepare("UPDATE users SET role = ?, role_status = 'approved' WHERE id = ?")
         ->execute([implode(',', $roles_array), $user_id]);

    echo 'success';
} catch (Exception $e) {
    http_response_code(500);
    error_log("Role update error: " . $e->getMessage());
    exit('Server error.');
}
?>