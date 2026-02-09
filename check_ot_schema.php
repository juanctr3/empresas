<?php
require 'db.php';
try {
    $stmt = $pdo->query("DESCRIBE ordenes_trabajo");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Columns: " . implode(", ", $columns);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
