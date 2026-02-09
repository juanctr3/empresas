<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

// Filtros
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$status_filter = $_GET['status'] ?? 'Todas';
$valid_statuses = ['Todas', 'Borrador', 'Enviada', 'Aprobada', 'Rechazada'];
if (!in_array($status_filter, $valid_statuses)) $status_filter = 'Todas';

// Obtener Cotizaciones con Filtro
// Obtener Cotizaciones con Filtro
$where = "WHERE c.empresa_id = ?";
$params = [getEmpresaId()];

if (!tienePermiso('ver_todas_cotizaciones')) {
    $where .= " AND c.usuario_id = ?";
    $params[] = $_SESSION['user_id'];
}

$sql = "
    SELECT c.*, cl.nombre as cliente_nombre, cl.email as cliente_email, cl.celular_contacto, cl.telefono as cliente_telefono 
    FROM cotizaciones c 
    JOIN clientes cl ON c.cliente_id = cl.id 
    $where
";

if ($status_filter !== 'Todas') {
    $sql .= " AND c.estado = ? ";
    $params[] = $status_filter;
}

$sql .= " ORDER BY c.fecha DESC, c.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cotizaciones = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight text-blue-600">Gestión de Cotizaciones</h1>
            <p class="text-gray-500 mt-1">Controla estados y notifica a tus clientes vía WhatsApp.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <!-- Tabs de Filtro -->
            <div class="flex bg-gray-100 p-1 rounded-2xl border border-gray-200">
                <?php foreach($valid_statuses as $st): ?>
                    <a href="?status=<?php echo $st; ?>" class="px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all <?php echo $status_filter == $st ? 'bg-white shadow-sm text-blue-600' : 'text-gray-400 hover:text-gray-600'; ?>">
                        <?php echo $st; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <a href="nueva-cotizacion.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-2xl shadow-xl shadow-blue-100 transition-all active:scale-95 flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Nueva
            </a>
        </div>
    </div>

    <!-- Mensajes de Estado -->
    <div class="flex flex-wrap gap-2">
        <?php if(isset($_GET['msg'])): ?>
            <div class="bg-green-100 text-green-700 px-4 py-2 rounded-xl text-sm font-bold flex items-center animate-fade-in shadow-sm">
                <?php 
                    if($_GET['msg'] == 'enviado') echo '✅ WhatsApp enviado!';
                    if($_GET['msg'] == 'estado_actualizado') echo '✅ Estado actualizado y notificado!';
                    if($_GET['msg'] == 'editado') echo '✅ Cotización actualizada con éxito!';
                ?>
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['error'])): ?>
            <div class="bg-red-100 text-red-700 px-4 py-2 rounded-xl text-sm font-bold flex items-center">
                ❌ <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tabla de Cotizaciones -->
    <div class="bg-white rounded-3xl border border-gray-100 shadow-2xl shadow-gray-200/50 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest">Número</th>
                        <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest">Cliente</th>
                        <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest">Total</th>
                        <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest">Estado Actual</th>
                        <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest text-center">Gestión Rápida</th>
                        <th class="px-6 py-5 text-xs font-bold text-gray-400 uppercase tracking-widest text-right">Ver</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($cotizaciones)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-20 text-center text-gray-400 italic">No hay cotizaciones con estado "<?php echo $status_filter; ?>".</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($cotizaciones as $cot): ?>
                        <tr class="hover:bg-blue-50/20 transition-all group">
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-2">
                                    <div class="font-black text-gray-900 tracking-tighter">#<?php echo htmlspecialchars($cot['numero_cotizacion'] ?: $cot['id']); ?></div>
                                    <?php if($cot['leida']): ?>
                                        <div class="flex items-center" title="Leída por el cliente el <?php echo date('d/m H:i', strtotime($cot['fecha_leida'])); ?> (<?php echo $cot['vistas_count']; ?> vistas)">
                                            <svg class="w-4 h-4 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline><polyline points="22 10 11 21 6 16"></polyline></svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-[10px] text-gray-400 font-bold"><?php echo date('d M, Y', strtotime($cot['fecha'])); ?></div>
                            </td>
                            <td class="px-6 py-5 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <a href="perfil-cliente.php?id=<?php echo $cot['cliente_id']; ?>" class="text-sm font-bold text-gray-800 hover:text-blue-600 transition-colors"><?php echo htmlspecialchars($cot['cliente_nombre']); ?></a>
                                    <?php if(!empty($cot['notas_internas'])): ?>
                                        <svg class="w-3 h-3 text-indigo-400" fill="currentColor" viewBox="0 0 20 20" title="Tiene notas internas"><path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z"></path><path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1H8a3 3 0 00-3 3v10a1 1 0 01-1 1H2V6z" clip-rule="evenodd"></path></svg>
                                    <?php endif; ?>
                                </div>
                                <?php 
                                    $tareas = json_decode($cot['tareas_json'] ?? '[]', true);
                                    $pendientes = count(array_filter($tareas, function($t) { return !($t['completada'] ?? false); }));
                                    if($pendientes > 0):
                                ?>
                                    <div class="flex items-center gap-1 mt-1">
                                        <span class="text-[9px] bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-md font-black uppercase tracking-tighter">
                                            <?php echo $pendientes; ?> Tarea<?php echo $pendientes > 1 ? 's' : ''; ?> pendiente
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-5 whitespace-nowrap">
                                <span class="font-black text-blue-600 tracking-tight">$<?php echo number_format($cot['total'], 2); ?></span>
                            </td>
                            <td class="px-6 py-5">
                                <?php 
                                    $statusClasses = [
                                        'Borrador' => 'bg-gray-100 text-gray-500',
                                        'Enviada' => 'bg-indigo-50 text-indigo-600',
                                        'Aprobada' => 'bg-green-50 text-green-600',
                                        'Rechazada' => 'bg-rose-50 text-rose-600'
                                    ];
                                    $class = $statusClasses[$cot['estado']] ?? 'bg-gray-100 text-gray-500';
                                ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-tighter <?php echo $class; ?>">
                                    <?php echo $cot['estado']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-5 text-center relative">
                                <button onclick='toggleActionMenu(event, <?php echo json_encode([
                                    "id" => $cot["id"],
                                    "numero" => $cot["numero_cotizacion"] ?: $cot["id"],
                                    "nombre" => $cot["cliente_nombre"],
                                    "email" => $cot["cliente_email"],
                                    "celular" => $cot["celular_contacto"],
                                    "telefono" => $cot["cliente_telefono"],
                                    "hash" => $cot["hash_publico"],
                                    "estado" => $cot["estado"],
                                    "link" => getBaseUrl() . "propuesta.php?h=" . $cot["hash_publico"]
                                ]); ?>)' class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-all">
                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 13a1 1 0 100-2 1 1 0 000 2zm0-5a1 1 0 100-2 1 1 0 000 2zm0 10a1 1 0 100-2 1 1 0 000 2z"></path></svg>
                                </button>
                            </td>
                            <!-- "Ver" Column removed as it is merged into actions or redundant? No, keep "Ver" column or merge? User asked to compact "Gestion Rapida". "Ver" is separate. I will keep "Ver" separate for now as requested only for "Gestión Rápida". Wait, previous code had "Ver" column AS WELL. I should check if I am replacing the right column. 
                            The previous code had:
                            <th ...>Gestión Rápida</th>
                            <th ...>Ver</th>
                            
                            And in body:
                            <td ...> [Buttons] </td>
                            <td ...> [Ver buttons] </td>
                            
                            The user said "mostrar las opciones dadas en la columna 'Gestión Rápida'". The "Ver" column has edit/view/delete.
                            The "Gestión Rápida" column has email, duplicate, history, WA, attachments, reminders, chat, link, approve/reject.
                            
                            I will ONLY replace the "Gestión Rápida" column content. 
                            -->
                            <td class="px-6 py-5 text-right">
                                <div class="flex justify-end gap-1">
                                    <button onclick="verEstadisticas(<?php echo $cot['id']; ?>, '<?php echo htmlspecialchars($cot['numero_cotizacion'] ?: $cot['id']); ?>')" class="p-2 text-gray-400 hover:text-orange-500 transition-colors" title="Estadísticas de Vistas">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                                    </button>
                                     <a href="nueva-cotizacion.php?id=<?php echo $cot['id']; ?>" class="p-2 text-gray-400 hover:text-indigo-600 transition-colors" title="Editar">
                                         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                     </a>
                                     <a href="nueva-cotizacion.php?id=<?php echo $cot['id']; ?>&step=4" class="p-2 text-gray-400 hover:text-purple-600 transition-colors" title="Diseño Visual / Plantilla">
                                         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                                     </a>
                                    <a href="exportar-cotizacion.php?id=<?php echo $cot['id']; ?>" class="p-2 text-gray-400 hover:text-blue-600 transition-colors" title="Visualizar / Imprimir PDF">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </a>
                                    <button onclick="eliminarCotizacion(<?php echo $cot['id']; ?>)" class="p-2 text-gray-400 hover:text-red-600 transition-colors" title="Eliminar">
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

