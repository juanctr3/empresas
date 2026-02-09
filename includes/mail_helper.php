<?php
/**
 * Helper para envío de correos vía SMTP o mail() estándar
 */
function enviarEmailPropuesta($info, $asunto, $mensaje_texto) {
    $host = $info['smtp_host'] ?? '';
    $user = $info['smtp_user'] ?? '';
    $pass = $info['smtp_pass'] ?? '';
    $port = $info['smtp_port'] ?? 587;
    $enc  = $info['smtp_encryption'] ?? 'none';
    $from_email = !empty($info['smtp_from_email']) ? $info['smtp_from_email'] : ($info['smtp_user'] ?: 'ventas@tusistema.com');
    
    $to = $info['cliente_email'];
    $headers = "From: " . $info['empresa_nombre'] . " <$from_email>\r\n";
    $headers .= "Reply-To: $from_email\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $cuerpo = "
    <html>
    <body style='font-family: sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
            <h2 style='color: #2563eb;'>Hola, " . htmlspecialchars($info['cliente_nombre']) . "</h2>
            <p>Tienes una nueva actualización en tu cotización <strong>#" . ($info['numero_cotizacion'] ?: 'N/A') . "</strong>:</p>
            <div style='background: #f9fafb; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                " . nl2br(htmlspecialchars($mensaje_texto)) . "
            </div>
            <p style='text-align: center;'>
                <a href='" . (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/propuesta.php?h=" . $info['hash_publico'] . (!empty($info['tracking_uid']) ? "&sid=" . $info['tracking_uid'] : "") . "' 
                   style='background: #2563eb; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                   Ver Propuesta Completa
                </a>
            </p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 30px 0;'>
            <p style='font-size: 12px; color: #999; text-align: center;'>Este correo fue enviado por " . htmlspecialchars($info['empresa_nombre']) . "</p>
        </div>
    </body>
    </html>";

    // Si hay host SMTP configurado, intentamos usar una conexión socket básica
    // para Amazon SES. Si falla o no hay host, usamos mail().
    if (!empty($host)) {
        try {
            return enviarViaSMTP($host, $port, $user, $pass, $from_email, $to, $asunto, $cuerpo, $enc, $info['empresa_nombre']);
        } catch (Exception $e) {
            error_log("Error SMTP: " . $e->getMessage());
            // Fallback a mail() si falla el socket
            return mail($to, $asunto, $cuerpo, $headers);
        }
    }

    return mail($to, $asunto, $cuerpo, $headers);
}

