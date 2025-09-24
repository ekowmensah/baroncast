<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit;
}

$db = new Database();
$pdo = $db->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle logo upload
        $logo_path = '';
        $current_logo = '';
        
        // Get current logo from database
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'site_logo'");
        $stmt->execute();
        $current_logo_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_logo = $current_logo_result['setting_value'] ?? '';
        
        // Handle logo removal
        if (isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1') {
            // Delete current logo file if it exists
            if (!empty($current_logo) && file_exists('../' . $current_logo)) {
                unlink('../' . $current_logo);
            }
            $logo_path = '';
        } else {
            // Keep current logo if no new upload
            $logo_path = $current_logo;
        }
        
        // Handle new logo upload
        if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/logos/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_info = pathinfo($_FILES['site_logo']['name']);
            $file_extension = strtolower($file_info['extension']);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                // Check file size (max 2MB)
                if ($_FILES['site_logo']['size'] <= 2 * 1024 * 1024) {
                    // Delete old logo if exists
                    if (!empty($current_logo) && file_exists('../' . $current_logo)) {
                        unlink('../' . $current_logo);
                    }
                    
                    // Generate unique filename
                    $filename = 'logo_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $upload_path)) {
                        $logo_path = 'uploads/logos/' . $filename;
                    } else {
                        throw new Exception('Failed to upload logo file.');
                    }
                } else {
                    throw new Exception('Logo file size must be less than 2MB.');
                }
            } else {
                throw new Exception('Invalid logo file type. Please use JPG, PNG, or GIF.');
            }
        }
        
        $settings = [
            'site_name' => $_POST['site_name'] ?? 'E-Cast Voting Platform',
            'site_description' => $_POST['site_description'] ?? '',
            'site_logo' => $logo_path,
            'admin_email' => $_POST['admin_email'] ?? '',
            'timezone' => $_POST['timezone'] ?? 'UTC',
            'date_format' => $_POST['date_format'] ?? 'Y-m-d',
            'time_format' => $_POST['time_format'] ?? 'H:i:s',
            'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
            'user_registration' => isset($_POST['user_registration']) ? 1 : 0,
            'auto_approve_events' => isset($_POST['auto_approve_events']) ? 1 : 0,
            'max_file_size' => (int)($_POST['max_file_size'] ?? 5),
            'allowed_file_types' => $_POST['allowed_file_types'] ?? 'jpg,jpeg,png,gif'
        ];
        
        // Update or insert settings
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, $value]);
        }
        
        $success_message = "General settings updated successfully!";
    } catch (Exception $e) {
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}

// Ensure system_settings table exists
try {
    $pdo->query("SELECT 1 FROM system_settings LIMIT 1");
} catch (PDOException $e) {
    // Table doesn't exist, create it
    $createTableSQL = "
        CREATE TABLE system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
            description TEXT,
            is_encrypted BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_setting_key (setting_key)
        )
    ";
    $pdo->exec($createTableSQL);
    
    // Insert default settings
    $defaultSettings = [
        ['site_name', 'E-Cast Voting Platform'],
        ['site_description', 'Secure and transparent online voting platform'],
        ['admin_email', 'admin@example.com'],
        ['timezone', 'Africa/Accra'],
        ['date_format', 'Y-m-d'],
        ['time_format', 'H:i:s'],
        ['maintenance_mode', '0'],
        ['user_registration', '1'],
        ['auto_approve_events', '0'],
        ['max_file_size', '5'],
        ['allowed_file_types', 'jpg,jpeg,png,gif']
    ];
    
    $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaultSettings as $setting) {
        $insertStmt->execute($setting);
    }
}

