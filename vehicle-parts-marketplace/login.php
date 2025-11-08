<?php
session_start();
include 'includes/config.php';

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
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $email)) {
        $message = "Email must be a valid Gmail address (ending with @gmail.com).";
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
  <title>Login | AutoParts Hub</title>
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

    <!-- Form -->
    <form method="POST" class="p-6 space-y-4" id="loginForm">
      <div>
        <label class="block text-sm font-medium text-blue-100 mb-1">Email</label>
        <input type="email" name="email" required
               class="glass-input w-full px-4 py-2.5 rounded-lg focus:outline-none"
               placeholder=""
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <div id="emailError" class="error-text hidden">Email must end with @gmail.com</div>
      </div>

      <div>
        <label class="block text-sm font-medium text-blue-100 mb-1">Password</label>
        <div class="relative">
          <input type="password" name="password" id="password" required
                 class="glass-input w-full px-4 py-2.5 rounded-lg focus:outline-none pr-10"
                 placeholder=""
                 minlength="6"
                 oninput="validatePassword()">
          <span toggle="#password" class="password-toggle absolute right-3 top-2.5 text-blue-100 cursor-pointer">
            <i class="fas fa-eye-slash"></i>
          </span>
        </div>
        <div id="passwordError" class="error-text hidden"></div>
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
    document.querySelectorAll('.password-toggle').forEach(toggle => {
      toggle.addEventListener('click', function () {
        const input = document.querySelector(this.getAttribute('toggle'));
        const icon = this.querySelector('i');
        input.type = input.type === 'password' ? 'text' : 'password';
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
      });
    });

    function validatePassword() {
      const password = document.getElementById('password').value;
      const errorDiv = document.getElementById('passwordError');

      if (password.length < 6) {
        errorDiv.textContent = "Password must be at least 6 characters long.";
        errorDiv.classList.remove('hidden');
      } else {
        errorDiv.classList.add('hidden');
      }
    }

    // Email validation
    function validateEmail() {
      const email = document.querySelector('input[name="email"]').value;
      const emailErrorDiv = document.getElementById('emailError');
      
      if (email && !email.endsWith('@gmail.com')) {
        emailErrorDiv.classList.remove('hidden');
        return false;
      } else {
        emailErrorDiv.classList.add('hidden');
        return true;
      }
    }

    // Add event listener for email input
    document.querySelector('input[name="email"]').addEventListener('input', validateEmail);

    // Prevent form submission if validation fails
    document.getElementById('loginForm').addEventListener('submit', function(e) {
      const password = document.getElementById('password').value;
      const email = document.querySelector('input[name="email"]').value;
      
      let hasError = false;
      
      if (password.length < 6) {
        e.preventDefault();
        document.getElementById('passwordError').textContent = "Password must be at least 6 characters long.";
        document.getElementById('passwordError').classList.remove('hidden');
        hasError = true;
      }
      
      if (email && !email.endsWith('@gmail.com')) {
        e.preventDefault();
        document.getElementById('emailError').classList.remove('hidden');
        hasError = true;
      }
    });
  </script>
</body>
</html>