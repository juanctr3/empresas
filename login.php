<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login | CoticeFacil</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 relative overflow-hidden">
    <div class="aurora-bg"></div>

    <div class="max-w-md w-full fade-in-up relative z-10">
        <!-- Logo Section -->
        <div class="text-center mb-10">
            <div class="inline-flex items-center gap-4 group">
                <div class="w-16 h-16 bg-indigo-600 rounded-[2rem] flex items-center justify-center shadow-2xl shadow-indigo-200 transform group-hover:rotate-12 transition-transform duration-500">
                    <span class="text-white font-black text-4xl">C</span>
                </div>
                <div class="text-left">
                    <h1 class="text-4xl font-black bg-clip-text text-transparent bg-gradient-to-r from-indigo-600 to-pink-500 tracking-tighter">CoticeFacil</h1>
                    <p class="text-[10px] uppercase font-black tracking-[0.2em] text-indigo-400/80">Business Intelligence</p>
                </div>
            </div>
        </div>

        <!-- Glass Auth Container -->
        <div class="glass-card p-10 relative overflow-hidden">
            <div id="step-1" class="space-y-8">
                <div class="space-y-2">
                    <h2 class="text-3xl font-black text-gray-900 tracking-tight">Bienvenido ✨</h2>
                    <p class="text-sm text-gray-500 font-medium">Gestiona tus cotizaciones con elegancia y rapidez.</p>
                </div>

                <div class="space-y-6">
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-indigo-500 uppercase tracking-widest ml-1">Identificación</label>
                        <div class="flex gap-2">
                            <select id="country_code" class="w-24 px-3 py-5 rounded-2xl bg-white/50 border border-white focus:bg-white focus:ring-4 focus:ring-indigo-100 outline-none transition-all text-sm font-bold shadow-sm">
                                <option value="57">🇨🇴 +57</option>
                                <option value="1">🇺🇸 +1</option>
                                <option value="34">🇪🇸 +34</option>
                                <option value="52">🇲🇽 +52</option>
                                <option value="54">🇦🇷 +54</option>
                                <option value="56">🇨🇱 +56</option>
                                <option value="51">🇵🇪 +51</option>
                                <option value="58">🇻🇪 +58</option>
                                <option value="593">🇪🇨 +593</option>
                                <option value="507">🇵🇦 +507</option>
                            </select>
                            <input type="text" id="identifier" placeholder="Email o Celular" class="flex-1 px-6 py-5 rounded-2xl bg-white/50 border border-white focus:bg-white focus:ring-4 focus:ring-indigo-100 outline-none transition-all text-lg font-semibold placeholder:text-gray-300 shadow-sm">
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-black text-indigo-500 uppercase tracking-widest ml-1">Contraseña</label>
                            <input type="password" id="password" placeholder="••••••••" class="w-full px-6 py-5 rounded-2xl bg-white/50 border border-white focus:bg-white focus:ring-4 focus:ring-indigo-100 outline-none transition-all text-lg font-semibold placeholder:text-gray-300 shadow-sm">
                        </div>
                        <div class="flex justify-end pr-1">
                            <button onclick="showStep(3)" class="text-[10px] font-black text-indigo-400 uppercase tracking-widest hover:text-indigo-600 transition-colors">¿Olvidaste tu contraseña?</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <button onclick="requestOTP('email')" class="flex flex-col items-center justify-center p-6 rounded-3xl bg-white/40 border border-white/60 hover:bg-white hover:border-indigo-200 hover:shadow-xl hover:shadow-indigo-50/50 transition-all group">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-500 mb-3 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <span class="text-xs font-black text-gray-600 uppercase tracking-wider">Email</span>
                        </button>
                        <button onclick="requestOTP('whatsapp')" class="flex flex-col items-center justify-center p-6 rounded-3xl bg-white/40 border border-white/60 hover:bg-white hover:border-green-200 hover:shadow-xl hover:shadow-green-50/50 transition-all group">
                            <div class="w-12 h-12 rounded-2xl bg-green-50 flex items-center justify-center text-green-500 mb-3 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.438 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.414 0 .004 5.412 0 12.049c0 2.123.554 4.197 1.607 6.037L0 24l6.105-1.602a11.834 11.834 0 005.937 1.61h.005c6.637 0 12.046-5.411 12.05-12.049 0-3.209-1.249-6.225-3.518-8.495z"/></svg>
                            </div>
                            <span class="text-xs font-black text-gray-600 uppercase tracking-wider">WhatsApp</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Paso 2: Verificación -->
            <div id="step-2" class="hidden space-y-8">
                <div class="space-y-2">
                    <h2 class="text-3xl font-black text-gray-900 tracking-tight">Verificar 🔑</h2>
                    <p class="text-sm text-gray-500 font-medium">Ingresa el código mágico enviado.</p>
                </div>

                <div class="space-y-8">
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-indigo-500 uppercase tracking-widest ml-1">Código OTP</label>
                        <input type="text" id="otp_code" maxlength="6" placeholder="000 000" class="w-full text-center tracking-[0.5em] px-6 py-6 rounded-2xl bg-white/50 border border-white outline-none text-4xl font-black focus:ring-4 focus:ring-indigo-100 transition-all">
                    </div>

                    <div class="space-y-4">
                        <button onclick="verifyOTP()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-5 rounded-2xl shadow-2xl shadow-indigo-200 transition-all active:scale-95 uppercase tracking-widest text-sm">
                            Acceder Ahora
                        </button>

                        <button onclick="backToStep1()" class="w-full text-gray-400 text-[10px] font-black uppercase tracking-widest hover:text-indigo-600 transition-colors">
                            Cambiar Identificación
                        </button>
                    </div>
                </div>
            </div>

            <!-- Paso 3: Recuperación -->
            <div id="step-3" class="hidden space-y-8">
                <div class="space-y-2">
                    <h2 class="text-3xl font-black text-gray-900 tracking-tight">Recuperar 🛡️</h2>
                    <p class="text-sm text-gray-500 font-medium">Te enviaremos un enlace para restablecer tu acceso.</p>
                </div>

                <div class="space-y-6">
                    <div class="space-y-2">
                        <label class="block text-[10px] font-black text-indigo-500 uppercase tracking-widest ml-1">Identificación</label>
                        <div class="flex gap-2">
                            <select id="recover_country_code" class="w-24 px-3 py-5 rounded-2xl bg-white/50 border border-white focus:bg-white focus:ring-4 focus:ring-indigo-100 outline-none transition-all text-sm font-bold shadow-sm">
                                <option value="57">🇨🇴 +57</option>
                                <option value="1">🇺🇸 +1</option>
                                <option value="34">🇪🇸 +34</option>
                                <option value="52">🇲🇽 +52</option>
                            </select>
                            <input type="text" id="recover_identifier" placeholder="Email o Celular" class="flex-1 px-6 py-5 rounded-2xl bg-white/50 border border-white focus:bg-white focus:ring-4 focus:ring-indigo-100 outline-none transition-all text-lg font-semibold placeholder:text-gray-300 shadow-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <button onclick="requestRecovery('email')" class="flex flex-col items-center justify-center p-6 rounded-3xl bg-white/40 border border-white/60 hover:bg-white hover:border-indigo-200 transition-all group">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-500 mb-3 group-hover:scale-110 transition-transform">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                            </div>
                            <span class="text-[10px] font-black text-gray-600 uppercase tracking-widest">Vía Email</span>
                        </button>
                        <button onclick="requestRecovery('whatsapp')" class="flex flex-col items-center justify-center p-6 rounded-3xl bg-white/40 border border-white/60 hover:bg-white hover:border-green-200 transition-all group">
                            <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center text-green-500 mb-3 group-hover:scale-110 transition-transform">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.438 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.414 0 .004 5.412 0 12.049c0 2.123.554 4.197 1.607 6.037L0 24l6.105-1.602a11.834 11.834 0 005.937 1.61h.005c6.637 0 12.046-5.411 12.05-12.049 0-3.209-1.249-6.225-3.518-8.495z"/></svg>
                            </div>
                            <span class="text-[10px] font-black text-gray-600 uppercase tracking-widest">WhatsApp</span>
                        </button>
                    </div>

                    <button onclick="showStep(1)" class="w-full text-gray-400 text-[10px] font-black uppercase tracking-widest hover:text-indigo-600 transition-colors">
                        Volver al Login
                    </button>
                </div>
            </div>

            <!-- Loader Overlay -->
            <div id="loader" class="hidden absolute inset-0 bg-white/60 backdrop-blur-md flex flex-col items-center justify-center z-50">
                <div class="w-16 h-16 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin mb-4 shadow-lg shadow-indigo-100"></div>
                <p class="text-[10px] uppercase font-black tracking-widest text-indigo-600">Procesando...</p>
            </div>
        </div>

        <div id="msg-container" class="mt-6 text-center transform transition-all">
             <div id="msg" class="inline-block px-6 py-2 rounded-full text-[10px] font-black uppercase tracking-widest hidden"></div>
        </div>

        <div class="mt-8 text-center space-y-4">
            <p class="text-gray-400 text-[10px] font-black uppercase tracking-widest">
                ¿No tienes una cuenta? <a href="registro.php" class="text-indigo-600 hover:underline">Regístrate ahora</a>
            </p>
            <p class="text-gray-400 text-[10px] font-black uppercase tracking-widest">
                &copy; <?php echo date('Y'); ?> CoticeFacil. Design with ❤️
            </p>
        </div>
    </div>

    <script>
        let currentUserId = 0;

        async function requestOTP(type) {
            const identifier = document.getElementById('identifier').value;
            const password = document.getElementById('password').value;
            const country_code = document.getElementById('country_code').value;
            const msg = document.getElementById('msg');
            const loader = document.getElementById('loader');

            if (!identifier || !password) {
                showMsg('Ingresa identificación y contraseña', 'red');
                return;
            }

            loader.classList.remove('hidden');
            msg.parentNode.classList.add('hidden');

            try {
                const res = await fetch('api_auth.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'request_otp', identifier, password, country_code, type})
                });
                
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch(e) {
                    showMsg('Error de respuesta', 'red');
                    return;
                }

                if (data.status === 'success') {
                    currentUserId = data.usuario_id;
                    document.getElementById('step-1').classList.add('fade-out');
                    setTimeout(() => {
                        document.getElementById('step-1').classList.add('hidden');
                        document.getElementById('step-2').classList.remove('hidden');
                        document.getElementById('step-2').classList.add('fade-in-up');
                    }, 300);
                    showMsg(data.message, 'indigo');
                } else if (data.status === 'setup_required') {
                    showMsg(data.message, 'blue');
                } else {
                    showMsg(data.message, 'red');
                }
            } catch (err) {
                showMsg('Error de conexión', 'red');
            } finally {
                loader.classList.add('hidden');
            }
        }

        async function verifyOTP() {
            const codigo = document.getElementById('otp_code').value;
            const loader = document.getElementById('loader');

            if (codigo.length < 6) {
                showMsg('Código incompleto', 'red');
                return;
            }

            loader.classList.remove('hidden');

            try {
                const res = await fetch('api_auth.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'verify_otp', usuario_id: currentUserId, codigo})
                });
                const data = await res.json();

                if (data.status === 'success') {
                    showMsg('¡Bienvenido!', 'indigo');
                    setTimeout(() => window.location.href = 'index.php', 1000);
                } else {
                    showMsg(data.message, 'red');
                }
            } catch (err) {
                showMsg('Error de conexión', 'red');
            } finally {
                loader.classList.add('hidden');
            }
        }

        function showMsg(text, color) {
            const msg = document.getElementById('msg');
            const container = document.getElementById('msg-container');
            msg.innerHTML = text;
            msg.classList.remove('hidden');
            container.classList.remove('hidden');
            
            if(color === 'red') {
                msg.className = "inline-block px-6 py-2 rounded-full text-[10px] font-black uppercase tracking-widest bg-red-50 text-red-500 border border-red-100";
            } else if(color === 'blue') {
                msg.className = "inline-block px-6 py-2 rounded-full text-[10px] font-black uppercase tracking-widest bg-blue-50 text-blue-500 border border-blue-100";
            } else {
                msg.className = "inline-block px-6 py-2 rounded-full text-[10px] font-black uppercase tracking-widest bg-indigo-50 text-indigo-500 border border-indigo-100";
            }
        }

        function backToStep1() {
            showStep(1);
        }

        function showStep(step) {
            const steps = [1, 2, 3];
            const currentStep = steps.find(s => !document.getElementById('step-' + s).classList.contains('hidden'));
            
            if (currentStep === step) return;

            document.getElementById('step-' + currentStep).classList.add('fade-out');
            setTimeout(() => {
                steps.forEach(s => {
                    document.getElementById('step-' + s).classList.add('hidden');
                    document.getElementById('step-' + s).classList.remove('fade-out', 'fade-in-up');
                });
                document.getElementById('step-' + step).classList.remove('hidden');
                document.getElementById('step-' + step).classList.add('fade-in-up');
                
                if (step === 1) {
                    document.getElementById('msg-container').classList.add('hidden');
                }
            }, 300);
        }

        async function requestRecovery(type) {
            const identifier = document.getElementById('recover_identifier').value;
            const country_code = document.getElementById('recover_country_code').value;
            const loader = document.getElementById('loader');

            if (!identifier) {
                showMsg('Ingresa tu identificación', 'red');
                return;
            }

            loader.classList.remove('hidden');

            try {
                const res = await fetch('api_auth.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'request_recovery', identifier, country_code, type})
                });
                
                const text = await res.text();
                console.log('Recovery raw response:', text);
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch(e) {
                    console.error('JSON Parse error:', e, text);
                    showMsg('Error de respuesta del servidor', 'red');
                    return;
                }

                if (data.status === 'success') {
                    showMsg(data.message, 'indigo');
                    setTimeout(() => showStep(1), 3000);
                } else {
                    showMsg(data.message, 'red');
                }
            } catch (err) {
                showMsg('Error de conexión', 'red');
            } finally {
                loader.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
