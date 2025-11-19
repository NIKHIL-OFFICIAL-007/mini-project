<?php
session_start();
include '../includes/config.php';

// User check - only require login, not admin role
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];

// === Fetch Current User's Requests Only ===

// 1. Seller Applications
$seller_apps = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            sa.id,
            sa.user_id,
            sa.name,
            sa.phone,
            sa.business_address,
            sa.website,
            sa.business_license,
            sa.role_reason,
            sa.status,
            sa.created_at,
            u.email
        FROM seller_applications sa
        JOIN users u ON sa.user_id = u.id
        WHERE sa.user_id = ?
        ORDER BY sa.created_at DESC
    ");
    $stmt->execute([$current_user_id]);
    $seller_apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch seller apps: " . $e->getMessage());
}

// 2. Support Applications
$support_apps = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            user_id,
            name,
            email,
            phone,
            experience,
            availability,
            reason,
            /* Removed additional_info */
            resume_filename,
            status,
            created_at
        FROM support_applications
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$current_user_id]);
    $support_apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch support apps: " . $e->getMessage());
}

// 3. Admin Applications
$admin_apps = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            user_id,
            name,
            email,
            phone,
            reason,
            experience,
            /* Removed additional_info */
            status,
            created_at
        FROM admin_applications
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$current_user_id]);
    $admin_apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch admin apps: " . $e->getMessage());
}

