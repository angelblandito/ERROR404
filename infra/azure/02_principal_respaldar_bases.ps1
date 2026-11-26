# VM PRINCIPAL: respalda las bases y el certificado de Caddy para la VM de respaldo.
#
# Instalar (una vez, PowerShell como administrador en la VM principal):
#   powershell -ExecutionPolicy Bypass -File C:\xampp\htdocs\ERROR404\infra\azure\02_principal_respaldar_bases.ps1 -Instalar
#
# Con -Instalar:
#   - crea C:\jam_respaldo y la comparte como \\<IP privada>\jamrespaldo (solo lectura para jamadmin);
#   - abre en el firewall de Windows los puertos 445 (carpeta compartida) y 8080 (Apache)
#     únicamente para la red local de Azure, nunca para internet;
#   - programa la tarea "JAM respaldo de bases" cada 10 minutos con la cuenta jamadmin.
# Sin -Instalar: hace un respaldo en ese momento (es lo que ejecuta la tarea).

param([switch] $Instalar)

$ErrorActionPreference = 'Stop'
$carpeta = 'C:\jam_respaldo'
$bases = @('JAM_db', 'JAM_tijuana', 'JAM_cdmx', 'JAM_online')
$certificados = 'C:\caddy\data'
$registro = Join-Path $carpeta 'registro.txt'

function Escribir([string] $texto) {
    Add-Content -Path $registro -Value ((Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '  ' + $texto)
}

if ($Instalar) {
    New-Item -ItemType Directory -Force -Path $carpeta | Out-Null
    # SQL Server escribe los .bak con su propia cuenta de servicio
    & icacls $carpeta /grant 'NT Service\MSSQLSERVER:(OI)(CI)M' | Out-Null
    if (-not (Get-SmbShare -Name jamrespaldo -ErrorAction SilentlyContinue)) {
        New-SmbShare -Name jamrespaldo -Path $carpeta -ReadAccess "$env:COMPUTERNAME\jamadmin" | Out-Null
    }
    if (-not (Get-NetFirewallRule -DisplayName 'JAM respaldo (red local)' -ErrorAction SilentlyContinue)) {
        New-NetFirewallRule -DisplayName 'JAM respaldo (red local)' -Direction Inbound -Protocol TCP `
            -LocalPort 445, 8080 -RemoteAddress LocalSubnet -Action Allow | Out-Null
    }
    $credencial = Get-Credential -UserName "$env:COMPUTERNAME\jamadmin" -Message 'Contraseña de jamadmin (la tarea corre con esta cuenta)'
    $accion = New-ScheduledTaskAction -Execute 'powershell.exe' `
        -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`""
    $cada10 = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 10)
    Register-ScheduledTask -TaskName 'JAM respaldo de bases' -Action $accion -Trigger $cada10 -Force `
        -User $credencial.UserName -Password $credencial.GetNetworkCredential().Password -RunLevel Highest | Out-Null
    Write-Host "Tarea instalada. Respaldos en $carpeta; registro en $registro"
    Write-Host "Carpeta compartida: \\$((Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -like '10.*' } | Select-Object -First 1).IPAddress)\jamrespaldo"
    exit 0
}

# ---------------------------------------------------------------------
# Respaldo (lo que corre la tarea cada 10 minutos)
# ---------------------------------------------------------------------
New-Item -ItemType Directory -Force -Path $carpeta | Out-Null
$marca = Join-Path $carpeta 'listo.txt'
try {
    # Mientras se escriben los .bak no hay marca: el respaldo nunca lee una copia a medias
    Remove-Item $marca -ErrorAction SilentlyContinue
    foreach ($base in $bases) {
        $destino = Join-Path $carpeta "$base.bak"
        $sql = "IF DB_ID(N'$base') IS NOT NULL BACKUP DATABASE [$base] TO DISK = N'$destino' WITH COPY_ONLY, INIT, COMPRESSION, CHECKSUM;"
        & sqlcmd -S localhost -E -C -b -Q $sql | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "No se pudo respaldar $base" }
    }
    if (Test-Path $certificados) {
        $copia = Join-Path $carpeta 'caddy'
        New-Item -ItemType Directory -Force -Path $copia | Out-Null
        Copy-Item -Path (Join-Path $certificados '*') -Destination $copia -Recurse -Force
    }
    Set-Content -Path $marca -Value (Get-Date -Format 'yyyy-MM-ddTHH:mm:ss')
    Escribir 'Respaldo completo'
} catch {
    Escribir ('ERROR: ' + $_.Exception.Message)
    exit 1
}
