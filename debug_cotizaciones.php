<?php
require_once 'db.php';
header('Content-Type: text/plain');

try {
    echo "--- COTIZACIONES SCHEMA ---\n";
    $stmt = $pdo->query("DESCRIBE cotizaciones");
    while ($row = $stmt->fetch()) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
