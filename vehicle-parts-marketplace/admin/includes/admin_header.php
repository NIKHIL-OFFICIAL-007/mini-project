<?php
// admin/includes/admin_header.php

// ✅ Include the admin auth file (which already includes session and role check)
include 'admin_auth.php';

$user_name = htmlspecialchars($_SESSION['name']);
$role = htmlspecialchars($_SESSION['role']);

// ✅ Fetch total ticket count
$ticket_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets");
    $stmt->execute();
    $ticket_count = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Failed to fetch ticket count: " . $e->getMessage());
    $ticket_count = 0;
}

// ✅ FETCH TOTAL PENDING USER REQUESTS (Role Apps + Deletion Requests)
$pending_role_requests = 0;
try {
    // Count pending applications across all role types
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM admin_applications WHERE status = 'pending') +
            (SELECT COUNT(*) FROM seller_applications WHERE status = 'pending') +
            (SELECT COUNT(*) FROM support_applications WHERE status = 'pending') +
            (SELECT COUNT(*) FROM account_deletion_requests WHERE status = 'pending')
    ");
    $stmt->execute();
    $pending_role_requests = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Failed to fetch pending user requests count: " . $e->getMessage());
    $pending_role_requests = 0;
}

// ✅ FETCH REPORTED REVIEWS COUNT (NEW ADDITION)
$reported_reviews_count = 0;
try {
    // We count reviews that have the status 'reported'
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE status = 'reported'");
    $stmt->execute();
    $reported_reviews_count = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    error_log("Failed to fetch reported reviews count: " . $e->getMessage());
    $reported_reviews_count = 0;
}

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
$site_name = htmlspecialchars($site_settings['site_name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Admin Dashboard - <?= $site_name ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'sidebar-bg': '#0f172a',
                        'sidebar-active': '#1e40af',
                        'sidebar-hover': '#334155',
                        'primary': '#3b82f6',
                        'secondary': '#6c757d',
                        'success': '#10b981',
                        'danger': '#ef4444',
                        'warning': '#f59e0b',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-900">

    <div class="fixed left-0 top-0 w-72 h-full bg-sidebar-bg text-white">
        <div class="p-6 border-b border-gray-700 bg-gradient-to-b from-blue-900 to-sidebar-bg">
            <div class="flex items-center space-x-3">
                <i class="fas fa-tools text-2xl text-blue-400"></i>
                <div>
                    <h2 class="text-xl font-bold"><?= $site_name ?></h2>
                    <p class="text-blue-200 text-sm">Admin Panel</p>
                </div>
            </div>
        </div>

        <nav class="mt-4 px-2">
            <a href="../../index.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition mb-1">
                <i class="fas fa-home mr-3 text-lg"></i>
                <span>Home</span>
            </a>
            <a href="dashboard.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'bg-sidebar-active text-white' : '' ?>">
                <i class="fas fa-tachometer-alt mr-3"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage_users.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition <?= basename($_SERVER['PHP_SELF']) === 'manage_users.php' ? 'bg-sidebar-active text-white' : '' ?>">
                <i class="fas fa-users mr-3"></i>
                <span>Manage Users</span>
            </a>

            <a href="user_requests.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition relative <?= basename($_SERVER['PHP_SELF']) === 'user_requests.php' ? 'bg-sidebar-active text-white' : '' ?>">
                <i class="fas fa-user-shield mr-3"></i>
                <span>User Requests</span>
                <?php if ($pending_role_requests > 0): ?>
                    <span class="absolute right-3 top-1/2 transform -translate-y-1/2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full min-w-[1.5rem] flex items-center justify-center">
                        <?= $pending_role_requests ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="manage_parts.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition <?= basename($_SERVER['PHP_SELF']) === 'manage_parts.php' ? 'bg-sidebar-active text-white' : '' ?>">
                <i class="fas fa-cogs mr-3"></i>
                <span>Manage Parts</span>
            </a>
            
            <a href="manage_orders.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition <?= basename($_SERVER['PHP_SELF']) === 'manage_orders.php' ? 'bg-sidebar-active text-white' : '' ?>">
                <i class="fas fa-box-open mr-3"></i>
                <span>Orders</span>
            </a>

            <a href="manage_reviews.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition <?= basename($_SERVER['PHP_SELF']) === 'manage_reviews.php' ? 'bg-sidebar-active text-white' : '' ?>">
                <i class="fas fa-star-half-alt mr-3"></i>
                <span>Manage Reviews</span>
            </a>
            
            <a href="tickets.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-sidebar-hover hover:text-white rounded-lg transition <?= basename($_SERVER['PHP_SELF']) === 'tickets.php' ? 'bg-sidebar-active text-white' : '' ?>">
                <i class="fas fa-ticket-alt mr-3"></i>
                <span>Support Tickets (<?= $ticket_count ?>)</span>
            </a>
        </nav>

        <div class="absolute bottom-0 w-72 p-4 border-t border-gray-700 bg-gray-900">
            <a href="../../logout.php" class="flex items-center text-red-400 hover:text-red-300 hover:bg-red-900/20 rounded-lg px-6 py-3 transition">
                <i class="fas fa-sign-out-alt mr-3"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <div class="ml-72 p-8">
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-6 rounded-xl shadow-lg mb-8">
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-2xl font-bold">Admin Dashboard</h2>
                    <p class="text-blue-100 mt-1">Welcome back, <span class="font-semibold"><?= $user_name ?></span>! Manage your platform.</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-3 bg-white border border-gray-300 rounded-full px-4 py-2 shadow-sm">
                        <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold">
                            <?= substr($user_name, 0, 1) ?>
                        </div>

                        <div class="text-sm">
                            <div class="font-medium text-gray-800"><?= $user_name ?></div>
                            <div class="text-gray-500">Administrator</div>
                        </div>
                        <i class="fas fa-chevron-down text-gray-500 text-sm"></i>
                    </div>
                </div>
            </div>
        </div>