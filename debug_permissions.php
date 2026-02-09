<?php
require_once 'db.php';
header('Content-Type: text/plain');

try {
    echo "--- ROL PERMISOS SCHEMA ---\n";
    $stmt = $pdo->query("DESCRIBE rol_permisos");
    while ($row = $stmt->fetch()) {
        print_r($row);
    }
    
    echo "\n--- PERMISSIONS FOR USER 6 (Michele Camacho) ---\n";
    $rol_id = 2; // Michele Camacho has rol_id 2
    $stmt = $pdo->prepare("SELECT p.clave FROM permisos p 
                           JOIN rol_permisos rp ON p.id = rp.permiso_id 
                           WHERE rp.rol_id = ?");
    $stmt->execute([$rol_id]);
    $permisos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Found " . count($permisos) . " permissions:\n";
    print_r($permisos);

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
