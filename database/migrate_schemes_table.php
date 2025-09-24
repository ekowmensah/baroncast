<?php
require_once __DIR__ . '/../config/database.php';

echo "Starting database migration for schemes table...\n";

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Connected to database successfully.\n";
    
    // Check if schemes table already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'schemes'");
    if ($stmt->rowCount() > 0) {
        echo "Schemes table already exists, skipping creation...\n";
    } else {
        // Create schemes table
        echo "Creating schemes table...\n";
        $pdo->exec("
            CREATE TABLE schemes (
                id INT PRIMARY KEY AUTO_INCREMENT,
                event_id INT NOT NULL,
                organizer_id INT NOT NULL,
                platform_commission DECIMAL(5,2) NOT NULL DEFAULT 10.00,
                organizer_share DECIMAL(5,2) NOT NULL DEFAULT 90.00,
                vote_price DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                processing_fee DECIMAL(10,2) NOT NULL DEFAULT 0.05,
                status ENUM('active', 'draft', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_event_scheme (event_id)
            )
        ");
        echo "Schemes table created successfully.\n";
    }
    
    // Insert some sample data if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM schemes");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        echo "Adding sample scheme data...\n";
        
        // Get some events to create schemes for
        $stmt = $pdo->query("SELECT id, organizer_id FROM events LIMIT 5");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($events as $event) {
            $pdo->prepare("
                INSERT INTO schemes (event_id, organizer_id, platform_commission, organizer_share, vote_price, processing_fee, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $event['id'],
                $event['organizer_id'],
                10.00, // 10% platform commission
                90.00, // 90% organizer share
                1.00,  // $1.00 vote price
                0.05,  // $0.05 processing fee
                'active'
            ]);
        }
        echo "Sample scheme data added.\n";
    }
    
    echo "\nSchemes table migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
