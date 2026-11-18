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
                         ┌──────────────────────────── Local (SQL Server 2022 Developer) ───────────────────────────┐
                         │                                                                                            │
   Navegador ──► API ───►│  JAM_db (central)  ──pub_catalogo──►  JAM_tijuana   JAM_cdmx   JAM_online                  │
   (index.html)  (PHP)   │     │   publicador         (replicación    fragmento     fragmento  fragmento + perfil         │
                         │     │   + dist.customer_private   transaccional)  id_store=1  id_store=2  id_store=4 de clientes   │
                         │     │                                                                                      │
                         └─────┼──────────────────────────────────────────────────────────────────────────────────────┘
                               │ pub_completa (replicación transaccional por internet)
                               ▼
                 ┌───────────────── Azure SQL ─────────────────┐
                 │  jam-sql-principal / JAM_cloud  (PRIMARY)    │
                 │            │ geo-replicación (espejo)        │
                 │            ▼                                 │
                 │  jam-sql-espejo / JAM_cloud     (SECONDARY)  │
                 │  Listener: jam-fog.database.windows.net      │
                 └──────────────────────────────────────────────┘
                               ▲
          API en Azure App Service (solo lectura) + frontend en Azure Static Web Apps
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
4. `API_JAM/distribuida/05_validacion.sql` → todas las columnas `diferencias` y `repetidos_entre_fragmentos` deben valer **0**.

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

1. Copiar `api/v1/php/config/settings.example.php` como `settings.php` y ajustar `JAM_LOCAL_SERVER` (p. ej. `localhost`).
2. Abrir una terminal en `C:\xampp\htdocs\ERROR404\api\v1\php` y ejecutar:
   ```
   C:\xampp\php\php.exe -S localhost:8080 index.php
   ```
3. Probar en el navegador `http://localhost:8080/health-check` → `"database": "en_linea"` y `"pdo_sqlsrv": true`.

### 2.5 Frontend

Con Apache de XAMPP encendido: `http://localhost/ERROR404/index.html`.

| Menú | Qué muestra |
|------|-------------|
| **Tienda** | Catálogo, disponibilidad por sucursal, carrito, cupón y creación de pedidos |
| **Inventario** | Fragmento de `inventory` de cada nodo (pestañas Tijuana, CDMX, En línea) |
| **Pedidos** | Pedidos reconstruidos desde los fragmentos, con sus artículos |
| **Base distribuida** | Estado de nodos, completitud de fragmentos, reconstrucción vertical, replicación y espejo |

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

Si un nodo fragmento no responde, la API lee sus datos del nodo por defecto filtrando por `id_store` (transparencia ante fallos). El campo `sources` de la respuesta indica de dónde salió cada parte.

---

## 4. Despliegue en Azure

Requisitos: cuenta **Azure for Students** (correo escolar) y **Azure CLI** (`winget install Microsoft.AzureCLI`).

> Los nombres de servidor deben ser únicos en todo Azure: agregar un sufijo, p. ej. `jam-sql-principal-je`.
> Azure for Students solo permite algunas regiones: si una región da error de política, elegir otra.
> Revisar las regiones permitidas en *Directivas → Asignaciones* de la suscripción.

### 4.1 Servidores, base y espejo

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

az sql db create -g $RG -s jam-sql-principal-$SUF -n JAM_cloud --service-objective Basic --backup-storage-redundancy Local

# Espejo: grupo de conmutación por error
az sql failover-group create -n jam-fog-$SUF -g $RG -s jam-sql-principal-$SUF `
   --partner-server jam-sql-espejo-$SUF --add-db JAM_cloud --failover-policy Manual
```

Puntos de conexión resultantes:
- Lectura/escritura: `jam-fog-<sufijo>.database.windows.net`
- Espejo (solo lectura): `jam-fog-<sufijo>.secondary.database.windows.net`

### 4.2 Replicación hacia Azure

1. En `03_replicacion.sql` llenar `@azure_servidor` (listener `jam-fog-<sufijo>.database.windows.net`), `@azure_usuario` y `@azure_password`.
2. Ejecutar el script completo otra vez: lo que ya existe se omite y solo se crea la suscripción a Azure.
3. En unos minutos `JAM_cloud` tendrá todas las tablas. Comprobar con `04_espejo_azure.sql` conectado al listener desde SSMS (autenticación SQL, *Cifrar: Obligatorio*).

