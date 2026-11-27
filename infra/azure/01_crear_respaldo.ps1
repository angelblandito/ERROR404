# Crea la máquina virtual de respaldo y la dirección única con conmutación automática.
#
# Se ejecuta UNA vez, desde la laptop, con Azure CLI (az login antes):
#   powershell -ExecutionPolicy Bypass -File infra\azure\01_crear_respaldo.ps1 -MiIp <tu IP pública>
#
# Qué hace:
#   1. Apaga la VM principal un momento, copia sus discos (snapshot) y la vuelve a encender.
#   2. Crea la VM de respaldo con esa copia, en la misma red virtual.
#   3. Crea el perfil de Traffic Manager: una sola dirección
#      https://<NombreGlobal>.trafficmanager.net que manda a los clientes a la principal
#      y, si deja de responder /api/v3/health, a la de respaldo.
#   4. Deja abiertos a internet solo los puertos 80 y 443; Escritorio remoto (3389)
#      solo desde -MiIp y SQL Server (1433) cerrado.
#
# Cuota de Azure for Students: 4 núcleos en total. Dos VM Standard_B2als_v2 (2 núcleos
# cada una) la llenan; si hay otra VM encendida, el paso 2 falla por cuota.

param(
    [Parameter(Mandatory = $true)] [string] $MiIp,
    [string] $Grupo = 'rg-jam',
    [string] $Principal = 'jam-vm-cl',
    [string] $Respaldo = 'jam-vm-cl2',
    [string] $DnsRespaldo = 'jam-sport-jp31-b',
    [string] $NombreGlobal = 'jam-sport-jp31',
    [string] $Tamano = 'Standard_B2als_v2'
)

$ErrorActionPreference = 'Stop'

function Az {
    # Ejecuta Azure CLI y detiene el script si falla.
    # az.cmd y no az: PowerShell no distingue mayúsculas y az llamaría a esta misma función
    $salida = & az.cmd @args
    if ($LASTEXITCODE -ne 0) { throw "Falló: az $($args -join ' ')" }
    return $salida
}

# ---------------------------------------------------------------------
# 0. Datos de la VM principal
# ---------------------------------------------------------------------
$vm = Az vm show -g $Grupo -n $Principal -o json | ConvertFrom-Json
$region = $vm.location
$discoSo = $vm.storageProfile.osDisk.managedDisk.id
$discosDatos = @($vm.storageProfile.dataDisks | ForEach-Object { $_.managedDisk.id })
$nic = Az network nic show --ids $vm.networkProfile.networkInterfaces[0].id -o json | ConvertFrom-Json
$subred = $nic.ipConfigurations[0].subnet.id
$ipPrincipal = $nic.ipConfigurations[0].publicIPAddress.id
$nsg = $nic.networkSecurityGroup.id
if (-not $nsg) {
    $nsg = (Az network vnet subnet show --ids $subred -o json | ConvertFrom-Json).networkSecurityGroup.id
}
Write-Host "Principal: $Principal en $region"

# ---------------------------------------------------------------------
# 1. Copia de los discos (con la VM apagada, la copia queda consistente)
# ---------------------------------------------------------------------
Write-Host 'Apagando la VM principal para copiar sus discos...'
Az vm deallocate -g $Grupo -n $Principal | Out-Null

$copias = @("$Respaldo-so-snap")
try {
    Az snapshot create -g $Grupo -n "$Respaldo-so-snap" --source $discoSo --sku Standard_LRS | Out-Null
    for ($i = 0; $i -lt $discosDatos.Count; $i++) {
        Az snapshot create -g $Grupo -n "$Respaldo-datos$i-snap" --source $discosDatos[$i] --sku Standard_LRS | Out-Null
        $copias += "$Respaldo-datos$i-snap"
    }
} finally {
    # Aunque falle la copia, la principal vuelve a encenderse
    Write-Host 'Encendiendo de nuevo la VM principal...'
    Az vm start -g $Grupo -n $Principal | Out-Null
}

# ---------------------------------------------------------------------
# 2. VM de respaldo con la copia
# ---------------------------------------------------------------------
Az disk create -g $Grupo -n "$Respaldo-so" --source "$Respaldo-so-snap" --sku StandardSSD_LRS | Out-Null
$datos = @()
for ($j = 0; $j -lt $discosDatos.Count; $j++) {
    Az disk create -g $Grupo -n "$Respaldo-datos$j" --source "$Respaldo-datos$j-snap" --sku StandardSSD_LRS | Out-Null
    $datos += "$Respaldo-datos$j"
}

