/* =====================================================================
   JAM - Base de datos distribuida
   08. Catálogo por temporada
   ---------------------------------------------------------------------
   Requisitos: db_jam.sql ejecutado (01 a 07 opcionales).

   Solo AGREGA datos; no borra ni modifica lo que ya existe:
     1. Tabla dbo.product_season: qué productos pertenecen a cada
        temporada o campaña de dbo.season_theme (relación N a N).
     2. Productos nuevos de primavera, verano, otoño, invierno,
        regreso a clases y Navidad, con sus tallas, colores e inventario
        en Tijuana (1), CDMX (2) y la tienda en línea (4).
     3. Temporadas de los productos que ya estaban en el catálogo.

   Se puede ejecutar varias veces: cada INSERT revisa si el registro ya
   existe. Al final se sincronizan los fragmentos si los scripts 01 y 02
   ya se ejecutaron; la replicación (03) envía los productos nuevos a los
   nodos sola porque product y product_variant ya están publicados.
   ===================================================================== */

USE JAM_db;
GO
/* Los índices filtrados (sku, barcode) necesitan QUOTED_IDENTIFIER ON */
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------
   1. Relación producto - temporada
   --------------------------------------------------------------------- */
IF OBJECT_ID(N'dbo.product_season', N'U') IS NULL
CREATE TABLE dbo.product_season (
    id_product   INT          NOT NULL,
    id_theme     TINYINT      NOT NULL,
    -- columnas de consolidación
    source_node  VARCHAR(20)  NOT NULL DEFAULT 'catalog_node',
    updated_at   DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted      BIT          NOT NULL DEFAULT 0,
    CONSTRAINT pk_product_season PRIMARY KEY (id_product, id_theme),
    CONSTRAINT fk_product_season_product FOREIGN KEY (id_product) REFERENCES dbo.product (id_product),
    CONSTRAINT fk_product_season_theme FOREIGN KEY (id_theme) REFERENCES dbo.season_theme (id_theme)
);
GO

/* ---------------------------------------------------------------------
   2. Productos nuevos
   --------------------------------------------------------------------- */
INSERT INTO dbo.product (id_category, name, description, target_gender, base_price, age_segment, source_node)
SELECT n.id_category, n.name, n.description, n.target_gender, n.base_price, n.age_segment, 'catalog_node'
FROM (VALUES
(5, N'Tenis JAM Breeze Knit', N'Tenis de tejido transpirable para correr en primavera', 'U', 1490.00, 'adult'),
(3, N'Leggings JAM Bloom', N'Leggings ligeros de cintura alta para yoga y pilates', 'F', 560.00, 'all'),
(6, N'Raqueta de pádel JAM Smash', N'Raqueta de pádel de fibra de vidrio con núcleo EVA suave', 'U', 1290.00, 'adult'),
(6, N'Mochila JAM Urban Day', N'Mochila de 20 L para la escuela, el gym y la ciudad', 'U', 790.00, 'youth'),
(5, N'Tenis JAM Night Run', N'Tenis con detalles reflejantes para correr de noche', 'U', 1590.00, 'adult'),
(1, N'Top JAM Sprint', N'Top de soporte alto con espalda cruzada', 'F', 420.00, 'all'),
(1, N'Tank top JAM Breeze', N'Playera sin mangas de malla fresca para el calor', 'M', 350.00, 'all'),
(5, N'Sandalias de playa JAM Beach', N'Sandalias ligeras que secan rápido para playa y alberca', 'U', 290.00, 'all'),
(7, N'Lentes de sol JAM Solar', N'Lentes de sol con protección UV400 para días de playa', 'U', 590.00, 'youth'),
(7, N'Lentes JAM Shade Polarizados', N'Lentes polarizados para correr y andar en bici', 'U', 790.00, 'adult'),
(9, N'Balón de voleibol de playa JAM Sand', N'Balón suave de costura a mano para voleibol de playa', 'U', 480.00, 'all'),
(9, N'Balón de fútbol rápido JAM Futsal', N'Balón de bote bajo para canchas de fútbol rápido', 'U', 520.00, 'youth'),
(1, N'Tank JAM Lift', N'Tank top de algodón y poliéster para pesas', 'M', 380.00, 'adult'),
(5, N'Tenis JAM Court Air', N'Tenis de básquetbol de caña baja con buena ventilación', 'U', 1690.00, 'youth'),
(5, N'Tenis JAM Rain Run', N'Tenis para correr con capa repelente al agua', 'U', 1790.00, 'adult'),
(4, N'Sudadera JAM Trail', N'Sudadera con capucha para caminatas y días frescos', 'U', 790.00, 'all'),
(6, N'Mochila de hidratación JAM Trail', N'Mochila ligera con bolsa para agua de 1.5 L', 'U', 990.00, 'adult'),
(5, N'Tenis JAM Street Low', N'Tenis urbanos de piel sintética para diario', 'U', 1390.00, 'youth'),
(1, N'Jersey JAM Club Visita', N'Jersey de visitante transpirable para fútbol', 'U', 650.00, 'youth'),
(5, N'Tenis JAM Retro Run', N'Tenis de estilo retro con suela de goma', 'U', 1450.00, 'youth'),
(8, N'Guantes de box JAM Fight Pro', N'Guantes de 14 oz con relleno de doble densidad', 'U', 990.00, 'adult'),
(9, N'Balón de fútbol americano JAM Junior', N'Balón tamaño juvenil fácil de lanzar', 'U', 390.00, 'youth'),
(4, N'Chamarra acolchada JAM Puffer', N'Chamarra acolchada ligera que guarda el calor', 'U', 1690.00, 'all'),
(4, N'Chamarra JAM Alpine', N'Chamarra impermeable para nieve y frío intenso', 'U', 1890.00, 'adult'),
(6, N'Gorro térmico JAM Beanie', N'Gorro tejido con forro térmico', 'U', 260.00, 'all'),
(6, N'Gorro JAM Knit', N'Gorro de punto grueso para el frío', 'U', 290.00, 'youth'),
(3, N'Leggings térmicos JAM Heat', N'Leggings con interior afelpado para correr con frío', 'F', 690.00, 'adult'),
(4, N'Sudadera polar JAM Fleece', N'Sudadera de tela polar suave y abrigadora', 'U', 850.00, 'all'),
(5, N'Botas JAM Snow Trek', N'Botas impermeables con suela antiderrapante', 'U', 1990.00, 'adult'),
(6, N'Mochila escolar JAM Campus', N'Mochila escolar de 28 L con espacio para laptop', 'U', 690.00, 'youth'),
(5, N'Tenis escolares JAM Class', N'Tenis cómodos para la escuela y educación física', 'U', 990.00, 'youth'),
(6, N'Botella deportiva JAM Squeeze 600 ml', N'Botella flexible libre de BPA con tapa de chupón', 'U', 190.00, 'all'),
(4, N'Sudadera JAM Holiday Stripe', N'Sudadera de rayas edición navideña', 'U', 820.00, 'all'),
(6, N'Calcetas JAM Holiday (3 pares)', N'Set de regalo de calcetas de colores', 'U', 260.00, 'all'),
(6, N'Mancuernas JAM Power 5 kg (par)', N'Par de mancuernas de 5 kg recubiertas de hule', 'U', 790.00, 'adult'),
(6, N'Mancuernas JAM Color 3 kg (par)', N'Par de mancuernas de 3 kg con agarre cómodo', 'U', 490.00, 'all'),
(5, N'Tenis JAM Gift Edition', N'Tenis edición especial en caja de regalo', 'U', 1690.00, 'youth')
) AS n (id_category, name, description, target_gender, base_price, age_segment)
WHERE NOT EXISTS (SELECT 1 FROM dbo.product AS p WHERE p.name = n.name);
GO

