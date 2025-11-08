<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ✅ Check if user has 'buyer' role
if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('buyer', $roles)) {
    header("Location: ../login.php");
    exit();
}

// ✅ All good
$user_name = htmlspecialchars($_SESSION['name']);
$user_id = $_SESSION['user_id'];

try {
    // --- Fetch Stats ---
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ?");
    $stmt->execute([$user_id]);
    $total_orders = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_orders = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ? AND status = 'delivered'");
    $stmt->execute([$user_id]);
    $delivered_orders = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wishlist_count = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as item_count, SUM(ci.quantity * p.price) as total 
        FROM cart_items ci 
        JOIN parts p ON ci.product_id = p.id 
        WHERE ci.buyer_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_data = $stmt->fetch();
    $cart_item_count = (int)$cart_data['item_count'];
    $cart_total = $cart_data['total'] ? number_format((float)$cart_data['total'], 0) : '0'; // Removed .00

    // --- Recent Cart Items ---
    $stmt = $pdo->prepare("
        SELECT ci.*, p.name, p.price, p.image_url 
        FROM cart_items ci 
        JOIN parts p ON ci.product_id = p.id 
        WHERE ci.buyer_id = ? 
        ORDER BY ci.added_at DESC 
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $recent_cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Unread Tickets ---
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM tickets t 
        WHERE t.user_id = ? 
          AND t.status != 'closed' 
          AND EXISTS (
            SELECT 1 FROM ticket_replies tr 
            WHERE tr.ticket_id = t.id 
              AND tr.is_read = FALSE 
              AND tr.sender_role = 'support'
          )
    ");
    $stmt->execute([$user_id]);
    $unread_tickets = (int)$stmt->fetchColumn();

    // --- Recent Activity ---
    $recent_activity = [];

    // Get recent orders
    $stmt = $pdo->prepare("
        SELECT id, created_at, status, COALESCE(total_amount, 0) as total_amount 
        FROM orders 
        WHERE buyer_id = ? 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($orders as $order) {
        $recent_activity[] = [
            'type' => 'order',
            'icon' => 'fa-boxes',
            'color' => 'blue',
            'title' => 'New order placed: #' . $order['id'],
            'details' => 'Total: ₹' . number_format((float)$order['total_amount'], 0), // Removed .00
            'time' => $order['created_at']
        ];
    }

    // Get recent wishlist additions
    $stmt = $pdo->prepare("
        SELECT w.created_at, p.name AS part_name 
        FROM wishlists w 
        JOIN parts p ON w.part_id = p.id 
        WHERE w.user_id = ? 
        ORDER BY w.created_at DESC 
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $wishlist_additions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($wishlist_additions as $wish) {
        $recent_activity[] = [
            'type' => 'wishlist',
            'icon' => 'fa-heart',
            'color' => 'red',
            'title' => 'Added to wishlist: ' . htmlspecialchars($wish['part_name']),
            'details' => '',
            'time' => $wish['created_at']
        ];
    }

    // Sort activity by time
    usort($recent_activity, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });

} catch (Exception $e) {
    error_log("Buyer dashboard error: " . $e->getMessage());
    $total_orders = $pending_orders = $delivered_orders = $wishlist_count = $cart_item_count = 0;
    $cart_total = '0';
    $recent_cart_items = $recent_activity = [];
    $unread_tickets = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Buyer Dashboard - AutoParts Hub</title>

  <!-- ✅ Tailwind & Font Awesome -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <style>
    .cart-item-image {
      width: 50px;
      height: 50px;
      object-fit: cover;
      border-radius: 8px;
    }
    .stats-card:hover {
      transform: translateY(-2px);
      transition: transform 0.2s ease;
    }
    .activity-item:hover {
      background-color: #f9fafb;
      transition: background-color 0.2s ease;
    }
    .status-badge {
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .pending      { @apply bg-yellow-100 text-yellow-800; }
    .processing   { @apply bg-blue-100 text-blue-800; }
    .shipped      { @apply bg-indigo-100 text-indigo-800; }
    .delivered    { @apply bg-green-100 text-green-800; }
    .cancelled    { @apply bg-red-100 text-red-800; }

    /* Truncate long text */
    .truncate-text {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 100%;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/buyer_header.php'; ?>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
    <!-- Total Orders -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600">
          <i class="fas fa-boxes fa-lg"></i>
        </div>
        <div class="ml-4">
          <div class="text-sm font-medium text-gray-500">Total Orders</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($total_orders) ?></div>
        </div>
      </div>
    </div>

    <!-- Pending Orders -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center text-yellow-600">
          <i class="fas fa-clock fa-lg"></i>
        </div>
        <div class="ml-4">
          <div class="text-sm font-medium text-gray-500">Pending Orders</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($pending_orders) ?></div>
        </div>
      </div>
    </div>

    <!-- Delivered Orders -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center text-green-600">
          <i class="fas fa-check-circle fa-lg"></i>
        </div>
        <div class="ml-4">
          <div class="text-sm font-medium text-gray-500">Delivered Orders</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($delivered_orders) ?></div>
        </div>
      </div>
    </div>

    <!-- Wishlist -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center text-red-600">
          <i class="fas fa-heart fa-lg"></i>
        </div>
        <div class="ml-4">
          <div class="text-sm font-medium text-gray-500">Wishlist Items</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($wishlist_count) ?></div>
        </div>
      </div>
    </div>

    <!-- Cart Summary -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center text-purple-600">
          <i class="fas fa-shopping-cart fa-lg"></i>
        </div>
        <div class="ml-4">
          <div class="text-sm font-medium text-gray-500">Cart Items</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($cart_item_count) ?></div>
          <div class="text-sm font-medium text-purple-600">Total: ₹<?= $cart_total ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Main Content Grid -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- My Cart -->
    <div class="bg-white rounded-xl shadow-md p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-gray-800">My Cart</h2>
        <a href="cart.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
          View Full Cart <i class="fas fa-arrow-right ml-1"></i>
        </a>
      </div>
      
      <?php if ($cart_item_count > 0): ?>
        <div class="space-y-4">
          <?php foreach ($recent_cart_items as $item): ?>
            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
              <?php if (!empty($item['image_url'])): ?>
                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="cart-item-image mr-4">
              <?php else: ?>
                <div class="cart-item-image bg-gray-200 rounded-lg flex items-center justify-center mr-4">
                  <i class="fas fa-cog text-gray-400"></i>
                </div>
              <?php endif; ?>
              <div class="flex-1">
                <p class="font-medium text-gray-800 truncate-text"><?= htmlspecialchars($item['name']) ?></p>
                <p class="text-sm text-gray-500">Qty: <?= (int)$item['quantity'] ?></p>
              </div>
              <!-- Removed the individual item price display -->
            </div>
          <?php endforeach; ?>
          
          <?php if ($cart_item_count > 3): ?>
            <div class="text-center pt-2">
              <p class="text-sm text-gray-500">+<?= $cart_item_count - 3 ?> more items</p>
            </div>
          <?php endif; ?>
          
          <div class="border-t pt-4 mt-4">
            <div class="flex justify-between items-center mb-2">
              <span class="font-medium text-gray-700">Subtotal:</span>
              <span class="font-bold text-lg text-purple-600">₹<?= $cart_total ?></span>
            </div>
            <a href="cart.php" class="block w-full mt-4 text-center px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition">
              Proceed to Checkout
            </a>
          </div>
        </div>
      <?php else: ?>
        <div class="text-center py-8">
          <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-shopping-cart text-gray-400 text-2xl"></i>
          </div>
          <h3 class="text-lg font-medium text-gray-600 mb-2">Your cart is empty</h3>
          <p class="text-gray-500 mb-4">Add vehicle parts to your cart</p>
          <a href="../buyer/browse_parts.php" class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            Browse Parts
          </a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Wishlist Preview -->
    <div class="bg-white rounded-xl shadow-md p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-gray-800">Wishlist Preview</h2>
        <a href="wishlist.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
          View All <i class="fas fa-arrow-right ml-1"></i>
        </a>
      </div>
      
      <?php if ($wishlist_count > 0): ?>
        <div class="space-y-4">
          <?php 
          // Re-fetch wishlist preview for simplicity
          $stmt = $pdo->prepare("
            SELECT w.id, p.name AS part_name, p.price, p.image_url 
            FROM wishlists w 
            JOIN parts p ON w.part_id = p.id 
            WHERE w.user_id = ? 
            ORDER BY w.created_at DESC 
            LIMIT 3
          ");
          $stmt->execute([$user_id]);
          $wishlist_preview = $stmt->fetchAll(PDO::FETCH_ASSOC);
          ?>
          <?php foreach ($wishlist_preview as $item): ?>
            <div class="flex items-center p-3 bg-gray-50 rounded-lg">
              <?php if (!empty($item['image_url'])): ?>
                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['part_name']) ?>" class="cart-item-image mr-4">
              <?php else: ?>
                <div class="cart-item-image bg-gray-200 rounded-lg flex items-center justify-center mr-4">
                  <i class="fas fa-cog text-gray-400"></i>
                </div>
              <?php endif; ?>
              <div class="flex-1">
                <p class="font-medium text-gray-800 truncate-text"><?= htmlspecialchars($item['part_name']) ?></p>
                <p class="text-sm text-gray-500">₹<?= number_format((float)$item['price'], 0) ?></p>
              </div>
              <!-- Removed "Remove" button -->
            </div>
          <?php endforeach; ?>
          
          <?php if ($wishlist_count > 3): ?>
            <div class="text-center pt-2">
              <p class="text-sm text-gray-500">+<?= $wishlist_count - 3 ?> more items</p>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="text-center py-8">
          <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-heart text-gray-400 text-2xl"></i>
          </div>
          <h3 class="text-lg font-medium text-gray-600 mb-2">Wishlist is empty</h3>
          <p class="text-gray-500 mb-4">Save your favorite parts here</p>
          <a href="../buyer/browse_parts.php" class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            Browse & Save
          </a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-xl shadow-md p-6">
      <h2 class="text-xl font-bold text-gray-800 mb-6">Recent Activity</h2>
      <div class="space-y-4">
        <?php if (empty($recent_activity)): ?>
          <div class="text-center py-8">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <i class="fas fa-history text-gray-400 text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-600 mb-2">No recent activity</h3>
            <p class="text-gray-500">Your actions will appear here.</p>
          </div>
        <?php else: ?>
          <?php foreach (array_slice($recent_activity, 0, 5) as $activity): ?>
            <div class="flex items-start p-4 bg-gray-50 rounded-lg activity-item">
              <div class="w-10 h-10 bg-<?= $activity['color'] ?>-100 rounded-full flex items-center justify-center mr-4">
                <i class="fas <?= $activity['icon'] ?> text-<?= $activity['color'] ?>-600"></i>
              </div>
              <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-800 truncate-text"><?= $activity['title'] ?></p>
                <?php if (!empty($activity['details'])): ?>
                  <p class="text-sm text-gray-500 truncate-text"><?= $activity['details'] ?></p>
                <?php endif; ?>
                <p class="text-xs text-gray-400 mt-1"><?= date('g:i A, M j', strtotime($activity['time'])) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <?php include 'includes/buyer_footer.php'; ?>
</body>
</html>