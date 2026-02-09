<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=coticefacil-db', 'cotice-user', 'JC@0020560392jc*-?');
    echo "--- Schema for clientes ---\n";
    $stmt = $pdo->query("DESCRIBE clientes");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "\n--- Triggers ---\n";
    $stmt = $pdo->query("SHOW TRIGGERS");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['Table'] == 'clientes') {
            print_r($row);
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
