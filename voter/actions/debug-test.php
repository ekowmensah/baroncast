<?php
/**
 * Simple Debug Test Page
 * Tests basic functionality without ModSecurity interference
 */

// Enable all debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Create logs directory if it doesn't exist
$logs_dir = __DIR__ . '/../../logs';
if (!is_dir($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}

// Debug function
function debug_log($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message";
    if ($data !== null) {
        $log_entry .= " | Data: " . json_encode($data);
    }
    $log_entry .= "\n";
    file_put_contents(__DIR__ . '/../../logs/debug-test.log', $log_entry, FILE_APPEND | LOCK_EX);
}

echo '<!DOCTYPE html>
<html>
<head>
    <title>Debug Test - E-Cast Voting</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .debug-box { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .success { border-left: 5px solid #28a745; }
        .error { border-left: 5px solid #dc3545; }
        .info { border-left: 5px solid #17a2b8; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>E-Cast Voting System Debug Test</h1>';

debug_log("Debug test started");

// Test 1: Basic PHP Info
echo '<div class="debug-box info">
    <h3>1. PHP Environment</h3>
    <p><strong>PHP Version:</strong> ' . phpversion() . '</p>
    <p><strong>Server Software:</strong> ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . '</p>
    <p><strong>Request Method:</strong> ' . $_SERVER['REQUEST_METHOD'] . '</p>
    <p><strong>User Agent:</strong> ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . '</p>
    <p><strong>Remote IP:</strong> ' . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . '</p>
</div>';

// Test 2: Database Connection
echo '<div class="debug-box">';
try {
    debug_log("Testing database connection");
    
    // Use proper database configuration
    require_once __DIR__ . '/../../config/database.php';
    $database = new Database();
    $pdo = $database->getConnection();
    
    echo '<h3 class="success">2. Database Connection ✓</h3>';
    echo '<p>Successfully connected to database</p>';
    debug_log("Database connection successful");
    
    // Test basic query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM events");
    $result = $stmt->fetch();
    echo '<p><strong>Events in database:</strong> ' . $result['count'] . '</p>';
    
    // Check events table structure
    $stmt = $pdo->query("DESCRIBE events");
    $columns = $stmt->fetchAll();
    echo '<h4>Events Table Structure:</h4>';
    echo '<pre>';
    foreach ($columns as $column) {
        echo $column['Field'] . ' - ' . $column['Type'] . "\n";
    }
    echo '</pre>';
    
} catch (Exception $e) {
    echo '<h3 class="error">2. Database Connection ✗</h3>';
    echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    debug_log("Database connection failed", $e->getMessage());
}
echo '</div>';

// Test 3: File System Permissions
echo '<div class="debug-box">';
$logs_writable = is_writable($logs_dir);
if ($logs_writable) {
    echo '<h3 class="success">3. File System ✓</h3>';
    echo '<p>Logs directory is writable</p>';
    debug_log("File system test passed");
} else {
    echo '<h3 class="error">3. File System ✗</h3>';
    echo '<p>Logs directory is not writable</p>';
    debug_log("File system test failed - logs not writable");
}
echo '<p><strong>Logs Directory:</strong> ' . $logs_dir . '</p>';
echo '</div>';

// Test 4: GET Parameters
echo '<div class="debug-box info">
    <h3>4. GET Parameters</h3>
    <pre>' . htmlspecialchars(json_encode($_GET, JSON_PRETTY_PRINT)) . '</pre>
</div>';

// Test 5: Server Variables
echo '<div class="debug-box info">
    <h3>5. Important Server Variables</h3>
    <pre>';
$important_vars = ['REQUEST_METHOD', 'REQUEST_URI', 'QUERY_STRING', 'HTTP_HOST', 'HTTPS', 'REMOTE_ADDR', 'HTTP_USER_AGENT'];
foreach ($important_vars as $var) {
    echo $var . ': ' . ($_SERVER[$var] ?? 'Not set') . "\n";
}
echo '</pre>
</div>';

// Test 6: Sample Vote Submission Test
if (isset($_GET['test_vote'])) {
    echo '<div class="debug-box">';
    try {
        $nominee_id = (int)($_GET['n'] ?? 1);
        $phone = $_GET['p'] ?? '0245152060';
        $votes = (int)($_GET['v'] ?? 1);
        
        debug_log("Test vote submission", [
            'nominee_id' => $nominee_id,
            'phone' => $phone,
            'votes' => $votes
        ]);
        
        echo '<h3 class="success">6. Vote Submission Test ✓</h3>';
        echo '<p>Parameters processed successfully</p>';
        echo '<p><strong>Nominee ID:</strong> ' . $nominee_id . '</p>';
        echo '<p><strong>Phone:</strong> ' . htmlspecialchars($phone) . '</p>';
        echo '<p><strong>Votes:</strong> ' . $votes . '</p>';
        
    } catch (Exception $e) {
        echo '<h3 class="error">6. Vote Submission Test ✗</h3>';
        echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        debug_log("Vote submission test failed", $e->getMessage());
    }
    echo '</div>';
} else {
    echo '<div class="debug-box info">
        <h3>6. Vote Submission Test</h3>
        <p><a href="?test_vote=1&n=1&p=0245152060&v=1">Click here to test vote submission</a></p>
    </div>';
}

debug_log("Debug test completed");

echo '<div class="debug-box info">
    <h3>Debug Log Location</h3>
    <p><strong>Log File:</strong> /logs/debug-test.log</p>
    <p><strong>Vote Debug Log:</strong> /logs/vote-debug.log</p>
    <p>Check these files for detailed debugging information.</p>
</div>

<div class="debug-box">
    <h3>Next Steps</h3>
    <ol>
        <li>Check if all tests pass above</li>
        <li>Try the vote submission test</li>
        <li>Check the log files for detailed information</li>
        <li>If issues persist, check server error logs</li>
    </ol>
</div>

</body>
</html>';
?>