<!-- Modal Envío Email -->
<div id="modalEmail" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="cerrarModalEmail()"></div>
    <div class="bg-white rounded-[2.5rem] w-full max-w-lg overflow-hidden shadow-2xl relative z-10 animate-fade-in-up">
        <div class="bg-gray-50/50 p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-xl font-black text-gray-900 italic">Enviar <span class="text-blue-600">por Email</span></h3>
            <button onclick="cerrarModalEmail()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="p-8 space-y-6">
            <input type="hidden" id="email-cot-id">
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Destinatarios (separados por coma)</label>
                <input type="text" id="email-recipients" class="w-full px-5 py-4 rounded-2xl bg-gray-50 border border-gray-100 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all text-sm font-bold" placeholder="email1@ejemplo.com, email2@ejemplo.com">
            </div>
            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Mensaje Personalizado (opcional)</label>
                <textarea id="email-message" rows="4" class="w-full px-5 py-4 rounded-2xl bg-gray-50 border border-gray-100 focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all text-sm" placeholder="Hola, te adjunto la cotización..."></textarea>
            </div>
            
            <button onclick="confirmarEnvioEmail()" id="btn-confirmar-email" class="w-full py-4 bg-blue-600 text-white rounded-2xl font-black shadow-xl shadow-blue-200 hover:bg-blue-700 transition-all flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                ENVIAR COTIZACIÓN
            </button>
        </div>
    </div>
</div>

<!-- Modal Envío WhatsApp Multi-Recipiente -->
<div id="modalEnvioWA" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="cerrarModalEnvioWA()"></div>
    <div class="bg-white rounded-[2.5rem] w-full max-w-xl overflow-hidden shadow-2xl relative z-10 animate-fade-in-up">
        <div class="bg-gray-50/50 p-6 border-b border-gray-100 flex justify-between items-center bg-white/90 backdrop-blur-md">
            <h3 class="text-xl font-black text-gray-900 italic">Enviar <span class="text-green-600">por WhatsApp</span></h3>
            <button onclick="cerrarModalEnvioWA()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="p-8 space-y-6 max-h-[70vh] overflow-y-auto custom-scroll">
            <input type="hidden" id="wa-envio-cot-id">
            <input type="hidden" id="wa-envio-hash">
            
            <div id="wa-recipients-container" class="space-y-3">
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1">Seleccionar Destinatarios</label>
                <!-- Filas de números se insertarán aquí -->
            </div>

            <button onclick="agregarFilaWA()" class="w-full py-3 border-2 border-dashed border-gray-200 rounded-2xl text-xs font-black text-gray-400 hover:border-green-400 hover:text-green-600 transition-all flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                AGREGAR OTRO NÚMERO
            </button>

            <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 ml-1">Mensaje (opcional)</label>
                <textarea id="wa-envio-mensaje" rows="3" class="w-full px-5 py-4 rounded-2xl bg-gray-50 border border-gray-100 focus:bg-white focus:ring-2 focus:ring-green-500 outline-none transition-all text-sm" placeholder="Hola, te adjunto la cotización..."></textarea>
            </div>

            <button onclick="confirmarEnvioWA()" id="btn-confirmar-wa" class="w-full py-4 bg-green-600 text-white rounded-2xl font-black shadow-xl shadow-green-200 hover:bg-green-700 transition-all flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.038 3.284l-.54 1.964 2.009-.527c.974.594 2.013.907 2.27.907 3.179.001 5.766-2.587 5.766-5.764 0-3.178-2.587-5.766-5.766-5.766zm3.326 8.239c-.114.32-.576.574-.917.614-.239.027-.549.034-1.12-.191-.73-.287-1.199-.757-1.638-1.192-.439-.434-.84-1.048-1.127-1.745-.089-.219-.115-.461-.09-.684.025-.224.22-.387.352-.519.133-.131.2-.206.273-.281.073-.075.097-.123.146-.221.048-.098.024-.184-.012-.258-.036-.074-.323-.778-.444-1.069-.117-.282-.236-.244-.323-.248-.083-.004-.178-.005-.275-.005-.096 0-.253.036-.385.18-.132.145-.505.493-.505 1.203s.517 1.396.589 1.492c.072.096 1.017 1.553 2.463 2.178.344.148.613.237.822.303.346.109.66.094.908.057.277-.041.853-.349.974-.686.12-.337.12-.626.084-.686-.036-.06-.132-.096-.277-.168z"/></svg>
                ENVIAR POR WHATSAPP
            </button>
        </div>
    </div>
</div>

