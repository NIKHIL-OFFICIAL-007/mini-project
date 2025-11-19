<?php
session_start();

// ✅ Include config.php to get $pdo and getPDO()
include 'includes/config.php';

$logged_in = isset($_SESSION['user_id']);
$user_name = $logged_in ? htmlspecialchars($_SESSION['name']) : '';

// Get single role (buyer by default)
$role = $logged_in && isset($_SESSION['role']) ? $_SESSION['role'] : 'buyer';
$role_status = $logged_in && isset($_SESSION['role_status']) ? $_SESSION['role_status'] : 'approved';

// Fetch unread notification count
$unread_count = 0;
if ($logged_in) {
    try {
        $pdo = getPDO(); // ← Now works!
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $unread_count = 0;
    }
}

// Helper function to get site settings
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

// Get site settings
$site_settings = getSiteSettings($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($site_settings['site_name']) ?> - Online Vehicle Parts Marketplace</title>

  <!-- ✅ Fixed: Correct Tailwind CDN (removed extra spaces) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <!-- Custom Tailwind Config -->
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            poppins: ['Poppins', 'sans-serif'],
          },
          colors: {
            primary: '#2563eb',
            secondary: '#1e293b',
            accent: '#f97316',
            support: '#0d9488',
            seller: '#16a34a',
            admin: '#dc2626',
            light: '#f8fafc',
            dark: '#0f172a'
          }
        }
      }
    }
  </script>

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f8fafc;
      scroll-behavior: smooth;
    }
    .hero-bg {
      background: linear-gradient(135deg, rgba(29, 78, 216, 0.85), rgba(15, 23, 42, 0.9)),
                  url('https://images.unsplash.com/photo-1580273916550-e323be2ae537?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80') no-repeat center center;
      background-size: cover;
    }
    .btn-primary {
      background: linear-gradient(135deg, #2563eb, #1d4ed8);
      color: white;
      transition: all 0.3s ease;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(29, 78, 216, 0.3);
    }
    .btn-outline {
      border: 2px solid white;
      color: white;
      transition: all 0.3s ease;
    }
    .btn-outline:hover {
      background: white;
      color: #1d4ed8;
    }
    .role-card {
      transition: all 0.3s ease;
    }
    .role-card:hover {
      transform: translateY(-5px);
    }
    .feature-icon {
      transition: transform 0.3s ease;
    }
    .feature-card:hover .feature-icon {
      transform: scale(1.1);
    }
  </style>
</head>
<body class="bg-light text-dark">

  <!-- Include Header -->
  <?php include 'includes/header.php'; ?>

  <!-- Hero Section -->
  <section id="home" class="hero-bg text-white py-32 px-6 text-center">
    <div class="container mx-auto">
      <h1 class="text-4xl md:text-6xl font-extrabold leading-tight mb-6 animate-fade-in">
        Find the Right <span class="text-accent">Vehicle Part</span> in Minutes
      </h1>
      <p class="text-lg md:text-xl mb-10 max-w-3xl mx-auto opacity-90">
        A trusted online marketplace connecting buyers and sellers of auto parts nationwide. 
        Fast, secure, and transparent — just like modern e-commerce should be.
      </p>
      <div class="flex flex-col sm:flex-row justify-center gap-5 mt-8">
        <!-- Browse Parts - Available to ALL logged-in users -->
        <?php if ($logged_in): ?>
          <a href="buyer/browse_parts.php" class="btn-primary px-8 py-4 text-lg font-semibold rounded-xl shadow-lg hover:shadow-xl flex items-center justify-center">
            <i class="fas fa-search mr-2"></i> Browse Parts
          </a>
        <?php else: ?>
          <a href="#" onclick="showLoginAlert(); return false;" class="btn-primary px-8 py-4 text-lg font-semibold rounded-xl shadow-lg hover:shadow-xl cursor-pointer flex items-center justify-center">
            <i class="fas fa-search mr-2"></i> Browse Parts
          </a>
        <?php endif; ?>

        <!-- Sell Your Parts -->
        <?php if ($logged_in): ?>
          <?php 
          // Check if user has seller role (supports comma-separated roles)
          $has_seller_role = str_contains($role, 'seller');
          ?>
          <?php if ($has_seller_role): ?>
            <!-- If user has seller role (any status), go to seller/add_part.php -->
            <a href="seller/add_part.php" class="btn-outline px-8 py-4 text-lg font-semibold rounded-xl bg-white bg-opacity-20 backdrop-blur-sm hover:bg-opacity-30 flex items-center justify-center">
              <i class="fas fa-store mr-2"></i> Sell Your Parts
            </a>
          <?php else: ?>
            <button onclick="showRoleAlert('You need to be a seller to sell parts.')" 
                    class="btn-outline px-8 py-4 text-lg font-semibold rounded-xl bg-white bg-opacity-20 backdrop-blur-sm hover:bg-opacity-30 flex items-center justify-center cursor-pointer">
              <i class="fas fa-store mr-2"></i> Sell Your Parts
            </button>
          <?php endif; ?>
        <?php else: ?>
          <button onclick="showLoginAlert()" 
                  class="btn-outline px-8 py-4 text-lg font-semibold rounded-xl bg-white bg-opacity-20 backdrop-blur-sm hover:bg-opacity-30 flex items-center justify-center cursor-pointer">
              <i class="fas fa-store mr-2"></i> Sell Your Parts
          </button>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Features Section -->
  <section id="features" class="py-24 bg-white">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <h2 class="text-3xl md:text-4xl font-bold text-secondary">Why <?= htmlspecialchars($site_settings['site_name']) ?>?</h2>
        <p class="text-gray-600 mt-4 max-w-2xl mx-auto">
          We solve the real-world problems of vehicle owners, mechanics, and part dealers with a powerful, digital-first marketplace.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
        <?php foreach ([
          ['fa-globe-americas', 'Nationwide Access', 'No more local shortages. Access thousands of parts from sellers across the country.', 'primary'],
          ['fa-shield-alt', 'Verified Sellers', 'Every seller is reviewed and rated. Shop with confidence knowing quality is guaranteed.', 'green-600'],
          ['fa-history', 'Order History', 'View and manage all your past orders in one place for easy re-ordering.', 'purple-600'],
          ['fa-comment-dots', '24/7 Support', 'Our support agents are ready to help with complaints, returns, and technical issues.', 'orange-600'],
          ['fa-tags', 'Transparent Pricing', 'Compare prices, read reviews, and make informed decisions — no hidden costs.', 'red-600'],
          ['fa-laptop', 'Easy Inventory Management', 'Sellers can manage stock, update listings, and track sales from a single dashboard.', 'indigo-600']
        ] as $feature): ?>
          <div class="bg-gray-50 p-8 rounded-2xl shadow-md hover:shadow-xl transition feature-card">
            <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center mb-5 feature-icon">
              <i class="fas <?= $feature[0] ?> text-2xl text-<?= $feature[3] ?>"></i>
            </div>
            <h3 class="text-2xl font-semibold mb-3"><?= $feature[1] ?></h3>
            <p class="text-gray-600"><?= $feature[2] ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- How It Works -->
  <section id="how-it-works" class="py-24 bg-gray-50">
    <div class="container mx-auto px-6 text-center">
      <h2 class="text-3xl md:text-4xl font-bold text-secondary mb-16">How It Works</h2>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
        <?php foreach (['Create Account', 'Browse or List', 'Order & Pay', 'Track & Review'] as $i => $title): ?>
          <div class="relative">
            <div class="w-16 h-16 bg-primary text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-4 z-10 relative">
              <?= $i+1 ?>
            </div>
            <?php if ($i < 3): ?>
              <div class="hidden md:block absolute top-8 left-1/2 w-full h-0.5 bg-primary transform translate-x-8 -translate-y-4"></div>
            <?php endif; ?>
            <h3 class="text-xl font-semibold mt-4"><?= $title ?></h3>
            <p class="text-gray-600 mt-2"><?= [
              'Register as a buyer, seller, or support agent in seconds.',
              'Buyers search parts. Sellers upload inventory with images and specs.',
              'Add to cart, checkout securely, and get order confirmation.',
              'Track delivery and leave feedback to help others.'
            ][$i] ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

<!-- Role-Based Apply Section -->
<section id="role-apply" class="py-20 bg-secondary text-white">
  <div class="container mx-auto px-6">
    <h2 class="text-3xl font-bold text-center mb-12">Apply For Additional Roles</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <?php 
      foreach ([
        ['seller', 'fa-store', 'Seller', 'List parts, manage inventory, grow your business.', 'apply_role/apply_seller.php'],
        ['support', 'fa-headset', 'Support', 'Handle complaints and assist users 24/7.', 'apply_role/apply_support.php'],
        ['admin', 'fa-user-shield', 'Admin', 'Manage users, categories, and platform operations.', 'apply_role/apply_admin.php']
      ] as $r): 
        // Check if user already has this role (supports comma-separated roles)
        $user_has_role = $logged_in && str_contains($role, $r[0]);
        
        // Debug: Check what's happening
        // echo "<!-- Debug: role='$role', checking for '{$r[0]}', user_has_role=" . ($user_has_role ? 'true' : 'false') . " -->";
        
        // Determine the correct link
        if ($logged_in) {
          if ($user_has_role) {
            $link = 'apply_role/my_requests.php'; // Redirect to my_requests.php if user already has the role
          } else {
            $link = $r[4]; // Go to application form if user doesn't have the role
          }
        } else {
          $link = '#'; // Show login alert for non-logged in users
        }
        
        $onclick = $logged_in ? '' : "showLoginAlert(); return false;";
        
        // Role status indicator
        $status_text = '';
        $status_class = '';
        if ($user_has_role) {
          if ($role_status === 'approved') {
            $status_text = '✓ Role Active';
            $status_class = 'text-green-300';
          } elseif ($role_status === 'pending') {
            $status_text = '⏳ Pending Approval';
            $status_class = 'text-yellow-300';
          } elseif ($role_status === 'rejected') {
            $status_text = '✗ Application Rejected';
            $status_class = 'text-red-300';
          }
        }
      ?>
        <a href="<?= $link ?>" 
           onclick="<?= $onclick ?>" 
           class="p-8 bg-opacity-80 hover:bg-opacity-100 transition text-center shadow-lg rounded-xl role-card relative
                  <?= $r[0] === 'seller' ? 'bg-green-700 hover:bg-green-800' :
                     ($r[0] === 'support' ? 'bg-teal-600 hover:bg-teal-700' : 'bg-red-700 hover:bg-red-800') ?>">
          <i class="fas <?= $r[1] ?> text-5xl mb-4"></i>
          <h3 class="text-2xl font-bold"><?= $r[2] ?></h3>
          <p class="mt-2 opacity-90"><?= $r[3] ?></p>
          
          <?php if ($user_has_role && $status_text): ?>
            <div class="mt-3 text-sm <?= $status_class ?> font-semibold">
              <i class="fas fa-info-circle mr-1"></i>
              <?= $status_text ?>
            </div>
            <div class="mt-2 text-xs opacity-75">
              Click to view application status
            </div>
          <?php elseif ($user_has_role): ?>
            <div class="mt-3 text-sm text-blue-200">
              <i class="fas fa-info-circle mr-1"></i>
              View your application status
            </div>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if ($logged_in): ?>
      <div class="text-center mt-8">
        <p class="text-blue-200">
          <i class="fas fa-info-circle mr-2"></i>
          You already have buyer privileges. Start browsing parts now!
        </p>
        <a href="buyer/browse_parts.php" class="inline-block mt-2 px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
          Browse Parts →
        </a>
      </div>
    <?php endif; ?>
  </div>
</section>

  <!-- Footer -->
  <?php include 'includes/footer.php'; ?>

  <!-- Login Alert -->
  <div id="loginAlert" class="fixed top-4 right-4 hidden p-4 bg-red-500 text-white rounded-lg shadow-lg max-w-xs z-50 transition-opacity duration-300">
    <div class="flex items-center">
      <i class="fas fa-exclamation-circle mr-3"></i>
      <span>Please login first.</span>
      <button class="ml-4" onclick="hideLoginAlert()">
        <i class="fas fa-times"></i>
      </button>
    </div>
  </div>

  <!-- Role Alert -->
  <div id="roleAlert" class="fixed top-4 right-4 hidden p-4 bg-yellow-500 text-white rounded-lg shadow-lg max-w-xs z-50 transition-opacity duration-300">
    <div class="flex items-center">
      <i class="fas fa-exclamation-triangle mr-3"></i>
      <span id="roleAlertMsg">You need to be approved for this role.</span>
      <button class="ml-4" onclick="hideRoleAlert()">
        <i class="fas fa-times"></i>
      </button>
    </div>
  </div>

  <script>
    function showLoginAlert() {
      document.getElementById('loginAlert').classList.remove('hidden');
      setTimeout(hideLoginAlert, 5000);
    }
    function hideLoginAlert() {
      document.getElementById('loginAlert')?.classList.add('hidden');
    }

    function showRoleAlert(message) {
      document.getElementById('roleAlertMsg').textContent = message;
      document.getElementById('roleAlert').classList.remove('hidden');
      setTimeout(hideRoleAlert, 6000);
    }
    function hideRoleAlert() {
      document.getElementById('roleAlert')?.classList.add('hidden');
    }
  </script>
</body>
</html>