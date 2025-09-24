<?php
require_once __DIR__ . '/includes/auth.php';

$auth = new Auth();
$result = $auth->logout();

header('Location: voter/index.php?logged_out=1');
exit();
?>
