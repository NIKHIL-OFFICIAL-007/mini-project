<?php
// ✅ DO NOT include session_start() here!
// It's already started in index.php, login.php, etc.

include 'config.php';

$logged_in = isset($_SESSION['user_id']);
$user_name = '';
$role = 'seller';  // Default role
$role_status = 'approved';

if ($logged_in) {
    try {
        $stmt = $pdo->prepare("SELECT name, role, role_status FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['role_status'] = $user['role_status'];

            $user_name = htmlspecialchars($user['name']);
            $role = $user['role'];
            $role_status = $user['role_status'];
        } else {
            session_destroy();
            header("Location: ../login.php");
            exit();
        }
    } catch (Exception $e) {
        error_log("Header error: " . $e->getMessage());
        session_destroy();
        header("Location: ../login.php");
        exit();
    }
}

// ✅ Fetch unread replies from support (not notifications)
$unread_count = 0;
if ($logged_in) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM ticket_replies tr
            JOIN tickets t ON tr.ticket_id = t.id
            WHERE t.user_id = ? 
              AND tr.sender_role = 'support' 
              AND tr.is_read = FALSE
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Unread replies count failed: " . $e->getMessage());
        $unread_count = 0;
    }
}

// Get PDO instance if not already available
if (!isset($pdo)) {
    try {
        $pdo = getPDO();
    } catch (Exception $e) {
        error_log("Failed to get PDO in seller header: " . $e->getMessage());
    }
}

// Helper function to get site settings (if not already defined)
if (!function_exists('getSiteSettings')) {
    function getSiteSettings($pdo) {
        static $settings = null;
        
        if ($settings === null) {
            try {
                $stmt = $pdo->query("SELECT * FROM settings ORDER BY id DESC LIMIT 1");
                $settings = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // If no settings exist, return defaults
                if (!$settings) {
                    $settings = [
                        'site_name' => 'AutoParts Hub',
                        'contact_email' => 'support@autopartshub.com',
                        'phone' => '+1 (555) 123-4567',
                        'address' => '123 Auto Lane, Tech City, TC 10101'
                    ];
                }
            } catch (Exception $e) {
                error_log("Failed to fetch site settings: " . $e->getMessage());
                // Return defaults if database error
                $settings = [
                    'site_name' => 'AutoParts Hub',
                    'contact_email' => 'support@autopartshub.com',
                    'phone' => '+1 (555) 123-4567',
                    'address' => '123 Auto Lane, Tech City, TC 10101'
                ];
            }
        }
        
        return $settings;
    }
}

// Get site settings if not already available
if (!isset($site_settings) && isset($pdo)) {
    $site_settings = getSiteSettings($pdo);
} else if (!isset($site_settings)) {
    $site_settings = [
        'site_name' => 'AutoParts Hub',
        'contact_email' => 'support@autopartshub.com',
        'phone' => '+1 (555) 123-4567',
        'address' => '123 Auto Lane, Tech City, TC 10101'
    ];
}

$site_name = htmlspecialchars($site_settings['site_name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Seller Dashboard - <?= $site_name ?></title>

  <!-- ✅ Correct Tailwind CDN (removed extra spaces) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <!-- Custom Tailwind Config -->
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            'sidebar-bg': '#0f172a',
            'sidebar-active': '#1e40af',
            'sidebar-hover': '#334155',
            'primary': '#3b82f6',
            'secondary': '#6c757d',
            'success': '#10b981',
            'danger': '#ef4444',
            'warning': '#f59e0b',
          }
        }
      }
    }
  </script>