<!-- Modal Chat WhatsApp -->
<div id="modalChatWA" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="cerrarChatWA()"></div>
    <div class="bg-white rounded-[2.5rem] w-full max-w-xl overflow-hidden shadow-2xl relative z-10 animate-fade-in-up flex flex-col h-[600px]">
        <div class="bg-gray-50/50 p-6 border-b border-gray-100 flex justify-between items-center bg-white/90 backdrop-blur-md">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center text-white font-black text-lg shadow-lg relative">
                    W
                    <div id="wa-chat-status" class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-400 border-2 border-white rounded-full"></div>
                </div>
                <div>
                    <h3 id="wa-chat-nombre" class="text-base font-black text-gray-900 leading-none">Cargando...</h3>
                    <p id="wa-chat-detail" class="text-[9px] font-black text-gray-400 uppercase tracking-widest mt-1">Chat en vivo</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="cargarHistorialWA(document.getElementById('wa-chat-id').value)" class="p-2 text-gray-400 hover:text-blue-600 transition-colors" title="Actualizar Chat">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </button>
                <button onclick="cerrarChatWA()" class="text-gray-400 hover:text-gray-600 p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>
        
        <div id="wa-chat-history" class="flex-1 overflow-y-auto p-6 space-y-4 bg-gray-100/30 custom-scroll">
            <div class="text-center py-10 text-gray-400 italic">Cargando conversación...</div>
        </div>

        <div class="p-4 bg-white border-t border-gray-100">
            <div class="flex items-center gap-2">
                <input type="hidden" id="wa-chat-id">
                <input type="hidden" id="wa-chat-celular">
                
                <!-- Botón Adjuntar -->
                <button onclick="document.getElementById('wa-chat-file').click()" class="p-3 bg-gray-50 text-gray-400 rounded-xl hover:bg-gray-100 transition-all flex items-center justify-center border border-gray-100" title="Adjuntar archivo">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                </button>
                <input type="file" id="wa-chat-file" class="hidden" onchange="enviarAdjuntoWA()">

                <input type="text" id="wa-chat-input" class="flex-1 px-5 py-3 rounded-xl bg-gray-50 border border-gray-100 focus:bg-white focus:ring-2 focus:ring-green-500 outline-none transition-all text-sm font-medium" placeholder="Escribe un mensaje..." onkeypress="if(event.key === 'Enter') enviarMensajeWA()">
                
                <button onclick="enviarMensajeWA()" class="p-3 bg-green-600 text-white rounded-xl shadow-lg hover:bg-green-700 transition-all flex items-center justify-center">
                    <svg class="w-5 h-5 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Estadísticas -->
<div id="modalEstadisticas" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="cerrarModalStats()"></div>
    <div class="bg-white rounded-[2.5rem] w-full max-w-lg overflow-hidden shadow-2xl relative z-10 animate-fade-in-up">
        <div class="bg-gray-50/50 p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-xl font-black text-gray-900 italic">Tracking de <span class="text-blue-600">Visualizaciones</span></h3>
            <button onclick="cerrarModalStats()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="p-8 max-h-[60vh] overflow-y-auto custom-scroll">
            <div id="stats-content" class="space-y-4">
                <div class="text-center py-10 text-gray-400 italic">Cargando datos...</div>
            </div>
        </div>
    </div>
</div>

<script>
let waChatInterval;
function abrirChatWhatsApp(cotId, nombre, celular) {
    if(!celular) return alert('El cliente no tiene un número de celular registrado.');
    
    document.getElementById('wa-chat-nombre').innerText = nombre;
    document.getElementById('wa-chat-celular').value = celular;
    document.getElementById('wa-chat-history').innerHTML = '<div class="text-center py-10 text-gray-400 italic">Cargando conversación...</div>';
    document.getElementById('modalChatWA').classList.remove('hidden');
    
    fetch('api_crm_whatsapp.php?action=get_chat_by_phone&phone=' + encodeURIComponent(celular))
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            document.getElementById('wa-chat-id').value = data.chat_id;
            cargarHistorialWA(data.chat_id);
            
            // Auto-refresh cada 10 segundos
            clearInterval(waChatInterval);
            waChatInterval = setInterval(() => {
                cargarHistorialWA(data.chat_id, true);
            }, 10000);
        } else {
            document.getElementById('wa-chat-history').innerHTML = '<div class="text-center py-10 text-red-400 italic">Error: ' + data.message + '</div>';
        }
    });
}

function cerrarChatWA() {
    document.getElementById('modalChatWA').classList.add('hidden');
    clearInterval(waChatInterval);
}

function cargarHistorialWA(chatId, silent = false) {
    if(!chatId) return;
    fetch('api_crm_whatsapp.php?action=get_messages&chat_id=' + chatId)
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            const container = document.getElementById('wa-chat-history');
            const currentScroll = container.scrollTop;
            const isAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 100;
            
            container.innerHTML = '';
            
            if(data.data.length === 0) {
                container.innerHTML = '<div class="text-center py-10 text-gray-400 italic text-xs uppercase tracking-widest font-black">Sin mensajes previos</div>';
            } else {
                data.data.forEach(m => {
                    const isOut = m.direccion === 'saliente' || m.from_me == 1;
                    const div = document.createElement('div');
                    div.className = `max-w-[80%] p-3 rounded-2xl shadow-sm relative ${isOut ? 'ml-auto bg-green-500 text-white rounded-tr-none' : 'mr-auto bg-white text-gray-900 rounded-tl-none border border-gray-100'}`;
                    
                    let content = m.contenido || m.body || '';
                    if(m.media_url) {
                        const isImg = ['jpg','jpeg','png','gif','webp'].some(ext => m.media_url.toLowerCase().endsWith(ext));
                        if(isImg) {
                            content = `<img src="${m.media_url}" class="rounded-lg mb-2 max-w-full cursor-pointer" onclick="window.open('${m.media_url}')">` + content;
                        } else {
                            content = `<div class="flex items-center gap-2 bg-black/10 p-2 rounded-lg mb-2 cursor-pointer" onclick="window.open('${m.media_url}')">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"></path></svg>
                                <span class="text-[10px] font-black truncate">${m.media_type || 'Archivo'}</span>
                            </div>` + content;
                        }
                    }

                    div.innerHTML = `
                        <p class="text-sm font-medium leading-relaxed">${content}</p>
                        <span class="text-[8px] opacity-60 block mt-1 text-right font-black uppercase tracking-widest">
                            ${new Date(m.fecha_envio || m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                        </span>
                    `;
                    container.appendChild(div);
                });
            }
            if(isAtBottom) {
                container.scrollTop = container.scrollHeight;
            } else {
                container.scrollTop = currentScroll;
            }
        }
    });
}