/* Tallas y colores (se buscan por nombre de producto, talla y color) */
INSERT INTO dbo.product_variant (id_product, id_size, id_color, sku, source_node)
SELECT p.id_product, sz.id_size, cl.id_color, v.sku, 'catalog_node'
FROM (VALUES
(N'Tenis JAM Breeze Knit', '25', 'white', 'JAM-BRZ-25-WHT'),
(N'Tenis JAM Breeze Knit', '26', 'white', 'JAM-BRZ-26-WHT'),
(N'Tenis JAM Breeze Knit', '27', 'white', 'JAM-BRZ-27-WHT'),
(N'Tenis JAM Breeze Knit', '28', 'white', 'JAM-BRZ-28-WHT'),
(N'Tenis JAM Breeze Knit', '25', 'red', 'JAM-BRZ-25-RED'),
(N'Tenis JAM Breeze Knit', '26', 'red', 'JAM-BRZ-26-RED'),
(N'Tenis JAM Breeze Knit', '27', 'red', 'JAM-BRZ-27-RED'),
(N'Tenis JAM Breeze Knit', '28', 'red', 'JAM-BRZ-28-RED'),
(N'Leggings JAM Bloom', 'XS', 'blue', 'JAM-BLM-XS-BLU'),
(N'Leggings JAM Bloom', 'S', 'blue', 'JAM-BLM-S-BLU'),
(N'Leggings JAM Bloom', 'M', 'blue', 'JAM-BLM-M-BLU'),
(N'Leggings JAM Bloom', 'L', 'blue', 'JAM-BLM-L-BLU'),
(N'Leggings JAM Bloom', 'XS', 'black', 'JAM-BLM-XS-BLK'),
(N'Leggings JAM Bloom', 'S', 'black', 'JAM-BLM-S-BLK'),
(N'Leggings JAM Bloom', 'M', 'black', 'JAM-BLM-M-BLK'),
(N'Leggings JAM Bloom', 'L', 'black', 'JAM-BLM-L-BLK'),
(N'Raqueta de pádel JAM Smash', 'UNI', 'black', 'JAM-SMH-UNI-BLK'),
(N'Raqueta de pádel JAM Smash', 'UNI', 'blue', 'JAM-SMH-UNI-BLU'),
(N'Mochila JAM Urban Day', 'UNI', 'red', 'JAM-URB-UNI-RED'),
(N'Mochila JAM Urban Day', 'UNI', 'black', 'JAM-URB-UNI-BLK'),
(N'Tenis JAM Night Run', '25', 'black', 'JAM-NGT-25-BLK'),
(N'Tenis JAM Night Run', '26', 'black', 'JAM-NGT-26-BLK'),
(N'Tenis JAM Night Run', '27', 'black', 'JAM-NGT-27-BLK'),
(N'Tenis JAM Night Run', '28', 'black', 'JAM-NGT-28-BLK'),
(N'Top JAM Sprint', 'XS', 'black', 'JAM-SPT-XS-BLK'),
(N'Top JAM Sprint', 'S', 'black', 'JAM-SPT-S-BLK'),
(N'Top JAM Sprint', 'M', 'black', 'JAM-SPT-M-BLK'),
(N'Top JAM Sprint', 'L', 'black', 'JAM-SPT-L-BLK'),
(N'Top JAM Sprint', 'XS', 'red', 'JAM-SPT-XS-RED'),
(N'Top JAM Sprint', 'S', 'red', 'JAM-SPT-S-RED'),
(N'Top JAM Sprint', 'M', 'red', 'JAM-SPT-M-RED'),
(N'Top JAM Sprint', 'L', 'red', 'JAM-SPT-L-RED'),
(N'Tank top JAM Breeze', 'S', 'gray', 'JAM-TNK-S-GRY'),
(N'Tank top JAM Breeze', 'M', 'gray', 'JAM-TNK-M-GRY'),
(N'Tank top JAM Breeze', 'L', 'gray', 'JAM-TNK-L-GRY'),
(N'Tank top JAM Breeze', 'XL', 'gray', 'JAM-TNK-XL-GRY'),
(N'Tank top JAM Breeze', 'S', 'black', 'JAM-TNK-S-BLK'),
(N'Tank top JAM Breeze', 'M', 'black', 'JAM-TNK-M-BLK'),
(N'Tank top JAM Breeze', 'L', 'black', 'JAM-TNK-L-BLK'),
(N'Tank top JAM Breeze', 'XL', 'black', 'JAM-TNK-XL-BLK'),
(N'Tank top JAM Breeze', 'S', 'white', 'JAM-TNK-S-WHT'),
(N'Tank top JAM Breeze', 'M', 'white', 'JAM-TNK-M-WHT'),
(N'Tank top JAM Breeze', 'L', 'white', 'JAM-TNK-L-WHT'),
(N'Tank top JAM Breeze', 'XL', 'white', 'JAM-TNK-XL-WHT'),
(N'Sandalias de playa JAM Beach', '25', 'black', 'JAM-BCH-25-BLK'),
(N'Sandalias de playa JAM Beach', '26', 'black', 'JAM-BCH-26-BLK'),
(N'Sandalias de playa JAM Beach', '27', 'black', 'JAM-BCH-27-BLK'),
(N'Sandalias de playa JAM Beach', '28', 'black', 'JAM-BCH-28-BLK'),
(N'Sandalias de playa JAM Beach', '25', 'white', 'JAM-BCH-25-WHT'),
(N'Sandalias de playa JAM Beach', '26', 'white', 'JAM-BCH-26-WHT'),
(N'Sandalias de playa JAM Beach', '27', 'white', 'JAM-BCH-27-WHT'),
(N'Sandalias de playa JAM Beach', '28', 'white', 'JAM-BCH-28-WHT'),
(N'Lentes de sol JAM Solar', 'UNI', 'black', 'JAM-SOL-UNI-BLK'),
(N'Lentes de sol JAM Solar', 'UNI', 'amber', 'JAM-SOL-UNI-AMB'),
(N'Lentes de sol JAM Solar', 'UNI', 'blue', 'JAM-SOL-UNI-BLU'),
(N'Lentes JAM Shade Polarizados', 'UNI', 'black', 'JAM-SHD-UNI-BLK'),
(N'Balón de voleibol de playa JAM Sand', 'UNI', 'blue', 'JAM-SND-UNI-BLU'),
(N'Balón de voleibol de playa JAM Sand', 'UNI', 'lime', 'JAM-SND-UNI-LIM'),
(N'Balón de fútbol rápido JAM Futsal', 'UNI', 'white', 'JAM-FSL-UNI-WHT'),
(N'Balón de fútbol rápido JAM Futsal', 'UNI', 'amber', 'JAM-FSL-UNI-AMB'),
(N'Tank JAM Lift', 'S', 'gray', 'JAM-LFT-S-GRY'),
(N'Tank JAM Lift', 'M', 'gray', 'JAM-LFT-M-GRY'),
(N'Tank JAM Lift', 'L', 'gray', 'JAM-LFT-L-GRY'),
(N'Tank JAM Lift', 'XL', 'gray', 'JAM-LFT-XL-GRY'),
(N'Tank JAM Lift', 'S', 'black', 'JAM-LFT-S-BLK'),
(N'Tank JAM Lift', 'M', 'black', 'JAM-LFT-M-BLK'),
(N'Tank JAM Lift', 'L', 'black', 'JAM-LFT-L-BLK'),
(N'Tank JAM Lift', 'XL', 'black', 'JAM-LFT-XL-BLK'),
(N'Tenis JAM Court Air', '25', 'white', 'JAM-CAI-25-WHT'),
(N'Tenis JAM Court Air', '26', 'white', 'JAM-CAI-26-WHT'),
(N'Tenis JAM Court Air', '27', 'white', 'JAM-CAI-27-WHT'),
(N'Tenis JAM Court Air', '28', 'white', 'JAM-CAI-28-WHT'),
(N'Tenis JAM Court Air', '25', 'black', 'JAM-CAI-25-BLK'),
(N'Tenis JAM Court Air', '26', 'black', 'JAM-CAI-26-BLK'),
(N'Tenis JAM Court Air', '27', 'black', 'JAM-CAI-27-BLK'),
(N'Tenis JAM Court Air', '28', 'black', 'JAM-CAI-28-BLK'),
(N'Tenis JAM Rain Run', '25', 'gray', 'JAM-RAN-25-GRY'),
(N'Tenis JAM Rain Run', '26', 'gray', 'JAM-RAN-26-GRY'),
(N'Tenis JAM Rain Run', '27', 'gray', 'JAM-RAN-27-GRY'),
(N'Tenis JAM Rain Run', '28', 'gray', 'JAM-RAN-28-GRY'),
(N'Tenis JAM Rain Run', '25', 'white', 'JAM-RAN-25-WHT'),
(N'Tenis JAM Rain Run', '26', 'white', 'JAM-RAN-26-WHT'),
(N'Tenis JAM Rain Run', '27', 'white', 'JAM-RAN-27-WHT'),
(N'Tenis JAM Rain Run', '28', 'white', 'JAM-RAN-28-WHT'),
(N'Sudadera JAM Trail', 'S', 'gray', 'JAM-TRL-S-GRY'),
(N'Sudadera JAM Trail', 'M', 'gray', 'JAM-TRL-M-GRY'),
(N'Sudadera JAM Trail', 'L', 'gray', 'JAM-TRL-L-GRY'),
(N'Sudadera JAM Trail', 'XL', 'gray', 'JAM-TRL-XL-GRY'),
(N'Sudadera JAM Trail', 'S', 'green', 'JAM-TRL-S-GRN'),
(N'Sudadera JAM Trail', 'M', 'green', 'JAM-TRL-M-GRN'),
(N'Sudadera JAM Trail', 'L', 'green', 'JAM-TRL-L-GRN'),
(N'Sudadera JAM Trail', 'XL', 'green', 'JAM-TRL-XL-GRN'),
(N'Mochila de hidratación JAM Trail', 'UNI', 'gray', 'JAM-HYD-UNI-GRY'),
(N'Mochila de hidratación JAM Trail', 'UNI', 'amber', 'JAM-HYD-UNI-AMB'),
(N'Tenis JAM Street Low', '25', 'white', 'JAM-SLW-25-WHT'),
(N'Tenis JAM Street Low', '26', 'white', 'JAM-SLW-26-WHT'),
(N'Tenis JAM Street Low', '27', 'white', 'JAM-SLW-27-WHT'),
(N'Tenis JAM Street Low', '28', 'white', 'JAM-SLW-28-WHT'),
(N'Tenis JAM Street Low', '25', 'black', 'JAM-SLW-25-BLK'),
(N'Tenis JAM Street Low', '26', 'black', 'JAM-SLW-26-BLK'),
(N'Tenis JAM Street Low', '27', 'black', 'JAM-SLW-27-BLK'),
(N'Tenis JAM Street Low', '28', 'black', 'JAM-SLW-28-BLK'),
(N'Jersey JAM Club Visita', 'S', 'white', 'JAM-CLV-S-WHT'),
(N'Jersey JAM Club Visita', 'M', 'white', 'JAM-CLV-M-WHT'),
(N'Jersey JAM Club Visita', 'L', 'white', 'JAM-CLV-L-WHT'),
(N'Jersey JAM Club Visita', 'XL', 'white', 'JAM-CLV-XL-WHT'),
(N'Jersey JAM Club Visita', 'S', 'red', 'JAM-CLV-S-RED'),
(N'Jersey JAM Club Visita', 'M', 'red', 'JAM-CLV-M-RED'),
(N'Jersey JAM Club Visita', 'L', 'red', 'JAM-CLV-L-RED'),
(N'Jersey JAM Club Visita', 'XL', 'red', 'JAM-CLV-XL-RED'),
(N'Tenis JAM Retro Run', '25', 'white', 'JAM-RTR-25-WHT'),
(N'Tenis JAM Retro Run', '26', 'white', 'JAM-RTR-26-WHT'),
(N'Tenis JAM Retro Run', '27', 'white', 'JAM-RTR-27-WHT'),
(N'Tenis JAM Retro Run', '28', 'white', 'JAM-RTR-28-WHT'),
(N'Tenis JAM Retro Run', '25', 'red', 'JAM-RTR-25-RED'),
(N'Tenis JAM Retro Run', '26', 'red', 'JAM-RTR-26-RED'),
(N'Tenis JAM Retro Run', '27', 'red', 'JAM-RTR-27-RED'),
(N'Tenis JAM Retro Run', '28', 'red', 'JAM-RTR-28-RED'),
(N'Guantes de box JAM Fight Pro', 'UNI', 'red', 'JAM-FPR-UNI-RED'),
(N'Balón de fútbol americano JAM Junior', 'UNI', 'brown', 'JAM-AMJ-UNI-BRN'),
(N'Chamarra acolchada JAM Puffer', 'S', 'black', 'JAM-PUF-S-BLK'),
(N'Chamarra acolchada JAM Puffer', 'M', 'black', 'JAM-PUF-M-BLK'),
(N'Chamarra acolchada JAM Puffer', 'L', 'black', 'JAM-PUF-L-BLK'),
(N'Chamarra acolchada JAM Puffer', 'XL', 'black', 'JAM-PUF-XL-BLK'),
(N'Chamarra acolchada JAM Puffer', 'S', 'navy', 'JAM-PUF-S-NVY'),
(N'Chamarra acolchada JAM Puffer', 'M', 'navy', 'JAM-PUF-M-NVY'),
(N'Chamarra acolchada JAM Puffer', 'L', 'navy', 'JAM-PUF-L-NVY'),
(N'Chamarra acolchada JAM Puffer', 'XL', 'navy', 'JAM-PUF-XL-NVY'),
(N'Chamarra JAM Alpine', 'S', 'white', 'JAM-ALP-S-WHT'),
(N'Chamarra JAM Alpine', 'M', 'white', 'JAM-ALP-M-WHT'),
(N'Chamarra JAM Alpine', 'L', 'white', 'JAM-ALP-L-WHT'),
(N'Chamarra JAM Alpine', 'XL', 'white', 'JAM-ALP-XL-WHT'),
(N'Chamarra JAM Alpine', 'S', 'black', 'JAM-ALP-S-BLK'),
(N'Chamarra JAM Alpine', 'M', 'black', 'JAM-ALP-M-BLK'),
(N'Chamarra JAM Alpine', 'L', 'black', 'JAM-ALP-L-BLK'),
(N'Chamarra JAM Alpine', 'XL', 'black', 'JAM-ALP-XL-BLK'),
(N'Gorro térmico JAM Beanie', 'UNI', 'black', 'JAM-BNI-UNI-BLK'),
(N'Gorro térmico JAM Beanie', 'UNI', 'gray', 'JAM-BNI-UNI-GRY'),
(N'Gorro JAM Knit', 'UNI', 'white', 'JAM-KNT-UNI-WHT'),
(N'Gorro JAM Knit', 'UNI', 'navy', 'JAM-KNT-UNI-NVY'),
(N'Leggings térmicos JAM Heat', 'XS', 'black', 'JAM-HEA-XS-BLK'),
(N'Leggings térmicos JAM Heat', 'S', 'black', 'JAM-HEA-S-BLK'),
(N'Leggings térmicos JAM Heat', 'M', 'black', 'JAM-HEA-M-BLK'),
(N'Leggings térmicos JAM Heat', 'L', 'black', 'JAM-HEA-L-BLK'),
(N'Sudadera polar JAM Fleece', 'S', 'gray', 'JAM-FLC-S-GRY'),
(N'Sudadera polar JAM Fleece', 'M', 'gray', 'JAM-FLC-M-GRY'),
(N'Sudadera polar JAM Fleece', 'L', 'gray', 'JAM-FLC-L-GRY'),
(N'Sudadera polar JAM Fleece', 'XL', 'gray', 'JAM-FLC-XL-GRY'),
(N'Sudadera polar JAM Fleece', 'S', 'black', 'JAM-FLC-S-BLK'),
(N'Sudadera polar JAM Fleece', 'M', 'black', 'JAM-FLC-M-BLK'),
(N'Sudadera polar JAM Fleece', 'L', 'black', 'JAM-FLC-L-BLK'),
(N'Sudadera polar JAM Fleece', 'XL', 'black', 'JAM-FLC-XL-BLK'),
(N'Botas JAM Snow Trek', '25', 'brown', 'JAM-SNW-25-BRN'),
(N'Botas JAM Snow Trek', '26', 'brown', 'JAM-SNW-26-BRN'),
(N'Botas JAM Snow Trek', '27', 'brown', 'JAM-SNW-27-BRN'),
(N'Botas JAM Snow Trek', '28', 'brown', 'JAM-SNW-28-BRN'),
(N'Botas JAM Snow Trek', '25', 'black', 'JAM-SNW-25-BLK'),
(N'Botas JAM Snow Trek', '26', 'black', 'JAM-SNW-26-BLK'),
(N'Botas JAM Snow Trek', '27', 'black', 'JAM-SNW-27-BLK'),
(N'Botas JAM Snow Trek', '28', 'black', 'JAM-SNW-28-BLK'),
(N'Mochila escolar JAM Campus', 'UNI', 'black', 'JAM-CMP-UNI-BLK'),
(N'Mochila escolar JAM Campus', 'UNI', 'navy', 'JAM-CMP-UNI-NVY'),
(N'Tenis escolares JAM Class', '25', 'white', 'JAM-CLS-25-WHT'),
(N'Tenis escolares JAM Class', '26', 'white', 'JAM-CLS-26-WHT'),
(N'Tenis escolares JAM Class', '27', 'white', 'JAM-CLS-27-WHT'),
(N'Tenis escolares JAM Class', '28', 'white', 'JAM-CLS-28-WHT'),
(N'Tenis escolares JAM Class', '25', 'black', 'JAM-CLS-25-BLK'),
(N'Tenis escolares JAM Class', '26', 'black', 'JAM-CLS-26-BLK'),
(N'Tenis escolares JAM Class', '27', 'black', 'JAM-CLS-27-BLK'),
(N'Tenis escolares JAM Class', '28', 'black', 'JAM-CLS-28-BLK'),
(N'Botella deportiva JAM Squeeze 600 ml', 'UNI', 'pink', 'JAM-SQZ-UNI-PNK'),
(N'Botella deportiva JAM Squeeze 600 ml', 'UNI', 'white', 'JAM-SQZ-UNI-WHT'),
(N'Sudadera JAM Holiday Stripe', 'S', 'gray', 'JAM-HOL-S-GRY'),
(N'Sudadera JAM Holiday Stripe', 'M', 'gray', 'JAM-HOL-M-GRY'),
(N'Sudadera JAM Holiday Stripe', 'L', 'gray', 'JAM-HOL-L-GRY'),
(N'Sudadera JAM Holiday Stripe', 'XL', 'gray', 'JAM-HOL-XL-GRY'),
(N'Sudadera JAM Holiday Stripe', 'S', 'red', 'JAM-HOL-S-RED'),
(N'Sudadera JAM Holiday Stripe', 'M', 'red', 'JAM-HOL-M-RED'),
(N'Sudadera JAM Holiday Stripe', 'L', 'red', 'JAM-HOL-L-RED'),
(N'Sudadera JAM Holiday Stripe', 'XL', 'red', 'JAM-HOL-XL-RED'),
(N'Calcetas JAM Holiday (3 pares)', 'UNI', 'red', 'JAM-HSK-UNI-RED'),
(N'Calcetas JAM Holiday (3 pares)', 'UNI', 'green', 'JAM-HSK-UNI-GRN'),
(N'Mancuernas JAM Power 5 kg (par)', 'UNI', 'black', 'JAM-PWR-UNI-BLK'),
(N'Mancuernas JAM Color 3 kg (par)', 'UNI', 'red', 'JAM-CLR-UNI-RED'),
(N'Tenis JAM Gift Edition', '25', 'white', 'JAM-GFT-25-WHT'),
(N'Tenis JAM Gift Edition', '26', 'white', 'JAM-GFT-26-WHT'),
(N'Tenis JAM Gift Edition', '27', 'white', 'JAM-GFT-27-WHT'),
(N'Tenis JAM Gift Edition', '28', 'white', 'JAM-GFT-28-WHT'),
(N'Tenis JAM Gift Edition', '25', 'black', 'JAM-GFT-25-BLK'),
(N'Tenis JAM Gift Edition', '26', 'black', 'JAM-GFT-26-BLK'),
(N'Tenis JAM Gift Edition', '27', 'black', 'JAM-GFT-27-BLK'),
(N'Tenis JAM Gift Edition', '28', 'black', 'JAM-GFT-28-BLK')
) AS v (product_name, size_code, color_code, sku)
JOIN dbo.product AS p ON p.name = v.product_name AND p.deleted = 0
JOIN dbo.size AS sz ON sz.code = v.size_code
JOIN dbo.color AS cl ON cl.code = v.color_code
WHERE NOT EXISTS (SELECT 1 FROM dbo.product_variant AS x WHERE x.sku = v.sku AND x.deleted = 0);
GO

