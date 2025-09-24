<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();
$database = new Database();
$pdo = $database->getConnection();

// Create system_logs table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_id (admin_id),
            INDEX idx_action (action),
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
$admin_filter = $_GET['admin'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($action_filter)) {
    $where_conditions[] = "sl.action LIKE ?";
    $params[] = "%{$action_filter}%";
}

if (!empty($admin_filter)) {
    $where_conditions[] = "u.full_name LIKE ?";
    $params[] = "%{$admin_filter}%";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(sl.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(sl.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "
    SELECT COUNT(*) as total
    FROM system_logs sl
    LEFT JOIN users u ON sl.admin_id = u.id
    {$where_clause}
";

try {
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_logs = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $total_logs = 0;
}

// Get logs
$sql = "
    SELECT sl.*, u.full_name as admin_name, u.email as admin_email
    FROM system_logs sl
    LEFT JOIN users u ON sl.admin_id = u.id
    {$where_clause}
    ORDER BY sl.created_at DESC
    LIMIT {$limit} OFFSET {$offset}
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $logs = [];
}

$total_pages = ceil($total_logs / $limit);

// Get unique actions for filter dropdown
try {
    $actions_stmt = $pdo->query("SELECT DISTINCT action FROM system_logs ORDER BY action");
    $actions = $actions_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $actions = [];
}

// Get admins for filter dropdown
try {
    $admins_stmt = $pdo->query("SELECT DISTINCT u.id, u.full_name FROM system_logs sl LEFT JOIN users u ON sl.admin_id = u.id WHERE u.full_name IS NOT NULL ORDER BY u.full_name");
    $admins = $admins_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $admins = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - Admin Dashboard</title>
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
                        <h1><i class="fas fa-list-alt"></i> System Logs</h1>
                        <p>View and monitor system activities and admin actions</p>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-outline" onclick="exportLogs()">
                            <i class="fas fa-download"></i>
                            Export Logs
                        </button>
                        <button class="btn btn-danger" onclick="clearOldLogs()">
                            <i class="fas fa-trash"></i>
                            Clear Old Logs
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
                            <div class="col-md-3">
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
                            <div class="col-md-3">
                                <label for="admin">Admin</label>
                                <select name="admin" id="admin" class="form-control">
                                    <option value="">All Admins</option>
                                    <?php foreach ($admins as $admin): ?>
                                        <option value="<?= htmlspecialchars($admin['full_name']) ?>" <?= $admin_filter === $admin['full_name'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($admin['full_name']) ?>
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
                                    <a href="system-logs.php" class="btn btn-outline">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Logs Table -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-list"></i> System Logs (<?= number_format($total_logs) ?> entries)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($logs)): ?>
                            <div class="empty-state">
                                <i class="fas fa-list-alt"></i>
                                <h3>No Logs Found</h3>
                                <p>No system logs match your current filters.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>Admin</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <td>
                                                    <div class="log-time">
                                                        <?= date('M j, Y', strtotime($log['created_at'])) ?>
                                                        <small class="text-muted d-block">
                                                            <?= date('g:i A', strtotime($log['created_at'])) ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="admin-info">
                                                        <strong><?= htmlspecialchars($log['admin_name'] ?? 'Unknown') ?></strong>
                                                        <?php if ($log['admin_email']): ?>
                                                            <small class="text-muted d-block">
                                                                <?= htmlspecialchars($log['admin_email']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?= getActionBadgeClass($log['action']) ?>">
                                                        <?= htmlspecialchars($log['action']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="log-details">
                                                        <?= htmlspecialchars($log['details'] ?? 'No details') ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <code><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></code>
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

    <script src="../assets/js/dashboard.js"></script>
    <script>
        function exportLogs() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.location.href = 'actions/export-logs.php?' + params.toString();
        }

        function clearOldLogs() {
            if (confirm('Are you sure you want to clear logs older than 30 days? This action cannot be undone.')) {
                fetch('actions/clear-old-logs.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ days: 30 })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Old logs cleared successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while clearing logs.');
                });
            }
        }
    </script>
</body>
</html>

<?php
function getActionBadgeClass($action) {
    $action = strtoupper($action);
    if (strpos($action, 'CREATE') !== false || strpos($action, 'ADD') !== false) return 'success';
    if (strpos($action, 'UPDATE') !== false || strpos($action, 'EDIT') !== false) return 'warning';
    if (strpos($action, 'DELETE') !== false || strpos($action, 'REMOVE') !== false) return 'danger';
    if (strpos($action, 'LOGIN') !== false || strpos($action, 'AUTH') !== false) return 'info';
    return 'secondary';
}
?>
