<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch all organizers with their statistics
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT e.id) as events_created,
           COALESCE(SUM(vote_counts.total_votes), 0) as total_votes,
           SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active_events
    FROM users u 
    LEFT JOIN events e ON u.id = e.organizer_id 
    LEFT JOIN (
        SELECT event_id, COUNT(*) as total_votes 
        FROM votes 
        GROUP BY event_id
    ) vote_counts ON e.id = vote_counts.event_id
    WHERE u.role = 'organizer'
    GROUP BY u.id 
    ORDER BY u.created_at DESC
");
$stmt->execute();
$organizers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Organizers - Admin Dashboard</title>
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
                        <a href="users.php" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>All Users</span>
                        </a>
                        <a href="organizers.php" class="nav-link active">
                            <i class="fas fa-user-tie"></i>
                            <span>Event Organizers</span>
                        </a>
                        <a href="admins.php" class="nav-link">
                            <i class="fas fa-user-shield"></i>
                            <span>Administrators</span>
                        </a>
                    </div>
                </div>
                
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
                    <h1 class="page-title mb-0">Event Organizers</h1>
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
                    <h2 class="page-title">Event Organizers</h2>
                    <p class="page-subtitle">Manage event organizers and monitor their performance.</p>
                </div>
                <!-- Platform Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= count($organizers) ?></div>
                        <div class="stat-label">Total Organizers</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value"><?= array_sum(array_column($organizers, 'events_created')) ?></div>
                        <div class="stat-label">Events Created</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value"><?= array_sum(array_column($organizers, 'active_events')) ?></div>
                        <div class="stat-label">Active Events</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-value"><?= array_sum(array_column($organizers, 'total_votes')) ?></div>
                        <div class="stat-label">Total Votes</div>
                    </div>
                </div>

                <!-- Quick Actions Row -->
                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--primary-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;">Add Organizer</div>
                        <div class="stat-label">Create New Account</div>
                        <button class="btn btn-primary btn-sm" style="margin-top: 0.5rem;">Create</button>
                    </div>
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--success-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;">Performance</div>
                        <div class="stat-label">Organizer Analytics</div>
                        <button class="btn btn-success btn-sm" style="margin-top: 0.5rem;">View</button>
                    </div>
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--warning-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;">Bulk Message</div>
                        <div class="stat-label">Send Notifications</div>
                        <button class="btn btn-warning btn-sm" style="margin-top: 0.5rem;">Send</button>
                    </div>
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--info-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-download"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;">Export</div>
                        <div class="stat-label">Organizer Reports</div>
                        <button class="btn btn-info btn-sm" style="margin-top: 0.5rem;">Export</button>
                    </div>
                </div>

                <!-- Organizer Management Table -->
                <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Event Organizers Management</h3>
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
                                            <th>Organizer Details</th>
                                            <th>Contact Information</th>
                                            <th>Event Statistics</th>
                                            <th>Performance Metrics</th>
                                            <th>Status & Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($organizers as $organizer): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, var(--warning-color), var(--primary-color)); display: flex; align-items: center; justify-content: center; color: white;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                    <div>
                                                        <div style="font-weight: 600; color: var(--text-color); margin-bottom: 0.25rem;"><?= htmlspecialchars($organizer['full_name']) ?></div>
                                                        <div style="font-size: 0.875rem; color: var(--text-muted);">@<?= htmlspecialchars($organizer['username']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem;">
                                                    <div style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                                        <i class="fas fa-envelope" style="color: var(--text-muted); width: 16px;"></i>
                                                        <span><?= htmlspecialchars($organizer['email']) ?></span>
                                                    </div>
                                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                        <i class="fas fa-phone" style="color: var(--text-muted); width: 16px;"></i>
                                                        <span><?= htmlspecialchars($organizer['phone'] ?? 'N/A') ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 1rem;">
                                                    <div style="text-align: center;">
                                                        <div style="font-weight: 600; color: var(--primary-color);"><?= $organizer['events_created'] ?></div>
                                                        <div style="font-size: 0.75rem; color: var(--text-muted);">Total Events</div>
                                                    </div>
                                                    <div style="text-align: center;">
                                                        <div style="font-weight: 600; color: var(--success-color);"><?= $organizer['active_events'] ?></div>
                                                        <div style="font-size: 0.75rem; color: var(--text-muted);">Active</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div style="flex: 1;">
                                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                                            <span style="font-size: 0.75rem; color: var(--text-muted);">Votes Received</span>
                                                            <span style="font-weight: 600; color: var(--success-color);"><?= $organizer['total_votes'] ?></span>
                                                        </div>
                                                        <div style="background: var(--bg-secondary); border-radius: 4px; height: 6px; overflow: hidden;">
                                                            <div style="height: 100%; background: var(--success-color); width: <?= min(100, ($organizer['total_votes'] / max(1, array_sum(array_column($organizers, 'total_votes'))) * 100)) ?>%;"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <span class="badge badge-success" style="margin-bottom: 0.25rem;">
                                                        <i class="fas fa-check-circle"></i> Active
                                                    </span>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                        Joined: <?= date('M j, Y', strtotime($organizer['created_at'])) ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.25rem;">
                                                    <button class="btn btn-outline btn-sm" title="View Profile" onclick="viewOrganizer(<?= $organizer['id'] ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline btn-sm" title="View Events" onclick="viewOrganizerEvents(<?= $organizer['id'] ?>)">
                                                        <i class="fas fa-calendar"></i>
                                                    </button>
                                                    <button class="btn btn-outline btn-sm" title="Send Message" onclick="sendMessage(<?= $organizer['id'] ?>)">
                                                        <i class="fas fa-envelope"></i>
                                                    </button>
                                                    <div class="dropdown" style="position: relative; display: inline-block;">
                                                        <button class="btn btn-outline btn-sm dropdown-toggle" title="More Options" onclick="toggleOrganizerDropdown(<?= $organizer['id'] ?>)">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <div id="organizer-dropdown-<?= $organizer['id'] ?>" class="dropdown-menu" style="display: none;">
                                                            <a href="#" onclick="editOrganizer(<?= $organizer['id'] ?>)">
                                                                <i class="fas fa-edit"></i> Edit Profile
                                                            </a>
                                                            <a href="#" onclick="viewOrganizerStats(<?= $organizer['id'] ?>)">
                                                                <i class="fas fa-chart-bar"></i> View Statistics
                                                            </a>
                                                            <a href="#" onclick="resetOrganizerPassword(<?= $organizer['id'] ?>)">
                                                                <i class="fas fa-key"></i> Reset Password
                                                            </a>
                                                            <div class="dropdown-divider"></div>
                                                            <a href="#" onclick="suspendOrganizer(<?= $organizer['id'] ?>)" class="text-warning">
                                                                <i class="fas fa-pause"></i> Suspend Account
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
</body>
</html>
