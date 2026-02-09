<?php
require_once 'db.php';

$cot_id = $_GET['id'] ?? 0;

if (!$cot_id) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'ID no válido']);
    exit;
}

// Verificar propiedad
$stmtCheck = $pdo->prepare("SELECT id FROM cotizaciones WHERE id = ? AND empresa_id = ?");
$stmtCheck->execute([$cot_id, getEmpresaId()]);
if (!$stmtCheck->fetch()) {
    header('Content-Type: application/json', true, 403);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$stmt = $pdo->prepare("SELECT fecha_vista, ip_address, user_agent FROM cotizacion_vistas WHERE cotizacion_id = ? ORDER BY fecha_vista DESC LIMIT 50");
$stmt->execute([$cot_id]);
$vistas = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'data' => $vistas]);
?>