function enviarMensajeWA() {
    const chatId = document.getElementById('wa-chat-id').value;
    const texto = document.getElementById('wa-chat-input').value;
    if(!texto || !chatId) return;

    const input = document.getElementById('wa-chat-input');
    input.disabled = true;

    const fd = new FormData();
    fd.append('action', 'send_message');
    fd.append('chat_id', chatId);
    fd.append('text', texto);

    fetch('api_crm_whatsapp.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            input.value = '';
            cargarHistorialWA(chatId);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .finally(() => {
        input.disabled = false;
        input.focus();
    });
}

function enviarAdjuntoWA() {
    const fileInput = document.getElementById('wa-chat-file');
    const chatId = document.getElementById('wa-chat-id').value;
    const telefono = document.getElementById('wa-chat-celular').value;
    
    if(!fileInput.files[0] || !chatId) return;

    const file = fileInput.files[0];
    const fd = new FormData();
    fd.append('action', 'upload_and_send');
    fd.append('chat_id', chatId);
    fd.append('telefono', telefono);
    fd.append('file', file);

    const container = document.getElementById('wa-chat-history');
    const loader = document.createElement('div');
    loader.className = 'text-center py-2 text-[10px] font-black text-blue-500 animate-pulse';
    loader.id = 'wa-sending-loader';
    loader.innerText = 'Subiendo y Enviando...';
    container.appendChild(loader);
    container.scrollTop = container.scrollHeight;

    fetch('api_crm_whatsapp.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            fileInput.value = '';
            cargarHistorialWA(chatId);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => alert('Error al enviar archivo'))
    .finally(() => {
        const l = document.getElementById('wa-sending-loader');
        if(l) l.remove();
    });
}

// NUEVO: Funciones multi-recipiente WhatsApp
const p_codigos = [
    { name: 'Colombia (+57)', code: '57' },
    { name: 'México (+52)', code: '52' },
    { name: 'Perú (+51)', code: '51' },
    { name: 'España (+34)', code: '34' },
    { name: 'Chile (+56)', code: '56' },
    { name: 'Argentina (+54)', code: '54' },
    { name: 'USA (+1)', code: '1' }
];

function abrirModalEnvioWA(cotId, celular, telefono, hash) {
    document.getElementById('wa-envio-cot-id').value = cotId;
    document.getElementById('wa-envio-hash').value = hash;
    document.getElementById('wa-envio-mensaje').value = "Hola, te enviamos la propuesta comercial que solicitaste. Puedes verla aquí:";
    
    const container = document.getElementById('wa-recipients-container');
    container.innerHTML = '<label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1 ml-1">Seleccionar Destinatarios</label>';
    
    // Solo agregar si son diferentes y existen
    const numbers = [];
    if(celular) numbers.push({ label: 'Contacto: ' + celular, val: celular.replace(/\D/g, '') });
    if(telefono && telefono != celular) {
        numbers.push({ label: 'Empresa: ' + telefono, val: telefono.replace(/\D/g, '') });
    }

    numbers.forEach((n, idx) => {
        container.innerHTML += `
            <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-2xl border border-gray-100 group">
                <input type="checkbox" checked value="${n.val}" data-pre="1" class="w-5 h-5 rounded-lg border-gray-300 text-green-600 focus:ring-green-500 wa-recipient-check">
                <div class="flex-1">
                    <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest leading-none mb-1">Pre-registrado</div>
                    <div class="text-sm font-bold text-gray-900">${n.label}</div>
                </div>
            </div>
        `;
    });

    if(numbers.length == 0) agregarFilaWA();

    document.getElementById('modalEnvioWA').classList.remove('hidden');
}

function agregarFilaWA() {
    const container = document.getElementById('wa-recipients-container');
    const div = document.createElement('div');
    div.className = "flex items-center gap-2 p-3 bg-blue-50/50 rounded-2xl border border-blue-100 animate-fade-in";
    
    let options = '';
    p_codigos.forEach(p => options += `<option value="${p.code}">${p.name}</option>`);

    div.innerHTML = `
        <input type="checkbox" checked data-pre="0" class="w-5 h-5 rounded-lg border-gray-300 text-green-600 focus:ring-green-500 wa-recipient-check">
        <select class="px-3 py-2 rounded-xl text-xs font-bold border border-gray-200 outline-none wa-country-code">
            ${options}
        </select>
        <input type="text" placeholder="Celular" class="flex-1 px-4 py-2 rounded-xl text-xs font-bold border border-gray-200 outline-none wa-custom-number">
    `;
    container.appendChild(div);
}

function cerrarModalEnvioWA() {
    document.getElementById('modalEnvioWA').classList.add('hidden');
}

async function confirmarEnvioWA() {
    const btn = document.getElementById('btn-confirmar-wa');
    const original = btn.innerHTML;
    const cotId = document.getElementById('wa-envio-cot-id').value;
    const hash = document.getElementById('wa-envio-hash').value;
    const msgBase = document.getElementById('wa-envio-mensaje').value;
    const link = location.origin + '/propuesta.php?h=' + hash;
    
    // Recopilar números
    const destinatarios = [];
    const rows = document.querySelectorAll('.wa-recipient-check:checked');
    
    rows.forEach(chk => {
        const parent = chk.parentElement;
        if(chk.dataset.pre === "1") { // Pre-registrado
            destinatarios.push(chk.value);
        } else { // Custom (Manual)
            const selectEl = parent.querySelector('.wa-country-code');
            const inputEl = parent.querySelector('.wa-custom-number');
            if(selectEl && inputEl) {
                const code = selectEl.value;
                const num = inputEl.value.replace(/\D/g, '');
                if(num) destinatarios.push(code + num);
            }
        }
    });

    if(destinatarios.length == 0) return alert('Selecciona al menos un destinatario');

    btn.disabled = true;
    btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>';

    let count = 0;
    for(const tel of [...new Set(destinatarios)]) { // Set para evitar duplicados reales
        const fd = new FormData();
        fd.append('accion', 'enviar_wa');
        fd.append('cotizacion_id', cotId);
        fd.append('telefono_pro', tel);
        fd.append('mensaje_pro', msgBase + "\n" + link);
        fd.append('ajax', 1);

        try {
            const res = await fetch('api_whatsapp.php', { method: 'POST', body: fd }).then(r => r.json());
            if(res.status === 'success') count++;
        } catch(e) {}
    }

    alert(`✅ Se enviaron ${count} mensajes de WhatsApp`);
    cerrarModalEnvioWA();
    btn.disabled = false;
    btn.innerHTML = original;
}

function duplicarCotizacion(id) {
    if(!confirm('¿Deseas crear una copia de esta cotización?')) return;

    const fd = new FormData();
    fd.append('id', id);

    fetch('api_duplicar_cotizacion.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            // Redirigir al editor de la nueva cotización
            window.location.href = `nueva-cotizacion.php?id=${data.new_id}`;
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => alert('Error de conexión al duplicar'));
}

function eliminarCotizacion(id) {
    if (confirm('¿Estás seguro de que deseas eliminar esta cotización? Esta acción no se puede deshacer.')) {
        const formData = new FormData();
        formData.append('id', id);

        fetch('api_eliminar_cotizacion.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.text())
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("Error parseando respuesta:", text);
                throw new Error("Respuesta del servidor inválida");
            }
        })
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert('Error al eliminar: ' + (data.message || 'Desconocido'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error crítico de conexión o respuesta: ' + err.message);
        });
    }
}

function copiarLink(url) {
    navigator.clipboard.writeText(url).then(() => {
        alert('✅ Enlace copiado al portapapeles');
    });
}

function abrirModalEmail(id, email) {
    document.getElementById('email-cot-id').value = id;
    document.getElementById('email-recipients').value = email;
    document.getElementById('email-message').value = "Hola, te enviamos la propuesta comercial que solicitaste. Puedes ver todos los detalles en el enlace adjunto.";
    document.getElementById('modalEmail').classList.remove('hidden');
}

function cerrarModalEmail() {
    document.getElementById('modalEmail').classList.add('hidden');
}

function confirmarEnvioEmail() {
    const id = document.getElementById('email-cot-id').value;
    const recipients = document.getElementById('email-recipients').value;
    const message = document.getElementById('email-message').value;

    if(!recipients) return alert('Por favor, ingresa al menos un correo electrónico.');

    const btn = document.getElementById('btn-confirmar-email');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>';

    const fd = new FormData();
    fd.append('cotizacion_id', id);
    fd.append('destinatarios', recipients);
    fd.append('mensaje', message);

    fetch('api_email_quote.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            alert('✅ ' + data.message);
            cerrarModalEmail();
            location.reload();
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(err => alert('Error de conexión'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
}

function verEstadisticas(id, numero) {
    const modal = document.getElementById('modalEstadisticas');
    const content = document.getElementById('stats-content');
    modal.classList.remove('hidden');
    content.innerHTML = '<div class="text-center py-10 text-gray-400 italic">Cargando historial de la cotización #' + numero + '...</div>';

    fetch('api_cotizacion_estadisticas.php?id=' + id)
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            if(data.data.length === 0) {
                content.innerHTML = '<div class="text-center py-10 text-gray-400 italic">Aún no hay registros de visualización para esta cotización.</div>';
                return;
            }
            let html = '<div class="space-y-3">';
            data.data.forEach(v => {
                html += `
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100">
                    <div>
                        <div class="text-sm font-black text-gray-900">${new Date(v.fecha_vista).toLocaleString()}</div>
                        <div class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">${v.ip_address}</div>
                    </div>
                    <div class="text-blue-500">
                         <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    </div>
                </div>`;
            });
            html += '</div>';
            content.innerHTML = html;
        } else {
            content.innerHTML = '<div class="text-center py-10 text-red-400 italic">Error: ' + data.message + '</div>';
        }
    });
}

