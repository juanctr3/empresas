<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

header('Content-Type: application/json');

// Validar sesión
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id = $_GET['id'] ?? 0;
if (!$id) {
    echo json_encode(['status' => 'error', 'message' => 'ID requerido']);
    exit;
}

// Obtener historial
$stmt = $pdo->prepare("
    SELECT * FROM cotizacion_historial 
    WHERE cotizacion_id = ? 
    ORDER BY fecha_creacion DESC
");
$stmt->execute([$id]);
$historial = $stmt->fetchAll(PDO::FETCH_ASSOC);

$html = '';

if (count($historial) > 0) {
    foreach ($historial as $h) {
        $fecha = date('d M, h:i A', strtotime($h['fecha_creacion']));
        $esPublico = $h['tipo'] === 'Publico';
        $esCliente = strpos($h['mensaje'], 'COMENTARIO DEL CLIENTE:') !== false;
        
        $msg = htmlspecialchars($h['mensaje']);
        // Limpiar prefijo de cliente si existe para que se vea mejor
        $msgClean = str_replace('COMENTARIO DEL CLIENTE: ', '', $msg);

        if ($esCliente) {
            // Mensaje del Cliente (izquierda)
            $html .= '
            <div class="flex items-start gap-4 mb-6">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-black text-xs shadow-sm border border-blue-200">
                    CLI
                </div>
                <div class="flex-1">
                    <div class="bg-blue-50 p-4 rounded-2xl rounded-tl-none border border-blue-100 text-slate-700 text-sm shadow-sm relative group">
                        <p class="font-bold mb-1 text-blue-800 text-xs uppercase tracking-wider">Cliente</p>
                        '.nl2br($msgClean).'
                        <div class="absolute top-2 right-2 text-[10px] text-blue-300 font-bold">'.$fecha.'</div>
                    </div>
                </div>
            </div>';
        } else {
            // Mensaje Interno/Asesor (derecha)
            $bgClass = $esPublico ? 'bg-indigo-50 border-indigo-100' : 'bg-gray-50 border-gray-100';
            $textClass = $esPublico ? 'text-indigo-800' : 'text-gray-600';
            $label = $esPublico ? 'Asesor (Público)' : 'Nota Interna';
            
            $html .= '
            <div class="flex items-start gap-4 mb-6 flex-row-reverse">
                <div class="w-10 h-10 rounded-full bg-slate-900 flex items-center justify-center text-white font-black text-xs shadow-lg">
                    YO
                </div>
                <div class="flex-1 text-right">
                    <div class="'.$bgClass.' p-4 rounded-2xl rounded-tr-none border '.$textClass.' text-slate-700 text-sm shadow-sm relative inline-block text-left min-w-[60%]">
                        <p class="font-bold mb-1 '.$textClass.' text-xs uppercase tracking-wider">'.$label.'</p>
                        '.nl2br($msgClean).'
                        <div class="mt-2 text-[10px] text-slate-400 font-bold text-right">'.$fecha.' '.($h['notificado_wa'] ? '✓✓' : '✓').'</div>
                    </div>
                </div>
            </div>';
        }
    }
}

echo json_encode(['status' => 'success', 'html' => $html]);
?>
