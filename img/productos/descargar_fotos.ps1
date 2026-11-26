# Descarga las fotos de los productos desde Unsplash (licencia gratuita de Unsplash).
# Uso:
#   powershell -ExecutionPolicy Bypass -File C:\xampp\htdocs\ERROR404\img\productos\descargar_fotos.ps1
# Las fotos que ya existen no se vuelven a descargar; con -Forzar se descargan todas otra vez.

param([switch]$Forzar)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
Add-Type -AssemblyName System.Drawing

$carpeta = $PSScriptRoot
$ancho = 900
$calidad = 80

# Archivo (sin extensión) e id de la foto en Unsplash
$fotos = @(
    @('tenis-jam-breeze-knit', 'W0iDskWnbYc'),
    @('leggings-jam-bloom', 'AObxUL-uBdM'),
    @('raqueta-de-padel-jam-smash', 'OArzVT7Xt_E'),
    @('mochila-jam-urban-day', '3wcfuygszjU'),
    @('tenis-jam-night-run', 'FvURQ4dmNFk'),
    @('top-jam-sprint', 'iEllR5Cw2Xo'),
    @('tank-top-jam-breeze', 'aLm_yIpTH8E'),
    @('sandalias-de-playa-jam-beach', 'GC__j7qiOuM'),
    @('lentes-de-sol-jam-solar', 'Zj18Eq3g7Jk'),
    @('lentes-jam-shade-polarizados', 'j88AxDXvqe8'),
    @('balon-de-voleibol-de-playa-jam-sand', 'lLZQcmullqA'),
    @('balon-de-futbol-rapido-jam-futsal', 'z1gNxguQblg'),
    @('tank-jam-lift', 'Awxc8x5l4I4'),
    @('tenis-jam-court-air', 'uFB2XmLBzkA'),
    @('tenis-jam-rain-run', '_aXPi9HNIE4'),
    @('sudadera-jam-trail', 'jjLylJOYlYg'),
    @('mochila-de-hidratacion-jam-trail', 'bQl2kRQyUE8'),
    @('tenis-jam-street-low', 'NUoPWImmjCU'),
    @('jersey-jam-club-visita', 'lQpFRPrepQ8'),
    @('tenis-jam-retro-run', 'LG88A2XgIXY'),
    @('guantes-de-box-jam-fight-pro', 'nfZKyKlGujs'),
    @('balon-de-futbol-americano-jam-junior', 'm5pz9D6MfUk'),
    @('chamarra-acolchada-jam-puffer', '2_r8gpoMBuA'),
    @('chamarra-jam-alpine', 'CDw4fFMjiAs'),
    @('gorro-termico-jam-beanie', 'kVg2DQTAK7c'),
    @('gorro-jam-knit', 'V1Y7B7ofJ3E'),
    @('leggings-termicos-jam-heat', '6n89mlApwFI'),
    @('sudadera-polar-jam-fleece', 'yIm6-EI2zaE'),
    @('botas-jam-snow-trek', 'NHsQt3h-pTM'),
    @('mochila-escolar-jam-campus', '43xpOwurMEI'),
    @('tenis-escolares-jam-class', '1NXsoyQaP5M'),
    @('botella-deportiva-jam-squeeze-600-ml', 'a_Q3fPs3qLs'),
    @('sudadera-jam-holiday-stripe', '_T0KBzDlqzE'),
    @('calcetas-jam-holiday-3-pares', 'sYgzIZKxqyA'),
    @('mancuernas-jam-power-5-kg-par', 'iOO4HtfjpvE'),
    @('mancuernas-jam-color-3-kg-par', 'jlf9QyI250Y'),
    @('tenis-jam-gift-edition', 'Cn2KD5xy9q8'),
    @('playera-dry-fit-jam-pro', 'YChGF6VS8Lc'),
    @('legging-compresion-jam-flex', 'k3slHCgiE1o'),
    @('sudadera-hoodie-jam-core', 'YNqHywGWGos'),
    @('tenis-trainer-jam-stride', 'LxVxPA1LOVM'),
    @('tenis-running-jam-pulse', 'XiZ7pRvCzro'),
    @('tenis-urbanos-jam-street', 'jKZ8qO6juik'),
    @('tenis-basketball-jam-jump', 'Px3xY9WT0BE'),
    @('sandalias-jam-recovery-slide', 'J66Ef_1JUM8'),
    @('top-deportivo-jam-core', '5IsdIqwwNP4'),
    @('jersey-futbol-jam-club', '2dKKKbUjh1Y'),
    @('pants-jogger-jam-move', 'ZvyGHdfRTL4'),
    @('chamarra-rompevientos-jam-storm', 'Uhbf6jdnOsA'),
    @('leggings-jam-seamless', 'hWgsxV_VQW0'),
    @('gorra-jam-sport-cap', 'jmqumq7H72s'),
    @('mochila-jam-gym-pack', 'OYqSDS-CDjI'),
    @('lentes-para-correr-jam-vision', 'R9nEbP0wVZ4'),
    @('goggles-de-natacion-jam-splash', 'aY1f-544G9U'),
    @('gorra-para-correr-jam-run-light', '_yAb0c5yHrU'),
    @('guantes-de-box-jam-fight-12-oz', 'XmvuWRDimrg'),
    @('balon-de-futbol-jam-strike-no-5', 'C64fclQF3Cc'),
    @('balon-de-basquetbol-jam-court-no-7', 'pBVzgXe1Q1I'),
    @('balon-de-voleibol-jam-spike', 'BIIgxvDhG2s'),
    @('pelotas-de-padel-jam-pro-3-piezas', '0wbYOLZwDPY'),
    @('balon-de-futbol-americano-jam-blitz', 'ybpcPXitWPI'),
    @('tenis-de-futbol-jam-strike-fg', 'GgEtIbD0hVo'),
    @('tenis-de-entrenamiento-jam-flow', '4qSb_FWhHKs'),
    @('tenis-jam-sprint-jr', 'dwKiHoqqxk8'),
    @('tenis-running-jam-pulse-2025', 'A4579vLezz8'),
    @('tenis-urbanos-jam-street-classic-2025', '6zO5VKogoZE'),
    @('tenis-trainer-jam-stride-2025', 'ao3VF36gi1c'),
    @('sandalias-jam-slide-2025', 'c1rOy44wuts'),
    @('playera-jam-pro-2025', '5Qcd-AEg4ns'),
    @('top-deportivo-jam-core-2025', 'Srq86uTa7i0'),
    @('leggings-jam-flex-2025', '_0zRdFnNrRQ'),
    @('sudadera-jam-core-2025', 'Gyyt9LQzi34'),
    @('balon-de-futbol-jam-strike-2025', 'Zjcf_V3DPVI')
)

