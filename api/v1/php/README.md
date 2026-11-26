# API v3 de JAM Sport (estructura nueva del profe)

Es el API del profe (`apicore`, versión PHP) aplicado a la tienda: las mismas carpetas, las
mismas clases base y el mismo formato de respuestas, pero conectado a **nuestra** base de
SQL Server (`API_JAM/db_jam.sql` y los scripts de `API_JAM/distribuida`) en lugar de la base
MySQL del profe. El `apicore.sql` del profe no se usa.

Es la API que usa la página (`config.js` apunta a `api/v3`). Las carpetas `api/v1` y `api/v2`
se conservan solo porque la máquina virtual de Azure todavía corre la v1 y porque la v3 lee
la contraseña de SQL Server de su `config/settings.php` (ver Configuración).

## Qué cambia respecto al API del profe

| Del profe | En JAM | Por qué |
| --- | --- | --- |
| MySQL (`pdo_mysql`) | SQL Server (`pdo_sqlsrv`) | Es nuestra base, con nodos distribuidos y espejo en Azure |
| Una sola base | Nodos `central`, `tijuana`, `cdmx`, `online` y `cloud` | `Core/Database.php` abre el nodo que se pida |
| Tabla `users` | `customer` + `customer_credential` | Son las cuentas que ya tiene la tienda |
| Roles `God/Admin/User/Other` | Los tipos de cuenta de `account_tier`: `standard` (Estándar), `priority` (Prioritario) y `admin` (Administrador) | Son los roles de nuestra base; se leen de la base en cada petición |
| Login con `Username` | `Username` = correo (también acepta `email`/`password`, que es lo que manda la página) | |
| PBKDF2 | bcrypt | Así están guardadas las contraseñas de la tienda y la v1/v2 las tienen que seguir leyendo. Los hashes del profe se siguen aceptando al verificar |
| `DELETE` borra la fila | Borrado lógico (`deleted = 1`) en las tablas que tienen esa columna | Así el borrado llega a las sucursales y a Azure en la siguiente sincronización |
| `LIMIT ... OFFSET` | `OFFSET ... FETCH NEXT` | Sintaxis de SQL Server |

Además de las rutas CRUD de cada tabla, la v3 tiene todas las rutas propias de la tienda
(catálogo, compra, panel de administración, base distribuida...), con las mismas
direcciones que la v2. Responden exactamente los mismos datos que la v2; solo cambia el
formato de la respuesta al del profe.

## Requisitos

- PHP 8.1 o superior con `pdo_sqlsrv`, `json` y `mbstring` (el mismo XAMPP de la tienda).
- SQL Server con `JAM_db` creada.

## Base de datos

En SSMS, en este orden (todos están en `API_JAM`):

1. `db_jam.sql`: crea `JAM_db` con todas las tablas y datos de la tienda.
2. `distribuida/08_catalogo_temporadas.sql`: temporadas y productos de temporada.
3. `distribuida/09_tokens_y_accesos.sql`: tablas `users_tokens` (sesiones) y
   `login_attempts` (intentos fallidos). **La v3 las necesita**: ahí guarda los tokens.
4. `distribuida/01` a `07`: nodos, replicación, espejo en Azure, seguridad. Solo hacen falta
   para la parte distribuida (rutas `/nodes`, `/distributed/...`, `/mirror/sync`).

Si ya tienes la base de los días anteriores, solo asegúrate de haber corrido el 08 y el 09.

## Configuración

1. Copia `.env.example` como `.env` en esta misma carpeta.
2. Pon un valor en `JWT_SECRET` (la línea del archivo dice cómo generarlo en PowerShell).
3. Listo: el servidor y la contraseña de SQL Server se leen del mismo archivo que ya usa la
   tienda (`api/v2/config/settings.php` o `api/v1/php/config/settings.php`). Si algún día se
   borran esas carpetas, antes hay que pasar `JAM_LOCAL_SERVER`, `JAM_LOCAL_USER`,
   `JAM_LOCAL_PASSWORD` y las `JAM_CLOUD_*` al `.env` de la v3.

