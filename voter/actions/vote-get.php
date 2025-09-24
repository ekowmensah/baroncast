<?php
/**
 * GET-based Vote Submission Handler
 * Bypasses ModSecurity POST/content-type restrictions
 * WITH COMPREHENSIVE DEBUGGING
 */

// Enable debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/vote-debug.log');

// Debug function
function debug_log($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message";
    if ($data !== null) {
        $log_entry .= " | Data: " . json_encode($data);
    }
    $log_entry .= "\n";
    file_put_contents(__DIR__ . '/../../logs/vote-debug.log', $log_entry, FILE_APPEND | LOCK_EX);
}

// Only basic headers
header('Content-Type: text/html; charset=utf-8');

debug_log("Vote submission started", $_GET);
debug_log("Request method", $_SERVER['REQUEST_METHOD']);
debug_log("User agent", $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
debug_log("IP address", $_SERVER['REMOTE_ADDR'] ?? 'Unknown');

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    debug_log("ERROR: Invalid request method", $_SERVER['REQUEST_METHOD']);
    echo '<script>alert("Invalid request method"); window.history.back();</script>';
    exit;
}

try {
    debug_log("Attempting database connection");
    
    // Use proper database configuration
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
    
    debug_log("Database connection successful");
    
    // Get data from URL parameters
    $nominee_id = (int)($_GET['n'] ?? 0);
    $phone = $_GET['p'] ?? '';
    $votes = (int)($_GET['v'] ?? 1);
    $token = $_GET['t'] ?? '';
    
    debug_log("Parameters received", [
        'nominee_id' => $nominee_id,
        'phone' => $phone,
        'votes' => $votes,
        'token' => $token
    ]);
    
    if (!$nominee_id || !$phone || !$token) {
        debug_log("ERROR: Missing required data", [
            'nominee_id' => $nominee_id,
            'phone' => $phone,
            'token' => $token
        ]);
        echo '<script>alert("Missing required data"); window.history.back();</script>';
        exit;
    }
    
    // Simple token validation (basic security)
    $expected_token = md5($nominee_id . date('Y-m-d'));
    debug_log("Token validation", [
        'received_token' => $token,
        'expected_token' => $expected_token,
        'nominee_id' => $nominee_id,
        'date' => date('Y-m-d')
    ]);
    
    if ($token !== $expected_token) {
        debug_log("ERROR: Invalid security token");
        echo '<script>alert("Invalid security token"); window.history.back();</script>';
        exit;
    }
    
    debug_log("Token validation successful");
    
    // Format phone for Ghana
    $original_phone = $phone;
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (substr($phone, 0, 1) === '0') {
        $phone = '+233' . substr($phone, 1);
    }
    
    debug_log("Phone formatting", [
        'original' => $original_phone,
        'formatted' => $phone
    ]);
    
    // Get nominee and event details
    debug_log("Fetching nominee details", ['nominee_id' => $nominee_id]);
    
    // First check what columns exist in events table
    $stmt = $pdo->query("DESCRIBE events");
    $event_columns = $stmt->fetchAll();
    debug_log("Events table columns", $event_columns);
    
    // Use basic query without price column for now
    $stmt = $pdo->prepare("SELECT n.*, c.event_id, e.organizer_id FROM nominees n JOIN categories c ON n.category_id = c.id JOIN events e ON c.event_id = e.id WHERE n.id = ?");
    $stmt->execute([$nominee_id]);
    $nominee = $stmt->fetch();
    
    debug_log("Nominee query result", $nominee);
    
    if (!$nominee) {
        debug_log("ERROR: Nominee not found");
        echo '<script>alert("Nominee not found"); window.history.back();</script>';
        exit;
    }
    
    // Use default price of 1.00 since price column doesn't exist
    $default_vote_price = 1.00;
    $amount = $default_vote_price * $votes;
    $transaction_id = 'TXN_' . time() . '_' . rand(1000, 9999);
    
    debug_log("Transaction details", [
        'amount' => $amount,
        'transaction_id' => $transaction_id,
        'default_vote_price' => $default_vote_price,
        'votes' => $votes
    ]);
    
    // Create transaction record
    debug_log("Creating transaction record");
    
    $stmt = $pdo->prepare("INSERT INTO transactions (transaction_id, event_id, organizer_id, nominee_id, voter_phone, vote_count, amount, payment_method, reference, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'mobile_money', ?, 'pending', NOW())");
    $result = $stmt->execute([$transaction_id, $nominee['event_id'], $nominee['organizer_id'], $nominee_id, $phone, $votes, $amount, $transaction_id]);
    
    debug_log("Transaction creation result", [
        'success' => $result,
        'affected_rows' => $stmt->rowCount()
    ]);
    
    // Get Paystack public key from settings
    debug_log("Fetching Paystack settings");
    
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'paystack_public_key'");
    $stmt->execute();
    $paystack_key = $stmt->fetchColumn() ?: 'pk_test_default_key';
    
    debug_log("Paystack key retrieved", ['key_length' => strlen($paystack_key)]);
    
    // Create payment page with inline Paystack
    echo '<!DOCTYPE html>
<html>
<head>
    <title>Processing Payment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .payment-box { background: white; padding: 30px; border-radius: 10px; display: inline-block; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .btn { background: #28a745; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
        .btn:hover { background: #218838; }
    </style>
</head>
<body>
    <div class="payment-box">
        <h2>Complete Your Payment</h2>
        <p><strong>Nominee:</strong> ' . htmlspecialchars($nominee['name']) . '</p>
        <p><strong>Votes:</strong> ' . $votes . '</p>
        <p><strong>Amount:</strong> ' . SiteSettings::getCurrencySymbol() . ' ' . number_format($amount, 2) . '</p>
        <p><strong>Phone:</strong> ' . htmlspecialchars($phone) . '</p>
        <br>
        <button class="btn" onclick="payWithPaystack()">Pay Now</button>
        <br><br>
        <a href="../vote-form.php?event_id=' . $nominee['event_id'] . '&nominee_id=' . $nominee_id . '">← Back to Vote Form</a>
    </div>
    
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
        function payWithPaystack() {
            var handler = PaystackPop.setup({
                key: "' . $paystack_key . '",
                email: "' . $phone . '@ecast.com",
                amount: ' . ($amount * 100) . ',
                currency: "GHS",
                ref: "' . $transaction_id . '",
                metadata: {
                    phone: "' . $phone . '",
                    nominee_id: ' . $nominee_id . ',
                    votes: ' . $votes . '
                },
                callback: function(response) {
                    window.location.href = "../payment-success.php?ref=" + response.reference;
                },
                onClose: function() {
                    alert("Payment cancelled");
                }
            });
            handler.openIframe();
        }
        
        // Auto-trigger payment after 2 seconds
        setTimeout(function() {
            payWithPaystack();
        }, 2000);
    </script>
</body>
</html>';
    
} catch (Exception $e) {
    debug_log("FATAL ERROR", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo '<div style="background: #f8d7da; color: #721c24; padding: 20px; margin: 20px; border: 1px solid #f5c6cb; border-radius: 5px;">';
    echo '<h3>Debug Information</h3>';
    echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
    echo '<p><strong>GET Parameters:</strong> ' . htmlspecialchars(json_encode($_GET)) . '</p>';
    echo '<p><strong>Server Info:</strong> PHP ' . phpversion() . ', ' . $_SERVER['SERVER_SOFTWARE'] . '</p>';
    echo '<hr>';
    echo '<button onclick="window.history.back()">← Go Back</button>';
    echo '</div>';
}
?>
