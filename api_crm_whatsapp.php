<?php
/**
 * API CRM WhatsApp - CoticeFacil.com (SMSEnLinea Version)
 */

require_once 'db.php';
require_once 'includes/whatsapp_helper.php';

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'] ?? null;
$usuario_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!$empresa_id) {
    echo json_encode(['status' => 'error', 'message' => 'Empresa no identificada']);
    exit;
}

// Obtener credenciales de la empresa
$stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
$stmt->execute([$empresa_id]);
$creds = $stmt->fetch();
$wa_provider = $creds['wa_provider'] ?? 'smsenlinea';

function formatWhatsAppId($jid, $canSeeFull) {
    $number = preg_replace('/[^0-9+]/', '', $jid);
    if ($canSeeFull) return $number;
    return substr($number, 0, -4) . "****";
}

switch ($action) {
    case 'get_chat_by_phone':
        $phone = preg_replace('/[^0-9+]/', '', $_GET['phone'] ?? '');
        if (!$phone) exit(json_encode(['status' => 'error', 'message' => 'Teléfono inválido']));
        
        $stmt = $pdo->prepare("SELECT id FROM wa_chats WHERE empresa_id = ? AND whatsapp_id LIKE ?");
        $stmt->execute([$empresa_id, "%" . ltrim($phone, '+') . "%"]);
        $chat = $stmt->fetch();
        
        if ($chat) {
            echo json_encode(['status' => 'success', 'chat_id' => $chat['id']]);
        } else {
            // Check if client exists
            $stmt_cli = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id = ? AND (celular_contacto LIKE ? OR telefono LIKE ?)");
            $stmt_cli->execute([$empresa_id, "%$phone%", "%$phone%"]);
            $cliente = $stmt_cli->fetch();

            $stmt_ins = $pdo->prepare("INSERT INTO wa_chats (empresa_id, cliente_id, whatsapp_id, fecha_ultimo_mensaje) VALUES (?, ?, ?, NOW())");
            $stmt_ins->execute([$empresa_id, $cliente ? $cliente['id'] : null, $phone]);
            echo json_encode(['status' => 'success', 'chat_id' => $pdo->lastInsertId()]);
        }
        break;

    case 'instance_status':
        if ($wa_provider === 'evolution') {
            if (!empty($creds['evolution_api_url']) && !empty($creds['evolution_instance_name'])) {
                $wa = new EvolutionAPI($creds['evolution_api_url'], $creds['evolution_api_key'], $creds['evolution_instance_name'], $creds['evolution_instance_token']);
                $res = $wa->getInstanceStatus();
                if (($res['instance']['state'] ?? '') === 'open') {
                    echo json_encode(['status' => 'success', 'connection' => 'open']);
                } else {
                    echo json_encode(['status' => 'disconnected', 'message' => 'Desconectado', 'raw' => $res]);
                }
            } else {
                echo json_encode(['status' => 'no_instance', 'message' => 'Credenciales no configuradas']);
            }
        } else {
            // SMSEnLinea is cloud-based, if we have credentials we consider it "open"
            if (!empty($creds['smsenlinea_secret']) && !empty($creds['smsenlinea_wa_account'])) {
                echo json_encode(['status' => 'success', 'connection' => 'open', 'provider' => 'smsenlinea']);
            } else {
                echo json_encode(['status' => 'no_instance', 'message' => 'Credenciales SMSEnLinea no configuradas']);
            }
        }
        break;
    
    case 'get_qr':
        if ($wa_provider === 'evolution') {
            if (!empty($creds['evolution_api_url']) && !empty($creds['evolution_instance_name'])) {
                $wa = new EvolutionAPI($creds['evolution_api_url'], $creds['evolution_api_key'], $creds['evolution_instance_name'], $creds['evolution_instance_token']);
                $res = $wa->getQR();
                echo json_encode($res);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Configuración Evolution incompleta']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'QR no disponible para este proveedor']);
        }
        break;

    case 'get_chats':
        // (get_chats logic remains mostly same, just ensuring database query uses correct fields if any)
        // ...

    case 'get_chats':
        $where = "WHERE c.empresa_id = ?";
        $params = [$empresa_id];

        // Check permissions
        $is_super_admin = $_SESSION['is_super_admin'] ?? false;
        $role = strtolower($_SESSION['user_rol'] ?? '');
        $is_admin = $is_super_admin || strpos($role, 'admin') !== false || $role === 'asesor';
        
        $permiso_ver_todos = tienePermiso('ver_todos_chats');
        $permiso_ver_telefono = tienePermiso('ver_numero_wa');
        $usuario_actual = $_SESSION['user_id'];

        // Debug Log
        error_log("WhatsApp API [get_chats]: User ID: $usuario_actual, Role: $role, SuperAdmin: " . ($is_super_admin ? 'Yes' : 'No'));

        if (!$is_admin && !$permiso_ver_todos) {
            // CAMBIO: Mostrar chats asignados al usuario O chats que NO tienen ninguna asignación aún.
            $where .= " AND (EXISTS (SELECT 1 FROM wa_chat_asignaciones wca WHERE wca.chat_id = c.id AND wca.usuario_id = ?) 
                        OR NOT EXISTS (SELECT 1 FROM wa_chat_asignaciones wca2 WHERE wca2.chat_id = c.id))";
            $params[] = $usuario_actual;
            error_log("WhatsApp API [get_chats]: Filtering by assignment (User or Unassigned).");
        }
        
        try {
            // Fetch chats with assigned agents aggregated
            // Usamos un separador más robusto para no romper con nombres que tengan dos puntos
            $stmt = $pdo->prepare("
                SELECT c.*, cl.nombre as cliente_nombre,
                (SELECT GROUP_CONCAT(CONCAT(u.id, '{SEP}', u.nombre) SEPARATOR '|') 
                 FROM wa_chat_asignaciones wca 
                 JOIN usuarios u ON wca.usuario_id = u.id 
                 WHERE wca.chat_id = c.id) as asignados_raw
                FROM wa_chats c
                LEFT JOIN clientes cl ON c.cliente_id = cl.id 
                $where 
                ORDER BY c.fecha_ultimo_mensaje DESC
            ");
            $stmt->execute($params);
            $chats = $stmt->fetchAll();
            error_log("WhatsApp API [get_chats]: Found " . count($chats) . " result(s).");
        } catch (PDOException $e) {
            error_log("WhatsApp API [get_chats] Error: " . $e->getMessage());
            $chats = [];
        }

        foreach ($chats as &$chat) {
            // Process assignments
            $chat['asignados'] = [];
            if ($chat['asignados_raw']) {
                $pairs = explode('|', $chat['asignados_raw']);
                foreach ($pairs as $p) {
                    if (strpos($p, '{SEP}') !== false) {
                        list($aid, $aname) = explode('{SEP}', $p);
                        $chat['asignados'][] = ['id' => $aid, 'nombre' => $aname];
                    }
                }
            }
            
            // Masking logic
            if (!$permiso_ver_telefono) {
                // Mask the ID itself to prevent frontend from accessing it
                $chat['whatsapp_id'] = substr($chat['whatsapp_id'], 0, strlen($chat['whatsapp_id']) - 4) . "****";
            }

            if ($chat['cliente_nombre']) {
               $chat['whatsapp_display'] = $chat['cliente_nombre'];
            } else {
                // Remove extra + if present
                $wa_clean = ltrim($chat['whatsapp_id'], '+');
                $chat['whatsapp_display'] = "+" . $wa_clean;
            }
            
            $chat['can_see_phone'] = $permiso_ver_telefono;
        }

        echo json_encode(['status' => 'success', 'data' => $chats]);
        break;

    case 'assign_chat':
        $chat_id = $_POST['chat_id'] ?? 0;
        $agent_id = $_POST['agent_id'] ?? 0;
        $action = $_POST['mode'] ?? 'add'; // 'add' or 'remove'
        
        if ($action === 'add') {
             $stmt = $pdo->prepare("INSERT IGNORE INTO wa_chat_asignaciones (chat_id, usuario_id) VALUES (?, ?)");
             $stmt->execute([$chat_id, $agent_id]);
        } else {
             $stmt = $pdo->prepare("DELETE FROM wa_chat_asignaciones WHERE chat_id = ? AND usuario_id = ?");
             $stmt->execute([$chat_id, $agent_id]);
        }
        
        echo json_encode(['status' => 'success']);
        break;

    case 'upload_and_send':
        if (!tienePermiso('acceso_whatsapp')) {
            exit(json_encode(['status' => 'error', 'message' => 'No tienes permiso']));
        }

        $chat_id = $_POST['chat_id'] ?? 0;
        $telefono = $_POST['telefono'] ?? '';
        
        error_log("WhatsApp API [upload_and_send]: Chat ID: $chat_id, Phone: $telefono");

        if (empty($_FILES['file']) || empty($telefono)) {
            error_log("WhatsApp API [upload_and_send]: Missing file or phone.");
            exit(json_encode(['status' => 'error', 'message' => 'Faltan datos']));
        }

        $file = $_FILES['file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'mp4'];
        
        if (!in_array($ext, $allowed)) {
            error_log("WhatsApp API [upload_and_send]: Invalid extension $ext");
            exit(json_encode(['status' => 'error', 'message' => 'Tipo de archivo no permitido']));
        }

        // Upload path
        $path = getCompanyUploadPath("whatsapp/$chat_id", true);
        $filename = uniqid('wa_') . '.' . $ext;
        $full_path = $path . $filename;
        
        error_log("WhatsApp API [upload_and_send]: Target Path: $full_path");

        if (move_uploaded_file($file['tmp_name'], $full_path)) {
            // Get public URL
            $rel_path = getCompanyUploadPath("whatsapp/$chat_id", false) . $filename;
            $public_url = getBaseUrl() . '/' . $rel_path; 
            
            error_log("WhatsApp API [upload_and_send]: Public URL: $public_url");

            // Send via Helper
            $type_wa = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'image' : (in_array($ext, ['mp4']) ? 'video' : 'document');
            
            $res = enviarWhatsApp($pdo, $empresa_id, $telefono, $file['name'], $public_url, $type_wa);
            error_log("WhatsApp API [upload_and_send]: Unified Helper Response: " . json_encode($res));
            
            if (($res['status'] ?? 0) == 200 || ($res['status'] ?? '') == 'success' || isset($res['key'])) {
                // Estándar con migrate_v11: url_archivo y tipo_mensaje
                $stmt = $pdo->prepare("INSERT INTO wa_mensajes (chat_id, empleado_id, direccion, contenido, url_archivo, tipo_mensaje, fecha_envio) VALUES (?, ?, 'saliente', ?, ?, ?, NOW())");
                $stmt->execute([$chat_id, $usuario_id, $file['name'], $public_url, $type_wa]);
                
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error API: ' . ($res['message'] ?? json_encode($res))]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al mover archivo']);
        }
        break;

    case 'send_quote_link':
        $cot_id = $_POST['cot_id'] ?? 0;
        $telefono = $_POST['telefono'] ?? '';
        $chat_id = $_POST['chat_id'] ?? 0;

        // Get Quote Hash/ID
        $stmt = $pdo->prepare("SELECT numero_cotizacion, id, total, cliente_id FROM cotizaciones WHERE id = ? AND empresa_id = ?");
        $link = getBaseUrl() . "/propuesta.php?id=" . $cot['id'];
        $msg = "📄 *Cotización #{$cot['numero_cotizacion']}*\nTotal: $" . number_format($cot['total'], 2) . "\n\nPuedes verla aquí:\n$link";

        // Send Text
        $res = enviarWhatsApp($pdo, $empresa_id, $telefono, $msg);

        if (($res['status'] ?? 0) == 200 || ($res['status'] ?? '') == 'success' || isset($res['key'])) {
            $stmt = $pdo->prepare("INSERT INTO wa_mensajes (chat_id, empleado_id, direccion, contenido, fecha_envio) VALUES (?, ?, 'saliente', ?, NOW())");
            $stmt->execute([$chat_id, $usuario_id, $msg]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error API: ' . ($res['message'] ?? json_encode($res))]);
        }
        break;

    case 'send_ot_link':
        $ot_id = $_POST['ot_id'] ?? 0;
        $telefono = $_POST['telefono'] ?? '';
        $chat_id = $_POST['chat_id'] ?? 0;

        $stmt = $pdo->prepare("SELECT numero_ot, notas FROM ordenes_trabajo WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$ot_id, $empresa_id]);
        $ot = $stmt->fetch();

        if (!$ot) exit(json_encode(['status' => 'error', 'message' => 'Orden no encontrada']));

        $msg = "🛠 *Orden de Trabajo #{$ot['numero_ot']}*\n\n{$ot['notas']}";
        $res = enviarWhatsApp($pdo, $empresa_id, $telefono, $msg);

        if (($res['status'] ?? 0) == 200 || ($res['status'] ?? '') == 'success' || isset($res['key'])) {
            $stmt = $pdo->prepare("INSERT INTO wa_mensajes (chat_id, empleado_id, direccion, contenido, fecha_envio) VALUES (?, ?, 'saliente', ?, NOW())");
            $stmt->execute([$chat_id, $usuario_id, $msg]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error API: ' . ($res['message'] ?? json_encode($res))]);
        }
        break;

    case 'get_client_quotes':
        $chat_id = $_GET['chat_id'] ?? 0;
        // Get client_id from chat
        $stmt = $pdo->prepare("SELECT cliente_id FROM wa_chats WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$chat_id, $empresa_id]);
        $chat = $stmt->fetch();
        
        if (!$chat || !$chat['cliente_id']) {
            echo json_encode(['status' => 'success', 'data' => []]); // No client linked
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, numero_cotizacion, total, fecha, estado FROM cotizaciones WHERE cliente_id = ? AND empresa_id = ? ORDER BY id DESC LIMIT 10");
        $stmt->execute([$chat['cliente_id'], $empresa_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        break;

    case 'get_client_ots':
        $chat_id = $_GET['chat_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT cliente_id FROM wa_chats WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$chat_id, $empresa_id]);
        $chat = $stmt->fetch();
        
        if (!$chat || !$chat['cliente_id']) {
             echo json_encode(['status' => 'success', 'data' => []]);
             exit;
        }

        $stmt = $pdo->prepare("SELECT id, numero_ot, modelo_dispositivo, estado, fecha_inicio FROM ordenes_trabajo WHERE cliente_id = ? AND empresa_id = ? ORDER BY id DESC LIMIT 10");
        $stmt->execute([$chat['cliente_id'], $empresa_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        break;

    case 'save_full_client':
        $chat_id = $_POST['chat_id'] ?? 0;
        $nombre = trim($_POST['nombre'] ?? '');
        $identificacion = trim($_POST['identificacion'] ?? '');
        $pais_codigo = trim($_POST['pais_codigo'] ?? '');
        $nombre_contacto = trim($_POST['nombre_contacto'] ?? '');
        $celular = trim($_POST['celular_contacto'] ?? '');
        $cargo = trim($_POST['cargo_contacto'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        if (!$nombre) exit(json_encode(['status' => 'error', 'message' => 'Nombre requerido']));

        // Obtener chat actual
        $stmt_c = $pdo->prepare("SELECT id, whatsapp_id FROM wa_chats WHERE id = ? AND empresa_id = ?");
        $stmt_c->execute([$chat_id, $empresa_id]);
        $chat = $stmt_c->fetch();

        if (!$chat) exit(json_encode(['status' => 'error', 'message' => 'Chat no encontrado']));

        // Verificar duplicados (Celular o Email)
        $msg_duplicado = "";
        $cliente_id_existente = null;

        // Limpiar celular para búsqueda
        $celular_search = preg_replace('/[^0-9]/', '', $celular);
        $wa_id_search = preg_replace('/[^0-9]/', '', $chat['whatsapp_id']);
        
        // Construir query de búsqueda
        $query_check = "SELECT id, nombre, celular_contacto FROM clientes WHERE empresa_id = ?";
        $params_check = [$empresa_id];
        $conditions = [];

        if ($celular_search) {
            $conditions[] = "celular_contacto LIKE ?";
            $params_check[] = "%$celular_search%";
        }
        if ($email) {
            $conditions[] = "email = ?";
            $params_check[] = $email;
        }
        // También buscar por el whatsapp_id del chat si no se proveyó celular
        if (!$celular_search) {
             $conditions[] = "celular_contacto LIKE ?";
             $params_check[] = "%$wa_id_search%";
        }

        if (!empty($conditions)) {
            $query_check .= " AND (" . implode(" OR ", $conditions) . ")";
            $stmt_chk = $pdo->prepare($query_check);
            $stmt_chk->execute($params_check);
            $existente = $stmt_chk->fetch();

            if ($existente) {
                $cliente_id_existente = $existente['id'];
                $msg_duplicado = "Cliente ya existe: " . $existente['nombre'];
            }
        }

        if ($cliente_id_existente) {
            // Asociar chat al cliente existente
            $stmt_upd = $pdo->prepare("UPDATE wa_chats SET cliente_id = ? WHERE id = ?");
            $stmt_upd->execute([$cliente_id_existente, $chat_id]);
            
            echo json_encode([
                'status' => 'duplicate_linked', 
                'message' => $msg_duplicado . ". El chat ha sido asociado.",
                'cliente_id' => $cliente_id_existente
            ]);
        } else {
            // Crear nuevo cliente
            // Si el celular viene del form, usar ese, sino el del chat si tiene +
            $cel_final = $celular ? $celular : '+' . $chat['whatsapp_id'];
            $token = md5(uniqid(rand(), true));

            $stmt_ins = $pdo->prepare("INSERT INTO clientes (
                empresa_id, usuario_id, nombre, identificacion, pais_codigo, 
                nombre_contacto, cargo_contacto, celular_contacto, 
                telefono, email, direccion, es_cliente, token_acceso
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
            
            try {
                $stmt_ins->execute([
                    $empresa_id, $_SESSION['user_id'], $nombre, $identificacion, $pais_codigo,
                    $nombre_contacto, $cargo, $cel_final,
                    $telefono, $email, $direccion, $token
                ]);
                $nuevo_id = $pdo->lastInsertId();

                // Asociar
                $pdo->prepare("UPDATE wa_chats SET cliente_id = ? WHERE id = ?")->execute([$nuevo_id, $chat_id]);

                // Notificación de Bienvenida
                if (!empty($_POST['enviar_notificacion'])) {
                    require_once 'includes/client_notifications.php';
                    enviarNotificacionBienvenida($pdo, $nuevo_id);
                }

                echo json_encode([
                    'status' => 'success', 
                    'message' => 'Cliente registrado exitosamente',
                    'cliente_id' => $nuevo_id
                ]);
            } catch (PDOException $e) {
                echo json_encode(['status' => 'error', 'message' => 'Error BD: ' . $e->getMessage()]);
            }
        }
        break;

    case 'get_agents':
        $stmt = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE empresa_id = ? AND activo = 1");
        $stmt->execute([$empresa_id]);
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll()]);
        break;
        
    case 'create_new_chat':
        $telefono_raw = $_POST['phone'] ?? '';
        $texto = $_POST['text'] ?? $_POST['message'] ?? '';
        $whatsapp_id = preg_replace('/[^0-9+]/', '', $telefono_raw);
        if (!$whatsapp_id) exit(json_encode(['status' => 'error', 'message' => 'Teléfono inválido']));

        $stmt_find = $pdo->prepare("SELECT id FROM wa_chats WHERE empresa_id = ? AND whatsapp_id = ?");
        $stmt_find->execute([$empresa_id, $whatsapp_id]);
        $chat = $stmt_find->fetch();

        if (!$chat) {
            $stmt_cli = $pdo->prepare("SELECT id FROM clientes WHERE empresa_id = ? AND (celular_contacto LIKE ? OR telefono LIKE ?)");
            $stmt_cli->execute([$empresa_id, "%$whatsapp_id%", "%$whatsapp_id%"]);
            $cliente = $stmt_cli->fetch();

            $stmt_ins = $pdo->prepare("INSERT INTO wa_chats (empresa_id, cliente_id, whatsapp_id, asignado_a, tipo_asignacion, fecha_ultimo_mensaje) VALUES (?, ?, ?, ?, 'manual', NOW())");
            $stmt_ins->execute([$empresa_id, $cliente ? $cliente['id'] : null, $whatsapp_id, $usuario_id]);
            $chat_id = $pdo->lastInsertId();
        } else {
            $chat_id = $chat['id'];
        }

        // Proceder a enviar mensaje
        $_POST['chat_id'] = $chat_id;
        $_POST['text'] = $texto;

    case 'send_message':
        $chat_id = $_POST['chat_id'] ?? 0;
        $texto = $_POST['text'] ?? '';
        
        $stmt_c = $pdo->prepare("SELECT whatsapp_id FROM wa_chats WHERE id = ? AND empresa_id = ?");
        $stmt_c->execute([$chat_id, $empresa_id]);
        $chat_row = $stmt_c->fetch();
        if (!$chat_row) {
            error_log("WhatsApp API [send_message]: Chat not found in DB.");
            exit(json_encode(['status' => 'error', 'message' => 'Chat no encontrado']));
        }
        
        $nombre_empleado = $_SESSION['user_nombre'] ?? 'Agente';
        $mensaje_final = "*$nombre_empleado*: $texto";

        error_log("WhatsApp API [send_message]: Sending to {$chat_row['whatsapp_id']}");
        $res = enviarWhatsApp($pdo, $empresa_id, $chat_row['whatsapp_id'], $mensaje_final);
        error_log("WhatsApp API [send_message]: Unified Result: " . json_encode($res));
        
        if (($res['status'] ?? 0) == 200 || ($res['status'] ?? '') == 'success' || isset($res['key'])) {
            $stmt_msg = $pdo->prepare("INSERT INTO wa_mensajes (chat_id, empleado_id, direccion, contenido, nombre_empleado_copia, fecha_envio) VALUES (?, ?, 'saliente', ?, ?, NOW())");
            $stmt_msg->execute([$chat_id, $usuario_id, $texto, $nombre_empleado]);
            
            $pdo->prepare("UPDATE wa_chats SET ultimo_mensaje = ?, fecha_ultimo_mensaje = NOW() WHERE id = ?")
                ->execute([$texto, $chat_id]);
                
            echo json_encode(['status' => 'success', 'chat_id' => $chat_id]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $res['message'] ?? 'Error API - Intenta de nuevo']);
        }
        break;

    case 'get_messages':
        $chat_id = $_GET['chat_id'] ?? 0;
        // Seguridad: Verificar que el chat pertenece a la empresa
        $stmt = $pdo->prepare("SELECT m.*, u.nombre as asesor_nombre 
                               FROM wa_mensajes m 
                               JOIN wa_chats c ON m.chat_id = c.id
                               LEFT JOIN usuarios u ON m.empleado_id = u.id 
                               WHERE m.chat_id = ? AND c.empresa_id = ? 
                               ORDER BY m.fecha_envio ASC");
        $stmt->execute([$chat_id, $empresa_id]);
        $messages = $stmt->fetchAll();
        
        // Marcar como leído
        $pdo->prepare("UPDATE wa_chats SET visto_por_admin = 1 WHERE id = ? AND empresa_id = ?")
            ->execute([$chat_id, $empresa_id]);
        
        echo json_encode(['status' => 'success', 'data' => $messages]);
        break;

    case 'delete_message':
        if (!tienePermiso('eliminar_chat')) {
            exit(json_encode(['status' => 'error', 'message' => 'No tienes permiso para eliminar mensajes']));
        }
        $msg_id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM wa_mensajes WHERE id = ? AND chat_id IN (SELECT id FROM wa_chats WHERE empresa_id = ?)");
        $stmt->execute([$msg_id, $empresa_id]);
        echo json_encode(['status' => 'success']);
        break;

    case 'delete_chat':
        if (!tienePermiso('eliminar_chat')) {
            exit(json_encode(['status' => 'error', 'message' => 'No tienes permiso para eliminar chats']));
        }
        $chat_id = $_POST['id'] ?? 0;
        $pdo->prepare("DELETE FROM wa_mensajes WHERE chat_id = ?")->execute([$chat_id]);
        $pdo->prepare("DELETE FROM wa_chats WHERE id = ? AND empresa_id = ?")->execute([$chat_id, $empresa_id]);
        echo json_encode(['status' => 'success']);        break;
}


