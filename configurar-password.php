<?php
require_once 'db.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

if (empty($token)) {
    header("Location: login.php");
    exit;
}

// Verificar token
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE password_reset_token = ? AND requires_password_setup = 1");
$stmt->execute([$token]);
$usuario = $stmt->fetch();

if (!$usuario) {
    $error = "El enlace es inválido o ya ha expirado.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($password) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } elseif ($password !== $confirm_password) {
        $error = "Las contraseñas no coinciden.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET password = ?, password_reset_token = NULL, requires_password_setup = 0 WHERE id = ?");
        $stmt->execute([$hashed_password, $usuario['id']]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Contraseña - CoticeFacil</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .aurora-bg {
            background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 50%, #C7D2FE 100%);
            position: relative;
            overflow: hidden;
        }
        .aurora-blur {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(165, 180, 252, 0) 70%);
            filter: blur(80px);
            z-index: 0;
        }
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body class="aurora-bg min-h-screen flex items-center justify-center p-4">
    <div class="aurora-blur top-[-200px] left-[-200px]"></div>
    <div class="aurora-blur bottom-[-200px] right-[-200px]"></div>

    <div class="w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-black text-gray-900 tracking-tight">CoticeFacil</h1>
            <p class="text-gray-500 font-medium">Configuración de acceso seguro</p>
        </div>

        <div class="glass-panel rounded-[2.5rem] p-8 md:p-10">
            <?php if($success): ?>
                <div class="text-center space-y-6 py-4">
                    <div class="w-20 h-20 bg-green-100 text-green-600 rounded-3xl flex items-center justify-center mx-auto mb-4 animate-bounce">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900">¡Contraseña Lista!</h2>
                    <p class="text-gray-600">Tu cuenta ha sido configurada correctamente. Ahora puedes iniciar sesión con tu nueva contraseña.</p>
                    <a href="login.php" class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-blue-200 active:scale-95">
                        Ir al Login
                    </a>
                </div>
            <?php elseif($error && !$usuario): ?>
                <div class="text-center space-y-6 py-4">
                    <div class="w-20 h-20 bg-red-100 text-red-600 rounded-3xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900">Enlace Inválido</h2>
                    <p class="text-gray-600"><?php echo $error; ?></p>
                    <a href="login.php" class="block w-full bg-gray-900 text-white font-bold py-4 rounded-2xl transition-all hover:bg-gray-800">
                        Volver al Inicio
                    </a>
                </div>
            <?php else: ?>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Hola, <?php echo htmlspecialchars($usuario['nombre']); ?></h2>
                <p class="text-gray-500 mb-8">Por favor, define la contraseña que usarás para acceder al sistema.</p>

                <?php if($error): ?>
                    <div class="bg-red-50 text-red-600 p-4 rounded-2xl text-sm font-semibold mb-6 border border-red-100">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700 ml-1">Nueva Contraseña</label>
                        <input type="password" name="password" required minlength="6"
                               class="w-full px-6 py-4 bg-white/50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all outline-none"
                               placeholder="••••••••">
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-bold text-gray-700 ml-1">Confirmar Contraseña</label>
                        <input type="password" name="confirm_password" required minlength="6"
                               class="w-full px-6 py-4 bg-white/50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all outline-none"
                               placeholder="••••••••">
                    </div>

                    <button type="submit" 
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl transition-all shadow-lg shadow-blue-200 active:scale-95 flex items-center justify-center gap-2">
                        Configurar Contraseña
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </form>
            <?php endif; ?>
        </div>
        
        <p class="text-center text-gray-400 text-sm mt-8">
            &copy; <?php echo date('Y'); ?> CoticeFacil. Todos los derechos reservados.
        </p>
    </div>
</body>
</html>