$codec = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { $_.MimeType -eq 'image/jpeg' }
$parametros = New-Object System.Drawing.Imaging.EncoderParameters(1)
$parametros.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter([System.Drawing.Imaging.Encoder]::Quality, [long]$calidad)

function Guardar-Reducida([string]$origen, [string]$destino) {
    $imagen = [System.Drawing.Image]::FromFile($origen)
    try {
        $w = [Math]::Min($ancho, $imagen.Width)
        $h = [int][Math]::Round($imagen.Height * $w / $imagen.Width)
        $lienzo = New-Object System.Drawing.Bitmap($w, $h)
        $g = [System.Drawing.Graphics]::FromImage($lienzo)
        $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
        $g.DrawImage($imagen, 0, 0, $w, $h)
        $g.Dispose()
        $lienzo.Save($destino, $codec, $parametros)
        $lienzo.Dispose()
    } finally {
        $imagen.Dispose()
    }
}

$navegador = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36'
$curl = Get-Command curl.exe -ErrorAction SilentlyContinue

# Baja la foto con curl.exe (incluido en Windows 10 y 11) y, si no está, con Invoke-WebRequest
function Bajar([string]$url, [string]$salida) {
    if (Test-Path $salida) { Remove-Item $salida -Force }
    if ($curl) {
        $codigo = & $curl.Source -s -L --max-time 60 -A $navegador -H 'Accept: image/jpeg,image/*' -o $salida -w '%{http_code}' $url
        if ($codigo -ne '200') { throw ('el servidor respondió ' + $codigo) }
    } else {
        Invoke-WebRequest -Uri $url -OutFile $salida -UseBasicParsing -TimeoutSec 60 -UserAgent $navegador -Headers @{ Accept = 'image/jpeg,image/*' }
    }
    $bytes = [System.IO.File]::ReadAllBytes($salida)
    $jpg = $bytes.Length -gt 4 -and $bytes[0] -eq 0xFF -and $bytes[1] -eq 0xD8
    $png = $bytes.Length -gt 4 -and $bytes[0] -eq 0x89 -and $bytes[1] -eq 0x50
    if ($bytes.Length -lt 5000 -or -not ($jpg -or $png)) {
        $inicio = [System.Text.Encoding]::ASCII.GetString($bytes, 0, [Math]::Min(80, $bytes.Length)) -replace '\s+', ' '
        throw ('no llegó una foto (' + $bytes.Length + ' bytes: ' + $inicio + ')')
    }
}

$bien = 0; $omitidas = 0; $fallas = @()
$temporal = Join-Path $env:TEMP 'jam_foto.tmp'

foreach ($foto in $fotos) {
    $archivo = $foto[0] + '.jpg'
    $destino = Join-Path $carpeta $archivo
    if ((Test-Path $destino) -and -not $Forzar) { $omitidas++; continue }

    $urls = @(
        ('https://unsplash.com/photos/' + $foto[1] + '/download?force=true&w=1200&fm=jpg'),
        ('https://unsplash.com/photos/' + $foto[1] + '/download?force=true')
    )
    $listo = $false; $error1 = ''
    foreach ($url in $urls) {
        if ($listo) { break }
        try {
            Bajar $url $temporal
            Guardar-Reducida $temporal $destino
            $listo = $true
        } catch {
            if (-not $error1) { $error1 = $_.Exception.Message }
            Start-Sleep -Milliseconds 800
        }
    }
    if ($listo) { $bien++; Write-Host ('  OK  ' + $archivo) -ForegroundColor Green }
    else {
        $fallas += $archivo + ': ' + $error1
        Write-Host ('  X   ' + $archivo + '  (' + $error1 + ')') -ForegroundColor Red
    }
}

Remove-Item $temporal -ErrorAction SilentlyContinue
Write-Host ''
Write-Host ('Descargadas: ' + $bien + '   Ya existían: ' + $omitidas + '   Fallaron: ' + $fallas.Count)
if ($fallas.Count) {
    Write-Host 'Las que fallaron se ven con la ilustración del producto. Puedes volver a correr el script.' -ForegroundColor Yellow
}
Write-Host 'Recarga la tienda con Ctrl + F5.'
