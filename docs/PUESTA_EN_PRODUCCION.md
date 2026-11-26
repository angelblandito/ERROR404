# Puesta en producción y validación

Pasos en orden para dejar JAM Sport funcionando en Azure (VM principal + VM de respaldo) con la API v3, y la lista de pruebas para validar que todo está listo antes de que el profe evalúe. Detalle técnico de cada parte en `GUIA_DISTRIBUIDA_Y_NUBE.md` (secciones 5.4, 5.6 y 6).

Tiempo aproximado: 1 h 30 min.

---

## Paso 1. Subir el código a GitHub (laptop, VS Code)

El commit ya está preparado en la rama `main` de tu carpeta.

```powershell
cd C:\xampp\htdocs\ERROR404
git status            # debe decir: On branch main, ... ahead of 'origin/main' by 1 commit
git push origin main
```

Avisar a Michelle y Angel que actualicen con `git checkout main` y `git pull`.

## Paso 2. Base de datos de la laptop (opcional, para probar en local)

En **SQL Server de la laptop** (PowerShell en `C:\xampp\htdocs\ERROR404\API_JAM\distribuida`):

```powershell
foreach ($s in '08_catalogo_temporadas','09_tokens_y_accesos','10_catalogo_ampliado','11_registro_y_prioritario','12_usuarios_de_prueba') {
    sqlcmd -S localhost -E -C -I -f 65001 -b -i "$s.sql"
}
```

Cada script termina con una consulta de resultado: el 10 muestra 20 productos nuevos por categoría y el 12 las tres cuentas de prueba. Abrir `http://localhost/ERROR404/` con **Ctrl+F5**.

## Paso 3. Actualizar la VM principal a la API v3

Escritorio remoto a `jam-vm-cl`, PowerShell como administrador.

1. **Código**: la VM se clonó solo con la rama `mejoras-tienda`; se le agrega `main`:
   ```powershell
   cd C:\xampp\htdocs\ERROR404
   git remote set-branches --add origin main
   git fetch --depth 1 origin main
   git checkout -f -B main origin/main
   ```
   `api\v1\php\config\settings.php` (contraseñas) no se toca: Git lo ignora.

2. **Base de datos** (en `API_JAM\distribuida`):
   ```powershell
   foreach ($s in '08_catalogo_temporadas','09_tokens_y_accesos','10_catalogo_ampliado','11_registro_y_prioritario','12_usuarios_de_prueba') {
       sqlcmd -S localhost -E -C -I -f 65001 -b -i "$s.sql"
   }
   ```

3. **Configuración de la v3**:
   ```powershell
   cd C:\xampp\htdocs\ERROR404\api\v3
   copy .env.example .env
   C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(32));"     # copiar el resultado
   notepad .env
   ```
   Cambiar en `.env`:
   ```
   JWT_SECRET=<el resultado del comando anterior>
   JAM_STORE_URL=https://jam-sport-jp31.trafficmanager.net/ERROR404/
   REQUIRE_HTTPS=1
   TRUST_PROXY=1
   ```
   Correo real (opcional): con una cuenta de Gmail del equipo, activar la verificación en dos pasos, crear una contraseña de aplicación en `myaccount.google.com/apppasswords` y llenar:
   ```
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=465
   MAIL_ENCRYPTION=ssl
   MAIL_USER=<correo de Gmail>
   MAIL_PASSWORD=<contraseña de aplicación, 16 letras sin espacios>
   ```
   Sin esto los correos (confirmación de registro, ascenso, pedidos) se ven en **Administración → Correos** y desde ahí se abre el enlace.

4. **Comprobar** (deben salir `OK` y `{"status":"ok"...}`):
   ```powershell
   C:\xampp\php\php.exe C:\xampp\htdocs\ERROR404\api\v3\tests\run.php
   Invoke-WebRequest -UseBasicParsing http://localhost:8080/ERROR404/api/v3/health -Headers @{ 'X-Forwarded-Proto' = 'https' } | Select-Object -Expand Content
   ```

5. **Espejo de Azure**: en la tienda, **Administración → Base distribuida → Copiar al espejo (Azure)**, para copiar los productos y cuentas nuevas a `JAM_cloud`.

## Paso 4. Servidor de respaldo

Seguir los 5 pasos de la sección **5.6** de `GUIA_DISTRIBUIDA_Y_NUBE.md`. Debe hacerse **después** del paso 3: la VM de respaldo es una copia de la principal y hereda su código, su base y su `.env`.

