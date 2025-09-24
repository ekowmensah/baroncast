<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch dynamic analytics data
try {
    // Total Events
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events");
    $totalEvents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Event Organizers (users with organizer role)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'organizer'");
    $totalOrganizers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total Votes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM votes");
    $totalVotes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active Events (events that are currently ongoing)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE status = 'active' AND start_date <= NOW() AND end_date >= NOW()");
    $activeEvents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pending Events (awaiting approval)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE status = 'pending'");
    $pendingEvents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Today's Votes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM votes WHERE DATE(created_at) = CURDATE()");
    $todayVotes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // This Month's Revenue (assuming there's a transactions table)
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status = 'completed'");
    $monthlyRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Recent Events (last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $recentEvents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // New Users This Week
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $newUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Top Event by Votes
    $stmt = $pdo->query("
        SELECT e.title, COUNT(v.id) as vote_count 
        FROM events e 
        LEFT JOIN votes v ON e.id = v.event_id 
        GROUP BY e.id 
        ORDER BY vote_count DESC 
        LIMIT 1
    ");
    $topEvent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent Activity (last 5 events with vote counts and revenue)
    $stmt = $pdo->query("
        SELECT 
            e.title, 
            e.created_at, 
            e.status,
            u.full_name as organizer_name,
            COUNT(DISTINCT v.id) as vote_count,
            COALESCE(SUM(t.amount), 0) as revenue
        FROM events e 
        LEFT JOIN users u ON e.organizer_id = u.id 
        LEFT JOIN votes v ON e.id = v.event_id
        LEFT JOIN transactions t ON e.id = t.event_id AND t.status = 'completed'
        GROUP BY e.id, e.title, e.created_at, e.status, u.full_name
        ORDER BY e.created_at DESC 
        LIMIT 5
    ");
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Fallback values in case of database error
    $totalEvents = 0;
    $totalOrganizers = 0;
    $totalUsers = 0;
    $totalVotes = 0;
    $activeEvents = 0;
    $pendingEvents = 0;
    $todayVotes = 0;
    $monthlyRevenue = 0;
    $recentEvents = 0;
    $newUsers = 0;
    $topEvent = ['title' => 'N/A', 'vote_count' => 0];
    $recentActivity = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - E-Cast Voting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/responsive-sidebar.css" rel="stylesheet">
</head>
<body>
    <?php 
    $pageTitle = "Admin Dashboard";
    include 'includes/header.php'; 
    include 'includes/sidebar.php'; 
    ?>
    <div class="main-content">
        <div class="container-fluid p-4">
            <!-- Dashboard Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Admin Dashboard</h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Quick Actions
                    </button>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2 text-muted">Total Events</h6>
                                    <h3 class="card-title mb-0"><?= number_format($totalEvents) ?></h3>
                                </div>
                                <div class="stats-icon bg-primary">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2 text-muted">Total Users</h6>
                                    <h3 class="card-title mb-0"><?= number_format($totalUsers) ?></h3>
                                </div>
                                <div class="stats-icon bg-success">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2 text-muted">Total Votes</h6>
                                    <h3 class="card-title mb-0"><?= number_format($totalVotes) ?></h3>
                                </div>
                                <div class="stats-icon bg-info">
                                    <i class="fas fa-vote-yea"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-2 text-muted">Monthly Revenue</h6>
                                    <h3 class="card-title mb-0">GHS <?= number_format($monthlyRevenue, 2) ?></h3>
                                </div>
                                <div class="stats-icon bg-warning">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Dashboard JavaScript -->
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
                        <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="system-menu" class="nav-submenu">
                        <a href="system-logs.php" class="nav-link">
                            <i class="fas fa-file-alt"></i>
                            <span>System Logs</span>
                        </a>
                        <a href="audit-trail.php" class="nav-link">
                            <i class="fas fa-history"></i>
                            <span>Audit Trail</span>
                        </a>
                        <a href="system-health.php" class="nav-link">
                            <i class="fas fa-heartbeat"></i>
                            <span>System Health</span>
                        </a>
                    </div>
                </div>
                
                <!-- Support -->
                <div class="nav-item">
                    <a href="support.php" class="nav-link">
                        <i class="fas fa-life-ring"></i>
                        <span>Support</span>
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
                    <h1 class="page-title mb-0">Admin Dashboard</h1>
                </div>
                
                <div class="header-right">
                    <button id="theme-toggle" class="theme-toggle">
                        <i id="theme-icon" class="fas fa-moon"></i>
                        <span id="theme-text">Dark</span>
                    </button>
                    
                    <div class="dropdown">
                        <button class="btn btn-outline dropdown-toggle">
                            <i class="fas fa-user-shield"></i>
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
                <div class="page-header">
                    <h2 class="page-title">System Overview</h2>
                    <p class="page-subtitle">Monitor and manage your entire voting platform from here.</p>
                </div>

                <!-- Platform Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= number_format($totalEvents) ?></div>
                        <div class="stat-label">Total Events</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value"><?= number_format($totalOrganizers) ?></div>
                        <div class="stat-label">Event Organizers</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value"><?= number_format($totalVotes) ?></div>
                        <div class="stat-label">Total Votes Cast</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-value">$<?= number_format($monthlyRevenue, 2) ?></div>
                        <div class="stat-label">Monthly Revenue</div>
                    </div>
                </div>

                <!-- Quick Stats Row -->
                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--primary-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;"><?= number_format($activeEvents) ?></div>
                        <div class="stat-label">Active Events</div>
                    </div>
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--success-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;"><?= number_format($totalUsers) ?></div>
                        <div class="stat-label">Registered Users</div>
                    </div>
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--warning-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;"><?= number_format($pendingEvents) ?></div>
                        <div class="stat-label">Pending Approvals</div>
                    </div>
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--info-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-vote-yea"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;"><?= number_format($todayVotes) ?></div>
                        <div class="stat-label">Today's Votes</div>
                    </div>
                </div>

                <!-- Recent Activity & System Status -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                    <!-- Recent Events -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Recent Events</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Event Name</th>
                                            <th>Organizer</th>
                                            <th>Status</th>
                                            <th>Votes</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recentActivity)): ?>
                                            <?php foreach ($recentActivity as $event): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($event['title']) ?></td>
                                                    <td><?= htmlspecialchars($event['organizer_name'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <?php 
                                                        $statusClass = '';
                                                        $statusText = ucfirst($event['status'] ?? 'pending');
                                                        switch(strtolower($event['status'] ?? 'pending')) {
                                                            case 'active':
                                                                $statusClass = 'badge-success';
                                                                break;
                                                            case 'pending':
                                                                $statusClass = 'badge-warning';
                                                                break;
                                                            case 'completed':
                                                            case 'ended':
                                                                $statusClass = 'badge-info';
                                                                break;
                                                            case 'cancelled':
                                                                $statusClass = 'badge-danger';
                                                                break;
                                                            default:
                                                                $statusClass = 'badge-secondary';
                                                        }
                                                        ?>
                                                        <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                                    </td>
                                                    <td><?= number_format($event['vote_count'] ?? 0) ?></td>
                                                    <td>$<?= number_format($event['revenue'] ?? 0, 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">No recent events found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- System Status -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">System Status</h3>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span>Database</span>
                                <span class="badge badge-success">
                                    <i class="fas fa-check"></i> Online
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span>Payment Gateway</span>
                                <span class="badge badge-success">
                                    <i class="fas fa-check"></i> Active
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span>Email Service</span>
                                <span class="badge badge-success">
                                    <i class="fas fa-check"></i> Running
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span>USSD Service</span>
                                <span class="badge badge-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Maintenance
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between">
                                <span>Server Load</span>
                                <span class="badge badge-info">
                                    <i class="fas fa-server"></i> 23%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-3 flex-wrap">
                            <a href="users.php" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i>
                                Manage Users
                            </a>
                            <a href="events.php" class="btn btn-success">
                                <i class="fas fa-calendar-plus"></i>
                                Review Events
                            </a>
                            <a href="withdrawals.php" class="btn btn-warning">
                                <i class="fas fa-hand-holding-usd"></i>
                                Process Withdrawals
                            </a>
                            <a href="reports.php" class="btn btn-info">
                                <i class="fas fa-chart-bar"></i>
                                Generate Reports
                            </a>
                            <a href="system-logs.php" class="btn btn-outline">
                                <i class="fas fa-file-alt"></i>
                                View System Logs
                            </a>
                            <a href="general-settings.php" class="btn btn-outline">
                                <i class="fas fa-cogs"></i>
                                Platform Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Additional admin dashboard functionality
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
                    
                    // Close dropdown when clicking outside
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
        /* Additional Admin Dashboard Styles */
        .stats-grid {
            margin-bottom: 2rem;
        }
        
        .stat-card {
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(20px, -20px);
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
