<?php
session_start();
include 'includes/config.php';

// Support check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}
$roles = explode(',', $_SESSION['role']);
if (!in_array('support', $roles) || ($_SESSION['role_status'] ?? '') !== 'approved') {
    header("Location: ../login.php");
    exit();
}

$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    header("Location: manage_orders.php");
    exit();
}

// Fetch order details
$order = [];
$order_items = [];
$status_history = [];

try {
    // Fetch main order details
    $order_stmt = $pdo->prepare("
        SELECT 
            o.*,
            u_buyer.name as buyer_name, u_buyer.email as buyer_email, u_buyer.phone as buyer_phone,
            u_seller.name as seller_name, u_seller.email as seller_email
        FROM orders o
        LEFT JOIN users u_buyer ON o.buyer_id = u_buyer.id
        LEFT JOIN users u_seller ON o.seller_id = u_seller.id
        WHERE o.id = ?
    ");
    $order_stmt->execute([$order_id]);
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['error'] = "Order not found.";
        header("Location: manage_orders.php");
        exit();
    }

    // Fetch order items with seller information
    $items_stmt = $pdo->prepare("
        SELECT 
            oi.*,
            p.name as part_name, p.image_url, p.description,
            u.name as seller_name, u.email as seller_email,
            c.name as category_name
        FROM order_items oi
        JOIN parts p ON oi.part_id = p.id
        JOIN users u ON p.seller_id = u.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE oi.order_id = ?
        ORDER BY u.name, p.name
    ");
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch status history
    $history_stmt = $pdo->prepare("
        SELECT 
            osh.*,
            u.name as changed_by_name
        FROM order_status_history osh
        LEFT JOIN users u ON osh.changed_by = u.id
        WHERE osh.order_id = ?
        ORDER BY osh.created_at DESC
    ");
    $history_stmt->execute([$order_id]);
    $status_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Failed to fetch order details: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load order details.";
    header("Location: manage_orders.php");
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    $allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    
    if (in_array($new_status, $allowed_statuses)) {
        try {
            $pdo->beginTransaction();
            
            // Update order status
            $update_stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $update_stmt->execute([$new_status, $order_id]);
            
            // Add to status history
            $history_stmt = $pdo->prepare("
                INSERT INTO order_status_history (order_id, status, changed_by, changed_by_role, notes)
                VALUES (?, ?, ?, 'support', ?)
            ");
            $history_stmt->execute([$order_id, $new_status, $_SESSION['user_id'], $notes]);
            
            $pdo->commit();
            
            $_SESSION['success'] = "Order status updated successfully!";
            
            // Refresh order data
            header("Location: view_order.php?id=" . $order_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Failed to update order status: " . $e->getMessage());
            $_SESSION['error'] = "Failed to update order status. Please try again.";
        }
    } else {
        $_SESSION['error'] = "Invalid status selected.";
    }
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $cancellation_reason = $_POST['cancellation_reason'] ?? '';
    
    if (empty($cancellation_reason)) {
        $_SESSION['error'] = "Cancellation reason is required.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update order status to cancelled
            $update_stmt = $pdo->prepare("
                UPDATE orders 
                SET status = 'cancelled', 
                    cancelled_by = 'support',
                    cancellation_reason = ?,
                    cancelled_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $update_stmt->execute([$cancellation_reason, $order_id]);
            
            // Add to status history
            $history_stmt = $pdo->prepare("
                INSERT INTO order_status_history (order_id, status, changed_by, changed_by_role, notes)
                VALUES (?, 'cancelled', ?, 'support', ?)
            ");
            $history_stmt->execute([$order_id, $_SESSION['user_id'], "Cancelled by support: " . $cancellation_reason]);
            
            $pdo->commit();
            
            $_SESSION['success'] = "Order has been cancelled successfully.";
            
            // Refresh order data
            header("Location: view_order.php?id=" . $order_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Failed to cancel order: " . $e->getMessage());
            $_SESSION['error'] = "Failed to cancel order. Please try again.";
        }
    }
}

// Get status theme
function getStatusTheme($status) {
    switch ($status) {
        case 'pending': 
            return [
                'bg_color' => 'bg-yellow-50',
                'border_color' => 'border-yellow-200',
                'text_color' => 'text-yellow-800',
                'badge_color' => 'bg-yellow-100 text-yellow-800',
                'icon' => 'fas fa-clock',
                'message' => 'Order is awaiting processing.'
            ];
        case 'processing': 
            return [
                'bg_color' => 'bg-blue-50',
                'border_color' => 'border-blue-200',
                'text_color' => 'text-blue-800',
                'badge_color' => 'bg-blue-100 text-blue-800',
                'icon' => 'fas fa-cog',
                'message' => 'Order is being prepared for shipment.'
            ];
        case 'shipped': 
            return [
                'bg_color' => 'bg-purple-50',
                'border_color' => 'border-purple-200',
                'text_color' => 'text-purple-800',
                'badge_color' => 'bg-purple-100 text-purple-800',
                'icon' => 'fas fa-shipping-fast',
                'message' => 'Order has been shipped and is on the way.'
            ];
        case 'delivered': 
            return [
                'bg_color' => 'bg-green-50',
                'border_color' => 'border-green-200',
                'text_color' => 'text-green-800',
                'badge_color' => 'bg-green-100 text-green-800',
                'icon' => 'fas fa-check-circle',
                'message' => 'Order has been delivered successfully!'
            ];
        case 'cancelled': 
            return [
                'bg_color' => 'bg-red-50',
                'border_color' => 'border-red-200',
                'text_color' => 'text-red-800',
                'badge_color' => 'bg-red-100 text-red-800',
                'icon' => 'fas fa-times-circle',
                'message' => 'This order has been cancelled.'
            ];
        default: 
            return [
                'bg_color' => 'bg-gray-50',
                'border_color' => 'border-gray-200',
                'text_color' => 'text-gray-800',
                'badge_color' => 'bg-gray-100 text-gray-800',
                'icon' => 'fas fa-question-circle',
                'message' => 'Order status unknown.'
            ];
    }
}

// Format price without .00
function formatPrice($price) {
    $price = floatval($price);
    if ($price == intval($price)) {
        return number_format($price, 0);
    } else {
        return number_format($price, 2);
    }
}

$status_theme = getStatusTheme($order['status']);

// Group items by seller
$items_by_seller = [];
foreach ($order_items as $item) {
    $seller_key = $item['seller_name'] . '|' . $item['seller_email'];
    if (!isset($items_by_seller[$seller_key])) {
        $items_by_seller[$seller_key] = [
            'seller_name' => $item['seller_name'],
            'seller_email' => $item['seller_email'],
            'items' => [],
            'subtotal' => 0
        ];
    }
    $items_by_seller[$seller_key]['items'][] = $item;
    $items_by_seller[$seller_key]['subtotal'] += $item['price'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Order Details - Support Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <style>
        .order-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 12px;
        }
        .timeline-dot {
            transition: all 0.3s ease;
        }
        .seller-section {
            border-radius: 12px;
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">
    <?php include 'includes/support_header.php'; ?>

    <!-- Page Header -->
    <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
        <div class="container mx-auto px-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-4xl md:text-5xl font-bold mb-4">Order Details</h1>
                    <p class="text-blue-100 text-lg">Order #<?= $order['id'] ?> - <?= htmlspecialchars($order['buyer_name']) ?></p>
                </div>
                <a href="manage_orders.php" 
                   class="flex items-center px-6 py-3 bg-white/20 hover:bg-white/30 text-white rounded-xl transition duration-300">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Orders
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span><?= htmlspecialchars($_SESSION['success']) ?></span>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span><?= htmlspecialchars($_SESSION['error']) ?></span>
                </div>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column - Order Actions & Info -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Status Update Card -->
                <div class="bg-white rounded-2xl shadow-lg border border-blue-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-50 to-cyan-50 px-6 py-4 border-b border-blue-100">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-edit mr-3 text-blue-600"></i>
                            Order Status Management
                        </h2>
                    </div>
                    
                    <div class="p-6">
                        <!-- Current Status -->
                        <div class="<?= $status_theme['bg_color'] ?> border <?= $status_theme['border_color'] ?> rounded-xl p-4 mb-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold <?= $status_theme['text_color'] ?> text-lg">Current Status</h3>
                                    <p class="<?= $status_theme['text_color'] ?>"><?= $status_theme['message'] ?></p>
                                </div>
                                <span class="inline-flex items-center px-4 py-2 <?= $status_theme['badge_color'] ?> rounded-full font-semibold">
                                    <i class="<?= $status_theme['icon'] ?> mr-2"></i>
                                    <?= strtoupper($order['status']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Status Update Form -->
                        <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'delivered'): ?>
                            <form method="POST" action="view_order.php?id=<?= $order_id ?>" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Update Status</label>
                                    <select name="status" required 
                                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select new status</option>
                                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                                        <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                        <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Update Notes</label>
                                    <textarea name="notes" rows="3" 
                                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="Add any notes about this status update..."></textarea>
                                </div>

                                <button type="submit" name="update_status" 
                                        class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg font-semibold">
                                    <i class="fas fa-save mr-2"></i>Update Order Status
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- Cancel Order Form -->
                        <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'delivered'): ?>
                            <form method="POST" action="view_order.php?id=<?= $order_id ?>" 
                                  onsubmit="return confirm('Are you sure you want to cancel this order? This action cannot be undone.');"
                                  class="mt-6 pt-6 border-t border-gray-200">
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-red-700 mb-2">Cancel Order</label>
                                    <input type="text" name="cancellation_reason" 
                                           class="w-full px-4 py-3 border border-red-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                           placeholder="Reason for cancellation..." required>
                                </div>
                                <button type="submit" name="cancel_order" 
                                        class="w-full px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg font-semibold">
                                    <i class="fas fa-times mr-2"></i>Cancel Order
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Items by Seller -->
                <div class="bg-white rounded-2xl shadow-lg border border-green-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 border-b border-green-100">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-shopping-cart mr-3 text-green-600"></i>
                            Order Items (<?= count($order_items) ?> items)
                        </h2>
                    </div>
                    <div class="p-6">
                        <?php if (empty($items_by_seller)): ?>
                            <p class="text-gray-500 text-center py-8">No items found in this order.</p>
                        <?php else: ?>
                            <div class="space-y-6">
                                <?php foreach ($items_by_seller as $seller_key => $seller_data): ?>
                                    <div class="seller-section border border-gray-200 rounded-xl overflow-hidden">
                                        <!-- Seller Header -->
                                        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                            <h3 class="font-semibold text-gray-800 text-lg">
                                                <i class="fas fa-store mr-2 text-blue-600"></i>
                                                <?= htmlspecialchars($seller_data['seller_name']) ?>
                                            </h3>
                                            <p class="text-gray-600 text-sm"><?= htmlspecialchars($seller_data['seller_email']) ?></p>
                                        </div>
                                        
                                        <!-- Seller Items -->
                                        <div class="p-4">
                                            <div class="space-y-4">
                                                <?php foreach ($seller_data['items'] as $item): ?>
                                                    <div class="flex items-center p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition duration-200">
                                                        <div class="flex-shrink-0 mr-4">
                                                            <?php if ($item['image_url']): ?>
                                                                <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                                                     alt="<?= htmlspecialchars($item['part_name']) ?>" 
                                                                     class="order-item-image">
                                                            <?php else: ?>
                                                                <div class="order-item-image bg-gray-200 rounded-xl flex items-center justify-center">
                                                                    <i class="fas fa-cog text-gray-400 text-2xl"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="flex-1">
                                                            <h4 class="font-semibold text-gray-800 text-lg"><?= htmlspecialchars($item['part_name']) ?></h4>
                                                            <p class="text-sm text-blue-600 capitalize mb-2"><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></p>
                                                            <div class="flex items-center text-sm text-gray-600 space-x-4">
                                                                <span class="bg-white px-3 py-1 rounded-lg border border-gray-300 font-medium">
                                                                    Qty: <?= $item['quantity'] ?>
                                                                </span>
                                                                <span class="font-semibold text-gray-800">
                                                                    ₹<?= formatPrice($item['price']) ?> each
                                                                </span>
                                                            </div>
                                                            <?php if ($item['description']): ?>
                                                                <p class="text-gray-600 text-sm mt-2"><?= htmlspecialchars($item['description']) ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-right">
                                                            <p class="text-lg font-bold text-gray-800">
                                                                ₹<?= formatPrice($item['price'] * $item['quantity']) ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                            <!-- Seller Subtotal -->
                                            <div class="border-t border-gray-200 pt-4 mt-4">
                                                <div class="flex justify-between items-center text-lg font-bold">
                                                    <span class="text-gray-700">Seller Subtotal:</span>
                                                    <span class="text-green-600">₹<?= formatPrice($seller_data['subtotal']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Order Total -->
                            <div class="border-t border-gray-200 pt-6 mt-6">
                                <div class="flex justify-between items-center text-xl font-bold bg-gradient-to-r from-gray-50 to-blue-50 p-4 rounded-xl">
                                    <span class="text-gray-800">Order Total</span>
                                    <span class="text-blue-600">₹<?= formatPrice($order['total_amount']) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status History -->
                <div class="bg-white rounded-2xl shadow-lg border border-purple-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-50 to-indigo-50 px-6 py-4 border-b border-purple-100">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-history mr-3 text-purple-600"></i>
                            Status History
                        </h2>
                    </div>
                    <div class="p-6">
                        <?php if (empty($status_history)): ?>
                            <p class="text-gray-500 text-center py-4">No status history available.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($status_history as $history): ?>
                                    <?php $history_theme = getStatusTheme($history['status']); ?>
                                    <div class="flex items-start space-x-4 p-4 border-l-4 <?= $history_theme['border_color'] ?> bg-gray-50 rounded-r-lg">
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 <?= $history_theme['badge_color'] ?> rounded-full flex items-center justify-center">
                                                <i class="<?= $history_theme['icon'] ?> <?= $history_theme['text_color'] ?>"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <h4 class="font-semibold text-gray-800">Status changed to <?= ucfirst($history['status']) ?></h4>
                                                    <p class="text-sm text-gray-600">By <?= htmlspecialchars($history['changed_by_name']) ?> (<?= $history['changed_by_role'] ?>)</p>
                                                    <?php if ($history['notes']): ?>
                                                        <p class="text-gray-700 mt-2"><?= nl2br(htmlspecialchars($history['notes'])) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="text-sm text-gray-500 whitespace-nowrap">
                                                    <?= date('M j, Y g:i A', strtotime($history['created_at'])) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column - Order Information -->
            <div class="space-y-6">
                <!-- Order Summary -->
                <div class="bg-white rounded-2xl shadow-lg border border-purple-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-50 to-indigo-50 px-6 py-4 border-b border-purple-100">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-receipt mr-3 text-purple-600"></i>
                            Order Summary
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Order Number:</span>
                                <span class="font-bold text-gray-800">#<?= $order['id'] ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Order Date:</span>
                                <span class="text-gray-800"><?= date('F j, Y g:i A', strtotime($order['created_at'])) ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Last Updated:</span>
                                <span class="text-gray-800"><?= date('F j, Y g:i A', strtotime($order['updated_at'])) ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Total Items:</span>
                                <span class="text-gray-800"><?= count($order_items) ?> item(s)</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Total Sellers:</span>
                                <span class="text-gray-800"><?= count($items_by_seller) ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Status:</span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?= $status_theme['badge_color'] ?>">
                                    <i class="<?= $status_theme['icon'] ?> mr-1"></i>
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </div>
                            <?php if ($order['status'] === 'cancelled'): ?>
                                <div class="border-t border-gray-200 pt-4">
                                    <div class="flex justify-between items-center">
                                        <span class="text-gray-600">Cancelled By:</span>
                                        <span class="text-red-600 font-medium"><?= ucfirst($order['cancelled_by']) ?></span>
                                    </div>
                                    <div class="mt-2">
                                        <span class="text-gray-600">Reason:</span>
                                        <p class="text-red-600 mt-1"><?= htmlspecialchars($order['cancellation_reason']) ?></p>
                                    </div>
                                    <div class="mt-2">
                                        <span class="text-gray-600">Cancelled At:</span>
                                        <p class="text-gray-800"><?= date('F j, Y g:i A', strtotime($order['cancelled_at'])) ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="border-t border-gray-200 pt-4">
                                <div class="flex justify-between items-center text-lg font-bold">
                                    <span class="text-gray-800">Total Amount:</span>
                                    <span class="text-purple-600">₹<?= formatPrice($order['total_amount']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="bg-white rounded-2xl shadow-lg border border-green-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 border-b border-green-100">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-user mr-3 text-green-600"></i>
                            Customer Information
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            <div>
                                <p class="text-gray-800 font-medium mb-1"><?= htmlspecialchars($order['buyer_name']) ?></p>
                                <p class="text-gray-600 text-sm"><?= htmlspecialchars($order['buyer_email']) ?></p>
                                <?php if ($order['buyer_phone']): ?>
                                    <p class="text-gray-600 text-sm"><?= htmlspecialchars($order['buyer_phone']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shipping Information -->
                <div class="bg-white rounded-2xl shadow-lg border border-blue-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-50 to-cyan-50 px-6 py-4 border-b border-blue-100">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-truck mr-3 text-blue-600"></i>
                            Shipping Information
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            <div>
                                <p class="text-gray-800 font-medium mb-1"><?= htmlspecialchars($order['shipping_name']) ?></p>
                                <p class="text-gray-600 text-sm"><?= htmlspecialchars($order['shipping_address']) ?></p>
                                <p class="text-gray-600 text-sm">
                                    <?= htmlspecialchars($order['shipping_city']) ?>, 
                                    <?= htmlspecialchars($order['shipping_state']) ?> - 
                                    <?= htmlspecialchars($order['shipping_zip_code']) ?>
                                </p>
                                <p class="text-gray-600 text-sm"><?= htmlspecialchars($order['shipping_country']) ?></p>
                            </div>
                            <div class="pt-2 border-t border-gray-200">
                                <p class="text-gray-600 text-sm flex items-center">
                                    <i class="fas fa-envelope mr-2 text-blue-500"></i>
                                    <?= htmlspecialchars($order['shipping_email']) ?>
                                </p>
                                <p class="text-gray-600 text-sm flex items-center mt-1">
                                    <i class="fas fa-phone mr-2 text-blue-500"></i>
                                    <?= htmlspecialchars($order['shipping_phone']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-2xl shadow-lg border border-orange-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-orange-50 to-amber-50 px-6 py-4 border-b border-orange-100">
                        <h2 class="text-xl font-bold text-gray-800 flex items-center">
                            <i class="fas fa-bolt mr-3 text-orange-600"></i>
                            Quick Actions
                        </h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <a href="manage_orders.php" 
                           class="w-full flex items-center justify-center px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition-all duration-300">
                            <i class="fas fa-arrow-left mr-3"></i>
                            Back to Orders
                        </a>
                        <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'delivered'): ?>
                            <button onclick="document.querySelector('select[name=\"status\"]').focus()" 
                                    class="w-full flex items-center justify-center px-4 py-3 border-2 border-green-600 text-green-600 hover:bg-green-50 rounded-xl transition-all duration-300">
                                <i class="fas fa-edit mr-3"></i>
                                Update Status
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/support_footer.php'; ?>

    <script>
        // Add interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to order items
            const orderItems = document.querySelectorAll('.bg-gray-50.rounded-xl');
            orderItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.1)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '';
                });
            });

            // Auto-focus on status select when page loads if there's an error
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('error')) {
                const statusSelect = document.querySelector('select[name="status"]');
                if (statusSelect) {
                    statusSelect.focus();
                }
            }
        });
    </script>
</body>
</html>