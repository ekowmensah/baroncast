<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();
$database = new Database();
$pdo = $database->getConnection();

// Create support_tickets table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS support_tickets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_type ENUM('admin', 'organizer') NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
            category VARCHAR(50),
            admin_response TEXT,
            admin_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_created_at (created_at)
        )
    ");
} catch (PDOException $e) {
    // Table creation failed, continue anyway
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_ticket') {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $category = $_POST['category'] ?? '';
        
        if (!empty($subject) && !empty($message)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO support_tickets (user_id, user_type, subject, message, priority, category)
                    VALUES (?, 'admin', ?, ?, ?, ?)
                ");
                $stmt->execute([$user['id'], $subject, $message, $priority, $category]);
                $success_message = "Support ticket created successfully!";
            } catch (PDOException $e) {
                $error_message = "Error creating ticket: " . $e->getMessage();
            }
        } else {
            $error_message = "Subject and message are required.";
        }
    }
}

// Fetch support tickets
$tickets = [];
try {
    $stmt = $pdo->prepare("
        SELECT st.*, u.full_name as user_name, u.email as user_email,
               a.full_name as admin_name
        FROM support_tickets st
        LEFT JOIN users u ON st.user_id = u.id
        LEFT JOIN users a ON st.admin_id = a.id
        ORDER BY 
            CASE st.status 
                WHEN 'open' THEN 1 
                WHEN 'in_progress' THEN 2 
                WHEN 'resolved' THEN 3 
                WHEN 'closed' THEN 4 
            END,
            CASE st.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                WHEN 'low' THEN 4 
            END,
            st.created_at DESC
    ");
    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tickets = [];
}

// Get ticket statistics
$stats = [
    'total' => 0,
    'open' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'closed' => 0
];

try {
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
        FROM support_tickets
    ");
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Keep default stats
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <?php include 'includes/header.php'; ?>

            <!-- Content -->
            <div class="content">
                <div class="page-header">
                    <div class="page-title">
                        <h1><i class="fas fa-life-ring"></i> Support Center</h1>
                        <p>Manage support tickets and get help with the platform</p>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-primary" onclick="showCreateTicketModal()">
                            <i class="fas fa-plus"></i>
                            Create Ticket
                        </button>
                        <button class="btn btn-outline" onclick="showKnowledgeBase()">
                            <i class="fas fa-book"></i>
                            Knowledge Base
                        </button>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <!-- Support Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-primary">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?= number_format($stats['total']) ?></h3>
                                <p>Total Tickets</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-warning">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?= number_format($stats['open']) ?></h3>
                                <p>Open Tickets</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-info">
                                <i class="fas fa-cog"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?= number_format($stats['in_progress']) ?></h3>
                                <p>In Progress</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-success">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?= number_format($stats['resolved']) ?></h3>
                                <p>Resolved</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Help Section -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-question-circle"></i> Quick Help</h3>
                            </div>
                            <div class="card-body">
                                <div class="help-links">
                                    <a href="#" class="help-link" onclick="showHelp('getting-started')">
                                        <i class="fas fa-play-circle"></i>
                                        <div>
                                            <strong>Getting Started</strong>
                                            <p>Learn the basics of the admin dashboard</p>
                                        </div>
                                    </a>
                                    <a href="#" class="help-link" onclick="showHelp('user-management')">
                                        <i class="fas fa-users"></i>
                                        <div>
                                            <strong>User Management</strong>
                                            <p>Managing users, organizers, and permissions</p>
                                        </div>
                                    </a>
                                    <a href="#" class="help-link" onclick="showHelp('event-management')">
                                        <i class="fas fa-calendar-alt"></i>
                                        <div>
                                            <strong>Event Management</strong>
                                            <p>Creating and managing voting events</p>
                                        </div>
                                    </a>
                                    <a href="#" class="help-link" onclick="showHelp('payment-settings')">
                                        <i class="fas fa-credit-card"></i>
                                        <div>
                                            <strong>Payment Settings</strong>
                                            <p>Configuring payment gateways and settings</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3><i class="fas fa-info-circle"></i> System Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="system-info">
                                    <div class="info-item">
                                        <strong>Platform Version:</strong>
                                        <span>E-Cast v2.0.0</span>
                                    </div>
                                    <div class="info-item">
                                        <strong>PHP Version:</strong>
                                        <span><?= PHP_VERSION ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Database:</strong>
                                        <span>MySQL <?= $pdo->query('SELECT VERSION()')->fetchColumn() ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Server Time:</strong>
                                        <span><?= date('Y-m-d H:i:s T') ?></span>
                                    </div>
                                    <div class="info-item">
                                        <strong>Support Email:</strong>
                                        <span>support@e-cast.com</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Support Tickets -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Support Tickets</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tickets)): ?>
                            <div class="empty-state">
                                <i class="fas fa-life-ring"></i>
                                <h3>No Support Tickets</h3>
                                <p>No support tickets have been created yet.</p>
                                <button class="btn btn-primary" onclick="showCreateTicketModal()">
                                    <i class="fas fa-plus"></i>
                                    Create Your First Ticket
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Subject</th>
                                            <th>User</th>
                                            <th>Priority</th>
                                            <th>Status</th>
                                            <th>Category</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tickets as $ticket): ?>
                                            <tr>
                                                <td>#<?= $ticket['id'] ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($ticket['subject']) ?></strong>
                                                    <small class="text-muted d-block">
                                                        <?= htmlspecialchars(substr($ticket['message'], 0, 100)) ?>...
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="user-info">
                                                        <strong><?= htmlspecialchars($ticket['user_name'] ?? 'Unknown') ?></strong>
                                                        <small class="text-muted d-block">
                                                            <span class="badge badge-<?= $ticket['user_type'] === 'admin' ? 'danger' : 'warning' ?>">
                                                                <?= ucfirst($ticket['user_type']) ?>
                                                            </span>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?= getPriorityBadgeClass($ticket['priority']) ?>">
                                                        <?= ucfirst($ticket['priority']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?= getStatusBadgeClass($ticket['status']) ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $ticket['status'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($ticket['category'] ?: 'General') ?></td>
                                                <td><?= date('M j, Y', strtotime($ticket['created_at'])) ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline" onclick="viewTicket(<?= $ticket['id'] ?>)" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-primary" onclick="respondToTicket(<?= $ticket['id'] ?>)" title="Respond">
                                                            <i class="fas fa-reply"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-success" onclick="updateTicketStatus(<?= $ticket['id'] ?>, 'resolved')" title="Resolve">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Ticket Modal -->
    <div id="createTicketModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create Support Ticket</h3>
                <button class="modal-close" onclick="closeCreateTicketModal()">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_ticket">
                    
                    <div class="form-group">
                        <label for="subject">Subject *</label>
                        <input type="text" id="subject" name="subject" class="form-control" required placeholder="Brief description of your issue">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="priority">Priority</label>
                            <select id="priority" name="priority" class="form-control">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="category">Category</label>
                            <select id="category" name="category" class="form-control">
                                <option value="">General</option>
                                <option value="technical">Technical Issue</option>
                                <option value="account">Account Management</option>
                                <option value="payment">Payment Issue</option>
                                <option value="feature">Feature Request</option>
                                <option value="bug">Bug Report</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" name="message" class="form-control" rows="6" required placeholder="Describe your issue in detail..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeCreateTicketModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Ticket</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        function showCreateTicketModal() {
            document.getElementById('createTicketModal').style.display = 'flex';
        }

        function closeCreateTicketModal() {
            document.getElementById('createTicketModal').style.display = 'none';
        }

        function showKnowledgeBase() {
            alert('Knowledge Base feature coming soon!');
        }

        function showHelp(topic) {
            alert(`Help for ${topic} coming soon!`);
        }

        function viewTicket(ticketId) {
            alert(`View ticket #${ticketId} - Feature coming soon!`);
        }

        function respondToTicket(ticketId) {
            alert(`Respond to ticket #${ticketId} - Feature coming soon!`);
        }

        function updateTicketStatus(ticketId, status) {
            if (confirm(`Are you sure you want to mark ticket #${ticketId} as ${status}?`)) {
                // Implement status update
                alert(`Ticket #${ticketId} marked as ${status} - Feature coming soon!`);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('createTicketModal');
            if (event.target === modal) {
                closeCreateTicketModal();
            }
        }
    </script>

    <style>
        .help-links {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .help-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background-color: var(--bg-tertiary);
            border-radius: 0.5rem;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.2s ease;
        }
        
        .help-link:hover {
            background-color: var(--bg-secondary);
            transform: translateY(-2px);
        }
        
        .help-link i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        
        .help-link p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        
        .system-info {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
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
            background-color: var(--bg-primary);
            border-radius: 0.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border-color);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--bg-secondary);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            background-color: var(--bg-secondary);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-close:hover {
            color: var(--text-primary);
        }
    </style>
</body>
</html>

<?php
function getPriorityBadgeClass($priority) {
    switch ($priority) {
        case 'urgent': return 'danger';
        case 'high': return 'warning';
        case 'medium': return 'info';
        case 'low': return 'secondary';
        default: return 'secondary';
    }
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'open': return 'warning';
        case 'in_progress': return 'info';
        case 'resolved': return 'success';
        case 'closed': return 'secondary';
        default: return 'secondary';
    }
}
?>
