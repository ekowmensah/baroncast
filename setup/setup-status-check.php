<?php
/**
 * Setup Script for Hubtel Transaction Status Check
 * This script helps configure the system for automatic status checking
 */

// Only allow admin access
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized access');
}

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'configure_settings':
                // Update Hubtel settings
                $settings = [
                    'hubtel_pos_id' => $_POST['pos_id'] ?? '',
                    'hubtel_api_key' => $_POST['api_key'] ?? '',
                    'hubtel_api_secret' => $_POST['api_secret'] ?? '',
                    'hubtel_environment' => $_POST['environment'] ?? 'live',
                    'batch_status_api_key' => $_POST['batch_api_key'] ?? bin2hex(random_bytes(16))
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO system_settings (setting_key, setting_value) 
                        VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE setting_value = ?
                    ");
                    $stmt->execute([$key, $value, $value]);
                }
                
                $message = 'Hubtel settings configured successfully!';
                break;
                
            case 'test_connection':
                // Test Hubtel connection
                require_once __DIR__ . '/../services/HubtelTransactionStatusService.php';
                $statusService = new HubtelTransactionStatusService();
                
                // Try to get pending transactions (this tests database and service setup)
                $pending = $statusService->getPendingTransactions(1);
                $message = 'Connection test successful! Service is properly configured.';
                break;
                
            case 'create_api_key':
                // Generate new API key for batch operations
                $newApiKey = bin2hex(random_bytes(32));
                $stmt = $pdo->prepare("
                    INSERT INTO system_settings (setting_key, setting_value) 
                    VALUES ('batch_status_api_key', ?) 
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$newApiKey, $newApiKey]);
                
                $message = 'New API key generated: ' . $newApiKey;
                break;
                
            case 'check_schema':
                // Check if extended schema is available
                $stmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'hubtel_transaction_id'");
                $hasExtended = $stmt->rowCount() > 0;
                
                if ($hasExtended) {
                    $message = 'Extended schema detected - full Hubtel integration available';
                } else {
                    $message = 'Basic schema detected - limited Hubtel data storage';
                }
                break;
        }
        
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Get current settings
$currentSettings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'hubtel_%' OR setting_key = 'batch_status_api_key'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $currentSettings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    $error = 'Could not load current settings: ' . $e->getMessage();
}

