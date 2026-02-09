<?php
require_once 'db.php';
include 'includes/header.php';

// Filtros
$estado = $_GET['estado'] ?? 'Todos';
$search = $_GET['q'] ?? '';

// Construir Query
$query = "SELECT ot.*, c.nombre as cliente_nombre, cot.numero_cotizacion 
          FROM ordenes_trabajo ot
          JOIN clientes c ON ot.cliente_id = c.id
          JOIN cotizaciones cot ON ot.cotizacion_id = cot.id
          WHERE ot.empresa_id = " . getEmpresaId();

if ($estado !== 'Todos') {
    $query .= " AND ot.estado = '$estado'";
}
if ($search) {
    $query .= " AND (c.nombre LIKE '%$search%' OR ot.id LIKE '%$search%' OR cot.numero_cotizacion LIKE '%$search%')";
}

$query .= " ORDER BY ot.fecha_creacion DESC";
$ordenes = $pdo->query($query)->fetchAll();

// Contadores
$pendientes = $pdo->query("SELECT COUNT(*) FROM ordenes_trabajo WHERE empresa_id = " . getEmpresaId() . " AND estado = 'Pendiente'")->fetchColumn();
$proceso = $pdo->query("SELECT COUNT(*) FROM ordenes_trabajo WHERE empresa_id = " . getEmpresaId() . " AND estado = 'En Proceso'")->fetchColumn();
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Órdenes de Trabajo</h1>
            <p class="text-sm text-gray-500 font-medium mt-1">Gestiona la ejecución de servicios aceptados.</p>
        </div>
        <div class="flex gap-3">
             <div class="bg-orange-50 px-4 py-2 rounded-xl text-orange-600 font-bold text-xs border border-orange-100 flex items-center gap-2">
                 <span class="w-2 h-2 bg-orange-500 rounded-full animate-pulse"></span>
                 <?php echo $pendientes; ?> Pendientes
             </div>
             <div class="bg-blue-50 px-4 py-2 rounded-xl text-blue-600 font-bold text-xs border border-blue-100 flex items-center gap-2">
                 <span class="w-2 h-2 bg-blue-500 rounded-full"></span>
                 <?php echo $proceso; ?> En Proceso
             </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 mb-8 flex flex-col md:flex-row gap-4 justify-between items-center">
        <div class="flex gap-2 overflow-x-auto pb-2 md:pb-0 w-full md:w-auto scrollbar-hide">
            <?php 
            $states = ['Todos', 'Pendiente', 'En Proceso', 'Finalizada', 'Entregada'];
            foreach($states as $st): 
                $active = ($estado === $st) ? 'bg-gray-900 text-white' : 'bg-gray-50 text-gray-500 hover:bg-gray-100';
            ?>
            <a href="?estado=<?php echo $st; ?>" class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap <?php echo $active; ?>">
                <?php echo $st; ?>
            </a>
            <?php endforeach; ?>
        </div>
        
        <form class="w-full md:w-64 relative">
             <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar OT, Cliente..." class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition-all">
             <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </form>
    </div>

    <!-- List -->
    <div class="grid grid-cols-1 gap-4">
        <?php if(empty($ordenes)): ?>
            <div class="text-center py-20 bg-gray-50 rounded-3xl border border-dashed border-gray-200">
                <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-sm">
                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                </div>
                <p class="text-gray-500 font-medium">No se encontraron órdenes de trabajo.</p>
            </div>
        <?php else: ?>
            <?php foreach($ordenes as $ot): 
                $f_prog = $ot['fecha_programada'] ? date('d M, Y', strtotime($ot['fecha_programada'])) : 'Sin Fecha';
                $st_color = match($ot['estado']) {
                    'Pendiente' => 'bg-orange-100 text-orange-700',
                    'En Proceso' => 'bg-blue-100 text-blue-700',
                    'Finalizada' => 'bg-green-100 text-green-700',
                    default => 'bg-gray-100 text-gray-600'
                };
            ?>
            <a href="ver-orden.php?id=<?php echo $ot['id']; ?>" class="group bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-xl hover:scale-[1.01] transition-all flex flex-col md:flex-row items-center gap-6 relative overflow-hidden">
                <div class="hidden md:block absolute left-0 top-0 bottom-0 w-1.5 <?php echo str_replace('bg-', 'bg-', $st_color); ?>"></div>
                
                <div class="w-full md:w-auto flex items-center justify-between md:justify-start gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-gray-50 flex items-center justify-center font-black text-xs text-gray-400 border border-gray-100 group-hover:bg-blue-50 group-hover:text-blue-500 transition-colors">
                        OT-<?php echo $ot['id']; ?>
                    </div>
                    <div class="text-left md:w-64">
                        <p class="font-black text-gray-900 text-lg leading-tight group-hover:text-blue-600 transition-colors"><?php echo htmlspecialchars($ot['cliente_nombre']); ?></p>
                        <p class="text-[10px] uppercase font-bold text-gray-400 mt-1 tracking-wider">Cotización #<?php echo $ot['numero_cotizacion']; ?></p>
                    </div>
                </div>

                <div class="flex-1 w-full flex flex-col md:flex-row items-start md:items-center gap-6 justify-between">
                    <div class="flex items-center gap-3 bg-gray-50 px-4 py-2 rounded-xl text-gray-500">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        <span class="text-xs font-bold"><?php echo $f_prog; ?></span>
                    </div>
                    
                    <span class="px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest <?php echo $st_color; ?>">
                        <?php echo $ot['estado']; ?>
                    </span>
                </div>

                <div class="text-gray-300 group-hover:translate-x-1 transition-transform">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
