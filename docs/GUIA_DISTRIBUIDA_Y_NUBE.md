# JAM Sport — Base de datos distribuida y despliegue en la nube

Tienda de ropa deportiva con **una tienda en línea y dos sucursales** (Tijuana y CDMX).
El proyecto cubre los cuatro puntos del pizarrón:

| # | Punto | Dónde está |
|---|-------|------------|
| 1 | UX/UI con media queries y menú de 4 opciones | `index.html`, `index.css` |
| 2 | Front End | `js/` (`app.js` y `views/`), `config.js` |
| 3 | Back End (API) | `api/v3/` |
| 4 | DB Manager: fragmentación vertical, fragmentación horizontal, replicación y espejo | `API_JAM/distribuida/` |

---

## 1. Arquitectura

```
MÁQUINA VIRTUAL EN AZURE · SQL Server 2022 Developer (en la laptop, Express para programar)
  JAM_db  (nodo central: publicador, catálogo maestro, dist.customer_private)
    ├─ fragmentos + pub_catalogo ─► JAM_tijuana   id_store = 1
    ├─ fragmentos + pub_catalogo ─► JAM_cdmx      id_store = 2
    ├─ fragmentos + pub_catalogo ─► JAM_online    id_store = 4  + customer_profile
    └─ copia de la API después de cada escritura ─► Azure SQL

AZURE
  jam-sql-principal / JAM_cloud  (PRIMARY)
        │  geo-replicación = espejo
        ▼
  jam-sql-espejo / JAM_cloud     (SECONDARY)
  Listener del grupo: jam-fog.database.windows.net

USO
  Laptop: navegador ─► Apache de XAMPP ─► API PHP ─► JAM_db y nodos
  Nube:   navegador o app ─► https (Caddy) ─► Apache de la VM ─► API PHP ─► JAM_db y nodos
                                                         └─► jam-fog (espejo cuando una sucursal se cae)
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
- **pub_completa**: publicación de todas las tablas de `JAM_db` para un suscriptor en Azure SQL. Queda sin suscriptor porque `JAM_cloud` ya recibe la copia de la API (sección 5.2); las dos a la vez chocarían.

### 1.4 Espejo (`04_espejo_azure.sql`)

Geo-replicación de Azure SQL con un **grupo de conmutación por error** (`jam-fog`): `JAM_cloud` en `jam-sql-principal` se copia continuamente en `jam-sql-espejo`, en otra región.

En el código (`core/Database.php`), si el servidor principal no responde, la API se conecta sola al espejo (`jam-fog.secondary.database.windows.net`). La pantalla **Base distribuida** lo muestra como “Conectado al espejo”.

Cómo llegan los datos a Azure: la API copia `JAM_db` a `JAM_cloud` (`models/mirror.php`). Después de cada compra o cambio solo copia lo que cambió (columna `updated_at`), y el botón **Copiar al espejo (Azure)** del panel copia todo. Las tablas del espejo se crean con la sección 0 de `04_espejo_azure.sql`. Funciona igual con SQL Server Express y con Developer.

### 1.5 Transparencia ante fallos (`07_tolerancia_fallos.sql`)

Objetivo: si se cae un servidor (una sucursal), la tienda y el panel siguen trabajando y los datos de ese nodo se toman de otra copia.

```
Consulta del fragmento Tijuana (id_store = 1)
   1. JAM_tijuana ............ ¿responde? → se usa (origen "fragmento")
   2. Azure JAM_cloud ........ espejo, mismo predicado id_store = 1 (origen "espejo:cloud")
        └─ si jam-sql-principal no responde → jam-sql-espejo (solo lectura)
   3. JAM_db (central) ....... mismo predicado id_store = 1 (origen "respaldo:central")
```

- **Escrituras**: los pedidos, el inventario y los clientes se escriben en el nodo central, así que vender no depende de la sucursal.
- **Sincronización tolerante**: `dist.sp_sincronizar_nodos` refresca cada nodo en su propia transacción con `TRY…CATCH`. Si un nodo está fuera de línea no detiene a los demás: queda marcado como **pendiente** en `dist.node_sync` con el error.
- **Recuperación**: en cuanto el nodo vuelve a responder, la API lo detecta al abrir el panel (`GET /nodes`) y lo pone al día sola.
- **En SSMS**: `dist.vw_estado_nodos` muestra el estado de cada base, y `dist.sp_consulta_global` reconstruye la relación con `UNION ALL` sustituyendo el nodo caído por su copia en el central (la vista normal `dist.vw_inventory_global` falla con el error 942).
- **En el panel**: un aviso dice qué nodo está caído y quién responde por él; cada tarjeta muestra **Lecturas** (Su fragmento / Espejo Azure / Nodo central) y **Sincronización** (Al día / Pendiente / Puesto al día).
- Si Azure no está configurado o no hay internet, el respaldo es el nodo central. Cuando Azure falla, la API deja de intentarlo durante 30 s para no hacer esperar cada petición.

---

## 2. Instalación local

### 2.1 SQL Server 2022 Developer

SQL Server Express no puede publicar replicación ni tiene SQL Server Agent, por eso se usa la edición Developer. Es gratuita y se instala junto a Express sin afectarlo.

1. Descargar **SQL Server 2022 Developer** desde `https://aka.ms/sqlserver2022developer` (descarga oficial de Microsoft). No usar la 2025: cambió el cifrado de las conexiones internas y la replicación puede fallar sin un certificado de confianza.
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
4. `API_JAM/distribuida/06_pedidos_y_seguridad.sql` (procedimiento de pedidos y usuario `jam_api`; antes escribir la contraseña de `jam_api` en la línea marcada con `>>>` y borrarla después de ejecutar)
5. `API_JAM/distribuida/07_tolerancia_fallos.sql` (estado de los nodos y consulta tolerante a fallos)
6. `API_JAM/distribuida/08_catalogo_temporadas.sql` (37 productos nuevos por temporada y la tabla `product_season`; solo agrega datos y se puede repetir)
7. `API_JAM/distribuida/05_validacion.sql` → todas las columnas `diferencias` y `repetidos_entre_fragmentos` deben valer **0**.

