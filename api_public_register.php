<?php
require_once 'db.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Validar datos básicos
    $nombre_empresa = $_POST['nombre_empresa'] ?? '';
    $nit = $_POST['nit'] ?? '';
    $admin_nombre = $_POST['admin_nombre'] ?? '';
    $admin_email = $_POST['admin_email'] ?? '';
    $admin_pass = $_POST['admin_pass'] ?? '';

    if (!$nombre_empresa || !$admin_nombre || !$admin_email || !$admin_pass) {
        throw new Exception("Todos los campos obligatorios deben ser completados.");
    }

    // 2. Verificar si el email ya existe
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$admin_email]);
    if ($stmt->fetch()) {
        throw new Exception("El correo electrónico ya está registrado.");
    }

    // 3. Obtener plan seleccionado o por defecto
    $plan_id = $_POST['plan_id'] ?? null;
    if (!$plan_id) {
        $plan_id = $pdo->query("SELECT id FROM planes ORDER BY precio ASC LIMIT 1")->fetchColumn();
    }
    
    if (!$plan_id) {
        throw new Exception("No hay planes de suscripción configurados en el sistema.");
    }

    // 4. Manejo de Logo
    $logo_path = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $target_dir = 'uploads/logos/';
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $nombre_archivo = 'logo_reg_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_dir . $nombre_archivo)) {
                $logo_path = $target_dir . $nombre_archivo;
            }
        }
    }

    // 5. Crear Empresa con credenciales SMTP por defecto (Ocultas/Hardcoded)
    $default_smtp_host = 'email-smtp.us-east-1.amazonaws.com';
    $default_smtp_user = 'AKIAVYSEJCELS4T7AOK5';
    $default_smtp_pass = 'BL0B8pq/6xm/3mJ0GuZCpHaK+DdVHHmJQ9bNTiuNWSUR';
    $default_smtp_from = 'info@coticefacil.com';

    $stmt = $pdo->prepare("INSERT INTO empresas (
        nombre, nit, plan_id, logo, 
        smtp_host, smtp_user, smtp_pass, smtp_from_email, smtp_port, smtp_encryption
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 587, 'tls')");
    
    $stmt->execute([
        $nombre_empresa, 
        $nit, 
        $plan_id, 
        $logo_path,
        $default_smtp_host,
        $default_smtp_user,
        $default_smtp_pass,
        $default_smtp_from
    ]);
    $empresa_id = $pdo->lastInsertId();

    // 6. Crear Rol Administrador
    $stmt = $pdo->prepare("INSERT INTO roles (empresa_id, nombre, descripcion) VALUES (?, 'Administrador', 'Acceso total (Generado en registro)')");
    $stmt->execute([$empresa_id]);
    $rol_id = $pdo->lastInsertId();

    // 7. Permisos
    $permisos = $pdo->query("SELECT id FROM permisos")->fetchAll(PDO::FETCH_COLUMN);
    $stmt_p = $pdo->prepare("INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (?, ?)");
    foreach ($permisos as $p_id) {
        $stmt_p->execute([$rol_id, $p_id]);
    }

    // 8. Crear Usuario Admin
    $pass_hash = password_hash($admin_pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO usuarios (empresa_id, rol_id, nombre, email, password, activo, requires_password_setup) VALUES (?, ?, ?, ?, ?, 1, 0)");
    $stmt->execute([$empresa_id, $rol_id, $admin_nombre, $admin_email, $pass_hash]);
    $user_id = $pdo->lastInsertId();

    $pdo->commit();

    // 9. Auto-login completo (Cargar sesión con permisos)
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_nombre'] = $admin_nombre;
    $_SESSION['user_email'] = $admin_email;
    $_SESSION['empresa_id'] = $empresa_id;
    $_SESSION['empresa_nombre'] = $nombre_empresa;
    $_SESSION['rol_id'] = $rol_id;
    $_SESSION['user_rol'] = 'Administrador';
    $_SESSION['is_super_admin'] = false;

    // Cargar los permisos que acabamos de asignar
    $_SESSION['permisos'] = $pdo->query("SELECT clave FROM permisos")->fetchAll(PDO::FETCH_COLUMN) ?: [];

    echo json_encode(['status' => 'success', 'message' => '¡Registro exitoso! Redirigiendo...']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
