<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch all transactions with related info
$stmt = $pdo->prepare("
    SELECT t.*, e.title as event_title, u.full_name as organizer_name
    FROM transactions t 
    LEFT JOIN events e ON t.event_id = e.id 
    LEFT JOIN users u ON t.organizer_id = u.id 
    ORDER BY t.created_at DESC
");
$stmt->execute();
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalRevenue = array_sum(array_column($transactions, 'amount'));
$todayTransactions = array_filter($transactions, fn($t) => date('Y-m-d', strtotime($t['created_at'])) === date('Y-m-d'));
$pendingTransactions = array_filter($transactions, fn($t) => $t['status'] === 'pending');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Management - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-logo">
                    <i class="fas fa-shield-alt"></i>
                    <span>E-Cast Admin</span>
                </a>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-item">
                    <a href="index.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users-cog"></i>
                        <span>User Management</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="events.php" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Event Management</span>
                    </a>
                </div>
                
                <!-- Financial Management -->
                <div class="nav-item">
                    <a href="transactions.php" class="nav-link active">
                        <i class="fas fa-money-check-alt"></i>
                        <span>Financial Management</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                    </div>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button id="sidebar-toggle" class="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title mb-0">Financial Management</h1>
                </div>
                
                <div class="header-right">
                    <button id="theme-toggle" class="theme-toggle">
                        <i id="theme-icon" class="fas fa-moon"></i>
                        <span id="theme-text">Dark</span>
                    </button>
                    
                    <div class="dropdown">
                        <button class="btn btn-outline dropdown-toggle">
                            <i class="fas fa-user-shield"></i>
                            <span><?= htmlspecialchars($user['full_name']) ?></span>
                        </button>
                        <div class="dropdown-menu">
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user-cog"></i>
                                Profile
                            </a>
                            <a href="settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i>
                                Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="../logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content" style="padding: 0 2rem;">
                <div class="page-header">
                    <h2 class="page-title">Financial Management</h2>
                    <p class="page-subtitle">Monitor revenue, transactions, and financial analytics.</p>
                </div>
                <!-- Platform Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value">$<?= number_format($totalRevenue, 2) ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value"><?= count($transactions) ?></div>
                        <div class="stat-label">Total Transactions</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value"><?= count($todayTransactions) ?></div>
                        <div class="stat-label">Today's Transactions</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-value"><?= count($pendingTransactions) ?></div>
                        <div class="stat-label">Pending Transactions</div>
                    </div>
                </div>

                <!-- Quick Actions Row -->
                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--primary-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;">Revenue Report</div>
                        <div class="stat-label">Financial Analytics</div>
                        <button class="btn btn-primary btn-sm" style="margin-top: 0.5rem;">Generate</button>
                    </div>
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--success-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;">Process Pending</div>
                        <div class="stat-label">Approve Transactions</div>
                        <button class="btn btn-success btn-sm" style="margin-top: 0.5rem;">Process</button>
                    </div>
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--warning-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-download"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;">Export Data</div>
                        <div class="stat-label">Download Records</div>
                        <button class="btn btn-warning btn-sm" style="margin-top: 0.5rem;">Export</button>
                    </div>
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--info-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-filter"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;">Filter & Search</div>
                        <div class="stat-label">Advanced Filters</div>
                        <button class="btn btn-info btn-sm" style="margin-top: 0.5rem;">Filter</button>
                    </div>
                </div>

                <!-- Transaction Management Table -->
                <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Financial Management</h3>
                            <div style="display: flex; gap: 0.5rem;">
                                <button class="btn btn-outline btn-sm">
                                    <i class="fas fa-calendar"></i> Date Range
                                </button>
                                <button class="btn btn-outline btn-sm">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Transaction Details</th>
                                            <th>Organizer & Event</th>
                                            <th>Amount & Type</th>
                                            <th>Status & Date</th>
                                            <th>Reference</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div style="width: 40px; height: 40px; border-radius: 8px; background: linear-gradient(135deg, var(--success-color), var(--primary-color)); display: flex; align-items: center; justify-content: center; color: white;">
                                                        <i class="fas fa-receipt"></i>
                                                    </div>
                                                    <div>
                                                        <div style="font-weight: 600; color: var(--text-color);">#<?= $transaction['id'] ?></div>
                                                        <div style="font-size: 0.875rem; color: var(--text-muted);">Transaction ID</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                                                        <div style="width: 24px; height: 24px; border-radius: 50%; background: var(--warning-color); display: flex; align-items: center; justify-content: center; color: white;">
                                                            <i class="fas fa-user-tie" style="font-size: 0.75rem;"></i>
                                                        </div>
                                                        <div style="font-weight: 500; color: var(--text-color);"><?= htmlspecialchars($transaction['organizer_name'] ?? 'N/A') ?></div>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-muted); margin-left: 1.75rem;">
                                                        <?= htmlspecialchars($transaction['event_title'] ?? 'N/A') ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="text-align: center;">
                                                    <div style="font-weight: 600; color: var(--success-color); font-size: 1.125rem;">$<?= number_format($transaction['amount'], 2) ?></div>
                                                    <span class="badge badge-info" style="margin-top: 0.25rem;">
                                                        <?= ucfirst(str_replace('_', ' ', $transaction['type'] ?? 'payment')) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = 'secondary';
                                                $statusIcon = 'circle';
                                                if ($transaction['status'] === 'completed') {
                                                    $statusClass = 'success';
                                                    $statusIcon = 'check-circle';
                                                } elseif ($transaction['status'] === 'pending') {
                                                    $statusClass = 'warning';
                                                    $statusIcon = 'clock';
                                                } elseif ($transaction['status'] === 'failed') {
                                                    $statusClass = 'danger';
                                                    $statusIcon = 'times-circle';
                                                }
                                                ?>
                                                <div>
                                                    <span class="badge badge-<?= $statusClass ?>" style="margin-bottom: 0.25rem;">
                                                        <i class="fas fa-<?= $statusIcon ?>"></i> <?= ucfirst($transaction['status']) ?>
                                                    </span>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                        <?= date('M j, Y H:i', strtotime($transaction['created_at'])) ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem; color: var(--text-muted); font-family: monospace;">
                                                    <?= htmlspecialchars($transaction['reference'] ?? 'N/A') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.25rem;">
                                                    <button class="btn btn-outline btn-sm" title="View Details" onclick="viewTransaction(<?= $transaction['id'] ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline btn-sm" title="Download Receipt" onclick="downloadReceipt(<?= $transaction['id'] ?>)">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                    <?php if ($transaction['status'] === 'pending'): ?>
                                                    <button class="btn btn-outline btn-sm btn-success" title="Approve" onclick="approveTransaction(<?= $transaction['id'] ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <div class="dropdown" style="position: relative; display: inline-block;">
                                                        <button class="btn btn-outline btn-sm dropdown-toggle" title="More Options" onclick="toggleDropdown(<?= $transaction['id'] ?>)">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <div class="dropdown-menu" id="dropdown-<?= $transaction['id'] ?>" style="display: none; position: absolute; right: 0; top: 100%; z-index: 1000; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 0.375rem; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); min-width: 150px;">
                                                            <a href="#" class="dropdown-item" onclick="refundTransaction(<?= $transaction['id'] ?>)" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: var(--text-primary);">
                                                                <i class="fas fa-undo"></i> Refund
                                                            </a>
                                                            <a href="#" class="dropdown-item" onclick="resendReceipt(<?= $transaction['id'] ?>)" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: var(--text-primary);">
                                                                <i class="fas fa-envelope"></i> Resend Receipt
                                                            </a>
                                                            <div class="dropdown-divider" style="border-top: 1px solid var(--border-color); margin: 0.25rem 0;"></div>
                                                            <a href="#" class="dropdown-item text-danger" onclick="flagTransaction(<?= $transaction['id'] ?>)" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: var(--danger-color);">
                                                                <i class="fas fa-flag"></i> Flag as Suspicious
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Financial Management Action Functions
        function viewTransaction(transactionId) {
            window.location.href = `transaction-details.php?id=${transactionId}`;
        }
        
        function downloadReceipt(transactionId) {
            window.open(`actions/download-receipt.php?id=${transactionId}`, '_blank');
        }
        
        function approveTransaction(transactionId) {
            if (confirm('Are you sure you want to approve this transaction?')) {
                fetch('actions/approve-transaction.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ transaction_id: transactionId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Transaction approved successfully!');
                        location.reload();
                    } else {
                        alert('Error approving transaction: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error approving transaction');
                });
            }
        }
        
        function refundTransaction(transactionId) {
            if (confirm('Are you sure you want to refund this transaction? This action cannot be undone.')) {
                fetch('actions/refund-transaction.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ transaction_id: transactionId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Transaction refunded successfully!');
                        location.reload();
                    } else {
                        alert('Error refunding transaction: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error refunding transaction');
                });
            }
        }
        
        function resendReceipt(transactionId) {
            fetch('actions/resend-receipt.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ transaction_id: transactionId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Receipt resent successfully!');
                } else {
                    alert('Error resending receipt: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error resending receipt');
            });
        }
        
        function flagTransaction(transactionId) {
            if (confirm('Are you sure you want to flag this transaction as suspicious?')) {
                fetch('actions/flag-transaction.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ transaction_id: transactionId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Transaction flagged successfully!');
                        location.reload();
                    } else {
                        alert('Error flagging transaction: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error flagging transaction');
                });
            }
        }
        
        function toggleDropdown(transactionId) {
            const dropdown = document.getElementById(`dropdown-${transactionId}`);
            const isVisible = dropdown.style.display === 'block';
            
            // Hide all dropdowns first
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
            });
            
            // Toggle current dropdown
            dropdown.style.display = isVisible ? 'none' : 'block';
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>