// 4. Account Deletion Requests (user's own requests)
$deletion_requests = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            adr.id AS request_id,
            adr.user_id,
            adr.requested_at,
            adr.status,
            u.name,
            u.email,
            u.role
        FROM account_deletion_requests adr
        JOIN users u ON adr.user_id = u.id
        WHERE adr.user_id = ?
        ORDER BY adr.requested_at DESC
    ");
    $stmt->execute([$current_user_id]);
    $deletion_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch deletion requests: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Requests - Application Status</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              50: '#eff6ff',
              100: '#dbeafe',
              500: '#3b82f6',
              600: '#2563eb',
              700: '#1d4ed8',
            },
            secondary: {
              50: '#f9fafb',
              100: '#f3f4f6',
              500: '#6b7280',
              600: '#4b5563',
              700: '#374151',
            },
            seller: '#3b82f6',
            support: '#10b981',
            admin: '#8b5cf6',
            deletion: '#ef4444',
          },
          animation: {
            'fade-in': 'fadeIn 0.5s ease-in-out',
            'slide-up': 'slideUp 0.3s ease-out',
          },
          keyframes: {
            fadeIn: {
              '0%': { opacity: '0' },
              '100%': { opacity: '1' },
            },
            slideUp: {
              '0%': { transform: 'translateY(10px)', opacity: '0' },
              '100%': { transform: 'translateY(0)', opacity: '1' },
            }
          }
        }
      }
    }
  </script>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background-color: #f3f4f6;
      min-height: 100vh;
    }
    .card-hover {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    }
    .card-hover:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
    .status-badge {
      padding: 0.3rem 0.65rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
    .section-title {
      position: relative;
      padding-left: 1.5rem;
      font-size: 1.75rem;
    }
    .section-title::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      height: 80%;
      width: 6px;
      border-radius: 3px;
    }
    .seller-title::before { background-color: #3b82f6; }
    .support-title::before { background-color: #10b981; }
    .admin-title::before { background-color: #8b5cf6; }
    .deletion-title::before { background-color: #ef4444; }

    .expandable-content {
      transition: max-height 0.3s ease-out;
      overflow: hidden;
      max-height: 100px; /* Default collapsed height */
      padding: 1rem;
      border-radius: 0.5rem;
      background-color: #f7f7fa;
      border: 1px solid #e5e7eb;
      line-height: 1.6;
      font-size: 0.875rem;
      color: #374151;
    }
    .expanded {
      max-height: 1000px !important; /* Sufficiently large to show all content */
    }
    .read-more-btn {
      color: #2563eb;
      font-weight: 600;
      font-size: 0.875rem;
      margin-top: 0.5rem;
      display: block;
      cursor: pointer;
      background: none;
      border: none;
      padding: 0;
      text-decoration: underline;
    }
  </style>
</head>
<body class="text-gray-800">

  <?php include '../includes/header.php'; ?>

  <div class="container mx-auto px-4 py-10 max-w-7xl">

    <div class="mb-10 border-b pb-6 border-gray-200">
      <h1 class="text-4xl font-extrabold text-gray-900 mb-2 flex items-center">
        <i class="fas fa-clipboard-list mr-4 text-primary-600"></i>
        My Application Requests
      </h1>
      <p class="text-gray-600 text-lg">Track the status of all your role applications and account requests.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
      <div class="stat-card rounded-xl p-6 card-hover shadow-xl bg-white border border-primary-100">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-base font-semibold text-gray-500 uppercase tracking-wider">Seller Apps</p>
            <p class="text-4xl font-extrabold text-primary-600 mt-1"><?= count($seller_apps) ?></p>
          </div>
          <div class="p-3 rounded-full bg-primary-100 text-primary-600 shadow-inner">
            <i class="fas fa-store text-2xl"></i>
          </div>
        </div>
        <p class="text-sm text-gray-500 mt-4 border-t border-gray-100 pt-3">
          <span class="text-orange-600 font-bold">
            <?= count(array_filter($seller_apps, fn($app) => $app['status'] === 'pending')) ?>
          </span> pending
        </p>
      </div>
      
      <div class="stat-card rounded-xl p-6 card-hover shadow-xl bg-white border border-green-100">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-base font-semibold text-gray-500 uppercase tracking-wider">Support Apps</p>
            <p class="text-4xl font-extrabold text-green-600 mt-1"><?= count($support_apps) ?></p>
          </div>
          <div class="p-3 rounded-full bg-green-100 text-green-600 shadow-inner">
            <i class="fas fa-headset text-2xl"></i>
          </div>
        </div>
        <p class="text-sm text-gray-500 mt-4 border-t border-gray-100 pt-3">
          <span class="text-orange-600 font-bold">
            <?= count(array_filter($support_apps, fn($app) => $app['status'] === 'pending')) ?>
          </span> pending
        </p>
      </div>
      
      <div class="stat-card rounded-xl p-6 card-hover shadow-xl bg-white border border-purple-100">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-base font-semibold text-gray-500 uppercase tracking-wider">Admin Apps</p>
            <p class="text-4xl font-extrabold text-purple-600 mt-1"><?= count($admin_apps) ?></p>
          </div>
          <div class="p-3 rounded-full bg-purple-100 text-purple-600 shadow-inner">
            <i class="fas fa-user-shield text-2xl"></i>
          </div>
        </div>
        <p class="text-sm text-gray-500 mt-4 border-t border-gray-100 pt-3">
          <span class="text-orange-600 font-bold">
            <?= count(array_filter($admin_apps, fn($app) => $app['status'] === 'pending')) ?>
          </span> pending
        </p>
      </div>
      
      <div class="stat-card rounded-xl p-6 card-hover shadow-xl bg-white border border-red-100">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-base font-semibold text-gray-500 uppercase tracking-wider">Deletion Requests</p>
            <p class="text-4xl font-extrabold text-red-600 mt-1"><?= count($deletion_requests) ?></p>
          </div>
          <div class="p-3 rounded-full bg-red-100 text-red-600 shadow-inner">
            <i class="fas fa-user-minus text-2xl"></i>
          </div>
        </div>
        <p class="text-sm text-gray-500 mt-4 border-t border-gray-100 pt-3">
          <span class="text-red-600 font-bold">
            <?= count(array_filter($deletion_requests, fn($req) => $req['status'] === 'pending')) ?>
          </span> pending
        </p>
      </div>
    </div>

    <?php if (isset($_GET['message'])): ?>
      <?php
      $messages = [
          'seller_application_submitted' => ['type' => 'success', 'title' => 'Seller Application Submitted!', 'desc' => 'Your application has been received and is under review.'],
          'support_application_submitted' => ['type' => 'success', 'title' => 'Support Application Submitted!', 'desc' => 'Your application has been received and is under review.'],
          'admin_application_submitted' => ['type' => 'success', 'title' => 'Admin Application Submitted!', 'desc' => 'Your application has been received and is under review.'],
          'deletion_requested' => ['type' => 'info', 'title' => 'Deletion Request Submitted!', 'desc' => 'Your account deletion request has been received and will be processed soon.'],
          'already_approved' => ['type' => 'info', 'title' => 'Already a Seller!', 'desc' => 'You are already a fully approved seller. Manage your listings through the main dashboard.'],
          'already_admin' => ['type' => 'info', 'title' => 'Already an Admin!', 'desc' => 'You already have administrator privileges. No further application is required.'],
          'pending_seller_application_exists' => ['type' => 'warning', 'title' => 'Pending Application Exists', 'desc' => 'You have a pending seller application. Please wait for a decision before submitting another.'],
          'pending_support_application_exists' => ['type' => 'warning', 'title' => 'Pending Application Exists', 'desc' => 'You have a pending support agent application. Please wait for a decision before submitting another.'],
          'pending_admin_application_exists' => ['type' => 'warning', 'title' => 'Pending Application Exists', 'desc' => 'You have a pending admin application. Please wait for a decision before submitting another.'],
          
      ];
      $msg = $_GET['message'];
      if (isset($messages[$msg])) {
          $m = $messages[$msg];
          $color = $m['type'] === 'success' ? 'green' : ($m['type'] === 'warning' ? 'orange' : 'blue');
          $icon = $m['type'] === 'success' ? 'check-circle' : ($m['type'] === 'warning' ? 'exclamation-triangle' : 'info-circle');
          echo "
          <div class='mb-8 p-4 bg-{$color}-100 border-l-4 border-{$color}-600 text-{$color}-800 rounded-lg text-base flex items-center animate-slide-up shadow-md'>
            <i class='fas fa-{$icon} mr-4 text-{$color}-600 text-xl'></i>
            <div>
              <p class='font-bold text-lg'>{$m['title']}</p>
              <p class='text-sm opacity-90'>{$m['desc']}</p>
            </div>
          </div>";
      }
      ?>
    <?php endif; ?>

    <div class="mb-16 pt-4 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8">
        <h2 class="section-title seller-title text-3xl font-bold text-gray-900 mb-4 sm:mb-0">My Seller Applications</h2>
        <div class="flex space-x-4">
          <div class="bg-white px-5 py-2.5 rounded-lg shadow-md border border-gray-200">
            <span class="text-sm font-medium text-gray-500">Total:</span>
            <span class="ml-1 font-extrabold text-primary-600 text-lg"><?= count($seller_apps) ?></span>
          </div>
          <div class="bg-white px-5 py-2.5 rounded-lg shadow-md border border-gray-200">
            <span class="text-sm font-medium text-gray-500">Pending:</span>
            <span class="ml-1 font-extrabold text-orange-600 text-lg">
              <?= count(array_filter($seller_apps, fn($app) => $app['status'] === 'pending')) ?>
            </span>
          </div>
        </div>
      </div>

      <?php if (empty($seller_apps)): ?>
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-16 text-center">
          <div class="mx-auto w-24 h-24 bg-primary-100 rounded-full flex items-center justify-center mb-6">
            <i class="fas fa-store-alt text-4xl text-primary-500"></i>
          </div>
          <h3 class="text-2xl font-semibold text-gray-800 mb-2">No Seller Applications</h3>
          <p class="text-gray-500 mb-6">You haven't submitted any seller applications yet.</p>
          <a href="apply_seller.php" class="inline-flex items-center px-6 py-3 bg-primary-600 text-white font-semibold rounded-lg hover:bg-primary-700 transition duration-150">
            <i class="fas fa-plus mr-2"></i> Apply as Seller
          </a>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($seller_apps as $app): ?>
            <div class="bg-white rounded-xl shadow-lg border-t-8 border-primary-500 overflow-hidden card-hover">
              <div class="p-6">
                <div class="flex items-start justify-between mb-4 pb-4 border-b border-gray-100">
                  <h3 class="text-xl font-extrabold text-gray-900"> Application #<?= $app['id'] ?> </h3>
                  <div class="flex-shrink-0 ml-2">
                    <?php if ($app['status'] === 'approved'): ?>
                      <span class="status-badge bg-green-500 text-white"><i class="fas fa-check-circle mr-1"></i> Approved</span>
                    <?php elseif ($app['status'] === 'rejected'): ?>
                      <span class="status-badge bg-red-500 text-white"><i class="fas fa-times-circle mr-1"></i> Rejected</span>
                    <?php else: ?>
                      <span class="status-badge bg-orange-500 text-white"><i class="fas fa-clock mr-1"></i> Under Review</span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="text-sm mb-4">
                  <dl class="space-y-2">
                    <div class="flex justify-between border-b border-gray-100 pb-1">
                      <dt class="font-medium text-gray-500">Submitted On:</dt>
                      <dd class="font-bold text-gray-700"><?= date('M j, Y', strtotime($app['created_at'])) ?></dd>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 pb-1">
                      <dt class="font-medium text-gray-500">Business Address:</dt>
                      <dd class="text-right text-gray-700 truncate max-w-[50%]"><?= htmlspecialchars($app['business_address']) ?></dd>
                    </div>
                    <?php if ($app['business_license']): ?>
                    <div class="flex justify-between">
                      <dt class="font-medium text-gray-500">License:</dt>
                      <dd><a href="../uploads/<?= htmlspecialchars($app['business_license']) ?>" target="_blank" class="text-green-600 hover:text-green-700 font-bold">View File</a></dd>
                    </div>
                    <?php endif; ?>
                  </dl>
                </div>

                <div class="text-xs text-gray-500 mt-4 pt-4 border-t border-gray-100">
                  <p class="font-semibold uppercase tracking-wider mb-1">Reason for Application:</p>
                  <div id="seller-reason-<?= $app['id'] ?>" class="expandable-content">
                    <?= htmlspecialchars($app['role_reason']) ?>
                  </div>
                  <button onclick="toggleReadMore('seller-reason-<?= $app['id'] ?>', this)" class="read-more-btn">Read More...</button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    
    <div class="mb-16 pt-4 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8">
        <h2 class="section-title support-title text-3xl font-bold text-gray-900 mb-4 sm:mb-0">My Support Applications</h2>
        <div class="flex space-x-4">
          <div class="bg-white px-5 py-2.5 rounded-lg shadow-md border border-gray-200">
            <span class="text-sm font-medium text-gray-500">Total:</span>
            <span class="ml-1 font-extrabold text-green-600 text-lg"><?= count($support_apps) ?></span>
          </div>
          <div class="bg-white px-5 py-2.5 rounded-lg shadow-md border border-gray-200">
            <span class="text-sm font-medium text-gray-500">Pending:</span>
            <span class="ml-1 font-extrabold text-orange-600 text-lg">
              <?= count(array_filter($support_apps, fn($app) => $app['status'] === 'pending')) ?>
            </span>
          </div>
        </div>
      </div>

      <?php if (empty($support_apps)): ?>
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-16 text-center">
          <div class="mx-auto w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mb-6">
            <i class="fas fa-headset text-4xl text-green-500"></i>
          </div>
          <h3 class="text-2xl font-semibold text-gray-800 mb-2">No Support Applications</h3>
          <p class="text-gray-500 mb-6">You haven't applied to be a support agent yet.</p>
          <a href="apply_support.php" class="inline-flex items-center px-6 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition duration-150">
            <i class="fas fa-plus mr-2"></i> Apply as Support Agent
          </a>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($support_apps as $app): ?>
            <div class="bg-white rounded-xl shadow-lg border-t-8 border-support overflow-hidden card-hover">
              <div class="p-6">
                <div class="flex items-start justify-between mb-4 pb-4 border-b border-gray-100">
                  <h3 class="text-xl font-extrabold text-gray-900"> Application #<?= $app['id'] ?> </h3>
                  <div class="flex-shrink-0 ml-2">
                    <?php if ($app['status'] === 'approved'): ?>
                      <span class="status-badge bg-green-500 text-white"><i class="fas fa-check-circle mr-1"></i> Approved</span>
                    <?php elseif ($app['status'] === 'rejected'): ?>
                      <span class="status-badge bg-red-500 text-white"><i class="fas fa-times-circle mr-1"></i> Rejected</span>
                    <?php else: ?>
                      <span class="status-badge bg-orange-500 text-white"><i class="fas fa-clock mr-1"></i> Under Review</span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="text-sm mb-4">
                  <dl class="space-y-2">
                    <div class="flex justify-between border-b border-gray-100 pb-1">
                      <dt class="font-medium text-gray-500">Submitted On:</dt>
                      <dd class="font-bold text-gray-700"><?= date('M j, Y', strtotime($app['created_at'])) ?></dd>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 pb-1">
                      <dt class="font-medium text-gray-500">Resume:</dt>
                      <dd>
                        <?php if ($app['resume_filename']): ?>
                          <a href="../uploads/<?= htmlspecialchars($app['resume_filename']) ?>" target="_blank" class="text-green-600 hover:text-green-700 font-bold">View File</a>
                        <?php else: ?>
                          <span class="text-gray-500">N/A</span>
                        <?php endif; ?>
                      </dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="font-medium text-gray-500">Experience Summary:</dt>
                      <dd class="text-right text-gray-700 truncate max-w-[50%]"><?= htmlspecialchars(substr($app['experience'], 0, 30)) ?>...</dd>
                    </div>
                  </dl>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="text-xs text-gray-500">
                    <p class="font-semibold uppercase tracking-wider mb-1">Reason for Applying:</p>
                    <div id="support-reason-<?= $app['id'] ?>" class="expandable-content">
                      <?= htmlspecialchars($app['reason']) ?>
                    </div>
                    <button onclick="toggleReadMore('support-reason-<?= $app['id'] ?>', this)" class="read-more-btn">Read More...</button>
                  </div>
                  <div class="text-xs text-gray-500">
                    <p class="font-semibold uppercase tracking-wider mb-1">Availability:</p>
                    <div id="support-avail-<?= $app['id'] ?>" class="expandable-content">
                      <?= htmlspecialchars($app['availability']) ?>
                    </div>
                    <button onclick="toggleReadMore('support-avail-<?= $app['id'] ?>', this)" class="read-more-btn">Read More...</button>
                  </div>
                </div>
                </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="mb-16 pt-4 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8">
        <h2 class="section-title admin-title text-3xl font-bold text-gray-900 mb-4 sm:mb-0">My Admin Applications</h2>
        <div class="flex space-x-4">
          <div class="bg-white px-5 py-2.5 rounded-lg shadow-md border border-gray-200">
            <span class="text-sm font-medium text-gray-500">Total:</span>
            <span class="ml-1 font-extrabold text-purple-600 text-lg"><?= count($admin_apps) ?></span>
          </div>
          <div class="bg-white px-5 py-2.5 rounded-lg shadow-md border border-gray-200">
            <span class="text-sm font-medium text-gray-500">Pending:</span>
            <span class="ml-1 font-extrabold text-orange-600 text-lg">
              <?= count(array_filter($admin_apps, fn($app) => $app['status'] === 'pending')) ?>
            </span>
          </div>
        </div>
      </div>

      <?php if (empty($admin_apps)): ?>
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-16 text-center">
          <div class="mx-auto w-24 h-24 bg-purple-100 rounded-full flex items-center justify-center mb-6">
            <i class="fas fa-user-shield text-4xl text-purple-500"></i>
          </div>
          <h3 class="text-2xl font-semibold text-gray-800 mb-2">No Admin Applications</h3>
          <p class="text-gray-500 mb-6">You haven't applied for the Admin role yet. This role is highly selective.</p>
          <a href="apply_admin.php" class="inline-flex items-center px-6 py-3 bg-purple-600 text-white font-semibold rounded-lg hover:bg-purple-700 transition duration-150">
            <i class="fas fa-plus mr-2"></i> Apply as Admin
          </a>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($admin_apps as $app): ?>
            <div class="bg-white rounded-xl shadow-lg border-t-8 border-admin overflow-hidden card-hover">
              <div class="p-6">
                <div class="flex items-start justify-between mb-4 pb-4 border-b border-gray-100">
                  <h3 class="text-xl font-extrabold text-gray-900"> Application #<?= $app['id'] ?> </h3>
                  <div class="flex-shrink-0 ml-2">
                    <?php if ($app['status'] === 'approved'): ?>
                      <span class="status-badge bg-green-500 text-white"><i class="fas fa-check-circle mr-1"></i> Approved</span>
                    <?php elseif ($app['status'] === 'rejected'): ?>
                      <span class="status-badge bg-red-500 text-white"><i class="fas fa-times-circle mr-1"></i> Rejected</span>
                    <?php else: ?>
                      <span class="status-badge bg-orange-500 text-white"><i class="fas fa-clock mr-1"></i> Under Review</span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="text-sm mb-4">
                  <dl class="space-y-2">
                    <div class="flex justify-between border-b border-gray-100 pb-1">
                      <dt class="font-medium text-gray-500">Submitted On:</dt>
                      <dd class="font-bold text-gray-700"><?= date('M j, Y', strtotime($app['created_at'])) ?></dd>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 pb-1">
                      <dt class="font-medium text-gray-500">Contact Email:</dt>
                      <dd class="text-gray-700 truncate max-w-[50%]"><?= htmlspecialchars($app['email']) ?></dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="font-medium text-gray-500">Contact Phone:</dt>
                      <dd class="text-gray-700"><?= htmlspecialchars($app['phone']) ?: 'N/A' ?></dd>
                    </div>
                  </dl>
                </div>
                
                <div class="text-xs text-gray-500 mt-4 pt-4 border-t border-gray-100">
                  <p class="font-semibold uppercase tracking-wider mb-1">Reason for Applying:</p>
                  <div id="admin-reason-<?= $app['id'] ?>" class="expandable-content">
                    <?= htmlspecialchars($app['reason']) ?>
                  </div>
                  <button onclick="toggleReadMore('admin-reason-<?= $app['id'] ?>', this)" class="read-more-btn">Read More...</button>
                </div>

                <div class="text-xs text-gray-500 mt-4 pt-4 border-t border-gray-100">
                  <p class="font-semibold uppercase tracking-wider mb-1">Relevant Experience:</p>
                  <div id="admin-exp-<?= $app['id'] ?>" class="expandable-content">
                    <?= htmlspecialchars($app['experience']) ?>
                  </div>
                  <button onclick="toggleReadMore('admin-exp-<?= $app['id'] ?>', this)" class="read-more-btn">Read More...</button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="mb-16 pt-4 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8">
        <h2 class="section-title deletion-title text-3xl font-bold text-gray-900 mb-4 sm:mb-0">My Account Deletion Requests</h2>
        <div class="flex space-x-4">
          <div class="bg-white px-5 py-2.5 rounded-lg shadow-md border border-gray-200">
            <span class="text-sm font-medium text-gray-500">Total:</span>
            <span class="ml-1 font-extrabold text-red-600 text-lg"><?= count($deletion_requests) ?></span>
          </div>
          <div class="bg-white px-5 py-2.5 rounded-lg shadow-md border border-gray-200">
            <span class="text-sm font-medium text-gray-500">Pending:</span>
            <span class="ml-1 font-extrabold text-red-600 text-lg">
              <?= count(array_filter($deletion_requests, fn($req) => $req['status'] === 'pending')) ?>
            </span>
          </div>
        </div>
      </div>

      <?php if (empty($deletion_requests)): ?>
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-16 text-center">
          <div class="mx-auto w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mb-6">
            <i class="fas fa-user-minus text-4xl text-red-500"></i>
          </div>
          <h3 class="text-2xl font-semibold text-gray-800 mb-2">No Deletion Requests</h3>
          <p class="text-gray-500 mb-6">You have no pending or completed account deletion requests.</p>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($deletion_requests as $req): ?>
            <div class="bg-white rounded-xl shadow-lg border-t-8 border-deletion overflow-hidden card-hover">
              <div class="p-6">
                <div class="flex items-start justify-between mb-4 pb-4 border-b border-gray-100">
                  <h3 class="text-xl font-extrabold text-gray-900"> Deletion Request #<?= $req['request_id'] ?> </h3>
                  <div class="flex-shrink-0 ml-2">
                    <?php if ($req['status'] === 'approved'): ?>
                      <span class="status-badge bg-green-500 text-white"><i class="fas fa-check-circle mr-1"></i> Completed</span>
                    <?php elseif ($req['status'] === 'rejected'): ?>
                      <span class="status-badge bg-red-500 text-white"><i class="fas fa-times-circle mr-1"></i> Rejected</span>
                    <?php else: ?>
                      <span class="status-badge bg-red-500 text-white"><i class="fas fa-clock mr-1"></i> Pending</span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="text-sm mb-4">
                  <dl class="space-y-2">
                    <div class="flex justify-between border-b border-gray-100 pb-1">
                      <dt class="font-medium text-gray-500">Requested On:</dt>
                      <dd class="font-bold text-gray-700"><?= date('M j, Y H:i', strtotime($req['requested_at'])) ?></dd>
                    </div>
                    <div class="flex justify-between border-b border-gray-100 pb-1">
                      <dt class="font-medium text-gray-500">Request Status:</dt>
                      <dd class="font-bold text-red-600"><?= ucfirst($req['status']) ?></dd>
                    </div>
                    <div class="flex justify-between">
                      <dt class="font-medium text-gray-500">Current Role:</dt>
                      <dd class="font-bold text-red-600"><?= ucfirst($req['role']) ?></dd>
                    </div>
                  </dl>
                </div>

                <?php if ($req['status'] === 'pending'): ?>
                <div class="bg-red-100 border-l-4 border-red-600 p-4 rounded-r-lg mt-4 shadow-sm">
                  <p class="text-sm font-bold text-red-900 uppercase tracking-wide mb-1 flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i> UNDER REVIEW
                  </p>
                  <p class="text-xs text-red-800 leading-relaxed">
                    Your account deletion request is being reviewed by our team. You will be notified once a decision is made.
                  </p>
                </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <?php include '../includes/footer.php'; ?>

  <script>
    /**
     * Toggles the "Read More" functionality for any application detail.
     * It adds/removes the 'expanded' class to show/hide the full content.
     */
    function toggleReadMore(contentId, button) {
        const contentDiv = document.getElementById(contentId);
        
        if (contentDiv.classList.contains('expanded')) {
            contentDiv.classList.remove('expanded');
            button.textContent = 'Read More...';
        } else {
            contentDiv.classList.add('expanded');
            button.textContent = 'Show Less';
        }
    }
    
    // Auto-check to see if Read More button is necessary
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.expandable-content').forEach(contentDiv => {
            const button = contentDiv.nextElementSibling;
            
            // Temporarily set max-height to 'none' to check full scroll height
            const originalMaxHeight = contentDiv.style.maxHeight;
            contentDiv.style.maxHeight = 'none';
            
            // If the element has overflow, the scrollHeight will be greater than the clientHeight
            // We use a constant check (100px from CSS) to ensure the button is only hidden if the content 
            // is shorter than the collapsed height.
            
            // Restore original max-height
            contentDiv.style.maxHeight = originalMaxHeight;

            if (contentDiv.scrollHeight <= 100) { // Using 100px as the approximate 'collapsed' height
                button.style.display = 'none';
            }
        });
    });
  </script>
</body>
</html>