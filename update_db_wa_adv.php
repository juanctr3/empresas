<?php
require 'db.php';
try {
    // Helper para verificar si existe columna
    function columnExists($pdo, $table, $column) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM $table LIKE ?");
        $stmt->execute([$column]);
        return $stmt->fetch() !== false;
    }

    if (!columnExists($pdo, 'usuarios', 'permiso_ver_telefono')) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN permiso_ver_telefono TINYINT(1) DEFAULT 0");
    }

    if (!columnExists($pdo, 'wa_chats', 'is_potential_lead')) {
        $pdo->exec("ALTER TABLE wa_chats ADD COLUMN is_potential_lead TINYINT(1) DEFAULT 0");
    }

    echo "Migration OK (Fixed compatibility)";
} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage();
}
