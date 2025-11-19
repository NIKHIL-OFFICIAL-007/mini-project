<?php
session_start();
include 'includes/config.php';

// ✅ Check login & approved seller role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
if (!isset($_SESSION['role']) || !isset($_SESSION['role_status'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('seller', $roles) || $_SESSION['role_status'] !== 'approved') {
    header("Location: ../login.php");
    exit();
}

$user_name = htmlspecialchars($_SESSION['name']);
$user_id = $_SESSION['user_id'];

try {
    // --- Stats ---
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM parts WHERE seller_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $total_parts = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT o.id)
        FROM orders o
        INNER JOIN order_items oi ON o.id = oi.order_id
        INNER JOIN parts p ON oi.part_id = p.id
        WHERE p.seller_id = ? AND o.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $pending_orders = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(oi.quantity * oi.price), 0)
        FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        INNER JOIN parts p ON oi.part_id = p.id
        WHERE p.seller_id = ? AND o.status = 'delivered'
    ");
    $stmt->execute([$user_id]);
    $total_revenue = number_format((int)$stmt->fetchColumn(), 0);

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM reviews r
        INNER JOIN parts p ON r.part_id = p.id
        WHERE p.seller_id = ? AND r.status = 'active'
    ");
    $stmt->execute([$user_id]);
    $total_reviews = (int)$stmt->fetchColumn();

    // --- Recent Reviews (last 5) ---
    $stmt = $pdo->prepare("
        SELECT r.id, r.rating, r.comment, r.created_at,
               u.name AS buyer_name, p.name AS part_name
        FROM reviews r
        INNER JOIN users u ON r.buyer_id = u.id
        INNER JOIN parts p ON r.part_id = p.id
        WHERE p.seller_id = ? AND r.status = 'active'
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Recent Parts (last 5) ---
    $stmt = $pdo->prepare("
        SELECT id, name, image_url
        FROM parts
        WHERE seller_id = ? AND status = 'active'
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Seller dashboard error: " . $e->getMessage());
    $total_parts = $pending_orders = $total_reviews = 0;
    $total_revenue = '0';
    $recent_reviews = $recent_parts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Seller Dashboard - AutoParts Hub</title>

  <!-- ✅ Tailwind & Font Awesome -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <style>
    .stats-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }
    .review-item,
    .part-item {
      transition: background-color 0.2s ease;
    }
    .review-item:hover,
    .part-item:hover {
      background-color: #f9fafb;
    }
    .part-image {
      width: 60px;
      height: 60px;
      object-fit: cover;
      border-radius: 8px;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/seller_header.php'; ?>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Parts -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card transition">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600">
          <i class="fas fa-cogs fa-lg"></i>
        </div>
        <div class="ml-4">
          <div class="text-sm font-medium text-gray-500">Parts Listed</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($total_parts) ?></div>
        </div>
      </div>
    </div>

    <!-- Pending Orders -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card transition">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center text-yellow-600">
          <i class="fas fa-truck fa-lg"></i>
        </div>
        <div class="ml-4">
          <div class="text-sm font-medium text-gray-500">Pending Orders</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($pending_orders) ?></div>
        </div>
      </div>
    </div>

    <!-- Revenue -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card transition">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center text-green-600">
          <i class="fas fa-rupee-sign fa-lg"></i>
        </div>
        <div class="ml-4">
          <div class="text-sm font-medium text-gray-500">Total Revenue</div>
          <div class="text-2xl font-bold text-gray-800">₹<?= $total_revenue ?></div>
        </div>
      </div>
    </div>

    <!-- Reviews -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card transition">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-teal-100 rounded-lg flex items-center justify-center text-teal-600">
          <i class="fas fa-star fa-lg"></i>
        </div>
        <div class="ml-4">
          <div class="text-sm font-medium text-gray-500">Total Reviews</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($total_reviews) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Two-Column Layout: Recent Reviews & Recent Parts -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">

    <!-- Recent Reviews -->
    <div class="bg-white rounded-xl shadow-md p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-gray-800">Recent Reviews</h2>
        <a href="reviews.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
          View All Reviews <i class="fas fa-arrow-right ml-1"></i>
        </a>
      </div>

      <?php if (!empty($recent_reviews)): ?>
        <div class="space-y-4">
          <?php foreach ($recent_reviews as $review): ?>
            <div class="p-4 bg-gray-50 rounded-lg review-item">
              <div class="flex items-start">
                <div class="flex-shrink-0">
                  <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center text-gray-600 font-bold">
                    <?= strtoupper(substr(htmlspecialchars($review['buyer_name']), 0, 1)) ?>
                  </div>
                </div>
                <div class="ml-4 flex-1">
                  <div class="flex items-center justify-between">
                    <h3 class="font-medium text-gray-800"><?= htmlspecialchars($review['part_name']) ?></h3>
                    <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">
                      <?= str_repeat('★', (int)$review['rating']) . str_repeat('☆', 5 - (int)$review['rating']) ?>
                    </span>
                  </div>
                  <p class="text-sm text-gray-500 mt-1">
                    by <?= htmlspecialchars($review['buyer_name']) ?>
                  </p>
                  <?php if (!empty($review['comment'])): ?>
                    <p class="text-gray-700 text-sm mt-2">
                      <?= htmlspecialchars(substr($review['comment'], 0, 80)) ?><?= strlen($review['comment']) > 80 ? '…' : '' ?>
                    </p>
                  <?php endif; ?>
                  <p class="text-xs text-gray-400 mt-2">
                    <?= date('M j, Y', strtotime($review['created_at'])) ?>
                  </p>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="text-center py-8">
          <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-star text-gray-400 text-2xl"></i>
          </div>
          <h3 class="text-lg font-medium text-gray-600 mb-2">No reviews yet</h3>
          <p class="text-gray-500">Reviews from buyers will appear here.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Recently Added Parts -->
    <div class="bg-white rounded-xl shadow-md p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-gray-800">Recently Added Parts</h2>
        <a href="manage_parts.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
          Manage All Parts <i class="fas fa-arrow-right ml-1"></i>
        </a>
      </div>

      <?php if (!empty($recent_parts)): ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
          <?php foreach ($recent_parts as $part): ?>
            <div class="part-item text-center">
              <div class="relative">
                <?php if (!empty($part['image_url'])): ?>
                  <img src="<?= htmlspecialchars($part['image_url']) ?>" 
                       alt="<?= htmlspecialchars($part['name']) ?>" 
                       class="part-image mx-auto">
                <?php else: ?>
                  <div class="part-image bg-gray-200 flex items-center justify-center mx-auto">
                    <i class="fas fa-cog text-gray-400"></i>
                  </div>
                <?php endif; ?>
              </div>
              <p class="mt-2 text-sm font-medium text-gray-800 truncate px-1">
                <?= htmlspecialchars($part['name']) ?>
              </p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="text-center py-8">
          <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-cogs text-gray-400 text-2xl"></i>
          </div>
          <h3 class="text-lg font-medium text-gray-600 mb-2">No parts listed yet</h3>
          <p class="text-gray-500">Add your first vehicle part!</p>
          <a href="add_part.php" class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Add Part
          </a>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- Quick Actions -->
  <div class="bg-white rounded-xl shadow-md p-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Quick Actions</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <a href="add_part.php" class="flex items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
        <i class="fas fa-plus-circle text-blue-600 mr-3"></i>
        <span class="font-medium text-gray-800">Add New Part</span>
      </a>
      <a href="manage_parts.php" class="flex items-center p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
        <i class="fas fa-list text-gray-600 mr-3"></i>
        <span class="font-medium text-gray-800">Manage Parts</span>
      </a>
      <a href="orders.php" class="flex items-center p-4 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition">
        <i class="fas fa-box-open text-yellow-600 mr-3"></i>
        <span class="font-medium text-gray-800">View Orders</span>
      </a>
      <a href="reviews.php" class="flex items-center p-4 bg-teal-50 hover:bg-teal-100 rounded-lg transition">
        <i class="fas fa-comments text-teal-600 mr-3"></i>
        <span class="font-medium text-gray-800">View Reviews</span>
      </a>
    </div>
  </div>

  <?php include 'includes/seller_footer.php'; ?>
</body>
</html>