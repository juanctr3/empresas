<?php
require_once 'db.php';
include 'includes/header.php';

// Fetch Active OTs with Logistics info or requiring it
$query = "SELECT ot.*, c.nombre as cliente_nombre, c.direccion as cliente_direccion
          FROM ordenes_trabajo ot
          JOIN clientes c ON ot.cliente_id = c.id
          WHERE ot.empresa_id = " . getEmpresaId() . "
          AND ot.estado NOT IN ('Cancelada')
          ORDER BY ot.fecha_programada ASC";

$ordenes = $pdo->query($query)->fetchAll();

// Group by Status for Kanban
$kanban = [
    'Pendiente' => [],
    'En Proceso' => [],
    'Finalizada' => [],
    'Entregada' => []
];

foreach ($ordenes as $ot) {
    // If status is not in keys, fallback to Pendiente or ignore?
    // Let's assume standard statuses.
    $st = $ot['estado'];
    if (!isset($kanban[$st])) $st = 'Pendiente';
    
    // Parse logistics data if available
    $aceptada_data = json_decode($ot['aceptada_data'] ?? '{}', true);
    $logistica = $aceptada_data['logistica'] ?? null;
    $ot['logistica'] = $logistica;
    
    $kanban[$st][] = $ot;
}
?>

<div class="max-w-7xl mx-auto px-4 py-8 h-[calc(100vh-80px)] overflow-hidden flex flex-col">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6 shrink-0">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Tablero Logístico</h1>
            <p class="text-sm text-gray-500 font-medium">Gestión visual de recolecciones y entregas.</p>
        </div>
        <div class="flex gap-2">
            <a href="ordenes.php" class="bg-white border border-gray-200 text-gray-600 px-4 py-2 rounded-xl font-bold hover:bg-gray-50 transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                Ver Lista
            </a>
            <button onclick="window.location.reload()" class="bg-blue-50 text-blue-600 p-2 rounded-xl hover:bg-blue-100 transition-all">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </button>
        </div>
    </div>

    <!-- Kanban Board -->
    <div class="flex-1 overflow-x-auto overflow-y-hidden">
        <div class="flex gap-6 h-full min-w-[1000px]">
            
            <!-- Column: Pendiente -->
            <div class="flex-1 flex flex-col bg-gray-50 rounded-3xl border border-gray-200/60 max-w-sm">
                <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-white rounded-t-3xl">
                    <h3 class="font-black text-gray-700 flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-orange-400"></span>
                        Pendiente / Por Recoger
                    </h3>
                    <span class="bg-gray-100 text-gray-600 text-xs font-bold px-2 py-1 rounded-lg"><?php echo count($kanban['Pendiente']); ?></span>
                </div>
                <div class="p-4 space-y-3 overflow-y-auto custom-scroll flex-1 bg-gray-50/50" id="col-pendiente" ondrop="drop(event, 'Pendiente')" ondragover="allowDrop(event)">
                    <?php renderCards($kanban['Pendiente'], 'orange'); ?>
                </div>
            </div>

            <!-- Column: En Proceso -->
            <div class="flex-1 flex flex-col bg-blue-50/30 rounded-3xl border border-blue-100 max-w-sm">
                <div class="p-4 border-b border-blue-100 flex justify-between items-center bg-white rounded-t-3xl">
                    <h3 class="font-black text-blue-900 flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-blue-500 animate-pulse"></span>
                        En Proceso / Tránsito
                    </h3>
                    <span class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-1 rounded-lg"><?php echo count($kanban['En Proceso']); ?></span>
                </div>
                <div class="p-4 space-y-3 overflow-y-auto custom-scroll flex-1" id="col-proceso" ondrop="drop(event, 'En Proceso')" ondragover="allowDrop(event)">
                    <?php renderCards($kanban['En Proceso'], 'blue'); ?>
                </div>
            </div>
            
             <!-- Column: Finalizada -->
             <div class="flex-1 flex flex-col bg-green-50/30 rounded-3xl border border-green-100 max-w-sm">
                <div class="p-4 border-b border-green-100 flex justify-between items-center bg-white rounded-t-3xl">
                    <h3 class="font-black text-green-900 flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-green-500"></span>
                        Finalizada / Entregada
                    </h3>
                    <span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded-lg"><?php echo count($kanban['Finalizada']) + count($kanban['Entregada']); ?></span>
                </div>
                <div class="p-4 space-y-3 overflow-y-auto custom-scroll flex-1" id="col-finalizada" ondrop="drop(event, 'Finalizada')" ondragover="allowDrop(event)">
                    <?php renderCards(array_merge($kanban['Finalizada'], $kanban['Entregada']), 'green'); ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php 
