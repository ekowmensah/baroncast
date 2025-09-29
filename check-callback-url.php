<?php
require_once 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->query('SELECT setting_value FROM system_settings WHERE setting_key = "hubtel_callback_url"');
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo 'Current callback URL: ' . ($result['setting_value'] ?? 'NOT SET') . PHP_EOL;
?>
