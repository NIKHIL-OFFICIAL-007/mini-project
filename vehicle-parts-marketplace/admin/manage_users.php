<?php
session_start();
include 'includes/config.php';

// Admin check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}
$roles = explode(',', $_SESSION['role']);
if (!in_array('admin', $roles)) {
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
 * Check if user has pending application
 */
function hasPendingApplication($pdo, $user_id) {
    $tables = ['seller_applications', 'support_applications', 'admin_applications'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE user_id = ? AND status = 'pending' LIMIT 1");
        $stmt->execute([$user_id]);
        if ($stmt->rowCount() > 0) return true;
    }
    return false;
}

/**
 * Get total pending role requests count
 */
function getPendingRoleRequestsCount($pdo) {
    $count = 0;
    $tables = ['seller_applications', 'support_applications', 'admin_applications'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table} WHERE status = 'pending'");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count += (int)$result['count'];
        } catch (Exception $e) {
            error_log("Failed to count pending requests from {$table}: " . $e->getMessage());
        }
    }
    
    return $count;
}

// Get pending requests count
$pending_requests_count = getPendingRoleRequestsCount($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Users - Admin Panel</title>
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
    .action-btn { transition: all 0.3s ease; border-radius: 8px; font-weight: 500; display: flex; align-items: center; justify-content: center; padding: 0.5rem 0.8rem; font-size: 0.8rem; min-width: 100px; height: 36px; border: none; cursor: pointer; text-decoration: none; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
    .action-btn:hover { transform: translateY(-2px); box-shadow: 0 3px 8px rgba(0,0,0,0.15); }
    .view-btn { background: linear-gradient(135deg, #4f46e5, #06b6d4); color: white; }
    .view-btn:hover { background: linear-gradient(135deg, #4338ca, #0891b2); }
    .delete-btn { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
    .delete-btn:hover { background: linear-gradient(135deg, #dc2626, #b91c1c); }
    .stats-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; color: white; padding: 1.5rem; box-shadow: 0 10px 20px rgba(102,126,234,0.3); }
    .search-box { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); padding: 0.5rem 1rem; }
    .filter-btn { background: white; border-radius: 10px; padding: 0.5rem 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); transition: all 0.2s ease; border: 1px solid #e5e7eb; }
    .filter-btn:hover, .filter-btn.active { background: #4f46e5; color: white; border-color: #4f46e5; }
    .empty-state { text-align: center; padding: 3rem 2rem; color: #6b7280; }
    .empty-state i { font-size: 4rem; margin-bottom: 1rem; color: #d1d5db; }
    .button-group { display: flex; gap: 0.5rem; justify-content: center; width: 100%; margin-top: auto; padding-top: 0.8rem; }
    .user-content { flex: 1; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 1.2rem; }
    .current-admin-badge { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); color: #6b7280; padding: 0.75rem 1rem; border-radius: 10px; font-size: 0.85rem; font-weight: 500; text-align: center; width: 100%; margin-top: auto; border: 1px solid #d1d5db; }
    .user-info { text-align: center; width: 100%; }
    .avatar-container { margin-bottom: 1rem; }
    .pending-badge { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 0.25rem 0.5rem; border-radius: 6px; font-size: 0.7rem; font-weight: 600; margin-top: 0.5rem; display: inline-block; }
  </style>
</head>
<body class="text-gray-800">
  <?php include 'includes/admin_header.php'; ?>
  <div class="container mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-4xl font-bold text-gray-900">User Management</h1>
      <p class="text-gray-600 mt-2">Manage all registered users, roles, and status changes.</p>
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
            <p class="text-sm text-gray-500">Pending Role Requests</p>
            <p class="text-2xl font-bold text-yellow-600"><?= $pending_requests_count ?></p>
          </div>
          <i class="fas fa-user-check text-2xl text-yellow-600"></i>
        </div>
      </div>
      <div class="bg-white rounded-2xl p-6 shadow-md">
        <div class="flex justify-between items-center">
          <div><p class="text-sm text-gray-500">Admins</p><p class="text-2xl font-bold text-red-600"><?= count(array_filter($users, fn($u) => strpos(strtolower($u['role']), 'admin') !== false)) ?></p></div>
          <i class="fas fa-shield-alt text-2xl text-red-500"></i>
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

    <!-- Success Messages -->
    <?php if (isset($_GET['message']) && $_GET['message'] === 'user_deleted'): ?>
      <div class="mb-6 p-4 bg-green-50 text-green-700 rounded-xl text-sm border-l-4 border-green-500 flex items-center">
        <i class="fas fa-check-circle mr-3 text-lg"></i> <span>User deleted successfully.</span>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['message']) && $_GET['message'] === 'roles_updated'): ?>
      <div class="mb-6 p-4 bg-green-50 text-green-700 rounded-xl text-sm border-l-4 border-green-500 flex items-center">
        <i class="fas fa-check-circle mr-3 text-lg"></i> <span>User roles updated successfully.</span>
      </div>
    <?php endif; ?>

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
            $has_pending = hasPendingApplication($pdo, $user['id']);
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
                <!-- Role Pills -->
                <div class="flex flex-wrap justify-center gap-1 mb-2">
                  <?php foreach ($current_roles as $role): ?>
                    <?php if (in_array($role, $all_roles)): ?>
                      <div class="relative">
                        <span class="role-pill <?= getRoleBadgeClass($role) ?>"><?= strtoupper($role) ?></span>
                        <?php if (!$is_current_user): ?>
                          <button type="button" 
                                  class="absolute -top-1 -right-1 w-4 h-4 flex items-center justify-center rounded-full bg-red-500 text-white text-xs remove-role-btn"
                                  data-user-id="<?= $user['id'] ?>" 
                                  data-role="<?= $role ?>"
                                  title="Remove <?= ucfirst($role) ?> role">
                            <i class="fas fa-times"></i>
                          </button>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  <?php endforeach; ?>

                  <?php foreach ($all_roles as $role): ?>
                    <?php if (!in_array($role, $current_roles)): ?>
                      <button type="button"
                              class="role-pill bg-gray-300 hover:bg-gray-400 text-gray-800 text-xs font-semibold py-0.5 px-2 rounded flex items-center add-role-btn"
                              data-user-id="<?= $user['id'] ?>"
                              data-role="<?= $role ?>"
                              title="Add <?= ucfirst($role) ?> role">
                        <i class="fas fa-plus text-gray-700 mr-1"></i> <?= strtoupper($role) ?>
                      </button>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>

                <p class="text-xl font-bold text-gray-800 mb-1"><?= htmlspecialchars($user['name']) ?></p>
                <p class="text-sm text-blue-600 truncate w-full mb-2"><?= htmlspecialchars($user['email']) ?></p>
                <p class="text-xs text-gray-500 mb-2">Joined <?= date('M j, Y', strtotime($user['created_at'])) ?></p>
                
                <?php if ($has_pending): ?>
                  <div class="pending-badge">
                    <i class="fas fa-clock mr-1"></i> Pending Requests
                  </div>
                <?php endif; ?>
              </div>

              <div class="button-group">
                <?php if (!$is_current_user): ?>
                  <?php if ($has_pending): ?>
                    <a href="user_requests.php?user_id=<?= $user['id'] ?>" class="action-btn view-btn">
                      <i class="fas fa-file-alt mr-1"></i> Requests
                    </a>
                  <?php endif; ?>

                  <a href="delete_user.php?id=<?= $user['id'] ?>" 
                     class="action-btn delete-btn"
                     onclick="return confirm('Are you sure you want to delete user <?= htmlspecialchars($user['name']) ?>? This action cannot be undone.');">
                    <i class="fas fa-trash-alt mr-1"></i> Delete
                  </a>
                <?php else: ?>
                  <div class="current-admin-badge">
                    <i class="fas fa-user-shield mr-2"></i> Current Admin
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php include 'includes/admin_footer.php'; ?>

  <script>
    // Get current roles for a user
    function getCurrentRoles(userId) {
      const userCard = document.querySelector(`.user-card[data-user-id="${userId}"]`);
      if (!userCard) return [];
      
      const currentRoles = userCard.getAttribute('data-roles').split(',');
      return currentRoles.filter(role => role.trim() !== '');
    }

    // Update roles display
    function updateRolesDisplay(userId, newRoles) {
      const userCard = document.querySelector(`.user-card[data-user-id="${userId}"]`);
      if (userCard) {
        userCard.setAttribute('data-roles', newRoles.join(','));
      }
    }

    // Add role function
    async function addRole(userId, role) {
      const currentRoles = getCurrentRoles(userId);
      
      // Check if role already exists
      if (currentRoles.includes(role)) {
        alert(`User already has the ${role.toUpperCase()} role.`);
        return;
      }

      // Add the new role
      currentRoles.push(role);
      const newRolesString = currentRoles.join(',');

      try {
        const response = await fetch('update_roles.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `user_id=${userId}&roles=${encodeURIComponent(newRolesString)}`
        });

        if (response.ok) {
          const result = await response.text();
          if (result === 'success') {
            location.reload();
          } else {
            alert('Error: ' + result);
          }
        } else {
          alert('Network error: ' + response.status);
        }
      } catch (error) {
        console.error('Error adding role:', error);
        alert('Error adding role: ' + error.message);
      }
    }

    // Remove role function
    async function removeRole(userId, role) {
      if (!confirm(`Are you sure you want to remove the ${role.toUpperCase()} role from this user?`)) {
        return;
      }

      const currentRoles = getCurrentRoles(userId);
      
      // Check if it's the last role
      if (currentRoles.length <= 1) {
        alert('User must have at least one role.');
        return;
      }

      // Remove the role
      const newRoles = currentRoles.filter(r => r !== role);
      const newRolesString = newRoles.join(',');

      try {
        const response = await fetch('update_roles.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `user_id=${userId}&roles=${encodeURIComponent(newRolesString)}`
        });

        if (response.ok) {
          const result = await response.text();
          if (result === 'success') {
            location.reload();
          } else {
            alert('Error: ' + result);
          }
        } else {
          alert('Network error: ' + response.status);
        }
      } catch (error) {
        console.error('Error removing role:', error);
        alert('Error removing role: ' + error.message);
      }
    }

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

      // Add event listeners for role buttons
      document.querySelectorAll('.add-role-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const userId = this.getAttribute('data-user-id');
          const role = this.getAttribute('data-role');
          addRole(userId, role);
        });
      });

      document.querySelectorAll('.remove-role-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const userId = this.getAttribute('data-user-id');
          const role = this.getAttribute('data-role');
          removeRole(userId, role);
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