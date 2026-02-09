<?php
require_once 'db.php';
echo "--- TABLE: cotizaciones ---\n";
$stmt = $pdo->query("DESCRIBE cotizaciones");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- TABLE: cotizacion_detalles ---\n";
$stmt = $pdo->query("DESCRIBE cotizacion_detalles");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
