<?php
/**
 * API Recordatorios - CoticeFacil.com
 * Handles CRUD for Quote Reminders
 */

require_once 'db.php';
require_once 'includes/auth_helper.php';
require_once 'includes/mail_helper.php';
require_once 'includes/smsenlinea_helper.php';

// Prevent HTML errors
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Check Auth
if (session_status() === PHP_SESSION_NONE) session_start();
$empresa_id = $_SESSION['empresa_id'] ?? null;
$usuario_id = $_SESSION['user_id'] ?? null;

if (!$empresa_id || !$usuario_id) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        // Permiso básico, idealmente separar 'crear_recordatorios'
        if (!tienePermiso('crear_cotizaciones')) { 
            echo json_encode(['status' => 'error', 'message' => 'Sin permisos']);
            exit;
        }

        $cot_id = $_POST['cotizacion_id'] ?? null;
        $cliente_id = $_POST['cliente_id'] ?? null;
        $asunto = trim($_POST['asunto'] ?? '');
        $mensaje = trim($_POST['mensaje'] ?? '');
        $fecha = $_POST['fecha_programada'] ?? '';
        $notificar_cliente = isset($_POST['notificar_cliente']) ? 1 : 0;
        
        // Additional contacts
        $emails_adicionales = $_POST['emails_adicionales'] ?? []; 
        $telefonos_adicionales = $_POST['telefonos_adicionales'] ?? []; 

        if ((empty($cot_id) && empty($cliente_id)) || empty($asunto) || empty($fecha)) {
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos obligatorios (Cotización o Cliente, Asunto, Fecha)']);
            exit;
        }

        // Validate IDs
        if($cot_id) {
            $stmt = $pdo->prepare("SELECT id, cliente_id FROM cotizaciones WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$cot_id, $empresa_id]);
            $cotData = $stmt->fetch();
            if (!$cotData) {
                echo json_encode(['status' => 'error', 'message' => 'Cotización no encontrada']);
                exit;
            }
            // If cliente_id not sent, use the one from quote
            if(!$cliente_id) $cliente_id = $cotData['cliente_id'];
        }

        // Validate Client if sent
        if($cliente_id) {
             $stmtC = $pdo->prepare("SELECT id FROM clientes WHERE id = ? AND empresa_id = ?");
             $stmtC->execute([$cliente_id, $empresa_id]);
             if (!$stmtC->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'Cliente no encontrado']);
                exit;
             }
        }

        // Pre-Reminder Fields
        $tiene_pre = isset($_POST['tiene_prerecordatorio']) ? 1 : 0;
        $dias_antes = $_POST['dias_antes'] ?? 1;
        $msg_pre = $_POST['mensaje_prerecordatorio'] ?? null;

        try {
            $stmtInsert = $pdo->prepare("INSERT INTO cotizacion_recordatorios 
                (empresa_id, cliente_id, cotizacion_id, usuario_id, asunto, mensaje, fecha_programada, emails_adicionales, telefonos_adicionales, notificar_cliente, tiene_prerecordatorio, dias_antes, mensaje_prerecordatorio, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')");
            
            $stmtInsert->execute([
                $empresa_id, 
                $cliente_id,
                $cot_id ?: null, // Store NULL if 0 or empty 
                $usuario_id, 
                $asunto, 
                $mensaje, 
                $fecha, 
                json_encode($emails_adicionales), 
                json_encode($telefonos_adicionales), 
                $notificar_cliente,
                $tiene_pre,
                $dias_antes,
                $msg_pre
            ]);

            echo json_encode(['status' => 'success', 'message' => 'Recordatorio programado']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error DB: ' . $e->getMessage()]);
        }
        break;

    case 'list':
        $cot_id = $_GET['cotizacion_id'] ?? 0;
        $cliente_id = $_GET['cliente_id'] ?? 0;
        
        if (!$cot_id && !$cliente_id) exit(json_encode([]));

        $sql = "SELECT r.*, u.nombre as creador_nombre 
                FROM cotizacion_recordatorios r 
                JOIN usuarios u ON r.usuario_id = u.id 
                WHERE r.empresa_id = ?";
        
        $params = [$empresa_id];

        if ($cot_id) {
            $sql .= " AND r.cotizacion_id = ?";
            $params[] = $cot_id;
        } elseif ($cliente_id) {
            $sql .= " AND r.cliente_id = ?";
            $params[] = $cliente_id;
        }
        
        $sql .= " ORDER BY r.fecha_programada ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $recordatorios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON fields for frontend
        foreach ($recordatorios as &$r) {
            $r['emails_adicionales'] = json_decode($r['emails_adicionales'] ?: '[]');
            $r['telefonos_adicionales'] = json_decode($r['telefonos_adicionales'] ?: '[]');
        }

        echo json_encode(['status' => 'success', 'data' => $recordatorios]);
        break;

    case 'delete':
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM cotizacion_recordatorios WHERE id = ? AND empresa_id = ?");
        if ($stmt->execute([$id, $empresa_id])) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al eliminar']);
        }
        break;
        
    case 'send_now':
        $id = $_POST['id'] ?? 0;
        require_once 'cron_recordatorios.php';
        
        $resultado = procesarRecordatorio($pdo, $id, $empresa_id); // Function we will define in cron
        
        if ($resultado['success']) {
            echo json_encode(['status' => 'success', 'message' => 'Enviado correctamente']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $resultado['error'] ?? 'Error desconocido']);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
}
?>
