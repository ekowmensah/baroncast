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
            'arkesel_api_key' => $_POST['arkesel_api_key'] ?? '',
            'ussd_short_code' => $_POST['ussd_short_code'] ?? '*170*123#',
            'ussd_app_name' => $_POST['ussd_app_name'] ?? 'E-Cast Voting',
            'ussd_welcome_message' => $_POST['ussd_welcome_message'] ?? 'Welcome to E-Cast Voting',
            'enable_ussd_voting' => isset($_POST['enable_ussd_voting']) ? 1 : 0,
            'enable_ussd_sms' => isset($_POST['enable_ussd_sms']) ? 1 : 0,
            'ussd_session_timeout' => (int)($_POST['ussd_session_timeout'] ?? 300),
            'ussd_max_menu_items' => (int)($_POST['ussd_max_menu_items'] ?? 9)
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
        
        $success_message = "USSD settings updated successfully!";
    } catch (Exception $e) {
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}

// Get current settings
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE '%ussd%' OR setting_key = 'arkesel_api_key'");
$stmt->execute();
$settings_result = $stmt->fetchAll(PDO::FETCH_ASSOC);

$settings = [];
foreach ($settings_result as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USSD Settings - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="d-flex">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'includes/header.php'; ?>
            
            <div class="container-fluid p-4">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="mb-0">
                                    <i class="fas fa-mobile-alt me-2"></i>USSD Payment Settings
                                </h4>
                                <p class="text-muted mb-0">Configure Arkesel USSD integration for offline voting</p>
                            </div>
                            <div class="card-body">
                                <?php if (isset($success_message)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($error_message)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-triangle me-2"></i><?= $error_message ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" class="needs-validation" novalidate>
                                    <div class="row">
                                        <!-- API Configuration -->
                                        <div class="col-md-6">
                                            <div class="card mb-4">
                                                <div class="card-header">
                                                    <h5><i class="fas fa-key me-2"></i>API Configuration</h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label for="arkesel_api_key" class="form-label">Arkesel API Key</label>
                                                        <input type="password" class="form-control" id="arkesel_api_key" name="arkesel_api_key" 
                                                               value="<?= htmlspecialchars($settings['arkesel_api_key'] ?? '') ?>" required>
                                                        <div class="form-text">Your Arkesel API key for USSD and SMS services</div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="ussd_short_code" class="form-label">USSD Short Code</label>
                                                        <input type="text" class="form-control" id="ussd_short_code" name="ussd_short_code" 
                                                               value="<?= htmlspecialchars($settings['ussd_short_code'] ?? '*170*123#') ?>" required>
                                                        <div class="form-text">The USSD code users will dial (e.g., *170*123#)</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Application Settings -->
                                        <div class="col-md-6">
                                            <div class="card mb-4">
                                                <div class="card-header">
                                                    <h5><i class="fas fa-cogs me-2"></i>Application Settings</h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label for="ussd_app_name" class="form-label">Application Name</label>
                                                        <input type="text" class="form-control" id="ussd_app_name" name="ussd_app_name" 
                                                               value="<?= htmlspecialchars($settings['ussd_app_name'] ?? 'E-Cast Voting') ?>" required>
                                                        <div class="form-text">Name displayed in USSD menus</div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="ussd_welcome_message" class="form-label">Welcome Message</label>
                                                        <textarea class="form-control" id="ussd_welcome_message" name="ussd_welcome_message" rows="2" required><?= htmlspecialchars($settings['ussd_welcome_message'] ?? 'Welcome to E-Cast Voting') ?></textarea>
                                                        <div class="form-text">First message users see when they dial the USSD code</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <!-- Feature Toggles -->
                                        <div class="col-md-6">
                                            <div class="card mb-4">
                                                <div class="card-header">
                                                    <h5><i class="fas fa-toggle-on me-2"></i>Feature Settings</h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="enable_ussd_voting" name="enable_ussd_voting" 
                                                               <?= ($settings['enable_ussd_voting'] ?? 0) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="enable_ussd_voting">
                                                            Enable USSD Voting
                                                        </label>
                                                        <div class="form-text">Allow users to vote via USSD</div>
                                                    </div>
                                                    
                                                    <div class="form-check form-switch mb-3">
                                                        <input class="form-check-input" type="checkbox" id="enable_ussd_sms" name="enable_ussd_sms" 
                                                               <?= ($settings['enable_ussd_sms'] ?? 0) ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="enable_ussd_sms">
                                                            Enable SMS Confirmations
                                                        </label>
                                                        <div class="form-text">Send SMS confirmations for USSD votes</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Technical Settings -->
                                        <div class="col-md-6">
                                            <div class="card mb-4">
                                                <div class="card-header">
                                                    <h5><i class="fas fa-sliders-h me-2"></i>Technical Settings</h5>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <label for="ussd_session_timeout" class="form-label">Session Timeout (seconds)</label>
                                                        <input type="number" class="form-control" id="ussd_session_timeout" name="ussd_session_timeout" 
                                                               value="<?= $settings['ussd_session_timeout'] ?? 300 ?>" min="60" max="600">
                                                        <div class="form-text">How long USSD sessions remain active</div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="ussd_max_menu_items" class="form-label">Max Menu Items</label>
                                                        <input type="number" class="form-control" id="ussd_max_menu_items" name="ussd_max_menu_items" 
                                                               value="<?= $settings['ussd_max_menu_items'] ?? 9 ?>" min="3" max="9">
                                                        <div class="form-text">Maximum items to show in USSD menus</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Setup Guide -->
                                    <div class="card mb-4">
                                        <div class="card-header">
                                            <h5><i class="fas fa-info-circle me-2"></i>Setup Guide</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6>1. Arkesel Account Setup</h6>
                                                    <ul class="list-unstyled">
                                                        <li><i class="fas fa-check text-success me-2"></i>Create account at <a href="https://arkesel.com" target="_blank">arkesel.com</a></li>
                                                        <li><i class="fas fa-check text-success me-2"></i>Get API key from dashboard</li>
                                                        <li><i class="fas fa-check text-success me-2"></i>Apply for USSD short code</li>
                                                    </ul>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>2. Webhook Configuration</h6>
                                                    <div class="input-group mb-2">
                                                        <input type="text" class="form-control" id="ussd-callback-url" readonly 
                                                               value="<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/e-cast-voting-system/api/ussd-callback.php' ?>">
                                                        <button class="btn btn-outline-secondary" type="button" onclick="copyCallbackUrl()">
                                                            <i class="fas fa-copy"></i>
                                                        </button>
                                                    </div>
                                                    <small class="text-muted">Add this URL as your USSD callback in Arkesel dashboard</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save USSD Settings
                                        </button>
                                        <button type="button" class="btn btn-info" onclick="testUSSDConnection()">
                                            <i class="fas fa-plug me-2"></i>Test USSD Connection
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
    <script>
        function copyCallbackUrl() {
            const callbackUrl = document.getElementById('ussd-callback-url');
            callbackUrl.select();
            document.execCommand('copy');
            
            // Show feedback
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-secondary');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }

        function testUSSDConnection() {
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Testing...';
            button.disabled = true;

            // Test USSD connection (placeholder)
            setTimeout(() => {
                alert('USSD connection test completed. Check logs for details.');
                button.innerHTML = originalText;
                button.disabled = false;
            }, 2000);
        }
    </script>
</body>
</html>
