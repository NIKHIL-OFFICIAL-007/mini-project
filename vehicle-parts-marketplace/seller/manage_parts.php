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

// Fetch seller's parts with category names
$parts = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, c.name as category_name, p.price, p.stock_quantity as stock, 
               p.description, p.image_url, p.created_at
        FROM parts p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.seller_id = ? AND p.status = 'active'
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $parts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch parts: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Parts - Seller Dashboard</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/seller_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Manage Parts</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Edit or delete your listed parts here.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-6 py-8">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php if (empty($parts)): ?>
        <div class="col-span-full text-center py-12">
          <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
          <h3 class="text-xl font-medium text-gray-500">No parts listed yet</h3>
          <p class="text-gray-400 mt-2">Start by adding your first part to the marketplace.</p>
          <a href="add_part.php" class="inline-block mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            Add Your First Part
          </a>
        </div>
      <?php else: ?>
        <?php foreach ($parts as $part): ?>
          <div class="bg-white rounded-xl overflow-hidden shadow-md hover:shadow-lg transition transform hover:-translate-y-1">
            <!-- Image -->
            <div class="relative h-48 bg-gray-100">
              <?php if ($part['image_url']): ?>
                <img src="<?= htmlspecialchars($part['image_url']) ?>" alt="<?= htmlspecialchars($part['name']) ?>"
                     class="w-full h-full object-cover transition-transform duration-300 hover:scale-105">
              <?php else: ?>
                <div class="w-full h-full flex items-center justify-center bg-gray-200">
                  <i class="fas fa-cog text-gray-400 text-4xl"></i>
                </div>
              <?php endif; ?>
              
              <!-- Price Tag (Updated to ₹) -->
              <div class="absolute top-4 left-4 bg-blue-600 text-white px-3 py-1 rounded-full text-sm font-bold">
                ₹<?= number_format($part['price'], 0) ?>
              </div>
            </div>
            
            <!-- Info -->
            <div class="p-5">
              <h3 class="font-semibold text-gray-800 mb-1"><?= htmlspecialchars($part['name']) ?></h3>
              <span class="capitalize text-sm text-blue-600 mb-2"><?= htmlspecialchars($part['category_name'] ?? 'Uncategorized') ?></span>
              <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($part['description']) ?></p>
              
              <div class="flex items-center justify-between mb-4">
                <div class="text-sm text-gray-500">
                  <i class="fas fa-calendar-alt mr-1"></i> <?= date('M j, Y', strtotime($part['created_at'])) ?>
                </div>
                <div class="text-sm <?= $part['stock'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                  <i class="fas fa-boxes mr-1"></i> <?= $part['stock'] ?> in stock
                </div>
              </div>
              
              <div class="flex space-x-2">
                <a href="edit_part.php?id=<?= $part['id'] ?>" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition flex items-center justify-center">
                  <i class="fas fa-edit mr-1"></i> Edit
                </a>
                <a href="delete_part.php?id=<?= $part['id'] ?>" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition flex items-center justify-center"
                   onclick="return confirm('Are you sure you want to delete this part? This cannot be undone.')">
                  <i class="fas fa-trash mr-1"></i> Delete
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php include 'includes/seller_footer.php'; ?>
</body>
</html>