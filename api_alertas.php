<?php
/**
 * API Alertas - CoticeFacil.com
 */

require_once 'db.php';
require_once 'includes/auth_helper.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$empresa_id = getEmpresaId();

if (!$empresa_id) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

switch ($action) {
    case 'crear':
        $cliente_id = $_POST['cliente_id'] ?? 0;
        $titulo = $_POST['titulo'] ?? '';
        $mensaje = $_POST['mensaje'] ?? '';
        $fecha_alerta = $_POST['fecha_alerta'] ?? date('Y-m-d H:i:s');
        
        $stmt = $pdo->prepare("INSERT INTO alertas (empresa_id, cliente_id, titulo, mensaje, fecha_alerta, tipo) VALUES (?, ?, ?, ?, ?, 'Manual')");
        $stmt->execute([$empresa_id, $cliente_id, $titulo, $mensaje, $fecha_alerta]);
        
        echo json_encode(['status' => 'success', 'message' => 'Alerta programada']);
        break;

    case 'listar_dashboard':
        $hoy = $pdo->prepare("SELECT a.*, cl.nombre as cliente_nombre FROM alertas a JOIN clientes cl ON a.cliente_id = cl.id WHERE a.empresa_id = ? AND a.estado = 'Pendiente' AND DATE(a.fecha_alerta) <= CURDATE() ORDER BY a.fecha_alerta ASC");
        $proximas = $pdo->prepare("SELECT a.*, cl.nombre as cliente_nombre FROM alertas a JOIN clientes cl ON a.cliente_id = cl.id WHERE a.empresa_id = ? AND a.estado = 'Pendiente' AND DATE(a.fecha_alerta) > CURDATE() ORDER BY a.fecha_alerta ASC");
        $resueltas = $pdo->prepare("SELECT a.*, cl.nombre as cliente_nombre FROM alertas a JOIN clientes cl ON a.cliente_id = cl.id WHERE a.empresa_id = ? AND a.estado = 'Resuelta' ORDER BY a.created_at DESC LIMIT 10");
        
        $hoy->execute([$empresa_id]);
        $proximas->execute([$empresa_id]);
        $resueltas->execute([$empresa_id]);

        echo json_encode([
            'status' => 'success',
            'hoy' => $hoy->fetchAll(),
            'proximas' => $proximas->fetchAll(),
            'resueltas' => $resueltas->fetchAll()
        ]);
        break;

    case 'completar':
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("UPDATE alertas SET estado = 'Resuelta' WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresa_id]);
        echo json_encode(['status' => 'success']);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
        break;
}
