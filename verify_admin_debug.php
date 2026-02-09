<?php
require_once 'db.php';
// Inspect admin user
$stmt = $pdo->query("SELECT id, nombre, email, telefono, empresa_id, is_super_admin FROM usuarios WHERE email LIKE '%admin%' OR is_super_admin = 1 LIMIT 5");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h1>Usuarios Admin Found:</h1>";
echo "<pre>";
print_r($users);
echo "</pre>";

if (!empty($users)) {
    foreach($users as $u) {
        if ($u['empresa_id']) {
            $stmtE = $pdo->prepare("SELECT id, nombre, smsenlinea_secret, smsenlinea_wa_account FROM empresas WHERE id = ?");
            $stmtE->execute([$u['empresa_id']]);
            $emp = $stmtE->fetch(PDO::FETCH_ASSOC);
            echo "<h2>Empresa for User {$u['id']} (ID: {$u['empresa_id']}):</h2><pre>";
            if ($emp) {
                // Mask secrets
                if(!empty($emp['smsenlinea_secret'])) $emp['smsenlinea_secret'] = substr($emp['smsenlinea_secret'], 0, 5) . '...';
                print_r($emp);
            } else {
                echo "Empresa ID {$u['empresa_id']} NOT FOUND in DB.";
            }
            echo "</pre>";
        } else {
            echo "<h2>User {$u['id']} has NO empresa_id</h2>";
        }
    }
} else {
    echo "No admins found.";
}
