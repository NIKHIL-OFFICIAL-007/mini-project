<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has buyer role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$roles = explode(',', $_SESSION['role'] ?? '');
if (!in_array('buyer', $roles)) {
    header("Location: ../login.php");
    exit();
}

$order_id = $_GET['order_id'] ?? null;
if (!$order_id || !is_numeric($order_id)) {
    header("Location: dashboard.php");
    exit();
}

// Fetch order details with shipping information
$order = [];
$order_items = [];
try {
    // Fetch order details
    $stmt = $pdo->prepare("
        SELECT id, total_amount, created_at, status,
               shipping_name, shipping_email, shipping_phone, 
               shipping_address, shipping_city, shipping_state, 
               shipping_zip_code, shipping_country
        FROM orders 
        WHERE id = ? AND buyer_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        // Order doesn't exist or doesn't belong to user
        header("Location: dashboard.php");
        exit();
    }

    // Fetch order items
    $items_stmt = $pdo->prepare("
        SELECT oi.quantity, oi.price, p.name, p.image_url, c.name as category_name
        FROM order_items oi
        JOIN parts p ON oi.part_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE oi.order_id = ?
    ");
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Failed to fetch order: " . $e->getMessage());
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Order Success - AutoParts Hub</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    .success-animation {
      animation: bounce 2s infinite;
    }
    @keyframes bounce {
      0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
      40% {transform: translateY(-10px);}
      60% {transform: translateY(-5px);}
    }
    .confetti {
      position: absolute;
      width: 10px;
      height: 10px;
      background: #ffd700;
      opacity: 0.7;
    }
  </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-blue-50 text-gray-900 min-h-screen">

  <?php include 'includes/buyer_header.php'; ?>

  <!-- Success Confetti Effect -->
  <div id="confetti-container" class="fixed inset-0 pointer-events-none z-0"></div>

  <!-- Main Content -->
  <div class="relative z-10">
    <!-- Success Header -->
    <div class="py-16 bg-gradient-to-r from-green-500 to-emerald-600 text-white shadow-lg">
      <div class="container mx-auto px-6 text-center">
        <div class="success-animation mb-6">
          <i class="fas fa-check-circle text-6xl md:text-7xl text-white opacity-90"></i>
        </div>
        <h1 class="text-4xl md:text-5xl font-bold mb-4">Order Confirmed!</h1>
        <p class="text-green-100 text-lg md:text-xl max-w-2xl mx-auto leading-relaxed">
          Thank you for your purchase. Your order <strong>#<?= htmlspecialchars($order['id']) ?></strong> has been successfully placed.
        </p>
      </div>
    </div>

    <!-- Order Details -->
    <div class="container mx-auto px-6 py-8 -mt-4">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 max-w-7xl mx-auto">
        
        <!-- Order Summary Card -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Order Status Card -->
          <div class="bg-white rounded-2xl shadow-lg border border-green-100 overflow-hidden">
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-4 border-b border-green-100">
              <h2 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-receipt mr-3 text-green-600"></i>
                Order Summary
              </h2>
            </div>
            <div class="p-6">
              <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                  <p class="text-sm text-gray-600">Order Number</p>
                  <p class="font-semibold text-gray-800">#<?= htmlspecialchars($order['id']) ?></p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Order Date</p>
                  <p class="font-semibold text-gray-800"><?= date('F j, Y', strtotime($order['created_at'])) ?></p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Status</p>
                  <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                    <i class="fas fa-check-circle mr-1"></i>
                    Confirmed
                  </span>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Total Amount</p>
                  <p class="text-xl font-bold text-green-600">₹<?= number_format($order['total_amount'], 0) ?></p>
                </div>
              </div>

              <!-- Order Items -->
              <div class="border-t pt-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Order Items</h3>
                <div class="space-y-4">
                  <?php foreach ($order_items as $item): ?>
                    <div class="flex items-center p-4 bg-gray-50 rounded-xl">
                      <div class="flex-shrink-0 mr-4">
                        <?php if ($item['image_url']): ?>
                          <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" 
                               class="w-16 h-16 object-cover rounded-lg">
                        <?php else: ?>
                          <div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center">
                            <i class="fas fa-cog text-gray-400 text-xl"></i>
                          </div>
                        <?php endif; ?>
                      </div>
                      <div class="flex-1">
                        <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($item['name']) ?></h4>
                        <p class="text-sm text-blue-600 capitalize"><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></p>
                        <div class="flex items-center mt-2 text-sm text-gray-600">
                          <span class="mr-4">Qty: <?= $item['quantity'] ?></span>
                          <span class="font-semibold text-gray-800">₹<?= number_format($item['price'], 0) ?> each</span>
                        </div>
                      </div>
                      <div class="text-right">
                        <p class="text-lg font-bold text-gray-800">₹<?= number_format($item['price'] * $item['quantity'], 0) ?></p>
                      </div>
                    </div>
                  <?php endforeach; ?>
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
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <h4 class="font-semibold text-gray-800 mb-2">Shipping Address</h4>
                  <p class="text-gray-600 leading-relaxed">
                    <?= htmlspecialchars($order['shipping_name']) ?><br>
                    <?= htmlspecialchars($order['shipping_address']) ?><br>
                    <?= htmlspecialchars($order['shipping_city']) ?>, <?= htmlspecialchars($order['shipping_state']) ?> - <?= htmlspecialchars($order['shipping_zip_code']) ?><br>
                    <?= htmlspecialchars($order['shipping_country']) ?>
                  </p>
                </div>
                <div>
                  <h4 class="font-semibold text-gray-800 mb-2">Contact Information</h4>
                  <p class="text-gray-600 mb-2">
                    <i class="fas fa-envelope mr-2 text-blue-500"></i>
                    <?= htmlspecialchars($order['shipping_email']) ?>
                  </p>
                  <p class="text-gray-600">
                    <i class="fas fa-phone mr-2 text-blue-500"></i>
                    <?= htmlspecialchars($order['shipping_phone']) ?>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions Sidebar -->
        <div class="space-y-6">
          <!-- Next Steps Card -->
          <div class="bg-white rounded-2xl shadow-lg border border-purple-100 overflow-hidden">
            <div class="bg-gradient-to-r from-purple-50 to-indigo-50 px-6 py-4 border-b border-purple-100">
              <h2 class="text-xl font-bold text-gray-800 flex items-center">
                <i class="fas fa-compass mr-3 text-purple-600"></i>
                Next Steps
              </h2>
            </div>
            <div class="p-6">
              <div class="space-y-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3 mt-1">
                    <i class="fas fa-envelope-open-text text-green-600 text-sm"></i>
                  </div>
                  <div>
                    <h4 class="font-semibold text-gray-800">Email Confirmation</h4>
                    <p class="text-sm text-gray-600">Check your email for order details and tracking information.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3 mt-1">
                    <i class="fas fa-shipping-fast text-blue-600 text-sm"></i>
                  </div>
                  <div>
                    <h4 class="font-semibold text-gray-800">Order Processing</h4>
                    <p class="text-sm text-gray-600">Your order will be processed within 24 hours.</p>
                  </div>
                </div>
                <div class="flex items-start">
                  <div class="flex-shrink-0 w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center mr-3 mt-1">
                    <i class="fas fa-headset text-orange-600 text-sm"></i>
                  </div>
                  <div>
                    <h4 class="font-semibold text-gray-800">Need Help?</h4>
                    <p class="text-sm text-gray-600">Contact our support team for any questions.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="p-6 space-y-4">
              <a href="orders.php" 
                 class="w-full flex items-center justify-center px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg">
                <i class="fas fa-list-alt mr-3"></i>
                View All Orders
              </a>
              <a href="browse_parts.php" 
                 class="w-full flex items-center justify-center px-6 py-3 border-2 border-blue-600 text-blue-600 hover:bg-blue-50 rounded-xl transition-all duration-300 transform hover:scale-105">
                <i class="fas fa-shopping-cart mr-3"></i>
                Continue Shopping
              </a>
              <a href="dashboard.php" 
                 class="w-full flex items-center justify-center px-6 py-3 border-2 border-gray-300 text-gray-700 hover:bg-gray-50 rounded-xl transition-all duration-300">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Back to Dashboard
              </a>
            </div>
          </div>

          <!-- Support Card -->
          <div class="bg-gradient-to-r from-orange-50 to-amber-50 rounded-2xl shadow-lg border border-orange-100 overflow-hidden">
            <div class="p-6 text-center">
              <i class="fas fa-headset text-3xl text-orange-500 mb-3"></i>
              <h3 class="font-bold text-gray-800 mb-2">Need Assistance?</h3>
              <p class="text-sm text-gray-600 mb-4">Our support team is here to help you</p>
              <a href="ticket_form.php" class="inline-flex items-center px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition">
                <i class="fas fa-phone mr-2"></i>
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
    // Confetti effect
    document.addEventListener('DOMContentLoaded', function() {
      const container = document.getElementById('confetti-container');
      const colors = ['#ffd700', '#ff6b6b', '#48dbfb', '#1dd1a1', '#f368e0'];
      
      for (let i = 0; i < 50; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.left = Math.random() * 100 + 'vw';
        confetti.style.animationDelay = Math.random() * 5 + 's';
        confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
        confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
        confetti.style.transform = `rotate(${Math.random() * 360}deg)`;
        container.appendChild(confetti);
      }

      // Remove confetti after animation
      setTimeout(() => {
        container.remove();
      }, 7000);
    });
  </script>
</body>
</html>