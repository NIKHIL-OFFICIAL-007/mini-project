<?php
session_start();
// Assuming config.php is in the parent directory's 'includes' folder: ../includes/config.php
include '../includes/config.php'; 

// --- Security Check ---
// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Redirect if not a support agent
$roles = explode(',', $_SESSION['role'] ?? '');
if (!in_array('support', $roles) || ($_SESSION['role_status'] ?? '') !== 'approved') {
    header("Location: ../dashboard.php");
    exit();
}

// --- Configuration and State ---
$per_page = 12; // Changed to fit grid layout better
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $per_page;

// Get filter parameters from URL
$filter_rating = isset($_GET['rating']) ? $_GET['rating'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- Handle Moderation Action (Hide/Show Review) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['review_id'])) {
    $review_id = (int)$_POST['review_id'];
    $new_status = $_POST['action'] === 'hide' ? 'hidden' : 'active';
    $success = false;

    try {
        $stmt = $pdo->prepare("UPDATE reviews SET status = ? WHERE id = ?");
        $success = $stmt->execute([$new_status, $review_id]);
        
        if ($success) {
            $_SESSION['message'] = "Review ID {$review_id} has been set to '{$new_status}'.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Failed to update review status.";
            $_SESSION['message_type'] = 'danger';
        }

    } catch (Exception $e) {
        error_log("Review moderation failed: " . $e->getMessage());
        $_SESSION['message'] = "Database error: Could not process action.";
        $_SESSION['message_type'] = 'danger';
    }
    
    // Redirect back to the page to prevent form resubmission
    $redirect_url = 'reviews.php?' . http_build_query([
        'page' => $current_page,
        'rating' => $filter_rating,
        'status' => $filter_status,
        'search' => $search_term
    ]);
    header("Location: " . $redirect_url);
    exit();
}

// --- Build SQL Query for Reviews ---
$sql_where = [];
$sql_params = [];

// Filter by Rating
if ($filter_rating && is_numeric($filter_rating)) {
    $sql_where[] = "r.rating = ?";
    $sql_params[] = (int)$filter_rating;
}

// Filter by Status
if ($filter_status !== 'all' && in_array($filter_status, ['active', 'hidden', 'reported'])) { 
    $sql_where[] = "r.status = ?";
    $sql_params[] = $filter_status;
}

// Filter by Search Term
if ($search_term) {
    $sql_where[] = "(p.name LIKE ? OR r.comment LIKE ? OR u_buyer.name LIKE ?)";
    $sql_params[] = '%' . $search_term . '%';
    $sql_params[] = '%' . $search_term . '%';
    $sql_params[] = '%' . $search_term . '%';
}

$where_clause = count($sql_where) > 0 ? " WHERE " . implode(" AND ", $sql_where) : "";

// 1. Get total count for pagination
$count_sql = "
    SELECT COUNT(r.id)
    FROM reviews r
    LEFT JOIN parts p ON r.part_id = p.id
    " . $where_clause;

$total_reviews = 0;
try {
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($sql_params);
    $total_reviews = (int)$count_stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Failed to count reviews: " . $e->getMessage());
}

$total_pages = ceil($total_reviews / $per_page);

// 2. Fetch reviews for the current page with additional part data
$reviews = [];
$review_sql = "
    SELECT 
        r.id, r.rating, r.comment, r.created_at, r.status, r.buyer_id,
        p.name as part_name, p.id as part_id, p.image_url, p.price,
        u_buyer.name as buyer_name,
        u_seller.name as seller_name,
        c.name as category_name
    FROM reviews r
    LEFT JOIN parts p ON r.part_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u_buyer ON r.buyer_id = u_buyer.id
    LEFT JOIN users u_seller ON p.seller_id = u_seller.id
    " . $where_clause . "
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?";

