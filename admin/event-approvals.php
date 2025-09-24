<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();
$database = new Database();
$pdo = $database->getConnection();

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $eventId = (int)$_POST['event_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE events SET status = 'active', approved_at = NOW(), approved_by = ? WHERE id = ?");
            $stmt->execute([$user['id'], $eventId]);
            $success = "Event approved successfully!";
        } elseif ($action === 'reject') {
            $reason = $_POST['rejection_reason'] ?? 'No reason provided';
            $stmt = $pdo->prepare("UPDATE events SET status = 'rejected', rejection_reason = ?, rejected_at = NOW(), rejected_by = ? WHERE id = ?");
            $stmt->execute([$reason, $user['id'], $eventId]);
            $success = "Event rejected successfully!";
        }
    } catch (Exception $e) {
        $error = "Error processing request: " . $e->getMessage();
    }
}

// Get pending events
$stmt = $pdo->query("
    SELECT e.*, u.full_name as organizer_name, u.email as organizer_email,
           COUNT(c.id) as category_count,
           COUNT(n.id) as nominee_count
    FROM events e 
    LEFT JOIN users u ON e.organizer_id = u.id
    LEFT JOIN categories c ON e.id = c.event_id
    LEFT JOIN nominees n ON c.id = n.category_id
    WHERE e.status = 'pending'
    GROUP BY e.id
    ORDER BY e.created_at DESC
");
$pendingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Approvals - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/responsive-sidebar.css" rel="stylesheet">
</head>
<body>
    <?php 
    $pageTitle = "Event Approvals";
    include 'includes/header.php'; 
    include 'includes/sidebar.php'; 
    ?>
    <div class="main-content">
        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Event Approvals</h2>
                <a href="events.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Events
                </a>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (empty($pendingEvents)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                        <h4>No Pending Approvals</h4>
                        <p class="text-muted">All events have been reviewed.</p>
                        <a href="events.php" class="btn btn-primary">View All Events</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($pendingEvents as $event): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><?= htmlspecialchars($event['title']) ?></h5>
                                    <span class="badge bg-warning">Pending</span>
                                </div>
                                <div class="card-body">
                                    <p class="card-text"><?= htmlspecialchars($event['description']) ?></p>
                                    
                                    <div class="row mb-3">
                                        <div class="col-sm-6">
                                            <small class="text-muted">Organizer:</small><br>
                                            <strong><?= htmlspecialchars($event['organizer_name']) ?></strong>
                                        </div>
                                        <div class="col-sm-6">
                                            <small class="text-muted">Email:</small><br>
                                            <?= htmlspecialchars($event['organizer_email']) ?>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-sm-4">
                                            <small class="text-muted">Start Date:</small><br>
                                            <?= date('M j, Y', strtotime($event['start_date'])) ?>
                                        </div>
                                        <div class="col-sm-4">
                                            <small class="text-muted">End Date:</small><br>
                                            <?= date('M j, Y', strtotime($event['end_date'])) ?>
                                        </div>
                                        <div class="col-sm-4">
                                            <small class="text-muted">Categories:</small><br>
                                            <?= $event['category_count'] ?> categories
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fas fa-check me-1"></i>Approve
                                            </button>
                                        </form>
                                        
                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $event['id'] ?>">
                                            <i class="fas fa-times me-1"></i>Reject
                                        </button>
                                        
                                        <a href="event-details.php?id=<?= $event['id'] ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rejection Modal -->
                        <div class="modal fade" id="rejectModal<?= $event['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Reject Event</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <p>Are you sure you want to reject "<strong><?= htmlspecialchars($event['title']) ?></strong>"?</p>
                                            <div class="mb-3">
                                                <label for="rejection_reason<?= $event['id'] ?>" class="form-label">Reason for Rejection</label>
                                                <textarea class="form-control" name="rejection_reason" id="rejection_reason<?= $event['id'] ?>" rows="3" required></textarea>
                                            </div>
                                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger">Reject Event</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
