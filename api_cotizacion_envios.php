<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$cot_id = $_GET['id'] ?? 0;
if (!$cot_id) {
    echo json_encode(['status' => 'error', 'message' => 'ID de cotización no válido']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT * FROM cotizacion_envios 
        WHERE cotizacion_id = ? 
        ORDER BY fecha_envio DESC
    ");
    $stmt->execute([$cot_id]);
    $envios = $stmt->fetchAll();

    echo json_encode(['status' => 'success', 'data' => $envios]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
