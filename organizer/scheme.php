<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['organizer']);

$user = $auth->getCurrentUser();

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch schemes for the current organizer
$schemes = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            e.title as event_title,
            e.status as event_status,
            e.start_date,
            e.end_date
        FROM schemes s
        LEFT JOIN events e ON s.event_id = e.id
        WHERE s.organizer_id = ? OR e.organizer_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$user['id'], $user['id']]);
    $schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $schemes = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scheme List - E-Cast Voting</title>
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
                        <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="transactions-menu" class="nav-submenu">
                        <a href="votes-payments.php" class="nav-link">
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
                    <a href="scheme.php" class="nav-link active">
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
                        <h1 class="page-title mb-0">Scheme List</h1>
                        <nav style="font-size: 0.875rem; color: var(--text-secondary);">
                            <a href="index.php" style="color: var(--text-secondary); text-decoration: none;">Home</a>
                            <span style="margin: 0 0.5rem;">/</span>
                            <span>Scheme</span>
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
                                        <th>Logo</th>
                                        <th>Title</th>
                                        <th>Reference</th>
                                        <th>Alias Name</th>
                                        <th>Unit Cost</th>
                                        <th>V. Charges %</th>
                                        <th>T. Charges %</th>
                                        <th>Regular Voting</th>
                                        <th>Bulk Voting</th>
                                        <th>View Voting</th>
                                        <th>Tools</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($schemes)): ?>
                                        <tr>
                                            <td colspan="11" class="text-center py-4">
                                                <div class="empty-state">
                                                    <i class="fas fa-cogs fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No Schemes Found</h5>
                                                    <p class="text-muted">Event schemes will appear here once you create voting schemes for your events.</p>
                                                    <button class="btn btn-primary" onclick="openCreateSchemeModal()">
                                                        <i class="fas fa-plus"></i> Create New Scheme
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($schemes as $index => $scheme): ?>
                                            <tr>
                                                <td><?= str_pad($scheme['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                                <td>
                                                    <div style="width: 60px; height: 40px; background: #f8f9fa; border: 1px solid var(--border-color); border-radius: 4px; display: flex; align-items: center; justify-content: center; position: relative;">
                                                        <?php if (!empty($scheme['logo_url'])): ?>
                                                            <img src="<?= htmlspecialchars($scheme['logo_url']) ?>" alt="Logo" style="max-width: 100%; max-height: 100%; border-radius: 4px;">
                                                        <?php else: ?>
                                                            <i class="fas fa-image" style="color: var(--text-muted);"></i>
                                                        <?php endif; ?>
                                                        <i class="fas fa-edit" style="position: absolute; bottom: 2px; right: 2px; font-size: 10px; color: var(--text-muted);"></i>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($scheme['scheme_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($scheme['event_title'] ?? 'No Event') ?></td>
                                                <td><?= htmlspecialchars($scheme['short_code'] ?? 'N/A') ?></td>
                                                <td><?= number_format($scheme['vote_price'] ?? 0, 2) ?></td>
                                                <td><?= number_format($scheme['organizer_percentage'] ?? 0, 0) ?>%</td>
                                                <td><?= number_format($scheme['admin_percentage'] ?? 0, 0) ?>%</td>
                                                <td>
                                                    <?php 
                                                    $eventStatus = $scheme['event_status'] ?? 'pending';
                                                    $statusClass = '';
                                                    switch(strtolower($eventStatus)) {
                                                        case 'active':
                                                        case 'ongoing': 
                                                            $statusClass = 'badge-success'; 
                                                            $eventStatus = 'Active';
                                                            break;
                                                        case 'ended':
                                                        case 'completed': 
                                                            $statusClass = 'badge-danger'; 
                                                            $eventStatus = 'Ended';
                                                            break;
                                                        case 'pending': 
                                                            $statusClass = 'badge-warning'; 
                                                            $eventStatus = 'Pending';
                                                            break;
                                                        default: 
                                                            $statusClass = 'badge-info';
                                                    }
                                                    ?>
                                                    <span class="badge <?= $statusClass ?>"><?= $eventStatus ?></span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $paymentStatus = $scheme['payment_status'] ?? 'unavailable';
                                                    $paymentClass = '';
                                                    switch(strtolower($paymentStatus)) {
                                                        case 'available': 
                                                            $paymentClass = 'badge-success'; 
                                                            break;
                                                        case 'unavailable': 
                                                            $paymentClass = 'badge-warning'; 
                                                            break;
                                                        default: 
                                                            $paymentClass = 'badge-info';
                                                    }
                                                    ?>
                                                    <span class="badge <?= $paymentClass ?>"><?= ucfirst($paymentStatus) ?></span>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $votingStatus = $scheme['voting_status'] ?? 'pending';
                                                    $votingClass = '';
                                                    switch(strtolower($votingStatus)) {
                                                        case 'ongoing': 
                                                            $votingClass = 'badge-success'; 
                                                            break;
                                                        case 'ended': 
                                                            $votingClass = 'badge-danger'; 
                                                            break;
                                                        case 'pending': 
                                                            $votingClass = 'badge-warning'; 
                                                            break;
                                                        default: 
                                                            $votingClass = 'badge-info';
                                                    }
                                                    ?>
                                                    <span class="badge <?= $votingClass ?>"><?= ucfirst($votingStatus) ?></span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-success btn-sm" onclick="editScheme(<?= $scheme['id'] ?>)" title="Edit Scheme">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </td>
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
                                    <?php if (!empty($schemes)): ?>
                                        <small class="text-muted">Showing <?= count($schemes) ?> of <?= count($schemes) ?> entries</small>
                                    <?php else: ?>
                                        <small class="text-muted">No entries to display</small>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($schemes) && count($schemes) > 10): ?>
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
        // Additional functionality for scheme page
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
