<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';
requerirPermiso('gestionar_roles');


// Procesar Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'guardar_rol':
                $id = $_POST['id'] ?? 0;
                $nombre = $_POST['nombre'];
                $descripcion = $_POST['descripcion'];
                $permisos_seleccionados = $_POST['permisos'] ?? [];

                if ($id > 0) {
                    // Verificar que el rol pertenece a la empresa
                    $stmtCheck = $pdo->prepare("SELECT id FROM roles WHERE id = ? AND empresa_id = ?");
                    $stmtCheck->execute([$id, getEmpresaId()]);
                    if (!$stmtCheck->fetch()) {
                        die("No tienes permiso para editar este rol.");
                    }

                    $stmt = $pdo->prepare("UPDATE roles SET nombre = ?, descripcion = ? WHERE id = ? AND empresa_id = ?");
                    $stmt->execute([$nombre, $descripcion, $id, getEmpresaId()]);
                    
                    // Actualizar permisos
                    $pdo->prepare("DELETE FROM rol_permisos WHERE rol_id = ?")->execute([$id]);
                    $stmt = $pdo->prepare("INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (?, ?)");
                    foreach ($permisos_seleccionados as $p_id) {
                        $stmt->execute([$id, $p_id]);
                    }
                } else {
                    $stmt = $pdo->prepare("INSERT INTO roles (empresa_id, nombre, descripcion) VALUES (?, ?, ?)");
                    $stmt->execute([getEmpresaId(), $nombre, $descripcion]);
                    $new_id = $pdo->lastInsertId();

                    $stmt = $pdo->prepare("INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (?, ?)");
                    foreach ($permisos_seleccionados as $p_id) {
                        $stmt->execute([$new_id, $p_id]);
                    }
                }
                break;
            case 'eliminar_rol':
                $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$_POST['id'], getEmpresaId()]);
                break;
        }
        header("Location: roles.php?success=1");
        exit;
    }
}

include 'includes/header.php';

$stmt_roles = $pdo->prepare("SELECT * FROM roles WHERE empresa_id = ?");
$stmt_roles->execute([getEmpresaId()]);
$roles = $stmt_roles->fetchAll();

$permisos_todos = $pdo->query("SELECT * FROM permisos ORDER BY nombre ASC")->fetchAll();

// Función para obtener permisos de un rol
function getPermisosRol($pdo, $rol_id) {
    $stmt = $pdo->prepare("SELECT permiso_id FROM rol_permisos WHERE rol_id = ?");
    $stmt->execute([$rol_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<div class="space-y-8 animate-fade-in">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Roles y Permisos</h1>
            <p class="text-gray-500 mt-1">Define quién puede hacer qué dentro del sistema.</p>
        </div>
        <button onclick="openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-2xl shadow-lg shadow-indigo-200 transition-all flex items-center gap-2 active:scale-95">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Nuevo Rol
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($roles as $r): ?>
            <?php $p_ids = getPermisosRol($pdo, $r['id']); ?>
            <div class="bg-white p-8 rounded-[2rem] border border-gray-100 card-shadow hover:border-indigo-200 transition-all group relative overflow-hidden">
                <div class="absolute top-0 right-0 p-4">
                    <span class="bg-indigo-50 text-indigo-600 text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full">
                        <?php echo count($p_ids); ?> Permisos
                    </span>
                </div>
                
                <h3 class="text-xl font-bold text-gray-900 mb-2 group-hover:text-indigo-600 transition-colors"><?php echo htmlspecialchars($r['nombre']); ?></h3>
                <p class="text-sm text-gray-500 mb-6 line-clamp-2"><?php echo htmlspecialchars($r['descripcion'] ?: 'Sin descripción'); ?></p>

                <div class="flex items-center justify-between pt-6 border-t border-gray-50">
                    <button onclick='editRol(<?php echo json_encode($r); ?>, <?php echo json_encode($p_ids); ?>)' class="text-sm font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        Configurar
                    </button>
                    <?php if ($r['nombre'] !== 'Super Admin'): ?>
                    <form action="roles.php" method="POST" onsubmit="return confirm('¿Eliminar este rol?')">
                        <input type="hidden" name="accion" value="eliminar_rol">
                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                        <button type="submit" class="text-sm font-bold text-red-400 hover:text-red-600 transition-colors">Eliminar</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal -->
<div id="modalRol" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-3xl rounded-[2.5rem] shadow-2xl overflow-hidden animate-fade-in max-h-[90vh] flex flex-col">
        <div class="p-8 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <div>
                <h2 id="modalTitle" class="text-2xl font-bold text-gray-900">Configuración de Rol</h2>
                <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">Permisos y Accesos</p>
            </div>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <form action="roles.php" method="POST" class="flex-grow overflow-y-auto p-8 space-y-8">
            <input type="hidden" name="accion" value="guardar_rol">
            <input type="hidden" name="id" id="rol_id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Nombre del Rol</label>
                    <input type="text" name="nombre" id="rol_nombre" required placeholder="Ej: Vendedor" class="w-full px-6 py-4 rounded-2xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-lg font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Descripción Corta</label>
                    <input type="text" name="descripcion" id="rol_descripcion" placeholder="Ej: Acceso a ventas y clientes" class="w-full px-6 py-4 rounded-2xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Seleccionar Permisos Disponibles</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($permisos_todos as $p): ?>
                        <label class="flex items-center gap-4 p-4 rounded-2xl border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50 transition-all cursor-pointer group">
                            <input type="checkbox" name="permisos[]" value="<?php echo $p['id']; ?>" class="permiso-checkbox w-6 h-6 rounded-lg text-indigo-600 focus:ring-indigo-500 border-gray-300 transition-all">
                            <div>
                                <h4 class="text-sm font-bold text-gray-800 group-hover:text-indigo-700 transition-colors"><?php echo htmlspecialchars($p['nombre']); ?></h4>
                                <p class="text-[10px] text-gray-400 line-clamp-1 italic"><?php echo htmlspecialchars($p['descripcion']); ?></p>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-end gap-4 sticky bottom-0 bg-white pt-4 pb-2 border-t border-gray-50 mt-4">
                <button type="button" onclick="closeModal()" class="px-8 py-3 rounded-2xl font-bold text-gray-500 hover:bg-gray-100 transition-all">Cancelar</button>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-10 rounded-2xl shadow-xl shadow-indigo-200 transition-all active:scale-95">Guardar Configuración</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('modalTitle').innerText = 'Nuevo Rol';
        document.getElementById('rol_id').value = '';
        document.getElementById('rol_nombre').value = '';
        document.getElementById('rol_descripcion').value = '';
        document.querySelectorAll('.permiso-checkbox').forEach(cb => cb.checked = false);
        document.getElementById('modalRol').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('modalRol').classList.add('hidden');
    }

    function editRol(rol, permisos) {
        document.getElementById('modalTitle').innerText = 'Editar Rol: ' + rol.nombre;
        document.getElementById('rol_id').value = rol.id;
        document.getElementById('rol_nombre').value = rol.nombre;
        document.getElementById('rol_descripcion').value = rol.descripcion;
        
        document.querySelectorAll('.permiso-checkbox').forEach(cb => {
            cb.checked = permisos.includes(cb.value) || permisos.includes(parseInt(cb.value));
        });
        
        document.getElementById('modalRol').classList.remove('hidden');
    }
</script>

<?php include 'includes/footer.php'; ?>
