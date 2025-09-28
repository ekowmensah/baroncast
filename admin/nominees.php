<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch all nominees across all organizers with their details
$stmt = $pdo->query("
    SELECT n.*, c.name as category_name, e.title as event_title, 
           u.full_name as organizer_name, e.organizer_id,
           COUNT(v.id) as vote_count
    FROM nominees n
    LEFT JOIN categories c ON n.category_id = c.id
    LEFT JOIN events e ON c.event_id = e.id
    LEFT JOIN users u ON e.organizer_id = u.id
    LEFT JOIN votes v ON n.id = v.nominee_id
    GROUP BY n.id
    ORDER BY n.created_at DESC
");
$nominees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Clear any potential cached data and fetch fresh data

// Fetch current events only (fresh from database)
$events = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT e.id, e.title 
        FROM events e
        WHERE e.status IN ('active', 'upcoming', 'completed')
        AND e.id IS NOT NULL
        ORDER BY e.title ASC
    ");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching events for nominees: " . $e->getMessage());
    $events = [];
}

// Fetch current categories only (fresh from database)
$categories = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.name, c.event_id, 
               COALESCE(e.title, 'Standalone Category') as event_title
        FROM categories c
        LEFT JOIN events e ON c.event_id = e.id
        WHERE c.id IS NOT NULL
        AND c.name IS NOT NULL
        AND (c.event_id IS NULL OR (e.id IS NOT NULL AND e.status IN ('active', 'upcoming', 'completed')))
        ORDER BY c.name ASC
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching categories for nominees: " . $e->getMessage());
    $categories = [];
}

