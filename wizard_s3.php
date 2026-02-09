<?php
require_once 'db.php';
require_once 'includes/auth_helper.php';
include 'includes/header.php';

$empresa_id = getEmpresaId();
if (!$empresa_id) header("Location: index.php");
?>

<div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-4">
    
    <div class="bg-white max-w-2xl w-full rounded-3xl shadow-2xl overflow-hidden animate-fade-in relative">
        <!-- Progress Bar -->
        <div class="h-2 bg-gray-100 w-full">
            <div id="progress-bar" class="h-full bg-blue-600 w-1/4 transition-all duration-500"></div>
        </div>

        <div class="p-8 md:p-12">
            
            <!-- STEP 1: Intro & Credentials -->
            <div id="step-1" class="step-content">
                <div class="w-16 h-16 bg-blue-50 rounded-2xl flex items-center justify-center mb-6 text-blue-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11.536 19.464a2.414 2.414 0 01-1.707.707H6.343l-.707-.707m12-3a3 3 0 00-3-3 3 3 0 00-3 3 3 3 0 003 3 3 3 0 003-3zm-9 14V19a2 2 0 01-2-2h1a2 2 0 01-2-2v-1z"></path></svg>
                </div>
                <h1 class="text-3xl font-black text-gray-900 mb-2">Conectar Amazon S3</h1>
                <p class="text-gray-500 mb-8">Vamos a configurar tu almacenamiento en la nube de Amazon S3 en unos sencillos pasos. Primero, necesitamos tus credenciales de acceso.</p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">AWS Access Key ID</label>
                        <input type="text" id="s3_key" placeholder="AKIA..." class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">AWS Secret Access Key</label>
                        <input type="password" id="s3_secret" placeholder="••••••••••••" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-blue-500 outline-none transition-all font-mono">
                    </div>
                </div>
                
                <div class="mt-8 flex justify-end">
                    <button onclick="nextStep(2)" class="bg-blue-600 text-white font-bold py-3 px-8 rounded-xl shadow-lg hover:bg-blue-700 transition-all flex items-center gap-2">
                        Siguiente <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                </div>
            </div>

            <!-- STEP 2: Region -->
            <div id="step-2" class="step-content hidden">
                <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center mb-6 text-indigo-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h2 class="text-3xl font-black text-gray-900 mb-2">Selecciona la Región</h2>
                <p class="text-gray-500 mb-8">Elige la región donde creaste tu Bucket. Por lo general es 'us-east-1' (N. Virginia).</p>

                <div class="space-y-4">
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Región AWS</label>
                    <select id="s3_region" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                        <option value="us-east-1">US East (N. Virginia) us-east-1</option>
                        <option value="us-east-2">US East (Ohio) us-east-2</option>
                        <option value="us-west-1">US West (N. California) us-west-1</option>
                        <option value="us-west-2">US West (Oregon) us-west-2</option>
                        <option value="eu-west-1">Europe (Ireland) eu-west-1</option>
                        <option value="eu-central-1">Europe (Frankfurt) eu-central-1</option>
                        <option value="sa-east-1">South America (São Paulo) sa-east-1</option>
                    </select>
                </div>

                <div class="mt-8 flex justify-between">
                    <button onclick="prevStep(1)" class="text-gray-500 font-bold py-3 px-6 hover:bg-gray-50 rounded-xl transition-all">Atrás</button>
                    <button onclick="nextStep(3)" class="bg-indigo-600 text-white font-bold py-3 px-8 rounded-xl shadow-lg hover:bg-indigo-700 transition-all flex items-center gap-2">
                        Siguiente <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                </div>
            </div>

            <!-- STEP 3: Bucket -->
            <div id="step-3" class="step-content hidden">
                 <div class="w-16 h-16 bg-amber-50 rounded-2xl flex items-center justify-center mb-6 text-amber-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                </div>
                <h2 class="text-3xl font-black text-gray-900 mb-2">Nombre del Bucket</h2>
                <p class="text-gray-500 mb-8">Escribe el nombre exacto de tu S3 Bucket.</p>

                <div class="space-y-4">
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Bucket Name</label>
                    <input type="text" id="s3_bucket" placeholder="ej: mi-empresa-archivos" class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-amber-500 outline-none transition-all">
                </div>

                <div class="mt-8 flex justify-between">
                    <button onclick="prevStep(2)" class="text-gray-500 font-bold py-3 px-6 hover:bg-gray-50 rounded-xl transition-all">Atrás</button>
                    <button onclick="testAndFinish()" id="btn-finish" class="bg-amber-500 text-white font-bold py-3 px-8 rounded-xl shadow-lg hover:bg-amber-600 transition-all flex items-center gap-2">
                        Verificar y Conectar
                    </button>
                </div>
            </div>

            <!-- STEP 4: Success -->
            <div id="step-4" class="step-content hidden text-center">
                 <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mb-6 mx-auto text-green-600 animate-bounce">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h2 class="text-3xl font-black text-gray-900 mb-2">¡Conexión Exitosa!</h2>
                <p class="text-gray-500 mb-8">Tu configuración de Amazon S3 ha sido guardada y activada. Tus archivos ahora se guardarán en la nube.</p>

                <button onclick="window.location.href='configuracion.php'" class="bg-gray-900 text-white font-bold py-3 px-8 rounded-xl shadow-lg hover:bg-black transition-all">
                    Volver a Configuración
                </button>
            </div>

        </div>
    </div>
