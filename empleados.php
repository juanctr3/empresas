<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';
requerirPermiso('gestionar_empleados');

// Procesar Acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'guardar_empleado':
                $id = $_POST['id'] ?? 0;
                $nombre = $_POST['nombre'];
                $email = $_POST['email'];
                $telefono = $_POST['telefono'];
                $cargo = $_POST['cargo'];
                $rol_id = $_POST['rol_id'];
                $activo = isset($_POST['activo']) ? 1 : 0;
                $biografia = $_POST['biografia'];

                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE usuarios SET nombre=?, email=?, telefono=?, cargo=?, rol_id=?, activo=?, biografia=? WHERE id=? AND empresa_id=?");
                    $stmt->execute([$nombre, $email, $telefono, $cargo, $rol_id, $activo, $biografia, $id, getEmpresaId()]);
                } else {
                    if (haAlcanzadoLimite('usuarios')) {
                        header("Location: empleados.php?error=limit_reached");
                        exit;
                    }
                    $stmt = $pdo->prepare("INSERT INTO usuarios (empresa_id, nombre, email, telefono, cargo, rol_id, activo, biografia, requires_password_setup) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([getEmpresaId(), $nombre, $email, $telefono, $cargo, $rol_id, $activo, $biografia]);
                    
                    $nuevo_id = $pdo->lastInsertId();
                    enviarInvitacionPassword($pdo, $nuevo_id);
                }
                break;
            case 'enviar_invitacion':
            case 'reiniciar_password':
                $id = $_POST['id'];
                enviarInvitacionPassword($pdo, $id);
                $msg = ($_POST['accion'] === 'reiniciar_password') ? 'password_reset_sent' : 'invitation_sent';
                header("Location: empleados.php?success=" . $msg);
                exit;
            case 'eliminar_empleado':
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$_POST['id'], getEmpresaId()]);
                break;
        }
        header("Location: empleados.php?success=1");
        exit;
    }
}

include 'includes/header.php';

$empresa_id = getEmpresaId();
$stmt_users = $pdo->prepare("SELECT u.*, r.nombre as rol_nombre FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.empresa_id = ?");
$stmt_users->execute([$empresa_id]);
$usuarios = $stmt_users->fetchAll();

$stmt_roles = $pdo->prepare("SELECT * FROM roles WHERE empresa_id = ?");
$stmt_roles->execute([$empresa_id]);
$roles = $stmt_roles->fetchAll();
?>

