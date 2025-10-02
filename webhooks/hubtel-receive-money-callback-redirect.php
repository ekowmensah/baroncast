<?php
/**
 * Redirect to correct payment callback handler
 * Place this at the URL Hubtel is trying to reach
 */

// Log the redirect attempt
error_log("Payment callback redirect accessed at " . date('Y-m-d H:i:s'));

// Include the actual callback handler
require_once __DIR__ . '/hubtel-receive-money-callback.php';
?>
