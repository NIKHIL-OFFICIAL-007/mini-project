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
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $profile_picture = $user['profile_picture'];

    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $file = $_FILES['profile_picture'];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (in_array($file_ext, $allowed_extensions) && in_array($mime_type, $allowed_mimes)) {
            $upload_dir = 'uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                if ($profile_picture && $profile_picture !== 'default.png' && file_exists($upload_dir . $profile_picture)) {
                    unlink($upload_dir . $profile_picture);
                }
                $profile_picture = $new_filename;
            } else {
                $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> Failed to upload profile picture.</div>";
            }
        } else {
            $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> Invalid file. Only JPG, PNG, or GIF allowed.</div>";
        }
    }

    // Validation
    if (empty($name) || !$email) {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> Name and email are required.</div>";
    } elseif ($new_password && strlen($new_password) < 6) {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> New password must be at least 6 characters.</div>";
    } elseif ($new_password && $new_password !== $confirm_password) {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> Passwords do not match.</div>";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->rowCount() > 0) {
            $message = "<div class='alert alert-error'><i class='fas fa-exclamation-circle mr-2'></i> Email is already in use.</div>";
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
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, profile_picture = ?, phone = ?, address = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $hashed, $profile_picture, $phone, $address, $_SESSION['user_id']]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, profile_picture = ?, phone = ?, address = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $profile_picture, $phone, $address, $_SESSION['user_id']]);
                    }

                    $_SESSION['name'] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                    $_SESSION['email'] = $email;
                    $_SESSION['profile_picture'] = $profile_picture;

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
          <div class="relative mb-4 group">
            <?php
            $profilePic = $user['profile_picture'] ?: 'default.png';
            $picPath = 'uploads/profiles/' . $profilePic;
            $picExists = $user['profile_picture'] && file_exists($picPath);
            $src = $picExists ? $picPath : 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&size=144&background=007bff&color=fff&bold=true';
            ?>
            <img src="<?= htmlspecialchars($src) ?>" 
              alt="Profile Picture" 
              class="w-36 h-36 rounded-full object-cover border-4 border-white shadow-xl transition-all duration-300 group-hover:scale-105">
            
            <div class="absolute inset-0 flex items-center justify-center bg-black/40 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300 cursor-pointer" onclick="document.getElementById('profile_picture').click()">
              <i class="fas fa-camera text-2xl text-white"></i>
            </div>
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

      <form method="POST" class="p-8 space-y-8" id="profileForm" enctype="multipart/form-data">
        <input type="file" name="profile_picture" id="profile_picture" accept="image/*" class="hidden" onchange="previewProfilePicture(event)">
        
        <div class="tab-content active" id="profile-tab">
          <h3 class="section-title">Personal Information</h3>
          
          <div id="profilePicturePreview" class="mb-6 hidden">
             <div class="text-center mb-3 text-sm text-gray-500">New Photo Preview:</div>
             <img id="previewImage" class="w-32 h-32 mx-auto rounded-full object-cover border-4 border-accent-teal shadow-lg" src="" alt="Preview">
          </div>
          
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="relative">
              <label for="name" class="block text-gray-700 font-medium mb-2"><i class="fas fa-user text-primary-brand mr-2"></i> Full Name</label>
              <input type="text" name="name" id="name" required
                  value="<?= htmlspecialchars($user['name']) ?>"
                  class="input-field">
            </div>

            <div class="relative">
              <label for="email" class="block text-gray-700 font-medium mb-2"><i class="fas fa-envelope text-primary-brand mr-2"></i> Email Address</label>
              <input type="email" name="email" id="email" required
                  value="<?= htmlspecialchars($user['email']) ?>"
                  class="input-field">
            </div>
            
            <div class="relative">
              <label for="phone" class="block text-gray-700 font-medium mb-2"><i class="fas fa-phone text-primary-brand mr-2"></i> Phone Number</label>
              <input type="tel" name="phone" id="phone"
                  value="<?= htmlspecialchars($user['phone']) ?>"
                  class="input-field" placeholder="+1 (555) 123-4567">
            </div>

            <div class="relative">
              <label class="block text-gray-700 font-medium mb-2"><i class="fas fa-calendar-alt text-primary-brand mr-2"></i> Member Since</label>
              <input type="text" value="<?= date('M j, Y', strtotime($user['created_at'])) ?>" disabled
                  class="input-field bg-gray-50 cursor-not-allowed">
            </div>
          </div>
          
          <div class="relative mt-6">
            <label for="address" class="block text-gray-700 font-medium mb-2"><i class="fas fa-map-marker-alt text-primary-brand mr-2"></i> Address</label>
            <textarea name="address" id="address" rows="3" placeholder="Enter your full address"
                        class="input-field"><?= htmlspecialchars($user['address']) ?></textarea>
          </div>
          
          <?php
          $show_apply_button = false;
          if ($user['role'] === 'buyer') {
              if (!$seller_app_status || $seller_app_status === 'rejected') {
                  $show_apply_button = true;
              }
          }
          ?>
          
          <?php if ($show_apply_button): ?>
          <div class="mt-8 pt-6 border-t border-gray-100">
            <a href="apply_seller.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-accent-teal to-cyan-600 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl hover:scale-[1.01] transition-all">
              <i class="fas fa-store mr-2"></i> Apply to be Seller
            </a>
          </div>
          <?php endif; ?>
        </div>

        <div class="tab-content" id="security-tab">
          <h3 class="section-title">Account Security</h3>
          <p class="text-sm text-gray-500 mb-6 -mt-4">Set a strong password to keep your account safe.</p>
          
          <div class="space-y-6">
            <div class="relative">
              <label for="current_password" class="block text-gray-700 font-medium mb-2"><i class='fas fa-lock text-primary-brand mr-2'></i> Current Password</label>
              <div class="relative">
                <input type="password" name="current_password" id="current_password"
                    placeholder="Enter current password to change or save profile"
                    class="input-field pr-10">
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
                      placeholder="At least 6 characters"
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
                      placeholder="Re-enter new password"
                      oninput="validateConfirmPassword()"
                      class="input-field pr-10">
                  <span class="password-toggle" onclick="togglePassword('confirm_password')">
                    <i class="fas fa-eye"></i>
                  </span>
                </div>
                <div id="confirmPasswordError" class="text-red-500 text-sm mt-1 hidden"></div>
              </div>
            </div>
            
            <div class="mt-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Password Strength</label>
              <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div id="passwordStrength" class="h-2.5 rounded-full transition-all duration-300" style="width: 0%; background-color: #dc2626;"></div>
              </div>
              <div id="passwordTips" class="text-xs text-gray-500 mt-1"></div>
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
        <a href="order_history.php" class="action-card text-blue-600 hover:border-blue-200">
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
    
    function previewProfilePicture(event) {
      const file = event.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
          document.getElementById('previewImage').src = e.target.result;
          document.getElementById('profilePicturePreview').classList.remove('hidden');
        };
        reader.readAsDataURL(file);
      }
    }
    
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');

    function setError(inputElement, errorDivId, message) {
        const errorDiv = document.getElementById(errorDivId);
        if (message) {
            errorDiv.textContent = message;
            errorDiv.classList.remove('hidden');
            inputElement.classList.add('border-red-500');
        } else {
            errorDiv.classList.add('hidden');
            inputElement.classList.remove('border-red-500');
        }
    }

    function validateNewPassword() {
        const newPassword = newPasswordInput.value;
        validateConfirmPassword();
        if (newPassword.length > 0 && newPassword.length < 6) {
            setError(newPasswordInput, 'newPasswordError', "Password must be at least 6 characters long.");
        } else {
            setError(newPasswordInput, 'newPasswordError', '');
        }
        updatePasswordStrength(newPassword);
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
    
    function updatePasswordStrength(password) {
        const strengthBar = document.getElementById('passwordStrength');
        const tipsDiv = document.getElementById('passwordTips');
        let score = 0;
        let tips = [];
        if (password.length > 0) {
            if (password.length >= 8) score += 25;
            else tips.push("8+ characters");
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score += 25;
            else tips.push("Mixed case");
            if (/\d/.test(password)) score += 25;
            else tips.push("A number");
            if (/[^a-zA-Z\d]/.test(password)) score += 25;
            else tips.push("Special char");
        }
        strengthBar.style.width = score + '%';
        if (score < 50) {
            strengthBar.style.backgroundColor = '#f56565';
            tipsDiv.textContent = score > 0 ? "Weak: Add " + tips.join(', ') : "";
        } else if (score < 75) {
            strengthBar.style.backgroundColor = '#f6ad55';
            tipsDiv.textContent = "Medium: Add " + tips.join(', ');
        } else {
            strengthBar.style.backgroundColor = '#48bb78';
            tipsDiv.textContent = "Strong password! 💪";
        }
        if (password.length === 0) {
            strengthBar.style.width = '0%';
            tipsDiv.textContent = "";
        }
    }
    
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        validateNewPassword();
        validateConfirmPassword();
        const hasError = !document.getElementById('newPasswordError').classList.contains('hidden') ||
                         !document.getElementById('confirmPasswordError').classList.contains('hidden');
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
  </script>
</body>
</html>