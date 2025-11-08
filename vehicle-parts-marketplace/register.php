<?php
session_start();
include 'includes/config.php';

$message = '';

if ($_POST) {
    $name = trim($_POST['name']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (empty($name) || !$email || empty($password) || empty($confirm_password)) {
        $message = "Please fill in all fields.";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', $email)) {
        $message = "Email must be a valid Gmail address (ending with @gmail.com).";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $message = "Email already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, role_status) VALUES (?, ?, ?, 'buyer', 'approved')");
                $stmt->execute([$name, $email, $hashed_password]);

                header("Location: login.php?message=registered");
                exit();
            }
        } catch (Exception $e) {
            $message = "Registration failed. Please try again.";
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Register | AutoParts Hub</title>
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
        <i class="fas fa-user-plus text-xl"></i>
      </div>
      <h2 class="text-2xl font-semibold">Create Account</h2>
      <p class="text-blue-100 text-sm mt-1">Join our community today</p>
    </div>

    <!-- Alert -->
    <?php if ($message): ?>
      <div class="mx-6 mt-4 p-3 bg-red-400/20 border border-red-500/30 text-white rounded-lg text-sm backdrop-blur-sm">
        <i class="fas fa-exclamation-triangle mr-1"></i>
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" class="p-6 space-y-4" id="registerForm">
      <div>
        <label class="block text-sm font-medium text-blue-100 mb-1">Full Name</label>
        <input type="text" name="name" required
               class="glass-input w-full px-4 py-2.5 rounded-lg focus:outline-none"
               placeholder=""
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
      </div>

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

      <div>
        <label class="block text-sm font-medium text-blue-100 mb-1">Confirm Password</label>
        <div class="relative">
          <input type="password" name="confirm_password" id="confirm_password" required
                 class="glass-input w-full px-4 py-2.5 rounded-lg focus:outline-none pr-10"
                 placeholder="">
          <span toggle="#confirm_password" class="password-toggle absolute right-3 top-2.5 text-blue-100 cursor-pointer">
            <i class="fas fa-eye-slash"></i>
          </span>
        </div>
      </div>

      <button type="submit" class="glass-button w-full text-white font-medium py-2.5 rounded-lg transition">
        Register
      </button>
    </form>

    <!-- Footer -->
    <div class="px-6 py-4 text-center border-t border-white/10">
      <p class="text-sm text-blue-100">
        Already have an account?
        <a href="login.php" class="text-white hover:underline font-medium">Login here</a>
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
    document.getElementById('registerForm').addEventListener('submit', function(e) {
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