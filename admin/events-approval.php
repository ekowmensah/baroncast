<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();
$success = '';
$error = '';

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = intval($_POST['event_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    
    if ($event_id > 0 && in_array($action, ['approve', 'reject'])) {
        try {
            $database = new Database();
            $pdo = $database->getConnection();
            
            $new_status = ($action === 'approve') ? 'active' : 'rejected';
            
            $stmt = $pdo->prepare("
                UPDATE events 
                SET status = ? 
                WHERE id = ? AND status IN ('pending', 'draft')
            ");
            
            $result = $stmt->execute([$new_status, $event_id]);
            
            if ($stmt->rowCount() > 0) {
                $success = $action === 'approve' ? 'Event approved successfully!' : 'Event rejected successfully!';
            } else {
                $error = 'Event not found or already processed.';
            }
            
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid request.';
    }
}

// Fetch pending events
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get pending events count (check both pending and draft status)
    $stmt = $pdo->query("SELECT COUNT(*) FROM events WHERE status IN ('pending', 'draft')");
    $pending_count = $stmt->fetchColumn();
    
    // Get approved events count
    $stmt = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'active'");
    $approved_count = $stmt->fetchColumn();
    
    // Get rejected events count
    $stmt = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'rejected'");
    $rejected_count = $stmt->fetchColumn();
    
    // Get pending events with organizer details (check both pending and draft status)
    $stmt = $pdo->query("
        SELECT e.*, u.full_name as organizer_name, u.email as organizer_email,
               u.phone as organizer_phone
        FROM events e 
        LEFT JOIN users u ON e.organizer_id = u.id 
        WHERE e.status IN ('pending', 'draft')
        ORDER BY e.created_at DESC
    ");
    $pending_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recently processed events
    $stmt = $pdo->query("
        SELECT e.*, u.full_name as organizer_name
        FROM events e 
        LEFT JOIN users u ON e.organizer_id = u.id 
        WHERE e.status IN ('active', 'rejected')
        ORDER BY e.updated_at DESC
        LIMIT 10
    ");
    $processed_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $pending_events = [];
    $processed_events = [];
    $pending_count = $approved_count = $rejected_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Approval - E-Cast Admin</title>
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
                        <i class="fas fa-users"></i>
                        <span>All Users</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="organizers.php" class="nav-link">
                        <i class="fas fa-user-tie"></i>
                        <span>Event Organizers</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="events.php" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Event Management</span>
                    </a>
                </div>
                
                <div class="nav-item">
                    <a href="events-approval.php" class="nav-link active">
                        <i class="fas fa-check-circle"></i>
                        <span>Event Approval</span>
                        <?php if ($pending_count > 0): ?>
                            <span class="nav-badge"><?= $pending_count ?></span>
                        <?php endif; ?>
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
                    <button class="sidebar-toggle" id="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title mb-0">Event Approval</h1>
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
                    <h2 class="page-title">Event Approval Management</h2>
                    <p class="page-subtitle">Review and approve or reject event submissions from organizers.</p>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <h4>Success!</h4>
                            <p><?= htmlspecialchars($success) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <h4>Error!</h4>
                            <p><?= htmlspecialchars($error) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Approval Stats -->
                <div class="stats-grid">
                    <div class="stat-card warning">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= number_format($pending_count) ?></div>
                            <div class="stat-label">Pending Approval</div>
                        </div>
                    </div>
                    
                    <div class="stat-card success">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= number_format($approved_count) ?></div>
                            <div class="stat-label">Approved Events</div>
                        </div>
                    </div>
                    
                    <div class="stat-card danger">
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= number_format($rejected_count) ?></div>
                            <div class="stat-label">Rejected Events</div>
                        </div>
                    </div>
                </div>

                <!-- Pending Events -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-clock"></i>
                            Pending Events (<?= count($pending_events) ?>)
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_events)): ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                <h3>All Caught Up!</h3>
                                <p>No events are currently pending approval.</p>
                            </div>
                        <?php else: ?>
                            <div class="events-grid">
                                <?php foreach ($pending_events as $event): ?>
                                    <div class="event-card pending">
                                        <div class="event-header">
                                            <h4 class="event-title"><?= htmlspecialchars($event['title']) ?></h4>
                                            <span class="event-status status-pending">
                                                <i class="fas fa-clock"></i>
                                                Pending
                                            </span>
                                        </div>
                                        
                                        <div class="event-details">
                                            <div class="detail-item">
                                                <i class="fas fa-user-tie"></i>
                                                <span><strong>Organizer:</strong> <?= htmlspecialchars($event['organizer_name']) ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-envelope"></i>
                                                <span><?= htmlspecialchars($event['organizer_email']) ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-calendar"></i>
                                                <span><strong>Duration:</strong> <?= date('M j, Y g:i A', strtotime($event['start_date'])) ?> - <?= date('M j, Y g:i A', strtotime($event['end_date'])) ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-dollar-sign"></i>
                                                <span><strong>Vote Cost:</strong> $<?= number_format($event['vote_cost'] ?? 0, 2) ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-eye"></i>
                                                <span><strong>Type:</strong> <?= ucfirst($event['event_type'] ?? 'standard') ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="event-description">
                                            <p><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                                        </div>
                                        
                                        <div class="event-meta">
                                            <small class="text-muted">
                                                <i class="fas fa-clock"></i>
                                                Submitted <?= date('M j, Y g:i A', strtotime($event['created_at'])) ?>
                                            </small>
                                        </div>
                                        
                                        <div class="event-actions">
                                            <button class="btn btn-success" data-event-id="<?= $event['id'] ?>" data-action="approve" data-event-title="<?= htmlspecialchars($event['title'], ENT_QUOTES) ?>" onclick="showApprovalModal(this.dataset.eventId, this.dataset.action, this.dataset.eventTitle)">
                                                <i class="fas fa-check"></i>
                                                Approve
                                            </button>
                                            <button class="btn btn-danger" data-event-id="<?= $event['id'] ?>" data-action="reject" data-event-title="<?= htmlspecialchars($event['title'], ENT_QUOTES) ?>" onclick="showApprovalModal(this.dataset.eventId, this.dataset.action, this.dataset.eventTitle)">
                                                <i class="fas fa-times"></i>
                                                Reject
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recently Processed Events -->
                <?php if (!empty($processed_events)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-history"></i>
                                Recently Processed Events
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Organizer</th>
                                            <th>Status</th>
                                            <th>Processed By</th>
                                            <th>Date</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($processed_events as $event): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($event['title']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($event['organizer_name']) ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= $event['status'] ?>">
                                                        <i class="fas fa-<?= $event['status'] === 'active' ? 'check-circle' : 'times-circle' ?>"></i>
                                                        <?= ucfirst($event['status']) ?>
                                                    </span>
                                                </td>
                                                <td><span class="text-muted">System</span></td>
                                                <td><?= date('M j, Y g:i A', strtotime($event['updated_at'])) ?></td>
                                                <td>
                                                    <span class="text-muted">-</span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="modal" style="display: none !important;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Approve Event</h3>
                <button class="modal-close" onclick="closeApprovalModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" id="approvalForm">
                <div class="modal-body">
                    <input type="hidden" id="eventId" name="event_id">
                    <input type="hidden" id="actionType" name="action">
                    
                    <p id="modalMessage">Are you sure you want to approve this event?</p>
                    
                    <div class="form-group">
                        <label for="admin_notes" class="form-label">
                            <i class="fas fa-sticky-note"></i>
                            Admin Notes (Optional)
                        </label>
                        <textarea id="admin_notes" name="admin_notes" class="form-control" rows="3" 
                                  placeholder="Add any notes or feedback for the organizer..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeApprovalModal()">Cancel</button>
                    <button type="submit" class="btn" id="confirmBtn">Approve</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="../assets/js/dashboard.js"></script>
    <style>
        /* Dark mode support for modal */
        [data-theme="dark"] #approvalModal .modal-content {
            --bg-color: #1a1d23;
            --text-color: #e9ecef;
            --border-color: #495057;
            --text-muted: #adb5bd;
            --input-bg: #2c3034;
            --primary-color: #0d6efd;
            --primary-color-alpha: rgba(13, 110, 253, 0.25);
        }
        
        /* Light mode variables (fallback) */
        [data-theme="light"] #approvalModal .modal-content,
        #approvalModal .modal-content {
            --bg-color: white;
            --text-color: #333;
            --border-color: #e9ecef;
            --text-muted: #6c757d;
            --input-bg: white;
            --primary-color: #007bff;
            --primary-color-alpha: rgba(0, 123, 255, 0.25);
        }
        
        #approvalModal {
            display: none !important;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        #approvalModal.show {
            display: flex !important;
        }
        #approvalModal .modal-content {
            background: var(--bg-color, white);
            color: var(--text-color, #333);
            border-radius: 8px;
            padding: 0;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }
        #approvalModal .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color, #e9ecef);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        #approvalModal .modal-body {
            padding: 1.5rem;
        }
        #approvalModal .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color, #e9ecef);
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }
        #approvalModal .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted, #6c757d);
        }
        #approvalModal .modal-close:hover {
            color: var(--text-color, #495057);
        }
        #approvalModal .form-group {
            margin-bottom: 1rem;
        }
        #approvalModal .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color, #333);
        }
        #approvalModal .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-color, #ced4da);
            border-radius: 4px;
            font-size: 0.875rem;
            background-color: var(--input-bg, white);
            color: var(--text-color, #333);
        }
        #approvalModal .form-control:focus {
            outline: none;
            border-color: var(--primary-color, #007bff);
            box-shadow: 0 0 0 2px var(--primary-color-alpha, rgba(0, 123, 255, 0.25));
        }
        #approvalModal .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
        }
        #approvalModal .btn-success {
            background-color: #28a745;
            color: white;
        }
        #approvalModal .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        #approvalModal .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
    </style>
    <script>
        // Ensure modal is hidden on page load and set up event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('approvalModal');
            modal.style.display = 'none';
            modal.style.setProperty('display', 'none', 'important');
            modal.classList.remove('show');
            
            // Add event listeners to all approve/reject buttons as backup
            document.querySelectorAll('[data-action="approve"], [data-action="reject"]').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const eventId = this.dataset.eventId;
                    const action = this.dataset.action;
                    const eventTitle = this.dataset.eventTitle;
                    console.log('Button clicked:', eventId, action, eventTitle); // Debug log
                    showApprovalModal(eventId, action, eventTitle);
                });
            });
        });
        function showApprovalModal(eventId, action, eventTitle) {
            console.log('showApprovalModal called with:', eventId, action, eventTitle); // Debug log
            
            const modal = document.getElementById('approvalModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const confirmBtn = document.getElementById('confirmBtn');
            const eventIdInput = document.getElementById('eventId');
            const actionInput = document.getElementById('actionType');
            const notesTextarea = document.getElementById('admin_notes');
            
            // Check if all elements exist
            if (!modal || !modalTitle || !modalMessage || !confirmBtn || !eventIdInput || !actionInput || !notesTextarea) {
                console.error('Modal elements not found:', {
                    modal: !!modal,
                    modalTitle: !!modalTitle,
                    modalMessage: !!modalMessage,
                    confirmBtn: !!confirmBtn,
                    eventIdInput: !!eventIdInput,
                    actionInput: !!actionInput,
                    notesTextarea: !!notesTextarea
                });
                return;
            }
            
            eventIdInput.value = eventId;
            actionInput.value = action;
            notesTextarea.value = '';
            
            if (action === 'approve') {
                modalTitle.textContent = 'Approve Event';
                modalMessage.textContent = `Are you sure you want to approve "${eventTitle}"? This will make the event active and visible to voters.`;
                confirmBtn.textContent = 'Approve Event';
                confirmBtn.className = 'btn btn-success';
            } else {
                modalTitle.textContent = 'Reject Event';
                modalMessage.textContent = `Are you sure you want to reject "${eventTitle}"? The organizer will be notified of the rejection.`;
                confirmBtn.textContent = 'Reject Event';
                confirmBtn.className = 'btn btn-danger';
            }
            
            modal.classList.add('show');
            modal.style.setProperty('display', 'flex', 'important');
        }
        
        function closeApprovalModal() {
            const modal = document.getElementById('approvalModal');
            modal.classList.remove('show');
            modal.style.setProperty('display', 'none', 'important');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('approvalModal');
            if (event.target === modal) {
                closeApprovalModal();
            }
        }
    </script>
</body>
</html>
