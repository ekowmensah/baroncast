<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Only allow admin access
$auth = new Auth();
$auth->requireAuth(['admin']);

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    try {
        $database = new Database();
        $pdo = $database->getConnection();
        
        $message .= "Starting withdrawal system migration...<br>";
        
        // Create withdrawal_requests table
        $sql1 = "CREATE TABLE IF NOT EXISTS withdrawal_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            organizer_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            withdrawal_method ENUM('mobile_money', 'bank') NOT NULL,
            account_number VARCHAR(50) NOT NULL,
            account_name VARCHAR(100) NOT NULL,
            bank_code VARCHAR(20) NULL,
            bank_name VARCHAR(100) NULL,
            mobile_network VARCHAR(50) NULL,
            status ENUM('pending', 'approved', 'processing', 'completed', 'rejected') DEFAULT 'pending',
            processed_by INT NULL,
            admin_notes TEXT NULL,
            transaction_id VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_organizer_id (organizer_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        )";
        
        $pdo->exec($sql1);
        $message .= "✓ Created withdrawal_requests table<br>";
        
        // Create activity_logs table
        $sql2 = "CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            description TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        )";
        
        $pdo->exec($sql2);
        $message .= "✓ Created activity_logs table<br>";
        
        // Create commission_settings table
        $sql3 = "CREATE TABLE IF NOT EXISTS commission_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            commission_rate DECIMAL(5,2) DEFAULT 10.00,
            minimum_withdrawal DECIMAL(10,2) DEFAULT 10.00,
            withdrawal_fee DECIMAL(10,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $pdo->exec($sql3);
        $message .= "✓ Created commission_settings table<br>";
        
        // Insert default commission settings
        $sql4 = "INSERT IGNORE INTO commission_settings (id, commission_rate, minimum_withdrawal, withdrawal_fee) 
                 VALUES (1, 10.00, 10.00, 0.00)";
        $pdo->exec($sql4);
        $message .= "✓ Inserted default commission settings<br>";
        
        // Insert default system settings
        $sql5 = "INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
                 ('commission_rate', '10', 'string', 'Platform commission rate percentage'),
                 ('minimum_withdrawal', '10', 'string', 'Minimum withdrawal amount in GHS'),
                 ('withdrawal_fee', '0', 'string', 'Fixed withdrawal processing fee in GHS')";
        $pdo->exec($sql5);
        $message .= "✓ Inserted default system settings<br>";
        
        // Verify tables were created
        $tables = ['withdrawal_requests', 'activity_logs', 'commission_settings'];
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            if ($stmt->rowCount() > 0) {
                $message .= "✓ Table '$table' verified<br>";
            } else {
                $message .= "✗ Table '$table' missing<br>";
            }
        }
        
        $message .= "<br><strong>Migration completed successfully!</strong><br>";
        $message .= "<a href='withdrawal.php' class='btn btn-primary'>Go to Withdrawal Management</a>";
        $success = true;
        
    } catch (Exception $e) {
        $message = "Migration failed: " . $e->getMessage();
        $success = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Withdrawal Tables - E-Cast Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="dashboard-container">
        <main class="main-content" style="margin-left: 0; width: 100%;">
            <div class="content" style="padding: 2rem;">
                <div class="card" style="max-width: 800px; margin: 0 auto;">
                    <div class="card-header">
                        <h3><i class="fas fa-database me-2"></i>Setup Withdrawal System Tables</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?>" style="margin-bottom: 1.5rem; padding: 1rem; border-radius: 0.375rem; <?= $success ? 'background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;' : 'background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;' ?>">
                                <?= $message ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$success): ?>
                            <p>This will create the necessary database tables for the withdrawal system:</p>
                            <ul>
                                <li><strong>withdrawal_requests</strong> - Stores organizer withdrawal requests</li>
                                <li><strong>activity_logs</strong> - Tracks admin actions and system events</li>
                                <li><strong>commission_settings</strong> - Stores commission configuration</li>
                            </ul>
                            
                            <form method="POST" style="margin-top: 2rem;">
                                <button type="submit" name="run_migration" class="btn btn-primary">
                                    <i class="fas fa-play me-2"></i>
                                    Run Migration
                                </button>
                                <a href="index.php" class="btn btn-outline">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Back to Dashboard
                                </a>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-right: 1rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .card {
            background-color: var(--bg-primary);
            border-radius: 0.5rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--bg-secondary);
        }
        
        .card-body {
            padding: 1.5rem;
        }
    </style>
</body>
</html>
