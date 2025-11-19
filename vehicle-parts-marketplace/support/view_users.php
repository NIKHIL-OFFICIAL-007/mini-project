<?php
session_start();
include 'includes/config.php';

// Support check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}
$roles = explode(',', $_SESSION['role']);
if (!in_array('support', $roles) || ($_SESSION['role_status'] ?? '') !== 'approved') {
    header("Location: ../login.php");
    exit();
}

// Fetch all users
$users = [];
try {
    $stmt = $pdo->query("
        SELECT id, name, email, role, role_status, created_at
        FROM users
        ORDER BY created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch users: " . $e->getMessage());
}

/**
 * Get role badge class
 */
function getRoleBadgeClass($role) {
    $role = strtolower(trim($role));
    return match($role) {
        'admin' => 'bg-red-600',
        'support' => 'bg-teal-600',
        'seller' => 'bg-green-600',
        'buyer' => 'bg-blue-600',
        default => 'bg-gray-600'
    };
}

/**
 * Get total active sellers count
 */
function getActiveSellersCount($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role_status = 'approved' AND role LIKE '%seller%'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    } catch (Exception $e) {
        error_log("Failed to count active sellers: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get total active tickets count
 */
function getActiveTicketsCount($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM tickets WHERE status IN ('open', 'in_progress')");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    } catch (Exception $e) {
        error_log("Failed to count active tickets: " . $e->getMessage());
        return 0;
    }
}

// Get counts
$active_sellers_count = getActiveSellersCount($pdo);
$active_tickets_count = getActiveTicketsCount($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>View Users - Support Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%); min-height: 100vh; }
    .profile-avatar { width: 5.5rem; height: 5.5rem; object-fit: cover; border: 4px solid white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .user-card { transition: all 0.3s ease; border-radius: 16px; overflow: hidden; background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.08); position: relative; display: flex; flex-direction: column; height: 100%; }
    .user-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.15); }
    .user-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #4f46e5, #06b6d4); }
    .role-pill { display: inline-block; color: white; padding: 0.15rem 0.4rem; border-radius: 8px; font-weight: 600; font-size: 0.6rem; margin: 0.1rem; box-shadow: 0 1px 2px rgba(0,0,0,0.2); white-space: nowrap; }
    .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; color: white; padding: 1.5rem; box-shadow: 0 10px 20px rgba(102,126,234,0.3); }
    .search-box { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 0.5rem 1rem; }
    .filter-btn { background: white; border-radius: 10px; padding: 0.5rem 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: all 0.2s ease; border: 1px solid #e5e7eb; }
    .filter-btn:hover, .filter-btn.active { background: #4f46e5; color: white; border-color: #4f46e5; }
    .empty-state { text-align: center; padding: 3rem 2rem; color: #6b7280; }
    .empty-state i { font-size: 4rem; margin-bottom: 1rem; color: #d1d5db; }
    .user-content { flex: 1; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 1.2rem; }
    .user-info { text-align: center; width: 100%; }
    .avatar-container { margin-bottom: 1rem; }
    .support-badge { background: linear-gradient(135deg, #0ea5e9, #0284c7); color: white; padding: 0.75rem 1rem; border-radius: 10px; font-size: 0.85rem; font-weight: 500; text-align: center; width: 100%; margin-top: auto; border: 1px solid #bae6fd; }
    .view-only-badge { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); color: #6b7280; padding: 0.75rem 1rem; border-radius: 10px; font-size: 0.85rem; font-weight: 500; text-align: center; width: 100%; margin-top: auto; border: 1px solid #d1d5db; }
  </style>
</head>
<body class="text-gray-800">
  <?php include 'includes/support_header.php'; ?>
  <div class="container mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-4xl font-bold text-gray-900">User Directory</h1>
      <p class="text-gray-600 mt-2">View all registered users and their roles (Read-only access).</p>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="stats-card">
        <div class="flex justify-between items-center">
          <div><p class="text-sm opacity-80">Total Users</p><p class="text-3xl font-bold"><?= count($users) ?></p></div>
          <i class="fas fa-users text-2xl opacity-70"></i>
        </div>
      </div>
      <div class="bg-white rounded-2xl p-6 shadow-md">
        <div class="flex justify-between items-center">
          <div>
            <p class="text-sm text-gray-500">Active Sellers</p>
            <p class="text-2xl font-bold text-green-600"><?= $active_sellers_count ?></p>
          </div>
          <i class="fas fa-store text-2xl text-green-500"></i>
        </div>
      </div>
<div class="bg-white rounded-2xl p-6 shadow-md">
        <div class="flex justify-between items-center">
          <div><p class="text-sm text-gray-500">Support Agents</p><p class="text-2xl font-bold text-teal-600"><?= count(array_filter($users, fn($u) => strpos(strtolower($u['role']), 'support') !== false)) ?></p></div>
          <i class="fas fa-headset text-2xl text-teal-500"></i>
        </div>
      </div>
    </div>

    <!-- Search & Filter -->
    <div class="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
      <div class="search-box w-full md:w-1/3">
        <div class="flex items-center">
          <i class="fas fa-search text-gray-400 mr-2"></i>
          <input type="text" placeholder="Search users by name or email..." class="w-full py-2 focus:outline-none" id="searchInput">
        </div>
      </div>
      <div class="flex gap-2">
        <button class="filter-btn active" data-filter="all">All</button>
        <button class="filter-btn" data-filter="admin">Admin</button>
        <button class="filter-btn" data-filter="seller">Seller</button>
        <button class="filter-btn" data-filter="support">Support</button>
        <button class="filter-btn" data-filter="buyer">Buyer</button>
      </div>
    </div>

    <!-- Users Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="usersGrid">
      <?php if (empty($users)): ?>
        <div class="col-span-full">
          <div class="empty-state bg-white rounded-2xl shadow-md">
            <i class="fas fa-users"></i>
            <h3 class="text-xl font-semibold mb-2">No Users Found</h3>
            <p class="max-w-md mx-auto">There are no users registered in the system yet.</p>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($users as $user): ?>
          <?php
            $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($user['name']) . "&background=random&color=fff&size=128&bold=true&font-size=0.5";
            $current_roles = array_map('trim', explode(',', $user['role']));
            $all_roles = ['buyer', 'seller', 'support', 'admin'];
            $is_current_user = $user['id'] == $_SESSION['user_id'];
          ?>
          <div class="user-card" data-user-id="<?= $user['id'] ?>" data-roles="<?= htmlspecialchars(strtolower(implode(',', $current_roles))) ?>">
            <div class="user-content">
              <div class="avatar-container">
                <img class="profile-avatar rounded-full" src="<?= htmlspecialchars($avatar_url) ?>" alt="<?= htmlspecialchars($user['name']) ?>'s Avatar">
              </div>
              <div class="user-info">
                <!-- Role Pills (View Only) -->
                <div class="flex flex-wrap justify-center gap-1 mb-2">
                  <?php foreach ($current_roles as $role): ?>
                    <?php if (in_array($role, $all_roles)): ?>
                      <span class="role-pill <?= getRoleBadgeClass($role) ?>"><?= strtoupper($role) ?></span>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>

                <p class="text-xl font-bold text-gray-800 mb-1"><?= htmlspecialchars($user['name']) ?></p>
                <p class="text-sm text-blue-600 truncate w-full mb-2"><?= htmlspecialchars($user['email']) ?></p>
                <p class="text-xs text-gray-500 mb-2">Joined <?= date('M j, Y', strtotime($user['created_at'])) ?></p>
              </div>

              <!-- User Status Badge -->
              <div class="w-full mt-auto">
                <?php if ($is_current_user): ?>
                  <div class="support-badge">
                    <i class="fas fa-user-shield mr-2"></i> Current Support Agent
                  </div>
                <?php else: ?>
                  <div class="view-only-badge">
                    <i class="fas fa-eye mr-2"></i> View Only
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php include 'includes/support_footer.php'; ?>

  <script>
    // Search functionality
    document.addEventListener('DOMContentLoaded', function() {
      const searchInput = document.getElementById('searchInput');
      const userCards = document.querySelectorAll('.user-card');
      const filterBtns = document.querySelectorAll('.filter-btn');

      // Search function
      searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        
        userCards.forEach(card => {
          const userName = card.querySelector('.text-xl').textContent.toLowerCase();
          const userEmail = card.querySelector('.text-blue-600').textContent.toLowerCase();
          
          if (userName.includes(searchTerm) || userEmail.includes(searchTerm)) {
            card.style.display = 'block';
          } else {
            card.style.display = 'none';
          }
        });
      });

      // Filter functionality
      filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
          // Remove active class from all buttons
          filterBtns.forEach(b => b.classList.remove('active'));
          // Add active class to clicked button
          this.classList.add('active');
          
          const filter = this.getAttribute('data-filter');
          
          userCards.forEach(card => {
            const userRoles = card.getAttribute('data-roles').split(',');
            
            if (filter === 'all' || userRoles.includes(filter)) {
              card.style.display = 'block';
            } else {
              card.style.display = 'none';
            }
          });
        });
      });
    });

    // Enter key support for search
    document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
      }
    });
  </script>
</body>
</html>