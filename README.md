# ERROR404 · JAM Sport

Proyecto de Bases de Datos Distribuidas (TecNM campus Tijuana): base de datos distribuida en SQL Server con espejo en Azure SQL.

Tienda de ropa deportiva con tienda en línea y sucursales Tijuana y CDMX.

- Tienda en línea: `index.html`, `index.css`, `js/` (inicio, catálogo, producto, carrito, pago, cuenta, seguimiento de pedidos, soporte, favoritos, comparador de mercado y administración con gráficas). Se abre en `http://localhost/ERROR404/` y se puede instalar como aplicación.
- API PHP: `api/v3` (estructura del profe, servida por Apache de XAMPP junto con la página). Rutas, configuración y pruebas: [`api/v3/README.md`](api/v3/README.md).
- Scripts SQL de fragmentación, replicación, espejo en Azure y tolerancia a fallos: `API_JAM/distribuida/`.
- El Administrador ve en **Visitas** quién está conectado (página web o app), las visitas por día, semana, mes y año, lo más buscado y las descargas de la app.
- Si una sucursal se cae, sus datos se leen del espejo en Azure SQL o del nodo central, y se pone al día al volver (guía, sección 6.1).
- Instalación local y despliegue en una máquina virtual de Azure: [`docs/GUIA_DISTRIBUIDA_Y_NUBE.md`](docs/GUIA_DISTRIBUIDA_Y_NUBE.md).
- Paso a paso para actualizar y validar la tienda en producción: [`docs/PUESTA_EN_PRODUCCION.md`](docs/PUESTA_EN_PRODUCCION.md).
- Servidor de respaldo (segunda máquina virtual con Traffic Manager): `infra/azure/`.
