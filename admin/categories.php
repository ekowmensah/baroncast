<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch categories with event information
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.name as category_name,
        c.description,
        c.status,
        c.created_at,
        e.title as event_title,
        e.id as event_id,
        u.full_name as organizer_name,
        COUNT(DISTINCT n.id) as nominee_count,
        COUNT(DISTINCT v.id) as total_votes
    FROM categories c
    LEFT JOIN events e ON c.event_id = e.id
    LEFT JOIN users u ON e.organizer_id = u.id
    LEFT JOIN nominees n ON c.id = n.category_id
    LEFT JOIN votes v ON n.id = v.nominee_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - E-Cast Admin</title>
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
                        <a href="categories.php" class="nav-link active">
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
                        <h1 class="page-title mb-0">Categories Management</h1>
                        <nav style="font-size: 0.875rem; color: var(--text-secondary);">
                            <a href="index.php" style="color: var(--text-secondary); text-decoration: none;">Home</a>
                            <span style="margin: 0 0.5rem;">/</span>
                            <span>Event Management</span>
                            <span style="margin: 0 0.5rem;">/</span>
                            <span>Categories</span>
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
                    <button class="btn btn-primary" onclick="openAddCategoryModal()">
                        <i class="fas fa-plus"></i>
                        Add New Category
                    </button>
                    <button class="btn btn-outline" onclick="openImportModal()">
                        <i class="fas fa-upload"></i>
                        Import Categories
                    </button>
                    <button class="btn btn-outline" onclick="exportCategories()">
                        <i class="fas fa-download"></i>
                        Export Categories
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
                                        <th>Category Description</th>
                                        <th>Organizer</th>
                                        <th>Nominees Count</th>
                                        <th>Total Votes</th>
                                        <th>Status</th>
                                        <th>Created Date</th>
                                        <th>Tools</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($categories)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <div class="empty-state">
                                                    <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                                                    <h5 class="text-muted">No Categories Found</h5>
                                                    <p class="text-muted">Start by creating your first category to organize nominees.</p>
                                                    <button class="btn btn-primary" onclick="openAddCategoryModal()">
                                                        <i class="fas fa-plus"></i> Add New Category
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($categories as $index => $category): ?>
                                            <tr>
                                                <td><?= str_pad($category['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                                <td><?= htmlspecialchars($category['event_title'] ?? 'Standalone Category') ?></td>
                                                <td><?= htmlspecialchars($category['category_name']) ?></td>
                                                <td><?= htmlspecialchars($category['organizer_name'] ?? 'Admin') ?></td>
                                                <td><?= $category['nominee_count'] ?></td>
                                                <td><?= number_format($category['total_votes']) ?></td>
                                                <td>
                                                    <?php 
                                                    $statusClass = '';
                                                    switch($category['status']) {
                                                        case 'active': $statusClass = 'badge-success'; break;
                                                        case 'inactive': $statusClass = 'badge-warning'; break;
                                                        case 'ended': $statusClass = 'badge-secondary'; break;
                                                        default: $statusClass = 'badge-info';
                                                    }
                                                    ?>
                                                    <span class="badge <?= $statusClass ?>"><?= ucfirst($category['status']) ?></span>
                                                </td>
                                                <td><?= date('Y-m-d', strtotime($category['created_at'])) ?></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <button class="btn btn-info btn-sm" title="View Details" onclick="viewCategory(<?= $category['id'] ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-success btn-sm" title="Edit" onclick="editCategory(<?= $category['id'] ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($category['status'] === 'active'): ?>
                                                            <button class="btn btn-warning btn-sm" title="Deactivate" onclick="toggleCategoryStatus(<?= $category['id'] ?>, 'inactive')">
                                                                <i class="fas fa-pause"></i>
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-primary btn-sm" title="Activate" onclick="toggleCategoryStatus(<?= $category['id'] ?>, 'active')">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <button class="btn btn-danger btn-sm" title="Delete" onclick="deleteCategory(<?= $category['id'] ?>)">
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
                        <div style="padding: 1.5rem;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">
                                        <?php 
                                        $totalCategories = count($categories);
                                        if ($totalCategories == 0) {
                                            echo "No entries found";
                                        } else {
                                            echo "Showing 1 to {$totalCategories} of {$totalCategories} entries";
                                        }
                                        ?>
                                    </small>
                                </div>
                                <?php if ($totalCategories > 0): ?>
                                <nav>
                                    <ul class="pagination" style="margin: 0; display: flex; list-style: none; gap: 0.25rem;">
                                        <li><a href="#" class="btn btn-outline btn-sm disabled">Previous</a></li>
                                        <li><a href="#" class="btn btn-primary btn-sm">1</a></li>
                                        <li><a href="#" class="btn btn-outline btn-sm disabled">Next</a></li>
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

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Category</h3>
                <button type="button" class="close-btn" onclick="closeAddCategoryModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="addCategoryForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="eventSelect" class="form-label">Select Event (Optional)</label>
                            <select id="eventSelect" name="event_id" class="form-control">
                                <option value="">No event (standalone category)</option>
                                <!-- Events will be loaded dynamically -->
                            </select>
                            <small class="form-text text-muted">Leave empty to create a standalone category that can be assigned to events later.</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="categoryName" class="form-label">Category Name *</label>
                            <input type="text" id="categoryName" name="name" class="form-control" 
                                   placeholder="Enter category name" required maxlength="100">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="categoryDescription" class="form-label">Description</label>
                            <textarea id="categoryDescription" name="description" class="form-control" 
                                      rows="3" placeholder="Enter category description" maxlength="500"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label for="maxNominees" class="form-label">Max Nominees</label>
                            <input type="number" id="maxNominees" name="max_nominees" class="form-control" 
                                   placeholder="Unlimited" min="1" max="100">
                        </div>
                        <div class="form-group col-6">
                            <label for="voteLimit" class="form-label">Vote Limit</label>
                            <input type="number" id="voteLimit" name="vote_limit" class="form-control" 
                                   placeholder="1" min="1" max="10" value="1">
                            <small class="form-text text-muted">Maximum votes per voter for this category</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label for="categoryStatus" class="form-label">Status</label>
                            <select id="categoryStatus" name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="form-group col-6">
                            <label for="displayOrder" class="form-label">Display Order</label>
                            <input type="number" id="displayOrder" name="display_order" class="form-control" 
                                   placeholder="1" min="1" value="1">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category Settings</label>
                            <div class="checkbox-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="allow_multiple_votes" value="1">
                                    <span class="checkmark"></span>
                                    Allow multiple votes per user
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="show_vote_count" value="1" checked>
                                    <span class="checkmark"></span>
                                    Show vote count to public
                                </label>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="featured_category" value="1">
                                    <span class="checkmark"></span>
                                    Featured category
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeAddCategoryModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAddCategory()">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Additional functionality for categories page
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
        
        // Category management functions
        function openAddCategoryModal() {
            // Load events for dropdown
            loadEventsForModal();
            // Show modal
            document.getElementById('addCategoryModal').style.display = 'flex';
        }
        
        function closeAddCategoryModal() {
            document.getElementById('addCategoryModal').style.display = 'none';
            // Reset form
            document.getElementById('addCategoryForm').reset();
        }
        
        function loadEventsForModal() {
            fetch('actions/get-events.php')
                .then(response => response.json())
                .then(data => {
                    const eventSelect = document.getElementById('eventSelect');
                    eventSelect.innerHTML = '<option value="">No event (standalone category)</option>';
                    
                    if (data.success) {
                        data.events.forEach(event => {
                            const option = document.createElement('option');
                            option.value = event.id;
                            option.textContent = event.title;
                            eventSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading events:', error);
                });
        }
        
        function submitAddCategory() {
            const form = document.getElementById('addCategoryForm');
            const formData = new FormData(form);
            
            // Validate required fields (only name is required now)
            if (!formData.get('name')) {
                alert('Please enter a category name');
                return;
            }
            
            // Show loading state
            const submitBtn = event.target;
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            submitBtn.disabled = true;
            
            fetch('actions/add-category.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Category added successfully!');
                    closeAddCategoryModal();
                    // Reload page to show new category
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error adding category:', error);
                alert('An error occurred while adding the category');
            })
            .finally(() => {
                // Restore button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }
        
        function openImportModal() {
            alert('Import Categories modal - to be implemented with CSV/Excel upload');
        }
        
        function exportCategories() {
            // Create export request
            window.location.href = 'actions/export-categories.php';
        }
        
        function viewCategory(id) {
            // Show category details in a modal instead of redirecting
            fetch(`actions/get-category-details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showCategoryDetailsModal(data.category);
                    } else {
                        alert('Error loading category details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading category details');
                });
        }
        
        function editCategory(id) {
            // Show edit form in a modal instead of redirecting
            fetch(`actions/get-category-details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showEditCategoryModal(data.category);
                    } else {
                        alert('Error loading category for editing: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading category for editing');
                });
        }
        
        function toggleCategoryStatus(id, newStatus) {
            if (confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this category?`)) {
                fetch('actions/toggle-category-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        category_id: id, 
                        status: newStatus 
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Category status updated successfully!');
                        location.reload();
                    } else {
                        alert('Error updating category status: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating category status');
                });
            }
        }
        
        function deleteCategory(id) {
            if (confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
                fetch('actions/delete-category.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ category_id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Category deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error deleting category: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting category');
                });
            }
        }
        
        // Modal functions for category details and edit
        function showCategoryDetailsModal(category) {
            const modalHtml = `
                <div id="categoryDetailsModal" class="modal" style="display: block; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background-color: var(--bg-primary); margin: 5% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 600px; color: var(--text-primary);">
                        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
                            <h3><i class="fas fa-eye"></i> Category Details</h3>
                            <span class="close" onclick="closeModal('categoryDetailsModal')" style="font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                        </div>
                        <div class="modal-body">
                            <div style="display: grid; gap: 15px;">
                                <div><strong>Name:</strong> ${category.name}</div>
                                <div><strong>Description:</strong> ${category.description || 'No description'}</div>
                                <div><strong>Status:</strong> <span class="badge badge-${category.status === 'active' ? 'success' : category.status === 'inactive' ? 'warning' : 'secondary'}">${category.status.charAt(0).toUpperCase() + category.status.slice(1)}</span></div>
                                <div><strong>Event:</strong> ${category.event_title || 'Standalone Category'}</div>
                                <div><strong>Nominees:</strong> ${category.nominee_count || 0}</div>
                                <div><strong>Total Votes:</strong> ${category.total_votes || 0}</div>
                                <div><strong>Created:</strong> ${new Date(category.created_at).toLocaleDateString()}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }
        
        function showEditCategoryModal(category) {
            const modalHtml = `
                <div id="editCategoryModal" class="modal" style="display: block; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background-color: var(--bg-primary); margin: 5% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 600px; color: var(--text-primary);">
                        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
                            <h3><i class="fas fa-edit"></i> Edit Category</h3>
                            <span class="close" onclick="closeModal('editCategoryModal')" style="font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form id="editCategoryForm">
                                <input type="hidden" name="category_id" value="${category.id}">
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label>Name:</label>
                                    <input type="text" name="name" value="${category.name}" class="form-control" required>
                                </div>
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label>Description:</label>
                                    <textarea name="description" class="form-control" rows="3">${category.description || ''}</textarea>
                                </div>
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label>Status:</label>
                                    <select name="status" class="form-control">
                                        <option value="active" ${category.status === 'active' ? 'selected' : ''}>Active</option>
                                        <option value="inactive" ${category.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                        <option value="ended" ${category.status === 'ended' ? 'selected' : ''}>Ended</option>
                                    </select>
                                </div>
                                <div style="text-align: right; margin-top: 20px;">
                                    <button type="button" onclick="closeModal('editCategoryModal')" class="btn btn-secondary" style="margin-right: 10px;">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Category</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Handle form submission
            document.getElementById('editCategoryForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('actions/update-category.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Category updated successfully!');
                        closeModal('editCategoryModal');
                        location.reload();
                    } else {
                        alert('Error updating category: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating category');
                });
            });
        }
        
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.remove();
            }
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
        
        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-content {
            background-color: var(--bg-primary);
            border-radius: 0.5rem;
            box-shadow: var(--shadow-lg);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .close-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 0.25rem;
            transition: all 0.2s ease;
        }
        
        .close-btn:hover {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .form-row {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group.col-6 {
            flex: 0 0 calc(50% - 0.5rem);
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            font-size: 0.875rem;
            color: var(--text-primary);
        }
        
        .checkbox-label input[type="checkbox"] {
            display: none;
        }
        
        .checkmark {
            width: 18px;
            height: 18px;
            border: 2px solid var(--border-color);
            border-radius: 0.25rem;
            position: relative;
            transition: all 0.2s ease;
        }
        
        .checkbox-label input[type="checkbox"]:checked + .checkmark {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .checkbox-label input[type="checkbox"]:checked + .checkmark::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 1rem;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .form-group.col-6 {
                flex: 1;
            }
            
            .modal-footer {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>
