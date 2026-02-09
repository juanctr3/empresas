<?php
require_once 'db.php';

if (!isset($_GET['id'])) {
    header("Location: cotizaciones.php");
    exit;
}

// Obtener Cotización (Datos Completos)
$stmt = $pdo->prepare("
    SELECT c.*, cl.nombre as cliente_nombre, cl.email as cliente_mail, cl.telefono as cliente_tel,
           e.nombre as empresa_nombre, e.logo as empresa_logo, e.moneda as empresa_moneda
    FROM cotizaciones c 
    JOIN clientes cl ON c.cliente_id = cl.id 
    JOIN empresas e ON c.empresa_id = e.id
    WHERE c.id = ? AND c.empresa_id = ?
");
$stmt->execute([$_GET['id'], getEmpresaId()]);
$cot = $stmt->fetch();

if (!$cot) die("Cotización no encontrada o acceso denegado.");

// Detalles
$stmtD = $pdo->prepare("SELECT * FROM cotizacion_detalles WHERE cotizacion_id = ?");
$stmtD->execute([$cot['id']]);
$detalles = $stmtD->fetchAll();

// Entradas de formulario vinculadas
$stmtForms = $pdo->prepare("
    SELECT fe.*, f.nombre as formulario_nombre 
    FROM formulario_entradas fe
    JOIN formularios f ON fe.formulario_id = f.id
    WHERE fe.cotizacion_id = ?
    ORDER BY fe.created_at DESC
");
$stmtForms->execute([$cot['id']]);
$entradas_formulario = $stmtForms->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualización Propuesta #<?php echo $cot['numero_cotizacion']; ?> | <?php echo htmlspecialchars($cot['empresa_nombre']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@200;400;600;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; color: #0f172a; overflow-x: hidden; }
        .glass-header { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(25px); border-bottom: 1px solid rgba(255, 255, 255, 0.5); }
        .glass-card { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.5); }
        .animate-up { animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        
        .floating-action { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .floating-action:hover { transform: translateY(-3px); filter: brightness(1.1); }
        
        @media print { 
            .no-print { display: none !important; }
            body { background: white !important; }
            .glass-card { background: white !important; border: 1px solid #f1f5f9 !important; backdrop-filter: none !important; }
        }
    </style>
</head>
<body class="pb-20">

    <!-- Navbar -->
    <nav class="fixed top-0 w-full z-50 glass-header no-print">
        <div class="max-w-6xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="cotizaciones.php" class="p-2 text-slate-400 hover:text-slate-900 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div class="h-6 w-px bg-slate-200"></div>
                <span class="font-black text-slate-900 tracking-tightest uppercase text-xs italic">Panel de <span class="text-blue-600">Visualización</span></span>
            </div>
            
            <div class="flex items-center gap-4">
                <?php 
                    $statusColors = [
                        'Borrador' => 'bg-slate-100 text-slate-500',
                        'Enviada' => 'bg-blue-50 text-blue-600',
                        'Aprobada' => 'bg-emerald-50 text-emerald-600',
                        'Rechazada' => 'bg-rose-50 text-rose-600'
                    ];
                    $stColor = $statusColors[$cot['estado']] ?? 'bg-slate-100 text-slate-500';
                ?>
                <span class="px-4 py-1.5 rounded-full <?php echo $stColor; ?> text-[10px] font-black uppercase tracking-widest shadow-sm">
                    <?php echo $cot['estado']; ?>
                </span>
                <button onclick="window.print()" class="p-2 text-slate-400 hover:text-blue-600 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-5xl mx-auto px-6 pt-32 space-y-12 animate-up">
        
        <!-- Hero Header -->
        <div class="glass-card p-10 md:p-16 rounded-[3rem] shadow-2xl shadow-slate-200/50 relative overflow-hidden">
             <div class="absolute top-0 right-0 w-96 h-96 bg-blue-500/5 rounded-full blur-[100px] -mr-48 -mt-48"></div>
             <div class="relative z-10 flex flex-col md:flex-row justify-between items-start gap-12">
                 <div class="max-w-xl">
                    <span class="inline-block px-4 py-1.5 rounded-full bg-slate-900 text-white text-[10px] font-black uppercase tracking-[0.2em] mb-8">Administración</span>
                    <h1 class="text-5xl md:text-7xl font-black text-slate-900 tracking-tightest leading-[0.9] mb-4">
                        Propuesta <br>
                        <span class="text-blue-600 italic">#<?php echo $cot['numero_cotizacion']; ?></span>
                    </h1>
                 </div>
                 <div class="space-y-6 text-left md:text-right">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Cliente</p>
                        <p class="text-2xl font-black text-slate-900 tracking-tight"><?php echo htmlspecialchars($cot['cliente_nombre']); ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Inversión Final</p>
                        <p class="text-4xl font-black text-blue-600 tracking-tightest leading-none">$<?php echo number_format($cot['total'], 2); ?></p>
                    </div>
                 </div>
             </div>
        </div>

        <!-- Details -->
        <div class="glass-card p-8 md:p-12 rounded-[3.5rem] shadow-xl shadow-slate-100">
            <h2 class="text-2xl font-black text-slate-900 mb-10 flex items-center gap-4">
                <span class="w-1.5 h-8 bg-blue-600 rounded-full"></span>
                Detalles del Proyecto
            </h2>
            
            <div class="space-y-8">
                <?php foreach($detalles as $d): ?>
                <div class="flex flex-col md:flex-row gap-8 items-start md:items-center group border-b border-slate-50 last:border-0 pb-8 last:pb-0">
                    <div class="w-24 h-24 rounded-[2rem] overflow-hidden shadow-2xl shadow-slate-200 border-4 border-white flex-shrink-0 group-hover:scale-105 transition-transform duration-500">
                         <?php if($d['imagen']): ?>
                            <img src="<?php echo $d['imagen']; ?>" class="w-full h-full object-cover">
                         <?php else: ?>
                            <div class="w-full h-full bg-slate-50 flex items-center justify-center text-slate-300">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                         <?php endif; ?>
                    </div>
                    
                    <div class="flex-1 space-y-1">
                        <h3 class="text-xl font-black text-slate-900 tracking-tight"><?php echo htmlspecialchars($d['nombre_producto']); ?></h3>
                        <p class="text-slate-500 text-sm leading-relaxed max-w-2xl font-medium"><?php echo nl2br(htmlspecialchars($d['descripcion'])); ?></p>
                        <div class="pt-2 flex flex-wrap gap-2">
                            <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-lg text-[9px] font-black uppercase tracking-widest border border-slate-200/50">
                                <?php echo (float)$d['cantidad']; ?> <?php echo htmlspecialchars($d['unidad_nombre'] ?: 'Und'); ?>
                            </span>
                            <?php if($d['impuesto_porcentaje'] > 0): ?>
                                <span class="px-3 py-1 bg-blue-50 text-blue-600 rounded-lg text-[9px] font-black uppercase tracking-widest border border-blue-100/50">
                                    + IVA <?php echo (float)$d['impuesto_porcentaje']; ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="text-right">
                         <span class="block text-2xl font-black text-slate-900 tracking-tight">$<?php echo number_format($d['subtotal'], 2); ?></span>
                         <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1 block">Precio Unit: $<?php echo number_format($d['precio_unitario'], 2); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Totals -->
            <?php 
                $uniqueSections = array_unique(array_map(function($d) { return $d['seccion'] ?? ''; }, $detalles));
                $showTotals = count($uniqueSections) <= 1;
                
                if ($showTotals): 
            ?>
            <div class="mt-16 pt-12 border-t border-slate-100 flex flex-col items-end gap-4">
                <div class="flex justify-between w-full md:w-80 text-sm font-bold text-slate-400 uppercase tracking-widest">
                    <span>Subtotal</span>
                    <span class="text-slate-900">$<?php echo number_format($cot['subtotal'], 2); ?></span>
                </div>
                <div class="flex justify-between w-full md:w-80 text-sm font-bold text-slate-400 uppercase tracking-widest">
                    <span>Impuestos</span>
                    <span class="text-slate-900">$<?php echo number_format($cot['impuesto_total'] ?? ($cot['impuestos'] ?? 0), 2); ?></span>
                </div>
                <div class="flex justify-between w-full md:w-80 text-4xl font-black text-blue-600 pt-6 border-t border-slate-100 mt-2 tracking-tightest">
                    <span>Total</span>
                    <span>$<?php echo number_format($cot['total'], 2); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Formas Vinculadas (NUEVO) -->
            <?php if(!empty($entradas_formulario)): ?>
            <div class="mt-12 pt-10 border-t border-slate-100">
                <h3 class="text-sm font-black text-slate-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-3">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Formularios Completados (Traceability)
                </h3>
                
                <div class="grid grid-cols-1 gap-4">
                    <?php foreach($entradas_formulario as $ent): 
                        $data = json_decode($ent['data'], true) ?: [];
                    ?>
                    <div class="bg-blue-50/50 p-6 rounded-3xl border border-blue-100/50 group hover:bg-white hover:shadow-xl transition-all duration-300">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h4 class="text-sm font-black text-slate-900 group-hover:text-blue-600 transition-colors uppercase tracking-tight"><?php echo htmlspecialchars($ent['formulario_nombre']); ?></h4>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1"><?php echo date('d M Y, h:i A', strtotime($ent['created_at'])); ?></p>
                            </div>
                            <span class="px-3 py-1 bg-white border border-blue-100 text-[9px] font-black text-blue-600 rounded-lg uppercase tracking-widest shadow-sm">Recibido</span>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach($data as $label => $value): 
                                if($label === 'paso_actual' || is_array($value)) continue;
                            ?>
                            <div class="bg-white/60 p-3 rounded-2xl border border-blue-50">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1"><?php echo htmlspecialchars($label); ?></p>
                                <p class="text-xs font-bold text-slate-700 truncate" title="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($value); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if($cot['notas']): ?>
        <div class="glass-card p-12 rounded-[3.5rem] shadow-xl shadow-slate-100">
            <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-4">
                <span class="w-12 h-px bg-slate-200"></span>
                Términos y Condiciones
            </h3>
            <div class="text-slate-600 leading-relaxed italic text-lg px-2">
                <?php echo nl2br(htmlspecialchars($cot['notas'])); ?>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <!-- Bottom Actions dock -->
    <div class="fixed bottom-0 w-full z-40 no-print flex justify-center pb-8 px-6 pointer-events-none">
        <div class="glass-dock px-8 py-5 rounded-[2.5rem] shadow-2xl flex items-center gap-6 animate-up pointer-events-auto bg-white/90 backdrop-blur-xl border border-white/50">
            <div class="hidden md:block pr-6 border-r border-slate-200/50">
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] mb-0.5">Estado actual</p>
                <p class="text-sm font-black text-slate-900 italic tracking-tight uppercase"><?php echo $cot['estado']; ?></p>
            </div>
            
            <div class="flex items-center gap-4">
                <a href="editar-cotizacion.php?id=<?php echo $cot['id']; ?>" class="group p-4 bg-slate-100 text-slate-600 rounded-2xl hover:bg-slate-200 transition-all font-black text-xs uppercase tracking-widest floating-action flex items-center gap-2">
                    <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    <span>Editar Datos</span>
                </a>

                <button onclick="abrirModalRecordatorios()" class="group p-4 bg-amber-50 text-amber-600 rounded-2xl hover:bg-amber-100 transition-all font-black text-xs uppercase tracking-widest floating-action flex items-center gap-2 border border-amber-100">
                    <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <span>Recordatorios</span>
                </button>

                <a href="disenar-cotizacion.php?id=<?php echo $cot['id']; ?>" class="group p-4 bg-purple-50 text-purple-600 rounded-2xl hover:bg-purple-100 transition-all font-black text-xs uppercase tracking-widest floating-action flex items-center gap-2 border border-purple-100">
                    <svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                    <span>Diseño</span>
                </a>
                
                <?php if ($cot['estado'] != 'Aprobada' && $cot['estado'] != 'Rechazada'): ?>
                <button onclick="cambiarEstado('Aprobada')" class="p-4 bg-blue-600 text-white rounded-2xl shadow-xl shadow-blue-200 hover:bg-blue-700 transition-all font-black text-xs uppercase tracking-widest floating-action flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Aprobar
                </button>
                <?php endif; ?>

                <button onclick="copiarLinkPublico()" class="p-4 bg-white text-slate-900 rounded-2xl shadow-xl border border-slate-100 hover:bg-slate-50 transition-all font-black text-xs uppercase tracking-widest floating-action flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                    Link
                </button>
            </div>
        </div>
    </div>

    <!-- Seccion de Chat / Notas Slide-over o Modal -->
    <div id="chat-section" class="max-w-5xl mx-auto px-6 mt-12 mb-32 no-print">
        <div class="glass-card p-8 rounded-[2rem] border border-blue-100 shadow-xl relative overflow-hidden">
             <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-400 via-indigo-500 to-purple-500"></div>
             
             <div class="flex items-center justify-between mb-8">
                 <h3 class="text-xl font-black text-slate-900 flex items-center gap-3">
                     <span class="p-2 bg-blue-50 text-blue-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                     </span>
                     Notas & Comentarios
                 </h3>
                 <span class="text-xs font-bold text-slate-400 uppercase tracking-widest">Historial de Comunicación</span>
             </div>

             <div class="flex flex-col md:flex-row gap-8">
                 <!-- Formulario -->
                 <div class="w-full md:w-1/3 space-y-4">
                     <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100">
                         <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Nueva Nota</label>
                         <textarea id="nota-mensaje" class="w-full h-32 p-4 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-500 resize-none transition-all" placeholder="Escribe un comentario..."></textarea>
                         
                         <div class="mt-4 flex items-center justify-between">
                             <label class="flex items-center gap-2 cursor-pointer group">
                                 <input type="checkbox" id="nota-publica" class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                                 <span class="text-xs font-bold text-slate-500 group-hover:text-blue-600 transition-colors">Visible para Cliente</span>
                             </label>
                             <button onclick="enviarNota()" class="px-6 py-2 bg-blue-600 text-white rounded-xl font-black text-xs uppercase tracking-widest hover:bg-blue-700 shadow-lg shadow-blue-200 transition-all flex items-center gap-2">
                                 Enviar
                                 <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                             </button>
                         </div>
                     </div>
                     <p class="text-[10px] text-slate-400 leading-relaxed px-2">
                        * Si marcas "Visible para Cliente", se le notificará por WhatsApp y Email inmediatamente.
                     </p>
                 </div>

                 <!-- Timeline -->
                 <div class="flex-1 bg-white rounded-2xl border border-slate-100 p-6 max-h-[500px] overflow-y-auto custom-scroll" id="historial-container">
                     <!-- Ajax Content -->
                     <div class="flex flex-col items-center justify-center h-40 text-slate-300">
                         <svg class="w-8 h-8 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                         <p class="text-xs font-bold mt-2">Cargando historial...</p>
                     </div>
                 </div>
             </div>
        </div>
    </div>

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

                    <div class="pt-2">
                        <label class="flex items-center gap-3 p-3 border border-slate-100 rounded-xl cursor-pointer hover:bg-slate-50">
                            <input type="checkbox" id="rec-notificar-cliente" checked class="w-4 h-4 text-amber-500 rounded border-slate-300 focus:ring-amber-400">
                            <div>
                                <span class="block text-xs font-bold text-slate-700">Notificar al Cliente</span>
                                <span class="block text-[10px] text-slate-400">Email: <?php echo $cot['cliente_mail']; ?> | WA: <?php echo $cot['cliente_tel']; ?></span>
                            </div>
                        </label>
                    </div>

                    <!-- Extra WhatsApps -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">WhatsApp Adicionales</label>
                        <div id="rec-extra-wa-list" class="space-y-2 mb-2"></div>
                        <div class="flex gap-2">
                            <input type="text" id="rec-new-wa" placeholder="+57 300..." class="flex-1 p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs">
                            <button onclick="addExtraWa()" class="px-3 py-2 bg-slate-200 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-300">+</button>
                        </div>
                        <p class="text-[9px] text-slate-400 mt-1">* Incluye código de país (ej: +57)</p>
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
                    <div id="lista-recordatorios" class="space-y-3">
                        <!-- JS renders here -->
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        let extraWas = [];

        function abrirModalRecordatorios() {
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
            const val = input.value.trim();
            if(!val) return;
            extraWas.push(val);
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
                    <span class="text-xs font-mono text-slate-600">${w}</span>
                    <button onclick="removeExtraWa(${i})" class="text-rose-400 hover:text-rose-600 font-bold">×</button>
                </div>
            `).join('');
        }

        function cargarRecordatorios() {
            const container = document.getElementById('lista-recordatorios');
            container.innerHTML = '<div class="text-center py-4"><svg class="w-6 h-6 animate-spin mx-auto text-slate-300" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg></div>';
            
            fetch(`api_recordatorios.php?action=list&cotizacion_id=<?php echo $cot['id']; ?>`)
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

        function guardarRecordatorio() {
            const fd = new FormData();
            fd.append('action', 'create');
            fd.append('cotizacion_id', '<?php echo $cot['id']; ?>');
            fd.append('asunto', document.getElementById('rec-asunto').value);
            fd.append('mensaje', document.getElementById('rec-mensaje').value);
            fd.append('fecha_programada', document.getElementById('rec-fecha').value);
            if(document.getElementById('rec-notificar-cliente').checked) {
                fd.append('notificar_cliente', '1');
            }
            
            extraWas.forEach(w => fd.append('telefonos_adicionales[]', w));

            fetch('api_recordatorios.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => {
                    if(d.status === 'success') {
                        alert('Recordatorio programado');
                        document.getElementById('rec-asunto').value = '';
                        document.getElementById('rec-mensaje').value = '';
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
    </script>
</body>
</html>