</head>
<body class="bg-gray-50 text-gray-900">

  <!-- Sidebar -->
  <div class="fixed left-0 top-0 w-72 h-full bg-sidebar-bg text-white">
    <!-- Brand -->
    <div class="p-6 border-b border-gray-700 bg-gradient-to-b from-blue-900 to-sidebar-bg">
      <div class="flex items-center space-x-3">
        <i class="fas fa-tools text-2xl text-blue-400"></i>
        <div>
          <h2 class="text-xl font-bold"><?= $site_name ?></h2>
          <p class="text-blue-200 text-sm">Seller Panel</p>
        </div>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-4 px-2">
      <a href="../../index.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition mb-1">
        <i class="fas fa-home mr-3 text-lg"></i>
        <span>Home</span>
      </a>
      <a href="dashboard.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-sidebar-active text-white' : '' ?>">
        <i class="fas fa-tachometer-alt mr-3"></i>
        <span>Dashboard</span>
      </a>
      <a href="add_part.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition <?= basename($_SERVER['PHP_SELF']) === 'add_part.php' ? 'bg-sidebar-active text-white' : '' ?>">
        <i class="fas fa-plus mr-3"></i>
        <span>Add Part</span>
      </a>
      <a href="manage_parts.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition <?= basename($_SERVER['PHP_SELF']) === 'manage_parts.php' ? 'bg-sidebar-active text-white' : '' ?>">
        <i class="fas fa-cogs mr-3"></i>
        <span>Manage Parts</span>
      </a>
      <a href="orders.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition <?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'bg-sidebar-active text-white' : '' ?>">
        <i class="fas fa-boxes mr-3"></i>
        <span>Orders</span>
      </a>
      <a href="reviews.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition <?= basename($_SERVER['PHP_SELF']) === 'reviews.php' ? 'bg-sidebar-active text-white' : '' ?>">
        <i class="fas fa-star mr-3"></i>
        <span>Reviews</span>
      </a>
      <a href="ticket_form.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition <?= basename($_SERVER['PHP_SELF']) === 'ticket_form.php' ? 'bg-sidebar-active text-white' : '' ?>">
        <i class="fas fa-ticket-alt mr-3"></i>
        <span>Support Ticket</span>
      </a>
      <a href="my_tickets.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition <?= basename($_SERVER['PHP_SELF']) === 'my_tickets.php' ? 'bg-sidebar-active text-white' : '' ?>">
        <i class="fas fa-list mr-3"></i>
        <span>My Tickets</span>
        <?php if ($unread_count > 0): ?>
          <span class="ml-auto bg-red-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center"><?= $unread_count ?></span>
        <?php endif; ?>
      </a>
    </nav>

    <!-- Logout -->
    <div class="absolute bottom-0 w-72 p-4 border-t border-gray-700 bg-gray-900">
      <a href="../../logout.php" class="flex items-center text-red-400 hover:text-red-300 hover:bg-red-900/20 rounded-lg px-6 py-3 transition">
        <i class="fas fa-sign-out-alt mr-3"></i>
        <span>Logout</span>
      </a>
    </div>
  </div>

  <!-- Main Content -->
  <div class="ml-72 p-8">
    <!-- Hero Card -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-6 rounded-xl shadow-lg mb-8">
      <div class="flex justify-between items-start">
        <div>
          <h2 class="text-2xl font-bold">Seller Dashboard</h2>
          <p class="text-blue-100 mt-1">Welcome back, <span class="font-semibold"><?= $user_name ?></span>! Here's what's happening today.</p>
        </div>
        <div class="flex items-center space-x-4">
          <!-- Notification Badge -->
          <button class="relative w-10 h-10 bg-white/20 backdrop-blur-sm border border-white/30 rounded-full flex items-center justify-center text-white hover:bg-white/30 transition">
            <i class="fas fa-bell"></i>
            <?php if ($unread_count > 0): ?>
              <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center"><?= $unread_count ?></span>
            <?php endif; ?>
          </button>

          <!-- User Profile -->
          <div class="flex items-center space-x-3 bg-white border border-gray-300 rounded-full px-4 py-2 shadow-sm">
            <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold">
              <?= substr($user_name, 0, 1) ?>
            </div>
            <div class="text-sm">
              <div class="font-medium text-gray-800"><?= $user_name ?></div>
              <div class="text-gray-500">Seller</div>
            </div>
            <i class="fas fa-chevron-down text-gray-500 text-sm"></i>
          </div>
        </div>
      </div>
    </div>