<?php
// ✅ DO NOT include session_start() here!
// It's already started in index.php, login.php, etc.

include 'config.php';

$logged_in = isset($_SESSION['user_id']);
$user_name = '';
$role = 'buyer';  // Default role
$role_status = 'approved';

if ($logged_in) {
    try {
        // Fetch user data
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

// Fetch unread notification count
$unread_count = 0;
if ($logged_in) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $unread_count = 0;
    }
}

// ✅ Split roles for multi-role check
$roles = !empty($role) ? explode(',', $role) : [];
$has_buyer = in_array('buyer', $roles);
$has_seller = in_array('seller', $roles);
$has_support = in_array('support', $roles);
$has_admin = in_array('admin', $roles);

// Determine current dashboard for highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$current_dashboard = '';
if (strpos($_SERVER['REQUEST_URI'], 'buyer/') !== false) $current_dashboard = 'buyer';
if (strpos($_SERVER['REQUEST_URI'], 'seller/') !== false) $current_dashboard = 'seller';
if (strpos($_SERVER['REQUEST_URI'], 'support/') !== false) $current_dashboard = 'support';
if (strpos($_SERVER['REQUEST_URI'], 'admin/') !== false) $current_dashboard = 'admin';

// Get PDO instance if not already available
if (!isset($pdo)) {
    try {
        $pdo = getPDO();
    } catch (Exception $e) {
        error_log("Failed to get PDO in header: " . $e->getMessage());
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($site_settings['site_name']) ?> - AutoParts Hub</title>

  <!-- ✅ Correct Tailwind CDN (removed extra spaces) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f8fafc;
    }
    .dropdown-menu {
      display: none;
      position: absolute;
      right: 0;
      top: 100%;
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 0.75rem;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
      z-index: 50;
      min-width: 280px;
      padding: 0.75rem;
      margin-top: 0.5rem;
    }
    .dropdown.open .dropdown-menu {
      display: block;
      animation: fadeIn 0.2s ease-out;
    }
    .dashboard-btn {
      @apply flex items-center w-full p-3 rounded-lg transition-all duration-200 mb-2 last:mb-0;
    }
    .dashboard-btn:hover {
      @apply transform scale-[1.02] shadow-md;
    }
    .dashboard-btn i {
      @apply text-xl mr-3 w-7 text-center;
    }
    .role-badge {
      @apply ml-auto px-2.5 py-1 rounded-full text-xs font-semibold;
    }
    .current-dashboard {
      @apply border-2 border-blue-500 shadow-md;
    }
    .dashboard-icon-buyer {
      @apply text-blue-600;
    }
    .dashboard-icon-seller {
      @apply text-green-600;
    }
    .dashboard-icon-support {
      @apply text-teal-600;
    }
    .dashboard-icon-admin {
      @apply text-red-600;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .dropdown-header {
      @apply px-2 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-100 mb-2;
    }
    .dropdown-btn {
      @apply flex items-center space-x-2 text-sm font-medium text-gray-700 transition-all duration-200 px-3 py-2 rounded-lg;
    }
    .dropdown-btn:hover {
      @apply bg-gray-100;
    }
    .btn-buyer {
      @apply bg-blue-50 hover:bg-blue-100 text-blue-700;
    }
    .btn-seller {
      @apply bg-green-50 hover:bg-green-100 text-green-700;
    }
    .btn-support {
      @apply bg-teal-50 hover:bg-teal-100 text-teal-700;
    }
    .btn-admin {
      @apply bg-red-50 hover:bg-red-100 text-red-700;
    }
    .user-avatar {
      @apply h-8 w-8 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold;
    }
    .profile-dropdown-menu {
      display: none;
      position: absolute;
      right: 0;
      top: 100%;
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 0.75rem;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
      z-index: 50;
      min-width: 200px;
      padding: 0.5rem 0;
      margin-top: 0.5rem;
    }
    .dropdown.open .profile-dropdown-menu {
      display: block;
      animation: fadeIn 0.2s ease-out;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">
  <!-- Navigation -->
  <header class="bg-white shadow-lg sticky top-0 z-50">
    <div class="container mx-auto px-6 py-4 flex items-center justify-between">
      <!-- Logo -->
      <a href="../index.php" class="flex items-center space-x-3">
        <i class="fas fa-tools text-3xl text-blue-600"></i>
        <span class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($site_settings['site_name']) ?></span>
      </a>

      <!-- Desktop Nav -->
      <nav class="hidden md:flex space-x-8 items-center">
        <a href="../index.php#home" class="font-medium hover:text-blue-600 transition">Home</a>
        <a href="../index.php#features" class="font-medium hover:text-blue-600 transition">Features</a>
        <a href="../index.php#how-it-works" class="font-medium hover:text-blue-600 transition">How It Works</a>
        <a href="../index.php#role-apply" class="font-medium hover:text-blue-600 transition">Apply Role</a>
        <a href="../index.php#contact" class="font-medium hover:text-blue-600 transition">Contact</a>

        <!-- Dashboard Link -->
        <?php if ($logged_in): ?>
          <?php if (count($roles) > 1): ?>
            <!-- Multi-role users get dropdown -->
            <div class="relative dropdown">
              <button class="dropdown-btn bg-blue-50 text-blue-700 hover:bg-blue-100"
                      onclick="this.parentElement.classList.toggle('open')">
                <span>Dashboards</span>
                <i class="fas fa-chevron-down text-xs transition-transform duration-200"></i>
              </button>
              <div class="dropdown-menu">
                <div class="dropdown-header">Switch Dashboard</div>
                
                <!-- Buyer Dashboard -->
                <a href="/buyer/dashboard.php" 
                   class="dashboard-btn btn-buyer <?= $current_dashboard === 'buyer' ? 'current-dashboard' : '' ?> block p-4 rounded-2xl shadow-md hover:shadow-lg transition-all bg-white border border-gray-200 hover:bg-blue-50">

  <!-- Top row: Icon + Role badge -->
  <div class="flex items-center justify-between mb-1">
    <div class="flex items-center space-x-2">
      <i class="fas fa-user dashboard-icon-buyer text-blue-600 text-lg"></i>
      <span class="font-semibold text-gray-800">Dashboard</span>
    </div>
    <span class="role-badge bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded-full">
      Buyer
    </span>
  </div>

  <!-- Subtitle -->
  <div class="text-xs text-gray-500 opacity-80">
    Manage purchases &amp; orders
  </div>
</a>

                
                <!-- Seller Dashboard -->
                <?php if ($has_seller): ?>
                  <a href="/seller/dashboard.php" 
                     class="dashboard-btn btn-seller <?= $current_dashboard === 'seller' ? 'current-dashboard' : '' ?> block p-4 rounded-2xl shadow-md hover:shadow-lg transition-all bg-white border border-gray-200 hover:bg-green-50">

  <!-- Top row: Icon + Title + Role badge -->
  <div class="flex items-center justify-between mb-1">
    <div class="flex items-center space-x-2">
      <i class="fas fa-store dashboard-icon-seller text-green-600 text-lg"></i>
      <span class="font-semibold text-gray-800">Dashboard</span>
    </div>
    <span class="role-badge bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded-full">
      Seller
    </span>
  </div>

  <!-- Subtitle -->
  <div class="text-xs text-gray-500 opacity-80">
    Manage products &amp; sales
  </div>
</a>
                <?php endif; ?>
                
                <!-- Support Dashboard -->
                <?php if ($has_support): ?>
                  <a href="/support/dashboard.php" 
                     class="dashboard-btn btn-support <?= $current_dashboard === 'support' ? 'current-dashboard' : '' ?> block p-4 rounded-2xl shadow-md hover:shadow-lg transition-all bg-white border border-gray-200 hover:bg-teal-50">

  <!-- Top row: Icon + Title + Role badge -->
  <div class="flex items-center justify-between mb-1">
    <div class="flex items-center space-x-2">
      <i class="fas fa-headset dashboard-icon-support text-teal-600 text-lg"></i>
      <span class="font-semibold text-gray-800">Dashboard</span>
    </div>
    <span class="role-badge bg-teal-100 text-teal-800 text-xs font-medium px-2 py-1 rounded-full">
      Support
    </span>
  </div>

  <!-- Subtitle -->
  <div class="text-xs text-gray-500 opacity-80">
    Handle customer inquiries
  </div>
</a>
                <?php endif; ?>
                
                <!-- Admin Dashboard -->
                <?php if ($has_admin): ?>
                  <a href="/admin/dashboard.php" 
                     class="dashboard-btn btn-admin <?= $current_dashboard === 'admin' ? 'current-dashboard' : '' ?> block p-4 rounded-2xl shadow-md hover:shadow-lg transition-all bg-white border border-gray-200 hover:bg-red-50">

  <!-- Top row: Icon + Title + Role badge -->
  <div class="flex items-center justify-between mb-1">
    <div class="flex items-center space-x-2">
      <i class="fas fa-user-shield dashboard-icon-admin text-red-600 text-lg"></i>
      <span class="font-semibold text-gray-800">Dashboard</span>
    </div>
    <span class="role-badge bg-red-100 text-red-800 text-xs font-medium px-2 py-1 rounded-full">
      Admin
    </span>
  </div>

  <!-- Subtitle -->
  <div class="text-xs text-gray-500 opacity-80">
    System management
  </div>
</a>
                <?php endif; ?>
              </div>
            </div>
          <?php else: ?>
            <!-- Single-role users get direct link to their dashboard -->
            <?php if ($has_buyer): ?>
              <a href="/buyer/dashboard.php" class="font-medium hover:text-blue-600 transition flex items-center bg-blue-50 px-4 py-2 rounded-lg text-blue-700">
                <i class="fas fa-th-large mr-2"></i> Dashboard
              </a>
            <?php elseif ($has_seller): ?>
              <a href="/seller/dashboard.php" class="font-medium hover:text-blue-600 transition flex items-center bg-green-50 px-4 py-2 rounded-lg text-green-700">
                <i class="fas fa-th-large mr-2"></i> Dashboard
              </a>
            <?php elseif ($has_support): ?>
              <a href="/support/dashboard.php" class="font-medium hover:text-blue-600 transition flex items-center bg-teal-50 px-4 py-2 rounded-lg text-teal-700">
                <i class="fas fa-th-large mr-2"></i> Dashboard
              </a>
            <?php elseif ($has_admin): ?>
              <a href="/admin/dashboard.php" class="font-medium hover:text-blue-600 transition flex items-center bg-red-50 px-4 py-2 rounded-lg text-red-700">
                <i class="fas fa-th-large mr-2"></i> Dashboard
              </a>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
      </nav>

      <!-- User Actions -->
      <div class="flex items-center space-x-4">
        <?php if ($logged_in): ?>
          <!-- Notifications Bell -->
          <a href="../notifications.php" class="relative p-2 text-gray-600 hover:text-blue-600 transition">
            <i class="fas fa-bell text-lg"></i>
            <?php if ($unread_count > 0): ?>
              <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs h-5 w-5 flex items-center justify-center rounded-full"><?= $unread_count ?></span>
            <?php endif; ?>
          </a>
          
          <!-- Profile Dropdown -->
          <div class="relative dropdown">
            <button 
              class="flex items-center space-x-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 px-3 py-2 rounded-lg transition"
              onclick="this.parentElement.classList.toggle('open')">
              
              <!-- Avatar -->
              <div class="user-avatar bg-blue-600 text-white w-8 h-8 flex items-center justify-center rounded-full font-semibold">
                <?= !empty($user_name) ? substr($user_name, 0, 1) : 'U' ?>
              </div>
              <span class="text-gray-800"><?= $user_name ?></span>
              <i class="fas fa-chevron-down text-xs text-gray-500"></i>
            </button>

            <!-- Dropdown menu -->
            <div class="profile-dropdown-menu">
              
              <!-- User Actions -->
              <a href="../profile.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
                <i class="fas fa-user text-blue-600"></i>
                <span>My Profile</span>
              </a>

              <a href="../apply_role/my_requests.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition">
                <i class="fas fa-clipboard-list text-purple-600"></i>
                <span>My Requests</span>
              </a>


              <a href="../notifications.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 relative transition">
                <i class="fas fa-bell text-yellow-600"></i>
                <span>Notifications</span>
                <?php if ($unread_count > 0): ?>
                  <span class="absolute right-4 bg-red-500 text-white text-xs font-semibold px-2 py-0.5 rounded-full">
                    <?= $unread_count ?>
                  </span>
                <?php endif; ?>
              </a>

              <!-- Divider -->
              <div class="border-t border-gray-200 my-1"></div>

              <a href="../logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
              </a>
            </div>
          </div>

        <?php else: ?>
          <a href="../login.php" class="px-4 py-2 border border-blue-600 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition">Login</a>
          <a href="../register.php" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Join Now</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- Click outside to close dropdown -->
  <script>
    document.addEventListener('click', function(e) {
      const dropdowns = document.querySelectorAll('.dropdown');
      dropdowns.forEach(dropdown => {
        if (!dropdown.contains(e.target)) {
          dropdown.classList.remove('open');
        }
      });
    });
  </script>
</body>
</html>