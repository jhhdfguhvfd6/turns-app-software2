# RESUMEN TÉCNICO — Sistema de Turnos Banco de Bogotá
> Documento de referencia para redactar el Manual de Usuario.  
> Generado automáticamente a partir del código fuente.

---

## 1. PROPÓSITO

**Nombre del sistema:** Sistema de Gestión de Turnos — Banco de Bogotá  
**Problema que resuelve:** Eliminar las filas físicas en las sucursales bancarias permitiendo a los clientes solicitar un turno digital, conocer su posición en la cola en tiempo real y recibir confirmaciones por correo electrónico.

**Actores involucrados:**
- **Cliente** — Solicita un turno para un servicio bancario desde cualquier dispositivo con navegador.
- **Cajero / Agente** — Atiende la cola en su caja, llama al siguiente turno y lo finaliza.
- **Administrador** — Gestiona usuarios, servicios y revisa registros de auditoría con gráficas.
- **Pantalla pública** — Cualquier visitante puede ver la cola actual en la pantalla de bienvenida sin necesidad de autenticarse.

**Flujo de valor:** El cliente se registra → inicia sesión con PIN enviado al correo → solicita un turno → ve en pantalla cuántos turnos hay delante → el cajero lo llama → el cajero lo finaliza → el turno queda registrado en auditoría.

---

## 2. STACK TECNOLÓGICO

### Backend
| Componente | Versión |
|---|---|
| PHP | ^8.2 |
| Laravel Framework | ^12.0 |
| MongoDB Driver (PHP) | `mongodb/laravel-mongodb` ^5.5 |
| MongoDB Driver (nativo) | `mongodb/mongodb` ^2.1 |
| Broadcasting (Pusher) | `pusher/pusher-php-server` ^7.2 |
| Auditoría (Spatie) | `spatie/laravel-activitylog` ^4.10 |
| ORM | Eloquent con colecciones MongoDB (`MongoDB\Laravel\Eloquent\Model`) |

### Frontend
| Componente | Versión |
|---|---|
| Tailwind CSS | ^3.4.4 |
| Vite | ^7.0.4 |
| Axios | ^1.11.0 |
| Laravel Vite Plugin | ^2.0.0 |
| Tailwind Forms Plugin | ^0.5.10 |
| Motor de plantillas | Laravel Blade |

### Base de datos
| Componente | Detalle |
|---|---|
| Motor | MongoDB 7 |
| Base de datos | `banco_bogota_turnos` |
| Conexión local | `mongodb://127.0.0.1:27017` |
| Conexión Docker | `mongodb://mongodb:27017` |

### Tiempo real
| Componente | Detalle |
|---|---|
| Servicio | Pusher (cluster `us2`, App ID `2050400`) |
| Librería cliente | Laravel Echo (via `resources/js/app.js`) |
| Eventos | `TurnCalled`, `TurnQueueUpdated` |

### Microservicios (añadidos)
| Servicio | Puerto | Tecnología | Función |
|---|---|---|---|
| `notification-service` | 3001 | Node.js + Express + Nodemailer | Envía emails (PIN, confirmación de turno) |
| `audit-service` | 3002 | Node.js + Express + Mongoose | Persiste y expone logs + estadísticas |
| `api-gateway` | 8080 | Node.js + Express + http-proxy-middleware | Enruta el tráfico entre servicios |

---

## 3. ROLES DE USUARIO

El control de acceso se implementa con el middleware `RoleMiddleware` (`app/Http/Middleware/RoleMiddleware.php`). Cada ruta especifica qué rol se requiere con `role:admin` o `role:cashier`. Si el usuario no tiene el rol, es redirigido a `/dashboard` con un mensaje de error.

Los roles válidos son: `client`, `cashier`, `admin` (campo `role` en la colección `users`).

### Rol: `client` (Cliente)
Rol por defecto al registrarse.

| Permiso | Descripción |
|---|---|
| Ver pantalla pública | Ver cola actual sin autenticarse (`/`) |
| Registrarse | Crear cuenta con nombre, documento, email y teléfono |
| Iniciar sesión | Login con número de documento + PIN de 6 dígitos enviado al correo |
| Ver su dashboard | Ver su turno activo, la cola pública y cuántos turnos van delante |
| Solicitar turno | Crear un turno para cualquier servicio activo |
| Cancelar turno | Cancelar su propio turno si está en estado `waiting` o `attending` |
| Cerrar sesión | Logout con registro de auditoría |

