<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit;
}

// Initialize database connection for global use
$db = new Database();
$pdo = $db->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settings = [
            'hubtel_pos_id' => $_POST['hubtel_pos_id'] ?? '',
            'hubtel_api_key' => $_POST['hubtel_api_key'] ?? '',
            'hubtel_api_secret' => $_POST['hubtel_api_secret'] ?? '',
            'hubtel_environment' => $_POST['hubtel_environment'] ?? 'sandbox',
            'hubtel_callback_url' => $_POST['hubtel_callback_url'] ?? '',
            'hubtel_ip_whitelist' => $_POST['hubtel_ip_whitelist'] ?? '',
            'enable_hubtel_payments' => isset($_POST['enable_hubtel_payments']) ? '1' : '0',
            'hubtel_timeout' => $_POST['hubtel_timeout'] ?? '30',
            'hubtel_max_retries' => $_POST['hubtel_max_retries'] ?? '3',
            'hubtel_test_mode' => isset($_POST['hubtel_test_mode']) ? '1' : '0'
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
        }
        
        $success_message = "Hubtel settings saved successfully!";
        
    } catch (Exception $e) {
        $error_message = "Error saving settings: " . $e->getMessage();
    }
}

// Get current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'hubtel_%'");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default values
$defaults = [
    'hubtel_pos_id' => '',
    'hubtel_api_key' => '',
    'hubtel_api_secret' => '',
    'hubtel_environment' => 'sandbox',
    'hubtel_callback_url' => '',
    'hubtel_ip_whitelist' => '',
    'hubtel_timeout' => '30',
    'hubtel_max_retries' => '3',
    'hubtel_test_mode' => '1'
];

