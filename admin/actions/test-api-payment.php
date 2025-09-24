<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../services/HubtelReceiveMoneyService.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !hasRole('admin')) {
    echo "ERROR: Unauthorized access";
    exit;
}

header('Content-Type: text/plain');

try {
    $phone = $_POST['phone'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $description = $_POST['description'] ?? '';
    
    if (empty($phone) || $amount <= 0 || empty($description)) {
        echo "ERROR: Missing required parameters";
        exit;
    }
    
    $hubtel = new HubtelReceiveMoneyService();
    
    // Generate test reference
    $test_reference = 'TEST_' . time() . '_' . rand(1000, 9999);
    
    echo "=== HUBTEL PAYMENT INITIATION TEST ===\n";
    echo "Phone: $phone\n";
    echo "Amount: GHS " . number_format($amount, 2) . "\n";
    echo "Description: $description\n";
    echo "Test Reference: $test_reference\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
    
    echo "Initiating payment...\n\n";
    
    $result = $hubtel->initiatePayment(
        $amount,
        $phone,
        $description,
        $test_reference,
        'Test User',
        'test@example.com'
    );
    
    echo "=== API RESPONSE ===\n";
    echo json_encode($result, JSON_PRETTY_PRINT);
    
    if ($result['success']) {
        echo "\n\n=== STATUS EXPLANATION ===\n";
        if ($result['status'] === 'completed') {
            echo "✓ Payment completed immediately\n";
        } elseif ($result['status'] === 'pending') {
            echo "⏳ Payment is pending - user will receive mobile money prompt\n";
        } else {
            echo "❓ Unknown status: " . $result['status'] . "\n";
        }
    } else {
        echo "\n\n=== ERROR ANALYSIS ===\n";
        echo "❌ Payment initiation failed\n";
        echo "Code: " . ($result['code'] ?? 'UNKNOWN') . "\n";
        echo "Message: " . ($result['message'] ?? 'No message provided') . "\n";
    }
    
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?>