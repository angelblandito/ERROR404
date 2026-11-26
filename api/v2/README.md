# API JAM v2 (PHP + SQL Server) · arquitectura de la clase

La API que usa la tienda, con la estructura del profe en una sola carpeta: `core`, `routers`,
`controllers`, `services`, `models`, `schemas`, `utils` y `tests`. Tiene **todas** las rutas de la
página, incluida la base distribuida (sucursales, fragmentos, replicación) y el espejo en Azure SQL.

## Capas

```
Petición ─► core/router.php ─► middlewares (core) ─► controllers ─► services ─► nodos de SQL Server
               (autentica la sesión)   (errores, bitácora,                (lógica y SQL)    central, sucursales
                                         seguridad y CORS)                                   y Azure SQL
```

| Carpeta o archivo | Qué contiene |
|---|---|
| `index.php` | Punto de entrada (equivale a `main.py`): registra los routers y las tareas después de responder |
| `bootstrap.php` | Carga el código en orden: core, utils, clases base, servicios, modelos, schemas y controllers |
| `core/` | Arranque (`app.php`), ajustes, conexión a los nodos (`Database::node()`), router, peticiones/respuestas, errores, seguridad y middlewares (errores de SQL Server en español, bitácora, HTTPS opcional y CORS) |
| `routers/` | Solo registran rutas. Los `*_router.php` de una tabla los genera `build.php` |
| `controllers/` | `BaseController` (CRUD), `ApiController` (rutas propias) y los controllers: leen parámetros y el actor de la sesión, llaman al servicio y devuelven datos |
| `services/` | Lógica de negocio: tienda, cuentas, compras, administración, visitas, comparador, correos, base distribuida, espejo, sesiones (`TokenService`) y permisos (`Policy`) |
| `models/`, `schemas/` | `BaseModel`, `BaseSchema` y los de cada tabla, generados por `build.php` desde `API_JAM/db_jam.sql` (31 tablas) |
| `utils/` | Fechas y zona horaria |
| `tests/` | Pruebas sin base de datos y comparación contra la API anterior |

Respuesta de todas las rutas: `{"success": true|false, "data": ..., "message": "..."}`.

## Sesión y permisos

- `POST /auth/login` devuelve un token aleatorio; en `dbo.customer_session` solo se guarda su SHA-256.
- La página lo manda en `Authorization: Bearer <token>`. El **router** valida el token
  (`TokenService::decode_token`) y pone el actor en el controller; los controllers nunca leen la cabecera.
- Tipos de cuenta (`account_tier`): Estándar, Prioritario y Administrador. El CRUD genérico
  (`/product`, `/customer`, …) es solo para el Administrador; sin sesión solo se leen
  `/promotion`, `/state` y `/season_theme`.

## Rutas propias (además del CRUD de las 31 tablas)

| Área | Rutas |
|---|---|
| Tienda | `GET /store/catalog`, `/store/products/{id}/reviews`, `/store/products/{id}/also-bought`, `/store/announcements`, `/season_theme/current`, `/postal-codes/{cp}`; `POST /store/reviews` |
| Cuentas | `POST /auth/register`, `/auth/login`, `/auth/logout`, `/auth/forgot`, `/auth/reset`; `GET /auth/me` |
| Cliente | `GET /me/orders`, `GET`/`POST /support/tickets`, `POST /orders/checkout`, `GET /market` |
| Visitas | `POST /analytics/ping`, `/leave`, `/search`, `/install`; `GET /admin/analytics`, `/admin/analytics/online` |
| Administración | `/admin/summary`, `/admin/customers`, `/admin/customers/{id}/tier`, `/admin/customers/evaluate`, `/admin/orders`, `/admin/orders/{id}/status`, `/admin/products/{id}`, `/admin/inventory`, `/admin/tickets`, `/admin/announcements`, `/admin/emails`, `/admin/market` |
| Base distribuida | `GET /nodes`, `/distributed/inventory?node=`, `/distributed/orders`, `/distributed/customers`, `/distributed/fragments`, `/replication/status`; `POST /distributed/sync`, `/mirror/sync?full=1` |

