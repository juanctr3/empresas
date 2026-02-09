<?php
session_start();
require_once '../db.php';

// Si ya es superadmin y está logueado, al dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true) {
    header("Location: ../saas_dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | CoticeFacil</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center p-6 relative overflow-hidden">
    
    <!-- Decorative Blobs -->
    <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] bg-indigo-600/20 rounded-full blur-[120px]"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[500px] h-[500px] bg-purple-600/20 rounded-full blur-[120px]"></div>

    <div class="max-w-md w-full relative z-10">
        <div class="text-center mb-10">
            <div class="inline-flex items-center gap-4 group">
                <div class="w-16 h-16 bg-gradient-to-tr from-indigo-600 to-purple-600 rounded-2xl flex items-center justify-center shadow-2xl shadow-indigo-500/20">
                    <span class="text-white font-black text-4xl">A</span>
                </div>
                <div class="text-left">
                    <h1 class="text-3xl font-black text-white tracking-tighter">Portal Admin</h1>
                    <p class="text-[10px] uppercase font-black tracking-[0.2em] text-indigo-400">Infraestructura Global</p>
                </div>
            </div>
        </div>

        <div class="glass rounded-[2.5rem] p-10 shadow-2xl shadow-black/50 overflow-hidden relative">
            <div id="login-step" class="space-y-8">
                <div class="space-y-2">
                    <h2 class="text-2xl font-black text-slate-800">Acceso Restringido</h2>
                    <p class="text-sm text-slate-500 font-medium">Solo personal autorizado de CoticeFacil.</p>
                </div>

                <form id="adminLoginForm" class="space-y-6">
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-indigo-600 uppercase tracking-widest ml-1">Email / Usuario</label>
                        <input type="text" id="identifier" required placeholder="admin@coticefacil.com" class="w-full px-6 py-5 rounded-2xl bg-white/50 border border-slate-100 focus:bg-white focus:ring-4 focus:ring-indigo-100 outline-none transition-all text-lg font-semibold placeholder:text-slate-300">
                    </div>

                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-indigo-600 uppercase tracking-widest ml-1">Contraseña</label>
                        <input type="password" id="password" required placeholder="••••••••" class="w-full px-6 py-5 rounded-2xl bg-white/50 border border-slate-100 focus:bg-white focus:ring-4 focus:ring-indigo-100 outline-none transition-all text-lg font-semibold placeholder:text-slate-300">
                    </div>

                    <button type="submit" id="btnSubmit" class="w-full bg-gradient-to-tr from-indigo-600 to-purple-600 hover:from-slate-900 hover:to-slate-900 text-white font-black py-5 rounded-2xl shadow-xl shadow-indigo-200 transition-all active:scale-95 uppercase tracking-widest text-sm">
                        Entrar al Panel
                    </button>
                </form>
            </div>

            <!-- Loader Overlay -->
            <div id="loader" class="hidden absolute inset-0 bg-white/60 backdrop-blur-md flex flex-col items-center justify-center z-50">
                <div class="w-16 h-16 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mb-4"></div>
                <p class="text-[10px] uppercase font-black tracking-widest text-indigo-600">Verificando credenciales...</p>
            </div>
        </div>

        <div id="msg" class="mt-6 text-center text-xs font-black uppercase tracking-widest hidden p-3 rounded-full"></div>

        <p class="mt-12 text-center text-slate-500 text-[10px] font-black uppercase tracking-widest">
            CoticeFacil Global Admin System &bull; <?php echo date('Y'); ?>
        </p>
    </div>

    <script>
        const form = document.getElementById('adminLoginForm');
        const loader = document.getElementById('loader');
        const msg = document.getElementById('msg');

        form.onsubmit = async (e) => {
            e.preventDefault();
            loader.classList.remove('hidden');
            msg.classList.add('hidden');

            const identifier = document.getElementById('identifier').value;
            const password = document.getElementById('password').value;

            try {
                const res = await fetch('../api_auth_admin.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({identifier, password})
                });
                
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch(e) {
                    console.error("No JSON response:", text);
                    showMsg('Error del servidor (no retornó JSON). Revisa el archivo api_auth_admin.php', 'red');
                    return;
                }
                
                if (data.status === 'success') {
                    showMsg('Acceso concedido. Redirigiendo...', 'indigo');
                    setTimeout(() => window.location.href = '../saas_dashboard.php', 1000);
                } else {
                    showMsg(data.message || 'Error desconocido', 'red');
                }
            } catch (err) {
                console.error(err);
                showMsg('Error de red o de ruta: ' + err.message, 'red');
            } finally {
                loader.classList.add('hidden');
            }
        };

        function showMsg(text, color) {
            msg.innerText = text;
            msg.classList.remove('hidden', 'bg-red-50', 'text-red-500', 'bg-indigo-50', 'text-indigo-500');
            if (color === 'red') {
                msg.classList.add('bg-red-50', 'text-red-500');
            } else {
                msg.classList.add('bg-indigo-50', 'text-indigo-500');
            }
        }
    </script>
</body>
</html>
