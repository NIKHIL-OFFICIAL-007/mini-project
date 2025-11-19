<?php
session_start();
include 'includes/config.php';

// Helper function to get site settings
function getSiteSettings($pdo) {
    static $settings = null;
    
    if ($settings === null) {
        try {
            $stmt = $pdo->query("SELECT * FROM settings ORDER BY id DESC LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If no settings exist, return defaults
            if (!$settings) {
                $settings = [
                    'site_name' => 'AutoParts Hub',
                    'contact_email' => 'support@autopartshub.com',
                    'phone' => '+1 (555) 123-4567',
                    'address' => '123 Auto Lane, Tech City, TC 10101'
                ];
            }
        } catch (Exception $e) {
            error_log("Failed to fetch site settings: " . $e->getMessage());
            // Return defaults if database error
            $settings = [
                'site_name' => 'AutoParts Hub',
                'contact_email' => 'support@autopartshub.com',
                'phone' => '+1 (555) 123-4567',
                'address' => '123 Auto Lane, Tech City, TC 10101'
            ];
        }
    }
    
    return $settings;
}

// Get site settings
$site_settings = getSiteSettings($pdo);

// Redirect logged-in users to their dashboard
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            $roles = explode(',', $user['role']);

            // Priority: Admin > Support > Seller > Buyer
            if (in_array('admin', $roles)) {
                header("Location: admin/dashboard.php");
                exit;
            }
            if (in_array('support', $roles)) {
                header("Location: support/dashboard.php");
                exit;
            }
            if (in_array('seller', $roles)) {
                header("Location: seller/dashboard.php");
                exit;
            }
            if (in_array('buyer', $roles)) {
                header("Location: buyer/dashboard.php");
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Redirect failed: " . $e->getMessage());
    }

    // Fallback
    header("Location: index.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email || empty($password)) {
        $message = "Please enter both email and password.";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
    } elseif (!preg_match('/^[a-z0-9.]+@gmail\.com$/', $email)) {
        $message = "Email must be a valid Gmail address (only letters a-z, numbers 0-9, and periods allowed).";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, name, password, role, role_status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = htmlspecialchars($user['name']);
                $_SESSION['role'] = $user['role'];
                $_SESSION['role_status'] = $user['role_status'];

                // Split roles for multi-role check
                $roles = explode(',', $user['role']);

                // Priority: Admin > Support > Seller > Buyer
                if (in_array('admin', $roles)) {
                    header("Location: admin/dashboard.php");
                    exit;
                }
                if (in_array('support', $roles)) {
                    header("Location: support/dashboard.php");
                    exit;
                }
                if (in_array('seller', $roles)) {
                    header("Location: seller/dashboard.php");
                    exit;
                }
                if (in_array('buyer', $roles)) {
                    header("Location: buyer/dashboard.php");
                    exit;
                }

                // Fallback
                header("Location: index.php");
                exit;
            } else {
                $message = "Invalid email or password.";
            }
        } catch (Exception $e) {
            $message = "Login failed. Please try again.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($site_settings['site_name']) ?> - Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background: url('assets/images/background.png') no-repeat center center fixed;
      background-size: cover;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      padding: 0;
    }
    .glass-card {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(12px);
      border-radius: 20px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
      width: 100%;
      max-width: 420px;
    }
    .glass-input {
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.2);
      color: white;
    }
    .glass-input:focus {
      background: rgba(255, 255, 255, 0.15);
      border-color: rgba(59, 130, 246, 0.6);
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }
    .glass-button {
      background: rgba(59, 130, 246, 0.8);
      backdrop-filter: blur(10px);
    }
    .glass-button:hover {
      background: rgba(37, 99, 235, 0.9);
      transform: translateY(-2px);
    }
    .error-text {
      color: #f87171;
      font-size: 0.8rem;
      margin-top: 0.25rem;
    }
    .input-error {
      border-color: #f87171 !important;
      box-shadow: 0 0 0 3px rgba(248, 113, 113, 0.2) !important;
    }
    .success-text {
      color: #4ade80;
      font-size: 0.8rem;
      margin-top: 0.25rem;
    }
  </style>
