<?php
require 'db.php';
try {
    $pdo->exec("ALTER TABLE wa_chats ADD COLUMN IF NOT EXISTS visto_por_admin TINYINT(1) DEFAULT 1");
    // También asegurar que wa_mensajes tenga los campos necesarios si hubo algún cambio
    echo "Migration OK";
} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage();
}
// Do not unlink yet so user can run it if needed, or I'll try to run it.
