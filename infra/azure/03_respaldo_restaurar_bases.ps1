# VM DE RESPALDO: trae los respaldos de la VM principal y los restaura.
#
# Instalar (una vez, PowerShell como administrador en la VM de respaldo):
#   powershell -ExecutionPolicy Bypass -File C:\xampp\htdocs\ERROR404\infra\azure\03_respaldo_restaurar_bases.ps1 -Instalar -IpPrincipal 10.0.0.4
#   (-IpPrincipal es la IP privada que muestra el script 01)
#
# La tarea "JAM restaurar respaldo" corre cada 10 minutos:
#   - Si la principal responde /api/v3/health, copia sus respaldos y los restaura aquí.
#   - Si la principal NO responde, no restaura nada: en ese momento Traffic Manager
#     manda a los clientes a esta VM y sus pedidos nuevos no se deben sobrescribir.
# Cuando la principal vuelve, Traffic Manager regresa a los clientes con ella y esta
# VM toma otra vez su copia.

param(
    [switch] $Instalar,
    [string] $IpPrincipal
)

$ErrorActionPreference = 'Stop'
$carpeta = 'C:\jam_respaldo'
$entrada = Join-Path $carpeta 'entrada'
$configuracion = Join-Path $carpeta 'principal.txt'
$ultimo = Join-Path $carpeta 'ultimo_restaurado.txt'
$registro = Join-Path $carpeta 'registro.txt'
$certificados = 'C:\caddy\data'
# Primero las sucursales y al final el nodo central
$bases = @('JAM_tijuana', 'JAM_cdmx', 'JAM_online', 'JAM_db')

function Escribir([string] $texto) {
    Add-Content -Path $registro -Value ((Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '  ' + $texto)
}

function Sql([string] $consulta) {
    & sqlcmd -S localhost -E -C -b -Q $consulta | Out-Null
    return ($LASTEXITCODE -eq 0)
}

if ($Instalar) {
    if (-not $IpPrincipal) { throw 'Falta -IpPrincipal (la IP privada de la VM principal).' }
    New-Item -ItemType Directory -Force -Path $entrada | Out-Null
    & icacls $carpeta /grant 'NT Service\MSSQLSERVER:(OI)(CI)M' | Out-Null
    Set-Content -Path $configuracion -Value $IpPrincipal
    $credencial = Get-Credential -UserName "$env:COMPUTERNAME\jamadmin" -Message 'Contraseña de jamadmin (la misma que en la VM principal)'
    $accion = New-ScheduledTaskAction -Execute 'powershell.exe' `
        -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`""
    # Desfasada 5 minutos de la tarea de la principal, para tomar un respaldo ya terminado
    $cada10 = New-ScheduledTaskTrigger -Once -At (Get-Date).Date.AddMinutes(5) -RepetitionInterval (New-TimeSpan -Minutes 10)
    Register-ScheduledTask -TaskName 'JAM restaurar respaldo' -Action $accion -Trigger $cada10 -Force `
        -User $credencial.UserName -Password $credencial.GetNetworkCredential().Password -RunLevel Highest | Out-Null
    Write-Host "Tarea instalada. Registro en $registro"
    exit 0
}

# ---------------------------------------------------------------------
# Restauración (lo que corre la tarea cada 10 minutos)
# ---------------------------------------------------------------------
$ip = (Get-Content $configuracion -ErrorAction Stop).Trim()
$origen = "\\$ip\jamrespaldo"

# 1. ¿La principal está sana? (REQUIRE_HTTPS=1: se avisa que la petición es https)
try {
    $salud = Invoke-WebRequest -UseBasicParsing -TimeoutSec 10 `
        -Uri "http://${ip}:8080/ERROR404/api/v3/health" -Headers @{ 'X-Forwarded-Proto' = 'https' }
    $sana = ($salud.StatusCode -eq 200)
} catch {
    $sana = $false
}
if (-not $sana) {
    Escribir 'La principal no responde: no se restaura (esta VM atiende a los clientes).'
    exit 0
}

# 2. ¿Hay un respaldo nuevo y completo?
$marca = Join-Path $origen 'listo.txt'
if (-not (Test-Path $marca)) {
    Escribir 'La principal está escribiendo un respaldo: se intenta en la siguiente vuelta.'
    exit 0
}
$version = (Get-Content $marca).Trim()
if ((Test-Path $ultimo) -and ((Get-Content $ultimo).Trim() -eq $version)) {
    exit 0
}

try {
    # 3. Copiar y comprobar que el respaldo no cambió mientras se copiaba
    foreach ($base in $bases) {
        Copy-Item -Path (Join-Path $origen "$base.bak") -Destination $entrada -Force
    }
    if (-not (Test-Path $marca) -or (Get-Content $marca).Trim() -ne $version) {
        Escribir 'El respaldo cambió durante la copia: se intenta en la siguiente vuelta.'
        exit 0
    }

    # 4. Restaurar
    foreach ($base in $bases) {
        $archivo = Join-Path $entrada "$base.bak"
        $restaurar = "RESTORE DATABASE [$base] FROM DISK = N'$archivo' WITH REPLACE, CHECKSUM"
        $antes = "IF DB_ID(N'$base') IS NOT NULL ALTER DATABASE [$base] SET SINGLE_USER WITH ROLLBACK IMMEDIATE;"
        $despues = "ALTER DATABASE [$base] SET MULTI_USER;"
        # JAM_db conserva su publicación de replicación (mismo nombre de servidor que la principal)
        $ok = ($base -eq 'JAM_db') -and (Sql "$antes $restaurar, KEEP_REPLICATION; $despues")
        if (-not $ok -and -not (Sql "$antes $restaurar; $despues")) {
            Sql "IF DB_ID(N'$base') IS NOT NULL $despues" | Out-Null
            throw "No se pudo restaurar $base"
        }
    }

    # 5. Certificados de Caddy (el de la dirección de Traffic Manager lo obtiene la principal)
    $copiaCaddy = Join-Path $origen 'caddy'
    if (Test-Path $copiaCaddy) {
        $antesCaddy = (Get-ChildItem $certificados -Recurse -File -ErrorAction SilentlyContinue | Measure-Object LastWriteTime -Maximum).Maximum
        New-Item -ItemType Directory -Force -Path $certificados | Out-Null
        Copy-Item -Path (Join-Path $copiaCaddy '*') -Destination $certificados -Recurse -Force
        $despuesCaddy = (Get-ChildItem $certificados -Recurse -File | Measure-Object LastWriteTime -Maximum).Maximum
        if ($despuesCaddy -ne $antesCaddy) {
            Restart-Service caddy
        }
    }

    Set-Content -Path $ultimo -Value $version
    Escribir "Restaurado el respaldo de $version"
} catch {
    Escribir ('ERROR: ' + $_.Exception.Message)
    exit 1
}
