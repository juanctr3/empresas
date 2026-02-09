<?php
require 'db.php';
$cols = $pdo->query("DESCRIBE wa_chats")->fetchAll(PDO::FETCH_COLUMN);
echo json_encode($cols);
unlink(__FILE__);
