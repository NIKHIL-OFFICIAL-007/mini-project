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
    // FIX: Use PDO::FETCH_ASSOC to ensure fields can be accessed by name
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

// Check if already has a PENDING support request
$stmt = $pdo->prepare("SELECT id, status FROM support_applications WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$existing_application = $stmt->fetch();

if ($existing_application) {
    header("Location: my_requests.php?message=pending_support_application_exists");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $experience = trim($_POST['experience']);
    $availability = trim($_POST['availability']); // Value from the select dropdown
    $reason = trim($_POST['reason']);
    // REMOVED: $additional_info = trim($_POST['additional_info'] ?? ''); 

    // Validate required fields
    if (empty($name) || empty($email) || empty($phone) || empty($reason) || empty($experience) || empty($availability)) {
        header("Location: apply_support.php?error=missing_required_fields");
        exit();
    }

    // Email validation (same as register.php)
    if (!preg_match('/^[a-z0-9.]+@gmail\.com$/', $email)) {
        error_log("Validation failed: Invalid email format");
        header("Location: apply_support.php?error=invalid_email_format");
        exit();
    }

    // Handle resume upload (PDF only)
    $resume_filename = null;
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === 0) {
        $allowed_types = ['application/pdf'];
        $file_type = mime_content_type($_FILES['resume']['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            header("Location: apply_support.php?error=invalid_file_type");
            exit();
        }
        
        if ($_FILES['resume']['size'] > 5 * 1024 * 1024) {
            header("Location: apply_support.php?error=file_too_large");
            exit();
        }
        
        $upload_dir = '../uploads/resumes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $filename = 'resume_' . $user_id . '_' . time() . '.pdf';
        $target_path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['resume']['tmp_name'], $target_path)) {
            $resume_filename = $filename;
        } else {
            header("Location: apply_support.php?error=upload_failed");
            exit();
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO support_applications 
            (user_id, name, email, phone, experience, availability, reason, resume_filename, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        // The execute array now has 8 parameters, matching the 8 columns above.
        $result = $stmt->execute([
            $user_id,
            $name,
            $email,
            $phone,
            $experience,
            $availability,
            $reason,
            $resume_filename // REMOVED: $additional_info
        ]);
        
        if ($result) {
            // Add notification
            $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, 'Your support agent application has been submitted.', 'info')")
                ->execute([$user_id]);

            header("Location: my_requests.php?message=support_application_submitted");
            exit();
        } else {
            throw new Exception("Database insert failed");
        }
    } catch (Exception $e) {
        error_log("Support application failed: " . $e->getMessage());
        header("Location: apply_support.php?error=application_failed");
        exit();
    }
}

// Extract user data with proper fallbacks
$name_value = htmlspecialchars($user['name'] ?? '');
$email_value = htmlspecialchars($user['email'] ?? '');
$phone_value = htmlspecialchars($user['phone'] ?? '');

