<?php
require_once 'db.php';
$stmt = $pdo->query("DESCRIBE cotizacion_detalles");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo implode("\n", $columns) . "\n";