/* Código de barras EAN-13 de las variantes nuevas (mismo cálculo que db_jam.sql) */
UPDATE pv
SET pv.barcode = b.base + CAST((10 - s.total % 10) % 10 AS CHAR(1))
FROM dbo.product_variant AS pv
CROSS APPLY (SELECT CAST('7501234' + RIGHT('00000' + CAST(pv.id_variant AS VARCHAR(5)), 5) AS CHAR(12)) AS base) AS b
CROSS APPLY (
    SELECT SUM(x.digito * x.peso) AS total
    FROM (
        SELECT CAST(SUBSTRING(b.base, d.n, 1) AS INT) AS digito,
               CASE WHEN d.n % 2 = 0 THEN 3 ELSE 1 END AS peso
        FROM (VALUES (1), (2), (3), (4), (5), (6), (7), (8), (9), (10), (11), (12)) AS d (n)
    ) AS x
) AS s
WHERE pv.barcode IS NULL;
GO

/* Existencias en Tijuana (1), CDMX (2) y la tienda en línea (4).
   La cantidad sale del id de la variante para que siempre sea la misma. */
INSERT INTO dbo.inventory (id_store, id_variant, stock, min_stock, source_node)
SELECT st.id_store, pv.id_variant,
       CASE WHEN (pv.id_variant + st.id_store) % 11 = 0 THEN 2
            ELSE 6 + (pv.id_variant * 7 + st.id_store * 5) % 30 END,
       5, 'catalog_node'
