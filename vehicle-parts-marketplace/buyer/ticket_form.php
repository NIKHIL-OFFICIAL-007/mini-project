<?php
session_start();
include 'includes/config.php';

// ✅ Check if user is logged in and has buyer role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

$roles = explode(',', $_SESSION['role']);
if (!in_array('buyer', $roles)) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $category = $_POST['category'] ?? 'other';
    $priority = $_POST['priority'] ?? 'medium';

    // Validate required fields
    if (empty($subject)) {
        $error = "Subject is required.";
    } elseif (strlen($subject) < 3) {
        $error = "Subject must be at least 3 characters long.";
    } elseif (empty($message)) {
        $error = "Message is required.";
    } elseif (strlen($message) < 10) {
        $error = "Message must be at least 10 characters long.";
    } else {
        try {
            // Insert ticket with sender_role = 'buyer'
            $stmt = $pdo->prepare("
                INSERT INTO tickets 
                (user_id, sender_role, subject, message, category, status, priority, created_at, updated_at)
                VALUES (?, 'buyer', ?, ?, ?, 'open', ?, NOW(), NOW())
            ");
            $result = $stmt->execute([$user_id, $subject, $message, $category, $priority]);
            
            if ($result && $stmt->rowCount() > 0) {
                $_SESSION['success'] = "Your support ticket has been submitted successfully. We'll get back to you soon!";
                header("Location: my_tickets.php");
                exit();
            } else {
                $error = "Failed to submit ticket. Please try again.";
            }
        } catch (PDOException $e) {
            error_log("Ticket submission DB error: " . $e->getMessage());
            $error = "A database error occurred. Please try again later.";
        } catch (Exception $e) {
            error_log("Ticket submission error: " . $e->getMessage());
            $error = "An unexpected error occurred. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Open Support Ticket - AutoParts Hub</title>

    <!-- ✅ Correct Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        .form-input, .form-select, .form-textarea {
            @apply w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none;
        }
        .form-label {
            @apply block text-sm font-medium text-gray-700 mb-2;
        }
        .form-group {
            @apply mb-6;
        }
        .form-row {
            @apply flex flex-col md:flex-row gap-4;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">

    <?php include 'includes/buyer_header.php'; ?>

    <!-- Page Header -->
    <div class="py-12 bg-gradient-to-r from-blue-600 to-blue-800 text-white">
        <div class="container mx-auto px-6 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">Open a Support Ticket</h1>
            <p class="text-blue-100 max-w-2xl mx-auto text-lg">Let us know how we can help you. Our support team will respond promptly.</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-6 py-8">
        <!-- Error Message -->
        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center animate-fade-in">
                <i class="fas fa-exclamation-circle mr-2"></i> 
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Success Message -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center animate-fade-in">
                <i class="fas fa-check-circle mr-2"></i> 
                <?= htmlspecialchars($_SESSION['success']) ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-md p-6 max-w-2xl mx-auto transition-all duration-300 hover:shadow-lg">
      <form method="POST">
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
          <input type="text" name="subject" 
                 class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none"
                 value="<?= htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES) ?>" required>
        </div>

        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
          <select name="category" 
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
            <option value="order" <?= (($_POST['category'] ?? '') == 'order') ? 'selected' : '' ?>>Order Issue</option>
            <option value="payment" <?= (($_POST['category'] ?? '') == 'payment') ? 'selected' : '' ?>>Payment Problem</option>
            <option value="account" <?= (($_POST['category'] ?? '') == 'account') ? 'selected' : '' ?>>Account Help</option>
            <option value="technical" <?= (($_POST['category'] ?? '') == 'technical') ? 'selected' : '' ?>>Technical Issue</option>
            <option value="other" <?= (($_POST['category'] ?? '') == 'other') ? 'selected' : '' ?>>Other</option>
          </select>
        </div>

        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
          <select name="priority" 
                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
            <option value="low" <?= (($_POST['priority'] ?? 'medium') == 'low') ? 'selected' : '' ?>>Low</option>
            <option value="medium" <?= (($_POST['priority'] ?? 'medium') == 'medium') ? 'selected' : '' ?>>Medium</option>
            <option value="high" <?= (($_POST['priority'] ?? 'medium') == 'high') ? 'selected' : '' ?>>High</option>
            <option value="urgent" <?= (($_POST['priority'] ?? 'medium') == 'urgent') ? 'selected' : '' ?>>Urgent</option>
          </select>
        </div>

        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Message</label>
          <textarea name="message" rows="6" 
                    class="w-full border border-gray-300 rounded-lg p-3" 
                    required><?= htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES) ?></textarea>
        </div>

        <div class="flex space-x-4">
          <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition font-medium">
            Submit Ticket
          </button>
          <a href="dashboard.php" class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg transition font-medium">
            Cancel
          </a>
        </div>
      </form>
        </div>
    </div>

    <?php include 'includes/buyer_footer.php'; ?>
</body>
</html>