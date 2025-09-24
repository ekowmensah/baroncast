<?php
require_once '../../config/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "Creating missing tables...\n";
    
    // Create event_categories table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS event_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            category_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_event_category (event_id, category_id)
        )
    ");
    echo "✓ event_categories table created/verified\n";
    
    // Create event_packages table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS event_packages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            package_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_event_package (event_id, package_id)
        )
    ");
    echo "✓ event_packages table created/verified\n";
    
    // Create system_logs table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ system_logs table created/verified\n";
    
    echo "\nAll tables created successfully!\n";
    echo "You can now use the Edit Event form to add categories and packages.\n";
    
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage() . "\n";
}
?>
