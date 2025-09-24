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
            'paystack_public_key' => $_POST['paystack_public_key'] ?? '',
            'paystack_secret_key' => $_POST['paystack_secret_key'] ?? '',
            'paystack_webhook_secret' => $_POST['paystack_webhook_secret'] ?? '',
            'payment_currency' => $_POST['payment_currency'] ?? 'GHS',
            'payment_gateway' => 'paystack',
            'payment_environment' => $_POST['payment_environment'] ?? 'test',
            'payment_timeout' => (int)($_POST['payment_timeout'] ?? 300),
            'enable_payment_logging' => isset($_POST['enable_payment_logging']) ? 1 : 0,
            'vote_cost' => (float)($_POST['vote_cost'] ?? 1.00)
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
        
        $success_message = "Paystack settings updated successfully!";
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
    
    // Insert default payment settings
    $defaultSettings = [
        ['arkesel_api_key', ''],
        ['vote_cost', '1.00'],
        ['hubtel_client_id', ''],
        ['hubtel_client_secret', ''],
        ['hubtel_api_key', ''],
        ['hubtel_sender_id', ''],
        ['enable_hubtel_sms', '0'],
        ['payment_environment', 'sandbox'],
        ['payment_timeout', '300'],
        ['otp_expiry_time', '600'],
        ['max_payment_retries', '3'],
        ['enable_payment_logging', '1'],
        ['ussd_code', '*123*456#'],
    ];
    
    $insertStmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaultSettings as $setting) {
        $insertStmt->execute($setting);
    }
}

// Get current settings
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE '%payment%' OR setting_key LIKE '%paystack%'");
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
    <title>Payment Settings - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php 
