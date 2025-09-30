<?php
/**
 * API endpoint to get pending transactions information
 */

header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    // Simple authentication check
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        throw new Exception('Unauthorized access');
    }
    
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../services/HubtelTransactionStatusService.php';
    
    $database = new Database();
    $pdo = $database->getConnection();
    $statusService = new HubtelTransactionStatusService();
    
    $action = $_GET['action'] ?? 'summary';
    
    switch ($action) {
        case 'summary':
            // Get summary of pending transactions
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_pending,
                    COUNT(CASE WHEN created_at <= NOW() - INTERVAL 5 MINUTE THEN 1 END) as ready_for_check,
                    COUNT(CASE WHEN created_at <= NOW() - INTERVAL 30 MINUTE THEN 1 END) as overdue,
                    MIN(created_at) as oldest_pending,
                    MAX(created_at) as newest_pending
                FROM transactions 
                WHERE status IN ('pending', 'processing') 
                AND payment_method IN ('mobile_money', 'hubtel_checkout', 'hubtel_ussd')
            ");
            $stmt->execute();
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get last status check run time
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'last_status_check_run'");
            $stmt->execute();
            $lastRun = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'summary' => $summary,
                'last_status_check' => $lastRun,
                'current_time' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'list':
            // Get list of pending transactions
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            $pendingTransactions = $statusService->getPendingTransactions($limit);
            
            echo json_encode([
                'success' => true,
                'transactions' => $pendingTransactions,
                'count' => count($pendingTransactions)
            ]);
            break;
            
        case 'stats':
            // Get detailed statistics
            $stmt = $pdo->prepare("
                SELECT 
                    payment_method,
                    status,
                    COUNT(*) as count,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount
                FROM transactions 
                WHERE status IN ('pending', 'processing', 'completed', 'failed') 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY payment_method, status
                ORDER BY payment_method, status
            ");
            $stmt->execute();
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
