<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';

// Verificar sesión
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = '';
$msg_type = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Actualizar Datos Básicos
    if (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_perfil') {
        $nombre = trim($_POST['nombre']);
        $telefono = trim($_POST['telefono']);
        $email = trim($_POST['email']); // Solo editable si no es admin, o con cuidado (no implementado cambio de email aqui por seguridad simple)
        
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, telefono = ? WHERE id = ?");
            $stmt->execute([$nombre, $telefono, $user_id]);
            
            // Actualizar sesión
            $_SESSION['user_nombre'] = $nombre;
            
            $msg = "Perfil actualizado correctamente.";
            $msg_type = "success";
        } catch (PDOException $e) {
            $msg = "Error al actualizar perfil: " . $e->getMessage();
            $msg_type = "error";
        }
    }

    // 2. Cambiar Contraseña
    if (isset($_POST['accion']) && $_POST['accion'] === 'cambiar_password') {
        $actual = $_POST['password_actual'];
        $nueva = $_POST['password_nueva'];
        $confirmar = $_POST['password_confirmar'];

        if ($nueva !== $confirmar) {
            $msg = "Las nuevas contraseñas no coinciden.";
            $msg_type = "error";
        } else {
            // Verificar actual
            $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
            $stmt->execute([$user_id]);
            $hash_actual = $stmt->fetchColumn();

            if (password_verify($actual, $hash_actual)) {
                $nuevo_hash = password_hash($nueva, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                $stmt->execute([$nuevo_hash, $user_id]);
                $msg = "Contraseña actualizada con éxito.";
                $msg_type = "success";
            } else {
                $msg = "La contraseña actual es incorrecta.";
                $msg_type = "error";
            }
        }
    }
}

// Obtener datos usuario
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();

include 'includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-8 animate-fade-in">
    <div>
        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">Mi Perfil</h1>
        <p class="text-gray-500 mt-1">Gestiona tu información personal y seguridad.</p>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl <?php echo $msg_type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'; ?> font-semibold">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Datos Personales -->
        <div class="bg-white p-8 rounded-3xl border border-gray-100 card-shadow h-fit">
            <h2 class="text-xl font-bold mb-6 text-gray-800 flex items-center gap-2">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                Información Personal
            </h2>
            <form action="perfil.php" method="POST" class="space-y-5">
                <input type="hidden" name="accion" value="actualizar_perfil">
                
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Nombre Completo</label>
                    <input type="text" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Correo Electrónico</label>
                    <input type="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" disabled class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-gray-500 cursor-not-allowed">
                    <p class="text-[10px] text-gray-400 mt-1">El correo electrónico no se puede editar.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Teléfono Móvil (WhatsApp)</label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-bold">📱</span>
                        <input type="text" name="telefono" value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>" placeholder="+573001234567" class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                    </div>
                    <p class="text-[10px] text-indigo-500 mt-1 font-bold">Importante: Necesario para recibir códigos OTP por WhatsApp.</p>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl shadow-lg transition-all active:scale-95">
                        Guardar Cambios
                    </button>
                </div>
            </form>
        </div>

        <!-- Seguridad -->
        <div class="bg-white p-8 rounded-3xl border border-gray-100 card-shadow h-fit">
            <h2 class="text-xl font-bold mb-6 text-gray-800 flex items-center gap-2">
                <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                Seguridad
            </h2>
            <form action="perfil.php" method="POST" class="space-y-5">
                <input type="hidden" name="accion" value="cambiar_password">
                
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Contraseña Actual</label>
                    <input type="password" name="password_actual" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-red-500 outline-none transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Nueva Contraseña</label>
                    <input type="password" name="password_nueva" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-red-500 outline-none transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Confirmar Nueva Contraseña</label>
                    <input type="password" name="password_confirmar" required class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-red-500 outline-none transition-all">
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full border border-red-200 text-red-600 hover:bg-red-50 font-bold py-3 rounded-xl transition-all active:scale-95">
                        Actualizar Contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