</head>
<body class="p-4">
  <div class="glass-card text-white overflow-hidden">
    <!-- Header -->
    <div class="py-6 px-8 text-center border-b border-white/10">
      <div class="w-14 h-14 bg-blue-500/20 rounded-full flex items-center justify-center mx-auto mb-3">
        <i class="fas fa-user text-xl"></i>
      </div>
      <h2 class="text-2xl font-semibold">Welcome Back</h2>
      <p class="text-blue-100 text-sm mt-1">Sign in to your account</p>
    </div>

    <!-- Alert -->
    <?php if ($message): ?>
      <div class="mx-6 mt-4 p-3 bg-red-400/20 border border-red-500/30 text-white rounded-lg text-sm backdrop-blur-sm">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <!-- Success message for registration -->
    <?php if (isset($_GET['message']) && $_GET['message'] === 'registered'): ?>
      <div class="mx-6 mt-4 p-3 bg-green-400/20 border border-green-500/30 text-white rounded-lg text-sm backdrop-blur-sm">
        <i class="fas fa-check-circle mr-1"></i>
        Registration successful! Please log in with your credentials.
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" class="p-6 space-y-4" id="loginForm">
      <div>
        <label class="block text-sm font-medium text-blue-100 mb-1">Email</label>
        <input type="email" name="email" id="email" required
               class="glass-input w-full px-4 py-2.5 rounded-lg focus:outline-none"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <div id="emailError" class="error-text hidden"></div>
        <div id="emailSuccess" class="success-text hidden">✓ Valid email format</div>
      </div>

      <div>
        <label class="block text-sm font-medium text-blue-100 mb-1">Password</label>
        <div class="relative">
          <input type="password" name="password" id="password" required
                 class="glass-input w-full px-4 py-2.5 rounded-lg focus:outline-none pr-10"
                 minlength="6">
          <span toggle="#password" class="password-toggle absolute right-3 top-2.5 text-blue-100 cursor-pointer">
            <i class="fas fa-eye-slash"></i>
          </span>
        </div>
        <div id="passwordError" class="error-text hidden"></div>
        <div id="passwordSuccess" class="success-text hidden">✓ Password meets requirements</div>
      </div>

      <button type="submit" class="glass-button w-full text-white font-medium py-2.5 rounded-lg transition">
        Sign In
      </button>
    </form>

    <!-- Footer -->
    <div class="px-6 py-4 text-center border-t border-white/10">
      <p class="text-sm text-blue-100">
        Don't have an account?
        <a href="register.php" class="text-white hover:underline font-medium">Register now</a>
      </p>
    </div>
  </div>

  <!-- Password Toggle & Validation Script -->
  <script>
    // Password toggle functionality
    document.querySelectorAll('.password-toggle').forEach(toggle => {
      toggle.addEventListener('click', function () {
        const input = document.querySelector(this.getAttribute('toggle'));
        const icon = this.querySelector('i');
        input.type = input.type === 'password' ? 'text' : 'password';
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
      });
    });

    // Email validation
    function validateEmail() {
      const emailInput = document.getElementById('email');
      const email = emailInput.value;
      const emailErrorDiv = document.getElementById('emailError');
      const emailSuccessDiv = document.getElementById('emailSuccess');
      
      // Reset states
      emailErrorDiv.classList.add('hidden');
      emailSuccessDiv.classList.add('hidden');
      emailInput.classList.remove('input-error');
      
      if (!email) {
        return false;
      }
      
      // Check if email contains spaces
      if (/\s/.test(email)) {
        emailErrorDiv.textContent = "Email should not contain spaces.";
        emailErrorDiv.classList.remove('hidden');
        emailInput.classList.add('input-error');
        return false;
      }
      
      // Check if email is in lowercase
      if (email !== email.toLowerCase()) {
        emailErrorDiv.textContent = "Email must be in lowercase.";
        emailErrorDiv.classList.remove('hidden');
        emailInput.classList.add('input-error');
        return false;
      }
      
      // Check if email ends with @gmail.com
      if (!email.endsWith('@gmail.com')) {
        emailErrorDiv.textContent = "Email must end with @gmail.com.";
        emailErrorDiv.classList.remove('hidden');
        emailInput.classList.add('input-error');
        return false;
      }
      
      // Check if email contains only allowed characters (a-z, 0-9, .)
      const localPart = email.split('@')[0];
      if (!/^[a-z0-9.]+$/.test(localPart)) {
        emailErrorDiv.textContent = "Email can only contain letters (a-z), numbers (0-9), and periods (.).";
        emailErrorDiv.classList.remove('hidden');
        emailInput.classList.add('input-error');
        return false;
      }
      
      // Check for consecutive periods
      if (/\.{2,}/.test(localPart)) {
        emailErrorDiv.textContent = "Email cannot contain consecutive periods.";
        emailErrorDiv.classList.remove('hidden');
        emailInput.classList.add('input-error');
        return false;
      }
      
      // Check if email starts or ends with a period
      if (localPart.startsWith('.') || localPart.endsWith('.')) {
        emailErrorDiv.textContent = "Email cannot start or end with a period.";
        emailErrorDiv.classList.remove('hidden');
        emailInput.classList.add('input-error');
        return false;
      }
      
      // If all validations pass
      emailSuccessDiv.classList.remove('hidden');
      return true;
    }

    // Password validation
    function validatePassword() {
      const password = document.getElementById('password').value;
      const errorDiv = document.getElementById('passwordError');
      const successDiv = document.getElementById('passwordSuccess');
      
      // Reset states
      errorDiv.classList.add('hidden');
      successDiv.classList.add('hidden');
      
      if (!password) {
        return false;
      }
      
      if (password.length < 6) {
        errorDiv.textContent = "Password must be at least 6 characters long.";
        errorDiv.classList.remove('hidden');
        return false;
      } else {
        successDiv.classList.remove('hidden');
        return true;
      }
    }

    // Auto-convert email to lowercase and remove spaces as user types
    document.getElementById('email').addEventListener('input', function(e) {
      let email = e.target.value;
      
      // Remove any spaces
      email = email.replace(/\s/g, '');
      
      // Convert to lowercase
      email = email.toLowerCase();
      
      // Update the input value
      e.target.value = email;
      
      // Validate the email
      validateEmail();
    });

    // Real-time validation for all fields
    document.getElementById('password').addEventListener('input', validatePassword);

    // Add event listeners for blur validation
    document.getElementById('email').addEventListener('blur', validateEmail);
    document.getElementById('password').addEventListener('blur', validatePassword);

    // Form submission validation
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      const isEmailValid = validateEmail();
      const isPasswordValid = validatePassword();
      
      if (!isEmailValid || !isPasswordValid) {
        e.preventDefault();
        
        // Focus on first invalid field
        if (!isEmailValid) {
          document.getElementById('email').focus();
        } else if (!isPasswordValid) {
          document.getElementById('password').focus();
        }
      }
    });

    // Initialize validation on page load if there are values
    document.addEventListener('DOMContentLoaded', function() {
      if (document.getElementById('email').value) {
        validateEmail();
      }
      if (document.getElementById('password').value) {
        validatePassword();
      }
    });
  </script>
</body>
</html>