function enviarViaSMTP($host, $port, $user, $pass, $from, $to, $subject, $body, $enc, $company) {
    $timeout = 10;
    $socket_host = ($enc == 'ssl') ? 'ssl://' . $host : $host;
    
    $socket = fsockopen($socket_host, $port, $errno, $errstr, $timeout);
    if (!$socket) throw new Exception("No se pudo conectar: $errstr ($errno)");

    readResponse($socket); // 220
    fwrite($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
    readResponse($socket);

    if ($enc == 'tlsv1.2' || $enc == 'tlsv1.3' || $port == 587) {
        fwrite($socket, "STARTTLS\r\n");
        readResponse($socket);
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        fwrite($socket, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
        readResponse($socket);
    }

    fwrite($socket, "AUTH LOGIN\r\n");
    readResponse($socket);
    fwrite($socket, base64_encode($user) . "\r\n");
    readResponse($socket);
    fwrite($socket, base64_encode($pass) . "\r\n");
    $authRes = readResponse($socket);
    if (strpos($authRes, '235') === false) throw new Exception("Error de autenticación: " . $authRes);

    fwrite($socket, "MAIL FROM: <$from>\r\n");
    readResponse($socket);
    fwrite($socket, "RCPT TO: <$to>\r\n");
    readResponse($socket);
    fwrite($socket, "DATA\r\n");
    readResponse($socket);

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $company <$from>\r\n";
    $headers .= "To: <$to>\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "Date: " . date('r') . "\r\n";
    
    fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
    readResponse($socket);
    fwrite($socket, "QUIT\r\n");
    fclose($socket);
    return true;
}

/**
 * Helper interno para leer respuestas de socket SMTP
 */
function readResponse($socket) {
    $res = "";
    while($str = fgets($socket, 515)) {
        $res .= $str;
        if(substr($str, 3, 1) == " ") break;
    }
    return $res;
}

/**
 * Función genérica para enviar correos electrónicos
 */
function enviarEmailGenerico($info, $asunto, $cuerpo_html) {
    $host = $info['smtp_host'] ?? '';
    $user = $info['smtp_user'] ?? '';
    $pass = $info['smtp_pass'] ?? '';
    $port = $info['smtp_port'] ?? 587;
    $enc  = $info['smtp_encryption'] ?? 'none';
    $from_email = !empty($info['smtp_from_email']) ? $info['smtp_from_email'] : ($info['smtp_user'] ?: 'ventas@tusistema.com');
    $to = $info['cliente_email'];
    $company = $info['empresa_nombre'] ?? 'CoticeFacil';

    $headers = "From: " . $company . " <$from_email>\r\n";
    $headers .= "Reply-To: $from_email\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $cuerpo_final = "
    <html>
    <body style='font-family: sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
            " . $cuerpo_html . "
            <hr style='border: 0; border-top: 1px solid #eee; margin: 30px 0;'>
            <p style='font-size: 12px; color: #999; text-align: center;'>Este correo fue enviado por " . htmlspecialchars($company) . "</p>
        </div>
    </body>
    </html>";

    if (!empty($host)) {
        try {
            return enviarViaSMTP($host, $port, $user, $pass, $from_email, $to, $asunto, $cuerpo_final, $enc, $company);
        } catch (Exception $e) {
            error_log("Error SMTP en enviarEmailGenerico: " . $e->getMessage());
            // Si nos da error SMTP en localhost, no intentamos mail() para evitar cuelgues
            if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) return true;
            return mail($to, $asunto, $cuerpo_final, $headers);
        }
    }

    // Protección para localhost sin SMTP configurado
    if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || ($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1') {
        return true; 
    }

    return mail($to, $asunto, $cuerpo_final, $headers);
}

function enviarEmailRecordatorio($info, $asunto, $mensaje_texto, $link_cotizacion) {
    $cuerpo = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;'>
        <div style='background-color: #1e293b; padding: 24px; text-align: center;'>
            <h2 style='color: #ffffff; margin: 0; font-size: 20px; font-weight: bold;'>" . htmlspecialchars($asunto) . "</h2>
        </div>
        <div style='padding: 32px; background-color: #ffffff;'>
            <p style='color: #475569; font-size: 16px; line-height: 1.6; margin-bottom: 24px;'>
                Hola <strong>" . htmlspecialchars($info['cliente_nombre']) . "</strong>,
            </p>
            <div style='background-color: #f8fafc; border-left: 4px solid #3b82f6; padding: 16px; margin-bottom: 24px; color: #334155;'>
                " . nl2br(htmlspecialchars($mensaje_texto)) . "
            </div>
            <div style='text-align: center; margin-top: 32px;'>
                <a href='" . $link_cotizacion . "' style='background-color: #2563eb; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);'>
                    Ver Cotización
                </a>
            </div>
        </div>
        <div style='background-color: #f1f5f9; padding: 16px; text-align: center; font-size: 12px; color: #94a3b8;'>
            Mensaje enviado por " . htmlspecialchars($info['empresa_nombre']) . "
        </div>
    </div>";

    return enviarEmailGenerico($info, $asunto, $cuerpo);
}

function enviarEmailBienvenidaCliente($info, $ot_id) {
    $asunto = "¡Gracias! Hemos recibido tu aprobación - OT #$ot_id";
    $link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/propuesta.php?h=" . $info['hash_publico'];
    
    $cuerpo = "
    <div style='text-align: center;'>
        <h1 style='color: #10b981;'>¡Gracias por tu confianza!</h1>
        <p>Hemos recibido tu aprobación de la cotización <strong>#" . ($info['numero_cotizacion'] ?: $info['cotizacion_id']) . "</strong>.</p>
        <p>Se ha generado la <strong>Orden de Trabajo #$ot_id</strong> y nuestro equipo se pondrá en marcha de inmediato.</p>
        
        <div style='background: #f0fdf4; color: #166534; padding: 20px; margin: 20px 0; border-radius: 10px;'>
            ✅ <strong>Estado:</strong> Aprobado y En Proceso<br>
            📅 <strong>Fecha:</strong> " . date('d/m/Y') . "
        </div>

        <p><a href='$link' style='color: #2563eb; font-weight: bold;'>Ver documento firmado</a></p>
    </div>";

    return enviarEmailGenerico($info, $asunto, $cuerpo);
}
