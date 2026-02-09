<?php
require_once 'db.php';
require_once 'includes/evolution_api_helper.php';

echo "<h2>Verificación de Integración Evolution API</h2>";

// 1. Verificar base de datos
echo "<h3>1. Verificando Campos en Base de Datos...</h3>";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM empresas LIKE 'evolution_api_%'");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Campos encontrados: " . implode(", ", $cols) . "<br>";
    
    if (count($cols) >= 2) {
        echo "[OK] Campos de base de datos presentes.<br>";
    } else {
        echo "[ERROR] Faltan campos en la base de datos.<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// 2. Verificar instanciación del Helper
echo "<h3>2. Verificando Clase Helper...</h3>";
if (class_exists('EvolutionAPI')) {
    $testApi = new EvolutionAPI('https://test.com', 'test_key', 'test_instance');
    echo "[OK] Clase EvolutionAPI cargada e instanciada correctamente.<br>";
} else {
    echo "[ERROR] No se pudo encontrar la clase EvolutionAPI.<br>";
}

// 3. Verificar configuración de Webhook
echo "<h3>3. Verificando Webhook...</h3>";
if (file_exists('wa_webhook.php')) {
    $webhookContent = file_get_contents('wa_webhook.php');
    if (strpos($webhookContent, 'evolution_instance_name') !== false) {
        echo "[OK] wa_webhook.php configurado para Evolution API.<br>";
    } else {
        echo "[WARNING] wa_webhook.php parece no tener la lógica de Evolution API.<br>";
    }
} else {
    echo "[ERROR] No se encuentra wa_webhook.php.<br>";
}
?>
