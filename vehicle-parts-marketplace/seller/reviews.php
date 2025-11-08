<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has approved seller role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('seller', $roles) || $_SESSION['role_status'] !== 'approved') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['name']);

// Handle report action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['review_id'])) {
    $review_id = (int)$_POST['review_id'];
    $action = $_POST['action'];
    
    if ($action === 'report') {
        try {
            // Update review status to 'reported'
            $stmt = $pdo->prepare("UPDATE reviews SET status = 'reported' WHERE id = ?");
            $success = $stmt->execute([$review_id]);
            
            if ($success) {
                $_SESSION['success'] = "Review has been reported and will be reviewed by support.";
            } else {
                $_SESSION['error'] = "Failed to report review.";
            }
        } catch (Exception $e) {
            error_log("Failed to report review: " . $e->getMessage());
            $_SESSION['error'] = "Failed to report review. Please try again.";
        }
        
        // Redirect back to prevent form resubmission
        header("Location: reviews.php");
        exit();
    }
}

// Fetch ALL parts sold by this seller (with category and image)
$parts = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name as part_name, c.name as category_name, p.image_url
        FROM parts p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.seller_id = ? AND p.status = 'active'
        ORDER BY p.name
    ");
    $stmt->execute([$user_id]);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch parts: " . $e->getMessage());
}

// Initialize reviews_by_part with ALL parts (even those with 0 reviews)
$reviews_by_part = [];
foreach ($parts as $part) {
    $reviews_by_part[$part['id']] = [
        'part' => $part['part_name'],
        'category' => $part['category_name'] ?? 'Uncategorized',
        'image_url' => $part['image_url'] ?? null,
        'reviews' => [],
        'avg_rating' => 0,
        'review_count' => 0,
        'active_review_count' => 0
    ];
}

