<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();
$database = new Database();
$pdo = $database->getConnection();

// Fetch dynamic votes payments data
try {
    // Get all events for filter dropdown
    $eventsQuery = "SELECT id, title FROM events ORDER BY title";
    $eventsStmt = $pdo->prepare($eventsQuery);
    $eventsStmt->execute();
    $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get votes payments data with dynamic calculations
    $paymentsQuery = "SELECT 
                      v.id as vote_id,
                      v.voter_phone,
                      v.voter_email,
                      v.payment_method,
                      v.payment_reference,
                      v.payment_status,
                      v.amount,
                      v.voted_at,
                      e.title as event_title,
                      n.name as nominee_name,
                      c.name as category_name,
                      u.full_name as organizer_name,
                      (v.amount * 0.10) as commission,
                      (v.amount * 0.90) as organizer_share
                      FROM votes v
                      LEFT JOIN events e ON v.event_id = e.id
                      LEFT JOIN nominees n ON v.nominee_id = n.id
                      LEFT JOIN categories c ON v.category_id = c.id
                      LEFT JOIN users u ON e.organizer_id = u.id
                      WHERE v.payment_status = 'completed'
                      ORDER BY v.voted_at DESC";
    $paymentsStmt = $pdo->prepare($paymentsQuery);
    $paymentsStmt->execute();
    $votesPayments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary statistics
    $summaryQuery = "SELECT 
                     COUNT(*) as total_payments,
                     SUM(amount) as total_revenue,
                     SUM(amount * 0.10) as total_commission,
                     SUM(amount * 0.90) as total_organizer_share
                     FROM votes 
                     WHERE payment_status = 'completed'";
    $summaryStmt = $pdo->prepare($summaryQuery);
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $events = [];
    $votesPayments = [];
    $summary = ['total_payments' => 0, 'total_revenue' => 0, 'total_commission' => 0, 'total_organizer_share' => 0];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votes Payments - E-Cast Admin</title>
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
                        <a href="votes-payments.php" class="nav-link active">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Votes Payments</span>
                        </a>
                        <a href="withdrawal.php" class="nav-link">
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
                        <h1 class="page-title mb-0">Votes Payments</h1>
                        <nav style="font-size: 0.875rem; color: var(--text-secondary);">
                            <a href="index.php" style="color: var(--text-secondary); text-decoration: none;">Home</a>
                            <span style="margin: 0 0.5rem;">/</span>
                            <span>Financial Management</span>
                            <span style="margin: 0 0.5rem;">/</span>
                            <span>Votes Payments</span>
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
                                        <h6 class="card-subtitle mb-2 text-muted">Total Payments</h6>
                                        <h3 class="card-title mb-0"><?php echo number_format($summary['total_payments']); ?></h3>
                                    </div>
                                    <div class="text-primary">
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
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
                                        <h6 class="card-subtitle mb-2 text-muted">Today's Payments</h6>
                                        <h3 class="card-title mb-0">$1,250</h3>
                                    </div>
                                    <div class="text-success">
                                        <i class="fas fa-calendar-day fa-2x"></i>
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
                                        <h6 class="card-subtitle mb-2 text-muted">Total Revenue</h6>
                                        <h3 class="card-title mb-0">GH₵<?php echo number_format($summary['total_revenue'], 2); ?></h3>
                                    </div>
                                    <div class="text-info">
                                        <i class="fas fa-vote-yea fa-2x"></i>
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
                                        <h6 class="card-subtitle mb-2 text-muted">Platform Commission</h6>
                                        <h3 class="card-title mb-0">GH₵<?php echo number_format($summary['total_commission'], 2); ?></h3>
                                    </div>
                                    <div class="text-warning">
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
                                        <th>Event</th>
                                        <th>Organizer</th>
                                        <th>Voter</th>
                                        <th>Amount</th>
                                        <th>Commission</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Tools</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($votesPayments)): ?>
                                        <?php $counter = 1; ?>
                                        <?php foreach ($votesPayments as $payment): ?>
                                            <tr>
                                                <td><?php echo str_pad($counter, 5, '0', STR_PAD_LEFT); ?></td>
                                                <td><?php echo htmlspecialchars($payment['event_title'] ?? 'No Event'); ?></td>
                                                <td><?php echo htmlspecialchars($payment['organizer_name'] ?? 'No Organizer'); ?></td>
                                                <td><?php echo htmlspecialchars($payment['voter_phone'] ?? $payment['voter_email'] ?? 'Anonymous'); ?></td>
                                                <td>GH₵<?php echo number_format($payment['amount'], 2); ?></td>
                                                <td>GH₵<?php echo number_format($payment['commission'], 2); ?></td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
                                                <td>
                                                    <?php 
                                                    $statusClass = '';
                                                    switch($payment['payment_status']) {
                                                        case 'completed': $statusClass = 'badge-success'; break;
                                                        case 'pending': $statusClass = 'badge-warning'; break;
                                                        case 'failed': $statusClass = 'badge-danger'; break;
                                                        default: $statusClass = 'badge-secondary';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($payment['payment_status']); ?></span>
                                                </td>
                                                <td><?php echo date('Y-m-d', strtotime($payment['voted_at'])); ?></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-info btn-sm" title="View Details" onclick="viewPaymentDetails(<?php echo $payment['vote_id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-warning btn-sm" title="Refund" onclick="refundPayment(<?php echo $payment['vote_id']; ?>)">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $counter++; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="10" class="text-center">No payment data available</td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td>00003</td>
                                        <td>Academic Excellence</td>
                                        <td>Mike Events</td>
                                        <td>Carol Johnson</td>
                                        <td>$15.00</td>
                                        <td>$1.50</td>
                                        <td>USSD</td>
                                        <td><span class="badge badge-warning">Pending</span></td>
                                        <td>2024-01-15</td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-info btn-sm" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-success btn-sm" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" title="Reject">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>00004</td>
                                        <td>Cultural Festival Awards</td>
                                        <td>Lisa Organizer</td>
                                        <td>David Wilson</td>
                                        <td>$8.00</td>
                                        <td>$0.80</td>
                                        <td>Mobile Money</td>
                                        <td><span class="badge badge-success">Completed</span></td>
                                        <td>2024-01-14</td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-info btn-sm" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-warning btn-sm" title="Refund">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>00005</td>
                                        <td>Innovation Awards 2024</td>
                                        <td>Tom Events</td>
                                        <td>Emma Davis</td>
                                        <td>$12.00</td>
                                        <td>$1.20</td>
                                        <td>Card Payment</td>
                                        <td><span class="badge badge-danger">Failed</span></td>
                                        <td>2024-01-14</td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-info btn-sm" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-primary btn-sm" title="Retry">
                                                    <i class="fas fa-redo"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div style="padding: 1.5rem;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">Showing 1 to 5 of 127 entries</small>
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
        // Additional functionality for votes payments page
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
