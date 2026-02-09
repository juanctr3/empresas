<?php
require_once 'db.php';
try {
    $stmt = $pdo->query("DESCRIBE formulario_campos");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "COLUMNS IN formulario_campos:\n";
    foreach ($cols as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    $stmt = $pdo->query("SELECT * FROM formulario_campos LIMIT 1");
    $sample = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($sample) {
        echo "\nSAMPLE DATA (ID: {$sample['id']}):\n";
        echo "Settings: " . var_export($sample['settings'], true) . "\n";
    } else {
        echo "\nNo data found in formulario_campos.\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
