<?php
require 'db.php';
// Obtener todos los roles y sus permisos
$roles = $pdo->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC);
$permisos = $pdo->query("SELECT * FROM permisos")->fetchAll(PDO::FETCH_ASSOC);
$rol_permisos = $pdo->query("
    SELECT r.nombre as role, p.nombre as permiso 
    FROM rol_permisos rp 
    JOIN roles r ON rp.rol_id = r.id 
    JOIN permisos p ON rp.permiso_id = p.id
")->fetchAll(PDO::FETCH_ASSOC);

echo "Roles:\n";
print_r($roles);
echo "\nPermisos:\n";
print_r($permisos);
echo "\nAsignaciones:\n";
print_r($rol_permisos);
unlink(__FILE__);
