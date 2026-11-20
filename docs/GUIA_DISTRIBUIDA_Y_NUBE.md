# JAM Sport — Base de datos distribuida y despliegue en la nube

Tienda de ropa deportiva con **una tienda en línea y dos sucursales** (Tijuana y CDMX).
El proyecto cubre los cuatro puntos del pizarrón:

| # | Punto | Dónde está |
|---|-------|------------|
| 1 | UX/UI con media queries y menú de 4 opciones | `index.html`, `index.css` |
| 2 | Front End | `index.js`, `config.js` |
| 3 | Back End (API) | `api/v1/php/` |
| 4 | DB Manager: fragmentación vertical, fragmentación horizontal, replicación y espejo | `API_JAM/distribuida/` |

---

## 1. Arquitectura

```
LOCAL · SQL Server 2022 Developer
  JAM_db  (nodo central: publicador, catálogo maestro, dist.customer_private)
    ├─ fragmentos + pub_catalogo ─► JAM_tijuana   id_store = 1
    ├─ fragmentos + pub_catalogo ─► JAM_cdmx      id_store = 2
    ├─ fragmentos + pub_catalogo ─► JAM_online    id_store = 4  + customer_profile
    └─ pub_completa (internet) ───► Azure SQL

AZURE
  jam-sql-principal / JAM_cloud  (PRIMARY)
        │  geo-replicación = espejo
        ▼
  jam-sql-espejo / JAM_cloud     (SECONDARY)
  Listener del grupo: jam-fog.database.windows.net

USO
  Local: navegador ─► API PHP (php -S :8080) ─► JAM_db y nodos
  Nube:  Static Web Apps ─► App Service (API, solo lectura) ─► jam-fog (principal o espejo)
```

### 1.1 Fragmentación horizontal (`01_fragmentacion_horizontal.sql`)

| Relación | Tipo | Predicado | Nodo |
|----------|------|-----------|------|
| `employee`, `inventory`, `customer_order` | Primaria | `id_store = 1` / `2` / `4` | `JAM_tijuana` / `JAM_cdmx` / `JAM_online` |
| `order_item`, `payment` | Derivada | Siguen a su `customer_order` | El mismo nodo que el pedido |

- Cada tabla fragmento tiene un `CHECK (id_store = N)`: la base rechaza cualquier renglón que no cumpla el predicado.
- La relación global se reconstruye con `UNION ALL` en las vistas `dist.vw_inventory_global`, `dist.vw_customer_order_global` y `dist.vw_employee_global`.
- `dist.sp_refrescar_fragmentos_horizontales` distribuye los datos desde `JAM_db`.

### 1.2 Fragmentación vertical (`02_fragmentacion_vertical.sql`)

| Fragmento | Columnas | Nodo | Motivo |
|-----------|----------|------|--------|
| `customer_profile` | id, nivel, sucursal, nombre, apellidos, acepta publicidad | `JAM_online` | Lo que necesita la tienda en línea |
| `customer_private` | id, domicilio, nacimiento, sexo, teléfono, correo | `JAM_db.dist` | Datos personales protegidos en el nodo central |

Se reconstruye con `JOIN` por `id_customer` (vista `dist.vw_customer_reconstruido`). La API enmascara teléfono y correo al mostrarlos.

### 1.3 Replicación (`03_replicacion.sql`)

- **pub_catalogo**: replicación transaccional de las tablas de catálogo (`product`, `product_variant`, `store`, `promotion`…) de `JAM_db` a las 3 sucursales. Cada sucursal tiene su catálogo local y puede seguir vendiendo aunque el nodo central no responda.
- **pub_completa**: copia completa de `JAM_db` hacia Azure SQL (`JAM_cloud`).

### 1.4 Espejo (`04_espejo_azure.sql`)

Geo-replicación de Azure SQL con un **grupo de conmutación por error** (`jam-fog`): `JAM_cloud` en `jam-sql-principal` se copia continuamente en `jam-sql-espejo`, en otra región.

En el código (`core/Database.php`), si el servidor principal no responde, la API se conecta sola al espejo (`jam-fog.secondary.database.windows.net`). La pantalla **Base distribuida** lo muestra como “Conectado al espejo”.

---

## 2. Instalación local

### 2.1 SQL Server 2022 Developer

SQL Server Express no puede publicar replicación ni tiene SQL Server Agent, por eso se usa la edición Developer. Es gratuita y se instala junto a Express sin afectarlo.

