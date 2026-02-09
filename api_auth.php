<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

try {
// session_start() se llama dentro de db.php si es necesario
require_once 'db.php';
require_once 'includes/auth_helper.php';

// Limpieza de buffer previo si existe por accidente
if (ob_get_level()) ob_end_clean();

// La inicialización de headers se hará al final para evitar conflictos con session_regenerate_id

    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true) ?: [];
$action = $data['action'] ?? $_POST['action'] ?? '';

    if ($action === 'request_otp') {
    $identifier = $data['identifier'] ?? ''; // Email o Teléfono
    $password = $data['password'] ?? ''; // Contraseña
    $country_code = $data['country_code'] ?? ''; // Código de país opcional
    $type = $data['type'] ?? 'email'; // 'email' o 'whatsapp'

    // Buscar usuario por Email o Teléfono (Smart Search)
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE (email = ? OR telefono = ? OR telefono = ?) AND activo = 1");
    
    // Limpieza básica de teléfono para búsqueda
    $clean_phone = preg_replace('/[^0-9]/', '', $identifier);
    // Si el identifier parece un email, el clean_phone será basura o vacío, pero la query busca OR email = ?
    
    $stmt->execute([$identifier, $identifier, '+' . $clean_phone]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado']);
        exit;
    }

    // Validar canal solicitado
    if ($type === 'whatsapp' && empty($usuario['telefono'])) {
        echo json_encode(['status' => 'error', 'message' => 'Este usuario no tiene un celular registrado. Por favor usa la opción de Email.']);
        exit;
    } 
    
    if ($type === 'email' && empty($usuario['email'])) {
        echo json_encode(['status' => 'error', 'message' => 'Este usuario no tiene un email registrado.']);
        exit;
    }

    // Verificar si requiere configuración de contraseña (solo si no tiene una ya)
    if ($usuario['requires_password_setup'] && empty($usuario['password'])) {
        echo json_encode(['status' => 'setup_required', 'message' => 'Debes configurar tu contraseña primero. Revisa tu Email o WhatsApp para el enlace de invitación.']);
        exit;
    }

    // Verificar contraseña
    if (!password_verify($password, $usuario['password'])) {
        echo json_encode(['status' => 'error', 'message' => 'Contraseña incorrecta']);
        exit;
    }

    $otp = generarOTP();
    guardarOTP($pdo, $usuario['id'], $otp, $type);

    $sent = false;
    if ($type === 'email') {
        $sent = enviarOTPEmail($pdo, $usuario, $otp);
    } else {
        $sent = enviarOTPWhatsApp($pdo, $usuario, $otp);
    }

    if ($sent) {
        $response = ['status' => 'success', 'message' => 'Código enviado correctamente', 'usuario_id' => $usuario['id']];
        
        // MODO DESARROLLO: Si estamos en localhost, devolvemos el código en la respuesta para facilitar pruebas
        if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1' || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
            $response['debug_otp'] = $otp;
            $response['message'] .= " (Modo Dev: Código es $otp)";
        }
        
        echo json_encode($response);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al enviar el código. Por favor intenta de nuevo.']);
    }
    exit;
}

if ($action === 'verify_otp') {
    $usuario_id = $data['usuario_id'] ?? 0;
    $codigo = $data['codigo'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM otp_codes WHERE usuario_id = ? AND codigo = ? AND expires_at > NOW()");
    $stmt->execute([$usuario_id, $codigo]);
    $otp_data = $stmt->fetch();

    if ($otp_data) {
        // Login exitoso
        $stmt = $pdo->prepare("SELECT u.*, r.nombre as rol_nombre, e.nombre as empresa_nombre 
                               FROM usuarios u 
                               LEFT JOIN roles r ON u.rol_id = r.id 
                               LEFT JOIN empresas e ON u.empresa_id = e.id
                               WHERE u.id = ?");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();

        // Actualizar último acceso
        $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")->execute([$usuario_id]);

        // Limpiar códigos usados
        $pdo->prepare("DELETE FROM otp_codes WHERE usuario_id = ?")->execute([$usuario_id]);

        // Regenerar ID de sesión para prevenir Session Fixation
        session_regenerate_id(true);

        // Guardar en sesión
        $_SESSION['user_id'] = $usuario['id'];
        $_SESSION['user_nombre'] = $usuario['nombre'];
        $_SESSION['user_email'] = $usuario['email'];
        $_SESSION['is_super_admin'] = (bool)$usuario['is_super_admin'];
        $_SESSION['user_rol'] = $usuario['rol_nombre'];
        $_SESSION['user_rol_id'] = $usuario['rol_id'];
        $_SESSION['empresa_id'] = $usuario['empresa_id'];
        $_SESSION['empresa_nombre'] = $usuario['empresa_nombre'];

        // Cargar permisos en sesión (con seguridad)
        $_SESSION['permisos'] = [];
        if (!empty($usuario['rol_id'])) {
            try {
                $stmt = $pdo->prepare("SELECT p.clave FROM permisos p 
                                       JOIN rol_permisos rp ON p.id = rp.permiso_id 
                                       WHERE rp.rol_id = ?");
                $stmt->execute([$usuario['rol_id']]);
                $_SESSION['permisos'] = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
            } catch (Throwable $pe) {
                // Si las tablas de permisos no existen, simplemente dejamos los permisos vacíos
                error_log("Error cargando permisos: " . $pe->getMessage());
            }
        }

        // Enviar respuesta exitosa
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Bienvenido']);
        exit;
    } else {
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Código inválido o expirado']);
        exit;
    }
}

    if ($action === 'request_recovery') {
        $identifier = $data['identifier'] ?? '';
        $country_code = $data['country_code'] ?? '';
        $type = $data['type'] ?? 'email';

        // Formatear teléfono si es necesario
        if ($type === 'whatsapp' && !empty($country_code)) {
            $clean_id = preg_replace('/[^0-9]/', '', $identifier);
            $clean_cc = preg_replace('/[^0-9]/', '', $country_code);
            if (strpos($clean_id, $clean_cc) !== 0) {
                $identifier = '+' . $clean_cc . $clean_id;
            } else {
                $identifier = '+' . $clean_id;
            }
        }

        // Buscar usuario
        if ($type === 'email') {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
            $stmt->execute([$identifier]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE (telefono = ? OR telefono = ?) AND activo = 1");
            $clean_phone = str_replace('+', '', $identifier);
            $stmt->execute([$identifier, $clean_phone]);
        }
        
        $usuario = $stmt->fetch();

        if (!$usuario) {
            echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado']);
            exit;
        }

        $sent = enviarRecuperacionPassword($pdo, $usuario['id'], $type);

        if ($sent) {
            $response = ['status' => 'success', 'message' => 'Enlace de recuperación enviado correctamente'];
            
            // MODO DESARROLLO: Devolver el token para pruebas
            if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1' || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
                $stmt = $pdo->prepare("SELECT password_reset_token FROM usuarios WHERE id = ?");
                $stmt->execute([$usuario['id']]);
                $token = $stmt->fetchColumn();
                $response['debug_token'] = $token;
                $response['message'] .= " (Modo Dev: Link es configurar-password.php?token=$token)";
            }
            
            echo json_encode($response);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al enviar la recuperación. Verifica tu configuración de Email/WhatsApp.']);
        }
        exit;
    }

    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);

} catch (Throwable $e) {
    error_log("API Error: " . $e->getMessage());
    if (!headers_sent()) header('Content-Type: application/json', true, 500);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error crítico del sistema: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
