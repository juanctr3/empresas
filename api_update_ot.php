<?php
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;
$notas = $_POST['notas'] ?? null;

if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ID faltante']);
    exit;
}

try {
    $empresa_id = getEmpresaId();
    
    // Update State
    if ($status) {
        $stmt = $pdo->prepare("UPDATE ordenes_trabajo SET estado = ? WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$status, $id, $empresa_id]);
    }
    
    // Update Notes
    if ($notas !== null) {
        $stmt = $pdo->prepare("UPDATE ordenes_trabajo SET notas_internas = ? WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$notas, $id, $empresa_id]);
    }

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