> **Restricción:** Un cliente solo puede tener **un turno activo** al mismo tiempo (`waiting` o `attending`). Intentar crear otro redirecciona con un mensaje de error.

### Rol: `cashier` (Cajero)
Asignado por el administrador.

| Permiso | Descripción |
|---|---|
| Todo lo del cliente | (hereda las rutas de auth) |
| Ver dashboard del cajero | Ver cola de turnos pendientes y su turno en atención actual |
| Llamar siguiente turno | Tomar el turno más antiguo en `waiting` y pasarlo a `attending` |
| Finalizar turno | Marcar el turno en `attending` como `completed` |

> **Restricción:** Un cajero **no puede llamar a otro turno** si ya tiene uno en `attending`. Primero debe finalizar el actual.

### Rol: `admin` (Administrador)
Asignado manualmente en la base de datos o por otro admin.

| Permiso | Descripción |
|---|---|
| Todo lo del cliente | (hereda las rutas de auth) |
| Dashboard admin | Ver estadísticas globales (usuarios, servicios, turnos del día) |
| Gestionar usuarios | Listar, editar rol/datos de cualquier usuario |
| Gestionar servicios | Crear, editar, activar/desactivar y eliminar servicios bancarios |
| Ver auditoría | Consultar el historial de acciones con filtros y gráficas |

---

## 4. MÓDULOS Y PANTALLAS

### Módulo público (sin autenticación)

| Ruta | Vista | Descripción |
|---|---|---|
| `GET /` | `welcome.blade.php` | Pantalla de cola pública. Muestra el turno siendo atendido actualmente y los próximos 5 en espera. Actualización en tiempo real via Pusher. |
| `GET /login` | `login.blade.php` | Formulario de login. Pide número de documento. |
| `POST /login` | — | Valida el documento, genera PIN, lo envía al email y redirige a `/verify-pin`. |
| `GET /verify-pin` | `verify-pin.blade.php` | Formulario para ingresar el PIN de 6 dígitos. |
| `POST /verify-pin` | — | Valida el PIN y su vigencia (3 min). Si es correcto, inicia sesión y redirige según rol. |
| `GET /register` | `register.blade.php` | Formulario de registro con nombre, documento, email y teléfono. |
| `POST /register` | — | Crea el usuario con rol `client` y redirige al login. |
| `POST /logout` | — | Cierra sesión y redirige a `/`. |

### Módulo cliente (requiere `auth`)

| Ruta | Vista | Descripción |
|---|---|---|
| `GET /dashboard` | `dashboard.blade.php` | Panel del cliente. Muestra: botones de servicios disponibles para solicitar turno, tarjeta de turno activo con posición en cola y actualización en tiempo real, y lista pública de la cola. |
| `POST /turn` | — | Crea un nuevo turno. Genera el código (`PREFIJO + número correlativo`, ej: `C001`). Emite evento WebSocket. |
| `DELETE /turn/{turn}` | — | Cancela un turno del cliente autenticado. Solo funciona si el turno está en `waiting` o `attending`. |

### Módulo cajero (requiere `auth` + `role:cashier`)

| Ruta | Vista | Descripción |
|---|---|---|
| `GET /cashier/dashboard` | `cashier/dashboard.blade.php` | Panel del cajero. Muestra el turno en atención actual (con datos del cliente y servicio) y la cola de turnos pendientes ordenada por antigüedad. |
| `POST /cashier/call-next` | — | Llama al siguiente turno de la cola (el más antiguo en `waiting`). Lo asigna al cajero y cambia su estado a `attending`. |
| `POST /cashier/finish-current` | — | Marca el turno en `attending` asignado al cajero como `completed` con timestamp `finished_at`. |

### Módulo administrador (requiere `auth` + `role:admin`)