| Variable | Por defecto | Uso |
| --- | --- | --- |
| `JWT_SECRET` | — | **Obligatoria**, mínimo 32 caracteres. Clave con la que se firman los tokens |
| `JWT_ALGORITHM` | `HS256` | Algoritmo de firma |
| `TOKEN_LIFETIME_MINUTES` | `60` (el ejemplo pone `10080`, 7 días) | Vida del token |
| `TOKEN_MIN_REMAINING_MINUTES` | `10` | Minutos que el cliente usa para renovar |
| `APP_TIMEZONE` | `America/Tijuana` | Zona con la que se muestran las fechas |
| `CORS_ALLOW_ORIGIN` | vacío | Vacío = misma dirección; `*` o una lista separada por comas |
| `JAM_STORE_URL` | vacío | Dirección de la página; los enlaces de los correos solo apuntan aquí, a un origen de `CORS_ALLOW_ORIGIN` o a localhost |
| `JAM_DEFAULT_NODE` | `central` | Nodo del CRUD y de la tienda |
| `JAM_LOCAL_SERVER`, `JAM_LOCAL_USER`, `JAM_LOCAL_PASSWORD` | settings.php | SQL Server local (sin usuario = autenticación de Windows) |
| `JAM_CLOUD_*` | settings.php | Azure SQL (si no hay usuario, el nodo `cloud` queda apagado) |
| `REQUIRE_HTTPS` | `0` | `1` rechaza peticiones sin https (máquina virtual con Caddy) |
| `TRUST_PROXY` | `0` | `1` detrás de Caddy en la máquina virtual |
| `RATE_LIMIT` | `1` | `0` apaga el límite de peticiones por IP |

## Ejecutar

Con Apache (XAMPP): la API queda en `http://localhost/ERROR404/api/v3/`. El `.htaccess`
manda todo a `index.php`.

Sin Apache, igual que el profe:

```bash
cd api/v3
php -S localhost:8000 index.php
```

Prueba: `http://localhost/ERROR404/api/v3/health` → `{"status":"ok","message":"Server is running"}`.

## Formato de las respuestas (el del profe)

```json
{"status": "ok", "data": {...}}
{"status": "ok", "ID": 12}
{"status": "ok", "message": "Product updated"}
{"error": "Product not found"}
```

Códigos: 400 validación, 401 token, 403 permisos, 404 no existe, 405 método, 409 conflicto,
413 cuerpo demasiado grande, 429 demasiados intentos, 503 base o nodo no disponible.

## Endpoints

### Cuenta

| Método | Ruta | Acceso | Descripción |
| --- | --- | --- | --- |
| GET | `/health` | público | 200 si la base responde, 503 si no |
| POST | `/auth/login` | público | Token. Cuerpo del profe `{"Username","Password","Persistent"}` o el de la página `{"email","password"}` |
| POST | `/auth/logout` | token | Revoca el token usado |
| POST | `/auth/register` | público | Crea una cuenta de cliente y devuelve su token |
| GET | `/auth/me` | token | Datos de la cuenta de la sesión |
| POST | `/auth/forgot` | público | Manda el enlace para cambiar la contraseña |
| POST | `/auth/reset` | público | Cambia la contraseña con ese enlace |

La respuesta del login trae lo mismo que la del profe (`token`, `token_type`, `expires_at`,
`persistent`, `min_remaining_minutes`) más `role` (el tipo de cuenta: `standard`, `priority`
o `admin`) y los datos del cliente (`customer`).

### Roles

Son los tipos de cuenta de la tabla `account_tier`:

| Rol | Nombre | Qué puede hacer |
| --- | --- | --- |
| `standard` | Estándar | Comprar, ver sus pedidos y sus tickets |
| `priority` | Prioritario | Lo mismo, con sus beneficios, y el comparador de mercado (`/market`) |
| `admin` | Administrador | Todo, incluido el panel, el CRUD de las tablas y la base distribuida |

El rol se lee de la base en cada petición: si un administrador cambia el tipo de cuenta de
un cliente, el cambio aplica de inmediato aunque el cliente ya tuviera sesión abierta.

Intentos fallidos (tabla `login_attempts`): 5 con el mismo correo desde la misma IP o 20
desde la misma IP bloquean esa conexión 15 minutos (429); 30 fallos en total bloquean la
cuenta del cliente.

### CRUD de cada tabla (generado)

Cada tabla de `db_jam.sql` con llave numérica tiene sus seis rutas, como `/moods` del profe:

