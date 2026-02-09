<?php
require_once 'db.php';
header('Content-Type: application/json');

// DEBUG TOP
file_put_contents('debug_autosave_top.log', date('Y-m-d H:i:s') . " - Request received\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Method']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $empresa_id = getEmpresaId();
    $cot_id = $_POST['id'] ?? $_POST['cotizacion_id'] ?? null;
    $usuario_id = $_SESSION['user_id'] ?? 0;
    
    // Validate Limits (only for new Inserts)
    if (!$cot_id && haAlcanzadoLimite('cotizaciones')) {
        throw new Exception("Límite de cotizaciones alcanzado.");
    }

    // Prepare Data
    $cliente_id = !empty($_POST['cliente_id']) ? $_POST['cliente_id'] : null;
    $fecha = $_POST['fecha'] ?? date('Y-m-d');
    $venc = $_POST['fecha_vencimiento'] ?? date('Y-m-d', strtotime('+30 days'));
    $subtotal = $_POST['total_subtotal'] ?? 0;
    $impuestos = $_POST['total_impuestos'] ?? 0;
    $total = $_POST['total_general'] ?? 0;

    // Robust subtotal calculation for items (failsafe)
    if (isset($_POST['productos']) && is_array($_POST['productos'])) {
        $calc_sub = 0;
        foreach ($_POST['productos'] as $p) {
            $cant = floatval($p['cantidad'] ?? 0);
            $pre = floatval($p['precio'] ?? 0);
            $calc_sub += ($cant * $pre);
        }
        // If the posted subtotal is 0 but we calculated more, fallback to calculated
        if (floatval($subtotal) == 0 && $calc_sub > 0) $subtotal = $calc_sub;
    }
    
    // Configs
    $config_aceptacion = json_encode([
        'requeridos' => $_POST['campos_requeridos'] ?? [], // Legacy compatibility
        'mensaje_exito' => $_POST['mensaje_exito'] ?? '¡Gracias por aceptar!'
    ]);
    $config_pasos = json_encode($_POST['pasos_siguientes'] ?? []);
    $tareas_cliente = json_encode($_POST['tareas_cliente'] ?? []);
    
    // Formulario Configuration
    $formulario_id = !empty($_POST['formulario_id']) ? $_POST['formulario_id'] : null;
    $raw_modo = $_POST['formulario_modo'] ?? 'ninguno';
    $formulario_config = json_encode(['modo' => $raw_modo]);
    $notificar_wa = isset($_POST['notificar_vistas_wa']) ? 1 : 0;
    
    // DEBUG LOG ADDED BACK
    file_put_contents('debug_autosave.txt', date('Y-m-d H:i:s') . " - ID: " . ($cot_id ?? 'N/A') . " - POST: " . json_encode($_POST) . "\n", FILE_APPEND);
    
    // Update or Insert
    $plantilla_id = !empty($_POST['plantilla_id']) ? $_POST['plantilla_id'] : null;

    if ($cot_id) {
        $stmt = $pdo->prepare("UPDATE cotizaciones SET 
            cliente_id=?, fecha=?, fecha_vencimiento=?, 
            subtotal=?, impuesto_total=?, total=?, 
            tareas_cliente=?, config_pasos=?, 
            mostrar_cantidad_como=?, conversion_automatica=?, plantilla_id=?,
            titulo_cotizacion=?,
            notificar_vistas_wa=?, formulario_id=?, formulario_config=?
            WHERE id=? AND empresa_id=?");
        
        $stmt->execute([
            $cliente_id, $fecha, $venc,
            $subtotal, $impuestos, $total,
            $tareas_cliente, $config_pasos,
            $_POST['mostrar_cantidad_como'] ?? 'unidad',
            isset($_POST['conversion_automatica']) ? 1 : 0,
            $plantilla_id,
            $_POST['titulo_cotizacion'] ?? '',
            $notificar_wa, $formulario_id, $formulario_config,
            $cot_id, $empresa_id
        ]);
        
        // Fetch existing hash to return it
        $stmtH = $pdo->prepare("SELECT hash_publico FROM cotizaciones WHERE id = ?");
        $stmtH->execute([$cot_id]);
        $hash = $stmtH->fetchColumn();
        
    } else {
        $hash = bin2hex(random_bytes(16));
        
        // Secure Number Generation with Locking
        $empresa_id = getEmpresaId();
        
        // Lock table row to preventing race conditions
        $stmtConf = $pdo->prepare("SELECT starting_quote_number, quote_prefix, quote_suffix FROM empresas WHERE id = ? FOR UPDATE");
        $stmtConf->execute([$empresa_id]);
        $conf = $stmtConf->fetch();
        
        $next_num = $conf['starting_quote_number'];
        $prefix = $conf['quote_prefix'] ?? '';
        $suffix = $conf['quote_suffix'] ?? '';
        
        $numero = $prefix . $next_num . $suffix;
        
        // Validar que no exista (casos raros de importación manual)
        // Si existe, busca el siguiente libre
        while(true) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM cotizaciones WHERE numero_cotizacion = ? AND empresa_id = ?");
            $check->execute([$numero, $empresa_id]);
            if ($check->fetchColumn() == 0) break;
            
            $next_num++;
            $numero = $prefix . $next_num . $suffix;
        }

        $stmt = $pdo->prepare("INSERT INTO cotizaciones 
            (empresa_id, usuario_id, cliente_id, numero_cotizacion, titulo_cotizacion, fecha, fecha_vencimiento, subtotal, impuesto_total, total, estado, hash_publico, tareas_cliente, plantilla_id, notificar_vistas_wa, formulario_id, formulario_config) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Borrador', ?, ?, ?, ?, ?, ?)");
            
        $stmt->execute([
            $empresa_id, $usuario_id, $cliente_id, $numero, $_POST['titulo_cotizacion'] ?? '', $fecha, $venc, 
            $subtotal, $impuestos, $total, $hash, $tareas_cliente, $plantilla_id,
            $notificar_wa, $formulario_id, $formulario_config
        ]);
        $cot_id = $pdo->lastInsertId();
        
        // Increment global counter
        $pdo->prepare("UPDATE empresas SET starting_quote_number = ? WHERE id = ?")->execute([$next_num + 1, $empresa_id]);
        
        $new_number_assigned = $numero;
    }

    // Handle Details (Only if products array is present)
    if (isset($_POST['productos']) && is_array($_POST['productos'])) {
        $pdo->prepare("DELETE FROM cotizacion_detalles WHERE cotizacion_id = ?")->execute([$cot_id]);
        $stmtDet = $pdo->prepare("INSERT INTO cotizacion_detalles (cotizacion_id, producto_id, nombre_producto, descripcion, cantidad, unidad_nombre, precio_unitario, impuesto_porcentaje, subtotal, seccion, es_opcional, seleccionado, imagen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($_POST['productos'] as $prod) {
            if (empty($prod['nombre'])) continue;
            $stmtDet->execute([
                $cot_id,
                $prod['id'] ?? null,
                $prod['nombre'],
                $prod['descripcion'] ?? '',
                $prod['cantidad'] ?? 1,
                $prod['unidad'] ?? 'und',
                $prod['precio'] ?? 0,
                $prod['impuesto_p'] ?? 0,
                ($prod['cantidad'] ?? 1) * ($prod['precio'] ?? 0),
                $prod['seccion'] ?? 'General',
                isset($prod['es_opcional']) ? 1 : 0,
                isset($prod['seleccionado']) ? 1 : 0,
                $prod['imagen'] ?? null
            ]);
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'id' => $cot_id, 'hash' => $hash ?? '', 'numero' => $new_number_assigned ?? null]);
    
} catch (Exception $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
