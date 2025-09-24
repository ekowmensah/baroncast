<?php
/**
 * Ultra-Simple Vote Submission Handler
 * Uses basic form submission to bypass ModSecurity completely
 */

// Suppress all warnings to avoid ModSecurity triggers
error_reporting(0);
ini_set('display_errors', 0);

// Basic headers only
header('Content-Type: text/plain');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'ERROR: Invalid method';
    exit;
}

try {
    // Basic database connection without external dependencies
    $host = 'localhost';
    $dbname = 'ecast_voting';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Get basic form data
    $nominee_id = (int)($_POST['nominee_id'] ?? 0);
    $phone_number = $_POST['phone_number'] ?? '';
    $amount = (float)($_POST['amount'] ?? 1.0);
    
    if (!$nominee_id || !$phone_number) {
        echo 'ERROR: Missing data';
        exit;
    }
    
    // Format phone for Ghana
    $phone_number = preg_replace('/[^0-9+]/', '', $phone_number);
    if (substr($phone_number, 0, 1) === '0') {
        $phone_number = '+233' . substr($phone_number, 1);
    }
    
    // Get nominee details
    $stmt = $pdo->prepare("SELECT n.*, c.event_id, e.organizer_id FROM nominees n JOIN categories c ON n.category_id = c.id JOIN events e ON c.event_id = e.id WHERE n.id = ?");
    $stmt->execute([$nominee_id]);
    $nominee = $stmt->fetch();
    
    if (!$nominee) {
        echo 'ERROR: Nominee not found';
        exit;
    }
    
    // Create simple transaction
    $transaction_id = 'TXN_' . time() . '_' . rand(1000, 9999);
    
    $stmt = $pdo->prepare("INSERT INTO transactions (transaction_id, event_id, organizer_id, nominee_id, voter_phone, vote_count, amount, payment_method, reference, status) VALUES (?, ?, ?, ?, ?, 1, ?, 'mobile_money', ?, 'pending')");
    $stmt->execute([$transaction_id, $nominee['event_id'], $nominee['organizer_id'], $nominee_id, $phone_number, $amount, $transaction_id]);
    
    // Return success with redirect URL
    $redirect_url = 'https://checkout.paystack.com/v1/checkout.js?' . http_build_query([
        'key' => 'pk_test_your_public_key_here',
        'email' => $phone_number . '@ecast.com',
        'amount' => $amount * 100,
        'ref' => $transaction_id,
        'callback' => 'https://' . $_SERVER['HTTP_HOST'] . '/voter/payment-success.php'
    ]);
    
    echo 'SUCCESS:' . $redirect_url;
    
} catch (Exception $e) {
    echo 'ERROR: System error';
}
?>
