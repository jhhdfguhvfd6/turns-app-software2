# Informe de Pruebas de Carga — Turns App
**Autor:** Juan Rada  
**Institución:** Fundación Universitaria Claretiana (Uniclaretiana) — Quibdó, Chocó  
**Fecha:** 19 de abril de 2026  
**Asignatura:** Electiva de Ingeniería de Sistemas

---

## 1. Descripción del sistema bajo prueba

**Turns App** es un sistema de gestión de colas y turnos en tiempo real desarrollado con el stack:

- **Backend:** Laravel 11 (PHP 8.2)
- **Base de datos:** MongoDB (via `mongodb/laravel-mongodb`)
- **WebSockets:** Laravel Reverb
- **Frontend:** Blade + Tailwind CSS
- **Servidor de prueba:** `php artisan serve` (servidor embebido PHP — monohilo)

El servidor embebido de PHP procesa **una sola petición a la vez**. Esta es una limitación conocida y deliberada para el entorno de desarrollo; en producción se usaría Nginx + PHP-FPM con múltiples workers. Esta limitación es relevante para interpretar los resultados.

---

## 2. Alcance de las pruebas

Se probaron los **tres endpoints públicos** (sin autenticación requerida):

| Endpoint | Descripción |
|----------|-------------|
| `GET /` | Página de bienvenida |
| `GET /login` | Formulario de inicio de sesión |
| `GET /register` | Formulario de registro |

Los endpoints autenticados (`POST /turn`, `/dashboard`, `/admin/*`) quedaron fuera del alcance por requerir sesión con autenticación por PIN vía email.

---

## 3. Herramientas utilizadas

| Herramienta | Versión | Lenguaje de script | Paradigma de carga |
|-------------|---------|--------------------|--------------------|
| **k6** | 1.7.1 | JavaScript | VUs con rampa de stages |
| **Apache JMeter** | 5.6.3 | XML (JMX) | Thread Groups con scheduler |

### Diferencia fundamental entre k6 y JMeter

- **k6** modela usuarios virtuales que ejecutan iteraciones con `sleep()` entre peticiones, simulando el tiempo de "lectura" del usuario. Esto reduce el RPS efectivo pero representa mejor el comportamiento real.
- **JMeter** usa threads con un `ConstantTimer` de 500 ms entre peticiones. Sin `sleep()` comparable al de k6, los threads hacen más peticiones por segundo por VU, generando mayor presión sobre el servidor.

Esta diferencia explica por qué JMeter midió latencias promedio más altas en el load test (204 ms vs 17 ms de k6) con el mismo número de VUs: JMeter saturó más el servidor monohilo al enviar peticiones con mayor frecuencia.

---

## 4. Escenarios ejecutados

| Escenario | VUs máx | Ramp-up | Duración total | Objetivo |
|-----------|---------|---------|---------------|----------|
| **Smoke** | 3–5 | 30 s | 1 min | Verificar que nada se rompe |
| **Load** | 50 | 60 s | 5 min | Simular carga normal esperada |
| **Stress** | 200 | 5 min | 10 min | Encontrar el punto de quiebre |

---

## 5. Resultados

### 5.1 Smoke Test — Verificación básica

| Métrica | k6 | JMeter |
|---------|----|--------|
| VUs máx | 3 | 5 |
| Total peticiones | 216 | 461 |
| RPS | 3,5 /s | 7,8 /s |
| Latencia promedio | 15,9 ms | 16,6 ms |
| **p(95)** | **20 ms** | **24 ms** |
| p(99) | — | 30 ms |
| Latencia máxima | 42 ms | 40 ms |
| Tasa de errores | **0,00%** | **0,00%** |

**Conclusión smoke:** El sistema responde sin errores bajo carga mínima. Los tiempos de respuesta son excelentes (< 25 ms p95). La app arranca correctamente.

---

### 5.2 Load Test — Carga normal (50 VUs)

| Métrica | k6 | JMeter |
|---------|----|--------|
| VUs máx | 50 | 50 |
| Total peticiones | 17.649 | 19.190 |
| RPS | 58,5 /s | 64,1 /s |
| Latencia promedio | 16,9 ms | 204,8 ms |
| **p(95)** | **37 ms** | **465 ms** |
| p(99) | — | 605 ms |
| Latencia máxima | 128 ms | 910 ms |
| Tasa de errores | **0,00%** | **0,00%** |

**Conclusión load:** Con 50 usuarios concurrentes el sistema sigue sin errores, pero JMeter ya revela latencias más altas (p95 = 465 ms) debido a que genera más presión por VU. k6 mantiene p95 en 37 ms gracias a las pausas de `sleep(2s)` por iteración. En condiciones reales (usuarios reales con tiempos de lectura), la app aguanta bien 50 usuarios simultáneos.

---

### 5.3 Stress Test — Punto de quiebre (hasta 200 VUs)

| Métrica | k6 | JMeter |
|---------|----|--------|
| VUs máx | 200 | 200 |
| Total peticiones | 21.153 | 35.875 |
| RPS | 35,1 /s | 59,9 /s |
| Latencia promedio | 2.215 ms | 2.014 ms |
| **p(95)** | **4.291 ms** | **4.091 ms** |
| p(99) | — | 4.848 ms |
| Latencia máxima | 5.720 ms | 5.722 ms |
| Tasa de errores | **0,00%** | **0,00%** |
| ¿Threshold superado? | ✗ SÍ (p95 > 2s) | N/A |

> **Dato clave:** k6 finalizó con código de error porque el threshold `p(95) < 2000 ms` fue superado (4.291 ms). Esto es exactamente lo que buscábamos: el sistema no colapsa (0% errores HTTP) pero se vuelve inaceptablemente lento a partir de ~100–150 VUs.

