<?php
/**
 * System Reset Action
 * Clears all sample/test data from the system for production deployment
 */

require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Check if user is logged in and is admin
$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

header('Content-Type: application/json');

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Start transaction
    $pdo->beginTransaction();
    
    // List of tables to reset (preserve admin accounts and system settings)
    $tablesToReset = [
        'votes',
        'transactions', 
        'withdrawal_requests',
        'nominees',
        'event_categories',
        'categories',
        // 'bulk_vote_packages', // Removed - table no longer exists
        'event_schemes',
        'events',
        'organizers'
    ];
    
    $resetCount = 0;
    
    // Reset each table
    foreach ($tablesToReset as $table) {
        try {
            // Get count before deletion
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `$table`");
            $countStmt->execute();
            $count = $countStmt->fetchColumn();
            
            if ($count > 0) {
                // Delete all records from table
                $deleteStmt = $pdo->prepare("DELETE FROM `$table`");
                $deleteStmt->execute();
                
                // Reset auto-increment
                $resetStmt = $pdo->prepare("ALTER TABLE `$table` AUTO_INCREMENT = 1");
                $resetStmt->execute();
                
                $resetCount += $count;
            }
        } catch (PDOException $e) {
            // Log error but continue with other tables
            error_log("Error resetting table $table: " . $e->getMessage());
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Log the reset action
    $logStmt = $pdo->prepare("
        INSERT INTO system_logs (admin_id, action, details, created_at) 
        VALUES (?, 'SYSTEM_RESET', ?, NOW())
    ");
    $logStmt->execute([
        $_SESSION['admin_id'],
        "System data reset completed. $resetCount records removed."
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => "System reset completed successfully. $resetCount records removed.",
        'records_removed' => $resetCount
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("System reset error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to reset system data: ' . $e->getMessage()
    ]);
}
?>
