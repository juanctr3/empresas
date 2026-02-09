<?php
require_once 'db.php';
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM facturas LIKE 'estado'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($col);
} catch(Exception $e) {
    echo $e->getMessage();
}
