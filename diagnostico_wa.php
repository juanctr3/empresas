<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

header('Content-Type: application/json');

$empresa_id = getEmpresaId();
$usuario_id = $_SESSION['user_id'] ?? null;
$rol = $_SESSION['user_rol'] ?? null;

$diagnose = [
    'session' => [
        'empresa_id' => $empresa_id,
        'user_id' => $usuario_id,
        'rol' => $rol,
        'is_super_admin' => $_SESSION['is_super_admin'] ?? false
    ],
    'tables' => []
];

$tables = ['wa_chats', 'wa_mensajes', 'wa_chat_asignaciones'];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM $table");
        $stmt->execute();
        $diagnose['tables'][$table] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $diagnose['tables'][$table] = "ERROR: " . $e->getMessage();
    }
}

echo json_encode($diagnose, JSON_PRETTY_PRINT);
