<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();

// Get withdrawal statistics (handle missing table gracefully)
try {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_completed,
            SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
            SUM(CASE WHEN status = 'completed' AND DATE(updated_at) = CURDATE() THEN amount ELSE 0 END) as approved_today,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
        FROM withdrawal_requests
    ");
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle missing table
    $stats = [
        'total_completed' => 0,
        'total_pending' => 0,
        'approved_today' => 0,
        'pending_count' => 0
    ];
    error_log("Withdrawal stats error: " . $e->getMessage());
}

// Get commission rate for processing fee calculation
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'commission_rate'");
$stmt->execute();
$commissionRate = floatval($stmt->fetchColumn() ?: 10);

// Get all withdrawal requests with organizer details
$stmt = $pdo->prepare("
    SELECT 
        wr.*,
        u.full_name as organizer_name,
        u.email as organizer_email,
        u.phone as organizer_phone,
        processed_by.full_name as processed_by_name
    FROM withdrawal_requests wr
    JOIN users u ON wr.organizer_id = u.id
    LEFT JOIN users processed_by ON wr.processed_by = processed_by.id
    ORDER BY 
        CASE wr.status 
            WHEN 'pending' THEN 1 
            WHEN 'processing' THEN 2 
            WHEN 'completed' THEN 3 
            WHEN 'rejected' THEN 4 
        END,
        wr.created_at DESC
");
$stmt->execute();
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawals - E-Cast Admin</title>
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
                    <a href="#" class="nav-link" data-submenu="users-menu">
                        <i class="fas fa-users"></i>
                        <span>User Management</span>
                        <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="users-menu" class="nav-submenu">
                        <a href="users.php" class="nav-link">
                            <i class="fas fa-user"></i>
                            <span>All Users</span>
                        </a>
                        <a href="organizers.php" class="nav-link">
                            <i class="fas fa-user-tie"></i>
                            <span>Event Organizers</span>
                        </a>
                        <a href="voters.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>Voters</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link" data-submenu="events-menu">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Event Management</span>
                        <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="events-menu" class="nav-submenu">
                        <a href="events.php" class="nav-link">
                            <i class="fas fa-calendar"></i>
                            <span>All Events</span>
                        </a>
                        <a href="categories.php" class="nav-link">
                            <i class="fas fa-tags"></i>
                            <span>Categories</span>
                        </a>
                        <a href="nominees.php" class="nav-link">
                            <i class="fas fa-user-friends"></i>
                            <span>Nominees</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link" data-submenu="financial-menu">
                        <i class="fas fa-dollar-sign"></i>
                        <span>Financial Management</span>
                        <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="financial-menu" class="nav-submenu">
                        <a href="votes-payments.php" class="nav-link">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Votes Payments</span>
                        </a>
                        <a href="withdrawal.php" class="nav-link active">
                            <i class="fas fa-hand-holding-usd"></i>
                            <span>Withdrawals</span>
                        </a>
                        <a href="bulk-votes.php" class="nav-link">
                            <i class="fas fa-layer-group"></i>
                            <span>Bulk Votes</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link" data-submenu="analytics-menu">
                        <i class="fas fa-chart-bar"></i>
                        <span>Analytics & Reports</span>
                        <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="analytics-menu" class="nav-submenu">
                        <a href="category-tally.php" class="nav-link">
                            <i class="fas fa-list-alt"></i>
                            <span>Category Tally</span>
                        </a>
                        <a href="nominees-tally.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>Nominees Tally</span>
                        </a>
                        <a href="scheme.php" class="nav-link">
                            <i class="fas fa-cogs"></i>
                            <span>Schemes</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link" data-submenu="settings-menu">
                        <i class="fas fa-cog"></i>
                        <span>Platform Settings</span>
                        <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="settings-menu" class="nav-submenu">
                        <a href="general-settings.php" class="nav-link">
                            <i class="fas fa-sliders-h"></i>
                            <span>General Settings</span>
                        </a>
                        <a href="payment-settings.php" class="nav-link">
                            <i class="fas fa-credit-card"></i>
                            <span>Payment Settings</span>
                        </a>
                    </div>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <button id="sidebar-toggle" class="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1 class="page-title mb-0">Withdrawals</h1>
                        <nav style="font-size: 0.875rem; color: var(--text-secondary);">
                            <a href="index.php" style="color: var(--text-secondary); text-decoration: none;">Home</a>
                            <span style="margin: 0 0.5rem;">/</span>
                            <span>Financial Management</span>
                            <span style="margin: 0 0.5rem;">/</span>
                            <span>Withdrawals</span>
                        </nav>
                    </div>
                </div>
                
                <div class="header-right">
                    <button id="theme-toggle" class="theme-toggle">
                        <i id="theme-icon" class="fas fa-moon"></i>
                        <span id="theme-text">Dark</span>
                    </button>
                    
                    <div class="dropdown">
                        <button class="btn btn-outline dropdown-toggle">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($user['full_name']); ?></span>
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
            <div class="content">
                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Total Withdrawals</h6>
                                        <h3 class="card-title mb-0">GH₵<?= number_format($stats['total_completed'] ?? 0, 2) ?></h3>
                                    </div>
                                    <div class="text-primary">
                                        <i class="fas fa-hand-holding-usd fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Pending Requests</h6>
                                        <h3 class="card-title mb-0">GH₵<?= number_format($stats['total_pending'] ?? 0, 2) ?></h3>
                                        <small class="text-muted"><?= $stats['pending_count'] ?? 0 ?> requests</small>
                                    </div>
                                    <div class="text-warning">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Approved Today</h6>
                                        <h3 class="card-title mb-0">GH₵<?= number_format($stats['approved_today'] ?? 0, 2) ?></h3>
                                    </div>
                                    <div class="text-success">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-subtitle mb-2 text-muted">Commission Rate</h6>
                                        <h3 class="card-title mb-0"><?= number_format($commissionRate, 1) ?>%</h3>
                                    </div>
                                    <div class="text-info">
                                        <i class="fas fa-percentage fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Table Card -->
                <div class="card">
                    <div class="card-body" style="padding: 0;">
                        <!-- Table Controls -->
                        <div style="padding: 1.5rem 1.5rem 0 1.5rem;">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <label>Show</label>
                                    <select class="form-control" style="width: auto; display: inline-block;">
                                        <option>10</option>
                                        <option>25</option>
                                        <option>50</option>
                                        <option>100</option>
                                    </select>
                                    <label>entries</label>
                                </div>
                                <div>
                                    <label>Search:</label>
                                    <input type="text" class="form-control" style="width: 200px; display: inline-block; margin-left: 0.5rem;" placeholder="Search...">
                                </div>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="table-responsive">
                            <table class="table" style="margin-bottom: 0;">
                                <thead>
                                    <tr>
                                        <th>Sl</th>
                                        <th>Organizer</th>
                                        <th>Event</th>
                                        <th>Amount</th>
                                        <th>Fee</th>
                                        <th>Net Amount</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th>Request Date</th>
                                        <th>Tools</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($withdrawals)): ?>
                                        <?php foreach ($withdrawals as $index => $withdrawal): ?>
                                            <?php 
                                            $processingFee = ($withdrawal['amount'] * $commissionRate) / 100;
                                            $netAmount = $withdrawal['amount'] - $processingFee;
                                            ?>
                                            <tr>
                                                <td><?= str_pad($withdrawal['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($withdrawal['organizer_name']) ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars($withdrawal['organizer_email']) ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="text-muted">Multiple Events</span>
                                                    <br>
                                                    <small>Withdrawal Request</small>
                                                </td>
                                                <td>GH₵<?= number_format($withdrawal['amount'], 2) ?></td>
                                                <td>GH₵<?= number_format($processingFee, 2) ?></td>
                                                <td>GH₵<?= number_format($netAmount, 2) ?></td>
                                                <td>
                                                    <?php 
                                                    $method = ucfirst(str_replace('_', ' ', $withdrawal['withdrawal_method']));
                                                    $methodColor = $withdrawal['withdrawal_method'] === 'mobile_money' ? '#007bff' : '#28a745';
                                                    ?>
                                                    <span class="badge" style="background-color: <?= $methodColor ?>; color: white;">
                                                        <?= $method ?>
                                                    </span>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php if ($withdrawal['withdrawal_method'] === 'mobile_money'): ?>
                                                            <?= htmlspecialchars($withdrawal['mobile_network'] ?? '') ?>
                                                        <?php else: ?>
                                                            <?= htmlspecialchars($withdrawal['bank_name'] ?? '') ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status = strtoupper($withdrawal['status']);
                                                    $statusClass = '';
                                                    switch($status) {
                                                        case 'PENDING': $statusClass = 'badge-warning'; break;
                                                        case 'PROCESSING': $statusClass = 'badge-info'; break;
                                                        case 'COMPLETED': $statusClass = 'badge-success'; break;
                                                        case 'REJECTED': $statusClass = 'badge-danger'; break;
                                                        default: $statusClass = 'badge-secondary';
                                                    }
                                                    ?>
                                                    <span class="badge <?= $statusClass ?>"><?= $status ?></span>
                                                    <?php if ($withdrawal['processed_by_name']): ?>
                                                        <br>
                                                        <small class="text-muted">by <?= htmlspecialchars($withdrawal['processed_by_name']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('Y-m-d H:i', strtotime($withdrawal['created_at'])) ?></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <?php if ($withdrawal['status'] === 'pending'): ?>
                                                            <button class="btn btn-success btn-sm" title="Approve" 
                                                                    onclick="approveWithdrawal(<?= $withdrawal['id'] ?>)">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button class="btn btn-danger btn-sm" title="Reject" 
                                                                    onclick="rejectWithdrawal(<?= $withdrawal['id'] ?>)">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php elseif ($withdrawal['status'] === 'approved'): ?>
                                                            <button class="btn btn-primary btn-sm" title="Process Payment" 
                                                                    onclick="processPayment(<?= $withdrawal['id'] ?>)">
                                                                <i class="fas fa-credit-card"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button class="btn btn-info btn-sm" title="View Details" 
                                                                onclick="viewWithdrawal(<?= $withdrawal['id'] ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                                    <p>No withdrawal requests found</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div style="padding: 1.5rem;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Showing 1 to 5 of 89 entries</small>
                                </div>
                                <nav>
                                    <ul class="pagination" style="margin: 0; display: flex; list-style: none; gap: 0.25rem;">
                                        <li><a href="#" class="btn btn-outline btn-sm">Previous</a></li>
                                        <li><a href="#" class="btn btn-primary btn-sm">1</a></li>
                                        <li><a href="#" class="btn btn-outline btn-sm">2</a></li>
                                        <li><a href="#" class="btn btn-outline btn-sm">3</a></li>
                                        <li><a href="#" class="btn btn-outline btn-sm">Next</a></li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Withdrawal approval functions
        function approveWithdrawal(id) {
            if (confirm('Are you sure you want to approve this withdrawal request?')) {
                fetch('actions/process-withdrawal.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'approve',
                        withdrawal_id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Withdrawal request approved successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing the request');
                });
            }
        }

        function rejectWithdrawal(id) {
            const reason = prompt('Please provide a reason for rejection:');
            if (reason && reason.trim() !== '') {
                fetch('actions/process-withdrawal.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'reject',
                        withdrawal_id: id,
                        reason: reason
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Withdrawal request rejected successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing the request');
                });
            }
        }

        function processPayment(id) {
            if (confirm('Are you sure you want to process this payment? This will initiate the actual payout via JuniPay.')) {
                fetch('actions/process-withdrawal.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'process_payment',
                        withdrawal_id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Payment processed successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing the payment');
                });
            }
        }

        function viewWithdrawal(id) {
            // Implementation for viewing withdrawal details
            alert('View withdrawal details for ID: ' + id);
        }

        // Additional functionality for withdrawal page
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize dropdown functionality
            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(dropdown => {
                const toggle = dropdown.querySelector('.dropdown-toggle');
                const menu = dropdown.querySelector('.dropdown-menu');
                
                if (toggle && menu) {
                    toggle.addEventListener('click', (e) => {
                        e.preventDefault();
                        menu.classList.toggle('show');
                    });
                    
                    document.addEventListener('click', (e) => {
                        if (!dropdown.contains(e.target)) {
                            menu.classList.remove('show');
                        }
                    });
                }
            });
        });
    </script>

    <style>
        .badge {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 0.25rem;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .pagination {
            align-items: center;
        }
        
        .pagination .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .table th {
            font-weight: 600;
            font-size: 0.875rem;
            white-space: nowrap;
        }
        
        .table td {
            font-size: 0.875rem;
            vertical-align: middle;
        }
        
        /* Dropdown Styles */
        .dropdown {
            position: relative;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            box-shadow: var(--shadow-lg);
            min-width: 200px;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.2s ease;
        }
        
        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover {
            background-color: var(--bg-tertiary);
            color: var(--primary-color);
        }
        
        .dropdown-divider {
            height: 1px;
            background-color: var(--border-color);
            margin: 0.5rem 0;
        }
    </style>
</body>
</html>