// Fetch current schemes only (fresh from database)
$schemes = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.id, s.name, 
               COALESCE(e.title, 'General Scheme') as event_title,
               COALESCE(u.full_name, 'Unknown Organizer') as organizer_name
        FROM schemes s
        LEFT JOIN events e ON s.event_id = e.id
        LEFT JOIN users u ON s.organizer_id = u.id
        WHERE s.id IS NOT NULL
        AND s.name IS NOT NULL
        AND (s.event_id IS NULL OR (e.id IS NOT NULL AND e.status IN ('active', 'upcoming', 'completed')))
        ORDER BY s.name ASC
    ");
    $stmt->execute();
    $schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching schemes for nominees: " . $e->getMessage());
    $schemes = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nominees Management - Admin Dashboard</title>
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
                        <i class="fas fa-users-cog"></i>
                        <span>User Management</span>
                        <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="users-menu" class="nav-submenu">
                        <a href="users.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>All Users</span>
                        </a>
                        <a href="organizers.php" class="nav-link">
                            <i class="fas fa-user-tie"></i>
                            <span>Event Organizers</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-item">
                    <a href="#" class="nav-link active" data-submenu="events-menu">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Event Management</span>
                        <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="events-menu" class="nav-submenu show">
                        <a href="events.php" class="nav-link">
                            <i class="fas fa-calendar"></i>
                            <span>All Events</span>
                        </a>
                        <a href="categories.php" class="nav-link">
                            <i class="fas fa-tags"></i>
                            <span>Categories</span>
                        </a>
                        <a href="nominees.php" class="nav-link active">
                            <i class="fas fa-user-friends"></i>
                            <span>Nominees</span>
                        </a>
                    </div>
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
                    <h1 class="page-title mb-0">Nominees Management</h1>
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
                    <h2 class="page-title">Nominees Management</h2>
                    <p class="page-subtitle">Manage all nominees across all events and organizers.</p>
                </div>

                <!-- Action Buttons -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <button class="btn btn-primary" onclick="openAddNomineeModal()">
                                    <i class="fas fa-plus"></i> Add New Nominee
                                </button>
                                <button class="btn btn-success" onclick="openAddVotesModal()">
                                    <i class="fas fa-vote-yea"></i> Add Manual Votes
                                </button>
                                <button class="btn btn-info" onclick="exportNominees()">
                                    <i class="fas fa-download"></i> Export Data
                                </button>
                            </div>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <input type="text" id="searchInput" placeholder="Search nominees..." class="form-control" style="width: 250px;">
                                <select id="filterEvent" class="form-control" style="width: 200px;">
                                    <option value="">All Events</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?= $event['id'] ?>"><?= htmlspecialchars($event['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Nominees Table -->
                <div class="card">
                    <div class="card-body" style="padding: 0;">
                        <div class="table-responsive">
                            <table class="table" style="margin: 0;">
                                <thead style="background: var(--bg-secondary);">
                                    <tr>
                                        <th style="padding: 1rem; border: none;">Nominee</th>
                                        <th style="padding: 1rem; border: none;">Short Code</th>
                                        <th style="padding: 1rem; border: none;">Event & Category</th>
                                        <th style="padding: 1rem; border: none;">Organizer</th>
                                        <th style="padding: 1rem; border: none;">Votes</th>
                                        <th style="padding: 1rem; border: none;">Status</th>
                                        <th style="padding: 1rem; border: none;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($nominees as $nominee): ?>
                                    <tr>
                                        <td style="padding: 1rem; border-bottom: 1px solid var(--border-color);">
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div class="nominee-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-color); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                                    <?= strtoupper(substr($nominee['name'], 0, 2)) ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--text-primary);">
                                                        <?= htmlspecialchars($nominee['name']) ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                        ID: #<?= str_pad($nominee['id'], 4, '0', STR_PAD_LEFT) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid var(--border-color);">
                                            <div style="text-align: center;">
                                                <?php if (!empty($nominee['short_code'])): ?>
                                                    <span style="color: #007bff; font-weight: bold; font-family: monospace; font-size: 1rem;">
                                                        <i class="fas fa-hashtag"></i><?= htmlspecialchars($nominee['short_code']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #6c757d; font-style: italic; font-size: 0.875rem;">
                                                        <i class="fas fa-minus"></i> Not Set
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid var(--border-color);">
                                            <div>
                                                <div style="font-weight: 500; color: var(--text-primary);">
                                                    <?= htmlspecialchars($nominee['event_title']) ?>
                                                </div>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                    <?= htmlspecialchars($nominee['category_name']) ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid var(--border-color);">
                                            <div style="font-size: 0.875rem; color: var(--text-primary);">
                                                <?= htmlspecialchars($nominee['organizer_name']) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid var(--border-color);">
                                            <div style="text-align: center;">
                                                <div style="font-weight: 600; font-size: 1.25rem; color: var(--primary-color);">
                                                    <?= number_format($nominee['vote_count']) ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);">votes</div>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid var(--border-color);">
                                            <span class="badge badge-success">Active</span>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid var(--border-color);">
                                            <div style="display: flex; gap: 0.25rem;">
                                                <button class="btn btn-outline btn-sm" title="View Details" onclick="viewNominee(<?= $nominee['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline btn-sm" title="Edit Nominee" onclick="editNominee(<?= $nominee['id'] ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <div class="dropdown" style="position: relative; display: inline-block;">
                                                    <button class="btn btn-outline btn-sm dropdown-toggle" title="More Options" onclick="toggleDropdown(<?= $nominee['id'] ?>)">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <div id="dropdown-<?= $nominee['id'] ?>" class="dropdown-menu" style="display: none;">
                                                        <a href="#" onclick="addVotesToNominee(<?= $nominee['id'] ?>)">
                                                            <i class="fas fa-plus"></i> Add Votes
                                                        </a>
                                                        <a href="#" onclick="viewNomineeStats(<?= $nominee['id'] ?>)">
                                                            <i class="fas fa-chart-bar"></i> View Statistics
                                                        </a>
                                                        <div class="dropdown-divider"></div>
                                                        <a href="#" onclick="deleteNominee(<?= $nominee['id'] ?>)" class="text-danger">
                                                            <i class="fas fa-trash"></i> Delete Nominee
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

                <!-- Pagination -->
                <div style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 1.5rem;">
                    <button class="btn btn-outline btn-sm" onclick="changePage('previous')">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="changePage(1)">1</button>
                    <button class="btn btn-outline btn-sm" onclick="changePage(2)">2</button>
                    <button class="btn btn-outline btn-sm" onclick="changePage(3)">3</button>
                    <button class="btn btn-outline btn-sm" onclick="changePage('next')">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Nominee Modal -->
    <div id="addNomineeModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New Nominee</h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addNomineeForm" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nomineeName" class="form-label">Nominee Name *</label>
                            <input type="text" id="nomineeName" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="nomineeCategory" class="form-label">Category *</label>
                            <select id="nomineeCategory" name="category_id" class="form-control" required>
                                <option value="">Select a category...</option>
                                <?php if (empty($categories)): ?>
                                    <option value="" disabled>No categories available</option>
                                <?php else: ?>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>">
                                            <?= htmlspecialchars($category['name']) ?>
                                            <?php if ($category['event_title'] && $category['event_title'] !== 'Standalone Category'): ?>
                                                (<?= htmlspecialchars($category['event_title']) ?>)
                                            <?php else: ?>
                                                (Standalone Category)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nomineeEvent" class="form-label">Event</label>
                            <select id="nomineeEvent" name="event_id" class="form-control">
                                <option value="">Select an event (optional)...</option>
                                <?php if (empty($events)): ?>
                                    <option value="" disabled>No events available</option>
                                <?php else: ?>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?= $event['id'] ?>">
                                            <?= htmlspecialchars($event['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="nomineeScheme" class="form-label">Event Scheme</label>
                            <select id="nomineeScheme" name="scheme_id" class="form-control">
                                <option value="">Select a scheme (optional)...</option>
                                <?php if (empty($schemes)): ?>
                                    <option value="" disabled>No schemes available</option>
                                <?php else: ?>
                                    <?php foreach ($schemes as $scheme): ?>
                                        <option value="<?= $scheme['id'] ?>">
                                            <?= htmlspecialchars($scheme['name']) ?>
                                            <?php if ($scheme['organizer_name'] && $scheme['organizer_name'] !== 'Unknown Organizer'): ?>
                                                - <?= htmlspecialchars($scheme['organizer_name']) ?>
                                            <?php endif; ?>
                                            <?php if ($scheme['event_title'] && $scheme['event_title'] !== 'General Scheme'): ?>
                                                (<?= htmlspecialchars($scheme['event_title']) ?>)
                                            <?php else: ?>
                                                (General Scheme)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="nomineeDescription" class="form-label">Description</label>
                        <textarea id="nomineeDescription" name="description" class="form-control" rows="3" 
                                  placeholder="Brief description about the nominee"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="nomineeImage" class="form-label">Nominee Image</label>
                        <input type="file" id="nomineeImage" name="image" class="form-control" 
                               accept="image/jpeg,image/jpg,image/png,image/gif">
                        <small class="form-text">Upload an image for the nominee (JPG, PNG, GIF - Max 5MB)</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nomineeShortCode" class="form-label" style="color: #007bff; font-weight: bold;">
                                <i class="fas fa-hashtag"></i> Short Code (USSD Voting)
                            </label>
                            <input type="text" id="nomineeShortCode" name="short_code" class="form-control" 
                                   placeholder="e.g., MHA012 (auto-generated if empty)"
                                   style="border: 2px solid #007bff;">
                            <small class="form-text" style="color: #007bff; font-weight: bold;">
                                <i class="fas fa-info-circle"></i> Unique code for USSD voting. Leave empty to auto-generate.
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="nomineeStatus" class="form-label">Status</label>
                            <select id="nomineeStatus" name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="displayOrder" class="form-label">Display Order</label>
                            <input type="number" id="displayOrder" name="display_order" class="form-control" 
                                   min="0" value="0">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                <button type="submit" form="addNomineeForm" class="btn btn-primary">Add Nominee</button>
            </div>
        </div>
    </div>

    <!-- Add Manual Votes Modal -->
    <div id="addVotesModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Manual Votes</h3>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addVotesForm">
                    <div class="form-group">
                        <label for="voteNominee" class="form-label">Select Nominee</label>
                        <select id="voteNominee" name="nominee_id" class="form-control" required>
                            <option value="">Choose a nominee...</option>
                            <?php foreach ($nominees as $nominee): ?>
                                <option value="<?= $nominee['id'] ?>">
                                    <?= htmlspecialchars($nominee['name']) ?> 
                                    (<?= htmlspecialchars($nominee['category_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="voteCount" class="form-label">Number of Votes</label>
                        <input type="number" id="voteCount" name="vote_count" class="form-control" 
                               min="1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label for="voterPhone" class="form-label">Voter Phone (Optional)</label>
                        <input type="tel" id="voterPhone" name="voter_phone" class="form-control" 
                               placeholder="e.g., +256700000000">
                    </div>
                    <div class="form-group">
                        <label for="voteReason" class="form-label">Reason/Notes</label>
                        <textarea id="voteReason" name="reason" class="form-control" rows="3" 
                                  placeholder="Reason for manual vote entry..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                <button type="submit" form="addVotesForm" class="btn btn-primary">Add Votes</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Nominee management functions
        function openAddNomineeModal() {
            document.getElementById('addNomineeModal').style.display = 'block';
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
        
        // Handle Add Nominee form submission
        document.getElementById('addNomineeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('actions/add-nominee.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Nominee added successfully!');
                    document.getElementById('addNomineeModal').style.display = 'none';
                    location.reload(); // Refresh to show new nominee
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the nominee');
            });
        });
        
        // Handle Add Manual Votes form submission
        document.getElementById('addVotesForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('actions/add-manual-votes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Manual votes added successfully!');
                    document.getElementById('addVotesModal').style.display = 'none';
                    location.reload(); // Refresh to show updated vote counts
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding votes');
            });
        });
        
        function openAddVotesModal() {
            document.getElementById('addVotesModal').style.display = 'flex';
        }
        
        function exportNominees() {
            window.location.href = 'actions/export-nominees.php';
        }
        
        function viewNominee(id) {
            // Show nominee details in a modal instead of redirecting
            fetch(`actions/get-nominee-details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNomineeDetailsModal(data.nominee);
                    } else {
                        alert('Error loading nominee details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading nominee details');
                });
        }
        
        function editNominee(id) {
            // Show edit form in a modal instead of redirecting
            fetch(`actions/get-nominee-details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showEditNomineeModal(data.nominee);
                    } else {
                        alert('Error loading nominee for editing: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading nominee for editing');
                });
        }
        
        function addVotesToNominee(id) {
            // Pre-select the nominee in the add votes modal
            const nomineeSelect = document.getElementById('nominee_id');
            if (nomineeSelect) {
                nomineeSelect.value = id;
            }
            openAddVotesModal();
        }
        
        function viewNomineeStats(id) {
            // Show nominee statistics in a modal instead of redirecting
            fetch(`actions/get-nominee-analytics.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNomineeStatsModal(data.stats);
                    } else {
                        alert('Error loading nominee statistics: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading nominee statistics');
                });
        }
        
        function deleteNominee(id) {
            if (confirm('Are you sure you want to delete this nominee? This action cannot be undone.')) {
                fetch('actions/delete-nominee.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ nominee_id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Nominee deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error deleting nominee: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting nominee');
                });
            }
        }
        
        function toggleDropdown(id) {
            const dropdown = document.getElementById('dropdown-' + id);
            const allDropdowns = document.querySelectorAll('.dropdown-menu');
            
            allDropdowns.forEach(menu => {
                if (menu.id !== 'dropdown-' + id) {
                    menu.style.display = 'none';
                }
            });
            
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        }
        
        function changePage(page) {
            alert('Pagination for page: ' + page);
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
            }
        });
        
        // Modal functions for nominee details, edit, and stats
        function showNomineeDetailsModal(nominee) {
            const modalHtml = `
                <div id="nomineeDetailsModal" class="modal" style="display: block; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background-color: var(--bg-primary); margin: 5% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 600px; color: var(--text-primary);">
                        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
                            <h3><i class="fas fa-eye"></i> Nominee Details</h3>
                            <span class="close" onclick="closeModal('nomineeDetailsModal')" style="font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                        </div>
                        <div class="modal-body">
                            <div style="display: grid; gap: 15px;">
                                <div><strong>Name:</strong> ${nominee.name}</div>
                                <div><strong>Description:</strong> ${nominee.description || 'No description'}</div>
                                <div><strong>Category:</strong> ${nominee.category_name || 'N/A'}</div>
                                <div><strong>Event:</strong> ${nominee.event_title || 'N/A'}</div>
                                <div><strong>Organizer:</strong> ${nominee.organizer_name || 'N/A'}</div>
                                <div><strong>Total Votes:</strong> ${nominee.vote_count || 0}</div>
                                <div><strong>Status:</strong> <span class="badge badge-success">Active</span></div>
                                <div><strong>Created:</strong> ${new Date(nominee.created_at).toLocaleDateString()}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }
        
        function showEditNomineeModal(nominee) {
            const modalHtml = `
                <div id="editNomineeModal" class="modal" style="display: block; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background-color: var(--bg-primary); margin: 5% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 600px; color: var(--text-primary);">
                        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
                            <h3><i class="fas fa-edit"></i> Edit Nominee</h3>
                            <span class="close" onclick="closeModal('editNomineeModal')" style="font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form id="editNomineeForm">
                                <input type="hidden" name="nominee_id" value="${nominee.id}">
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label>Name:</label>
                                    <input type="text" name="name" value="${nominee.name}" class="form-control" required>
                                </div>
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label>Description:</label>
                                    <textarea name="description" class="form-control" rows="3">${nominee.description || ''}</textarea>
                                </div>
                                <div style="text-align: right; margin-top: 20px;">
                                    <button type="button" onclick="closeModal('editNomineeModal')" class="btn btn-secondary" style="margin-right: 10px;">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Nominee</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Handle form submission
            document.getElementById('editNomineeForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('actions/update-nominee.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Nominee updated successfully!');
                        closeModal('editNomineeModal');
                        location.reload();
                    } else {
                        alert('Error updating nominee: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating nominee');
                });
            });
        }
        
        function showNomineeStatsModal(stats) {
            const modalHtml = `
                <div id="nomineeStatsModal" class="modal" style="display: block; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background-color: var(--bg-primary); margin: 5% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 700px; color: var(--text-primary);">
                        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
                            <h3><i class="fas fa-chart-bar"></i> Nominee Statistics</h3>
                            <span class="close" onclick="closeModal('nomineeStatsModal')" style="font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                        </div>
                        <div class="modal-body">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                                <div class="stat-card" style="background: var(--bg-secondary); padding: 20px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 2rem; color: var(--primary-color); margin-bottom: 10px;">
                                        <i class="fas fa-vote-yea"></i>
                                    </div>
                                    <div style="font-size: 1.5rem; font-weight: 600; margin-bottom: 5px;">${stats.total_votes || 0}</div>
                                    <div style="color: var(--text-muted);">Total Votes</div>
                                </div>
                                <div class="stat-card" style="background: var(--bg-secondary); padding: 20px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 2rem; color: var(--success-color); margin-bottom: 10px;">
                                        <i class="fas fa-trophy"></i>
                                    </div>
                                    <div style="font-size: 1.5rem; font-weight: 600; margin-bottom: 5px;">#${stats.position || 'N/A'}</div>
                                    <div style="color: var(--text-muted);">Current Position</div>
                                </div>
                                <div class="stat-card" style="background: var(--bg-secondary); padding: 20px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 2rem; color: var(--warning-color); margin-bottom: 10px;">
                                        <i class="fas fa-percentage"></i>
                                    </div>
                                    <div style="font-size: 1.5rem; font-weight: 600; margin-bottom: 5px;">${stats.vote_percentage || 0}%</div>
                                    <div style="color: var(--text-muted);">Vote Share</div>
                                </div>
                                <div class="stat-card" style="background: var(--bg-secondary); padding: 20px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 2rem; color: var(--info-color); margin-bottom: 10px;">
                                        <i class="fas fa-calendar"></i>
                                    </div>
                                    <div style="font-size: 1.5rem; font-weight: 600; margin-bottom: 5px;">${stats.votes_today || 0}</div>
                                    <div style="color: var(--text-muted);">Votes Today</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }
        
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.remove();
            }
        }
    </script>
</body>
</html>
