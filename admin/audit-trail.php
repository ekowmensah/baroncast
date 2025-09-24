<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();
$database = new Database();
$pdo = $database->getConnection();

// Create audit_trail table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_trail (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_type ENUM('admin', 'organizer', 'voter') NOT NULL,
            action VARCHAR(100) NOT NULL,
            table_name VARCHAR(50),
            record_id INT,
            old_values JSON,
            new_values JSON,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_table_name (table_name),
            INDEX idx_created_at (created_at)
        )
    ");
} catch (PDOException $e) {
    // Table creation failed, continue anyway
}

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$action_filter = $_GET['action'] ?? '';
$table_filter = $_GET['table'] ?? '';
$user_filter = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($action_filter)) {
    $where_conditions[] = "at.action LIKE ?";
    $params[] = "%{$action_filter}%";
}

if (!empty($table_filter)) {
    $where_conditions[] = "at.table_name = ?";
    $params[] = $table_filter;
}

if (!empty($user_filter)) {
    $where_conditions[] = "u.full_name LIKE ?";
    $params[] = "%{$user_filter}%";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(at.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(at.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "
    SELECT COUNT(*) as total
    FROM audit_trail at
    LEFT JOIN users u ON at.user_id = u.id
    {$where_clause}
";

try {
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_audits = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $total_audits = 0;
}

// Get audit records
$sql = "
    SELECT at.*, u.full_name as user_name, u.email as user_email
    FROM audit_trail at
    LEFT JOIN users u ON at.user_id = u.id
    {$where_clause}
    ORDER BY at.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $audits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $audits = [];
}

$total_pages = ceil($total_audits / $limit);

// Get unique actions for filter dropdown
try {
    $actions_stmt = $pdo->query("SELECT DISTINCT action FROM audit_trail ORDER BY action");
    $actions = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $actions = [];
}

// Get unique tables for filter dropdown
try {
    $tables_stmt = $pdo->query("SELECT DISTINCT table_name FROM audit_trail WHERE table_name IS NOT NULL ORDER BY table_name");
    $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $tables = [];
}

// Get users for filter dropdown
try {
    $users_stmt = $pdo->query("SELECT DISTINCT u.id, u.full_name FROM audit_trail at LEFT JOIN users u ON at.user_id = u.id WHERE u.full_name IS NOT NULL ORDER BY u.full_name");
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail - Admin Dashboard</title>
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
                        <h1><i class="fas fa-history"></i> Audit Trail</h1>
                        <p>Track all data changes and user activities across the platform</p>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-outline" onclick="exportAudit()">
                            <i class="fas fa-download"></i>
                            Export Audit
                        </button>
                        <button class="btn btn-info" onclick="showAuditStats()">
                            <i class="fas fa-chart-bar"></i>
                            Statistics
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3><i class="fas fa-filter"></i> Filters</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row">
                            <div class="col-md-2">
                                <label for="action">Action</label>
                                <select name="action" id="action" class="form-control">
                                    <option value="">All Actions</option>
                                    <?php foreach ($actions as $action): ?>
                                        <option value="<?= htmlspecialchars($action) ?>" <?= $action_filter === $action ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($action) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="table">Table</label>
                                <select name="table" id="table" class="form-control">
                                    <option value="">All Tables</option>
                                    <?php foreach ($tables as $table): ?>
                                        <option value="<?= htmlspecialchars($table) ?>" <?= $table_filter === $table ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($table) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="user">User</label>
                                <select name="user" id="user" class="form-control">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user_option): ?>
                                        <option value="<?= htmlspecialchars($user_option['full_name']) ?>" <?= $user_filter === $user_option['full_name'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($user_option['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from">From Date</label>
                                <input type="date" name="date_from" id="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to">To Date</label>
                                <input type="date" name="date_to" id="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                            </div>
                            <div class="col-md-2">
                                <label>&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="audit-trail.php" class="btn btn-outline">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Audit Trail Table -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> Audit Records (<?= number_format($total_audits) ?> entries)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($audits)): ?>
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <h3>No Audit Records Found</h3>
                                <p>No audit records match your current filters.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Table</th>
                                            <th>Record ID</th>
                                            <th>Changes</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($audits as $audit): ?>
                                            <tr>
                                                <td>
                                                    <div class="audit-time">
                                                        <?= date('M j, Y', strtotime($audit['created_at'])) ?>
                                                        <small class="text-muted d-block">
                                                            <?= date('g:i A', strtotime($audit['created_at'])) ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="user-info">
                                                        <strong><?= htmlspecialchars($audit['user_name'] ?? 'Unknown') ?></strong>
                                                        <small class="text-muted d-block">
                                                            <span class="badge badge-<?= $audit['user_type'] === 'admin' ? 'danger' : ($audit['user_type'] === 'organizer' ? 'warning' : 'info') ?>">
                                                                <?= ucfirst($audit['user_type']) ?>
                                                            </span>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?= getAuditActionBadgeClass($audit['action']) ?>">
                                                        <?= htmlspecialchars($audit['action']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <code><?= htmlspecialchars($audit['table_name'] ?? 'N/A') ?></code>
                                                </td>
                                                <td>
                                                    <?= $audit['record_id'] ? '#' . $audit['record_id'] : 'N/A' ?>
                                                </td>
                                                <td>
                                                    <?php if ($audit['old_values'] || $audit['new_values']): ?>
                                                        <button class="btn btn-sm btn-outline" onclick="showChanges(<?= htmlspecialchars(json_encode($audit)) ?>)">
                                                            <i class="fas fa-eye"></i> View Changes
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">No changes</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <code><?= htmlspecialchars($audit['ip_address'] ?? 'N/A') ?></code>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="pagination-wrapper">
                                    <nav>
                                        <ul class="pagination">
                                            <?php if ($page > 1): ?>
                                                <li><a href="?page=<?= $page - 1 ?>&<?= http_build_query($_GET) ?>" class="btn btn-outline btn-sm">Previous</a></li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li>
                                                    <a href="?page=<?= $i ?>&<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                                       class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline' ?> btn-sm">
                                                        <?= $i ?>
                                                    </a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $total_pages): ?>
                                                <li><a href="?page=<?= $page + 1 ?>&<?= http_build_query($_GET) ?>" class="btn btn-outline btn-sm">Next</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Changes Modal -->
    <div id="changesModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Record Changes</h3>
                <button class="modal-close" onclick="closeChangesModal()">&times;</button>
            </div>
            <div class="modal-body" id="changesContent">
                <!-- Changes will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeChangesModal()">Close</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        function exportAudit() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.location.href = 'actions/export-audit.php?' + params.toString();
        }

        function showAuditStats() {
            // Implement audit statistics modal
            alert('Audit statistics feature coming soon!');
        }

        function showChanges(audit) {
            const modal = document.getElementById('changesModal');
            const content = document.getElementById('changesContent');
            
            let html = '<div class="changes-comparison">';
            
            if (audit.old_values) {
                html += '<div class="changes-section">';
                html += '<h4><i class="fas fa-minus-circle text-danger"></i> Old Values</h4>';
                html += '<pre class="changes-json">' + JSON.stringify(JSON.parse(audit.old_values), null, 2) + '</pre>';
                html += '</div>';
            }
            
            if (audit.new_values) {
                html += '<div class="changes-section">';
                html += '<h4><i class="fas fa-plus-circle text-success"></i> New Values</h4>';
                html += '<pre class="changes-json">' + JSON.stringify(JSON.parse(audit.new_values), null, 2) + '</pre>';
                html += '</div>';
            }
            
            html += '</div>';
            content.innerHTML = html;
            modal.style.display = 'flex';
        }

        function closeChangesModal() {
            document.getElementById('changesModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('changesModal');
            if (event.target === modal) {
                closeChangesModal();
            }
        }
    </script>

    <style>
        .changes-comparison {
            display: grid;
            gap: 1rem;
        }
        
        .changes-section h4 {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .changes-json {
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            padding: 1rem;
            font-size: 0.875rem;
            overflow-x: auto;
            white-space: pre-wrap;
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
            max-width: 800px;
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
function getAuditActionBadgeClass($action) {
    $action = strtoupper($action);
    if (strpos($action, 'CREATE') !== false || strpos($action, 'INSERT') !== false) return 'success';
    if (strpos($action, 'UPDATE') !== false || strpos($action, 'MODIFY') !== false) return 'warning';
    if (strpos($action, 'DELETE') !== false || strpos($action, 'REMOVE') !== false) return 'danger';
    if (strpos($action, 'LOGIN') !== false || strpos($action, 'AUTH') !== false) return 'info';
    return 'secondary';
}
?>