function cerrarModalStats() {
    document.getElementById('modalEstadisticas').classList.add('hidden');
}

function verHistorialEnvios(id, numero) {
    const modal = document.getElementById('modalHistorialEnvios');
    const content = document.getElementById('historial-envios-content');
    modal.classList.remove('hidden');
    content.innerHTML = '<div class="text-center py-10 text-gray-400 italic">Cargando historial de envíos...</div>';

    fetch('api_cotizacion_envios.php?id=' + id)
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            if(data.data.length === 0) {
                content.innerHTML = '<div class="text-center py-10 text-gray-400 italic">Esta cotización aún no ha sido enviada por Email o WhatsApp.</div>';
                return;
            }
            let html = '<div class="space-y-3">';
            data.data.forEach(e => {
                const icono = e.tipo === 'Email' 
                    ? '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>'
                    : '<svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.151-.172.199-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.199 0-.52.074-.792.372s-1.04 1.016-1.04 2.479c0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413z"/></svg>';
                
                const badgeColor = e.visto == 1 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500';
                const statusText = e.visto == 1 ? 'ABIERTO' : 'ENVIADO';

                html += `
                <div class="p-4 bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="p-2 ${e.tipo === 'Email' ? 'bg-blue-50 text-blue-500' : 'bg-green-50 text-green-500'} rounded-lg">
                                ${icono}
                            </span>
                            <div>
                                <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest leading-none mb-1">${e.tipo}</div>
                                <div class="text-sm font-bold text-gray-900">${e.destinatario}</div>
                            </div>
                        </div>
                        <div class="text-right">
                             <div class="px-2 py-1 rounded-lg text-[9px] font-black ${badgeColor}">${statusText}</div>
                             <div class="text-[9px] text-gray-400 font-bold mt-1">${new Date(e.fecha_envio).toLocaleString()}</div>
                        </div>
                    </div>
                    ${e.visto == 1 ? `
                        <div class="mt-2 pt-2 border-t border-gray-50 flex items-center gap-1">
                             <span class="text-[9px] font-bold text-green-600">Vizualizado el: ${new Date(e.fecha_visto).toLocaleString()}</span>
                        </div>
                    ` : ''}
                </div>`;
            });
            html += '</div>';
            content.innerHTML = html;
        } else {
            content.innerHTML = '<div class="text-center py-10 text-red-500">Error: ' + data.message + '</div>';
        }
    });
}

function cerrarModalHistorialEnvios() {
    document.getElementById('modalHistorialEnvios').classList.add('hidden');
}

function enviarWhatsApp(e, form) {
    e.preventDefault();
    const btn = form.querySelector('button');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>';

    const fd = new FormData(form);
    fd.append('ajax', 1);

    fetch('api_whatsapp.php', { method: 'POST', body: fd })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            alert('✅ WhatsApp enviado correctamente');
            location.reload();
        } else {
            alert('❌ Error: ' + data.message);
        }
    })
    .catch(err => alert('Error de conexión'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    });
}
</script>

<!-- Modal Adjuntos -->
<div id="modalAdjuntos" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
    <div class="bg-white w-full max-w-2xl rounded-[3rem] shadow-2xl overflow-hidden animate-slide-up">
        <div class="p-8 md:p-12">
            <div class="flex justify-between items-center mb-10">
                <div>
                    <h3 class="text-2xl font-black text-gray-900 tracking-tight">Adjuntar Documentos</h3>
                    <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">Selecciona los archivos de tu bóveda</p>
                </div>
                <button onclick="cerrarModalAdjuntos()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div id="adjuntos-container" class="max-h-96 overflow-y-auto space-y-3 p-2 bg-gray-50/50 rounded-3xl border border-gray-100">
                <!-- Se cargan vía AJAX -->
                <div class="p-10 text-center text-gray-400 italic">Cargando documentos de la bóveda...</div>
            </div>

            <div class="mt-10 flex gap-3">
                <button onclick="cerrarModalAdjuntos()" class="flex-1 py-4 px-6 rounded-2xl text-gray-600 font-bold hover:bg-gray-100 transition-all">Cancelar</button>
                <button onclick="guardarAdjuntos()" class="flex-1 py-4 px-6 rounded-2xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-xl active:scale-95 transition-all">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Historial de Envíos -->
<div id="modalHistorialEnvios" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
    <div class="bg-white w-full max-w-lg rounded-[3rem] shadow-2xl overflow-hidden animate-slide-up">
        <div class="p-8 md:p-12">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h3 class="text-2xl font-black text-gray-900 tracking-tight">Historial de Envíos</h3>
                    <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">Rastreo de Email y WhatsApp</p>
                </div>
                <button onclick="cerrarModalHistorialEnvios()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div id="historial-envios-content" class="max-h-96 overflow-y-auto space-y-4 p-2 custom-scrollbar">
                <!-- Se carga vía AJAX -->
            </div>

            <div class="mt-8">
                <button onclick="cerrarModalHistorialEnvios()" class="w-full py-4 rounded-2xl bg-gray-100 text-gray-600 font-bold hover:bg-gray-200 transition-all">Cerrar Historial</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentAdjCotId = null;

async function abrirModalAdjuntos(cotId) {
    currentAdjCotId = cotId;
    document.getElementById('modalAdjuntos').classList.remove('hidden');
    
    const fd = new FormData();
    fd.append('accion', 'listar_disponibles');
    fd.append('cotizacion_id', cotId);

    try {
        const res = await fetch('api_cotizacion_adjuntos.php', { method: 'POST', body: fd }).then(r => r.json());
        if(res.status === 'success') {
            renderAdjuntosDisponibles(res.data);
        }
    } catch(e) {
        console.error(e);
    }
}

