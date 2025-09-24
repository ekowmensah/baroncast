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
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $withdrawalId = intval($input['withdrawal_id'] ?? 0);
    
    if (!$withdrawalId) {
        echo json_encode(['success' => false, 'message' => 'Invalid withdrawal ID']);
        exit;
    }
    
    // Get withdrawal details
    $stmt = $pdo->prepare("
        SELECT wr.*, u.full_name as organizer_name, u.email as organizer_email, u.phone as organizer_phone
        FROM withdrawal_requests wr
        JOIN users u ON wr.organizer_id = u.id
        WHERE wr.id = ?
    ");
    $stmt->execute([$withdrawalId]);
    $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$withdrawal) {
        echo json_encode(['success' => false, 'message' => 'Withdrawal request not found']);
        exit;
    }
    
    switch ($action) {
        case 'approve':
            // Update withdrawal status to approved
            $stmt = $pdo->prepare("
                UPDATE withdrawal_requests 
                SET status = 'approved', processed_by = ?, updated_at = NOW()
                WHERE id = ? AND status = 'pending'
            ");
            $result = $stmt->execute([$user['id'], $withdrawalId]);
            
            if ($result && $stmt->rowCount() > 0) {
                // Send notification to organizer (disabled - no SMS service)
                try {
                    $message = "Your withdrawal request of GH₵{$withdrawal['amount']} has been approved. Payment will be processed shortly.";
                    error_log("Withdrawal approved notification: {$message} for phone: {$withdrawal['organizer_phone']}");
                } catch (Exception $e) {
                    error_log("Failed to log approval notification: " . $e->getMessage());
                }
                
                echo json_encode(['success' => true, 'message' => 'Withdrawal request approved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to approve withdrawal request']);
            }
            break;
            
        case 'reject':
            $reason = $input['reason'] ?? 'No reason provided';
            
            // Update withdrawal status to rejected
            $stmt = $pdo->prepare("
                UPDATE withdrawal_requests 
                SET status = 'rejected', processed_by = ?, admin_notes = ?, updated_at = NOW()
                WHERE id = ? AND status = 'pending'
            ");
            $result = $stmt->execute([$user['id'], $reason, $withdrawalId]);
            
            if ($result && $stmt->rowCount() > 0) {
                // Send notification to organizer (disabled - no SMS service)
                try {
                    $message = "Your withdrawal request of GH₵{$withdrawal['amount']} has been rejected. Reason: {$reason}";
                    error_log("Withdrawal rejected notification: {$message} for phone: {$withdrawal['organizer_phone']}");
                } catch (Exception $e) {
                    error_log("Failed to log rejection notification: " . $e->getMessage());
                }
                
                echo json_encode(['success' => true, 'message' => 'Withdrawal request rejected successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reject withdrawal request']);
            }
            break;
            
        case 'process_payment':
            if ($withdrawal['status'] !== 'approved') {
                echo json_encode(['success' => false, 'message' => 'Withdrawal must be approved before processing payment']);
                exit;
            }
            
            try {
                // Update status to processing
                $stmt = $pdo->prepare("
                    UPDATE withdrawal_requests 
                    SET status = 'processing', updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$withdrawalId]);
                
                // Initialize JuniPay service
                $juniPay = new JuniPayService();
                
                // Prepare transfer data
                $transferData = [
                    'channel' => $withdrawal['withdrawal_method'] === 'mobile_money' ? 'mobile_money' : 'bank',
                    'amount' => $withdrawal['amount'],
                    'foreignID' => 'WD_' . $withdrawalId . '_' . time(),
                    'sender' => 'E-Cast Platform',
                    'receiver' => $withdrawal['account_name'],
                    'callbackUrl' => 'https://your-domain.com/webhooks/withdrawal-callback.php'
                ];
                
                if ($withdrawal['withdrawal_method'] === 'mobile_money') {
                    $transferData['account_number'] = $withdrawal['account_number'];
                } else {
                    $transferData['bank_code'] = $withdrawal['bank_code'];
                    $transferData['account_number'] = $withdrawal['account_number'];
                }
                
                // Process transfer via JuniPay
                $response = $juniPay->transfer($transferData);
                
                if ($response['success']) {
                    // Update withdrawal status to completed
                    $stmt = $pdo->prepare("
                        UPDATE withdrawal_requests 
                        SET status = 'completed', transaction_id = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$response['transaction_id'] ?? null, $withdrawalId]);
                    
                    // Send success notification (disabled - no SMS service)
                    try {
                        $message = "Your withdrawal of GH₵{$withdrawal['amount']} has been processed successfully. You should receive the funds shortly.";
                        error_log("Withdrawal payment notification: {$message} for phone: {$withdrawal['organizer_phone']}");
                    } catch (Exception $e) {
                        error_log("Failed to log payment notification: " . $e->getMessage());
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);
                } else {
                    // Update status back to approved on failure
                    $stmt = $pdo->prepare("
                        UPDATE withdrawal_requests 
                        SET status = 'approved', admin_notes = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute(['Payment failed: ' . ($response['message'] ?? 'Unknown error'), $withdrawalId]);
                    
                    echo json_encode(['success' => false, 'message' => 'Payment processing failed: ' . ($response['message'] ?? 'Unknown error')]);
                }
                
            } catch (Exception $e) {
                // Update status back to approved on exception
                $stmt = $pdo->prepare("
                    UPDATE withdrawal_requests 
                    SET status = 'approved', admin_notes = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute(['Payment exception: ' . $e->getMessage(), $withdrawalId]);
                
                error_log("Withdrawal payment processing error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Payment processing failed due to technical error']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Withdrawal processing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Withdrawal processing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while processing the request']);
}
?>