Después de cada escritura que sale bien (una compra, un cambio de existencias…), la API manda la
respuesta y luego refresca los fragmentos de las sucursales y copia los cambios al espejo en Azure
(`index.php`). Si una sucursal no responde, sus datos se leen del espejo o del nodo central.

## Seguridad

| Protección | Cómo |
|---|---|
| Sesiones | Token aleatorio de 256 bits; en la base solo su SHA-256; vence en 7 días; se revoca al cerrar sesión o al cambiar la contraseña |
| Contraseñas | bcrypt; mínimo 8 caracteres con letras y números; bloqueo de 15 minutos tras 5 intentos fallidos; mismo mensaje y mismo tiempo exista o no el correo |
| Permisos | El router exige sesión; panel, base distribuida y CRUD solo para Administrador (401 sin sesión, 403 con otra cuenta) |
| Datos de otros clientes | El id del cliente sale de la sesión, nunca del cuerpo (pedidos, compras, reseñas, tickets) |
| Inyección SQL | Consultas preparadas; nombres de tablas y columnas solo de listas fijas; parámetros validados (nodo, estado, ids numéricos) |
| Entradas | JSON obligatorio como objeto, UTF-8, máximo 1 MB; el CRUD rechaza campos desconocidos y nunca acepta ni devuelve `password_hash` |
| Recuperación | Enlace de un solo uso, 30 minutos, con espera de 60 s entre solicitudes; responde igual exista o no la cuenta; el enlace solo apunta al mismo sitio |
| Respuestas | Sin detalles técnicos (ni SQL ni rutas de archivos); cabeceras `nosniff`, `no-referrer`, `no-store`; CORS cerrado a otros sitios |
| Archivos | `.htaccess` manda todo a `index.php`: no se puede abrir código, pruebas, `.env` ni la configuración |

## Configuración

No hay contraseñas en el código. Cada ajuste se busca en este orden: variables de entorno,
`api/v2/.env`, `api/v2/config/settings.php` y, si no existe, el mismo
`api/v1/php/config/settings.php` que ya usa la tienda (así las contraseñas están en un solo lugar).

| Ajuste | Para qué |
|---|---|
| `JAM_LOCAL_SERVER`, `JAM_LOCAL_USER`, `JAM_LOCAL_PASSWORD` | SQL Server local (sin usuario = autenticación de Windows) |
| `JAM_CLOUD_SERVER`, `JAM_CLOUD_MIRROR_SERVER`, `JAM_CLOUD_USER`, `JAM_CLOUD_PASSWORD` | Azure SQL y su espejo (sin usuario o contraseña, el nodo de Azure queda apagado) |
| `JAM_DEFAULT_NODE`, `JAM_ENABLED_NODES`, `SESSION_DAYS`, `APP_TIMEZONE` | Nodo por defecto, nodos habilitados, días de sesión y zona horaria |
| `REQUIRE_HTTPS`, `TRUST_PROXY`, `CORS_ALLOW_ORIGIN`, `APP_DEBUG` | Seguridad del transporte (detrás de Caddy: `TRUST_PROXY=1`) |

Requiere PHP 8.2 o superior con la extensión `pdo_sqlsrv`. En Apache, `api/v2/.htaccess` manda
todas las peticiones a `index.php` y no deja abrir ningún otro archivo de la carpeta.

## Comandos (desde `api/v2`)

```powershell
php tests/run.php            # pruebas unitarias del núcleo, permisos y schemas
php tests/rutas.php          # cada ruta llega a su controller y servicio con la autenticación correcta
php build.php                # vuelve a generar models, schemas, controllers y routers de las tablas
powershell -ExecutionPolicy Bypass -File tests/comparar_v1_v2.ps1   # compara v1 y v2 con la base real
```

`build.php` lee `API_JAM/db_jam.sql` y solo reescribe los archivos con la marca `GENERADO`.

## La página

`config.js` usa esta API (`api/v2`). Para comparar con la anterior se abre la tienda con `?api=v1`
y para regresar, con `?api=v2`; la elección se guarda en el navegador.
