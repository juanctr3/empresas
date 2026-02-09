<?php
require_once 'db.php';

try {
    echo "--- Table: formularios ---\n";
    $cols = $pdo->query("SHOW COLUMNS FROM formularios")->fetchAll();
    print_r($cols);
    
    echo "\n--- Table: formulario_campos ---\n";
    $cols = $pdo->query("SHOW COLUMNS FROM formulario_campos")->fetchAll();
    print_r($cols);
    
    echo "\n--- Table: formulario_entradas ---\n";
    $cols = $pdo->query("SHOW COLUMNS FROM formulario_entradas")->fetchAll();
    print_r($cols);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
