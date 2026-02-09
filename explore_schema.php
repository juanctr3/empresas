<?php
require_once 'db.php';
$tables = ['productos', 'cotizaciones', 'cotizacion_detalles'];
foreach($tables as $table) {
    echo "--- $table ---\n";
    $stmt = $pdo->query("DESCRIBE $table");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($cols as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    echo "\n";
}
