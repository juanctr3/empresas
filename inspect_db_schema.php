<?php
require_once 'db.php';
try {
    $stmt = $pdo->query("DESCIBE usuarios"); // Intentional typo fix in next step if it fails, or just use DESCRIBE
    // Wait, let's just use SHOW COLUMNS
    $stmt = $pdo->query("SHOW COLUMNS FROM usuarios");
    $cols = $stmt->fetchAll();
    echo "COLUMNS IN usuarios:\n";
    foreach ($cols as $c) {
        echo "- " . $c['Field'] . " (" . $c['Type'] . ") Default: " . ($c['Default'] ?? 'NULL') . "\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM empresas");
    $cols = $stmt->fetchAll();
    echo "\nCOLUMNS IN empresas:\n";
    foreach ($cols as $c) {
        echo "- " . $c['Field'] . " (" . $c['Type'] . ") Default: " . ($c['Default'] ?? 'NULL') . "\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM cotizaciones");
    $cols = $stmt->fetchAll();
    echo "\nCOLUMNS IN cotizaciones:\n";
    foreach ($cols as $c) {
        echo "- " . $c['Field'] . " (" . $c['Type'] . ") Default: " . ($c['Default'] ?? 'NULL') . "\n";
    }

    $stmt = $pdo->query("SELECT COUNT(*) FROM roles");
    echo "\nROLES COUNT: " . $stmt->fetchColumn() . "\n";

    $stmt = $pdo->query("SHOW COLUMNS FROM formularios");
    $cols = $stmt->fetchAll();
    echo "\nCOLUMNS IN formularios:\n";
    foreach ($cols as $c) {
        echo "- " . $c['Field'] . " (" . $c['Type'] . ") Default: " . ($c['Default'] ?? 'NULL') . "\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM formulario_campos");
    $cols = $stmt->fetchAll();
    echo "\nCOLUMNS IN formulario_campos:\n";
    foreach ($cols as $c) {
        echo "- " . $c['Field'] . " (" . $c['Type'] . ") Default: " . ($c['Default'] ?? 'NULL') . "\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
