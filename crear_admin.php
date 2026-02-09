<?php
require_once 'db.php';

// Contraseña a asignar
$pass_raw = 'admin123';
$email = 'admin@coticefacil.com';

echo "<h1>Generador de Super Admin</h1>";

try {
    // 1. Verificar/Crear Rol Super Admin
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE nombre = 'Super Admin' LIMIT 1");
    $stmt->execute();
    $rol_id = $stmt->fetchColumn();

    if (!$rol_id) {
        // Crear rol si no existe (aunque full_schema.sql debería tenerlo, a veces tables están vacías)
        // Asegurar que existe una empresa primero
        $emp_id = $pdo->query("SELECT id FROM empresas LIMIT 1")->fetchColumn();
        if (!$emp_id) {
            $pdo->exec("INSERT INTO empresas (nombre, nit) VALUES ('Empresa Principal', '900000000')");
            $emp_id = $pdo->lastInsertId();
            echo "✅ Empresa Principal creada (ID: $emp_id)<br>";
        }

        $pdo->prepare("INSERT INTO roles (empresa_id, nombre, descripcion) VALUES (?, 'Super Admin', 'Acceso Total')")->execute([$emp_id]);
        $rol_id = $pdo->lastInsertId();
        echo "✅ Rol Super Admin creado (ID: $rol_id)<br>";
    }

    // 2. Hash Password
    $hash = password_hash($pass_raw, PASSWORD_DEFAULT);

    // 3. Insertar o Actualizar Usuario
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user_exists = $stmt->fetchColumn();

    if ($user_exists) {
        $pdo->prepare("UPDATE usuarios SET password = ?, is_super_admin = 1, rol_id = ? WHERE email = ?")->execute([$hash, $rol_id, $email]);
        echo "✅ Usuario <b>$email</b> ACTUALIZADO con permisos de Super Admin.<br>";
    } else {
        // Necesitamos un empresa_id
        $emp_id = $pdo->query("SELECT id FROM empresas LIMIT 1")->fetchColumn();
        
        $pdo->prepare("INSERT INTO usuarios (empresa_id, rol_id, nombre, email, password, is_super_admin, activo) VALUES (?, ?, ?, ?, ?, 1, 1)")
            ->execute([$emp_id, $rol_id, 'Administrador', $email, $hash]);
        echo "✅ Usuario <b>$email</b> CREAOD con éxito.<br>";
    }

    echo "<br><hr>";
    echo "<h3>Credenciales:</h3>";
    echo "Usuario: <b>$email</b><br>";
    echo "Contraseña: <b>$pass_raw</b><br>";
    echo "<br><a href='index.php'>Ir al Login</a>";
    echo "<br><br><small>Nota: Por seguridad, borra este archivo (crear_admin.php) del servidor una vez termines.</small>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
