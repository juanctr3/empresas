<?php
require_once 'db.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM documentos");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($cols as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
} catch(Exception $e) {
    echo $e->getMessage();
}
