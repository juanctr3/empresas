<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validar que solo un Super Admin pueda ver esto
if (!isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== true) {
    header("Location: index.php");
    exit;
}

// Filtros
$empresa_id = $_GET['empresa_id'] ?? '';
$accion = $_GET['accion'] ?? '';

$query = "SELECT a.*, u.nombre as usuario_nombre, e.nombre as empresa_nombre 
          FROM audit_logs a 
          JOIN usuarios u ON a.usuario_id = u.id 
          LEFT JOIN empresas e ON a.empresa_id_context = e.id 
          WHERE 1=1";

$params = [];

if ($empresa_id) {
    $query .= " AND a.empresa_id_context = ?";
    $params[] = $empresa_id;
}
if ($accion) {
    $query .= " AND a.accion = ?";
    $params[] = $accion;
}

$query .= " ORDER BY a.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Obtener empresas para el filtro
$empresas = $pdo->query("SELECT id, nombre FROM empresas ORDER BY nombre ASC")->fetchAll();
// Obtener acciones únicas para el filtro
$acciones = $pdo->query("SELECT DISTINCT accion FROM audit_logs ORDER BY accion ASC")->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<div class="space-y-8 fade-in-up">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">Logs de Auditoría</h1>
            <p class="text-gray-500 font-medium">Historial de acciones administrativas en la plataforma.</p>
        </div>
        <a href="saas_dashboard.php" class="text-[10px] font-black text-gray-400 uppercase tracking-widest hover:text-indigo-600 transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Volver al Dashboard
        </a>
    </div>

    <!-- Filtros -->
    <div class="glass-card p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
            <div>
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 block">Empresa Contexto</label>
                <select name="empresa_id" class="w-full px-4 py-2 rounded-xl border border-gray-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all bg-white text-sm font-bold text-gray-700">
                    <option value="">Todas las empresas</option>
                    <?php foreach ($empresas as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php echo $empresa_id == $emp['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 block">Tipo de Acción</label>
                <select name="accion" class="w-full px-4 py-2 rounded-xl border border-gray-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all bg-white text-sm font-bold text-gray-700">
                    <option value="">Todas las acciones</option>
                    <?php foreach ($acciones as $acc): ?>
                        <option value="<?php echo $acc; ?>" <?php echo $accion == $acc ? 'selected' : ''; ?>><?php echo $acc; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100 h-[42px]">
                Filtrar
            </button>
        </form>
    </div>

    <!-- Tabla de Logs -->
    <div class="glass-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100">
                        <th class="px-6 py-4">Fecha/Hora</th>
                        <th class="px-6 py-4">Usuario</th>
                        <th class="px-6 py-4">Acción</th>
                        <th class="px-6 py-4">Empresa (Contexto)</th>
                        <th class="px-6 py-4">Detalle</th>
                        <th class="px-6 py-4">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-400 italic">No se encontraron logs con los filtros seleccionados.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($logs as $log): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors group">
                            <td class="px-6 py-4 text-xs font-medium text-gray-500">
                                <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-bold text-gray-900 text-sm"><?php echo htmlspecialchars($log['usuario_nombre']); ?></span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 <?php 
                                    echo strpos($log['accion'], 'START') !== false ? 'bg-green-50 text-green-600' : 
                                        (strpos($log['accion'], 'STOP') !== false ? 'bg-orange-50 text-orange-600' : 'bg-gray-50 text-gray-600');
                                ?> text-[9px] font-black uppercase rounded-lg">
                                    <?php echo $log['accion']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-semibold <?php echo $log['empresa_nombre'] ? 'text-indigo-600' : 'text-gray-400'; ?>">
                                    <?php echo $log['empresa_nombre'] ? htmlspecialchars($log['empresa_nombre']) : 'N/A'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-xs text-gray-600 max-w-xs truncate" title="<?php echo htmlspecialchars($log['detalle']); ?>">
                                    <?php echo htmlspecialchars($log['detalle']); ?>
                                </p>
                            </td>
                            <td class="px-6 py-4 text-[10px] font-mono text-gray-400">
                                <?php echo $log['ip_address']; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