$pageTitle = 'Payment Settings';
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
                                <h1><i class="fas fa-credit-card"></i> Payment Settings</h1>
                                <p class="text-muted">Configure payment gateway settings and API credentials</p>
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
                                            <!-- Arkesel Settings -->
                                            <div class="col-lg-6">
                                                <div class="settings-section">
                                                    <h4><i class="fas fa-sms"></i> Arkesel API Settings</h4>
                                                    <p class="text-muted">Configure Arkesel for SMS and USSD services</p>
                                                    
                                                    <div class="mb-3">
                                                        <label for="arkesel_api_key" class="form-label">API Key</label>
                                                        <input type="text" class="form-control" id="arkesel_api_key" name="arkesel_api_key" 
                                                               value="<?php echo htmlspecialchars($settings['arkesel_api_key'] ?? ''); ?>" 
                                                               placeholder="Enter Arkesel API Key">
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                            
                                            <!-- Vote Cost Settings -->
                                            <div class="col-lg-6">
                                                <div class="settings-section">
                                                    <h4><i class="fas fa-money-bill"></i> Vote Cost Settings</h4>
                                                    <p class="text-muted">Configure the cost per vote</p>
                                                    
                                                    <div class="mb-3">
                                                        <label for="vote_cost" class="form-label">Cost Per Vote (GHS)</label>
                                                        <input type="number" step="0.01" min="0.01" class="form-control" id="vote_cost" name="vote_cost" 
                                                               value="<?php echo htmlspecialchars($settings['vote_cost'] ?? '1.00'); ?>" 
                                                               placeholder="Enter cost per vote">
                                                        <div class="form-text">Amount charged for each vote in Ghana Cedis</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <div class="row">
                                            <!-- General Payment Settings -->
                                            <div class="col-lg-6">
                                                <div class="settings-section">
                                                    <h4><i class="fas fa-cog"></i> General Settings</h4>
                                                    
                                                    <div class="mb-3">
                                                        <label for="payment_environment" class="form-label">Environment</label>
                                                        <select class="form-select" id="payment_environment" name="payment_environment">
                                                            <option value="sandbox" <?php echo ($settings['payment_environment'] ?? 'sandbox') === 'sandbox' ? 'selected' : ''; ?>>
                                                                Sandbox (Testing)
                                                            </option>
                                                            <option value="production" <?php echo ($settings['payment_environment'] ?? '') === 'production' ? 'selected' : ''; ?>>
                                                                Production (Live)
                                                            </option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="payment_timeout" class="form-label">Payment Timeout (seconds)</label>
                                                        <input type="number" class="form-control" id="payment_timeout" name="payment_timeout" 
                                                               value="<?php echo $settings['payment_timeout'] ?? 300; ?>" min="60" max="600">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="max_payment_retries" class="form-label">Max Payment Retries</label>
                                                        <input type="number" class="form-control" id="max_payment_retries" name="max_payment_retries" 
                                                               value="<?php echo $settings['max_payment_retries'] ?? 3; ?>" min="1" max="5">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- OTP & Security Settings -->
                                            <div class="col-lg-6">
                                                <div class="settings-section">
                                                    <h4><i class="fas fa-shield-alt"></i> Security Settings</h4>
                                                    
                                                    <div class="mb-3">
                                                        <label for="otp_expiry_time" class="form-label">OTP Expiry Time (seconds)</label>
                                                        <input type="number" class="form-control" id="otp_expiry_time" name="otp_expiry_time" 
                                                               value="<?php echo $settings['otp_expiry_time'] ?? 600; ?>" min="300" max="900">
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="enable_payment_logging" name="enable_payment_logging" 
                                                                   <?php echo ($settings['enable_payment_logging'] ?? '1') ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="enable_payment_logging">
                                                                Enable Payment Logging
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Log all payment activities for debugging and audit purposes</small>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="ussd_code" class="form-label">USSD Code</label>
                                                        <input type="text" class="form-control" id="ussd_code" name="ussd_code" 
                                                               value="<?php echo htmlspecialchars($settings['ussd_code'] ?? '*123*456#'); ?>" 
                                                               placeholder="*123*456#">
                                                        <small class="text-muted">USSD code that voters will dial for payment</small>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="enable_card_payments" name="enable_card_payments" 
                                                                   <?php echo ($settings['enable_card_payments'] ?? '0') ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="enable_card_payments">
                                                                Enable Credit/Debit Card Payments
                                                            </label>
                                                        </div>
                                                        <small class="text-muted">Allow voters to pay using credit/debit cards via JuniPay</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <!-- Paystack Settings -->
                                        <div class="card mb-4">
                                            <div class="card-header">
                                                <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Paystack Payment Settings</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="alert alert-info">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    <strong>Paystack Integration:</strong> Get your API keys from your Paystack dashboard at 
                                                    <a href="https://dashboard.paystack.com/#/settings/developers" target="_blank">dashboard.paystack.com</a>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="paystack_public_key" class="form-label">Public Key</label>
                                                            <input type="text" class="form-control" id="paystack_public_key" name="paystack_public_key" 
                                                                   value="<?php echo htmlspecialchars($settings['paystack_public_key'] ?? ''); ?>"
                                                                   placeholder="pk_test_...">
                                                            <div class="form-text">Used for frontend payment initialization</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="paystack_secret_key" class="form-label">Secret Key</label>
                                                            <input type="password" class="form-control" id="paystack_secret_key" name="paystack_secret_key" 
                                                                   value="<?php echo htmlspecialchars($settings['paystack_secret_key'] ?? ''); ?>"
                                                                   placeholder="sk_test_...">
                                                            <div class="form-text">Used for server-side API calls</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="paystack_webhook_secret" class="form-label">Webhook Secret</label>
                                                            <input type="password" class="form-control" id="paystack_webhook_secret" name="paystack_webhook_secret" 
                                                                   value="<?php echo htmlspecialchars($settings['paystack_webhook_secret'] ?? ''); ?>">
                                                            <div class="form-text">Used to verify webhook signatures</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="payment_currency" class="form-label">Currency</label>
                                                            <select class="form-control" id="payment_currency" name="payment_currency">
                                                                <option value="GHS" <?php echo ($settings['payment_currency'] ?? 'GHS') === 'GHS' ? 'selected' : ''; ?>>GHS - Ghana Cedis</option>
                                                                <option value="NGN" <?php echo ($settings['payment_currency'] ?? '') === 'NGN' ? 'selected' : ''; ?>>NGN - Nigerian Naira</option>
                                                                <option value="USD" <?php echo ($settings['payment_currency'] ?? '') === 'USD' ? 'selected' : ''; ?>>USD - US Dollars</option>
                                                            </select>
                                                            <div class="form-text">Payment currency for transactions</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="mb-3">
                                                            <label class="form-label">Webhook URL</label>
                                                            <div class="input-group">
                                                                <input type="text" class="form-control" readonly 
                                                                       value="<?php echo 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/webhooks/paystack-webhook.php'; ?>">
                                                                <button class="btn btn-outline-secondary" type="button" onclick="copyWebhookUrl()">Copy</button>
                                                            </div>
                                                            <div class="form-text">Add this URL to your Paystack webhook settings</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <button type="button" class="btn btn-outline-secondary" onclick="testConnection()">
                                                <i class="fas fa-plug"></i> Test Connection
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Save Settings
                                            </button>
                                            <button type="button" class="btn btn-info ms-2" onclick="testPaystackConnection()">
                                                <i class="fas fa-plug me-2"></i>Test Paystack Connection
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
        function copyWebhookUrl() {
            const webhookUrl = document.getElementById('webhook-url');
            webhookUrl.select();
            document.execCommand('copy');
            
            // Show feedback
            const button = event.target.closest('button');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Copied!';
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-secondary');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }

        function testPaystackConnection() {
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Testing...';
            button.disabled = true;

            fetch('api/test-paystack.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    public_key: document.getElementById('paystack_public_key').value,
                    secret_key: document.getElementById('paystack_secret_key').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Paystack connection successful!');
                } else {
                    alert('❌ Connection failed: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Test failed: ' + error.message);
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
    </script>
</body>
</html>
