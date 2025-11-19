<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'includes/config.php';

// Fetch notifications
try {
    $stmt = $pdo->prepare("
        SELECT message, created_at, is_read 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch notifications: " . $e->getMessage());
    $notifications = [];
}

// Mark all unread notifications as read
try {
    $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE")
        ->execute([$_SESSION['user_id']]);
} catch (Exception $e) {
    error_log("Failed to mark notifications as read: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Notifications | AutoParts Hub</title>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500&display=swap" rel="stylesheet">

  <style>
    body { 
      font-family: 'Inter', sans-serif; 
      background-color: #f8fafc;
    }
  </style>
</head>
<body class="bg-gray-50 min-h-screen">

  <div class="container mx-auto px-4 py-10 max-w-2xl">

    <!-- Page Header -->
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Your Notifications</h2>

    <!-- No Notifications -->
    <?php if (empty($notifications)): ?>
      <div class="text-center py-10 bg-white rounded-lg shadow">
        <i class="fas fa-bell-slash text-5xl text-gray-300 mb-4"></i>
        <p class="text-gray-500">You don't have any notifications yet.</p>
      </div>
    <?php else: ?>
      <!-- Notifications List -->
      <div class="space-y-3">
        <?php foreach ($notifications as $n): ?>
          <div class="p-4 bg-white rounded-lg shadow-sm border-l-4 
            <?= $n['is_read'] ? 'border-gray-300' : 'border-blue-500 bg-blue-50' ?>">
            <div class="flex justify-between items-start">
              <div class="text-sm text-gray-800"><?= htmlspecialchars($n['message']) ?></div>
              <div class="text-xs text-gray-500 ml-4 whitespace-nowrap">
                <?= date('M j, H:i', strtotime($n['created_at'])) ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Back Link -->
    <div class="mt-8">
      <a href="index.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 hover:underline text-sm font-medium">
        <i class="fas fa-arrow-left mr-1 text-xs"></i> Back to Home
      </a>
    </div>

  </div>

</body>
</html>