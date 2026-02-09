<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id = $_POST['id'] ?? null;
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ID de cotización no proporcionado']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Obtener datos de la cotización original
    $stmt = $pdo->prepare("SELECT * FROM cotizaciones WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id, getEmpresaId()]);
    $original = $stmt->fetch();

    if (!$original) {
        throw new Exception("Cotización no encontrada");
    }

    // 2. Preparar datos para la copia
    $hash_publico = md5(uniqid(rand(), true));
    
    // Generar un nuevo número (opcional, pero mejor dejar el mismo + " - COPIA" o simplemente el mismo y que lo cambien)
    // Decidimos mantener la mayoría de datos e insertar como borrador
    
    $cols = [
        'empresa_id', 'usuario_id', 'cliente_id', 'plantilla_id', 'numero_cotizacion', 
        'titulo_cotizacion', 'fecha', 'fecha_vencimiento', 'notas', 'notas_internas', 
        'subtotal', 'impuestos', 'total', 'config_aceptacion', 'config_pasos', 
        'tareas_cliente', 'contenido_html', 'mostrar_cantidad_como', 'requiere_recoleccion', 
        'conversion_automatica', 'notificar_vistas_wa'
    ];
    
    $placeholders = implode(',', array_fill(0, count($cols), '?')) . ", 'Borrador', ?";
    $sqlInsert = "INSERT INTO cotizaciones (" . implode(',', $cols) . ", estado, hash_publico) VALUES ($placeholders)";
    
    $vals = [];
    foreach ($cols as $col) {
        if ($col === 'usuario_id') {
            $vals[] = $_SESSION['user_id']; // El que duplica es el nuevo dueño
        } else {
            $vals[] = $original[$col];
        }
    }
    $vals[] = $hash_publico;

    $stmtInsert = $pdo->prepare($sqlInsert);
    $stmtInsert->execute($vals);
    $newId = $pdo->lastInsertId();

    // 3. Duplicar detalles (items)
    $stmtItems = $pdo->prepare("SELECT * FROM cotizacion_detalles WHERE cotizacion_id = ?");
    $stmtItems->execute([$id]);
    $items = $stmtItems->fetchAll();

    foreach ($items as $item) {
        $itemCols = [
            'producto_id', 'nombre_producto', 'descripcion', 'cantidad', 
            'precio_unitario', 'subtotal', 'impuestos', 'total', 'imagen', 
            'unidad_id', 'orden', 'seccion'
        ];
        
        $itemPlaceholders = "?," . implode(',', array_fill(0, count($itemCols), '?'));
        $sqlItemInsert = "INSERT INTO cotizacion_detalles (cotizacion_id, " . implode(',', $itemCols) . ") VALUES ($itemPlaceholders)";
        
        $itemVals = [$newId];
        foreach ($itemCols as $icol) {
            $itemVals[] = $item[$icol];
        }
        
        $pdo->prepare($sqlItemInsert)->execute($itemVals);
    }

    // 4. Duplicar adjuntos
    $stmtAdj = $pdo->prepare("SELECT documento_id FROM cotizacion_adjuntos WHERE cotizacion_id = ?");
    $stmtAdj->execute([$id]);
    $adjuntos = $stmtAdj->fetchAll();

    foreach ($adjuntos as $adj) {
        $pdo->prepare("INSERT INTO cotizacion_adjuntos (cotizacion_id, documento_id) VALUES (?, ?)")
            ->execute([$newId, $adj['documento_id']]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'new_id' => $newId]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
