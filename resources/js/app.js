import './bootstrap';

// Animación de salida al navegar
document.addEventListener('click', (e) => {
    const link = e.target.closest('a[href]');
    if (!link) return;
    const href = link.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('mailto') || link.target === '_blank') return;
    if (link.closest('form')) return;
    e.preventDefault();
    document.body.classList.add('page-exit');
    setTimeout(() => { window.location.href = href; }, 200);
});

/**
 * Función para renderizar la tabla de turnos.
 * Recibe la lista de turnos y el cuerpo de la tabla donde se dibujarán.
 */
function renderTurnQueue(turns, tableBody) {
    // 1. Limpiamos cualquier contenido previo en la tabla.
    tableBody.innerHTML = '';

    // 2. Si no hay turnos, mostramos un mensaje.
    if (turns.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="3" class="text-center">No hay turnos en espera.</td></tr>';
        return;
    }

    // 3. Recorremos cada turno de la lista para crear una fila en la tabla.
    turns.forEach(turn => {
        const row = document.createElement('tr');
        
        // Creamos las celdas para el código del turno, el servicio y la sucursal.
        row.innerHTML = `
            <td class="turn-code">${turn.turn_code}</td>
            <td>${turn.service.name}</td>
            <td>Módulo/Caja (Próximamente)</td>
        `;
        
        // 4. Añadimos la nueva fila al cuerpo de la tabla.
        tableBody.appendChild(row);
    });
}

// --- Lógica Principal ---

// Buscamos el cuerpo de la tabla en nuestro HTML cuando la página cargue.
const turnQueueBody = document.getElementById('turn-queue-body');

// Nos aseguramos de que el elemento exista antes de continuar.
// Esto evita errores si estamos en otra página que no sea la de bienvenida.
if (turnQueueBody) {
    console.log('Escuchando eventos de turnos en el canal public-turns...');

    // Usamos Laravel Echo para conectarnos al canal público 'public-turns'.
    window.Echo.channel('public-turns')
        // Nos quedamos escuchando por el evento 'turn.queue.updated'.
        .listen('.turn.queue.updated', (e) => {
            
            // Cuando recibimos el evento, e.turns contiene la nueva lista de turnos.
            console.log('¡Evento recibido! Actualizando cola de turnos:', e.turns);
            
            // Llamamos a nuestra función para redibujar la tabla con los nuevos datos.
            renderTurnQueue(e.turns, turnQueueBody);
        });
}