1. Descargar **SQL Server 2022 Developer** desde la página oficial de Microsoft (*SQL Server downloads*).
2. Instalación **Personalizada** → *Nueva instalación independiente*.
3. Características: **Servicios de motor de base de datos** y **Replicación de SQL Server**.
4. Instancia: **predeterminada** (`MSSQLSERVER`). El servidor se llamará `localhost` (o el nombre del equipo, p. ej. `JONATHAN`).
5. En *Configuración del servidor*, poner el **Agente SQL Server** en inicio **Automático**.
6. Autenticación: Windows (se puede agregar el modo mixto).
7. Verificar en SSMS: `SELECT @@SERVERNAME, SERVERPROPERTY('Edition');` debe mostrar *Developer Edition*.

> Si `@@SERVERNAME` no coincide con el nombre real del equipo, la replicación falla. Se corrige con
> `EXEC sp_dropserver @@SERVERNAME; EXEC sp_addserver 'NOMBRE_REAL', 'local';` y reiniciando el servicio.

### 2.2 Base de datos y fragmentación

En SSMS, conectado a la instancia Developer, ejecutar en orden:

1. `API_JAM/db_jam.sql`
2. `API_JAM/distribuida/01_fragmentacion_horizontal.sql`
3. `API_JAM/distribuida/02_fragmentacion_vertical.sql`
4. `API_JAM/distribuida/06_pedidos_y_seguridad.sql` (procedimiento de pedidos y usuario `jam_api`; antes cambiar la contraseña marcada con `>>>`)
5. `API_JAM/distribuida/05_validacion.sql` → todas las columnas `diferencias` y `repetidos_entre_fragmentos` deben valer **0**.

> El usuario `jam_api` necesita que la instancia acepte autenticación de SQL Server:
> en SSMS, clic derecho en el servidor → *Propiedades → Seguridad → Modo de autenticación de SQL Server y Windows*, y reiniciar el servicio.

### 2.3 Replicación local

1. Confirmar que el servicio **SQL Server Agent (MSSQLSERVER)** está iniciado (Administrador de configuración de SQL Server).
2. Ejecutar `API_JAM/distribuida/03_replicacion.sql`. La suscripción a Azure se omite mientras `@azure_servidor` esté vacío.
3. Esperar 1–2 minutos y abrir **Replicación → Iniciar el Monitor de replicación** en SSMS.
4. Prueba rápida:
   ```sql
   UPDATE JAM_db.dbo.product SET base_price = 499.00 WHERE id_product = 1;
   -- unos segundos después:
   SELECT base_price FROM JAM_tijuana.dbo.product WHERE id_product = 1;   -- 499.00
   ```

### 2.4 API (PHP)

La API y la página se sirven juntas desde **Apache de XAMPP**: ya no hace falta `php -S`.

1. Copiar `api/v1/php/config/settings.example.php` como `settings.php` y ajustar `JAM_LOCAL_SERVER` (p. ej. `localhost`), `JAM_LOCAL_USER` = `jam_api` y `JAM_LOCAL_PASSWORD`.
2. Encender **Apache** en el panel de XAMPP.
3. Probar `http://localhost/ERROR404/api/v1/php/health-check` → `"database": "en_linea"` y `"pdo_sqlsrv": true`.

> Si Apache corre como servicio de Windows, la autenticación de Windows usa la cuenta del servicio y no la tuya: usa el usuario `jam_api` del script 06.
> Los archivos `.htaccess` mandan todas las rutas a `index.php` y bloquean el acceso directo a `config/`, `core/`, `models/`, `.git`, `API_JAM/` y `docs/`.

### 2.5 Tienda en línea

Abrir `http://localhost/ERROR404/`.

| Sección | Qué incluye |
|---------|-------------|
| **Inicio** | Banner de la temporada, beneficios, departamentos, lo más vendido, Juvenil / Adulto, cupones vigentes, novedades y mejor calificados |
| **Tienda** | Menú Novedades / Hombre / Mujer / Juvenil / Calzado / Ropa / Accesorios / Ofertas, buscador con sugerencias, filtros (departamento, género, edad, precio, talla, color, existencias, calificación) y orden (más vendidos, mejor calificados, precio, novedades) |
| **Producto** | Imagen que cambia con el color, tallas agotadas deshabilitadas, cantidad, favoritos, disponibilidad para envío y por sucursal, descripción, detalles, reseñas con estrellas y compra verificada, productos relacionados |
| **Carrito** | Cantidades, mover a favoritos, barra de avance para envío gratis, descuento de la cuenta |
| **Pago** | Inicio de sesión o registro, envío a domicilio o recoger en sucursal (con existencias por tienda), pago simulado con tarjeta, OXXO, transferencia o efectivo, cupón, confirmación con instrucciones de pago |
| **Mi cuenta** | Tipo de cuenta y sus beneficios, comunicados, pedidos con estado, artículos, entrega y pago |
| **Soporte** | Tickets con plazo de respuesta según el tipo de cuenta |
| **Administración** | Solo cuentas Administrador: resumen de ventas, pedidos, clientes, precios, inventario, soporte, comunicados y panel de la base distribuida |

