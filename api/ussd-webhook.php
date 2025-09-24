<?php
/**
 * USSD Payment Webhook Handler
 * Processes payment confirmations from Paystack for USSD-initiated transactions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/PaystackService.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get webhook payload
    $input = file_get_contents('php://input');
    $event = json_decode($input, true);
    
    if (!$event) {
        throw new Exception('Invalid webhook payload');
    }
    
    // Verify webhook signature
    $paystack = new PaystackService();
    if (!$paystack->verifyWebhookSignature($input, $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '')) {
        throw new Exception('Invalid webhook signature');
    }
    
    // Process webhook event
    $eventType = $event['event'] ?? '';
    $data = $event['data'] ?? [];
    
    $database = new Database();
    $pdo = $database->getConnection();
    
    switch ($eventType) {
        case 'charge.success':
            handleSuccessfulPayment($pdo, $data);
            break;
            
        case 'charge.failed':
            handleFailedPayment($pdo, $data);
            break;
            
        default:
            error_log("Unhandled USSD webhook event: " . $eventType);
    }
    
    // Respond to Paystack
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    error_log("USSD Webhook Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Handle successful payment
 */
function handleSuccessfulPayment($pdo, $data) {
    $reference = $data['reference'] ?? '';
    $amount = ($data['amount'] ?? 0) / 100; // Paystack amounts are in kobo
    $phoneNumber = $data['customer']['phone'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        // Update transaction status
        $stmt = $pdo->prepare("
            UPDATE transactions 
            SET status = 'completed', payment_response = ?, updated_at = NOW()
            WHERE reference = ? AND status = 'pending'
        ");
        $stmt->execute([json_encode($data), $reference]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Transaction not found: " . $reference);
        }
        
        // Get transaction details
        $stmt = $pdo->prepare("
            SELECT t.*, e.title as event_title, n.name as nominee_name
            FROM transactions t
            JOIN events e ON t.event_id = e.id
            JOIN nominees n ON t.nominee_id = n.id
            WHERE t.reference = ?
        ");
        $stmt->execute([$reference]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            throw new Exception("Transaction details not found");
        }
        
        // Create vote records
        for ($i = 0; $i < $transaction['vote_count']; $i++) {
            $stmt = $pdo->prepare("
                INSERT INTO votes (
                    nominee_id, voter_phone, payment_reference, 
                    payment_method, status, created_at
                ) VALUES (?, ?, ?, 'ussd', 'confirmed', NOW())
            ");
            $stmt->execute([
                $transaction['nominee_id'],
                $transaction['voter_phone'],
                $reference
            ]);
        }
        
        // Send confirmation SMS
        sendConfirmationSMS($phoneNumber, [
            'votes' => $transaction['vote_count'],
            'nominee' => $transaction['nominee_name'],
            'event' => $transaction['event_title'],
            'amount' => $amount,
            'reference' => $reference
        ]);
        
        $pdo->commit();
        error_log("USSD Payment completed: " . $reference);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Handle failed payment
 */
function handleFailedPayment($pdo, $data) {
    $reference = $data['reference'] ?? '';
    
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET status = 'failed', payment_response = ?, updated_at = NOW()
        WHERE reference = ? AND status = 'pending'
    ");
    $stmt->execute([json_encode($data), $reference]);
    
    error_log("USSD Payment failed: " . $reference);
}

/**
 * Send confirmation SMS
 */
function sendConfirmationSMS($phoneNumber, $details) {
    try {
        $message = "Vote confirmed! {$details['votes']} vote(s) for {$details['nominee']} in {$details['event']}. " .
                  "Amount: GH₵{$details['amount']}. Ref: {$details['reference']}. Thank you!";
        
        // Use Arkesel SMS API
        $database = new Database();
        $pdo = $database->getConnection();
        
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'arkesel_api_key'");
        $stmt->execute();
        $apiKey = $stmt->fetchColumn();
        
        if ($apiKey) {
            $smsData = [
                'sender' => 'E-Cast',
                'message' => $message,
                'recipients' => [$phoneNumber]
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://sms.arkesel.com/api/v2/sms/send');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($smsData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'api-key: ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            error_log("SMS sent to " . $phoneNumber . ": " . $response);
        }
        
    } catch (Exception $e) {
        error_log("SMS sending failed: " . $e->getMessage());
    }
}
?>
