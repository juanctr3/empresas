<?php
require_once 'db.php';
$tables = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables:\n";
foreach($tables as $t) echo "- $t\n";

echo "\nChecking 'impuestos' columns:\n";
try {
    $cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'impuestos'")->fetchAll(PDO::FETCH_COLUMN);
    foreach($cols as $c) echo "- $c\n";
} catch(Exception $e) { echo "Error reading impuestos: " . $e->getMessage() . "\n"; }

echo "\nChecking 'cotizacion_historial' columns:\n";
try {
    $cols = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'cotizacion_historial'")->fetchAll(PDO::FETCH_COLUMN);
    foreach($cols as $c) echo "- $c\n";
} catch(Exception $e) { echo "Error reading historial: " . $e->getMessage() . "\n"; }
?>
