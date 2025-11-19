<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ✅ Check if user has 'buyer' role (even if multi-role)
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

// Fetch orders for this buyer
$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.id, o.total_amount, o.status, o.created_at as order_date, 
               COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.buyer_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch orders: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Orders - AutoParts Hub</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/buyer_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">My Orders</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Track your vehicle parts orders and delivery status.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
      <div class="bg-gray-50 px-6 py-5 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-800">Order History</h2>
        <p class="text-gray-600 mt-1 text-sm">Track your orders and delivery status.</p>
      </div>

      <div class="overflow-x-auto">
        <?php if (empty($orders)): ?>
          <div class="text-center py-16 px-6">
            <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-500">No orders yet</h3>
            <p class="text-gray-400 mt-2">Start shopping to see your orders here.</p>
            <a href="../buyer/browse_parts.php" class="inline-block mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
              Browse Parts
            </a>
          </div>
        <?php else: ?>
          <table class="min-w-full">
            <thead class="bg-gray-800 text-white">
              <tr>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Order ID</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Items</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Total</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Status</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Date</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $index => $order): ?>
                <tr class="hover:bg-gray-200 <?= $index % 2 === 0 ? 'bg-white' : 'bg-gray-100' ?>">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="font-mono font-medium text-gray-800">#<?= htmlspecialchars($order['id']) ?></span>
                  </td>
                  <td class="px-6 py-4">
                    <div class="font-medium text-gray-900"><?= $order['item_count'] ?> item(s)</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="font-bold text-gray-800">₹<?= number_format($order['total_amount'], 0) ?></span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <?php
                    $status = $order['status'];
                    $status_badge = match($status) {
                        'pending'    => ['bg-yellow-100', 'text-yellow-800', 'Pending'],
                        'processing' => ['bg-blue-100', 'text-blue-800', 'Processing'],
                        'shipped'    => ['bg-purple-100', 'text-purple-800', 'Shipped'],
                        'delivered'  => ['bg-green-100', 'text-green-800', 'Delivered'],
                        default      => ['bg-red-100', 'text-red-800', 'Cancelled']
                    };
                    ?>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $status_badge[0] ?> <?= $status_badge[1] ?>">
                      <?= $status_badge[2] ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                    <?= date('M j, Y', strtotime($order['order_date'])) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap space-x-3">
                    <a href="view_order.php?id=<?= (int)$order['id'] ?>" 
                       class="inline-flex items-center text-blue-600 hover:text-blue-800 hover:underline text-sm font-medium">
                      <i class="fas fa-eye mr-1.5"></i> View
                    </a>

                    <?php if ($order['status'] === 'pending' || $order['status'] === 'processing'): ?>
                      <form method="POST" action="cancel_order.php" class="inline" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <button type="submit" 
                                class="inline-flex items-center text-red-600 hover:text-red-800 hover:underline text-sm font-medium">
                          <i class="fas fa-times-circle mr-1.5"></i> Cancel
                        </button>
                      </form>
                    <?php elseif ($order['status'] === 'cancelled'): ?>
                      <span class="inline-flex items-center text-gray-500 text-sm">
                        <i class="fas fa-ban mr-1.5"></i> Cancelled
                      </span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include 'includes/buyer_footer.php'; ?>
</body>
</html>