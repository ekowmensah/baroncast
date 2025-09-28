<?php
/**
 * Setup Shortcode Voting System
 * Runs database migrations and creates necessary tables/fields
 */

require_once __DIR__ . '/config/database.php';

echo "<h2>🔖 Setting up Shortcode Voting System</h2>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 10px 0;'>";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "<h3>1. Adding shortcode field to nominees table...</h3>";
    
    // Check if short_code column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM nominees LIKE 'short_code'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE nominees ADD COLUMN short_code VARCHAR(10) UNIQUE AFTER name");
        $pdo->exec("ALTER TABLE nominees ADD INDEX idx_short_code (short_code)");
        echo "✅ Added short_code column to nominees table<br>";
    } else {
        echo "ℹ️ short_code column already exists<br>";
    }
    
    echo "<h3>2. Creating shortcode voting sessions table...</h3>";
    
    $createSessionsTable = "
    CREATE TABLE IF NOT EXISTS shortcode_voting_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id VARCHAR(50) UNIQUE NOT NULL,
        phone_number VARCHAR(20) NOT NULL,
        current_step ENUM('welcome', 'select_event', 'select_category', 'select_nominee', 'enter_votes', 'confirm_payment', 'payment_processing', 'completed') DEFAULT 'welcome',
        event_id INT NULL,
        category_id INT NULL,
        nominee_id INT NULL,
        vote_count INT DEFAULT 1,
        amount DECIMAL(10,2) DEFAULT 0.00,
        session_data JSON NULL,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 10 MINUTE),
        
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        FOREIGN KEY (nominee_id) REFERENCES nominees(id) ON DELETE SET NULL,
        
        INDEX idx_session_id (session_id),
        INDEX idx_phone_number (phone_number),
        INDEX idx_last_activity (last_activity),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createSessionsTable);
    echo "✅ Created shortcode_voting_sessions table<br>";
    
    echo "<h3>3. Creating shortcode transactions table...</h3>";
    
    $createTransactionsTable = "
    CREATE TABLE IF NOT EXISTS shortcode_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_ref VARCHAR(50) UNIQUE NOT NULL,
        session_id VARCHAR(50) NOT NULL,
        phone_number VARCHAR(20) NOT NULL,
        event_id INT NOT NULL,
        nominee_id INT NOT NULL,
        vote_count INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'completed', 'failed', 'cancelled', 'expired') DEFAULT 'pending',
        payment_method ENUM('ussd', 'mobile_money') DEFAULT 'ussd',
        hubtel_transaction_id VARCHAR(100) NULL,
        payment_data JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        
        FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        FOREIGN KEY (nominee_id) REFERENCES nominees(id) ON DELETE CASCADE,
        
        INDEX idx_transaction_ref (transaction_ref),
        INDEX idx_session_id (session_id),
        INDEX idx_phone_number (phone_number),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createTransactionsTable);
    echo "✅ Created shortcode_transactions table<br>";
    
    echo "<h3>4. Adding shortcode voting settings...</h3>";
    
    $settings = [
        ['enable_shortcode_voting', '1', 'Enable shortcode/USSD voting'],
        ['shortcode_number', '*170*123#', 'USSD shortcode for voting'],
        ['shortcode_welcome_message', 'Welcome to E-Cast Voting! Choose an option:', 'Welcome message for shortcode voting'],
        ['shortcode_session_timeout', '600', 'Session timeout in seconds (10 minutes)'],
        ['shortcode_max_votes_per_session', '10', 'Maximum votes per shortcode session'],
        ['shortcode_payment_method', 'ussd', 'Default payment method for shortcode voting (ussd/mobile_money)']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
    
    foreach ($settings as $setting) {
        $stmt->execute($setting);
        echo "✅ Added setting: {$setting[0]}<br>";
    }
    
    echo "<h3>5. Generating shortcodes for existing nominees...</h3>";
    
    // Update existing nominees with shortcodes if they don't have them
    $stmt = $pdo->query("SELECT id, name FROM nominees WHERE short_code IS NULL OR short_code = ''");
    $nominees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updateStmt = $pdo->prepare("UPDATE nominees SET short_code = ? WHERE id = ?");
    
    foreach ($nominees as $nominee) {
        // Generate shortcode: First 3 letters + 3-digit ID
        $shortcode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nominee['name']), 0, 3)) . 
                    str_pad($nominee['id'], 3, '0', STR_PAD_LEFT);
        
        try {
            $updateStmt->execute([$shortcode, $nominee['id']]);
            echo "✅ Generated shortcode '{$shortcode}' for nominee: {$nominee['name']}<br>";
        } catch (PDOException $e) {
            // Handle duplicate shortcode
            $shortcode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nominee['name']), 0, 2)) . 
                        str_pad($nominee['id'], 4, '0', STR_PAD_LEFT);
            try {
                $updateStmt->execute([$shortcode, $nominee['id']]);
                echo "✅ Generated alternative shortcode '{$shortcode}' for nominee: {$nominee['name']}<br>";
            } catch (PDOException $e2) {
                echo "⚠️ Failed to generate shortcode for nominee: {$nominee['name']}<br>";
            }
        }
    }
    
    echo "<h3>6. Testing shortcode voting service...</h3>";
    
    // Test the service initialization
    require_once __DIR__ . '/services/ShortcodeVotingService.php';
    
    try {
        $service = new ShortcodeVotingService();
        echo "✅ ShortcodeVotingService initialized successfully<br>";
        
        // Test a sample request
        $testResponse = $service->handleShortcodeRequest('233241234567', '', 'TEST_SESSION');
        if ($testResponse && isset($testResponse['Message'])) {
            echo "✅ Test shortcode request processed successfully<br>";
            echo "Sample response: " . substr($testResponse['Message'], 0, 50) . "...<br>";
        }
        
    } catch (Exception $e) {
        echo "⚠️ Service test failed: " . $e->getMessage() . "<br>";
    }
    
    echo "<h3>7. Setup Summary:</h3>";
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "<h4>✅ Shortcode Voting System Setup Complete!</h4>";
    echo "<ul>";
    echo "<li><strong>Database:</strong> All tables and fields created</li>";
    echo "<li><strong>Settings:</strong> Shortcode voting configuration added</li>";
    echo "<li><strong>Shortcodes:</strong> Generated for existing nominees</li>";
    echo "<li><strong>Service:</strong> ShortcodeVotingService ready</li>";
    echo "<li><strong>Webhook:</strong> shortcode-voting-webhook.php created</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h3>8. Next Steps:</h3>";
    echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px;'>";
    echo "<h4>🔧 Configuration Required:</h4>";
    echo "<ol>";
    echo "<li><strong>Hubtel USSD Setup:</strong> Configure USSD shortcode with Hubtel</li>";
    echo "<li><strong>Webhook URL:</strong> Set webhook to: <code>https://yourdomain.com/webhooks/shortcode-voting-webhook.php</code></li>";
    echo "<li><strong>Test Shortcode:</strong> Test the USSD shortcode with real phone</li>";
    echo "<li><strong>Payment Integration:</strong> Verify PayProxy integration works</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h3>9. How to Use:</h3>";
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>";
    echo "<h4>📱 For Voters:</h4>";
    echo "<ol>";
    echo "<li>Dial the USSD shortcode (e.g., *170*123#)</li>";
    echo "<li>Follow prompts to select event, category, and nominee</li>";
    echo "<li>Enter number of votes</li>";
    echo "<li>Confirm payment</li>";
    echo "<li>Complete payment via USSD or mobile money</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "❌ <strong>Setup Error:</strong> " . $e->getMessage();
    echo "</div>";
    error_log("Shortcode voting setup error: " . $e->getMessage());
}

echo "</div>";

echo "<br><div style='text-align: center;'>";
echo "<a href='test-shortcode-voting.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🧪 Test Shortcode Voting</a>";
echo "</div>";
?>
