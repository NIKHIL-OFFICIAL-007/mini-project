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

// Fetch pending complaints
$complaints = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.subject, c.message, u.name as user_name, c.status, c.created_at
        FROM complaints c
        JOIN users u ON c.user_id = u.id
        WHERE c.status = 'pending'
        ORDER BY c.created_at DESC
    ");
    $stmt->execute();
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch complaints: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Complaints - Support Dashboard</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/support_header.php'; ?>

  <!-- Page Header -->
  <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
    <div class="container mx-auto px-6 text-center">
      <h1 class="text-4xl md:text-5xl font-bold mb-4">Complaints</h1>
      <p class="text-blue-100 max-w-2xl mx-auto text-lg">Handle customer complaints and resolve issues.</p>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto px-6 py-8">
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
      <div class="p-6 border-b">
        <h2 class="text-xl font-bold text-gray-800">Pending Complaints</h2>
        <p class="text-gray-600 mt-1">Review and resolve customer complaints.</p>
      </div>
      
      <div class="p-6">
        <?php if (empty($complaints)): ?>
          <div class="text-center py-12">
            <i class="fas fa-exclamation-circle text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-500">No complaints pending</h3>
            <p class="text-gray-400 mt-2">All complaints have been resolved.</p>
          </div>
        <?php else: ?>
          <div class="space-y-6">
            <?php foreach ($complaints as $complaint): ?>
              <div class="border border-gray-200 rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                  <div class="flex items-center">
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center text-red-600 font-semibold">
                      <?= substr($complaint['user_name'], 0, 1) ?>
                    </div>
                    <div class="ml-3">
                      <div class="font-medium text-gray-800"><?= htmlspecialchars($complaint['user_name']) ?></div>
                      <div class="text-sm text-gray-500">Submitted on <?= date('M j, Y', strtotime($complaint['created_at'])) ?></div>
                    </div>
                  </div>
                  <div class="flex items-center">
                    <span class="capitalize px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">
                      Pending
                    </span>
                  </div>
                </div>
                
                <h3 class="font-semibold text-gray-800 mb-2"><?= htmlspecialchars($complaint['subject']) ?></h3>
                <p class="text-gray-700 mb-4"><?= htmlspecialchars($complaint['message']) ?></p>
                
                <div class="flex space-x-3">
                  <a href="resolve_complaint.php?id=<?= $complaint['id'] ?>" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition flex items-center justify-center">
                    <i class="fas fa-check mr-1"></i> Resolve
                  </a>
                  <a href="view_complaint.php?id=<?= $complaint['id'] ?>" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition flex items-center justify-center">
                    <i class="fas fa-eye mr-1"></i> View Details
                  </a>
                </div>
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