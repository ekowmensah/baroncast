<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch all admin users
$stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'admin' ORDER BY created_at DESC");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get admin statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
$stmt->execute();
$totalAdmins = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'admin' AND status = 'active'");
$stmt->execute();
$activeAdmins = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - Admin Dashboard</title>
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
                    <a href="#" class="nav-link active" data-submenu="users-menu">
                        <i class="fas fa-users-cog"></i>
                        <span>User Management</span>
                        <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="users-menu" class="nav-submenu show">
                        <a href="users.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>All Users</span>
                        </a>
                        <a href="organizers.php" class="nav-link">
                            <i class="fas fa-user-tie"></i>
                            <span>Event Organizers</span>
                        </a>
                        <a href="admins.php" class="nav-link active">
                            <i class="fas fa-user-shield"></i>
                            <span>System Admins</span>
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
                    <a href="#" class="nav-link" data-submenu="analytics-menu">
                        <i class="fas fa-chart-line"></i>
                        <span>Voting Analytics</span>
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
                    </div>
                </div>
                
                <div class="nav-item">
                    <a href="transactions.php" class="nav-link">
                        <i class="fas fa-credit-card"></i>
                        <span>Transactions</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                        <div class="user-role">System Administrator</div>
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
                    <h1 class="page-title mb-0">System Administrators</h1>
                </div>
                <div class="header-right">
                    <button id="theme-toggle" class="theme-toggle">
                        <i id="theme-icon" class="fas fa-moon"></i>
                        <span id="theme-text">Dark</span>
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-outline dropdown-toggle" onclick="toggleDropdown('user-menu')">
                            <i class="fas fa-user"></i>
                            <span><?= htmlspecialchars($user['full_name']) ?></span>
                        </button>
                        <div id="user-menu" class="dropdown-menu">
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-user-cog"></i>
                                Profile Settings
                            </a>
                            <a href="settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i>
                                System Settings
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
            <div class="content" style="padding: 0 2rem;">
                <div class="page-header">
                    <h2 class="page-title">System Administrators</h2>
                    <p class="page-subtitle">Manage system administrator accounts and permissions.</p>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid" style="margin-bottom: 2rem;">
                    <div class="stat-card">
                        <div class="stat-value"><?= $totalAdmins ?></div>
                        <div class="stat-label">Total Admins</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value"><?= $activeAdmins ?></div>
                        <div class="stat-label">Active Admins</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value"><?= $totalAdmins - $activeAdmins ?></div>
                        <div class="stat-label">Inactive Admins</div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <button class="btn btn-primary" onclick="openAddAdminModal()">
                                    <i class="fas fa-plus"></i> Add New Admin
                                </button>
                                <button class="btn btn-outline" onclick="exportAdmins()">
                                    <i class="fas fa-download"></i> Export Data
                                </button>
                            </div>
                            <div style="display: flex; gap: 0.5rem; align-items: center;">
                                <input type="text" id="searchInput" placeholder="Search administrators..." class="form-control" style="width: 250px;">
                                <select id="statusFilter" class="form-control" style="width: 150px;">
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admins Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Admin Details</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Created Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div class="user-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-color); display: flex; align-items: center; justify-content: center; color: white;">
                                                    <i class="fas fa-user-shield"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--text-primary);">
                                                        <?= htmlspecialchars($admin['full_name']) ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                        ID: <?= $admin['id'] ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($admin['email']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $admin['status'] === 'active' ? 'success' : 'secondary' ?>">
                                                <?= ucfirst($admin['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= isset($admin['last_login']) && $admin['last_login'] ? date('M j, Y g:i A', strtotime($admin['last_login'])) : 'Never' ?>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($admin['created_at'])) ?></td>
                                        <td>
                                            <div class="dropdown" style="position: relative;">
                                                <button class="btn btn-primary btn-sm" onclick="toggleDropdown(<?= $admin['id'] ?>)" 
                                                        style="background: var(--primary-color); color: white; border: none; padding: 0.375rem 0.75rem; border-radius: 0.25rem;">
                                                    <i class="fas fa-ellipsis-v" style="font-size: 0.875rem;"></i>
                                                </button>
                                                <div id="dropdown-<?= $admin['id'] ?>" class="dropdown-menu" style="display: none; position: absolute; right: 0; top: 100%; z-index: 1000; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 0.375rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); min-width: 180px; padding: 0.5rem 0;">
                                                    <a href="#" onclick="viewAdmin(<?= $admin['id'] ?>)" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: var(--text-primary); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='var(--bg-secondary)'" onmouseout="this.style.backgroundColor='transparent'">
                                                        <i class="fas fa-eye" style="width: 16px; margin-right: 0.5rem; color: var(--primary-color);"></i> View Details
                                                    </a>
                                                    <a href="#" onclick="editAdmin(<?= $admin['id'] ?>)" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: var(--text-primary); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='var(--bg-secondary)'" onmouseout="this.style.backgroundColor='transparent'">
                                                        <i class="fas fa-edit" style="width: 16px; margin-right: 0.5rem; color: var(--success-color);"></i> Edit Admin
                                                    </a>
                                                    <?php if ($admin['id'] != $user['id']): ?>
                                                    <a href="#" onclick="toggleAdminStatus(<?= $admin['id'] ?>, '<?= $admin['status'] ?>')" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: var(--text-primary); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='var(--bg-secondary)'" onmouseout="this.style.backgroundColor='transparent'">
                                                        <i class="fas fa-<?= $admin['status'] === 'active' ? 'ban' : 'check' ?>" style="width: 16px; margin-right: 0.5rem; color: var(--warning-color);"></i> 
                                                        <?= $admin['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                                                    </a>
                                                    <a href="#" onclick="resetAdminPassword(<?= $admin['id'] ?>)" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: var(--text-primary); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='var(--bg-secondary)'" onmouseout="this.style.backgroundColor='transparent'">
                                                        <i class="fas fa-key" style="width: 16px; margin-right: 0.5rem; color: var(--info-color);"></i> Reset Password
                                                    </a>
                                                    <div class="dropdown-divider" style="border-top: 1px solid var(--border-color); margin: 0.5rem 0;"></div>
                                                    <a href="#" onclick="deleteAdmin(<?= $admin['id'] ?>)" class="text-danger" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: var(--danger-color); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(220, 53, 69, 0.1)'" onmouseout="this.style.backgroundColor='transparent'">
                                                        <i class="fas fa-trash" style="width: 16px; margin-right: 0.5rem;"></i> Delete Admin
                                                    </a>
                                                    <?php endif; ?>
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
            </div>
        </main>
    </div>

    <!-- Add New Admin Modal -->
    <div id="addAdminModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Administrator</h3>
                <span class="close" onclick="closeAddAdminModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="addAdminForm">
                    <div class="form-group">
                        <label for="admin_full_name">Full Name *</label>
                        <input type="text" id="admin_full_name" name="full_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_email">Email Address *</label>
                        <input type="email" id="admin_email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_phone">Phone Number</label>
                        <input type="tel" id="admin_phone" name="phone" class="form-control" placeholder="+233XXXXXXXXX">
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_password">Password *</label>
                        <input type="password" id="admin_password" name="password" class="form-control" required minlength="8">
                        <small class="text-muted">Minimum 8 characters</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_confirm_password">Confirm Password *</label>
                        <input type="password" id="admin_confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_status">Status</label>
                        <select id="admin_status" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_permissions">Permissions</label>
                        <div class="checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="permissions[]" value="manage_users" checked>
                                <span class="checkmark"></span>
                                Manage Users
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permissions[]" value="manage_events" checked>
                                <span class="checkmark"></span>
                                Manage Events
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permissions[]" value="manage_finances" checked>
                                <span class="checkmark"></span>
                                Manage Finances
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permissions[]" value="system_settings" checked>
                                <span class="checkmark"></span>
                                System Settings
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeAddAdminModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveNewAdmin()">
                    <i class="fas fa-save"></i> Create Administrator
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        // Admin management functions
        function openAddAdminModal() {
            document.getElementById('addAdminModal').style.display = 'block';
            document.getElementById('addAdminForm').reset();
        }
        
        function closeAddAdminModal() {
            document.getElementById('addAdminModal').style.display = 'none';
        }
        
        function saveNewAdmin() {
            const form = document.getElementById('addAdminForm');
            const formData = new FormData(form);
            
            // Validate passwords match
            const password = document.getElementById('admin_password').value;
            const confirmPassword = document.getElementById('admin_confirm_password').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return;
            }
            
            // Validate form
            if (!form.checkValidity()) {
                alert('Please fill in all required fields correctly.');
                return;
            }
            
            // Show loading state
            const saveBtn = document.querySelector('#addAdminModal .btn-primary');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            saveBtn.disabled = true;
            
            // Submit form
            fetch('actions/add-admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Administrator created successfully!');
                    closeAddAdminModal();
                    location.reload(); // Refresh page to show new admin
                } else {
                    alert('Error: ' + (data.message || 'Failed to create administrator'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while creating the administrator');
            })
            .finally(() => {
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            });
        }
        
        function exportAdmins() {
            // Create export URL with current filters
            const searchTerm = document.getElementById('searchInput').value;
            const statusFilter = document.getElementById('statusFilter').value;
            
            let exportUrl = 'actions/export-admins.php?';
            if (searchTerm) exportUrl += 'search=' + encodeURIComponent(searchTerm) + '&';
            if (statusFilter) exportUrl += 'status=' + encodeURIComponent(statusFilter) + '&';
            
            // Open export in new window
            window.open(exportUrl, '_blank');
        }
        
        function viewAdmin(id) {
            alert('View admin details for ID: ' + id);
        }
        
        function editAdmin(id) {
            alert('Edit admin for ID: ' + id);
        }
        
        function toggleAdminStatus(id, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            if (confirm(`Are you sure you want to ${newStatus === 'active' ? 'activate' : 'deactivate'} this administrator?`)) {
                alert('Toggle admin status functionality - to be implemented');
            }
        }
        
        function resetAdminPassword(id) {
            if (confirm('Are you sure you want to reset this administrator\'s password?')) {
                alert('Reset password functionality - to be implemented');
            }
        }
        
        function deleteAdmin(id) {
            if (confirm('Are you sure you want to delete this administrator? This action cannot be undone.')) {
                alert('Delete admin functionality - to be implemented');
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
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>
