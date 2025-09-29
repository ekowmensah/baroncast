<?php
/**
 * Migration: Create USSD Tables
 * Creates tables needed for USSD voting functionality
 */

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Creating USSD tables...\n";
    
    // Create ussd_sessions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ussd_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(100) NOT NULL,
            session_key VARCHAR(50) NOT NULL,
            session_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_session_key (session_id, session_key),
            INDEX idx_session_id (session_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created ussd_sessions table\n";
    
    // Create ussd_transactions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ussd_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_ref VARCHAR(100) NOT NULL UNIQUE,
            session_id VARCHAR(100),
            phone_number VARCHAR(20) NOT NULL,
            event_id INT NOT NULL,
            nominee_id INT NOT NULL,
            vote_count INT NOT NULL DEFAULT 1,
            amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            hubtel_transaction_id VARCHAR(100),
            completed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_transaction_ref (transaction_ref),
            INDEX idx_phone_number (phone_number),
            INDEX idx_event_id (event_id),
            INDEX idx_nominee_id (nominee_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY (nominee_id) REFERENCES nominees(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created ussd_transactions table\n";
    
    // Create ussd_webhook_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ussd_webhook_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(100),
            phone_number VARCHAR(20),
            request_data TEXT,
            response_data TEXT,
            processing_time_ms INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_id (session_id),
            INDEX idx_phone_number (phone_number),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Created ussd_webhook_logs table\n";
    
    // Add vote_cost column to events table if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'vote_cost'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE events ADD COLUMN vote_cost DECIMAL(10,2) DEFAULT 1.00 AFTER status");
        echo "✓ Added vote_cost column to events table\n";
    } else {
        echo "✓ vote_cost column already exists in events table\n";
    }
    
    echo "\n✅ All USSD tables created successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Test your USSD shortcode - dial the code and select option 1\n";
    echo "2. Make sure you have active events in your database\n";
    echo "3. Check the logs in /logs/ directory for any errors\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
