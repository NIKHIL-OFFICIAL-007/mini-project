<?php
session_start();
include '../includes/config.php';

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Fetch user role and status
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT role, role_status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// ✅ Check if user has buyer role (even if multi-role)
$roles = [];
if (!empty($user['role'])) {
    $roles = explode(',', $user['role']);
}
$has_buyer_role = in_array('buyer', $roles) && $user['role_status'] === 'approved';

// Fetch wishlist and cart items only if user has buyer role
$wishlist_items = [];
$cart_items = [];

if ($has_buyer_role) {
    try {
        // Get wishlist items
        $wishlist_stmt = $pdo->prepare("SELECT part_id FROM wishlists WHERE user_id = ?");
        $wishlist_stmt->execute([$user_id]);
        $wishlist_items = $wishlist_stmt->fetchAll(PDO::FETCH_COLUMN);

        // Get cart items
        $cart_stmt = $pdo->prepare("SELECT product_id FROM cart_items WHERE buyer_id = ?");
        $cart_stmt->execute([$user_id]);
        $cart_items = $cart_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Failed to fetch wishlist/cart: " . $e->getMessage());
    }
}

// Get filters
$search = trim($_GET['search'] ?? '');
$category_id = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$rating = $_GET['rating'] ?? ''; // New rating filter
$page = (int)($_GET['page'] ?? 1);
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query
$parts = [];
$total_parts = 0;

