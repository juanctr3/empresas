<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

// Verificación de sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$empresa_id = getEmpresaId();
$usuario_id = $_SESSION['user_id'];

// Filtro simple
$estado_filter = $_GET['estado'] ?? 'Pendiente';

// Consulta
$sql = "
    SELECT r.*, c.numero_cotizacion, c.total, cl.nombre as cliente_nombre 
    FROM cotizacion_recordatorios r
    JOIN cotizaciones c ON r.cotizacion_id = c.id
    JOIN clientes cl ON c.cliente_id = cl.id
    WHERE r.empresa_id = ?
";
$params = [$empresa_id];

if ($estado_filter !== 'Todos') {
    $sql .= " AND r.estado = ?";
    $params[] = $estado_filter;
}

$sql .= " ORDER BY r.fecha_programada ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recordatorios = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight text-amber-500">Recordatorios</h1>
            <p class="text-gray-500 mt-1">Gestión de alertas y seguimiento de cotizaciones.</p>
        </div>
        <div class="flex bg-gray-100 p-1 rounded-2xl border border-gray-200">
            <a href="?estado=Pendiente" class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all <?php echo $estado_filter == 'Pendiente' ? 'bg-white shadow-sm text-amber-600' : 'text-gray-400 hover:text-gray-600'; ?>">Pendientes</a>
            <a href="?estado=Enviado" class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all <?php echo $estado_filter == 'Enviado' ? 'bg-white shadow-sm text-green-600' : 'text-gray-400 hover:text-gray-600'; ?>">Enviados</a>
            <a href="?estado=Todos" class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all <?php echo $estado_filter == 'Todos' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-400 hover:text-gray-600'; ?>">Todos</a>
        </div>
    </div>

    <div class="bg-white rounded-3xl border border-gray-100 shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest">Fecha Programada</th>
                        <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest">Cotización</th>
                        <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest">Asunto / Mensaje</th>
                        <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest">Cliente</th>
                        <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest text-center">Estado</th>
                        <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($recordatorios)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-20 text-center text-gray-400 italic">No hay recordatorios en esta sección.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($recordatorios as $rec): ?>
                        <tr class="hover:bg-amber-50/20 transition-all group">
                            <td class="px-6 py-5 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div class="p-2 bg-amber-50 text-amber-500 rounded-lg">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-bold text-gray-900"><?php echo date('d M, Y', strtotime($rec['fecha_programada'])); ?></span>
                                        <span class="text-[10px] text-gray-400 font-bold uppercase"><?php echo date('h:i A', strtotime($rec['fecha_programada'])); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <a href="ver-cotizacion.php?id=<?php echo $rec['cotizacion_id']; ?>" class="font-black text-blue-600 hover:underline">
                                    #<?php echo htmlspecialchars($rec['numero_cotizacion']); ?>
                                </a>
                                <div class="text-[10px] text-gray-400 font-bold uppercase mt-1">
                                    $<?php echo number_format($rec['total'], 2); ?>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <div class="font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($rec['asunto']); ?></div>
                                <div class="text-xs text-gray-500 line-clamp-2" title="<?php echo htmlspecialchars($rec['mensaje']); ?>">
                                    <?php echo htmlspecialchars($rec['mensaje']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <div class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($rec['cliente_nombre']); ?></div>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <?php 
                                    $stColor = match($rec['estado']) {
                                        'Pendiente' => 'bg-amber-100 text-amber-600',
                                        'Enviado' => 'bg-green-100 text-green-600',
                                        'Fallido' => 'bg-red-100 text-red-600',
                                        'Cancelado' => 'bg-gray-100 text-gray-400',
                                        default => 'bg-gray-100'
                                    };
                                ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest <?php echo $stColor; ?>">
                                    <?php echo $rec['estado']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-5 text-right">
                                <div class="flex justify-end gap-2">
                                    <?php if($rec['estado'] == 'Pendiente'): ?>
                                    <button onclick="enviarAhora(<?php echo $rec['id']; ?>)" class="p-2 text-blue-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Enviar Ahora">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                    </button>
                                    <?php endif; ?>
                                    <button onclick="eliminarRecordatorio(<?php echo $rec['id']; ?>)" class="p-2 text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Eliminar">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function eliminarRecordatorio(id) {
    if(!confirm('¿Estás seguro de eliminar este recordatorio?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    
    fetch('api_recordatorios.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        if(d.status === 'success') location.reload();
        else alert('Error: ' + d.message);
    });
}

function enviarAhora(id) {
    if(!confirm('¿Enviar este recordatorio inmediatamente?')) return;
    const fd = new FormData();
    fd.append('action', 'send_now');
    fd.append('id', id);
    
    fetch('api_recordatorios.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        alert(d.message);
        location.reload();
    });
}
</script>

<?php include 'includes/footer.php'; ?>
