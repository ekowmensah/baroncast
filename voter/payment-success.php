<?php
/**
 * Payment Success Page
 * Displays success message after successful payment
 */

require_once __DIR__ . '/../config/database.php';

$reference = $_GET['ref'] ?? '';
$message = 'Payment completed successfully! Your vote has been confirmed.';
$vote_details = null;

if (!empty($reference)) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        // Get vote details and summary from payment reference
        $stmt = $pdo->prepare("
            SELECT v.*, n.name as nominee_name, e.title as event_title, c.description as category_name,
                   COUNT(*) as total_votes, SUM(v.amount) as total_amount
            FROM votes v
            LEFT JOIN nominees n ON v.nominee_id = n.id
            LEFT JOIN events e ON v.event_id = e.id
            LEFT JOIN categories c ON n.category_id = c.id
            WHERE v.payment_reference = ? AND v.payment_status = 'completed'
            GROUP BY v.payment_reference, v.nominee_id, v.event_id
            ORDER BY v.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$reference]);
        $vote_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        error_log("Error fetching vote details: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - E-Cast Voting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .success-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
        }
        .btn-outline-secondary {
            border-radius: 25px;
            padding: 12px 30px;
            margin-top: 10px;
        }
        .payment-summary {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }
        .payment-summary .h2 {
            font-weight: bold;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .vote-details-card {
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h2 class="text-success mb-3">Payment Successful!</h2>
        
        <p class="text-muted mb-4"><?php echo htmlspecialchars($message); ?></p>
        
        <?php if ($vote_details): ?>
        <!-- Payment Summary -->
        <div class="card payment-summary text-white mb-3">
            <div class="card-body text-center">
                <h5 class="card-title mb-3">
                    <i class="fas fa-check-circle me-2"></i>Payment Summary
                </h5>
                <div class="row">
                    <div class="col-6">
                        <div class="h2 mb-1"><?php echo $vote_details['total_votes']; ?></div>
                        <small>Vote<?php echo $vote_details['total_votes'] > 1 ? 's' : ''; ?> Cast</small>
                    </div>
                    <div class="col-6">
                        <div class="h2 mb-1">₵<?php echo number_format($vote_details['total_amount'], 2); ?></div>
                        <small>Total Paid</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Vote Details -->
        <div class="card vote-details-card bg-light mb-4">
            <div class="card-body">
                <h6 class="card-title">
                    <i class="fas fa-vote-yea me-2"></i>Vote Details
                </h6>
                <p class="mb-1"><strong>Event:</strong> <?php echo htmlspecialchars($vote_details['event_title']); ?></p>
                <p class="mb-1"><strong>Category:</strong> <?php echo htmlspecialchars($vote_details['category_name']); ?></p>
                <p class="mb-1"><strong>Nominee:</strong> <?php echo htmlspecialchars($vote_details['nominee_name']); ?></p>
                <p class="mb-1"><strong>Amount per Vote:</strong> ₵<?php echo number_format($vote_details['amount'], 2); ?></p>
                <p class="mb-1"><strong>Payment Date:</strong> <?php echo date('M j, Y g:i A', strtotime($vote_details['voted_at'])); ?></p>
                <p class="mb-0"><strong>Reference:</strong> <?php echo htmlspecialchars($reference); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="d-grid gap-2">
            <?php if ($vote_details && isset($vote_details['event_id'])): ?>
                <a href="event.php?id=<?php echo $vote_details['event_id']; ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Event
                </a>
                <a href="events.php" class="btn btn-outline-secondary">
                    <i class="fas fa-list me-2"></i>All Events
                </a>
            <?php else: ?>
                <a href="events.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Events
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
