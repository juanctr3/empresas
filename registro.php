<?php
// registro.php - Wizard de Registro Premium
require_once 'db.php';
$planes = $pdo->query("SELECT * FROM planes ORDER BY precio ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro | COTICEFACIL SaaS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .step-transition {
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .hidden-step {
            opacity: 0;
            transform: translateX(20px);
            pointer-events: none;
            position: absolute;
        }
        .active-step {
            opacity: 1;
            transform: translateX(0);
            position: relative;
        }
        .blob {
            position: fixed;
            width: 500px;
            height: 500px;
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            filter: blur(80px);
            border-radius: 50%;
            z-index: -1;
            opacity: 0.15;
            animation: move 20s infinite alternate;
        }
        @keyframes move {
            from { transform: translate(-10%, -10%); }
            to { transform: translate(100%, 80%); }
        }
        
        .plan-card.selected {
            border-color: #6366f1;
            background: rgba(99, 102, 241, 0.05);
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.1);
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4 overflow-x-hidden">

    <div class="blob"></div>
    <div class="blob" style="animation-delay: -10s; background: #3b82f6; right: -10%; top: -10%;"></div>

    <div class="w-full max-w-xl">
        <!-- Logo/Header -->
        <div class="text-center mb-10">
            <h1 class="text-4xl font-extrabold text-slate-900 tracking-tight mb-2">Comienza ahora</h1>
            <p class="text-slate-500 font-medium">Crea tu cuenta SaaS en menos de 2 minutos.</p>
        </div>

        <!-- Wizard Container -->
        <form id="registroForm" enctype="multipart/form-data" class="glass rounded-[2.5rem] p-10 shadow-2xl shadow-indigo-100 relative overflow-hidden">
            
            <!-- Progress Bar -->
            <div class="absolute top-0 left-0 w-full h-1.5 bg-slate-100/50">
                <div id="progressBar" class="h-full bg-indigo-600 step-transition" style="width: 25%;"></div>
            </div>

            <!-- Step 1: Empresa -->
            <div id="step1" class="active-step step-transition">
                <div class="mb-8">
                    <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest bg-indigo-50 px-3 py-1 rounded-full">Paso 1 de 4</span>
                    <h2 class="text-2xl font-extrabold text-slate-900 mt-4 leading-tight">Configura tu perfil comercial</h2>
                </div>

                <div class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Nombre de la Empresa</label>
                        <input type="text" name="nombre_empresa" required placeholder="Ej: Tech Solutions S.A.S" class="w-full px-5 py-4 rounded-2xl border border-slate-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all placeholder:text-slate-300">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">NIT / Identificación</label>
                        <input type="text" name="nit" placeholder="Ej: 900.123.456-1" class="w-full px-5 py-4 rounded-2xl border border-slate-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all placeholder:text-slate-300">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Logotipo (Opcional)</label>
                        <div class="flex items-center gap-4 p-4 border-2 border-dashed border-slate-100 rounded-2xl hover:border-indigo-200 transition-colors cursor-pointer group relative">
                            <input type="file" name="logo" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                            <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400 group-hover:text-indigo-500 step-transition">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2-2v12a2 2 0 002 2z"></path></svg>
                            </div>
                            <span class="text-sm font-bold text-slate-400 group-hover:text-slate-600">Haz clic para subir imagen</span>
                        </div>
                    </div>
                </div>

                <div class="mt-10">
                    <button type="button" onclick="nextStep(2)" class="w-full py-5 bg-indigo-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-900 transition-all shadow-xl shadow-indigo-100 active:scale-95">
                        Continuar
                    </button>
                    <p class="text-center text-[10px] text-slate-400 font-bold mt-4 uppercase tracking-tighter">Tu marca es el primer paso</p>
                </div>
            </div>

            <!-- Step 2: Planes -->
            <div id="step2" class="hidden-step step-transition">
                <div class="mb-8">
                    <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest bg-indigo-50 px-3 py-1 rounded-full">Paso 2 de 4</span>
                    <h2 class="text-2xl font-extrabold text-slate-900 mt-4 leading-tight">Elige tu plan ideal</h2>
                </div>

                <div class="grid grid-cols-1 gap-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                    <?php foreach ($planes as $idx => $p): ?>
                        <label class="plan-card relative flex items-center gap-4 p-5 rounded-3xl border border-slate-100 cursor-pointer hover:border-indigo-200 transition-all group <?php echo $idx === 0 ? 'selected' : ''; ?>">
                            <input type="radio" name="plan_id" value="<?php echo $p['id']; ?>" class="hidden" <?php echo $idx === 0 ? 'checked' : ''; ?> onchange="updatePlanSelection(this)">
                            <div class="w-5 h-5 rounded-full border-2 border-slate-200 group-hover:border-indigo-400 flex items-center justify-center transition-all bg-white check-circle">
                                <div class="w-2.5 h-2.5 rounded-full bg-indigo-600 opacity-0 transition-all inner-dot"></div>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-black text-slate-900 text-sm"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest"><?php echo $p['limite_cotizaciones'] ?: '∞'; ?> Cotizaciones • <?php echo $p['limite_usuarios'] ?: '∞'; ?> Usuarios</p>
                            </div>
                            <div class="text-right">
                                <span class="text-lg font-black text-indigo-600">$<?php echo number_format($p['precio'], 0); ?></span>
                                <p class="text-[9px] text-slate-400 font-bold">/mes</p>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="mt-10 flex gap-4">
                    <button type="button" onclick="nextStep(1)" class="flex-1 py-5 bg-slate-50 text-slate-400 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-100 transition-all active:scale-95 text-center">
                        Atrás
                    </button>
                    <button type="button" onclick="nextStep(3)" class="flex-[2] py-5 bg-indigo-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-900 transition-all shadow-xl shadow-indigo-100 active:scale-95">
                        Continuar
                    </button>
                </div>
            </div>

            <!-- Step 3: Administrador -->
            <div id="step3" class="hidden-step step-transition">
                <div class="mb-8">
                    <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest bg-indigo-50 px-3 py-1 rounded-full">Paso 3 de 4</span>
                    <h2 class="text-2xl font-extrabold text-slate-900 mt-4 leading-tight">Tu acceso principal</h2>
                </div>

                <div class="space-y-6">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Nombre Completo</label>
                        <input type="text" name="admin_nombre" required placeholder="Ej: Juan Pérez" class="w-full px-5 py-4 rounded-2xl border border-slate-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all placeholder:text-slate-300">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Correo Electrónico</label>
                        <input type="email" name="admin_email" required placeholder="juan@ejemplo.com" class="w-full px-5 py-4 rounded-2xl border border-slate-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all placeholder:text-slate-300">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Crear Contraseña</label>
                        <input type="password" name="admin_pass" required placeholder="••••••••" class="w-full px-5 py-4 rounded-2xl border border-slate-100 focus:ring-4 focus:ring-indigo-50 outline-none transition-all placeholder:text-slate-300">
                    </div>
                </div>

                <div class="mt-10 flex gap-4">
                    <button type="button" onclick="nextStep(2)" class="flex-1 py-5 bg-slate-50 text-slate-400 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-100 transition-all active:scale-95 text-center">
                        Atrás
                    </button>
                    <button type="button" onclick="nextStep(4)" class="flex-[2] py-5 bg-indigo-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-900 transition-all shadow-xl shadow-indigo-100 active:scale-95">
                        Confirmar
                    </button>
                </div>
            </div>

            <!-- Step 4: Finalizar -->
            <div id="step4" class="hidden-step step-transition">
                <div class="mb-10 text-center">
                    <div class="w-20 h-20 bg-emerald-50 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest bg-indigo-50 px-3 py-1 rounded-full">Finalizar</span>
                    <h2 class="text-3xl font-extrabold text-slate-900 mt-4 leading-tight">¡Todo listo!</h2>
                    <p class="text-slate-500 mt-4 leading-relaxed px-4">Al hacer clic en finalizar, configuraremos tu panel de control y podrás enviar tu primera cotización en segundos.</p>
                </div>

                <div class="bg-indigo-50/50 rounded-3xl p-6 mb-8 border border-indigo-100/50">
                    <p class="text-[9px] font-black text-indigo-400 uppercase tracking-widest mb-2">Infraestructura Sugerida</p>
                    <p class="text-xs text-indigo-900 font-bold flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        Email SMTP Automático (Amazon SES) Activado
                    </p>
                </div>

                <div class="mt-10 flex gap-4">
                    <button type="button" onclick="nextStep(3)" id="btnBackFinal" class="flex-1 py-5 bg-slate-50 text-slate-400 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-100 transition-all active:scale-95 text-center">
                        Atrás
                    </button>
                    <button type="submit" id="btnSubmit" class="flex-[3] py-5 bg-indigo-600 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-slate-900 transition-all shadow-xl shadow-indigo-100 active:scale-95">
                        Crear mi cuenta
                    </button>
                </div>
            </div>

        </form>

        <p class="text-center text-slate-400 text-sm mt-10">
            ¿Ya tienes cuenta? <a href="index.php" class="text-indigo-600 font-bold hover:underline">Inicia sesión</a>
        </p>
    </div>

    <!-- Toast/Notification -->
    <div id="toast" class="fixed bottom-10 left-1/2 -translate-x-1/2 px-8 py-4 rounded-2xl bg-slate-900 text-white text-xs font-bold uppercase tracking-widest shadow-2xl translate-y-20 opacity-0 step-transition z-[60]">
        Cargando...
    </div>

    <script>
        let currentStep = 1;

        function updatePlanSelection(radio) {
            // Remover selección previa
            document.querySelectorAll('.plan-card').forEach(c => {
                c.classList.remove('selected');
                c.querySelector('.inner-dot').classList.add('opacity-0');
            });
            
            // Aplicar nueva selección
            const card = radio.closest('.plan-card');
            card.classList.add('selected');
            card.querySelector('.inner-dot').classList.remove('opacity-0');
        }

        // Inicializar punto si hay uno seleccionado
        document.addEventListener('DOMContentLoaded', () => {
            const selected = document.querySelector('input[name="plan_id"]:checked');
            if(selected) updatePlanSelection(selected);
        });

        function nextStep(step) {
            // Validar antes de pasar al siguiente
            if(step > currentStep) {
                const currentContainer = document.getElementById('step' + currentStep);
                const inputs = currentContainer.querySelectorAll('input[required]');
                for(let i=0; i < inputs.length; i++) {
                    if(!inputs[i].value) {
                        showToast("Por favor completa los campos obligatorios");
                        inputs[i].classList.add('border-red-400');
                        return;
                    }
                    inputs[i].classList.remove('border-red-400');
                }
            }

            // Transición
            document.getElementById('step' + currentStep).classList.replace('active-step', 'hidden-step');
            document.getElementById('step' + step).classList.replace('hidden-step', 'active-step');
            
            currentStep = step;

            // Actualizar Progreso (4 pasos)
            const progress = (step / 4) * 100;
            document.getElementById('progressBar').style.width = progress + '%';
        }

        function showToast(msg, duration = 3000) {
            const t = document.getElementById('toast');
            t.innerText = msg;
            t.classList.remove('translate-y-20', 'opacity-0');
            setTimeout(() => {
                t.classList.add('translate-y-20', 'opacity-0');
            }, duration);
        }

        const form = document.getElementById('registroForm');
        form.onsubmit = async (e) => {
            e.preventDefault();
            
            const btn = document.getElementById('btnSubmit');
            const btnBack = document.getElementById('btnBackFinal');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = 'Configurando plataforma...';
            btn.disabled = true;
            btnBack.style.display = 'none';

            const formData = new FormData(form);
            
            try {
                const res = await fetch('api_public_register.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if(data.status === 'success') {
                    showToast("¡Plataforma lista! Accediendo...");
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1500);
                } else {
                    showToast(data.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    btnBack.style.display = 'block';
                }
            } catch (err) {
                showToast("Error de conexión");
                btn.innerHTML = originalText;
                btn.disabled = false;
                btnBack.style.display = 'block';
            }
        };
    </script>
</body>
</html>
