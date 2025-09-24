<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../services/PaystackService.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireAuth(['organizer']);

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
    $amount = floatval($_POST['amount'] ?? 0);
    $withdrawalMethod = $_POST['withdrawal_method'] ?? '';
    $accountNumber = $_POST['account_number'] ?? '';
    $accountName = $_POST['account_name'] ?? '';
    $bankName = $_POST['bank_name'] ?? '';
    $bankCode = $_POST['bank_code'] ?? '';
    $mobileNetwork = $_POST['mobile_network'] ?? '';
    
    // Validation
    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal amount']);
        exit;
    }
    
    if (!in_array($withdrawalMethod, ['mobile_money', 'bank'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal method']);
        exit;
    }
    
    if (empty($accountNumber) || empty($accountName)) {
        echo json_encode(['success' => false, 'message' => 'Account details are required']);
        exit;
    }
    
    // Get minimum withdrawal amount from settings
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'minimum_withdrawal'");
    $stmt->execute();
    $minWithdrawal = floatval($stmt->fetchColumn() ?: 10);
    
    if ($amount < $minWithdrawal) {
        echo json_encode(['success' => false, 'message' => "Minimum withdrawal amount is GH₵{$minWithdrawal}"]);
        exit;
    }
    
    // Calculate available balance
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'commission_rate'");
    $stmt->execute();
    $commissionRate = floatval($stmt->fetchColumn() ?: 10);
    
    // Get total earnings
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total_earnings
        FROM transactions 
        WHERE organizer_id = ? AND type = 'vote_payment' AND status = 'completed'
    ");
    $stmt->execute([$user['id']]);
    $totalEarnings = floatval($stmt->fetchColumn() ?: 0);
    
    // Get total withdrawn
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total_withdrawn
        FROM withdrawal_requests 
        WHERE organizer_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user['id']]);
    $totalWithdrawn = floatval($stmt->fetchColumn() ?: 0);
    
    // Calculate available balance
    $commission = ($totalEarnings * $commissionRate) / 100;
    $netEarnings = $totalEarnings - $commission;
    $availableBalance = $netEarnings - $totalWithdrawn;
    
    if ($amount > $availableBalance) {
        echo json_encode(['success' => false, 'message' => 'Insufficient balance for withdrawal']);
        exit;
    }
    
    // Insert withdrawal request
    $stmt = $pdo->prepare("
        INSERT INTO withdrawal_requests (
            organizer_id, amount, withdrawal_method, account_number, account_name,
            bank_code, bank_name, mobile_network, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->execute([
        $user['id'],
        $amount,
        $withdrawalMethod,
        $accountNumber,
        $accountName,
        $bankCode ?: null,
        $bankName ?: null,
        $mobileNetwork ?: null
    ]);
    
    $withdrawalId = $pdo->lastInsertId();
    
    // Send notification to admin (SMS and email)
    try {
        // Get admin users
        $adminStmt = $pdo->prepare("SELECT email, phone FROM users WHERE role = 'admin' AND status = 'active'");
        $adminStmt->execute();
        $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $arkesel = new PaystackService();
        $message = "New withdrawal request from {$user['full_name']}: GH₵{$amount} via {$withdrawalMethod}. Login to admin dashboard to review.";
        
        foreach ($admins as $admin) {
            // Send SMS notification
            if (!empty($admin['phone'])) {
                $arkesel->sendSMS($admin['phone'], $message);
            }
            
            // Send email notification (if email service is configured)
            if (!empty($admin['email'])) {
                // Email implementation would go here
                // mail($admin['email'], 'New Withdrawal Request', $message);
            }
        }
        
    } catch (Exception $e) {
        error_log("Failed to send withdrawal notification: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Withdrawal request submitted successfully',
        'withdrawal_id' => $withdrawalId
    ]);
    
} catch (PDOException $e) {
    error_log("Withdrawal submission error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Withdrawal submission error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request']);
}
?>
