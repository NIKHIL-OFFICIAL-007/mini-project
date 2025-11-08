<?php
session_start();
include '../../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please log in to add items to your cart.";
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check role
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !in_array('buyer', explode(',', $user['role']))) {
    $_SESSION['error'] = "You need a buyer role to add items to the cart.";
    header("Location: ../../buyer/wishlist.php");
    exit();
}

// Validate input
$part_id = $_POST['part_id'] ?? null;
$quantity = (int)($_POST['quantity'] ?? 1);

if (!$part_id || $quantity < 1) {
    $_SESSION['error'] = "Invalid part or quantity.";
    header("Location: ../../buyer/wishlist.php");
    exit();
}

// Check if part exists and is active
$stmt = $pdo->prepare("SELECT id, price, stock_quantity as stock FROM parts WHERE id = ? AND status = 'active'");
$stmt->execute([$part_id]);
$part = $stmt->fetch();

if (!$part) {
    $_SESSION['error'] = "Part not found or inactive.";
    header("Location: ../../buyer/wishlist.php");
    exit();
}

// Check stock
if ($part['stock'] < $quantity) {
    $_SESSION['error'] = "Not enough stock available.";
    header("Location: ../../buyer/wishlist.php");
    exit();
}

try {
    // Insert into cart_items
    $stmt = $pdo->prepare("INSERT INTO cart_items (buyer_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
    $stmt->execute([$user_id, $part_id, $quantity, $quantity]);

    // Success
    $_SESSION['success'] = "Item added to cart!";
    header("Location: ../../buyer/wishlist.php?added_to_cart=1");
    exit();
} catch (Exception $e) {
    error_log("Cart add failed: " . $e->getMessage());
    $_SESSION['error'] = "Failed to add item to cart.";
    header("Location: ../../buyer/wishlist.php");
    exit();
}
?>