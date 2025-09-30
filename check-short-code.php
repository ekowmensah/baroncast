<?php
require_once 'config/database.php';
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->query('SELECT short_code FROM nominees WHERE id = 12');
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo 'Short code for nominee 12: ' . ($result['short_code'] ?? 'NULL') . PHP_EOL;
?>
