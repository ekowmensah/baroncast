<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Pagination parameters
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$itemsPerPage = isset($_GET['per_page']) ? max(1, min(100, (int)$_GET['per_page'])) : 10;
$offset = ($currentPage - 1) * $itemsPerPage;

// Get total count of schemes for pagination
$totalSchemes = 0;
try {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM schemes s
        LEFT JOIN events e ON s.event_id = e.id
        LEFT JOIN users u ON e.organizer_id = u.id
    ");
    $countStmt->execute();
    $totalSchemes = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch(PDOException $e) {
    $totalSchemes = 0;
}

// Fetch active events for the Create Scheme form
$events = [];
try {
    $stmt = $pdo->prepare("
        SELECT e.id, e.title, e.organizer_id, u.full_name as organizer_name
        FROM events e
        LEFT JOIN users u ON e.organizer_id = u.id
        WHERE e.status IN ('active', 'upcoming', 'completed')
        ORDER BY u.full_name, e.title
    ");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $events = [];
}

// Fetch all organizers for the Create Scheme form
$organizers = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, full_name, email
        FROM users 
        WHERE role = 'organizer' AND status = 'active'
        ORDER BY full_name
    ");
    $stmt->execute();
    $organizers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $organizers = [];
}

// Fetch schemes for admin view (across all organizers) with pagination
$schemes = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            e.title as event_title,
            e.status as event_status,
            e.start_date,
            e.end_date,
            u.full_name as organizer_name
        FROM schemes s
        LEFT JOIN events e ON s.event_id = e.id
        LEFT JOIN users u ON e.organizer_id = u.id
        ORDER BY s.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
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
    <title>Schemes - E-Cast Admin</title>
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
                        <a href="nominees-tally.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>Nominees Tally</span>
                        </a>
                        <a href="scheme.php" class="nav-link active">
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
                        <h1 class="page-title mb-0">Revenue Schemes</h1>
                        <nav style="font-size: 0.875rem; color: var(--text-secondary);">
                            <a href="index.php" style="color: var(--text-secondary); text-decoration: none;">Home</a>
                            <span style="margin: 0 0.5rem;">/</span>
                            <span>Analytics & Reports</span>
                            <span style="margin: 0 0.5rem;">/</span>
                            <span>Schemes</span>
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
                <!-- Action Buttons -->
                <div class="d-flex gap-2 mb-4">
                    <button class="btn btn-primary" id="createSchemeBtn" onclick="openCreateSchemeModal()">
                        <i class="fas fa-plus"></i>
                        Create New Scheme
                    </button>
                    <button class="btn btn-outline" id="exportSchemeBtn" onclick="exportSchemes()">
                        <i class="fas fa-download"></i>
                        Export Schemes
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
                                        <th>Event</th>
                                        <th>Organizer</th>
                                        <th>Platform Commission (%)</th>
                                        <th>Organizer Share (%)</th>
                                        <th>Vote Price</th>
                                        <th>Processing Fee</th>
                                        <th>Status</th>
                                        <th>Created Date</th>
                                        <th>Tools</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($schemes)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center py-4">
                                                <div class="empty-state">
                                                    <i class="fas fa-cogs fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No Schemes Found</h5>
                                                    <p class="text-muted">Event schemes will appear here once organizers create voting schemes for their events.</p>
                                                    <a href="events.php" class="btn btn-primary">
                                                        <i class="fas fa-calendar-alt"></i> View Events
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($schemes as $index => $scheme): ?>
                                            <tr>
                                                <td><?= str_pad($scheme['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                                <td><?= htmlspecialchars($scheme['event_title'] ?? $scheme['scheme_name'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($scheme['organizer_name'] ?? 'N/A') ?></td>
                                                <td><?= number_format($scheme['organizer_percentage'] ?? 0, 0) ?>%</td>
                                                <td><?= number_format($scheme['admin_percentage'] ?? 0, 0) ?>%</td>
                                                <td>$<?= number_format($scheme['vote_price'] ?? 0, 2) ?></td>
                                                <td>$<?= number_format(($scheme['vote_price'] ?? 0) * ($scheme['admin_percentage'] ?? 0) / 100, 2) ?></td>
                                                <td>
                                                    <?php 
                                                    $status = $scheme['status'] ?? 'draft';
                                                    $statusClass = '';
                                                    $statusText = '';
                                                    switch(strtolower($status)) {
                                                        case 'active':
                                                        case 'ongoing': 
                                                            $statusClass = 'badge-success'; 
                                                            $statusText = 'Active';
                                                            break;
                                                        case 'suspended':
                                                        case 'paused': 
                                                            $statusClass = 'badge-warning'; 
                                                            $statusText = 'Suspended';
                                                            break;
                                                        case 'draft':
                                                        case 'pending': 
                                                            $statusClass = 'badge-info'; 
                                                            $statusText = 'Draft';
                                                            break;
                                                        case 'ended':
                                                        case 'completed': 
                                                            $statusClass = 'badge-danger'; 
                                                            $statusText = 'Ended';
                                                            break;
                                                        default: 
                                                            $statusClass = 'badge-secondary';
                                                            $statusText = ucfirst($status);
                                                    }
                                                    ?>
                                                    <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                                                </td>
                                                <td><?= date('Y-m-d', strtotime($scheme['created_at'] ?? 'now')) ?></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-info btn-sm" onclick="viewScheme(<?= $scheme['id'] ?>)" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-success btn-sm" onclick="editScheme(<?= $scheme['id'] ?>)" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if (strtolower($status) === 'active'): ?>
                                                            <button class="btn btn-warning btn-sm" onclick="suspendScheme(<?= $scheme['id'] ?>)" title="Suspend">
                                                                <i class="fas fa-pause"></i>
                                                            </button>
                                                        <?php elseif (strtolower($status) === 'suspended'): ?>
                                                            <button class="btn btn-primary btn-sm" onclick="activateScheme(<?= $scheme['id'] ?>)" title="Activate">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                        <?php elseif (strtolower($status) === 'draft'): ?>
                                                            <button class="btn btn-primary btn-sm" onclick="approveScheme(<?= $scheme['id'] ?>)" title="Approve">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button class="btn btn-danger btn-sm" onclick="deleteScheme(<?= $scheme['id'] ?>)" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if (!empty($schemes)): ?>
                            <div style="padding: 1.5rem;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php 
                                        $startItem = ($currentPage - 1) * $itemsPerPage + 1;
                                        $endItem = min($currentPage * $itemsPerPage, $totalSchemes);
                                        ?>
                                        <small class="text-muted">
                                            Showing <?= $startItem ?> to <?= $endItem ?> of <?= $totalSchemes ?> entries
                                        </small>
                                    </div>
                                    <?php if ($totalSchemes > $itemsPerPage): ?>
                                        <nav>
                                            <?php 
                                            $totalPages = ceil($totalSchemes / $itemsPerPage);
                                            $prevPage = max(1, $currentPage - 1);
                                            $nextPage = min($totalPages, $currentPage + 1);
                                            ?>
                                            <ul class="pagination" style="margin: 0; display: flex; list-style: none; gap: 0.25rem;">
                                                <?php if ($currentPage > 1): ?>
                                                    <li><a href="?page=<?= $prevPage ?>&per_page=<?= $itemsPerPage ?>" class="btn btn-outline btn-sm">Previous</a></li>
                                                <?php endif; ?>
                                                
                                                <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                                                    <li>
                                                        <a href="?page=<?= $i ?>&per_page=<?= $itemsPerPage ?>" 
                                                           class="btn <?= $i === $currentPage ? 'btn-primary' : 'btn-outline' ?> btn-sm">
                                                            <?= $i ?>
                                                        </a>
                                                    </li>
                                                <?php endfor; ?>
                                                
                                                <?php if ($currentPage < $totalPages): ?>
                                                    <li><a href="?page=<?= $nextPage ?>&per_page=<?= $itemsPerPage ?>" class="btn btn-outline btn-sm">Next</a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Scheme Modal -->
    <div id="createSchemeModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Create New Scheme</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="createSchemeForm">
                    <div class="form-group">
                        <label for="schemeName" class="form-label">Scheme Name *</label>
                        <input type="text" id="schemeName" name="name" class="form-control" 
                               placeholder="Enter scheme name" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="schemeOrganizer" class="form-label">Assign to Organizer *</label>
                            <select id="schemeOrganizer" name="organizer_id" class="form-control" required>
                                <option value="">Select an organizer...</option>
                                <?php foreach ($organizers as $organizer): ?>
                                    <option value="<?= $organizer['id'] ?>">
                                        <?= htmlspecialchars($organizer['full_name']) ?> 
                                        (<?= htmlspecialchars($organizer['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="schemeEvent" class="form-label">Associated Event</label>
                            <select id="schemeEvent" name="event_id" class="form-control">
                                <option value="">Select an event (optional)...</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?= $event['id'] ?>" data-organizer="<?= $event['organizer_id'] ?>">
                                        <?= htmlspecialchars($event['title']) ?> 
                                        (<?= htmlspecialchars($event['organizer_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="votePrice" class="form-label">Unit Cost (Vote Price $) *</label>
                            <input type="number" id="votePrice" name="vote_price" class="form-control" 
                                   min="0" step="0.01" value="1.00" required>
                            <small class="form-text text-muted">Price per vote that voters will pay</small>
                        </div>
                        <div class="form-group">
                            <label for="adminCharges" class="form-label">V. Charges (Admin Commission %) *</label>
                            <input type="number" id="adminCharges" name="admin_percentage" class="form-control" 
                                   min="0" max="100" step="0.1" value="10" required>
                            <small class="form-text text-muted">Admin's commission percentage from each vote</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="organizerShare" class="form-label">Organizer Share (%)</label>
                            <input type="number" id="organizerShare" name="organizer_percentage" class="form-control" 
                                   min="0" max="100" step="0.1" value="90" readonly>
                            <small class="form-text text-muted">Automatically calculated (100% - Admin Commission)</small>
                        </div>
                        <div class="form-group">
                            <label for="schemeStatus" class="form-label">Status</label>
                            <select id="schemeStatus" name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bulkDiscount" class="form-label">Bulk Discount (%)</label>
                            <input type="number" id="bulkDiscount" name="bulk_discount_percentage" class="form-control" 
                                   min="0" max="100" step="0.1" value="0">
                            <small class="form-text text-muted">Discount for bulk vote purchases</small>
                        </div>
                        <div class="form-group">
                            <label for="minBulkQuantity" class="form-label">Min Bulk Quantity</label>
                            <input type="number" id="minBulkQuantity" name="min_bulk_quantity" class="form-control" 
                                   min="0" value="10">
                            <small class="form-text text-muted">Minimum votes to qualify for bulk discount</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                <button type="submit" form="createSchemeForm" class="btn btn-primary">Create Scheme</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Global functions for onclick handlers
        function openCreateSchemeModal() {
            console.log('openCreateSchemeModal called');
            const modal = document.getElementById('createSchemeModal');
            if (modal) {
                modal.style.display = 'block';
            } else {
                console.error('Create Scheme modal not found');
                alert('Modal not found. Please refresh the page.');
            }
        }
        
        function exportSchemes() {
            console.log('exportSchemes called');
            window.location.href = 'actions/export-schemes.php';
        }
        
        // Additional functionality for scheme page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded - initializing scheme page...');
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
            
            // Create New Scheme button functionality
            const createSchemeBtn = document.getElementById('createSchemeBtn');
            if (createSchemeBtn) {
                createSchemeBtn.addEventListener('click', function() {
                    console.log('Create Scheme button clicked'); // Debug log
                    // Show create scheme modal
                    const modal = document.getElementById('createSchemeModal');
                    if (modal) {
                        modal.style.display = 'block';
                    } else {
                        console.error('Create Scheme modal not found');
                    }
                });
            } else {
                console.error('Create Scheme button not found');
            }
            
            // Export Scheme button functionality
            const exportSchemeBtn = document.getElementById('exportSchemeBtn');
            if (exportSchemeBtn) {
                exportSchemeBtn.addEventListener('click', function() {
                    console.log('Export Scheme button clicked'); // Debug log
                    // Trigger export functionality
                    window.location.href = 'actions/export-schemes.php';
                });
            } else {
                console.error('Export Scheme button not found');
            }
            
            // Modal close functionality
            document.querySelectorAll('.modal-close').forEach(closeBtn => {
                closeBtn.addEventListener('click', function() {
                    this.closest('.modal').style.display = 'none';
                });
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target.classList.contains('modal')) {
                    event.target.style.display = 'none';
                }
            });
            
            // Handle Create Scheme form submission
            setTimeout(function() {
                const createSchemeForm = document.getElementById('createSchemeForm');
                if (createSchemeForm) {
                    console.log('Create Scheme form found, attaching event listener');
                    createSchemeForm.addEventListener('submit', function(e) {
                        console.log('Form submit event triggered');
                        e.preventDefault();
                        
                        // Get form data
                        const formData = new FormData(this);
                        
                        // Show loading state
                        const submitBtn = document.querySelector('#createSchemeModal .btn-primary');
                        const originalText = submitBtn.textContent;
                        submitBtn.textContent = 'Creating...';
                        submitBtn.disabled = true;
                        
                        console.log('Sending form data to server...');
                        
                        fetch('actions/create-scheme.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            console.log('Response status:', response.status);
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Response data:', data);
                            if (data.success) {
                                alert('Scheme created successfully!');
                                document.getElementById('createSchemeModal').style.display = 'none';
                                location.reload();
                            } else {
                                alert('Error: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred while creating the scheme: ' + error.message);
                        })
                        .finally(() => {
                            // Reset button state
                            submitBtn.textContent = originalText;
                            submitBtn.disabled = false;
                        });
                    });
                } else {
                    console.error('Create Scheme form not found!');
                }
                
                // Auto-calculate organizer share when admin commission changes
                const adminChargesInput = document.getElementById('adminCharges');
                const organizerShareInput = document.getElementById('organizerShare');
                
                if (adminChargesInput && organizerShareInput) {
                    adminChargesInput.addEventListener('input', function() {
                        const adminPercentage = parseFloat(this.value) || 0;
                        const organizerPercentage = Math.max(0, 100 - adminPercentage);
                        organizerShareInput.value = organizerPercentage.toFixed(1);
                    });
                }
                
                // Filter events by selected organizer
                const organizerSelect = document.getElementById('schemeOrganizer');
                const eventSelect = document.getElementById('schemeEvent');
                
                if (organizerSelect && eventSelect) {
                    organizerSelect.addEventListener('change', function() {
                        const selectedOrganizerId = this.value;
                        const eventOptions = eventSelect.querySelectorAll('option');
                        
                        eventOptions.forEach(option => {
                            if (option.value === '') {
                                option.style.display = 'block'; // Always show the default option
                            } else {
                                const eventOrganizerId = option.getAttribute('data-organizer');
                                if (!selectedOrganizerId || eventOrganizerId === selectedOrganizerId) {
                                    option.style.display = 'block';
                                } else {
                                    option.style.display = 'none';
                                }
                            }
                        });
                        
                        // Reset event selection if current selection doesn't match organizer
                        const currentEventOption = eventSelect.querySelector('option[value="' + eventSelect.value + '"]');
                        if (currentEventOption && currentEventOption.style.display === 'none') {
                            eventSelect.value = '';
                        }
                    });
                }
            }, 100);
        });
        
        // Edit Scheme Function
        function editScheme(schemeId) {
            console.log('Edit scheme called with ID:', schemeId);
            // For now, redirect to edit page or open edit modal
            // You can implement inline editing or modal editing here
            if (confirm('Edit scheme functionality will be implemented. For now, would you like to view the scheme details?')) {
                viewScheme(schemeId);
            }
        }
        
        // Delete Scheme Function
        function deleteScheme(schemeId) {
            console.log('Delete scheme called with ID:', schemeId);
            if (confirm('Are you sure you want to delete this scheme? This action cannot be undone.')) {
                fetch('actions/delete-scheme.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ scheme_id: schemeId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Scheme deleted successfully!');
                        location.reload(); // Refresh the page to update the table
                    } else {
                        alert('Error deleting scheme: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting scheme. Please try again.');
                });
            }
        }
        
        // View Scheme Function
        function viewScheme(schemeId) {
            // Implement view scheme details modal or page
            alert('View scheme details for ID: ' + schemeId + '\nThis functionality will show detailed scheme information.');
        }
        
        // Suspend Scheme Function
        function suspendScheme(schemeId) {
            if (confirm('Are you sure you want to suspend this scheme?')) {
                updateSchemeStatus(schemeId, 'suspended');
            }
        }
        
        // Activate Scheme Function
        function activateScheme(schemeId) {
            if (confirm('Are you sure you want to activate this scheme?')) {
                updateSchemeStatus(schemeId, 'active');
            }
        }
        
        // Approve Scheme Function
        function approveScheme(schemeId) {
            if (confirm('Are you sure you want to approve this scheme?')) {
                updateSchemeStatus(schemeId, 'active');
            }
        }
        
        // Update Scheme Status Function
        function updateSchemeStatus(schemeId, status) {
            fetch('actions/update-scheme-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ scheme_id: schemeId, status: status })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Scheme status updated successfully!');
                    location.reload();
                } else {
                    alert('Error updating scheme status: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating scheme status. Please try again.');
            });
        }
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
        
        /* Modal Styles */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background-color: #ffffff;
            border-radius: 0.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid #dee2e6;
        }
        
        [data-theme="dark"] .modal-content {
            background-color: #2d3748;
            border-color: #4a5568;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #f8f9fa;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        
        [data-theme="dark"] .modal-header {
            background-color: #374151;
            border-bottom-color: #4a5568;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #212529;
            font-weight: 600;
        }
        
        [data-theme="dark"] .modal-header h3 {
            color: #f7fafc;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            color: #212529;
        }
        
        [data-theme="dark"] .modal-close {
            color: #9ca3af;
        }
        
        [data-theme="dark"] .modal-close:hover {
            color: #f7fafc;
        }
        
        .modal-body {
            padding: 1.5rem;
            background-color: #ffffff;
        }
        
        [data-theme="dark"] .modal-body {
            background-color: #2d3748;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            background-color: #f8f9fa;
            border-radius: 0 0 0.5rem 0.5rem;
        }
        
        [data-theme="dark"] .modal-footer {
            background-color: #374151;
            border-top-color: #4a5568;
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
