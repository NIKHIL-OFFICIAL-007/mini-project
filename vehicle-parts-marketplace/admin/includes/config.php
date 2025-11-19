<?php
// admin/includes/config.php

// Include main config
include '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Fetch fresh user data
try {
    $stmt = $pdo->prepare("SELECT name, role, role_status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header("Location: ../../login.php");
        exit();
    }

    // ✅ Multi-role: check if 'admin' is one of the roles AND approved
    $roles = explode(',', $user['role']);
    if (!in_array('admin', $roles) || $user['role_status'] !== 'approved') {
        header("Location: ../../index.php");
        exit();
    }

    // Refresh session values
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['role_status'] = $user['role_status'];

} catch (Exception $e) {
    error_log("Admin auth failed: " . $e->getMessage());
    session_destroy();
    header("Location: ../../login.php");
    exit();
}
?>