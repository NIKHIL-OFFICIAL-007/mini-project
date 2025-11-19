<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has approved support role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
if (!isset($_SESSION['role']) || !isset($_SESSION['role_status'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('support', $roles) || $_SESSION['role_status'] !== 'approved') {
    header("Location: ../login.php");
    exit();
}

$user_name = htmlspecialchars($_SESSION['name']);
$user_id = $_SESSION['user_id'];

try {
    // --- Stats ---
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets");
    $stmt->execute();
    $total_tickets = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE status = 'open'");
    $stmt->execute();
    $open_tickets = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE status = 'resolved'");
    $stmt->execute();
    $resolved_tickets = (int)$stmt->fetchColumn();

    // ✅ Replace "Pending Complaints" with "Total Orders"
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders");
    $stmt->execute();
    $total_orders = (int)$stmt->fetchColumn();

    // --- Recent Tickets (last 5) ---
    $stmt = $pdo->prepare("
        SELECT t.id, t.subject, t.status, t.priority, t.created_at,
               u.name AS user_name
        FROM tickets t
        INNER JOIN users u ON t.user_id = u.id
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Recent Orders (last 5) - Updated to get seller names properly ---
    $stmt = $pdo->prepare("
        SELECT 
            o.id, 
            o.total_amount, 
            o.status, 
            o.created_at,
            ub.name AS buyer_name,
            GROUP_CONCAT(DISTINCT COALESCE(us.name, 'Not Assigned') SEPARATOR ', ') AS seller_names
        FROM orders o
        LEFT JOIN users ub ON o.buyer_id = ub.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN parts p ON oi.part_id = p.id
        LEFT JOIN users us ON p.seller_id = us.id
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Support dashboard error: " . $e->getMessage());
    $total_tickets = $open_tickets = $resolved_tickets = $total_orders = 0;
    $recent_tickets = $recent_orders = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Support Dashboard - AutoParts Hub</title>

  <!-- ✅ Fixed Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <style>
    .stats-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }
    .ticket-item,
    .order-item {
      transition: background-color 0.2s ease;
    }
    .ticket-item:hover,
    .order-item:hover {
      background-color: #f9fafb;
    }
    .truncate-text {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    /* Status Badges */
    .status-open       { background-color: #dbeafe; color: #1e40af; }
    .status-in_progress{ background-color: #f3e8ff; color: #7e22ce; }
    .status-resolved   { background-color: #dcfce7; color: #166534; }
    .status-closed     { background-color: #f3f4f6; color: #374151; }

    /* Priority Badges */
    .priority-urgent { background-color: #fecaca; color: #dc2626; }
    .priority-high   { background-color: #fed7aa; color: #ea580c; }
    .priority-medium { background-color: #fef08a; color: #ca8a04; }
    .priority-low    { background-color: #f3f4f6; color: #6b7280; }

    /* Order Status Badges - Matching manage_orders.php */
    .status-pending    { background-color: #fef3c7; color: #d97706; }
    .status-processing { background-color: #dbeafe; color: #1d4ed8; }
    .status-shipped    { background-color: #e0e7ff; color: #4338ca; }
    .status-delivered  { background-color: #dcfce7; color: #166534; }
    .status-cancelled  { background-color: #fee2e2; color: #dc2626; }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/support_header.php'; ?>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Tickets -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card transition">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600">
          <i class="fas fa-ticket-alt fa-lg"></i>
        </div>
        <div class="ml-4">
          <div class="text-sm font-medium text-gray-500">Total Tickets</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($total_tickets) ?></div>
        </div>
      </div>
    </div>

    <!-- Open Tickets -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card transition">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center text-yellow-600">
          <i class="fas fa-clock fa-lg"></i>
        </div>
        <div class="ml-4">
          <div class="text-sm font-medium text-gray-500">Open Tickets</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($open_tickets) ?></div>
        </div>
      </div>
    </div>

    <!-- Resolved Tickets -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card transition">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center text-green-600">
          <i class="fas fa-check-circle fa-lg"></i>
        </div>
        <div class="ml-4">
          <div class="text-sm font-medium text-gray-500">Resolved Tickets</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($resolved_tickets) ?></div>
        </div>
      </div>
    </div>

    <!-- ✅ Total Orders -->
    <div class="bg-white p-6 rounded-xl shadow-md stats-card transition">
      <div class="flex items-center">
        <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center text-indigo-600">
          <i class="fas fa-boxes fa-lg"></i>
        </div>
        <div class="ml-4">
          <div class="text-sm font-medium text-gray-500">Total Orders</div>
          <div class="text-2xl font-bold text-gray-800"><?= number_format($total_orders) ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Two-Column Layout -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">

    <!-- Recent Tickets -->
    <div class="bg-white rounded-xl shadow-md p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-gray-800">Recent Tickets</h2>
        <a href="tickets.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
          View All <i class="fas fa-arrow-right ml-1"></i>
        </a>
      </div>

      <?php if (!empty($recent_tickets)): ?>
        <div class="space-y-4">
          <?php foreach ($recent_tickets as $ticket): ?>
            <div class="ticket-item p-4 bg-gray-50 rounded-lg">
              <div class="flex justify-between items-start">
                <div class="flex-1 min-w-0">
                  <h3 class="font-medium text-gray-800 truncate-text"><?= htmlspecialchars($ticket['subject']) ?></h3>
                  <p class="text-sm text-gray-500 mt-1">
                    <?= htmlspecialchars($ticket['user_name']) ?>
                  </p>
                </div>
                <div class="flex items-center space-x-2 ml-4">
                  <span class="priority-<?= htmlspecialchars($ticket['priority']) ?> px-2 py-1 rounded-full text-xs font-medium">
                    <i class="fas fa-<?= $ticket['priority'] === 'urgent' ? 'fire' : 'exclamation-circle' ?>"></i>
                  </span>
                  <span class="status-<?= htmlspecialchars($ticket['status']) ?> px-2 py-1 rounded-full text-xs font-medium">
                    <?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?>
                  </span>
                </div>
              </div>
              <p class="text-xs text-gray-400 mt-2">
                <?= date('M j, g:i A', strtotime($ticket['created_at'])) ?>
              </p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="text-center py-8">
          <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-ticket-alt text-gray-400 text-2xl"></i>
          </div>
          <h3 class="text-lg font-medium text-gray-600 mb-2">No tickets yet</h3>
          <p class="text-gray-500">New support tickets will appear here.</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Recent Orders -->
    <div class="bg-white rounded-xl shadow-md p-6">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-xl font-bold text-gray-800">Recent Orders</h2>
        <a href="manage_orders.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
          View All <i class="fas fa-arrow-right ml-1"></i>
        </a>
      </div>

      <?php if (!empty($recent_orders)): ?>
        <div class="space-y-4">
          <?php foreach ($recent_orders as $order): ?>
            <div class="order-item p-4 bg-gray-50 rounded-lg">
              <div class="flex justify-between items-start">
                <div class="flex-1 min-w-0">
                  <h3 class="font-medium text-gray-800">Order #<?= $order['id'] ?></h3>

                  <!-- ✅ Clear Buyer & Seller labels - Matching manage_orders.php style -->
                  <div class="mt-3 space-y-2">
                    <!-- Buyer Info -->
                    <div class="flex items-start">
                      <div class="w-20 flex-shrink-0">
                        <span class="text-xs font-semibold text-gray-600">Buyer:</span>
                      </div>
                      <div class="flex-1">
                        <span class="text-sm font-medium text-gray-800">
                          <?= htmlspecialchars($order['buyer_name']) ?>
                        </span>
                      </div>
                    </div>

                    <!-- Seller Info -->
                    <div class="flex items-start">
                      <div class="w-20 flex-shrink-0">
                        <span class="text-xs font-semibold text-gray-600">Sellers:</span>
                      </div>
                      <div class="flex-1">
                        <span class="text-sm font-medium text-gray-800 line-clamp-2" 
                              title="<?= htmlspecialchars($order['seller_names']) ?>">
                          <?= htmlspecialchars($order['seller_names']) ?>
                        </span>
                      </div>
                    </div>
                  </div>

                </div>
                <?php
                  $statusClass = 'status-' . str_replace(' ', '_', strtolower($order['status']));
                  if (!in_array($statusClass, ['status-pending', 'status-processing', 'status-shipped', 'status-delivered', 'status-cancelled'])) {
                    $statusClass = 'status-pending';
                  }
                ?>
                <span class="<?= $statusClass ?> px-3 py-1 rounded-full text-xs font-medium">
                  <i class="fas 
                    <?= $order['status'] === 'pending' ? 'fa-clock' : '' ?>
                    <?= $order['status'] === 'processing' ? 'fa-cog' : '' ?>
                    <?= $order['status'] === 'shipped' ? 'fa-shipping-fast' : '' ?>
                    <?= $order['status'] === 'delivered' ? 'fa-check-circle' : '' ?>
                    <?= $order['status'] === 'cancelled' ? 'fa-times-circle' : '' ?>
                    mr-1"></i>
                  <?= ucfirst($order['status']) ?>
                </span>
              </div>

              <!-- Order Total & Date -->
              <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-200">
                <p class="text-sm font-semibold text-gray-800">
                  ₹<?= number_format((int)$order['total_amount'], 0) ?>
                </p>
                <p class="text-xs text-gray-500">
                  <?= date('M j, g:i A', strtotime($order['created_at'])) ?>
                </p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="text-center py-8">
          <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-box-open text-gray-400 text-2xl"></i>
          </div>
          <h3 class="text-lg font-medium text-gray-600 mb-2">No orders yet</h3>
          <p class="text-gray-500">Recent orders will appear here.</p>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- Quick Actions -->
  <div class="bg-white rounded-xl shadow-md p-6">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Quick Actions</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <a href="tickets.php" class="flex items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition">
        <i class="fas fa-ticket-alt text-blue-600 mr-3"></i>
        <div>
          <div class="font-medium text-gray-800">Manage Tickets</div>
          <div class="text-xs text-gray-500 mt-1">Support requests</div>
        </div>
      </a>
      <a href="view_users.php" class="flex items-center p-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition">
        <i class="fas fa-users text-gray-600 mr-3"></i>
        <div>
          <div class="font-medium text-gray-800">View Users</div>
          <div class="text-xs text-gray-500 mt-1">Roles & status</div>
        </div>
      </a>
      <a href="manage_orders.php" class="flex items-center p-4 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition">
        <i class="fas fa-box-open text-yellow-600 mr-3"></i>
        <div>
          <div class="font-medium text-gray-800">Manage Orders</div>
          <div class="text-xs text-gray-500 mt-1">All platform orders</div>
        </div>
      </a>
      <a href="reviews.php" class="flex items-center p-4 bg-teal-50 hover:bg-teal-100 rounded-lg transition">
        <i class="fas fa-comments text-teal-600 mr-3"></i>
        <div>
          <div class="font-medium text-gray-800">Manage Reviews</div>
          <div class="text-xs text-gray-500 mt-1">Moderate feedback</div>
        </div>
      </a>
    </div>
  </div>

  <?php include 'includes/support_footer.php'; ?>
</body>
</html>