import ws from 'k6/ws';
import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 20 }, // Sube a 20 usuarios en 30s
    { duration: '1m', target: 50 },  // Mantén 50 usuarios por 1 minuto
    { duration: '30s', target: 0 },  // Baja a 0
  ],
  thresholds: {
    http_req_duration: ['p(95)<2000'], // El 95% de peticiones debe ser < 2s
  },
};

// Configuración de URLs - AJUSTA ESTAS RUTAS
const BASE_URL = 'http://127.0.0.1:8000'; 
const WS_URL = 'ws://127.0.0.1:8080/app/tu_app_key'; // URL de tu servidor WebSocket (Reverb/Soketi)

export default function () {
  // 1. Simular entrada a la Web (HTTP)
  const res = http.get(`${BASE_URL}/dashboard`);
  check(res, {
    'página carga (200)': (r) => r.status === 200,
  });

  // 2. Conexión al WebSocket para ver la "Cola en vivo"
  const url = WS_URL;
  const params = { tags: { my_tag: 'user_waiting' } };

  ws.connect(url, params, function (socket) {
    socket.on('open', () => {
      console.log('Usuario conectado a la cola en vivo');
      
      // Simular que el usuario envía un mensaje de suscripción si usas Pusher/Reverb
      // socket.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel: 'queue-channel' } }));
    });

    socket.on('message', (data) => {
      const message = JSON.parse(data);
      check(message, {
        'mensaje recibido es actualización': (m) => m.event !== null,
      });
      console.log('Actualización de turno recibida');
    });

    // El usuario se queda "viendo" la cola durante 20 segundos
    socket.setTimeout(function () {
      socket.close();
      console.log('Usuario terminó su consulta');
    }, 20000);

    socket.on('error', (e) => {
      console.error('Error en WebSocket:', e.error());
    });
  });

  sleep(1);
}