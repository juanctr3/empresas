<?php
require_once 'db.php';
$stmt = $pdo->query("SELECT * FROM wa_chats ORDER BY id DESC LIMIT 5");
$chats = $stmt->fetchAll();
header('Content-Type: application/json');
echo json_encode($chats, JSON_PRETTY_PRINT);
unlink(__FILE__);
