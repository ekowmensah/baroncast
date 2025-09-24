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
        $settings = [
            'smtp_host' => $_POST['smtp_host'] ?? '',
            'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
            'smtp_username' => $_POST['smtp_username'] ?? '',
            'smtp_password' => $_POST['smtp_password'] ?? '',
            'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
            'mail_from_address' => $_POST['mail_from_address'] ?? '',
            'mail_from_name' => $_POST['mail_from_name'] ?? '',
            'enable_email_notifications' => isset($_POST['enable_email_notifications']) ? 1 : 0,
            'notify_new_events' => isset($_POST['notify_new_events']) ? 1 : 0,
            'notify_new_users' => isset($_POST['notify_new_users']) ? 1 : 0,
            'notify_payment_issues' => isset($_POST['notify_payment_issues']) ? 1 : 0
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
        
        $success_message = "Email settings updated successfully!";
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
    
    // Insert default email settings
    $defaultSettings = [
        ['smtp_host', ''],
        ['smtp_port', '587'],
        ['smtp_username', ''],
        ['smtp_password', ''],
        ['smtp_encryption', 'tls'],
        ['mail_from_address', ''],
        ['mail_from_name', 'E-Cast Voting Platform'],
        ['enable_email_notifications', '1'],
        ['notify_new_events', '1'],
        ['notify_new_users', '1'],
        ['notify_payment_issues', '1']
    ];
    
    $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaultSettings as $setting) {
        $insertStmt->execute($setting);
    }
}

// Get current settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%' OR setting_key LIKE 'mail_%' OR setting_key LIKE 'notify_%' OR setting_key = 'enable_email_notifications'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default values
$defaults = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_encryption' => 'tls',
    'mail_from_name' => 'E-Cast Voting Platform'
];
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
$pageTitle = 'Email Settings';
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
                                <h1><i class="fas fa-envelope"></i> Email Settings</h1>
                                <p class="text-muted">Configure SMTP settings and email notifications</p>
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
                                    <form method="POST">
                                        <div class="row">
                                            <!-- SMTP Configuration -->
                                            <div class="col-lg-6">
                                                <div class="settings-section">
                                                    <h4><i class="fas fa-server"></i> SMTP Configuration</h4>
                                                    <p class="text-muted">Configure your email server settings</p>
                                                    
                                                    <div class="mb-3">
                                                        <label for="smtp_host" class="form-label">SMTP Host</label>
                                                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                                               value="<?php echo htmlspecialchars($settings['smtp_host'] ?? $defaults['smtp_host']); ?>" 
                                                               placeholder="smtp.gmail.com">
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="smtp_port" class="form-label">SMTP Port</label>
                                                                <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                                                       value="<?php echo $settings['smtp_port'] ?? $defaults['smtp_port']; ?>" 
                                                                       placeholder="587">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="smtp_encryption" class="form-label">Encryption</label>
                                                                <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                                                    <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                                    <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                                    <option value="none" <?php echo ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>None</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="smtp_username" class="form-label">SMTP Username</label>
                                                        <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                                               value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" 
                                                               placeholder="your-email@gmail.com">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="smtp_password" class="form-label">SMTP Password</label>
                                                        <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                                               value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>" 
                                                               placeholder="Your email password or app password">
                                                        <small class="text-muted">For Gmail, use an App Password instead of your regular password</small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Email Settings -->
                                            <div class="col-lg-6">
                                                <div class="settings-section">
                                                    <h4><i class="fas fa-at"></i> Email Settings</h4>
                                                    <p class="text-muted">Configure sender information</p>
                                                    
                                                    <div class="mb-3">
                                                        <label for="mail_from_address" class="form-label">From Email Address</label>
                                                        <input type="email" class="form-control" id="mail_from_address" name="mail_from_address" 
                                                               value="<?php echo htmlspecialchars($settings['mail_from_address'] ?? ''); ?>" 
                                                               placeholder="noreply@yourdomain.com">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="mail_from_name" class="form-label">From Name</label>
                                                        <input type="text" class="form-control" id="mail_from_name" name="mail_from_name" 
                                                               value="<?php echo htmlspecialchars($settings['mail_from_name'] ?? $defaults['mail_from_name']); ?>" 
                                                               placeholder="E-Cast Voting Platform">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="enable_email_notifications" name="enable_email_notifications" 
                                                                   <?php echo ($settings['enable_email_notifications'] ?? '1') ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="enable_email_notifications">
                                                                Enable Email Notifications
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Master switch for all email notifications</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <!-- Notification Settings -->
                                        <div class="settings-section">
                                            <h4><i class="fas fa-bell"></i> Notification Settings</h4>
                                            <p class="text-muted">Choose which events trigger email notifications</p>
                                            
                                            <div class="row">
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="notify_new_events" name="notify_new_events" 
                                                                   <?php echo ($settings['notify_new_events'] ?? '1') ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="notify_new_events">
                                                                New Event Submissions
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Notify when organizers create new events</small>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="notify_new_users" name="notify_new_users" 
                                                                   <?php echo ($settings['notify_new_users'] ?? '1') ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="notify_new_users">
                                                                New User Registrations
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Notify when new organizers register</small>
                                                    </div>
                                                </div>
                                                
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="notify_payment_issues" name="notify_payment_issues" 
                                                                   <?php echo ($settings['notify_payment_issues'] ?? '1') ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="notify_payment_issues">
                                                                Payment Issues
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Notify about payment failures or issues</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <div class="d-flex justify-content-between">
                                            <button type="button" class="btn btn-outline-secondary" onclick="testEmail()">
                                                <i class="fas fa-paper-plane"></i> Send Test Email
                                            </button>
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
        function testEmail() {
            // Add test email functionality
            const adminEmail = '<?php echo htmlspecialchars($settings['mail_from_address'] ?? ''); ?>';
            if (!adminEmail) {
                alert('Please configure email settings first');
                return;
            }
            
            if (confirm('Send a test email to verify your SMTP configuration?')) {
                // Implement test email sending
                alert('Test email functionality will be implemented here');
            }
        }
    </script>
</body>
</html>