// Fetch reviews for seller's parts (including all statuses for reporting)
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.rating, r.comment, r.created_at, r.status, 
               u.name as buyer_name, p.name as part_name, p.id as part_id
        FROM reviews r
        JOIN parts p ON r.part_id = p.id
        JOIN users u ON r.buyer_id = u.id
        WHERE p.seller_id = ?
        ORDER BY p.name, r.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $all_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group reviews by part_id
    foreach ($all_reviews as $review) {
        $part_id = $review['part_id'];
        if (isset($reviews_by_part[$part_id])) {
            $reviews_by_part[$part_id]['reviews'][] = $review;
        }
    }

    // Calculate average rating and counts for each part
    foreach ($reviews_by_part as &$part_data) {
        $active_ratings = [];
        $all_ratings = [];
        
        foreach ($part_data['reviews'] as $review) {
            if ($review['status'] === 'active') {
                $active_ratings[] = $review['rating'];
            }
            $all_ratings[] = $review['rating'];
        }
        
        $part_data['avg_rating'] = count($active_ratings) > 0 ? round(array_sum($active_ratings) / count($active_ratings), 1) : 0;
        $part_data['review_count'] = count($all_ratings);
        $part_data['active_review_count'] = count($active_ratings);
    }
} catch (Exception $e) {
    error_log("Failed to fetch reviews: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reviews - Seller Dashboard</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    .part-card {
      transition: all 0.3s ease;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    }

    .part-card:hover {
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

    .review-content {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.4s ease-out;
    }
    
    .review-content.expanded {
      max-height: 2000px;
      transition: max-height 0.5s ease-in;
    }

    .review-toggle-btn {
      transition: transform 0.3s ease;
    }
    
    .review-toggle-btn.expanded {
      transform: rotate(180deg);
    }

    .status-badge {
      font-size: 0.7rem;
      padding: 0.2rem 0.5rem;
    }
  </style>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/seller_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Product Reviews</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Manage customer feedback for your parts. Report inappropriate reviews.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-4 py-8">
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
        <div class="flex items-center">
          <i class="fas fa-check-circle mr-2"></i>
          <span><?= htmlspecialchars($_SESSION['success']) ?></span>
        </div>
      </div>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
        <div class="flex items-center">
          <i class="fas fa-exclamation-circle mr-2"></i>
          <span><?= htmlspecialchars($_SESSION['error']) ?></span>
        </div>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Results Info -->
    <div class="flex justify-between items-center mb-6">
      <div class="text-gray-600">
        <?php if (empty($parts)): ?>
          <p class="text-lg">No products listed.</p>
        <?php else: ?>
          <p class="font-medium text-gray-800">
            Showing <span class="text-blue-600 font-semibold"><?= count($parts) ?></span> product<?= count($parts) !== 1 ? 's' : '' ?>
            with <span class="text-blue-600 font-semibold"><?= array_sum(array_column($reviews_by_part, 'review_count')) ?></span> total review<?= array_sum(array_column($reviews_by_part, 'review_count')) !== 1 ? 's' : '' ?>
          </p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Reviews Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php if (empty($parts)): ?>
        <div class="col-span-full text-center py-16 bg-white rounded-2xl shadow-sm border-dashed border-gray-300">
          <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
          <h3 class="text-xl font-semibold text-gray-600 mb-2">No products listed</h3>
          <p class="text-gray-500 max-w-md mx-auto">You haven't listed any parts for sale yet.</p>
        </div>
      <?php else: ?>
        <?php foreach ($reviews_by_part as $part_id => $part_data): ?>
          <div class="part-card bg-white rounded-2xl overflow-hidden shadow-sm border">
            <!-- Image -->
            <div class="relative h-48 bg-gray-50 overflow-hidden">
              <?php if ($part_data['image_url']): ?>
                <img src="<?= htmlspecialchars($part_data['image_url']) ?>" 
                     alt="<?= htmlspecialchars($part_data['part']) ?>"
                     class="w-full h-full object-cover">
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center bg-gray-100">
                  <i class="fas fa-cog text-gray-400 text-4xl"></i>
                </div>
              <?php endif; ?>
              
              <!-- Review Count Badge -->
              <div class="absolute top-4 left-4">
                <span class="bg-blue-600 text-white text-xs px-3 py-1.5 rounded-full font-semibold shadow-lg">
                  <?= $part_data['active_review_count'] ?> active review<?= $part_data['active_review_count'] !== 1 ? 's' : '' ?>
                </span>
              </div>

              <!-- Reported Reviews Badge -->
              <?php if ($part_data['review_count'] > $part_data['active_review_count']): ?>
                <div class="absolute top-4 right-4">
                  <span class="bg-red-600 text-white text-xs px-3 py-1.5 rounded-full font-semibold shadow-lg">
                    <?= $part_data['review_count'] - $part_data['active_review_count'] ?> reported
                  </span>
                </div>
              <?php endif; ?>
            </div>

            <!-- Info -->
            <div class="p-5">
              <div class="flex justify-between items-start mb-3">
                <h3 class="font-semibold text-gray-800 text-base cursor-pointer hover:text-blue-600 line-clamp-1">
                  <?= htmlspecialchars($part_data['part']) ?>
                </h3>
              </div>

              <!-- Rating -->
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center">
                  <div class="flex items-center">
                    <?php if ($part_data['active_review_count'] > 0): ?>
                      <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star <?= $i <= $part_data['avg_rating'] ? 'text-yellow-400' : 'text-gray-300' ?> text-sm"></i>
                      <?php endfor; ?>
                      <span class="ml-1 text-xs text-gray-600">(<?= number_format($part_data['avg_rating'], 1) ?>)</span>
                    <?php else: ?>
                      <span class="text-xs text-gray-500">No active reviews</span>
                    <?php endif; ?>
                  </div>
                </div>
                
                <!-- Toggle Reviews Button -->
                <?php if ($part_data['review_count'] > 0): ?>
                  <button onclick="toggleReviews(<?= $part_id ?>)"
                          class="text-blue-600 hover:text-blue-800 transition duration-150 ease-in-out p-2 rounded-full hover:bg-blue-100 review-toggle-btn"
                          id="toggle-icon-<?= $part_id ?>"
                          title="Toggle Reviews">
                    <i class="fas fa-chevron-down text-sm"></i>
                  </button>
                <?php endif; ?>
              </div>

              <!-- Category -->
              <span class="inline-block capitalize text-xs font-semibold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full mb-3">
                <?= htmlspecialchars($part_data['category']) ?>
              </span>

              <!-- Review Count Summary -->
              <div class="flex justify-between text-sm text-gray-500 mb-4">
                <span class="<?= $part_data['active_review_count'] > 0 ? 'text-green-600' : 'text-gray-500' ?>">
                  <i class="fas fa-comment-alt mr-1"></i> 
                  <?= $part_data['active_review_count'] ?> active review<?= $part_data['active_review_count'] !== 1 ? 's' : '' ?>
                </span>
                <?php if ($part_data['review_count'] > $part_data['active_review_count']): ?>
                  <span class="text-red-600">
                    <i class="fas fa-flag mr-1"></i>
                    <?= $part_data['review_count'] - $part_data['active_review_count'] ?> reported
                  </span>
                <?php endif; ?>
              </div>

              <!-- Reviews Content (Collapsible) -->
              <?php if ($part_data['review_count'] > 0): ?>
                <div id="reviews-content-<?= $part_id ?>" class="review-content border-t border-gray-100 pt-4 mt-4">
                  <div class="space-y-4">
                    <?php foreach ($part_data['reviews'] as $review): ?>
                      <div class="border-l-4 
                        <?php 
                          switch($review['status']) {
                            case 'active': echo 'border-blue-500 bg-blue-50'; break;
                            case 'reported': echo 'border-yellow-500 bg-yellow-50'; break;
                            case 'hidden': echo 'border-red-500 bg-red-50'; break;
                            default: echo 'border-gray-500 bg-gray-50';
                          }
                        ?> 
                        pl-4 py-3 rounded-lg">
                        
                        <!-- Review Header -->
                        <div class="flex items-center justify-between mb-2">
                          <div class="flex items-center">
                            <div class="w-7 h-7 
                              <?php 
                                switch($review['status']) {
                                  case 'active': echo 'bg-blue-100 text-blue-800'; break;
                                  case 'reported': echo 'bg-yellow-100 text-yellow-800'; break;
                                  case 'hidden': echo 'bg-red-100 text-red-800'; break;
                                  default: echo 'bg-gray-100 text-gray-800';
                                }
                              ?> 
                              rounded-full flex items-center justify-center text-xs font-medium mr-2">
                              <?= strtoupper(substr($review['buyer_name'], 0, 1)) ?>
                            </div>
                            <div>
                              <span class="font-medium text-gray-800 text-sm"><?= htmlspecialchars($review['buyer_name']) ?></span>
                              <span class="ml-2 status-badge rounded-full 
                                <?php 
                                  switch($review['status']) {
                                    case 'active': echo 'bg-green-100 text-green-800'; break;
                                    case 'reported': echo 'bg-yellow-100 text-yellow-800'; break;
                                    case 'hidden': echo 'bg-red-100 text-red-800'; break;
                                    default: echo 'bg-gray-100 text-gray-800';
                                  }
                                ?>">
                                <?= ucfirst($review['status']) ?>
                              </span>
                            </div>
                          </div>
                          <div class="flex items-center">
                            <span class="text-sm font-bold text-gray-700 mr-1"><?= $review['rating'] ?>.0</span>
                            <div class="flex">
                              <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star text-xs <?= $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                              <?php endfor; ?>
                            </div>
                          </div>
                        </div>

                        <!-- Review Comment -->
                        <p class="text-gray-700 text-sm mb-3 leading-relaxed"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>

                        <!-- Review Footer -->
                        <div class="flex justify-between items-center">
                          <div class="text-xs text-gray-500">
                            Reviewed on <?= date('M j, Y', strtotime($review['created_at'])) ?>
                          </div>
                          
                          <!-- Report Button (only for active reviews) -->
                          <?php if ($review['status'] === 'active'): ?>
                            <form method="POST" action="reviews.php" 
                                  onsubmit="return confirm('Are you sure you want to report this review? It will be hidden until support reviews it.');"
                                  class="inline-block">
                              <input type="hidden" name="review_id" value="<?= $review['id'] ?>">
                              <input type="hidden" name="action" value="report">
                              <button type="submit" 
                                      class="text-red-600 hover:text-red-800 transition duration-150 ease-in-out px-3 py-1 rounded-full hover:bg-red-100 text-xs font-medium"
                                      title="Report inappropriate review">
                                <i class="fas fa-flag mr-1"></i>Report
                              </button>
                            </form>
                          <?php elseif ($review['status'] === 'reported'): ?>
                            <span class="text-yellow-600 text-xs font-medium">
                              <i class="fas fa-clock mr-1"></i>Under Review
                            </span>
                          <?php elseif ($review['status'] === 'hidden'): ?>
                            <span class="text-red-600 text-xs font-medium">
                              <i class="fas fa-ban mr-1"></i>Removed
                            </span>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php include 'includes/seller_footer.php'; ?>

  <script>
    function toggleReviews(partId) {
      const content = document.getElementById('reviews-content-' + partId);
      const icon = document.getElementById('toggle-icon-' + partId);

      if (content.classList.contains('expanded')) {
        content.classList.remove('expanded');
        icon.classList.remove('expanded');
      } else {
        content.classList.add('expanded');
        icon.classList.add('expanded');
      }
    }

    // Add hover effects to review cards
    document.addEventListener('DOMContentLoaded', function() {
      const partCards = document.querySelectorAll('.part-card');
      partCards.forEach(card => {
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