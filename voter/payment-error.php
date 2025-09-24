<?php
/**
 * Payment Error Page
 * Displays error message after failed payment
 */

$error = $_GET['error'] ?? 'Payment failed. Please try again.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Error - E-Cast Voting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 1rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
        }
        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">
            <i class="fas fa-times-circle"></i>
        </div>
        
        <h2 class="text-danger mb-3">Payment Failed</h2>
        
        <p class="text-muted mb-4"><?php echo htmlspecialchars($error); ?></p>
        
        <div class="d-grid gap-2">
            <button onclick="history.back()" class="btn btn-danger">
                <i class="fas fa-redo me-2"></i>Try Again
            </button>
            <a href="../events.php" class="btn btn-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Events
            </a>
        </div>
    </div>
</body>
</html>
