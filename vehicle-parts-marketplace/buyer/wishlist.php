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
$user_name = htmlspecialchars($_SESSION['name']);

// ✅ Check if user has buyer role (even if multi-role)
$has_buyer_role = in_array('buyer', $roles);

// Fetch cart items for the user
$cart_items = [];
if ($has_buyer_role) {
    try {
        $cart_stmt = $pdo->prepare("SELECT product_id FROM cart_items WHERE buyer_id = ?");
        $cart_stmt->execute([$user_id]);
        $cart_items = $cart_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Failed to fetch cart items: " . $e->getMessage());
    }
}

// Fetch wishlist items with part details
$wishlist = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.price, p.stock_quantity as stock, 
               p.image_url, p.description, c.name as category_name
        FROM wishlists w
        JOIN parts p ON w.part_id = p.id
        JOIN categories c ON p.category_id = c.id
        WHERE w.user_id = ? AND p.status = 'active'
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $wishlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch wishlist: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Wishlist - Buyer Dashboard</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    .part-card {
      transition: all 0.3s ease;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
      position: relative;
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
      color: #ef4444 !important;
    }

    .wishlist-btn:hover {
      background: #fef2f2;
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

    .line-clamp-2 {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .part-image-container {
      position: relative;
    }

    .added-to-cart {
      background: linear-gradient(135deg, #10b981, #059669);
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/buyer_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Wishlist</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Save your favorite parts for later purchase.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-4 py-8">
    <!-- Results Info -->
    <div class="flex justify-between items-center mb-6">
      <div class="text-gray-600">
        <?php if (empty($wishlist)): ?>
          <p class="text-lg">No items in wishlist.</p>
        <?php else: ?>
          <p class="font-medium text-gray-800">
            Showing <span class="text-blue-600 font-semibold"><?= count($wishlist) ?></span> item<?= count($wishlist) !== 1 ? 's' : '' ?> in wishlist
          </p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Wishlist Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php if (empty($wishlist)): ?>
        <div class="col-span-full text-center py-16 bg-white rounded-2xl shadow-sm border-dashed border-gray-300">
          <i class="fas fa-heart-broken text-6xl text-gray-300 mb-4"></i>
          <h3 class="text-xl font-semibold text-gray-600 mb-2">Your wishlist is empty</h3>
          <p class="text-gray-500 max-w-md mx-auto">Add items to your wishlist while browsing parts.</p>
          <a href="browse_parts.php" class="inline-block mt-4 px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700">
            Browse Parts
          </a>
        </div>
      <?php else: ?>
        <?php foreach ($wishlist as $item): ?>
          <div class="part-card bg-white rounded-2xl overflow-hidden shadow-sm border">
            <!-- Image Container -->
            <div class="part-image-container relative h-48 bg-gray-50 overflow-hidden">
              <?php if ($item['image_url']): ?>
                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>"
                     class="w-full h-full object-cover">
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center bg-gray-100">
                  <i class="fas fa-cog text-gray-400 text-4xl"></i>
                </div>
              <?php endif; ?>
              
              <!-- Wishlist Button - Inside image container -->
              <div class="wishlist-btn"
                   data-part-id="<?= $item['id'] ?>" onclick="handleWishlistAction(this, <?= $item['id'] ?>, event)">
                <i class="fas fa-heart heartbeat"></i>
              </div>

              <!-- Price Tag -->
              <div class="absolute top-4 left-4">
                <span class="price-tag">₹<?= number_format($item['price'], 0) ?></span>
              </div>
              
              <!-- Stock Status -->
              <?php if ($item['stock'] <= 5 && $item['stock'] > 0): ?>
                <div class="absolute top-4 right-16 bg-amber-500 text-white text-xs px-2.5 py-1 rounded-full font-medium">Low Stock</div>
              <?php elseif ($item['stock'] == 0): ?>
                <div class="absolute top-4 right-16 bg-red-500 text-white text-xs px-2.5 py-1 rounded-full font-medium">Out of Stock</div>
              <?php endif; ?>
            </div>

            <!-- Info -->
            <div class="p-5">
              <div class="flex justify-between items-start mb-3">
                <h3 class="font-semibold text-gray-800 text-base line-clamp-1">
                  <?= htmlspecialchars($item['name']) ?>
                </h3>
              </div>

              <span class="inline-block capitalize text-xs font-semibold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full mb-3">
                <?= htmlspecialchars($item['category_name']) ?>
              </span>
              
              <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($item['description'] ?? '') ?></p>

              <div class="flex justify-between text-sm text-gray-500 mb-4">
                <span class="<?= $item['stock'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                  <i class="fas fa-boxes mr-1"></i> <?= $item['stock'] ?> in stock
                </span>
              </div>

              <div class="flex space-x-3">
                <!-- ✅ Updated Add to Cart Functionality -->
                <form method="POST" action="cart/add_to_cart1.php" class="flex-1">
                  <input type="hidden" name="part_id" value="<?= $item['id'] ?>">
                  <input type="hidden" name="quantity" value="1">
                  <?php if ($has_buyer_role): ?>
                    <?php if (in_array($item['id'], $cart_items)): ?>
                      <button type="button" class="w-full px-3.5 py-2.5 bg-green-600 text-white rounded-xl cursor-default added-to-cart">
                        <i class="fas fa-check-circle mr-1.5"></i> Added to Cart
                      </button>
                    <?php else: ?>
                      <button type="submit" class="w-full px-3.5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl" <?= $item['stock'] <= 0 ? 'disabled' : '' ?>>
                        <i class="fas fa-shopping-cart mr-1.5"></i> <?= $item['stock'] > 0 ? 'Add to Cart' : 'Out of Stock' ?>
                      </button>
                    <?php endif; ?>
                  <?php else: ?>
                    <button type="button" class="w-full px-3.5 py-2.5 bg-gray-400 text-white rounded-xl cursor-not-allowed"
                            onclick="showRoleError()">
                      <i class="fas fa-ban mr-1.5"></i> Buyer Only
                    </button>
                  <?php endif; ?>
                </form>
                
                <!-- External Link Icon -->
                <a href="../buyer/view_part.php?id=<?= $item['id'] ?>" title="View Part" target="_blank" 
                   class="flex items-center justify-center text-blue-600 hover:text-blue-800 transition duration-150 ease-in-out p-2 rounded-full hover:bg-blue-100">
                  <i class="fas fa-external-link-alt text-sm"></i>
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php include 'includes/buyer_footer.php'; ?>

  <!-- Role Error Modal -->
  <div id="roleErrorModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl p-6 max-w-sm mx-4 text-center shadow-xl">
      <i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-4"></i>
      <h3 class="text-lg font-bold text-gray-800 mb-2">Permission Denied</h3>
      <p class="text-gray-600 mb-4">You need a buyer role to use this function.</p>
      <button onclick="hideRoleError()" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
        OK
      </button>
    </div>
  </div>

  <script>
    function handleWishlistAction(button, partId, event) {
      event.stopPropagation();
      const heartIcon = button.querySelector('i');
      heartIcon.className = 'fas fa-spinner fa-spin';
      
      const formData = new FormData();
      formData.append('part_id', partId);
      formData.append('action', 'remove');

      fetch('wishlist/toggle_wishlist.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            // Remove the card from the DOM
            button.closest('.part-card').remove();
            
            // Update the item count
            updateWishlistCount();
            
            showWishlistNotification(data.message);
          } else {
            showWishlistNotification('Error: ' + data.message);
            heartIcon.className = 'fas fa-heart heartbeat';
          }
        })
        .catch(() => {
          showWishlistNotification('Network error. Please try again.');
          heartIcon.className = 'fas fa-heart heartbeat';
        });
    }

    function updateWishlistCount() {
      const items = document.querySelectorAll('.part-card');
      const count = items.length;
      const countElement = document.querySelector('.text-blue-600.font-semibold');
      
      if (countElement) {
        countElement.textContent = count;
        
        // Update the text
        const parentText = countElement.closest('p');
        if (parentText) {
          parentText.innerHTML = `Showing <span class="text-blue-600 font-semibold">${count}</span> item${count !== 1 ? 's' : ''} in wishlist`;
        }
      }

      // Show empty state if no items left
      if (count === 0) {
        const grid = document.querySelector('.grid');
        grid.innerHTML = `
          <div class="col-span-full text-center py-16 bg-white rounded-2xl shadow-sm border-dashed border-gray-300">
            <i class="fas fa-heart-broken text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-600 mb-2">Your wishlist is empty</h3>
            <p class="text-gray-500 max-w-md mx-auto">Add items to your wishlist while browsing parts.</p>
            <a href="browse_parts.php" class="inline-block mt-4 px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700">
              Browse Parts
            </a>
          </div>
        `;
      }
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

    function showRoleError() {
      document.getElementById('roleErrorModal').classList.remove('hidden');
    }

    function hideRoleError() {
      document.getElementById('roleErrorModal').classList.add('hidden');
    }

    // ✅ REMOVED: Card click redirection - only external link icon works now
  </script>
</body>
</html>