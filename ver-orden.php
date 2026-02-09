<?php
require_once 'db.php';
include 'includes/header.php';

$id = $_GET['id'] ?? null;
if (!$id) die("ID Inválido");

// Obtener OT
$stmt = $pdo->prepare("
    SELECT ot.*, c.nombre as cliente_nombre, c.email, c.telefono, c.celular_contacto, c.direccion as cliente_direccion, 
           cot.numero_cotizacion, cot.total as monto_total
    FROM ordenes_trabajo ot
    JOIN clientes c ON ot.cliente_id = c.id
    JOIN cotizaciones cot ON ot.cotizacion_id = cot.id
    WHERE ot.id = ? AND ot.empresa_id = ?
");
$stmt->execute([$id, getEmpresaId()]);
$ot = $stmt->fetch();

if (!$ot) die("Orden no encontrada");

// Obtener Items
$items = $pdo->prepare("SELECT * FROM ordenes_items WHERE orden_id = ?");
$items->execute([$id]);
$items = $items->fetchAll();

// Parsear Datos de Aceptación (Logística)
$aceptada_data = json_decode($ot['aceptada_data'] ?? '{}', true);
$logistica = $aceptada_data['logistica'] ?? null;
$firma = $ot['firma_digital'] ?? null; // Should be in OT or Cot? Usually copied or ref'd. 
// If not in OT, fetch from Cot.
if(!$firma) {
    $cot_firma = $pdo->query("SELECT firma_digital FROM cotizaciones WHERE id = {$ot['cotizacion_id']}")->fetchColumn();
    $firma = $cot_firma;
}

// Entradas de formulario vinculadas
$stmtForms = $pdo->prepare("
    SELECT fe.*, f.nombre as formulario_nombre 
    FROM formulario_entradas fe
    JOIN formularios f ON fe.formulario_id = f.id
    WHERE fe.orden_trabajo_id = ? OR fe.cotizacion_id = ?
    ORDER BY fe.created_at DESC
");
$stmtForms->execute([$id, $ot['cotizacion_id']]);
$entradas_formulario = $stmtForms->fetchAll();
?>

<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <a href="ordenes.php" class="text-gray-400 hover:text-gray-600 transition-colors">
                    &larr; Volver
                </a>
                <span class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-widest bg-gray-100 text-gray-600">OT #<?php echo $ot['id']; ?></span>
            </div>
            <h1 class="text-4xl font-black text-gray-900"><?php echo htmlspecialchars($ot['cliente_nombre']); ?></h1>
            <p class="text-gray-500 font-medium">Basado en Cotización #<a href="ver-cotizacion.php?id=<?php echo $ot['cotizacion_id']; ?>" class="text-blue-500 hover:underline"><?php echo $ot['numero_cotizacion']; ?></a></p>
        </div>

        <div class="flex gap-3">
            <select onchange="updateStatus(<?php echo $id; ?>, this.value)" class="px-4 py-2 rounded-xl border border-gray-200 bg-white text-sm font-bold outline-none ring-2 ring-transparent focus:ring-blue-500 cursor-pointer shadow-sm">
                <?php foreach(['Pendiente','En Proceso','Finalizada','Entregada','Cancelada'] as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo $ot['estado'] === $st ? 'selected' : ''; ?>><?php echo $st; ?></option>
                <?php endforeach; ?>
            </select>
            <button onclick="window.print()" class="bg-gray-900 text-white px-6 py-2 rounded-xl font-bold shadow-lg hover:scale-105 transition-all">
                Imprimir OT
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- Columna Izquierda: Detalles e Items -->
        <div class="md:col-span-2 space-y-8">
            
            <!-- Items a Ejecutar -->
            <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm">
                <h3 class="font-black text-gray-900 text-xl mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    Items del Servicio
                </h3>
                <div class="space-y-4">
                    <?php foreach($items as $item): ?>
                    <div class="flex gap-4 p-4 rounded-2xl bg-gray-50 border border-gray-100 items-start">
                        <div class="w-16 h-16 bg-white rounded-xl flex items-center justify-center shrink-0 text-gray-300 font-bold text-xs border border-gray-200">
                             <?php if($item['imagen']): ?>
                                <img src="<?php echo $item['imagen']; ?>" class="w-full h-full object-cover rounded-xl">
                             <?php else: ?>
                                IMG
                             <?php endif; ?>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-900"><?php echo htmlspecialchars($item['nombre_producto']); ?></h4>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($item['descripcion']); ?></p>
                            <div class="mt-2 text-xs font-bold text-blue-600 bg-blue-50 inline-block px-2 py-1 rounded-lg">
                                <?php echo $item['cantidad']; ?> x <?php echo $item['unidad_nombre'] ?? 'Unidad'; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Datos de Logística (Si aplica) -->
            <?php if (!empty($logistica)): ?>
            <div class="bg-amber-50 p-8 rounded-3xl border border-amber-100 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4 opacity-10">
                    <svg class="w-32 h-32 text-amber-900" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"></path></svg>
                </div>
                <h3 class="font-black text-amber-900 text-xl mb-4 relative z-10">Datos de Recolección / Logística</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 relative z-10">
                    <div>
                        <span class="block text-xs font-bold text-amber-600 uppercase tracking-widest">Dirección</span>
                        <p class="font-medium text-amber-900 text-lg"><?php echo htmlspecialchars($logistica['direccion']); ?></p>
                        <p class="text-sm text-amber-800"><?php echo htmlspecialchars($logistica['ciudad']); ?></p>
                    </div>
                    <div>
                        <span class="block text-xs font-bold text-amber-600 uppercase tracking-widest">Fecha Programada</span>
                        <p class="font-medium text-amber-900 text-lg"><?php echo htmlspecialchars($logistica['fecha']); ?></p>
                        <p class="text-sm text-amber-800"><?php echo htmlspecialchars($logistica['horario']); ?></p>
                    </div>
                    <div class="col-span-2">
                        <span class="block text-xs font-bold text-amber-600 uppercase tracking-widest">Observaciones</span>
                        <p class="text-amber-900 italic">"<?php echo htmlspecialchars($logistica['observaciones'] ?: 'Ninguna'); ?>"</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Formularios Vinculados (Traceability) -->
            <?php if (!empty($entradas_formulario)): ?>
            <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm">
                <h3 class="font-black text-gray-900 text-xl mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Historial de Formularios Recibidos
                </h3>
                <div class="space-y-4">
                    <?php foreach($entradas_formulario as $ent): 
                        $data = json_decode($ent['data'], true) ?: [];
                    ?>
                    <div class="p-5 rounded-2xl bg-indigo-50/30 border border-indigo-100/50">
                        <div class="flex justify-between items-start mb-3">
                            <h4 class="font-bold text-gray-900 text-sm"><?php echo htmlspecialchars($ent['formulario_nombre']); ?></h4>
                            <span class="text-[10px] font-bold text-gray-400"><?php echo date('d M, H:i', strtotime($ent['created_at'])); ?></span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach($data as $lbl => $val): 
                                if($lbl === 'paso_actual' || is_array($val)) continue;
                            ?>
                            <div class="bg-white p-2 rounded-xl border border-indigo-50">
                                <span class="block text-[9px] font-black text-indigo-400 uppercase tracking-widest"><?php echo htmlspecialchars($lbl); ?></span>
                                <span class="text-xs font-bold text-gray-700 truncate block"><?php echo htmlspecialchars($val); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Firma Digital -->
            <?php if ($firma): ?>
            <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm flex flex-col items-center">
                 <h3 class="font-black text-gray-900 text-sm uppercase tracking-widest mb-4">Aceptado y Firmado por Cliente</h3>
                 <img src="<?php echo $firma; ?>" class="max-h-32 border-b-2 border-gray-900 pb-2 mb-2">
                 <p class="text-xs text-gray-400 font-mono">Firma Digital Capturada - <?php echo $ot['fecha_creacion']; ?></p>
            </div>
            <?php endif; ?>

        </div>

        <!-- Columna Derecha: Info Cliente y Notas -->
        <div class="space-y-6">
            <div class="bg-gray-50 p-6 rounded-3xl border border-gray-200">
                <h3 class="font-bold text-gray-900 mb-4">Cliente</h3>
                <div class="space-y-3">
                    <p class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        <?php echo htmlspecialchars($ot['cliente_nombre']); ?>
                    </p>
                    <p class="flex items-center gap-2 text-sm text-gray-600">
                         <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                         <?php echo htmlspecialchars($ot['celular_contacto']); ?>
                    </p>
                    <p class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <?php echo htmlspecialchars($ot['email']); ?>
                    </p>
                    <div class="pt-4 mt-4 border-t border-gray-200">
                        <a href="https://wa.me/57<?php echo $ot['celular_contacto']; ?>?text=Hola, te escribo sobre la Orden de Trabajo #<?php echo $id; ?>" target="_blank" class="flex items-center justify-center gap-2 w-full py-3 bg-green-500 text-white font-bold rounded-xl hover:bg-green-600 transition-all">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-8.683-2.031-.967-.272-.297-.471-.446-.917-.446-.446 0-.966.173-1.472.719-.506.546-1.931 1.884-1.931 4.595 0 2.711 1.982 5.332 2.254 5.698.272.371 3.868 6.096 9.479 8.356 3.655 1.473 4.404 1.18 5.196 1.106.791-.074 1.758-.718 2.006-1.413.248-.694.248-1.289.173-1.413z"/></svg>
                            Contactar Cliente
                        </a>
                    </div>
                </div>
            </div>

            <div class="bg-yellow-50 p-6 rounded-3xl border border-yellow-100">
                <h3 class="font-bold text-yellow-900 mb-2">Notas Internas</h3>
                <textarea class="w-full bg-white border border-yellow-200 rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-yellow-400" rows="4" placeholder="Notas de uso interno sobre este servicio..."><?php echo htmlspecialchars($ot['notas_internas'] ?? ''); ?></textarea>
                <button class="mt-2 text-xs font-bold text-yellow-700 hover:text-yellow-900">Guardar Nota</button>
            </div>
        </div>
    </div>
</div>

<script>
async function updateStatus(id, status) {
    try {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('status', status);
        
        await fetch('api_update_ot.php', {
            method: 'POST',
            body: formData
        });
        
        // Visual feedback (optional)
        alert('Estado actualizado');
    } catch(e) {
        alert('Error actualizando estado');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
