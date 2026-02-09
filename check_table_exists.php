<?php
require_once 'db.php';
try {
    $stmt = $pdo->query("SELECT 1 FROM cotizacion_recordatorios LIMIT 1");
    echo "Table exists";
} catch (PDOException $e) {
    echo "Table does not exist: " . $e->getMessage();
}
?>
