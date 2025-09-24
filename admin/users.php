<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch all users with their roles and statistics
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT e.id) as events_created
    FROM users u 
    LEFT JOIN events e ON u.id = e.organizer_id 
    GROUP BY u.id 
    ORDER BY u.created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Users - Admin Dashboard</title>
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
                
                <!-- User Management -->
                <div class="nav-item">
                    <a href="#" class="nav-link active" data-submenu="users-menu">
                        <i class="fas fa-users-cog"></i>
                        <span>User Management</span>
                        <i class="fas fa-chevron-down submenu-icon" style="margin-left: auto;"></i>
                    </a>
                    <div id="users-menu" class="nav-submenu show">
                        <a href="users.php" class="nav-link active">
                            <i class="fas fa-users"></i>
                            <span>All Users</span>
                        </a>
                        <a href="organizers.php" class="nav-link">
                            <i class="fas fa-user-tie"></i>
                            <span>Event Organizers</span>
                        </a>
                        <a href="admins.php" class="nav-link">
                            <i class="fas fa-user-shield"></i>
                            <span>Administrators</span>
                        </a>
                    </div>
                </div>
                
                <!-- Other menu items... -->
                <div class="nav-item">
                    <a href="events.php" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Event Management</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="transactions.php" class="nav-link">
                        <i class="fas fa-money-check-alt"></i>
                        <span>Financial Management</span>
                    </a>
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
                    <h1 class="page-title mb-0">All Users</h1>
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
            <div class="content" style="padding: 0 2rem;">
                <div class="page-header">
                    <h2 class="page-title">User Management</h2>
                    <p class="page-subtitle">Manage all users, organizers, and administrators from here.</p>
                </div>
                <!-- Platform Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= count($users) ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value"><?= count(array_filter($users, fn($u) => $u['role'] === 'organizer')) ?></div>
                        <div class="stat-label">Event Organizers</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value"><?= count(array_filter($users, fn($u) => $u['role'] === 'admin')) ?></div>
                        <div class="stat-label">Administrators</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-value"><?= count(array_filter($users, fn($u) => date('Y-m-d', strtotime($u['created_at'])) === date('Y-m-d'))) ?></div>
                        <div class="stat-label">New Today</div>
                    </div>
                </div>



                <!-- User Management Table -->
                <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">User Management</h3>
                            <div style="display: flex; gap: 0.5rem;">
                                <button class="btn btn-outline btn-sm">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <button class="btn btn-outline btn-sm">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>User Details</th>
                                            <th>Role & Status</th>
                                            <th>Contact Information</th>
                                            <th>Activity</th>
                                            <th>Joined Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $userData): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-color); display: flex; align-items: center; justify-content: center; color: white;">
                                                        <i class="fas fa-<?= $userData['role'] === 'admin' ? 'user-shield' : ($userData['role'] === 'organizer' ? 'user-tie' : 'user') ?>"></i>
                                                    </div>
                                                    <div>
                                                        <div style="font-weight: 600; color: var(--text-color);"><?= htmlspecialchars($userData['full_name']) ?></div>
                                                        <div style="font-size: 0.875rem; color: var(--text-muted);">@<?= htmlspecialchars($userData['username']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?= $userData['role'] === 'admin' ? 'danger' : ($userData['role'] === 'organizer' ? 'primary' : 'secondary') ?>">
                                                    <?= ucfirst($userData['role']) ?>
                                                </span>
                                                <div style="margin-top: 0.25rem;">
                                                    <span class="badge badge-success">Active</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <div style="margin-bottom: 0.25rem;">
                                                        <i class="fas fa-envelope" style="color: var(--text-muted); margin-right: 0.5rem;"></i>
                                                        <?= htmlspecialchars($userData['email']) ?>
                                                    </div>
                                                    <div>
                                                        <i class="fas fa-phone" style="color: var(--text-muted); margin-right: 0.5rem;"></i>
                                                        <?= htmlspecialchars($userData['phone'] ?? 'N/A') ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="text-align: center;">
                                                    <div style="font-weight: 600; color: var(--primary-color);"><?= $userData['events_created'] ?></div>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted);">Events Created</div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem; color: var(--text-muted);">
                                                    <?= date('M j, Y', strtotime($userData['created_at'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                                    <button class="btn btn-primary btn-sm" title="View Details" onclick="viewUser(<?= $userData['id'] ?>)"
                                                            style="background: var(--primary-color); color: white; border: none; padding: 0.375rem 0.5rem; border-radius: 0.25rem;">
                                                        <i class="fas fa-eye" style="font-size: 0.875rem;"></i>
                                                    </button>
                                                    <button class="btn btn-success btn-sm" title="Edit User" onclick="editUser(<?= $userData['id'] ?>)"
                                                            style="background: var(--success-color); color: white; border: none; padding: 0.375rem 0.5rem; border-radius: 0.25rem;">
                                                        <i class="fas fa-edit" style="font-size: 0.875rem;"></i>
                                                    </button>
                                                    <div class="dropdown" style="position: relative; display: inline-block;">
                                                        <button class="btn btn-secondary btn-sm dropdown-toggle" title="More Options" onclick="toggleDropdown(<?= $userData['id'] ?>)"
                                                                style="background: var(--text-secondary); color: white; border: none; padding: 0.375rem 0.5rem; border-radius: 0.25rem;">
                                                            <i class="fas fa-ellipsis-v" style="font-size: 0.875rem;"></i>
                                                        </button>
                                                        <div id="dropdown-<?= $userData['id'] ?>" class="dropdown-menu" style="display: none; position: absolute; right: 0; top: 100%; z-index: 1000; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 0.375rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); min-width: 180px; padding: 0.5rem 0;">
                                                            <a href="#" onclick="resetPassword(<?= $userData['id'] ?>)" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: var(--text-primary); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='var(--bg-secondary)'" onmouseout="this.style.backgroundColor='transparent'">
                                                                <i class="fas fa-key" style="width: 16px; margin-right: 0.5rem; color: var(--info-color);"></i> Reset Password
                                                            </a>
                                                            <a href="#" onclick="toggleUserStatus(<?= $userData['id'] ?>)" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: var(--text-primary); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='var(--bg-secondary)'" onmouseout="this.style.backgroundColor='transparent'">
                                                                <i class="fas fa-user-lock" style="width: 16px; margin-right: 0.5rem; color: var(--warning-color);"></i> Toggle Status
                                                            </a>
                                                            <a href="#" onclick="viewUserActivity(<?= $userData['id'] ?>)" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: var(--text-primary); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='var(--bg-secondary)'" onmouseout="this.style.backgroundColor='transparent'">
                                                                <i class="fas fa-history" style="width: 16px; margin-right: 0.5rem; color: var(--primary-color);"></i> View Activity
                                                            </a>
                                                            <div class="dropdown-divider" style="border-top: 1px solid var(--border-color); margin: 0.5rem 0;"></div>
                                                            <a href="#" onclick="deleteUser(<?= $userData['id'] ?>)" class="text-danger" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: var(--danger-color); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(220, 53, 69, 0.1)'" onmouseout="this.style.backgroundColor='transparent'">
                                                                <i class="fas fa-trash" style="width: 16px; margin-right: 0.5rem;"></i> Delete User
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
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    
    <style>
        /* Dropdown Menu Styles */
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-shadow: var(--shadow-lg);
            min-width: 180px;
            z-index: 1000;
            padding: 0.5rem 0;
        }
        
        .dropdown-menu a {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: background-color 0.2s ease;
        }
        
        .dropdown-menu a:hover {
            background-color: var(--bg-tertiary);
        }
        
        .dropdown-menu a i {
            margin-right: 0.5rem;
            width: 16px;
        }
        
        .dropdown-divider {
            height: 1px;
            background-color: var(--border-color);
            margin: 0.5rem 0;
        }
        
        .text-danger {
            color: var(--danger-color) !important;
        }
        
        .dropdown-toggle::after {
            display: none;
        }
    </style>
    
    <script>
        // User Management Functions
        function viewUser(userId) {
            // Open user details modal or redirect to user profile page
            alert(`Viewing user details for User ID: ${userId}`);
            // You can implement a modal or redirect to a detailed view
            // window.location.href = `user-details.php?id=${userId}`;
        }
        
        function editUser(userId) {
            // Open edit user modal or redirect to edit page
            alert(`Editing user with ID: ${userId}`);
            // You can implement a modal or redirect to edit form
            // window.location.href = `edit-user.php?id=${userId}`;
        }
        
        function resetPassword(userId) {
            if (confirm('Are you sure you want to reset this user\'s password? They will receive an email with a new temporary password.')) {
                // Send AJAX request to reset password
                fetch('actions/reset-password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ user_id: userId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Password reset email sent successfully!');
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while resetting the password.');
                });
            }
        }
        
        function toggleUserStatus(userId) {
            if (confirm('Are you sure you want to toggle this user\'s status?')) {
                // Send AJAX request to toggle user status
                fetch('actions/toggle-user-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ user_id: userId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('User status updated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating user status.');
                });
            }
        }
        
        function viewUserActivity(userId) {
            // Open activity log modal or redirect to activity page
            alert(`Viewing activity log for User ID: ${userId}`);
            // You can implement a modal or redirect to activity log
            // window.location.href = `user-activity.php?id=${userId}`;
        }
        
        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user? This action cannot be undone!')) {
                if (confirm('This will permanently delete all user data. Are you absolutely sure?')) {
                    // Send AJAX request to delete user
                    fetch('actions/delete-user.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ user_id: userId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('User deleted successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while deleting the user.');
                    });
                }
            }
        }
        
        // Dropdown Toggle Function
        function toggleDropdown(userId) {
            const dropdown = document.getElementById(`dropdown-${userId}`);
            const allDropdowns = document.querySelectorAll('.dropdown-menu');
            
            // Close all other dropdowns
            allDropdowns.forEach(menu => {
                if (menu.id !== `dropdown-${userId}`) {
                    menu.style.display = 'none';
                }
            });
            
            // Toggle current dropdown
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
        
        // Prevent dropdown from closing when clicking inside
        document.addEventListener('click', function(event) {
            if (event.target.closest('.dropdown-menu')) {
                event.stopPropagation();
            }
        });
    </script>
</body>
</html>
