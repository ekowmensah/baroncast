<?php
/**
 * Fix Missing Event Columns Migration
 * Adds missing columns to events table to prevent PHP warnings
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
    
    $migrationsRun = [];
    
    // Check and add missing columns
    $columnsToAdd = [
        'event_type' => "VARCHAR(50) DEFAULT 'other'",
        'location' => "VARCHAR(255) NULL",
        'max_participants' => "INT NULL",
        'rules' => "TEXT NULL",
        'is_public' => "TINYINT(1) DEFAULT 1",
        'allow_multiple_votes' => "TINYINT(1) DEFAULT 0"
    ];
    
    foreach ($columnsToAdd as $columnName => $columnDefinition) {
        // Check if column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE '$columnName'");
        $columnExists = $stmt->fetch();
        
        if (!$columnExists) {
            // Add the column
            $pdo->exec("ALTER TABLE events ADD COLUMN $columnName $columnDefinition");
            $migrationsRun[] = "$columnName column added";
        }
    }
    
    // Log the migration if any changes were made
    if (!empty($migrationsRun)) {
        $stmt = $pdo->prepare("
            INSERT INTO system_logs (admin_id, action, details, created_at) 
            VALUES (?, 'DATABASE_MIGRATION', ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['admin_id'],
            "Event columns migration: " . implode(', ', $migrationsRun)
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
