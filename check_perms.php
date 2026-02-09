<?php
require 'db.php';
$cols = $pdo->query("DESCRIBE permisos")->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($cols);
$rows = $pdo->query("SELECT * FROM permisos")->fetchAll(PDO::FETCH_ASSOC);
echo "\nRows:\n" . json_encode($rows);
unlink(__FILE__);
