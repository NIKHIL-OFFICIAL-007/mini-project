<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has admin role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('admin', $roles)) {
    header("Location: ../login.php");
    exit();
}

// Get filter parameters from URL
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';

// Fetch categories for filter dropdown
$categories = [];
try {
    $cat_stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch categories: " . $e->getMessage());
}

// Build SQL query with filters
$sql_where = [];
$sql_params = [];
$types = [];

// Search filter
if ($search) {
    $sql_where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $sql_params[] = '%' . $search . '%';
    $sql_params[] = '%' . $search . '%';
    $types[] = \PDO::PARAM_STR;
    $types[] = \PDO::PARAM_STR;
}

// Category filter
if ($category_id) {
    $sql_where[] = "p.category_id = ?";
    $sql_params[] = $category_id;
    $types[] = \PDO::PARAM_INT;
}

// Status filter
if ($status_filter !== 'all') {
    $sql_where[] = "p.status = ?";
    $sql_params[] = $status_filter;
    $types[] = \PDO::PARAM_STR;
}

$where_clause = count($sql_where) > 0 ? " WHERE " . implode(" AND ", $sql_where) : "";

// Sort order
$order_by = "ORDER BY ";
switch ($sort) {
    case 'name_desc':
        $order_by .= "p.name DESC";
        break;
    case 'name_asc':
    default:
        $order_by .= "p.name ASC";
        break;
}

// Fetch filtered parts
$parts = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, c.name as category_name, p.price, p.stock_quantity as stock, 
               p.image_url, p.status, p.description, p.category_id, p.seller_id, p.created_at
        FROM parts p
        LEFT JOIN categories c ON p.category_id = c.id
        " . $where_clause . "
        " . $order_by . "
    ");
    
    foreach ($sql_params as $key => $value) {
        $stmt->bindValue($key + 1, $value, $types[$key]);
    }
    
    $stmt->execute();
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch parts: " . $e->getMessage());
}

