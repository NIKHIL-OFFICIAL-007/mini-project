<?php
session_start();
include '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
try {
    $stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: ../logout.php");
        exit();
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("Database error occurred. Please try again later.");
}

// Prevent if already admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_roles = $stmt->fetch();
if ($user_roles) {
    $roles = array_filter(array_map('trim', explode(',', $user_roles['role'])));
    if (in_array('admin', $roles)) {
        header("Location: my_requests.php?message=already_admin");
        exit();
    }
}

// Check for existing pending application
$stmt = $pdo->prepare("SELECT id FROM admin_applications WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
if ($stmt->fetch()) {
    header("Location: my_requests.php?message=pending_admin_application_exists");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $reason = trim($_POST['reason']);
    $experience = trim($_POST['experience'] ?? '');
    // Removed: $additional_info = trim($_POST['additional_info'] ?? '');

    // Validate required fields
    if (empty($name) || empty($email) || empty($reason)) {
        header("Location: apply_admin.php?error=missing_required_fields");
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_applications 
            (user_id, name, email, phone, reason, experience, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $result = $stmt->execute([
            $user_id,
            $name,
            $email,
            $phone,
            $reason,
            $experience
            // Removed: $additional_info
        ]);
        
        if ($result) {
            // Add notification
            $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, 'Your admin role application has been submitted.', 'info')")
                ->execute([$user_id]);

            header("Location: my_requests.php?message=admin_application_submitted");
            exit();
        } else {
            throw new Exception("Database insert failed");
        }
    } catch (Exception $e) {
        error_log("Admin application failed: " . $e->getMessage());
        header("Location: apply_admin.php?error=application_failed");
        exit();
    }
}

// Extract user data with proper fallbacks
$name_value = htmlspecialchars($user['name'] ?? '');
$email_value = htmlspecialchars($user['email'] ?? '');
$phone_value = htmlspecialchars($user['phone'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Apply for Admin Role - AutoParts Hub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f9fafb;
      color: #1f2937;
    }
    
    .form-section {
      padding: 32px;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      margin-bottom: 24px;
      background-color: white;
    }
    
    .form-label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #1f2937;
    }
    
    .form-input, .form-textarea {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 16px;
      transition: border-color 0.2s ease;
    }
    
    .form-input:focus, .form-textarea:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }
    
    .form-textarea {
      min-height: 100px;
      resize: vertical;
    }
    
    .submit-btn {
      background-color: #3b82f6;
      color: white;
      border: none;
      padding: 16px 32px;
      font-size: 16px;
      font-weight: 600;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s ease;
      width: 100%;
      margin-top: 16px;
    }
    
    .submit-btn:hover {
      background-color: #2563eb;
    }
    
    .warning-banner {
      background-color: #fffbeb;
      border-left: 4px solid #f59e0b;
      padding: 16px;
      border-radius: 8px;
      margin-bottom: 24px;
    }
  </style>
</head>
<body class="min-h-screen bg-gray-50">
  <?php include '../includes/header.php'; ?>

  <div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="text-center mb-10">
      <h1 class="text-4xl font-bold text-gray-900 mb-4">Apply for Admin Role</h1>
      <p class="text-xl text-gray-600 max-w-2xl mx-auto">
        Admin access is granted only to highly trusted contributors. Please provide a detailed justification.
      </p>
    </div>

    <div class="warning-banner">
      <div class="flex">
        <i class="fas fa-exclamation-triangle text-yellow-500 mt-0.5 mr-3"></i>
        <div>
          <p class="text-sm font-medium text-yellow-800">Important Notice</p>
          <p class="text-sm text-yellow-700 mt-1">
            Admin privileges include full access to user data, orders, and system settings. 
            Applications are reviewed manually and rarely approved. Only apply if you have been 
            explicitly invited or have made significant contributions to the platform.
          </p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow-lg p-8">
      <form method="POST" class="space-y-8">
        <div class="form-section">
          <h2 class="text-2xl font-bold mb-6">Personal Information</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="form-label">Full Name *</label>
              <input type="text" name="name" required class="form-input" value="<?= $name_value ?>">
            </div>
            
            <div>
              <label class="form-label">Email *</label>
              <input type="email" name="email" required class="form-input" value="<?= $email_value ?>">
            </div>
            
            <div>
              <label class="form-label">Phone Number</label>
              <input type="tel" name="phone" class="form-input" value="<?= $phone_value ?>">
            </div>
          </div>
        </div>

        <div class="form-section">
          <h2 class="text-2xl font-bold mb-6">Application Details</h2>
          
          <div class="mb-6">
            <label class="form-label">Why should you be granted admin access? *</label>
            <textarea name="reason" required class="form-textarea" placeholder="Explain your relationship with the platform, trustworthiness, technical understanding, and why you need admin rights..."><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
          </div>
          
          <div class="mb-6">
            <label class="form-label">Relevant Experience</label>
            <textarea name="experience" class="form-textarea" placeholder="Previous admin/moderation experience, technical skills, contributions to the community..."><?= htmlspecialchars($_POST['experience'] ?? '') ?></textarea>
          </div>
          
          </div>

        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg">
          <p class="text-sm text-blue-800">
            <strong>Review Time:</strong> Admin applications are reviewed within 7–14 business days. 
            You will be notified via email and in-app notification.
          </p>
        </div>

        <div class="pt-6">
          <button type="submit" class="submit-btn">
            <i class="fas fa-paper-plane mr-2"></i> Submit Admin Application
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php include '../includes/footer.php'; ?>
</body>
</html>