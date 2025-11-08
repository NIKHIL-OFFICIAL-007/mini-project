<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Fetch user role
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// ✅ Check if user has buyer role (even if multi-role)
$roles = explode(',', $user['role']);
$has_buyer_role = in_array('buyer', $roles);

// Fetch cart items if buyer
$cart_items = [];
// Fetch wishlist items if buyer
$wishlist_items = [];
if ($has_buyer_role) {
    try {
        $cart_stmt = $pdo->prepare("SELECT product_id FROM cart_items WHERE buyer_id = ?");
        $cart_stmt->execute([$user_id]);
        $cart_items = $cart_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Fetch wishlist items
        $wishlist_stmt = $pdo->prepare("SELECT part_id FROM wishlists WHERE user_id = ?");
        $wishlist_stmt->execute([$user_id]);
        $wishlist_items = $wishlist_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Failed to fetch cart/wishlist items: " . $e->getMessage());
    }
}

// Get part ID
$part_id = $_GET['id'] ?? null;
if (!$part_id) {
    header("Location: ../browse_parts.php");
    exit();
}

// Fetch part data with category name
$part = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, c.name as category_name, p.price, p.stock_quantity as stock, 
               p.description, p.image_url, p.created_at, u.name as seller_name, u.email as seller_email
        FROM parts p
        LEFT JOIN users u ON p.seller_id = u.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id = ? AND p.status = 'active'
    ");
    $stmt->execute([$part_id]);
    $part = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$part) {
        header("Location: ../browse_parts.php");
        exit();
    }
} catch (Exception $e) {
    error_log("Failed to fetch part: " . $e->getMessage());
    header("Location: ../browse_parts.php");
    exit();
}