La paleta cambia sola según la temporada; el selector **Temporada** del pie de página permite mostrar cualquiera en la presentación.

Cada temporada también tiene una animación de fondo, guardada en la columna `season_theme.effect`:

| Temporada | Efecto |
|---|---|
| Invierno | Nieve (`snow`) |
| Navidad | Nieve con luces de colores (`festive`) |
| Otoño | Hojas cayendo (`leaves`) |
| Primavera | Pétalos (`petals`) |
| Verano | Burbujas de luz que suben (`sun`) |
| Regreso a clases y El Buen Fin | Confeti con los colores de la campaña (`confetti`) |
| Black Friday | Chispas de neón (`sparks`) |

Las partículas se apartan del cursor (o del dedo en el celular) y el viento cambia de dirección al hacer scroll. La animación no recibe clics, se pausa cuando la pestaña no está visible y se puede apagar con **Animación de temporada** en el pie de página; si el sistema tiene activado "reducir movimiento", empieza apagada.

Para la presentación se puede abrir una temporada directamente: `http://localhost/ERROR404/?temporada=winter`.

### 2.6 Tipos de usuario

La tabla `account_tier` define tres tipos de cuenta. Los beneficios son datos de la tabla: cambiar un descuento o un tiempo de respuesta es un `UPDATE`.

| | Estándar | Prioritario | Administrador |
|---|---|---|---|
| Quién | Cualquier cliente que se registra | Cliente de mayoreo con más de un año | Personal de la tienda |
| Descuento permanente | — | 12 % | 12 % |
| Envío | Gratis desde $999, 3 a 5 días | Siempre gratis y prioritario, 1 a 2 días | Igual que Prioritario |
| Piezas por artículo | 10 | 200 (mayoreo) | 200 |
| Soporte | Respuesta en menos de 24 h | Atención inmediata, menos de 15 min | Igual que Prioritario |
| Comunicados | Generales | Generales y exclusivos | Todos, y los publica |
| Administración | — | — | Pedidos, clientes, precios, inventario, soporte, comunicados y base distribuida |

Cómo funciona en la base:

- `dbo.sp_crear_pedido` lee el tipo de cuenta del cliente: suma el descuento de la cuenta al del cupón (tope de 90 %), da envío gratis según `free_shipping_from`, rechaza más piezas de las permitidas (error 50016) y marca `customer_order.priority_shipping`.
- `dbo.sp_evaluar_clientes_prioritarios` sube a Prioritario a los clientes Estándar con al menos 365 días (`customer.customer_since`) y 50 piezas compradas en los últimos 12 meses. El botón **Aplicar regla** de Administración → Clientes lo ejecuta.
- `support_ticket.priority` y `respond_by` se calculan con el tipo de cuenta al abrir el ticket; la cola del administrador ordena primero los prioritarios y los que están por vencer.
- `announcement.audience = 'priority'` solo se entrega a cuentas Prioritario y Administrador.
- `customer.id_account_tier` y `customer_since` viajan en el fragmento vertical `customer_profile`; `account_tier` se replica con el catálogo.

Cuentas de demostración:

| Tipo | Correo | Contraseña |
|---|---|---|
| Estándar | `ana.ruiz@email.com` | `Estandar2026` |
| Prioritario | `luis.gomez@email.com` | `Prioritario2026` |
| Administrador | `admin@jamsport.mx` | `Admin2026JAM` |

> Cambia la contraseña del administrador si publicas la tienda en internet: entra como administrador, cierra sesión y usa **¿Olvidaste tu contraseña?** con `admin@jamsport.mx`; el enlace aparece en Administración → **Correos**.

### 2.6.1 Comparador de mercado (Prioritario y Administrador)

`#/mercado` compara cada producto JAM con productos equivalentes de Nike, Adidas, Puma e Ice Shaker:

- **Precio promedio por tipo de producto**: barras de nuestro precio de lista, el precio con el descuento de la cuenta y el promedio de la competencia.
- **Producto por producto**: cada punto naranja es un precio de la competencia y la franja gris su rango; los puntos azul y verde son nuestro precio de lista y el de la cuenta.
- **Detalle y fuentes**: tabla con el enlace a cada página donde se consultó el precio.