// Get current settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default values
$defaults = [
    'site_name' => 'E-Cast Voting Platform',
    'site_description' => 'Secure and transparent online voting platform',
    'admin_email' => 'admin@example.com',
    'timezone' => 'UTC',
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i:s',
    'max_file_size' => 5,
    'allowed_file_types' => 'jpg,jpeg,png,gif'
];
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Settings - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
$pageTitle = 'General Settings';
include 'includes/sidebar.php'; 
?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <?php include 'includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="content-wrapper">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">
                            <div class="page-header">
                                <h1><i class="fas fa-cogs"></i> General Settings</h1>
                                <p class="text-muted">Configure general system settings and preferences</p>
                            </div>
                            
                            <?php if (isset($success_message)): ?>
                                <div class="alert alert-success alert-dismissible fade show">
                                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($error_message)): ?>
                                <div class="alert alert-danger alert-dismissible fade show">
                                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card">
                                <div class="card-body">
                                    <form method="POST" enctype="multipart/form-data">
                                        <div class="row">
                                            <!-- Site Information -->
                                            <div class="col-lg-6">
                                                <div class="settings-section">
                                                    <h4><i class="fas fa-globe"></i> Site Information</h4>
                                                    
                                                    <div class="mb-3">
                                                        <label for="site_name" class="form-label">Site Name</label>
                                                        <input type="text" class="form-control" id="site_name" name="site_name" 
                                                               value="<?php echo htmlspecialchars($settings['site_name'] ?? $defaults['site_name']); ?>" 
                                                               required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="site_description" class="form-label">Site Description</label>
                                                        <textarea class="form-control" id="site_description" name="site_description" rows="3"
                                                                  placeholder="Brief description of your voting platform"><?php echo htmlspecialchars($settings['site_description'] ?? $defaults['site_description']); ?></textarea>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="admin_email" class="form-label">Admin Email</label>
                                                        <input type="email" class="form-control" id="admin_email" name="admin_email" 
                                                               value="<?php echo htmlspecialchars($settings['admin_email'] ?? $defaults['admin_email']); ?>" 
                                                               required>
                                                        <small class="text-muted">Primary contact email for system notifications</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Site Branding -->
                                            <div class="col-lg-6">
                                                <div class="settings-section">
                                                    <h4><i class="fas fa-image"></i> Site Branding</h4>
                                                    
                                                    <div class="mb-3">
                                                        <label for="site_logo" class="form-label">Site Logo</label>
                                                        <input type="file" class="form-control" id="site_logo" name="site_logo" 
                                                               accept="image/*" onchange="previewLogo(this)">
                                                        <small class="text-muted">Upload a logo for your site (PNG, JPG, GIF - Max 2MB)</small>
                                                        
                                                        <?php if (!empty($settings['site_logo'])): ?>
                                                            <div class="mt-2">
                                                                <label class="form-label">Current Logo:</label>
                                                                <div class="current-logo">
                                                                    <img src="../<?php echo htmlspecialchars($settings['site_logo']); ?>" 
                                                                         alt="Current Logo" style="max-height: 60px; max-width: 200px;">
                                                                    <div class="mt-1">
                                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                                onclick="removeLogo()">Remove Logo</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <div id="logo-preview" class="mt-2" style="display: none;">
                                                            <label class="form-label">Preview:</label>
                                                            <div>
                                                                <img id="logo-preview-img" src="" alt="Logo Preview" 
                                                                     style="max-height: 60px; max-width: 200px; border: 1px solid #ddd; padding: 5px;">
                                                            </div>
                                                        </div>
                                                        
                                                        <input type="hidden" id="remove_logo" name="remove_logo" value="0">
                                                    </div>
                                                    
                                                    <!-- PWA Icon Upload -->
                                                    <div class="mb-3">
                                                        <label for="pwa_icon" class="form-label">PWA App Icon</label>
                                                        <input type="file" class="form-control" id="pwa_icon" name="pwa_icon" 
                                                               accept="image/*" onchange="previewPWAIcon(this)">
                                                        <small class="text-muted">Upload PWA app icon (512x512 PNG recommended for best quality on mobile devices)</small>
                                                        
                                                        <?php if (!empty($settings['pwa_icon'])): ?>
                                                            <div class="mt-2">
                                                                <label class="form-label">Current PWA Icon:</label>
                                                                <div class="current-pwa-icon">
                                                                    <img src="../<?php echo htmlspecialchars($settings['pwa_icon']); ?>" 
                                                                         alt="Current PWA Icon" style="max-height: 60px; max-width: 60px; border: 1px solid #ddd; padding: 5px; border-radius: 8px;">
                                                                    <div class="mt-1">
                                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                                onclick="removePWAIcon()">Remove PWA Icon</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <div id="pwa-icon-preview" class="mt-2" style="display: none;">
                                                            <label class="form-label">Preview:</label>
                                                            <div>
                                                                <img id="pwa-icon-preview-img" src="" alt="PWA Icon Preview" 
                                                                     style="max-height: 60px; max-width: 60px; border: 1px solid #ddd; padding: 5px; border-radius: 8px;">
                                                            </div>
                                                        </div>
                                                        
                                                        <input type="hidden" id="remove_pwa_icon" name="remove_pwa_icon" value="0">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <div class="row">
                                            <!-- Regional Settings -->
                                            <div class="col-lg-6">
                                                <div class="settings-section">
                                                    <h4><i class="fas fa-clock"></i> Regional Settings</h4>
                                                    
                                                    <div class="mb-3">
                                                        <label for="timezone" class="form-label">Timezone</label>
                                                        <select class="form-select" id="timezone" name="timezone">
                                                            <option value="UTC" <?php echo ($settings['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                                            <option value="Africa/Accra" <?php echo ($settings['timezone'] ?? '') === 'Africa/Accra' ? 'selected' : ''; ?>>Africa/Accra (Ghana)</option>
                                                            <option value="Africa/Lagos" <?php echo ($settings['timezone'] ?? '') === 'Africa/Lagos' ? 'selected' : ''; ?>>Africa/Lagos (Nigeria)</option>
                                                            <option value="Africa/Nairobi" <?php echo ($settings['timezone'] ?? '') === 'Africa/Nairobi' ? 'selected' : ''; ?>>Africa/Nairobi (Kenya)</option>
                                                            <option value="Africa/Cairo" <?php echo ($settings['timezone'] ?? '') === 'Africa/Cairo' ? 'selected' : ''; ?>>Africa/Cairo (Egypt)</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="date_format" class="form-label">Date Format</label>
                                                        <select class="form-select" id="date_format" name="date_format">
                                                            <option value="Y-m-d" <?php echo ($settings['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                                            <option value="d/m/Y" <?php echo ($settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                                            <option value="m/d/Y" <?php echo ($settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                                            <option value="d-M-Y" <?php echo ($settings['date_format'] ?? '') === 'd-M-Y' ? 'selected' : ''; ?>>DD-MMM-YYYY</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="time_format" class="form-label">Time Format</label>
                                                        <select class="form-select" id="time_format" name="time_format">
                                                            <option value="H:i:s" <?php echo ($settings['time_format'] ?? 'H:i:s') === 'H:i:s' ? 'selected' : ''; ?>>24-hour (HH:MM:SS)</option>
                                                            <option value="h:i:s A" <?php echo ($settings['time_format'] ?? '') === 'h:i:s A' ? 'selected' : ''; ?>>12-hour (HH:MM:SS AM/PM)</option>
                                                            <option value="H:i" <?php echo ($settings['time_format'] ?? '') === 'H:i' ? 'selected' : ''; ?>>24-hour (HH:MM)</option>
                                                            <option value="h:i A" <?php echo ($settings['time_format'] ?? '') === 'h:i A' ? 'selected' : ''; ?>>12-hour (HH:MM AM/PM)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <div class="row">
                                            <!-- System Behavior -->
                                            <div class="col-lg-6">
                                                <div class="settings-section">
                                                    <h4><i class="fas fa-toggle-on"></i> System Behavior</h4>
                                                    
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                                                   <?php echo ($settings['maintenance_mode'] ?? '0') ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="maintenance_mode">
                                                                Maintenance Mode
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Temporarily disable public access for maintenance</small>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="user_registration" name="user_registration" 
                                                                   <?php echo ($settings['user_registration'] ?? '1') ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="user_registration">
                                                                Allow User Registration
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Allow new organizers to register accounts</small>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="auto_approve_events" name="auto_approve_events" 
                                                                   <?php echo ($settings['auto_approve_events'] ?? '0') ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="auto_approve_events">
                                                                Auto-approve Events
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Automatically approve new events without admin review</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- File Upload Settings -->
                                            <div class="col-lg-6">
                                                <div class="settings-section">
                                                    <h4><i class="fas fa-upload"></i> File Upload Settings</h4>
                                                    
                                                    <div class="mb-3">
                                                        <label for="max_file_size" class="form-label">Max File Size (MB)</label>
                                                        <input type="number" class="form-control" id="max_file_size" name="max_file_size" 
                                                               value="<?php echo $settings['max_file_size'] ?? $defaults['max_file_size']; ?>" 
                                                               min="1" max="50">
                                                        <small class="text-muted">Maximum file size for uploads (images, documents)</small>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="allowed_file_types" class="form-label">Allowed File Types</label>
                                                        <input type="text" class="form-control" id="allowed_file_types" name="allowed_file_types" 
                                                               value="<?php echo htmlspecialchars($settings['allowed_file_types'] ?? $defaults['allowed_file_types']); ?>" 
                                                               placeholder="jpg,jpeg,png,gif">
                                                        <small class="text-muted">Comma-separated list of allowed file extensions</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        

                                        
                                        <!-- PWA Settings Section -->
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <div class="settings-section">
                                                    <h4><i class="fas fa-mobile-alt"></i> Progressive Web App (PWA) Settings</h4>
                                                    <div class="alert alert-info">
                                                        <i class="fas fa-info-circle"></i>
                                                        <strong>PWA Settings:</strong> Configure your app for mobile installation. Users can install your voting platform as a native app on their phones.
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-lg-6">
                                                            <div class="mb-3">
                                                                <label for="pwa_app_name" class="form-label">App Name</label>
                                                                <input type="text" class="form-control" id="pwa_app_name" name="pwa_app_name" 
                                                                       value="<?php echo htmlspecialchars($settings['pwa_app_name'] ?? $settings['site_name'] ?? 'E-Cast Voting Platform'); ?>" 
                                                                       placeholder="E-Cast Voting Platform" maxlength="45">
                                                                <small class="text-muted">Full app name displayed during installation (max 45 characters)</small>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label for="pwa_short_name" class="form-label">Short Name</label>
                                                                <input type="text" class="form-control" id="pwa_short_name" name="pwa_short_name" 
                                                                       value="<?php echo htmlspecialchars($settings['pwa_short_name'] ?? 'E-Cast'); ?>" 
                                                                       placeholder="E-Cast" maxlength="12">
                                                                <small class="text-muted">Short name for app icon (max 12 characters)</small>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label for="pwa_description" class="form-label">App Description</label>
                                                                <textarea class="form-control" id="pwa_description" name="pwa_description" 
                                                                          rows="3" maxlength="200" placeholder="Professional voting platform for events and awards"><?php echo htmlspecialchars($settings['pwa_description'] ?? $settings['site_description'] ?? 'Professional voting platform for events and awards'); ?></textarea>
                                                                <small class="text-muted">Brief description of your app (max 200 characters)</small>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="col-lg-6">
                                                            <div class="mb-3">
                                                                <label for="pwa_theme_color" class="form-label">Theme Color</label>
                                                                <input type="color" class="form-control form-control-color" id="pwa_theme_color" name="pwa_theme_color" 
                                                                       value="<?php echo htmlspecialchars($settings['pwa_theme_color'] ?? '#007bff'); ?>" 
                                                                       title="Choose theme color">
                                                                <small class="text-muted">Primary color for app interface</small>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label for="pwa_background_color" class="form-label">Background Color</label>
                                                                <input type="color" class="form-control form-control-color" id="pwa_background_color" name="pwa_background_color" 
                                                                       value="<?php echo htmlspecialchars($settings['pwa_background_color'] ?? '#1a1a1a'); ?>" 
                                                                       title="Choose background color">
                                                                <small class="text-muted">Background color during app launch</small>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label for="pwa_display_mode" class="form-label">Display Mode</label>
                                                                <select class="form-select" id="pwa_display_mode" name="pwa_display_mode">
                                                                    <option value="standalone" <?php echo ($settings['pwa_display_mode'] ?? 'standalone') === 'standalone' ? 'selected' : ''; ?>>Standalone (Recommended)</option>
                                                                    <option value="fullscreen" <?php echo ($settings['pwa_display_mode'] ?? '') === 'fullscreen' ? 'selected' : ''; ?>>Fullscreen</option>
                                                                    <option value="minimal-ui" <?php echo ($settings['pwa_display_mode'] ?? '') === 'minimal-ui' ? 'selected' : ''; ?>>Minimal UI</option>
                                                                    <option value="browser" <?php echo ($settings['pwa_display_mode'] ?? '') === 'browser' ? 'selected' : ''; ?>>Browser</option>
                                                                </select>
                                                                <small class="text-muted">How the app appears when launched</small>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" id="pwa_enabled" name="pwa_enabled" 
                                                                           <?php echo ($settings['pwa_enabled'] ?? '1') ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="pwa_enabled">
                                                                        <strong>Enable PWA Features</strong>
                                                                    </label>
                                                                </div>
                                                                <small class="text-muted">Allow users to install your app on their devices</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- System Reset Section -->
                                        <div class="row mt-4">
                                            <div class="col-12">
                                                <div class="settings-section">
                                                    <h4><i class="fas fa-database"></i> System Reset</h4>
                                                    <div class="alert alert-warning">
                                                        <i class="fas fa-exclamation-triangle"></i>
                                                        <strong>Warning:</strong> This will permanently delete all sample data from the system including events, nominees, categories, votes, and transactions. This action cannot be undone.
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-lg-6">
                                                            <p>Use this function to clean the system for production deployment:</p>
                                                            <ul>
                                                                <li>Remove all test events and nominees</li>
                                                                <li>Clear all sample votes and transactions</li>
                                                                <li>Reset categories and schemes</li>
                                                                <li>Preserve admin accounts and settings</li>
                                                            </ul>
                                                        </div>
                                                        <div class="col-lg-6">
                                                            <button type="button" class="btn btn-danger" id="system-reset-btn">
                                                                <i class="fas fa-trash-alt"></i> Reset System Data
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Save Settings
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        function previewLogo(input) {
            const preview = document.getElementById('logo-preview');
            const previewImg = document.getElementById('logo-preview-img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
        
        function removeLogo() {
            if (confirm('Are you sure you want to remove the current logo?')) {
                document.getElementById('remove_logo').value = '1';
                document.querySelector('.current-logo').style.display = 'none';
                
                // Show a message that logo will be removed on save
                const currentLogoDiv = document.querySelector('.current-logo').parentElement;
                const removeMessage = document.createElement('div');
                removeMessage.className = 'alert alert-warning mt-2';
                removeMessage.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Logo will be removed when you save settings.';
                currentLogoDiv.appendChild(removeMessage);
            }
        }
        
        // PWA Icon Preview Function
        function previewPWAIcon(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('pwa-icon-preview');
                    const previewImg = document.getElementById('pwa-icon-preview-img');
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // PWA Icon Removal Function
        function removePWAIcon() {
            if (confirm('Are you sure you want to remove the PWA icon? This will also remove all generated app icons.')) {
                document.getElementById('remove_pwa_icon').value = '1';
                document.querySelector('.current-pwa-icon').style.display = 'none';
                
                // Show a message that PWA icon will be removed on save
                const currentPWAIconDiv = document.querySelector('.current-pwa-icon').parentElement;
                const removeMessage = document.createElement('div');
                removeMessage.className = 'alert alert-warning mt-2';
                removeMessage.innerHTML = '<i class="fas fa-exclamation-triangle"></i> PWA icon and all generated app icons will be removed when you save settings.';
                currentPWAIconDiv.appendChild(removeMessage);
            }
        }
        
        // PWA Icon Preview Function
        function previewPWAIcon(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('pwa-icon-preview');
                    const previewImg = document.getElementById('pwa-icon-preview-img');
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // PWA Icon Removal Function
        function removePWAIcon() {
            if (confirm('Are you sure you want to remove the PWA icon? This will also remove all generated app icons.')) {
                document.getElementById('remove_pwa_icon').value = '1';
                document.querySelector('.current-pwa-icon').style.display = 'none';
                
                // Show a message that PWA icon will be removed on save
                const currentPWAIconDiv = document.querySelector('.current-pwa-icon').parentElement;
                const removeMessage = document.createElement('div');
                removeMessage.className = 'alert alert-warning mt-2';
                removeMessage.innerHTML = '<i class="fas fa-exclamation-triangle"></i> PWA icon and all generated app icons will be removed when you save settings.';
                currentPWAIconDiv.appendChild(removeMessage);
            }
        }
        
        // System Reset Function - More robust implementation
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, looking for reset button...');
            
            // Try multiple ways to find the button
            const resetBtn = document.getElementById('system-reset-btn') || 
                           document.querySelector('#system-reset-btn') ||
                           document.querySelector('.btn-danger[id="system-reset-btn"]');
            
            console.log('Reset button found:', resetBtn);
            
            if (resetBtn) {
                // Remove any existing event listeners
                resetBtn.replaceWith(resetBtn.cloneNode(true));
                const newResetBtn = document.getElementById('system-reset-btn');
                
                newResetBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Reset button clicked!');
                    
                    if (confirm('⚠️ WARNING: This will permanently delete ALL data including events, users, votes, and transactions. This action cannot be undone!\n\nAre you absolutely sure you want to reset the entire system?')) {
                        if (confirm('This is your final confirmation. All data will be lost forever. Continue?')) {
                            console.log('User confirmed reset, proceeding...');
                            
                            // Show loading state
                            const originalContent = newResetBtn.innerHTML;
                            newResetBtn.disabled = true;
                            newResetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting System...';
                            
                            // Use FormData instead of JSON for better compatibility
                            const formData = new FormData();
                            formData.append('confirm_reset', 'true');
                            
                            fetch('actions/system-reset.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => {
                                console.log('Response received:', response);
                                return response.json();
                            })
                            .then(data => {
                                console.log('Response data:', data);
                                if (data.success) {
                                    alert('✅ System reset completed successfully! All data has been cleared.');
                                    window.location.reload();
                                } else {
                                    alert('❌ Error resetting system: ' + data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('❌ Error resetting system. Please try again.');
                            })
                            .finally(() => {
                                newResetBtn.disabled = false;
                                newResetBtn.innerHTML = originalContent;
                            });
                        }
                    }
                });
                
                console.log('Event listener attached to reset button');
            } else {
                console.error('Reset button not found!');
            }
        });
    </script>
</body>
</html>
