<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has buyer role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('buyer', $roles)) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    header("Location: orders.php");
    exit();
}

// Fetch order details with tracking information
$order = [];
$order_items = [];
$status_history = [];

try {
    // Fetch order details
    $stmt = $pdo->prepare("
        SELECT o.*, u.name as buyer_name, u.email as buyer_email
        FROM orders o 
        JOIN users u ON o.buyer_id = u.id
        WHERE o.id = ? AND o.buyer_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $_SESSION['error'] = "Order not found.";
        header("Location: orders.php");
        exit();
    }

    // Fetch order items
    $items_stmt = $pdo->prepare("
        SELECT oi.id, oi.quantity, oi.price, p.id as part_id, p.name, p.image_url, c.name as category_name
        FROM order_items oi
        JOIN parts p ON oi.part_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE oi.order_id = ?
    ");
    $items_stmt->execute([$order_id]);
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
        // Table might not exist yet, continue without status history
        $status_history = [];
    }

} catch (Exception $e) {
    error_log("Failed to fetch order: " . $e->getMessage());
    $_SESSION['error'] = "Failed to load order details.";
    header("Location: orders.php");
    exit();
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
                'message' => 'Your order has been received and is awaiting processing.'
            ];
        case 'processing': 
            return [
                'bg_color' => 'bg-blue-50',
                'border_color' => 'border-blue-200',
                'text_color' => 'text-blue-800',
                'badge_color' => 'bg-blue-100 text-blue-800',
                'icon' => 'fas fa-cog',
                'message' => 'We are preparing your order for shipment.'
            ];
        case 'shipped': 
            return [
                'bg_color' => 'bg-purple-50',
                'border_color' => 'border-purple-200',
                'text_color' => 'text-purple-800',
                'badge_color' => 'bg-purple-100 text-purple-800',
                'icon' => 'fas fa-shipping-fast',
                'message' => 'Your order is on the way!'
            ];
        case 'delivered': 
            return [
                'bg_color' => 'bg-green-50',
                'border_color' => 'border-green-200',
                'text_color' => 'text-green-800',
                'badge_color' => 'bg-green-100 text-green-800',
                'icon' => 'fas fa-check-circle',
                'message' => 'Your order has been delivered successfully!'
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
  <title>Order Tracking - AutoParts Hub</title>

  <!-- ✅ Correct Tailwind CDN -->
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
    
    /* Status-specific animations */
    .status-pending { animation: pulse-yellow 2s infinite; }
    .status-processing { animation: pulse-blue 2s infinite; }
    .status-shipped { animation: pulse-purple 2s infinite; }
    .status-delivered { animation: pulse-green 2s infinite; }
    .status-cancelled { animation: pulse-red 2s infinite; }
    
    @keyframes pulse-yellow {
      0%, 100% { background-color: #fef3c7; }
      50% { background-color: #fde68a; }
    }
    @keyframes pulse-blue {
      0%, 100% { background-color: #dbeafe; }
      50% { background-color: #93c5fd; }
    }
    @keyframes pulse-purple {
      0%, 100% { background-color: #f3e8ff; }
      50% { background-color: #d8b4fe; }
    }
    @keyframes pulse-green {
      0%, 100% { background-color: #dcfce7; }
      50% { background-color: #86efac; }
    }
    @keyframes pulse-red {
      0%, 100% { background-color: #fee2e2; }
      50% { background-color: #fca5a5; }
    }
  </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 text-gray-900 min-h-screen">

  <?php include 'includes/buyer_header.php'; ?>

  <!-- Main Content -->
  <div class="relative z-10">
    <!-- Page Header -->
    <div class="py-16 bg-gradient-to-r from-blue-600 to-blue-800 text-white shadow-lg">
      <div class="container mx-auto px-6 text-center">
        <div class="mb-6">
          <i class="fas fa-receipt text-6xl md:text-7xl text-white opacity-90"></i>
        </div>
        <h1 class="text-4xl md:text-5xl font-bold mb-4">Order Tracking</h1>
        <p class="text-blue-100 text-lg md:text-xl max-w-2xl mx-auto leading-relaxed">
          Track your order <strong>#<?= htmlspecialchars($order['id']) ?></strong> and delivery progress.
        </p>
      </div>
    </div>

    <!-- Order Details -->
    <div class="container mx-auto px-6 py-8 -mt-4">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 max-w-7xl mx-auto">
        
        <!-- Order Tracking & Summary -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Order Tracking Timeline -->
          <div class="bg-white rounded-2xl shadow-lg border border-blue-100 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-50 to-cyan-50 px-6 py-4 border-b border-blue-100">
              <h2 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-shipping-fast mr-3 text-blue-600"></i>
                Order Tracking
              </h2>
            </div>
            
            <div class="p-6">
              <!-- Current Status -->
              <div class="<?= $status_theme['bg_color'] ?> border <?= $status_theme['border_color'] ?> rounded-xl p-4 mb-6 status-<?= $order['status'] ?>">
                <div class="flex items-center justify-between">
                  <div>
                    <h3 class="font-semibold <?= $status_theme['text_color'] ?> text-lg">Current Status</h3>
                    <p class="<?= $status_theme['text_color'] ?>">
                      <?= ucfirst($order['status']) ?> - <?= $status_theme['message'] ?>
                    </p>
                  </div>
                  <span class="inline-flex items-center px-4 py-2 <?= $status_theme['badge_color'] ?> rounded-full font-semibold status-badge">
                    <i class="<?= $status_theme['icon'] ?> mr-2"></i>
                    <?= strtoupper($order['status']) ?>
                  </span>
                </div>
              </div>

              <!-- Tracking Timeline -->
              <div class="space-y-6">
                <?php
                $timeline_steps = [
                    'pending' => ['Order Placed', 'Your order has been received', 'fas fa-shopping-cart', 'yellow'],
                    'processing' => ['Processing', 'We are preparing your order', 'fas fa-cog', 'blue'],
                    'shipped' => ['Shipped', 'Your order is on the way', 'fas fa-shipping-fast', 'purple'],
                    'delivered' => ['Delivered', 'Your order has been delivered', 'fas fa-check-circle', 'green']
                ];
                
                $current_step = array_search($order['status'], array_keys($timeline_steps));
                if ($current_step === false) $current_step = -1;
                ?>

                <?php foreach ($timeline_steps as $step => $step_info): ?>
                  <?php
                  $step_index = array_search($step, array_keys($timeline_steps));
                  $is_completed = $step_index <= $current_step;
                  $is_current = $step_index === $current_step;
                  $is_future = $step_index > $current_step;
                  
                  // Get step color
                  $step_color = match($step_info[3]) {
                      'yellow' => ['completed' => 'bg-yellow-500', 'current' => 'bg-yellow-500', 'future' => 'bg-gray-300'],
                      'blue' => ['completed' => 'bg-blue-500', 'current' => 'bg-blue-500', 'future' => 'bg-gray-300'],
                      'purple' => ['completed' => 'bg-purple-500', 'current' => 'bg-purple-500', 'future' => 'bg-gray-300'],
                      'green' => ['completed' => 'bg-green-500', 'current' => 'bg-green-500', 'future' => 'bg-gray-300'],
                      default => ['completed' => 'bg-gray-500', 'current' => 'bg-gray-500', 'future' => 'bg-gray-300']
                  };
                  
                  $badge_color = match($step_info[3]) {
                      'yellow' => 'bg-yellow-100 text-yellow-800',
                      'blue' => 'bg-blue-100 text-blue-800',
                      'purple' => 'bg-purple-100 text-purple-800',
                      'green' => 'bg-green-100 text-green-800',
                      default => 'bg-gray-100 text-gray-800'
                  };
                  ?>
                  
                  <div class="flex items-start">
                    <!-- Timeline dot -->
                    <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center mr-4 timeline-dot
                      <?= $is_completed ? $step_color['completed'] . ' shadow-lg' : ($is_current ? $step_color['current'] . ' animate-pulse' : $step_color['future']) ?>">
                      <?php if ($is_completed): ?>
                        <i class="fas fa-check text-white text-sm"></i>
                      <?php elseif ($is_current): ?>
                        <i class="<?= $step_info[2] ?> text-white text-sm"></i>
                      <?php else: ?>
                        <i class="<?= $step_info[2] ?> text-gray-500 text-sm"></i>
                      <?php endif; ?>
                    </div>
                    
                    <!-- Timeline content -->
                    <div class="flex-1 pb-8 <?= $step_index < count($timeline_steps) - 1 ? 'border-l-2 border-gray-300' : '' ?>">
                      <div class="ml-4">
                        <div class="flex items-center">
                          <h4 class="font-semibold text-gray-800 text-lg <?= $is_current ? 'text-' . $step_info[3] . '-600' : '' ?>">
                            <?= $step_info[0] ?>
                          </h4>
                          <?php if ($is_current): ?>
                            <span class="ml-3 text-sm <?= $badge_color ?> px-3 py-1 rounded-full font-medium">Current</span>
                          <?php elseif ($is_completed): ?>
                            <span class="ml-3 text-sm <?= $badge_color ?> px-3 py-1 rounded-full font-medium">Completed</span>
                          <?php endif; ?>
                        </div>
                        <p class="text-gray-600 mt-1"><?= $step_info[1] ?></p>
                        
                        <!-- Show estimated time for current and future steps -->
                        <?php if ($is_current || $is_future): ?>
                          <p class="text-gray-500 text-sm mt-2 flex items-center">
                            <i class="fas fa-clock mr-2"></i>
                            <?php
                            $estimated_times = [
                                'pending' => 'Usually processed within 24 hours',
                                'processing' => 'Typically takes 1-2 business days',
                                'shipped' => 'Delivery in 3-7 business days',
                                'delivered' => 'Delivery completed'
                            ];
                            echo $estimated_times[$step];
                            ?>
                          </p>
                        <?php endif; ?>
                        
                        <!-- Show actual timestamp for completed steps -->
                        <?php if ($is_completed && !empty($status_history)): ?>
                          <?php 
                          $step_history = array_filter($status_history, function($history) use ($step) {
                              return $history['status'] === $step;
                          });
                          $step_history = array_shift($step_history);
                          ?>
                          <?php if ($step_history): ?>
                            <p class="text-green-600 text-sm mt-1 flex items-center">
                              <i class="fas fa-calendar-check mr-2"></i>
                              Completed on <?= date('M j, Y g:i A', strtotime($step_history['created_at'])) ?>
                            </p>
                          <?php endif; ?>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Tracking Number (if shipped) -->
              <?php if ($order['status'] === 'shipped' || $order['status'] === 'delivered'): ?>
                <div class="mt-8 p-4 bg-purple-50 border border-purple-200 rounded-xl">
                  <h4 class="font-semibold text-purple-800 mb-3 flex items-center">
                    <i class="fas fa-box mr-2"></i>
                    Tracking Information
                  </h4>
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between space-y-3 sm:space-y-0">
                    <div>
                      <p class="text-purple-700 font-medium">Tracking Number: <span class="font-mono">TRK<?= $order['id'] ?>IN</span></p>
                      <p class="text-purple-600 text-sm">Carrier: AutoParts Hub Logistics</p>
                    </div>
                    <a href="#" class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition font-medium">
                      <i class="fas fa-external-link-alt mr-2"></i>Track Package
                    </a>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Order Items -->
          <div class="bg-white rounded-2xl shadow-lg border border-green-100 overflow-hidden">
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 border-b border-green-100">
              <h2 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-list-alt mr-3 text-green-600"></i>
                Order Items
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
                      <div class="flex items-center text-sm text-gray-600">
                        <span class="mr-4 bg-white px-3 py-1 rounded-lg border border-gray-300 font-medium">Qty: <?= $item['quantity'] ?></span>
                        <span class="font-semibold text-gray-800">₹<?= number_format($item['price'], 0) ?> each</span>
                      </div>
                    </div>
                    <div class="text-right">
                      <p class="text-lg font-bold text-gray-800">₹<?= number_format($item['price'] * $item['quantity'], 0) ?></p>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Order Total -->
              <div class="border-t pt-6 mt-6">
                <div class="flex justify-between items-center text-xl font-bold bg-gradient-to-r from-gray-50 to-blue-50 p-4 rounded-xl">
                  <span class="text-gray-800">Total Amount</span>
                  <span class="text-blue-600">₹<?= number_format($order['total_amount'], 0) ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Order Summary Card -->
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
                  <span class="text-gray-600">Order Date:</span>
                  <span class="text-gray-800"><?= date('F j, Y', strtotime($order['created_at'])) ?></span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-gray-600">Items:</span>
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
                  <span class="text-gray-800">Total Amount:</span>
                  <span class="text-purple-600">₹<?= number_format($order['total_amount'], 0) ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Order Actions -->
          <div class="bg-white rounded-2xl shadow-lg border border-orange-100 overflow-hidden">
            <div class="bg-gradient-to-r from-orange-50 to-amber-50 px-6 py-4 border-b border-orange-100">
              <h2 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-cogs mr-3 text-orange-600"></i>
                Order Actions
              </h2>
            </div>
            <div class="p-6">
              <div class="space-y-4">
                <!-- Cancel Order Button -->
                <?php if ($order['status'] === 'pending' || $order['status'] === 'processing'): ?>
                  <form method="POST" action="cancel_order.php" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <button type="submit" 
                            class="w-full flex items-center justify-center px-4 py-3 bg-red-500 hover:bg-red-600 text-white rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg">
                      <i class="fas fa-times-circle mr-3"></i>
                      Cancel Order
                    </button>
                  </form>
                <?php elseif ($order['status'] === 'cancelled'): ?>
                  <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
                    <i class="fas fa-ban text-red-500 text-2xl mb-2"></i>
                    <p class="text-red-700 font-medium">This order has been cancelled</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Shipping Information -->
          <div class="bg-white rounded-2xl shadow-lg border border-green-100 overflow-hidden">
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 border-b border-green-100">
              <h2 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-truck mr-3 text-green-600"></i>
                Shipping Information
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

          <!-- Support Card -->
          <div class="bg-gradient-to-r from-orange-50 to-amber-50 rounded-2xl shadow-lg border border-orange-100 overflow-hidden">
            <div class="p-6 text-center">
              <i class="fas fa-headset text-3xl text-orange-500 mb-3"></i>
              <h3 class="font-bold text-gray-800 mb-2">Need Help?</h3>
              <p class="text-sm text-gray-600 mb-4">Our support team is here to assist you</p>
              <a href="ticket_form.php?order_id=<?= $order['id'] ?>" 
                 class="inline-flex items-center px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition">
                <i class="fas fa-question-circle mr-2"></i>
                Contact Support
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/buyer_footer.php'; ?>

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

      // Add animation to current timeline step
      const currentTimelineDot = document.querySelector('.timeline-dot.animate-pulse');
      if (currentTimelineDot) {
        setInterval(() => {
          currentTimelineDot.classList.toggle('opacity-100');
          currentTimelineDot.classList.toggle('opacity-80');
        }, 1000);
      }
    });
  </script>
</body>
</html>