try {
    $review_stmt = $pdo->prepare($review_sql);
    
    // Bind parameters
    for ($i = 0; $i < count($sql_params); $i++) {
        $review_stmt->bindValue($i + 1, $sql_params[$i], is_int($sql_params[$i]) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $review_stmt->bindValue(count($sql_params) + 1, (int)$per_page, PDO::PARAM_INT);
    $review_stmt->bindValue(count($sql_params) + 2, (int)$offset, PDO::PARAM_INT);

    $review_stmt->execute();
    $reviews = $review_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch reviews: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Review Moderation - Support Dashboard</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    .review-card {
      transition: all 0.3s ease;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }

    .review-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 12px 20px -4px rgba(37, 99, 235, 0.25);
    }

    .line-clamp-2 {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .line-clamp-1 {
      display: -webkit-box;
      -webkit-line-clamp: 1;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .price-tag {
      background: linear-gradient(135deg, #2563eb, #1d4ed8);
      color: white;
      padding: 0.3rem 0.8rem;
      border-radius: 30px;
      font-weight: 600;
      font-size: 0.9rem;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/support_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Review Moderation</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Manage and moderate all customer reviews for parts on the platform.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-4 py-8">
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['message'])): ?>
      <div class="p-4 mb-6 rounded-xl 
          <?php 
          echo $_SESSION['message_type'] === 'success' 
            ? 'bg-green-100 border border-green-400 text-green-700' 
            : 'bg-red-100 border border-red-400 text-red-700'; 
          ?>">
        <div class="flex items-center">
          <i class="fas <?= $_SESSION['message_type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
          <span><?= htmlspecialchars($_SESSION['message']) ?></span>
        </div>
      </div>
      <?php 
      unset($_SESSION['message']); 
      unset($_SESSION['message_type']);
      ?>
    <?php endif; ?>

    <!-- Filters Card -->
    <div class="bg-white rounded-2xl shadow-lg border border-blue-100 overflow-hidden mb-8">
      <div class="bg-gradient-to-r from-blue-50 to-cyan-50 px-6 py-4 border-b border-blue-100">
        <h2 class="text-xl font-bold text-gray-800 flex items-center">
          <i class="fas fa-filter mr-3 text-blue-600"></i>
          Filter Reviews
        </h2>
      </div>
      
      <div class="p-6">
        <form method="GET" action="reviews.php" class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <!-- Search -->
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <div class="relative">
              <input type="text" name="search" placeholder="Search by part, review, or buyer..."
                     class="w-full px-4 py-3 pl-10 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                     value="<?= htmlspecialchars($search_term) ?>">
              <div class="absolute inset-y-0 left-0 flex items-center pl-3">
                <i class="fas fa-search text-gray-400"></i>
              </div>
            </div>
          </div>

          <!-- Rating Filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Rating</label>
            <select name="rating" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <option value="">All Ratings</option>
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <option value="<?= $i ?>" <?= $filter_rating == $i ? 'selected' : '' ?>><?= $i ?> Star<?= $i !== 1 ? 's' : '' ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <!-- Status Filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select name="status" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
              <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Reviews</option>
              <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="hidden" <?= $filter_status === 'hidden' ? 'selected' : '' ?>>Hidden</option>
              <option value="reported" <?= $filter_status === 'reported' ? 'selected' : '' ?>>Reported</option>
            </select>
          </div>

          <!-- Filter Button -->
          <div class="md:col-span-4 flex space-x-4">
            <button type="submit" 
                    class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg font-semibold">
              <i class="fas fa-filter mr-2"></i>Apply Filters
            </button>
            <a href="reviews.php" 
               class="px-6 py-3 border-2 border-gray-300 text-gray-700 hover:bg-gray-50 rounded-xl transition-all duration-300 font-semibold">
              <i class="fas fa-refresh mr-2"></i>Reset
            </a>
          </div>
        </form>
      </div>
    </div>

    <!-- Results Info -->
    <div class="flex justify-between items-center mb-6">
      <div class="text-gray-600">
        <?php if (empty($reviews)): ?>
          <p class="text-lg">No reviews found.</p>
        <?php else: ?>
          <p class="font-medium text-gray-800">
            Showing <span class="text-blue-600 font-semibold"><?= count($reviews) ?></span> of <span class="text-blue-600 font-semibold"><?= $total_reviews ?></span> review<?= $total_reviews !== 1 ? 's' : '' ?>
          </p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Reviews Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php if (empty($reviews)): ?>
        <div class="col-span-full text-center py-16 bg-white rounded-2xl shadow-sm border-dashed border-gray-300">
          <i class="fas fa-search-minus text-6xl text-gray-300 mb-4"></i>
          <h3 class="text-xl font-semibold text-gray-600 mb-2">No reviews found</h3>
          <p class="text-gray-500 max-w-md mx-auto">Try adjusting your filters or search terms.</p>
        </div>
      <?php else: ?>
        <?php foreach ($reviews as $review): ?>
          <div class="review-card bg-white rounded-2xl overflow-hidden shadow-sm border 
            <?= ($review['status'] === 'hidden' || $review['status'] === 'reported') ? 'border-red-200 bg-red-50' : 'border-gray-200' ?>">
            
            <!-- Image -->
            <div class="relative h-48 bg-gray-50 overflow-hidden">
              <?php if ($review['image_url']): ?>
                <img src="<?= htmlspecialchars($review['image_url']) ?>" 
                     alt="<?= htmlspecialchars($review['part_name']) ?>"
                     class="w-full h-full object-cover">
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center bg-gray-100">
                  <i class="fas fa-cog text-gray-400 text-4xl"></i>
                </div>
              <?php endif; ?>
              
              <!-- Price Tag -->
              <div class="absolute top-4 left-4">
                <span class="price-tag">₹<?= number_format($review['price'] ?? 0, 0) ?></span>
              </div>
              
              <!-- Status Badge -->
              <div class="absolute top-4 right-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                  <?php
                    if ($review['status'] === 'active') echo 'bg-green-100 text-green-800';
                    elseif ($review['status'] === 'reported') echo 'bg-yellow-100 text-yellow-800';
                    else echo 'bg-red-100 text-red-800';
                  ?>">
                  <?= ucfirst($review['status'] ?? 'unknown') ?>
                </span>
              </div>
            </div>

            <!-- Info -->
            <div class="p-5">
              <!-- Part Name and Date -->
              <div class="flex justify-between items-start mb-3">
                <h3 class="font-semibold text-gray-800 text-base cursor-pointer hover:text-blue-600 line-clamp-1">
                  <?= htmlspecialchars($review['part_name'] ?? 'Part Deleted') ?>
                </h3>
                <span class="text-xs text-gray-500"><?= date('M j, Y', strtotime($review['created_at'])) ?></span>
              </div>

              <!-- Rating and Actions -->
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center">
                  <div class="flex items-center">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300' ?> text-sm"></i>
                    <?php endfor; ?>
                    <span class="ml-1 text-xs text-gray-600">(<?= number_format($review['rating'], 1) ?>)</span>
                  </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex items-center space-x-2">
                  <form method="POST" action="reviews.php" onsubmit="return confirm('Are you sure you want to <?= $review['status'] === 'active' ? 'HIDE' : 'SHOW' ?> this review?');">
                    <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                    <input type="hidden" name="action" value="<?= $review['status'] === 'active' ? 'hide' : 'show' ?>">
                    <input type="hidden" name="page" value="<?= $current_page ?>">
                    <input type="hidden" name="rating" value="<?= $filter_rating ?>">
                    <input type="hidden" name="status" value="<?= $filter_status ?>">
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search_term) ?>">

                    <?php if ($review['status'] === 'active'): ?>
                      <button type="submit" title="Hide Review" 
                              class="text-red-600 hover:text-red-800 transition duration-150 ease-in-out p-2 rounded-full hover:bg-red-100">
                        <i class="fas fa-eye-slash text-sm"></i>
                      </button>
                    <?php else: ?>
                      <button type="submit" title="Show Review" 
                              class="text-green-600 hover:text-green-800 transition duration-150 ease-in-out p-2 rounded-full hover:bg-green-100">
                        <i class="fas fa-eye text-sm"></i>
                      </button>
                    <?php endif; ?>
                  </form>
                  
                  <?php if ($review['part_id']): ?>
                    <a href="../buyer/view_part.php?id=<?= $review['part_id'] ?>" title="View Part" target="_blank" 
                       class="text-blue-600 hover:text-blue-800 transition duration-150 ease-in-out p-2 rounded-full hover:bg-blue-100">
                      <i class="fas fa-external-link-alt text-sm"></i>
                    </a>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Category and User Info -->
              <div class="space-y-2 mb-3">
                <span class="inline-block capitalize text-xs font-semibold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full">
                  <?= htmlspecialchars($review['category_name'] ?? 'Uncategorized') ?>
                </span>
                
                <div class="flex justify-between text-xs text-gray-500">
                  <span>
                    <i class="fas fa-user mr-1"></i>
                    <?= htmlspecialchars($review['buyer_name'] ?? 'Unknown Buyer') ?>
                  </span>
                  <span>
                    <i class="fas fa-store mr-1"></i>
                    <?= htmlspecialchars($review['seller_name'] ?? 'Unknown Seller') ?>
                  </span>
                </div>
              </div>

              <!-- Review Comment -->
              <div class="border-t border-gray-100 pt-3">
                <p class="text-gray-700 text-sm leading-relaxed line-clamp-3"><?= htmlspecialchars($review['comment']) ?></p>
              </div>

              <!-- Review ID -->
              <div class="mt-3 pt-3 border-t border-gray-100">
                <span class="text-xs text-gray-500">Review ID: #<?= $review['id'] ?></span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <div class="mt-8 flex justify-between items-center bg-white rounded-2xl shadow-lg border border-gray-200 p-6">
        <p class="text-sm text-gray-700">
          Page <span class="font-semibold"><?= $current_page ?></span> of <span class="font-semibold"><?= $total_pages ?></span>
        </p>
        
        <div class="flex space-x-2">
          <?php
          function get_pagination_url($page, $filter_rating, $filter_status, $search_term) {
              $query_params = [
                  'rating' => $filter_rating,
                  'status' => $filter_status,
                  'search' => $search_term,
                  'page' => $page
              ];
              return 'reviews.php?' . http_build_query(array_filter($query_params));
          }
          ?>

          <a href="<?= $current_page > 1 ? get_pagination_url($current_page - 1, $filter_rating, $filter_status, $search_term) : '#' ?>"
             class="px-4 py-2 rounded-xl font-medium transition-all duration-300 
                    <?= $current_page > 1 
                      ? 'bg-blue-600 text-white hover:bg-blue-700 transform hover:scale-105' 
                      : 'bg-gray-100 text-gray-400 cursor-not-allowed' ?>">
            <i class="fas fa-chevron-left mr-2"></i>Previous
          </a>
          
          <a href="<?= $current_page < $total_pages ? get_pagination_url($current_page + 1, $filter_rating, $filter_status, $search_term) : '#' ?>"
             class="px-4 py-2 rounded-xl font-medium transition-all duration-300 
                    <?= $current_page < $total_pages 
                      ? 'bg-blue-600 text-white hover:bg-blue-700 transform hover:scale-105' 
                      : 'bg-gray-100 text-gray-400 cursor-not-allowed' ?>">
            Next<i class="fas fa-chevron-right ml-2"></i>
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php include 'includes/support_footer.php'; ?>

  <script>
    // Add interactive effects
    document.addEventListener('DOMContentLoaded', function() {
      // Add hover effects to review cards
      const reviewCards = document.querySelectorAll('.review-card');
      reviewCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
          this.style.transform = 'translateY(-6px)';
          this.style.boxShadow = '0 12px 20px -4px rgba(37, 99, 235, 0.25)';
        });
        
        card.addEventListener('mouseleave', function() {
          this.style.transform = 'translateY(0)';
          this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.06)';
        });
      });
    });
  </script>
</body>
</html>