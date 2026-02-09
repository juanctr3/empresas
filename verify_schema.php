<?php
// Manually load .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

require_once 'db.php';

try {
    echo "Checking 'formularios' table columns...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM formularios");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required = ['submit_label', 'success_message'];
    $missing = [];
    
    foreach($required as $col) {
        if(in_array($col, $columns)) {
            echo "[OK] Found column: $col\n";
        } else {
            echo "[FAIL] Missing column: $col\n";
            $missing[] = $col;
        }
    }
    
    if(empty($missing)) {
        echo "SUCCESS: All required columns are present.\n";
    } else {
        echo "FAILURE: Missing columns.\n";
    }
    
} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
?>
