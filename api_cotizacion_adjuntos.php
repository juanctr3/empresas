<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

$empresa_id = getEmpresaId();
if (!$empresa_id) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$accion = $_POST['accion'] ?? '';

switch ($accion) {
    case 'listar_disponibles':
        // Listar todos los documentos de la empresa que NO estén adjuntos a esta cotización
        $cot_id = $_POST['cotizacion_id'] ?? 0;
        $stmt = $pdo->prepare("
            SELECT d.*, (SELECT COUNT(*) FROM cotizacion_adjuntos WHERE cotizacion_id = ? AND documento_id = d.id) as is_attached
            FROM documentos d 
            WHERE d.empresa_id = ?
        ");
        $stmt->execute([$cot_id, $empresa_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        break;

    case 'vincular':
        $cot_id = $_POST['cotizacion_id'] ?? 0;
        $doc_ids = $_POST['documento_ids'] ?? []; // Array de IDs

        $pdo->beginTransaction();
        try {
            // Limpiar anteriores para re-vincular
            $pdo->prepare("DELETE FROM cotizacion_adjuntos WHERE cotizacion_id = ?")->execute([$cot_id]);
            
            $stmt = $pdo->prepare("INSERT INTO cotizacion_adjuntos (cotizacion_id, documento_id) VALUES (?, ?)");
            foreach ($doc_ids as $did) {
                $stmt->execute([$cot_id, $did]);
            }
            $pdo->commit();

            // 🔔 TRIGGER: Notificar si hay documentos adjuntados
            if (count($doc_ids) > 0) {
                try {
                    require_once 'includes/notificaciones_helper.php';
                    
                    // Obtener info de la cotización
                    $stmt = $pdo->prepare("SELECT numero_cotizacion, empresa_id FROM cotizaciones WHERE id = ?");
                    $stmt->execute([$cot_id]);
                    $cot = $stmt->fetch();
                    
                    if ($cot) {
                        $num_docs = count($doc_ids);
                        $mensaje = $num_docs === 1 
                            ? "Se adjuntó 1 documento a la cotización #{$cot['numero_cotizacion']}"
                            : "Se adjuntaron {$num_docs} documentos a la cotización #{$cot['numero_cotizacion']}";
                        
                        notificarEmpresa(
                            $pdo,
                            $cot['empresa_id'],
                            'documento_compartido',
                            'Documentos Adjuntados',
                            $mensaje,
                            "cotizaciones.php?ver={$cot_id}"
                        );
                    }
                } catch (Exception $e) {
                    error_log("Error creando notificación: " . $e->getMessage());
                }
            }

            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'eliminar':
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM cotizacion_adjuntos WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['status' => 'success']);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
        break;
}
