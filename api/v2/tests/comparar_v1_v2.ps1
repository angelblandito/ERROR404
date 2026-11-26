<#
  Compara la API anterior (api/v1/php) con la API nueva (api/v2) ruta por ruta, contra la
  base real de la laptop. No cambia datos: solo inicia sesion y hace consultas GET.

  Uso (PowerShell, con Apache encendido):
    powershell -ExecutionPolicy Bypass -File C:\xampp\htdocs\ERROR404\api\v2\tests\comparar_v1_v2.ps1
#>
param([string]$Base = "http://localhost/ERROR404")

function Llamar([string]$Metodo, [string]$Url, [string]$Token, [string]$Cuerpo) {
    $h = @{}
    if ($Token) { $h["Authorization"] = "Bearer $Token" }
    try {
        $p = @{ UseBasicParsing = $true; Method = $Metodo; Uri = $Url; Headers = $h }
        if ($Cuerpo) { $p["Body"] = [Text.Encoding]::UTF8.GetBytes($Cuerpo); $p["ContentType"] = "application/json; charset=utf-8" }
        $r = Invoke-WebRequest @p
        return @{ status = [int]$r.StatusCode; text = $r.Content }
    } catch {
        $resp = $_.Exception.Response
        if ($resp) {
            $sr = New-Object IO.StreamReader($resp.GetResponseStream())
            return @{ status = [int]$resp.StatusCode; text = $sr.ReadToEnd() }
        }
        return @{ status = 0; text = $_.Exception.Message }
    }
}

# Campos que cambian en cada consulta (latencias, horas de la consulta, conectados en este momento)
$volatiles = '"(latency_ms|checked_at|synchronized_at|generated_at|now|seconds_ago|last_seen|online)"\s*:\s*("[^"]*"|[^,}\]]*)'
# Datos que solo da la API nueva: temporadas de cada producto y fotos por nombre
$soloV2 = '"(seasons|photos)"\s*:\s*\[[^\]]*\]'
function Normalizar([string]$t) { return ([regex]::Replace([regex]::Replace($t, $volatiles, ''), $soloV2, '')) }

Write-Host "Inicio de sesion del administrador (se usa la API nueva)" -ForegroundColor Yellow
$correo = Read-Host "Correo"
$s = Read-Host "Contrasena" -AsSecureString
$clave = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($s))
$cuerpo = (@{ email = $correo; password = $clave } | ConvertTo-Json -Compress)
Remove-Variable clave
$login = Llamar "POST" "$Base/api/v2/auth/login" "" $cuerpo
if ($login.status -ne 200) { Write-Host "No se pudo iniciar sesion en v2: $($login.status) $($login.text)" -ForegroundColor Red; exit 1 }
$token = ($login.text | ConvertFrom-Json).data.token
Write-Host "Sesion iniciada en la API nueva (la misma sesion sirve en las dos)" -ForegroundColor Green

$rutas = @(
  "/store/catalog", "/store/announcements", "/season_theme", "/season_theme/current",
  "/promotion", "/state", "/postal-codes/22000", "/store/products/1/reviews", "/store/products/1/also-bought",
  "/auth/me", "/me/orders", "/support/tickets", "/market",
  "/admin/summary", "/admin/customers", "/admin/orders", "/admin/tickets", "/admin/announcements",
  "/admin/emails", "/admin/market", "/admin/analytics",
  "/nodes", "/distributed/inventory", "/distributed/inventory?node=tijuana", "/distributed/orders",
  "/distributed/customers", "/distributed/fragments", "/replication/status"
)

$carpeta = Join-Path $env:TEMP "jam_comparacion"
New-Item -ItemType Directory -Force $carpeta | Out-Null
$iguales = 0; $distintas = 0
foreach ($ruta in $rutas) {
    $a = Llamar "GET" "$Base/api/v1/php$ruta" $token ""
    $b = Llamar "GET" "$Base/api/v2$ruta" $token ""
    $igual = ($a.status -eq $b.status) -and ((Normalizar $a.text) -eq (Normalizar $b.text))
    if ($igual) {
        $iguales++
        Write-Host ("{0,-40} v1 {1}  v2 {2}  igual" -f $ruta, $a.status, $b.status) -ForegroundColor Green
    } else {
        $distintas++
        Write-Host ("{0,-40} v1 {1}  v2 {2}  DISTINTA" -f $ruta, $a.status, $b.status) -ForegroundColor Red
        $nombre = ($ruta -replace '[^a-zA-Z0-9]', '_')
        [IO.File]::WriteAllText((Join-Path $carpeta "$nombre.v1.json"), $a.text)
        [IO.File]::WriteAllText((Join-Path $carpeta "$nombre.v2.json"), $b.text)
    }
}
Write-Host ""
Write-Host "Iguales: $iguales   Distintas: $distintas" -ForegroundColor Cyan
if ($distintas -gt 0) { Write-Host "Las respuestas distintas quedaron en $carpeta" -ForegroundColor Yellow }
