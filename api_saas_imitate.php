<?php
session_start();
require_once 'db.php';
require_once 'includes/audit_log_helper.php';

header('Content-Type: application/json');

// Solo Super Admins pueden Switcher
if (!isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Acceso no autorizado']);
    exit;
}

$raw_data = file_get_contents('php://input');
$data = json_decode($raw_data, true) ?: [];
$action = $data['action'] ?? '';

if ($action === 'start') {
    $empresa_id = $data['empresa_id'] ?? 0;
    
    if (!$empresa_id) {
        echo json_encode(['status' => 'error', 'message' => 'ID de empresa omitido']);
        exit;
    }

    // Obtener información de la empresa para el log
    $stmt = $pdo->prepare("SELECT nombre FROM empresas WHERE id = ?");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch();

    if (!$empresa) {
        echo json_encode(['status' => 'error', 'message' => 'Empresa no encontrada']);
        exit;
    }

    $_SESSION['simulated_empresa_id'] = $empresa_id;
    $_SESSION['simulated_empresa_nombre'] = $empresa['nombre'];

    registrarAuditoria("IMPERSONATION_START", "Inició soporte/imitación de la empresa: " . $empresa['nombre'], $empresa_id);

    echo json_encode(['status' => 'success', 'message' => 'Modo soporte activado para ' . $empresa['nombre']]);
    exit;
}

if ($action === 'stop') {
    $nombre_antiguo = $_SESSION['simulated_empresa_nombre'] ?? 'Desconocida';
    $id_antiguo = $_SESSION['simulated_empresa_id'] ?? null;

    unset($_SESSION['simulated_empresa_id']);
    unset($_SESSION['simulated_empresa_nombre']);

    registrarAuditoria("IMPERSONATION_STOP", "Finalizó soporte/imitación de la empresa: " . $nombre_antiguo, $id_antiguo);

    echo json_encode(['status' => 'success', 'message' => 'Modo soporte desactivado']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
