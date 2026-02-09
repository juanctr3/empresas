<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$response = [];

// 1. Connection check
try {
    require 'db.php';
    if (!isset($pdo)) {
        throw new Exception('$pdo not defined in db.php');
    }
    $response['db'] = 'OK';
} catch (Exception $e) {
    die(json_encode(['status' => 'error', 'message' => 'DB Connection Failed: ' . $e->getMessage()]));
}

// 2. Query check (Table exists?)
try {
    $pdo->query("SELECT 1 FROM wa_chat_asignaciones LIMIT 1");
    $response['table'] = 'OK';
} catch (Exception $e) {
    die(json_encode(['status' => 'error', 'message' => 'Table check failed: ' . $e->getMessage()]));
}

// 3. Insert/Delete simulation (Safe mode)
$chat_id = 999;
$agent_id = 999;

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO wa_chat_asignaciones (chat_id, usuario_id) VALUES (?, ?)");
    $stmt->execute([$chat_id, $agent_id]);
    $response['insert'] = 'OK';
    
    $stmt = $pdo->prepare("DELETE FROM wa_chat_asignaciones WHERE chat_id = ? AND usuario_id = ?");
    $stmt->execute([$chat_id, $agent_id]);
    $response['delete'] = 'OK';

} catch (Exception $e) {
    die(json_encode(['status' => 'error', 'message' => 'Assignment logic failed: ' . $e->getMessage()]));
}

$response['status'] = 'success';
echo json_encode($response);
exit;
