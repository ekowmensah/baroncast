<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['organizer']);

$user = $auth->getCurrentUser();

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch nominees tally for the current organizer
$nomineesTally = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            n.id,
            n.nominee_name,
            n.short_code,
            c.category_name,
            e.title as event_title,
            COALESCE(SUM(v.vote_count), 0) as total_votes,
            COALESCE(SUM(v.vote_count * v.vote_price), 0) as total_amount
        FROM nominees n
        LEFT JOIN categories c ON n.category_id = c.id
        LEFT JOIN events e ON c.event_id = e.id
        LEFT JOIN votes v ON n.id = v.nominee_id
        WHERE (e.organizer_id = ? OR c.organizer_id = ?)
        GROUP BY n.id, n.nominee_name, n.short_code, c.category_name, e.title
        ORDER BY total_votes DESC, n.nominee_name ASC
    ");
    $stmt->execute([$user['id'], $user['id']]);
    $nomineesTally = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $nomineesTally = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nominees Tally - E-Cast Voting</title>
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
                        <i class="fas fa-chevron-up submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="tally-menu" class="nav-submenu show">
                        <a href="category-tally.php" class="nav-link">
                            <i class="fas fa-list-alt"></i>
                            <span>Category Tally</span>
                        </a>
                        <a href="nominees-tally.php" class="nav-link active">
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
                        <h1 class="page-title mb-0">Nominees Tally</h1>
                        <nav style="font-size: 0.875rem; color: var(--text-secondary);">
                            <a href="index.php" style="color: var(--text-secondary); text-decoration: none;">Home</a>
                            <span style="margin: 0 0.5rem;">/</span>
                            <span>Nominees Tally</span>
                        </nav>
                    </div>
                </div>
                
                <div class="header-right">
                    <button class="btn btn-success">
                        <i class="fas fa-print"></i>
                        Print Result
                    </button>
                    
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
                                        <th>Nominees Name</th>
                                        <th>Nominees Shortcode</th>
                                        <th>Total Votes</th>
                                        <th>Votes Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($nomineesTally)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <div class="empty-state">
                                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No Nominees Data Found</h5>
                                                    <p class="text-muted">Nominee voting tallies will appear here once you have nominees with votes.</p>
                                                    <a href="nominees.php" class="btn btn-primary">
                                                        <i class="fas fa-plus"></i> Manage Nominees
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($nomineesTally as $index => $nominee): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td>
                                                    <?= htmlspecialchars($nominee['nominee_name']) ?>
                                                    <?php if (!empty($nominee['category_name'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($nominee['category_name']) ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($nominee['event_title'])): ?>
                                                        <br><small class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($nominee['event_title']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($nominee['short_code'] ?? 'N/A') ?></td>
                                                <td><?= number_format($nominee['total_votes']) ?></td>
                                                <td>GHS <?= number_format($nominee['total_amount'], 2) ?></td>
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
                                    <?php if (!empty($nomineesTally)): ?>
                                        <small class="text-muted">Showing <?= count($nomineesTally) ?> of <?= count($nomineesTally) ?> entries</small>
                                    <?php else: ?>
                                        <small class="text-muted">No entries to display</small>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($nomineesTally) && count($nomineesTally) > 10): ?>
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
