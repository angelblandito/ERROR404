# AMBAS VM: configura Caddy (https) para la dirección propia de la VM y la de Traffic Manager.
#
# En la VM principal:
#   powershell -ExecutionPolicy Bypass -File C:\xampp\htdocs\ERROR404\infra\azure\04_configurar_caddy.ps1 -Propio jam-sport-jp31.chilecentral.cloudapp.azure.com
# En la VM de respaldo:
#   powershell -ExecutionPolicy Bypass -File C:\xampp\htdocs\ERROR404\infra\azure\04_configurar_caddy.ps1 -Propio jam-sport-jp31-b.chilecentral.cloudapp.azure.com
#
# - Los certificados se guardan en C:\caddy\data: la principal obtiene el de la dirección
#   de Traffic Manager y el script 02 lo copia a la VM de respaldo.
# - Límites contra conexiones lentas o abusivas: encabezados en 10 s, cuerpo en 30 s,
#   cuerpo máximo de 10 MB (la foto más grande que sube el panel) y conexiones
#   inactivas cerradas a los 2 minutos.

param(
    [Parameter(Mandatory = $true)] [string] $Propio,
    [string] $Global = 'jam-sport-jp31.trafficmanager.net'
)

$ErrorActionPreference = 'Stop'

$caddyfile = @"
{
    storage file_system {
        root C:/caddy/data
    }
    servers {
        timeouts {
            read_header 10s
            read_body 30s
            write 60s
            idle 2m
        }
    }
}

$Global, $Propio {
    request_body {
        max_size 10MB
    }
    header -Server
    redir / /ERROR404/
    reverse_proxy localhost:8080
}
"@

New-Item -ItemType Directory -Force -Path 'C:\caddy\data' | Out-Null
Set-Content -Path 'C:\caddy\Caddyfile' -Value $caddyfile -Encoding ASCII
& C:\caddy\caddy.exe validate --config C:\caddy\Caddyfile --adapter caddyfile
if ($LASTEXITCODE -ne 0) { throw 'El Caddyfile no es válido.' }
Restart-Service caddy
Write-Host "Caddy atiende https://$Global y https://$Propio"
