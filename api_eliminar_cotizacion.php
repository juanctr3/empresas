<?php
require_once 'db.php';
// require_once 'includes/auth_helper.php'; // Comentado para evitar errores 500 por dependencias

header('Content-Type: application/json');

function tienePermiso($clave) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true) return true;
    if (!isset($_SESSION['permisos'])) return false;
    return in_array($clave, $_SESSION['permisos']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;

    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'ID no válido']);
        exit;
    }

    try {
        if (!tienePermiso('eliminar_cotizacion')) {
            echo json_encode(['status' => 'error', 'message' => 'No tienes permiso para eliminar cotizaciones']);
            exit;
        }

        // Verificar que pertenezca a la empresa y su estado
        $stmt = $pdo->prepare("SELECT id, estado FROM cotizaciones WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, getEmpresaId()]);
        $cot = $stmt->fetch();
        
        if ($cot) {
            // Protección contra borrado de aprobadas
            if ($cot['estado'] === 'Aprobada') {
                if (!$_SESSION['is_super_admin'] && $_SESSION['rol'] !== 'Administrador') {
                    echo json_encode(['status' => 'error', 'message' => 'No se puede eliminar una cotización que ya ha sido aprobada por el cliente.']);
                    exit;
                }
                if (!isset($_POST['confirmar_aprobada'])) {
                    echo json_encode(['status' => 'warning', 'message' => 'Esta cotización ya cuenta con aceptación del cliente. ¿Realmente desea eliminarla?']);
                    exit;
                }
            }

            $pdo->beginTransaction();
            
            // 1. Eliminar Notificaciones (Si existe la tabla)
            try {
                $pdo->prepare("DELETE FROM notificaciones WHERE cotizacion_id = ?")->execute([$id]);
            } catch (Exception $e) { /* Ignorar si no existe, pero loguear */ error_log($e->getMessage()); }

            // 2. Cotización Detalles (CRÍTICO: Debe borrarse sí o sí)
            $pdo->prepare("DELETE FROM cotizacion_detalles WHERE cotizacion_id = ?")->execute([$id]);

            // 3. Cotización Historial
            $pdo->prepare("DELETE FROM cotizacion_historial WHERE cotizacion_id = ?")->execute([$id]);

            // 4. Cotización Vistas (CRÍTICO: Diagnosticado como bloqueante)
            $pdo->prepare("DELETE FROM cotizacion_vistas WHERE cotizacion_id = ?")->execute([$id]);

            // 5. Cotización Adjuntos
            try {
                $pdo->prepare("DELETE FROM cotizacion_adjuntos WHERE cotizacion_id = ?")->execute([$id]);
            } catch (Exception $e) { /* Ignorar si tabla no existe */ }

            // 6. Desvincular Órdenes de Trabajo (SET NULL)
            try {
                $pdo->prepare("UPDATE ordenes_trabajo SET cotizacion_id = NULL WHERE cotizacion_id = ?")->execute([$id]);
            } catch (Exception $e) {
                // Si falla, es crítico porque impedirá el borrado final si hay FK
                throw new Exception("No se pudo desvincular Órdenes de Trabajo: " . $e->getMessage());
            }

            // 7. Desvincular Certificados
            try {
                $pdo->prepare("UPDATE certificados SET cotizacion_id = NULL WHERE cotizacion_id = ?")->execute([$id]);
            } catch (Exception $e) {
                 throw new Exception("No se pudo desvincular Certificados: " . $e->getMessage());
            }

            // 8. Borrar la Cotización Principal
            $stmtDel = $pdo->prepare("DELETE FROM cotizaciones WHERE id = ?");
            $stmtDel->execute([$id]);
            
            if ($stmtDel->rowCount() === 0) {
                 throw new Exception("No se eliminó el registro principal. ¿Quizás ya fue borrado?");
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Cotización eliminada correctamente']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No tienes permiso o no existe']);
        }
    } catch (Throwable $e) { // Catch Error and Exception
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500); 
        echo json_encode(['status' => 'error', 'message' => 'Error del servidor: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
