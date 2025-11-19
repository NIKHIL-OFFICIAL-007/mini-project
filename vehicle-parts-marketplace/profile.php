<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'includes/config.php';

// Fetch user data
$stmt = $pdo->prepare("SELECT id, name, email, role, created_at, 
                       COALESCE(profile_picture, '') AS profile_picture,
                       COALESCE(phone, '') AS phone,
                       COALESCE(address, '') AS address 
                       FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

// Check seller application status
$seller_app_status = null;
if ($user['role'] === 'buyer') {
    $stmt = $pdo->prepare("SELECT status FROM seller_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $seller_app = $stmt->fetch();
    if ($seller_app) {
        $seller_app_status = $seller_app['status'];
    }
}

// Check if deletion already requested
$deletion_requested = false;
$stmt = $pdo->prepare("SELECT status FROM account_deletion_requests WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetch()) {
    $deletion_requested = true;
}

$message = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['action'])) {
    $name = trim($_POST['name']);
    $email = $user['email']; // Keep original email, not from form
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($name)) {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> Name is required.</div>";
    } elseif ($new_password && strlen($new_password) < 6) {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> New password must be at least 6 characters.</div>";
    } elseif ($new_password && $new_password !== $confirm_password) {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> Passwords do not match.</div>";
    } elseif ($phone && !preg_match('/^[6-9]\d{9}$/', $phone)) {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> Phone number must be 10 digits starting with 6-9.</div>";
    } else {
        if ($new_password) {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $hash = $stmt->fetchColumn();
            if (!password_verify($current_password, $hash)) {
                $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> Current password is incorrect.</div>";
            }
        }

        if (!$message) {
            try {
                if ($new_password) {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, password = ?, phone = ?, address = ? WHERE id = ?");
                    $stmt->execute([$name, $hashed, $phone, $address, $_SESSION['user_id']]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?");
                    $stmt->execute([$name, $phone, $address, $_SESSION['user_id']]);
                }

                $_SESSION['name'] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

                $message = "<div class='alert alert-success'><i class='fas fa-check-circle mr-2'></i> Profile updated successfully!</div>";

                $stmt = $pdo->prepare("SELECT id, name, email, role, created_at, 
                                       COALESCE(profile_picture, '') AS profile_picture,
                                       COALESCE(phone, '') AS phone,
                                       COALESCE(address, '') AS address 
                                       FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();

            } catch (Exception $e) {
                error_log("Profile update failed: " . $e->getMessage());
                $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> Update failed. Please try again.</div>";
            }
        }
    }
}

// Handle deletion request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_deletion') {
    if (!$deletion_requested) {
        try {
            $stmt = $pdo->prepare("INSERT INTO account_deletion_requests (user_id) VALUES (?)");
            $stmt->execute([$_SESSION['user_id']]);
            $message = "<div class='alert alert-success'><i class='fas fa-check-circle mr-2'></i> Account deletion request sent to admin. You'll be notified once processed.</div>";
            $deletion_requested = true;
        } catch (Exception $e) {
            error_log("Deletion request failed: " . $e->getMessage());
            $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> Failed to submit request. Please try again.</div>";
        }
    } else {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> You already have a pending deletion request.</div>";
    }
}