`04_espejo_azure.sql` no se ejecuta aquí: se ejecuta conectado a Azure (sección 5.2).

> El usuario `jam_api` necesita que la instancia acepte autenticación de SQL Server:
> en SSMS, clic derecho en el servidor → *Propiedades → Seguridad → Modo de autenticación de SQL Server y Windows*, y reiniciar el servicio.

### 2.3 Replicación local

1. Confirmar que el servicio **SQL Server Agent (MSSQLSERVER)** está iniciado (Administrador de configuración de SQL Server).
2. Dar permiso de escritura al Agente en la carpeta de instantáneas (sin esto el snapshot falla con *Access to the path … is denied* y las sucursales se quedan en *The initial snapshot … is not yet available*):
   ```powershell
   icacls "C:\Program Files\Microsoft SQL Server\MSSQL16.MSSQLSERVER\MSSQL\ReplData" /grant "NT SERVICE\SQLSERVERAGENT:(OI)(CI)M" /T
   ```
3. Ejecutar `API_JAM/distribuida/03_replicacion.sql` (escribir antes la contraseña del distribuidor en la línea `>>>`). La suscripción a Azure se omite mientras `@azure_servidor` esté vacío.
4. Esperar 1–2 minutos y abrir **Replicación → Iniciar el Monitor de replicación** en SSMS.
5. Prueba rápida:
   ```sql
   UPDATE JAM_db.dbo.product SET base_price = 499.00 WHERE id_product = 1;
   -- unos segundos después:
   SELECT base_price FROM JAM_tijuana.dbo.product WHERE id_product = 1;   -- 499.00
   ```

### 2.4 API (PHP)

La API y la página se sirven juntas desde **Apache de XAMPP**: ya no hace falta `php -S`.

La página usa **`api/v3`**, la API con la estructura del profe (`Controllers`, `Core`, `Middlewares`, `Models`, `Routers`, `Schemas`, `Services` y `tests`). Su estructura, rutas, ajustes y pruebas están en `api/v3/README.md`.

1. Copiar `api/v3/.env.example` como `.env`, poner una `JWT_SECRET` propia y ajustar `JAM_LOCAL_SERVER` (p. ej. `localhost`). Sin `JAM_LOCAL_USER` se usa la autenticación de Windows; con Apache como servicio, `JAM_LOCAL_USER` = `jam_api` y su `JAM_LOCAL_PASSWORD`.
2. Encender **Apache** en el panel de XAMPP.
3. Probar `http://localhost/ERROR404/api/v3/health` → `{"status":"ok"}` y correr `C:\xampp\php\php.exe api\v3\tests\run.php`.

> Si Apache corre como servicio de Windows, la autenticación de Windows usa la cuenta del servicio y no la tuya: usa el usuario `jam_api` del script 06.
> El `.htaccess` de `api/v3` manda todas las rutas a `index.php`, así que el código, las pruebas y el `.env` no se pueden abrir desde el navegador. El de la raíz bloquea `.git`, `API_JAM/` y `docs/`.

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
- `dbo.sp_evaluar_clientes_prioritarios` sube a Prioritario a los clientes Estándar con más de 365 días (`customer.customer_since`) y más de $50,000 en pedidos pagados, enviados o entregados. El botón **Aplicar regla** de Administración → Clientes lo ejecuta, y cada cliente puede subir su cuenta desde **Cómo ser Prioritario** (`POST /me/priority/upgrade`).
- `support_ticket.priority` y `respond_by` se calculan con el tipo de cuenta al abrir el ticket; la cola del administrador ordena primero los prioritarios y los que están por vencer.
- `announcement.audience = 'priority'` solo se entrega a cuentas Prioritario y Administrador.
- `customer.id_account_tier` y `customer_since` viajan en el fragmento vertical `customer_profile`; `account_tier` se replica con el catálogo.

Cuentas de demostración (las contraseñas las tiene el equipo; no se publican en el repositorio):

