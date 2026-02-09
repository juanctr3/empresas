<?php
// diagnose_cotizaciones_state.php
require_once 'db.php';

try {
    echo "<h1>Diagnóstico de Estados de Cotizaciones</h1>";
    
    // 1. Ver qué valores hay actualmente
    $stmt = $pdo->query("SELECT DISTINCT estado, COUNT(*) as count FROM cotizaciones GROUP BY estado");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Valores Actuales:</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Estado</th><th>Cantidad</th></tr>";
    foreach($rows as $row) {
        echo "<tr>";
        echo "<td>'" . htmlspecialchars($row['estado']) . "'</td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 2. Ver definición actual de la columna
    $stmt = $pdo->query("SHOW COLUMNS FROM cotizaciones LIKE 'estado'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<h2>Definición Actual:</h2>";
    echo "<pre>" . print_r($col, true) . "</pre>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
