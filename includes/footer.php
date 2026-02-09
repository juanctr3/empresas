
    </main>

    <!-- Modal Global de Envío WhatsApp Premium -->
    <div id="modalWS" class="fixed inset-0 z-[200] hidden flex items-center justify-center px-4">
        <div class="fixed inset-0 bg-gray-900/80 backdrop-blur-md" onclick="if(window.ws_finished) cerrarModalWS()"></div>
        <div class="relative bg-white rounded-[2.5rem] w-full max-w-sm overflow-hidden shadow-2xl transform transition-all animate-fade-in-up">
            <div class="p-10 text-center space-y-6">
                <!-- Icono Animado -->
                <div id="ws-icon-container" class="w-24 h-24 bg-green-50 rounded-full mx-auto flex items-center justify-center mb-4 relative">
                    <div id="ws-pulse" class="absolute inset-0 bg-green-100 rounded-full animate-ping opacity-75"></div>
                    <svg id="ws-svg" class="w-12 h-12 text-green-500 relative z-10" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.438 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                </div>

                <div class="space-y-2">
                    <h3 id="ws-title" class="text-2xl font-black text-gray-900">Enviando Propuesta</h3>
                    <p id="ws-status" class="text-sm text-gray-500 font-medium">Personalizando cada detalle...</p>
                </div>

                <!-- Barra de Progreso Custom -->
                <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                    <div id="ws-progress" class="bg-green-500 h-full w-0 transition-all duration-700 ease-out"></div>
                </div>

                <div id="ws-footer" class="hidden">
                    <button onclick="cerrarModalWS()" class="w-full bg-gray-900 text-white font-black py-4 rounded-2xl hover:bg-gray-800 transition-all">
                        ENTENDIDO
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.ws_finished = false;

        async function enviarWhatsApp(event, form) {
            if(event) event.preventDefault();
            
            const modal = document.getElementById('modalWS');
            const progress = document.getElementById('ws-progress');
            const status = document.getElementById('ws-status');
            const title = document.getElementById('ws-title');
            const icon = document.getElementById('ws-icon-container');
            const pulse = document.getElementById('ws-pulse');
            const footer = document.getElementById('ws-footer');

            // Reset Modal
            window.ws_finished = false;
            modal.classList.remove('hidden');
            progress.style.width = '0%';
            status.textContent = 'Personalizando cada detalle...';
            title.textContent = 'Enviando Propuesta';
            icon.classList.remove('bg-red-50', 'bg-blue-50');
            icon.classList.add('bg-green-50');
            pulse.classList.remove('hidden');
            footer.classList.add('hidden');

            const formData = new FormData(form);
            formData.append('ajax', '1');

            try {
                // Simulación de pasos para la animación
                setTimeout(() => { progress.style.width = '30%'; status.textContent = 'Generando link público...'; }, 800);
                setTimeout(() => { progress.style.width = '60%'; status.textContent = 'Contactando servidor WhatsApp...'; }, 1600);

                const response = await fetch('api_whatsapp.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                setTimeout(() => {
                    progress.style.width = '100%';
                    pulse.classList.add('hidden');
                    window.ws_finished = true;
                    
                    if(data.status === 'success') {
                        status.textContent = '¡Enviado con éxito! 🚀';
                        title.textContent = 'Propuesta Entregada';
                        footer.classList.remove('hidden');
                    } else {
                        status.textContent = 'Ups! ' + data.message;
                        title.textContent = 'Error al enviar';
                        icon.classList.replace('bg-green-50', 'bg-red-50');
                        document.getElementById('ws-svg').classList.add('text-red-500');
                        footer.classList.remove('hidden');
                    }
                }, 2500);

            } catch (error) {
                status.textContent = 'Error de conexión fatal.';
                footer.classList.remove('hidden');
            }
        }

        function cerrarModalWS() {
            document.getElementById('modalWS').classList.add('hidden');
            if(window.location.search.includes('msg=enviado') || window.ws_finished) {
                // Opcional: recargar si el estado cambió
                // window.location.reload();
            }
        }
    </script>

    <footer class="md:ml-[280px] py-10 border-t border-gray-100/50 text-center">
        <div class="px-6 text-gray-400 text-xs font-semibold tracking-widest uppercase">
            &copy; <?php echo date('Y'); ?> CoticeFacil. <span class="hidden sm:inline">Todos los derechos reservados.</span>
        </div>
    </footer>

    <?php include 'includes/chat_widget.php'; ?>
</body>
</html>
