<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireAuth(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user = $auth->getCurrentUser();

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Get form data
    $commissionRate = floatval($_POST['commission_rate'] ?? 0);
    $minimumWithdrawal = floatval($_POST['minimum_withdrawal'] ?? 0);
    $withdrawalFee = floatval($_POST['withdrawal_fee'] ?? 0);
    
    // Validation
    if ($commissionRate < 0 || $commissionRate > 50) {
        echo json_encode(['success' => false, 'message' => 'Commission rate must be between 0% and 50%']);
        exit;
    }
    
    if ($minimumWithdrawal < 1) {
        echo json_encode(['success' => false, 'message' => 'Minimum withdrawal must be at least GH₵1.00']);
        exit;
    }
    
    if ($withdrawalFee < 0) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal fee cannot be negative']);
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Update or insert commission rate
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_at) 
            VALUES ('commission_rate', ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        $stmt->execute([$commissionRate]);
        
        // Update or insert minimum withdrawal
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_at) 
            VALUES ('minimum_withdrawal', ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        $stmt->execute([$minimumWithdrawal]);
        
        // Update or insert withdrawal fee
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_at) 
            VALUES ('withdrawal_fee', ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        $stmt->execute([$withdrawalFee]);
        
        // Log the settings change
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description, created_at)
            VALUES (?, 'commission_settings_updated', ?, NOW())
        ");
        $description = "Updated commission settings: Rate={$commissionRate}%, Min Withdrawal=GH₵{$minimumWithdrawal}, Fee=GH₵{$withdrawalFee}";
        $stmt->execute([$user['id'], $description]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Commission settings updated successfully',
            'data' => [
                'commission_rate' => $commissionRate,
                'minimum_withdrawal' => $minimumWithdrawal,
                'withdrawal_fee' => $withdrawalFee
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Commission settings update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Commission settings update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating settings']);
}
?>