function renderAdjuntosDisponibles(files) {
    const container = document.getElementById('adjuntos-container');
    if (files.length === 0) {
        container.innerHTML = '<div class="p-10 text-center text-gray-400">No hay documentos en la bóveda. <br><a href="documentos.php" class="text-indigo-600 font-bold underline">Subir ahora</a></div>';
        return;
    }

    container.innerHTML = files.map(f => `
        <label class="flex items-center gap-4 p-4 bg-white rounded-2xl border border-gray-100 hover:border-indigo-200 transition-all cursor-pointer group">
            <input type="checkbox" name="adjunto_check" value="${f.id}" ${f.is_attached > 0 ? 'checked' : ''} class="w-5 h-5 rounded-lg border-gray-300 text-indigo-600 focus:ring-indigo-500">
            <div class="w-10 h-10 bg-gray-50 rounded-xl flex items-center justify-center text-gray-400 group-hover:bg-indigo-50 group-hover:text-indigo-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
            </div>
            <div class="flex-1 overflow-hidden">
                <p class="text-sm font-bold text-gray-800 truncate">${f.nombre_original}</p>
                <div class="flex items-center gap-2 mt-1">
                    <span class="text-[9px] font-black uppercase text-gray-400 tracking-widest">${f.categoria || 'GENERAL'}</span>
                    ${f.fecha_vencimiento ? `<span class="text-[9px] font-black uppercase tracking-widest ${new Date(f.fecha_vencimiento) < new Date() ? 'text-red-500' : 'text-gray-400'}">Exp: ${new Date(f.fecha_vencimiento).toLocaleDateString()}</span>` : ''}
                </div>
            </div>
        </label>
    `).join('');
}

async function guardarAdjuntos() {
    const checks = document.querySelectorAll('input[name="adjunto_check"]:checked');
    const ids = Array.from(checks).map(c => c.value);

    const fd = new FormData();
    fd.append('accion', 'vincular');
    fd.append('cotizacion_id', currentAdjCotId);
    ids.forEach(id => fd.append('documento_ids[]', id));

    try {
        const res = await fetch('api_cotizacion_adjuntos.php', { method: 'POST', body: fd }).then(r => r.json());
        if(res.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Adjuntos actualizados',
                showConfirmButton: false,
                timer: 1500,
                customClass: { popup: 'rounded-[2rem]' }
            });
            cerrarModalAdjuntos();
        }
    } catch(e) {
        console.error(e);
    }
}

function cerrarModalAdjuntos() {
    document.getElementById('modalAdjuntos').classList.add('hidden');
}

async function eliminarCotizacion(id, confirmado = false) {
    if(!confirmado && !confirm("¿Seguro que deseas eliminar esta cotización?")) return;

    const fd = new FormData();
    fd.append('id', id);
    if(confirmado) fd.append('confirmar_aprobada', 1);

    try {
        const res = await fetch('api_eliminar_cotizacion.php', { method: 'POST', body: fd }).then(r => r.json());
        if(res.status === 'success') {
            location.reload();
        } else if(res.status === 'warning') {
            if(confirm(res.message)) {
                eliminarCotizacion(id, true);
            }
        } else {
            alert(res.message);
        }
    } catch(e) {
        console.error(e);
    }
}
</script>

<!-- Modal Recordatorios -->
<div id="modal-recordatorios" class="fixed inset-0 z-[60] hidden">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="cerrarModalRecordatorios()"></div>
    <div class="absolute right-0 top-0 h-full w-full md:w-[500px] bg-white shadow-2xl transform transition-transform translate-x-full duration-300 flex flex-col" id="modal-panel-rec">
        
        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-white/80 backdrop-blur-md z-10">
            <h3 class="text-xl font-black text-slate-900 flex items-center gap-2">
                <span class="p-2 bg-amber-50 text-amber-500 rounded-xl">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                </span>
                Recordatorios
            </h3>
            <button onclick="cerrarModalRecordatorios()" class="text-slate-400 hover:text-rose-500 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-8 bg-slate-50 relative">
            <input type="hidden" id="rec-cot-id">
            
            <!-- Crear Nuevo -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4">
                <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2">Programar Nuevo</h4>
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Asunto (Negrita en Notificación)</label>
                    <input type="text" id="rec-asunto" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-amber-400" placeholder="Ej: Pago Pendiente">
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Mensaje Adicional</label>
                    <textarea id="rec-mensaje" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-amber-400 resize-none h-20" placeholder="Hola, recordamos que..."></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Fecha y Hora</label>
                        <input type="datetime-local" id="rec-fecha" class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 outline-none focus:ring-2 focus:ring-amber-400">
                    </div>
                </div>

                <!-- Pre-Recordatorio -->
                <div class="p-4 bg-amber-50 rounded-xl border border-amber-100">
                    <label class="flex items-center gap-2 mb-3 cursor-pointer">
                        <input type="checkbox" id="rec-pre-enable" onchange="togglePreRec()" class="w-4 h-4 text-amber-600 rounded border-gray-300 focus:ring-amber-500">
                        <span class="text-xs font-bold text-amber-800 uppercase tracking-widest">Enviar Pre-Aviso</span>
                    </label>
                    <div id="rec-pre-fields" class="hidden space-y-3 pl-6 border-l-2 border-amber-200">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-600">Enviar</span>
                            <select id="rec-pre-dias" class="p-1 bg-white border border-gray-200 rounded text-xs font-bold">
                                <option value="1">1 día antes</option>
                                <option value="2">2 días antes</option>
                                <option value="3">3 días antes</option>
                            </select>
                            <span class="text-xs text-gray-600">de la fecha programada.</span>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Mensaje del Pre-Aviso</label>
                            <textarea id="rec-pre-mensaje" class="w-full p-2 bg-white border border-gray-200 rounded-lg text-xs h-16 resize-none" placeholder="Hola, recuerda que mañana tienes programada..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <label class="flex items-center gap-3 p-3 border border-slate-100 rounded-xl cursor-pointer hover:bg-slate-50">
                        <input type="checkbox" id="rec-notificar-cliente" checked class="w-4 h-4 text-amber-500 rounded border-slate-300 focus:ring-amber-400">
                        <div>
                            <span class="block text-xs font-bold text-slate-700">Notificar al Cliente</span>
                            <span class="block text-[10px] text-slate-400" id="rec-cliente-info">Email & WA</span>
                        </div>
                    </label>
                </div>

                <!-- Extra WhatsApps -->
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">WhatsApp Adicionales</label>
                    <div id="rec-extra-wa-list" class="space-y-2 mb-2"></div>
                    <div class="flex gap-2">
                        <div id="rec-country-wrapper"></div>
                        <input type="text" id="rec-new-wa" placeholder="300 123 4567" class="flex-1 p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                        <button onclick="addExtraWa()" class="px-3 py-2 bg-slate-200 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-300">+</button>
                    </div>
                </div>

                <button onclick="guardarRecordatorio()" class="w-full py-3 bg-amber-500 text-white rounded-xl font-black text-xs uppercase tracking-widest hover:bg-amber-600 shadow-lg shadow-amber-200 transition-all">
                    Programar Recordatorio
                </button>
            </div>

            <!-- Listado -->
            <div>
                <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center justify-between">
                    <span>Programados</span>
                    <button onclick="cargarRecordatorios()" class="text-blue-500 hover:text-blue-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    </button>
                </h4>
                <div id="lista-recordatorios" class="space-y-3"></div>
            </div>

        </div>
    </div>
</div>

