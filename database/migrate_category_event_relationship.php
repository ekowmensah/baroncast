<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo "Starting category-event relationship migration...\n";
    
    // Step 1: Create junction table for category-event relationships
    echo "Creating category_events junction table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `category_events` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `category_id` int(11) NOT NULL,
            `event_id` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_category_event` (`category_id`, `event_id`),
            KEY `fk_category_events_category` (`category_id`),
            KEY `fk_category_events_event` (`event_id`),
            CONSTRAINT `fk_category_events_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_category_events_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Step 2: Migrate existing data to junction table
    echo "Migrating existing category-event relationships...\n";
    $pdo->exec("
        INSERT IGNORE INTO `category_events` (`category_id`, `event_id`)
        SELECT `id`, `event_id` FROM `categories` WHERE `event_id` IS NOT NULL
    ");
    
    // Step 3: Check if migration was successful
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM category_events");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Migrated {$result['count']} category-event relationships.\n";
    
    // Step 4: Make event_id nullable in categories table (prepare for removal)
    echo "Making event_id nullable in categories table...\n";
    $pdo->exec("ALTER TABLE `categories` MODIFY `event_id` INT NULL");
    
    echo "Migration completed successfully!\n";
    echo "Note: The event_id column in categories table is now nullable.\n";
    echo "You can manually remove it later after verifying the migration worked correctly.\n";
    
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
