<?php
session_start();
include 'includes/config.php';

// Admin check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}
$roles = explode(',', $_SESSION['role']);
if (!in_array('admin', $roles)) {
    header("Location: ../login.php");
    exit();
}

// === Fetch All Request Types ===
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
        ORDER BY sa.created_at DESC
    ");
    $stmt->execute();
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
            additional_info,
            resume_filename,
            status,
            created_at
        FROM support_applications
        ORDER BY created_at DESC
    ");
    $stmt->execute();
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
            status,
            created_at
        FROM admin_applications
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $admin_apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch admin apps: " . $e->getMessage());
}

// 4. Account Deletion Requests
$deletion_requests = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            adr.id AS request_id,
            adr.user_id,
            adr.requested_at,
            adr.status,
            adr.processed_at,
            COALESCE(u.name, dua.name) AS name,
            COALESCE(u.email, dua.email) AS email,
            COALESCE(u.role, dua.role) AS role
        FROM account_deletion_requests adr
        LEFT JOIN users u ON adr.user_id = u.id
        LEFT JOIN deleted_users_archive dua ON adr.user_id = dua.original_user_id
        ORDER BY adr.requested_at DESC
    ");
    $stmt->execute();
    $deletion_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to fetch deletion requests: " . $e->getMessage());
}