FROM dbo.product_variant AS pv
JOIN dbo.product AS p ON p.id_product = pv.id_product
CROSS JOIN (VALUES (1), (2), (4)) AS st (id_store)
WHERE pv.deleted = 0
  AND p.name IN (
N'Tenis JAM Breeze Knit',
               N'Leggings JAM Bloom',
               N'Raqueta de pádel JAM Smash',
               N'Mochila JAM Urban Day',
               N'Tenis JAM Night Run',
               N'Top JAM Sprint',
               N'Tank top JAM Breeze',
               N'Sandalias de playa JAM Beach',
               N'Lentes de sol JAM Solar',
               N'Lentes JAM Shade Polarizados',
               N'Balón de voleibol de playa JAM Sand',
               N'Balón de fútbol rápido JAM Futsal',
               N'Tank JAM Lift',
               N'Tenis JAM Court Air',
               N'Tenis JAM Rain Run',
               N'Sudadera JAM Trail',
               N'Mochila de hidratación JAM Trail',
               N'Tenis JAM Street Low',
               N'Jersey JAM Club Visita',
               N'Tenis JAM Retro Run',
               N'Guantes de box JAM Fight Pro',
               N'Balón de fútbol americano JAM Junior',
               N'Chamarra acolchada JAM Puffer',
               N'Chamarra JAM Alpine',
               N'Gorro térmico JAM Beanie',
               N'Gorro JAM Knit',
               N'Leggings térmicos JAM Heat',
               N'Sudadera polar JAM Fleece',
               N'Botas JAM Snow Trek',
               N'Mochila escolar JAM Campus',
               N'Tenis escolares JAM Class',
               N'Botella deportiva JAM Squeeze 600 ml',
               N'Sudadera JAM Holiday Stripe',
               N'Calcetas JAM Holiday (3 pares)',
               N'Mancuernas JAM Power 5 kg (par)',
               N'Mancuernas JAM Color 3 kg (par)',
               N'Tenis JAM Gift Edition'
               )
  AND NOT EXISTS (SELECT 1 FROM dbo.inventory AS i WHERE i.id_store = st.id_store AND i.id_variant = pv.id_variant);