> Si la red de la escuela bloquea el puerto 1433 de salida, hacer este paso desde casa o con datos del celular.

### 4.3 API en App Service

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
az webapp cors add -n $APP -g $RG --allowed-origins "*"

# Publicar el contenido de api/v1/php
cd C:\xampp\htdocs\ERROR404\api\v1\php
Compress-Archive -Path * -DestinationPath ..\api.zip -Force
az webapp deploy -n $APP -g $RG --src-path ..\api.zip --type zip
```

- Si `PHP:8.3` no aparece, revisar los disponibles con `az webapp list-runtimes --os linux`.
- Comprobar `https://jam-api-<sufijo>.azurewebsites.net/health-check` → `"pdo_sqlsrv": true`.
- `settings.php` no se sube: en Azure la configuración sale de las variables de entorno.
- El plan B1 tiene costo (se cubre con el crédito de estudiante). Apagar o borrar el grupo al terminar: `az group delete -n rg-jam`.

### 4.4 Frontend en Static Web Apps

1. En `config.js` cambiar `AZURE_API_URL` por `https://jam-api-<sufijo>.azurewebsites.net` y subir el cambio a GitHub.
2. Portal de Azure → **Static Web Apps → Crear** → plan **Free** → origen **GitHub** → repo `ERROR404`, rama `main`.
3. Detalles de compilación: *Custom*, **App location** `/`, **Output location** vacío.
4. Azure crea un workflow de GitHub Actions; al terminar, la URL pública queda en la página del recurso.

En la nube la tienda funciona en modo consulta: el catálogo, el inventario y los pedidos se leen de la réplica `JAM_cloud`. Los pedidos nuevos se registran en el nodo central local, que los replica hacia Azure.

---

## 5. Guion para la presentación (≈ 6 min)

1. **Tienda** (móvil y escritorio): mostrar el menú hamburguesa y la cuadrícula adaptable (media queries).
2. **Fragmentación horizontal**: menú **Inventario** → pestañas por nodo. En SSMS, `SELECT * FROM JAM_cdmx.dbo.inventory` muestra solo `id_store = 2`, e intentar insertar `id_store = 1` ahí falla por el `CHECK`.
3. **Pedido distribuido**: comprar en la tienda eligiendo *Sucursal Tijuana* → el pedido aparece en **Pedidos** dentro del fragmento de Tijuana.
4. **Fragmentación vertical**: **Base distribuida** → tabla de clientes con las columnas de cada fragmento en distinto color.
5. **Transparencia ante fallos**: desconectar una sucursal (`ALTER DATABASE JAM_cdmx SET OFFLINE WITH ROLLBACK IMMEDIATE;`), actualizar **Inventario** → CDMX aparece como “nodo sin conexión, leído de central”. Volver con `SET ONLINE`.
6. **Replicación**: cambiar un precio en `JAM_db` → la matriz de **Replicación del catálogo** pasa a “Sincronizado” en todos los nodos.
7. **Espejo**: `az sql failover-group set-primary --name jam-fog-<sufijo> --resource-group rg-jam --server jam-sql-espejo-<sufijo>` → en la API de Azure, **Base distribuida** muestra el otro servidor como principal.

---

## 6. Problemas comunes

| Síntoma | Causa / solución |
|---------|------------------|
| `could not find driver` | Falta `pdo_sqlsrv` en XAMPP: copiar la DLL de *Microsoft Drivers for PHP for SQL Server* que coincida con PHP 8.2 TS x64 y habilitarla en `php.ini`. |
| `SSL Provider: certificate chain not trusted` | ODBC Driver 18 cifra por defecto; la configuración local ya agrega `TrustServerCertificate=yes`. |
| `Cannot open database "JAM_tijuana"` | Falta ejecutar `01_fragmentacion_horizontal.sql` en la instancia que usa la API. |
| La replicación no avanza | SQL Server Agent detenido, o `@@SERVERNAME` distinto del nombre del equipo (ver 2.1). |
| Azure: `Client with IP address … is not allowed` | Agregar la IP a las reglas de firewall de **ambos** servidores. |
| El navegador muestra error CORS | Revisar que la URL de `config.js` sea correcta y que la API responda `/health-check`. |
