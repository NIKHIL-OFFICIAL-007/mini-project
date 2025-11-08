<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has approved support role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('support', $roles) || $_SESSION['role_status'] !== 'approved') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = htmlspecialchars($_SESSION['name']);

// Fetch published articles
$articles = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, title, content, category, created_at
        FROM knowledge_base
        WHERE is_published = 1
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch articles: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Knowledge Base - Support Dashboard</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/support_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Knowledge Base</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Browse helpful articles and guides for customers.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-6 py-8">
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
      <div class="p-6 border-b">
        <h2 class="text-xl font-bold text-gray-800">Articles</h2>
        <p class="text-gray-600 mt-1">Helpful guides and FAQs for customers.</p>
      </div>
      
      <div class="p-6">
        <?php if (empty($articles)): ?>
          <div class="text-center py-12">
            <i class="fas fa-book text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-500">No articles available</h3>
            <p class="text-gray-400 mt-2">Check back later for new articles.</p>
          </div>
        <?php else: ?>
          <div class="space-y-6">
            <?php foreach ($articles as $article): ?>
              <div class="border border-gray-200 rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                  <div class="flex items-center">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 font-semibold">
                      <?= substr($article['title'], 0, 1) ?>
                    </div>
                    <div class="ml-3">
                      <div class="font-medium text-gray-800"><?= htmlspecialchars($article['title']) ?></div>
                      <div class="text-sm text-gray-500">Published on <?= date('M j, Y', strtotime($article['created_at'])) ?></div>
                    </div>
                  </div>
                  <?php if ($article['category']): ?>
                    <span class="capitalize px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                      <?= htmlspecialchars($article['category']) ?>
                    </span>
                  <?php endif; ?>
                </div>
                
                <p class="text-gray-700 mb-4 line-clamp-3"><?= htmlspecialchars($article['content']) ?></p>
                
                <a href="view_article.php?id=<?= $article['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm">
                  Read More →
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include 'includes/support_footer.php'; ?>
</body>
</html>