GO

/* ---------------------------------------------------------------------
   3. Temporada de cada producto
   --------------------------------------------------------------------- */
INSERT INTO dbo.product_season (id_product, id_theme)
SELECT p.id_product, t.id_theme
FROM (VALUES
(N'Tenis JAM Breeze Knit', 'spring'),
(N'Leggings JAM Bloom', 'spring'),
(N'Raqueta de pádel JAM Smash', 'spring'),
(N'Mochila JAM Urban Day', 'spring'),
(N'Tenis JAM Night Run', 'spring'),
(N'Top JAM Sprint', 'spring'),
(N'Tank top JAM Breeze', 'summer'),
(N'Sandalias de playa JAM Beach', 'summer'),
(N'Lentes de sol JAM Solar', 'summer'),
(N'Lentes JAM Shade Polarizados', 'summer'),
(N'Balón de voleibol de playa JAM Sand', 'summer'),
(N'Balón de fútbol rápido JAM Futsal', 'summer'),
(N'Tank JAM Lift', 'summer'),
(N'Tenis JAM Court Air', 'summer'),
(N'Tenis JAM Rain Run', 'autumn'),
(N'Sudadera JAM Trail', 'autumn'),
(N'Mochila de hidratación JAM Trail', 'autumn'),
(N'Tenis JAM Street Low', 'autumn'),
(N'Jersey JAM Club Visita', 'autumn'),
(N'Tenis JAM Retro Run', 'autumn'),
(N'Guantes de box JAM Fight Pro', 'autumn'),
(N'Balón de fútbol americano JAM Junior', 'autumn'),
(N'Chamarra acolchada JAM Puffer', 'winter'),
(N'Chamarra JAM Alpine', 'winter'),
(N'Gorro térmico JAM Beanie', 'winter'),
(N'Gorro JAM Knit', 'winter'),
(N'Leggings térmicos JAM Heat', 'winter'),
(N'Sudadera polar JAM Fleece', 'winter'),
(N'Botas JAM Snow Trek', 'winter'),
(N'Mochila escolar JAM Campus', 'back_to_school'),
(N'Tenis escolares JAM Class', 'back_to_school'),
(N'Botella deportiva JAM Squeeze 600 ml', 'back_to_school'),
(N'Sudadera JAM Holiday Stripe', 'christmas'),
(N'Calcetas JAM Holiday (3 pares)', 'christmas'),
(N'Mancuernas JAM Power 5 kg (par)', 'christmas'),
(N'Mancuernas JAM Color 3 kg (par)', 'christmas'),
(N'Tenis JAM Gift Edition', 'christmas'),
(N'Playera Dry-Fit JAM Pro', 'summer'),
(N'Short Running JAM Air', 'spring'),
(N'Short Running JAM Air', 'summer'),
(N'Legging Compresión JAM Flex', 'autumn'),
(N'Legging Compresión JAM Flex', 'winter'),
(N'Sudadera Hoodie JAM Core', 'autumn'),
(N'Sudadera Hoodie JAM Core', 'winter'),
(N'Sudadera Hoodie JAM Core', 'christmas'),
(N'Tenis Trainer JAM Stride', 'autumn'),
(N'Tenis Running JAM Pulse', 'spring'),
(N'Tenis Running JAM Pulse', 'christmas'),
(N'Tenis Urbanos JAM Street', 'back_to_school'),
(N'Tenis Basketball JAM Jump', 'autumn'),
(N'Sandalias JAM Recovery Slide', 'summer'),
(N'Top Deportivo JAM Core', 'spring'),
(N'Jersey Futbol JAM Club', 'autumn'),
(N'Pants Jogger JAM Move', 'autumn'),
(N'Pants Jogger JAM Move', 'winter'),
(N'Chamarra Rompevientos JAM Storm', 'autumn'),
(N'Chamarra Rompevientos JAM Storm', 'winter'),
(N'Leggings JAM Seamless', 'spring'),
(N'Gorra JAM Sport Cap', 'summer'),
(N'Mochila JAM Gym Pack', 'back_to_school'),
(N'Botella Térmica JAM 750 ml', 'summer'),
(N'Botella Térmica JAM 750 ml', 'christmas'),
(N'Calcetas JAM Pro (3 pares)', 'winter'),
(N'Lentes para correr JAM Vision', 'spring'),
(N'Goggles de natación JAM Splash', 'summer'),
(N'Gorra Trucker JAM Urban', 'summer'),
(N'Gorra para correr JAM Run Light', 'spring'),
(N'Guantes de gimnasio JAM Grip', 'winter'),
(N'Guantes de portero JAM Keeper', 'back_to_school'),
(N'Guantes de box JAM Fight 12 oz', 'christmas'),
(N'Balón de fútbol JAM Strike No. 5', 'back_to_school'),
(N'Balón de básquetbol JAM Court No. 7', 'autumn'),
(N'Balón de básquetbol JAM Court No. 7', 'christmas'),
(N'Balón de voleibol JAM Spike', 'summer'),
(N'Pelotas de pádel JAM Pro (3 piezas)', 'spring'),
(N'Balón de fútbol americano JAM Blitz', 'autumn'),
(N'Tenis de fútbol JAM Strike FG', 'back_to_school'),
(N'Tenis de entrenamiento JAM Flow', 'spring'),
(N'Tenis JAM Sprint Jr', 'back_to_school'),
(N'Short de básquetbol JAM Court', 'summer'),
(N'Falda short de tenis JAM Ace', 'spring'),
(N'Playera térmica manga larga JAM Thermo', 'winter')
) AS ps (product_name, theme_code)
JOIN dbo.product AS p ON p.name = ps.product_name AND p.deleted = 0
JOIN dbo.season_theme AS t ON t.code = ps.theme_code
WHERE NOT EXISTS (SELECT 1 FROM dbo.product_season AS x WHERE x.id_product = p.id_product AND x.id_theme = t.id_theme);
GO

/* ---------------------------------------------------------------------
   4. Sincronizar los fragmentos (solo si 01 y 02 ya se ejecutaron)
   --------------------------------------------------------------------- */
IF OBJECT_ID(N'dist.sp_sincronizar_nodos', N'P') IS NOT NULL
    EXEC dist.sp_sincronizar_nodos;
GO

/* Resultado esperado: productos por temporada */
SELECT t.name AS temporada, COUNT(*) AS productos
FROM dbo.product_season AS ps
JOIN dbo.season_theme AS t ON t.id_theme = ps.id_theme
WHERE ps.deleted = 0
GROUP BY t.id_theme, t.name
ORDER BY t.id_theme;
GO

SELECT COUNT(*) AS productos_totales FROM dbo.product WHERE deleted = 0;
GO
