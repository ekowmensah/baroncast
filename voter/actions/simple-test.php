<?php
header('Content-Type: application/json');

// Simple test that doesn't require database
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST method allowed']);
    exit;
}

// Check if we received the form data
$data = [
    'event_id' => $_POST['event_id'] ?? 'missing',
    'nominee_id' => $_POST['nominee_id'] ?? 'missing',
    'vote_count' => $_POST['vote_count'] ?? 'missing',
    'voter_name' => $_POST['voter_name'] ?? 'missing',
    'phone_number' => $_POST['phone_number'] ?? 'missing',
    'email' => $_POST['email'] ?? 'missing'
];

// Return success with the data we received
echo json_encode([
    'success' => true,
    'message' => 'API is working! Form data received successfully.',
    'received_data' => $data,
    'php_version' => phpversion(),
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