<div class="space-y-8 animate-fade-in">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <?php if(isset($_GET['error']) && $_GET['error'] === 'limit_reached'): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4 rounded-r-xl text-red-700 text-sm w-full">
                Has alcanzado el límite de usuarios de tu plan.
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['success']) && $_GET['success'] === 'invitation_sent'): ?>
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4 rounded-r-xl text-blue-700 text-sm w-full">
                Enlace de configuración enviado correctamente por Email y WhatsApp.
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['success']) && $_GET['success'] === 'password_reset_sent'): ?>
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4 rounded-r-xl text-blue-700 text-sm w-full">
                Enlace para restablecer contraseña enviado correctamente.
            </div>
        <?php endif; ?>
        <div>
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Equipo de Trabajo</h1>
            <p class="text-gray-500 mt-1">Gestiona los colaboradores y sus niveles de acceso.</p>
        </div>
        <div class="flex gap-3">
            <a href="roles.php" class="bg-white border border-gray-200 text-gray-700 font-bold py-3 px-6 rounded-2xl hover:bg-gray-50 transition-all flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                Configurar Roles
            </a>
            <button onclick="openModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-2xl shadow-lg shadow-blue-200 transition-all flex items-center gap-2 active:scale-95">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Nuevo Empleado
            </button>
        </div>
    </div>

    <!-- Grid de Empleados -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($usuarios as $u): ?>
            <div class="bg-white p-6 rounded-[2rem] border border-gray-100 card-shadow hover:border-blue-200 transition-all group">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-50 flex items-center justify-center text-blue-600 font-bold text-xl overflow-hidden shadow-inner uppercase">
                            <?php if($u['avatar']): ?>
                                <img src="<?php echo $u['avatar']; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?php echo substr($u['nombre'], 0, 2); ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 group-hover:text-blue-600 transition-colors"><?php echo htmlspecialchars($u['nombre']); ?></h3>
                            <p class="text-xs font-semibold text-blue-500 uppercase tracking-wider"><?php echo htmlspecialchars($u['cargo'] ?: 'Colaborador'); ?></p>
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest <?php echo $u['activo'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'; ?>">
                        <?php echo $u['activo'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </div>
                
                <div class="space-y-2 mb-6">
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        <?php echo htmlspecialchars($u['email']); ?>
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        <?php echo htmlspecialchars($u['telefono'] ?: 'Sin teléfono'); ?>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-gray-50">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium text-gray-400">Rol:</span>
                        <span class="text-xs font-bold text-gray-700"><?php echo htmlspecialchars($u['rol_nombre'] ?: 'Sin asignar'); ?></span>
                    </div>
                    <div class="flex gap-2">
                        <?php if($u['requires_password_setup']): ?>
                            <form action="empleados.php" method="POST">
                                <input type="hidden" name="accion" value="enviar_invitacion">
                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                <button type="submit" title="Reenviar invitación de acceso" class="p-2 text-blue-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                </button>
                            </form>
                        <?php else: ?>
                            <form action="empleados.php" method="POST" onsubmit="return confirm('¿Seguro que deseas reiniciar la contraseña de este empleado? Perderá su acceso actual hasta que configure la nueva.')">
                                <input type="hidden" name="accion" value="reiniciar_password">
                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                <button type="submit" title="Reiniciar contraseña" class="p-2 text-orange-400 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition-all">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                                </button>
                            </form>
                        <?php endif; ?>
                        <button onclick='editEmpleado(<?php echo json_encode($u); ?>)' class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        </button>
                        <form action="empleados.php" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar a este empleado?')">
                            <input type="hidden" name="accion" value="eliminar_empleado">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button type="submit" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal -->
<div id="modalEmpleado" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-2xl rounded-[2.5rem] shadow-2xl overflow-hidden animate-fade-in">
        <div class="p-8 border-b border-gray-100 flex justify-between items-center">
            <h2 id="modalTitle" class="text-2xl font-bold text-gray-900">Nuevo Empleado</h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form action="empleados.php" method="POST" class="p-8 space-y-6">
            <input type="hidden" name="accion" value="guardar_empleado">
            <input type="hidden" name="id" id="emp_id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Nombre Completo</label>
                    <input type="text" name="nombre" id="emp_nombre" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Email</label>
                    <input type="email" name="email" id="emp_email" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Teléfono (WhatsApp)</label>
                    <input type="text" name="telefono" id="emp_telefono" placeholder="+57..." class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Cargo</label>
                    <input type="text" name="cargo" id="emp_cargo" placeholder="Vendedor, Contadora..." class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Rol de Acceso</label>
                    <select name="rol_id" id="emp_rol" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
                        <option value="">Seleccionar Rol</option>
                        <?php foreach($roles as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end pb-2">
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" name="activo" id="emp_activo" checked class="w-6 h-6 rounded-lg border-gray-300 text-blue-600 focus:ring-blue-500 transition-all">
                        <span class="text-sm font-bold text-gray-600 group-hover:text-gray-900">Usuario Activo</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Biografía / Perfil</label>
                <textarea name="biografia" id="emp_biografia" rows="3" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all"></textarea>
            </div>

            <div class="flex justify-end gap-4 pt-4">
                <button type="button" onclick="closeModal()" class="px-8 py-3 rounded-xl font-bold text-gray-500 hover:bg-gray-50 transition-all">Cancelar</button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-12 rounded-xl shadow-lg shadow-blue-200 transition-all active:scale-95">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('modalTitle').innerText = 'Nuevo Empleado';
        document.getElementById('emp_id').value = '';
        document.getElementById('emp_nombre').value = '';
        document.getElementById('emp_email').value = '';
        document.getElementById('emp_telefono').value = '';
        document.getElementById('emp_cargo').value = '';
        document.getElementById('emp_rol').value = '';
        document.getElementById('emp_biografia').value = '';
        document.getElementById('emp_activo').checked = true;
        document.getElementById('modalEmpleado').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('modalEmpleado').classList.add('hidden');
    }

    function editEmpleado(u) {
        document.getElementById('modalTitle').innerText = 'Editar Empleado';
        document.getElementById('emp_id').value = u.id;
        document.getElementById('emp_nombre').value = u.nombre;
        document.getElementById('emp_email').value = u.email;
        document.getElementById('emp_telefono').value = u.telefono;
        document.getElementById('emp_cargo').value = u.cargo;
        document.getElementById('emp_rol').value = u.rol_id;
        document.getElementById('emp_biografia').value = u.biografia;
        document.getElementById('emp_activo').checked = u.activo == 1;
        document.getElementById('modalEmpleado').classList.remove('hidden');
    }
</script>

<?php include 'includes/footer.php'; ?>
