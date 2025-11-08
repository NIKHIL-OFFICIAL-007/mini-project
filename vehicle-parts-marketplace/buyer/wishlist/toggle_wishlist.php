<?php
header('Content-Type: application/json');
session_start();
include '../../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !in_array('buyer', explode(',', $user['role']))) {
    echo json_encode(['success' => false, 'message' => 'Buyer role required']);
    exit();
}

// Validate input
$part_id = $_POST['part_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$part_id || !is_numeric($part_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid part ID']);
    exit();
}

if ($action !== 'add' && $action !== 'remove') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

try {
    // Check if part exists and is active
    $stmt = $pdo->prepare("SELECT id FROM parts WHERE id = ? AND status = 'active'");
    $stmt->execute([$part_id]);
    $part = $stmt->fetch();

    if (!$part) {
        echo json_encode(['success' => false, 'message' => 'Part not found or inactive']);
        exit();
    }

    // Perform action
    if ($action === 'remove') {
        $stmt = $pdo->prepare("DELETE FROM wishlists WHERE user_id = ? AND part_id = ?");
        $stmt->execute([$user_id, $part_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Removed from wishlist']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Item not in wishlist']);
        }
    } else {
        $stmt = $pdo->prepare("INSERT IGNORE INTO wishlists (user_id, part_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $part_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Added to wishlist']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Already in wishlist']);
        }
    }
} catch (Exception $e) {
    error_log("Wishlist error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
?>