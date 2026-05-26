/**
 * Prueba de carga k6 — Turns App
 * Endpoints públicos: GET /  |  GET /login  |  GET /register
 *
 * Uso:
 *   k6 run -e SCENARIO=smoke   tests/load/k6_turns_test.js
 *   k6 run -e SCENARIO=load    tests/load/k6_turns_test.js
 *   k6 run -e SCENARIO=stress  tests/load/k6_turns_test.js
 *
 * Para reporte HTML interactivo (abre en el navegador mientras corre):
 *   K6_WEB_DASHBOARD=true K6_WEB_DASHBOARD_EXPORT=tests/results/k6/smoke/report.html \
 *     k6 run -e SCENARIO=smoke tests/load/k6_turns_test.js
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';

// ─── Configuración ──────────────────────────────────────────────────────────

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8001';
const SCENARIO  = __ENV.SCENARIO  || 'smoke';

// Cada escenario define una "rampa" de usuarios virtuales (VUs).
// Stages: k6 interpola la cantidad de VUs entre etapas a lo largo del tiempo.
// Por qué 3 escenarios: smoke verifica que nada se rompa, load simula
// uso normal, stress busca el punto de quiebre.
const ESCENARIOS = {
  smoke: {
    // 3 usuarios, 1 minuto total — si hay un error aquí, hay un bug grave
    stages: [
      { duration: '15s', target: 3 },
      { duration: '30s', target: 3 },
      { duration: '15s', target: 0 },
    ],
  },
  load: {
    // Rampa hasta 50 usuarios, sostenida 3 minutos — carga normal esperada
    stages: [
      { duration: '1m',  target: 50 },
      { duration: '3m',  target: 50 },
      { duration: '1m',  target: 0  },
    ],
  },
  stress: {
    // Sube escalonado hasta 200 VUs buscando cuándo la app empieza a degradarse
    stages: [
      { duration: '2m', target: 50  },
      { duration: '2m', target: 100 },
      { duration: '2m', target: 150 },
      { duration: '2m', target: 200 },
      { duration: '2m', target: 0   }, // enfriamiento para que el servidor se recupere
    ],
  },
};

// ─── Opciones globales ───────────────────────────────────────────────────────

export const options = {
  stages: ESCENARIOS[SCENARIO]?.stages ?? ESCENARIOS.smoke.stages,

  thresholds: {
    // El 95% de las peticiones debe responder en < 2 s
    // El 99% debe responder en < 5 s
    // Si se superan estos umbrales, k6 termina con código de error (útil en CI)
    http_req_duration: ['p(95)<2000', 'p(99)<5000'],

    // Menos del 5% de peticiones pueden fallar (status >= 400 o error de red)
    http_req_failed: ['rate<0.05'],
  },
};

// ─── Función principal (se ejecuta una vez por VU por iteración) ─────────────

export default function () {

  // group() agrupa las métricas bajo un nombre en el reporte — así podés ver
  // por separado cuánto tardó cada endpoint.

  group('GET /', function () {
    const res = http.get(`${BASE_URL}/`);
    check(res, {
      'welcome → status 200':      (r) => r.status === 200,
      'welcome → tiene contenido': (r) => r.body.length > 100,
    });
  });

  sleep(0.5); // pausa entre peticiones: simula que el usuario lee la página

  group('GET /login', function () {
    const res = http.get(`${BASE_URL}/login`);
    check(res, {
      'login → status 200':          (r) => r.status === 200,
      'login → contiene formulario': (r) => r.body.includes('form'),
    });
  });

  sleep(0.5);

  group('GET /register', function () {
    const res = http.get(`${BASE_URL}/register`);
    check(res, {
      'register → status 200':          (r) => r.status === 200,
      'register → contiene formulario': (r) => r.body.includes('form'),
    });
  });

  sleep(1); // pausa al final de cada iteración — sin esto k6 martillaría el servidor sin respiro
}

// ─── Resumen al finalizar ────────────────────────────────────────────────────
// handleSummary() recibe todos los datos de la prueba y puede escribirlos
// a archivos. Aquí guardamos un JSON con todas las métricas.

export function handleSummary(data) {
  const outFile = `tests/results/k6/${SCENARIO}/summary.json`;
  return {
    [outFile]: JSON.stringify(data, null, 2),
    stdout: `\n✓ Prueba "${SCENARIO}" finalizada. Resumen guardado en: ${outFile}\n`,
  };
}
