<?php
require 'db.php';

$perms = [
    ['nombre' => 'acceso_whatsapp', 'descripcion' => 'Permite acceder al módulo de CRM de WhatsApp'],
    ['nombre' => 'ver_numero_wa', 'descripcion' => 'Permite ver el número de teléfono completo en chats'],
    ['nombre' => 'ver_todos_chats', 'descripcion' => 'Permite ver todos los chats, ignorando asignación']
];

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO permisos (nombre, descripcion) VALUES (?, ?)");
    foreach ($perms as $p) {
        $stmt->execute([$p['nombre'], $p['descripcion']]);
    }
    echo "Permissions added successfully.";
} catch (Exception $e) {
    echo "Error adding permissions: " . $e->getMessage();
}
