<?php
/**
 * CRON Recordatorios - CoticeFacil.com
 * Can be run via CLI or included by API for "Send Now"
 */

if (!isset($pdo)) {
    // Only include if not already included (e.g. by API)
    if (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    } else {
        require_once 'db.php';
    }
}

require_once __DIR__ . '/includes/mail_helper.php';
require_once __DIR__ . '/includes/whatsapp_helper.php';

// Function to process a single reminder
function procesarRecordatorio($pdo, $recordatorio_id, $empresa_id_check = null) {
    // Fetch reminder details with related info
    // LEFT JOIN cotizaciones because it might be null
    $sql = "
        SELECT r.*, 
               c.numero_cotizacion, c.hash_publico, c.total, 
               cl.nombre as cliente_nombre, cl.email as cliente_email, cl.celular_contacto, cl.telefono, cl.pais_codigo,
               e.nombre as empresa_nombre, e.smsenlinea_secret, e.smsenlinea_wa_account,
               e.smtp_host, e.smtp_port, e.smtp_user, e.smtp_pass, e.smtp_encryption, e.smtp_from_email,
               e.color_hex, e.logo, e.moneda
        FROM cotizacion_recordatorios r
        LEFT JOIN cotizaciones c ON r.cotizacion_id = c.id
        LEFT JOIN clientes cl ON (r.cliente_id = cl.id OR c.cliente_id = cl.id)
        JOIN empresas e ON r.empresa_id = e.id
        WHERE r.id = ?
    ";
    
    $params = [$recordatorio_id];
    if ($empresa_id_check) {
        $sql .= " AND r.empresa_id = ?";
        $params[] = $empresa_id_check;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rec) {
        return ['success' => false, 'error' => 'Recordatorio no encontrado'];
    }

    $log = [];
    $errores = 0;
    
    // Configurar entorno de emails con los datos de la empresa
    $info_empresa = [
        'smtp_host' => $rec['smtp_host'],
        'smtp_user' => $rec['smtp_user'],
        'smtp_pass' => $rec['smtp_pass'],
        'smtp_port' => $rec['smtp_port'],
        'smtp_encryption' => $rec['smtp_encryption'],
        'smtp_from_email' => $rec['smtp_from_email'],
        'empresa_nombre' => $rec['empresa_nombre'],
        'cliente_nombre' => $rec['cliente_nombre'],
        'cliente_email' => $rec['cliente_email'] // Default, will override per recipient
    ];

    $link_cotizacion = "";
    if(!empty($rec['hash_publico'])) {
        $link_cotizacion = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'coticefacil.com') . dirname($_SERVER['PHP_SELF'] ?? '/') . "/propuesta.php?h=" . $rec['hash_publico'];
    }
    
    // --- 1. ENVIAR EMAILS ---
    $emails_to_send = [];
    
    // Email Cliente
    if ($rec['notificar_cliente'] && !empty($rec['cliente_email'])) {
        $emails_to_send[] = $rec['cliente_email'];
    }
    
    // Emails Adicionales
    $extras = json_decode($rec['emails_adicionales'] ?? '[]', true);
    if (is_array($extras)) {
        foreach ($extras as $e) {
            if (filter_var($e, FILTER_VALIDATE_EMAIL)) $emails_to_send[] = $e;
        }
    }
    
    foreach (array_unique($emails_to_send) as $email) {
        $info_actual = $info_empresa;
        $info_actual['cliente_email'] = $email; // Set recipient
        
        if (enviarEmailRecordatorio($info_actual, $rec['asunto'], $rec['mensaje'], $link_cotizacion)) {
            $log[] = "Email enviado a $email";
        } else {
            $log[] = "Fallo envio email a $email";
            $errores++;
        }
    }

    // --- 2. ENVIAR WHATSAPP ---
    $telefonos_to_send = [];
    
    // Teléfono Cliente
    if ($rec['notificar_cliente']) {
        // Usar código de país del cliente si existe
        $pCode = $rec['pais_codigo'] ?: '57'; 

        $t = $rec['celular_contacto'] ?: $rec['telefono'];
        if($t) {
             $clean = preg_replace('/[^0-9+]/', '', $t);
             // Si no tiene +, concatenar pais.
             if(substr($clean, 0, 1) !== '+') $clean = '+' . $pCode . $clean;
             $telefonos_to_send[] = $clean;
        }
    }
    
    // Teléfonos Adicionales
    $extras_tel = json_decode($rec['telefonos_adicionales'] ?? '[]', true);
    if (is_array($extras_tel)) {
        foreach ($extras_tel as $t) {
            $t_clean = preg_replace('/[^0-9+]/', '', $t);
            if ($t_clean) $telefonos_to_send[] = $t_clean;
        }
    }

    // WhatsApp sending
    $footer = "";
    if($rec['numero_cotizacion']) {
        $footer = "\n\n📄 Ver Cotización #{$rec['numero_cotizacion']}:\n$link_cotizacion";
    } elseif($link_cotizacion) {
            $footer = "\n\n📄 Ver Detalle:\n$link_cotizacion";
    }

    $wa_msg = "*" . $rec['asunto'] . "*\n\n";
    if (!empty($rec['mensaje'])) {
        $wa_msg .= $rec['mensaje'] . $footer;
    } else {
            $wa_msg .= $footer;
    }

    foreach (array_unique($telefonos_to_send) as $tel) {
        $res = enviarWhatsApp($pdo, $rec['empresa_id'], $tel, $wa_msg);
        if (($res['status'] ?? 0) == 200 || ($res['status'] ?? '') == 'success') {
            $log[] = "WhatsApp enviado a $tel";
        } else {
            $log[] = "Fallo WhatsApp a $tel: " . ($res['message'] ?? json_encode($res));
            $errores++;
        }
    }

    // Update Reminder Status
    $status = ($errores == 0) ? 'Enviado' : 'Fallido'; // Or 'Parcial'
    $log_str = implode("\n", $log);
    
    $stmtUpd = $pdo->prepare("UPDATE cotizacion_recordatorios SET estado = ?, log_envio = ? WHERE id = ?");
    $stmtUpd->execute([$status, $log_str, $recordatorio_id]);
    
    return ['success' => true, 'log' => $log, 'errors' => $errores];
}
// Check if running from CLI or HTTP with Token
$is_cli = php_sapi_name() === 'cli';
$is_http_cron = isset($_GET['token']) && $_GET['token'] === 'cron_secure_123'; // Token fijo por ahora para simplicidad