// Fetch average rating and review count for this part
$avg_rating = 0;
$review_count = 0;
try {
    $stmt = $pdo->prepare("
        SELECT AVG(rating) as avg, COUNT(*) as count 
        FROM reviews 
        WHERE part_id = ? AND status = 'active'
    ");
    $stmt->execute([$part_id]);
    $rating_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $avg_rating = $rating_data['avg'] ?? 0;
    $review_count = $rating_data['count'] ?? 0;
} catch (Exception $e) {
    error_log("Failed to fetch ratings: " . $e->getMessage());
}

// Check if current user has already reviewed this part
$has_reviewed = false;
$my_review = null;
if ($has_buyer_role) {
    try {
        $stmt = $pdo->prepare("SELECT id, rating, comment, created_at FROM reviews WHERE buyer_id = ? AND part_id = ? AND status = 'active'");
        $stmt->execute([$user_id, $part_id]);
        $my_review = $stmt->fetch(PDO::FETCH_ASSOC);
        $has_reviewed = $my_review !== false;
    } catch (Exception $e) {
        error_log("Failed to check existing review: " . $e->getMessage());
    }
}

// Check if user already requested an alert for this part
$has_alert = false;
if ($part['stock'] == 0 && $has_buyer_role) {
    try {
        $stmt = $pdo->prepare("SELECT alert_id FROM product_alerts WHERE user_id = ? AND part_id = ? AND status = 'pending'");
        $stmt->execute([$user_id, $part_id]);
        $has_alert = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Failed to check alert: " . $e->getMessage());
    }
}

// Fetch recent reviews (last 5)
$recent_reviews = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.rating, r.comment, r.created_at, u.name as buyer_name
        FROM reviews r
        JOIN users u ON r.buyer_id = u.id
        WHERE r.part_id = ? AND r.status = 'active'
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$part_id]);
    $recent_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch recent reviews: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($part['name']) ?> - AutoParts Hub</title>

  <!-- ✅ Tailwind & Font Awesome -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    .wishlist-btn {
      position: absolute;
      top: 16px;
      right: 16px;
      background: white;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 20;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .wishlist-btn:hover {
      transform: scale(1.15);
    }

    .wishlist-btn.active {
      color: #ef4444;
    }

    .wishlist-btn:not(.active) {
      color: #9ca3af;
    }

    .wishlist-btn:not(.active):hover {
      color: #ef4444;
    }

    .heartbeat {
      animation: heartbeat 1.5s ease-in-out infinite both;
    }

    @keyframes heartbeat {
      from { transform: scale(1); }
      10% { transform: scale(0.91); }
      17% { transform: scale(0.98); }
      33% { transform: scale(0.87); }
      45% { transform: scale(1); }
    }

    .added-to-cart {
      background: linear-gradient(135deg, #10b981, #059669);
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include '../includes/header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-3xl md:text-4xl font-bold mb-4"><?= htmlspecialchars($part['name']) ?></h1>
      <p class="text-blue-100 max-w-2xl mx-auto">Detailed information about this vehicle part.</p>
    </div>
  </div>

  <!-- Role Error Modal -->
  <div id="roleErrorModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg p-6 max-w-sm mx-4 text-center shadow-lg">
      <i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-4"></i>
      <h3 class="text-lg font-bold text-gray-800 mb-2">Permission Denied</h3>
      <p class="text-gray-600 mb-4">You need a buyer role to use this function.</p>
      <button onclick="hideRoleError()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
        OK
      </button>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-6 py-8">
    <div class="flex flex-col lg:flex-row gap-8">
      <!-- Product Image -->
      <div class="lg:w-1/2">
        <div class="relative h-80 lg:h-[500px] bg-gray-100 rounded-xl overflow-hidden">
          <!-- Wishlist Button -->
          <div class="wishlist-btn <?= $has_buyer_role ? (in_array($part['id'], $wishlist_items) ? 'active' : '') : 'cursor-not-allowed opacity-50' ?>"
               data-part-id="<?= $part['id'] ?>" onclick="handleWishlistAction(this, <?= $part['id'] ?>, event)">
            <i class="fas fa-heart <?= in_array($part['id'], $wishlist_items) ? 'heartbeat' : '' ?>"></i>
          </div>

          <?php if ($part['image_url']): ?>
            <img src="<?= htmlspecialchars($part['image_url']) ?>" alt="<?= htmlspecialchars($part['name']) ?>"
                 class="w-full h-full object-cover transition-transform duration-300 hover:scale-105">
          <?php else: ?>
            <div class="w-full h-full flex items-center justify-center">
              <i class="fas fa-cog text-gray-400 text-6xl"></i>
            </div>
          <?php endif; ?>
          
          <!-- Price Tag -->
          <div class="absolute top-4 left-4 bg-blue-600 text-white px-4 py-2 rounded-lg font-bold text-lg">
            ₹<?= number_format($part['price'], 0) ?>
          </div>
          
          <!-- Stock Status -->
          <?php if ($part['stock'] <= 5 && $part['stock'] > 0): ?>
            <div class="absolute top-4 right-16 bg-amber-500 text-white px-3 py-1 rounded-full text-sm">
              Low Stock
            </div>
          <?php elseif ($part['stock'] == 0): ?>
            <div class="absolute top-4 right-16 bg-red-500 text-white px-3 py-1 rounded-full text-sm">
              Out of Stock
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Product Info -->
      <div class="lg:w-1/2">
        <div class="bg-white rounded-xl shadow-md p-6">
          <div class="flex items-center mb-4">
            <span class="inline-block capitalize text-sm font-medium text-blue-600 bg-blue-100 px-2 py-1 rounded-full">
              <?= htmlspecialchars($part['category_name'] ?? 'Uncategorized') ?>
            </span>
            <span class="ml-2 text-sm text-gray-500">
              Added on <?= date('M j, Y', strtotime($part['created_at'])) ?>
            </span>
          </div>

          <h2 class="text-2xl font-bold mb-2"><?= htmlspecialchars($part['name']) ?></h2>
          <p class="text-gray-600 mb-6"><?= htmlspecialchars($part['description']) ?></p>

          <div class="space-y-4 mb-6">
            <div class="flex items-center">
              <i class="fas fa-store mr-2 text-blue-600"></i>
              <span class="text-gray-700"><strong>Seller:</strong> <?= htmlspecialchars($part['seller_name'] ?? 'Unknown') ?></span>
            </div>
            <div class="flex items-center">
              <i class="fas fa-envelope mr-2 text-blue-600"></i>
              <span class="text-gray-700"><strong>Email:</strong> <?= htmlspecialchars($part['seller_email'] ?? 'N/A') ?></span>
            </div>
            <div class="flex items-center">
              <i class="fas fa-boxes mr-2 text-blue-600"></i>
              <span class="text-gray-700"><strong>Stock:</strong> <?= $part['stock'] ?> in stock</span>
            </div>

            <!-- Average Rating -->
            <div class="flex items-center mt-2">
              <i class="fas fa-star text-yellow-400 mr-1"></i>
              <span class="font-semibold text-gray-800"><?= number_format($avg_rating, 1) ?></span>
              <span class="text-gray-500 ml-1">(<?= $review_count ?> reviews)</span>
            </div>
            <div class="flex items-center mt-1">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="fas fa-star <?= $i <= $avg_rating ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
              <?php endfor; ?>
            </div>
          </div>

          <div class="flex space-x-3">
            <!-- Conditional Buttons: Cart or Stock Alert -->
            <?php if ($part['stock'] > 0): ?>
              <!-- In Stock -->
              <form method="POST" action="cart/add_to_cart.php" class="flex-1">
                <input type="hidden" name="part_id" value="<?= $part['id'] ?>">
                <input type="hidden" name="quantity" value="1">
                <?php if ($has_buyer_role): ?>
                  <?php if (in_array($part['id'], $cart_items)): ?>
                    <button type="button"
                            class="w-full px-6 py-3 bg-green-600 text-white rounded-lg flex items-center justify-center cursor-default added-to-cart">
                      <i class="fas fa-check-circle mr-2"></i>
                      Added to Cart
                    </button>
                  <?php else: ?>
                    <button type="submit"
                            class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition flex items-center justify-center">
                      <i class="fas fa-shopping-cart mr-2"></i>
                      Add to Cart
                    </button>
                  <?php endif; ?>
                <?php else: ?>
                  <button type="button"
                          class="w-full px-6 py-3 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center justify-center"
                          onclick="showRoleError()">
                    <i class="fas fa-ban mr-2"></i> Buyer Only
                  </button>
                <?php endif; ?>
              </form>
            <?php else: ?>
              <!-- Out of Stock -->
              <?php if ($has_buyer_role): ?>
                <?php if ($has_alert): ?>
                  <button type="button"
                          onclick="removeStockAlert(<?= $part['id'] ?>)"
                          class="w-full px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition flex items-center justify-center">
                    <i class="fas fa-bell-slash mr-2"></i> Click to Unsubscribe
                  </button>
                <?php else: ?>
                  <button type="button"
                          onclick="requestStockAlert(<?= $part['id'] ?>)"
                          class="w-full px-6 py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition flex items-center justify-center">
                    <i class="fas fa-bell mr-2"></i> Notify Me When Available
                  </button>
                <?php endif; ?>
              <?php else: ?>
                <button type="button"
                        class="w-full px-6 py-3 bg-gray-400 text-white rounded-lg cursor-not-allowed flex items-center justify-center"
                        onclick="showRoleError()">
                  <i class="fas fa-ban mr-2"></i> Buyer Only
                </button>
              <?php endif; ?>
            <?php endif; ?>
            
            <!-- Back to Parts -->
            <a href="browse_parts.php" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition flex items-center justify-center">
              <i class="fas fa-arrow-left mr-2"></i> Back to Parts
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Reviews Section -->
  <div class="container mx-auto px-6 py-8">
    <div class="bg-white rounded-xl shadow-md p-6 mb-8">
      <h2 class="text-2xl font-bold text-gray-800 mb-6">Customer Reviews</h2>

      <!-- Review Submission / Edit Form -->
      <?php if ($has_buyer_role): ?>
        <?php if ($has_reviewed): ?>
          <!-- Edit Existing Review -->
          <div class="border-b pb-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Edit Your Review</h3>
            <form method="POST" action="update_review.php" class="space-y-4">
              <input type="hidden" name="review_id" value="<?= $my_review['id'] ?>">
              <input type="hidden" name="part_id" value="<?= $part['id'] ?>">

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Your Rating <span class="text-red-500">*</span></label>
                <div class="flex space-x-1">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label class="cursor-pointer relative" data-rating="<?= $i ?>">
                      <input type="radio" name="rating" value="<?= $i ?>" class="sr-only" required <?= $my_review['rating'] == $i ? 'checked' : '' ?>>
                      <i class="fas fa-star text-2xl text-<?= $i <= $my_review['rating'] ? 'yellow-400' : 'gray-300' ?> hover:text-yellow-400 transition"></i>
                    </label>
                  <?php endfor; ?>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Your Review</label>
                <textarea name="comment" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                          placeholder="Share your thoughts..." required><?= htmlspecialchars($my_review['comment']) ?></textarea>
              </div>

              <div class="flex space-x-4">
                <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
                  Update Review
                </button>
                <a href="delete_review.php?id=<?= $my_review['id'] ?>&part_id=<?= $part['id'] ?>" 
                   onclick="return confirm('Are you sure you want to delete your review? This cannot be undone.')" 
                   class="px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition">
                  Delete Review
                </a>
              </div>
            </form>
          </div>
        <?php else: ?>
          <!-- Submit New Review -->
          <div class="border-b pb-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Write a Review</h3>
            <form method="POST" action="add_review.php" class="space-y-4">
              <input type="hidden" name="part_id" value="<?= $part['id'] ?>">

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Your Rating <span class="text-red-500">*</span></label>
                <div class="flex space-x-1">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label class="cursor-pointer relative" data-rating="<?= $i ?>">
                      <input type="radio" name="rating" value="<?= $i ?>" class="sr-only" required>
                      <i class="fas fa-star text-2xl text-gray-300 hover:text-yellow-400 transition"></i>
                    </label>
                  <?php endfor; ?>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Your Review</label>
                <textarea name="comment" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                          placeholder="How was your experience with this part? Share your thoughts..."></textarea>
              </div>

              <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
                Submit Review
              </button>
            </form>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="bg-gray-50 p-4 rounded-lg mb-6">
          <p class="text-gray-600">Sign in as a buyer to leave a review.</p>
        </div>
      <?php endif; ?>

      <!-- Recent Reviews -->
      <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Reviews (<?= $review_count ?> total)</h3>
      
      <?php if ($review_count === 0): ?>
        <p class="text-gray-500 italic">Be the first to review this part!</p>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($recent_reviews as $review): ?>
            <div class="border-b pb-4 last:border-b-0">
              <div class="flex items-center justify-between mb-1">
                <div class="flex items-center">
                  <span class="font-medium text-gray-800"><?= htmlspecialchars($review['buyer_name']) ?></span>
                  <div class="flex ml-2">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                    <?php endfor; ?>
                  </div>
                </div>
                <span class="text-xs text-gray-400"><?= date('M j, Y', strtotime($review['created_at'])) ?></span>
              </div>
              <p class="text-gray-600 text-sm mb-1"><?= htmlspecialchars($review['comment']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($review_count > 5): ?>
          <a href="#" class="text-blue-600 hover:underline text-sm mt-4 inline-block">View All Reviews</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php include '../includes/footer.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const stars = document.querySelectorAll('.relative[data-rating]');
      const ratingInputs = document.querySelectorAll('input[name="rating"]');
      
      stars.forEach(star => {
        star.addEventListener('mouseover', function() {
          const rating = this.getAttribute('data-rating');
          stars.forEach(s => {
            if (s.getAttribute('data-rating') <= rating) {
              s.querySelector('i').style.color = '#f59e0b';
            } else {
              s.querySelector('i').style.color = '#d1d5db';
            }
          });
        });

        star.addEventListener('mouseout', function() {
          const selectedRating = document.querySelector('input[name="rating"]:checked')?.value || 0;
          stars.forEach(s => {
            const starRating = parseInt(s.getAttribute('data-rating'));
            if (starRating <= selectedRating) {
              s.querySelector('i').style.color = '#f59e0b';
            } else {
              s.querySelector('i').style.color = '#d1d5db';
            }
          });
        });

        star.addEventListener('click', function() {
          const rating = this.getAttribute('data-rating');
          ratingInputs.forEach(input => {
            input.checked = input.value === rating;
          });
          stars.forEach(s => {
            const starRating = parseInt(s.getAttribute('data-rating'));
            if (starRating <= rating) {
              s.querySelector('i').style.color = '#f59e0b';
            } else {
              s.querySelector('i').style.color = '#d1d5db';
            }
          });
        });
      });
    });

    function showRoleError() {
      document.getElementById('roleErrorModal').classList.remove('hidden');
    }

    function hideRoleError() {
      document.getElementById('roleErrorModal').classList.add('hidden');
    }

    function handleWishlistAction(button, partId, event) {
      event.stopPropagation();
      <?php if (!$has_buyer_role): ?>
        showRoleError();
        return;
      <?php else: ?>
        const isActive = button.classList.contains('active');
        const heartIcon = button.querySelector('i');
        heartIcon.className = 'fas fa-spinner fa-spin';
        const formData = new FormData();
        formData.append('part_id', partId);
        formData.append('action', isActive ? 'remove' : 'add');

        fetch('wishlist/toggle_wishlist.php', { method: 'POST', body: formData })
          .then(r => r.json())
          .then(data => {
            if (data.success) {
              button.classList.toggle('active');
              heartIcon.className = 'fas fa-heart' + (data.action === 'add' ? ' heartbeat' : '');
              showWishlistNotification(data.message);
            } else {
              showWishlistNotification('Error: ' + data.message);
              heartIcon.className = 'fas fa-heart' + (isActive ? ' heartbeat' : '');
            }
          })
          .catch(() => {
            showWishlistNotification('Network error. Please try again.');
            heartIcon.className = 'fas fa-heart' + (isActive ? ' heartbeat' : '');
          });
      <?php endif; ?>
    }

    function showWishlistNotification(message) {
      // Create notification element if it doesn't exist
      let n = document.getElementById('wishlistNotification');
      if (!n) {
        n = document.createElement('div');
        n.id = 'wishlistNotification';
        n.className = 'fixed top-4 right-4 p-4 bg-green-500 text-white rounded-lg shadow-lg max-w-xs z-50';
        document.body.appendChild(n);
      }
      
      n.innerHTML = `
        <div class="flex items-center">
          <i class="fas fa-check-circle mr-3"></i>
          <span>${message}</span>
          <button class="ml-4" onclick="this.parentElement.parentElement.remove()">
            <i class="fas fa-times"></i>
          </button>
        </div>
      `;
      
      setTimeout(() => {
        if (n && n.parentElement) {
          n.remove();
        }
      }, 3000);
    }

    async function requestStockAlert(partId) {
        const response = await fetch('request_alert.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `part_id=${partId}&action=request`
        });
        const result = await response.text();
        alert(result);
        location.reload();
    }

    async function removeStockAlert(partId) {
        const response = await fetch('request_alert.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `part_id=${partId}&action=remove`
        });
        const result = await response.text();
        alert(result);
        location.reload();
    }
  </script>
</body>
</html>