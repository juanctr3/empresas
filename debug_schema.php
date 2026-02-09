<?php
require_once 'db.php';
header('Content-Type: text/plain');

try {
    echo "--- ROL PERMISOS SCHEMA ---\n";
    checkTable($pdo, 'rol_permisos');
    
    echo "\n--- PERMISOS SCHEMA ---\n";
    checkTable($pdo, 'permisos');

    function checkTable($pdo, $table) {
        try {
            $stmt = $pdo->query("DESCRIBE `$table`");
            while ($row = $stmt->fetch()) {
                print_r($row);
            }
        } catch (Exception $e) {
            echo "Error describing $table: " . $e->getMessage() . "\n";
        }
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
