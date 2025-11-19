<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has approved seller role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('seller', $roles) || $_SESSION['role_status'] !== 'approved') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    header("Location: orders.php");
    exit();
}

// Fetch order details
$order = [];
$order_items = [];
$status_history = [];

try {
    // Fetch order details - verify seller owns the parts in this order
    $stmt = $pdo->prepare("
        SELECT o.*, u.name as buyer_name, u.email as buyer_email
        FROM orders o 
        JOIN order_items oi ON o.id = oi.order_id
        JOIN parts p ON oi.part_id = p.id
        JOIN users u ON o.buyer_id = u.id
        WHERE o.id = ? AND p.seller_id = ?
        GROUP BY o.id
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['error'] = "Order not found or you don't have permission to view this order.";
        header("Location: orders.php");
        exit();
    }

    // Fetch order items from this seller
    $items_stmt = $pdo->prepare("
        SELECT oi.id, oi.quantity, oi.price, p.id as part_id, p.name, p.image_url, 
               c.name as category_name, p.stock_quantity
        FROM order_items oi
        JOIN parts p ON oi.part_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE oi.order_id = ? AND p.seller_id = ?
    ");
    $items_stmt->execute([$order_id, $user_id]);
    $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch status history (if table exists)
    try {
        $history_stmt = $pdo->prepare("
            SELECT osh.*, u.name as changed_by_name
            FROM order_status_history osh
            LEFT JOIN users u ON osh.changed_by = u.id
            WHERE osh.order_id = ?
            ORDER BY osh.created_at ASC
        ");
        $history_stmt->execute([$order_id]);
        $status_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $status_history = [];
    }

} catch (Exception $e) {
    error_log("Failed to fetch order: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load order details.";
    header("Location: orders.php");
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
            
            // Add to status history if table exists
            try {
                $history_stmt = $pdo->prepare("
                    INSERT INTO order_status_history (order_id, status, changed_by, changed_by_role, notes)
                    VALUES (?, ?, ?, 'seller', ?)
                ");
                $history_stmt->execute([$order_id, $new_status, $user_id, $notes]);
            } catch (Exception $e) {
                // History table might not exist, continue
            }
            
            $pdo->commit();
            
            $_SESSION['success'] = "Order status updated successfully!";
            
            // Refresh order data
            $stmt->execute([$order_id, $user_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Failed to update order status: " . $e->getMessage());
            $_SESSION['error'] = "Failed to update order status. Please try again.";
        }
    } else {
        $_SESSION['error'] = "Invalid status selected.";
    }
}

// Get status color and theme
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

$status_theme = getStatusTheme($order['status']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Order Details - Seller Dashboard</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    .status-badge {
      transition: all 0.3s ease;
    }
    .order-item-image {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 12px;
    }
    .timeline-dot {
      transition: all 0.3s ease;
    }
  </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 text-gray-900 min-h-screen">

  <?php include 'includes/seller_header.php'; ?>

  <!-- Main Content -->
  <div class="relative z-10">
    <!-- Page Header -->
    <div class="py-16 bg-gradient-to-r from-blue-600 to-blue-800 text-white shadow-lg">
      <div class="container mx-auto px-6 text-center">
        <div class="mb-6">
          <i class="fas fa-receipt text-6xl md:text-7xl text-white opacity-90"></i>
        </div>
        <h1 class="text-4xl md:text-5xl font-bold mb-4">Order Management</h1>
        <p class="text-blue-100 text-lg md:text-xl max-w-2xl mx-auto leading-relaxed">
          Manage order <strong>#<?= htmlspecialchars($order['id']) ?></strong> for <?= htmlspecialchars($order['buyer_name']) ?>
        </p>
      </div>
    </div>

    <!-- Success/Error Messages -->
    <div class="container mx-auto px-6 -mt-4">
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
    </div>

    <!-- Order Details -->
    <div class="container mx-auto px-6 py-8">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 max-w-7xl mx-auto">
        
        <!-- Order Tracking & Items -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Status Update Card -->
          <div class="bg-white rounded-2xl shadow-lg border border-blue-100 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-50 to-cyan-50 px-6 py-4 border-b border-blue-100">
              <h2 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-edit mr-3 text-blue-600"></i>
                Update Order Status
              </h2>
            </div>
            
            <div class="p-6">
              <form method="POST" class="space-y-4">
                <!-- Current Status Display -->
                <div class="<?= $status_theme['bg_color'] ?> border <?= $status_theme['border_color'] ?> rounded-xl p-4">
                  <div class="flex items-center justify-between">
                    <div>
                      <h3 class="font-semibold <?= $status_theme['text_color'] ?> text-lg">Current Status</h3>
                      <p class="<?= $status_theme['text_color'] ?>"><?= $status_theme['message'] ?></p>
                    </div>
                    <span class="inline-flex items-center px-4 py-2 <?= $status_theme['badge_color'] ?> rounded-full font-semibold status-badge">
                      <i class="<?= $status_theme['icon'] ?> mr-2"></i>
                      <?= strtoupper($order['status']) ?>
                    </span>
                  </div>
                </div>

                <!-- Status Selection -->
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

                <!-- Notes -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Update Notes (Optional)</label>
                  <textarea name="notes" rows="3" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Add any notes about this status update..."></textarea>
                </div>

                <!-- Submit Button -->
                <div>
                  <button type="submit" name="update_status" 
                          class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg font-semibold">
                    <i class="fas fa-save mr-2"></i>Update Order Status
                  </button>
                </div>
              </form>
            </div>
          </div>

          <!-- Order Items -->
          <div class="bg-white rounded-2xl shadow-lg border border-green-100 overflow-hidden">
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 border-b border-green-100">
              <h2 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-list-alt mr-3 text-green-600"></i>
                Your Parts in This Order
              </h2>
            </div>
            <div class="p-6">
              <div class="space-y-4">
                <?php foreach ($order_items as $item): ?>
                  <div class="flex items-center p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition duration-200">
                    <div class="flex-shrink-0 mr-4">
                      <?php if ($item['image_url']): ?>
                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" 
                             class="order-item-image">
                      <?php else: ?>
                        <div class="order-item-image bg-gray-200 rounded-xl flex items-center justify-center">
                          <i class="fas fa-cog text-gray-400 text-2xl"></i>
                        </div>
                      <?php endif; ?>
                    </div>
                    <div class="flex-1">
                      <h4 class="font-semibold text-gray-800 text-lg"><?= htmlspecialchars($item['name']) ?></h4>
                      <p class="text-sm text-blue-600 capitalize mb-2"><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></p>
                      <div class="flex items-center text-sm text-gray-600 space-x-4">
                        <span class="bg-white px-3 py-1 rounded-lg border border-gray-300 font-medium">Qty: <?= $item['quantity'] ?></span>
                        <span class="font-semibold text-gray-800">₹<?= number_format($item['price'], 0) ?> each</span>
                        <span class="text-sm <?= $item['stock_quantity'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                          Stock: <?= $item['stock_quantity'] ?>
                        </span>
                      </div>
                    </div>
                    <div class="text-right">
                      <p class="text-lg font-bold text-gray-800">₹<?= number_format($item['price'] * $item['quantity'], 0) ?></p>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Order Total for Seller's Items -->
              <div class="border-t pt-6 mt-6">
                <?php
                $seller_total = 0;
                foreach ($order_items as $item) {
                    $seller_total += $item['price'] * $item['quantity'];
                }
                ?>
                <div class="flex justify-between items-center text-xl font-bold bg-gradient-to-r from-gray-50 to-blue-50 p-4 rounded-xl">
                  <span class="text-gray-800">Your Parts Total</span>
                  <span class="text-blue-600">₹<?= number_format($seller_total, 0) ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
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
                  <span class="font-bold text-gray-800">#<?= htmlspecialchars($order['id']) ?></span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-gray-600">Customer:</span>
                  <span class="text-gray-800 font-medium"><?= htmlspecialchars($order['buyer_name']) ?></span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-gray-600">Order Date:</span>
                  <span class="text-gray-800"><?= date('F j, Y', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-gray-600">Your Items:</span>
                  <span class="text-gray-800"><?= count($order_items) ?> item(s)</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-gray-600">Status:</span>
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium <?= $status_theme['badge_color'] ?>">
                    <i class="<?= $status_theme['icon'] ?> mr-1"></i>
                    <?= ucfirst($order['status']) ?>
                  </span>
                </div>
                <div class="flex justify-between items-center text-lg font-bold pt-3 border-t border-gray-200">
                  <span class="text-gray-800">Your Earnings:</span>
                  <span class="text-purple-600">₹<?= number_format($seller_total, 0) ?></span>
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
                  <p class="text-gray-800 font-medium mb-1"><?= htmlspecialchars($order['shipping_name']) ?></p>
                  <p class="text-gray-600 text-sm"><?= htmlspecialchars($order['shipping_address']) ?></p>
                  <p class="text-gray-600 text-sm"><?= htmlspecialchars($order['shipping_city']) ?>, <?= htmlspecialchars($order['shipping_state']) ?> - <?= htmlspecialchars($order['shipping_zip_code']) ?></p>
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
              <a href="orders.php" 
                 class="w-full flex items-center justify-center px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition-all duration-300">
                <i class="fas fa-arrow-left mr-3"></i>
                Back to Orders
              </a>
              <a href="manage_parts.php" 
                 class="w-full flex items-center justify-center px-4 py-3 border-2 border-green-600 text-green-600 hover:bg-green-50 rounded-xl transition-all duration-300">
                <i class="fas fa-cog mr-3"></i>
                Manage Parts
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/seller_footer.php'; ?>

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

      // Auto-focus on status select when page loads
      const statusSelect = document.querySelector('select[name="status"]');
      if (statusSelect) {
        statusSelect.focus();
      }
    });
  </script>
</body>
</html>