<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../services/HubtelReceiveMoneyService.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: ../auth/login.php');
    exit;
}

// Initialize database connection for global use
$database = new Database();
$pdo = $database->getConnection();

$test_results = [];
$overall_status = 'success';

// Run tests if requested
if (isset($_POST['run_tests'])) {
    try {
        $hubtel = new HubtelReceiveMoneyService();
        
        // Test 1: Database Connection
        try {
            $stmt = $pdo->query("SELECT 1");
            $test_results['database'] = ['status' => 'success', 'message' => 'Database connection successful'];
        } catch (Exception $e) {
            $test_results['database'] = ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
            $overall_status = 'error';
        }
        
        // Test 2: Hubtel Settings Configuration
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'hubtel_%'");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $required_settings = ['hubtel_pos_id', 'hubtel_api_key', 'hubtel_api_secret'];
            $missing_settings = [];
            
            foreach ($required_settings as $setting) {
                if (empty($settings[$setting])) {
                    $missing_settings[] = $setting;
                }
            }
            
            if (empty($missing_settings)) {
                $test_results['settings'] = ['status' => 'success', 'message' => 'All required Hubtel settings are configured'];
            } else {
                $test_results['settings'] = ['status' => 'warning', 'message' => 'Missing settings: ' . implode(', ', $missing_settings)];
                if ($overall_status !== 'error') $overall_status = 'warning';
            }
        } catch (Exception $e) {
            $test_results['settings'] = ['status' => 'error', 'message' => 'Settings check failed: ' . $e->getMessage()];
            $overall_status = 'error';
        }
        
        // Test 3: Database Schema
        try {
            // Check if required columns exist
            $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'hubtel_transaction_id'");
            $hubtel_column_exists = $stmt->rowCount() > 0;
            
            $stmt = $pdo->query("SHOW TABLES LIKE 'hubtel_transaction_logs'");
            $logs_table_exists = $stmt->rowCount() > 0;
            
            if ($hubtel_column_exists && $logs_table_exists) {
                $test_results['schema'] = ['status' => 'success', 'message' => 'Database schema is properly configured'];
            } else {
                $missing = [];
                if (!$hubtel_column_exists) $missing[] = 'hubtel_transaction_id column';
                if (!$logs_table_exists) $missing[] = 'hubtel_transaction_logs table';
                
                $test_results['schema'] = ['status' => 'error', 'message' => 'Missing database components: ' . implode(', ', $missing)];
                $overall_status = 'error';
            }
        } catch (Exception $e) {
            $test_results['schema'] = ['status' => 'error', 'message' => 'Schema check failed: ' . $e->getMessage()];
            $overall_status = 'error';
        }
        
        // Test 4: Phone Number Formatting
        try {
            $test_phone = '0245123456';
            $reflection = new ReflectionClass($hubtel);
            $method = $reflection->getMethod('formatPhoneNumber');
            $method->setAccessible(true);
            $formatted = $method->invoke($hubtel, $test_phone);
            
            if ($formatted === '233245123456') {
                $test_results['phone_format'] = ['status' => 'success', 'message' => 'Phone number formatting works correctly'];
            } else {
                $test_results['phone_format'] = ['status' => 'error', 'message' => "Phone formatting failed. Expected: 233245123456, Got: $formatted"];
                $overall_status = 'error';
            }
        } catch (Exception $e) {
            $test_results['phone_format'] = ['status' => 'error', 'message' => 'Phone formatting test failed: ' . $e->getMessage()];
            $overall_status = 'error';
        }
        
        // Test 5: Network Detection
        try {
            $reflection = new ReflectionClass($hubtel);
            $method = $reflection->getMethod('detectChannel');
            $method->setAccessible(true);
            
            $mtn_result = $method->invoke($hubtel, '233245123456');
            $telecel_result = $method->invoke($hubtel, '233201234567');
            $airteltigo_result = $method->invoke($hubtel, '233261234567');
            
            $expected = ['mtn-gh', 'vodafone-gh', 'tigo-gh'];
            $actual = [$mtn_result, $telecel_result, $airteltigo_result];
            
            if ($actual === $expected) {
                $test_results['network_detection'] = ['status' => 'success', 'message' => 'Network detection works correctly'];
            } else {
                $test_results['network_detection'] = ['status' => 'error', 'message' => "Network detection failed. Expected: [" . implode(', ', $expected) . "], Got: [" . implode(', ', $actual) . "]"];
                $overall_status = 'error';
            }
        } catch (Exception $e) {
            $test_results['network_detection'] = ['status' => 'error', 'message' => 'Network detection test failed: ' . $e->getMessage()];
            $overall_status = 'error';
        }
        
        // Test 6: Webhook URL accessibility
        try {
            $webhook_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/webhooks/hubtel-receive-money-callback.php';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $webhook_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200 || $http_code === 405) { // 405 is expected for GET request
                $test_results['webhook'] = ['status' => 'success', 'message' => 'Webhook URL is accessible'];
            } else {
                $test_results['webhook'] = ['status' => 'warning', 'message' => "Webhook returned HTTP $http_code. This may affect payment confirmations."];
                if ($overall_status !== 'error') $overall_status = 'warning';
            }
        } catch (Exception $e) {
            $test_results['webhook'] = ['status' => 'warning', 'message' => 'Could not test webhook accessibility: ' . $e->getMessage()];
            if ($overall_status !== 'error') $overall_status = 'warning';
        }
        
        // Test 7: Test Payment Initiation (without actually charging)
        if (!empty($settings['hubtel_pos_id']) && !empty($settings['hubtel_api_key']) && !empty($settings['hubtel_api_secret'])) {
            try {
                // This is a test that should fail gracefully
                $test_payment = $hubtel->initiatePayment(
                    1.00,
                    '233200000000',
                    'Test payment - DO NOT PROCESS',
                    'TEST_' . time(),
                    'Test User',
                    'test@example.com'
                );
                
                if (isset($test_payment['success'])) {
                    $test_results['api_connection'] = ['status' => 'success', 'message' => 'Hubtel API connection successful'];
                } else {
                    $test_results['api_connection'] = ['status' => 'warning', 'message' => 'Hubtel API connection could not be verified'];
                    if ($overall_status !== 'error') $overall_status = 'warning';
                }
            } catch (Exception $e) {
                $test_results['api_connection'] = ['status' => 'warning', 'message' => 'API test failed: ' . $e->getMessage()];
                if ($overall_status !== 'error') $overall_status = 'warning';
            }
        } else {
            $test_results['api_connection'] = ['status' => 'skipped', 'message' => 'API test skipped - missing credentials'];
        }
        
    } catch (Exception $e) {
        $test_results['general'] = ['status' => 'error', 'message' => 'General test failure: ' . $e->getMessage()];
        $overall_status = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hubtel Debug & Test - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/responsive-sidebar.css" rel="stylesheet">
    <style>
        .test-result {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            border-left: 4px solid;
        }
        .test-success {
            background: rgba(16, 185, 129, 0.1);
            border-left-color: #10b981;
            color: #10b981;
        }
        .test-warning {
            background: rgba(245, 158, 11, 0.1);
            border-left-color: #f59e0b;
            color: #f59e0b;
        }
        .test-error {
            background: rgba(239, 68, 68, 0.1);
            border-left-color: #ef4444;
            color: #ef4444;
        }
        .test-skipped {
            background: rgba(107, 114, 128, 0.1);
            border-left-color: #6b7280;
            color: #6b7280;
        }
        .code-block {
            background: #1f2937;
            color: #e5e7eb;
            padding: 1rem;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            white-space: pre-wrap;
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php 
    $pageTitle = 'Hubtel Debug Tools';
    include 'includes/header.php'; 
    include 'includes/sidebar.php'; 
    ?>
    <div class="main-content">
        <div class="container-fluid p-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1"><i class="fas fa-bug me-2"></i>Hubtel Debug & Test Utilities</h2>
                    <p class="text-muted mb-0">Test and debug Hubtel integration components</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="hubtel-settings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                    <a href="transaction-monitor.php" class="btn btn-outline-primary">
                        <i class="fas fa-heartbeat me-2"></i>Monitor
                    </a>
                </div>
            </div>
            
            <!-- System Test -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-check-circle me-2"></i>System Integration Test</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">Run comprehensive tests to verify Hubtel integration setup.</p>
                    
                    <form method="POST">
                        <button type="submit" name="run_tests" class="btn btn-primary">
                            <i class="fas fa-play me-2"></i>Run All Tests
                        </button>
                    </form>
                    
                    <?php if (!empty($test_results)): ?>
                    <div class="mt-4">
                        <h5>Test Results 
                            <?php if ($overall_status === 'success'): ?>
                                <span class="badge bg-success">All Passed</span>
                            <?php elseif ($overall_status === 'warning'): ?>
                                <span class="badge bg-warning">Some Warnings</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Failures Detected</span>
                            <?php endif; ?>
                        </h5>
                        
                        <?php foreach ($test_results as $test_name => $result): ?>
                        <div class="test-result test-<?php echo $result['status']; ?>">
                            <strong><?php echo ucwords(str_replace('_', ' ', $test_name)); ?>:</strong>
                            <?php echo $result['message']; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- API Test Tool -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-code me-2"></i>API Test Tool</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">Test individual Hubtel API functions with custom parameters.</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Test Payment Initiation</h5>
                            <form id="testPaymentForm">
                                <div class="mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control" value="233200000000" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Amount (GHS)</label>
                                    <input type="number" name="amount" class="form-control" value="1.00" step="0.01" min="0.01" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <input type="text" name="description" class="form-control" value="API Test Payment" required>
                                </div>
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-flask me-2"></i>Test API Call
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <h5>Test Status Check</h5>
                            <form id="testStatusForm">
                                <div class="mb-3">
                                    <label class="form-label">Transaction Reference</label>
                                    <input type="text" name="reference" class="form-control" placeholder="ECAST_1234567890_1234" required>
                                </div>
                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-search me-2"></i>Check Status
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div id="apiTestResult" class="mt-4" style="display: none;">
                        <h5>API Response:</h5>
                        <div class="code-block" id="apiResponseContent"></div>
                    </div>
                </div>
            </div>
            
            <!-- Configuration Display -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-cog me-2"></i>Current Configuration</h4>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'hubtel_%' ORDER BY setting_key");
                        $hubtel_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="code-block">
<?php foreach ($hubtel_settings as $setting): ?>
<?php echo $setting['setting_key']; ?>: <?php echo $setting['setting_key'] === 'hubtel_api_secret' ? str_repeat('*', strlen($setting['setting_value'])) : $setting['setting_value']; ?>

<?php endforeach; ?>
                    </div>
                    <?php } catch (Exception $e) { ?>
                    <div class="alert alert-danger">Could not load settings: <?php echo $e->getMessage(); ?></div>
                    <?php } ?>
                </div>
            </div>
            
            <!-- Webhook Test -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-webhook me-2"></i>Webhook Test</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">Test webhook endpoint with sample Hubtel callback data.</p>
                    
                    <button id="testWebhookBtn" class="btn btn-secondary">
                        <i class="fas fa-satellite-dish me-2"></i>Send Test Webhook
                    </button>
                    
                    <div id="webhookTestResult" class="mt-3" style="display: none;">
                        <div class="code-block" id="webhookResponseContent"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Test Payment API
        document.getElementById('testPaymentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const resultDiv = document.getElementById('apiTestResult');
            const contentDiv = document.getElementById('apiResponseContent');
            
            resultDiv.style.display = 'block';
            contentDiv.textContent = 'Testing payment initiation...';
            
            fetch('actions/test-api-payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                contentDiv.textContent = data;
            })
            .catch(error => {
                contentDiv.textContent = 'Error: ' + error.message;
            });
        });
        
        // Test Status Check API
        document.getElementById('testStatusForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const resultDiv = document.getElementById('apiTestResult');
            const contentDiv = document.getElementById('apiResponseContent');
            
            resultDiv.style.display = 'block';
            contentDiv.textContent = 'Checking transaction status...';
            
            fetch('actions/test-api-status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                contentDiv.textContent = data;
            })
            .catch(error => {
                contentDiv.textContent = 'Error: ' + error.message;
            });
        });
        
        // Test Webhook
        document.getElementById('testWebhookBtn').addEventListener('click', function() {
            const resultDiv = document.getElementById('webhookTestResult');
            const contentDiv = document.getElementById('webhookResponseContent');
            
            resultDiv.style.display = 'block';
            contentDiv.textContent = 'Sending test webhook...';
            
            fetch('actions/test-webhook.php', {
                method: 'POST'
            })
            .then(response => response.text())
            .then(data => {
                contentDiv.textContent = data;
            })
            .catch(error => {
                contentDiv.textContent = 'Error: ' + error.message;
            });
        });
    </script>
</body>
</html>