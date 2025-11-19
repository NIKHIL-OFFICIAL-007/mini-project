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

$user_id = $_GET['id'] ?? null;

// Prevent self-deletion
if ($user_id == $_SESSION['user_id']) {
    header("Location: manage_users.php?error=delete_self");
    exit();
}

if (!$user_id) {
    header("Location: manage_users.php");
    exit();
}

// Confirm deletion
if ($_POST && $_POST['confirm'] === 'yes') {
    try {
        $pdo->beginTransaction();

        // Delete related records first
        $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$user_id]);

        // Delete user
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Delete failed: " . $e->getMessage());
    }

    header("Location: manage_users.php?message=user_deleted");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Delete User - Admin Panel</title>

  <!-- ✅ Correct Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body class="bg-gray-50 text-gray-900">

  <?php include 'includes/admin_header.php'; ?>

  <!-- Confirmation -->
  <div class="max-w-2xl mx-auto my-8 p-6 bg-white rounded-xl shadow-md">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Confirm Deletion</h2>
    <p class="text-gray-600 mb-6">Are you sure you want to delete this user? This action cannot be undone. All data will be permanently removed.</p>

    <form method="POST" class="flex justify-end space-x-4">
      <a href="manage_users.php" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 transition">
        Cancel
      </a>
      <button type="submit" name="confirm" value="yes" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition">
        Delete User
      </button>
    </form>
  </div>

  <?php include 'includes/admin_footer.php'; ?>
</body>
</html>