| Tipo | Correo |
|---|---|
| Estándar | `ana.ruiz@email.com` |
| Prioritario | `luis.gomez@email.com` |
| Administrador | `admin@jamsport.mx` |

> Antes de publicar la tienda en internet cambia la contraseña del administrador: entra como administrador, cierra sesión y usa **¿Olvidaste tu contraseña?** con `admin@jamsport.mx`; el enlace aparece en Administración → **Correos**.

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

### 2.6.3 Escáner, código postal y empaque de regalo

- **Escáner de la app** (`#/escanear`, botón *Escanear* de la barra inferior del celular):
  - Lee el código de barras EAN-13 de la etiqueta con la cámara, o con una foto del código.
  - Muestra las existencias en línea, en Tijuana y en CDMX, el precio y el botón para agregarlo al carrito.
  - Las cuentas Prioritario ven además la comparación con la competencia: una gráfica y la lista de precios con su fuente.
  - Cada variante tiene su código en `product_variant.barcode` (750 + 1234 + id de la variante + dígito verificador).
  - Las etiquetas para imprimir o mostrar en otra pantalla están en Administración → **Etiquetas**.
  - La cámara funciona en Chrome para Android y solo en una dirección segura: `https` (el túnel de la sección 2.7) o `localhost`. En los demás casos se escribe el código, el SKU o el nombre.
- **Código postal en el pago**: al escribir los 5 dígitos, la página llena la ciudad y el estado y sugiere las colonias.
  - Los datos vienen de la tabla `postal_code`: 388 asentamientos de Tijuana y de las alcaldías Cuauhtémoc y Benito Juárez de la CDMX, tomados del catálogo de SEPOMEX publicado en micodigopostal.org.
  - Con un código postal que no está en la tabla, el cliente escribe los datos a mano.
- **Empaque especial JAM** ($30):
  - Al pulsar *Pagar* en el carrito, la tienda pregunta si es para regalo.
  - En el pago se elige la ocasión (cumpleaños, amistad o Navidad) y uno de 8 colores de papel, con vista previa.
  - El regalo puede ir solo envuelto o con tarjeta "Para" y dedicatoria.
  - Llega al domicilio que se escriba en Entrega (puede ser el de quien lo recibe) o se recoge en sucursal.
  - `dbo.sp_crear_pedido` cobra `customer_order.gift_cost` y guarda los datos en `order_gift`. El `CHECK` del total ahora es subtotal − descuento + envío + empaque.
  - El regalo aparece en Mi cuenta, en la confirmación, en el correo y en Administración → Pedidos.

### 2.6.4 Otras funciones de la tienda

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
| Colección por temporada | Inicio: pestañas Primavera, Verano, Otoño, Invierno, Regreso a clases y Navidad; catálogo: filtro *Temporada* | `product_season` (relación N a N entre `product` y `season_theme`), creada por `08_catalogo_temporadas.sql` |
| Ofertas del outlet | Inicio, carrusel con los productos de mayor descuento | `product.outlet_percent` |
| Fotos reales | Tarjetas, página de producto y mosaicos de departamentos | Archivos en `img/productos`; `descargar_fotos.ps1` baja 73 fotos de Unsplash (créditos en `img/productos/CREDITOS.md`) |

El script también carga un **historial de 32 ventas** de las últimas cuatro semanas en Tijuana, CDMX y en línea (fechas relativas al día en que se ejecuta), para que las gráficas y las recomendaciones tengan datos.

> Después de actualizar a esta versión hay que volver a ejecutar `db_jam.sql` y los scripts `01`, `02`, `06` y `07` de `API_JAM/distribuida` (en ese orden), porque se agregaron tablas, columnas y triggers.

### 2.6.5 Visitas, búsquedas y descargas (Administrador)

**Administración → Visitas** (solo cuentas de Administrador):

| Apartado | Qué muestra |
|---|---|
| Conectados ahora | Visitantes con actividad en los últimos 90 s, separados en **Página web** (navegador) y **Aplicación móvil** (app instalada), cuántos tienen sesión, por dispositivo (teléfono, tableta, computadora) y qué página tienen abierta. Se actualiza cada 15 s |
| Visitas por periodo | Hoy, esta semana (desde el lunes), este mes y este año, cada uno con web y app |
| Visitas por día y por mes | Últimos 30 días y últimos 12 meses; el tooltip separa web y app |
| Lo más buscado | Los 10 términos más buscados del periodo elegido, cuántas búsquedas vinieron de la app y los términos que **no encontraron** productos |
| Descargas de la app | Total de instalaciones, de hoy, la semana, el mes y el año, y por sistema (Android, iPhone y iPad, Windows, Mac) |

