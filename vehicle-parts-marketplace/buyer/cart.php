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

// Handle quantity updates and removals
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_quantity'])) {
        $cart_item_id = (int)$_POST['cart_item_id'];
        $quantity = (int)$_POST['quantity'];
        
        if ($quantity > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE id = ? AND buyer_id = ?");
                $stmt->execute([$quantity, $cart_item_id, $user_id]);
                $_SESSION['success'] = "Cart updated successfully!";
            } catch (Exception $e) {
                error_log("Cart update failed: " . $e->getMessage());
                $_SESSION['error'] = "Failed to update cart.";
            }
        }
    } elseif (isset($_POST['remove_item'])) {
        $cart_item_id = (int)$_POST['cart_item_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM cart_items WHERE id = ? AND buyer_id = ?");
            $stmt->execute([$cart_item_id, $user_id]);
            $_SESSION['success'] = "Item removed from cart!";
        } catch (Exception $e) {
            error_log("Cart removal failed: " . $e->getMessage());
            $_SESSION['error'] = "Failed to remove item from cart.";
        }
    }
    
    header("Location: cart.php");
    exit();
}

// Fetch cart items with part details
$cart_items = [];
$cart_total = 0;
$cart_count = 0;

try {
    $stmt = $pdo->prepare("
        SELECT ci.id as cart_item_id, ci.quantity, 
               p.id as part_id, p.name, p.price, p.stock_quantity as stock, 
               p.image_url, c.name as category_name, p.description
        FROM cart_items ci
        JOIN parts p ON ci.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE ci.buyer_id = ? AND p.status = 'active'
        ORDER BY ci.added_at DESC
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    foreach ($cart_items as $item) {
        $cart_total += $item['price'] * $item['quantity'];
        $cart_count += $item['quantity'];
    }
    
} catch (Exception $e) {
    error_log("Failed to fetch cart items: " . $e->getMessage());
}

// Calculate additional costs
$shipping_cost = $cart_total > 0 ? 99 : 0; // ₹99 shipping
$tax_amount = $cart_total > 0 ? $cart_total * 0.08 : 0; // 8% tax
$final_total = $cart_total + $shipping_cost + $tax_amount;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Shopping Cart - Buyer Dashboard</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    .cart-item-image {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border-radius: 8px;
    }
    .quantity-btn {
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid #e5e7eb;
      background: white;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .quantity-btn:hover {
      background: #f3f4f6;
    }
    .quantity-input {
      width: 50px;
      height: 32px;
      text-align: center;
      border: 1px solid #e5e7eb;
      border-left: none;
      border-right: none;
    }
    .line-clamp-1 {
      display: -webkit-box;
      -webkit-line-clamp: 1;
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
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Shopping Cart</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Review and manage your cart items before checkout.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-6 py-8">
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
      <!-- Cart Items -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
          <div class="p-6 border-b">
            <h2 class="text-xl font-bold text-gray-800">Cart Items (<?= $cart_count ?>)</h2>
          </div>
          
          <div class="p-6">
            <?php if (empty($cart_items)): ?>
              <div class="text-center py-12">
                <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-medium text-gray-500">Your cart is empty</h3>
                <p class="text-gray-400 mt-2">Add some parts to your cart to get started.</p>
                <a href="../buyer/browse_parts.php" class="inline-block mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                  Browse Parts
                </a>
              </div>
            <?php else: ?>
              <div class="space-y-6">
                <?php foreach ($cart_items as $item): ?>
                  <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                    <!-- Image -->
                    <div class="flex-shrink-0 mr-4">
                      <?php if ($item['image_url']): ?>
                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="cart-item-image">
                      <?php else: ?>
                        <div class="cart-item-image bg-gray-200 rounded-lg flex items-center justify-center">
                          <i class="fas fa-cog text-gray-400"></i>
                        </div>
                      <?php endif; ?>
                    </div>
                    
                    <!-- Info -->
                    <div class="flex-1 mr-4">
                      <h3 class="font-semibold text-gray-800 mb-1"><?= htmlspecialchars($item['name']) ?></h3>
                      <span class="capitalize text-sm text-blue-600 mb-2"><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></span>
                      <p class="text-gray-600 text-sm line-clamp-1"><?= htmlspecialchars($item['description']) ?></p>
                      <!-- ✅ Price in Rupees without decimal -->
                      <p class="text-lg font-bold text-blue-600 mt-2">₹<?= number_format($item['price'], 0) ?></p>
                      <div class="text-sm <?= $item['stock'] > 0 ? 'text-green-600' : 'text-red-600' ?> mt-1">
                        <i class="fas fa-boxes mr-1"></i> <?= $item['stock'] ?> in stock
                      </div>
                    </div>
                    
                    <!-- Quantity Controls -->
                    <div class="flex items-center space-x-2 mr-4">
                      <form method="POST" class="flex items-center" onsubmit="return false;">
                        <input type="hidden" name="cart_item_id" value="<?= $item['cart_item_id'] ?>">
                        <button type="button" 
                                class="quantity-btn rounded-l-md" 
                                <?= $item['quantity'] <= 1 ? 'disabled' : '' ?>
                                onclick="updateQuantity(<?= $item['cart_item_id'] ?>, <?= $item['quantity'] - 1 ?>)">
                          <i class="fas fa-minus text-xs"></i>
                        </button>
                        <input type="number" 
                               name="quantity" 
                               value="<?= $item['quantity'] ?>" 
                               min="1" 
                               max="<?= $item['stock'] ?>" 
                               class="quantity-input" 
                               id="quantity-<?= $item['cart_item_id'] ?>"
                               onchange="submitQuantity(<?= $item['cart_item_id'] ?>)">
                        <button type="button" 
                                class="quantity-btn rounded-r-md" 
                                <?= $item['quantity'] >= $item['stock'] ? 'disabled' : '' ?>
                                onclick="updateQuantity(<?= $item['cart_item_id'] ?>, <?= $item['quantity'] + 1 ?>)">
                          <i class="fas fa-plus text-xs"></i>
                        </button>
                      </form>
                    </div>
                    
                    <!-- Total and Remove -->
                    <div class="text-right">
                      <!-- ✅ Total price in Rupees without decimal -->
                      <p class="text-lg font-bold text-gray-800">₹<?= number_format($item['price'] * $item['quantity'], 0) ?></p>
                      <form method="POST" class="mt-2">
                        <input type="hidden" name="cart_item_id" value="<?= $item['cart_item_id'] ?>">
                        <button type="submit" name="remove_item" class="text-red-600 hover:text-red-800 text-sm">
                          <i class="fas fa-trash mr-1"></i> Remove
                        </button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <!-- Order Summary -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-md overflow-hidden sticky top-24">
          <div class="p-6 border-b">
            <h2 class="text-xl font-bold text-gray-800">Order Summary</h2>
          </div>
          
          <div class="p-6">
            <div class="space-y-4">
              <div class="flex justify-between items-center">
                <span class="text-gray-600">Subtotal (<?= $cart_count ?> items)</span>
                <!-- ✅ Subtotal in Rupees without decimal -->
                <span class="font-medium">₹<?= number_format($cart_total, 0) ?></span>
              </div>
              
              <div class="flex justify-between items-center">
                <span class="text-gray-600">Shipping</span>
                <!-- ✅ Shipping in Rupees without decimal -->
                <span class="font-medium">₹<?= number_format($shipping_cost, 0) ?></span>
              </div>
              
              <div class="flex justify-between items-center">
                <span class="text-gray-600">Tax</span>
                <!-- ✅ Tax in Rupees without decimal -->
                <span class="font-medium">₹<?= number_format($tax_amount, 0) ?></span>
              </div>
              
              <div class="border-t pt-4 mt-4">
                <div class="flex justify-between items-center text-lg font-bold">
                  <span>Total</span>
                  <!-- ✅ Final total in Rupees without decimal -->
                  <span>₹<?= number_format($final_total, 0) ?></span>
                </div>
              </div>
              
              <div class="pt-4">
                <a href="checkout.php" 
                   class="block w-full text-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                   <?= empty($cart_items) ? 'disabled' : '' ?>>
                  Proceed to Checkout
                </a>
                
                <a href="../buyer/browse_parts.php" class="block w-full text-center mt-3 px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                  Continue Shopping
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/buyer_footer.php'; ?>

  <script>
    function updateQuantity(cartItemId, newQuantity) {
      const input = document.getElementById('quantity-' + cartItemId);
      const max = parseInt(input.max);
      const min = parseInt(input.min);
      
      if (newQuantity >= min && newQuantity <= max) {
        input.value = newQuantity;
        submitQuantity(cartItemId);
      }
    }

    function submitQuantity(cartItemId) {
      const form = document.querySelector(`input[name="cart_item_id"][value="${cartItemId}"]`).closest('form');
      const formData = new FormData(form);
      formData.append('update_quantity', '1');

      fetch('cart.php', {
        method: 'POST',
        body: formData
      })
      .then(() => window.location.reload())
      .catch(err => {
        alert('Failed to update cart. Please try again.');
        window.location.reload();
      });
    }
  </script>
</body>
</html>