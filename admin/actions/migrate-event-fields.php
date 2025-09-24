<?php
/**
 * Database Migration: Add Missing Event Fields
 * Adds new columns to events table for comprehensive event management
 */

session_start();
require_once '../../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Check if columns already exist
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'event_type'");
    $eventTypeExists = $stmt->fetch();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'location'");
    $locationExists = $stmt->fetch();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'max_participants'");
    $maxParticipantsExists = $stmt->fetch();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'rules'");
    $rulesExists = $stmt->fetch();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'is_public'");
    $isPublicExists = $stmt->fetch();
    
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'allow_multiple_votes'");
    $allowMultipleVotesExists = $stmt->fetch();
    
    $migrationsRun = [];
    
    // Add event_type column if it doesn't exist
    if (!$eventTypeExists) {
        $pdo->exec("ALTER TABLE events ADD COLUMN event_type VARCHAR(50) DEFAULT 'other' AFTER description");
        $migrationsRun[] = 'event_type column added';
    }
    
    // Add location column if it doesn't exist
    if (!$locationExists) {
        $pdo->exec("ALTER TABLE events ADD COLUMN location VARCHAR(255) NULL AFTER end_date");
        $migrationsRun[] = 'location column added';
    }
    
    // Add max_participants column if it doesn't exist
    if (!$maxParticipantsExists) {
        $pdo->exec("ALTER TABLE events ADD COLUMN max_participants INT NULL AFTER location");
        $migrationsRun[] = 'max_participants column added';
    }
    
    // Add rules column if it doesn't exist
    if (!$rulesExists) {
        $pdo->exec("ALTER TABLE events ADD COLUMN rules TEXT NULL AFTER max_participants");
        $migrationsRun[] = 'rules column added';
    }
    
    // Add is_public column if it doesn't exist
    if (!$isPublicExists) {
        $pdo->exec("ALTER TABLE events ADD COLUMN is_public TINYINT(1) DEFAULT 1 AFTER rules");
        $migrationsRun[] = 'is_public column added';
    }
    
    // Add allow_multiple_votes column if it doesn't exist
    if (!$allowMultipleVotesExists) {
        $pdo->exec("ALTER TABLE events ADD COLUMN allow_multiple_votes TINYINT(1) DEFAULT 0 AFTER is_public");
        $migrationsRun[] = 'allow_multiple_votes column added';
    }
    
    // Log the migration
    if (!empty($migrationsRun)) {
        $stmt = $pdo->prepare("
            INSERT INTO system_logs (admin_id, action, details, created_at) 
            VALUES (?, 'DATABASE_MIGRATION', ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "Event fields migration: " . implode(', ', $migrationsRun)
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => empty($migrationsRun) ? 'All columns already exist' : 'Migration completed successfully',
        'migrations_run' => $migrationsRun,
        'total_migrations' => count($migrationsRun)
    ]);
    
} catch (Exception $e) {
    error_log("Migration error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Migration failed: ' . $e->getMessage()
    ]);
}
?>