// Define availability options and pre-select value
$availability_options = [
    'Full-time (40+ hrs/week)',
    'Part-time (20-40 hrs/week)',
    'Evenings and Weekends',
    'Flexible/As Needed (under 20 hrs/week)'
];
$selected_availability = htmlspecialchars($_POST['availability'] ?? '');

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Apply as Support Agent - AutoParts Hub</title>
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
    
    .form-input, .form-textarea, .form-select {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 16px;
      transition: border-color 0.2s ease;
    }
    
    .form-input:focus, .form-textarea:focus, .form-select:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }
    
    .form-textarea {
      min-height: 100px;
      resize: vertical;
    }

    /* === Custom styles for Select/Dropdowns (.form-select is used only for the availability select) === */
    .form-select {
      /* Remove default arrow in Chrome/Safari/Firefox */
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      /* Add custom SVG arrow */
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none'%3e%3cpath d='M7 7l3-3 3 3m0 6l-3 3-3-3' stroke='%236B7280' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 1.5em 1.5em;
      padding-right: 2.5rem;
    }

    /* === CSS to remove spinner/scroll arrows from other inputs === */

    /* Remove arrows for tel inputs (which can sometimes default to number styling) */
    input[type="tel"]::-webkit-outer-spin-button,
    input[type="tel"]::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }
    input[type="tel"] {
      -moz-appearance: textfield; /* Firefox */
    }
    
    /* Remove default scrollbar on textarea if content doesn't require it */
    .form-textarea {
      overflow: auto; 
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
    <div class="text-center mb-10">
      <h1 class="text-4xl font-bold text-gray-900 mb-4">Become a Support Agent</h1>
      <p class="text-xl text-gray-600 max-w-2xl mx-auto">
        Help our customers with their inquiries and become a trusted member of our support team.
      </p>
    </div>
    
    <?php if (isset($_GET['error'])): ?>
        <?php
        $error_message = '';
        switch ($_GET['error']) {
            case 'missing_required_fields':
                $error_message = 'Please fill out all required fields (*).';
                break;
            case 'invalid_email_format':
                $error_message = 'Email must be a valid Gmail address (only letters a-z, numbers 0-9, and periods allowed).';
                break;
            case 'invalid_file_type':
                $error_message = 'Invalid file type. Only PDF resumes are accepted.';
                break;
            case 'file_too_large':
                $error_message = 'The resume file is too large. Max size is 5MB.';
                break;
            case 'upload_failed':
                $error_message = 'File upload failed. Please try again.';
                break;
            case 'application_failed':
                $error_message = 'Application failed to submit due to a system error. Please try again later.';
                break;
        }
        if ($error_message) {
            echo "
            <div class='mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-lg text-left shadow-md'>
                <p class='font-bold'>Submission Error</p>
                <p class='text-sm'>{$error_message}</p>
            </div>";
        }
        ?>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-lg p-8">
      <form method="POST" class="space-y-8" enctype="multipart/form-data" id="supportForm">
        <div class="form-section">
          <h2 class="text-2xl font-bold mb-6">Contact Information</h2>
          
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
          <h2 class="text-2xl font-bold mb-6">Support Qualifications</h2>
          
          <div class="mb-6">
            <label class="form-label">Relevant Experience *</label>
            <textarea name="experience" required class="form-textarea" placeholder="Describe your customer service, technical support, or communication experience..."><?= htmlspecialchars($_POST['experience'] ?? '') ?></textarea>
            <div class="error-message" id="experience-error">Please describe your relevant experience</div>
          </div>
          
          <div class="mb-6">
            <label class="form-label">Availability *</label>
            <select name="availability" required class="form-input form-select">
                <option value="" disabled <?php echo empty($selected_availability) ? 'selected' : ''; ?>>Select your typical availability...</option>
                <?php foreach ($availability_options as $option): ?>
                    <option value="<?= htmlspecialchars($option) ?>"
                        <?php echo ($selected_availability === htmlspecialchars($option)) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($option) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="error-message" id="availability-error">Please select your availability</div>
          </div>
        </div>

        <div class="form-section">
          <h2 class="text-2xl font-bold mb-6">Application Details</h2>
          
          <div class="mb-6">
            <label class="form-label">Why do you want to be a support agent? *</label>
            <textarea name="reason" required class="form-textarea" placeholder="Explain your motivation and interest..."><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
            <div class="error-message" id="reason-error">Please explain why you want to be a support agent</div>
          </div>
        </div>

        <div class="form-section">
          <h2 class="text-2xl font-bold mb-6">Resume (Optional)</h2>
          <div>
            <label class="form-label">Upload Resume (PDF, max 5MB)</label>
            <div class="file-upload" onclick="document.getElementById('resume').click()">
              <i class="fas fa-file-upload file-upload-icon"></i>
              <p class="text-sm text-gray-600 mt-2">Click to upload your resume</p>
              <p class="text-xs text-gray-500 mt-1">PDF only, up to 5MB</p>
            </div>
            <input type="file" name="resume" accept=".pdf" class="hidden" id="resume">
          </div>
        </div>

        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg">
          <div class="flex items-start">
            <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
            <div class="ml-3">
              <p class="text-sm text-blue-800">
                <strong>Review Time:</strong> Applications are reviewed within 3–5 business days. You'll be notified by email.
              </p>
            </div>
          </div>
        </div>

        <div class="pt-6">
          <button type="submit" class="submit-btn">
            <i class="fas fa-paper-plane mr-2"></i> Submit Application
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php include '../includes/footer.php'; ?>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('supportForm');
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
        const inputs = form.querySelectorAll('input, textarea, select');
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
          { element: document.querySelector('textarea[name="experience"]'), errorId: 'experience-error', message: 'Please describe your relevant experience' },
          { element: document.querySelector('select[name="availability"]'), errorId: 'availability-error', message: 'Please select your availability' },
          { element: document.querySelector('textarea[name="reason"]'), errorId: 'reason-error', message: 'Please explain why you want to be a support agent' }
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
      const fileInput = document.getElementById('resume');
      if (fileInput) {
        fileInput.addEventListener('change', function() {
          const fileName = this.files[0]?.name || '';
          if (fileName) {
            const uploadDiv = document.querySelector('.file-upload');
            uploadDiv.innerHTML = `
              <i class="fas fa-file-pdf text-3xl text-red-500 mb-2"></i>
              <p class="text-sm font-medium text-gray-800">${fileName}</p>
              <p class="text-xs text-gray-500 mt-1">Click to change</p>
            `;
            uploadDiv.style.border = '2px dashed #3b82f6';
            uploadDiv.style.backgroundColor = '#f3f4f6';
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