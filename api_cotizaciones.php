<?php
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$empresa_id = getEmpresaId();
if (!$empresa_id) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada']);
    exit;
}

$accion = $_POST['accion'] ?? '';

if ($accion === 'actualizar_contenido') {
    $cot_id = $_POST['cotizacion_id'] ?? 0;
    $html = $_POST['html'] ?? '';

    if (!$cot_id) {
        echo json_encode(['status' => 'error', 'message' => 'ID requerido']);
        exit;
    }

    // Verificar propiedad
    $stmtCheck = $pdo->prepare("SELECT id FROM cotizaciones WHERE id = ? AND empresa_id = ?");
    $stmtCheck->execute([$cot_id, $empresa_id]);
    if (!$stmtCheck->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Cotización no encontrada o acceso denegado']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE cotizaciones SET contenido_html = ? WHERE id = ?");
        $stmt->execute([$html, $cot_id]);
        echo json_encode(['status' => 'success', 'message' => 'Diseño guardado correctamente']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
?>
