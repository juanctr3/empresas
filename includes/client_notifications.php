<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/mail_helper.php';
require_once __DIR__ . '/whatsapp_helper.php';

/**
 * Envia la notificación de bienvenida (Email y WhatsApp) a un cliente/prospecto
 * 
 * @param PDO $pdo Conexión a BD
 * @param int $cliente_id ID del cliente generado
 * @return array Resultado de las operaciones ['email' => bool, 'whatsapp' => bool]
 */
function enviarNotificacionBienvenida($pdo, $cliente_id) {
    try {
        // 1. Obtener datos del cliente y empresa
        $stmt = $pdo->prepare("
            SELECT c.*, e.*, e.nombre as empresa_nombre
            FROM clientes c
            JOIN empresas e ON c.empresa_id = e.id
            WHERE c.id = ?
        ");
        $stmt->execute([$cliente_id]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$info) return ['email' => false, 'whatsapp' => false, 'error' => 'Cliente no encontrado'];

        // Asegurar que tenga token de acceso
        if (empty($info['token_acceso'])) {
            $token = md5(uniqid(rand(), true));
            $pdo->prepare("UPDATE clientes SET token_acceso = ? WHERE id = ?")->execute([$token, $cliente_id]);
            $info['token_acceso'] = $token;
        }

        $link_portal = getBaseUrl() . 'mi-area.php?t=' . $info['token_acceso'];
        $tipo_usuario = $info['es_cliente'] ? 'Cliente' : 'Prospecto'; // O 'Usuario' para ser más genérico
        $nombre_cliente = $info['nombre_contacto'] ?: $info['nombre'];

        // --- 2. Enviar Email ---
        $resultado_email = false;
        if (!empty($info['email'])) {
            $asunto = "¡Bienvenido a " . $info['empresa_nombre'] . "!";
            
            $logo_html = "";
            if (!empty($info['logo_url'])) {
                $logo_html = "<div style='text-align: center; margin-bottom: 30px;'><img src='{$info['logo_url']}' alt='{$info['empresa_nombre']}' style='max-height: 80px;'></div>";
            }

            $cuerpo_html = "
            <div style='background-color: #f3f4f6; padding: 40px 0; font-family: \"Segoe UI\", Tahoma, Geneva, Verdana, sans-serif;'>
                <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.05);'>
                    <div style='background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 40px 20px; text-align: center;'>
                        <h1 style='color: #ffffff; margin: 0; font-size: 28px; font-weight: 800;'>¡Bienvenido!</h1>
                        <p style='color: rgba(255,255,255,0.9); font-size: 16px; margin-top: 10px;'>Nos alegra tenerte con nosotros</p>
                    </div>
                    
                    <div style='padding: 40px;'>
                        $logo_html
                        
                        <h2 style='color: #1f2937; font-size: 22px; margin-top: 0; text-align: center;'>Hola, $nombre_cliente</h2>
                        
                        <p style='color: #4b5563; font-size: 16px; line-height: 1.6; text-align: center;'>
                            Has sido registrado exitosamente en nuestra plataforma. Ahora tienes acceso exclusivo a tu área de clientes donde podrás revisar:
                        </p>
                        
                        <div style='display: flex; justify-content: center; margin: 30px 0;'>
                            <ul style='list-style: none; padding: 0; text-align: left; display: inline-block; color: #4b5563;'>
                                <li style='margin-bottom: 10px; display: flex; align-items: center;'>
                                    <span style='color: #4f46e5; margin-right: 10px; font-weight: bold;'>✓</span> Tus Cotizaciones
                                </li>
                                <li style='margin-bottom: 10px; display: flex; align-items: center;'>
                                    <span style='color: #4f46e5; margin-right: 10px; font-weight: bold;'>✓</span> Órdenes de Trabajo
                                </li>
                                <li style='margin-bottom: 10px; display: flex; align-items: center;'>
                                    <span style='color: #4f46e5; margin-right: 10px; font-weight: bold;'>✓</span> Facturas y Documentos
                                </li>
                            </ul>
                        </div>

                        <div style='text-align: center; margin-top: 20px;'>
                            <a href='$link_portal' style='background-color: #4f46e5; color: #ffffff; display: inline-block; padding: 16px 32px; border-radius: 12px; text-decoration: none; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(79, 70, 229, 0.25); transition: background-color 0.3s;'>
                                Ingresar a mi Cuenta
                            </a>
                        </div>
                        
                        <p style='text-align: center; margin-top: 30px; font-size: 14px; color: #9ca3af;'>
                            Si el botón no funciona, copia y pega este enlace:<br>
                            <a href='$link_portal' style='color: #4f46e5;'>$link_portal</a>
                        </p>
                    </div>
                    
                    <div style='background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;'>
                        <p style='color: #9ca3af; font-size: 12px; margin: 0;'>
                            © " . date('Y') . " {$info['empresa_nombre']}. Todos los derechos reservados.
                        </p>
                    </div>
                </div>
            </div>
            ";

            // Preparar array info para el helper de mail existente
            $mail_info = [
                'smtp_host' => $info['smtp_host'],
                'smtp_user' => $info['smtp_user'],
                'smtp_pass' => $info['smtp_pass'],
                'smtp_port' => $info['smtp_port'],
                'smtp_from_email' => $info['smtp_from_email'],
                'cliente_email' => $info['email'],
                'empresa_nombre' => $info['empresa_nombre']
            ];

            $resultado_email = enviarEmailGenerico($mail_info, $asunto, $cuerpo_html);
        }

        // --- 3. Enviar WhatsApp ---
        $resultado_wa = false;
        // Prioridad: Celular Contacto > Teléfono
        $telefono_wa = $info['celular_contacto'] ?: $info['telefono'];
        
        if (!empty($telefono_wa)) {
            $msg = "👋 *Hola $nombre_cliente,*\n\n";
            $msg .= "Te damos la bienvenida a *{$info['empresa_nombre']}*. Hemos creado tu cuenta de cliente para que puedas gestionar tus servicios con nosotros.\n\n";
            $msg .= "🔗 *Accede a tu portal aquí:*\n$link_portal\n\n";
            $msg .= "Aquí podrás ver tus cotizaciones, órdenes y más. ¡Gracias por confiar en nosotros!";

            $res = enviarWhatsApp($pdo, $info['empresa_id'], $telefono_wa, $msg);
            $resultado_wa = (($res['status'] ?? 0) == 200 || ($res['status'] ?? '') == 'success' || isset($res['key']));
            
            // Log message to chat history if successful
            if ($resultado_wa) {
                // Find or create chat
                $chat_stmt = $pdo->prepare("SELECT id FROM wa_chats WHERE empresa_id = ? AND cliente_id = ?");
                $chat_stmt->execute([$info['empresa_id'], $info['id']]);
                $chat = $chat_stmt->fetch();
                $chat_id = null;

                if ($chat) {
                    $chat_id = $chat['id'];
                } else {
                    // Try finding by phone
                    $clean_phone = preg_replace('/[^0-9]/', '', $telefono_wa);
                    $chat_search = $pdo->prepare("SELECT id FROM wa_chats WHERE empresa_id = ? AND whatsapp_id LIKE ?");
                    $chat_search->execute([$info['empresa_id'], "%$clean_phone%"]);
                    $chat_found = $chat_search->fetch();
                    
                    if ($chat_found) {
                        $chat_id = $chat_found['id'];
                        // Link client
                        $pdo->prepare("UPDATE wa_chats SET cliente_id = ? WHERE id = ?")->execute([$info['id'], $chat_id]);
                    } else {
                        // Create new chat
                        $chat_ins = $pdo->prepare("INSERT INTO wa_chats (empresa_id, cliente_id, whatsapp_id, fecha_ultimo_mensaje) VALUES (?, ?, ?, NOW())");
                        $chat_ins->execute([$info['empresa_id'], $info['id'], $clean_phone]);
                        $chat_id = $pdo->lastInsertId();
                    }
                }

                if ($chat_id) {
                    $pdo->prepare("INSERT INTO wa_mensajes (chat_id, empleado_id, direccion, contenido, fecha_envio) VALUES (?, ?, 'saliente', ?, NOW())")
                        ->execute([$chat_id, $_SESSION['user_id'] ?? 0, $msg]);
                }
            }
        }

        return ['email' => $resultado_email, 'whatsapp' => $resultado_wa];
    } catch (Exception $e) {
        error_log("Error en enviarNotificacionBienvenida: " . $e->getMessage());
        return ['email' => false, 'whatsapp' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Envia alerta de vencimiento próximo (2 días antes)
 */
function enviarAlertaVencimiento($pdo, $cotizacion, $cliente) {
    // Generar link seguro si es necesario o usar hash
    $link_cotizacion = getBaseUrl() . 'propuesta.php?h=' . $cotizacion['hash_publico'];
    
    // --- Email ---
    $resultado_email = false;
    if (!empty($cliente['email'])) {
        $asunto = "⚠️ Tu cotización #{$cotizacion['numero_cotizacion']} vence pronto";
        $cuerpo_html = "
        <div style='font-family: Arial, sans-serif; background: #f9fafb; padding: 40px 0;'>
            <div style='background: white; max-width: 600px; margin: 0 auto; border-radius: 10px; padding: 30px; border: 1px solid #e5e7eb;'>
                <h2 style='color: #ea580c;'>⏳ Tu cotización está por vencer</h2>
                <p>Hola <strong>{$cliente['nombre']}</strong>,</p>
                <p>Te recordamos que la cotización <strong>#{$cotizacion['numero_cotizacion']}</strong> por un total de <strong>{$cotizacion['total']}</strong> está próxima a vencer el <strong>" . date('d/m/Y', strtotime($cotizacion['fecha_vencimiento'])) . "</strong>.</p>
                <p>Asegura las condiciones actuales aceptándola antes de la fecha límite.</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$link_cotizacion' style='background: #4f46e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Ver Cotización</a>
                </div>
            </div>
        </div>";

        // Info básica para mail_helper
        $col_email = 'email'; // Depende de tu esquema de empresa, asumiendo standard SMTP de empresa
        // Necesitamos datos de empresa para SMTP. Asumimos que $cliente trae JOIN de empresa o los pasamos.
        // CORRECCION: $cliente aquí debe traer datos SMTP de empresa. Lo validaremos en el cron.
        
        $mail_info = [
            'smtp_host' => $cliente['smtp_host'],
            'smtp_user' => $cliente['smtp_user'],
            'smtp_pass' => $cliente['smtp_pass'],
            'smtp_port' => $cliente['smtp_port'],
            'smtp_from_email' => $cliente['smtp_from_email'],
            'cliente_email' => $cliente['email'],
            'empresa_nombre' => $cliente['empresa_nombre']
        ];
        $resultado_email = enviarEmailGenerico($mail_info, $asunto, $cuerpo_html);
    }

    // --- WhatsApp ---
    $resultado_wa = false;
    $telefono_wa = $cliente['celular_contacto'] ?: $cliente['telefono'];
    
    if ($telefono_wa) {
        $msg = "⏳ *Cotización por Vencer*\n\nHola {$cliente['nombre']}, tu cotización *#{$cotizacion['numero_cotizacion']}* vence el " . date('d/m/Y', strtotime($cotizacion['fecha_vencimiento'])) . ". \n\n👉 *Revísala aquí:* $link_cotizacion";
        
        $res = enviarWhatsApp($pdo, $cliente['empresa_id'], $telefono_wa, $msg);
        $resultado_wa = (($res['status'] ?? 0) == 200 || ($res['status'] ?? '') == 'success' || isset($res['key']));
        
        // Log WA message similar to welcome... (Omitted for brevity unless critical)
    }

    return ['email' => $resultado_email, 'whatsapp' => $resultado_wa];
}

/**
 * Notifica solicitud de recotización (Cliente -> Empresa)
 */
function enviarSolicitudRecotizacion($pdo, $cotizacion, $cliente, $mensaje_cliente) {
    // 1. Notificar al Empleado/Admin (Quien creó la cotización)
    // Obtenemos datos del usuario creador
    $stmtU = $pdo->prepare("SELECT email, nombre FROM usuarios WHERE id = ?");
    $stmtU->execute([$cotizacion['usuario_id']]);
    $usuario = $stmtU->fetch(PDO::FETCH_ASSOC);

    if ($usuario && $usuario['email']) {
        $asunto = "🔄 Solicitud de Recotización - Cot #{$cotizacion['numero_cotizacion']}";
        $cuerpo = "
        <div style='font-family: sans-serif; padding: 20px;'>
            <h2 style='color: #2563eb;'>Solicitud de Actualización</h2>
            <p>El cliente <strong>{$cliente['nombre']}</strong> ha solicitado actualizar la cotización vencida <strong>#{$cotizacion['numero_cotizacion']}</strong>.</p>
            <div style='background: #f3f4f6; padding: 15px; border-left: 4px solid #2563eb; margin: 20px 0;'>
                <strong>Mensaje del Cliente:</strong><br>
                " . nl2br(htmlspecialchars($mensaje_cliente)) . "
            </div>
            <p><a href='" . getBaseUrl() . "editar-cotizacion.php?id={$cotizacion['id']}'>Ir a Editar Cotización</a></p>
        </div>";
        
        // Enviamos usando credenciales de empresa, pero DESTINO es el empleado
        $mail_info = [
            'smtp_host' => $cliente['smtp_host'],
            'smtp_user' => $cliente['smtp_user'],
            'smtp_pass' => $cliente['smtp_pass'],
            'smtp_port' => $cliente['smtp_port'],
            'smtp_from_email' => $cliente['smtp_from_email'],
            'cliente_email' => $usuario['email'], // OJO: Destino empleado
            'empresa_nombre' => $cliente['empresa_nombre']
        ];
        enviarEmailGenerico($mail_info, $asunto, $cuerpo);
    }

    // 2. Confirmación al Cliente
    if ($cliente['email']) {
        $asunto_cli = "Recibimos tu solicitud - Cot #{$cotizacion['numero_cotizacion']}";
        $cuerpo_cli = "
        <div style='font-family: sans-serif; padding: 20px;'>
            <h2>✅ Solicitud Recibida</h2>
            <p>Hola <strong>{$cliente['nombre']}</strong>,</p>
            <p>Hemos recibido tu solicitud para actualizar la cotización. Uno de nuestros asesores revisará los nuevos precios/condiciones y te contactará a la brevedad.</p>
            <hr>
            <p style='color: #666; font-size: 12px;'>Comentarios enviados: " . htmlspecialchars($mensaje_cliente) . "</p>
        </div>";
        
        $mail_info_cli = [
            'smtp_host' => $cliente['smtp_host'],
            'smtp_user' => $cliente['smtp_user'],
            'smtp_pass' => $cliente['smtp_pass'],
            'smtp_port' => $cliente['smtp_port'],
            'smtp_from_email' => $cliente['smtp_from_email'],
            'cliente_email' => $cliente['email'],
            'empresa_nombre' => $cliente['empresa_nombre']
        ];
        enviarEmailGenerico($mail_info_cli, $asunto_cli, $cuerpo_cli);
    }
    
    // 3. WhatsApp al Empleado (Si tiene número configurado en su perfil - TODO: Add phone to users table logic if needed, for now skip or send to Admin WA if configured)
    
    return true;
}
