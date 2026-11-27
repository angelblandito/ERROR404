# Fotos de los productos

Coloca aquí las fotos de la ropa. La tienda las muestra automáticamente; si una
foto no existe, aparece la ilustración del producto en ese color.

## Descargar las fotos reales

`descargar_fotos.ps1` baja 73 fotos de [Unsplash](https://unsplash.com/license)
(uso gratuito) y las guarda aquí con el nombre de cada producto:

```powershell
powershell -ExecutionPolicy Bypass -File C:\xampp\htdocs\ERROR404\img\productos\descargar_fotos.ps1
```

Los créditos de cada foto están en `CREDITOS.md`.

## Foto por nombre del producto

`<nombre-del-producto>.jpg`: el nombre en minúsculas, sin acentos y con guiones.
Ejemplo: *Balón de fútbol JAM Strike No. 5* → `balon-de-futbol-jam-strike-no-5.jpg`.
Se usa para todos los colores del producto.

## Foto por color

`<id_producto>-<color>.jpg` (o `.png` / `.webp`), en minúsculas. Ejemplo: `6-black.jpg` es el
Tenis Running JAM Pulse en color negro.

Colores: `black` (negro), `white` (blanco), `red` (rojo), `navy` (azul marino),
`gray` (gris), `pink` (rosa), `green` (verde), `blue` (azul rey).

## Recomendaciones

- Formato JPG, PNG o WebP (`.jpg`, `.png`, `.webp`), cuadrada (por ejemplo 1000 × 1000 px) y menor a 300 KB.
- Fondo claro y el producto centrado: la foto se recorta para llenar el cuadro.
- Después de copiar una foto, recarga la página con Ctrl + F5.

## Archivos por producto

| ID | Producto | Archivos |
|----|----------|----------|
| 1 | Playera Dry-Fit JAM Pro | `1-black.jpg`, `1-white.jpg`, `1-red.jpg` |
| 2 | Short Running JAM Air | `2-navy.jpg`, `2-black.jpg` |
| 3 | Legging Compresión JAM Flex | `3-black.jpg`, `3-gray.jpg` |
| 4 | Sudadera Hoodie JAM Core | `4-gray.jpg`, `4-black.jpg`, `4-navy.jpg` |
| 5 | Tenis Trainer JAM Stride | `5-white.jpg`, `5-black.jpg` |
| 6 | Tenis Running JAM Pulse | `6-black.jpg`, `6-white.jpg`, `6-blue.jpg` |
| 7 | Tenis Urbanos JAM Street | `7-white.jpg`, `7-black.jpg` |
| 8 | Tenis Basketball JAM Jump | `8-red.jpg`, `8-black.jpg` |
| 9 | Sandalias JAM Recovery Slide | `9-black.jpg`, `9-navy.jpg` |
| 10 | Top Deportivo JAM Core | `10-pink.jpg`, `10-black.jpg` |
| 11 | Jersey Futbol JAM Club | `11-green.jpg`, `11-white.jpg` |
| 12 | Pants Jogger JAM Move | `12-gray.jpg`, `12-black.jpg` |
| 13 | Chamarra Rompevientos JAM Storm | `13-navy.jpg`, `13-black.jpg` |
| 14 | Leggings JAM Seamless | `14-black.jpg`, `14-blue.jpg` |
| 15 | Gorra JAM Sport Cap | `15-black.jpg`, `15-white.jpg`, `15-red.jpg` |
| 16 | Mochila JAM Gym Pack | `16-gray.jpg`, `16-black.jpg` |
| 17 | Botella Térmica JAM 750 ml | `17-blue.jpg`, `17-black.jpg`, `17-white.jpg` |
| 18 | Calcetas JAM Pro (3 pares) | `18-white.jpg`, `18-black.jpg` |

## Producto nuevo

Si agregas un producto, su ID es el que le asigna la base de datos (columna
`id_product`). También puedes guardar una sola foto para todos los colores en
la columna `product.image_url`, por ejemplo `img/productos/mi-producto.jpg`.
