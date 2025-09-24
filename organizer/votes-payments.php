<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['organizer']);

$user = $auth->getCurrentUser();

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch vote payments for the current organizer from transactions table
$payments = [];
$totalEarnings = 0;
$totalCommission = 0;
$netEarnings = 0;

try {
    // Get commission rate from settings
    $commissionStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'commission_rate'");
    $commissionStmt->execute();
    $commissionRate = floatval($commissionStmt->fetchColumn() ?: 10); // Default 10%
    
    // Fetch all vote payments for organizer's events
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            e.title as event_title,
            v.voter_phone,
            v.nominee_id,
            n.name as nominee_name,
            c.name as category_name,
            'Standard Vote' as package_name,
            t.amount as package_price
        FROM transactions t
        LEFT JOIN events e ON t.event_id = e.id
        LEFT JOIN votes v ON t.transaction_id = v.transaction_id
        LEFT JOIN nominees n ON v.nominee_id = n.id
        LEFT JOIN categories c ON n.category_id = c.id
        WHERE t.organizer_id = ? AND t.type = 'vote_payment' AND t.status = 'completed'
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    foreach ($payments as $payment) {
        $totalEarnings += $payment['amount'];
        $commission = ($payment['amount'] * $commissionRate) / 100;
        $totalCommission += $commission;
    }
    $netEarnings = $totalEarnings - $totalCommission;
    
} catch(PDOException $e) {
    error_log("Error fetching organizer payments: " . $e->getMessage());
    $payments = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votes Payments - E-Cast Voting</title>
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
                    <i class="fas fa-vote-yea"></i>
                    <span>E-Cast Organizer</span>
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
                    <a href="#" class="nav-link" data-submenu="transactions-menu">
                        <i class="fas fa-credit-card"></i>
                        <span>Transactions</span>
                        <i class="fas fa-chevron-up submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="transactions-menu" class="nav-submenu show">
                        <a href="votes-payments.php" class="nav-link active">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Votes Payments</span>
                        </a>
                        <a href="withdrawal.php" class="nav-link">
                            <i class="fas fa-hand-holding-usd"></i>
                            <span>Withdrawal</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link" data-submenu="tally-menu">
                        <i class="fas fa-chart-bar"></i>
                        <span>Tally</span>
                        <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="tally-menu" class="nav-submenu">
                        <a href="category-tally.php" class="nav-link">
                            <i class="fas fa-list-alt"></i>
                            <span>Category Tally</span>
                        </a>
                        <a href="nominees-tally.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>Nominees Tally</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-item">
                    <a href="scheme.php" class="nav-link">
                        <i class="fas fa-cogs"></i>
                        <span>Scheme</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="bulk-votes.php" class="nav-link">
                        <i class="fas fa-layer-group"></i>
                        <span>Bulk Votes</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="categories.php" class="nav-link">
                        <i class="fas fa-tags"></i>
                        <span>Categories</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="nominees.php" class="nav-link">
                        <i class="fas fa-user-friends"></i>
                        <span>Nominees</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="registration.php" class="nav-link">
                        <i class="fas fa-user-plus"></i>
                        <span>Registration</span>
                    </a>
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
                        <h1 class="page-title mb-0">Payments List</h1>
                        <nav style="font-size: 0.875rem; color: var(--text-secondary);">
                            <a href="index.php" style="color: var(--text-secondary); text-decoration: none;">Home</a>
                            <span style="margin: 0 0.5rem;">/</span>
                            <span>Payments</span>
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
                <!-- Earnings Summary Cards -->
                <div class="stats-grid mb-4">
                    <div class="stat-card">
                        <div class="stat-value">GH₵<?= number_format($totalEarnings, 2) ?></div>
                        <div class="stat-label">Total Earnings</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <?= count($payments) ?> transactions
                        </div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value">GH₵<?= number_format($totalCommission, 2) ?></div>
                        <div class="stat-label">Platform Commission (<?= $commissionRate ?>%)</div>
                        <div class="stat-change">
                            <i class="fas fa-percentage"></i>
                            System fee
                        </div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value">GH₵<?= number_format($netEarnings, 2) ?></div>
                        <div class="stat-label">Net Earnings</div>
                        <div class="stat-change positive">
                            <i class="fas fa-wallet"></i>
                            Available for withdrawal
                        </div>
                    </div>
                    <div class="stat-card info">
                        <div class="stat-value"><?= count($payments) ?></div>
                        <div class="stat-label">Total Transactions</div>
                        <div class="stat-change">
                            <i class="fas fa-receipt"></i>
                            Payment records
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-2 mb-4">
                    <button class="btn btn-primary" onclick="printPaymentsStatement()">
                        <i class="fas fa-print"></i>
                        Print Payments Statement
                    </button>
                    <button class="btn btn-success" onclick="exportPaymentsStatement()">
                        <i class="fas fa-file-export"></i>
                        Export Payments Statement
                    </button>
                    <button class="btn btn-warning" onclick="window.location.href='withdrawal.php'">
                        <i class="fas fa-hand-holding-usd"></i>
                        Request Withdrawal
                    </button>
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
                                        <th>Transaction ID</th>
                                        <th>Event</th>
                                        <th>Nominee</th>
                                        <th>Package</th>
                                        <th>Voter Phone</th>
                                        <th>Amount</th>
                                        <th>Commission</th>
                                        <th>Net Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($payments)): ?>
                                        <tr>
                                            <td colspan="11" class="text-center py-4">
                                                <div class="empty-state">
                                                    <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No Payment Records Found</h5>
                                                    <p class="text-muted">Payment transactions will appear here once voters start purchasing votes for your events.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($payments as $index => $payment): 
                                            $commission = ($payment['amount'] * $commissionRate) / 100;
                                            $netAmount = $payment['amount'] - $commission;
                                        ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($payment['reference'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($payment['event_title'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($payment['nominee_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($payment['package_name'] ?? 'Standard') ?></td>
                                                <td><?= htmlspecialchars($payment['voter_phone'] ?? 'N/A') ?></td>
                                                <td>GH₵<?= number_format($payment['amount'], 2) ?></td>
                                                <td>GH₵<?= number_format($commission, 2) ?></td>
                                                <td>GH₵<?= number_format($netAmount, 2) ?></td>
                                                <td>
                                                    <?php 
                                                    $statusClass = '';
                                                    $status = strtoupper($payment['status'] ?? 'PENDING');
                                                    switch($status) {
                                                        case 'COMPLETED': 
                                                            $statusClass = 'badge-success'; 
                                                            $status = 'COMPLETED';
                                                            break;
                                                        case 'FAILED':
                                                        case 'DECLINED': 
                                                            $statusClass = 'badge-danger'; 
                                                            break;
                                                        case 'PENDING': 
                                                            $statusClass = 'badge-warning'; 
                                                            break;
                                                        default: 
                                                            $statusClass = 'badge-info';
                                                    }
                                                    ?>
                                                    <span class="badge <?= $statusClass ?>"><?= $status ?></span>
                                                </td>
                                                <td><?= date('Y-m-d, H:i', strtotime($payment['created_at'] ?? 'now')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div style="padding: 1.5rem;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?php if (!empty($payments)): ?>
                                        <small class="text-muted">Showing <?= count($payments) ?> of <?= count($payments) ?> entries</small>
                                    <?php else: ?>
                                        <small class="text-muted">No entries to display</small>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($payments) && count($payments) > 10): ?>
                                <nav>
                                    <ul class="pagination" style="margin: 0; display: flex; list-style: none; gap: 0.25rem;">
                                        <li><a href="#" class="btn btn-outline btn-sm">Previous</a></li>
                                        <li><a href="#" class="btn btn-primary btn-sm">1</a></li>
                                        <li><a href="#" class="btn btn-outline btn-sm">Next</a></li>
                                    </ul>
                                </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Print payments statement
        function printPaymentsStatement() {
            window.print();
        }

        // Export payments statement
        function exportPaymentsStatement() {
            // Create CSV content
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Transaction ID,Event,Nominee,Package,Voter Phone,Amount,Commission,Net Amount,Status,Date\n";
            
            <?php foreach ($payments as $payment): 
                $commission = ($payment['amount'] * $commissionRate) / 100;
                $netAmount = $payment['amount'] - $commission;
            ?>
            csvContent += "<?= addslashes($payment['reference'] ?? 'N/A') ?>,<?= addslashes($payment['event_title'] ?? 'N/A') ?>,<?= addslashes($payment['nominee_name'] ?? 'N/A') ?>,<?= addslashes($payment['package_name'] ?? 'Standard') ?>,<?= addslashes($payment['voter_phone'] ?? 'N/A') ?>,<?= $payment['amount'] ?>,<?= number_format($commission, 2) ?>,<?= number_format($netAmount, 2) ?>,<?= addslashes($payment['status'] ?? 'PENDING') ?>,<?= date('Y-m-d H:i', strtotime($payment['created_at'])) ?>\n";
            <?php endforeach; ?>
            
            // Create download link
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "payments_statement_<?= date('Y-m-d') ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Additional functionality for payments page
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
