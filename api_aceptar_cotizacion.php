<?php
header('Content-Type: application/json');
require_once 'db.php';

// Disable display_errors to prevent HTML pollution in JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $hash = $_POST['hash'] ?? '';
    $accion = $_POST['accion'] ?? ''; // 'aceptar' o 'rechazar'
    
    // 1. Validar Cotización
    $stmt = $pdo->prepare("
        SELECT c.*, cl.nombre as cliente_nombre, e.nombre as empresa_nombre, 
               e.smsenlinea_secret, e.smsenlinea_wa_account, e.id as empresa_id
        FROM cotizaciones c 
        JOIN clientes cl ON c.cliente_id = cl.id 
        JOIN empresas e ON c.empresa_id = e.id
        WHERE c.hash_publico = ?
    ");
    $stmt->execute([$hash]);
    $cot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cot) {
        throw new Exception('Propuesta no válida o no encontrada');
    }

    if ($accion === 'aceptar') {
        // --- PROCESO DE ACEPTACIÓN ---
        require_once 'includes/cotizacion_helper.php';
        
        $datos = $_POST['datos'] ?? [];
        $logistica = $_POST['logistica'] ?? null;
        $firma_base64 = $_POST['firma_base64'] ?? '';
        
        // El helper manejará la validación de firma si es requerida, 
        // pero aquí mantenemos la validación local si no es reemplazada por formulario.
        // Nota: El user pidió que si no hay formulario, no se pida firma.
        // Por ahora el helper procesa lo que le enviemos.

        $metadata = [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'fecha' => date('Y-m-d H:i:s'),
            'form_data' => $datos,
            'logistica' => $logistica
        ];

        // 1. Manejo de Documentos Subidos (Si quedara alguno legacy, aunque vamos a limpiar la UI)
        $docs_subidos = [];
        if (isset($_FILES['docs'])) {
            $uploadDir = getCompanyUploadPath('clientes/docs', true); 
             if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            foreach ($_FILES['docs']['name'] as $key => $name) {
                if ($_FILES['docs']['error'][$key] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $cleanName = preg_replace('/[^a-zA-Z0-9]/', '_', pathinfo($name, PATHINFO_FILENAME));
                    $filename = "OT_{$cot['id']}_{$key}_{$cleanName}_" . time() . ".$ext";
                    $targetPath = $uploadDir . '/' . $filename;
                    
                    if (move_uploaded_file($_FILES['docs']['tmp_name'][$key], $targetPath)) {
                        $relPath = getCompanyUploadPath('clientes/docs', false) . $filename;
                        $docs_subidos[$key] = $relPath;
                    }
                }
            }
        }
        $metadata['documentos'] = $docs_subidos;

        // --- LLAMADA AL HELPER ---
        $resultado = procesarAceptacionCotizacion($pdo, $cot['id'], $firma_base64, $metadata);

        if ($resultado['status'] === 'success') {
            echo json_encode($resultado);
        } else {
            throw new Exception($resultado['message']);
        }

    } elseif ($accion === 'rechazar') {
        $pdo->prepare("UPDATE cotizaciones SET estado = 'Rechazada' WHERE id = ?")->execute([$cot['id']]);
        
        // Notificacion simple
        try {
             if (function_exists('notificarEmpresa')) {
                notificarEmpresa(
                    $pdo, $cot['empresa_id'], 'cotizacion_rechazada', 
                    'Cotización Rechazada', "El cliente rechazó la cotización #{$cot['id']}", 
                    "cotizaciones.php"
                );
             }
        } catch (Exception $e) {}

        echo json_encode(['status' => 'success', 'message' => 'Has rechazado la propuesta exitosamente.']);
    } else {
        throw new Exception('Acción desconocida');
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("API Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