Cómo se mide (`js/analytics.js` y `models/analytics.php`):
- El navegador genera un identificador aleatorio (`localStorage`) y avisa a la API al abrir la tienda, al cambiar de página y cada 45 s mientras la pestaña está a la vista. Al cerrar o cambiar de pestaña avisa que se fue (`sendBeacon`). No se guarda la IP.
- **Web o app**: si la tienda se abrió instalada (`display-mode: standalone`) cuenta como app; si no, como página web.
- **Visita**: se abre con la primera señal y se cierra tras 30 minutos sin actividad (`site_visit`).
- **Búsquedas**: el catálogo registra el término (sin acentos ni mayúsculas) y cuántos productos encontró (`search_log`).
- **Descargas**: el evento `appinstalled` del navegador, o la primera vez que la app se abre instalada en iPhone/iPad, que no envían ese evento (`app_install`, una fila por dispositivo).
- `db_jam.sql` carga un año de **datos de ejemplo** (unas 9,000 visitas, 1,500 búsquedas y 640 descargas) con fechas relativas al día en que se ejecuta. Las visitas reales se suman a esas.
- Son tablas del nodo central: no se fragmentan ni se copian al espejo.

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
| GET | `/nodes` | Estado, latencia, servidor y modo de cada nodo, quién atiende sus lecturas y si está pendiente; pone al día los nodos que volvieron. En la nube incluye el enlace de geo-replicación |
| GET | `/distributed/inventory?node=tijuana` | Inventario leído del fragmento (sin `node` = todos); si el nodo está caído, del espejo o del central (`source`) |
| GET | `/distributed/orders?node=cdmx` | Pedidos y artículos leídos del fragmento |
| GET | `/distributed/customers` | Clientes reconstruidos con los dos fragmentos verticales |
| GET | `/distributed/fragments` | Renglones de cada fragmento contra lo esperado según el nodo central |
| GET | `/replication/status` | Conteo y checksum del catálogo en el publicador contra cada suscriptor |
| POST | `/distributed/sync` | Ejecuta `dist.sp_sincronizar_nodos` y devuelve los nodos pendientes |
| POST | `/mirror/sync` | Copia los cambios de `JAM_db` a `JAM_cloud` en Azure (`?full=1` copia todo) |
| GET | `/season_theme` | Temporadas y campañas con su paleta de colores |
| GET | `/season_theme/current?date=AAAA-MM-DD` | Temporada vigente para esa fecha (campaña > estación) |
| GET | `/store/catalog` | Catálogo con variantes, existencias por tienda, calificación, unidades vendidas y precio de outlet (`outlet_percent`, `sale_price`) |
| GET | `/store/products/{id}/reviews` | Reseñas y distribución de estrellas |
| POST | `/store/reviews` | Publicar reseña (sesión; marca compra verificada) |
| POST | `/auth/register`, `/auth/login`, `/auth/logout` | Cuentas de cliente con contraseña bcrypt, bloqueo tras 5 intentos y token de sesión |
| GET | `/auth/me`, `/me/orders` | Perfil y pedidos del cliente con sesión (cada pedido incluye `history`) |
| POST | `/orders/checkout` | Registra un pedido completo con `dbo.sp_crear_pedido` (precios, cupón, descuento de la cuenta, empaque de regalo opcional con `gift: {occasion, color, recipient, message}`, total y stock se calculan en la base) |
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
| POST | `/analytics/ping`, `/analytics/leave` | Señal de visita (cada 45 s y al cambiar de página) y salida del visitante |
| POST | `/analytics/search`, `/analytics/install` | Término buscado con su número de resultados; instalación de la app |
| GET | `/admin/analytics` | Conectados, visitas por periodo, por día y por mes, lo más buscado y descargas |
| GET | `/admin/analytics/online` | Solo los conectados (el panel lo consulta cada 15 s) |
| POST | `/auth/forgot`, `/auth/reset` | Enlace para restablecer la contraseña y cambio de contraseña |
| GET | `/store/products/{id}/also-bought` | Productos comprados junto con este |
| GET | `/postal-codes/{cp}` | Estado, ciudad y colonias de un código postal (404 si no está en la tabla) |

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
$RG="rg-jam"; $A="westus2"; $B="westus"; $PWD_SQL="<Contraseña-Fuerte-123!>"; $SUF="je"

az group create -n $RG -l $A

az sql server create -n jam-sql-principal-$SUF -g $RG -l $A -u jamadmin -p $PWD_SQL
az sql server create -n jam-sql-espejo-$SUF    -g $RG -l $B -u jamadmin -p $PWD_SQL

# Permitir servicios de Azure (incluida la máquina virtual) y la IP de tu casa en ambos servidores
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

### 5.2 Datos hacia Azure

La API copia `JAM_db` a `JAM_cloud` (`api/v3/Services/MirrorService.php`). Funciona igual con SQL Server Express (laptop) y con Developer (máquina virtual).

1. En SSMS conectarse a `jam-fog-<sufijo>.database.windows.net` (autenticación SQL `jamadmin`, *Cifrar: Obligatorio*), elegir la base `JAM_cloud` y ejecutar `API_JAM/distribuida/04_espejo_azure.sql`. La sección 0 crea las tablas del espejo.
2. En `api/v3/.env` llenar:
   ```
   JAM_CLOUD_SERVER=jam-fog-<sufijo>.database.windows.net
   JAM_CLOUD_MIRROR_SERVER=jam-fog-<sufijo>.secondary.database.windows.net
   JAM_CLOUD_DB=JAM_cloud
   JAM_CLOUD_USER=jamadmin
   JAM_CLOUD_PASSWORD=<la contraseña de Azure>
   ```
