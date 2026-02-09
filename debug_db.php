<?php
require_once 'db.php';
try {
    $stmt = $pdo->query("DESCRIBE cotizaciones");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h1>Columnas de cotizaciones</h1>";
    echo "<table border='1'>";
    foreach ($cols as $c) {
        echo "<tr><td>{$c['Field']}</td><td>{$c['Type']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
