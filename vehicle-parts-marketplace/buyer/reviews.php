<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has buyer role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('buyer', $roles)) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['name']);

// Fetch reviews by this buyer
$reviews = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.rating, r.comment, r.created_at, 
               p.name as part_name, p.image_url, p.id as part_id, p.price, p.stock_quantity,
               c.name as category_name
        FROM reviews r
        JOIN parts p ON r.part_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE r.buyer_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch reviews: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Reviews - Buyer Dashboard</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    .part-card {
      transition: all 0.3s ease;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }

    .part-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 12px 20px -4px rgba(37, 99, 235, 0.25);
    }

    .price-tag {
      background: linear-gradient(135deg, #2563eb, #1d4ed8);
      color: white;
      padding: 0.3rem 0.8rem;
      border-radius: 30px;
      font-weight: 600;
      font-size: 0.9rem;
    }

    .line-clamp-2 {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/buyer_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">My Reviews</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">See your feedback on purchased parts.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-4 py-8">
    <!-- Results Info -->
    <div class="flex justify-between items-center mb-6">
      <div class="text-gray-600">
        <?php if (empty($reviews)): ?>
          <p class="text-lg">No reviews found.</p>
        <?php else: ?>
          <p class="font-medium text-gray-800">
            Showing <span class="text-blue-600 font-semibold"><?= count($reviews) ?></span> review<?= count($reviews) !== 1 ? 's' : '' ?>
          </p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Reviews Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php if (empty($reviews)): ?>
        <div class="col-span-full text-center py-16 bg-white rounded-2xl shadow-sm border-dashed border-gray-300">
          <i class="fas fa-star-half-alt text-6xl text-gray-300 mb-4"></i>
          <h3 class="text-xl font-semibold text-gray-600 mb-2">No reviews yet</h3>
          <p class="text-gray-500 max-w-md mx-auto">Leave a review after purchasing and using parts.</p>
        </div>
      <?php else: ?>
        <?php foreach ($reviews as $review): ?>
          <div class="part-card bg-white rounded-2xl overflow-hidden shadow-sm border">
            <!-- Image -->
            <div class="relative h-48 bg-gray-50 overflow-hidden">
              <?php if ($review['image_url']): ?>
                <img src="<?= htmlspecialchars($review['image_url']) ?>" alt="<?= htmlspecialchars($review['part_name']) ?>"
                     class="w-full h-full object-cover">
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center bg-gray-100">
                  <i class="fas fa-cog text-gray-400 text-4xl"></i>
                </div>
              <?php endif; ?>
              <div class="absolute top-4 left-4">
                <span class="price-tag">₹<?= number_format($review['price'], 0) ?></span>
              </div>
              <?php if ($review['stock_quantity'] <= 5 && $review['stock_quantity'] > 0): ?>
                <div class="absolute top-4 right-4 bg-amber-500 text-white text-xs px-2.5 py-1 rounded-full font-medium">Low Stock</div>
              <?php elseif ($review['stock_quantity'] == 0): ?>
                <div class="absolute top-4 right-4 bg-red-500 text-white text-xs px-2.5 py-1 rounded-full font-medium">Out of Stock</div>
              <?php endif; ?>
            </div>

            <!-- Info -->
            <div class="p-5">
              <div class="flex justify-between items-start mb-3">
                <h3 class="font-semibold text-gray-800 text-base cursor-pointer hover:text-blue-600 line-clamp-1">
                  <?= htmlspecialchars($review['part_name']) ?>
                </h3>
                <span class="text-xs text-gray-500"><?= date('M j', strtotime($review['created_at'])) ?></span>
              </div>

              <!-- Rating and External Link -->
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center">
                  <div class="flex items-center">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300' ?> text-sm"></i>
                    <?php endfor; ?>
                    <span class="ml-1 text-xs text-gray-600">(<?= number_format($review['rating'], 1) ?>)</span>
                    <span class="ml-1 text-xs text-gray-400">• 1 review</span>
                  </div>
                </div>
                
                <!-- ✅ External Link Icon Below Stars -->
                <a href="view_part.php?id=<?= $review['part_id'] ?>" title="View Part" target="_blank" 
                   class="text-blue-600 hover:text-blue-800 transition duration-150 ease-in-out p-2 rounded-full hover:bg-blue-100">
                  <i class="fas fa-external-link-alt text-sm"></i>
                </a>
              </div>

              <!-- Category -->
              <span class="inline-block capitalize text-xs font-semibold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full mb-3">
                <?= htmlspecialchars($review['category_name'] ?? 'Unknown') ?>
              </span>

              <!-- Review Comment -->
              <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($review['comment']) ?></p>

              <!-- Stock Info -->
              <div class="flex justify-between text-sm text-gray-500">
                <span class="<?= $review['stock_quantity'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                  <i class="fas fa-boxes mr-1"></i> <?= $review['stock_quantity'] ?> in stock
                </span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php include 'includes/buyer_footer.php'; ?>
</body>
</html>