Al terminar, la dirección para todos (profe incluido) es:

**https://jam-sport-jp31.trafficmanager.net**

## Paso 5. Validación (antes de presentar)

Marcar cada punto en la dirección de Traffic Manager.

| # | Qué probar | Resultado esperado |
|---|---|---|
| 1 | Abrir la tienda | Carga con el tema de la temporada, fotos reales y candado de https |
| 2 | **Tienda** y cada departamento | Productos con foto; al menos 20 nuevos por categoría |
| 3 | **Crear cuenta** con un correo real | Aviso *Revisa tu correo*; todavía no hay sesión |
| 4 | Abrir el enlace del correo (o desde **Administración → Correos**) | *¡Listo!*, sesión iniciada como Estándar |
| 5 | Abrir el mismo enlace otra vez | *No pudimos confirmar tu correo* (sirve una sola vez) |
| 6 | Entrar con `prueba.estandar@jamsport.mx` / `JamEstandar2026` → **Clientes Prioritarios** | Avance: $5,350 de $50,000 y 120 días; sin botón para subir |
| 7 | Entrar con `prueba.listo@jamsport.mx` / `JamListo2026` → **Subir a Prioritario** | *¡Ya eres cliente Prioritario!* y correo de ascenso en Administración → Correos |
| 8 | Entrar con `prueba.prioritario@jamsport.mx` / `JamPrioritario2026` | Precios con 12 % de descuento, envío gratis y acceso al comparador de mercado |
| 9 | **Soporte** → marcar *Es urgente* → Enviar | Ticket *URGENTE* con respuesta en menos de 1 hora |
| 10 | **Contacto y emergencias** desde el celular | Los botones llaman (911, línea urgente, sucursales), abren WhatsApp y el correo |
| 11 | Comprar un producto con la cuenta Estándar | Pedido en **Mis pedidos** y en Administración → Pedidos |
| 12 | Administración → Base distribuida | Los 3 nodos en línea y el espejo de Azure |
| 13 | Apagar la VM principal (sección 6.2 de la guía) | En ~1 minuto la tienda sigue funcionando desde el respaldo |
| 14 | Encender la VM principal | Traffic Manager regresa a todos con ella |

Para que una cuenta de prueba vuelva a su estado inicial (por ejemplo, para repetir el punto 7), volver a ejecutar `12_usuarios_de_prueba.sql`.

## Pruebas que puede hacer el profe

- **Postman**: importar `api/v3/postman_jam_v3.json` y cambiar `base_url` a `https://jam-sport-jp31.trafficmanager.net/ERROR404/api/v3`. `POST /auth/login` guarda el token solo.
- **Seguridad** (con el token de una cuenta Estándar):

  | Petición | Respuesta |
  |---|---|
  | `GET /customer` (lista de clientes) | 403: solo Administrador |
  | `POST /customer` (crear un usuario) | 403 |
  | `GET /me/orders` | Solo los pedidos de esa cuenta |
  | `POST /auth/register` con `"tier": "admin"` | La cuenta se crea como Estándar; el campo se ignora |
  | `POST /me/priority/upgrade` sin cumplir requisitos | 403 con lo que falta |
  | Sin token: `GET /me/priority` | 401 |
  | Más de 300 peticiones en un minuto desde una IP | 429 |

- **Pruebas automáticas**: `C:\xampp\php\php.exe api\v3\tests\run.php` → 107 pruebas, `OK`.

## Si algo falla

| Síntoma | Causa y solución |
|---|---|
| La tienda dice *No hay conexión con la tienda* | Apache detenido: `Start-Service Apache2.4` |
| `/api/v3/health` responde 503 | SQL Server detenido: `Start-Service MSSQLSERVER` |
| No se puede iniciar sesión (500) | Falta el script 09 en esa base |
| Todos reciben 429 al mismo tiempo | `TRUST_PROXY=1` no está en `api\v3\.env` |
| El correo no llega | Revisar spam; `MAIL_PASSWORD` debe ser la contraseña de aplicación. El correo siempre queda en Administración → Correos |
| Traffic Manager marca la principal como *Degradado* | Abrir `https://jam-sport-jp31.chilecentral.cloudapp.azure.com/ERROR404/api/v3/health` en la VM y revisar Apache, SQL Server y Caddy |
| El respaldo no restaura | `C:\jam_respaldo\registro.txt` en las dos VM dice el motivo |
