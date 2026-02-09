<?php
/**
 * API de Notificaciones
 * Maneja operaciones CRUD y consultas de notificaciones
 */
require_once 'db.php';
require_once 'includes/auth_helper.php';
require_once 'includes/notificaciones_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$usuario_id = $_SESSION['user_id'];

switch ($accion) {
    case 'obtener_no_leidas':
        // Obtener contador de notificaciones no leídas
        $count = contarNotificacionesNoLeidas($pdo, $usuario_id);
        echo json_encode(['status' => 'success', 'count' => $count]);
        break;

    case 'listar':
        // Obtener últimas notificaciones (límite opcional)
        $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        
        $stmt = $pdo->prepare("SELECT * FROM notificaciones 
                              WHERE usuario_id = ? 
                              ORDER BY fecha_creacion DESC 
                              LIMIT ? OFFSET ?");
        $stmt->execute([$usuario_id, $limite, $offset]);
        $notificaciones = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $notificaciones]);
        break;

    case 'listar_recientes':
        // Obtener solo las últimas 10 para el dropdown
        $stmt = $pdo->prepare("SELECT * FROM notificaciones 
                              WHERE usuario_id = ? 
                              ORDER BY fecha_creacion DESC 
                              LIMIT 10");
        $stmt->execute([$usuario_id]);
        $notificaciones = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $notificaciones]);
        break;

    case 'marcar_leida':
        $id = $_POST['id'] ?? 0;
        
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
            exit;
        }

        $exito = marcarNotificacionLeida($pdo, $id, $usuario_id);
        
        if ($exito) {
            echo json_encode(['status' => 'success', 'message' => 'Notificación marcada como leída']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo marcar la notificación']);
        }
        break;

    case 'marcar_todas_leidas':
        $stmt = $pdo->prepare("UPDATE notificaciones 
                              SET leida = 1, fecha_lectura = NOW() 
                              WHERE usuario_id = ? AND leida = 0");
        $stmt->execute([$usuario_id]);
        $count = $stmt->rowCount();
        
        echo json_encode(['status' => 'success', 'message' => "$count notificaciones marcadas como leídas", 'count' => $count]);
        break;

    case 'eliminar':
        $id = $_POST['id'] ?? 0;
        
        if (!$id) {
            echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM notificaciones WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$id, $usuario_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Notificación eliminada']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar la notificación']);
        }
        break;

    case 'eliminar_leidas':
        $stmt = $pdo->prepare("DELETE FROM notificaciones WHERE usuario_id = ? AND leida = 1");
        $stmt->execute([$usuario_id]);
        $count = $stmt->rowCount();
        
        echo json_encode(['status' => 'success', 'message' => "$count notificaciones eliminadas", 'count' => $count]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
}
?>