<script>

    let extraWas = [];
    let currentRecCotId = 0;
    
    // Lista de códigos de país comunes en LatAm / ES
    const countryCodes = [
        { c: '57', n: 'Colombia (+57)' },
        { c: '52', n: 'México (+52)' },
        { c: '51', n: 'Perú (+51)' },
        { c: '54', n: 'Argentina (+54)' },
        { c: '56', n: 'Chile (+56)' },
        { c: '34', n: 'España (+34)' },
        { c: '1', n: 'USA (+1)' },
        { c: '593', n: 'Ecuador (+593)' },
        { c: '58', n: 'Venezuela (+58)' },
        { c: '507', n: 'Panamá (+507)' }
    ];

    function abrirModalRecordatorios(cotId, email, tel) {
        currentRecCotId = cotId;
        document.getElementById('rec-cot-id').value = cotId;
        document.getElementById('rec-cliente-info').innerText = `Email: ${email} | WA: ${tel}`;
        
        // Renderizar select de países si no existe
        const container = document.getElementById('rec-country-wrapper');
        if(container.innerHTML === '') {
            container.innerHTML = `
                <select id="rec-country-select" class="p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-bold outline-none">
                    ${countryCodes.map(c => `<option value="${c.c}">${c.n}</option>`).join('')}
                </select>
            `;
        }

        document.getElementById('modal-recordatorios').classList.remove('hidden');
        setTimeout(() => {
            document.getElementById('modal-panel-rec').classList.remove('translate-x-full');
        }, 10);
        cargarRecordatorios();
    }

    function cerrarModalRecordatorios() {
        document.getElementById('modal-panel-rec').classList.add('translate-x-full');
        setTimeout(() => {
            document.getElementById('modal-recordatorios').classList.add('hidden');
        }, 300);
    }

    function addExtraWa() {
        const input = document.getElementById('rec-new-wa');
        const select = document.getElementById('rec-country-select');
        const val = input.value.trim();
        
        if(!val) return;
        
        // Limpiar número y agregar código
        const cleanNum = val.replace(/\D/g, '');
        const fullNum = '+' + select.value + cleanNum;
        
        extraWas.push(fullNum);
        input.value = '';
        renderExtraWa();
    }

    function removeExtraWa(idx) {
        extraWas.splice(idx, 1);
        renderExtraWa();
    }

    function renderExtraWa() {
        const container = document.getElementById('rec-extra-wa-list');
        container.innerHTML = extraWas.map((w, i) => `
            <div class="flex justify-between items-center bg-white p-2 border border-slate-100 rounded-lg">
                <span class="text-xs font-mono text-slate-600 font-bold">${w}</span>
                <button onclick="removeExtraWa(${i})" class="text-rose-400 hover:text-rose-600 font-bold">×</button>
            </div>
        `).join('');
    }

    function cargarRecordatorios() {
        const container = document.getElementById('lista-recordatorios');
        container.innerHTML = '<div class="text-center py-4"><svg class="w-6 h-6 animate-spin mx-auto text-slate-300" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg></div>';
        
        fetch(`api_recordatorios.php?action=list&cotizacion_id=${currentRecCotId}`)
            .then(r => r.json())
            .then(d => {
                if(d.status === 'success') {
                    if(d.data.length === 0) {
                        container.innerHTML = '<p class="text-xs text-slate-400 text-center italic">No hay recordatorios pendientes.</p>';
                        return;
                    }
                    container.innerHTML = d.data.map(r => renderRecordatorioItem(r)).join('');
                }
            });
    }

    function renderRecordatorioItem(r) {
        const date = new Date(r.fecha_programada);
        const fmt = date.toLocaleString();
        return `
            <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm relative group">
                <div class="flex justify-between items-start mb-2">
                    <h5 class="font-bold text-slate-800 text-sm">${r.asunto}</h5>
                    <span class="text-[9px] font-black uppercase tracking-widest ${r.estado === 'Enviado' ? 'text-green-500' : 'text-amber-500'}">${r.estado}</span>
                </div>
                <p class="text-xs text-slate-500 mb-3">${r.mensaje || ''}</p>
                <div class="flex justify-between items-end">
                    <span class="text-[10px] text-slate-400 font-mono bg-slate-50 px-2 py-1 rounded">${fmt}</span>
                    <div class="flex gap-2">
                        ${r.estado === 'Pendiente' ? `
                        <button onclick="enviarAhora(${r.id})" class="p-1.5 text-blue-500 hover:bg-blue-50 rounded-lg" title="Enviar Ahora">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        </button>` : ''}
                        <button onclick="eliminarRecordatorio(${r.id})" class="p-1.5 text-rose-400 hover:bg-rose-50 rounded-lg" title="Eliminar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    function togglePreRec() {
        const check = document.getElementById('rec-pre-enable').checked;
        document.getElementById('rec-pre-fields').classList.toggle('hidden', !check);
        if(check && !document.getElementById('rec-pre-mensaje').value) {
             document.getElementById('rec-pre-mensaje').value = "Hola, te recordamos que pronto tienes una actividad programada con nosotros.";
        }
    }

    function guardarRecordatorio() {
        const fd = new FormData();
        fd.append('action', 'create');
        fd.append('cotizacion_id', currentRecCotId);
        fd.append('asunto', document.getElementById('rec-asunto').value);
        fd.append('mensaje', document.getElementById('rec-mensaje').value);
        fd.append('fecha_programada', document.getElementById('rec-fecha').value);
        if(document.getElementById('rec-notificar-cliente').checked) {
            fd.append('notificar_cliente', '1');
        }
        
        // Pre-Reminder Data
        if(document.getElementById('rec-pre-enable').checked) {
            fd.append('tiene_prerecordatorio', '1');
            fd.append('dias_antes', document.getElementById('rec-pre-dias').value);
            fd.append('mensaje_prerecordatorio', document.getElementById('rec-pre-mensaje').value);
        }
        
        extraWas.forEach(w => fd.append('telefonos_adicionales[]', w));

        fetch('api_recordatorios.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if(d.status === 'success') {
                    alert('Recordatorio programado');
                    document.getElementById('rec-asunto').value = '';
                    document.getElementById('rec-mensaje').value = '';
                    
                    // Reset Pre-Reminders
                    document.getElementById('rec-pre-enable').checked = false;
                    document.getElementById('rec-pre-fields').classList.add('hidden');
                    document.getElementById('rec-pre-mensaje').value = '';
                    
                    extraWas = [];
                    renderExtraWa();
                    cargarRecordatorios();
                } else {
                    alert(d.message);
                }
            });
    }

    function eliminarRecordatorio(id) {
        if(!confirm('¿Eliminar este recordatorio?')) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        fetch('api_recordatorios.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(() => cargarRecordatorios());
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
                cargarRecordatorios();
            });
    }

    // Global Action Menu Logic
    let currentActionData = null;

    function toggleActionMenu(event, data) {
        event.stopPropagation();
        currentActionData = data;
        
        const menu = document.getElementById('globalActionDropdown');
        const isMobile = window.innerWidth < 768; // Tailwind md breakpoint

        // Reset classes
        menu.className = 'fixed hidden bg-white shadow-2xl border border-gray-100 z-[100] overflow-hidden text-sm flex flex-col transition-all duration-300';

        if (isMobile) {
            // Mobile: Bottom Sheet Style
            menu.style.top = 'auto';
            menu.style.left = '0';
            menu.style.right = '0';
            menu.style.bottom = '0';
            menu.style.width = '100%';
            menu.style.transform = 'translateY(100%)'; // Start off-screen
            
            // Add mobile specific classes
            menu.classList.add('rounded-t-[2rem]', 'rounded-b-none', 'pb-8', 'border-t', 'border-gray-200');
            menu.classList.remove('hidden');
            
            // Animate slide up
            requestAnimationFrame(() => {
                menu.style.transform = 'translateY(0)';
            });

        } else {
            // Desktop: Popover Style
            menu.style.transform = ''; // Reset transform
            menu.classList.add('rounded-xl', 'w-64', 'animate-fade-in-up');
            
            // Position Logic
            const rect = event.currentTarget.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            const menuHeight = 380; // Estimated height
            
            if(spaceBelow < menuHeight) {
                menu.style.top = (rect.top - menuHeight) + 'px';
                menu.style.transformOrigin = 'bottom right';
            } else {
                menu.style.top = (rect.bottom + 5) + 'px';
                menu.style.transformOrigin = 'top right';
            }
            menu.style.left = (rect.right - 240) + 'px';
            menu.classList.remove('hidden');
        }
        
        // Populate Menu
        let mobileHandle = isMobile ? `<div class="w-12 h-1.5 bg-gray-200 rounded-full mx-auto mb-4 mt-2"></div>` : '';
        
        menu.innerHTML = `
            ${mobileHandle}
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between ${isMobile ? 'text-lg' : ''}">
                <span class="font-black text-xs text-gray-500 uppercase tracking-widest">Acciones</span>
                <span class="text-[10px] font-bold text-gray-400">#${data.numero}</span>
            </div>
            
            <div class="py-2 ${isMobile ? 'space-y-1' : ''}">
                 <button onclick="cerrarActionMenu(); abrirModalEmail(${data.id}, '${data.email || ''}')" class="w-full text-left px-5 py-3 text-gray-600 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-3 transition-colors nav-item">
                    <div class="p-2 bg-blue-50 text-blue-500 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg></div>
                    <span class="font-bold">Enviar por Email</span>
                </button>
                <button onclick="cerrarActionMenu(); abrirModalEnvioWA(${data.id}, '${data.celular || ''}', '${data.telefono || ''}', '${data.hash}')" class="w-full text-left px-5 py-3 text-gray-600 hover:bg-green-50 hover:text-green-600 flex items-center gap-3 transition-colors nav-item">
                     <div class="p-2 bg-green-50 text-green-500 rounded-lg"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.438 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg></div>
                    <span class="font-bold">Enviar por WhatsApp</span>
                </button>
                <button onclick="cerrarActionMenu(); abrirChatWhatsApp(${data.id}, '${data.nombre || ''}', '${data.celular || ''}')" class="w-full text-left px-5 py-3 text-gray-600 hover:bg-indigo-50 hover:text-indigo-600 flex items-center gap-3 transition-colors nav-item">
                     <div class="p-2 bg-indigo-50 text-indigo-500 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg></div>
                    <span class="font-bold">Chat en Vivo</span>
                </button>
                
                <div class="h-px bg-gray-100 my-1 mx-4"></div>

                <div class="grid grid-cols-2 gap-2 px-4">
                    <button onclick="cerrarActionMenu(); duplicarCotizacion(${data.id})" class="text-left px-3 py-2 text-gray-600 hover:bg-purple-50 hover:text-purple-600 flex items-center gap-2 transition-colors nav-item rounded-xl border border-transparent hover:border-purple-100">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg>
                        <span class="text-xs font-bold">Duplicar</span>
                    </button>
                    <button onclick="cerrarActionMenu(); verHistorialEnvios(${data.id}, '${data.numero}')" class="text-left px-3 py-2 text-gray-600 hover:bg-gray-50 flex items-center gap-2 transition-colors nav-item rounded-xl border border-transparent hover:border-gray-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="text-xs font-bold">Historial</span>
                    </button>
                    <button onclick="cerrarActionMenu(); abrirModalAdjuntos(${data.id})" class="text-left px-3 py-2 text-gray-600 hover:bg-gray-50 flex items-center gap-2 transition-colors nav-item rounded-xl border border-transparent hover:border-gray-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.414a4 4 0 00-5.656-5.656l-6.415 6.414a6 6 0 108.486 8.486L20.5 13"></path></svg>
                        <span class="text-xs font-bold">Adjuntos</span>
                    </button>
                    <button onclick="cerrarActionMenu(); abrirModalRecordatorios(${data.id}, '${data.email || ''}', '${data.telefono || ''}')" class="text-left px-3 py-2 text-gray-600 hover:bg-amber-50 hover:text-amber-600 flex items-center gap-2 transition-colors nav-item rounded-xl border border-transparent hover:border-amber-100">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <span class="text-xs font-bold">Recordatorios</span>
                    </button>
                </div>
                
                <button onclick="cerrarActionMenu(); copingLink('${data.link}')" class="w-full text-left px-5 py-3 text-gray-600 hover:bg-gray-50 flex items-center gap-3 transition-colors nav-item">
                    <div class="p-1.5 bg-gray-100 rounded-md"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.826a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg></div>
                    <span class="font-bold text-sm">Copiar Enlace Público</span>
                </button>

                ${data.estado !== 'Aprobada' || data.estado !== 'Rechazada' ? '<div class="h-px bg-gray-100 my-1 mx-4"></div>' : ''}
                
                <div class="p-4 grid grid-cols-2 gap-3">
                    ${data.estado !== 'Aprobada' ? `
                    <button onclick="cerrarActionMenu(); actualizarEstadoCotizacion(${data.id}, 'Aprobada')" class="text-center px-4 py-3 bg-green-50 text-green-700 rounded-xl font-bold border border-green-100 shadow-sm col-span-1">
                        Aprobar
                    </button>` : ''}
                    
                    ${data.estado !== 'Rechazada' ? `
                    <button onclick="cerrarActionMenu(); actualizarEstadoCotizacion(${data.id}, 'Rechazada')" class="text-center px-4 py-3 bg-rose-50 text-rose-700 rounded-xl font-bold border border-rose-100 shadow-sm col-span-1">
                        Rechazar
                    </button>` : ''}
                </div>
            </div>
        `;
    }

    function cerrarActionMenu() {
        const menu = document.getElementById('globalActionDropdown');
        if(window.innerWidth < 768) {
            menu.style.transform = 'translateY(100%)';
            setTimeout(() => menu.classList.add('hidden'), 300);
        } else {
            menu.classList.add('hidden');
        }
    }
    
    // Alias for coping link typo fix if needed, assuming copiarLink exists
    function copingLink(url) {
        if(typeof copiarLink === 'function') copiarLink(url);
        else navigator.clipboard.writeText(url).then(() => alert('Link copiado'));
    }

    // Close Dropdown on Click Outside
    document.addEventListener('click', function(event) {
        const menu = document.getElementById('globalActionDropdown');
        if (!menu.classList.contains('hidden') && !event.target.closest('#globalActionDropdown')) {
            cerrarActionMenu();
        }
    });

    function actualizarEstadoCotizacion(id, nuevoEstado) {
        const fd = new FormData();
        fd.append('accion', 'cambiar_estado');
        fd.append('cotizacion_id', id);
        fd.append('nuevo_estado', nuevoEstado);
        fd.append('notificar', 1);

        fetch('api_whatsapp.php', { method: 'POST', body: fd })
        .then(res => {
             location.reload(); 
        }).catch(e => location.reload());
    }
</script>

<!-- Global Action Dropdown Container -->
<div id="globalActionDropdown" class="fixed hidden bg-white rounded-xl shadow-2xl border border-gray-100 z-[100] overflow-hidden text-sm flex flex-col transition-all duration-300">
    <!-- Populated by JS -->
</div>
