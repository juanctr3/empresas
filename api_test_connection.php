<?php
require_once 'db.php';
require_once 'includes/mail_helper.php';
require_once 'includes/smsenlinea_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'test_smtp') {
    // Recibir parámetros del POST (para probar sin guardar)
    $config = [
        'smtp_host' => $_POST['host'],
        'smtp_port' => $_POST['port'],
        'smtp_user' => $_POST['user'],
        'smtp_pass' => $_POST['pass'],
        'smtp_encryption' => $_POST['encryption'],
        'smtp_from_email' => $_POST['from'],
        'empresa_nombre' => 'Test CoticeFacil'
    ];

    try {
        $mail = getMailer($config);
        $mail->setFrom($config['smtp_from_email'], 'Prueba CoticeFacil');
        $mail->addAddress($_SESSION['user_email']); // Enviar al usuario actual
        $mail->Subject = 'Prueba de Conexión SMTP - CoticeFacil';
        $mail->Body = '<h1>¡Funciona!</h1><p>Tu configuración SMTP es correcta.</p>';
        $mail->isHTML(true);

        if ($mail->send()) {
            echo json_encode(['status' => 'success', 'message' => 'Correo enviado correctamente a ' . $_SESSION['user_email']]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al enviar: ' . $mail->ErrorInfo]);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Excepción SMTP: ' . $e->getMessage()]);
    }
} 
elseif ($action === 'test_wa_connection') {
    $provider = $_POST['provider'] ?? 'smsenlinea';
    
    // Obtener teléfono del usuario para la prueba
    $stmt = $pdo->prepare("SELECT telefono FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $telefono = $stmt->fetchColumn();

    if (empty($telefono)) {
        echo json_encode(['status' => 'error', 'message' => 'Tu usuario no tiene teléfono configurado. Ve a "Mi Perfil" y agrégalo para probar.']);
        exit;
    }

    if ($provider === 'evolution') {
        require_once 'includes/evolution_api_helper.php';
        $url = $_POST['url'];
        $key = $_POST['key'];
        $name = $_POST['name'];
        $wa = new EvolutionAPI($url, $key, $name);
        $res = $wa->enviarMensaje($telefono, "Prueba de conexión Evolution API exitosa desde CoticeFacil 🚀");
    } else {
        require_once 'includes/smsenlinea_helper.php';
        $secret = $_POST['secret'];
        $account = $_POST['account'];
        $wa = new SMSEnLinea($secret, $account);
        $res = $wa->enviarMensaje($telefono, "Prueba de conexión SMSenlinea exitosa desde CoticeFacil 🚀");
    }

    if (($res['status'] ?? 0) === 200 || ($res['status'] ?? '') === 'success') {
        echo json_encode(['status' => 'success', 'message' => '¡Conexión exitosa! Mensaje enviado a ' . $telefono]);
    } else {
        $errorMsg = $res['message'] ?? (isset($res['error']) ? $res['error'] : json_encode($res));
        echo json_encode(['status' => 'error', 'message' => 'Error de conexión: ' . $errorMsg]); 
    }
} 
elseif ($action === 'test_s3') {
    require_once 'includes/s3_helper.php';
    $key = $_POST['key'] ?? '';
    $secret = $_POST['secret'] ?? '';
    $region = $_POST['region'] ?? '';
    $bucket = $_POST['bucket'] ?? '';

    if (empty($key) || empty($secret) || empty($region) || empty($bucket)) {
        echo json_encode(['status' => 'error', 'message' => 'Todos los campos de S3 son requeridos para la prueba']);
        exit;
    }

    try {
        $s3 = new S3Helper($key, $secret, $region, $bucket);
        $result = $s3->testConnection();
        
        if ($result) {
            echo json_encode([
                'status' => 'success', 
                'message' => '¡Conexión exitosa con Amazon S3! ✅'
            ]);
        } else {
            // Mensaje simple y claro
            $errorMsg = '⚠️ No se pudo conectar con S3. Verifica:';
            $errorMsg .= '\n\n1. Las credenciales (Access Key y Secret Key) sean correctas';
            $errorMsg .= '\n2. El nombre del bucket sea exacto';
            $errorMsg .= '\n3. La región sea la correcta (ej: us-east-1)';
            $errorMsg .= '\n4. El usuario IAM tenga permisos: PutObject, DeleteObject, GetObject';
            
            echo json_encode(['status' => 'error', 'message' => $errorMsg]);
        }
    } catch (Exception $e) {
        $msg = 'Error de conexión con S3. Verifica que las credenciales sean correctas.';
        echo json_encode(['status' => 'error', 'message' => $msg]);
    }
}
else {
    echo json_encode(['status' => 'error', 'message' => 'Acción no válida']);
}
?>