// Get user roles as array
$user_roles = explode(',', $user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Edit Profile | AutoParts Hub</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            'primary-brand': '#007bff',
            'accent-teal': '#17a2b8',
          }
        }
      }
    }
  </script>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    :root {
      --primary: #007bff;
      --secondary: #17a2b8;
    }
    
    body { 
      font-family: 'Roboto', sans-serif;
      background-color: #f8fafc;
      min-height: 100vh;
    }
    
    .input-field {
      width: 100%;
      padding: 0.75rem 1rem;
      border: 1px solid #e2e8f0;
      border-radius: 0.75rem;
      outline: none;
      transition: all 0.2s;
      font-size: 1rem;
      color: #1f2937;
      background: white;
    }
    .input-field:focus {
      border-color: #007bff;
      box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }
    .input-field:disabled {
      background-color: #f9fafb;
      color: #6b7280;
      cursor: not-allowed;
    }
    .input-error {
      border-color: #ef4444 !important;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
    }

    .alert {
      padding: 1rem 1.25rem;
      border-radius: 12px;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      font-weight: 500;
      border: 1px solid;
    }
    .alert-success {
      background-color: #f0fff4;
      color: #38a169;
      border-color: #c6f6d5;
    }
    .alert-error {
      background-color: #fff5f5;
      color: #e53e3e;
      border-color: #fed7d7;
    }
    
    .password-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #a0aec0;
      cursor: pointer;
      transition: color 0.2s;
      z-index: 10;
    }
    .password-toggle:hover {
      color: var(--primary);
    }
    
    .section-title {
      font-family: 'Poppins', sans-serif;
      font-size: 1.25rem;
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 1.5rem;
      padding-left: 1rem;
      border-left: 4px solid var(--secondary);
    }
    
    .status-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      margin-left: 0.5rem;
    }
    .status-pending { background-color: #fff3cd; color: #856404; }
    .status-approved { background-color: #d4edda; color: #155724; }
    .status-rejected { background-color: #f8d7da; color: #721c24; }

    .tab-list {
      display: flex;
      border-bottom: 1px solid #e5e7eb;
      padding-left: 2rem;
      padding-top: 1.5rem;
      background: white;
    }
    .tab {
      padding: 0.75rem 1.5rem;
      cursor: pointer;
      font-weight: 500;
      color: #4b5563;
      border-bottom: 3px solid transparent;
      margin-bottom: -1px;
      transition: all 0.2s;
    }
    .tab:hover {
      color: #007bff;
      border-color: #d1d5db;
    }
    .tab.active {
      color: #007bff;
      border-color: #007bff;
      font-weight: 600;
    }
    
    .tab-content {
      display: none;
    }
    .tab-content.active {
      display: block;
    }

    .action-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1.5rem 1rem;
      border-radius: 1rem;
      transition: all 0.3s;
      text-align: center;
      cursor: pointer;
      background: white;
      border: 1px solid #f1f5f9;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .action-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 16px rgba(0,0,0,0.1);
      border-color: #e2e8f0;
    }

    .role-badge {
      display: inline-flex;
      align-items: center;
      padding: 0.5rem 1rem;
      border-radius: 12px;
      font-size: 0.875rem;
      font-weight: 600;
      margin: 0.25rem;
      border: 2px solid;
    }
    .role-buyer { background-color: #dbeafe; color: #1e40af; border-color: #93c5fd; }
    .role-seller { background-color: #dcfce7; color: #166534; border-color: #86efac; }
    .role-support { background-color: #fef3c7; color: #92400e; border-color: #fcd34d; }
    .role-admin { background-color: #fce7f3; color: #be185d; border-color: #f9a8d4; }
  </style>
</head>
<body class="min-h-screen py-8 px-4">
  <div class="container mx-auto max-w-5xl">
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-100">
      <div class="bg-gradient-to-r from-primary-brand to-blue-700 text-white py-12 px-8 text-center relative">
        <div class="absolute inset-0 opacity-10 bg-black/10"></div>
        <div class="flex flex-col items-center relative z-10">
          <div class="relative mb-4">
            <?php
            $profilePic = $user['profile_picture'] ?: 'default.png';
            $picPath = 'uploads/profiles/' . $profilePic;
            $picExists = $user['profile_picture'] && file_exists($picPath);
            $src = $picExists ? $picPath : 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&size=144&background=007bff&color=fff&bold=true';
            ?>
            <img src="<?= htmlspecialchars($src) ?>" 
              alt="Profile Picture" 
              class="w-36 h-36 rounded-full object-cover border-4 border-white shadow-xl">
          </div>

          <h1 class="text-3xl font-bold mb-2 font-poppins"><?= htmlspecialchars($user['name']) ?></h1>
          <p class="text-lg opacity-90 font-light mb-4"><?= htmlspecialchars($user['email']) ?></p>
          
          <!-- Role Display - Separated like in screenshot -->
          <div class="flex flex-wrap justify-center gap-2 mb-3">
            <?php foreach ($user_roles as $role): ?>
              <?php if (trim($role) !== ''): ?>
                <span class="role-badge role-<?= htmlspecialchars(trim($role)) ?>">
                  <i class="fas 
                    <?= $role === 'buyer' ? 'fa-shopping-cart' : '' ?>
                    <?= $role === 'seller' ? 'fa-store' : '' ?>
                    <?= $role === 'support' ? 'fa-headset' : '' ?>
                    <?= $role === 'admin' ? 'fa-user-shield' : '' ?>
                    mr-2"></i>
                  <?= ucfirst(htmlspecialchars(trim($role))) ?>
                </span>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>

          <div class="text-sm opacity-80">
            <i class="fas fa-calendar-alt mr-1"></i> Joined <?= date('M j, Y', strtotime($user['created_at'])) ?>
          </div>

          <?php if ($seller_app_status): ?>
            <div class="mt-3">
              <span class="status-badge status-<?= htmlspecialchars($seller_app_status) ?>">
                <i class="fas fa-clipboard-list mr-1"></i> <?= ucfirst(htmlspecialchars($seller_app_status)) ?> Seller Application
              </span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="mx-8 mt-6"><?= $message ?></div>
      <?php endif; ?>

      <div class="tab-list">
        <div class="tab active" data-tab="profile">Profile Details</div>
        <div class="tab" data-tab="security">Security & Password</div>
      </div>

      <form method="POST" class="p-8 space-y-8" id="profileForm">
        <div class="tab-content active" id="profile-tab">
          <h3 class="section-title">Personal Information</h3>
          
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="relative">
              <label for="name" class="block text-gray-700 font-medium mb-2"><i class="fas fa-user text-primary-brand mr-2"></i> Full Name</label>
              <input type="text" name="name" id="name" required
                  value="<?= htmlspecialchars($user['name']) ?>"
                  class="input-field">
            </div>

            <div class="relative">
              <label for="email" class="block text-gray-700 font-medium mb-2"><i class="fas fa-envelope text-primary-brand mr-2"></i> Email Address</label>
              <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled
                  class="input-field">
              <input type="hidden" name="email" value="<?= htmlspecialchars($user['email']) ?>">
            </div>
            
            <div class="relative">
              <label for="phone" class="block text-gray-700 font-medium mb-2"><i class="fas fa-phone text-primary-brand mr-2"></i> Phone Number</label>
              <input type="tel" name="phone" id="phone" maxlength="10"
                  value="<?= htmlspecialchars($user['phone']) ?>"
                  class="input-field" oninput="validatePhone()" onkeypress="return isNumberKey(event)">
              <div id="phone-error" class="text-red-500 text-sm mt-1 hidden"></div>
            </div>

            <div class="relative">
              <label class="block text-gray-700 font-medium mb-2"><i class="fas fa-calendar-alt text-primary-brand mr-2"></i> Member Since</label>
              <input type="text" value="<?= date('M j, Y', strtotime($user['created_at'])) ?>" disabled
                  class="input-field">
            </div>
          </div>
          
          <div class="relative mt-6">
            <label for="address" class="block text-gray-700 font-medium mb-2"><i class="fas fa-map-marker-alt text-primary-brand mr-2"></i> Address</label>
            <textarea name="address" id="address" rows="3"
                        class="input-field"><?= htmlspecialchars($user['address']) ?></textarea>
          </div>
        </div>

        <div class="tab-content" id="security-tab">
          <h3 class="section-title">Account Security</h3>
          <p class="text-sm text-gray-500 mb-6 -mt-4">Set a strong password to keep your account safe.</p>
          
          <div class="space-y-6">
            <div class="relative">
              <label for="current_password" class="block text-gray-700 font-medium mb-2"><i class='fas fa-lock text-primary-brand mr-2'></i> Current Password</label>
              <div class="relative">
                <input type="password" name="current_password" id="current_password"
                    class="input-field pr-10" oninput="validateNewPassword()">
                <span class="password-toggle" onclick="togglePassword('current_password')">
                  <i class="fas fa-eye"></i>
                </span>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="relative">
                <label for="new_password" class="block text-gray-700 font-medium mb-2"><i class='fas fa-key text-primary-brand mr-2'></i> New Password</label>
                <div class="relative">
                  <input type="password" name="new_password" id="new_password"
                      minlength="6"
                      oninput="validateNewPassword()"
                      class="input-field pr-10">
                  <span class="password-toggle" onclick="togglePassword('new_password')">
                    <i class="fas fa-eye"></i>
                  </span>
                </div>
                <div id="newPasswordError" class="text-red-500 text-sm mt-1 hidden"></div>
              </div>

              <div class="relative">
                <label for="confirm_password" class="block text-gray-700 font-medium mb-2"><i class='fas fa-key text-primary-brand mr-2'></i> Confirm New Password</label>
                <div class="relative">
                  <input type="password" name="confirm_password" id="confirm_password"
                      oninput="validateConfirmPassword()"
                      class="input-field pr-10">
                  <span class="password-toggle" onclick="togglePassword('confirm_password')">
                    <i class="fas fa-eye"></i>
                  </span>
                </div>
                <div id="confirmPasswordError" class="text-red-500 text-sm mt-1 hidden"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-100">
          <button type="submit" class="flex-1 bg-gradient-to-r from-primary-brand to-blue-700 text-white font-bold py-3 px-6 rounded-xl shadow-lg hover:shadow-xl hover:scale-[1.01] transition-all flex items-center justify-center transform active:scale-95">
            <i class="fas fa-save mr-2"></i> Save Changes
          </button>
          <a href="index.php" class="flex-1 bg-gray-100 text-gray-700 font-medium py-3 px-6 rounded-xl shadow-sm hover:shadow-md hover:bg-gray-200 transition-all text-center flex items-center justify-center">
            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
          </a>
        </div>
      </form>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-2xl shadow-sm p-8 mt-8 border border-gray-100">
      <h3 class="section-title">Quick Actions</h3>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <a href="buyer/orders.php" class="action-card text-blue-600 hover:border-blue-200">
          <i class="fas fa-history text-2xl mb-3"></i>
          <span class="text-sm font-medium">Order History</span>
        </a>
        <a href="buyer/wishlist.php" class="action-card text-red-500 hover:border-red-200">
          <i class="fas fa-heart text-2xl mb-3"></i>
          <span class="text-sm font-medium">My Wishlist</span>
        </a>
        <a href="buyer/reviews.php" class="action-card text-green-600 hover:border-green-200">
          <i class="fas fa-star text-2xl mb-3"></i>
          <span class="text-sm font-medium">My Reviews</span>
        </a>
        
        <!-- Delete Account Button -->
        <button class="action-card text-gray-500 hover:border-red-200 hover:text-red-500" 
                onclick="showDeleteConfirmation()">
          <i class="fas fa-trash-alt text-2xl mb-3"></i>
          <span class="text-sm font-medium">Delete Account</span>
        </button>
      </div>
    </div>
    
    <div class="text-center mt-8">
      <a href="index.php" class="text-primary-brand font-medium hover:text-blue-700 transition-colors flex items-center justify-center">
        <i class="fas fa-home mr-2"></i> Go to Homepage
      </a>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 hidden transition-opacity duration-300 opacity-0">
    <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl transform scale-95 transition-transform duration-300">
      <div class="text-center">
        <i class="fas fa-exclamation-triangle text-4xl text-red-500 mb-4"></i>
        <h3 class="text-2xl font-bold text-gray-900 mb-2 font-poppins">Request Account Deletion</h3>
        <p class="text-gray-600 mb-6">
          Your request will be reviewed by an admin. You'll receive an email once processed.
          <br><br>
          <strong>This cannot be undone.</strong>
        </p>
      </div>
      <div class="flex justify-end space-x-3">
        <button class="bg-gray-200 text-gray-700 px-6 py-2 rounded-xl font-medium transition-all hover:bg-gray-300" onclick="hideDeleteConfirmation()">Cancel</button>
        
        <!-- Form to submit deletion request -->
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action" value="request_deletion">
          <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-xl font-medium shadow-md hover:bg-red-700 transition-all active:scale-95">
            Submit Request
          </button>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Tabs
    document.querySelectorAll('.tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        const tabId = tab.getAttribute('data-tab');
        document.getElementById(`${tabId}-tab`).classList.add('active');
      });
    });
    
    function togglePassword(inputId) {
      const input = document.getElementById(inputId);
      const icon = input.nextElementSibling.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    }

    // Only allow numbers in phone field
    function isNumberKey(evt) {
      const charCode = (evt.which) ? evt.which : evt.keyCode;
      if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        return false;
      }
      return true;
    }
    
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const currentPasswordInput = document.getElementById('current_password');
    const phoneInput = document.getElementById('phone');

    function setError(inputElement, errorDivId, message) {
        const errorDiv = document.getElementById(errorDivId);
        if (message) {
            errorDiv.textContent = message;
            errorDiv.classList.remove('hidden');
            inputElement.classList.add('input-error');
        } else {
            errorDiv.classList.add('hidden');
            inputElement.classList.remove('input-error');
        }
    }

    function validateNewPassword() {
        const currentPassword = currentPasswordInput.value;
        const newPassword = newPasswordInput.value;
        validateConfirmPassword();
        
        let errorMessage = '';
        
        if (newPassword.length > 0 && newPassword.length < 6) {
            errorMessage = "Password must be at least 6 characters long.";
        } else if (newPassword && currentPassword && newPassword === currentPassword) {
            errorMessage = "New password cannot be the same as current password.";
        }
        
        setError(newPasswordInput, 'newPasswordError', errorMessage);
    }
    
    function validateConfirmPassword() {
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        if (confirmPassword.length > 0 && newPassword !== confirmPassword) {
            setError(confirmPasswordInput, 'confirmPasswordError', "Passwords do not match.");
        } else {
            setError(confirmPasswordInput, 'confirmPasswordError', '');
        }
    }

    // Phone validation function
    function validatePhone() {
      const phone = phoneInput.value;
      const phoneErrorDiv = document.getElementById('phone-error');
      
      // Remove any non-digit characters (extra safety)
      const digitsOnly = phone.replace(/\D/g, '');
      
      // Update the input value to only contain digits
      if (phone !== digitsOnly) {
        phoneInput.value = digitsOnly;
      }
      
      // Check if exactly 10 digits
      if (digitsOnly.length !== 10 && digitsOnly.length > 0) {
        phoneErrorDiv.textContent = "Phone number must be exactly 10 digits.";
        phoneErrorDiv.classList.remove('hidden');
        phoneInput.classList.add('input-error');
        return false;
      }
      
      // Check if first digit is between 6-9
      if (digitsOnly.length === 10) {
        const firstDigit = parseInt(digitsOnly.charAt(0));
        if (firstDigit < 6 || firstDigit > 9) {
          phoneErrorDiv.textContent = "Phone number must start with 6, 7, 8, or 9.";
          phoneErrorDiv.classList.remove('hidden');
          phoneInput.classList.add('input-error');
          return false;
        }
      }
      
      // If all validations pass
      phoneErrorDiv.classList.add('hidden');
      phoneInput.classList.remove('input-error');
      return true;
    }
    
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        validateNewPassword();
        validateConfirmPassword();
        validatePhone();
        
        const hasError = !document.getElementById('newPasswordError').classList.contains('hidden') ||
                         !document.getElementById('confirmPasswordError').classList.contains('hidden') ||
                         !document.getElementById('phone-error').classList.contains('hidden');
        
        if (hasError) {
            e.preventDefault();
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelector('[data-tab="security"]').classList.add('active');
            document.getElementById('security-tab').classList.add('active');
        }
    });
    
    const deleteModal = document.getElementById('deleteModal');
    function showDeleteConfirmation() {
        deleteModal.classList.remove('hidden', 'opacity-0');
        deleteModal.classList.add('opacity-100');
        deleteModal.querySelector('div').classList.replace('scale-95', 'scale-100');
    }
    function hideDeleteConfirmation() {
        deleteModal.classList.remove('opacity-100');
        deleteModal.querySelector('div').classList.replace('scale-100', 'scale-95');
        setTimeout(() => deleteModal.classList.add('hidden', 'opacity-0'), 300);
    }

    // Initialize phone validation if there's an existing value
    document.addEventListener('DOMContentLoaded', function() {
      if (phoneInput.value) {
        validatePhone();
      }
    });
  </script>
</body>
</html>