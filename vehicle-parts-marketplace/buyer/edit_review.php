<?php
session_start();
include '../includes/config.php';

// ✅ Check if user is logged in and has buyer role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$roles = explode(',', $_SESSION['role']);
if (!in_array('buyer', $roles)) {
    header("Location: ../login.php");
    exit();
}

$review_id = $_GET['id'] ?? null;
if (!$review_id) {
    header("Location: ../browse_parts.php");
    exit();
}

try {
    // Fetch review with part info
    $stmt = $pdo->prepare("
        SELECT r.id, r.part_id, r.rating, r.comment, r.created_at, p.name as part_name
        FROM reviews r
        JOIN parts p ON r.part_id = p.id
        WHERE r.id = ? AND r.buyer_id = ? AND r.status = 'active'
    ");
    $stmt->execute([$review_id, $user_id]);
    $review = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$review) {
        header("Location: ../view_part.php?id=" . urlencode($review['part_id']));
        exit();
    }
} catch (Exception $e) {
    error_log("Failed to fetch review: " . $e->getMessage());
    header("Location: ../view_part.php?id=" . urlencode($review['part_id']));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Review - AutoParts Hub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include '../includes/header.php'; ?>

  <div class="container mx-auto px-6 py-8">
    <h1 class="text-3xl font-bold mb-6">Edit Your Review</h1>
    
    <form method="POST" action="update_review.php" class="bg-white p-6 rounded-lg shadow-md max-w-2xl mx-auto">
      <input type="hidden" name="review_id" value="<?= $review['id'] ?>">

      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">Your Rating *</label>
        <div class="flex space-x-1">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <label class="cursor-pointer relative" data-rating="<?= $i ?>">
              <input type="radio" name="rating" value="<?= $i ?>" 
                     class="sr-only" 
                     <?= $review['rating'] == $i ? 'checked' : '' ?>>
              <i class="fas fa-star text-2xl text-<?= $review['rating'] >= $i ? 'yellow-400' : 'gray-300' ?>"></i>
            </label>
          <?php endfor; ?>
        </div>
      </div>

      <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-1">Your Review</label>
        <textarea name="comment" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                  placeholder="Share your thoughts..." required><?= htmlspecialchars($review['comment']) ?></textarea>
      </div>

      <button type="submit" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
        Save Changes
      </button>
    </form>
  </div>

  <?php include '../includes/footer.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const stars = document.querySelectorAll('.relative');
      const ratingInput = document.querySelector('input[name="rating"]');

      stars.forEach(star => {
        star.addEventListener('mouseover', function () {
          const rating = this.getAttribute('data-rating');
          stars.forEach(s => {
            if (s.getAttribute('data-rating') <= rating) {
              s.querySelector('i').style.color = '#f59e0b';
            } else {
              s.querySelector('i').style.color = '#d1d5db';
            }
          });
        });

        star.addEventListener('mouseout', function () {
          stars.forEach(s => {
            if (s.querySelector('i').style.color === '#f59e0b') {
              s.querySelector('i').style.color = '#f59e0b';
            } else {
              s.querySelector('i').style.color = '#d1d5db';
            }
          });
        });

        star.addEventListener('click', function () {
          const rating = this.getAttribute('data-rating');
          ratingInput.value = rating;

          stars.forEach(s => {
            if (s.getAttribute('data-rating') <= rating) {
              s.querySelector('i').style.color = '#f59e0b';
            } else {
              s.querySelector('i').style.color = '#d1d5db';
            }
          });
        });
      });
    });
  </script>
</body>
</html>