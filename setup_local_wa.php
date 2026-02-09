<?php
// setup_local_wa.php
require_once 'db.php';

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empresa_id = $_POST['empresa_id'];
    $api_url = $_POST['api_url'];
    $api_key = $_POST['api_key'];
    $webhook = $_POST['webhook'];
    
    // Instancia Dummy para pruebas
    $instancia_nombre = $_POST['instancia_nombre'];
    $instancia_token = $_POST['instancia_token'];
    
    try {
        // 1. Guardar Config General
        $stmt = $pdo->prepare("DELETE FROM wa_configuracion_general WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]); // Limpiar previo
        
        $stmt = $pdo->prepare("INSERT INTO wa_configuracion_general (empresa_id, api_url, api_key, webhook_url) VALUES (?, ?, ?, ?)");
        $stmt->execute([$empresa_id, $api_url, $api_key, $webhook]);
        
        // 2. Crear Instancia Dummy si no existe
        $stmt = $pdo->prepare("SELECT id FROM wa_instancias WHERE instancia_nombre = ?");
        $stmt->execute([$instancia_nombre]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO wa_instancias (empresa_id, instancia_nombre, token_instancia, estado_conexion) VALUES (?, ?, ?, 'CONNECTED')");
            $stmt->execute([$empresa_id, $instancia_nombre, $instancia_token]);
        }
        
        $mensaje = "✅ Configuración guardada correctamente.";
    } catch (PDOException $e) {
        $mensaje = "❌ Error SQL: " . $e->getMessage();
    }
}

// Obtener empresas
$empresas = $pdo->query("SELECT id, nombre FROM empresas")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Setup WhatsApp Local</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-10">
    <div class="max-w-2xl mx-auto bg-white p-8 rounded-2xl shadow-xl">
        <h1 class="text-2xl font-bold mb-6 text-indigo-600">Configuración WhatsApp Local (Dev)</h1>
        
        <?php if($mensaje): ?>
            <div class="p-4 mb-6 bg-green-100 text-green-700 rounded-lg">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block font-bold mb-1">Empresa</label>
                <select name="empresa_id" class="w-full p-2 border rounded">
                    <?php foreach($empresas as $e): ?>
                        <option value="<?php echo $e['id']; ?>"><?php echo $e['nombre']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold mb-1">URL API (Docker/Ngrok)</label>
                    <input type="text" name="api_url" value="http://localhost:8080" class="w-full p-2 border rounded">
                    <p class="text-xs text-gray-400">Normalmente http://localhost:8080</p>
                </div>
                <div>
                    <label class="block font-bold mb-1">Global API Key</label>
                    <input type="text" name="api_key" placeholder="Global API Key de Evolution" class="w-full p-2 border rounded">
                </div>
            </div>

            <div>
                <label class="block font-bold mb-1">Webhook URL (Ngrok)</label>
                <input type="text" name="webhook" placeholder="https://xxxx.ngrok-free.app/wa_webhook.php" class="w-full p-2 border rounded">
            </div>
            
            <hr class="my-4">
            <h3 class="font-bold text-gray-600 mb-2">Instancia de Prueba</h3>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold mb-1">Nombre Instancia</label>
                    <input type="text" name="instancia_nombre" value="EmpresaDemo" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block font-bold mb-1">Token Instancia (API Key)</label>
                    <input type="text" name="instancia_token" placeholder="Token único de la instancia" class="w-full p-2 border rounded">
                </div>
            </div>

            <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-3 rounded mt-4 hover:bg-indigo-700">Guardar Configuración</button>
        </form>
    </div>
</body>
</html>