| Ruta | Vista | Descripción |
|---|---|---|
| `GET /admin/dashboard` | `admin/dashboard.blade.php` | Panel admin con tarjetas de estadísticas: total usuarios, cajeros, servicios activos, turnos del día, turnos activos, completados hoy. Lista de últimos 5 usuarios y 10 turnos recientes. |
| `GET /admin/users` | `admin/users/index.blade.php` | Listado paginado (10/página) de todos los usuarios con nombre, documento, email, rol. |
| `GET /admin/users/{id}/edit` | `admin/users/edit.blade.php` | Formulario para editar nombre, email, documento, teléfono, rol y contraseña de un usuario. |
| `PUT /admin/users/{id}` | — | Actualiza el usuario. Registra en auditoría qué campos cambiaron. |
| `GET /admin/services` | `admin/services/index.blade.php` | Listado paginado de servicios con nombre, prefijo y estado activo/inactivo. |
| `GET /admin/services/create` | `admin/services/create.blade.php` | Formulario para crear un nuevo servicio. |
| `POST /admin/services` | — | Crea el servicio. Prefijo debe ser único (máx 5 chars). |
| `GET /admin/services/{id}/edit` | `admin/services/edit.blade.php` | Formulario de edición de un servicio. |
| `PUT /admin/services/{id}` | — | Actualiza el servicio. |
| `DELETE /admin/services/{id}` | — | Elimina el servicio. |
| `GET /admin/audits` | `admin/audits/index.blade.php` | Historial de auditoría con filtros (acción, nombre de usuario, rango de fechas), paginación de 20 registros y tres gráficas (acciones por tipo, actividad diaria 7 días, top 5 usuarios). |

---

## 5. FLUJOS PRINCIPALES

### Flujo 1: Registro e inicio de sesión

```
1. El usuario accede a GET /register
2. Completa: nombre completo, número de documento (único), email (único), teléfono (opcional)
3. POST /register → se crea usuario con rol 'client', contraseña generada aleatoriamente
4. Redirige a GET /login
5. El usuario ingresa su número de documento
6. POST /login → el sistema:
   a. Busca el usuario por document_number
   b. Genera un PIN de 6 dígitos aleatorio
   c. Guarda el PIN en users.pin con expiración de 3 minutos (users.pin_expires_at)
   d. Llama a NotificationMicroservice::sendPin() → notification-service envía el email
   e. Si el microservicio falla, usa el mailer de Laravel como fallback
   f. Guarda user_id y email en sesión para el siguiente paso
   g. Redirige a GET /verify-pin
7. El usuario ingresa el PIN recibido
8. POST /verify-pin → el sistema:
   a. Compara el PIN con users.pin
   b. Verifica que no haya expirado (pin_expires_at)
   c. Limpia el PIN del usuario
   d. Inicia sesión con Auth::login()
   e. Registra auditoría 'login'
   f. Redirige según rol: /admin/dashboard, /cashier/dashboard, o /dashboard
```

### Flujo 2: Solicitar y gestionar un turno (cliente)

```
1. Cliente autenticado accede a GET /dashboard
2. Ve los servicios disponibles (is_active = true)
3. Hace clic en un servicio → POST /turn con service_id
4. El sistema verifica que no tenga turno activo (waiting o attending)
5. Calcula el siguiente número correlativo para el servicio
6. Genera turn_code = PREFIX + número con ceros (ej: 'C001', 'A012')
7. Crea el turno con status = 'waiting'
8. Emite evento TurnQueueUpdated por WebSocket (actualiza todas las pantallas)
9. Llama a NotificationMicroservice::sendTurnConfirmation() → email al cliente
10. Registra auditoría 'create_turn'
11. El cliente ve en su dashboard el código, cuántos turnos hay delante y actualización en tiempo real
12. Si desea cancelar: DELETE /turn/{turn} → status = 'cancelled', emite evento WebSocket
```

### Flujo 3: Atención de turno (cajero)

