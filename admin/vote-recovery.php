<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/HubtelReceiveMoneyService.php';

$auth = new Auth();
$auth->requireAuth(['admin']);

$user = $auth->getCurrentUser();

// Handle vote creation requests
if ($_POST && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        $hubtelService = new HubtelReceiveMoneyService();
        
        if ($_POST['action'] === 'create_missing_votes') {
            // Find and create missing votes
            $stmt = $pdo->query("
                SELECT t.id, t.reference, t.transaction_id, t.vote_count, t.nominee_id, 
                       t.voter_phone, t.amount, COUNT(v.id) as existing_votes
                FROM transactions t
                LEFT JOIN votes v ON (v.transaction_id = t.id OR v.payment_reference = t.reference)
                WHERE t.status = 'completed' 
                AND t.payment_method = 'mobile_money'
                AND t.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY t.id
                HAVING t.vote_count > existing_votes OR existing_votes = 0
            ");
            
            $missing_vote_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $fixed_count = 0;
            
            foreach ($missing_vote_transactions as $tx) {
                // Get event_id and category_id from the nominee
                $stmt = $pdo->prepare("
                    SELECT n.category_id, c.event_id 
                    FROM nominees n 
                    JOIN categories c ON n.category_id = c.id 
                    WHERE n.id = ?
                ");
                $stmt->execute([$tx['nominee_id']]);
                $nominee_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$nominee_data) {
                    continue; // Skip if no nominee data found
                }
                
                $reference = $tx['reference'] ?: $tx['transaction_id'];
                $votes_needed = $tx['vote_count'] - $tx['existing_votes'];
                $vote_amount = $tx['amount'] / $tx['vote_count'];
                
                for ($i = 0; $i < $votes_needed; $i++) {
                    $stmt = $pdo->prepare("
                        INSERT INTO votes (
                            event_id, category_id, nominee_id, voter_phone, 
                            transaction_id, payment_method, payment_reference, 
                            payment_status, amount, voted_at
                        ) VALUES (?, ?, ?, ?, ?, 'mobile_money', ?, 'completed', ?, NOW())
                    ");
                    
                    $stmt->execute([
                        $nominee_data['event_id'],
                        $nominee_data['category_id'],
                        $tx['nominee_id'],
                        $tx['voter_phone'],
                        $tx['id'],
                        $reference,
                        $vote_amount
                    ]);
                }
                $fixed_count += $votes_needed;
            }
            
            echo json_encode(['success' => true, 'message' => "Created $fixed_count missing vote records"]);
            exit;
        }
        
        if ($_POST['action'] === 'check_pending_status') {
            // Check pending transactions
            $stmt = $pdo->query("
                SELECT reference, transaction_id 
                FROM transactions 
                WHERE status = 'pending' 
                AND payment_method = 'mobile_money'
                AND created_at <= NOW() - INTERVAL 5 MINUTE
                LIMIT 10
            ");
            $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $updated_count = 0;
            foreach ($pending as $tx) {
                $reference = $tx['reference'] ?: $tx['transaction_id'];
                $status_result = $hubtelService->checkTransactionStatus($reference);
                
                if ($status_result['success'] && $status_result['is_paid']) {
                    $stmt = $pdo->prepare("UPDATE transactions SET status = 'completed', updated_at = NOW() WHERE reference = ? OR transaction_id = ?");
                    $stmt->execute([$reference, $reference]);
                    
                    $hubtelService->createVoteRecordsFromTransaction($reference);
                    $updated_count++;
                }
            }
            
            echo json_encode(['success' => true, 'message' => "Updated $updated_count pending transactions"]);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Get statistics
$database = new Database();
$pdo = $database->getConnection();

// Get problem transactions
$stmt = $pdo->query("
    SELECT t.id, t.reference, t.status, t.vote_count, t.amount, t.created_at,
           n.name as nominee_name, COUNT(v.id) as existing_votes
    FROM transactions t
    LEFT JOIN nominees n ON t.nominee_id = n.id
    LEFT JOIN votes v ON (v.transaction_id = t.id OR v.payment_reference = t.reference)
    WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY t.id
    HAVING (t.status = 'completed' AND (t.vote_count > existing_votes OR existing_votes = 0))
           OR (t.status = 'pending' AND t.created_at <= NOW() - INTERVAL 5 MINUTE)
    ORDER BY t.created_at DESC
");
$problem_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary stats
$stmt = $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending' AND payment_method = 'mobile_money' AND created_at <= NOW() - INTERVAL 5 MINUTE");
$pending_count = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT COUNT(*) FROM transactions t
    LEFT JOIN votes v ON (v.transaction_id = t.id OR v.payment_reference = t.reference)
    WHERE t.status = 'completed' AND t.payment_method = 'mobile_money' 
    AND t.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY t.id
    HAVING COUNT(v.id) = 0
");
$missing_votes_count = $stmt->rowCount();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote Recovery Tool - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="admin-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="content-header">
                <h1><i class="fas fa-tools"></i> Vote Recovery Tool</h1>
                <p>Fix missing votes and payment status issues</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-content">
                        <h3><?= $pending_count ?></h3>
                        <p>Pending Payments (>5min)</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-content">
                        <h3><?= $missing_votes_count ?></h3>
                        <p>Completed Payments Missing Votes</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-tools"></i></div>
                    <div class="stat-content">
                        <h3><?= count($problem_transactions) ?></h3>
                        <p>Total Issues Found</p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-magic"></i> Quick Fix Actions</h2>
                </div>
                <div class="card-content">
                    <div class="quick-actions">
                        <button id="createMissingVotes" class="btn btn-primary">
                            <i class="fas fa-vote-yea"></i>
                            Create Missing Votes
                        </button>
                        
                        <button id="checkPendingStatus" class="btn btn-secondary">
                            <i class="fas fa-sync"></i>
                            Check Pending Status
                        </button>
                        
                        <a href="../auto-vote-checker.php" target="_blank" class="btn btn-info">
                            <i class="fas fa-play"></i>
                            Run Full Auto-Check
                        </a>
                    </div>
                    
                    <div id="actionResult" class="action-result" style="display: none;"></div>
                </div>
            </div>
            
            <!-- Problem Transactions -->
            <?php if (!empty($problem_transactions)): ?>
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-exclamation-circle"></i> Problem Transactions (Last 24 Hours)</h2>
                </div>
                <div class="card-content">
                    <div class="table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Transaction</th>
                                    <th>Nominee</th>
                                    <th>Status</th>
                                    <th>Expected Votes</th>
                                    <th>Actual Votes</th>
                                    <th>Issue</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($problem_transactions as $tx): ?>
                                <tr>
                                    <td>
                                        <strong>#<?= $tx['id'] ?></strong><br>
                                        <small><?= htmlspecialchars($tx['reference']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($tx['nominee_name'] ?? 'Unknown') ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $tx['status'] ?>">
                                            <?= ucfirst($tx['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $tx['vote_count'] ?></td>
                                    <td><?= $tx['existing_votes'] ?></td>
                                    <td>
                                        <?php if ($tx['status'] === 'pending'): ?>
                                            <span class="text-warning">Payment stuck</span>
                                        <?php elseif ($tx['existing_votes'] == 0): ?>
                                            <span class="text-danger">No votes created</span>
                                        <?php else: ?>
                                            <span class="text-warning">Missing <?= $tx['vote_count'] - $tx['existing_votes'] ?> votes</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, H:i', strtotime($tx['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-content text-center">
                    <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success-color); margin-bottom: 1rem;"></i>
                    <h3>All Good!</h3>
                    <p>No payment or vote issues detected in the last 24 hours.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
    document.getElementById('createMissingVotes').addEventListener('click', function() {
        performAction('create_missing_votes', 'Creating missing votes...');
    });
    
    document.getElementById('checkPendingStatus').addEventListener('click', function() {
        performAction('check_pending_status', 'Checking pending transactions...');
    });
    
    function performAction(action, loadingText) {
        const resultDiv = document.getElementById('actionResult');
        resultDiv.style.display = 'block';
        resultDiv.className = 'action-result loading';
        resultDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + loadingText;
        
        const formData = new FormData();
        formData.append('action', action);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.className = 'action-result success';
                resultDiv.innerHTML = '<i class="fas fa-check"></i> ' + data.message;
                
                // Refresh page after 2 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                resultDiv.className = 'action-result error';
                resultDiv.innerHTML = '<i class="fas fa-times"></i> Error: ' + data.message;
            }
        })
        .catch(error => {
            resultDiv.className = 'action-result error';
            resultDiv.innerHTML = '<i class="fas fa-times"></i> Network error: ' + error.message;
        });
    }
    </script>
    
    <style>
    .quick-actions {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }
    
    .action-result {
        padding: 1rem;
        border-radius: 8px;
        margin-top: 1rem;
    }
    
    .action-result.loading {
        background: var(--info-bg);
        color: var(--info-color);
        border: 1px solid var(--info-color);
    }
    
    .action-result.success {
        background: var(--success-bg);
        color: var(--success-color);
        border: 1px solid var(--success-color);
    }
    
    .action-result.error {
        background: var(--danger-bg);
        color: var(--danger-color);
        border: 1px solid var(--danger-color);
    }
    
    .text-warning { color: #f39c12; }
    .text-danger { color: #e74c3c; }
    </style>
</body>
</html>