<?php
/**
 * Direct test of callback endpoint
 */

echo "Testing callback endpoint accessibility...\n";

// Test if the file exists
$callback_file = __DIR__ . '/webhooks/hubtel-receive-money-callback.php';
if (file_exists($callback_file)) {
    echo "✓ Callback file exists at: $callback_file\n";
} else {
    echo "✗ Callback file NOT found at: $callback_file\n";
}

// Test if logs directory exists
$logs_dir = __DIR__ . '/logs';
if (is_dir($logs_dir)) {
    echo "✓ Logs directory exists\n";
    if (is_writable($logs_dir)) {
        echo "✓ Logs directory is writable\n";
    } else {
        echo "✗ Logs directory is NOT writable\n";
    }
} else {
    echo "✗ Logs directory does NOT exist\n";
    mkdir($logs_dir, 0755, true);
    echo "✓ Created logs directory\n";
}

// Test direct file write
$test_log = $logs_dir . '/test-write.log';
if (file_put_contents($test_log, "Test write: " . date('c') . "\n")) {
    echo "✓ Can write to logs directory\n";
    unlink($test_log);
} else {
    echo "✗ Cannot write to logs directory\n";
}

// Test callback file syntax
echo "\nTesting callback file syntax...\n";
$output = [];
$return_code = 0;
exec("php -l \"$callback_file\"", $output, $return_code);

if ($return_code === 0) {
    echo "✓ Callback file syntax is valid\n";
} else {
    echo "✗ Callback file has syntax errors:\n";
    foreach ($output as $line) {
        echo "  $line\n";
    }
}

echo "\nDone.\n";
?>