| Método | Ruta | Descripción |
| --- | --- | --- |
| GET | `/{tabla}` | Lista (`?page=1&size=20`; sin `size`, todas) |
| GET | `/{tabla}/{id}` | Un registro |
| POST | `/{tabla}` | Crea (201 + `Location`) |
| PUT | `/{tabla}/{id}` | Actualización completa |
| PATCH | `/{tabla}/{id}` | Actualización parcial |
| DELETE | `/{tabla}/{id}` | Borra (lógico si la tabla tiene `deleted`) |

Tablas: `role`, `store_type`, `category`, `size`, `color`, `loyalty_level`, `account_tier`,
`absence_type`, `state`, `season_theme`, `owner`, `address`, `store`, `employee`,
`employee_schedule`, `employee_absence`, `customer`, `product`, `product_variant`,
`inventory`, `promotion`, `customer_order`, `order_item`, `payment`, `product_review`,
`announcement`, `support_ticket`, `competitor`, `market_price`, `postal_code` y `order_gift`.

Acceso (`Controllers/JamController.php`): solo cuentas **Administrador** (`admin`). Sin sesión se pueden leer
`promotion`, `state` y `season_theme`, que la tienda muestra a todos. `account_tier` (los
permisos de cada tipo de cuenta) solo se puede leer, ni el administrador la cambia. En `owner` y `employee`
se manda `password` y se guarda su hash; `password_hash` nunca se acepta ni se devuelve.

Las tablas internas (credenciales, sesiones, bitácoras, correos, visitas) no tienen CRUD:
se usan solo desde las rutas de la tienda.

### Tienda

| Método | Ruta | Acceso |
| --- | --- | --- |
| GET | `/store/catalog` | público (con ETag: si no cambió responde 304) |
| GET | `/store/products/{id}/reviews` | público |
| GET | `/store/products/{id}/also-bought` | público |
| POST | `/store/reviews` | token de cliente |
| GET | `/store/announcements` | público |
| GET | `/store/coupons/{code}` | público |
| GET | `/season_theme/current` | público |
| GET | `/postal-codes/{cp}` | público |
| POST | `/orders/checkout` | público (con sesión aplica los beneficios de la cuenta) |
| GET | `/me/orders` | token de cliente |
| GET/POST | `/support/tickets` | token de cliente |
| GET | `/market` | `priority` o `admin` |
| POST | `/analytics/ping`, `/analytics/leave`, `/analytics/search`, `/analytics/install` | público |

### Administración y base distribuida (solo `admin`)

`/admin/summary`, `/admin/customers`, `/admin/customers/{id}/tier`,
`/admin/customers/evaluate`, `/admin/orders`, `/admin/orders/{id}/status`,
`/admin/products/{id}`, `/admin/products/{id}/photo`, `/admin/products/{id}/seasons`,
`/admin/inventory`, `/admin/tickets`, `/admin/tickets/{id}`, `/admin/announcements`,
`/admin/emails`, `/admin/market`, `/admin/audit`, `/admin/analytics`,
`/admin/analytics/online`, `/nodes`, `/distributed/inventory`, `/distributed/orders`,
`/distributed/customers`, `/distributed/fragments`, `/replication/status`,
`/distributed/sync` y `/mirror/sync`.

Cada cambio del panel queda en `audit_log`.

Después de cada escritura correcta, `Middlewares/SyncMiddleware.php` refresca los fragmentos
de las sucursales y copia los cambios al espejo de Azure (lo mismo que hacía la v2).

## Seguridad

