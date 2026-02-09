<?php
require_once 'db.php';
echo "<pre>";
try {
    $stmt = $pdo->query("DESCRIBE usuarios");
    while($row = $stmt->fetch()) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
echo "</pre>";
