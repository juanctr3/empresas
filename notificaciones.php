<?php
require_once 'includes/header.php';
require_once 'includes/notificaciones_helper.php';

$usuario_id = $_SESSION['user_id'];
$limite = 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// Obtener notificaciones
$stmt = $pdo->prepare("SELECT * FROM notificaciones 
                      WHERE usuario_id = ? 
                      ORDER BY fecha_creacion DESC 
                      LIMIT ? OFFSET ?");
$stmt->execute([$usuario_id, $limite, $offset]);
$notificaciones = $stmt->fetchAll();

// Contar total
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$total = $stmt->fetchColumn();

// Contar no leídas
$no_leidas = contarNotificacionesNoLeidas($pdo, $usuario_id);
?>

<div class="space-y-8 animate-fade-in">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Centro de Notificaciones</h1>
            <p class="text-gray-500 mt-1">Maneja todas tus notificaciones y alertas del sistema.</p>
        </div>
        
        <div class="flex gap-2">
            <?php if ($no_leidas > 0): ?>
            <button onclick="marcarTodasLeidasPagina()" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-2xl shadow-lg transition-all active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Marcar Todas Leídas
            </button>
            <?php endif; ?>
            
            <button onclick="eliminarLeidasPagina()" class="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 px-6 rounded-2xl transition-all active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Limpiar Leídas
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-black text-gray-900"><?php echo $total; ?></p>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Total</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-2xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-black text-gray-900"><?php echo $no_leidas; ?></p>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Sin Leer</p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-green-100 text-green-600 rounded-2xl flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-3xl font-black text-gray-900"><?php echo $total - $no_leidas; ?></p>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Leídas</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Notificaciones -->
    <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
        <?php if (count($notificaciones) === 0): ?>
            <div class="p-20 text-center">
                <svg class="w-20 h-20 mx-auto text-gray-200 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <p class="text-gray-400 font-bold text-lg">No tienes notificaciones</p>
            </div>
        <?php else: ?>
         <?php foreach ($notificaciones as $n): 
            $icon_map = [
                'cotizacion_aceptada' => ['bg-green-100', 'text-green-600', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                'cotizacion_rechazada' => ['bg-red-100', 'text-red-600', 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
                'cotizacion_vencimiento' => ['bg-orange-100', 'text-orange-600', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                'documento_compartido' => ['bg-blue-100', 'text-blue-600', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                'default' => ['bg-gray-100', 'text-gray-600', 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9']
            ];
            $icon_data = $icon_map[$n['tipo']] ?? $icon_map['default'];
            $unread_class = $n['leida'] == 0 ? 'bg-blue-50 border-l-4 border-l-blue-500' : 'bg-white';
        ?>
            <div class="<?php echo $unread_class; ?> hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0">
                <div class="p-6 flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 <?php echo $icon_data[0]; ?> <?php echo $icon_data[1]; ?> rounded-2xl flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $icon_data[2]; ?>"></path>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-lg font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($n['titulo']); ?></p>
                        <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($n['mensaje']); ?></p>
                        <p class="text-xs text-gray-400 font-bold uppercase tracking-widest">
                            <?php echo date('d/m/Y H:i', strtotime($n['fecha_creacion'])); ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($n['leida'] == 0): ?>
                            <span class="w-3 h-3 bg-blue-600 rounded-full"></span>
                        <?php endif; ?>
                        
                        <?php if ($n['url']): ?>
                            <a href="<?php echo htmlspecialchars($n['url']); ?>" onclick="marcarLeida(<?php echo $n['id']; ?>)" class="p-2 hover:bg-gray-200 rounded-xl transition-colors">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                </svg>
                            </a>
                        <?php endif; ?>
                        
                        <button onclick="eliminarNotificacion(<?php echo $n['id']; ?>)" class="p-2 hover:bg-red-100 rounded-xl transition-colors">
                            <svg class="w-5 h-5 text-gray-400 hover:text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Paginación -->
    <?php if ($total > $limite): ?>
        <div class="flex justify-center gap-2">
            <?php if ($offset > 0): ?>
                <a href="?offset=<?php echo max(0, $offset - $limite); ?>" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition-colors">
                    ← Anterior
                </a>
            <?php endif; ?>
            
            <?php if ($offset + $limite < $total): ?>
                <a href="?offset=<?php echo $offset + $limite; ?>" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold rounded-xl transition-colors">
                    Siguiente →
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
async function marcarLeida(id) {
    const formData = new FormData();
    formData.append('accion', 'marcar_leida');
    formData.append('id', id);
    
    try {
        await fetch('api_notificaciones.php', { method: 'POST', body: formData });
    } catch (e) {
        console.error(e);
    }
}

async function eliminarNotificacion(id) {
    if (!confirm('¿Eliminar esta notificación?')) return;
    
    const formData = new FormData();
    formData.append('accion', 'eliminar');
    formData.append('id', id);
    
    try {
        const res = await fetch('api_notificaciones.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.status === 'success') {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (e) {
        alert('Error de conexión');
    }
}

async function marcarTodasLeidasPagina() {
    const formData = new FormData();
    formData.append('accion', 'marcar_todas_leidas');
    
    try {
        const res = await fetch('api_notificaciones.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.status === 'success') {
            location.reload();
        }
    } catch (e) {
        alert('Error');
    }
}

async function eliminarLeidasPagina() {
    if (!confirm('¿Eliminar todas las notificaciones leídas?')) return;
    
    const formData = new FormData();
    formData.append('accion', 'eliminar_leidas');
    
    try {
        const res = await fetch('api_notificaciones.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.status === 'success') {
            alert(data.message);
            location.reload();
        }
    } catch (e) {
        alert('Error');
    }
}
</script>

<?php include 'includes/footer.php'; ?>