| Qué | Cómo |
| --- | --- |
| Quién puede hacer qué | Sin sesión: catálogo y lecturas públicas. `standard`/`priority`: solo sus propios datos. `admin`: panel y tablas. Cualquier otra cosa responde 401 (sin sesión) o 403 (sin permiso) |
| Nadie se vuelve administrador por la API | El registro siempre crea cuentas `standard` (ignora `id_account_tier` o `role` en el cuerpo). El CRUD de `customer` no asigna el tipo Administrador ni modifica o borra una cuenta Administrador (`Controllers/CustomerController.php`). `account_tier` es de solo lectura |
| Datos de otros clientes | `/me/orders`, `/support/tickets`, reseñas y compras toman el id de la sesión, nunca del cuerpo |
| Tokens | JWT firmado con `JWT_SECRET` (HS256). En la base solo se guarda su SHA-256. Vence, se revoca al cerrar sesión o al cambiar la contraseña, y el rol se vuelve a leer de la base en cada petición: cambiar el rol dentro del token no sirve |
| Contraseñas | bcrypt. `password_hash` nunca sale en una respuesta ni se acepta en el cuerpo |
| Fuerza bruta | Bloqueo por correo + IP, por IP y por cuenta (`login_attempts`), y límite de peticiones por IP en registro, login, recuperación, cupones, reseñas, tickets y compras |
| Inyección SQL | Todas las consultas usan parámetros; los nombres de columna salen de una lista fija del modelo y un campo desconocido responde 400 |
| Entradas | Cuerpo de máximo 1 MB, UTF-8 y JSON válido; cada campo se valida con su tipo, largo y CHECK de la base. Una lista u objeto donde va texto o número responde 400 |
| Compras | El cliente sale de la sesión; del cuerpo solo se usan `id_variant` y `quantity` (máximo 100 renglones). Precio, descuento, envío y existencias los calcula `dbo.sp_crear_pedido` en una transacción |
| Recuperar contraseña | Responde igual exista o no el correo; el enlace es de un solo uso, vence en 30 minutos, en la base solo va su SHA-256 y nunca apunta a otro sitio aunque se falsifique la cabecera `Host` |
| Errores | Los mensajes de los procedimientos (sin existencias, cupón vencido...) llegan a la página como 409; cualquier otro error interno responde `{"error":"Internal server error"}` sin detalles y el detalle solo va al log |
| Cabeceras | `nosniff`, `no-referrer`, `X-Frame-Options: DENY`, `Cache-Control: no-store` en todas las respuestas; CORS solo para la misma dirección por defecto; `REQUIRE_HTTPS=1` rechaza http |

Las pruebas `php tests/run.php` cubren estas reglas sin base de datos.

## Estructura

```
api/v3
├── index.php            arranque: autoloader y Application::boot()->run()
├── .htaccess            Apache: todo entra por index.php
├── build.php            genera Model, Schema, Controller y Router de cada tabla
├── Core/                settings, logger, base de datos (nodos), router, seguridad, errores
├── Middlewares/         cors -> seguridad -> error -> logging -> límite por IP -> sincronización -> auth
├── Controllers/         BaseController del profe, JamController (reglas de la tienda),
│                        uno por tabla y los de la tienda (Auth, Shop, Admin, Checkout...)
├── Models/              BaseModel (SQL Server) y uno por tabla
├── Schemas/             BaseSchema del profe y uno por tabla
├── Services/            tokens y la lógica de la tienda (catálogo, compras, panel, nodos)
├── Routers/             rutas, descubiertas automáticamente
└── tests/               pruebas unitarias (las del profe y las de la tienda)
```

Cada capa solo habla con la de al lado: los controladores no escriben SQL, los modelos no
conocen HTTP y los esquemas no tocan la base.

## Tablas nuevas o cambios en la base

Los archivos de cada tabla salen de `API_JAM/db_jam.sql`. Si cambias una tabla (o agregas
una), vuelve a generarlos:

```bash
cd api/v3
php build.php
```

Solo reescribe los archivos que llevan la marca `GENERATED`. Si un modelo necesita consultas
propias, se quita la marca y se escribe a mano: `build.php` ya no lo toca.

Para una entidad escrita a mano se siguen los mismos cuatro pasos del profe: `Models/`,
`Schemas/`, `Controllers/` y `Routers/`; el router se descubre solo.

## Pruebas

```bash
cd api/v3
php tests/run.php
```

No necesitan SQL Server: prueban el router, los middlewares, los esquemas, la seguridad, los
tokens y las reglas de acceso de la tienda.

## Límites conocidos

- La v3 necesita `users_tokens` (script 09) en el nodo central. Sin esa tabla no se puede
  iniciar sesión.
- Un token de la v2 no sirve en la v3: quien tenía sesión abierta con la v2 vuelve a iniciar
  sesión una vez (la página lo hace sola al recibir 401).
- `DELETE /store/catalog` responde 401 o 404 y no 405: la tabla `store` tiene su CRUD en
  `/store/{id}` y esa ruta también coincide.
