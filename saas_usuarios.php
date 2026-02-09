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

// Búsqueda
$search = $_GET['q'] ?? '';
$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (u.nombre LIKE ? OR u.email LIKE ? OR e.nombre LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}

// Obtener todos los usuarios
$stmt = $pdo->prepare("
    SELECT u.*, e.nombre as empresa_nombre, r.nombre as rol_nombre 
    FROM usuarios u 
    LEFT JOIN empresas e ON u.empresa_id = e.id 
    LEFT JOIN roles r ON u.rol_id = r.id 
    $where 
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="space-y-10 fade-in-up">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-4xl font-black text-gray-900 tracking-tight">Gestión de <span class="text-indigo-600">Usuarios</span></h1>
            <p class="text-gray-500 font-medium text-lg">Administración global de accesos en la plataforma.</p>
        </div>
        <a href="saas_dashboard.php" class="bg-gray-100 text-gray-600 px-6 py-3 rounded-2xl font-black uppercase text-[10px] tracking-widest hover:bg-gray-200 transition-all">&larr; Volver</a>
    </div>

    <!-- Search -->
    <div class="glass-card p-4">
        <form method="GET" class="flex gap-4">
            <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar por nombre, email o empresa..." class="flex-1 px-6 py-4 rounded-xl border border-gray-100 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all font-medium">
            <button type="submit" class="bg-indigo-600 text-white px-8 py-4 rounded-xl font-black uppercase text-xs tracking-widest hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200">Buscar</button>
        </form>
    </div>

    <!-- Users Table -->
    <div class="glass-card p-8">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100">
                        <th class="px-4 py-4">Usuario</th>
                        <th class="px-4 py-4">Empresa</th>
                        <th class="px-4 py-4">Rol</th>
                        <th class="px-4 py-4">Estado</th>
                        <th class="px-4 py-4 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-gray-400 italic">No se encontraron usuarios.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($usuarios as $user): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors group">
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                                        <?php echo substr($user['nombre'], 0, 1); ?>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-900"><?php echo htmlspecialchars($user['nombre']); ?></span>
                                        <span class="text-[10px] text-gray-400 font-medium"><?php echo htmlspecialchars($user['email']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="text-sm font-semibold text-gray-600"><?php echo htmlspecialchars($user['empresa_nombre'] ?: 'N/A'); ?></span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="px-3 py-1 bg-indigo-50 text-indigo-600 text-[9px] font-black uppercase rounded-full">
                                    <?php echo $user['rol_nombre'] ?: ($user['is_super_admin'] ? 'Super Admin' : 'N/A'); ?>
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <?php if ($user['activo']): ?>
                                    <span class="flex items-center gap-1.5 text-green-600 text-[10px] font-black uppercase tracking-widest">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Activo
                                    </span>
                                <?php else: ?>
                                    <span class="flex items-center gap-1.5 text-red-500 text-[10px] font-black uppercase tracking-widest">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400"></span> Inactivo
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button class="text-xs font-black text-indigo-500 hover:text-indigo-700 uppercase tracking-widest bg-indigo-50 px-3 py-1.5 rounded-lg transition-all">Editar</button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button class="text-xs font-black text-red-500 hover:text-red-700 uppercase tracking-widest bg-red-50 px-3 py-1.5 rounded-lg transition-all">Suspender</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
