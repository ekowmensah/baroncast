<?php
/**
 * Save General Settings Action
 * Handles saving of general system settings including PWA icon upload
 */

session_start();
require_once '../../config/database.php';
require_once 'generate-pwa-icons.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    $uploadDir = '../../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Handle logo upload
    $logoPath = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logoFile = $_FILES['logo'];
        $logoExtension = strtolower(pathinfo($logoFile['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($logoExtension, $allowedExtensions)) {
            $logoFilename = 'logo_' . time() . '.' . $logoExtension;
            $logoPath = $uploadDir . $logoFilename;
            
            if (move_uploaded_file($logoFile['tmp_name'], $logoPath)) {
                $logoPath = 'uploads/' . $logoFilename;
            } else {
                throw new Exception('Failed to upload logo');
            }
        } else {
            throw new Exception('Invalid logo file type');
        }
    }
    
    // Handle PWA icon upload and generation
    $pwaIconPath = null;
    if (isset($_FILES['pwa_icon']) && $_FILES['pwa_icon']['error'] === UPLOAD_ERR_OK) {
        $pwaIconFile = $_FILES['pwa_icon'];
        $pwaIconExtension = strtolower(pathinfo($pwaIconFile['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($pwaIconExtension, $allowedExtensions)) {
            $pwaIconFilename = 'pwa_icon_' . time() . '.' . $pwaIconExtension;
            $pwaIconPath = $uploadDir . $pwaIconFilename;
            
            if (move_uploaded_file($pwaIconFile['tmp_name'], $pwaIconPath)) {
                // Generate PWA icons
                $iconGenerator = new PWAIconGenerator();
                $iconGenerator->cleanupOldIcons();
                $iconResults = $iconGenerator->generateIcons($pwaIconPath);
                
                // Update manifest with site info
                $iconGenerator->updateManifest(
                    $_POST['site_name'] ?? 'E-Cast Voting Platform',
                    $_POST['site_name'] ?? 'E-Cast',
                    $_POST['site_description'] ?? 'Professional voting platform for events and awards'
                );
                
                $pwaIconPath = 'uploads/' . $pwaIconFilename;
            } else {
                throw new Exception('Failed to upload PWA icon');
            }
        } else {
            throw new Exception('Invalid PWA icon file type');
        }
    }
    
    // Prepare settings to save
    $settings = [
        'site_name' => $_POST['site_name'] ?? '',
        'site_description' => $_POST['site_description'] ?? '',
        'site_keywords' => $_POST['site_keywords'] ?? '',
        'admin_email' => $_POST['admin_email'] ?? '',
        'contact_email' => $_POST['contact_email'] ?? '',
        'contact_phone' => $_POST['contact_phone'] ?? '',
        'timezone' => $_POST['timezone'] ?? 'UTC',
        'date_format' => $_POST['date_format'] ?? 'Y-m-d',
        'time_format' => $_POST['time_format'] ?? 'H:i:s',
        'currency' => $_POST['currency'] ?? 'GHS',
        'currency_symbol' => $_POST['currency_symbol'] ?? '₵',
        'user_registration' => isset($_POST['user_registration']) ? '1' : '0',
        'auto_approve_events' => isset($_POST['auto_approve_events']) ? '1' : '0',
        'max_file_size' => $_POST['max_file_size'] ?? '5',
        'allowed_file_types' => $_POST['allowed_file_types'] ?? 'jpg,jpeg,png,gif',
        // PWA Settings
        'pwa_enabled' => isset($_POST['pwa_enabled']) ? '1' : '0',
        'pwa_app_name' => $_POST['pwa_app_name'] ?? $_POST['site_name'] ?? 'E-Cast Voting Platform',
        'pwa_short_name' => $_POST['pwa_short_name'] ?? 'E-Cast',
        'pwa_description' => $_POST['pwa_description'] ?? $_POST['site_description'] ?? 'Professional voting platform for events and awards',
        'pwa_theme_color' => $_POST['pwa_theme_color'] ?? '#007bff',
        'pwa_background_color' => $_POST['pwa_background_color'] ?? '#1a1a1a',
        'pwa_display_mode' => $_POST['pwa_display_mode'] ?? 'standalone'
    ];
    
    // Add logo path if uploaded
    if ($logoPath) {
        $settings['site_logo'] = $logoPath;
    }
    
    // Add PWA icon path if uploaded
    if ($pwaIconPath) {
        $settings['pwa_icon'] = $pwaIconPath;
    }
    
    // Handle logo removal
    if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
        $settings['site_logo'] = '';
    }
    
    // Handle PWA icon removal
    if (isset($_POST['remove_pwa_icon']) && $_POST['remove_pwa_icon'] === '1') {
        $settings['pwa_icon'] = '';
        // Clean up generated icons
        $iconGenerator = new PWAIconGenerator();
        $iconGenerator->cleanupOldIcons();
    }
    
    // Save settings to database
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    
    foreach ($settings as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    
    // Update PWA manifest with new settings
    updatePWAManifest($settings);
    
    echo json_encode([
        'success' => true,
        'message' => 'Settings saved successfully!',
        'pwa_icons_generated' => isset($iconResults) ? count($iconResults) : 0,
        'manifest_updated' => true
    ]);
    
} catch (Exception $e) {
    error_log("Save settings error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save settings: ' . $e->getMessage()
    ]);
}

/**
 * Update PWA manifest.json with current settings
 */
function updatePWAManifest($settings) {
    $manifestPath = '../../manifest.json';
    
    // Create base manifest structure
    $manifest = [
        'name' => $settings['pwa_app_name'] ?? 'E-Cast Voting Platform',
        'short_name' => $settings['pwa_short_name'] ?? 'E-Cast',
        'description' => $settings['pwa_description'] ?? 'Professional voting platform for events and awards',
        'start_url' => '/e-cast-voting-system/voter/',
        'display' => $settings['pwa_display_mode'] ?? 'standalone',
        'background_color' => $settings['pwa_background_color'] ?? '#1a1a1a',
        'theme_color' => $settings['pwa_theme_color'] ?? '#007bff',
        'orientation' => 'portrait-primary',
        'scope' => '/e-cast-voting-system/',
        'lang' => 'en',
        'categories' => ['voting', 'events', 'awards'],
        'icons' => [],
        'shortcuts' => [
            [
                'name' => 'Vote Now',
                'short_name' => 'Vote',
                'description' => 'Cast your vote in ongoing events',
                'url' => '/e-cast-voting-system/voter/events.php',
                'icons' => [
                    [
                        'src' => 'assets/icons/icon-192x192.png',
                        'sizes' => '192x192'
                    ]
                ]
            ],
            [
                'name' => 'View Results',
                'short_name' => 'Results',
                'description' => 'Check voting results',
                'url' => '/e-cast-voting-system/voter/results.php',
                'icons' => [
                    [
                        'src' => 'assets/icons/icon-192x192.png',
                        'sizes' => '192x192'
                    ]
                ]
            ]
        ]
    ];
    
    // Add icons if they exist
    $iconSizes = [72, 96, 128, 144, 152, 192, 384, 512];
    foreach ($iconSizes as $size) {
        $iconPath = "assets/icons/icon-{$size}x{$size}.png";
        if (file_exists("../../{$iconPath}")) {
            $manifest['icons'][] = [
                'src' => $iconPath,
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
                'purpose' => 'maskable any'
            ];
        }
    }
    
    // Save updated manifest
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    
    return true;
}
?>
