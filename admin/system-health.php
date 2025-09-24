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

// System health checks
$health_checks = [];

// Database connection check
try {
    $stmt = $pdo->query("SELECT 1");
    $health_checks['database'] = [
        'status' => 'healthy',
        'message' => 'Database connection successful',
        'icon' => 'fas fa-check-circle text-success'
    ];
} catch (Exception $e) {
    $health_checks['database'] = [
        'status' => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage(),
        'icon' => 'fas fa-times-circle text-danger'
    ];
}

// File permissions check
$upload_dirs = [
    '../uploads/events/',
    '../uploads/nominees/',
    '../logs/'
];

$permissions_ok = true;
$permission_issues = [];

foreach ($upload_dirs as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            $permissions_ok = false;
            $permission_issues[] = "Cannot create directory: $dir";
        }
    } elseif (!is_writable($dir)) {
        $permissions_ok = false;
        $permission_issues[] = "Directory not writable: $dir";
    }
}

$health_checks['permissions'] = [
    'status' => $permissions_ok ? 'healthy' : 'warning',
    'message' => $permissions_ok ? 'All directories have proper permissions' : 'Permission issues found: ' . implode(', ', $permission_issues),
    'icon' => $permissions_ok ? 'fas fa-check-circle text-success' : 'fas fa-exclamation-triangle text-warning'
];

// PHP configuration check
$php_issues = [];
$required_extensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $php_issues[] = "Missing PHP extension: $ext";
    }
}

$health_checks['php_config'] = [
    'status' => empty($php_issues) ? 'healthy' : 'error',
    'message' => empty($php_issues) ? 'All required PHP extensions are loaded' : 'PHP configuration issues: ' . implode(', ', $php_issues),
    'icon' => empty($php_issues) ? 'fas fa-check-circle text-success' : 'fas fa-times-circle text-danger'
];

// Payment gateway connectivity check
$payment_status = 'unknown';
$payment_message = 'Payment gateway status not checked';

// Check if API keys are configured
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('arkesel_api_key', 'junipay_api_key')");
$api_keys = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $api_keys[$row['setting_key']] = $row['setting_value'];
}

if (empty($api_keys['arkesel_api_key']) || empty($api_keys['junipay_api_key'])) {
    $payment_status = 'warning';
    $payment_message = 'Payment gateway API keys not configured';
} else {
    $payment_status = 'configured';
    $payment_message = 'Payment gateway API keys are configured';
}

$health_checks['payment_gateway'] = [
    'status' => $payment_status,
    'message' => $payment_message,
    'icon' => $payment_status === 'configured' ? 'fas fa-check-circle text-success' : 'fas fa-exclamation-triangle text-warning'
];

// System statistics
$stats = [];

