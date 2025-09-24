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

// Get pending transactions count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_count
    FROM transactions 
    WHERE status = 'pending' 
    AND payment_method = 'mobile_money'
    AND created_at <= NOW() - INTERVAL 5 MINUTE
");
$stmt->execute();
$pending_count = $stmt->fetchColumn();

// Get recent transaction logs
$stmt = $pdo->prepare("
    SELECT * FROM hubtel_transaction_logs 
    WHERE log_type = 'status_check' 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get transaction summary
$stmt = $pdo->prepare("
    SELECT 
        status,
        COUNT(*) as count,
        SUM(amount) as total_amount
    FROM transactions 
    WHERE payment_method = 'mobile_money'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY status
");
$stmt->execute();
$summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Status Monitor - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/responsive-sidebar.css" rel="stylesheet">
    <style>
        .status-card {
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .status-pending { border-left: 4px solid #f59e0b; }
        .status-completed { border-left: 4px solid #10b981; }
        .status-failed { border-left: 4px solid #ef4444; }
        .log-entry {
            background: var(--bs-secondary-bg);
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
        }
        .check-button {
            background: var(--bs-primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .check-button:hover {
            background: var(--bs-primary);
            opacity: 0.8;
            transform: translateY(-1px);
        }
        .check-button:disabled {
            background: #6b7280;
            cursor: not-allowed;
            transform: none;
        }
        .output-area {
            background: #1f2937;
            border-radius: 6px;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            color: #e5e7eb;
            max-height: 400px;
            overflow-y: auto;
            display: none;
        }
        .output-area.show {
            display: block;
        }
    </style>
</head>
<body>
    <?php 
    $pageTitle = 'Transaction Monitor';
    include 'includes/header.php'; 
    include 'includes/sidebar.php'; 
    ?>
    <div class="main-content">
        <div class="container-fluid p-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1"><i class="fas fa-heartbeat me-2"></i>Transaction Status Monitor</h2>
                    <p class="text-muted mb-0">Monitor and check Hubtel transaction statuses</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="hubtel-settings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                    <a href="hubtel-debug.php" class="btn btn-outline-primary">
                        <i class="fas fa-bug me-2"></i>Debug Tools
                    </a>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card status-card status-pending">
                        <h4><i class="fas fa-clock me-2"></i>Pending Checks</h4>
                        <h2><?php echo $pending_count; ?></h2>
                        <p class="text-muted mb-0">Transactions needing status check</p>
                    </div>
                </div>
                
                <?php foreach ($summary as $stat): ?>
                <div class="col-md-3">
                    <div class="card status-card status-<?php echo $stat['status']; ?>">
                        <h4><i class="fas fa-<?php echo $stat['status'] === 'completed' ? 'check-circle' : ($stat['status'] === 'failed' ? 'times-circle' : 'clock'); ?> me-2"></i> 
                        <?php echo ucfirst($stat['status']); ?></h4>
                        <h2><?php echo $stat['count']; ?></h2>
                        <p class="text-muted mb-0">₵<?php echo number_format($stat['total_amount'], 2); ?> (24h)</p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Manual Status Check -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-play me-2"></i>Manual Status Check</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted">Check the status of all pending transactions with Hubtel. This will verify payment status and update vote records accordingly.</p>
                    
                    <div class="d-flex gap-3 mb-3">
                        <button id="runCheckBtn" class="check-button">
                            <i class="fas fa-sync me-2"></i>Run Status Check
                        </button>
                        <button id="viewLogsBtn" class="btn btn-outline-secondary">
                            <i class="fas fa-list me-2"></i>View Recent Logs
                        </button>
                    </div>
                    
                    <div id="outputArea" class="output-area">
                        <div id="outputContent"></div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Logs -->
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-history me-2"></i>Recent Status Checks</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_logs)): ?>
                        <p class="text-muted">No status checks have been run recently.</p>
                    <?php else: ?>
                        <?php foreach ($recent_logs as $log): ?>
                            <?php 
                            $log_data = json_decode($log['log_data'], true);
                            $timestamp = date('M j, Y H:i:s', strtotime($log['created_at']));
                            ?>
                            <div class="log-entry">
                                <strong><?php echo $timestamp; ?></strong> - 
                                Checked: <?php echo $log_data['checked'] ?? 0; ?>, 
                                Completed: <span style="color: #10b981;"><?php echo $log_data['completed'] ?? 0; ?></span>, 
                                Failed: <span style="color: #ef4444;"><?php echo $log_data['failed'] ?? 0; ?></span>, 
                                Still Pending: <?php echo $log_data['still_pending'] ?? 0; ?>
                                <?php if (($log_data['errors'] ?? 0) > 0): ?>
                                , <span style="color: #f59e0b;">Errors: <?php echo $log_data['errors']; ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('runCheckBtn').addEventListener('click', function() {
            const btn = this;
            const outputArea = document.getElementById('outputArea');
            const outputContent = document.getElementById('outputContent');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Running Check...';
            
            outputArea.classList.add('show');
            outputContent.innerHTML = '<div style="color: #3b82f6;">Starting status check...</div>';
            
            // Use fetch to stream the output
            fetch('../check-hubtel-status.php')
                .then(response => response.text())
                .then(data => {
                    // Extract the content between body tags if it's HTML
                    const bodyMatch = data.match(/<body[^>]*>(.*?)<\/body>/s);
                    if (bodyMatch) {
                        outputContent.innerHTML = bodyMatch[1];
                    } else {
                        outputContent.innerHTML = data.replace(/\n/g, '<br>');
                    }
                })
                .catch(error => {
                    outputContent.innerHTML += '<div style="color: #ef4444;">Error: ' + error.message + '</div>';
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-sync me-2"></i>Run Status Check';
                    
                    // Refresh page after 3 seconds to show updated counts
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                });
        });
        
        document.getElementById('viewLogsBtn').addEventListener('click', function() {
            const outputArea = document.getElementById('outputArea');
            outputArea.classList.toggle('show');
        });
        
        // Auto-refresh every 30 seconds
        setInterval(() => {
            const pendingCountElement = document.querySelector('.status-pending h2');
            if (pendingCountElement) {
                fetch('actions/get-pending-count.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            pendingCountElement.textContent = data.count;
                        }
                    })
                    .catch(error => console.error('Error updating pending count:', error));
            }
        }, 30000);
    </script>
</body>
</html>