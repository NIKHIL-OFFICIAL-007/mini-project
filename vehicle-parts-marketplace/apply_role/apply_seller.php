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
    $stmt = $pdo->prepare("SELECT name, email, phone, address FROM users WHERE id = ?");
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

// Check if already approved seller
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_roles = $stmt->fetch();

if ($user_roles) {
    $roles = array_filter(array_map('trim', explode(',', $user_roles['role'])));
    $has_seller = in_array('seller', $roles);
    
    if ($has_seller) {
        header("Location: my_requests.php?message=already_approved");
        exit();
    }
}

// Check if already has a PENDING seller request
$stmt = $pdo->prepare("SELECT id, status FROM seller_applications WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$existing_application = $stmt->fetch();

if ($existing_application) {
    header("Location: my_requests.php?message=pending_application_exists");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== SELLER APPLICATION SUBMITTED ===");
    
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $business_address = trim($_POST['business_address']);
    $website = trim($_POST['website']);
    $reason = trim($_POST['reason']);

    // Validate required fields
    if (empty($name) || empty($email) || empty($phone) || empty($business_address) || empty($reason)) {
        error_log("Validation failed: Missing required fields");
        header("Location: apply_seller.php?error=missing_required_fields");
        exit();
    }

    // Email validation (same as register.php)
    if (!preg_match('/^[a-z0-9.]+@gmail\.com$/', $email)) {
        error_log("Validation failed: Invalid email format");
        header("Location: apply_seller.php?error=invalid_email_format");
        exit();
    }

    // Handle file upload
    $business_license = null;
    if (isset($_FILES['business_license']) && $_FILES['business_license']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        $file_type = mime_content_type($_FILES['business_license']['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            error_log("Invalid file type: " . $file_type);
            header("Location: apply_seller.php?error=invalid_file_type");
            exit();
        }
        
        // Check file size (5MB max)
        if ($_FILES['business_license']['size'] > 5 * 1024 * 1024) {
            error_log("File too large: " . $_FILES['business_license']['size'] . " bytes");
            header("Location: apply_seller.php?error=file_too_large");
            exit();
        }
        
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $filename = uniqid('license_') . '_' . basename($_FILES['business_license']['name']);
        $target_path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['business_license']['tmp_name'], $target_path)) {
            $business_license = $filename;
            error_log("File uploaded successfully: " . $filename);
        } else {
            error_log("File upload failed for: " . $_FILES['business_license']['name']);
            header("Location: apply_seller.php?error=upload_failed");
            exit();
        }
    }

    try {
        // Insert into seller_applications table
        $stmt = $pdo->prepare("
            INSERT INTO seller_applications 
            (user_id, name, email, phone, business_address, website, business_license, role_reason, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $result = $stmt->execute([
            $user_id,
            $name,
            $email,
            $phone,
            $business_address,
            $website,
            $business_license,
            $reason
        ]);
        
        if ($result) {
            error_log("Seller application created successfully");
            
            // Add notification
            $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, 'Your seller application has been submitted.', 'info')")
                ->execute([$user_id]);

            header("Location: my_requests.php?message=seller_application_submitted");
            exit();
        } else {
            error_log("Database insert failed");
            throw new Exception("Database insert failed");
        }
    } catch (Exception $e) {
        error_log("Seller application failed: " . $e->getMessage());
        header("Location: apply_seller.php?error=application_failed");
        exit();
    }
}

// Extract user data with proper fallbacks
$name_value = htmlspecialchars($user['name'] ?? '');
$email_value = htmlspecialchars($user['email'] ?? '');
$phone_value = htmlspecialchars($user['phone'] ?? '');
$address_value = htmlspecialchars($user['address'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Apply as Seller - AutoParts Hub</title>
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
    
    .form-input {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 16px;
      transition: border-color 0.2s ease;
    }
    
    .form-input:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }
    
    .form-textarea {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 16px;
      min-height: 120px;
      resize: vertical;
      transition: border-color 0.2s ease;
    }
    
    .form-textarea:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }
    
    .file-upload {
      border: 2px dashed #d1d5db;
      border-radius: 8px;
      padding: 24px;
      text-align: center;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    
    .file-upload:hover {
      border-color: #3b82f6;
      background-color: #f3f4f6;
    }
    
    .file-upload-icon {
      font-size: 32px;
      color: #6b7280;
      margin-bottom: 12px;
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
    
    .error-message {
      color: #ef4444;
      font-size: 14px;
      margin-top: 8px;
      display: none;
    }
    
    .success-message {
      color: #10b981;
      font-size: 14px;
      margin-top: 8px;
      display: none;
    }
    
    .input-error {
      border-color: #ef4444 !important;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2) !important;
    }
  </style>
</head>
<body class="min-h-screen bg-gray-50">
  <?php include '../includes/header.php'; ?>

  <div class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Page Header -->
    <div class="text-center mb-10">
      <h1 class="text-4xl font-bold text-gray-900 mb-4">Become a Verified Seller</h1>
      <p class="text-xl text-gray-600 max-w-2xl mx-auto">
        Join our marketplace of trusted sellers and start reaching thousands of customers looking for quality auto parts
      </p>
    </div>

    <!-- Form Container -->
    <div class="bg-white rounded-xl shadow-lg p-8">
      <form method="POST" class="space-y-8" enctype="multipart/form-data" id="sellerForm">
        <div class="form-section">
          <h2 class="text-2xl font-bold mb-6">Personal Information</h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="form-label">Full Name *</label>
              <input type="text" name="name" required class="form-input" value="<?= $name_value ?>">
              <div class="error-message" id="name-error">Please enter your full name</div>
            </div>
            
            <div>
              <label class="form-label">Email *</label>
              <input type="email" name="email" id="email" required class="form-input" value="<?= $email_value ?>">
              <div class="error-message" id="email-error">Email must be a valid Gmail address (only letters a-z, numbers 0-9, and periods allowed).</div>
              <div class="success-message" id="email-success">✓ Valid email format</div>
            </div>
            
            <div>
              <label class="form-label">Phone Number *</label>
              <input type="tel" name="phone" id="phone" required class="form-input" value="<?= $phone_value ?>">
              <div class="error-message" id="phone-error">Phone number must be 10 digits starting with 6, 7, 8, or 9</div>
            </div>
          </div>
        </div>

        <div class="form-section">
          <h2 class="text-2xl font-bold mb-6">Business Information</h2>
          
          <div>
            <label class="form-label">Business Address *</label>
            <textarea name="business_address" required class="form-textarea" placeholder="123 Main Street, City, State, ZIP Code"><?= $address_value ?></textarea>
            <div class="error-message" id="address-error">Please enter your business address</div>
          </div>
          
          <div class="mt-6">
            <label class="form-label">Website or LinkedIn Profile</label>
            <input type="url" name="website" class="form-input" placeholder="https://yourbusiness.com" value="">
          </div>
        </div>

        <div class="form-section">
          <h2 class="text-2xl font-bold mb-6">Documentation</h2>
          
          <div>
            <label class="form-label">Business License (PDF/JPG/PNG)</label>
            <div class="file-upload" onclick="document.getElementById('business_license').click()">
              <i class="fas fa-file-upload file-upload-icon"></i>
              <p class="text-sm text-gray-600 mt-2">Click to upload or drag and drop</p>
              <p class="text-xs text-gray-500 mt-1">PNG, JPG, PDF up to 5MB</p>
            </div>
            <input type="file" name="business_license" accept=".pdf,.jpg,.jpeg,.png" class="hidden" id="business_license">
          </div>
        </div>

        <div class="form-section">
          <h2 class="text-2xl font-bold mb-6">Application Details</h2>
          
          <div>
            <label class="form-label">Reason for Applying *</label>
            <textarea name="reason" required class="form-textarea" placeholder="Please explain why you want to sell auto parts on our platform, your relevant experience, qualifications, or business details..."></textarea>
            <div class="error-message" id="reason-error">Please explain why you want to become a seller</div>
          </div>
        </div>

        <!-- Processing Information -->
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg">
          <div class="flex items-start">
            <div class="flex-shrink-0">
              <i class="fas fa-info-circle text-yellow-500 mt-0.5"></i>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-yellow-800">Processing Information</h3>
              <div class="mt-2 text-sm text-yellow-700">
                <strong>Review Time:</strong> Applications are typically reviewed within 3-5 business days. You'll receive an email notification once a decision has been made.
              </div>
            </div>
          </div>
        </div>

        <!-- Submit Button -->
        <div class="pt-6">
          <button type="submit" class="submit-btn">
            <i class="fas fa-paper-plane mr-2"></i>
            Submit Application
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php include '../includes/footer.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('sellerForm');
      const emailInput = document.getElementById('email');
      const phoneInput = document.getElementById('phone');
      
      // Email validation function (same as register.php)
      function validateEmail() {
        const email = emailInput.value;
        const emailErrorDiv = document.getElementById('email-error');
        const emailSuccessDiv = document.getElementById('email-success');
        
        // Reset states
        emailErrorDiv.style.display = 'none';
        emailSuccessDiv.style.display = 'none';
        emailInput.classList.remove('input-error');
        
        if (!email) {
          return false;
        }
        
        // Check if email contains spaces
        if (/\s/.test(email)) {
          emailErrorDiv.textContent = "Email should not contain spaces.";
          emailErrorDiv.style.display = 'block';
          emailInput.classList.add('input-error');
          return false;
        }
        
        // Check if email is in lowercase
        if (email !== email.toLowerCase()) {
          emailErrorDiv.textContent = "Email must be in lowercase.";
          emailErrorDiv.style.display = 'block';
          emailInput.classList.add('input-error');
          return false;
        }
        
        // Check if email ends with @gmail.com
        if (!email.endsWith('@gmail.com')) {
          emailErrorDiv.textContent = "Email must end with @gmail.com.";
          emailErrorDiv.style.display = 'block';
          emailInput.classList.add('input-error');
          return false;
        }
        
        // Check if email contains only allowed characters (a-z, 0-9, .)
        const localPart = email.split('@')[0];
        if (!/^[a-z0-9.]+$/.test(localPart)) {
          emailErrorDiv.textContent = "Email can only contain letters (a-z), numbers (0-9), and periods (.).";
          emailErrorDiv.style.display = 'block';
          emailInput.classList.add('input-error');
          return false;
        }
        
        // Check for consecutive periods
        if (/\.{2,}/.test(localPart)) {
          emailErrorDiv.textContent = "Email cannot contain consecutive periods.";
          emailErrorDiv.style.display = 'block';
          emailInput.classList.add('input-error');
          return false;
        }
        
        // Check if email starts or ends with a period
        if (localPart.startsWith('.') || localPart.endsWith('.')) {
          emailErrorDiv.textContent = "Email cannot start or end with a period.";
          emailErrorDiv.style.display = 'block';
          emailInput.classList.add('input-error');
          return false;
        }
        
        // If all validations pass
        emailSuccessDiv.style.display = 'block';
        return true;
      }
      
      // Phone validation function
      function validatePhone() {
        const phone = phoneInput.value;
        const phoneErrorDiv = document.getElementById('phone-error');
        
        // Remove any non-digit characters
        const digitsOnly = phone.replace(/\D/g, '');
        
        // Check if exactly 10 digits
        if (digitsOnly.length !== 10 && digitsOnly.length > 0) {
          phoneErrorDiv.textContent = "Phone number must be exactly 10 digits.";
          phoneErrorDiv.style.display = 'block';
          phoneInput.classList.add('input-error');
          return false;
        }
        
        // Check if first digit is between 6-9
        if (digitsOnly.length === 10) {
          const firstDigit = parseInt(digitsOnly.charAt(0));
          if (firstDigit < 6 || firstDigit > 9) {
            phoneErrorDiv.textContent = "Phone number must start with 6, 7, 8, or 9.";
            phoneErrorDiv.style.display = 'block';
            phoneInput.classList.add('input-error');
            return false;
          }
        }
        
        // If all validations pass
        phoneErrorDiv.style.display = 'none';
        phoneInput.classList.remove('input-error');
        return true;
      }
      
      // Auto-convert email to lowercase and remove spaces as user types
      emailInput.addEventListener('input', function(e) {
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
      
      // Format phone number and validate as user types
      phoneInput.addEventListener('input', function(e) {
        let phone = e.target.value;
        
        // Remove all non-digit characters
        phone = phone.replace(/\D/g, '');
        
        // Limit to 10 digits
        phone = phone.substring(0, 10);
        
        // Update the input value
        e.target.value = phone;
        
        // Validate the phone number
        validatePhone();
      });
      
      // Add event listeners for blur events
      emailInput.addEventListener('blur', validateEmail);
      phoneInput.addEventListener('blur', validatePhone);
      
      // Form validation on submit
      form.addEventListener('submit', function(e) {
        let hasError = false;
        
        // Reset error states
        const inputs = form.querySelectorAll('input, textarea');
        inputs.forEach(input => {
          input.classList.remove('input-error');
          const errorElement = input.nextElementSibling;
          if (errorElement && errorElement.classList.contains('error-message')) {
            errorElement.style.display = 'none';
          }
        });
        
        // Validate required fields
        const requiredFields = [
          { element: document.querySelector('input[name="name"]'), errorId: 'name-error', message: 'Please enter your full name' },
          { element: document.querySelector('textarea[name="business_address"]'), errorId: 'address-error', message: 'Please enter your business address' },
          { element: document.querySelector('textarea[name="reason"]'), errorId: 'reason-error', message: 'Please explain why you want to become a seller' }
        ];
        
        requiredFields.forEach(field => {
          if (!field.element.value.trim()) {
            field.element.classList.add('input-error');
            const errorElement = document.getElementById(field.errorId);
            if (errorElement) {
              errorElement.textContent = field.message;
              errorElement.style.display = 'block';
            }
            hasError = true;
          }
        });
        
        // Validate email
        if (!validateEmail()) {
          hasError = true;
        }
        
        // Validate phone
        if (!validatePhone()) {
          hasError = true;
        }
        
        if (hasError) {
          e.preventDefault();
          return;
        }
      });
      
      // File upload preview
      const fileInput = document.getElementById('business_license');
      if (fileInput) {
        fileInput.addEventListener('change', function() {
          const fileName = this.files[0]?.name || '';
          if (fileName) {
            const fileUploadDiv = document.querySelector('.file-upload');
            fileUploadDiv.style.backgroundColor = '#f3f4f6';
            fileUploadDiv.style.border = '2px dashed #3b82f6';
            
            // Update the text inside the upload area
            const textSpan = fileUploadDiv.querySelector('p:nth-child(2)');
            if (textSpan) {
              textSpan.textContent = fileName;
            }
          }
        });
      }
      
      // Initialize validation on page load if there are values
      if (emailInput.value) {
        validateEmail();
      }
      if (phoneInput.value) {
        validatePhone();
      }
    });
  </script>
</body>
</html>