**Conclusión stress:** El punto de quiebre del servidor embebido PHP se ubica entre **100 y 150 usuarios concurrentes**. A partir de ese rango, el p(95) supera los 2 segundos. El servidor monohilo empieza a encolar peticiones en lugar de rechazarlas, lo que explica el 0% de errores pero con latencias de 4–5 segundos.

---

## 6. Análisis comparativo: k6 vs JMeter

### Concordancia en los resultados

Ambas herramientas **coinciden en los hallazgos principales**:
- El sistema es estable bajo carga normal (0% errores en todos los escenarios).
- El p(95) del stress test es similar: k6 midió 4.291 ms y JMeter 4.091 ms — diferencia de solo el 4,8%.
- La latencia máxima es prácticamente idéntica: 5.720 ms (k6) vs 5.722 ms (JMeter).

### Diferencias observadas

| Aspecto | k6 | JMeter |
|---------|----|--------|
| **Facilidad de scripting** | Alta — JavaScript intuitivo | Media — XML verboso |
| **Curva de aprendizaje** | Baja | Media-alta |
| **Reporte en terminal** | Inmediato, colorido, con checks | Líneas de summary cada 30 s |
| **Reporte HTML** | Con `K6_WEB_DASHBOARD=true` | `-e -o directorio/` automático |
| **Parametrización** | Variables de entorno (`-e VAR=val`) | Propiedades (`-JVAR=val`) |
| **Precisión de thresholds** | Integrado en el script (falla el test si se supera) | Requiere plugin externo |
| **Simulación de usuario real** | Mejor (sleep entre requests) | Más agresiva (timer fijo) |
| **Velocidad de ejecución** | Ligero (binario Go) | Más pesado (JVM Java) |

### ¿Cuándo usar cada uno?

| Usar **k6** cuando... | Usar **JMeter** cuando... |
|-----------------------|--------------------------|
| Se necesita integrar en CI/CD | El equipo ya conoce JMeter |
| Se prefiere código legible | Se necesita grabación de sesiones HTTP |
| Se quiere threshold automático | Se requieren protocolos no-HTTP (FTP, JDBC) |
| El proyecto es nuevo | La empresa tiene licencias/plugins existentes |

---

## 7. Conclusiones generales

1. **La app Turns App es estable bajo carga normal.** Con hasta 50 usuarios concurrentes, el sistema responde sin errores y con latencias aceptables (< 500 ms p95 en el peor caso medido).

2. **El cuello de botella es el servidor, no la aplicación.** El servidor embebido `php artisan serve` (monohilo) es la limitación principal. En producción con Nginx + PHP-FPM, los resultados del stress test serían significativamente mejores.

3. **El punto de quiebre está entre 100 y 150 VUs** para este servidor de desarrollo. A partir de ese punto, el p(95) supera 2 segundos, aunque sin generar errores HTTP.

4. **k6 y JMeter son complementarios.** k6 es más ágil para desarrollo y CI/CD; JMeter ofrece más opciones para escenarios complejos y protocolos variados. Los resultados de ambas herramientas son consistentes entre sí (diferencia < 5% en las métricas principales del stress test).

---

## 8. Estructura de archivos generados

```
tests/
├── load/
│   ├── k6_turns_test.js          ← Script k6 (3 escenarios parametrizables)
│   └── jmeter_turns_test.jmx     ← Plan JMeter (parametrizable por -JVUS -JRAMP -JDURATION)
└── results/
    ├── k6/
    │   ├── smoke/summary.json    ← Métricas JSON del smoke test
    │   ├── load/summary.json     ← Métricas JSON del load test
    │   └── stress/summary.json   ← Métricas JSON del stress test
    ├── jmeter/
    │   ├── smoke/
    │   │   ├── results.jtl       ← Datos crudos (CSV)
    │   │   └── html/index.html   ← Dashboard HTML interactivo
    │   ├── load/
    │   │   ├── results.jtl
    │   │   └── html/index.html
    │   └── stress/
    │       ├── results.jtl
    │       └── html/index.html
    └── resumen.md                ← Este archivo
```

---

## 9. Comandos de referencia

### Ejecutar pruebas k6
```bash
# Smoke (1 min, 3 VUs)
k6 run -e SCENARIO=smoke tests/load/k6_turns_test.js

# Load (5 min, 50 VUs)
k6 run -e SCENARIO=load tests/load/k6_turns_test.js

# Stress (10 min, hasta 200 VUs) — con dashboard HTML en vivo
K6_WEB_DASHBOARD=true \
K6_WEB_DASHBOARD_EXPORT=tests/results/k6/stress/report.html \
k6 run -e SCENARIO=stress tests/load/k6_turns_test.js
```

### Ejecutar pruebas JMeter
```bash
# Smoke
jmeter -n -t tests/load/jmeter_turns_test.jmx \
  -JVUS=5 -JRAMP=30 -JDURATION=60 \
  -l tests/results/jmeter/smoke/results.jtl \
  -e -o tests/results/jmeter/smoke/html

# Load
jmeter -n -t tests/load/jmeter_turns_test.jmx \
  -JVUS=50 -JRAMP=60 -JDURATION=300 \
  -l tests/results/jmeter/load/results.jtl \
  -e -o tests/results/jmeter/load/html

# Stress
jmeter -n -t tests/load/jmeter_turns_test.jmx \
  -JVUS=200 -JRAMP=300 -JDURATION=600 \
  -l tests/results/jmeter/stress/results.jtl \
  -e -o tests/results/jmeter/stress/html
```

> **Importante:** antes de re-ejecutar JMeter, borrar el `.jtl` y el directorio `html/` del escenario correspondiente, ya que JMeter no sobreescribe archivos existentes.
