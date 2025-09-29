<?php
/**
 * Hubtel USSD Payment Webhook Handler
 * Handles payment callbacks for USSD voting transactions
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';

// Set up logging
$debug_log = __DIR__ . '/logs/ussd-payment-webhook.log';

function log_debug($msg) {
    global $debug_log;
    if (!is_dir(dirname($debug_log))) {
        mkdir(dirname($debug_log), 0755, true);
    }
    file_put_contents($debug_log, date('c') . " $msg\n", FILE_APPEND);
}

// Log raw input for debugging
$raw_input = file_get_contents('php://input');
log_debug('USSD Payment webhook called');
log_debug('Raw input: ' . $raw_input);

// Parse and validate input
$data = json_decode($raw_input, true);
log_debug('Webhook data: ' . json_encode($data));

if (!$data) {
    log_debug('Invalid JSON data received');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

// Extract payment information from Hubtel Service Fulfillment webhook
$order_info = $data['OrderInfo'] ?? null;
if (!$order_info) {
    log_debug('Missing OrderInfo in webhook data');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid webhook format - missing OrderInfo']);
    exit;
}

$session_id = $data['SessionId'] ?? null;
$order_id = $data['OrderId'] ?? null;
$phone = $order_info['CustomerMobileNumber'] ?? null;
$status = $order_info['Status'] ?? 'pending';
$order_date = $order_info['OrderDate'] ?? date('c');
$subtotal = $order_info['Subtotal'] ?? null;

// Extract payment details
$payment_info = $order_info['Payment'] ?? null;
$amount = $payment_info['AmountAfterCharges'] ?? $payment_info['AmountPaid'] ?? $subtotal;
$is_successful = $payment_info['IsSuccessful'] ?? false;

// Extract item details for USSD reference
$items = $order_info['Items'] ?? [];
$ussd_reference = null;

if (!empty($items)) {
    foreach ($items as $item) {
        $item_name = $item['Name'] ?? '';
        // Extract USSD reference from item name
        if (preg_match('/Ref: (USSD_\d+_\d+)/', $item_name, $matches)) {
            $ussd_reference = $matches[1];
            break;
        }
    }
}

log_debug("Processed payment data - Amount: $amount, Phone: $phone, USSD Ref: $ussd_reference, Status: $status");

// Only process successful payments
if (strtolower($status) !== 'paid' || !$is_successful || !$ussd_reference) {
    log_debug("Payment not successful or no USSD reference. Status: $status, IsSuccessful: " . ($is_successful ? 'true' : 'false') . ", USSD Ref: $ussd_reference");
    echo json_encode(['status' => 'pending', 'message' => 'Payment not successful or invalid']);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Find the USSD transaction
    $stmt = $pdo->prepare("
        SELECT ut.*, n.name as nominee_name, e.title as event_title
        FROM ussd_transactions ut
        JOIN nominees n ON ut.nominee_id = n.id
        JOIN events e ON ut.event_id = e.id
        WHERE ut.transaction_ref = ?
    ");
    $stmt->execute([$ussd_reference]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        log_debug("USSD transaction not found for ref: $ussd_reference");
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Transaction not found']);
        exit;
    }
    
    log_debug("Found USSD transaction: " . json_encode($transaction));
    
    // Start database transaction
    $pdo->beginTransaction();
    
    try {
        // Update USSD transaction status
        $stmt = $pdo->prepare("
            UPDATE ussd_transactions 
            SET status = 'completed', completed_at = NOW()
            WHERE transaction_ref = ?
        ");
        $stmt->execute([$ussd_reference]);
        
        // Get organizer_id from event
        $stmt = $pdo->prepare("SELECT organizer_id FROM events WHERE id = ?");
        $stmt->execute([$transaction['event_id']]);
        $organizerId = $stmt->fetchColumn();
        
        // Create main transaction record
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                transaction_id, reference, event_id, organizer_id, nominee_id,
                voter_phone, vote_count, amount, payment_method,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'hubtel_ussd', 'completed', NOW())
        ");
        $stmt->execute([
            $ussd_reference,
            $ussd_reference,
            $transaction['event_id'],
            $organizerId,
            $transaction['nominee_id'],
            $transaction['phone_number'],
            $transaction['vote_count'],
            $transaction['amount']
        ]);
        
        $mainTransactionId = $pdo->lastInsertId();
        
        // Create individual votes
        $voteCount = (int)$transaction['vote_count'];
        $voteAmount = $transaction['amount'] / $voteCount;
        
        // Get category_id from nominee
        $stmt = $pdo->prepare("SELECT category_id FROM nominees WHERE id = ?");
        $stmt->execute([$transaction['nominee_id']]);
        $categoryId = $stmt->fetchColumn();
        
        for ($i = 0; $i < $voteCount; $i++) {
            $stmt = $pdo->prepare("
                INSERT INTO votes (
                    event_id, category_id, nominee_id, voter_phone, 
                    transaction_id, payment_method, payment_reference, 
                    payment_status, amount, voted_at, created_at
                ) VALUES (?, ?, ?, ?, ?, 'hubtel_ussd', ?, 'completed', ?, NOW(), NOW())
            ");
            $stmt->execute([
                $transaction['event_id'],
                $categoryId,
                $transaction['nominee_id'],
                $transaction['phone_number'],
                $mainTransactionId,
                $ussd_reference,
                $voteAmount
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        log_debug("SUCCESS: Recorded $voteCount votes for {$transaction['nominee_name']}");
        
        // Send callback confirmation to Hubtel (as per church system)
        $callback_payload = [
            'SessionId' => $session_id,
            'OrderId' => $order_id,
            'ServiceStatus' => 'success',
            'MetaData' => null
        ];
        
        $callback_url = 'https://gs-callback.hubtel.com:9055/callback';
        $callback_options = [
            'http' => [
                'header' => "Content-Type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($callback_payload),
                'timeout' => 10
            ]
        ];
        
        $callback_context = stream_context_create($callback_options);
        $callback_result = @file_get_contents($callback_url, false, $callback_context);
        
        if ($callback_result !== false) {
            log_debug('Hubtel gs-callback sent successfully: ' . json_encode($callback_payload));
        } else {
            log_debug('Failed to send Hubtel gs-callback: ' . json_encode($callback_payload));
        }
        
        // Respond success to Hubtel
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Payment processed and votes recorded']);
        log_debug('USSD payment webhook processed successfully');
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    log_debug('Error processing USSD payment webhook: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
?>
