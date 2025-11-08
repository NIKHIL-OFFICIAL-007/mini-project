<?php
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
?>