function renderCards($items, $color) {
    if(empty($items)) {
        echo '<div class="text-center py-10 opacity-40"><p class="text-xs font-bold text-gray-400">Sin órdenes</p></div>';
        return;
    }
    foreach($items as $ot) {
        $log = $ot['logistica'];
        $fecha = $log['fecha'] ?? ($ot['fecha_programada'] ?? ($ot['fecha_creacion']));
        $fecha_fmt = date('d M', strtotime($fecha));
        $direccion = $log['direccion'] ?? $ot['cliente_direccion'] ?? 'Sin dirección';
        
        echo "
        <div id='card-{$ot['id']}' draggable='true' ondragstart='drag(event)' class='bg-white p-4 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md cursor-grab active:cursor-grabbing transition-all relative group'>
            <div class='flex justify-between items-start mb-2'>
                <span class='text-[10px] font-black uppercase text-gray-400 bg-gray-50 px-2 py-1 rounded-md'>OT #{$ot['id']}</span>
                <span class='text-[10px] font-bold text-{$color}-600 bg-{$color}-50 px-2 py-1 rounded-md'>{$fecha_fmt}</span>
            </div>
            <h4 class='font-bold text-gray-800 text-sm leading-tight mb-1'>".htmlspecialchars($ot['cliente_nombre'])."</h4>
            <p class='text-xs text-gray-500 flex items-center gap-1 truncate mb-3'>
                <svg class='w-3 h-3 text-gray-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'></path><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 11a3 3 0 11-6 0 3 3 0 016 0z'></path></svg>
                ".htmlspecialchars($direccion)."
            </p>
            <div class='flex justify-end pt-2 border-t border-gray-50'>
                 <a href='ver-orden.php?id={$ot['id']}' class='text-xs font-bold text-indigo-500 hover:text-indigo-700'>Ver detalle &rarr;</a>
            </div>
        </div>
        ";
    }
}
?>

<script>
    function allowDrop(ev) { ev.preventDefault(); }
    
    function drag(ev) {
        ev.dataTransfer.setData("text", ev.target.id);
        ev.target.classList.add('opacity-50');
    }
    
    // Quick status update on drop
    async function drop(ev, newStatus) {
        ev.preventDefault();
        var data = ev.dataTransfer.getData("text");
        var card = document.getElementById(data);
        card.classList.remove('opacity-50');
        
        // Find dropping container
        let target = ev.target;
        while(target && !target.id.startsWith('col-')) {
            target = target.parentNode;
        }
        
        if (target) {
            target.appendChild(card); // Move visual
            
            // API Call
            const id = data.replace('card-', '');
            const formData = new FormData();
            formData.append('id', id);
            formData.append('status', newStatus);
            
            try {
                await fetch('api_update_ot.php', { method: 'POST', body: formData });
                // Optional: Flash success
            } catch(e) {
                alert("Error al actualizar estado");
            }
        }
    }
    
    // Reset opacity if drag ends without drop
    document.addEventListener("dragend", function(event) {
        event.target.classList.remove("opacity-50");
    });
</script>

<?php include 'includes/footer.php'; ?>