</div>

<script>
function nextStep(step) {
    if(step === 2) {
        if(!document.getElementById('s3_key').value || !document.getElementById('s3_secret').value) return alert("Ingresa las llaves primero.");
    }
    
    document.querySelectorAll('.step-content').forEach(el => el.classList.add('hidden'));
    document.getElementById(`step-${step}`).classList.remove('hidden');
    document.getElementById('progress-bar').style.width = (step * 25) + '%';
}

function prevStep(step) {
    document.querySelectorAll('.step-content').forEach(el => el.classList.add('hidden'));
    document.getElementById(`step-${step}`).classList.remove('hidden');
    document.getElementById('progress-bar').style.width = (step * 25) + '%';
}

async function testAndFinish() {
    const btn = document.getElementById('btn-finish');
    const originalText = btn.innerHTML;
    
    const key = document.getElementById('s3_key').value;
    const secret = document.getElementById('s3_secret').value;
    const region = document.getElementById('s3_region').value;
    const bucket = document.getElementById('s3_bucket').value;

    if(!bucket) return alert("Escribe el nombre del bucket");

    btn.disabled = true;
    btn.innerHTML = 'Probando conexión...';

    // 1. Test Connection
    try {
        const formData = new FormData();
        formData.append('action', 'test_s3');
        formData.append('key', key);
        formData.append('secret', secret);
        formData.append('region', region);
        formData.append('bucket', bucket);

        const res = await fetch('api_test_connection.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.status === 'success') {
            // 2. Save Config
            const saveForm = new FormData();
            saveForm.append('action', 'save_s3_config');
            saveForm.append('key', key);
            saveForm.append('secret', secret);
            saveForm.append('region', region);
            saveForm.append('bucket', bucket);
            
            const saveRes = await fetch('api_s3_config.php', { method: 'POST', body: saveForm });
            const saveData = await saveRes.json();
            
            if(saveData.status === 'success') {
                nextStep(4);
            } else {
                alert("Conexión OK, pero error al guardar: " + saveData.message);
            }
        } else {
            alert("Falló la conexión: " + data.message);
        }
    } catch(e) {
        alert("Error de red o servidor.");
    }

    btn.disabled = false;
    btn.innerHTML = originalText;
}
</script>

<style>
.animate-fade-in { animation: fadeIn 0.5s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<?php include 'includes/footer.php'; ?>
