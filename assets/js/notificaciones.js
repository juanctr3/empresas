/**
 * Sistema de Notificaciones en Tiempo Real
 * Maneja el polling y actualización de notificaciones
 */

let notificationsPollInterval = null;
let lastNotificationCount = 0;

document.addEventListener('DOMContentLoaded', () => {
    inicializarNotificaciones();
});

function inicializarNotificaciones() {
    // Obtener contador inicial
    actualizarContadorNotificaciones();

    // Polling cada 15 segundos
    notificationsPollInterval = setInterval(actualizarContadorNotificaciones, 15000);

    // Event listeners
    const bellButton = document.getElementById('notifications-bell');
    if (bellButton) {
        bellButton.addEventListener('click', () => toggleNotificationsDropdown(false));
    }

    // Cerrar dropdown al hacer clic fuera (solo desktop)
    document.addEventListener('click', (e) => {
        const dropdown = document.getElementById('notifications-dropdown');
        const bell = document.getElementById('notifications-bell');

        if (dropdown && !dropdown.contains(e.target) && !bell.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
}

async function actualizarContadorNotificaciones() {
    try {
        const res = await fetch('api_notificaciones.php?accion=obtener_no_leidas');
        const data = await res.json();

        if (data.status === 'success') {
            const count = data.count;
            const badge = document.getElementById('notifications-badge');
            const badgeMobile = document.getElementById('notifications-badge-mobile');

            // Actualizar ambos badges
            [badge, badgeMobile].forEach(b => {
                if (b) {
                    if (count > 0) {
                        b.innerText = count > 99 ? '99+' : count;
                        b.classList.remove('hidden');

                        // Animación si hay nuevas notificaciones
                        if (count > lastNotificationCount) {
                            b.classList.add('animate-bounce');
                            setTimeout(() => b.classList.remove('animate-bounce'), 1000);
                        }
                    } else {
                        b.classList.add('hidden');
                    }
                }
            });

            lastNotificationCount = count;
        }
    } catch (e) {
        console.error('Error actualizando notificaciones:', e);
    }
}

async function toggleNotificationsDropdown(isMobile = null) {
    // Auto-detectar si es mobile si no se especifica
    if (isMobile === null) {
        isMobile = window.innerWidth < 768;
    }

    if (isMobile) {
        const dropdownMobile = document.getElementById('notifications-dropdown-mobile');
        if (dropdownMobile) {
            await cargarNotificacionesRecientes(true);
            dropdownMobile.classList.remove('hidden');
        }
    } else {
        const dropdown = document.getElementById('notifications-dropdown');
        if (!dropdown) return;

        if (dropdown.classList.contains('hidden')) {
            await cargarNotificacionesRecientes(false);
            dropdown.classList.remove('hidden');
        } else {
            dropdown.classList.add('hidden');
        }
    }
}

async function cargarNotificacionesRecientes(isMobile = false) {
    const containerId = isMobile ? 'notifications-list-mobile' : 'notifications-list';
    const container = document.getElementById(containerId);

    if (!container) return;

    container.innerHTML = '<div class="p-4 text-center text-gray-400"><div class="w-6 h-6 border-2 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto"></div></div>';

    try {
        const res = await fetch('api_notificaciones.php?accion=listar_recientes');
        const data = await res.json();

        if (data.status === 'success') {
            renderNotificaciones(data.data, containerId);
        } else {
            container.innerHTML = '<div class="p-4 text-center text-gray-400 text-sm">Error cargando notificaciones</div>';
        }
    } catch (e) {
        console.error('Error cargando notificaciones:', e);
        container.innerHTML = '<div class="p-4 text-center text-gray-400 text-sm">Error de conexión</div>';
    }
}

function renderNotificaciones(notificaciones, containerId = 'notifications-list') {
    const container = document.getElementById(containerId);

    if (!container) return;

    if (notificaciones.length === 0) {
        container.innerHTML = `
            <div class="p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-200 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <p class="text-gray-400 text-sm font-bold">No tienes notificaciones</p>
            </div>
        `;
        return;
    }

    container.innerHTML = notificaciones.map(n => {
        const iconClass = getIconForType(n.tipo);
        const colorClass = getColorForType(n.tipo);
        const timeAgo = formatTimeAgo(n.fecha_creacion);
        const unreadClass = n.leida == 0 ? 'bg-blue-50 border-l-4 border-l-blue-500' : 'bg-white';

        return `
            <div class="notification-item ${unreadClass} hover:bg-gray-50 transition-colors cursor-pointer" 
                 onclick="handleNotificationClick(${n.id}, '${escapeHtml(n.url || '')}')">
                <div class="flex items-start gap-3 p-4">
                    <div class="flex-shrink-0 w-10 h-10 ${colorClass} rounded-xl flex items-center justify-center">
                        ${iconClass}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-900 leading-tight">${escapeHtml(n.titulo)}</p>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">${escapeHtml(n.mensaje)}</p>
                        <p class="text-[10px] text-gray-400 mt-2 font-bold uppercase tracking-wider">${timeAgo}</p>
                    </div>
                    ${n.leida == 0 ? '<div class="w-2 h-2 bg-blue-600 rounded-full flex-shrink-0"></div>' : ''}
                </div>
            </div>
        `;
    }).join('');
}

function getIconForType(tipo) {
    const icons = {
        'cotizacion_aceptada': '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
        'cotizacion_rechazada': '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
        'cotizacion_vencimiento': '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
        'documento_compartido': '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>',
        'default': '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>'
    };

    return icons[tipo] || icons['default'];
}

function getColorForType(tipo) {
    const colors = {
        'cotizacion_aceptada': 'bg-green-100 text-green-600',
        'cotizacion_rechazada': 'bg-red-100 text-red-600',
        'cotizacion_vencimiento': 'bg-orange-100 text-orange-600',
        'documento_compartido': 'bg-blue-100 text-blue-600',
        'default': 'bg-gray-100 text-gray-600'
    };

    return colors[tipo] || colors['default'];
}

function formatTimeAgo(timestamp) {
    const now = new Date();
    const then = new Date(timestamp);
    const diff = Math.floor((now - then) / 1000); // segundos

    if (diff < 60) return 'Justo ah ora';
    if (diff < 3600) return `Hace ${Math.floor(diff / 60)} min`;
    if (diff < 86400) return `Hace ${Math.floor(diff / 3600)} h`;
    if (diff < 604800) return `Hace ${Math.floor(diff / 86400)} días`;

    return then.toLocaleDateString();
}

async function handleNotificationClick(id, url) {
    // Marcar como leída
    const formData = new FormData();
    formData.append('accion', 'marcar_leida');
    formData.append('id', id);

    try {
        await fetch('api_notificaciones.php', { method: 'POST', body: formData });

        // Actualizar contador
        await actualizarContadorNotificaciones();

        // Cerrar dropdowns
        const dropdown = document.getElementById('notifications-dropdown');
        const dropdownMobile = document.getElementById('notifications-dropdown-mobile');
        if (dropdown) dropdown.classList.add('hidden');
        if (dropdownMobile) dropdownMobile.classList.add('hidden');

        // Redirigir si hay URL
        if (url) {
            window.location.href = url;
        }
    } catch (e) {
        console.error('Error marcando notificación:', e);
    }
}

async function marcarTodasLeidas() {
    const formData = new FormData();
    formData.append('accion', 'marcar_todas_leidas');

    try {
        const res = await fetch('api_notificaciones.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.status === 'success') {
            await actualizarContadorNotificaciones();
            await cargarNotificacionesRecientes(window.innerWidth < 768);
        }
    } catch (e) {
        console.error('Error:', e);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