if (empty($settings['hubtel_callback_url'])) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $settings['hubtel_callback_url'] = "{$protocol}://{$host}/webhooks/hubtel-receive-money-callback.php";
    // For USSD services, we need to update the Service fulfillment URL in Hubtel portal
    $ussd_callback_url = "{$protocol}://{$host}/webhooks/hubtel-receive-money-callback.php";
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hubtel Payment Settings - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/responsive-sidebar.css" rel="stylesheet">
</head>
<body>
    <?php 
    $pageTitle = 'Hubtel Settings';
    include 'includes/header.php'; 
    include 'includes/sidebar.php'; 
    ?>
    <div class="main-content">
        <div class="container-fluid p-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1"><i class="fas fa-mobile-alt me-2"></i>Hubtel Payment Settings</h2>
                    <p class="text-muted mb-0">Configure Hubtel Direct Receive Money API for mobile money payments</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="hubtel-debug.php" class="btn btn-outline-secondary">
                        <i class="fas fa-bug me-2"></i>Debug Tools
                    </a>
                    <a href="transaction-monitor.php" class="btn btn-outline-primary">
                        <i class="fas fa-heartbeat me-2"></i>Monitor
                    </a>
                </div>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <!-- API Configuration -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-key me-2"></i>API Configuration</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hubtel_pos_id" class="form-label">POS Sales ID</label>
                                    <input type="text" class="form-control" id="hubtel_pos_id" name="hubtel_pos_id" 
                                           value="<?php echo htmlspecialchars($settings['hubtel_pos_id'] ?? $defaults['hubtel_pos_id']); ?>" 
                                           placeholder="Your Hubtel POS Sales ID" required>
                                    <div class="form-text">Provided by Hubtel for Direct Receive Money API</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hubtel_environment" class="form-label">Environment</label>
                                    <select class="form-select" id="hubtel_environment" name="hubtel_environment">
                                        <option value="sandbox" <?php echo (($settings['hubtel_environment'] ?? $defaults['hubtel_environment']) === 'sandbox') ? 'selected' : ''; ?>>Sandbox (Testing)</option>
                                        <option value="production" <?php echo (($settings['hubtel_environment'] ?? $defaults['hubtel_environment']) === 'production') ? 'selected' : ''; ?>>Production (Live)</option>
                                    </select>
                                    <div class="form-text">Choose sandbox for testing, production for live payments</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hubtel_api_key" class="form-label">API Key</label>
                                    <input type="text" class="form-control" id="hubtel_api_key" name="hubtel_api_key" 
                                           value="<?php echo htmlspecialchars($settings['hubtel_api_key'] ?? $defaults['hubtel_api_key']); ?>" 
                                           placeholder="Your Hubtel API Key" required>
                                    <div class="form-text">Used for Basic Authentication</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hubtel_api_secret" class="form-label">API Secret</label>
                                    <input type="password" class="form-control" id="hubtel_api_secret" name="hubtel_api_secret" 
                                           value="<?php echo htmlspecialchars($settings['hubtel_api_secret'] ?? $defaults['hubtel_api_secret']); ?>" 
                                           placeholder="Your Hubtel API Secret" required>
                                    <div class="form-text">Keep this secret and secure</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Configuration -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-cog me-2"></i>Payment Configuration</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="enable_hubtel_payments" name="enable_hubtel_payments" 
                                           <?php echo (($settings['enable_hubtel_payments'] ?? $defaults['enable_hubtel_payments']) === '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="enable_hubtel_payments">
                                        <strong>Enable Hubtel Payments</strong>
                                    </label>
                                    <div class="form-text">Allow voters to pay via mobile money through Hubtel</div>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="hubtel_test_mode" name="hubtel_test_mode" 
                                           <?php echo (($settings['hubtel_test_mode'] ?? $defaults['hubtel_test_mode']) === '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="hubtel_test_mode">
                                        Enable Test Mode
                                    </label>
                                    <div class="form-text">Enable additional logging and debugging for development</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hubtel_timeout" class="form-label">API Timeout (seconds)</label>
                                    <input type="number" class="form-control" id="hubtel_timeout" name="hubtel_timeout" 
                                           value="<?php echo htmlspecialchars($settings['hubtel_timeout'] ?? $defaults['hubtel_timeout']); ?>" 
                                           min="10" max="120">
                                    <div class="form-text">Maximum time to wait for API responses</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="hubtel_max_retries" class="form-label">Max Retry Attempts</label>
                                    <input type="number" class="form-control" id="hubtel_max_retries" name="hubtel_max_retries" 
                                           value="<?php echo htmlspecialchars($settings['hubtel_max_retries'] ?? $defaults['hubtel_max_retries']); ?>" 
                                           min="1" max="10">
                                    <div class="form-text">Number of times to retry failed API requests</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Webhook Configuration -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-webhook me-2"></i>Webhook Configuration</h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="hubtel_callback_url" class="form-label">Callback URL</label>
                            <input type="url" class="form-control" id="hubtel_callback_url" name="hubtel_callback_url" 
                                   value="<?php echo htmlspecialchars($settings['hubtel_callback_url'] ?? $defaults['hubtel_callback_url']); ?>" 
                                   placeholder="https://yourdomain.com/webhooks/hubtel-receive-money-callback.php" required>
                            <div class="form-text">URL where Hubtel will send payment confirmations</div>
                        </div>
                        
                        <div class="alert alert-info mb-3">
                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Webhook Setup</h6>
                            <p class="mb-2">Configure this URL in your Hubtel Merchant Account:</p>
                            <ul class="mb-0 small">
                                <li>Log into your Hubtel merchant dashboard</li>
                                <li>Navigate to API Settings → Webhooks</li>
                                <li>Add the callback URL above</li>
                                <li>Enable payment notifications</li>
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <label for="hubtel_ip_whitelist" class="form-label">IP Whitelist</label>
                            <textarea class="form-control" id="hubtel_ip_whitelist" name="hubtel_ip_whitelist" rows="3"
                                      placeholder="192.168.1.1,10.0.0.1,hubtel-server-ip"><?php echo htmlspecialchars($settings['hubtel_ip_whitelist'] ?? $defaults['hubtel_ip_whitelist']); ?></textarea>
                            <div class="form-text">Comma-separated list of IPs allowed to access webhooks</div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Security Note</h6>
                            <p class="mb-0 small">
                                Always use HTTPS in production and implement IP whitelisting for webhook endpoints. 
                                Contact Hubtel support for their current server IP addresses.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Supported Mobile Networks -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-mobile-alt me-2"></i>Supported Mobile Money Networks</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card border-success mb-3">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">MTN Mobile Money</h6>
                                        <p class="card-text small">024, 025, 053, 054, 055, 059</p>
                                        <span class="badge bg-success">Active</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-success mb-3">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">Telecel Ghana</h6>
                                        <p class="card-text small">020, 050</p>
                                        <span class="badge bg-success">Active</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-success mb-3">
                                    <div class="card-body text-center">
                                        <h6 class="card-title">AirtelTigo</h6>
                                        <p class="card-text small">026, 027, 056, 057</p>
                                        <span class="badge bg-success">Active</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary" id="test-connection-btn">
                        <i class="fas fa-plug me-2"></i>Test Connection
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Settings
                    </button>
                </div>
            </form>
            
            <!-- Connection Test Result -->
            <div id="connection-result" class="mt-3" style="display: none;">
                <div class="alert" id="connection-status"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Test connection functionality
        document.getElementById('test-connection-btn').addEventListener('click', function() {
            const btn = this;
            const resultDiv = document.getElementById('connection-result');
            const statusDiv = document.getElementById('connection-status');
            
            // Get form values
            const formData = new FormData();
            formData.append('action', 'test_connection');
            formData.append('hubtel_pos_id', document.getElementById('hubtel_pos_id').value);
            formData.append('hubtel_api_key', document.getElementById('hubtel_api_key').value);
            formData.append('hubtel_api_secret', document.getElementById('hubtel_api_secret').value);
            formData.append('hubtel_environment', document.getElementById('hubtel_environment').value);
            
            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Testing...';
            resultDiv.style.display = 'block';
            statusDiv.className = 'alert alert-info';
            statusDiv.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Testing connection to Hubtel API...';
            
            // Make test request
            fetch('actions/test-hubtel-connection.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.className = 'alert alert-success';
                    statusDiv.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i> 
                        <strong>Connection Successful!</strong><br>
                        ${data.message}
                    `;
                } else {
                    statusDiv.className = 'alert alert-danger';
                    statusDiv.innerHTML = `
                        <i class="fas fa-times-circle me-2"></i> 
                        <strong>Connection Failed!</strong><br>
                        ${data.message}
                    `;
                }
            })
            .catch(error => {
                statusDiv.className = 'alert alert-danger';
                statusDiv.innerHTML = `
                    <i class="fas fa-times-circle me-2"></i> 
                    <strong>Test Failed!</strong><br>
                    ${error.message}
                `;
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-plug me-2"></i>Test Connection';
            });
        });
    </script>
</body>
</html>
</body>
</html>