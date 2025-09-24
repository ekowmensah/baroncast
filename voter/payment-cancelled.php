<?php
require_once __DIR__ . '/../config/database.php';

$transactionRef = $_GET['ref'] ?? '';
$message = 'Payment was cancelled. You can try again or contact support if you need assistance.';

// If we have a transaction reference, update its status
if ($transactionRef) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("UPDATE transactions SET status = 'cancelled', updated_at = NOW() WHERE reference = ?");
        $stmt->execute([$transactionRef]);
    } catch (Exception $e) {
        // Log error but don't show to user
        error_log("Error updating cancelled transaction: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled - E-Cast Voting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mt-5">
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <i class="fas fa-times-circle text-warning" style="font-size: 4rem;"></i>
                        </div>
                        <h3 class="card-title text-warning">Payment Cancelled</h3>
                        <p class="card-text"><?= htmlspecialchars($message) ?></p>
                        
                        <div class="mt-4">
                            <a href="javascript:history.back()" class="btn btn-primary me-2">
                                <i class="fas fa-arrow-left"></i> Try Again
                            </a>
                            <a href="../index.php" class="btn btn-outline-secondary">
                                <i class="fas fa-home"></i> Home
                            </a>
                        </div>
                        
                        <?php if ($transactionRef): ?>
                        <div class="mt-3">
                            <small class="text-muted">Reference: <?= htmlspecialchars($transactionRef) ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
