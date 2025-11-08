<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has buyer role
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Login required.";
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    $_SESSION['error'] = "Session error.";
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('buyer', $roles)) {
    $_SESSION['error'] = "Access denied.";
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = $_POST['order_id'] ?? null;

if (!$order_id || !is_numeric($order_id)) {
    $_SESSION['error'] = "Invalid order.";
    header("Location: orders.php");
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Fetch order and check ownership + status
    $stmt = $pdo->prepare("
        SELECT o.status, oi.part_id, oi.quantity
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        WHERE o.id = ? AND o.buyer_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($order_items)) {
        throw new Exception("Order not found or access denied.");
    }

    $status = $order_items[0]['status'];
    if ($status === 'shipped' || $status === 'delivered' || $status === 'cancelled') {
        throw new Exception("Order cannot be cancelled.");
    }

    // Restore stock
    foreach ($order_items as $item) {
        $update_stock = $pdo->prepare("UPDATE parts SET stock_quantity = stock_quantity + ? WHERE id = ?");
        $update_stock->execute([$item['quantity'], $item['part_id']]);
    }

    // Update order status
    $update_order = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
    $update_order->execute([$order_id]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success'] = "Order #{$order_id} has been cancelled and stock restored.";
} catch (Exception $e) {
    $pdo->rollback();
    error_log("Order cancellation failed: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
}

// Redirect back
header("Location: orders.php");
exit();
?>