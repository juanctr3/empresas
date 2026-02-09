<?php
/**
 * Vista Premium Estándar de Cotización
 * Compartida entre propuesta.php y exportar-cotizacion.php
 * 
 * Variables requeridas: $cot, $detalles, $tabla_html, $hash, $es_pdf (opcional)
 */

// Trusted clients (para la vista del cliente solamente)
$trusted_clients = [];
$adjuntos = [];

// Trusted clients (para la vista del cliente solamente)
$trusted_clients = [];
$adjuntos = [];

// Variables de formato
$logo_src = !empty($cot['plantilla_logo']) ? $cot['plantilla_logo'] : (!empty($cot['empresa_logo']) ? $cot['empresa_logo'] : '');
$logo_html = $logo_src ? '<img src="' . getSecureUrl($logo_src, $hash) . '" class="h-24 md:h-32 w-auto object-contain transition-all hover:scale-105 duration-500">' : '<div class="text-3xl font-black text-slate-900 tracking-tighter uppercase">' . htmlspecialchars($cot['empresa_nombre']) . '</div>';
$num_cot = htmlspecialchars($cot['numero_cotizacion'] ?: $cot['id']);
$fecha_fmt = date('d M, Y', strtotime($cot['fecha']));
$venc_fmt = date('d M, Y', strtotime($cot['fecha_vencimiento']));
?>

<div class="max-w-5xl mx-auto space-y-16 px-4">
    <!-- Main Header Card -->
    <div class="glass-card p-10 md:p-20 rounded-[4rem] shadow-2xl shadow-blue-900/10 relative overflow-hidden print-no-shadow">
        <div class="absolute -top-40 -right-40 w-[500px] h-[500px] bg-blue-500/10 rounded-full blur-[120px]"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-indigo-500/10 rounded-full blur-[100px]"></div>
        
        <div class="relative z-10">
            <div class="flex flex-col lg:flex-row justify-between items-start gap-16">
                <div class="flex-1 space-y-12">
                    <div class="inline-block p-4 bg-white/50 backdrop-blur-xl rounded-[2.5rem] shadow-lg border border-white/50">
                        <?php echo $logo_html; ?>
                    </div>
                    <div class="space-y-6">
                        <div class="flex items-center gap-3">
                            <span class="px-4 py-1.5 rounded-full bg-blue-600 text-white text-[10px] font-black uppercase tracking-[0.3em]">Propuesta Comercial</span>
                            <span class="text-slate-400 font-bold text-xs">#<?php echo $num_cot; ?></span>
                        </div>
                        <h1 class="text-6xl md:text-8xl font-black text-slate-900 tracking-tightest leading-[0.85] print:text-5xl">
                            <span class="text-slate-400">Para:</span><br>
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-700 via-blue-600 to-indigo-600"><?php echo htmlspecialchars($cot['cliente_nombre']); ?></span>
                        </h1>
                        <div class="flex flex-wrap gap-8 pt-4">
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Fecha de Emisión</p>
                                <p class="text-xl font-bold text-slate-900"><?php echo $fecha_fmt; ?></p>
                            </div>
                            <div class="h-10 w-px bg-slate-100 hidden md:block"></div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Validez</p>
                                <p class="text-xl font-bold text-slate-900"><?php echo $venc_fmt; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="w-full lg:w-72 space-y-6">
                    <div class="p-8 rounded-[3rem] bg-slate-900 text-white shadow-2xl relative overflow-hidden group">
                       <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/20 rounded-full blur-2xl -mr-16 -mt-16"></div>
                       <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest mb-4">Valor total de la cotización</p>
                       <div class="space-y-1">
                           <p class="text-3xl font-black tracking-tighter leading-none">$<?php echo number_format($cot['total'], 2); ?></p>
                           <p class="text-xs font-bold text-slate-500 uppercase tracking-widest"><?php echo $cot['empresa_moneda']; ?></p>
                       </div>
                    </div>
                    <div class="p-8 rounded-[3rem] bg-blue-50 border border-blue-100/50">
                        <p class="text-[10px] font-black text-blue-600 uppercase tracking-widest mb-2 font-bold">Estado</p>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                            <p class="text-lg font-black text-blue-900 uppercase"><?php echo $cot['estado']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Details & Pricing -->
    <div class="relative z-10">
         <div class="flex items-center gap-4 mb-10">
            <h2 class="text-sm font-black text-slate-400 uppercase tracking-[0.3em]">Detalle de Servicios</h2>
            <div class="flex-1 h-px bg-slate-100"></div>
         </div>
         <?php echo $tabla_html; ?>
    </div>

    <!-- Summary & Totals -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 relative z-10">
        <div class="<?php echo !$hideGlobalTotals ? 'md:col-span-2' : 'col-span-3'; ?> glass-card p-10 md:p-16 rounded-[4rem] shadow-xl shadow-slate-100 print-no-shadow">
            <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.3em] mb-10 flex items-center gap-3">
                <span class="w-8 h-px bg-slate-200"></span>
                Términos y Observaciones Adicionales
            </h3>
            <div class="prose max-w-none text-slate-600 leading-relaxed text-lg italic">
                <?php echo !empty($cot['notas']) ? nl2br(htmlspecialchars($cot['notas'])) : 'No se han especificado términos adicionales para esta propuesta.'; ?>
            </div>
        </div>
        
        <?php if (!$hideGlobalTotals): ?>
        <div class="bg-white p-10 md:p-12 rounded-[4rem] shadow-2xl shadow-blue-900/5 border border-slate-50 flex flex-col justify-center space-y-8 relative overflow-hidden">
            <div class="space-y-4">
                <div class="flex justify-between items-center text-slate-400 font-bold text-xs uppercase tracking-widest">
                    <span>Subtotal</span>
                    <span>$<?php echo number_format($cot['subtotal'], 2); ?></span>
                </div>
                <div class="flex justify-between items-center text-slate-400 font-bold text-xs uppercase tracking-widest">
                    <span>Impuestos</span>
                    <span>$<?php echo number_format($cot['impuesto_total'] ?? ($cot['impuestos'] ?? 0), 2); ?></span>
                </div>
                <div class="h-px bg-slate-50 my-6"></div>
                <div class="flex justify-between items-end">
                    <div>
                        <p class="text-[10px] font-black text-blue-600 uppercase tracking-widest mb-1">Inversión Final</p>
                        <p class="text-4xl font-black text-slate-900 tracking-tightest leading-none">$<?php echo number_format($cot['total'], 2); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="text-center pt-16 border-t border-slate-100">
        <p class="text-[10px] text-gray-400 font-black uppercase tracking-[0.3em]">Propuesta Comercial Generada por CoticeFacil</p>
    </div>
</div>
