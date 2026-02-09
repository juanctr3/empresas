<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$empresa_id = getEmpresaId();
$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'create_update') {
        $id = $_POST['id'] ?? null;
        $cliente_id = $_POST['cliente_id'];
        $fecha_emision = $_POST['fecha_emision'];
        $fecha_vencimiento = $_POST['fecha_vencimiento'];
        $notas = $_POST['notas'] ?? '';
        $notas_internas = $_POST['notas_internas'] ?? '';
        
        // Calcular Totales
        $items = $_POST['items'] ?? []; // Array de items
        $subtotal = 0;
        $impuestos = 0;
        
        foreach ($items as $item) {
            $qty = floatval($item['cantidad']);
            $price = floatval($item['precio']);
            $taxP = floatval($item['impuesto'] ?? 0);
            
            $rowSub = $qty * $price;
            $rowTax = $rowSub * ($taxP / 100);
            
            $subtotal += $rowSub;
            $impuestos += $rowTax;
        }
        $total = $subtotal + $impuestos;

        // Validar / Generar Número de Factura
        $numero_factura = $_POST['numero_factura'] ?? '';
        
        if ($numero_factura !== '') {
            // Validar unicidad manual
            $sqlCheck = "SELECT id FROM facturas WHERE numero_factura = ? AND empresa_id = ? AND id != ?";
            $stmtCheck = $pdo->prepare($sqlCheck);
            $stmtCheck->execute([$numero_factura, $empresa_id, $id ?? 0]);
            if ($stmtCheck->fetch()) {
                echo json_encode(['status' => 'error', 'message' => 'El número de factura ' . $numero_factura . ' ya existe.']);
                exit;
            }
        } else {
            // Autogenerar si está vacío y es NUEVA
            if (!$id) {
                $stmtConf = $pdo->prepare("SELECT starting_invoice_number, invoice_prefix, invoice_suffix FROM empresas WHERE id = ? FOR UPDATE");
                $stmtConf->execute([$empresa_id]);
                $conf = $stmtConf->fetch();
                
                $next_num = $conf['starting_invoice_number'];
                $numero_factura = ($conf['invoice_prefix'] ?? '') . $next_num . ($conf['invoice_suffix'] ?? '');
                
                while(true) {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM facturas WHERE numero_factura = ? AND empresa_id = ?");
                    $chk->execute([$numero_factura, $empresa_id]);
                    if($chk->fetchColumn() == 0) break;
                    $next_num++;
                    $numero_factura = ($conf['invoice_prefix'] ?? '') . $next_num . ($conf['invoice_suffix'] ?? '');
                }
                
                // Actualizar consecutivo
                $pdo->prepare("UPDATE empresas SET starting_invoice_number = ? WHERE id = ?")->execute([$next_num + 1, $empresa_id]);
            } else {
                // Si es actualización y no mandaron número, mantener el existente (no hacer nada)
                // Pero necesitamos el número para retornarlo
                $stmtNum = $pdo->prepare("SELECT numero_factura FROM facturas WHERE id = ?");
                $stmtNum->execute([$id]);
                $numero_factura = $stmtNum->fetchColumn();
            }
        }

        if (!$id) {
            // CREAR
            $hash = bin2hex(random_bytes(32));
            $sql = "INSERT INTO facturas (empresa_id, cliente_id, cotizacion_id, numero_factura, fecha_emision, fecha_vencimiento, subtotal, impuestos, total, notas, notas_internas, hash_publico, estado, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Borrador', NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $empresa_id, $cliente_id, $_POST['cotizacion_id'] ?? null, $numero_factura, $fecha_emision, $fecha_vencimiento, $subtotal, $impuestos, $total, $notas, $notas_internas, $hash
            ]);
            $id = $pdo->lastInsertId();
            $hash_publico = $hash;

            // ACTUALIZAR ESTADO DE COTIZACIÓN
            // ACTUALIZAR ESTADO DE COTIZACIÓN (Defensivo)
            if (!empty($_POST['cotizacion_id'])) {
                try {
                    $pdo->prepare("UPDATE cotizaciones SET estado = 'Facturada' WHERE id = ? AND empresa_id = ?")->execute([$_POST['cotizacion_id'], $empresa_id]);
                } catch (Exception $e) {
                    // Si falla actualizar la cotización, no detenemos la creación de la factura
                    error_log("Warn: No se pudo actualizar estado cotización a Facturada: " . $e->getMessage());
                }
            }
        } else {
            // ACTUALIZAR
            $sql = "UPDATE facturas SET cliente_id=?, numero_factura=?, fecha_emision=?, fecha_vencimiento=?, subtotal=?, impuestos=?, total=?, notas=?, notas_internas=? WHERE id=? AND empresa_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $cliente_id, $numero_factura, $fecha_emision, $fecha_vencimiento, $subtotal, $impuestos, $total, $notas, $notas_internas, $id, $empresa_id
            ]);
            // Hash existente
            $stmtH = $pdo->prepare("SELECT hash_publico FROM facturas WHERE id = ?");
            $stmtH->execute([$id]);
            $hash_publico = $stmtH->fetchColumn();

            $pdo->prepare("DELETE FROM factura_detalles WHERE factura_id = ?")->execute([$id]);
        }
        
        // Insertar Items
        $sqlItem = "INSERT INTO factura_detalles (factura_id, producto_id, nombre_producto, descripcion, cantidad, precio_unitario, impuesto_porcentaje, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtItem = $pdo->prepare($sqlItem);
        
        foreach ($items as $item) {
            $qty = floatval($item['cantidad']);
            $price = floatval($item['precio']);
            $taxP = floatval($item['impuesto'] ?? 0);
            $rowSub = $qty * $price; // Subtotal de línea sin impuesto para la DB (o con? usualmente es base)
            
            $stmtItem->execute([
                $id,
                $item['producto_id'] ?? null,
                $item['nombre'],
                $item['descripcion'] ?? '',
                $qty,
                $price,
                $taxP,
                $rowSub
            ]);
        }
        
        echo json_encode(['status' => 'success', 'id' => $id, 'invoice_number' => $numero_factura, 'hash' => $hash_publico]);

    } elseif ($action === 'import_quote_files') {
        $factura_id = $_POST['factura_id'];
        $cotizacion_id = $_POST['cotizacion_id'];
        $files_to_import = $_POST['files'] ?? []; // Array of file names/indices

        if(empty($files_to_import)) die(json_encode(['status'=>'success', 'imported'=>0]));

        // Get quote acceptance data
        $stmtC = $pdo->prepare("SELECT aceptada_data FROM cotizaciones WHERE id = ? AND empresa_id = ?");
        $stmtC->execute([$cotizacion_id, $empresa_id]);
        $cot = $stmtC->fetch();
        
        if(!$cot || !$cot['aceptada_data']) throw new Exception("No hay datos de aceptación");
        
        $data = json_decode($cot['aceptada_data'], true);
        $docs = $data['documentos'] ?? [];
        
        $count = 0;
        $target_dir = "uploads/e{$empresa_id}/facturas/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

        foreach($files_to_import as $file_path) {
            // Clean path
            $real_source = $file_path; // Assuming relative path stored in JSON
            if(file_exists($real_source)) {
                $filename = basename($real_source);
                $new_name = uniqid('imported_') . '_' . $filename;
                
                if(copy($real_source, $target_dir . $new_name)) {
                    $mime = mime_content_type($target_dir . $new_name);
                    $size = filesize($target_dir . $new_name);
                    
                    $stmtF = $pdo->prepare("INSERT INTO factura_archivos (factura_id, nombre_original, ruta_archivo, tipo_mime, tamano) VALUES (?, ?, ?, ?, ?)");
                    $stmtF->execute([$factura_id, $filename, $target_dir . $new_name, $mime, $size]);
                    $count++;
                }
            }
        }
        echo json_encode(['status'=>'success', 'imported'=>$count]);

    } elseif ($action === 'upload_file') {
        $factura_id = $_POST['factura_id'];
        if (!isset($_FILES['file'])) throw new Exception("No se envió archivo");
        
        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $name = uniqid('inv_') . '.' . $ext;
        $target = "uploads/e{$empresa_id}/facturas/";
        
        if (!file_exists($target)) mkdir($target, 0777, true);
        
        if (move_uploaded_file($file['tmp_name'], $target . $name)) {
            $stmt = $pdo->prepare("INSERT INTO factura_archivos (factura_id, nombre_original, ruta_archivo, tipo_mime, tamano) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$factura_id, $file['name'], $target . $name, $file['type'], $file['size']]);
            echo json_encode(['status' => 'success']);
        } else {
            throw new Exception("Error al mover archivo");
        }
    }
    elseif ($action === 'get') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresa_id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($factura) {
            $stmtDet = $pdo->prepare("SELECT * FROM factura_detalles WHERE factura_id = ?");
            $stmtDet->execute([$id]);
            $factura['items'] = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
            
            $stmtFiles = $pdo->prepare("SELECT * FROM factura_archivos WHERE factura_id = ?");
            $stmtFiles->execute([$id]);
            $factura['archivos'] = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['status' => 'success', 'data' => $factura]);

    } elseif ($action === 'send') {
        $id = $_POST['id'];
        $method = $_POST['method']; // 'whatsapp', 'email', 'both'
        
        // Obtener datos factura y cliente
        $stmt = $pdo->prepare("
            SELECT f.*, c.nombre as cliente_nombre, c.email as cliente_email, c.celular_contacto, c.pais_codigo, e.nombre as empresa_nombre, e.smsenlinea_secret, e.smsenlinea_wa_account, f.hash_publico 
            FROM facturas f
            JOIN clientes c ON f.cliente_id = c.id
            JOIN empresas e ON f.empresa_id = e.id
            WHERE f.id = ? AND f.empresa_id = ?
        ");
        $stmt->execute([$id, $empresa_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) throw new Exception("Factura no encontrada");
        
        $link = "https://" . $_SERVER['HTTP_HOST'] . "/ver-factura.php?h=" . $data['hash_publico'];
        $mensaje = "Hola {$data['cliente_nombre']}, adjunto encontrarás tu factura *{$data['numero_factura']}* de {$data['empresa_nombre']} por valor de $" . number_format($data['total'], 2) . ".\n\nPuedes verla y descargarla aquí: $link";
        
        $results = [];

        // ENVIAR WHATSAPP
        if (($method === 'whatsapp' || $method === 'both') && $data['celular_contacto']) {
            try {
                require_once 'includes/whatsapp_helper.php';
                enviarWhatsApp($pdo, $empresa_id, $data['celular_contacto'], $mensaje, null, 'text', $data['pais_codigo'] ?: '57');
                $results['whatsapp'] = 'Enviado';
            } catch (Exception $e) {
                $results['whatsapp'] = 'Error: ' . $e->getMessage();
            }
        }
        
        // ENVIAR EMAIL
        if (($method === 'email' || $method === 'both') && $data['cliente_email']) {
             if (file_exists('includes/mail_helper.php')) {
                require_once 'includes/mail_helper.php';
                $cuerpo = "<p>Hola <strong>{$data['cliente_nombre']}</strong>,</p>
                           <p>Has recibido una nueva factura de <strong>{$data['empresa_nombre']}</strong>.</p>
                           <p><strong>N° Factura:</strong> {$data['numero_factura']}<br>
                           <strong>Total:</strong> $".number_format($data['total'], 2)."</p>
                           <p>Puedes ver el detalle en el siguiente enlace:</p>
                           <p><a href='$link' style='background:#4f46e5; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Ver Factura</a></p>";
                try {
                    $emailInfo = [
                        'smtp_host' => $data['smtp_host'] ?? '', 
                         // ... (necesitaríamos traer más datos de empresa si no están en la query anterior, asumamos que helper lo maneja o traerlos)
                         // Para simplificar, asumiremos que enviarEmailGenerico maneja la config de la empresa si pasamos el ID o la data completa
                    ];
                    // Re-query empresa completa para mail helper si es necesario
                    $stmtEmp = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
                    $stmtEmp->execute([$empresa_id]);
                    $empresaData = $stmtEmp->fetch(PDO::FETCH_ASSOC);
                    $empresaData['cliente_email'] = $data['cliente_email'];
                    
                    enviarEmailGenerico($empresaData, "Nueva Factura {$data['numero_factura']}", $cuerpo);
                    $results['email'] = 'Enviado';
                } catch (Exception $e) {
                    $results['email'] = 'Error: ' . $e->getMessage();
                }
             }
        }
        
        // Actualizar estado a Enviada si estaba en Borrador
        if ($data['estado'] === 'Borrador') {
            $pdo->prepare("UPDATE facturas SET estado = 'Enviada' WHERE id = ?")->execute([$id]);
        }
        
        echo json_encode(['status' => 'success', 'results' => $results]);
        
    } elseif ($action === 'update_status') {
        $id = $_POST['id'];
        $new_status = $_POST['status'];
        
        $allowed = ['Borrador', 'Creada', 'Enviada', 'Pendiente de pago', 'Pagada', 'Anulada', 'Vencida'];
        if (!in_array($new_status, $allowed)) throw new Exception("Estado inválido: " . $new_status);

        $pdo->prepare("UPDATE facturas SET estado = ? WHERE id = ? AND empresa_id = ?")->execute([$new_status, $id, $empresa_id]);

        echo json_encode(['status' => 'success']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
