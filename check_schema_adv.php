<?php
require 'db.php';
$users = $pdo->query("DESCRIBE usuarios")->fetchAll(PDO::FETCH_COLUMN);
$clients = $pdo->query("DESCRIBE clientes")->fetchAll(PDO::FETCH_COLUMN);
echo json_encode(['users' => $users, 'clients' => $clients]);
unlink(__FILE__);