$crear = @('vm', 'create', '-g', $Grupo, '-n', $Respaldo, '-l', $region,
    '--attach-os-disk', "$Respaldo-so", '--os-type', 'Windows', '--size', $Tamano,
    '--subnet', $subred, '--nsg', $nsg,
    '--public-ip-address', "$Respaldo-ip", '--public-ip-address-dns-name', $DnsRespaldo,
    '--public-ip-sku', 'Standard')
if ($datos.Count -gt 0) { $crear += @('--attach-data-disks') + $datos }
Az @crear | Out-Null

foreach ($copia in $copias) {
    Az snapshot delete -g $Grupo -n $copia | Out-Null
}

$ipRespaldo = (Az network public-ip show -g $Grupo -n "$Respaldo-ip" -o json | ConvertFrom-Json).id
$privadaPrincipal = $nic.ipConfigurations[0].privateIPAddress
$nicRespaldo = Az vm show -g $Grupo -n $Respaldo -o json | ConvertFrom-Json
$privadaRespaldo = (Az network nic show --ids $nicRespaldo.networkProfile.networkInterfaces[0].id -o json | ConvertFrom-Json).ipConfigurations[0].privateIPAddress

# ---------------------------------------------------------------------
# 3. Traffic Manager: una dirección para las dos VM
# ---------------------------------------------------------------------
Az network traffic-manager profile create -g $Grupo -n jam-tm --routing-method Priority `
    --unique-dns-name $NombreGlobal --ttl 30 `
    --protocol HTTPS --port 443 --path '/ERROR404/api/v3/health' `
    --interval 10 --timeout 5 --max-failures 2 | Out-Null

Az network traffic-manager endpoint create -g $Grupo --profile-name jam-tm -n principal `
    --type azureEndpoints --target-resource-id $ipPrincipal --priority 1 | Out-Null
Az network traffic-manager endpoint create -g $Grupo --profile-name jam-tm -n respaldo `
    --type azureEndpoints --target-resource-id $ipRespaldo --priority 2 | Out-Null

# ---------------------------------------------------------------------
# 4. Puertos: 80 y 443 abiertos; 3389 solo desde tu IP; 1433 cerrado
# ---------------------------------------------------------------------
$nsgNombre = ($nsg -split '/')[-1]
$nsgGrupo = ($nsg -split '/')[4]
$reglas = Az network nsg rule list -g $nsgGrupo --nsg-name $nsgNombre -o json | ConvertFrom-Json
foreach ($r in $reglas | Where-Object { $_.direction -eq 'Inbound' -and $_.access -eq 'Allow' }) {
    $puertos = @($r.destinationPortRange) + @($r.destinationPortRanges) | Where-Object { $_ }
    if ($puertos -contains '3389') {
        Az network nsg rule update -g $nsgGrupo --nsg-name $nsgNombre -n $r.name --source-address-prefixes $MiIp | Out-Null
    }
    if ($puertos -contains '1433' -or $puertos -contains '8080' -or $puertos -contains '*') {
        Az network nsg rule delete -g $nsgGrupo --nsg-name $nsgNombre -n $r.name | Out-Null
    }
}
$web = $reglas | Where-Object { $_.direction -eq 'Inbound' -and $_.access -eq 'Allow' -and ((@($_.destinationPortRange) + @($_.destinationPortRanges)) -contains '443') }
if (-not $web) {
    Az network nsg rule create -g $nsgGrupo --nsg-name $nsgNombre -n Web-80-443 --priority 310 `
        --direction Inbound --access Allow --protocol Tcp --destination-port-ranges 80 443 | Out-Null
}

Write-Host ''
Write-Host 'Listo.'
Write-Host "  Dirección para los clientes:  https://$NombreGlobal.trafficmanager.net"
Write-Host "  VM de respaldo:               $DnsRespaldo.$region.cloudapp.azure.com"
Write-Host "  IP privada de la principal:   $privadaPrincipal   (la pide el script 03 en la VM de respaldo)"
Write-Host "  IP privada del respaldo:      $privadaRespaldo"
Write-Host 'Sigue con los pasos 2 a 4 de la sección 5.6 de docs/GUIA_DISTRIBUIDA_Y_NUBE.md.'