Los precios viven en `competitor` y `market_price` (sección 12 de `db_jam.sql`). Son precios de lista consultados el 19 de noviembre de 2026 en Nike.com, adidas.mx, puma.com, Liverpool y Mercado Libre; el administrador los actualiza en Administración → **Mercado** (al guardar se registra la fecha del día). Una cuenta Estándar que abre `#/mercado` ve la invitación a subir a Prioritario y la API le responde 403.

### 2.6.2 Outlet y equipo deportivo

- **Outlet** (`#/outlet`, enlace rojo del menú): 12 modelos de la temporada anterior de ropa, calzado y accesorios. Al entrar aparece el aviso *Todo al 30 % de descuento* con la advertencia de que no hay cambios ni devoluciones.
  - En la base es la columna `product.outlet_percent`. Con `NULL` el producto tiene precio normal; con un valor, ese es el descuento del outlet.
  - `dbo.sp_crear_pedido` cobra el precio de outlet y no le suma cupón ni el descuento de la cuenta.
  - Administración no deja marcar como *Devuelto* un pedido con artículos de outlet (error 409), y el botón *Devolución* no aparece en esos pedidos.
  - Los productos de outlet no se mezclan con la tienda normal: solo se ven en el outlet, en el buscador y en favoritos.
- **Equipo deportivo en todas las secciones**: 18 productos nuevos, con categorías nuevas *Lentes*, *Guantes* y *Balones y pelotas* y colores *Naranja*, *Amarillo neón* y *Café*.
  - Accesorios: lentes para correr, goggles de natación, dos gorras, guantes de gimnasio, de portero y de box, y balones de fútbol, básquetbol, voleibol, fútbol americano y pelotas de pádel.
  - Calzado: tacos de fútbol, tenis de entrenamiento para mujer y tenis juveniles.
  - Ropa: short de básquetbol, falda de tenis y playera térmica.
  - Hombre, Mujer, Juvenil y Adulto tienen ropa, calzado y accesorios. Cada producto tiene existencias en Tijuana, CDMX y en línea.

### 2.6.3 Otras funciones de la tienda

| Función | Dónde se ve | En la base |
|---|---|---|
| Gráficas de ventas | Administración → Resumen: ventas por día (30 días), por sucursal y por categoría | `/admin/summary` agrupa `customer_order` con hora del centro de México (`AT TIME ZONE`) |
| Seguimiento del pedido | Mi cuenta: pasos Recibido → Pago → En camino → Entregado con fecha y hora | `order_status_history`, llenada por el trigger `trg_customer_order_status_history`; quién cambió el estado sale de `SESSION_CONTEXT(N'id_customer')` |
| Correos | Administración → Correos (bienvenida, confirmación de pedido, cambio de estado, recuperación de contraseña) | `email_outbox`: la tienda no tiene SMTP, el correo se guarda como lo recibiría el cliente |
| Olvidé mi contraseña | Iniciar sesión → ¿Olvidaste tu contraseña? → enlace `#/restablecer?token=…` | `password_reset` guarda el SHA-256 del token; vence en 30 min, sirve una vez y cierra todas las sesiones |
| Aviso de privacidad, términos y devoluciones | Pie de página y casilla obligatoria al crear cuenta | `customer_credential.terms_accepted_at` |
| También compraron | Página de producto | Pedidos que comparten artículos (`order_item`); si no hay, los más vendidos del departamento |
| Vistos recientemente | Inicio y página de producto | Solo en el navegador (`localStorage`) |
| Cuenta regresiva | Banner de inicio durante Black Friday y Navidad | Fechas de `season_theme` |
| ¿Cuál es mi talla? | Página de producto (ropa y calzado) | Tabla de medidas en `js/sizes.js` |
| Búsqueda tolerante | Buscador y catálogo: “tenix”, “sudadra” | Distancia de edición en `js/search.js` |
| Instalable (PWA) | “Instalar aplicación” en Chrome/Edge | `manifest.webmanifest` y `sw.js`; requiere `https` o `localhost` |
| Vista previa al compartir | WhatsApp, Facebook, X | Etiquetas Open Graph e imagen `img/og-jam.png`. Al publicar en Azure cambia `og:image` a la dirección completa |

El script también carga un **historial de 32 ventas** de las últimas cuatro semanas en Tijuana, CDMX y en línea (fechas relativas al día en que se ejecuta), para que las gráficas y las recomendaciones tengan datos.

