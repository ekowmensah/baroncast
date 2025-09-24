<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Setting up withdrawal system database tables...\n";
    
    // Create withdrawal_requests table
    echo "Creating withdrawal_requests table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS withdrawal_requests (
            id INT PRIMARY KEY AUTO_INCREMENT,
            organizer_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            withdrawal_method ENUM('bank', 'mobile_money') NOT NULL,
            account_number VARCHAR(50) NOT NULL,
            account_name VARCHAR(100) NOT NULL,
            bank_code VARCHAR(10) NULL,
            bank_name VARCHAR(100) NULL,
            mobile_network VARCHAR(50) NULL,
            status ENUM('pending', 'approved', 'rejected', 'completed', 'failed') DEFAULT 'pending',
            admin_notes TEXT NULL,
            processed_by INT NULL,
            processed_at TIMESTAMP NULL,
            junipay_reference VARCHAR(100) NULL,
            junipay_status VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create commission_settings table
    echo "Creating commission_settings table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS commission_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            commission_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
            minimum_withdrawal DECIMAL(10,2) NOT NULL DEFAULT 10.00,
            withdrawal_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            auto_approve_limit DECIMAL(10,2) NOT NULL DEFAULT 100.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Insert default commission settings
    echo "Inserting default commission settings...\n";
    $pdo->exec("
        INSERT IGNORE INTO commission_settings (id, commission_rate, minimum_withdrawal, withdrawal_fee, auto_approve_limit) 
        VALUES (1, 10.00, 10.00, 0.00, 100.00)
    ");
    
    // Ensure system_settings table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            value TEXT,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Add withdrawal and commission settings
    echo "Adding system settings...\n";
    $settings = [
        ['commission_rate', '10', 'Platform commission rate percentage'],
        ['minimum_withdrawal', '10.00', 'Minimum withdrawal amount'],
        ['withdrawal_fee', '0.00', 'Withdrawal processing fee'],
        ['auto_approve_limit', '100.00', 'Auto-approve withdrawals below this amount'],
        ['junipay_client_id', '', 'JuniPay Client ID for disbursements'],
        ['junipay_private_key', '', 'JuniPay Private Key for JWT token generation'],
        ['junipay_public_key', '', 'JuniPay Public Key for verification'],
        ['junipay_sandbox_mode', '1', 'JuniPay sandbox mode (1=enabled, 0=disabled)']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, value, description) VALUES (?, ?, ?)");
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
    
    echo "Withdrawal system setup completed successfully!\n";
    echo "Tables created: withdrawal_requests, commission_settings\n";
    echo "System settings added for commission and JuniPay configuration\n";
    
} catch (PDOException $e) {
    echo "Setup failed: " . $e->getMessage() . "\n";
}
?>