if (($is_cli || $is_http_cron) && !isset($_POST['id'])) {
    if ($is_http_cron) header('Content-Type: text/plain');
    
    echo "Iniciando Cron de Recordatorios (" . ($is_cli ? "CLI" : "HTTP") . ")...\n";
    
    // Find pending reminders due for now or past
    // LIMIT 50 para evitar timeouts en HTTP
    $stmt = $pdo->prepare("SELECT id FROM cotizacion_recordatorios WHERE estado = 'Pendiente' AND fecha_programada <= NOW() LIMIT 50");
    $stmt->execute();
    $pendientes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // --- 1. PROCESAR PRE-RECORDATORIOS ---
    $stmtPre = $pdo->prepare("
        SELECT id FROM cotizacion_recordatorios 
        WHERE tiene_prerecordatorio = 1 
        AND prerecordatorio_enviado = 0 
        AND estado = 'Pendiente'
        AND DATE_SUB(fecha_programada, INTERVAL dias_antes DAY) <= NOW()
        LIMIT 50
    ");
    $stmtPre->execute();
    $pre_pendientes = $stmtPre->fetchAll(PDO::FETCH_COLUMN);

    echo "Encontrados " . count($pre_pendientes) . " pre-recordatorios pendientes.\n";

    foreach ($pre_pendientes as $id) {
        // Usamos la misma lógica pero cambiando el mensaje por el de pre-aviso
        // y NO actualizando el estado principal, sino 'prerecordatorio_enviado'
        $res = procesarPreRecordatorio($pdo, $id);
        if($is_http_cron) {
            echo "PRE ID $id: " . ($res['success'] ? "OK" : "Error") . "\n";
        } else {
            echo "Procesando PRE ID $id... " . json_encode($res) . "\n";
        }
    }

    // --- 2. PROCESAR RECORDATORIOS NORMALES ---
    // Find pending reminders due for now or past
    // LIMIT 50 para evitar timeouts en HTTP
    $stmt = $pdo->prepare("SELECT id FROM cotizacion_recordatorios WHERE estado = 'Pendiente' AND fecha_programada <= NOW() LIMIT 50");
    $stmt->execute();
    $pendientes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Encontrados " . count($pendientes) . " recordatorios pendientes.\n";
    
    foreach ($pendientes as $id) {
        $res = procesarRecordatorio($pdo, $id);
        if($is_http_cron) {
            echo "ID $id: " . ($res['success'] ? "OK" : "Error") . "\n";
        } else {
            echo "Procesando ID $id... " . json_encode($res) . "\n";
        }
    }
    
    echo "Fin del proceso.\n";
}

function procesarPreRecordatorio($pdo, $id) {
    // Fetch info similar to regular reminder
    // LEFT JOIN cotizaciones because it might be null
    $sql = "
        SELECT r.*, 
               c.numero_cotizacion, c.hash_publico, c.total, 
               cl.nombre as cliente_nombre, cl.email as cliente_email, cl.celular_contacto, cl.telefono, cl.pais_codigo,
               e.nombre as empresa_nombre, e.smsenlinea_secret, e.smsenlinea_wa_account,
               e.smtp_host, e.smtp_port, e.smtp_user, e.smtp_pass, e.smtp_encryption, e.smtp_from_email,
               e.color_hex, e.logo, e.moneda
        FROM cotizacion_recordatorios r
        LEFT JOIN cotizaciones c ON r.cotizacion_id = c.id
        LEFT JOIN clientes cl ON (r.cliente_id = cl.id OR c.cliente_id = cl.id)
        JOIN empresas e ON r.empresa_id = e.id
        WHERE r.id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rec) return ['success' => false, 'error' => 'No encontrado'];

    // Override Message
    $asunto_pre = "Recordatorio Próximo: " . $rec['asunto'];
    $mensaje_pre = $rec['mensaje_prerecordatorio'] ?: ("Te recordamos que el " . date('d/m/Y H:i', strtotime($rec['fecha_programada'])) . " tienes programada: " . $rec['asunto']);
    
    // --- Lógica de Envío (Simplificada / Copiada para PRE) ---
    // Idealmente refactorizaríamos 'enviar' a una función común, pero por rapidez duplicamos la estructura de envío manteniendo la separación
    
    $log = [];
    $errores = 0;
    
    // Envío Email
    $info_empresa = [
        'smtp_host' => $rec['smtp_host'],
        'smtp_user' => $rec['smtp_user'],
        'smtp_pass' => $rec['smtp_pass'],
        'smtp_port' => $rec['smtp_port'],
        'smtp_encryption' => $rec['smtp_encryption'],
        'smtp_from_email' => $rec['smtp_from_email'],
        'empresa_nombre' => $rec['empresa_nombre'],
        'cliente_nombre' => $rec['cliente_nombre'],
        'cliente_email' => $rec['cliente_email']
    ];
    
    $link_cotizacion = "";
    if(!empty($rec['hash_publico'])) {
        $link_cotizacion = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . ($_SERVER['HTTP_HOST'] ?? 'coticefacil.com') . dirname($_SERVER['PHP_SELF'] ?? '/') . "/propuesta.php?h=" . $rec['hash_publico'];
    }

    // 1. Emails
    $emails = [];
    if ($rec['notificar_cliente'] && !empty($rec['cliente_email'])) $emails[] = $rec['cliente_email'];
    $extras = json_decode($rec['emails_adicionales'] ?? '[]', true);
    if(is_array($extras)) foreach($extras as $e) if(filter_var($e, FILTER_VALIDATE_EMAIL)) $emails[] = $e;
    
    foreach(array_unique($emails) as $email) {
        $info_actual = $info_empresa;
        $info_actual['cliente_email'] = $email;
        if (enviarEmailRecordatorio($info_actual, $asunto_pre, $mensaje_pre, $link_cotizacion)) {
            $log[] = "PRE-Email enviado a $email";
        } else {
            $log[] = "Fallo PRE-Email a $email";
            $errores++;
        }
    }

    // 2. WhatsApp
    $tels = [];
    if($rec['notificar_cliente']) {
        // Usar código de país del cliente si existe
        $pCode = $rec['pais_codigo'] ?: '57'; 
        
        $t = $rec['celular_contacto'] ?: $rec['telefono'];
        if($t) {
             $clean = preg_replace('/[^0-9+]/', '', $t);
             // Si no tiene +, asumimos que le falta el codigo o ya lo tiene? 
             // Mejor: si no tiene +, concatenar pais.
             if(substr($clean, 0, 1) !== '+') $clean = '+' . $pCode . $clean;
             $tels[] = $clean;
        }
    }
    $extras_tel = json_decode($rec['telefonos_adicionales'] ?? '[]', true);
    if(is_array($extras_tel)) foreach($extras_tel as $t) {
        $tc = preg_replace('/[^0-9+]/', '', $t);
        if($tc) $tels[] = $tc;
    }

    // WhatsApp Pre-Aviso
    $footer = $link_cotizacion ? "\n\n📄 Ver: $link_cotizacion" : "";
    $wa_msg = "*PRE-AVISO: " . $rec['asunto'] . "*\n\n" . $mensaje_pre . $footer;
    
    foreach(array_unique($tels) as $tel) {
        $res = enviarWhatsApp($pdo, $rec['empresa_id'], $tel, $wa_msg);
        if (($res['status'] ?? 0) == 200 || ($res['status'] ?? '') == 'success') {
            $log[] = "PRE-WA enviado a $tel";
        } else {
            $log[] = "Fallo PRE-WA a $tel: " . ($res['message'] ?? json_encode($res));
        }
    }

    // Update SOLO 'prerecordatorio_enviado' y append log
    $current_log = $rec['log_envio'] ?? '';
    $new_log = $current_log . "\n--- Pre-Aviso ---\n" . implode("\n", $log);
    
    $stmtUpd = $pdo->prepare("UPDATE cotizacion_recordatorios SET prerecordatorio_enviado = 1, log_envio = ? WHERE id = ?");
    $stmtUpd->execute([$new_log, $id]);

    return ['success' => true, 'log' => $log];
}
?>