// Fetch total parts count (unfiltered)
$total_parts = 0;
try {
    $total_stmt = $pdo->query("SELECT COUNT(*) as total FROM parts");
    $total_row = $total_stmt->fetch(PDO::FETCH_ASSOC);
    $total_parts = (int)$total_row['total'];
} catch (Exception $e) {
    error_log("Failed to fetch total parts count: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Parts - Admin Panel</title>

  <!-- Tailwind CSS (fixed extra spaces) -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/admin_header.php'; ?>

  <!-- Page Header -->
  <div class="mb-6 px-4 sm:px-6">
    <h1 class="text-2xl font-bold text-gray-800">Manage Parts</h1>
    <p class="text-gray-600 mt-1">Edit, hide, or delete vehicle parts from the marketplace.</p>
  </div>

  <!-- Filters Section (Top) -->
  <div class="bg-white rounded-xl shadow-md p-4 sm:p-6 mb-6 mx-4 sm:mx-6">
    <h2 class="text-lg font-bold mb-4 text-gray-800">Filters</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
      <!-- Search -->
      <div class="lg:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
        <input type="text" id="searchInput" placeholder="Search parts..." 
               class="w-full px-3 py-2 sm:px-4 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
               value="<?= htmlspecialchars($search) ?>">
      </div>

      <!-- Category -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
        <select id="categorySelect" class="w-full px-3 py-2 sm:px-4 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars(ucfirst($cat['name'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Status -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
        <select id="statusSelect" class="w-full px-3 py-2 sm:px-4 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
          <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
          <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="hidden" <?= $status_filter === 'hidden' ? 'selected' : '' ?>>Hidden</option>
        </select>
      </div>

      <!-- Sort -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
        <select id="sortSelect" class="w-full px-3 py-2 sm:px-4 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
          <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A to Z</option>
          <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name: Z to A</option>
        </select>
      </div>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 mt-4">
      <button id="applyFilters" class="px-4 py-2 sm:px-6 sm:py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-medium flex items-center justify-center">
        <i class="fas fa-filter mr-2"></i> Apply Filters
      </button>
      <button id="resetFilters" class="px-4 py-2 sm:px-6 sm:py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg transition font-medium flex items-center justify-center">
        <i class="fas fa-redo mr-2"></i> Reset Filters
      </button>
    </div>
  </div>

  <!-- Stats: Total & Currently Showing -->
  <div class="mx-4 sm:mx-6 mb-6">
    <div class="bg-white rounded-xl shadow-md p-4 flex flex-col sm:flex-row gap-4 justify-between items-center">
      <div class="text-center sm:text-left">
        <div class="text-sm text-gray-500">Total Parts</div>
        <div class="text-2xl font-bold text-blue-600"><?= number_format($total_parts) ?></div>
      </div>
      <div class="text-center sm:text-left">
        <div class="text-sm text-gray-500">Currently Showing</div>
        <div class="text-2xl font-bold text-green-600"><?= number_format(count($parts)) ?></div>
      </div>
    </div>
  </div>

  <!-- Parts Grid -->
  <div class="mx-4 sm:mx-6 mb-6">
    <?php if (empty($parts)): ?>
      <div class="bg-white rounded-xl shadow-md py-12 text-center">
        <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-xl font-medium text-gray-500">No parts found</h3>
        <p class="text-gray-400 mt-2">Try adjusting your filters or search terms.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-6">
        <?php foreach ($parts as $part): ?>
          <div class="bg-white rounded-xl shadow-md overflow-hidden hover:shadow-lg transition transform hover:-translate-y-1 relative">
            <!-- Status Badge -->
            <div class="absolute top-2 right-2 z-10">
              <?php if ($part['status'] === 'active'): ?>
                <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full font-medium">Active</span>
              <?php else: ?>
                <span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full font-medium">Hidden</span>
              <?php endif; ?>
            </div>

            <!-- Image -->
            <div class="h-48 bg-gray-100">
              <?php if ($part['image_url']): ?>
                <img src="<?= htmlspecialchars($part['image_url']) ?>" alt="<?= htmlspecialchars($part['name']) ?>"
                     class="w-full h-full object-cover">
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center">
                  <i class="fas fa-car text-gray-400 text-4xl"></i>
                </div>
              <?php endif; ?>
            </div>

            <!-- Content -->
            <div class="p-5">
              <h3 class="font-semibold text-gray-800 mb-1 line-clamp-1"><?= htmlspecialchars($part['name']) ?></h3>
              <span class="capitalize text-sm text-blue-600 mb-2"><?= htmlspecialchars($part['category_name'] ?? 'Unknown') ?></span>
              <div class="flex items-center justify-between mt-2">
                <span class="text-xl font-bold text-gray-800">₹<?= number_format($part['price']) ?></span>
                <span class="text-sm text-gray-500">Stock: <?= $part['stock'] ?></span>
              </div>

              <!-- Actions -->
              <div class="flex flex-wrap gap-2 mt-4">
                <a href="../buyer/view_part.php?id=<?= $part['id'] ?>" 
                   target="_blank"
                   class="text-blue-600 hover:text-blue-800 transition p-2 rounded-full hover:bg-blue-100"
                   title="View part details">
                  <i class="fas fa-external-link-alt"></i>
                </a>
                <?php if ($part['status'] === 'active'): ?>
                  <a href="hide_part.php?id=<?= $part['id'] ?>" 
                     class="text-yellow-600 hover:text-yellow-800 transition p-2 rounded-full hover:bg-yellow-100"
                     onclick="return confirm('Hide this part from users?')"
                     title="Hide Part">
                    <i class="fas fa-eye-slash"></i>
                  </a>
                <?php else: ?>
                  <a href="show_part.php?id=<?= $part['id'] ?>" 
                     class="text-green-600 hover:text-green-800 transition p-2 rounded-full hover:bg-green-100"
                     onclick="return confirm('Make this part visible to users?')"
                     title="Show Part">
                    <i class="fas fa-eye"></i>
                  </a>
                <?php endif; ?>
                <a href="delete_part.php?id=<?= $part['id'] ?>" 
                   class="text-red-600 hover:text-red-800 transition p-2 rounded-full hover:bg-red-100"
                   onclick="return confirm('Permanently delete this part? This cannot be undone.')"
                   title="Delete Part">
                  <i class="fas fa-trash"></i>
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php include 'includes/admin_footer.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const applyFiltersBtn = document.getElementById('applyFilters');
      const resetFiltersBtn = document.getElementById('resetFilters');
      
      function applyFilters() {
        const search = document.getElementById('searchInput').value.trim();
        const category = document.getElementById('categorySelect').value;
        const status = document.getElementById('statusSelect').value;
        const sort = document.getElementById('sortSelect').value;
        
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (category) params.set('category', category);
        if (status !== 'all') params.set('status', status);
        if (sort !== 'name_asc') params.set('sort', sort);
        
        window.location.href = 'manage_parts.php?' + params.toString();
      }
      
      function resetFilters() {
        window.location.href = 'manage_parts.php';
      }
      
      applyFiltersBtn.addEventListener('click', applyFilters);
      resetFiltersBtn.addEventListener('click', resetFilters);
      
      document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') applyFilters();
      });
    });
  </script>
</body>
</html>