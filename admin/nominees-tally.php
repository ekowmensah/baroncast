<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();
$database = new Database();
$pdo = $database->getConnection();

// Fetch dynamic nominee tally data
try {
    // Get all events for filter dropdown
    $eventsQuery = "SELECT id, title FROM events ORDER BY title";
    $eventsStmt = $pdo->prepare($eventsQuery);
    $eventsStmt->execute();
    $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get nominee tally data with dynamic calculations
    $tallyQuery = "SELECT 
                   n.id as nominee_id,
                   n.name as nominee_name,
                   c.name as category_name,
                   e.title as event_title,
                   COUNT(v.id) as total_votes,
                   SUM(v.amount) as total_revenue,
                   SUM(v.amount * 0.10) as commission,
                   SUM(v.amount * 0.90) as organizer_share,
                   e.status,
                   n.status as nominee_status
                   FROM nominees n
                   LEFT JOIN categories c ON n.category_id = c.id
                   LEFT JOIN events e ON c.event_id = e.id
                   LEFT JOIN votes v ON n.id = v.nominee_id AND v.payment_status = 'completed'
                   GROUP BY n.id, n.name, c.name, e.title, e.status, n.status
                   ORDER BY total_votes DESC";
    $tallyStmt = $pdo->prepare($tallyQuery);
    $tallyStmt->execute();
    $nomineeTallies = $tallyStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $events = [];
    $nomineeTallies = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nominees Tally - E-Cast Admin</title>
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
                        <a href="nominees-tally.php" class="nav-link active">
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
                        <h1 class="page-title mb-0">Nominees Tally</h1>
                        <nav style="font-size: 0.875rem; color: var(--text-secondary);">
                            <a href="index.php" style="color: var(--text-secondary); text-decoration: none;">Home</a>
                            <span style="margin: 0 0.5rem;">/</span>
                            <span>Analytics & Reports</span>
                            <span style="margin: 0 0.5rem;">/</span>
                            <span>Nominees Tally</span>
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
                <!-- Filter Controls -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Select Event</label>
                                <select class="form-control">
                                    <option>All Events</option>
                                    <option>University Awards 2024</option>
                                    <option>Sports Excellence Awards</option>
                                    <option>Academic Excellence</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Select Category</label>
                                <select class="form-control">
                                    <option>All Categories</option>
                                    <option>Best Besties of the year</option>
                                    <option>Outstanding Traditional Hall Team</option>
                                    <option>Best Student Coach</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-control">
                                    <option>All Status</option>
                                    <option>Leading</option>
                                    <option>Trailing</option>
                                    <option>Tied</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button class="btn btn-primary">
                                        <i class="fas fa-filter"></i>
                                        Apply Filter
                                    </button>
                                    <button class="btn btn-outline">
                                        <i class="fas fa-download"></i>
                                        Export
                                    </button>
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
                                        <th>Category</th>
                                        <th>Nominee</th>
                                        <th>Votes</th>
                                        <th>Revenue</th>
                                        <th>Percentage</th>
                                        <th>Position</th>
                                        <th>Tools</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($nomineeTallies)): ?>
                                        <?php 
                                        $counter = 1; 
                                        $totalVotes = array_sum(array_column($nomineeTallies, 'total_votes'));
                                        ?>
                                        <?php foreach ($nomineeTallies as $index => $nominee): ?>
                                            <?php 
                                            $percentage = $totalVotes > 0 ? ($nominee['total_votes'] / $totalVotes) * 100 : 0;
                                            $position = $index + 1;
                                            $positionBadge = '';
                                            switch($position) {
                                                case 1: $positionBadge = 'badge-success'; break;
                                                case 2: $positionBadge = 'badge-warning'; break;
                                                case 3: $positionBadge = 'badge-info'; break;
                                                default: $positionBadge = 'badge-secondary';
                                            }
                                            ?>
                                            <tr>
                                                <td><?php echo str_pad($counter, 5, '0', STR_PAD_LEFT); ?></td>
                                                <td><?php echo htmlspecialchars($nominee['event_title'] ?? 'No Event'); ?></td>
                                                <td><?php echo htmlspecialchars($nominee['category_name']); ?></td>
                                                <td><?php echo htmlspecialchars($nominee['nominee_name']); ?></td>
                                                <td><?php echo number_format($nominee['total_votes']); ?></td>
                                                <td>GH₵<?php echo number_format($nominee['total_revenue'], 2); ?></td>
                                                <td><?php echo number_format($percentage, 1); ?>%</td>
                                                <td><span class="badge <?php echo $positionBadge; ?>"><?php echo $position; ?><?php echo $position == 1 ? 'st' : ($position == 2 ? 'nd' : ($position == 3 ? 'rd' : 'th')); ?></span></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-info btn-sm" title="View Details" onclick="viewNomineeDetails(<?php echo $nominee['nominee_id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-primary btn-sm" title="Export Report" onclick="exportNomineeReport(<?php echo $nominee['nominee_id']; ?>)">
                                                            <i class="fas fa-download"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php $counter++; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No nominee tally data available</td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td>00005</td>
                                        <td>Sports Excellence Awards</td>
                                        <td>Best Student Coach</td>
                                        <td>Robert Anderson</td>
                                        <td>445</td>
                                        <td>$445.00</td>
                                        <td>33.2%</td>
                                        <td><span class="badge badge-warning">2nd</span></td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-info btn-sm" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-primary btn-sm" title="Export Report">
                                                    <i class="fas fa-download"></i>
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
                                    <small class="text-muted">Showing 1 to 5 of 47 entries</small>
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
        // Additional functionality for nominees tally page
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
        
        .badge-info {
            background-color: #17a2b8;
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