try {
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) FROM parts p WHERE p.status = 'active'";
    $params = [];
    $types = [];

    if ($search !== '') {
        $count_sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $types[] = \PDO::PARAM_STR;
        $types[] = \PDO::PARAM_STR;
    }
    if ($category_id !== '' && is_numeric($category_id)) {
        $count_sql .= " AND p.category_id = ?";
        $params[] = $category_id;
        $types[] = \PDO::PARAM_INT;
    }
    if ($min_price !== '' && is_numeric($min_price)) {
        $count_sql .= " AND p.price >= ?";
        $params[] = $min_price;
        $types[] = \PDO::PARAM_STR;
    }
    if ($max_price !== '' && is_numeric($max_price)) {
        $count_sql .= " AND p.price <= ?";
        $params[] = $max_price;
        $types[] = \PDO::PARAM_STR;
    }

    $stmt = $pdo->prepare($count_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value, $types[$key]);
    }
    $stmt->execute();
    $total_parts = $stmt->fetchColumn();

    // Get parts with category name and average rating
    $sql = "SELECT p.id, p.name, c.name as category_name, p.price, p.stock_quantity as stock, 
                   p.description, p.image_url, p.created_at, u.name as seller_name,
                   COALESCE(AVG(r.rating), 0) as average_rating,
                   COUNT(r.id) as review_count
            FROM parts p 
            LEFT JOIN users u ON p.seller_id = u.id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN reviews r ON p.id = r.part_id AND r.status = 'active'
            WHERE p.status = 'active'";

    $params = [];
    $types = [];

    if ($search !== '') {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $types[] = \PDO::PARAM_STR;
        $types[] = \PDO::PARAM_STR;
    }
    if ($category_id !== '' && is_numeric($category_id)) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_id;
        $types[] = \PDO::PARAM_INT;
    }
    if ($min_price !== '' && is_numeric($min_price)) {
        $sql .= " AND p.price >= ?";
        $params[] = $min_price;
        $types[] = \PDO::PARAM_STR;
    }
    if ($max_price !== '' && is_numeric($max_price)) {
        $sql .= " AND p.price <= ?";
        $params[] = $max_price;
        $types[] = \PDO::PARAM_STR;
    }

    // Add GROUP BY for the aggregate functions
    $sql .= " GROUP BY p.id";

    // Apply rating filter after grouping
    if ($rating !== '' && is_numeric($rating)) {
        $sql .= " HAVING average_rating >= ?";
        $params[] = $rating;
        $types[] = \PDO::PARAM_STR;
    }

    // Add sorting
    switch ($sort) {
        case 'price_low':
            $sql .= " ORDER BY p.price ASC";
            break;
        case 'price_high':
            $sql .= " ORDER BY p.price DESC";
            break;
        case 'name':
            $sql .= " ORDER BY p.name ASC";
            break;
        case 'rating':
            $sql .= " ORDER BY average_rating DESC";
            break;
        default:
            $sql .= " ORDER BY p.created_at DESC";
            break;
    }

    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $types[] = \PDO::PARAM_INT;
    $params[] = $offset;
    $types[] = \PDO::PARAM_INT;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value, $types[$key]);
    }
    $stmt->execute();
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all categories for filter
    $categories = $pdo->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    if ($categories === null) $categories = [];
} catch (Exception $e) {
    error_log("Failed to fetch parts: " . $e->getMessage());
    $parts = [];
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Browse Parts - AutoParts Hub</title>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    :root {
      --primary: #2563eb;
      --primary-dark: #1d4ed8;
    }

    body {
      font-family: 'Inter', sans-serif;
      background-color: #f9fafb;
    }

    .gradient-bg {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    }

    .part-card {
      transition: all 0.3s ease;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
      position: relative;
    }

    .part-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 12px 20px -4px rgba(37, 99, 235, 0.25);
    }

    .price-tag {
      background: linear-gradient(135deg, var(--primary), var(--primary-dark));
      color: white;
      padding: 0.3rem 0.8rem;
      border-radius: 30px;
      font-weight: 600;
      font-size: 0.9rem;
    }

    .wishlist-btn {
      position: absolute;
      top: 16px;
      right: 16px;
      background: white;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 10;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .wishlist-btn:hover {
      transform: scale(1.15);
    }

    .wishlist-btn.active {
      color: #ef4444;
    }

    .wishlist-btn:not(.active) {
      color: #9ca3af;
    }

    .wishlist-btn:not(.active):hover {
      color: #ef4444;
    }

    .part-image-container {
      position: relative;
      cursor: pointer;
    }

    .part-image-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.03);
      opacity: 0;
      transition: opacity 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .part-image-container:hover .part-image-overlay {
      opacity: 1;
    }

    .view-details-btn {
      background: rgba(255, 255, 255, 0.95);
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 600;
      transform: translateY(10px);
      transition: all 0.3s ease;
      opacity: 0;
    }

    .part-image-container:hover .view-details-btn {
      transform: translateY(0);
      opacity: 1;
    }

    .line-clamp-2 {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .heartbeat {
      animation: heartbeat 1.5s ease-in-out infinite both;
    }

    @keyframes heartbeat {
      from { transform: scale(1); }
      10% { transform: scale(0.91); }
      17% { transform: scale(0.98); }
      33% { transform: scale(0.87); }
      45% { transform: scale(1); }
    }

    .added-to-cart {
      background: linear-gradient(135deg, #10b981, #059669);
    }

    /* Filter Styles */
    .filter-section {
      border-bottom: 1px solid #e5e7eb;
      padding: 1rem 0;
    }

    .filter-section:last-child {
      border-bottom: none;
    }

    .filter-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
      padding: 0.5rem 0;
      font-weight: 600;
      color: #1f2937;
    }

    .filter-header:hover {
      color: #111827;
    }

    .filter-content {
      display: none; /* Start collapsed */
      overflow: hidden;
    }

    /* View Toggle */
    .view-toggle {
      display: flex;
      gap: 0.5rem;
      background: white;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 0.25rem;
    }

    .view-button {
      padding: 0.5rem 1rem;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.875rem;
      font-weight: 500;
    }

    .view-button.active {
      background: #2563eb;
      color: white;
    }

    .view-button:hover {
      background: #3b82f6;
      color: white;
    }

    /* Rating Stars */
    .rating-stars {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .rating-option {
      display: flex;
      align-items: center;
      cursor: pointer;
      padding: 0.5rem;
      border-radius: 8px;
      transition: background-color 0.2s;
    }

    .rating-option:hover {
      background-color: #f3f4f6;
    }

    .rating-option.selected {
      background-color: #dbeafe;
    }

    .stars-display {
      display: flex;
      align-items: center;
      gap: 0.25rem;
    }

    .star {
      color: #d1d5db;
    }

    .star.filled {
      color: #f59e0b;
    }

    .rating-text {
      margin-left: 0.5rem;
      font-size: 0.875rem;
      color: #6b7280;
    }

    .rating-option.selected .rating-text {
      color: #1f2937;
      font-weight: 500;
    }

    .no-reviews {
      color: #9ca3af;
      font-size: 0.75rem;
    }

    /* Scrollable Filters */
    .filters-sidebar {
      max-height: calc(100vh - 120px);
      overflow-y: auto;
    }

    /* Custom Scrollbar */
    .filters-sidebar::-webkit-scrollbar {
      width: 6px;
    }

    .filters-sidebar::-webkit-scrollbar-track {
      background: #f1f5f9;
      border-radius: 10px;
    }

    .filters-sidebar::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 10px;
    }

    .filters-sidebar::-webkit-scrollbar-thumb:hover {
      background: #94a3b8;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include '../includes/header.php'; ?>

  <!-- Page Header -->
  <div class="gradient-bg text-white py-16">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Browse Vehicle Parts</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Discover high-quality auto parts from trusted sellers across India.</p>
    </div>
  </div>

  <!-- ✅ Search Bar with Gap Below Header -->
  <div class="container mx-auto px-4 sm:px-6 lg:px-8 mt-6">
    <div class="flex items-center">
      <input type="text" id="searchInput" placeholder="Search parts..." 
             class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
             value="<?= htmlspecialchars($search) ?>">
      <button id="searchButton" class="ml-3 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium shadow-md">
        <i class="fas fa-search mr-2"></i> Search
      </button>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-4 py-8">
    <div class="flex flex-col lg:flex-row gap-8">
      <!-- ✅ Scrollable Filters Sidebar -->
      <div class="w-full lg:w-80 flex-shrink-0">
        <div class="bg-white rounded-xl shadow-md p-6 h-fit lg:sticky lg:top-24 filters-sidebar">
          
          <!-- Small Heading -->
          <div class="mb-4 pb-3 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-800">Filters</h2>
          </div>

          <!-- Price Filter -->
          <div class="filter-section">
            <div class="filter-header" onclick="toggleFilter('price')">
              <span>Price (₹)</span>
              <i class="fas fa-plus toggle-icon text-gray-500"></i>
            </div>
            <div class="filter-content mt-3" id="priceContent">
              <div class="grid grid-cols-2 gap-3">
                <input type="number" id="minPrice" placeholder="Min" 
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500"
                       value="<?= htmlspecialchars($min_price) ?>" min="0">
                <input type="number" id="maxPrice" placeholder="Max" 
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500"
                       value="<?= htmlspecialchars($max_price) ?>" min="0">
              </div>
            </div>
          </div>

          <!-- Category Filter -->
          <div class="filter-section">
            <div class="filter-header" onclick="toggleFilter('category')">
              <span>Category</span>
              <i class="fas fa-plus toggle-icon text-gray-500"></i>
            </div>
            <div class="filter-content mt-3" id="categoryContent">
              <select id="categorySelect" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars(ucfirst($cat['name'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Rating Filter -->
          <div class="filter-section">
            <div class="filter-header" onclick="toggleFilter('rating')">
              <span>Customer Rating</span>
              <i class="fas fa-plus toggle-icon text-gray-500"></i>
            </div>
            <div class="filter-content mt-3" id="ratingContent">
              <div class="rating-stars">
                <?php
                $ratings = [
                  5 => '5 Stars & Up',
                  4 => '4 Stars & Up',
                  3 => '3 Stars & Up',
                  2 => '2 Stars & Up',
                  1 => '1 Star & Up'
                ];
                foreach ($ratings as $value => $text): 
                  $isSelected = $rating == $value;
                ?>
                  <div class="rating-option <?= $isSelected ? 'selected' : '' ?>" 
                       onclick="selectRating(<?= $value ?>)">
                    <div class="stars-display">
                      <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star <?= $i <= $value ? 'filled' : '' ?>">
                          <i class="fas fa-star text-sm"></i>
                        </span>
                      <?php endfor; ?>
                    </div>
                    <span class="rating-text"><?= $text ?></span>
                  </div>
                <?php endforeach; ?>
                <input type="hidden" id="ratingInput" value="<?= htmlspecialchars($rating) ?>">
              </div>
            </div>
          </div>

          <!-- Sort Filter -->
          <div class="filter-section">
            <div class="filter-header" onclick="toggleFilter('sort')">
              <span>Sort By</span>
              <i class="fas fa-plus toggle-icon text-gray-500"></i>
            </div>
            <div class="filter-content mt-3" id="sortContent">
              <select id="sortSelect" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name: A to Z</option>
                <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
              </select>
            </div>
          </div>

          <!-- Apply & Reset Buttons -->
          <div class="space-y-3 mt-6">
            <button id="applyFilters" class="w-full px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium shadow-md">
              <i class="fas fa-filter mr-2"></i> Apply Filters
            </button>
            <button id="resetFilters" class="w-full px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-xl font-medium">
              <i class="fas fa-undo mr-2"></i> Reset All
            </button>
          </div>
        </div>
      </div>

      <!-- Main Content Area -->
      <div class="flex-1">
        <!-- Results Info and View Toggle -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
          <div class="text-gray-600 mb-4 md:mb-0">
            <?php if ($total_parts === 0): ?>
              <p class="text-lg">No parts found.</p>
            <?php else: ?>
              <p class="font-medium text-gray-800">
                Showing <span class="text-blue-600 font-semibold"><?= min($offset + 1, $total_parts) ?>–<?= min($offset + count($parts), $total_parts) ?></span> of <span class="text-blue-600 font-semibold"><?= $total_parts ?></span> parts
              </p>
            <?php endif; ?>
          </div>

          <div class="view-toggle">
            <button id="gridView" class="view-button active">
              <i class="fas fa-th"></i>
            </button>
            <button id="listView" class="view-button">
              <i class="fas fa-list"></i>
            </button>
          </div>
        </div>

        <!-- Parts Grid -->
        <div id="partsContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php if (empty($parts)): ?>
            <div class="col-span-full text-center py-16 bg-white rounded-2xl shadow-sm border-dashed border-gray-300">
              <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
              <h3 class="text-xl font-semibold text-gray-600 mb-2">No parts available</h3>
              <p class="text-gray-500 max-w-md mx-auto">Try adjusting your filters.</p>
              <?php if ($search || $category_id || $min_price || $max_price || $rating): ?>
                <a href="?" class="inline-block mt-4 px-5 py-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700">
                  Clear All Filters
                </a>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <?php foreach ($parts as $part): ?>
              <div class="part-card bg-white rounded-2xl overflow-hidden shadow-sm border">
                <!-- Wishlist -->
                <div class="wishlist-btn <?= $has_buyer_role ? (in_array($part['id'], $wishlist_items) ? 'active' : '') : 'cursor-not-allowed opacity-50' ?>"
                  data-part-id="<?= $part['id'] ?>" onclick="handleWishlistAction(this, <?= $part['id'] ?>, event)">
                  <i class="fas fa-heart <?= in_array($part['id'], $wishlist_items) ? 'heartbeat' : '' ?>"></i>
                </div>

                <!-- Image -->
                <div class="part-image-container relative h-48 bg-gray-50 overflow-hidden"
                  onclick="window.location.href='view_part.php?id=<?= $part['id'] ?>'">
                  <?php if ($part['image_url']): ?>
                    <img src="<?= htmlspecialchars($part['image_url']) ?>" alt="<?= htmlspecialchars($part['name']) ?>"
                         class="w-full h-full object-cover">
                  <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center bg-gray-100">
                      <i class="fas fa-cog text-gray-400 text-4xl"></i>
                    </div>
                  <?php endif; ?>
                  <div class="part-image-overlay">
                    <span class="view-details-btn">View Details <i class="fas fa-arrow-right ml-1"></i></span>
                  </div>
                  <div class="absolute top-4 left-4">
                    <span class="price-tag">₹<?= number_format($part['price'], 0) ?></span>
                  </div>
                  <?php if ($part['stock'] <= 5 && $part['stock'] > 0): ?>
                    <div class="absolute top-4 right-12 bg-amber-500 text-white text-xs px-2.5 py-1 rounded-full font-medium">Low Stock</div>
                  <?php elseif ($part['stock'] == 0): ?>
                    <div class="absolute top-4 right-12 bg-red-500 text-white text-xs px-2.5 py-1 rounded-full font-medium">Out of Stock</div>
                  <?php endif; ?>
                </div>

                <!-- Info -->
                <div class="p-5">
                  <div class="flex justify-between items-start mb-3">
                    <h3 class="font-semibold text-gray-800 text-base cursor-pointer hover:text-blue-600 line-clamp-1"
                        onclick="window.location.href='view_part.php?id=<?= $part['id'] ?>'">
                      <?= htmlspecialchars($part['name']) ?>
                    </h3>
                    <span class="text-xs text-gray-500"><?= date('M j', strtotime($part['created_at'])) ?></span>
                  </div>
                  
                  <!-- Rating Display -->
                  <div class="flex items-center mb-3">
                    <div class="flex items-center">
                      <?php
                      $avg_rating = $part['average_rating'] ?? 0;
                      $review_count = $part['review_count'] ?? 0;
                      $full_stars = floor($avg_rating);
                      $has_half_star = ($avg_rating - $full_stars) >= 0.5;
                      
                      if ($review_count > 0): ?>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                          <?php if ($i <= $full_stars): ?>
                            <i class="fas fa-star text-yellow-400 text-xs"></i>
                          <?php elseif ($has_half_star && $i == $full_stars + 1): ?>
                            <i class="fas fa-star-half-alt text-yellow-400 text-xs"></i>
                          <?php else: ?>
                            <i class="far fa-star text-yellow-400 text-xs"></i>
                          <?php endif; ?>
                        <?php endfor; ?>
                        <span class="ml-1 text-xs text-gray-600">(<?= number_format($avg_rating, 1) ?>)</span>
                        <span class="ml-1 text-xs text-gray-400">• <?= $review_count ?> review<?= $review_count !== 1 ? 's' : '' ?></span>
                      <?php else: ?>
                        <span class="no-reviews">No reviews yet</span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <span class="inline-block capitalize text-xs font-semibold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full mb-3">
                    <?= htmlspecialchars($part['category_name'] ?? 'Unknown') ?>
                  </span>
                  <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($part['description']) ?></p>

                  <div class="flex justify-between text-sm text-gray-500 mb-4">
                    <span><i class="fas fa-store mr-1"></i> <?= htmlspecialchars($part['seller_name'] ?? 'Seller') ?></span>
                    <span class="<?= $part['stock'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                      <i class="fas fa-boxes mr-1"></i> <?= $part['stock'] ?>
                    </span>
                  </div>

                  <div class="flex space-x-3">
                    <form method="POST" action="cart/add_to_cart.php" class="flex-1">
                      <input type="hidden" name="part_id" value="<?= $part['id'] ?>">
                      <input type="hidden" name="quantity" value="1">
                      <?php if ($has_buyer_role): ?>
                        <?php if (in_array($part['id'], $cart_items)): ?>
                          <button type="button" class="w-full px-3.5 py-2.5 bg-green-600 text-white rounded-xl cursor-default added-to-cart">
                            <i class="fas fa-check-circle mr-1.5"></i> Added to Cart
                          </button>
                        <?php else: ?>
                          <button type="submit" class="w-full px-3.5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl" <?= $part['stock'] <= 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-shopping-cart mr-1.5"></i> <?= $part['stock'] > 0 ? 'Add to Cart' : 'Out of Stock' ?>
                          </button>
                        <?php endif; ?>
                      <?php else: ?>
                        <button type="button" class="w-full px-3.5 py-2.5 bg-gray-400 text-white rounded-xl cursor-not-allowed"
                                onclick="showRoleError()">
                          <i class="fas fa-ban mr-1.5"></i> Buyer Only
                        </button>
                      <?php endif; ?>
                    </form>
                    <a href="view_part.php?id=<?= $part['id'] ?>" 
                       class="flex items-center justify-center w-12 h-12 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-100">
                      <i class="fas fa-eye"></i>
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_parts > $limit): ?>
          <div class="flex justify-center mt-12">
            <nav class="inline-flex rounded-lg shadow-sm border">
              <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(['search' => $search, 'category' => $category_id, 'min_price' => $min_price, 'max_price' => $max_price, 'rating' => $rating, 'sort' => $sort, 'page' => $page - 1]) ?>"
                   class="px-4 py-2.5 border-r bg-white text-blue-600 hover:bg-blue-50 rounded-l-lg flex items-center">
                  <i class="fas fa-chevron-left mr-1"></i> Prev
                </a>
              <?php endif; ?>

              <?php 
              $total_pages = ceil($total_parts / $limit);
              $start_page = max(1, $page - 2);
              $end_page = min($total_pages, $start_page + 4);
              if ($end_page - $start_page < 4 && $start_page > 1) {
                $start_page = max(1, $end_page - 4);
              }
              for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?<?= http_build_query(array_filter(['search' => $search, 'category' => $category_id, 'min_price' => $min_price, 'max_price' => $max_price, 'rating' => $rating, 'sort' => $sort, 'page' => $i])) ?>"
                   class="px-4 py-2.5 bg-white text-blue-600 hover:bg-blue-50 <?= $page === $i ? 'font-bold bg-blue-50' : '' ?>">
                  <?= $i ?>
                </a>
              <?php endfor; ?>

              <?php if ($page < $total_pages): ?>
                <a href="?<?= http_build_query(['search' => $search, 'category' => $category_id, 'min_price' => $min_price, 'max_price' => $max_price, 'rating' => $rating, 'sort' => $sort, 'page' => $page + 1]) ?>"
                   class="px-4 py-2.5 bg-white text-blue-600 hover:bg-blue-50 rounded-r-lg flex items-center">
                  Next <i class="fas fa-chevron-right ml-1"></i>
                </a>
              <?php endif; ?>
            </nav>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include '../includes/footer.php'; ?>

  <!-- Modals -->
  <div id="roleErrorModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl p-6 max-w-sm mx-4 text-center shadow-xl">
      <i class="fas fa-exclamation-triangle text-yellow-500 text-4xl mb-4"></i>
      <h3 class="text-lg font-bold text-gray-800 mb-2">Permission Denied</h3>
      <p class="text-gray-600 mb-4">You need a buyer role to use this function.</p>
      <button onclick="hideRoleError()" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
        OK
      </button>
    </div>
  </div>

  <div id="wishlistNotification" class="fixed top-4 right-4 hidden p-4 bg-green-500 text-white rounded-lg shadow-lg max-w-xs z-50">
    <div class="flex items-center">
      <i class="fas fa-check-circle mr-3"></i>
      <span id="wishlistMessage">Item added to wishlist</span>
      <button class="ml-4" onclick="hideWishlistNotification()">
        <i class="fas fa-times"></i>
      </button>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const searchInput = document.getElementById('searchInput');
      const searchButton = document.getElementById('searchButton');
      const categorySelect = document.getElementById('categorySelect');
      const sortSelect = document.getElementById('sortSelect');
      const minPrice = document.getElementById('minPrice');
      const maxPrice = document.getElementById('maxPrice');
      const ratingInput = document.getElementById('ratingInput');
      const applyFilters = document.getElementById('applyFilters');
      const resetFilters = document.getElementById('resetFilters');
      const gridView = document.getElementById('gridView');
      const listView = document.getElementById('listView');
      const partsContainer = document.getElementById('partsContainer');

      function applyFiltersWithLoading() {
        const params = new URLSearchParams();
        if (searchInput.value.trim() !== '') params.set('search', searchInput.value.trim());
        if (categorySelect.value) params.set('category', categorySelect.value);
        if (sortSelect.value) params.set('sort', sortSelect.value);
        if (minPrice.value) params.set('min_price', minPrice.value);
        if (maxPrice.value) params.set('max_price', maxPrice.value);
        if (ratingInput.value) params.set('rating', ratingInput.value);
        window.location.href = 'browse_parts.php?' + params.toString();
      }

      searchButton.addEventListener('click', applyFiltersWithLoading);
      applyFilters.addEventListener('click', applyFiltersWithLoading);
      resetFilters.addEventListener('click', function() {
        searchInput.value = '';
        categorySelect.value = '';
        sortSelect.value = 'newest';
        minPrice.value = '';
        maxPrice.value = '';
        ratingInput.value = '';
        // Reset rating UI
        document.querySelectorAll('.rating-option').forEach(option => {
          option.classList.remove('selected');
        });
        applyFiltersWithLoading();
      });

      [searchInput, minPrice, maxPrice].forEach(el => {
        el.addEventListener('keypress', (e) => { if (e.key === 'Enter') applyFiltersWithLoading(); });
      });

      // View toggle
      gridView.addEventListener('click', function() {
        partsContainer.className = 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6';
        gridView.classList.add('active');
        listView.classList.remove('active');
      });

      listView.addEventListener('click', function() {
        partsContainer.className = 'grid grid-cols-1 gap-6';
        listView.classList.add('active');
        gridView.classList.remove('active');
      });

      // Initialize all filter sections as collapsed on page load
      document.querySelectorAll('.filter-content').forEach(content => {
        content.style.display = 'none';
      });
    });

    // Fixed toggle filter function
    function toggleFilter(id) {
      const content = document.getElementById(id + 'Content');
      const icon = content.previousElementSibling.querySelector('.toggle-icon');
      const isVisible = content.style.display !== 'none';
      
      if (isVisible) {
        content.style.display = 'none';
        icon.className = 'fas fa-plus toggle-icon text-gray-500';
      } else {
        content.style.display = 'block';
        icon.className = 'fas fa-minus toggle-icon text-gray-500';
      }
    }

    function selectRating(value) {
      // Update hidden input
      document.getElementById('ratingInput').value = value;
      
      // Update UI
      document.querySelectorAll('.rating-option').forEach(option => {
        option.classList.remove('selected');
      });
      
      // Find and select the clicked option
      document.querySelectorAll('.rating-option').forEach(option => {
        const stars = option.querySelectorAll('.star.filled');
        if (stars.length === value) {
          option.classList.add('selected');
        }
      });
    }

    function showRoleError() {
      document.getElementById('roleErrorModal').classList.remove('hidden');
    }

    function hideRoleError() {
      document.getElementById('roleErrorModal').classList.add('hidden');
    }

    function handleWishlistAction(button, partId, event) {
      event.stopPropagation();
      <?php if (!$has_buyer_role): ?>
        showRoleError();
        return;
      <?php else: ?>
        const isActive = button.classList.contains('active');
        const heartIcon = button.querySelector('i');
        heartIcon.className = 'fas fa-spinner fa-spin';
        const formData = new FormData();
        formData.append('part_id', partId);
        formData.append('action', isActive ? 'remove' : 'add');

        fetch('wishlist/toggle_wishlist.php', { method: 'POST', body: formData })
          .then(r => r.json())
          .then(data => {
            if (data.success) {
              button.classList.toggle('active');
              heartIcon.className = 'fas fa-heart' + (data.action === 'add' ? ' heartbeat' : '');
              showWishlistNotification(data.message);
            } else {
              showWishlistNotification('Error: ' + data.message);
              heartIcon.className = 'fas fa-heart' + (isActive ? ' heartbeat' : '');
            }
          })
          .catch(() => {
            showWishlistNotification('Network error. Please try again.');
            heartIcon.className = 'fas fa-heart' + (isActive ? ' heartbeat' : '');
          });
      <?php endif; ?>
    }

    function showWishlistNotification(message) {
      const n = document.getElementById('wishlistNotification');
      document.getElementById('wishlistMessage').textContent = message;
      n.classList.remove('hidden');
      setTimeout(() => n.classList.add('hidden'), 3000);
    }

    function hideWishlistNotification() {
      document.getElementById('wishlistNotification').classList.add('hidden');
    }

    document.querySelectorAll('.part-card').forEach(card => {
      card.addEventListener('click', function(e) {
        if (e.target.tagName === 'BUTTON' || e.target.closest('button') || e.target.closest('.wishlist-btn')) return;
        const partId = this.querySelector('.wishlist-btn')?.dataset.partId;
        if (partId) window.location.href = 'view_part.php?id=' + partId;
      });
    });
  </script>
</body>
</html>