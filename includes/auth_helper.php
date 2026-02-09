<?php
/**
 * Class AuthHelper
 * Wrapper para compatibilidad con llamadas estáticas o de instancia
 */
class AuthHelper {
    public static function check() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
    }

    public static function user() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['user_id'] ?? null;
    }
    
    public static function hasPermission($clave) {
        return tienePermiso($clave);
    }

    public static function getUserId() {
        return self::user();
    }

    public static function isSuperAdmin() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return $_SESSION['is_super_admin'] ?? false;
    }
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/mail_helper.php';
require_once __DIR__ . '/whatsapp_helper.php';

/**
 * Genera un código OTP aleatorio de 6 dígitos
 */
function generarOTP() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Guarda un código OTP en la base de datos
 */
function guardarOTP($pdo, $usuario_id, $codigo, $tipo) {
    // Invalidar códigos anteriores
    $stmt = $pdo->prepare("DELETE FROM otp_codes WHERE usuario_id = ? AND tipo = ?");
    $stmt->execute([$usuario_id, $tipo]);

    // Insertar nuevo código (válido por 10 minutos)
    $stmt = $pdo->prepare("INSERT INTO otp_codes (usuario_id, codigo, tipo, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    return $stmt->execute([$usuario_id, $codigo, $tipo]);
}

/**
 * Envía el OTP por Email
 */
function enviarOTPEmail($pdo, $usuario, $codigo) {
    // FIX: Usar el empresa_id del usuario, no de la sesión (no iniciada aún)
    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt->execute([$usuario['empresa_id']]);
    $empresa = $stmt->fetch();
    
    $info = is_array($empresa) ? $empresa : [];
    $info['cliente_email'] = $usuario['email'];
    $info['cliente_nombre'] = $usuario['nombre'];
    $info['empresa_nombre'] = $empresa['nombre'] ?? 'CoticeFacil';
    
    $asunto = "Código de verificación: $codigo";
    $mensaje = "Tu código de acceso para CoticeFacil es: <br><br>
                <div style='font-size: 24px; font-weight: bold; color: #2563eb; letter-spacing: 5px; text-align: center; padding: 20px; background: #f3f4f6; border-radius: 10px;'>
                    $codigo
                </div><br>
                Este código expirará en 10 minutos.";

    return enviarEmailGenerico($info, $asunto, $mensaje);
}

/**
 * Envía el OTP por WhatsApp
 */
function enviarOTPWhatsApp($pdo, $usuario, $codigo) {
    if (empty($usuario['telefono'])) return false;

    // FIX: Usar el empresa_id del usuario
    $mensaje = "Tu código de verificación para CoticeFacil es: $codigo. Válido por 10 minutos.";
    
    $res = enviarWhatsApp($pdo, $usuario['empresa_id'], $usuario['telefono'], $mensaje);
    return (($res['status'] ?? 0) == 200 || ($res['status'] ?? '') == 'success' || isset($res['key']));
}



/**
 * Verifica si el usuario actual tiene un permiso específico
 */
function tienePermiso($clave) {
    if (isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true) return true;
    if (!isset($_SESSION['permisos'])) return false;
    
    return in_array($clave, $_SESSION['permisos']);
}

/**
 * Redirige si el usuario no tiene permiso
 */
function requerirPermiso($clave) {
    if (!tienePermiso($clave)) {
        header("Location: index.php?error=no_permission");
        exit;
    }
}

/**
 * Genera un token y envía invitación por Email y WhatsApp
 */
function enviarInvitacionPassword($pdo, $usuario_id) {
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("UPDATE usuarios SET password_reset_token = ?, requires_password_setup = 1 WHERE id = ?");
    $stmt->execute([$token, $usuario_id]);

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt->execute([$usuario['empresa_id']]); // Fix here too
    $empresa = $stmt->fetch();

    $link = getBaseUrl() . "/configurar-password.php?token=" . $token;

    // 1. Enviar Email
    $info = $empresa;
    $info['cliente_email'] = $usuario['email'];
    $info['cliente_nombre'] = $usuario['nombre'];
    $info['empresa_nombre'] = $empresa['nombre'];
    
    $asunto = "Bienvenido a " . $empresa['nombre'] . " - Configura tu acceso";
    $mensaje = "Hola " . $usuario['nombre'] . ",<br><br>
                Has sido invitado a unirte al equipo de <b>" . $empresa['nombre'] . "</b> en CoticeFacil.<br><br>
                Para configurar tu contraseña y acceder al sistema, haz clic en el siguiente enlace:<br><br>
                <div style='text-align: center; padding: 20px;'>
                    <a href='$link' style='background: #2563eb; color: white; padding: 15px 30px; text-decoration: none; border-radius: 12px; font-weight: bold;'>Configurar mi Contraseña</a>
                </div><br>
                O copia y pega este enlace en tu navegador:<br> $link";

    enviarEmailGenerico($info, $asunto, $mensaje);

    // 2. Enviar WhatsApp
    if (!empty($usuario['telefono'])) {
        $msg_wa = "¡Hola " . $usuario['nombre'] . "! Bienvenido a *" . $empresa['nombre'] . "* en CoticeFacil. 🚀\n\nConfigura tu contraseña de acceso aquí: " . $link;
        enviarWhatsApp($pdo, $empresa['id'], $usuario['telefono'], $msg_wa);
    }

    return true;
}

/**
 * Genera un token de recuperación y lo envía por Email o WhatsApp
 */
// ... existing code ...
function enviarRecuperacionPassword($pdo, $usuario_id, $type = 'email') {
    // ... existing implementation ...
    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare("UPDATE usuarios SET password_reset_token = ?, requires_password_setup = 1 WHERE id = ?");
    $stmt->execute([$token, $usuario_id]);

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt->execute([$usuario['empresa_id']]);
    $empresa = $stmt->fetch();

    $link = getBaseUrl() . "/configurar-password.php?token=" . $token;

    if ($type === 'email') {
        $info = $empresa;
        $info['cliente_email'] = $usuario['email'];
        $info['cliente_nombre'] = $usuario['nombre'];
        $info['empresa_nombre'] = $empresa['nombre'] ?? 'CoticeFacil';
        
        $asunto = "Recuperación de contraseña - " . ($empresa['nombre'] ?? 'CoticeFacil');
        $mensaje = "Hola " . $usuario['nombre'] . ",<br><br>
                    Hemos recibido una solicitud para restablecer tu contraseña en <b>" . ($empresa['nombre'] ?? 'CoticeFacil') . "</b>.<br><br>
                    Para crear una nueva contraseña, haz clic en el siguiente enlace:<br><br>
                    <div style='text-align: center; padding: 20px;'>
                        <a href='$link' style='background: #2563eb; color: white; padding: 15px 30px; text-decoration: none; border-radius: 12px; font-weight: bold;'>Restablecer mi Contraseña</a>
                    </div><br>
                    Si no solicitaste este cambio, puedes ignorar este mensaje. Tu contraseña actual seguirá funcionando hasta que uses el enlace.<br><br>
                    O copia y pega este enlace en tu navegador:<br> $link";

        return enviarEmailGenerico($info, $asunto, $mensaje);
    } else {
        if (!empty($usuario['telefono'])) {
            $msg_wa = "¡Hola " . $usuario['nombre'] . "! 👋 ¿Olvidaste tu contraseña en *" . ($empresa['nombre'] ?? 'CoticeFacil') . "*?\n\nNo te preocupes, puedes restablecerla aquí: " . $link . "\n\nSi no fuiste tú, ignora este mensaje.";
            $res = enviarWhatsApp($pdo, $empresa['id'], $usuario['telefono'], $msg_wa);
            return (($res['status'] ?? 0) == 200 || ($res['status'] ?? '') == 'success' || isset($res['key']));
        }
    }

    return false;
}