3. Administración → **Base distribuida** → **Copiar al espejo (Azure)**. La tarjeta *Azure SQL* debe mostrar la hora de la copia y el estado del espejo (`jam-sql-espejo`).
4. Comprobar en Azure con las consultas 5 de `04_espejo_azure.sql`: renglones por `id_store` iguales a los de cada sucursal.

A partir de ahí cada compra o cambio se copia solo a Azure al terminar la petición, y Azure lo pasa a `jam-sql-espejo` por geo-replicación.

En `03_replicacion.sql`, `@azure_servidor` se deja vacío: si la publicación `pub_completa` también escribiera en `JAM_cloud`, chocaría con la copia de la API.

> Si la red de la escuela bloquea el puerto 1433 de salida, hacer este paso desde casa o con datos del celular. En clase, si no hay salida a Azure, el respaldo es el nodo central y la demostración funciona igual.

### 5.3 Máquina virtual Windows

La tienda, la API y las cuatro bases corren en una VM `Standard_B2als_v2` (2 núcleos, 4 GB) con Windows Server 2022, igual que en la laptop pero con SQL Server Developer.

```powershell
$VM="jam-vm-cl"; $VMREG="chilecentral"; $PWD_VM="<Otra-Contraseña-Fuerte-123!>"
az vm create -g $RG -n $VM -l $VMREG --image Win2022Datacenter --size Standard_B2als_v2 --admin-username jamadmin --admin-password $PWD_VM --public-ip-address-dns-name jam-sport-$SUF --storage-sku StandardSSD_LRS
az vm open-port -g $RG -n $VM --port 80,443 --priority 900

# La VM también necesita entrar a Azure SQL
$VMIP=(az vm show -d -g $RG -n $VM --query publicIps -o tsv)
foreach ($s in "jam-sql-principal-$SUF","jam-sql-espejo-$SUF") {
  az sql server firewall-rule create -g $RG -s $s -n MaquinaVirtual --start-ip-address $VMIP --end-ip-address $VMIP
}
```

- Entrar con Escritorio remoto: `mstsc /v:jam-sport-<sufijo>.chilecentral.cloudapp.azure.com`, usuario `jamadmin`.
- Antes de elegir región y tamaño: `az vm list-skus -l <región> --resource-type virtualMachines` y quedarse con los que no tienen restricciones. En Canadá Central la cuenta de estudiante solo ofrecía tamaños ARM (con *p* en el nombre), que no corren Windows Server; en Chile Central sí hay `B2als_v2`.
- Chile Central no tiene apagado automático (`Microsoft.DevTestLab/schedules`). Al terminar: `az vm deallocate -g rg-jam -n jam-vm-cl` (deja de cobrar; apagarla desde Windows no). Encenderla: `az vm start -g rg-jam -n jam-vm-cl`.

### 5.4 Instalación en la máquina virtual

1. SQL Server 2022 Developer (instalación *Custom*): **Database Engine Services** y **SQL Server Replication**, instancia predeterminada, Agente en **Automatic**, collation `Modern_Spanish_CI_AS` (*Windows collation designator* `Modern_Spanish` + *Accent-sensitive*), **Mixed Mode** y *Max Server Memory* 2048 MB (la VM tiene 4 GB). Después SSMS.
2. XAMPP 8.2 (solo Apache y PHP) en `C:\xampp` y Git for Windows.
3. Proyecto (si la descarga se corta, usar OpenSSL y solo la última versión):
   ```powershell
   git config --global http.sslBackend openssl
   cd C:\xampp\htdocs
   git clone --depth 1 -b mejoras-tienda https://github.com/angelblandito/ERROR404.git
   ```
4. Conector de PHP: `php_pdo_sqlsrv-5.12.0-8.2-ts-vs16-x64.zip` de `downloads.php.net/~windows/pecl/releases/pdo_sqlsrv/`, copiar `php_pdo_sqlsrv.dll` a `C:\xampp\php\ext` y agregar `extension=pdo_sqlsrv` al `php.ini`. Los drivers ODBC 17 y 18 ya vienen con SQL Server.
5. Scripts con `sqlcmd` desde `API_JAM` (`-I` enciende `QUOTED_IDENTIFIER`, que exigen los índices filtrados; `-f 65001` lee los archivos en UTF-8):
   ```powershell
   sqlcmd -S localhost -E -C -I -f 65001 -b -i db_jam.sql
   ```
   Orden: `db_jam.sql`, `01`, `02`, login `jam_api`, `06`, `07`, `05` (validación), `08`, `09`, `10`, `11` y `12` (usuarios de prueba).
6. En `api\v3\.env` (copia de `.env.example`): `JAM_LOCAL_SERVER=localhost`, el usuario `jam_api` con su contraseña, los datos `JAM_CLOUD_*`, una `JWT_SECRET` propia, `REQUIRE_HTTPS=1`, `TRUST_PROXY=1` (Caddy va delante; sin esto todos los clientes parecen la misma IP) y `JAM_STORE_URL=https://jam-sport-jp31.trafficmanager.net/ERROR404/` (los enlaces de los correos). Apache como servicio: `httpd.exe -k install` y `Start-Service Apache2.4`. Comprobar `http://localhost:8080/ERROR404/api/v3/health`.