$pending_deletion_requests = array_filter($deletion_requests, fn($req) => $req['status'] === 'pending');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>User Requests - Admin Panel</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: { 50: '#eff6ff', 100: '#dbeafe', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' },
            secondary: { 50: '#f9fafb', 100: '#f3f4f6', 500: '#6b7280', 600: '#4b5563', 700: '#374151' },
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
            fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
            slideUp: { '0%': { transform: 'translateY(10px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } }
          }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; min-height: 100vh; }
    .card-hover { transition: all 0.3s cubic-bezier(0.4,0,0.2,1); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    .card-hover:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    .status-badge { padding: 0.2rem 0.5rem; border-radius: 0.375rem; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; }
    .stat-card { background-color: #ffffff; border: 1px solid #e5e7eb; }
    .section-title { position: relative; padding-left: 1.5rem; font-size: 1.75rem; }
    .section-title::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); height: 70%; width: 5px; border-radius: 3px; }
    .seller-title::before { background-color: #3b82f6; }
    .support-title::before { background-color: #10b981; }
    .admin-title::before { background-color: #8b5cf6; }
    .deletion-title::before { background-color: #ef4444; }
    .expandable-content { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; transition: all 0.3s ease-in-out; }
    .expandable-content.expanded { -webkit-line-clamp: unset; overflow: visible; }
    .read-more-btn { @apply text-sm text-primary-600 hover:text-primary-700 font-semibold mt-1 inline-block transition duration-150; }
    .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  </style>
</head>
<body class="text-gray-800">

  <?php include 'includes/admin_header.php'; ?>

  <div class="container mx-auto px-4 py-10 max-w-7xl">
    <div class="mb-10 border-b pb-4 border-gray-200">
      <h1 class="text-4xl font-extrabold text-gray-900 mb-2">User Requests Dashboard <span role="img" aria-label="Clipboard">📋</span></h1>
      <p class="text-gray-600 text-lg">Central hub to manage all role applications and account termination requests.</p>
    </div>

    <!-- Stats & Messages (unchanged) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
      <!-- ... your existing stats ... -->
      <div class="stat-card rounded-xl p-6 card-hover shadow-lg">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-base font-semibold text-gray-500 uppercase tracking-wider">Seller Apps</p>
            <p class="text-3xl font-extrabold text-gray-900 mt-1"><?= count($seller_apps) ?></p>
          </div>
          <div class="p-3 rounded-xl bg-primary-50 text-primary-600">
            <i class="fas fa-store text-2xl"></i>
          </div>
        </div>
        <p class="text-sm text-gray-500 mt-3 border-t border-gray-100 pt-3">
          <span class="text-orange-600 font-bold">
            <?= count(array_filter($seller_apps, fn($app) => $app['status'] === 'pending')) ?>
          </span> pending review
        </p>
      </div>
      <div class="stat-card rounded-xl p-6 card-hover shadow-lg">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-base font-semibold text-gray-500 uppercase tracking-wider">Support Apps</p>
            <p class="text-3xl font-extrabold text-gray-900 mt-1"><?= count($support_apps) ?></p>
          </div>
          <div class="p-3 rounded-xl bg-green-50 text-green-600">
            <i class="fas fa-headset text-2xl"></i>
          </div>
        </div>
        <p class="text-sm text-gray-500 mt-3 border-t border-gray-100 pt-3">
          <span class="text-orange-600 font-bold">
            <?= count(array_filter($support_apps, fn($app) => $app['status'] === 'pending')) ?>
          </span> pending review
        </p>
      </div>
      <div class="stat-card rounded-xl p-6 card-hover shadow-lg">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-base font-semibold text-gray-500 uppercase tracking-wider">Admin Apps</p>
            <p class="text-3xl font-extrabold text-gray-900 mt-1"><?= count($admin_apps) ?></p>
          </div>
          <div class="p-3 rounded-xl bg-purple-50 text-purple-600">
            <i class="fas fa-user-shield text-2xl"></i>
          </div>
        </div>
        <p class="text-sm text-gray-500 mt-3 border-t border-gray-100 pt-3">
          <span class="text-orange-600 font-bold">
            <?= count(array_filter($admin_apps, fn($app) => $app['status'] === 'pending')) ?>
          </span> pending review
        </p>
      </div>
      <div class="stat-card rounded-xl p-6 card-hover shadow-lg">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-base font-semibold text-gray-500 uppercase tracking-wider">Deletion Requests</p>
            <p class="text-3xl font-extrabold text-gray-900 mt-1"><?= count($deletion_requests) ?></p>
          </div>
          <div class="p-3 rounded-xl bg-red-50 text-red-600">
            <i class="fas fa-user-minus text-2xl"></i>
          </div>
        </div>
        <p class="text-sm text-gray-500 mt-3 border-t border-gray-100 pt-3">
          <span class="text-red-600 font-bold"><?= count($pending_deletion_requests) ?></span> require immediate action
        </p>
      </div>
    </div>

    <?php if (isset($_GET['message'])): ?>
      <?php
      $messages = [
          'application_approved' => ['type' => 'success', 'title' => 'Application Approved!', 'desc' => 'The user has been successfully upgraded and notified.'],
          'application_rejected' => ['type' => 'error', 'title' => 'Application Rejected.', 'desc' => 'The user has been notified of the decision.'],
          'support_application_approved' => ['type' => 'success', 'title' => 'Support Application Approved!', 'desc' => 'The user is now a support agent.'],
          'admin_application_approved' => ['type' => 'success', 'title' => 'Admin Application Approved!', 'desc' => 'The user now has admin access.'],
          'deletion_approved' => ['type' => 'success', 'title' => 'Account Deletion Approved!', 'desc' => 'The user account has been permanently removed and archived.'],
          'deletion_rejected' => ['type' => 'info', 'title' => 'Deletion Request Rejected.', 'desc' => 'The user will be notified that their account was not deleted.'],
      ];
      $msg = $_GET['message'];
      if (isset($messages[$msg])) {
          $m = $messages[$msg];
          $color = $m['type'] === 'success' ? 'green' : ($m['type'] === 'error' ? 'red' : 'blue');
          $icon = $m['type'] === 'success' ? 'check-circle' : ($m['type'] === 'error' ? 'times-circle' : 'info-circle');
          echo "
          <div class='mb-8 p-4 bg-{$color}-50 border-l-4 border-{$color}-500 text-{$color}-800 rounded-lg text-base flex items-center animate-slide-up shadow-md'>
            <i class='fas fa-{$icon} mr-4 text-{$color}-600 text-xl'></i>
            <div>
              <p class='font-bold text-lg'>{$m['title']}</p>
              <p class='text-sm opacity-90'>{$m['desc']}</p>
            </div>
          </div>";
      }
      ?>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div class='mb-8 p-4 bg-red-50 border-l-4 border-red-500 text-red-800 rounded-lg text-base flex items-center animate-slide-up shadow-md'>
        <i class='fas fa-exclamation-circle mr-4 text-red-600 text-xl'></i>
        <div>
          <p class='font-bold text-lg'>Processing Error</p>
          <p class='text-sm opacity-90'>There was an error processing your request. Please try again.</p>
        </div>
      </div>
    <?php endif; ?>

    <!-- SELLER APPLICATIONS -->
    <div class="mb-14 pt-4 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8">
        <h2 class="section-title seller-title text-3xl font-bold text-gray-900 mb-4 sm:mb-0">Seller Applications <span role="img" aria-label="Store">🏪</span></h2>
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
          <h3 class="text-2xl font-semibold text-gray-800 mb-2">Queue Empty</h3>
          <p class="text-gray-500">There are no new seller applications awaiting your review.</p>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($seller_apps as $app): ?>
            <div id="app-seller-<?= $app['id'] ?>" data-user-id="<?= $app['user_id'] ?>" class="bg-white rounded-xl shadow-lg border-t-4 border-primary-500 overflow-hidden card-hover flex flex-col justify-between">
              <!-- ... your existing seller card content ... -->
              <div class="p-6 flex-grow">
                <div class="flex items-start justify-between mb-4 border-b pb-4 border-gray-100">
                  <div class="flex items-center">
                    <div class="w-10 h-10 bg-primary-100 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                      <span class="text-primary-600 font-extrabold text-lg"><?= strtoupper(substr($app['name'], 0, 1)) ?></span>
                    </div>
                    <div>
                      <h3 class="text-lg font-bold text-gray-900 truncate" title="<?= htmlspecialchars($app['name']) ?>"><?= htmlspecialchars($app['name']) ?></h3>
                      <p class="text-gray-500 text-xs font-medium truncate"><i class="fas fa-envelope mr-1"></i> <?= htmlspecialchars($app['email']) ?></p>
                    </div>
                  </div>
                  <div class="flex-shrink-0 ml-2">
                    <?php if ($app['status'] === 'approved'): ?>
                      <span class="status-badge bg-green-500 text-white">Approved</span>
                    <?php elseif ($app['status'] === 'rejected'): ?>
                      <span class="status-badge bg-red-500 text-white">Rejected</span>
                    <?php else: ?>
                      <span class="status-badge bg-orange-500 text-white animate-pulse">Pending</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="space-y-3 text-sm text-gray-700 mb-4">
                  <p class="flex items-center"><i class="fas fa-phone mr-3 w-4 text-primary-500"></i><span class="font-medium"><?= htmlspecialchars($app['phone']) ?></span></p>
                  <div class="flex items-start">
                    <i class="fas fa-map-marker-alt mr-3 w-4 text-primary-500 mt-1"></i>
                    <div>
                      <span class="font-medium text-xs text-gray-500 uppercase">Business Address:</span>
                      <p class="text-gray-700 mt-1"><?= htmlspecialchars($app['business_address']) ?></p>
                    </div>
                  </div>
                  <?php if ($app['website']): ?>
                  <p class="flex items-center truncate">
                    <i class="fas fa-globe mr-3 w-4 text-primary-500"></i>
                    <a href="<?= htmlspecialchars($app['website']) ?>" target="_blank" class="text-primary-600 hover:underline truncate">
                      <?= htmlspecialchars(str_replace(['http://', 'https://'], '', $app['website'])) ?>
                    </a>
                  </p>
                  <?php endif; ?>
                  <?php if ($app['business_license']): ?>
                  <p class="flex items-center">
                    <i class="fas fa-file-invoice mr-3 w-4 text-green-500"></i>
                    <a href="../uploads/<?= htmlspecialchars($app['business_license']) ?>" target="_blank" class="text-green-600 hover:underline">View License</a>
                  </p>
                  <?php endif; ?>
                </div>
                <div class="text-xs text-gray-500 mt-4 pt-4 border-t border-gray-100">
                  <p class="font-semibold uppercase tracking-wider mb-1">Reason for Applying:</p>
                  <div class="reason-container">
                    <div id="seller-reason-<?= $app['id'] ?>" class="expandable-content leading-relaxed text-gray-700 bg-blue-50 p-3 rounded-lg border border-blue-100">
                      <?= htmlspecialchars($app['role_reason']) ?>
                    </div>
                    <?php if (strlen($app['role_reason']) > 150): ?>
                    <button type="button" class="read-more-btn" onclick="toggleReadMore('seller-reason-<?= $app['id'] ?>')" id="read-more-btn-seller-reason-<?= $app['id'] ?>"> Read More </button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <?php if ($app['status'] === 'pending'): ?>
              <div class="p-6 pt-0 mt-auto">
                <div class="grid grid-cols-2 gap-3">
                  <form method="POST" action="process_application.php" onsubmit="return confirm('Approve this seller application?')">
                    <input type="hidden" name="type" value="seller">
                    <input type="hidden" name="id" value="<?= $app['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="w-full text-sm py-2 px-3 rounded-lg font-semibold text-white bg-green-600 hover:bg-green-700 transition duration-150 shadow-md hover:shadow-lg">
                      <i class="fas fa-check mr-1"></i> Approve
                    </button>
                  </form>
                  <form method="POST" action="process_application.php" onsubmit="return confirm('Reject this seller application?')">
                    <input type="hidden" name="type" value="seller">
                    <input type="hidden" name="id" value="<?= $app['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="w-full text-sm py-2 px-3 rounded-lg font-semibold text-white bg-red-600 hover:bg-red-700 transition duration-150 shadow-md hover:shadow-lg">
                      <i class="fas fa-times mr-1"></i> Reject
                    </button>
                  </form>
                </div>
              </div>
              <?php else: ?>
              <div class="p-6 pt-0 mt-auto">
                <p class="text-xs text-gray-500 pt-3 border-t border-gray-100">Decision made on <span class="font-medium text-gray-700"><?= date('M j, Y', strtotime($app['created_at'])) ?></span></p>
              </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <hr class="border-t border-gray-300 my-10" />

    <!-- SUPPORT APPLICATIONS -->
    <div class="mb-14 pt-4 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8">
        <h2 class="section-title support-title text-3xl font-bold text-gray-900 mb-4 sm:mb-0">Support Agent Applications <span role="img" aria-label="Headset">🎧</span></h2>
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
          <h3 class="text-2xl font-semibold text-gray-800 mb-2">Queue Empty</h3>
          <p class="text-gray-500">There are no new support agent applications awaiting your review.</p>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($support_apps as $app): ?>
            <div id="app-support-<?= $app['id'] ?>" data-user-id="<?= $app['user_id'] ?>" class="bg-white rounded-xl shadow-lg border-t-4 border-green-500 overflow-hidden card-hover flex flex-col justify-between">
              <!-- ... your existing support card content ... -->
              <div class="p-6 flex-grow">
                <div class="flex items-start justify-between mb-4 border-b pb-4 border-gray-100">
                  <div class="flex items-center">
                    <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                      <span class="text-green-600 font-extrabold text-lg"><?= strtoupper(substr($app['name'], 0, 1)) ?></span>
                    </div>
                    <div>
                      <h3 class="text-lg font-bold text-gray-900 truncate" title="<?= htmlspecialchars($app['name']) ?>"><?= htmlspecialchars($app['name']) ?></h3>
                      <p class="text-gray-500 text-xs font-medium truncate"><i class="fas fa-envelope mr-1"></i> <?= htmlspecialchars($app['email']) ?></p>
                    </div>
                  </div>
                  <div class="flex-shrink-0 ml-2">
                    <?php if ($app['status'] === 'approved'): ?>
                      <span class="status-badge bg-green-500 text-white">Approved</span>
                    <?php elseif ($app['status'] === 'rejected'): ?>
                      <span class="status-badge bg-red-500 text-white">Rejected</span>
                    <?php else: ?>
                      <span class="status-badge bg-orange-500 text-white animate-pulse">Pending</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="space-y-3 text-sm text-gray-700 mb-4">
                  <p class="flex items-center"><i class="fas fa-phone mr-3 w-4 text-green-500"></i><span class="font-medium"><?= htmlspecialchars($app['phone']) ?></span></p>
                  <p class="flex items-center"><i class="fas fa-clock mr-3 w-4 text-green-500"></i><span class="font-medium"><?= htmlspecialchars($app['availability']) ?></span></p>
                  <?php if ($app['resume_filename']): ?>
                  <p class="flex items-center">
                    <i class="fas fa-file-pdf mr-3 w-4 text-red-500"></i>
                    <a href="../uploads/resumes/<?= htmlspecialchars($app['resume_filename']) ?>" target="_blank" class="text-red-600 hover:underline">View Resume</a>
                  </p>
                  <?php endif; ?>
                </div>
                <div class="text-xs text-gray-500 mt-4 pt-4 border-t border-gray-100">
                  <p class="font-semibold uppercase tracking-wider mb-1">Relevant Experience:</p>
                  <div class="reason-container">
                    <div id="support-exp-<?= $app['id'] ?>" class="expandable-content leading-relaxed text-gray-700 bg-green-50 p-3 rounded-lg border border-green-100">
                      <?= htmlspecialchars($app['experience']) ?>
                    </div>
                    <?php if (strlen($app['experience']) > 150): ?>
                    <button type="button" class="read-more-btn" onclick="toggleReadMore('support-exp-<?= $app['id'] ?>')" id="read-more-btn-support-exp-<?= $app['id'] ?>"> Read More </button>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="text-xs text-gray-500 mt-4 pt-4 border-t border-gray-100">
                  <p class="font-semibold uppercase tracking-wider mb-1">Reason for Applying:</p>
                  <div class="reason-container">
                    <div id="support-reason-<?= $app['id'] ?>" class="expandable-content leading-relaxed text-gray-700 bg-green-50 p-3 rounded-lg border border-green-100">
                      <?= htmlspecialchars($app['reason']) ?>
                    </div>
                    <?php if (strlen($app['reason']) > 150): ?>
                    <button type="button" class="read-more-btn" onclick="toggleReadMore('support-reason-<?= $app['id'] ?>')" id="read-more-btn-support-reason-<?= $app['id'] ?>"> Read More </button>
                    <?php endif; ?>
                  </div>
                </div>
                <?php if ($app['additional_info']): ?>
                <div class="text-xs text-gray-500 mt-4 pt-4 border-t border-gray-100">
                  <p class="font-semibold uppercase tracking-wider mb-1">Additional Info:</p>
                  <div class="reason-container">
                    <div id="support-add-<?= $app['id'] ?>" class="expandable-content leading-relaxed text-gray-700 bg-green-50 p-3 rounded-lg border border-green-100">
                      <?= htmlspecialchars($app['additional_info']) ?>
                    </div>
                    <?php if (strlen($app['additional_info']) > 150): ?>
                    <button type="button" class="read-more-btn" onclick="toggleReadMore('support-add-<?= $app['id'] ?>')" id="read-more-btn-support-add-<?= $app['id'] ?>"> Read More </button>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endif; ?>
              </div>
              <?php if ($app['status'] === 'pending'): ?>
              <div class="p-6 pt-0 mt-auto">
                <div class="grid grid-cols-2 gap-3">
                  <form method="POST" action="process_application.php" onsubmit="return confirm('Approve this support application?')">
                    <input type="hidden" name="type" value="support">
                    <input type="hidden" name="id" value="<?= $app['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="w-full text-sm py-2 px-3 rounded-lg font-semibold text-white bg-green-600 hover:bg-green-700 transition duration-150 shadow-md hover:shadow-lg">
                      <i class="fas fa-check mr-1"></i> Approve
                    </button>
                  </form>
                  <form method="POST" action="process_application.php" onsubmit="return confirm('Reject this support application?')">
                    <input type="hidden" name="type" value="support">
                    <input type="hidden" name="id" value="<?= $app['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="w-full text-sm py-2 px-3 rounded-lg font-semibold text-white bg-red-600 hover:bg-red-700 transition duration-150 shadow-md hover:shadow-lg">
                      <i class="fas fa-times mr-1"></i> Reject
                    </button>
                  </form>
                </div>
              </div>
              <?php else: ?>
              <div class="p-6 pt-0 mt-auto">
                <p class="text-xs text-gray-500 pt-3 border-t border-gray-100">Decision made on <span class="font-medium text-gray-700"><?= date('M j, Y', strtotime($app['created_at'])) ?></span></p>
              </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <hr class="border-t border-gray-300 my-10" />

    <!-- ADMIN APPLICATIONS -->
    <div class="mb-14 pt-4 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8">
        <h2 class="section-title admin-title text-3xl font-bold text-gray-900 mb-4 sm:mb-0">Admin Applications <span role="img" aria-label="Shield">🛡️</span></h2>
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
          <h3 class="text-2xl font-semibold text-gray-800 mb-2">Queue Empty</h3>
          <p class="text-gray-500">There are no new admin applications awaiting your review.</p>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($admin_apps as $app): ?>
            <div id="app-admin-<?= $app['id'] ?>" data-user-id="<?= $app['user_id'] ?>" class="bg-white rounded-xl shadow-lg border-t-4 border-purple-500 overflow-hidden card-hover flex flex-col justify-between">
              <!-- ... your existing admin card content ... -->
              <div class="p-6 flex-grow">
                <div class="flex items-start justify-between mb-4 border-b pb-4 border-gray-100">
                  <div class="flex items-center">
                    <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                      <span class="text-purple-600 font-extrabold text-lg"><?= strtoupper(substr($app['name'], 0, 1)) ?></span>
                    </div>
                    <div>
                      <h3 class="text-lg font-bold text-gray-900 truncate" title="<?= htmlspecialchars($app['name']) ?>"><?= htmlspecialchars($app['name']) ?></h3>
                      <p class="text-gray-500 text-xs font-medium truncate"><i class="fas fa-envelope mr-1"></i> <?= htmlspecialchars($app['email']) ?></p>
                    </div>
                  </div>
                  <div class="flex-shrink-0 ml-2">
                    <?php if ($app['status'] === 'approved'): ?>
                      <span class="status-badge bg-green-500 text-white">Approved</span>
                    <?php elseif ($app['status'] === 'rejected'): ?>
                      <span class="status-badge bg-red-500 text-white">Rejected</span>
                    <?php else: ?>
                      <span class="status-badge bg-orange-500 text-white animate-pulse">Pending</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="space-y-3 text-sm text-gray-700 mb-4">
                  <p class="flex items-center"><i class="fas fa-phone mr-3 w-4 text-purple-500"></i><span class="font-medium"><?= htmlspecialchars($app['phone']) ?></span></p>
                </div>
                <div class="text-xs text-gray-500 mt-4 pt-4 border-t border-gray-100">
                  <p class="font-semibold uppercase tracking-wider mb-1">Reason for Applying:</p>
                  <div class="reason-container">
                    <div id="admin-reason-<?= $app['id'] ?>" class="expandable-content leading-relaxed text-gray-700 bg-purple-50 p-3 rounded-lg border border-purple-100">
                      <?= htmlspecialchars($app['reason']) ?>
                    </div>
                    <?php if (strlen($app['reason']) > 150): ?>
                    <button type="button" class="read-more-btn" onclick="toggleReadMore('admin-reason-<?= $app['id'] ?>')" id="read-more-btn-admin-reason-<?= $app['id'] ?>"> Read More </button>
                    <?php endif; ?>
                  </div>
                </div>
                <?php if ($app['experience']): ?>
                <div class="text-xs text-gray-500 mt-4 pt-4 border-t border-gray-100">
                  <p class="font-semibold uppercase tracking-wider mb-1">Relevant Experience:</p>
                  <div class="reason-container">
                    <div id="admin-exp-<?= $app['id'] ?>" class="expandable-content leading-relaxed text-gray-700 bg-purple-50 p-3 rounded-lg border border-purple-100">
                      <?= htmlspecialchars($app['experience']) ?>
                    </div>
                    <?php if (strlen($app['experience']) > 150): ?>
                    <button type="button" class="read-more-btn" onclick="toggleReadMore('admin-exp-<?= $app['id'] ?>')" id="read-more-btn-admin-exp-<?= $app['id'] ?>"> Read More </button>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endif; ?>
              </div>
              <?php if ($app['status'] === 'pending'): ?>
              <div class="p-6 pt-0 mt-auto">
                <div class="grid grid-cols-2 gap-3">
                  <form method="POST" action="process_application.php" onsubmit="return confirm('Approve this admin application?')">
                    <input type="hidden" name="type" value="admin">
                    <input type="hidden" name="id" value="<?= $app['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="w-full text-sm py-2 px-3 rounded-lg font-semibold text-white bg-green-600 hover:bg-green-700 transition duration-150 shadow-md hover:shadow-lg">
                      <i class="fas fa-check mr-1"></i> Approve
                    </button>
                  </form>
                  <form method="POST" action="process_application.php" onsubmit="return confirm('Reject this admin application?')">
                    <input type="hidden" name="type" value="admin">
                    <input type="hidden" name="id" value="<?= $app['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="w-full text-sm py-2 px-3 rounded-lg font-semibold text-white bg-red-600 hover:bg-red-700 transition duration-150 shadow-md hover:shadow-lg">
                      <i class="fas fa-times mr-1"></i> Reject
                    </button>
                  </form>
                </div>
              </div>
              <?php else: ?>
              <div class="p-6 pt-0 mt-auto">
                <p class="text-xs text-gray-500 pt-3 border-t border-gray-100">Decision made on <span class="font-medium text-gray-700"><?= date('M j, Y', strtotime($app['created_at'])) ?></span></p>
              </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <hr class="border-t border-gray-300 my-10" />

    <!-- DELETION REQUESTS (no changes needed) -->
    <div class="mb-14 pt-4 animate-fade-in">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8">
        <h2 class="section-title deletion-title text-3xl font-bold text-gray-900 mb-4 sm:mb-0">Account Deletion Requests <span role="img" aria-label="Warning">⚠️</span></h2>
        <div class="flex space-x-4">
          <div class="bg-white px-5 py-2.5 rounded-lg shadow-md border border-gray-200">
            <span class="text-sm font-medium text-gray-500">Total:</span>
            <span class="ml-1 font-extrabold text-red-600 text-lg"><?= count($deletion_requests) ?></span>
          </div>
          <div class="bg-white px-5 py-2.5 rounded-lg shadow-md border border-gray-200">
            <span class="text-sm font-medium text-gray-500">Pending:</span>
            <span class="ml-1 font-extrabold text-orange-600 text-lg"><?= count($pending_deletion_requests) ?></span>
          </div>
        </div>
      </div>
      <?php if (empty($deletion_requests)): ?>
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-16 text-center">
          <div class="mx-auto w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mb-6">
            <i class="fas fa-user-minus text-4xl text-red-500"></i>
          </div>
          <h3 class="text-2xl font-semibold text-gray-800 mb-2">Queue Empty</h3>
          <p class="text-gray-500">There are no account deletion requests.</p>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($deletion_requests as $req): ?>
            <div class="bg-white rounded-xl shadow-lg border-t-4 border-red-500 overflow-hidden card-hover flex flex-col justify-between">
              <!-- ... your existing deletion card content ... -->
              <div class="p-6 flex-grow">
                <div class="flex items-start justify-between mb-4 border-b pb-4 border-gray-100">
                  <div class="flex items-center">
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                      <span class="text-red-600 font-extrabold text-lg"><?= strtoupper(substr($req['name'], 0, 1)) ?></span>
                    </div>
                    <div>
                      <h3 class="text-lg font-bold text-gray-900 truncate" title="<?= htmlspecialchars($req['name']) ?>"><?= htmlspecialchars($req['name']) ?></h3>
                      <p class="text-gray-500 text-xs font-medium truncate"><i class="fas fa-envelope mr-1"></i> <?= htmlspecialchars($req['email']) ?></p>
                    </div>
                  </div>
                  <div class="flex-shrink-0 ml-2">
                    <?php if ($req['status'] === 'approved'): ?>
                      <span class="status-badge bg-green-500 text-white">Approved</span>
                    <?php elseif ($req['status'] === 'rejected'): ?>
                      <span class="status-badge bg-red-500 text-white">Rejected</span>
                    <?php else: ?>
                      <span class="status-badge bg-orange-500 text-white animate-pulse">Pending</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="space-y-3 text-sm text-gray-700 mb-4">
                  <p class="flex items-center"><i class="fas fa-user-tag mr-3 w-4 text-red-500"></i><span class="font-medium"><?= htmlspecialchars($req['role']) ?></span></p>
                  <p class="flex items-center"><i class="fas fa-calendar-alt mr-3 w-4 text-red-500"></i><span class="font-medium">Requested: <?= date('M j, Y', strtotime($req['requested_at'])) ?></span></p>
                  <?php if ($req['processed_at']): ?>
                  <p class="flex items-center"><i class="fas fa-calendar-check mr-3 w-4 text-red-500"></i><span class="font-medium">Processed: <?= date('M j, Y', strtotime($req['processed_at'])) ?></span></p>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($req['status'] === 'pending'): ?>
              <div class="p-6 pt-0 mt-auto">
                <div class="grid grid-cols-2 gap-3">
                  <form method="POST" action="process_deletion.php" onsubmit="return confirm('PERMANENTLY DELETE this user account? This action cannot be undone!')">
                    <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                    <input type="hidden" name="user_id" value="<?= $req['user_id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="w-full text-sm py-2 px-3 rounded-lg font-semibold text-white bg-red-600 hover:bg-red-700 transition duration-150 shadow-md hover:shadow-lg">
                      <i class="fas fa-trash mr-1"></i> Delete
                    </button>
                  </form>
                  <form method="POST" action="process_deletion.php" onsubmit="return confirm('Reject this deletion request? The user account will remain active.')">
                    <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                    <input type="hidden" name="user_id" value="<?= $req['user_id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="w-full text-sm py-2 px-3 rounded-lg font-semibold text-white bg-gray-600 hover:bg-gray-700 transition duration-150 shadow-md hover:shadow-lg">
                      <i class="fas fa-times mr-1"></i> Reject
                    </button>
                  </form>
                </div>
              </div>
              <?php else: ?>
              <div class="p-6 pt-0 mt-auto">
                <p class="text-xs text-gray-500 pt-3 border-t border-gray-100">
                  Request was 
                  <?php if ($req['status'] === 'approved'): ?>
                    <span class="font-bold text-green-600">approved</span>
                  <?php else: ?>
                    <span class="font-bold text-red-600">rejected</span>
                  <?php endif; ?>
                  on <span class="font-medium text-gray-700"><?= date('M j, Y', strtotime($req['processed_at'])) ?></span>
                </p>
              </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- === AUTO-SCROLL & HIGHLIGHT SCRIPT === -->
<script>
  function toggleReadMore(elementId) {
    const element = document.getElementById(elementId);
    const button = document.getElementById('read-more-btn-' + elementId);
    if (element.classList.contains('expanded')) {
      element.classList.remove('expanded');
      button.textContent = 'Read More';
      element.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
      element.classList.add('expanded');
      button.textContent = 'Read Less';
      element.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }

  // ✅ FIXED: Auto-scroll to user's pending application
  document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const userId = urlParams.get('user_id');

    if (userId) {
      // Correct way: select ANY card with matching data-user-id
      const targetElement = document.querySelector(`[data-user-id="${userId}"]`);

      if (targetElement) {
        // Highlight
        targetElement.classList.add('ring-2', 'ring-yellow-400', 'ring-offset-2');
        // Scroll smoothly to center
        targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        // Remove highlight after 3 seconds
        setTimeout(() => {
          targetElement.classList.remove('ring-2', 'ring-yellow-400', 'ring-offset-2');
        }, 3000);
      }
    }
  });
</script>
</body>
</html>