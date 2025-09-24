<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();

// Get database connection
$database = new Database();
$pdo = $database->getConnection();

// Fetch all events with organizer info, statistics, and vote package count
$stmt = $pdo->prepare("
    SELECT e.*, u.full_name as organizer_name,
           COUNT(DISTINCT c.id) as category_count,
           COUNT(DISTINCT n.id) as nominee_count,
           COUNT(DISTINCT v.id) as total_votes
    FROM events e 
    LEFT JOIN users u ON e.organizer_id = u.id 
    LEFT JOIN categories c ON e.id = c.event_id 
    LEFT JOIN nominees n ON c.id = n.category_id
    LEFT JOIN votes v ON e.id = v.event_id 
    GROUP BY e.id 
    ORDER BY e.created_at DESC
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Events - Admin Dashboard</title>
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
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users-cog"></i>
                        <span>User Management</span>
                    </a>
                </div>
                
                <!-- Event Management -->
                <div class="nav-item">
                    <a href="events.php" class="nav-link active">
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
                    <h1 class="page-title mb-0">Event Management</h1>
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
                    <h2 class="page-title">Event Management</h2>
                    <p class="page-subtitle">Create, manage, and monitor all voting events from here.</p>
                </div>
                <!-- Platform Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= count($events) ?></div>
                        <div class="stat-label">Total Events</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-value"><?= count(array_filter($events, fn($e) => $e['status'] === 'active')) ?></div>
                        <div class="stat-label">Active Events</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value"><?= count(array_filter($events, fn($e) => $e['status'] === 'draft')) ?></div>
                        <div class="stat-label">Draft Events</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-value"><?= count(array_filter($events, fn($e) => $e['status'] === 'ended')) ?></div>
                        <div class="stat-label">Ended Events</div>
                    </div>
                </div>

                <!-- Quick Actions Row -->
                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--primary-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;">Create Event</div>
                        <div class="stat-label">New Event Setup</div>
                        <button class="btn btn-primary btn-sm" style="margin-top: 0.5rem;">Create</button>
                    </div>
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--success-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;">Approve Events</div>
                        <div class="stat-label">Pending Approvals</div>
                        <button class="btn btn-success btn-sm" style="margin-top: 0.5rem;">Review</button>
                    </div>
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--warning-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;">Analytics</div>
                        <div class="stat-label">Event Performance</div>
                        <button class="btn btn-warning btn-sm" style="margin-top: 0.5rem;">View</button>
                    </div>
                    <div class="card text-center" style="padding: 1.5rem;">
                        <div style="font-size: 2rem; color: var(--info-color); margin-bottom: 0.5rem;">
                            <i class="fas fa-download"></i>
                        </div>
                        <div class="stat-value" style="font-size: 1.5rem;">Export</div>
                        <div class="stat-label">Event Reports</div>
                        <button class="btn btn-info btn-sm" style="margin-top: 0.5rem;">Export</button>
                    </div>
                </div>

                <!-- Event Management Table -->
                <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Event Management</h3>
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
                                            <th>Event Details</th>
                                            <th>Organizer</th>
                                            <th>Statistics</th>
                                            <th>Status & Dates</th>
                                            <th>Performance</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($events as $event): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div style="width: 50px; height: 50px; border-radius: 8px; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; color: white;">
                                                        <i class="fas fa-calendar-alt"></i>
                                                    </div>
                                                    <div>
                                                        <div style="font-weight: 600; color: var(--text-color); margin-bottom: 0.25rem;"><?= htmlspecialchars($event['title']) ?></div>
                                                        <div style="font-size: 0.875rem; color: var(--text-muted); line-height: 1.3;"><?= htmlspecialchars(substr($event['description'], 0, 80)) ?>...</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--success-color); display: flex; align-items: center; justify-content: center; color: white;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                    <div>
                                                        <div style="font-weight: 500; color: var(--text-color);"><?= htmlspecialchars($event['organizer_name']) ?></div>
                                                        <div style="font-size: 0.75rem; color: var(--text-muted);">Event Organizer</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 1rem;">
                                                    <div style="text-align: center;">
                                                        <div style="font-weight: 600; color: var(--primary-color);"><?= $event['category_count'] ?></div>
                                                        <div style="font-size: 0.75rem; color: var(--text-muted);">Categories</div>
                                                    </div>
                                                    <div style="text-align: center;">
                                                        <div style="font-weight: 600; color: var(--warning-color);"><?= $event['nominee_count'] ?></div>
                                                        <div style="font-size: 0.75rem; color: var(--text-muted);">Nominees</div>
                                                    </div>
                                                    <div style="text-align: center;">
                                                        <div style="font-weight: 600; color: var(--success-color);"><?= $event['total_votes'] ?></div>
                                                        <div style="font-size: 0.75rem; color: var(--text-muted);">Votes</div>
                                                    </div>
                                                    <!-- Vote packages removed - now using single vote system -->
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = 'secondary';
                                                $statusIcon = 'circle';
                                                if ($event['status'] === 'active') {
                                                    $statusClass = 'success';
                                                    $statusIcon = 'play-circle';
                                                } elseif ($event['status'] === 'draft') {
                                                    $statusClass = 'warning';
                                                    $statusIcon = 'edit';
                                                } elseif ($event['status'] === 'ended') {
                                                    $statusClass = 'info';
                                                    $statusIcon = 'check-circle';
                                                }
                                                ?>
                                                <div>
                                                    <span class="badge badge-<?= $statusClass ?>" style="margin-bottom: 0.25rem;">
                                                        <i class="fas fa-<?= $statusIcon ?>"></i> <?= ucfirst($event['status']) ?>
                                                    </span>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                        Created: <?= date('M j, Y', strtotime($event['created_at'])) ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                    <div style="flex: 1; background: var(--bg-secondary); border-radius: 4px; height: 6px; overflow: hidden;">
                                                        <div style="height: 100%; background: var(--success-color); width: <?= min(100, ($event['total_votes'] / max(1, $event['nominee_count']) * 10)) ?>%;"></div>
                                                    </div>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted); min-width: 40px;">
                                                        <?= $event['total_votes'] > 0 ? round(($event['total_votes'] / max(1, $event['nominee_count'])), 1) : '0' ?> avg
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                                    <button class="btn btn-primary btn-sm" title="View Event" onclick="viewEvent(<?= $event['id'] ?>)" 
                                                            style="background: var(--primary-color); color: white; border: none; padding: 0.375rem 0.5rem; border-radius: 0.25rem;">
                                                        <i class="fas fa-eye" style="font-size: 0.875rem;"></i>
                                                    </button>
                                                    <button class="btn btn-success btn-sm" title="Edit Event" onclick="window.location.href='edit-event.php?id=<?= $event['id'] ?>'"
                                                            style="background: var(--success-color); color: white; border: none; padding: 0.375rem 0.5rem; border-radius: 0.25rem;">
                                                        <i class="fas fa-edit" style="font-size: 0.875rem;"></i>
                                                    </button>
                                                    <button class="btn btn-info btn-sm" title="Analytics" onclick="viewAnalytics(<?= $event['id'] ?>)"
                                                            style="background: var(--info-color); color: white; border: none; padding: 0.375rem 0.5rem; border-radius: 0.25rem;">
                                                        <i class="fas fa-chart-bar" style="font-size: 0.875rem;"></i>
                                                    </button>
                                                    <div class="dropdown" style="position: relative; display: inline-block;">
                                                        <button class="btn btn-secondary btn-sm dropdown-toggle" title="More Options" onclick="toggleDropdown(<?= $event['id'] ?>)"
                                                                style="background: var(--text-secondary); color: white; border: none; padding: 0.375rem 0.5rem; border-radius: 0.25rem;">
                                                            <i class="fas fa-ellipsis-v" style="font-size: 0.875rem;"></i>
                                                        </button>
                                                        <div class="dropdown-menu" id="dropdown-<?= $event['id'] ?>" style="display: none; position: absolute; right: 0; top: 100%; z-index: 1000; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 0.375rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); min-width: 160px; padding: 0.5rem 0;">
                                                            <a href="#" class="dropdown-item" onclick="duplicateEvent(<?= $event['id'] ?>)" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: var(--text-primary); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='var(--bg-secondary)'" onmouseout="this.style.backgroundColor='transparent'">
                                                                <i class="fas fa-copy" style="width: 16px; margin-right: 0.5rem;"></i> Duplicate
                                                            </a>
                                                            <a href="#" class="dropdown-item" onclick="exportEvent(<?= $event['id'] ?>)" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: var(--text-primary); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='var(--bg-secondary)'" onmouseout="this.style.backgroundColor='transparent'">
                                                                <i class="fas fa-download" style="width: 16px; margin-right: 0.5rem;"></i> Export
                                                            </a>
                                                            <div class="dropdown-divider" style="border-top: 1px solid var(--border-color); margin: 0.5rem 0;"></div>
                                                            <button type="button" class="dropdown-item text-danger delete-event-btn" data-event-id="<?= $event['id'] ?>" style="display: block; padding: 0.5rem 1rem; text-decoration: none; color: var(--danger-color); transition: background-color 0.2s; background: none; border: none; width: 100%; text-align: left; cursor: pointer;" onmouseover="this.style.backgroundColor='rgba(220, 53, 69, 0.1)'" onmouseout="this.style.backgroundColor='transparent'">
                                                                <i class="fas fa-trash" style="width: 16px; margin-right: 0.5rem;"></i> Delete
                                                            </button>
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
    <script>
        // Event Management Action Functions
        function viewEvent(eventId) {
            // Show event details in a modal instead of redirecting
            fetch(`actions/get-event-details.php?id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showEventDetailsModal(data.event);
                    } else {
                        alert('Error loading event details: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading event details');
                });
        }
        
        function editEvent(eventId) {
            // Redirect to comprehensive edit event page
            window.location.href = `edit-event.php?id=${eventId}`;
        }
        
        function viewAnalytics(eventId) {
            // Show analytics in a modal instead of redirecting
            fetch(`actions/get-event-analytics.php?id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAnalyticsModal(data.analytics);
                    } else {
                        alert('Error loading analytics: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading analytics');
                });
        }
        
        function duplicateEvent(eventId) {
            if (confirm('Are you sure you want to duplicate this event?')) {
                fetch('actions/duplicate-event.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ event_id: eventId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Event duplicated successfully!');
                        location.reload();
                    } else {
                        alert('Error duplicating event: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error duplicating event');
                });
            }
        }
        
        function exportEvent(eventId) {
            window.open(`actions/export-event.php?id=${eventId}`, '_blank');
        }
        
        function deleteEvent(eventId) {
            if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('event_id', eventId);
                
                fetch('actions/delete-event.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Event deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error deleting event: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting event');
                });
            }
        }
        
        function toggleDropdown(eventId) {
            const dropdown = document.getElementById(`dropdown-${eventId}`);
            const isVisible = dropdown.style.display === 'block';
            
            // Hide all dropdowns first
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
            });
            
            // Toggle current dropdown
            dropdown.style.display = isVisible ? 'none' : 'block';
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
            }
        });
        
        // Event listeners for delete buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to all delete buttons
            document.addEventListener('click', function(event) {
                if (event.target.closest('.delete-event-btn')) {
                    event.preventDefault();
                    const button = event.target.closest('.delete-event-btn');
                    const eventId = button.getAttribute('data-event-id');
                    
                    if (eventId && confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                        // Show loading state
                        const originalContent = button.innerHTML;
                        button.innerHTML = '<i class="fas fa-spinner fa-spin" style="width: 16px; margin-right: 0.5rem;"></i> Deleting...';
                        button.disabled = true;
                        
                        const formData = new FormData();
                        formData.append('event_id', eventId);
                        
                        fetch('actions/delete-event.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Event deleted successfully!');
                                location.reload();
                            } else {
                                alert('Error deleting event: ' + data.message);
                                button.innerHTML = originalContent;
                                button.disabled = false;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error deleting event. Please try again.');
                            button.innerHTML = originalContent;
                            button.disabled = false;
                        });
                    }
                }
            });
        });
        
        // Modal functions for event details, edit, and analytics
        function showEventDetailsModal(event) {
            const modalHtml = `
                <div id="eventDetailsModal" class="modal" style="display: block; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background-color: var(--bg-primary); margin: 5% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 600px; color: var(--text-primary);">
                        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
                            <h3><i class="fas fa-eye"></i> Event Details</h3>
                            <span class="close" onclick="closeModal('eventDetailsModal')" style="font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                        </div>
                        <div class="modal-body">
                            <div style="display: grid; gap: 15px;">
                                <div><strong>Title:</strong> ${event.title}</div>
                                <div><strong>Description:</strong> ${event.description}</div>
                                <div><strong>Status:</strong> <span class="badge badge-${event.status === 'active' ? 'success' : event.status === 'pending' ? 'warning' : 'secondary'}">${event.status.charAt(0).toUpperCase() + event.status.slice(1)}</span></div>
                                <div><strong>Organizer:</strong> ${event.organizer_name || 'N/A'}</div>
                                <div><strong>Start Date:</strong> ${event.start_date || 'Not set'}</div>
                                <div><strong>End Date:</strong> ${event.end_date || 'Not set'}</div>
                                <div><strong>Created:</strong> ${new Date(event.created_at).toLocaleDateString()}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }
        
        function showEditEventModal(event) {
            const modalHtml = `
                <div id="editEventModal" class="modal" style="display: block; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background-color: var(--bg-primary); margin: 5% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 600px; color: var(--text-primary);">
                        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
                            <h3><i class="fas fa-edit"></i> Edit Event</h3>
                            <span class="close" onclick="closeModal('editEventModal')" style="font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form id="editEventForm">
                                <input type="hidden" name="event_id" value="${event.id}">
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label>Title:</label>
                                    <input type="text" name="title" value="${event.title}" class="form-control" required>
                                </div>
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label>Description:</label>
                                    <textarea name="description" class="form-control" rows="3">${event.description}</textarea>
                                </div>
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label>Status:</label>
                                    <select name="status" class="form-control">
                                        <option value="active" ${event.status === 'active' ? 'selected' : ''}>Active</option>
                                        <option value="upcoming" ${event.status === 'upcoming' ? 'selected' : ''}>Upcoming</option>
                                        <option value="completed" ${event.status === 'completed' ? 'selected' : ''}>Completed</option>
                                        <option value="draft" ${event.status === 'draft' ? 'selected' : ''}>Draft</option>
                                    </select>
                                </div>
                                <div style="text-align: right; margin-top: 20px;">
                                    <button type="button" onclick="closeModal('editEventModal')" class="btn btn-secondary" style="margin-right: 10px;">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Update Event</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Handle form submission
            document.getElementById('editEventForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch('actions/update-event.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Event updated successfully!');
                        closeModal('editEventModal');
                        location.reload();
                    } else {
                        alert('Error updating event: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating event');
                });
            });
        }
        
        function showAnalyticsModal(analytics) {
            const modalHtml = `
                <div id="analyticsModal" class="modal" style="display: block; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background-color: var(--bg-primary); margin: 5% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 700px; color: var(--text-primary);">
                        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px;">
                            <h3><i class="fas fa-chart-bar"></i> Event Analytics</h3>
                            <span class="close" onclick="closeModal('analyticsModal')" style="font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                        </div>
                        <div class="modal-body">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                                <div class="stat-card" style="background: var(--bg-secondary); padding: 20px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 2rem; color: var(--primary-color); margin-bottom: 10px;">
                                        <i class="fas fa-vote-yea"></i>
                                    </div>
                                    <div style="font-size: 1.5rem; font-weight: 600; margin-bottom: 5px;">${analytics.total_votes || 0}</div>
                                    <div style="color: var(--text-muted);">Total Votes</div>
                                </div>
                                <div class="stat-card" style="background: var(--bg-secondary); padding: 20px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 2rem; color: var(--success-color); margin-bottom: 10px;">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div style="font-size: 1.5rem; font-weight: 600; margin-bottom: 5px;">${analytics.total_nominees || 0}</div>
                                    <div style="color: var(--text-muted);">Nominees</div>
                                </div>
                                <div class="stat-card" style="background: var(--bg-secondary); padding: 20px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 2rem; color: var(--warning-color); margin-bottom: 10px;">
                                        <i class="fas fa-tags"></i>
                                    </div>
                                    <div style="font-size: 1.5rem; font-weight: 600; margin-bottom: 5px;">${analytics.total_categories || 0}</div>
                                    <div style="color: var(--text-muted);">Categories</div>
                                </div>
                                <div class="stat-card" style="background: var(--bg-secondary); padding: 20px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 2rem; color: var(--info-color); margin-bottom: 10px;">
                                        <i class="fas fa-dollar-sign"></i>
                                    </div>
                                    <div style="font-size: 1.5rem; font-weight: 600; margin-bottom: 5px;">$${analytics.total_revenue || 0}</div>
                                    <div style="color: var(--text-muted);">Revenue</div>
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
        
        // Quick action buttons functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Create Event button
            const createBtn = document.querySelector('.btn-primary');
            if (createBtn && createBtn.textContent.trim() === 'Create') {
                createBtn.addEventListener('click', function() {
                    window.location.href = 'create-event.php';
                });
            }
            
            // Review/Approve Events button
            const reviewBtn = document.querySelector('.btn-success');
            if (reviewBtn && reviewBtn.textContent.trim() === 'Review') {
                reviewBtn.addEventListener('click', function() {
                    window.location.href = 'event-approvals.php';
                });
            }
            
            // Analytics button
            const analyticsBtn = document.querySelector('.btn-warning');
            if (analyticsBtn && analyticsBtn.textContent.trim() === 'View') {
                analyticsBtn.addEventListener('click', function() {
                    window.location.href = 'analytics.php';
                });
            }
            
            // Export button
            const exportBtn = document.querySelector('.btn-info');
            if (exportBtn && exportBtn.textContent.trim() === 'Export') {
                exportBtn.addEventListener('click', function() {
                    window.location.href = 'export-reports.php';
                });
            }
        });
    </script>
</body>
</html>
