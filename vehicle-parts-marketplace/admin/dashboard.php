<?php
session_start();
include '../includes/config.php';        // Loads $pdo, session
include 'includes/admin_auth.php';       // Handles admin access control

$user_name = htmlspecialchars($_SESSION['name']);
$user_id = $_SESSION['user_id'];

// Fetch stats
try {
    // Total Users
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // Pending Role Requests (from application tables)
    $pending_roles = 0;
    $tables = ['seller_applications', 'support_applications', 'admin_applications'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'");
        $pending_roles += (int)$stmt->fetchColumn();
    }

    // Count users with specific roles (roles are comma-separated)
    $total_sellers = $pdo->query("SELECT COUNT(*) FROM users WHERE FIND_IN_SET('seller', role) > 0")->fetchColumn();
    $total_support = $pdo->query("SELECT COUNT(*) FROM users WHERE FIND_IN_SET('support', role) > 0")->fetchColumn();
    $total_admins = $pdo->query("SELECT COUNT(*) FROM users WHERE FIND_IN_SET('admin', role) > 0")->fetchColumn();

    // Additional Stats
    $total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $total_tickets = $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn();
    $open_tickets = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'open'")->fetchColumn();
    $total_parts = $pdo->query("SELECT COUNT(*) FROM parts")->fetchColumn();

    // Recent Activity
    $recent_users = $pdo->query("SELECT name, email, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $recent_tickets = $pdo->query("
        SELECT t.id, t.subject, t.status, u.name as user_name, t.created_at 
        FROM tickets t 
        JOIN users u ON t.user_id = u.id 
        ORDER BY t.created_at DESC 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Dashboard stats query failed: " . $e->getMessage());
    $total_users = $pending_roles = $total_sellers = $total_support = $total_admins = 0;
    $total_orders = $total_tickets = $open_tickets = $total_parts = 0;
    $recent_users = $recent_tickets = [];
}

// Settings functionality
$message = '';
$error = '';

// Fetch current settings
$current_settings = [];
try {
    $stmt = $pdo->query("SELECT * FROM settings ORDER BY id DESC LIMIT 1");
    $current_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no settings exist, create default
    if (!$current_settings) {
        $stmt = $pdo->prepare("INSERT INTO settings (site_name, contact_email, phone, address) VALUES (?, ?, ?, ?)");
        $stmt->execute(['AutoParts Hub', 'support@autopartshub.com', '+1 (555) 123-4567', '123 Auto Lane, Tech City, TC 10101']);
        
        $stmt = $pdo->query("SELECT * FROM settings ORDER BY id DESC LIMIT 1");
        $current_settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Failed to fetch settings: " . $e->getMessage());
    $current_settings = [
        'site_name' => 'AutoParts Hub',
        'contact_email' => 'support@autopartshub.com',
        'phone' => '+1 (555) 123-4567',
        'address' => '123 Auto Lane, Tech City, TC 10101'
    ];
}

// Handle settings form submission
if ($_POST && isset($_POST['save_settings'])) {
    $site_name = trim($_POST['site_name'] ?? '');
    $contact_email = filter_var($_POST['contact_email'] ?? '', FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (!$site_name || !$contact_email || !$phone || !$address) {
        $error = "All fields are required.";
    } else {
        try {
            // Update existing settings or insert new ones
            if ($current_settings) {
                $stmt = $pdo->prepare("UPDATE settings SET site_name = ?, contact_email = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$site_name, $contact_email, $phone, $address, $current_settings['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO settings (site_name, contact_email, phone, address) VALUES (?, ?, ?, ?)");
                $stmt->execute([$site_name, $contact_email, $phone, $address]);
            }
            
            $message = "Settings saved successfully.";
            // Refresh current settings
            $stmt = $pdo->query("SELECT * FROM settings ORDER BY id DESC LIMIT 1");
            $current_settings = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to save settings: " . $e->getMessage());
            $error = "Failed to save settings. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Dashboard - AutoParts Hub</title>

  <!-- ✅ Tailwind & Font Awesome -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    .stats-card {
      transition: all 0.3s ease;
      border-radius: 16px;
      overflow: hidden;
    }
    .stats-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }
    .activity-item {
      transition: all 0.2s ease;
    }
    .activity-item:hover {
      background-color: #f8fafc;
      transform: translateX(4px);
    }
    .gradient-bg {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .gradient-bg-secondary {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    .gradient-bg-success {
      background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    .gradient-bg-warning {
      background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }
    .gradient-bg-danger {
      background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }
    .active-tab {
      background-color: #3b82f6 !important;
      color: white !important;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/admin_header.php'; ?>

  <!-- Main Content -->
  <div class="container mx-auto px-6 py-8">
    <!-- Stats Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <!-- Total Users -->
      <div class="stats-card bg-white rounded-2xl shadow-lg border-l-4 border-blue-500 overflow-hidden">
        <div class="p-6">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-sm font-medium text-gray-500">Total Users</p>
              <p class="text-3xl font-bold text-gray-800 mt-2"><?= number_format($total_users) ?></p>
              <p class="text-xs text-gray-400 mt-2">Registered accounts</p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
              <i class="fas fa-users text-blue-600 text-xl"></i>
            </div>
          </div>
        </div>
        <div class="bg-blue-50 px-6 py-3 border-t border-blue-100">
          <a href="manage_users.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
            Manage Users <i class="fas fa-arrow-right ml-2 text-xs"></i>
          </a>
        </div>
      </div>

      <!-- Pending Roles -->
      <div class="stats-card bg-white rounded-2xl shadow-lg border-l-4 border-yellow-500 overflow-hidden">
        <div class="p-6">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-sm font-medium text-gray-500">Pending Roles</p>
              <p class="text-3xl font-bold text-gray-800 mt-2"><?= number_format($pending_roles) ?></p>
              <p class="text-xs text-gray-400 mt-2">Awaiting approval</p>
            </div>
            <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
              <i class="fas fa-user-clock text-yellow-600 text-xl"></i>
            </div>
          </div>
        </div>
        <div class="bg-yellow-50 px-6 py-3 border-t border-yellow-100">
          <a href="user_requests.php" class="text-yellow-600 hover:text-yellow-800 text-sm font-medium flex items-center">
            Review Requests <i class="fas fa-arrow-right ml-2 text-xs"></i>
          </a>
        </div>
      </div>

      <!-- Total Orders -->
      <div class="stats-card bg-white rounded-2xl shadow-lg border-l-4 border-green-500 overflow-hidden">
        <div class="p-6">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-sm font-medium text-gray-500">Total Orders</p>
              <p class="text-3xl font-bold text-gray-800 mt-2"><?= number_format($total_orders) ?></p>
              <p class="text-xs text-gray-400 mt-2">Platform orders</p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
              <i class="fas fa-shopping-cart text-green-600 text-xl"></i>
            </div>
          </div>
        </div>
        <div class="bg-green-50 px-6 py-3 border-t border-green-100">
          <a href="manage_orders.php" class="text-green-600 hover:text-green-800 text-sm font-medium flex items-center">
            View Orders <i class="fas fa-arrow-right ml-2 text-xs"></i>
          </a>
        </div>
      </div>

      <!-- Open Tickets -->
      <div class="stats-card bg-white rounded-2xl shadow-lg border-l-4 border-red-500 overflow-hidden">
        <div class="p-6">
          <div class="flex justify-between items-start">
            <div>
              <p class="text-sm font-medium text-gray-500">Open Tickets</p>
              <p class="text-3xl font-bold text-gray-800 mt-2"><?= number_format($open_tickets) ?></p>
              <p class="text-xs text-gray-400 mt-2">Need attention</p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
              <i class="fas fa-ticket-alt text-red-600 text-xl"></i>
            </div>
          </div>
        </div>
        <div class="bg-red-50 px-6 py-3 border-t border-red-100">
          <a href="tickets.php" class="text-red-600 hover:text-red-800 text-sm font-medium flex items-center">
            Handle Tickets <i class="fas fa-arrow-right ml-2 text-xs"></i>
          </a>
        </div>
      </div>
    </div>

    <!-- Two Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
      <!-- Role Distribution -->
      <div class="lg:col-span-1 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-bold text-gray-800">Role Distribution</h2>
          <i class="fas fa-chart-pie text-gray-400"></i>
        </div>
        
        <div class="space-y-4">
          <!-- Admin -->
          <div class="flex items-center justify-between p-3 bg-red-50 rounded-xl">
            <div class="flex items-center">
              <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-user-shield text-red-600"></i>
              </div>
              <div>
                <p class="font-medium text-gray-800">Admins</p>
                <p class="text-sm text-gray-500">Platform administrators</p>
              </div>
            </div>
            <span class="text-2xl font-bold text-red-600"><?= $total_admins ?></span>
          </div>

          <!-- Sellers -->
          <div class="flex items-center justify-between p-3 bg-green-50 rounded-xl">
            <div class="flex items-center">
              <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-store text-green-600"></i>
              </div>
              <div>
                <p class="font-medium text-gray-800">Sellers</p>
                <p class="text-sm text-gray-500">Product vendors</p>
              </div>
            </div>
            <span class="text-2xl font-bold text-green-600"><?= $total_sellers ?></span>
          </div>

          <!-- Support -->
          <div class="flex items-center justify-between p-3 bg-blue-50 rounded-xl">
            <div class="flex items-center">
              <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                <i class="fas fa-headset text-blue-600"></i>
              </div>
              <div>
                <p class="font-medium text-gray-800">Support</p>
                <p class="text-sm text-gray-500">Customer support agents</p>
              </div>
            </div>
            <span class="text-2xl font-bold text-blue-600"><?= $total_support ?></span>
          </div>
        </div>

        <div class="mt-6 pt-6 border-t border-gray-100">
          <a href="user_requests.php" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-3 px-4 rounded-xl transition duration-150 flex items-center justify-center">
            <i class="fas fa-cog mr-2"></i> Manage Role Requests
          </a>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg p-6">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-bold text-gray-800">Recent Activity</h2>
          <div class="flex space-x-2">
            <button id="users-tab" class="px-3 py-1 bg-blue-100 text-blue-600 rounded-lg text-sm font-medium active-tab transition duration-150">
              Users
            </button>
            <button id="tickets-tab" class="px-3 py-1 bg-gray-100 text-gray-600 rounded-lg text-sm font-medium transition duration-150 hover:bg-gray-200">
              Tickets
            </button>
          </div>
        </div>

        <div class="space-y-4">
          <!-- Users Section -->
          <div id="users-section">
            <h3 class="font-semibold text-gray-700 mb-3 flex items-center">
              <i class="fas fa-user-plus mr-2 text-green-500"></i> New Users
            </h3>
            <div class="space-y-3">
              <?php if (!empty($recent_users)): ?>
                <?php foreach ($recent_users as $user): ?>
                  <div class="activity-item p-3 bg-gray-50 rounded-xl border border-gray-100">
                    <div class="flex justify-between items-center">
                      <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                          <i class="fas fa-user text-blue-600 text-sm"></i>
                        </div>
                        <div>
                          <p class="font-medium text-gray-800"><?= htmlspecialchars($user['name']) ?></p>
                          <p class="text-sm text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
                        </div>
                      </div>
                      <span class="text-xs text-gray-400"><?= date('M j', strtotime($user['created_at'])) ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="text-center py-4 text-gray-500">
                  <i class="fas fa-users text-2xl mb-2 opacity-50"></i>
                  <p>No recent users</p>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Tickets Section -->
          <div id="tickets-section" class="hidden">
            <h3 class="font-semibold text-gray-700 mb-3 flex items-center">
              <i class="fas fa-ticket-alt mr-2 text-orange-500"></i> Recent Tickets
            </h3>
            <div class="space-y-3">
              <?php if (!empty($recent_tickets)): ?>
                <?php foreach ($recent_tickets as $ticket): ?>
                  <div class="activity-item p-3 bg-gray-50 rounded-xl border border-gray-100">
                    <div class="flex justify-between items-start">
                      <div class="flex-1">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($ticket['subject']) ?></p>
                        <p class="text-sm text-gray-500">By <?= htmlspecialchars($ticket['user_name']) ?></p>
                      </div>
                      <span class="px-2 py-1 bg-blue-100 text-blue-600 rounded-full text-xs font-medium">
                        <?= ucfirst($ticket['status']) ?>
                      </span>
                    </div>
                    <p class="text-xs text-gray-400 mt-2"><?= date('M j, g:i A', strtotime($ticket['created_at'])) ?></p>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="text-center py-4 text-gray-500">
                  <i class="fas fa-ticket-alt text-2xl mb-2 opacity-50"></i>
                  <p>No recent tickets</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Actions & Settings -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Quick Actions -->
      <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-lg p-6">
          <h2 class="text-xl font-bold text-gray-800 mb-6">Quick Actions</h2>
          <div class="space-y-3">
            <a href="manage_users.php" class="flex items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-xl transition duration-150 border border-blue-100">
              <i class="fas fa-users text-blue-600 text-lg mr-4"></i>
              <div>
                <div class="font-medium text-gray-800">Manage Users</div>
                <div class="text-sm text-gray-500 mt-1">View and manage all users</div>
              </div>
            </a>
            
            <a href="user_requests.php" class="flex items-center p-4 bg-yellow-50 hover:bg-yellow-100 rounded-xl transition duration-150 border border-yellow-100">
              <i class="fas fa-user-check text-yellow-600 text-lg mr-4"></i>
              <div>
                <div class="font-medium text-gray-800">Role Requests</div>
                <div class="text-sm text-gray-500 mt-1">Approve/deny applications</div>
              </div>
            </a>
            
            <a href="manage_orders.php" class="flex items-center p-4 bg-green-50 hover:bg-green-100 rounded-xl transition duration-150 border border-green-100">
              <i class="fas fa-shopping-cart text-green-600 text-lg mr-4"></i>
              <div>
                <div class="font-medium text-gray-800">Order Management</div>
                <div class="text-sm text-gray-500 mt-1">Monitor all orders</div>
              </div>
            </a>
            
            <a href="manage_reviews.php" class="flex items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-xl transition duration-150 border border-purple-100">
              <i class="fas fa-comments text-purple-600 text-lg mr-4"></i>
              <div>
                <div class="font-medium text-gray-800">Manage Reviews</div>
                <div class="text-sm text-gray-500 mt-1">Moderate user reviews</div>
              </div>
            </a>
          </div>
        </div>
      </div>

      <!-- Settings Section -->
      <div class="lg:col-span-2">
        <div id="settings-section" class="bg-white rounded-2xl shadow-lg p-6">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold text-gray-800">Site Settings</h2>
            <span class="text-sm text-gray-500 flex items-center">
              <i class="fas fa-cog mr-2"></i> Platform Configuration
            </span>
          </div>

          <!-- Success/Error Messages -->
          <?php if ($message): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-200 text-green-800 rounded-xl text-sm flex items-center">
              <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($message) ?>
            </div>
          <?php endif; ?>
          <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-200 text-red-800 rounded-xl text-sm flex items-center">
              <i class="fas fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($error) ?>
            </div>
          <?php endif; ?>

          <!-- Site Settings Form -->
          <form method="POST">
            <input type="hidden" name="save_settings" value="1">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Site Name</label>
                <input type="text" name="site_name" value="<?= htmlspecialchars($current_settings['site_name'] ?? 'AutoParts Hub') ?>" 
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150" 
                       placeholder="Enter site name" required>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contact Email</label>
                <input type="email" name="contact_email" value="<?= htmlspecialchars($current_settings['contact_email'] ?? 'support@autopartshub.com') ?>" 
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150" 
                       placeholder="contact@example.com" required>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($current_settings['phone'] ?? '+1 (555) 123-4567') ?>" 
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150" 
                       placeholder="+1 (555) 123-4567" required>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                <input type="text" name="address" value="<?= htmlspecialchars($current_settings['address'] ?? '123 Auto Lane, Tech City, TC 10101') ?>" 
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150" 
                       placeholder="Enter full address" required>
              </div>
            </div>
            <div class="mt-6 flex justify-end space-x-4">
              <button type="reset" class="px-6 py-3 border-2 border-gray-300 text-gray-700 hover:bg-gray-50 rounded-xl font-medium transition duration-150">
                <i class="fas fa-undo mr-2"></i> Reset
              </button>
              <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium transition duration-150 transform hover:scale-105 shadow-lg flex items-center">
                <i class="fas fa-save mr-2"></i> Save Settings
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/admin_footer.php'; ?>

  <script>
    // Add interactive effects
    document.addEventListener('DOMContentLoaded', function() {
      // Add hover effects to stats cards
      const statsCards = document.querySelectorAll('.stats-card');
      statsCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
          this.style.transform = 'translateY(-8px)';
        });
        
        card.addEventListener('mouseleave', function() {
          this.style.transform = 'translateY(0)';
        });
      });

      // Tab functionality for Recent Activity
      const usersTab = document.getElementById('users-tab');
      const ticketsTab = document.getElementById('tickets-tab');
      const usersSection = document.getElementById('users-section');
      const ticketsSection = document.getElementById('tickets-section');

      function switchTab(activeTab, inactiveTab, activeSection, inactiveSection) {
        // Update buttons
        activeTab.classList.add('active-tab');
        activeTab.classList.remove('bg-gray-100', 'text-gray-600', 'hover:bg-gray-200');
        activeTab.classList.add('bg-blue-600', 'text-white');
        
        inactiveTab.classList.remove('active-tab', 'bg-blue-600', 'text-white');
        inactiveTab.classList.add('bg-gray-100', 'text-gray-600', 'hover:bg-gray-200');
        
        // Update sections
        activeSection.classList.remove('hidden');
        inactiveSection.classList.add('hidden');
      }

      // Users tab click
      usersTab.addEventListener('click', function() {
        switchTab(usersTab, ticketsTab, usersSection, ticketsSection);
      });

      // Tickets tab click
      ticketsTab.addEventListener('click', function() {
        switchTab(ticketsTab, usersTab, ticketsSection, usersSection);
      });
    });
  </script>
</body>
</html>