<?php
require_once 'db.php';
$stmt = $pdo->query("DESCRIBE clientes");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($cols, JSON_PRETTY_PRINT);
