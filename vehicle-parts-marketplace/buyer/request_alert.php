<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    echo "Please log in.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['part_id']) || !isset($_POST['action'])) {
    echo "Invalid request.";
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$part_id = (int)$_POST['part_id'];
$action = $_POST['action'];

// Validate buyer role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user || !in_array('buyer', explode(',', $user['role']))) {
    echo "Only buyers can manage stock alerts.";
    exit();
}

// Validate part exists and is out of stock
$stmt = $pdo->prepare("SELECT name, stock_quantity FROM parts WHERE id = ? AND status = 'active'");
$stmt->execute([$part_id]);
$part = $stmt->fetch();
if (!$part) {
    echo "Part not found.";
    exit();
}
if ($part['stock_quantity'] > 0) {
    echo "This part is in stock.";
    exit();
}

if ($action === 'request') {
    // Prevent duplicate
    $stmt = $pdo->prepare("SELECT alert_id FROM product_alerts WHERE user_id = ? AND part_id = ? AND status = 'pending'");
    $stmt->execute([$user_id, $part_id]);
    if ($stmt->rowCount() > 0) {
        echo "✅ You are already subscribed to stock alerts for this part.";
    } else {
        // Insert without email column
        $stmt = $pdo->prepare("INSERT INTO product_alerts (user_id, part_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$user_id, $part_id]);
        echo "✅ Alert requested! You'll get a notification when \"{$part['name']}\" is back in stock.";
    }
} elseif ($action === 'remove') {
    $stmt = $pdo->prepare("DELETE FROM product_alerts WHERE user_id = ? AND part_id = ? AND status = 'pending'");
    $stmt->execute([$user_id, $part_id]);
    if ($stmt->rowCount() > 0) {
        echo "✅ Alert removed. You will no longer be notified.";
    } else {
        echo "⚠️ No active alert found to remove.";
    }
} else {
    echo "Invalid action.";
}
?>