Solo una instalación debe copiar a `JAM_cloud` (la máquina virtual): en la laptop se deja vacío `JAM_CLOUD_PASSWORD` para no mezclar pedidos de dos bases distintas en el espejo.

### 5.5 Dirección pública con https

Caddy recibe el tráfico en los puertos 80 y 443, obtiene un certificado gratuito de Let's Encrypt y lo pasa a Apache. Sin https no funcionan la cámara del escáner ni la instalación de la app.

1. Apache a `Listen 8080` en `httpd.conf` (y `ServerName localhost:8080`); en `extra\httpd-ssl.conf`, `Listen 4443`.
2. Firewall de Windows: `New-NetFirewallRule -DisplayName "Tienda JAM 80 y 443" -Direction Inbound -Protocol TCP -LocalPort 80,443 -Action Allow`.
3. `C:\caddy\caddy.exe` (de caddyserver.com) y `C:\caddy\Caddyfile`:

   ```
   jam-sport-<sufijo>.<región>.cloudapp.azure.com {
       redir / /ERROR404/
       reverse_proxy localhost:8080
   }
   ```

4. Como servicio: `sc.exe create caddy start= auto binPath= "C:\caddy\caddy.exe run --config C:\caddy\Caddyfile --adapter caddyfile"` y `sc.exe start caddy`.

Dirección pública: `https://jam-sport-jp31.chilecentral.cloudapp.azure.com` (Apache, SQL Server y Caddy arrancan solos al encender la VM).

Costos aproximados: la VM con Windows unos US$0.05 por hora encendida más unos US$10 al mes de disco, y dos bases Basic de Azure SQL de unos US$5 al mes cada una. Al terminar el semestre: `az group delete -n rg-jam`.

### 5.6 Servidor de respaldo

Si la VM principal se cae, una segunda VM sigue atendiendo la tienda en la misma dirección:

```
  clientes ─► https://jam-sport-jp31.trafficmanager.net ─► Traffic Manager
                                                            │ revisa /api/v3/health cada 10 s
                          ┌─────────── prioridad 1 ─────────┴───── prioridad 2 ───────────┐
                          ▼                                                                 ▼
                VM principal (jam-vm-cl)        respaldos cada 10 min        VM de respaldo (jam-vm-cl2)
                Caddy ► Apache ► API ► JAM_db  ─────────────────────────►    copia completa de la principal
```

- **Traffic Manager** manda a todos a la principal. Si `/api/v3/health` falla 2 veces seguidas (unos 20 s; responde 503 también cuando se cae SQL Server), manda a todos al respaldo. Cuando la principal vuelve, los regresa.
- **Datos**: la principal respalda `JAM_db` y los tres nodos cada 10 minutos (`COPY_ONLY`, no afecta otros respaldos) y la VM de respaldo los restaura. Si la principal está caída, el respaldo no restaura nada, para no borrar los pedidos que reciba mientras atiende. Pérdida máxima al caer la principal: los últimos 10 a 20 minutos. Los pedidos que reciba el respaldo durante la caída no regresan solos a la principal.
- **Sesiones**: las dos VM tienen la misma `JWT_SECRET` y la misma tabla `users_tokens`, así que quien ya inició sesión sigue dentro después del cambio.
- **Ataques**: desde internet solo están abiertos los puertos 80 y 443; Escritorio remoto solo desde tu IP; SQL Server y Apache (8080) nunca. Caddy corta conexiones lentas y cuerpos de más de 10 MB, y la API limita cada IP a 300 peticiones por minuto, además de los límites de registro, inicio de sesión y compras.
- **Costo**: la VM de respaldo cuesta lo mismo que la principal mientras está encendida; Traffic Manager, centavos al mes. La cuota de Azure for Students (4 núcleos) alcanza justo para las dos VM `Standard_B2als_v2`.
- **Límite**: las dos VM están en la misma región (Chile Central). Protege contra la caída de una VM, de su SQL Server o de su Apache, no contra la caída de toda la región.

Pasos (los scripts están en `infra\azure`):

1. **Laptop** (Azure CLI, `az login`). Copia la principal, crea el respaldo y Traffic Manager, y cierra puertos. La principal se apaga unos minutos mientras se copian sus discos:
   ```powershell
   powershell -ExecutionPolicy Bypass -File infra\azure\01_crear_respaldo.ps1 -MiIp <tu IP pública>
   ```
   Anota la IP privada de la principal que muestra al final.
2. **Las dos VM** (Escritorio remoto, PowerShell como administrador). Caddy con las dos direcciones. Correr primero en la principal, que obtiene el certificado de `trafficmanager.net`:
   ```powershell
   cd C:\xampp\htdocs\ERROR404
   powershell -ExecutionPolicy Bypass -File infra\azure\04_configurar_caddy.ps1 -Propio jam-sport-jp31.chilecentral.cloudapp.azure.com     # principal
   powershell -ExecutionPolicy Bypass -File infra\azure\04_configurar_caddy.ps1 -Propio jam-sport-jp31-b.chilecentral.cloudapp.azure.com   # respaldo
   ```
