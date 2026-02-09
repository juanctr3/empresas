<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';
require_once 'includes/header.php';

$empresa_id = getEmpresaId();

// Filtros
$status_filter = $_GET['status'] ?? '';
$search = $_GET['q'] ?? '';

$where = "WHERE f.empresa_id = ?";
$params = [$empresa_id];

if ($status_filter) {
    $where .= " AND f.estado = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where .= " AND (f.numero_factura LIKE ? OR c.nombre LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM facturas f JOIN clientes c ON f.cliente_id = c.id $where");
$stmtCount->execute($params);
$total_rows = $stmtCount->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

$sql = "
    SELECT f.*, c.nombre as cliente_nombre 
    FROM facturas f 
    JOIN clientes c ON f.cliente_id = c.id 
    $where 
    ORDER BY f.created_at DESC 
    LIMIT $per_page OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$facturas = $stmt->fetchAll();
?>

<div class="max-w-7xl mx-auto space-y-6">
    
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Facturación</h1>
            <p class="text-gray-500 font-medium">Gestiona los cobros y facturas de tu empresa.</p>
        </div>
        <a href="nueva-factura.php" class="px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition-all flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Nueva Factura
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex flex-col md:flex-row gap-4 justify-between items-center">
        <div class="flex gap-2 overflow-x-auto w-full md:w-auto pb-2 md:pb-0">
            <a href="facturas.php" class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-widest <?php echo !$status_filter ? 'bg-indigo-50 text-indigo-600' : 'text-gray-500 hover:bg-gray-50'; ?>">Todas</a>
            <a href="?status=Borrador" class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-widest <?php echo $status_filter === 'Borrador' ? 'bg-gray-100 text-gray-700' : 'text-gray-500 hover:bg-gray-50'; ?>">Borrador</a>
            <a href="?status=Enviada" class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-widest <?php echo $status_filter === 'Enviada' ? 'bg-blue-50 text-blue-600' : 'text-gray-500 hover:bg-gray-50'; ?>">Enviada</a>
            <a href="?status=Pagada" class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-widest <?php echo $status_filter === 'Pagada' ? 'bg-green-50 text-green-600' : 'text-gray-500 hover:bg-gray-50'; ?>">Pagada</a>
            <a href="?status=Anulada" class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-widest <?php echo $status_filter === 'Anulada' ? 'bg-red-50 text-red-600' : 'text-gray-500 hover:bg-gray-50'; ?>">Anulada</a>
        </div>
        
        <form class="flex gap-2 w-full md:w-auto">
            <input type="text" name="q" placeholder="Buscar factura o cliente..." value="<?php echo htmlspecialchars($search); ?>" class="w-full md:w-64 px-4 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all text-sm">
            <button type="submit" class="p-2 bg-gray-100 text-gray-600 rounded-xl hover:bg-gray-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </button>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-[2rem] shadow-xl shadow-gray-100 overflow-hidden border border-gray-100">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-wider">Número</th>
                        <th class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-wider">Cliente</th>
                        <th class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-wider">Fecha</th>
                        <th class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-4 text-left text-xs font-black text-gray-400 uppercase tracking-wider">Total</th>
                        <th class="px-6 py-4 text-right text-xs font-black text-gray-400 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($facturas)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-gray-400 font-medium">
                                No se encontraron facturas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($facturas as $fac): 
                            $bgStatus = match($fac['estado']) {
                                'Borrador' => 'bg-gray-100 text-gray-600',
                                'Enviada' => 'bg-blue-50 text-blue-600',
                                'Pagada' => 'bg-green-50 text-green-600',
                                'Anulada' => 'bg-red-50 text-red-600',
                                default => 'bg-gray-100'
                            };
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-bold text-gray-900"><?php echo htmlspecialchars($fac['numero_factura']); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <a href="perfil-cliente.php?id=<?php echo $fac['cliente_id']; ?>" class="font-bold text-gray-700 hover:text-indigo-600 transition-colors">
                                    <?php echo htmlspecialchars($fac['cliente_nombre']); ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-600"><?php echo date('d M, Y', strtotime($fac['fecha_emision'])); ?></div>
                                <div class="text-[10px] uppercase text-gray-400 font-black">Vence: <?php echo date('d M', strtotime($fac['fecha_vencimiento'])); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <select onchange="updateStatus(<?php echo $fac['id']; ?>, this.value)" class="px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest cursor-pointer border-none focus:ring-2 focus:ring-indigo-500 <?php echo $bgStatus; ?>">
                                    <?php 
                                    $states = ['Borrador', 'Creada', 'Enviada', 'Pendiente de pago', 'Pagada', 'Anulada', 'Vencida'];
                                    foreach($states as $st): 
                                    ?>
                                    <option value="<?php echo $st; ?>" <?php echo $fac['estado'] == $st ? 'selected' : ''; ?> class="bg-white text-gray-900">
                                        <?php echo $st; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-lg font-black text-gray-900">$<?php echo number_format($fac['total'], 0); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2 text-gray-400">
                                    <a href="ver-factura.php?h=<?php echo $fac['hash_publico']; ?>" target="_blank" class="p-2 hover:text-indigo-600 hover:bg-white rounded-lg transition-all" title="Ver Pública">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                    <a href="nueva-factura.php?id=<?php echo $fac['id']; ?>" class="p-2 hover:text-blue-600 hover:bg-white rounded-lg transition-all" title="Editar">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </a>
                                    <button class="p-2 hover:text-green-600 hover:bg-white rounded-lg transition-all" onclick="sendInvoice(<?php echo $fac['id']; ?>)" title="Enviar">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-center gap-2">
            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&q=<?php echo $search; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg font-bold text-xs <?php echo $page == $i ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function updateStatus(id, newStatus) {
    if(!confirm('¿Cambiar estado a ' + newStatus + '?')) {
        location.reload(); // Revert selection
        return;
    }
    
    const body = new FormData();
    body.append('action', 'update_status');
    body.append('id', id);
    body.append('status', newStatus);
    
    fetch('api_facturacion.php', { method: 'POST', body: body })
    .then(r => r.json())
    .then(d => {
        if(d.status === 'success') {
            // Optional: Toast or just reload to update colors logic
            location.reload();
        } else {
            alert('Error: ' + d.message);
            location.reload();
        }
    })
    .catch(e => {
        alert('Error de conexión');
        location.reload();
    });
}

function sendInvoice(id) {
    if(!confirm('¿Enviar factura al cliente? Se usará WhatsApp y Email si están disponibles.')) return;
    
    // Simple alert UI for Demo, ideally a modal
    const body = new FormData();
    body.append('action', 'send');
    body.append('method', 'both');
    body.append('id', id);
    
    fetch('api_facturacion.php', { method: 'POST', body: body })
    .then(r => r.json())
    .then(d => {
        if(d.status === 'success') {
            alert('Enviado correctamente:\nWhatsApp: ' + (d.results.whatsapp||'-') + '\nEmail: ' + (d.results.email||'-'));
            location.reload();
        } else {
            alert('Error: ' + d.message);
        }
    })
    .catch(e => alert('Error de conexión'));
}
</script>

<?php include 'includes/footer.php'; ?>