> Después de actualizar a esta versión hay que volver a ejecutar `db_jam.sql` y los scripts `01` a `06` de `API_JAM/distribuida` (en ese orden), porque se agregaron tablas, columnas y triggers.

### 2.7 Entrar desde el celular

**En la misma red Wi-Fi que la computadora**

1. En la computadora, abrir `cmd` y ejecutar `ipconfig`. Anotar la **Dirección IPv4** del adaptador Wi-Fi (p. ej. `192.168.1.70`).
2. Si Windows pregunta si permite a Apache HTTP Server comunicarse en redes privadas, aceptar. Si no aparece: *Firewall de Windows → Permitir una aplicación* → `C:\xampp\apache\bin\httpd.exe` en redes **privadas**.
3. En el celular abrir `http://192.168.1.70/ERROR404/`.

**Desde cualquier lugar (internet) para la presentación**

1. Instalar Cloudflare Tunnel: `winget install Cloudflare.cloudflared`.
2. Con Apache encendido, ejecutar `cloudflared tunnel --url http://localhost:80`.
3. Copiar la dirección `https://<algo>.trycloudflare.com` que aparece y abrir `https://<algo>.trycloudflare.com/ERROR404/` en cualquier celular.
4. Cerrar la ventana al terminar: la dirección deja de funcionar. Mientras está abierta, cualquiera con el enlace entra a la tienda; la administración y el panel distribuido piden una cuenta de Administrador.

La versión en Azure (sección 5) también se abre desde el celular, en modo consulta.

---

