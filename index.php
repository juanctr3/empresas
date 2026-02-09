<?php
try {
    require_once 'db.php';
    require_once 'includes/auth_helper.php';

    // Lazy Migration: Check for usuario_id if it's missing
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM cotizaciones LIKE 'usuario_id'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE cotizaciones ADD COLUMN usuario_id INT AFTER empresa_id");
            $pdo->exec("ALTER TABLE cotizaciones ADD CONSTRAINT fk_cotizaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL");
            $pdo->exec("UPDATE cotizaciones c SET c.usuario_id = (SELECT id FROM usuarios u WHERE u.empresa_id = c.empresa_id LIMIT 1) WHERE c.usuario_id IS NULL");
        }

        // Lazy Migration: Check for usuario_id in clientes
        $stmt = $pdo->query("SHOW COLUMNS FROM clientes LIKE 'usuario_id'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE clientes ADD COLUMN usuario_id INT AFTER empresa_id");
            $pdo->exec("ALTER TABLE clientes ADD CONSTRAINT fk_clientes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL");
            $pdo->exec("UPDATE clientes c SET c.usuario_id = (SELECT id FROM usuarios u WHERE u.empresa_id = c.empresa_id LIMIT 1) WHERE c.usuario_id IS NULL");
        }
    } catch (Exception $e) {
        // Log but don't block if migration fails (might already be running)
        error_log("Lazy migration failed: " . $e->getMessage());
    }

    $empresa_id = getEmpresaId();

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    if (!$empresa_id && isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true) {
        header("Location: saas_dashboard.php");
        exit;
    }

    // Filtro de Permisos
    $filtro_stats = "";
    $param_user = [];

    if (!tienePermiso('ver_todas_estadisticas')) {
        $filtro_stats = " AND c.usuario_id = ?";
        $param_user = [$_SESSION['user_id']];
    }

    // Obtener estadísticas rápidas
    $total_productos = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE empresa_id = ?");
    $total_productos->execute([$empresa_id]);
    $total_productos = $total_productos->fetchColumn();

    $total_clientes = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE empresa_id = ?");
    $total_clientes->execute([$empresa_id]);
    $total_clientes = $total_clientes->fetchColumn();

    // Stats con Filtro
    $total_cotizaciones = $pdo->prepare("SELECT COUNT(*) FROM cotizaciones c WHERE c.empresa_id = ? $filtro_stats");
    $total_cotizaciones->execute(array_merge([$empresa_id], $param_user));
    $total_cotizaciones = $total_cotizaciones->fetchColumn();

    // Estadísticas Premium
    $ventas_totales = $pdo->prepare("SELECT SUM(c.total) FROM cotizaciones c WHERE c.empresa_id = ? AND c.estado = 'Aprobada' $filtro_stats");
    $ventas_totales->execute(array_merge([$empresa_id], $param_user));
    $ventas_totales = $ventas_totales->fetchColumn() ?: 0;

    $cotizaciones_aprobadas = $pdo->prepare("SELECT COUNT(*) FROM cotizaciones c WHERE c.empresa_id = ? AND c.estado = 'Aprobada' $filtro_stats");
    $cotizaciones_aprobadas->execute(array_merge([$empresa_id], $param_user));
    $cotizaciones_aprobadas = $cotizaciones_aprobadas->fetchColumn() ?: 0;

    $tasa_conversion = $total_cotizaciones > 0 ? ($cotizaciones_aprobadas / $total_cotizaciones) * 100 : 0;

    // Datos para el gráfico (últimos 6 meses)
    $stmt_gra = $pdo->prepare("
        SELECT 
            DATE_FORMAT(c.fecha, '%Y-%m') as mes_id,
            DATE_FORMAT(c.fecha, '%b') as mes_nombre,
            SUM(c.total) as total
        FROM cotizaciones c
        WHERE c.empresa_id = ? AND c.estado = 'Aprobada' AND c.fecha >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) $filtro_stats
        GROUP BY mes_id, mes_nombre
        ORDER BY mes_id ASC
    ");
    $stmt_gra->execute(array_merge([$empresa_id], $param_user));
    $datos_grafico = $stmt_gra->fetchAll();

    $meses_js = json_encode(array_column($datos_grafico, 'mes_nombre'));
    $ventas_js = json_encode(array_column($datos_grafico, 'total'));

    // Últimas cotizaciones
    $stmt_ult = $pdo->prepare("
        SELECT c.*, cl.nombre as cliente_nombre 
        FROM cotizaciones c 
        JOIN clientes cl ON c.cliente_id = cl.id 
        WHERE c.empresa_id = ? $filtro_stats
        ORDER BY c.created_at DESC 
        LIMIT 5
    ");
    $stmt_ult->execute(array_merge([$empresa_id], $param_user));
    $ultimas_cotizaciones = $stmt_ult->fetchAll();

    include 'includes/header.php';
?>

<div class="space-y-8 fade-in-up pb-20">
    <!-- Header: Saludo y Acción -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
        <div class="space-y-1">
            <h1 class="text-4xl font-black text-gray-900 tracking-tight">Hola, <?php echo explode(' ', $_SESSION['user_name'])[0]; ?> <span class="text-indigo-600">👋</span></h1>
            <p class="text-gray-500 font-bold text-sm uppercase tracking-widest">Resumen de tu negocio</p>
        </div>
        <div class="flex gap-2 w-full md:w-auto overflow-x-auto pb-2 md:pb-0 hide-scroll">
            <select id="period-selector" onchange="loadDashboard()" class="bg-white border border-gray-200 text-gray-700 font-bold py-3 px-4 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-xs uppercase tracking-widest">
                <option value="today">Hoy</option>
                <option value="week">Esta Semana</option>
                <option value="month" selected>Este Mes</option>
                <option value="year">Este Año</option>
            </select>
            <a href="nueva-cotizacion.php" class="whitespace-nowrap bg-indigo-600 hover:bg-indigo-700 text-white font-black py-3 px-6 rounded-xl shadow-lg shadow-indigo-100 transition-all active:scale-95 flex items-center gap-2 uppercase tracking-widest text-xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Cotizar
            </a>
        </div>
    </div>

    <!-- KPI Cards (Scrollable Mobile) -->
    <div class="overflow-x-auto pb-4 -mx-4 px-4 md:overflow-visible md:pb-0 md:px-0">
        <div class="flex md:grid md:grid-cols-4 gap-4 md:gap-6 min-w-max md:min-w-0">
            <!-- Card 1: Cotizaciones -->
            <div class="glass-card p-6 w-[280px] md:w-auto flex-shrink-0 relative overflow-hidden group">
                <div class="absolute right-0 top-0 w-24 h-24 bg-blue-500/5 rounded-bl-[3rem] group-hover:bg-blue-500/10 transition-all"></div>
                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2 bg-blue-50 text-blue-600 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></div>
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Cotizado</span>
                    </div>
                    <h3 class="text-3xl font-black text-gray-900 tracking-tight mb-1" id="kpi-quote-amount">$0</h3>
                    <p class="text-xs text-gray-500 font-medium"><span id="kpi-quote-count" class="font-bold text-gray-800">0</span> enviadas</p>
                </div>
            </div>

            <!-- Card 2: Ventas (Aprobadas) -->
            <div class="glass-card p-6 w-[280px] md:w-auto flex-shrink-0 relative overflow-hidden group">
                <div class="absolute right-0 top-0 w-24 h-24 bg-green-500/5 rounded-bl-[3rem] group-hover:bg-green-500/10 transition-all"></div>
                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2 bg-green-50 text-green-600 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Ventas</span>
                    </div>
                    <h3 class="text-3xl font-black text-gray-900 tracking-tight mb-1" id="kpi-sales-amount">$0</h3>
                    <p class="text-xs text-gray-500 font-medium"><span id="kpi-sales-count" class="font-bold text-gray-800">0</span> aprobadas (<span id="kpi-conversion" class="text-green-600 font-bold">0%</span>)</p>
                </div>
            </div>

            <!-- Card 3: Facturado vs Pagado -->
            <div class="glass-card p-6 w-[280px] md:w-auto flex-shrink-0 relative overflow-hidden group">
                <div class="absolute right-0 top-0 w-24 h-24 bg-violet-500/5 rounded-bl-[3rem] group-hover:bg-violet-500/10 transition-all"></div>
                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2 bg-violet-50 text-violet-600 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg></div>
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Cobrado</span>
                    </div>
                    <h3 class="text-3xl font-black text-gray-900 tracking-tight mb-1" id="kpi-paid-amount">$0</h3>
                    <p class="text-xs text-gray-500 font-medium">de <span id="kpi-billed-amount" class="font-bold text-gray-800">$0</span> facturados</p>
                </div>
            </div>

             <!-- Card 4: Clientes Nuevos -->
             <div class="glass-card p-6 w-[280px] md:w-auto flex-shrink-0 relative overflow-hidden group">
                <div class="absolute right-0 top-0 w-24 h-24 bg-orange-500/5 rounded-bl-[3rem] group-hover:bg-orange-500/10 transition-all"></div>
                <div class="relative z-10">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2 bg-orange-50 text-orange-600 rounded-lg"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg></div>
                        <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Crecimiento</span>
                    </div>
                    <h3 class="text-3xl font-black text-gray-900 tracking-tight mb-1" id="kpi-new-clients">0</h3>
                    <p class="text-xs text-gray-500 font-medium">Nuevos Clientes</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Chart -->
        <div class="lg:col-span-2 glass-card p-6 md:p-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-xl font-black text-gray-900">Evolución Comercial</h3>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Cotizaciones vs Contrataciones</p>
                </div>
                <!-- Tabs Chart -->
                <div class="flex bg-gray-100 p-1 rounded-xl">
                    <button onclick="switchChart('quotes')" id="tab-quotes" class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all bg-white shadow-sm text-indigo-600">Cotizaciones</button>
                    <button onclick="switchChart('finance')" id="tab-finance" class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all text-gray-500 hover:text-gray-700">Finanzas</button>
                </div>
            </div>
            <div class="h-[300px] w-full relative">
                <canvas id="mainChart"></canvas>
            </div>
        </div>

        <!-- Top Products / Services -->
        <div class="glass-card p-6 md:p-8 flex flex-col">
            <h3 class="text-xl font-black text-gray-900 mb-1">Top Servicios</h3>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-6">Lo más cotizado</p>
            
            <div class="flex-1 overflow-y-auto pr-1 custom-scroll space-y-4" id="top-products-list">
                 <!-- Injected via JS -->
                 <div class="animate-pulse space-y-4">
                    <div class="h-12 bg-gray-100 rounded-xl w-full"></div>
                    <div class="h-12 bg-gray-100 rounded-xl w-full"></div>
                    <div class="h-12 bg-gray-100 rounded-xl w-full"></div>
                 </div>
            </div>
        </div>
    </div>

    <!-- Pending Invoices & Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Pending Invoices -->
        <div class="lg:col-span-2 glass-card p-6 md:p-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-xl font-black text-gray-900">Facturas Pendientes</h3>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Gestión de Cobro</p>
                </div>
                <a href="facturas.php" class="text-indigo-600 text-xs font-bold hover:underline">Ver todas</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[10px] text-gray-400 uppercase tracking-widest border-b border-gray-100">
                            <th class="pb-3 pl-2">Factura</th>
                            <th class="pb-3">Cliente</th>
                            <th class="pb-3">Vence</th>
                            <th class="pb-3 text-right">Total</th>
                            <th class="pb-3 text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="pending-invoices-list" class="text-sm">
                        <!-- Injected via JS -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Quick Actions / Mini Stats -->
        <div class="glass-card p-6 md:p-8 bg-indigo-600 text-white relative overflow-hidden">
             <!-- Decorative circles -->
             <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 rounded-full bg-white/10 blur-xl"></div>
             <div class="absolute bottom-0 left-0 -ml-8 -mb-8 w-32 h-32 rounded-full bg-white/10 blur-xl"></div>
             
             <div class="relative z-10 h-full flex flex-col justify-between">
                <div>
                    <h3 class="text-xl font-black mb-1">Base de Clientes</h3>
                    <div class="flex items-end gap-2 mb-6">
                        <span class="text-4xl font-black" id="total-clients-display">0</span>
                        <span class="text-indigo-200 text-sm font-medium mb-1">Total registrados</span>
                    </div>
                    
                    <div class="space-y-3">
                        <div class="bg-white/10 rounded-xl p-3 flex justify-between items-center">
                            <span class="text-xs font-bold text-indigo-100">Clientes Activos</span>
                            <span class="font-black" id="count-clients">0</span>
                        </div>
                        <div class="bg-white/10 rounded-xl p-3 flex justify-between items-center">
                            <span class="text-xs font-bold text-indigo-100">Prospectos</span>
                            <span class="font-black" id="count-prospects">0</span>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                     <button onclick="window.location.href='clientes.php'" class="w-full py-3 bg-white text-indigo-600 font-bold rounded-xl shadow-lg hover:bg-indigo-50 transition-all uppercase tracking-widest text-xs">
                        Gestionar Clientes
                     </button>
                </div>
             </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    let mainChartInstance = null;
    let dashboardData = { charts: {} };
    let currentChartMode = 'quotes';

    document.addEventListener('DOMContentLoaded', () => {
        loadDashboard();
    });

    async function loadDashboard() {
        const period = document.getElementById('period-selector').value;
        
        // 1. KPIs
        fetch(`api_dashboard.php?action=kpis&period=${period}`)
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    updateKPIs(res.data);
                }
            });

        // 2. Charts
        fetch(`api_dashboard.php?action=charts&period=${period}`)
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    console.log('Dashboard Charts Data Received:', res.data); // DEBUG: Show data in console
                    dashboardData.charts = res.data;
                    renderMainChart();
                    renderTopProducts(res.data.top_products);
                } else {
                    console.error('API Error for charts:', res);
                    // Show error in a toast or alert if needed
                }
            });

        // 3. Lists
        fetch(`api_dashboard.php?action=lists`)
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    renderPendingInvoices(res.data.pending_invoices);
                    renderClientRatio(res.data.client_ratio);
                }
            });
    }

    function updateKPIs(data) {
        // Quotes
        animateValue('kpi-quote-amount', data.quotes.amount, '$');
        animateValue('kpi-quote-count', data.quotes.count);
        
        // Sales
        animateValue('kpi-sales-amount', data.quotes.approved_amount, '$');
        animateValue('kpi-sales-count', data.quotes.approved_count);
        document.getElementById('kpi-conversion').innerText = data.quotes.conversion_rate + '%';
        
        // Finance
        animateValue('kpi-billed-amount', data.billing.billed, '$');
        animateValue('kpi-paid-amount', data.billing.paid, '$');
        
        // Clients
        animateValue('kpi-new-clients', data.clients.new);
    }

    function renderMainChart() {
        const ctx = document.getElementById('mainChart').getContext('2d');
        if(mainChartInstance) mainChartInstance.destroy();

        let labels = [];
        let datasets = [];

        if(currentChartMode === 'quotes') {
            const history = dashboardData.charts.quotes_evolution || [];
            labels = history.map(h => h.label);
            datasets = [
                {
                    label: 'Enviadas',
                    data: history.map(h => h.total),
                    borderColor: '#94a3b8',
                    backgroundColor: 'rgba(148, 163, 184, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Aprobadas',
                    data: history.map(h => h.won),
                    borderColor: '#4f46e5', // Indigo 600
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true
                }
            ];
        } else {
            const finance = dashboardData.charts.finance_evolution || [];
            labels = finance.map(f => f.label);
            datasets = [
                {
                    label: 'Facturado ($)',
                    data: finance.map(f => f.billed),
                    borderColor: '#8b5cf6', // Violet 500
                    backgroundColor: 'rgba(139, 92, 246, 0.05)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Cobrado ($)',
                    data: finance.map(f => f.paid),
                    borderColor: '#10b981', // Emerald 500
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true
                }
            ];
        }

        mainChartInstance = new Chart(ctx, {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { display: false },
                        ticks: { font: { size: 10, weight: 'bold' } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
    }

    function switchChart(mode) {
        currentChartMode = mode;
        
        // Update Tabs UI
        const btnQ = document.getElementById('tab-quotes');
        const btnF = document.getElementById('tab-finance');
        
        if(mode === 'quotes') {
            btnQ.classList.add('bg-white', 'shadow-sm', 'text-indigo-600');
            btnQ.classList.remove('text-gray-500');
            btnF.classList.remove('bg-white', 'shadow-sm', 'text-indigo-600');
            btnF.classList.add('text-gray-500');
        } else {
            btnF.classList.add('bg-white', 'shadow-sm', 'text-indigo-600');
            btnF.classList.remove('text-gray-500');
            btnQ.classList.remove('bg-white', 'shadow-sm', 'text-indigo-600');
            btnQ.classList.add('text-gray-500');
        }
        
        renderMainChart();
    }

    function renderTopProducts(products) {
        const container = document.getElementById('top-products-list');
        container.innerHTML = '';
        
        if(!products || products.length === 0) {
            container.innerHTML = '<p class="text-sm text-gray-400 text-center py-4">No hay datos suficientes</p>';
            return;
        }

        products.forEach((p, index) => {
            const html = `
                <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors group">
                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center font-black text-xs text-gray-400 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                        ${index + 1}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-gray-800 text-sm truncate">${p.label}</p>
                        <p class="text-[10px] text-gray-400 font-medium">${p.count} ventas</p>
                    </div>
                    <span class="font-black text-indigo-600 text-sm">$${parseInt(p.total_value).toLocaleString()}</span>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        });
    }

    function renderPendingInvoices(invoices) {
        const tbody = document.getElementById('pending-invoices-list');
        tbody.innerHTML = '';

        if(!invoices || invoices.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-gray-400 text-xs">No hay facturas pendientes</td></tr>';
            return;
        }

        invoices.forEach(inv => {
            const html = `
                <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50 transition-colors">
                    <td class="py-3 pl-2 font-bold text-gray-800 text-xs">#${inv.numero_factura}</td>
                    <td class="py-3 text-xs text-gray-600 truncate max-w-[100px]">${inv.cliente_nombre}</td>
                    <td class="py-3 text-xs text-amber-600 font-bold">${inv.fecha_vencimiento}</td>
                    <td class="py-3 text-right font-black text-gray-800 text-xs">$${parseInt(inv.total).toLocaleString()}</td>
                    <td class="py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                             ${inv.celular_contacto ? 
                                `<button onclick="sendReminderWA('${inv.celular_contacto}', '${inv.numero_factura}', '${inv.total}', '${inv.hash_publico}')" class="p-1.5 bg-green-50 text-green-600 rounded-lg hover:bg-green-600 hover:text-white transition-colors" title="WhatsApp">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.438 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"></path></svg>
                                </button>` : ''}
                             <a href="ver-factura.php?h=${inv.hash_publico}" target="_blank" class="p-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition-colors" title="Ver">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                             </a>
                        </div>
                    </td>
                </tr>
            `;
            tbody.insertAdjacentHTML('beforeend', html);
        });
    }

    function renderClientRatio(data) {
        document.getElementById('total-clients-display').innerText = (parseInt(data.clientes || 0) + parseInt(data.prospectos || 0));
        animateValue('count-clients', data.clientes);
        animateValue('count-prospects', data.prospectos);
    }

    function sendReminderWA(phone, invoice, amount, hash) {
        const url = `https://wa.me/${phone}?text=Hola, te recordamos que tienes la factura ${invoice} pendiente de pago por $${amount}. Puedes verla aquí: ${window.location.origin}/ver-factura.php?h=${hash}`;
        window.open(url, '_blank');
    }

    function animateValue(id, value, prefix = '') {
        const obj = document.getElementById(id);
        if(!obj) return;
        
        // Simple Set for now, animation logic can be added later
        obj.innerHTML = prefix + parseInt(value).toLocaleString();
    }

</script>

<?php
    include 'includes/footer.php';
} catch (Throwable $e) {
    echo "<div style='padding: 20px; background: #fff1f2; border: 1px solid #fda4af; border-radius: 12px; margin: 20px; font-family: sans-serif;'>";
    echo "<h2 style='color: #be123c; margin: 0 0 10px 0;'>Error en el Dashboard</h2>";
    echo "<p style='color: #9f1239; margin: 0;'><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p style='color: #9f1239; margin: 5px 0 0 0;'><strong>Archivo:</strong> " . htmlspecialchars($e->getFile()) . " (Línea " . $e->getLine() . ")</p>";
    echo "</div>";
}
?>
