<?php
// Bypass db.php and connect manually
$host = 'localhost'; // Try localhost first
$db   = 'coticefacil-db';
$credentials = [
    ['user' => 'cotice-user', 'pass' => 'JC@0020560392jc*-?'],
    ['user' => 'cotice-user', 'pass' => 'cotice_temp_123'],
    ['user' => 'root', 'pass' => 'root_secure_password'],
    ['user' => 'root', 'pass' => ''], 
];

$pdo = null;

foreach ($credentials as $cred) {
    try {
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, $cred['user'], $cred['pass'], $options);
        echo "Connected successfully as " . $cred['user'] . "\n";
        break;
    } catch (\PDOException $e) {
        // Try 127.0.0.1
        try {
             $dsn2 = "mysql:host=127.0.0.1;dbname=$db;charset=$charset";
             $pdo = new PDO($dsn2, $cred['user'], $cred['pass'], $options);
             echo "Connected successfully to 127.0.0.1 as " . $cred['user'] . "\n";
             break;
        } catch (\PDOException $e2) {}
    }
}

if (!$pdo) {
    die("CRITICAL: All connection attempts failed.\n");
}

// 1. Check existing permissions
$stmt = $pdo->query("SELECT * FROM permisos WHERE nombre IN ('acceso_whatsapp', 'ver_numero_wa', 'ver_todos_chats')");
$existing = $stmt->fetchAll();
echo "Existing permissions found: " . count($existing) . "\n";
foreach ($existing as $p) {
    echo "- " . $p['nombre'] . "\n";
}

// 2. Insert if missing
$perms = [
    ['nombre' => 'acceso_whatsapp', 'descripcion' => 'Permite acceder al módulo de CRM de WhatsApp'],
    ['nombre' => 'ver_numero_wa', 'descripcion' => 'Permite ver el número de teléfono completo en chats'],
    ['nombre' => 'ver_todos_chats', 'descripcion' => 'Permite ver todos los chats, ignorando asignación']
];

foreach ($perms as $p) {
    $found = false;
    foreach ($existing as $ex) {
        if ($ex['nombre'] === $p['nombre']) $found = true;
    }
    
    if (!$found) {
        echo "Inserting " . $p['nombre'] . "...\n";
        try {
            $stmt = $pdo->prepare("INSERT INTO permisos (nombre, descripcion) VALUES (?, ?)");
            $stmt->execute([$p['nombre'], $p['descripcion']]);
            echo "Inserted.\n";
        } catch (Exception $e) {
            echo "Error inserting " . $p['nombre'] . ": " . $e->getMessage() . "\n";
        }
    } else {
        echo "Skipping " . $p['nombre'] . " (already exists).\n";
    }
}

// 3. Verify roles
echo "\nChecking 'Empleado' role (or similar)...\n";
$roles = $pdo->query("SELECT * FROM roles")->fetchAll();
foreach ($roles as $r) {
    echo "Role: " . $r['nombre'] . " (ID: " . $r['id'] . ")\n";
    $rp = $pdo->prepare("SELECT p.nombre FROM rol_permisos rp JOIN permisos p ON rp.permiso_id = p.id WHERE rp.rol_id = ?");
    $rp->execute([$r['id']]);
    $perms_names = $rp->fetchAll(PDO::FETCH_COLUMN);
    echo "  Permissions: " . implode(', ', $perms_names) . "\n";
}