```
1. Cajero accede a GET /cashier/dashboard
2. Ve la lista de turnos en estado 'waiting' ordenados del más antiguo al más nuevo
3. Hace clic en "Llamar siguiente" → POST /cashier/call-next
4. El sistema verifica que no tenga otro turno en 'attending'
5. Toma el turno más antiguo en 'waiting'
6. Lo actualiza: status = 'attending', assigned_cashier_id = cajero._id, called_at = now()
7. Registra auditoría 'call_turn'
8. El cajero ve el turno en su panel con los datos del cliente y el servicio
9. Cuando termina la atención: POST /cashier/finish-current
10. El sistema actualiza: status = 'completed', finished_at = now()
11. Registra auditoría 'finish_turn'
12. El cajero puede llamar al siguiente turno
```

### Flujo 4: Gestión de servicios (admin)

```
1. Admin accede a GET /admin/services
2. Para crear: GET /admin/services/create → completa nombre y prefijo (máx 5 chars, único)
3. POST /admin/services → se crea el servicio, se registra auditoría 'service_create'
4. Para editar: GET /admin/services/{id}/edit → modifica los campos
5. PUT /admin/services/{id} → actualiza, registra 'service_update'
6. Para eliminar: DELETE /admin/services/{id} → elimina, registra 'service_delete'
7. El prefijo del servicio determina la primera parte del código de turno generado
```

---

## 6. REGLAS DE NEGOCIO

### Autenticación
- El login **no usa contraseña tradicional**; usa un PIN de 6 dígitos enviado al correo.
- El PIN expira a los **3 minutos** de ser generado.
- Si el PIN es incorrecto, se puede volver a intentar mientras no expire (los datos se guardan en sesión).
- Si el PIN expiró, se limpia de la base de datos y se debe solicitar uno nuevo.
- Al registrarse, se genera una contraseña aleatoria (no visible, no se usa para login).

### Turnos
- Un cliente solo puede tener **un turno activo** a la vez (estado `waiting` o `attending`).
- El código de turno se forma con el **prefijo del servicio** + número secuencial con ceros a la izquierda de 3 dígitos (ej: `C001`, `A002`). El número es correlativo por servicio, no global.
- Un turno solo puede cancelarse si está en estado `waiting` o `attending`.
- Solo el **propietario del turno** puede cancelarlo (validado por `user_id`).
- Un cajero **no puede llamar un nuevo turno** si ya tiene uno en `attending`.
- La cola de turnos se ordena: primero los `attending`, luego los `waiting`, por fecha de creación ascendente (FIFO dentro de cada grupo).

### Servicios
- El prefijo del servicio es **único** en toda la base de datos.
- El prefijo tiene un máximo de **5 caracteres** (en seeder se usan 1 carácter: C, A, P).
- Solo los servicios con `is_active = true` aparecen disponibles para los clientes.

### Auditoría
- Cada acción importante genera un registro en la colección `audit_logs` con: acción, descripción, usuario, IP, user agent y timestamp.
- El `AuditHelper::log()` persiste el registro **localmente en MongoDB** y también lo replica al `audit-service` (microservicio). Si el microservicio no está disponible, el log local se guarda igual.
- Acciones auditadas: `login`, `logout`, `pin_request`, `user_register`, `create_turn`, `cancel_turn`, `call_turn`, `finish_turn`, `user_update`, `service_create`, `service_update`, `service_delete`.

### Roles
- Los roles válidos son exactamente: `client`, `cashier`, `admin`.
- Un usuario con rol `client` que intenta acceder a rutas de admin o cashier es redirigido a `/dashboard`.
- Los admins pueden cambiar el rol de cualquier usuario desde el panel de usuarios.

---

## 7. ESTRUCTURA DE DATOS (MongoDB)

### Colección: `users`
| Campo | Tipo | Descripción |
|---|---|---|
| `_id` | ObjectId | Identificador único (MongoDB) |
| `name` | string | Nombre completo |
| `document_number` | string (único) | Número de cédula / documento de identidad |
| `email` | string (único) | Correo electrónico |
| `password` | string (hashed) | Contraseña bcrypt (generada aleatoriamente, no se usa para login) |
| `phone` | string / null | Teléfono de contacto (opcional) |
| `role` | string | Rol del usuario: `client`, `cashier` o `admin` |
| `pin` | string / null | PIN de 6 dígitos (se borra tras usarse o expirar) |
| `pin_expires_at` | datetime / null | Timestamp de expiración del PIN |
| `created_at` | datetime | Fecha de creación |
| `updated_at` | datetime | Fecha de última modificación |

