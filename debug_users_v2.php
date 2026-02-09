<?php
require_once 'db.php';
header('Content-Type: text/plain');

try {
    echo "--- USUARIOS (Full) ---\n";
    $stmt = $pdo->query("SELECT id, nombre, email, rol_id, empresa_id, is_super_admin, requires_password_setup, activo FROM usuarios");
    while ($row = $stmt->fetch()) {
        print_r($row);
    }
    
    echo "\n--- EMPRESAS ---\n";
    $stmt = $pdo->query("SELECT id, nombre FROM empresas");
    while ($row = $stmt->fetch()) {
        print_r($row);
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
