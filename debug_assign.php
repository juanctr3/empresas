<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db.php';

$chat_id = 999; // Dummy or check existing
$agent_id = 999;
$action = 'add';

echo "Testing connection... OK\n";

try {
    if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT IGNORE INTO wa_chat_asignaciones (chat_id, usuario_id) VALUES (?, ?)");
            $stmt->execute([$chat_id, $agent_id]);
            echo "Inserted\n";
    } else {
            $stmt = $pdo->prepare("DELETE FROM wa_chat_asignaciones WHERE chat_id = ? AND usuario_id = ?");
            $stmt->execute([$chat_id, $agent_id]);
            echo "Deleted\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