3. **VM principal**. Respaldos cada 10 minutos (pide la contraseña de `jamadmin`, que debe ser administrador de SQL Server):
   ```powershell
   powershell -ExecutionPolicy Bypass -File infra\azure\02_principal_respaldar_bases.ps1 -Instalar
   ```
4. **VM de respaldo**. Restauración cada 10 minutos:
   ```powershell
   powershell -ExecutionPolicy Bypass -File infra\azure\03_respaldo_restaurar_bases.ps1 -Instalar -IpPrincipal <IP privada de la principal>
   ```
5. **Comprobar**: `C:\jam_respaldo\registro.txt` en las dos VM dice *Respaldo completo* y *Restaurado el respaldo de ...*; en el portal, **jam-tm → Puntos de conexión** muestra los dos en **En línea**.

Cada cambio de código se instala en las dos VM (`git pull` en ambas). Para ahorrar crédito, la VM de respaldo se puede apagar fuera de la presentación: Traffic Manager la marca como degradada y todo sigue en la principal.

---

## 6. Guion para la presentación (≈ 10 min)

Antes de empezar (10 min antes): `az vm start -g rg-jam -n jam-vm-cl`, abrir `https://jam-sport-jp31.chilecentral.cloudapp.azure.com` en la laptop y en el celular, y entrar por Escritorio remoto (`mstsc /v:jam-sport-jp31.chilecentral.cloudapp.azure.com`, usuario `jam-vm-cl\jamadmin`) con SSMS abierto en `localhost`.

1. **Tienda** (móvil y escritorio): mostrar el menú hamburguesa y la cuadrícula adaptable (media queries). Abrir **Administración → Visitas** en la laptop: al entrar desde el celular con la app instalada, *Aplicación móvil* sube en menos de un minuto; buscar algo en el celular y aparece en **Lo más buscado** al recargar.
2. **Tipos de usuario**: entrar como Estándar y como Prioritario y comparar precio, envío, piezas permitidas y plazo de soporte; luego entrar como Administrador, responder el ticket y aplicar la regla de Prioritarios.
3. **Comparador de mercado**: como Prioritario, botón **Comparador** del encabezado → filtrar *Calzado* y pasar el cursor sobre los puntos para ver cada marca y su fuente.
4. **Escáner y regalo** (en el celular, en la dirección pública `https`): abrir Administración → Etiquetas en la laptop, escanear una etiqueta con *Escanear* y mostrar existencias y comparación; agregar al carrito, aceptar el empaque de regalo, escribir el código postal `22020` y ver cómo se llenan colonia, ciudad y estado.
5. **Seguimiento**: como Administrador, marcar un pedido como *Enviado* → en Mi cuenta del cliente aparece el paso con su hora y en **Correos** el aviso; en SSMS `SELECT * FROM dbo.order_status_history ORDER BY id_history DESC` muestra quién lo cambió.
6. **Fragmentación horizontal**: Administración → **Base distribuida** → **Inventario** → pestañas por nodo. En SSMS, `SELECT * FROM JAM_cdmx.dbo.inventory` muestra solo `id_store = 2`, e intentar insertar `id_store = 1` ahí falla por el `CHECK`.
7. **Pedido distribuido**: comprar en la tienda eligiendo *Sucursal Tijuana* → el pedido aparece en **Pedidos** dentro del fragmento de Tijuana.
8. **Fragmentación vertical**: **Base distribuida** → tabla de clientes con las columnas de cada fragmento en distinto color.
9. **Transparencia ante fallos**: seguir la sección 6.1.
10. **Replicación**: cambiar un precio en `JAM_db` → la matriz de **Replicación del catálogo** pasa a “Sincronizado” en todos los nodos.
11. **Espejo**: `az sql failover-group set-primary --name jam-fog-jp3 --resource-group rg-jam --server jam-sql-espejo-jp31` → **Base distribuida** muestra a `jam-sql-espejo-jp31` (Chile) como principal. Para regresar: el mismo comando con `--server jam-sql-principal-jp31`.

### 6.1 Demostración: el maestro tumba un servidor

Preparación: panel **Base distribuida** abierto en el navegador y, en la máquina virtual (Escritorio remoto), SSMS conectado a `localhost` con una ventana en `JAM_db`.

1. **Estado inicial**
   ```sql
   SELECT * FROM dist.vw_estado_nodos;   -- 3 nodos ONLINE, pendiente = 0
   EXEC dist.sp_resumen_global;          -- origen "fragmento JAM_..." en los 3
   ```
2. **Tumbar la sucursal** que pida el maestro (aquí Tijuana):
   ```sql
   ALTER DATABASE JAM_tijuana SET OFFLINE WITH ROLLBACK IMMEDIATE;
   ```