### Colección: `turns`
| Campo | Tipo | Descripción |
|---|---|---|
| `_id` | ObjectId | Identificador único |
| `user_id` | string (ref users) | Cliente dueño del turno |
| `service_id` | string (ref services) | Servicio solicitado |
| `branch_id` | string (ref branches) | Sucursal (opcional en uso actual) |
| `assigned_cashier_id` | string / null | Cajero que atiende el turno |
| `turn_code` | string (único) | Código legible del turno (ej: `C001`) |
| `turn_number` | integer | Número correlativo por servicio |
| `status` | string | Estado: `waiting`, `attending`, `completed`, `cancelled` |
| `called_at` | datetime / null | Momento en que el cajero llamó al turno |
| `finished_at` | datetime / null | Momento en que se finalizó la atención |
| `created_at` | datetime | Momento en que se creó el turno |
| `updated_at` | datetime | Última modificación |

**Estados posibles del turno:**
```
[solicitud] → waiting → attending → completed
                  ↓
               cancelled
```

### Colección: `services`
| Campo | Tipo | Descripción |
|---|---|---|
| `_id` | ObjectId | Identificador único |
| `name` | string | Nombre del servicio (ej: "Caja", "Asesoría General") |
| `prefix` | string (único, máx 5) | Prefijo para código de turno (ej: `C`, `A`, `P`) |
| `is_active` | boolean | Si aparece disponible para los clientes |
| `created_at` | datetime | Fecha de creación |
| `updated_at` | datetime | Fecha de modificación |

**Servicios cargados por defecto (seeder):**
| Nombre | Prefijo |
|---|---|
| Caja | C |
| Asesoría General | A |
| Créditos y Cartera | P |

### Colección: `branches`
| Campo | Tipo | Descripción |
|---|---|---|
| `_id` | ObjectId | Identificador único |
| `name` | string | Nombre de la sucursal |
| `address` | string / null | Dirección física |
| `created_at` | datetime | Fecha de creación |
| `updated_at` | datetime | Fecha de modificación |

**Sucursal cargada por defecto:** `Sucursal Principal - Centro`

### Colección: `audit_logs`
| Campo | Tipo | Descripción |
|---|---|---|
| `_id` | ObjectId | Identificador único |
| `user_id` | string / null | ID del usuario que realizó la acción |
| `user_name` | string | Nombre del usuario (o `'Sistema'` si no hay sesión) |
| `user_email` | string / null | Email del usuario |
| `action` | string | Código de la acción (ej: `login`, `create_turn`) |
| `description` | string | Descripción detallada en texto libre |
| `ip_address` | string | IP del cliente |
| `user_agent` | string | Navegador / agente del cliente |
| `created_at` | datetime | Timestamp de la acción |

---

## 8. VARIABLES DE ENTORNO

Archivo de referencia: `.env` (configurar en base a este listado)

```env
# ── Aplicación ───────────────────────────────────────────
APP_NAME=Laravel
APP_ENV=local                     # local | production
APP_KEY=base64:...                # Generar con: php artisan key:generate
APP_DEBUG=true                    # false en producción
APP_URL=http://localhost

# ── Base de datos MongoDB ────────────────────────────────
DB_CONNECTION=mongodb
DB_URI=mongodb://127.0.0.1:27017/?directConnection=true
DB_DATABASE=banco_bogota_turnos

# ── Correo electrónico (Brevo / SMTP) ────────────────────
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=tu_usuario_brevo
MAIL_PASSWORD=tu_clave_smtp_brevo
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tu_email@dominio.com
MAIL_FROM_NAME="Sistema de Turnos"

# ── Tiempo real (Pusher) ─────────────────────────────────
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=tu_app_id
PUSHER_APP_KEY=tu_app_key
PUSHER_APP_SECRET=tu_app_secret
PUSHER_APP_CLUSTER=us2
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

# ── Microservicios ────────────────────────────────────────
NOTIFICATION_SERVICE_URL=http://localhost:3001
AUDIT_SERVICE_URL=http://localhost:3002
SERVICE_SECRET=clave_secreta_compartida_entre_servicios

# ── Sesión y caché ────────────────────────────────────────
SESSION_DRIVER=file
SESSION_LIFETIME=120
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

---

## 9. INSTALACIÓN Y EJECUCIÓN

### Prerrequisitos
- PHP 8.2+
- Composer 2+
- Node.js 18+ y npm
- MongoDB 7 en ejecución local (`mongod`)
- Cuenta en Pusher (gratis en pusher.com)
- Cuenta en Brevo o cualquier servidor SMTP

### Instalación paso a paso

```bash
# 1. Instalar dependencias PHP
composer install

