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
$user_name = htmlspecialchars($_SESSION['name']);

// Fetch orders for this seller
$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.id, o.total_amount, o.status, o.created_at as order_date, u.name as buyer_name
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN parts p ON oi.part_id = p.id
        JOIN users u ON o.buyer_id = u.id
        WHERE p.seller_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch orders: " . $e->getMessage());
}

// Get status color and icon
function getStatusTheme($status) {
    switch ($status) {
        case 'pending': 
            return [
                'bg_color' => 'bg-yellow-100',
                'text_color' => 'text-yellow-800',
                'icon' => 'fas fa-clock'
            ];
        case 'processing': 
            return [
                'bg_color' => 'bg-blue-100',
                'text_color' => 'text-blue-800',
                'icon' => 'fas fa-cog'
            ];
        case 'shipped': 
            return [
                'bg_color' => 'bg-purple-100',
                'text_color' => 'text-purple-800',
                'icon' => 'fas fa-shipping-fast'
            ];
        case 'delivered': 
            return [
                'bg_color' => 'bg-green-100',
                'text_color' => 'text-green-800',
                'icon' => 'fas fa-check-circle'
            ];
        case 'cancelled': 
            return [
                'bg_color' => 'bg-red-100',
                'text_color' => 'text-red-800',
                'icon' => 'fas fa-times-circle'
            ];
        default: 
            return [
                'bg_color' => 'bg-gray-100',
                'text_color' => 'text-gray-800',
                'icon' => 'fas fa-question-circle'
            ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Customer Orders - AutoParts Hub</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/seller_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white shadow-lg">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Customer Orders</h1>
      <p class="text-blue-100 text-lg md:text-xl max-w-2xl mx-auto leading-relaxed">
        Manage orders containing your auto parts and track their progress.
      </p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
      <div class="bg-gray-50 px-6 py-5 border-b border-gray-200">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
          <div>
            <h2 class="text-xl font-bold text-gray-800">Order Management</h2>
            <p class="text-gray-600 mt-1 text-sm">View and manage orders containing your parts</p>
          </div>
          <div class="mt-3 sm:mt-0 flex items-center space-x-2">
            <span class="text-sm text-gray-600">Total Orders:</span>
            <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-bold">
              <?= count($orders) ?>
            </span>
          </div>
        </div>
      </div>

      <div class="overflow-x-auto">
        <?php if (empty($orders)): ?>
          <div class="text-center py-16 px-6">
            <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-500">No orders yet</h3>
            <p class="text-gray-400 mt-2">Start selling parts to see customer orders here.</p>
            <a href="manage_parts.php" class="inline-block mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
              Manage Parts
            </a>
          </div>
        <?php else: ?>
          <table class="min-w-full">
            <thead class="bg-gray-800 text-white">
              <tr>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Order ID</th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wider">Customer</th>
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
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($order['buyer_name']) ?></div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="font-bold text-gray-800">₹<?= number_format($order['total_amount'], 0) ?></span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <?php
                    $status_theme = getStatusTheme($order['status']);
                    ?>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $status_theme['bg_color'] ?> <?= $status_theme['text_color'] ?>">
                      <i class="<?= $status_theme['icon'] ?> mr-1.5"></i>
                      <?= ucfirst(htmlspecialchars($order['status'])) ?>
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                    <?= date('M j, Y', strtotime($order['order_date'])) ?>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <a href="view_order.php?id=<?= (int)$order['id'] ?>" 
                       class="inline-flex items-center text-blue-600 hover:text-blue-800 hover:underline text-sm font-medium">
                      <i class="fas fa-eye mr-1.5"></i> View Details
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- Order Statistics -->
      <?php if (!empty($orders)): ?>
        <?php
        $status_counts = [
            'pending' => 0,
            'processing' => 0,
            'shipped' => 0,
            'delivered' => 0,
            'cancelled' => 0
        ];
        
        foreach ($orders as $order) {
            if (isset($status_counts[$order['status']])) {
                $status_counts[$order['status']]++;
            }
        }
        ?>
        
        <div class="border-t border-gray-200 bg-gray-50 px-6 py-4">
          <h3 class="text-lg font-semibold text-gray-800 mb-3">Order Statistics</h3>
          <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="text-center p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
              <div class="text-2xl font-bold text-yellow-700"><?= $status_counts['pending'] ?></div>
              <div class="text-sm text-yellow-600 font-medium">Pending</div>
            </div>
            <div class="text-center p-3 bg-blue-50 border border-blue-200 rounded-lg">
              <div class="text-2xl font-bold text-blue-700"><?= $status_counts['processing'] ?></div>
              <div class="text-sm text-blue-600 font-medium">Processing</div>
            </div>
            <div class="text-center p-3 bg-purple-50 border border-purple-200 rounded-lg">
              <div class="text-2xl font-bold text-purple-700"><?= $status_counts['shipped'] ?></div>
              <div class="text-sm text-purple-600 font-medium">Shipped</div>
            </div>
            <div class="text-center p-3 bg-green-50 border border-green-200 rounded-lg">
              <div class="text-2xl font-bold text-green-700"><?= $status_counts['delivered'] ?></div>
              <div class="text-sm text-green-600 font-medium">Delivered</div>
            </div>
            <div class="text-center p-3 bg-red-50 border border-red-200 rounded-lg">
              <div class="text-2xl font-bold text-red-700"><?= $status_counts['cancelled'] ?></div>
              <div class="text-sm text-red-600 font-medium">Cancelled</div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <?php if (!empty($orders)): ?>
      <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-gradient-to-r from-blue-50 to-cyan-50 rounded-xl shadow-lg border border-blue-200 p-6">
          <div class="flex items-center">
            <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
              <i class="fas fa-clock text-blue-600 text-xl"></i>
            </div>
            <div>
              <h3 class="font-semibold text-gray-800">Pending Orders</h3>
              <p class="text-2xl font-bold text-blue-600"><?= $status_counts['pending'] ?></p>
            </div>
          </div>
        </div>
        
        <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl shadow-lg border border-green-200 p-6">
          <div class="flex items-center">
            <div class="flex-shrink-0 w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
              <i class="fas fa-check-circle text-green-600 text-xl"></i>
            </div>
            <div>
              <h3 class="font-semibold text-gray-800">Completed</h3>
              <p class="text-2xl font-bold text-green-600"><?= $status_counts['delivered'] ?></p>
            </div>
          </div>
        </div>
        
        <div class="bg-gradient-to-r from-purple-50 to-indigo-50 rounded-xl shadow-lg border border-purple-200 p-6">
          <div class="flex items-center">
            <div class="flex-shrink-0 w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
              <i class="fas fa-shipping-fast text-purple-600 text-xl"></i>
            </div>
            <div>
              <h3 class="font-semibold text-gray-800">In Transit</h3>
              <p class="text-2xl font-bold text-purple-600"><?= $status_counts['shipped'] + $status_counts['processing'] ?></p>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php include 'includes/seller_footer.php'; ?>
</body>
</html>