3. **Panel → Base distribuida** (recargar): aviso amarillo *“El sistema sigue funcionando con 1 nodo caído”*; la tarjeta de Tijuana en rojo con **Lecturas: Espejo Azure** (o *Nodo central* si Azure no está configurado).
4. **Inventario por sucursal → Tijuana**: los datos siguen apareciendo, con la etiqueta *“nodo sin conexión, leído desde el espejo en Azure SQL”*.
5. **En SSMS**, la diferencia entre la vista normal y la tolerante:
   ```sql
   SELECT COUNT(*) FROM dist.vw_inventory_global;   -- error 942: la base está fuera de línea
   EXEC dist.sp_resumen_global;                     -- Tijuana sale de "respaldo JAM_db"
   ```
6. **Seguir trabajando**: en la tienda comprar un producto eligiendo *Recoger en Sucursal Tijuana*, o cambiar existencias desde Administración. El pedido se guarda en el central:
   ```sql
   EXEC dist.sp_sincronizar_nodos;        -- pending_nodes = 1
   SELECT * FROM dist.vw_estado_nodos;    -- tijuana: pendiente = 1 y el error
   ```
   **Pedidos por nodo → Tijuana** muestra el pedido nuevo aunque la sucursal siga caída.
7. **Levantar la sucursal**:
   ```sql
   ALTER DATABASE JAM_tijuana SET ONLINE;
   ```
8. **Recargar el panel**: aviso verde *“Sucursal Tijuana volvió a responder y se puso al día”*, tarjeta con **Sincronización: Puesto al día**. En SSMS:
   ```sql
   SELECT * FROM dist.vw_estado_nodos;              -- pendiente = 0
   SELECT TOP 3 * FROM JAM_tijuana.dbo.customer_order ORDER BY id_order DESC;  -- ya tiene el pedido nuevo
   ```
9. **Si el maestro tumba Azure principal**: `az sql failover-group set-primary --name jam-fog-jp3 --resource-group rg-jam --server jam-sql-espejo-jp31`. La tarjeta *Azure SQL* muestra el otro servidor como principal y las lecturas de la sucursal caída siguen saliendo del espejo.

> Si el maestro detiene el servicio completo de SQL Server de la VM principal, se caen a la vez el central y las tres sucursales de esa VM: `/api/v3/health` responde 503 y Traffic Manager pasa a todos a la VM de respaldo (sección 6.2).

### 6.2 Demostración: el maestro tumba la máquina virtual principal

1. En el navegador, `https://jam-sport-jp31.trafficmanager.net`. En la laptop: `nslookup jam-sport-jp31.trafficmanager.net`. Responde con `jam-sport-jp31.chilecentral.cloudapp.azure.com` (la principal).
2. Apagar la principal: `az vm deallocate -g rg-jam -n jam-vm-cl` (o detener Apache o SQL Server en ella).
3. Esperar alrededor de un minuto (20 s de revisiones más los 30 s que los navegadores guardan la dirección) y recargar la página: sigue funcionando y la sesión sigue abierta. `nslookup` ahora responde con `jam-sport-jp31-b...` (el respaldo). En el portal, **jam-tm** muestra la principal como **Degradado**.
4. Comprar algo: el pedido lo guarda la VM de respaldo.
5. Encender la principal: `az vm start -g rg-jam -n jam-vm-cl`. Traffic Manager regresa a todos con ella cuando su `/api/v3/health` responde 200.

---

## 7. Problemas comunes

| Síntoma | Causa / solución |
|---------|------------------|
| `could not find driver` | Falta `pdo_sqlsrv` en XAMPP: copiar la DLL de *Microsoft Drivers for PHP for SQL Server* que coincida con PHP 8.2 TS x64 y habilitarla en `php.ini`. |
| `SSL Provider: certificate chain not trusted` | ODBC Driver 18 cifra por defecto; la configuración local ya agrega `TrustServerCertificate=yes`. |
| `Cannot open database "JAM_tijuana"` | Falta ejecutar `01_fragmentacion_horizontal.sql` en la instancia que usa la API. |
| La replicación no avanza | SQL Server Agent detenido, o `@@SERVERNAME` distinto del nombre del equipo (ver 2.1). |
| Azure: `Client with IP address … is not allowed` | Agregar la IP a las reglas de firewall de **ambos** servidores. |
| Copiar al espejo: *La tabla … no existe en JAM_cloud* | Falta ejecutar `04_espejo_azure.sql` conectado a `JAM_cloud` en Azure. |
| Copiar al espejo: *el espejo secundario es de solo lectura* | El servidor principal de Azure no responde; la copia se reanuda cuando vuelva (o hacer *failover* al otro). |
| `ALTER DATABASE … SET OFFLINE` se queda esperando | Hay conexiones abiertas a esa base: usar `WITH ROLLBACK IMMEDIATE`. |
| El nodo volvió pero sigue *Pendiente* | Recargar **Base distribuida** o pulsar **Sincronizar fragmentos**; revisar `last_error` en `dist.vw_estado_nodos`. |
| El navegador muestra error CORS | Revisar que la URL de `config.js` sea correcta y que la API responda `/health-check`. |
