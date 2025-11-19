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

$seller_id = $_SESSION['user_id'];

// Get all reviews for seller's parts
$reviews = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.rating, r.comment, r.created_at, r.status,
               u.name as buyer_name, p.name as part_name, p.id as part_id
        FROM reviews r
        JOIN parts p ON r.part_id = p.id
        JOIN users u ON r.buyer_id = u.id
        WHERE p.seller_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$seller_id]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch reviews: " . $e->getMessage());
    $reviews = [];
}

// Calculate average rating for seller
$avg_rating = 0;
$total_reviews = count($reviews);
if ($total_reviews > 0) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avg_rating = round($sum / $total_reviews, 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Reviews - Seller Dashboard</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/seller_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Manage Reviews</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">View and respond to customer feedback.</p>
    </div>
  </div>

  <!-- Stats Banner -->
  <div class="container mx-auto px-6 py-4 mb-8">
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-center justify-between">
      <div>
        <p class="text-sm text-blue-700">Your Overall Rating</p>
        <div class="flex items-center mt-1">
          <span class="text-3xl font-bold text-blue-600"><?= $avg_rating ?></span>
          <div class="flex ml-2">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <i class="fas fa-star <?= $i <= $avg_rating ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
            <?php endfor; ?>
          </div>
          <span class="ml-2 text-sm text-blue-600">(<?= $total_reviews ?> reviews)</span>
        </div>
      </div>
      <a href="manage_parts.php" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">
        Back to Parts
      </a>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-6 py-8">
    <?php if (empty($reviews)): ?>
      <div class="text-center py-12">
        <i class="fas fa-comment-dots text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-xl font-medium text-gray-500">No reviews yet</h3>
        <p class="text-gray-400 mt-2">Customers will leave reviews after purchasing your parts.</p>
      </div>
    <?php else: ?>
      <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-gray-700 uppercase text-xs">
            <tr>
              <th class="px-6 py-3 text-left">Buyer</th>
              <th class="px-6 py-3 text-left">Part</th>
              <th class="px-6 py-3 text-left">Rating</th>
              <th class="px-6 py-3 text-left">Comment</th>
              <th class="px-6 py-3 text-left">Date</th>
              <th class="px-6 py-3 text-left">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php foreach ($reviews as $review): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                  <div class="font-medium"><?= htmlspecialchars($review['buyer_name']) ?></div>
                </td>
                <td class="px-6 py-4">
                  <a href="../buyer/view_part.php?id=<?= $review['part_id'] ?>" class="text-blue-600 hover:underline">
                    <?= htmlspecialchars(substr($review['part_name'], 0, 30)) . (strlen($review['part_name']) > 30 ? '...' : '') ?>
                  </a>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                    <?php endfor; ?>
                    <span class="ml-1 text-sm">(<?= $review['rating'] ?>/5)</span>
                  </div>
                </td>
                <td class="px-6 py-4 max-w-xs truncate" title="<?= htmlspecialchars($review['comment']) ?>">
                  <?= htmlspecialchars($review['comment'] ?: 'No comment') ?>
                </td>
                <td class="px-6 py-4 text-gray-600"><?= date('M j, Y', strtotime($review['created_at'])) ?></td>
                <td class="px-6 py-4">
                  <span class="px-2 py-1 text-xs rounded-full
                    <?= $review['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                    <?= ucfirst($review['status']) ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <?php include 'includes/seller_footer.php'; ?>
</body>
</html>