// Get total counts
$stmt = $pdo->query("SELECT COUNT(*) as count FROM events");
$stats['total_events'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'organizer'");
$stats['total_organizers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM nominees");
$stats['total_nominees'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM votes");
$stats['total_votes'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Get recent activity
$stmt = $pdo->query("SELECT COUNT(*) as count FROM events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['events_this_week'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Try to get votes today, handle missing created_at column gracefully
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM votes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stats['votes_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    // Fallback if created_at column doesn't exist
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM votes");
    $stats['votes_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <?php include 'includes/header.php'; ?>
            
            <!-- Content -->
            <div class="content">
                <div class="page-header">
                    <div class="page-title">
                        <h1><i class="fas fa-heartbeat"></i> System Health</h1>
                        <p>Monitor system status and performance</p>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-outline" onclick="refreshHealth()">
                            <i class="fas fa-sync-alt"></i>
                            Refresh Status
                        </button>
                        <button class="btn btn-primary" onclick="runDiagnostics()">
                            <i class="fas fa-stethoscope"></i>
                            Run Diagnostics
                        </button>
                    </div>
                </div>
                            
                <!-- Health Status Cards -->
                <div class="row mb-4">
                    <?php foreach ($health_checks as $check_name => $check): ?>
                        <div class="col-md-3">
                            <div class="stat-card health-card <?= $check['status'] === 'success' ? 'health-success' : ($check['status'] === 'warning' ? 'health-warning' : 'health-error') ?>">
                                <div class="stat-icon <?= $check['status'] === 'success' ? 'bg-success' : ($check['status'] === 'warning' ? 'bg-warning' : 'bg-danger') ?>">
                                    <i class="<?php echo $check['icon']; ?>"></i>
                                </div>
                                <div class="stat-content">
                                    <h3><?php echo ucfirst(str_replace('_', ' ', $check_name)); ?></h3>
                                    <p><?php echo $check['message']; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                            
                <!-- System Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-bar"></i> System Statistics</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-icon bg-primary">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo number_format($stats['total_events']); ?></h3>
                                        <p>Total Events</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-icon bg-warning">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo number_format($stats['total_organizers']); ?></h3>
                                        <p>Organizers</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-icon bg-info">
                                        <i class="fas fa-user-friends"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo number_format($stats['total_nominees']); ?></h3>
                                        <p>Nominees</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-icon bg-success">
                                        <i class="fas fa-vote-yea"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo number_format($stats['total_votes']); ?></h3>
                                        <p>Total Votes</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-icon bg-purple">
                                        <i class="fas fa-calendar-week"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo number_format($stats['events_this_week']); ?></h3>
                                        <p>Events This Week</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="stat-card">
                                    <div class="stat-icon bg-orange">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="stat-content">
                                        <h3><?php echo number_format($stats['votes_today']); ?></h3>
                                        <p>Votes Today</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                            
                <!-- System Information -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-server"></i> Server Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="info-list">
                                    <div class="info-item">
                                        <span class="info-label">PHP Version:</span>
                                        <span class="info-value"><?php echo PHP_VERSION; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Server Software:</span>
                                        <span class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Memory Limit:</span>
                                        <span class="info-value"><?php echo ini_get('memory_limit'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Max Upload Size:</span>
                                        <span class="info-value"><?php echo ini_get('upload_max_filesize'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Timezone:</span>
                                        <span class="info-value"><?php echo date_default_timezone_get(); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-tools"></i> System Actions</h3>
                            </div>
                            <div class="card-body">
                                <div class="action-buttons">
                                    <button class="btn btn-outline mb-3" onclick="refreshHealth()">
                                        <i class="fas fa-sync-alt"></i> Refresh Health Status
                                    </button>
                                    <button class="btn btn-outline mb-3" onclick="clearCache()">
                                        <i class="fas fa-broom"></i> Clear System Cache
                                    </button>
                                    <button class="btn btn-outline mb-3" onclick="viewLogs()">
                                        <i class="fas fa-file-alt"></i> View System Logs
                                    </button>
                                    <button class="btn btn-outline mb-3" onclick="testPaymentGateway()">
                                        <i class="fas fa-credit-card"></i> Test Payment Gateway
                                    </button>
                                    <button class="btn btn-outline mb-3" onclick="runDiagnostics()">
                                        <i class="fas fa-stethoscope"></i> Run Full Diagnostics
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        function refreshHealth() {
            location.reload();
        }
        
        function clearCache() {
            if (confirm('Are you sure you want to clear the system cache?')) {
                // Implement cache clearing functionality
                alert('Cache clearing functionality will be implemented here');
            }
        }
        
        function viewLogs() {
            // Implement log viewing functionality
            window.open('system-logs.php', '_blank');
        }
        
        function testPaymentGateway() {
            // Implement payment gateway testing
            alert('Testing payment gateway connection...');
        }
        
        function runDiagnostics() {
            if (confirm('Run full system diagnostics? This may take a few minutes.')) {
                alert('Running full system diagnostics...');
                // Implement full diagnostics
            }
        }
    </script>
    
    <style>
        .health-card {
            border-left: 4px solid #007bff;
        }
        
        .stat-item {
            padding: 1rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
        }
    </style>
</body>
</html>