## 3. Endpoints nuevos de la API

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/nodes` | Estado, latencia, servidor y modo de cada nodo; en la nube incluye el enlace de geo-replicación |
| GET | `/distributed/inventory?node=tijuana` | Inventario leído del fragmento (sin `node` = todos) |
| GET | `/distributed/orders?node=cdmx` | Pedidos y artículos leídos del fragmento |
| GET | `/distributed/customers` | Clientes reconstruidos con los dos fragmentos verticales |
| GET | `/distributed/fragments` | Renglones de cada fragmento contra lo esperado según el nodo central |
| GET | `/replication/status` | Conteo y checksum del catálogo en el publicador contra cada suscriptor |
| POST | `/distributed/sync` | Ejecuta `dist.sp_sincronizar_nodos` |
| GET | `/season_theme` | Temporadas y campañas con su paleta de colores |
| GET | `/season_theme/current?date=AAAA-MM-DD` | Temporada vigente para esa fecha (campaña > estación) |
| GET | `/store/catalog` | Catálogo con variantes, existencias por tienda, calificación, unidades vendidas y precio de outlet (`outlet_percent`, `sale_price`) |
| GET | `/store/products/{id}/reviews` | Reseñas y distribución de estrellas |
| POST | `/store/reviews` | Publicar reseña (sesión; marca compra verificada) |
| POST | `/auth/register`, `/auth/login`, `/auth/logout` | Cuentas de cliente con contraseña bcrypt, bloqueo tras 5 intentos y token de sesión |
| GET | `/auth/me`, `/me/orders` | Perfil y pedidos del cliente con sesión (cada pedido incluye `history`) |
| POST | `/orders/checkout` | Registra un pedido completo con `dbo.sp_crear_pedido` (precios, cupón, descuento de la cuenta, total y stock se calculan en la base) |
| GET | `/store/announcements` | Comunicados vigentes; los exclusivos solo con sesión Prioritario o Administrador |
| GET, POST | `/support/tickets` | Tickets del cliente con sesión / abrir un ticket |
| GET | `/admin/summary` | Ventas, pedidos por surtir, tickets, clientes por tipo de cuenta y series para las gráficas (`sales_by_day`, `sales_by_store`, `sales_by_category`) |
| GET | `/admin/customers` | Clientes con tipo de cuenta, antigüedad y compras de 12 meses |
| PUT | `/admin/customers/{id}/tier` | Cambiar entre Estándar y Prioritario |
| POST | `/admin/customers/evaluate` | Ejecuta `dbo.sp_evaluar_clientes_prioritarios` |
| GET | `/admin/orders?status=` | Pedidos del nodo central, envío prioritario primero |
| PUT | `/admin/orders/{id}/status` | Cambiar estado; cancelar o devolver regresa el stock y reembolsa el pago |
| PUT | `/admin/products/{id}`, `/admin/inventory` | Precio de lista y existencias por sucursal |
| GET, PUT | `/admin/tickets`, `/admin/tickets/{id}` | Cola de soporte y respuesta |
| GET, POST, DELETE | `/admin/announcements` | Comunicados |
| GET | `/market` | Comparador de mercado por producto y por categoría (solo Prioritario y Administrador) |
| GET, POST, PUT, DELETE | `/admin/market`, `/admin/market/{id}` | Precios de la competencia |
| GET | `/admin/emails` | Bandeja de correos simulada (últimos 100) |
| POST | `/auth/forgot`, `/auth/reset` | Enlace para restablecer la contraseña y cambio de contraseña |
| GET | `/store/products/{id}/also-bought` | Productos comprados junto con este |

**Permisos:** las rutas `/admin/*`, `/nodes`, `/distributed/*`, `/replication/*` y el CRUD genérico de tablas requieren una sesión de Administrador (401 sin sesión, 403 con otra cuenta). Sin sesión solo quedan abiertos el catálogo, las reseñas, las temporadas, los cupones, los estados y el registro o inicio de sesión.

Todos los `GET` de catálogo aceptan paginación: `?page=1&page_size=100` (máximo 500) y no devuelven registros con `deleted = 1`.
Los `DELETE` de tablas con columna `deleted` hacen borrado lógico.

Códigos de respuesta:

| Código | Cuándo |
|--------|--------|
| 200 / 201 | Consulta, actualización o creación correcta |
| 400 | JSON inválido, campo obligatorio faltante o formato incorrecto |
| 401 / 403 | Sin sesión, o la cuenta no es de Administrador |
| 404 | El registro no existe o fue eliminado |
| 405 | Escritura en la réplica de solo lectura (Azure) |
| 409 | Duplicado, violación de FK o CHECK, cupón no vigente, sin stock, o registro modificado por otro usuario (`row_version`) |
| 500 / 503 | Error interno o nodo sin conexión; el detalle queda en el log del servidor |

Si un nodo fragmento no responde, la API lee sus datos del nodo por defecto filtrando por `id_store` (transparencia ante fallos). El campo `sources` de la respuesta indica de dónde salió cada parte.

---

## 4. Decisiones de diseño de la base de datos

| Decisión | Motivo |
|----------|--------|
| Collation `Modern_Spanish_CI_AS` en todas las bases (local y Azure) | Evita el error *Cannot resolve collation conflict* al comparar datos entre nodos y ordena correctamente en español |
| Fechas `DATETIME2(3)` en UTC (`SYSUTCDATETIME()`) | Los nodos están en zonas horarias distintas (Tijuana, CDMX, Azure); UTC hace comparables las marcas de tiempo |
| `NVARCHAR` en nombres, direcciones y descripciones | Soporta acentos y ñ sin depender de la página de códigos |
| FK completas en el nodo central; referencias lógicas en los fragmentos | El central garantiza la integridad global; un fragmento no puede referenciar tablas de otro nodo |
| Borrado lógico (`deleted`) con índices únicos filtrados (`WHERE deleted = 0`) | Conserva el historial y permite volver a registrar un correo o SKU de un registro eliminado |
| `CHECK (total = subtotal - discount)` | El total nunca puede quedar inconsistente, venga de donde venga el dato |
| `dbo.sp_crear_pedido` en una transacción | Pedido, artículos y descuento de stock se guardan completos o no se guardan; el `UPDATE ... WHERE stock >= cantidad` evita vender la misma pieza dos veces |
| Tabla `season_theme` (estaciones y campañas con colores y efecto validados por `CHECK`) | La paleta y la animación de la página son datos: se cambia una campaña, sus colores o su efecto con un `UPDATE`, sin tocar el código, y se replica a las sucursales con el catálogo |
| `category.department` y `product.age_segment` | Separan Calzado / Ropa / Accesorios y el público Juvenil (15–24) / Adulto (25–45) con valores controlados por `CHECK` |
| `product_review`, `customer_credential`, `customer_session` | Reseñas con una por cliente y producto (índice único filtrado); contraseñas solo como hash bcrypt y sesiones guardadas como SHA-256 del token, que además no se replican a la nube |
| `customer_order.delivery_type`, `shipping_cost`, `id_address` | Envío a domicilio (surte la tienda en línea) o recoger en sucursal (surte esa sucursal y su fragmento) |
| `ROWVERSION` en `product`, `inventory` y `customer_order` | Control de concurrencia optimista: un `PUT` con `row_version` viejo responde 409 en vez de sobrescribir |
| Índices en todas las FK no cubiertas | Evita recorridos completos de tabla en JOIN y en la validación de FK |
| Usuario `jam_api` sin permisos de DDL | Principio de mínimo privilegio para la aplicación |
| Tabla `account_tier` en lugar de valores fijos en el código | Los beneficios de cada tipo de cuenta se cambian con un `UPDATE` y el procedimiento de pedidos los aplica siempre igual |
| `customer_order.priority_shipping` guardado al crear el pedido | Si el cliente cambia de tipo de cuenta, sus pedidos anteriores conservan la prioridad con la que se compraron |

---

## 5. Despliegue en Azure

Requisitos: cuenta **Azure for Students** (correo escolar) y **Azure CLI** (`winget install Microsoft.AzureCLI`).

> Los nombres de servidor deben ser únicos en todo Azure: agregar un sufijo, p. ej. `jam-sql-principal-je`.
> Azure for Students solo permite algunas regiones: si una región da error de política, elegir otra.
> Revisar las regiones permitidas en *Directivas → Asignaciones* de la suscripción.

### 5.1 Servidores, base y espejo

```powershell
az login
$RG="rg-jam"; $A="westus2"; $B="westus3"; $PWD_SQL="<Contraseña-Fuerte-123!>"; $SUF="je"

az group create -n $RG -l $A

az sql server create -n jam-sql-principal-$SUF -g $RG -l $A -u jamadmin -p $PWD_SQL
az sql server create -n jam-sql-espejo-$SUF    -g $RG -l $B -u jamadmin -p $PWD_SQL

# Permitir servicios de Azure (App Service) y la IP de tu casa en ambos servidores
foreach ($s in "jam-sql-principal-$SUF","jam-sql-espejo-$SUF") {
  az sql server firewall-rule create -g $RG -s $s -n AzureServices --start-ip-address 0.0.0.0 --end-ip-address 0.0.0.0
  az sql server firewall-rule create -g $RG -s $s -n MiIP --start-ip-address <TU_IP_PUBLICA> --end-ip-address <TU_IP_PUBLICA>
}

az sql db create -g $RG -s jam-sql-principal-$SUF -n JAM_cloud --service-objective Basic --backup-storage-redundancy Local --collation Modern_Spanish_CI_AS

# Espejo: grupo de conmutación por error
az sql failover-group create -n jam-fog-$SUF -g $RG -s jam-sql-principal-$SUF `
   --partner-server jam-sql-espejo-$SUF --add-db JAM_cloud --failover-policy Manual
```

Puntos de conexión resultantes:
- Lectura/escritura: `jam-fog-<sufijo>.database.windows.net`
- Espejo (solo lectura): `jam-fog-<sufijo>.secondary.database.windows.net`

### 5.2 Replicación hacia Azure

1. En `03_replicacion.sql` llenar `@azure_servidor` (listener `jam-fog-<sufijo>.database.windows.net`), `@azure_usuario` y `@azure_password`.
2. Ejecutar el script completo otra vez: lo que ya existe se omite y solo se crea la suscripción a Azure.
3. En unos minutos `JAM_cloud` tendrá todas las tablas. Comprobar con `04_espejo_azure.sql` conectado al listener desde SSMS (autenticación SQL, *Cifrar: Obligatorio*).

> Si la red de la escuela bloquea el puerto 1433 de salida, hacer este paso desde casa o con datos del celular.

### 5.3 API en App Service

```powershell
$APP="jam-api-$SUF"
az appservice plan create -n plan-jam -g $RG -l $A --is-linux --sku B1
az webapp create -n $APP -g $RG -p plan-jam --runtime "PHP:8.3"

az webapp config appsettings set -n $APP -g $RG --settings `
  JAM_DEFAULT_NODE=cloud JAM_ENABLED_NODES=cloud JAM_READ_ONLY=1 `
  JAM_CLOUD_SERVER=jam-fog-$SUF.database.windows.net `
  JAM_CLOUD_MIRROR_SERVER=jam-fog-$SUF.secondary.database.windows.net `
  JAM_CLOUD_DB=JAM_cloud JAM_CLOUD_USER=jamadmin JAM_CLOUD_PASSWORD=$PWD_SQL

az webapp config set -n $APP -g $RG --startup-file "bash /home/site/wwwroot/azure/startup.sh"

# Publicar el contenido de api/v1/php
cd C:\xampp\htdocs\ERROR404\api\v1\php
Compress-Archive -Path * -DestinationPath ..\api.zip -Force
az webapp deploy -n $APP -g $RG --src-path ..\api.zip --type zip
```

- Si `PHP:8.3` no aparece, revisar los disponibles con `az webapp list-runtimes --os linux`.
- Comprobar `https://jam-api-<sufijo>.azurewebsites.net/health-check` → `"pdo_sqlsrv": true`.
- No activar CORS en el portal de App Service: la API ya envía sus encabezados CORS y duplicarlos hace que el navegador rechace la respuesta.
- `settings.php` no se sube: en Azure la configuración sale de las variables de entorno.
- El plan B1 tiene costo (se cubre con el crédito de estudiante). Apagar o borrar el grupo al terminar: `az group delete -n rg-jam`.

### 5.4 Frontend en Static Web Apps

1. En `config.js` cambiar `AZURE_API_URL` por `https://jam-api-<sufijo>.azurewebsites.net` y subir el cambio a GitHub.
2. Portal de Azure → **Static Web Apps → Crear** → plan **Free** → origen **GitHub** → repo `ERROR404`, rama `main`.
3. Detalles de compilación: *Custom*, **App location** `/`, **Output location** vacío.
4. Azure crea un workflow de GitHub Actions; al terminar, la URL pública queda en la página del recurso.

En la nube la tienda funciona en modo consulta: el catálogo, el inventario y los pedidos se leen de la réplica `JAM_cloud`. Los pedidos nuevos se registran en el nodo central local, que los replica hacia Azure.

---

## 6. Guion para la presentación (≈ 8 min)

1. **Tienda** (móvil y escritorio): mostrar el menú hamburguesa y la cuadrícula adaptable (media queries).
2. **Tipos de usuario**: entrar como Estándar y como Prioritario y comparar precio, envío, piezas permitidas y plazo de soporte; luego entrar como Administrador, responder el ticket y aplicar la regla de Prioritarios.
3. **Comparador de mercado**: como Prioritario, botón **Comparador** del encabezado → filtrar *Calzado* y pasar el cursor sobre los puntos para ver cada marca y su fuente.
4. **Seguimiento**: como Administrador, marcar un pedido como *Enviado* → en Mi cuenta del cliente aparece el paso con su hora y en **Correos** el aviso; en SSMS `SELECT * FROM dbo.order_status_history ORDER BY id_history DESC` muestra quién lo cambió.
5. **Fragmentación horizontal**: Administración → **Base distribuida** → **Inventario** → pestañas por nodo. En SSMS, `SELECT * FROM JAM_cdmx.dbo.inventory` muestra solo `id_store = 2`, e intentar insertar `id_store = 1` ahí falla por el `CHECK`.
6. **Pedido distribuido**: comprar en la tienda eligiendo *Sucursal Tijuana* → el pedido aparece en **Pedidos** dentro del fragmento de Tijuana.
7. **Fragmentación vertical**: **Base distribuida** → tabla de clientes con las columnas de cada fragmento en distinto color.
8. **Transparencia ante fallos**: desconectar una sucursal (`ALTER DATABASE JAM_cdmx SET OFFLINE WITH ROLLBACK IMMEDIATE;`), actualizar **Inventario** → CDMX aparece como “nodo sin conexión, leído de central”. Volver con `SET ONLINE`.
9. **Replicación**: cambiar un precio en `JAM_db` → la matriz de **Replicación del catálogo** pasa a “Sincronizado” en todos los nodos.
10. **Espejo**: `az sql failover-group set-primary --name jam-fog-<sufijo> --resource-group rg-jam --server jam-sql-espejo-<sufijo>` → en la API de Azure, **Base distribuida** muestra el otro servidor como principal.

---

## 7. Problemas comunes

| Síntoma | Causa / solución |
|---------|------------------|
| `could not find driver` | Falta `pdo_sqlsrv` en XAMPP: copiar la DLL de *Microsoft Drivers for PHP for SQL Server* que coincida con PHP 8.2 TS x64 y habilitarla en `php.ini`. |
| `SSL Provider: certificate chain not trusted` | ODBC Driver 18 cifra por defecto; la configuración local ya agrega `TrustServerCertificate=yes`. |
| `Cannot open database "JAM_tijuana"` | Falta ejecutar `01_fragmentacion_horizontal.sql` en la instancia que usa la API. |
| La replicación no avanza | SQL Server Agent detenido, o `@@SERVERNAME` distinto del nombre del equipo (ver 2.1). |
| Azure: `Client with IP address … is not allowed` | Agregar la IP a las reglas de firewall de **ambos** servidores. |
| El navegador muestra error CORS | Revisar que la URL de `config.js` sea correcta y que la API responda `/health-check`. |
