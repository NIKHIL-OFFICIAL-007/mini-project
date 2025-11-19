<?php
// Get PDO instance if not already available
if (!isset($pdo)) {
    try {
        $pdo = getPDO();
    } catch (Exception $e) {
        error_log("Failed to get PDO in footer: " . $e->getMessage());
    }
}

// Helper function to get site settings (if not already defined)
if (!function_exists('getSiteSettings')) {
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
}

// Get site settings if not already available
if (!isset($site_settings) && isset($pdo)) {
    $site_settings = getSiteSettings($pdo);
} else if (!isset($site_settings)) {
    $site_settings = [
        'site_name' => 'AutoParts Hub',
        'contact_email' => 'support@autopartshub.com',
        'phone' => '+1 (555) 123-4567',
        'address' => '123 Auto Lane, Tech City, TC 10101'
    ];
}
?>

<footer id="contact" class="bg-gray-900 text-gray-300 py-16">
    <div class="container mx-auto px-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
            <div>
                <div class="flex items-center space-x-3 mb-4">
                    <i class="fas fa-tools text-2xl text-primary"></i>
                    <h3 class="text-xl font-bold text-white"><?= htmlspecialchars($site_settings['site_name']) ?></h3>
                </div>
                <p class="mb-4">A modern, secure, and efficient online marketplace for buying and selling vehicle parts.</p>
            </div>

            <div>
                <h4 class="text-lg font-semibold text-white mb-4">Quick Links</h4>
                <ul class="space-y-2">
                    <li><a href="#home" class="hover:text-white transition">Home</a></li>
                    <li><a href="#features" class="hover:text-white transition">Features</a></li>
                    <li><a href="#how-it-works" class="hover:text-white transition">How It Works</a></li>
                    <li><a href="register.php" class="hover:text-white transition">Register</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="buyer/browse_parts.php" class="hover:text-white transition">Browse Parts</a></li>
                        <li><a href="logout.php" class="hover:text-white transition">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="hover:text-white transition">Login</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div>
                <h4 class="text-lg font-semibold text-white mb-4">Contact Us</h4>
                <ul class="space-y-2 text-sm">
                    <li class="flex items-start space-x-2">
                        <i class="fas fa-envelope mt-1"></i>
                        <span><?= htmlspecialchars($site_settings['contact_email']) ?></span>
                    </li>
                    <li class="flex items-start space-x-2">
                        <i class="fas fa-phone mt-1"></i>
                        <span><?= htmlspecialchars($site_settings['phone']) ?></span>
                    </li>
                    <li class="flex items-start space-x-2">
                        <i class="fas fa-map-marker-alt mt-1"></i>
                        <span><?= htmlspecialchars($site_settings['address']) ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <hr class="border-gray-800 my-8" />
        <p class="text-center text-sm">
            &copy; <?= date("Y") ?> <?= htmlspecialchars($site_settings['site_name']) ?>. All rights reserved.
        </p>
    </div>
</footer>