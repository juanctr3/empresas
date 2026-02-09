<?php
require_once 'db.php';

try {
    $pdo->exec("ALTER TABLE ordenes_trabajo ADD COLUMN responsable_id INT(11) NULL DEFAULT NULL AFTER cliente_id");
    echo "Columna agregada.";
} catch (Exception $e) {
    echo "La columna ya existe o error: " . $e->getMessage();
}
?>
