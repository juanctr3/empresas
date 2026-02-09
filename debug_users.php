<?php
require_once 'db.php';
// Inspect admin user (assuming id 1 or searching by common email)
$stmt = $pdo->query("SELECT id, nombre, email, telefono, empresa_id, is_super_admin FROM usuarios WHERE email LIKE '%admin%' OR is_super_admin = 1 LIMIT 5");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h1>Usuarios Admin Found:</h1>";
echo "<pre>";
print_r($users);
echo "</pre>";

if (!empty($users)) {
    foreach($users as $u) {
        if ($u['empresa_id']) {
            $stmtE = $pdo->prepare("SELECT id, nome, smsenlinea_secret, smsenlinea_wa_account FROM empresas WHERE id = ?"); // nome or nombre?
            // Check columns first
            $cols = $pdo->query("SHOW COLUMNS FROM empresas")->fetchAll(PDO::FETCH_COLUMN);
            $nameCol = in_array('nombre', $cols) ? 'nombre' : 'nome';
            
            $stmtE = $pdo->prepare("SELECT id, $nameCol, smsenlinea_secret, smsenlinea_wa_account FROM empresas WHERE id = ?");
            $stmtE->execute([$u['empresa_id']]);
            $emp = $stmtE->fetch(PDO::FETCH_ASSOC);
            echo "<h2>Empresa for User {$u['id']}:</h2><pre>";
            print_r($emp);
            echo "</pre>";
        } else {
            echo "<h2>User {$u['id']} has NO empresa_id</h2>";
        }
    }
}