// Check system status
$systemStatus = [];
try {
    // Check pending transactions
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM transactions 
        WHERE status IN ('pending', 'processing') 
        AND payment_method IN ('mobile_money', 'hubtel_checkout', 'hubtel_ussd')
    ");
    $stmt->execute();
    $systemStatus['pending_transactions'] = $stmt->fetchColumn();
    
    // Check last status check run
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'last_status_check_run'");
    $stmt->execute();
    $systemStatus['last_status_check'] = $stmt->fetchColumn() ?: 'Never';
    
    // Check if cron logs exist
    $cronLogPath = __DIR__ . '/../logs/cron-status-check.log';
    $systemStatus['cron_log_exists'] = file_exists($cronLogPath);
    $systemStatus['cron_log_size'] = file_exists($cronLogPath) ? filesize($cronLogPath) : 0;
    
} catch (Exception $e) {
    $systemStatus['error'] = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hubtel Transaction Status Check Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-danger {
            background: #dc3545;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .status-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        .code-block {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Hubtel Transaction Status Check Setup</h1>
            <p>Configure and test the automatic transaction status checking system</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- System Status -->
        <div class="section">
            <h2>📊 System Status</h2>
            <div class="status-grid">
                <div class="status-item">
                    <strong>Pending Transactions</strong><br>
                    <?= $systemStatus['pending_transactions'] ?? 'Unknown' ?>
                </div>
                <div class="status-item">
                    <strong>Last Status Check</strong><br>
                    <?= htmlspecialchars($systemStatus['last_status_check'] ?? 'Unknown') ?>
                </div>
                <div class="status-item">
                    <strong>Cron Log Status</strong><br>
                    <?= $systemStatus['cron_log_exists'] ? 'Exists (' . number_format($systemStatus['cron_log_size']) . ' bytes)' : 'Not found' ?>
                </div>
                <div class="status-item">
                    <strong>Service Status</strong><br>
                    <?= !empty($currentSettings['hubtel_pos_id']) ? 'Configured' : 'Not configured' ?>
                </div>
            </div>
        </div>

        <!-- Configuration -->
        <div class="section">
            <h2>⚙️ Hubtel Configuration</h2>
            <form method="POST">
                <input type="hidden" name="action" value="configure_settings">
                
                <div class="form-group">
                    <label for="pos_id">POS Sales ID</label>
                    <input type="text" id="pos_id" name="pos_id" 
                           value="<?= htmlspecialchars($currentSettings['hubtel_pos_id'] ?? '') ?>" 
                           placeholder="Your Hubtel POS Sales ID" required>
                </div>
                
                <div class="form-group">
                    <label for="api_key">API Key</label>
                    <input type="text" id="api_key" name="api_key" 
                           value="<?= htmlspecialchars($currentSettings['hubtel_api_key'] ?? '') ?>" 
                           placeholder="Your Hubtel API Key" required>
                </div>
                
                <div class="form-group">
                    <label for="api_secret">API Secret</label>
                    <input type="password" id="api_secret" name="api_secret" 
                           value="<?= htmlspecialchars($currentSettings['hubtel_api_secret'] ?? '') ?>" 
                           placeholder="Your Hubtel API Secret" required>
                </div>
                
                <div class="form-group">
                    <label for="environment">Environment</label>
                    <select id="environment" name="environment">
                        <option value="live" <?= ($currentSettings['hubtel_environment'] ?? '') === 'live' ? 'selected' : '' ?>>Live</option>
                        <option value="sandbox" <?= ($currentSettings['hubtel_environment'] ?? '') === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="batch_api_key">Batch API Key (for cron access)</label>
                    <input type="text" id="batch_api_key" name="batch_api_key" 
                           value="<?= htmlspecialchars($currentSettings['batch_status_api_key'] ?? '') ?>" 
                           placeholder="API key for batch operations">
                </div>
                
                <button type="submit" class="btn">💾 Save Configuration</button>
            </form>
        </div>

        <!-- Testing -->
        <div class="section">
            <h2>🧪 Testing & Validation</h2>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="test_connection">
                <button type="submit" class="btn btn-success">🔌 Test Connection</button>
            </form>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="check_schema">
                <button type="submit" class="btn btn-warning">🗄️ Check Database Schema</button>
            </form>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="create_api_key">
                <button type="submit" class="btn">🔑 Generate New API Key</button>
            </form>
        </div>

        <!-- Cron Setup Instructions -->
        <div class="section">
            <h2>⏰ Cron Job Setup</h2>
            <p>Set up automatic status checking by adding one of these cron jobs:</p>
            
            <h4>Linux/Unix Cron (Recommended - every 10 minutes):</h4>
            <div class="code-block">*/10 * * * * /usr/bin/php <?= __DIR__ ?>/../cron/status-check-cron.php</div>
            
            <h4>Web-based Cron (if command line access not available):</h4>
            <div class="code-block">*/10 * * * * wget -q -O - "<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ?>/baroncast/cron/status-check-cron.php?api_key=<?= htmlspecialchars($currentSettings['batch_status_api_key'] ?? 'YOUR_API_KEY') ?>"</div>
            
            <h4>Windows Task Scheduler:</h4>
            <div class="code-block">Program: php.exe
Arguments: "<?= __DIR__ ?>/../cron/status-check-cron.php"
Trigger: Every 10 minutes</div>
        </div>

        <!-- Quick Actions -->
        <div class="section">
            <h2>🚀 Quick Actions</h2>
            <a href="../admin/transaction-status-checker.html" class="btn">🔍 Open Status Checker</a>
            <a href="../admin/batch-status-check.php" class="btn btn-success">⚡ Run Batch Check Now</a>
            <a href="../logs/" class="btn btn-warning">📋 View Logs</a>
        </div>

        <!-- Current Configuration Display -->
        <?php if (!empty($currentSettings)): ?>
        <div class="section">
            <h2>📋 Current Configuration</h2>
            <div class="status-grid">
                <?php foreach ($currentSettings as $key => $value): ?>
                <div class="status-item">
                    <strong><?= htmlspecialchars(ucwords(str_replace(['hubtel_', '_'], ['', ' '], $key))) ?></strong><br>
                    <?php if (strpos($key, 'secret') !== false || strpos($key, 'key') !== false): ?>
                        <?= $value ? str_repeat('*', min(strlen($value), 20)) : 'Not set' ?>
                    <?php else: ?>
                        <?= htmlspecialchars($value ?: 'Not set') ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Documentation Links -->
        <div class="section">
            <h2>📚 Documentation & Resources</h2>
            <ul>
                <li><a href="../docs/HUBTEL_TRANSACTION_STATUS_CHECK.md">📖 Complete Documentation</a></li>
                <li><a href="https://developers.hubtel.com/" target="_blank">🌐 Hubtel Developer Documentation</a></li>
                <li><a href="../admin/api/pending-transactions.php?action=summary" target="_blank">📊 Pending Transactions API</a></li>
            </ul>
        </div>
    </div>
</body>
</html>
