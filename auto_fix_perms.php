<?php
require 'db.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Diagnóstico y Corrección de Permisos</h1>";

echo "<p>Conectando a base de datos... <strong>OK</strong></p>";

try {
    $perms = [
        ['nombre' => 'acceso_whatsapp', 'descripcion' => 'Permite acceder al módulo de CRM de WhatsApp'],
        ['nombre' => 'ver_numero_wa', 'descripcion' => 'Permite ver el número de teléfono completo en chats'],
        ['nombre' => 'ver_todos_chats', 'descripcion' => 'Permite ver todos los chats, ignorando asignación']
    ];

    echo "<ul>";
    foreach ($perms as $p) {
        $stmt = $pdo->prepare("SELECT id FROM permisos WHERE nombre = ?");
        $stmt->execute([$p['nombre']]);
        if ($stmt->fetch()) {
            echo "<li>Permiso '{$p['nombre']}' ya existe. OK.</li>";
        } else {
            $stmt = $pdo->prepare("INSERT INTO permisos (nombre, clave, descripcion) VALUES (?, ?, ?)");
            $stmt->execute([$p['nombre'], $p['nombre'], $p['descripcion']]);
            echo "<li style='color:green'><strong>Permiso '{$p['nombre']}' creado exitosamente.</strong></li>";
        }
    }
    echo "</ul>";

    echo "<h2>Verificación de Roles</h2>";
    $roles = $pdo->query("SELECT * FROM roles")->fetchAll();
    echo "<table border='1' cellpadding='5'><tr><th>Rol</th><th>Permisos Actuales</th></tr>";
    foreach ($roles as $r) {
        $rp = $pdo->prepare("SELECT p.nombre FROM rol_permisos rp JOIN permisos p ON rp.permiso_id = p.id WHERE rp.rol_id = ?");
        $rp->execute([$r['id']]);
        $perms_assigned = $rp->fetchAll(PDO::FETCH_COLUMN);
        
        $has_wa = in_array('acceso_whatsapp', $perms_assigned) ? '<span style="color:green">SI</span>' : '<span style="color:red">NO</span>';
        
        echo "<tr>";
        echo "<td>{$r['nombre']}</td>";
        echo "<td>" . implode(', ', $perms_assigned) . "<br>¿Tiene WhatsApp?: $has_wa</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<h3 style='color:blue'>¡Proceso Completado! Por favor revisa la Configuración de Roles.</h3>";
    echo "<p><a href='roles.php'>Ir a Roles</a></p>";

} catch (Exception $e) {
    echo "<h1>Error</h1><p>" . $e->getMessage() . "</p>";
}