# 2. Instalar dependencias JavaScript
npm install

# 3. Copiar y configurar el archivo de entorno
cp .env.example .env          # si existe el .env.example
php artisan key:generate       # genera APP_KEY

# 4. Editar .env con las credenciales de MongoDB, SMTP y Pusher

# 5. Ejecutar migraciones (crea las colecciones en MongoDB)
php artisan migrate

# 6. Cargar datos de prueba (usuarios, sucursal, servicios)
php artisan db:seed

# 7. Compilar assets frontend
npm run build                  # producción
# o
npm run dev                    # desarrollo (con hot reload)

# 8. Levantar el servidor de desarrollo
php artisan serve

# La app queda disponible en: http://localhost:8000
```

### Ejecución en modo desarrollo completo (todos los procesos)

```bash
# Levanta en paralelo: Laravel, queue, logs y Vite
composer run dev
```

### Ejecución con Docker (microservicios incluidos)

```bash
# Construye y levanta todos los contenedores
docker-compose up --build

# Servicios disponibles:
# http://localhost:8080 → API Gateway (punto de entrada recomendado)
# http://localhost:8000 → Laravel app directamente
# http://localhost:3001 → notification-service (API interna)
# http://localhost:3002 → audit-service (API interna)
# mongodb://localhost:27017 → MongoDB
```

### Usuarios de prueba (cargados por el seeder)

| Rol | Número de documento | Email |
|---|---|---|
| Cliente | `123456789` | cliente@prueba.com |
| Cajero | `987654321` | cajero@prueba.com |
| Admin | `111222333` | admin@prueba.com |

> Para iniciar sesión, ingresar el número de documento → se envía PIN al email configurado en la cuenta de usuario.

### Instalación de microservicios (modo desarrollo local)

```bash
# Notification service
cd microservices/notification-service
cp .env.example .env    # configurar MAIL_* y SERVICE_SECRET
npm install
npm run dev             # puerto 3001

# Audit service
cd microservices/audit-service
cp .env.example .env    # configurar MONGO_URI y SERVICE_SECRET
npm install
npm run dev             # puerto 3002

# API Gateway
cd microservices/api-gateway
cp .env.example .env    # configurar URLs de servicios
npm install
npm run dev             # puerto 8080
```

---

## 10. CAPTURAS / IMÁGENES DISPONIBLES

| Archivo | Ruta | Uso sugerido en el manual |
|---|---|---|
| Logo Banco de Bogotá | `public/img/logo_banco_bogota.png` | Portada del manual, encabezados de sección |

> **Nota:** No se encontraron capturas de pantalla de las vistas en el repositorio. Para el manual de usuario se recomienda tomar capturas de las siguientes pantallas clave:
>
> | Pantalla | URL |
> |---|---|
> | Cola pública | `http://localhost:8000/` |
> | Formulario de login | `http://localhost:8000/login` |
> | Verificación de PIN | `http://localhost:8000/verify-pin` |
> | Registro de usuario | `http://localhost:8000/register` |
> | Dashboard del cliente | `http://localhost:8000/dashboard` |
> | Dashboard del cajero | `http://localhost:8000/cashier/dashboard` |
> | Dashboard del administrador | `http://localhost:8000/admin/dashboard` |
> | Gestión de usuarios | `http://localhost:8000/admin/users` |
> | Gestión de servicios | `http://localhost:8000/admin/services` |
> | Registros de auditoría | `http://localhost:8000/admin/audits` |

---

*Este documento fue generado a partir de la lectura directa del código fuente: `routes/web.php`, modelos Eloquent, controladores, migraciones, seeders y configuración. Refleja el estado del proyecto al momento de su generación.*
