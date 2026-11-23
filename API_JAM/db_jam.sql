USE master;
GO

/* Si JAM_db está publicada (03_replicacion.sql), se quita la replicación
   y se cierran las conexiones antes de borrarla. */
IF EXISTS (SELECT 1 FROM sys.databases WHERE name = N'JAM_db' AND is_published = 1)
    EXEC sp_removedbreplication @dbname = N'JAM_db';
IF DB_ID(N'JAM_db') IS NOT NULL
    ALTER DATABASE JAM_db SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
GO

DROP DATABASE IF EXISTS JAM_db;
GO

CREATE DATABASE JAM_db COLLATE Modern_Spanish_CI_AS;
GO

USE JAM_db;
GO


CREATE TABLE role (
    id_role TINYINT NOT NULL PRIMARY KEY,
    code    VARCHAR(13) NOT NULL,
    name    NVARCHAR(20) NOT NULL,
    CONSTRAINT uq_role_code UNIQUE (code)
);
GO

INSERT INTO role (id_role, code, name) VALUES
(1, 'store_manager', 'Gerente de Tienda'),
(2, 'seller',        'Vendedor'),
(3, 'cashier',        'Cajero'),
(4, 'warehouse',      'Almacén');
GO

CREATE TABLE store_type (
    id_store_type TINYINT NOT NULL PRIMARY KEY,
    code          VARCHAR(8) NOT NULL,
    name          NVARCHAR(30) NOT NULL,
    CONSTRAINT uq_store_type_code UNIQUE (code)
);
GO

INSERT INTO store_type (id_store_type, code, name) VALUES
(1, 'flagship', 'Tienda Insignia'),
(2, 'mall',     'Tienda en Centro Comercial'),
(3, 'outlet',   'Outlet'),
(4, 'online',   'Tienda en Línea'),
(5, 'kiosk',    'Kiosko');
GO

CREATE TABLE category (
    id_category TINYINT NOT NULL PRIMARY KEY,
    code        VARCHAR(11) NOT NULL,
    name        NVARCHAR(20) NOT NULL,
    department  VARCHAR(11) NOT NULL,
    CONSTRAINT uq_category_code UNIQUE (code),
    CONSTRAINT ck_category_department CHECK (department IN ('apparel','footwear','accessories'))
);
GO

INSERT INTO category (id_category, code, name, department) VALUES
(1, 'tops',        'Parte Superior', 'apparel'),
(2, 'bottoms',     'Parte Inferior', 'apparel'),
(3, 'leggings',    'Leggings', 'apparel'),
(4, 'hoodies',     'Sudaderas', 'apparel'),
(5, 'footwear',    'Calzado', 'footwear'),
(6, 'accessories', 'Accesorios', 'accessories');
GO

-- Equipo deportivo
INSERT INTO category (id_category, code, name, department) VALUES
(7, 'eyewear',     'Lentes', 'accessories'),
(8, 'gloves',      'Guantes', 'accessories'),
(9, 'balls',       'Balones y pelotas', 'accessories');
GO

CREATE TABLE size (
    id_size TINYINT NOT NULL PRIMARY KEY,
    code    VARCHAR(3) NOT NULL,
    name    NVARCHAR(20) NOT NULL,
    CONSTRAINT uq_size_code UNIQUE (code)
);
GO

INSERT INTO size (id_size, code, name) VALUES
(1, 'XS',  'Extra Chico'),
(2, 'S',   'Chico'),
(3, 'M',   'Mediano'),
(4, 'L',   'Grande'),
(5, 'XL',  'Extra Grande'),
(6, 'XXL', 'Extra Extra Grande'),
(7, '26',  'Calzado 26'),
(8, '27',  'Calzado 27');
GO

INSERT INTO size (id_size, code, name) VALUES
(9,  'UNI', 'Unitalla'),
(10, '25',  'Calzado 25'),
(11, '28',  'Calzado 28');
GO

CREATE TABLE color (
    id_color TINYINT NOT NULL PRIMARY KEY,
    code     VARCHAR(5) NOT NULL,
    name     NVARCHAR(15) NOT NULL,
    CONSTRAINT uq_color_code UNIQUE (code)
);
GO

INSERT INTO color (id_color, code, name) VALUES
(1, 'black', 'Negro'),
(2, 'white', 'Blanco'),
(3, 'red',   'Rojo'),
(4, 'navy',  'Azul Marino'),
(5, 'gray',  'Gris');
GO

INSERT INTO color (id_color, code, name) VALUES
(6, 'pink',  'Rosa'),
(7, 'green', 'Verde'),
(8, 'blue',  'Azul Rey');
GO

INSERT INTO color (id_color, code, name) VALUES
(9,  'amber', 'Naranja'),
(10, 'lime',  'Amarillo neón'),
(11, 'brown', 'Café');
GO

CREATE TABLE loyalty_level (
    id_loyalty_level TINYINT NOT NULL PRIMARY KEY,
    code              VARCHAR(6) NOT NULL,
    name              NVARCHAR(10) NOT NULL,
    discount_percent  DECIMAL(4,2) NOT NULL,
    min_spent         DECIMAL(10,2) NOT NULL,
    CONSTRAINT uq_loyalty_level_code UNIQUE (code),
    CONSTRAINT ck_loyalty_level_discount CHECK (discount_percent BETWEEN 0 AND 99.99),
    CONSTRAINT ck_loyalty_level_min_spent CHECK (min_spent >= 0)
);
GO

INSERT INTO loyalty_level (id_loyalty_level, code, name, discount_percent, min_spent) VALUES
(1, 'member', 'Miembro', 0.00, 0.00),
(2, 'silver', 'Plata',   5.00, 3000.00),
(3, 'gold',   'Oro',    10.00, 8000.00);
GO

-- Tipos de cuenta de la tienda en línea. Los beneficios son datos: cambiar el
-- descuento o el tiempo de respuesta de soporte es un UPDATE, no un cambio de código.
CREATE TABLE account_tier (
    id_account_tier     TINYINT       NOT NULL PRIMARY KEY,
    code                VARCHAR(10)   NOT NULL,
    name                NVARCHAR(20)  NOT NULL,
    discount_percent    DECIMAL(4,2)  NOT NULL,
    free_shipping_from  DECIMAL(10,2) NOT NULL,   -- 0 = envío siempre gratis
    delivery_days       NVARCHAR(30)  NOT NULL,
    max_units_per_item  SMALLINT      NOT NULL,   -- piezas por artículo en un pedido (mayoreo)
    support_minutes     SMALLINT      NOT NULL,   -- tiempo máximo de respuesta de soporte
    can_manage_store    BIT           NOT NULL,
    CONSTRAINT uq_account_tier_code UNIQUE (code),
    CONSTRAINT ck_account_tier_code CHECK (code IN ('standard','priority','admin')),
    CONSTRAINT ck_account_tier_discount CHECK (discount_percent BETWEEN 0 AND 50),
    CONSTRAINT ck_account_tier_shipping CHECK (free_shipping_from >= 0),
    CONSTRAINT ck_account_tier_units CHECK (max_units_per_item BETWEEN 1 AND 999),
    CONSTRAINT ck_account_tier_support CHECK (support_minutes > 0)
);
GO

INSERT INTO account_tier (id_account_tier, code, name, discount_percent, free_shipping_from, delivery_days,
                          max_units_per_item, support_minutes, can_manage_store) VALUES
(1, 'standard', 'Estándar',      0.00, 999.00, '3 a 5 días hábiles',  10, 1440, 0),
(2, 'priority', 'Prioritario',  12.00,   0.00, '1 a 2 días hábiles', 200,   15, 0),
(3, 'admin',    'Administrador', 12.00,   0.00, '1 a 2 días hábiles', 200,   15, 1);
GO

CREATE TABLE absence_type (
    id_absence_type TINYINT NOT NULL PRIMARY KEY,
    code            VARCHAR(11) NOT NULL,
    name            NVARCHAR(20) NOT NULL,
    CONSTRAINT uq_absence_type_code UNIQUE (code)
);
GO

INSERT INTO absence_type (id_absence_type, code, name) VALUES
(1, 'day_off',     'Día de Descanso'),
(2, 'no_show',     'Inasistencia'),
(3, 'vacation',    'Vacaciones'),
(4, 'sick_leave',  'Incapacidad'),
(5, 'resignation', 'Renuncia');
GO

CREATE TABLE state (
    id_state TINYINT NOT NULL PRIMARY KEY,
    code     CHAR(3) NOT NULL,
    name     NVARCHAR(20) NOT NULL,
    CONSTRAINT uq_state_code UNIQUE (code)
);
GO

INSERT INTO state (id_state, code, name) VALUES
(1, 'BCN', 'Baja California'),
(2, 'CMX', 'Ciudad de México'),
(3, 'JAL', 'Jalisco'),
(4, 'NLE', 'Nuevo León'),
(5, 'SON', 'Sonora');
GO

INSERT INTO state (id_state, code, name) VALUES
(6, 'AGU', 'Aguascalientes'),
(7, 'BCS', 'Baja California Sur'),
(8, 'CAM', 'Campeche'),
(9, 'CHP', 'Chiapas'),
(10, 'CHH', 'Chihuahua'),
(11, 'COA', 'Coahuila'),
(12, 'COL', 'Colima'),
(13, 'DUR', 'Durango'),
(14, 'GUA', 'Guanajuato'),
(15, 'GRO', 'Guerrero'),
(16, 'HID', 'Hidalgo'),
(17, 'MEX', 'Estado de México'),
(18, 'MIC', 'Michoacán'),
(19, 'MOR', 'Morelos'),
(20, 'NAY', 'Nayarit'),
(21, 'OAX', 'Oaxaca'),
(22, 'PUE', 'Puebla'),
(23, 'QUE', 'Querétaro'),
(24, 'ROO', 'Quintana Roo'),
(25, 'SLP', 'San Luis Potosí'),
(26, 'SIN', 'Sinaloa'),
(27, 'TAB', 'Tabasco'),
(28, 'TAM', 'Tamaulipas'),
(29, 'TLA', 'Tlaxcala'),
(30, 'VER', 'Veracruz'),
(31, 'YUC', 'Yucatán'),
(32, 'ZAC', 'Zacatecas');
GO

-- Temporadas visuales de la tienda: la página toma su paleta de colores de
-- aquí según la fecha. Las estaciones se repiten cada año; las campañas
-- tienen mayor prioridad y se sobreponen a la estación vigente.
CREATE TABLE season_theme (
    id_theme          TINYINT       NOT NULL PRIMARY KEY,
    code              VARCHAR(20)   NOT NULL,
    name              NVARCHAR(40)  NOT NULL,
    theme_type        VARCHAR(8)    NOT NULL,
    start_month       TINYINT       NOT NULL,
    start_day         TINYINT       NOT NULL,
    end_month         TINYINT       NOT NULL,
    end_day           TINYINT       NOT NULL,
    priority          TINYINT       NOT NULL,
    color_primary     CHAR(7)       NOT NULL,
    color_secondary   CHAR(7)       NOT NULL,
    color_accent      CHAR(7)       NOT NULL,
    color_accent_text CHAR(7)       NOT NULL,
    hero_title        NVARCHAR(80)  NOT NULL,
    hero_text         NVARCHAR(160) NOT NULL,
    effect            VARCHAR(10)   NOT NULL DEFAULT 'none',
    active            BIT           NOT NULL DEFAULT 1,
    CONSTRAINT uq_season_theme_code UNIQUE (code),
    CONSTRAINT ck_season_theme_type CHECK (theme_type IN ('season','campaign')),
    CONSTRAINT ck_season_theme_effect CHECK (effect IN ('none','snow','leaves','petals','sun','confetti','sparks','festive')),
    CONSTRAINT ck_season_theme_months CHECK (start_month BETWEEN 1 AND 12 AND end_month BETWEEN 1 AND 12),
    CONSTRAINT ck_season_theme_days CHECK (start_day BETWEEN 1 AND 31 AND end_day BETWEEN 1 AND 31),
    CONSTRAINT ck_season_theme_colors CHECK (
        color_primary     LIKE '#[0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f]' AND
        color_secondary   LIKE '#[0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f]' AND
        color_accent      LIKE '#[0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f]' AND
        color_accent_text LIKE '#[0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f][0-9A-Fa-f]')
);
GO

INSERT INTO season_theme (id_theme, code, name, theme_type, start_month, start_day, end_month, end_day, priority,
                          color_primary, color_secondary, color_accent, color_accent_text, hero_title, hero_text, effect) VALUES
(1, 'spring',        'Primavera',          'season',    3, 20,  6, 20, 1, '#1D4D3A', '#2A6B50', '#FFB3C7', '#1D4D3A', 'Renueva tu rutina',          'Colores frescos y prendas ligeras para entrenar al aire libre.', 'petals'),
(2, 'summer',        'Verano',             'season',    6, 21,  9, 22, 1, '#0B3D5C', '#0E5A85', '#FFD23F', '#0B3D5C', 'Verano en movimiento',       'Shorts, tops y tenis transpirables para los días de más calor.', 'sun'),
(3, 'autumn',        'Otoño',              'season',    9, 23, 12, 20, 1, '#3B1F14', '#5A2E1B', '#FF8C42', '#3B1F14', 'Entrena en otoño',           'Capas ligeras, rompevientos y tenis para correr con el clima fresco.', 'leaves'),
(4, 'winter',        'Invierno',           'season',   12, 21,  3, 19, 1, '#0F1D3D', '#1C2F5E', '#9BE7FF', '#0F1D3D', 'Invierno sin pausa',         'Sudaderas, chamarras y leggings térmicos para no dejar de moverte.', 'snow'),
(5, 'back_to_school','Regreso a clases',   'campaign',  8,  1,  8, 31, 5, '#1E1B4B', '#312E81', '#FACC15', '#1E1B4B', 'Regreso a clases',           'Mochilas, tenis y uniformes deportivos para el nuevo ciclo escolar.', 'confetti'),
(6, 'buen_fin',      'El Buen Fin',        'campaign', 11, 13, 11, 17, 9, '#7A0019', '#A3001F', '#FFD000', '#3A0010', 'El Buen Fin JAM',            'Descuentos en calzado, ropa y accesorios del 13 al 17 de noviembre.', 'confetti'),
(7, 'black_friday',  'Black Friday',       'campaign', 11, 20, 11, 30, 9, '#0A0A0A', '#262626', '#FF2D55', '#FFFFFF', 'Black Friday',               'Hasta 25% de descuento con el cupón BLACKFRIDAY.', 'sparks'),
(8, 'christmas',     'Navidad',            'campaign', 12,  1,  1,  6, 8, '#14351F', '#1E4D2B', '#E63946', '#FFFFFF', 'Regala movimiento',          'Ideas de regalo deportivas para jóvenes y adultos.', 'festive');
GO


-- =====================================================================
-- 2. Servicio Admin
-- =====================================================================

CREATE TABLE owner (
    id_owner            INT IDENTITY(1,1) PRIMARY KEY,
    first_name          NVARCHAR(50) NOT NULL,
    last_name_paternal  NVARCHAR(50) NOT NULL,
    last_name_maternal  NVARCHAR(50) NULL,
    email               VARCHAR(100) NOT NULL,
    phone               VARCHAR(15) NOT NULL,
    password_hash       VARCHAR(100) NOT NULL,
    active              BIT NOT NULL DEFAULT 1,
    created_at          DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    -- columnas de consolidación
    source_node         VARCHAR(20) NOT NULL,
    updated_at          DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted             BIT NOT NULL DEFAULT 0
);
GO

INSERT INTO owner (first_name, last_name_paternal, last_name_maternal, email, phone, password_hash, active, source_node) VALUES
('Mariana',   'Castillo', 'Rubio',    'mariana.castillo@jam.mx', '6641234501', '$2y$10$hash0001', 1, 'admin_node'),
('Héctor',    'Villareal','Nuñez',    'hector.villareal@jam.mx', '6641234502', '$2y$10$hash0002', 1, 'admin_node'),
('Paola',     'Esquivel', 'Mora',     'paola.esquivel@jam.mx',  '6641234503', '$2y$10$hash0003', 1, 'admin_node'),
('Ricardo',   'Beltrán',  'Solís',    'ricardo.beltran@jam.mx', '6641234504', '$2y$10$hash0004', 1, 'admin_node'),
('Fernanda',  'Ocampo',   'Reyes',    'fernanda.ocampo@jam.mx', '6641234505', '$2y$10$hash0005', 1, 'admin_node');
GO


-- =====================================================================
-- 3. Servicio Sucursales
-- =====================================================================

CREATE TABLE address (
    id_address     INT IDENTITY(1,1) PRIMARY KEY,
    street         NVARCHAR(100) NOT NULL,
    ext_number     VARCHAR(10) NOT NULL,
    int_number     VARCHAR(10) NULL,
    neighborhood   NVARCHAR(80) NOT NULL,
    city           NVARCHAR(60) NOT NULL,
    id_state       TINYINT NOT NULL,
    zip_code       CHAR(5) NOT NULL,
    reference      NVARCHAR(150) NULL,
    -- columnas de consolidación
    source_node    VARCHAR(20) NOT NULL,
    updated_at     DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted        BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_address_state FOREIGN KEY (id_state) REFERENCES state (id_state)
);
GO

INSERT INTO address (street, ext_number, int_number, neighborhood, city, id_state, zip_code, reference, source_node) VALUES
('Blvd. Insurgentes',   '1500', NULL,  'Centro',           'Tijuana',    1, '22000', 'Frente a Plaza Macroplaza',     'stores_node'),
('Av. Reforma',          '222', 'A',   'Juárez',           'Ciudad de México', 2, '06600', 'Esquina con Niza',         'stores_node'),
('Av. Vallarta',        '3400', NULL,  'Vallarta Norte',   'Guadalajara',3, '44690', 'Junto a Plaza del Sol',        'stores_node'),
('Blvd. Solidaridad',    '500', NULL,  'Villa Satélite',   'Hermosillo', 5, '83200', 'Plaza Outlet Norte',           'stores_node'),
('Av. Constitución',     '800', '3',   'Centro',           'Monterrey',  4, '64000', 'Domicilio particular cliente', 'stores_node');
GO

CREATE TABLE store (
    id_store       INT IDENTITY(1,1) PRIMARY KEY,
    id_owner       INT NOT NULL, -- ref. logica a owner (otro servicio: Admin)
    id_store_type  TINYINT NOT NULL,
    id_address     INT NULL, -- NULL = tienda en línea
    store_name     NVARCHAR(80) NOT NULL,
    tax_id         VARCHAR(13) NOT NULL,
    contact_phone  VARCHAR(15) NOT NULL,
    email          VARCHAR(100) NOT NULL,
    whatsapp       VARCHAR(15) NOT NULL,
    opened_at      DATE NOT NULL,
    active         BIT NOT NULL DEFAULT 1,
    -- columnas de consolidación
    source_node    VARCHAR(20) NOT NULL,
    updated_at     DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted        BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_store_store_type FOREIGN KEY (id_store_type) REFERENCES store_type (id_store_type),
    CONSTRAINT fk_store_address FOREIGN KEY (id_address) REFERENCES address (id_address)
);
GO

CREATE INDEX ix_store_owner ON store (id_owner);
GO

INSERT INTO store (id_owner, id_store_type, id_address, store_name, tax_id, contact_phone, email, whatsapp, opened_at, source_node) VALUES
(1, 1, 1,    'JAM Tijuana Flagship',    'JAM010101AB1', '6641110001', 'tijuana@jam.mx',  '6641110001', '2022-03-01', 'stores_node'),
(1, 2, 2,    'JAM CDMX Reforma',        'JAM010101AB2', '5521110002', 'cdmx@jam.mx',     '5521110002', '2023-05-15', 'stores_node'),
(1, 2, 3,    'JAM Guadalajara Vallarta','JAM010101AB3', '3331110003', 'gdl@jam.mx',      '3331110003', '2023-09-20', 'stores_node'),
(1, 4, NULL, 'JAM Online Store',        'JAM010101AB4', '8001110004', 'online@jam.mx',   '8001110004', '2021-11-01', 'stores_node'),
(1, 3, 4,    'JAM Outlet Hermosillo',   'JAM010101AB5', '6621110005', 'hermosillo@jam.mx','6621110005','2024-02-10', 'stores_node');
GO

CREATE TABLE store_config (
    id_store      INT NOT NULL,
    config_key    VARCHAR(50) NOT NULL,
    config_value  VARCHAR(255) NOT NULL,
    -- columnas de consolidación
    source_node   VARCHAR(20) NOT NULL,
    updated_at    DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted       BIT NOT NULL DEFAULT 0,
    CONSTRAINT pk_store_config PRIMARY KEY (id_store, config_key),
    CONSTRAINT fk_store_config_store FOREIGN KEY (id_store) REFERENCES store (id_store)
);
GO

INSERT INTO store_config (id_store, config_key, config_value, source_node) VALUES
(1, 'tax_rate',             '16.00',        'stores_node'),
(1, 'opening_hours',        '10:00-21:00',  'stores_node'),
(4, 'currency',             'MXN',          'stores_node'),
(4, 'whatsapp_bot_enabled', 'true',         'stores_node'),
(5, 'return_policy_days',   '30',           'stores_node');
GO


-- =====================================================================
-- 4. Servicio Empleados
-- =====================================================================

CREATE TABLE employee (
    id_employee           INT IDENTITY(1,1) PRIMARY KEY,
    id_store               INT NOT NULL, -- ref. logica a store (otro servicio: Sucursales)
    id_role                TINYINT NOT NULL,
    first_name             NVARCHAR(50) NOT NULL,
    last_name_paternal     NVARCHAR(50) NOT NULL,
    last_name_maternal     NVARCHAR(50) NULL,
    phone                  VARCHAR(15) NOT NULL,
    phone_verified         BIT NOT NULL DEFAULT 0,
    email                  VARCHAR(100) NOT NULL,
    password_hash          VARCHAR(100) NOT NULL,
    must_change_password   BIT NOT NULL DEFAULT 1,
    hire_date              DATE NOT NULL,
    -- columnas de consolidación
    source_node            VARCHAR(20) NOT NULL,
    updated_at             DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted                BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_employee_role FOREIGN KEY (id_role) REFERENCES role (id_role)
);
GO

CREATE INDEX ix_employee_store ON employee (id_store);
GO

INSERT INTO employee (id_store, id_role, first_name, last_name_paternal, last_name_maternal, phone, phone_verified, email, password_hash, must_change_password, hire_date, source_node) VALUES
(1, 1, 'Roberto',  'Díaz',    'Lemus',   '6645550001', 1, 'roberto.diaz@jam.mx',   '$2y$10$hash1001', 0, '2022-03-05', 'employees_node'),
(1, 2, 'Laura',    'Jiménez', 'Castro',  '6645550002', 1, 'laura.jimenez@jam.mx',  '$2y$10$hash1002', 0, '2022-04-10', 'employees_node'),
(2, 3, 'Carlos',   'Mendoza', 'Ruiz',    '5545550003', 1, 'carlos.mendoza@jam.mx', '$2y$10$hash1003', 0, '2023-05-20', 'employees_node'),
(3, 4, 'Ana',      'Torres',  'Vega',    '3345550004', 0, 'ana.torres@jam.mx',     '$2y$10$hash1004', 1, '2023-09-25', 'employees_node'),
(5, 2, 'Jorge',    'Ramírez', 'Solano',  '6625550005', 1, 'jorge.ramirez@jam.mx',  '$2y$10$hash1005', 0, '2024-02-15', 'employees_node');
GO

CREATE TABLE employee_schedule (
    id_schedule    INT IDENTITY(1,1) PRIMARY KEY,
    id_employee    INT NOT NULL,
    weekday        VARCHAR(3) NOT NULL,
    start_time     TIME NOT NULL,
    end_time       TIME NOT NULL,
    -- columnas de consolidación
    source_node    VARCHAR(20) NOT NULL,
    updated_at     DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted        BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_employee_schedule_employee FOREIGN KEY (id_employee) REFERENCES employee (id_employee),
    CONSTRAINT uq_employee_schedule_employee_weekday UNIQUE (id_employee, weekday),
    CONSTRAINT ck_employee_schedule_weekday CHECK (weekday IN ('mon','tue','wed','thu','fri','sat','sun')),
    CONSTRAINT ck_employee_schedule_time CHECK (end_time > start_time)
);
GO

INSERT INTO employee_schedule (id_employee, weekday, start_time, end_time, source_node) VALUES
(1, 'mon', '09:00:00', '18:00:00', 'employees_node'),
(2, 'tue', '10:00:00', '19:00:00', 'employees_node'),
(3, 'wed', '09:00:00', '17:00:00', 'employees_node'),
(4, 'thu', '08:00:00', '16:00:00', 'employees_node'),
(5, 'fri', '11:00:00', '20:00:00', 'employees_node');
GO

CREATE TABLE employee_absence (
    id_absence       INT IDENTITY(1,1) PRIMARY KEY,
    id_employee      INT NOT NULL,
    id_absence_type  TINYINT NOT NULL,
    absence_date     DATE NOT NULL,
    -- columnas de consolidación
    source_node      VARCHAR(20) NOT NULL,
    updated_at       DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted          BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_employee_absence_employee FOREIGN KEY (id_employee) REFERENCES employee (id_employee),
    CONSTRAINT fk_employee_absence_type FOREIGN KEY (id_absence_type) REFERENCES absence_type (id_absence_type),
    CONSTRAINT uq_employee_absence_employee_date UNIQUE (id_employee, absence_date)
);
GO

INSERT INTO employee_absence (id_employee, id_absence_type, absence_date, source_node) VALUES
(2, 1, '2026-11-02', 'employees_node'),
(3, 4, '2026-10-15', 'employees_node'),
(4, 3, '2026-12-20', 'employees_node'),
(1, 2, '2026-09-05', 'employees_node'),
(5, 5, '2026-12-31', 'employees_node');
GO

CREATE TABLE verification_code (
    id_code       INT IDENTITY(1,1) PRIMARY KEY,
    id_employee   INT NOT NULL,
    purpose       VARCHAR(14) NOT NULL,
    code_hash     VARCHAR(100) NOT NULL,
    expires_at    DATETIME2(3) NOT NULL,
    attempts      TINYINT NOT NULL DEFAULT 0,
    used          BIT NOT NULL DEFAULT 0,
    -- tabla de bitácora: source_node y created_at, sin deleted
    source_node   VARCHAR(20) NOT NULL,
    created_at    DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT fk_verification_code_employee FOREIGN KEY (id_employee) REFERENCES employee (id_employee),
    CONSTRAINT ck_verification_code_purpose CHECK (purpose IN ('password_reset','phone_link'))
);
GO

INSERT INTO verification_code (id_employee, purpose, code_hash, expires_at, attempts, used, source_node) VALUES
(1, 'password_reset', '$2y$10$codehash01', '2026-11-13 20:00:00', 0, 0, 'employees_node'),
(2, 'phone_link',     '$2y$10$codehash02', '2026-11-13 21:00:00', 1, 1, 'employees_node'),
(3, 'password_reset', '$2y$10$codehash03', '2026-11-14 10:00:00', 0, 0, 'employees_node'),
(4, 'phone_link',     '$2y$10$codehash04', '2026-11-14 11:00:00', 2, 1, 'employees_node'),
(5, 'password_reset', '$2y$10$codehash05', '2026-11-15 09:00:00', 0, 0, 'employees_node');
GO


-- =====================================================================
-- 5. Servicio Clientes finales
-- =====================================================================

CREATE TABLE customer (
    id_customer         INT IDENTITY(1,1) PRIMARY KEY,
    id_loyalty_level    TINYINT NOT NULL DEFAULT 1,
    id_account_tier     TINYINT NOT NULL CONSTRAINT df_customer_account_tier DEFAULT 1,
    id_store            INT NOT NULL, -- ref. logica a store (otro servicio: Sucursales)
    id_address          INT NULL,     -- ref. logica a address (otro servicio: Sucursales)
    first_name          NVARCHAR(50) NOT NULL,
    last_name_paternal  NVARCHAR(50) NOT NULL,
    last_name_maternal  NVARCHAR(50) NULL,
    birth_date          DATE NOT NULL,
    sex                 CHAR(1) NOT NULL,
    phone               VARCHAR(15) NOT NULL,
    email               VARCHAR(100) NOT NULL,
    accepts_marketing   BIT NOT NULL DEFAULT 0,
    customer_since      DATE NOT NULL CONSTRAINT df_customer_since DEFAULT CAST(SYSUTCDATETIME() AS DATE),
    -- columnas de consolidación
    source_node         VARCHAR(20) NOT NULL,
    updated_at          DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted             BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_customer_loyalty_level FOREIGN KEY (id_loyalty_level) REFERENCES loyalty_level (id_loyalty_level),
    CONSTRAINT fk_customer_account_tier FOREIGN KEY (id_account_tier) REFERENCES account_tier (id_account_tier),
    CONSTRAINT ck_customer_sex CHECK (sex IN ('M','F','X'))
);
GO

CREATE INDEX ix_customer_store ON customer (id_store);
CREATE INDEX ix_customer_address ON customer (id_address);
GO

-- Luis Alberto es cliente de mayoreo desde 2022 (cuenta Prioritaria);
-- el registro 6 es la cuenta del administrador de la tienda.
INSERT INTO customer (id_loyalty_level, id_account_tier, id_store, id_address, first_name, last_name_paternal, last_name_maternal, birth_date, sex, phone, email, accepts_marketing, customer_since, source_node) VALUES
(1, 1, 1, NULL, 'María Fernanda', 'López',     'Hidalgo', '1996-04-12', 'F', '6647770001', 'mariaf.lopez@email.com',     1, '2024-03-15', 'customers_node'),
(2, 2, 4, NULL, 'Luis Alberto',   'Gómez',     'Nava',    '1990-08-23', 'M', '6647770002', 'luis.gomez@email.com',       1, '2022-05-10', 'customers_node'),
(3, 1, 3, 5,    'Daniela',        'Herrera',   'Campos',  '1993-01-30', 'F', '8187770003', 'daniela.herrera@email.com',  1, '2023-09-02', 'customers_node'),
(1, 1, 5, NULL, 'Jorge',          'Escamilla', 'Pardo',   '1988-11-05', 'M', '6647770004', 'jorge.escamilla@email.com',  0, '2025-07-21', 'customers_node'),
(1, 1, 4, NULL, 'Ana Patricia',   'Ruiz',      'Montes',  '1999-06-18', 'F', '6647770005', 'ana.ruiz@email.com',         1, '2026-02-08', 'customers_node'),
(1, 3, 4, NULL, 'Administrador',  'JAM',       NULL,      '1990-01-01', 'X', '6640000000', 'admin@jamsport.mx',          0, '2021-01-01', 'customers_node');
GO


-- =====================================================================
-- 6. Servicio Catálogo de productos
-- =====================================================================

CREATE TABLE product (
    id_product      INT IDENTITY(1,1) PRIMARY KEY,
    id_category     TINYINT NOT NULL,
    name            NVARCHAR(80) NOT NULL,
    description     NVARCHAR(255) NULL,
    target_gender   CHAR(1) NOT NULL,
    base_price      DECIMAL(8,2) NOT NULL,
    age_segment     VARCHAR(5) NOT NULL CONSTRAINT df_product_age_segment DEFAULT 'all',
    image_url       NVARCHAR(255) NULL,   -- NULL: el frontend dibuja la ilustración de la categoría
    outlet_percent  TINYINT NULL,         -- NULL: precio normal; con valor: producto de outlet con ese descuento
    -- columnas de consolidación
    source_node     VARCHAR(20) NOT NULL,
    updated_at      DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted         BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_product_category FOREIGN KEY (id_category) REFERENCES category (id_category),
    CONSTRAINT ck_product_target_gender CHECK (target_gender IN ('M','F','U')),
    CONSTRAINT ck_product_base_price CHECK (base_price >= 0),
    CONSTRAINT ck_product_age_segment CHECK (age_segment IN ('youth','adult','all')),
    CONSTRAINT ck_product_outlet_percent CHECK (outlet_percent IS NULL OR outlet_percent BETWEEN 1 AND 90)
);
GO

INSERT INTO product (id_category, name, description, target_gender, base_price, source_node) VALUES
(1, 'Playera Dry-Fit JAM Pro',         'Playera deportiva de secado rápido', 'U', 450.00, 'catalog_node'),
(2, 'Short Running JAM Air',           'Short ligero para correr',           'U', 380.00, 'catalog_node'),
(3, 'Legging Compresión JAM Flex',     'Legging de compresión para entrenar','F', 520.00, 'catalog_node'),
(4, 'Sudadera Hoodie JAM Core',        'Sudadera con capucha de algodón',    'U', 750.00, 'catalog_node'),
(5, 'Tenis Trainer JAM Stride',        'Tenis para entrenamiento multiuso',  'U',1850.00, 'catalog_node');
GO

-- Catálogo ampliado: calzado, ropa y accesorios para jóvenes (15-24) y adultos (25-45)
INSERT INTO product (id_category, name, description, target_gender, base_price, age_segment, source_node) VALUES
(5, 'Tenis Running JAM Pulse', 'Tenis ligero con amortiguación para distancias largas', 'U', 1650.00, 'adult', 'catalog_node'),
(5, 'Tenis Urbanos JAM Street', 'Tenis casuales de estilo urbano para diario', 'U', 1290.00, 'youth', 'catalog_node'),
(5, 'Tenis Basketball JAM Jump', 'Tenis de caña alta con soporte de tobillo', 'M', 1990.00, 'youth', 'catalog_node'),
(5, 'Sandalias JAM Recovery Slide', 'Sandalias acolchadas para después de entrenar', 'U', 450.00, 'all', 'catalog_node'),
(1, 'Top Deportivo JAM Core', 'Top de soporte medio con tela de secado rápido', 'F', 390.00, 'all', 'catalog_node'),
(1, 'Jersey Futbol JAM Club', 'Jersey transpirable para futbol y entrenamiento', 'U', 650.00, 'youth', 'catalog_node'),
(2, 'Pants Jogger JAM Move', 'Pants jogger con puños y bolsillos con cierre', 'U', 690.00, 'youth', 'catalog_node'),
(4, 'Chamarra Rompevientos JAM Storm', 'Chamarra ligera contra viento y lluvia', 'U', 1190.00, 'adult', 'catalog_node'),
(3, 'Leggings JAM Seamless', 'Leggings sin costuras de cintura alta', 'F', 590.00, 'adult', 'catalog_node'),
(6, 'Gorra JAM Sport Cap', 'Gorra ajustable de secado rápido', 'U', 290.00, 'youth', 'catalog_node'),
(6, 'Mochila JAM Gym Pack', 'Mochila de 25 L con compartimento para tenis', 'U', 890.00, 'all', 'catalog_node'),
(6, 'Botella Térmica JAM 750 ml', 'Botella de acero que conserva la temperatura 12 horas', 'U', 350.00, 'all', 'catalog_node'),
(6, 'Calcetas JAM Pro (3 pares)', 'Calcetas acolchadas para correr', 'U', 220.00, 'all', 'catalog_node');
GO

UPDATE product SET age_segment = 'adult' WHERE id_product IN (2, 3);
UPDATE product SET age_segment = 'youth' WHERE id_product = 4;
GO

CREATE TABLE product_variant (
    id_variant   INT IDENTITY(1,1) PRIMARY KEY,
    id_product   INT NOT NULL,
    id_size      TINYINT NOT NULL,
    id_color     TINYINT NOT NULL,
    sku          VARCHAR(20) NOT NULL,
    barcode      CHAR(13) NULL,        -- EAN-13 que lee el escáner de la app
    -- columnas de consolidación
    source_node  VARCHAR(20) NOT NULL,
    updated_at   DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted      BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_product_variant_product FOREIGN KEY (id_product) REFERENCES product (id_product),
    CONSTRAINT fk_product_variant_size FOREIGN KEY (id_size) REFERENCES size (id_size),
    CONSTRAINT fk_product_variant_color FOREIGN KEY (id_color) REFERENCES color (id_color)
);
GO

INSERT INTO product_variant (id_product, id_size, id_color, sku, source_node) VALUES
(1, 3, 1, 'JAM-PRO-M-BLK',  'catalog_node'),
(2, 3, 4, 'JAM-AIR-M-NVY',  'catalog_node'),
(3, 2, 1, 'JAM-FLX-S-BLK',  'catalog_node'),
(4, 4, 5, 'JAM-CORE-L-GRY', 'catalog_node'),
(5, 8, 2, 'JAM-STR-27-WHT', 'catalog_node');
GO

-- Tallas y colores del catálogo: calzado 25-28, ropa XS/S-XL, accesorios unitalla
INSERT INTO product_variant (id_product, id_size, id_color, sku, source_node) VALUES
(1, 2, 1, 'JAM-PRO-S-BLK', 'catalog_node'),
(1, 4, 1, 'JAM-PRO-L-BLK', 'catalog_node'),
(1, 5, 1, 'JAM-PRO-XL-BLK', 'catalog_node'),
(1, 2, 2, 'JAM-PRO-S-WHT', 'catalog_node'),
(1, 3, 2, 'JAM-PRO-M-WHT', 'catalog_node'),
(1, 4, 2, 'JAM-PRO-L-WHT', 'catalog_node'),
(1, 5, 2, 'JAM-PRO-XL-WHT', 'catalog_node'),
(1, 2, 3, 'JAM-PRO-S-RED', 'catalog_node'),
(1, 3, 3, 'JAM-PRO-M-RED', 'catalog_node'),
(1, 4, 3, 'JAM-PRO-L-RED', 'catalog_node'),
(1, 5, 3, 'JAM-PRO-XL-RED', 'catalog_node'),
(2, 2, 4, 'JAM-AIR-S-NVY', 'catalog_node'),
(2, 4, 4, 'JAM-AIR-L-NVY', 'catalog_node'),
(2, 5, 4, 'JAM-AIR-XL-NVY', 'catalog_node'),
(2, 2, 1, 'JAM-AIR-S-BLK', 'catalog_node'),
(2, 3, 1, 'JAM-AIR-M-BLK', 'catalog_node'),
(2, 4, 1, 'JAM-AIR-L-BLK', 'catalog_node'),
(2, 5, 1, 'JAM-AIR-XL-BLK', 'catalog_node'),
(3, 1, 1, 'JAM-FLX-XS-BLK', 'catalog_node'),
(3, 3, 1, 'JAM-FLX-M-BLK', 'catalog_node'),
(3, 4, 1, 'JAM-FLX-L-BLK', 'catalog_node'),
(3, 1, 5, 'JAM-FLX-XS-GRY', 'catalog_node'),
(3, 2, 5, 'JAM-FLX-S-GRY', 'catalog_node'),
(3, 3, 5, 'JAM-FLX-M-GRY', 'catalog_node'),
(3, 4, 5, 'JAM-FLX-L-GRY', 'catalog_node'),
(4, 2, 5, 'JAM-CORE-S-GRY', 'catalog_node'),
(4, 3, 5, 'JAM-CORE-M-GRY', 'catalog_node'),
(4, 5, 5, 'JAM-CORE-XL-GRY', 'catalog_node'),
(4, 2, 1, 'JAM-CORE-S-BLK', 'catalog_node'),
(4, 3, 1, 'JAM-CORE-M-BLK', 'catalog_node'),
(4, 4, 1, 'JAM-CORE-L-BLK', 'catalog_node'),
(4, 5, 1, 'JAM-CORE-XL-BLK', 'catalog_node'),
(4, 2, 4, 'JAM-CORE-S-NVY', 'catalog_node'),
(4, 3, 4, 'JAM-CORE-M-NVY', 'catalog_node'),
(4, 4, 4, 'JAM-CORE-L-NVY', 'catalog_node'),
(4, 5, 4, 'JAM-CORE-XL-NVY', 'catalog_node'),
(5, 10, 2, 'JAM-STR-25-WHT', 'catalog_node'),
(5, 7, 2, 'JAM-STR-26-WHT', 'catalog_node'),
(5, 11, 2, 'JAM-STR-28-WHT', 'catalog_node'),
(5, 10, 1, 'JAM-STR-25-BLK', 'catalog_node'),
(5, 7, 1, 'JAM-STR-26-BLK', 'catalog_node'),
(5, 8, 1, 'JAM-STR-27-BLK', 'catalog_node'),
(5, 11, 1, 'JAM-STR-28-BLK', 'catalog_node'),
(6, 10, 1, 'JAM-PLS-25-BLK', 'catalog_node'),
(6, 7, 1, 'JAM-PLS-26-BLK', 'catalog_node'),
(6, 8, 1, 'JAM-PLS-27-BLK', 'catalog_node'),
(6, 11, 1, 'JAM-PLS-28-BLK', 'catalog_node'),
(6, 10, 2, 'JAM-PLS-25-WHT', 'catalog_node'),
(6, 7, 2, 'JAM-PLS-26-WHT', 'catalog_node'),
(6, 8, 2, 'JAM-PLS-27-WHT', 'catalog_node'),
(6, 11, 2, 'JAM-PLS-28-WHT', 'catalog_node'),
(6, 10, 8, 'JAM-PLS-25-BLU', 'catalog_node'),
(6, 7, 8, 'JAM-PLS-26-BLU', 'catalog_node'),
(6, 8, 8, 'JAM-PLS-27-BLU', 'catalog_node'),
(6, 11, 8, 'JAM-PLS-28-BLU', 'catalog_node'),
(7, 10, 2, 'JAM-STT-25-WHT', 'catalog_node'),
(7, 7, 2, 'JAM-STT-26-WHT', 'catalog_node'),
(7, 8, 2, 'JAM-STT-27-WHT', 'catalog_node'),
(7, 11, 2, 'JAM-STT-28-WHT', 'catalog_node'),
(7, 10, 1, 'JAM-STT-25-BLK', 'catalog_node'),
(7, 7, 1, 'JAM-STT-26-BLK', 'catalog_node'),
(7, 8, 1, 'JAM-STT-27-BLK', 'catalog_node'),
(7, 11, 1, 'JAM-STT-28-BLK', 'catalog_node'),
(8, 10, 3, 'JAM-JMP-25-RED', 'catalog_node'),
(8, 7, 3, 'JAM-JMP-26-RED', 'catalog_node'),
(8, 8, 3, 'JAM-JMP-27-RED', 'catalog_node'),
(8, 11, 3, 'JAM-JMP-28-RED', 'catalog_node'),
(8, 10, 1, 'JAM-JMP-25-BLK', 'catalog_node'),
(8, 7, 1, 'JAM-JMP-26-BLK', 'catalog_node'),
(8, 8, 1, 'JAM-JMP-27-BLK', 'catalog_node'),
(8, 11, 1, 'JAM-JMP-28-BLK', 'catalog_node'),
(9, 7, 1, 'JAM-SLD-26-BLK', 'catalog_node'),
(9, 8, 1, 'JAM-SLD-27-BLK', 'catalog_node'),
(9, 7, 4, 'JAM-SLD-26-NVY', 'catalog_node'),
(9, 8, 4, 'JAM-SLD-27-NVY', 'catalog_node'),
(10, 1, 6, 'JAM-TOP-XS-PNK', 'catalog_node'),
(10, 2, 6, 'JAM-TOP-S-PNK', 'catalog_node'),
(10, 3, 6, 'JAM-TOP-M-PNK', 'catalog_node'),
(10, 4, 6, 'JAM-TOP-L-PNK', 'catalog_node'),
(10, 1, 1, 'JAM-TOP-XS-BLK', 'catalog_node'),
(10, 2, 1, 'JAM-TOP-S-BLK', 'catalog_node'),
(10, 3, 1, 'JAM-TOP-M-BLK', 'catalog_node'),
(10, 4, 1, 'JAM-TOP-L-BLK', 'catalog_node'),
(11, 2, 7, 'JAM-CLB-S-GRN', 'catalog_node'),
(11, 3, 7, 'JAM-CLB-M-GRN', 'catalog_node'),
(11, 4, 7, 'JAM-CLB-L-GRN', 'catalog_node'),
(11, 5, 7, 'JAM-CLB-XL-GRN', 'catalog_node'),
(11, 2, 2, 'JAM-CLB-S-WHT', 'catalog_node'),
(11, 3, 2, 'JAM-CLB-M-WHT', 'catalog_node'),
(11, 4, 2, 'JAM-CLB-L-WHT', 'catalog_node'),
(11, 5, 2, 'JAM-CLB-XL-WHT', 'catalog_node'),
(12, 2, 5, 'JAM-JOG-S-GRY', 'catalog_node'),
(12, 3, 5, 'JAM-JOG-M-GRY', 'catalog_node'),
(12, 4, 5, 'JAM-JOG-L-GRY', 'catalog_node'),
(12, 5, 5, 'JAM-JOG-XL-GRY', 'catalog_node'),
(12, 2, 1, 'JAM-JOG-S-BLK', 'catalog_node'),
(12, 3, 1, 'JAM-JOG-M-BLK', 'catalog_node'),
(12, 4, 1, 'JAM-JOG-L-BLK', 'catalog_node'),
(12, 5, 1, 'JAM-JOG-XL-BLK', 'catalog_node'),
(13, 2, 4, 'JAM-STM-S-NVY', 'catalog_node'),
(13, 3, 4, 'JAM-STM-M-NVY', 'catalog_node'),
(13, 4, 4, 'JAM-STM-L-NVY', 'catalog_node'),
(13, 5, 4, 'JAM-STM-XL-NVY', 'catalog_node'),
(13, 2, 1, 'JAM-STM-S-BLK', 'catalog_node'),
(13, 3, 1, 'JAM-STM-M-BLK', 'catalog_node'),
(13, 4, 1, 'JAM-STM-L-BLK', 'catalog_node'),
(13, 5, 1, 'JAM-STM-XL-BLK', 'catalog_node'),
(14, 1, 1, 'JAM-SML-XS-BLK', 'catalog_node'),
(14, 2, 1, 'JAM-SML-S-BLK', 'catalog_node'),
(14, 3, 1, 'JAM-SML-M-BLK', 'catalog_node'),
(14, 4, 1, 'JAM-SML-L-BLK', 'catalog_node'),
(14, 1, 8, 'JAM-SML-XS-BLU', 'catalog_node'),
(14, 2, 8, 'JAM-SML-S-BLU', 'catalog_node'),
(14, 3, 8, 'JAM-SML-M-BLU', 'catalog_node'),
(14, 4, 8, 'JAM-SML-L-BLU', 'catalog_node'),
(15, 9, 1, 'JAM-CAP-UNI-BLK', 'catalog_node'),
(15, 9, 2, 'JAM-CAP-UNI-WHT', 'catalog_node'),
(15, 9, 3, 'JAM-CAP-UNI-RED', 'catalog_node'),
(16, 9, 5, 'JAM-GYM-UNI-GRY', 'catalog_node'),
(16, 9, 1, 'JAM-GYM-UNI-BLK', 'catalog_node'),
(17, 9, 8, 'JAM-BTL-UNI-BLU', 'catalog_node'),
(17, 9, 1, 'JAM-BTL-UNI-BLK', 'catalog_node'),
(17, 9, 2, 'JAM-BTL-UNI-WHT', 'catalog_node'),
(18, 2, 2, 'JAM-SCK-S-WHT', 'catalog_node'),
(18, 3, 2, 'JAM-SCK-M-WHT', 'catalog_node'),
(18, 4, 2, 'JAM-SCK-L-WHT', 'catalog_node'),
(18, 2, 1, 'JAM-SCK-S-BLK', 'catalog_node'),
(18, 3, 1, 'JAM-SCK-M-BLK', 'catalog_node'),
(18, 4, 1, 'JAM-SCK-L-BLK', 'catalog_node');
GO


-- Equipo deportivo para todas las secciones: lentes, gorras, guantes, balones,
-- tacos de fútbol y más ropa y calzado para hombre, mujer y juvenil.
INSERT INTO product (id_category, name, description, target_gender, base_price, age_segment, outlet_percent, source_node) VALUES
(7, 'Lentes para correr JAM Vision', 'Lentes deportivos ligeros con protección UV400 y patas antideslizantes', 'U', 690.00, 'adult', NULL, 'catalog_node'),
(7, 'Goggles de natación JAM Splash', 'Goggles antiempañantes con correa ajustable de silicón', 'U', 250.00, 'all', NULL, 'catalog_node'),
(6, 'Gorra Trucker JAM Urban', 'Gorra con malla trasera y broche ajustable', 'U', 320.00, 'youth', NULL, 'catalog_node'),
(6, 'Gorra para correr JAM Run Light', 'Gorra ultraligera con visera flexible y banda absorbente', 'U', 360.00, 'adult', NULL, 'catalog_node'),
(8, 'Guantes de gimnasio JAM Grip', 'Guantes con palma acolchada y muñequera ajustable para pesas', 'U', 420.00, 'adult', NULL, 'catalog_node'),
(8, 'Guantes de portero JAM Keeper', 'Guantes de portero con palma de látex de alto agarre', 'U', 590.00, 'youth', NULL, 'catalog_node'),
(8, 'Guantes de box JAM Fight 12 oz', 'Guantes de entrenamiento para box y kickboxing', 'U', 890.00, 'all', NULL, 'catalog_node'),
(9, 'Balón de fútbol JAM Strike No. 5', 'Balón cosido para pasto natural y sintético', 'U', 490.00, 'all', NULL, 'catalog_node'),
(9, 'Balón de básquetbol JAM Court No. 7', 'Balón de hule con buen agarre para duela y exterior', 'U', 590.00, 'all', NULL, 'catalog_node'),
(9, 'Balón de voleibol JAM Spike', 'Balón de piel sintética de tacto suave', 'U', 450.00, 'all', NULL, 'catalog_node'),
(9, 'Pelotas de pádel JAM Pro (3 piezas)', 'Bote constante para pádel y tenis recreativo', 'U', 220.00, 'adult', NULL, 'catalog_node'),
(9, 'Balón de fútbol americano JAM Blitz', 'Balón de piel sintética con agujetas cosidas', 'U', 520.00, 'youth', NULL, 'catalog_node'),
(5, 'Tenis de fútbol JAM Strike FG', 'Tacos para pasto natural con suela de 13 tacos', 'U', 1490.00, 'youth', NULL, 'catalog_node'),
(5, 'Tenis de entrenamiento JAM Flow', 'Tenis ligeros para gimnasio, yoga y cardio', 'F', 1390.00, 'all', NULL, 'catalog_node'),
(5, 'Tenis JAM Sprint Jr', 'Tenis ligeros para la escuela y el deporte', 'U', 990.00, 'youth', NULL, 'catalog_node'),
(2, 'Short de básquetbol JAM Court', 'Short holgado de malla transpirable', 'M', 450.00, 'youth', NULL, 'catalog_node'),
(2, 'Falda short de tenis JAM Ace', 'Falda con short interior y bolsillo para pelota', 'F', 520.00, 'all', NULL, 'catalog_node'),
(1, 'Playera térmica manga larga JAM Thermo', 'Capa base térmica para entrenar con frío', 'U', 580.00, 'adult', NULL, 'catalog_node');
GO

-- Outlet: modelos de la temporada anterior con 30 % de descuento.
-- Las compras del outlet no tienen cambios ni devoluciones.
INSERT INTO product (id_category, name, description, target_gender, base_price, age_segment, outlet_percent, source_node) VALUES
(5, 'Tenis Running JAM Pulse 2025', 'Modelo de la temporada anterior con la misma amortiguación', 'U', 1590.00, 'adult', 30, 'catalog_node'),
(5, 'Tenis Urbanos JAM Street Classic 2025', 'Tenis casuales de la temporada anterior', 'U', 1190.00, 'youth', 30, 'catalog_node'),
(5, 'Tenis Trainer JAM Stride 2025', 'Tenis de entrenamiento de la temporada anterior', 'U', 1690.00, 'all', 30, 'catalog_node'),
(5, 'Sandalias JAM Slide 2025', 'Sandalias acolchadas de la temporada anterior', 'U', 390.00, 'all', 30, 'catalog_node'),
(1, 'Playera JAM Pro 2025', 'Playera de secado rápido de la temporada anterior', 'U', 420.00, 'all', 30, 'catalog_node'),
(1, 'Top Deportivo JAM Core 2025', 'Top de soporte medio de la temporada anterior', 'F', 370.00, 'all', 30, 'catalog_node'),
(2, 'Pants Jogger JAM Move 2025', 'Pants jogger de la temporada anterior', 'U', 650.00, 'youth', 30, 'catalog_node'),
(3, 'Leggings JAM Flex 2025', 'Leggings de compresión de la temporada anterior', 'F', 540.00, 'adult', 30, 'catalog_node'),
(4, 'Sudadera JAM Core 2025', 'Sudadera con capucha de la temporada anterior', 'U', 720.00, 'all', 30, 'catalog_node'),
(4, 'Chamarra JAM Storm 2025', 'Rompevientos de la temporada anterior', 'U', 1150.00, 'adult', 30, 'catalog_node'),
(6, 'Gorra JAM Sport Cap 2025', 'Gorra de la temporada anterior', 'U', 270.00, 'all', 30, 'catalog_node'),
(9, 'Balón de fútbol JAM Strike 2025', 'Balón de la temporada anterior', 'U', 450.00, 'all', 30, 'catalog_node');
GO

-- Variantes de los productos nuevos (se buscan por nombre, talla y color)
INSERT INTO product_variant (id_product, id_size, id_color, sku, source_node)
SELECT p.id_product, sz.id_size, cl.id_color, v.sku, 'catalog_node'
FROM (VALUES
('Lentes para correr JAM Vision', 'UNI', 'black', 'JAM-VIS-UNI-BLK'),
('Lentes para correr JAM Vision', 'UNI', 'white', 'JAM-VIS-UNI-WHT'),
('Lentes para correr JAM Vision', 'UNI', 'blue', 'JAM-VIS-UNI-BLU'),
('Goggles de natación JAM Splash', 'UNI', 'black', 'JAM-SPL-UNI-BLK'),
('Goggles de natación JAM Splash', 'UNI', 'blue', 'JAM-SPL-UNI-BLU'),
('Goggles de natación JAM Splash', 'UNI', 'pink', 'JAM-SPL-UNI-PNK'),
('Gorra Trucker JAM Urban', 'UNI', 'black', 'JAM-TRK-UNI-BLK'),
('Gorra Trucker JAM Urban', 'UNI', 'white', 'JAM-TRK-UNI-WHT'),
('Gorra Trucker JAM Urban', 'UNI', 'navy', 'JAM-TRK-UNI-NVY'),
('Gorra para correr JAM Run Light', 'UNI', 'black', 'JAM-RUN-UNI-BLK'),
('Gorra para correr JAM Run Light', 'UNI', 'white', 'JAM-RUN-UNI-WHT'),
('Gorra para correr JAM Run Light', 'UNI', 'pink', 'JAM-RUN-UNI-PNK'),
('Guantes de gimnasio JAM Grip', 'S', 'black', 'JAM-GRP-S-BLK'),
('Guantes de gimnasio JAM Grip', 'M', 'black', 'JAM-GRP-M-BLK'),
('Guantes de gimnasio JAM Grip', 'L', 'black', 'JAM-GRP-L-BLK'),
('Guantes de gimnasio JAM Grip', 'XL', 'black', 'JAM-GRP-XL-BLK'),
('Guantes de gimnasio JAM Grip', 'S', 'gray', 'JAM-GRP-S-GRY'),
('Guantes de gimnasio JAM Grip', 'M', 'gray', 'JAM-GRP-M-GRY'),
('Guantes de gimnasio JAM Grip', 'L', 'gray', 'JAM-GRP-L-GRY'),
('Guantes de gimnasio JAM Grip', 'XL', 'gray', 'JAM-GRP-XL-GRY'),
('Guantes de portero JAM Keeper', 'S', 'black', 'JAM-KPR-S-BLK'),
('Guantes de portero JAM Keeper', 'M', 'black', 'JAM-KPR-M-BLK'),
('Guantes de portero JAM Keeper', 'L', 'black', 'JAM-KPR-L-BLK'),
('Guantes de portero JAM Keeper', 'S', 'green', 'JAM-KPR-S-GRN'),
('Guantes de portero JAM Keeper', 'M', 'green', 'JAM-KPR-M-GRN'),
('Guantes de portero JAM Keeper', 'L', 'green', 'JAM-KPR-L-GRN'),
('Guantes de box JAM Fight 12 oz', 'UNI', 'red', 'JAM-BOX-UNI-RED'),
('Guantes de box JAM Fight 12 oz', 'UNI', 'black', 'JAM-BOX-UNI-BLK'),
('Guantes de box JAM Fight 12 oz', 'UNI', 'pink', 'JAM-BOX-UNI-PNK'),
('Balón de fútbol JAM Strike No. 5', 'UNI', 'black', 'JAM-FUT-UNI-BLK'),
('Balón de fútbol JAM Strike No. 5', 'UNI', 'blue', 'JAM-FUT-UNI-BLU'),
('Balón de fútbol JAM Strike No. 5', 'UNI', 'red', 'JAM-FUT-UNI-RED'),
('Balón de básquetbol JAM Court No. 7', 'UNI', 'amber', 'JAM-BSK-UNI-AMB'),
('Balón de básquetbol JAM Court No. 7', 'UNI', 'black', 'JAM-BSK-UNI-BLK'),
('Balón de voleibol JAM Spike', 'UNI', 'blue', 'JAM-VOL-UNI-BLU'),
('Balón de voleibol JAM Spike', 'UNI', 'lime', 'JAM-VOL-UNI-LIM'),
('Pelotas de pádel JAM Pro (3 piezas)', 'UNI', 'lime', 'JAM-PAD-UNI-LIM'),
('Balón de fútbol americano JAM Blitz', 'UNI', 'brown', 'JAM-AMR-UNI-BRN'),
('Tenis de fútbol JAM Strike FG', '25', 'black', 'JAM-TAC-25-BLK'),
('Tenis de fútbol JAM Strike FG', '26', 'black', 'JAM-TAC-26-BLK'),
('Tenis de fútbol JAM Strike FG', '27', 'black', 'JAM-TAC-27-BLK'),
('Tenis de fútbol JAM Strike FG', '28', 'black', 'JAM-TAC-28-BLK'),
('Tenis de fútbol JAM Strike FG', '25', 'white', 'JAM-TAC-25-WHT'),
('Tenis de fútbol JAM Strike FG', '26', 'white', 'JAM-TAC-26-WHT'),
('Tenis de fútbol JAM Strike FG', '27', 'white', 'JAM-TAC-27-WHT'),
('Tenis de fútbol JAM Strike FG', '28', 'white', 'JAM-TAC-28-WHT'),
('Tenis de entrenamiento JAM Flow', '25', 'pink', 'JAM-FLW-25-PNK'),
('Tenis de entrenamiento JAM Flow', '26', 'pink', 'JAM-FLW-26-PNK'),
('Tenis de entrenamiento JAM Flow', '27', 'pink', 'JAM-FLW-27-PNK'),
('Tenis de entrenamiento JAM Flow', '25', 'white', 'JAM-FLW-25-WHT'),
('Tenis de entrenamiento JAM Flow', '26', 'white', 'JAM-FLW-26-WHT'),
('Tenis de entrenamiento JAM Flow', '27', 'white', 'JAM-FLW-27-WHT'),
('Tenis de entrenamiento JAM Flow', '25', 'black', 'JAM-FLW-25-BLK'),
('Tenis de entrenamiento JAM Flow', '26', 'black', 'JAM-FLW-26-BLK'),
('Tenis de entrenamiento JAM Flow', '27', 'black', 'JAM-FLW-27-BLK'),
('Tenis JAM Sprint Jr', '25', 'blue', 'JAM-SPR-25-BLU'),
('Tenis JAM Sprint Jr', '26', 'blue', 'JAM-SPR-26-BLU'),
('Tenis JAM Sprint Jr', '27', 'blue', 'JAM-SPR-27-BLU'),
('Tenis JAM Sprint Jr', '25', 'black', 'JAM-SPR-25-BLK'),
('Tenis JAM Sprint Jr', '26', 'black', 'JAM-SPR-26-BLK'),
('Tenis JAM Sprint Jr', '27', 'black', 'JAM-SPR-27-BLK'),
('Short de básquetbol JAM Court', 'S', 'black', 'JAM-CRT-S-BLK'),
('Short de básquetbol JAM Court', 'M', 'black', 'JAM-CRT-M-BLK'),
('Short de básquetbol JAM Court', 'L', 'black', 'JAM-CRT-L-BLK'),
('Short de básquetbol JAM Court', 'XL', 'black', 'JAM-CRT-XL-BLK'),
('Short de básquetbol JAM Court', 'S', 'red', 'JAM-CRT-S-RED'),
('Short de básquetbol JAM Court', 'M', 'red', 'JAM-CRT-M-RED'),
('Short de básquetbol JAM Court', 'L', 'red', 'JAM-CRT-L-RED'),
('Short de básquetbol JAM Court', 'XL', 'red', 'JAM-CRT-XL-RED'),
('Short de básquetbol JAM Court', 'S', 'navy', 'JAM-CRT-S-NVY'),
('Short de básquetbol JAM Court', 'M', 'navy', 'JAM-CRT-M-NVY'),
('Short de básquetbol JAM Court', 'L', 'navy', 'JAM-CRT-L-NVY'),
('Short de básquetbol JAM Court', 'XL', 'navy', 'JAM-CRT-XL-NVY'),
('Falda short de tenis JAM Ace', 'XS', 'white', 'JAM-ACE-XS-WHT'),
('Falda short de tenis JAM Ace', 'S', 'white', 'JAM-ACE-S-WHT'),
('Falda short de tenis JAM Ace', 'M', 'white', 'JAM-ACE-M-WHT'),
('Falda short de tenis JAM Ace', 'L', 'white', 'JAM-ACE-L-WHT'),
('Falda short de tenis JAM Ace', 'XS', 'black', 'JAM-ACE-XS-BLK'),
('Falda short de tenis JAM Ace', 'S', 'black', 'JAM-ACE-S-BLK'),
('Falda short de tenis JAM Ace', 'M', 'black', 'JAM-ACE-M-BLK'),
('Falda short de tenis JAM Ace', 'L', 'black', 'JAM-ACE-L-BLK'),
('Falda short de tenis JAM Ace', 'XS', 'pink', 'JAM-ACE-XS-PNK'),
('Falda short de tenis JAM Ace', 'S', 'pink', 'JAM-ACE-S-PNK'),
('Falda short de tenis JAM Ace', 'M', 'pink', 'JAM-ACE-M-PNK'),
('Falda short de tenis JAM Ace', 'L', 'pink', 'JAM-ACE-L-PNK'),
('Playera térmica manga larga JAM Thermo', 'S', 'black', 'JAM-THM-S-BLK'),
('Playera térmica manga larga JAM Thermo', 'M', 'black', 'JAM-THM-M-BLK'),
('Playera térmica manga larga JAM Thermo', 'L', 'black', 'JAM-THM-L-BLK'),
('Playera térmica manga larga JAM Thermo', 'XL', 'black', 'JAM-THM-XL-BLK'),
('Playera térmica manga larga JAM Thermo', 'S', 'gray', 'JAM-THM-S-GRY'),
('Playera térmica manga larga JAM Thermo', 'M', 'gray', 'JAM-THM-M-GRY'),
('Playera térmica manga larga JAM Thermo', 'L', 'gray', 'JAM-THM-L-GRY'),
('Playera térmica manga larga JAM Thermo', 'XL', 'gray', 'JAM-THM-XL-GRY'),
('Playera térmica manga larga JAM Thermo', 'S', 'navy', 'JAM-THM-S-NVY'),
('Playera térmica manga larga JAM Thermo', 'M', 'navy', 'JAM-THM-M-NVY'),
('Playera térmica manga larga JAM Thermo', 'L', 'navy', 'JAM-THM-L-NVY'),
('Playera térmica manga larga JAM Thermo', 'XL', 'navy', 'JAM-THM-XL-NVY'),
('Tenis Running JAM Pulse 2025', '26', 'gray', 'JAM-PLO-26-GRY'),
('Tenis Running JAM Pulse 2025', '27', 'gray', 'JAM-PLO-27-GRY'),
('Tenis Running JAM Pulse 2025', '28', 'gray', 'JAM-PLO-28-GRY'),
('Tenis Running JAM Pulse 2025', '26', 'blue', 'JAM-PLO-26-BLU'),
('Tenis Running JAM Pulse 2025', '27', 'blue', 'JAM-PLO-27-BLU'),
('Tenis Running JAM Pulse 2025', '28', 'blue', 'JAM-PLO-28-BLU'),
('Tenis Urbanos JAM Street Classic 2025', '25', 'white', 'JAM-STO-25-WHT'),
('Tenis Urbanos JAM Street Classic 2025', '26', 'white', 'JAM-STO-26-WHT'),
('Tenis Urbanos JAM Street Classic 2025', '27', 'white', 'JAM-STO-27-WHT'),
('Tenis Urbanos JAM Street Classic 2025', '25', 'black', 'JAM-STO-25-BLK'),
('Tenis Urbanos JAM Street Classic 2025', '26', 'black', 'JAM-STO-26-BLK'),
('Tenis Urbanos JAM Street Classic 2025', '27', 'black', 'JAM-STO-27-BLK'),
('Tenis Trainer JAM Stride 2025', '26', 'black', 'JAM-SDO-26-BLK'),
('Tenis Trainer JAM Stride 2025', '27', 'black', 'JAM-SDO-27-BLK'),
('Tenis Trainer JAM Stride 2025', '28', 'black', 'JAM-SDO-28-BLK'),
('Tenis Trainer JAM Stride 2025', '26', 'red', 'JAM-SDO-26-RED'),
('Tenis Trainer JAM Stride 2025', '27', 'red', 'JAM-SDO-27-RED'),
('Tenis Trainer JAM Stride 2025', '28', 'red', 'JAM-SDO-28-RED'),
('Sandalias JAM Slide 2025', '26', 'black', 'JAM-SLO-26-BLK'),
('Sandalias JAM Slide 2025', '27', 'black', 'JAM-SLO-27-BLK'),
('Sandalias JAM Slide 2025', '26', 'navy', 'JAM-SLO-26-NVY'),
('Sandalias JAM Slide 2025', '27', 'navy', 'JAM-SLO-27-NVY'),
('Playera JAM Pro 2025', 'S', 'red', 'JAM-PRO5-S-RED'),
('Playera JAM Pro 2025', 'M', 'red', 'JAM-PRO5-M-RED'),
('Playera JAM Pro 2025', 'L', 'red', 'JAM-PRO5-L-RED'),
('Playera JAM Pro 2025', 'XL', 'red', 'JAM-PRO5-XL-RED'),
('Playera JAM Pro 2025', 'S', 'navy', 'JAM-PRO5-S-NVY'),
('Playera JAM Pro 2025', 'M', 'navy', 'JAM-PRO5-M-NVY'),
('Playera JAM Pro 2025', 'L', 'navy', 'JAM-PRO5-L-NVY'),
('Playera JAM Pro 2025', 'XL', 'navy', 'JAM-PRO5-XL-NVY'),
('Top Deportivo JAM Core 2025', 'XS', 'pink', 'JAM-TPO-XS-PNK'),
('Top Deportivo JAM Core 2025', 'S', 'pink', 'JAM-TPO-S-PNK'),
('Top Deportivo JAM Core 2025', 'M', 'pink', 'JAM-TPO-M-PNK'),
('Top Deportivo JAM Core 2025', 'XS', 'black', 'JAM-TPO-XS-BLK'),
('Top Deportivo JAM Core 2025', 'S', 'black', 'JAM-TPO-S-BLK'),
('Top Deportivo JAM Core 2025', 'M', 'black', 'JAM-TPO-M-BLK'),
('Pants Jogger JAM Move 2025', 'S', 'gray', 'JAM-JGO-S-GRY'),
('Pants Jogger JAM Move 2025', 'M', 'gray', 'JAM-JGO-M-GRY'),
('Pants Jogger JAM Move 2025', 'L', 'gray', 'JAM-JGO-L-GRY'),
('Pants Jogger JAM Move 2025', 'S', 'black', 'JAM-JGO-S-BLK'),
('Pants Jogger JAM Move 2025', 'M', 'black', 'JAM-JGO-M-BLK'),
('Pants Jogger JAM Move 2025', 'L', 'black', 'JAM-JGO-L-BLK'),
('Leggings JAM Flex 2025', 'XS', 'blue', 'JAM-FXO-XS-BLU'),
('Leggings JAM Flex 2025', 'S', 'blue', 'JAM-FXO-S-BLU'),
('Leggings JAM Flex 2025', 'M', 'blue', 'JAM-FXO-M-BLU'),
('Leggings JAM Flex 2025', 'L', 'blue', 'JAM-FXO-L-BLU'),
('Leggings JAM Flex 2025', 'XS', 'black', 'JAM-FXO-XS-BLK'),
('Leggings JAM Flex 2025', 'S', 'black', 'JAM-FXO-S-BLK'),
('Leggings JAM Flex 2025', 'M', 'black', 'JAM-FXO-M-BLK'),
('Leggings JAM Flex 2025', 'L', 'black', 'JAM-FXO-L-BLK'),
('Sudadera JAM Core 2025', 'S', 'green', 'JAM-CRO-S-GRN'),
('Sudadera JAM Core 2025', 'M', 'green', 'JAM-CRO-M-GRN'),
('Sudadera JAM Core 2025', 'L', 'green', 'JAM-CRO-L-GRN'),
('Sudadera JAM Core 2025', 'XL', 'green', 'JAM-CRO-XL-GRN'),
('Sudadera JAM Core 2025', 'S', 'gray', 'JAM-CRO-S-GRY'),
('Sudadera JAM Core 2025', 'M', 'gray', 'JAM-CRO-M-GRY'),
('Sudadera JAM Core 2025', 'L', 'gray', 'JAM-CRO-L-GRY'),
('Sudadera JAM Core 2025', 'XL', 'gray', 'JAM-CRO-XL-GRY'),
('Chamarra JAM Storm 2025', 'M', 'navy', 'JAM-STM5-M-NVY'),
('Chamarra JAM Storm 2025', 'L', 'navy', 'JAM-STM5-L-NVY'),
('Chamarra JAM Storm 2025', 'XL', 'navy', 'JAM-STM5-XL-NVY'),
('Chamarra JAM Storm 2025', 'M', 'black', 'JAM-STM5-M-BLK'),
('Chamarra JAM Storm 2025', 'L', 'black', 'JAM-STM5-L-BLK'),
('Chamarra JAM Storm 2025', 'XL', 'black', 'JAM-STM5-XL-BLK'),
('Gorra JAM Sport Cap 2025', 'UNI', 'red', 'JAM-CPO-UNI-RED'),
('Gorra JAM Sport Cap 2025', 'UNI', 'gray', 'JAM-CPO-UNI-GRY'),
('Balón de fútbol JAM Strike 2025', 'UNI', 'red', 'JAM-FTO-UNI-RED')
) AS v (product_name, size_code, color_code, sku)
JOIN product AS p ON p.name = v.product_name
JOIN size AS sz ON sz.code = v.size_code
JOIN color AS cl ON cl.code = v.color_code;
GO


-- Código de barras EAN-13 de cada variante para el escáner de la app:
-- prefijo 750 (México) + 1234 (empresa de ejemplo) + id de la variante con
-- 5 dígitos + dígito verificador.
UPDATE pv
SET pv.barcode = b.base + CAST((10 - s.total % 10) % 10 AS CHAR(1))
FROM product_variant AS pv
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

CREATE UNIQUE INDEX uq_product_variant_barcode ON product_variant (barcode) WHERE barcode IS NOT NULL;
GO

CREATE TABLE inventory (
    id_inventory  INT IDENTITY(1,1) PRIMARY KEY,
    id_store      INT NOT NULL, -- ref. logica a store (otro servicio: Sucursales)
    id_variant    INT NOT NULL,
    stock         INT NOT NULL DEFAULT 0,
    min_stock     INT NOT NULL DEFAULT 5,
    -- columnas de consolidación
    source_node   VARCHAR(20) NOT NULL,
    updated_at    DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted       BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_inventory_variant FOREIGN KEY (id_variant) REFERENCES product_variant (id_variant),
    CONSTRAINT uq_inventory_store_variant UNIQUE (id_store, id_variant),
    CONSTRAINT ck_inventory_stock CHECK (stock >= 0),
    CONSTRAINT ck_inventory_min_stock CHECK (min_stock >= 0)
);
GO

GO

INSERT INTO inventory (id_store, id_variant, stock, min_stock, source_node) VALUES
(1, 1, 40,  5,  'catalog_node'),
(1, 4, 3,   5,  'catalog_node'),
(2, 2, 60,  10, 'catalog_node'),
(5, 5, 2,   5,  'catalog_node'),
(4, 3, 100, 20, 'catalog_node');
GO

-- Existencias de la tienda en línea (4) y las sucursales Tijuana (1) y CDMX (2)
INSERT INTO inventory (id_store, id_variant, stock, min_stock, source_node) VALUES
(2, 1, 32, 5, 'catalog_node'),
(4, 1, 19, 5, 'catalog_node'),
(1, 2, 28, 5, 'catalog_node'),
(4, 2, 26, 5, 'catalog_node'),
(1, 3, 35, 5, 'catalog_node'),
(2, 3, 11, 5, 'catalog_node'),
(2, 4, 18, 5, 'catalog_node'),
(4, 4, 5, 5, 'catalog_node'),
(1, 5, 14, 5, 'catalog_node'),
(4, 5, 12, 5, 'catalog_node'),
(2, 6, 32, 5, 'catalog_node'),
(4, 6, 19, 5, 'catalog_node'),
(1, 7, 28, 5, 'catalog_node'),
(2, 7, 4, 5, 'catalog_node'),
(4, 7, 0, 5, 'catalog_node'),
(1, 8, 35, 5, 'catalog_node'),
(2, 8, 11, 5, 'catalog_node'),
(4, 8, 33, 5, 'catalog_node'),
(1, 9, 7, 5, 'catalog_node'),
(2, 9, 0, 5, 'catalog_node'),
(4, 9, 5, 5, 'catalog_node'),
(1, 10, 0, 5, 'catalog_node'),
(2, 10, 25, 5, 'catalog_node'),
(4, 10, 2, 5, 'catalog_node'),
(1, 11, 21, 5, 'catalog_node'),
(4, 11, 19, 5, 'catalog_node'),
(2, 12, 2, 5, 'catalog_node'),
(4, 12, 26, 5, 'catalog_node'),
(1, 13, 2, 5, 'catalog_node'),
(2, 13, 11, 5, 'catalog_node'),
(4, 13, 33, 5, 'catalog_node'),
(1, 14, 7, 5, 'catalog_node'),
(2, 14, 18, 5, 'catalog_node'),
(4, 14, 5, 5, 'catalog_node'),
(1, 15, 14, 5, 'catalog_node'),
(2, 15, 25, 5, 'catalog_node'),
(4, 15, 12, 5, 'catalog_node'),
(1, 16, 21, 5, 'catalog_node'),
(2, 16, 32, 5, 'catalog_node'),
(4, 16, 19, 5, 'catalog_node'),
(1, 17, 28, 5, 'catalog_node'),
(4, 17, 2, 5, 'catalog_node'),
(2, 18, 11, 5, 'catalog_node'),
(4, 18, 0, 5, 'catalog_node'),
(1, 19, 7, 5, 'catalog_node'),
(2, 19, 2, 5, 'catalog_node'),
(4, 19, 5, 5, 'catalog_node'),
(1, 20, 2, 5, 'catalog_node'),
(2, 20, 0, 5, 'catalog_node'),
(4, 20, 12, 5, 'catalog_node'),
(1, 21, 0, 5, 'catalog_node'),
(2, 21, 32, 5, 'catalog_node'),
(4, 21, 19, 5, 'catalog_node'),
(1, 22, 28, 5, 'catalog_node'),
(2, 22, 4, 5, 'catalog_node'),
(4, 22, 26, 5, 'catalog_node'),
(1, 23, 35, 5, 'catalog_node'),
(4, 23, 33, 5, 'catalog_node'),
(2, 24, 18, 5, 'catalog_node'),
(4, 24, 2, 5, 'catalog_node'),
(1, 25, 14, 5, 'catalog_node'),
(2, 25, 25, 5, 'catalog_node'),
(4, 25, 12, 5, 'catalog_node'),
(1, 26, 21, 5, 'catalog_node'),
(2, 26, 2, 5, 'catalog_node'),
(4, 26, 19, 5, 'catalog_node'),
(1, 27, 2, 5, 'catalog_node'),
(2, 27, 4, 5, 'catalog_node'),
(4, 27, 26, 5, 'catalog_node'),
(1, 28, 35, 5, 'catalog_node'),
(2, 28, 11, 5, 'catalog_node'),
(4, 28, 33, 5, 'catalog_node'),
(1, 29, 7, 5, 'catalog_node'),
(4, 29, 0, 5, 'catalog_node'),
(2, 30, 25, 5, 'catalog_node'),
(4, 30, 12, 5, 'catalog_node'),
(1, 31, 21, 5, 'catalog_node'),
(2, 31, 0, 5, 'catalog_node'),
(4, 31, 2, 5, 'catalog_node'),
(1, 32, 0, 5, 'catalog_node'),
(2, 32, 4, 5, 'catalog_node'),
(4, 32, 26, 5, 'catalog_node'),
(1, 33, 35, 5, 'catalog_node'),
(2, 33, 2, 5, 'catalog_node'),
(4, 33, 33, 5, 'catalog_node'),
(1, 34, 2, 5, 'catalog_node'),
(2, 34, 18, 5, 'catalog_node'),
(4, 34, 5, 5, 'catalog_node'),
(1, 35, 14, 5, 'catalog_node'),
(4, 35, 12, 5, 'catalog_node'),
(2, 36, 32, 5, 'catalog_node'),
(4, 36, 19, 5, 'catalog_node'),
(1, 37, 28, 5, 'catalog_node'),
(2, 37, 4, 5, 'catalog_node'),
(4, 37, 26, 5, 'catalog_node'),
(1, 38, 35, 5, 'catalog_node'),
(2, 38, 11, 5, 'catalog_node'),
(4, 38, 2, 5, 'catalog_node'),
(1, 39, 7, 5, 'catalog_node'),
(2, 39, 18, 5, 'catalog_node'),
(4, 39, 5, 5, 'catalog_node'),
(1, 40, 14, 5, 'catalog_node'),
(2, 40, 2, 5, 'catalog_node'),
(4, 40, 0, 5, 'catalog_node'),
(1, 41, 2, 5, 'catalog_node'),
(4, 41, 19, 5, 'catalog_node'),
(2, 42, 0, 5, 'catalog_node'),
(4, 42, 26, 5, 'catalog_node'),
(1, 43, 0, 5, 'catalog_node'),
(2, 43, 11, 5, 'catalog_node'),
(4, 43, 33, 5, 'catalog_node'),
(1, 44, 7, 5, 'catalog_node'),
(2, 44, 18, 5, 'catalog_node'),
(4, 44, 5, 5, 'catalog_node'),
(1, 45, 14, 5, 'catalog_node'),
(2, 45, 25, 5, 'catalog_node'),
(4, 45, 2, 5, 'catalog_node'),
(1, 46, 21, 5, 'catalog_node'),
(2, 46, 32, 5, 'catalog_node'),
(4, 46, 19, 5, 'catalog_node'),
(1, 47, 28, 5, 'catalog_node'),
(4, 47, 26, 5, 'catalog_node'),
(2, 48, 11, 5, 'catalog_node'),
(4, 48, 33, 5, 'catalog_node'),
(1, 49, 7, 5, 'catalog_node'),
(2, 49, 18, 5, 'catalog_node'),
(4, 49, 5, 5, 'catalog_node'),
(1, 50, 14, 5, 'catalog_node'),
(2, 50, 25, 5, 'catalog_node'),
(4, 50, 12, 5, 'catalog_node'),
(1, 51, 21, 5, 'catalog_node'),
(2, 51, 32, 5, 'catalog_node'),
(4, 51, 0, 5, 'catalog_node'),
(1, 52, 28, 5, 'catalog_node'),
(2, 52, 4, 5, 'catalog_node'),
(4, 52, 2, 5, 'catalog_node'),
(1, 53, 35, 5, 'catalog_node'),
(4, 53, 33, 5, 'catalog_node'),
(2, 54, 2, 5, 'catalog_node'),
(4, 54, 5, 5, 'catalog_node'),
(1, 55, 2, 5, 'catalog_node'),
(2, 55, 25, 5, 'catalog_node'),
(4, 55, 12, 5, 'catalog_node'),
(1, 56, 21, 5, 'catalog_node'),
(2, 56, 32, 5, 'catalog_node'),
(4, 56, 19, 5, 'catalog_node'),
(1, 57, 28, 5, 'catalog_node'),
(2, 57, 4, 5, 'catalog_node'),
(4, 57, 26, 5, 'catalog_node'),
(1, 58, 35, 5, 'catalog_node'),
(2, 58, 11, 5, 'catalog_node'),
(4, 58, 33, 5, 'catalog_node'),
(1, 59, 7, 5, 'catalog_node'),
(4, 59, 2, 5, 'catalog_node'),
(2, 60, 25, 5, 'catalog_node'),
(4, 60, 12, 5, 'catalog_node'),
(1, 61, 21, 5, 'catalog_node'),
(2, 61, 2, 5, 'catalog_node'),
(4, 61, 19, 5, 'catalog_node'),
(1, 62, 2, 5, 'catalog_node'),
(2, 62, 4, 5, 'catalog_node'),
(4, 62, 0, 5, 'catalog_node'),
(1, 63, 35, 5, 'catalog_node'),
(2, 63, 11, 5, 'catalog_node'),
(4, 63, 33, 5, 'catalog_node'),
(1, 64, 7, 5, 'catalog_node'),
(2, 64, 0, 5, 'catalog_node'),
(4, 64, 5, 5, 'catalog_node'),
(1, 65, 0, 5, 'catalog_node'),
(4, 65, 12, 5, 'catalog_node'),
(2, 66, 32, 5, 'catalog_node'),
(4, 66, 2, 5, 'catalog_node'),
(1, 67, 28, 5, 'catalog_node'),
(2, 67, 4, 5, 'catalog_node'),
(4, 67, 26, 5, 'catalog_node'),
(1, 68, 35, 5, 'catalog_node'),
(2, 68, 2, 5, 'catalog_node'),
(4, 68, 33, 5, 'catalog_node'),
(1, 69, 2, 5, 'catalog_node'),
(2, 69, 18, 5, 'catalog_node'),
(4, 69, 5, 5, 'catalog_node'),
(1, 70, 14, 5, 'catalog_node'),
(2, 70, 25, 5, 'catalog_node'),
(4, 70, 12, 5, 'catalog_node'),
(1, 71, 21, 5, 'catalog_node'),
(4, 71, 19, 5, 'catalog_node'),
(2, 72, 4, 5, 'catalog_node'),
(4, 72, 26, 5, 'catalog_node'),
(1, 73, 35, 5, 'catalog_node'),
(2, 73, 11, 5, 'catalog_node'),
(4, 73, 0, 5, 'catalog_node'),
(1, 74, 7, 5, 'catalog_node'),
(2, 74, 18, 5, 'catalog_node'),
(4, 74, 5, 5, 'catalog_node'),
(1, 75, 14, 5, 'catalog_node'),
(2, 75, 0, 5, 'catalog_node'),
(4, 75, 12, 5, 'catalog_node'),
(1, 76, 0, 5, 'catalog_node'),
(2, 76, 32, 5, 'catalog_node'),
(4, 76, 19, 5, 'catalog_node'),
(1, 77, 28, 5, 'catalog_node'),
(4, 77, 26, 5, 'catalog_node'),
(2, 78, 11, 5, 'catalog_node'),
(4, 78, 33, 5, 'catalog_node'),
(1, 79, 7, 5, 'catalog_node'),
(2, 79, 18, 5, 'catalog_node'),
(4, 79, 5, 5, 'catalog_node'),
(1, 80, 14, 5, 'catalog_node'),
(2, 80, 25, 5, 'catalog_node'),
(4, 80, 2, 5, 'catalog_node'),
(1, 81, 21, 5, 'catalog_node'),
(2, 81, 32, 5, 'catalog_node'),
(4, 81, 19, 5, 'catalog_node'),
(1, 82, 28, 5, 'catalog_node'),
(2, 82, 2, 5, 'catalog_node'),
(4, 82, 26, 5, 'catalog_node'),
(1, 83, 2, 5, 'catalog_node'),
(4, 83, 33, 5, 'catalog_node'),
(2, 84, 18, 5, 'catalog_node'),
(4, 84, 0, 5, 'catalog_node'),
(1, 85, 14, 5, 'catalog_node'),
(2, 85, 25, 5, 'catalog_node'),
(4, 85, 12, 5, 'catalog_node'),
(1, 86, 21, 5, 'catalog_node'),
(2, 86, 0, 5, 'catalog_node'),
(4, 86, 19, 5, 'catalog_node'),
(1, 87, 0, 5, 'catalog_node'),
(2, 87, 4, 5, 'catalog_node'),
(4, 87, 2, 5, 'catalog_node'),
(1, 88, 35, 5, 'catalog_node'),
(2, 88, 11, 5, 'catalog_node'),
(4, 88, 33, 5, 'catalog_node'),
(1, 89, 7, 5, 'catalog_node'),
(4, 89, 5, 5, 'catalog_node'),
(2, 90, 25, 5, 'catalog_node'),
(4, 90, 12, 5, 'catalog_node'),
(1, 91, 21, 5, 'catalog_node'),
(2, 91, 32, 5, 'catalog_node'),
(4, 91, 19, 5, 'catalog_node'),
(1, 92, 28, 5, 'catalog_node'),
(2, 92, 4, 5, 'catalog_node'),
(4, 92, 26, 5, 'catalog_node'),
(1, 93, 35, 5, 'catalog_node'),
(2, 93, 11, 5, 'catalog_node'),
(4, 93, 33, 5, 'catalog_node'),
(1, 94, 7, 5, 'catalog_node'),
(2, 94, 18, 5, 'catalog_node'),
(4, 94, 2, 5, 'catalog_node'),
(1, 95, 14, 5, 'catalog_node'),
(4, 95, 0, 5, 'catalog_node'),
(2, 96, 2, 5, 'catalog_node'),
(4, 96, 19, 5, 'catalog_node'),
(1, 97, 2, 5, 'catalog_node'),
(2, 97, 0, 5, 'catalog_node'),
(4, 97, 26, 5, 'catalog_node'),
(1, 98, 0, 5, 'catalog_node'),
(2, 98, 11, 5, 'catalog_node'),
(4, 98, 33, 5, 'catalog_node'),
(1, 99, 7, 5, 'catalog_node'),
(2, 99, 18, 5, 'catalog_node'),
(4, 99, 5, 5, 'catalog_node'),
(1, 100, 14, 5, 'catalog_node'),
(2, 100, 25, 5, 'catalog_node'),
(4, 100, 12, 5, 'catalog_node'),
(1, 101, 21, 5, 'catalog_node'),
(4, 101, 2, 5, 'catalog_node'),
(2, 102, 4, 5, 'catalog_node'),
(4, 102, 26, 5, 'catalog_node'),
(1, 103, 35, 5, 'catalog_node'),
(2, 103, 2, 5, 'catalog_node'),
(4, 103, 33, 5, 'catalog_node'),
(1, 104, 2, 5, 'catalog_node'),
(2, 104, 18, 5, 'catalog_node'),
(4, 104, 5, 5, 'catalog_node'),
(1, 105, 14, 5, 'catalog_node'),
(2, 105, 25, 5, 'catalog_node'),
(4, 105, 12, 5, 'catalog_node'),
(1, 106, 21, 5, 'catalog_node'),
(2, 106, 32, 5, 'catalog_node'),
(4, 106, 0, 5, 'catalog_node'),
(1, 107, 28, 5, 'catalog_node'),
(4, 107, 26, 5, 'catalog_node'),
(2, 108, 0, 5, 'catalog_node'),
(4, 108, 2, 5, 'catalog_node'),
(1, 109, 0, 5, 'catalog_node'),
(2, 109, 18, 5, 'catalog_node'),
(4, 109, 5, 5, 'catalog_node'),
(1, 110, 14, 5, 'catalog_node'),
(2, 110, 2, 5, 'catalog_node'),
(4, 110, 12, 5, 'catalog_node'),
(1, 111, 2, 5, 'catalog_node'),
(2, 111, 32, 5, 'catalog_node'),
(4, 111, 19, 5, 'catalog_node'),
(1, 112, 28, 5, 'catalog_node'),
(2, 112, 4, 5, 'catalog_node'),
(4, 112, 26, 5, 'catalog_node'),
(1, 113, 35, 5, 'catalog_node'),
(4, 113, 33, 5, 'catalog_node'),
(2, 114, 18, 5, 'catalog_node'),
(4, 114, 5, 5, 'catalog_node'),
(1, 115, 14, 5, 'catalog_node'),
(2, 115, 25, 5, 'catalog_node'),
(4, 115, 2, 5, 'catalog_node'),
(1, 116, 21, 5, 'catalog_node'),
(2, 116, 32, 5, 'catalog_node'),
(4, 116, 19, 5, 'catalog_node'),
(1, 117, 28, 5, 'catalog_node'),
(2, 117, 2, 5, 'catalog_node'),
(4, 117, 0, 5, 'catalog_node'),
(1, 118, 2, 5, 'catalog_node'),
(2, 118, 11, 5, 'catalog_node'),
(4, 118, 33, 5, 'catalog_node'),
(1, 119, 7, 5, 'catalog_node'),
(4, 119, 5, 5, 'catalog_node'),
(2, 120, 25, 5, 'catalog_node'),
(4, 120, 12, 5, 'catalog_node'),
(1, 121, 21, 5, 'catalog_node'),
(2, 121, 32, 5, 'catalog_node'),
(4, 121, 19, 5, 'catalog_node'),
(1, 122, 28, 5, 'catalog_node'),
(2, 122, 4, 5, 'catalog_node'),
(4, 122, 2, 5, 'catalog_node'),
(1, 123, 35, 5, 'catalog_node'),
(2, 123, 11, 5, 'catalog_node'),
(4, 123, 33, 5, 'catalog_node'),
(1, 124, 7, 5, 'catalog_node'),
(2, 124, 2, 5, 'catalog_node'),
(4, 124, 5, 5, 'catalog_node'),
(1, 125, 2, 5, 'catalog_node'),
(4, 125, 12, 5, 'catalog_node'),
(2, 126, 32, 5, 'catalog_node'),
(4, 126, 19, 5, 'catalog_node'),
(1, 127, 28, 5, 'catalog_node'),
(2, 127, 4, 5, 'catalog_node'),
(4, 127, 26, 5, 'catalog_node'),
(1, 128, 35, 5, 'catalog_node'),
(2, 128, 11, 5, 'catalog_node'),
(4, 128, 0, 5, 'catalog_node'),
(1, 129, 7, 5, 'catalog_node'),
(2, 129, 18, 5, 'catalog_node'),
(4, 129, 2, 5, 'catalog_node'),
(1, 130, 14, 5, 'catalog_node'),
(2, 130, 0, 5, 'catalog_node'),
(4, 130, 12, 5, 'catalog_node'),
(1, 131, 0, 5, 'catalog_node'),
(4, 131, 19, 5, 'catalog_node'),
(2, 132, 4, 5, 'catalog_node'),
(4, 132, 26, 5, 'catalog_node'),
(1, 133, 35, 5, 'catalog_node'),
(2, 133, 11, 5, 'catalog_node'),
(4, 133, 33, 5, 'catalog_node'),
(1, 134, 7, 5, 'catalog_node'),
(2, 134, 18, 5, 'catalog_node'),
(4, 134, 5, 5, 'catalog_node');
GO

-- Existencias de los productos nuevos en Tijuana (1), CDMX (2) y la tienda en línea (4)
INSERT INTO inventory (id_store, id_variant, stock, min_stock, source_node)
SELECT s.id_store, pv.id_variant,
       CASE WHEN (pv.id_variant + s.id_store) % 11 = 0 THEN 0
            ELSE 4 + (pv.id_variant * 7 + s.id_store * 13) % 26 END,
       3, 'catalog_node'
FROM product_variant AS pv
JOIN product AS p ON p.id_product = pv.id_product
CROSS JOIN (VALUES (1), (2), (4)) AS s (id_store)
WHERE p.name IN ('Lentes para correr JAM Vision',
                 'Goggles de natación JAM Splash',
                 'Gorra Trucker JAM Urban',
                 'Gorra para correr JAM Run Light',
                 'Guantes de gimnasio JAM Grip',
                 'Guantes de portero JAM Keeper',
                 'Guantes de box JAM Fight 12 oz',
                 'Balón de fútbol JAM Strike No. 5',
                 'Balón de básquetbol JAM Court No. 7',
                 'Balón de voleibol JAM Spike',
                 'Pelotas de pádel JAM Pro (3 piezas)',
                 'Balón de fútbol americano JAM Blitz',
                 'Tenis de fútbol JAM Strike FG',
                 'Tenis de entrenamiento JAM Flow',
                 'Tenis JAM Sprint Jr',
                 'Short de básquetbol JAM Court',
                 'Falda short de tenis JAM Ace',
                 'Playera térmica manga larga JAM Thermo',
                 'Tenis Running JAM Pulse 2025',
                 'Tenis Urbanos JAM Street Classic 2025',
                 'Tenis Trainer JAM Stride 2025',
                 'Sandalias JAM Slide 2025',
                 'Playera JAM Pro 2025',
                 'Top Deportivo JAM Core 2025',
                 'Pants Jogger JAM Move 2025',
                 'Leggings JAM Flex 2025',
                 'Sudadera JAM Core 2025',
                 'Chamarra JAM Storm 2025',
                 'Gorra JAM Sport Cap 2025',
                 'Balón de fútbol JAM Strike 2025');
GO


-- =====================================================================
-- 7. Servicio Pedidos
-- =====================================================================

CREATE TABLE promotion (
    id_promotion      INT IDENTITY(1,1) PRIMARY KEY,
    code              VARCHAR(20) NOT NULL,
    description       NVARCHAR(150) NULL,
    discount_percent  DECIMAL(5,2) NOT NULL,
    start_date        DATE NOT NULL,
    end_date          DATE NOT NULL,
    -- columnas de consolidación
    source_node       VARCHAR(20) NOT NULL,
    updated_at        DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted           BIT NOT NULL DEFAULT 0,
    CONSTRAINT ck_promotion_discount_percent CHECK (discount_percent BETWEEN 0 AND 100),
    CONSTRAINT ck_promotion_dates CHECK (end_date >= start_date)
);
GO

INSERT INTO promotion (code, description, discount_percent, start_date, end_date, source_node) VALUES
('BIENVENIDA10', 'Descuento de bienvenida para nuevos clientes',      10.00, '2026-01-01', '2026-12-31', 'orders_node'),
('VERANO2026',   'Promoción de temporada de verano',                  15.00, '2026-06-01', '2026-08-31', 'orders_node'),
('BLACKFRIDAY',  'Descuento especial de Black Friday JAM',             25.00, '2026-11-20', '2026-11-30', 'orders_node'),
('VIPGOLD',      'Descuento exclusivo para clientes nivel Oro',       20.00, '2026-01-01', '2026-12-31', 'orders_node'),
('ENVIOGRATIS',  'Promoción de envío gratis (sin descuento en producto)', 0.00, '2026-01-01', '2026-12-31', 'orders_node');
GO

CREATE TABLE customer_order (
    id_order       INT IDENTITY(1,1) PRIMARY KEY,
    id_store       INT NOT NULL,      -- ref. logica a store (otro servicio: Sucursales)
    id_customer    INT NOT NULL,      -- ref. logica a customer (otro servicio: Clientes finales)
    id_employee    INT NULL,          -- ref. logica a employee (otro servicio: Empleados)
    id_promotion   INT NULL,
    order_date     DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    status         VARCHAR(9) NOT NULL,
    channel        VARCHAR(8) NOT NULL,
    subtotal       DECIMAL(10,2) NOT NULL,
    discount       DECIMAL(10,2) NOT NULL DEFAULT 0,
    total          DECIMAL(10,2) NOT NULL,
    delivery_type  VARCHAR(8) NOT NULL CONSTRAINT df_customer_order_delivery_type DEFAULT 'pickup',
    id_address     INT NULL,          -- domicilio de entrega cuando delivery_type = 'shipping'
    shipping_cost  DECIMAL(8,2) NOT NULL CONSTRAINT df_customer_order_shipping_cost DEFAULT 0,
    priority_shipping BIT NOT NULL CONSTRAINT df_customer_order_priority_shipping DEFAULT 0,
    gift_cost      DECIMAL(6,2) NOT NULL CONSTRAINT df_customer_order_gift_cost DEFAULT 0,
    notes          NVARCHAR(255) NULL,
    -- columnas de consolidación
    source_node    VARCHAR(20) NOT NULL,
    updated_at     DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted        BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_customer_order_promotion FOREIGN KEY (id_promotion) REFERENCES promotion (id_promotion),
    CONSTRAINT ck_customer_order_status CHECK (status IN ('pending','paid','shipped','delivered','cancelled','returned')),
    CONSTRAINT ck_customer_order_channel CHECK (channel IN ('in_store','online','whatsapp')),
    CONSTRAINT ck_customer_order_subtotal CHECK (subtotal >= 0),
    CONSTRAINT ck_customer_order_discount CHECK (discount >= 0),
    CONSTRAINT ck_customer_order_total CHECK (total >= 0),
    CONSTRAINT ck_customer_order_delivery_type CHECK (delivery_type IN ('pickup','shipping')),
    CONSTRAINT ck_customer_order_shipping_cost CHECK (shipping_cost >= 0)
);
GO

CREATE INDEX ix_customer_order_store ON customer_order (id_store);
CREATE INDEX ix_customer_order_customer ON customer_order (id_customer);
CREATE INDEX ix_customer_order_employee ON customer_order (id_employee);
GO

INSERT INTO customer_order (id_store, id_customer, id_employee, id_promotion, status, channel, subtotal, discount, total, notes, source_node) VALUES
(1, 1, 1, NULL, 'paid',      'in_store', 900.00,  0.00,   900.00,  'Compra en tienda Tijuana',           'orders_node'),
(4, 2, NULL, 2, 'paid',      'online',   750.00,  112.50, 637.50,  'Aplicó cupón VERANO2026',            'orders_node'),
(3, 3, NULL, NULL,'pending', 'in_store', 520.00,  52.00,  468.00,  'Pendiente de pago, nivel Oro',       'orders_node'),
(5, 4, 5, 3,    'paid',      'in_store', 1850.00, 462.50, 1387.50, 'Aplicó cupón BLACKFRIDAY',           'orders_node'),
(4, 5, NULL, NULL,'cancelled','online',  1140.00, 0.00,   1140.00, 'Cliente canceló, pago reembolsado',  'orders_node');
GO

CREATE TABLE order_item (
    id_item        INT IDENTITY(1,1) PRIMARY KEY,
    id_order       INT NOT NULL,
    id_variant     INT NOT NULL, -- ref. logica a product_variant (otro servicio: Catálogo de productos)
    quantity       SMALLINT NOT NULL,
    unit_price     DECIMAL(8,2) NOT NULL,
    -- columnas de consolidación
    source_node    VARCHAR(20) NOT NULL,
    updated_at     DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted        BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_order_item_order FOREIGN KEY (id_order) REFERENCES customer_order (id_order),
    CONSTRAINT uq_order_item_order_variant UNIQUE (id_order, id_variant),
    CONSTRAINT ck_order_item_quantity CHECK (quantity > 0),
    CONSTRAINT ck_order_item_unit_price CHECK (unit_price >= 0)
);
GO

CREATE INDEX ix_order_item_variant ON order_item (id_variant);
GO

INSERT INTO order_item (id_order, id_variant, quantity, unit_price, source_node) VALUES
(1, 1, 2, 450.00,  'orders_node'),
(2, 4, 1, 750.00,  'orders_node'),
(3, 3, 1, 520.00,  'orders_node'),
(4, 5, 1, 1850.00, 'orders_node'),
(5, 2, 3, 380.00,  'orders_node');
GO

CREATE TABLE payment (
    id_payment          INT IDENTITY(1,1) PRIMARY KEY,
    id_order            INT NOT NULL,
    amount              DECIMAL(10,2) NOT NULL,
    currency            CHAR(3) NOT NULL DEFAULT 'MXN',
    payment_date        DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    method              VARCHAR(8) NOT NULL,
    external_reference  VARCHAR(50) NULL,
    status              VARCHAR(9) NOT NULL,
    -- columnas de consolidación
    source_node         VARCHAR(20) NOT NULL,
    updated_at          DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted             BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_payment_order FOREIGN KEY (id_order) REFERENCES customer_order (id_order),
    CONSTRAINT ck_payment_amount CHECK (amount >= 0),
    CONSTRAINT ck_payment_method CHECK (method IN ('card','transfer','cash','oxxo')),
    CONSTRAINT ck_payment_status CHECK (status IN ('pending','completed','failed','refunded'))
);
GO

INSERT INTO payment (id_order, amount, currency, method, external_reference, status, source_node) VALUES
(1, 900.00,   'MXN', 'cash',     NULL,          'completed', 'orders_node'),
(2, 637.50,   'MXN', 'card',     'TXN-00234',   'completed', 'orders_node'),
(3, 468.00,   'MXN', 'transfer', NULL,          'pending',   'orders_node'),
(4, 1387.50,  'MXN', 'card',     'TXN-00567',   'completed', 'orders_node'),
(5, 1140.00,  'MXN', 'card',     'TXN-00812',   'refunded',  'orders_node');
GO

-- Historial de ventas de las últimas cuatro semanas en las tres sucursales
-- que forman la base distribuida (1 Tijuana, 2 CDMX, 4 En línea). Alimenta
-- las gráficas del panel y las recomendaciones "también compraron".
-- Las fechas son relativas al día en que se ejecuta el script.
DECLARE @historial TABLE (
    n           INT         NOT NULL PRIMARY KEY,
    days_ago    INT         NOT NULL,
    id_store    INT         NOT NULL,
    id_customer INT         NOT NULL,
    status      VARCHAR(9)  NOT NULL,
    method      VARCHAR(8)  NOT NULL,
    sku1        VARCHAR(30) NOT NULL,
    qty1        SMALLINT    NOT NULL,
    sku2        VARCHAR(30) NULL,
    qty2        SMALLINT    NULL
);

INSERT INTO @historial (n, days_ago, id_store, id_customer, status, method, sku1, qty1, sku2, qty2) VALUES
( 1, 27, 1, 1, 'delivered', 'cash',     'JAM-PRO-M-BLK',   1, 'JAM-AIR-M-BLK',   1),
( 2, 26, 4, 3, 'delivered', 'card',     'JAM-PLS-27-BLK',  1, 'JAM-SCK-M-BLK',   1),
( 3, 25, 2, 4, 'delivered', 'card',     'JAM-CORE-L-GRY',  1, 'JAM-JOG-L-GRY',   1),
( 4, 24, 4, 2, 'delivered', 'transfer', 'JAM-CLB-M-GRN',  12, 'JAM-AIR-M-NVY',  12),
( 5, 23, 1, 3, 'delivered', 'card',     'JAM-FLX-S-BLK',   1, 'JAM-TOP-M-BLK',   1),
( 6, 22, 2, 1, 'delivered', 'cash',     'JAM-STT-27-WHT',  1, NULL,             NULL),
( 7, 21, 4, 4, 'delivered', 'oxxo',     'JAM-GYM-UNI-BLK', 1, 'JAM-BTL-UNI-BLK', 1),
( 8, 20, 1, 1, 'delivered', 'card',     'JAM-PLS-27-BLK',  1, 'JAM-SCK-M-BLK',   1),
( 9, 19, 2, 3, 'delivered', 'card',     'JAM-SML-S-BLK',   1, 'JAM-TOP-M-BLK',   1),
(10, 18, 4, 5, 'delivered', 'card',     'JAM-CAP-UNI-BLK', 1, NULL,             NULL),
(11, 17, 1, 4, 'delivered', 'cash',     'JAM-JMP-27-BLK',  1, 'JAM-SCK-L-BLK',   1),
(12, 16, 4, 1, 'delivered', 'card',     'JAM-STM-L-BLK',   1, 'JAM-CORE-M-BLK',  1),
(13, 15, 2, 3, 'delivered', 'transfer', 'JAM-PRO-S-WHT',   2, 'JAM-AIR-S-BLK',   2),
(14, 14, 1, 4, 'delivered', 'card',     'JAM-STR-27-WHT',  1, 'JAM-BTL-UNI-BLU', 1),
(15, 13, 4, 3, 'delivered', 'card',     'JAM-FLX-M-GRY',   1, 'JAM-SML-S-BLK',   1),
(16, 12, 2, 1, 'delivered', 'cash',     'JAM-SLD-27-BLK',  1, NULL,             NULL),
(17, 11, 4, 2, 'delivered', 'transfer', 'JAM-PRO-L-BLK',  10, 'JAM-SCK-L-WHT',  10),
(18, 10, 1, 3, 'delivered', 'card',     'JAM-PLS-27-BLK',  1, 'JAM-BTL-UNI-BLK', 1),
(19,  9, 2, 4, 'delivered', 'card',     'JAM-CORE-XL-NVY', 1, 'JAM-JOG-M-BLK',   1),
(20,  8, 4, 1, 'delivered', 'oxxo',     'JAM-TOP-M-BLK',   1, 'JAM-FLX-S-BLK',   1),
(21,  7, 1, 4, 'shipped',   'card',     'JAM-JMP-27-BLK',  1, NULL,             NULL),
(22,  6, 4, 3, 'shipped',   'card',     'JAM-STM-L-BLK',   1, 'JAM-CAP-UNI-RED', 1),
(23,  5, 2, 1, 'delivered', 'cash',     'JAM-PRO-M-RED',   1, 'JAM-AIR-L-BLK',   1),
(24,  5, 4, 5, 'shipped',   'card',     'JAM-SCK-S-BLK',   1, NULL,             NULL),
(25,  4, 1, 3, 'delivered', 'card',     'JAM-STT-27-WHT',  1, 'JAM-SCK-M-WHT',   1),
(26,  3, 4, 4, 'paid',      'card',     'JAM-PLS-27-BLK',  1, 'JAM-GYM-UNI-GRY', 1),
(27,  3, 2, 3, 'delivered', 'cash',     'JAM-CORE-M-GRY',  1, 'JAM-JOG-L-GRY',   1),
(28,  2, 1, 1, 'paid',      'card',     'JAM-FLX-M-BLK',   1, 'JAM-TOP-M-BLK',   1),
(29,  1, 4, 2, 'paid',      'transfer', 'JAM-STR-27-WHT',  6, 'JAM-SCK-M-BLK',   6),
(30,  1, 2, 4, 'paid',      'card',     'JAM-SLD-26-BLK',  1, 'JAM-CAP-UNI-WHT', 1),
(31,  0, 1, 3, 'paid',      'cash',     'JAM-PRO-M-WHT',   1, 'JAM-AIR-M-BLK',   1),
(32,  0, 4, 1, 'pending',   'oxxo',     'JAM-BTL-UNI-WHT', 1, NULL,             NULL);

DECLARE @n INT = 1, @ultimo INT = (SELECT MAX(n) FROM @historial), @id_order INT;

WHILE @n <= @ultimo
BEGIN
    INSERT INTO customer_order (id_store, id_customer, order_date, status, channel, subtotal, discount, total,
                                delivery_type, shipping_cost, priority_shipping, notes, source_node)
    SELECT h.id_store, h.id_customer,
           DATEADD(MINUTE, -(60 + (h.n * 37) % 540), DATEADD(DAY, -h.days_ago, SYSUTCDATETIME())),
           h.status,
           CASE WHEN h.id_store = 4 THEN 'online' ELSE 'in_store' END,
           x.subtotal, 0,
           x.subtotal + envio.costo,
           CASE WHEN h.id_store = 4 THEN 'shipping' ELSE 'pickup' END,
           envio.costo,
           CASE WHEN h.id_customer = 2 THEN 1 ELSE 0 END,
           'Historial de ventas',
           'orders_node'
    FROM @historial AS h
    CROSS APPLY (
        SELECT SUM(p.base_price * l.qty) AS subtotal
        FROM (VALUES (h.sku1, h.qty1), (h.sku2, h.qty2)) AS l (sku, qty)
        JOIN product_variant AS pv ON pv.sku = l.sku
        JOIN product AS p ON p.id_product = pv.id_product
    ) AS x
    CROSS APPLY (
        SELECT CAST(CASE WHEN h.id_store = 4 AND h.id_customer <> 2 AND x.subtotal < 999 THEN 99 ELSE 0 END AS DECIMAL(8,2)) AS costo
    ) AS envio
    WHERE h.n = @n;

    SET @id_order = SCOPE_IDENTITY();

    INSERT INTO order_item (id_order, id_variant, quantity, unit_price, source_node)
    SELECT @id_order, pv.id_variant, l.qty, p.base_price, 'orders_node'
    FROM @historial AS h
    CROSS APPLY (VALUES (h.sku1, h.qty1), (h.sku2, h.qty2)) AS l (sku, qty)
    JOIN product_variant AS pv ON pv.sku = l.sku
    JOIN product AS p ON p.id_product = pv.id_product
    WHERE h.n = @n;

    INSERT INTO payment (id_order, amount, currency, payment_date, method, status, source_node)
    SELECT co.id_order, co.total, 'MXN', co.order_date, h.method,
           CASE WHEN co.status = 'pending' THEN 'pending' ELSE 'completed' END, 'orders_node'
    FROM customer_order AS co
    JOIN @historial AS h ON h.n = @n
    WHERE co.id_order = @id_order;

    SET @n += 1;
END;
GO


-- =====================================================================
-- 8. Servicio Mensajería / Auditoría
-- =====================================================================

CREATE TABLE message_log (
    id_message        INT IDENTITY(1,1) PRIMARY KEY,
    id_store          INT NOT NULL, -- ref. logica a store (otro servicio: Sucursales)
    phone             VARCHAR(15) NOT NULL,
    direction         VARCHAR(8) NOT NULL,
    type              VARCHAR(20) NOT NULL,
    content           NVARCHAR(MAX) NOT NULL,
    response_time_ms  INT NULL,
    -- tabla de bitácora: source_node y created_at, sin deleted
    source_node       VARCHAR(20) NOT NULL,
    created_at        DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT ck_message_log_direction CHECK (direction IN ('inbound','outbound')),
    CONSTRAINT ck_message_log_response_time CHECK (response_time_ms IS NULL OR response_time_ms >= 0)
);
GO

CREATE INDEX ix_message_log_store ON message_log (id_store);
GO

INSERT INTO message_log (id_store, phone, direction, type, content, response_time_ms, source_node) VALUES
(1, '6641230001', 'inbound',  'whatsapp_text', 'Buenas tardes, ¿tienen tenis talla 27?',        NULL, 'messaging_node'),
(1, '6641230001', 'outbound', 'whatsapp_text', 'Sí, tenemos disponibilidad en la tienda Tijuana', 4200, 'messaging_node'),
(4, '6641230002', 'inbound',  'webchat',       '¿Cuánto tarda el envío a Monterrey?',           NULL, 'messaging_node'),
(4, '6641230002', 'outbound', 'webchat',       'El envío tarda de 3 a 5 días hábiles',          1500, 'messaging_node'),
(5, '6621230003', 'inbound',  'sms',           '¿Cuál es su horario los domingos?',             NULL, 'messaging_node');
GO

CREATE TABLE audit_log (
    id_audit     INT IDENTITY(1,1) PRIMARY KEY,
    id_store     INT NOT NULL, -- ref. logica a store (otro servicio: Sucursales)
    id_actor     INT NOT NULL, -- ref. logica polimorfica a owner o employee segun actor_role
    actor_role   VARCHAR(20) NOT NULL,
    action       VARCHAR(50) NOT NULL,
    entity       VARCHAR(50) NOT NULL,
    entity_id    INT NOT NULL,
    detail       NVARCHAR(MAX) NULL,
    -- tabla de bitácora: source_node y created_at, sin deleted
    source_node  VARCHAR(20) NOT NULL,
    created_at   DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT ck_audit_log_detail_json CHECK (detail IS NULL OR ISJSON(detail) = 1)
);
GO

CREATE INDEX ix_audit_log_store ON audit_log (id_store);
CREATE INDEX ix_audit_log_actor ON audit_log (id_actor);
GO

INSERT INTO audit_log (id_store, id_actor, actor_role, action, entity, entity_id, detail, source_node) VALUES
(1, 1, 'employee', 'update_inventory',     'inventory',       2, '{"old_stock": 10, "new_stock": 3}', 'messaging_node'),
(1, 1, 'owner',    'create_promotion',     'promotion',       3, '{"code": "BLACKFRIDAY"}',            'messaging_node'),
(2, 3, 'employee', 'create_order',         'customer_order',  2, '{"channel": "online"}',              'messaging_node'),
(5, 5, 'employee', 'process_payment',      'payment',         4, '{"method": "card"}',                 'messaging_node'),
(3, 1, 'owner',    'deactivate_employee',  'employee',        4, '{"reason": "licencia"}',             'messaging_node');
GO


-- =====================================================================
-- 9. Servicio Tienda en línea: reseñas y cuentas de clientes
-- =====================================================================

CREATE TABLE product_review (
    id_review          INT IDENTITY(1,1) PRIMARY KEY,
    id_product         INT NOT NULL,
    id_customer        INT NOT NULL,
    rating             TINYINT NOT NULL,
    title              NVARCHAR(80) NOT NULL,
    comment            NVARCHAR(1000) NULL,
    verified_purchase  BIT NOT NULL DEFAULT 0,
    -- columnas de consolidación
    source_node        VARCHAR(20) NOT NULL,
    created_at         DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    updated_at         DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted            BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_product_review_product FOREIGN KEY (id_product) REFERENCES product (id_product),
    CONSTRAINT fk_product_review_customer FOREIGN KEY (id_customer) REFERENCES customer (id_customer),
    CONSTRAINT ck_product_review_rating CHECK (rating BETWEEN 1 AND 5)
);
GO

-- Una reseña vigente por cliente y producto
CREATE UNIQUE INDEX uq_product_review_product_customer ON product_review (id_product, id_customer) WHERE deleted = 0;
CREATE INDEX ix_product_review_customer ON product_review (id_customer);
GO

INSERT INTO product_review (id_product, id_customer, rating, title, comment, verified_purchase, source_node) VALUES
(1, 1, 5, 'Excelente para correr', 'Seca rapidísimo y no se pega al cuerpo.', 0, 'store_node'),
(1, 2, 4, 'Buena calidad', 'La talla es exacta, la tela se siente resistente.', 0, 'store_node'),
(1, 4, 5, 'Mi favorita', 'Ya tengo tres colores.', 0, 'store_node'),
(2, 2, 4, 'Ligero', 'Ideal para el calor, los bolsillos son pequeños.', 0, 'store_node'),
(3, 3, 5, 'Comprime perfecto', 'No se transparenta al hacer sentadillas.', 0, 'store_node'),
(3, 5, 4, 'Muy cómodos', 'La cintura alta se mantiene en su lugar.', 0, 'store_node'),
(4, 1, 5, 'Calientita', 'Perfecta para las mañanas frías.', 0, 'store_node'),
(4, 4, 3, 'Bonita pero grande', 'Recomiendo pedir una talla menos.', 0, 'store_node'),
(5, 2, 5, 'Gran soporte', 'Los uso para crossfit y aguantan todo.', 0, 'store_node'),
(5, 3, 4, 'Cómodos desde el primer día', 'Un poco pesados para correr.', 0, 'store_node'),
(6, 1, 5, 'Amortiguación increíble', 'Hice mi primer medio maratón con ellos.', 0, 'store_node'),
(6, 4, 4, 'Buenos para distancia', 'Tardaron un par de días en ajustarse.', 0, 'store_node'),
(7, 5, 5, 'Combinan con todo', 'Los uso para la escuela y salir.', 0, 'store_node'),
(7, 2, 4, 'Estilo urbano', 'Muy cómodos, se ensucian fácil.', 0, 'store_node'),
(8, 4, 5, 'Saltan solos', 'Excelente agarre en la duela.', 0, 'store_node'),
(9, 3, 4, 'Para después del gym', 'Muy suaves, ideales para descansar los pies.', 0, 'store_node'),
(10, 3, 5, 'Soporte medio ideal', 'Para yoga y pesas va perfecto.', 0, 'store_node'),
(10, 5, 4, 'Bonito color', 'El rosa es tal cual la foto.', 0, 'store_node'),
(11, 4, 5, 'Como profesional', 'Transpira muy bien en los partidos.', 0, 'store_node'),
(12, 5, 4, 'Cómodo para diario', 'Los bolsillos con cierre son un plus.', 0, 'store_node'),
(12, 1, 5, 'Mi jogger favorito', 'Ni muy pegado ni muy holgado.', 0, 'store_node'),
(13, 2, 5, 'Aguanta la lluvia', 'Me salvó en una carrera con tormenta.', 0, 'store_node'),
(14, 3, 5, 'Sin costuras de verdad', 'No rozan nada en distancias largas.', 0, 'store_node'),
(15, 4, 4, 'Buena gorra', 'Se ajusta bien, el cierre es resistente.', 0, 'store_node'),
(16, 1, 5, 'Cabe todo', 'Llevo los tenis aparte y la ropa no huele.', 0, 'store_node'),
(16, 2, 4, 'Resistente', 'Las correas podrían ser más acolchadas.', 0, 'store_node'),
(17, 3, 5, 'Mantiene el frío', 'El agua sigue fría después de toda la tarde.', 0, 'store_node'),
(18, 5, 4, 'Calcetas cómodas', 'El talón acolchado se nota.', 0, 'store_node'),
(18, 1, 5, 'Ya no salen ampollas', 'Las uso para todos mis entrenamientos.', 0, 'store_node');
GO

-- Compra verificada: el cliente tiene un pedido vigente con ese producto
UPDATE r
SET r.verified_purchase = CASE WHEN EXISTS (
        SELECT 1
        FROM order_item AS oi
        JOIN customer_order AS co ON co.id_order = oi.id_order
        JOIN product_variant AS pv ON pv.id_variant = oi.id_variant
        WHERE co.id_customer = r.id_customer
          AND pv.id_product = r.id_product
          AND co.status NOT IN ('cancelled', 'returned')) THEN 1 ELSE 0 END
FROM product_review AS r;
GO

-- Credenciales separadas de los datos del cliente: password_hash guarda el
-- resultado de password_hash() de PHP (bcrypt), nunca la contraseña.
CREATE TABLE customer_credential (
    id_customer      INT NOT NULL PRIMARY KEY,
    password_hash    VARCHAR(255) NOT NULL,
    failed_attempts  TINYINT NOT NULL DEFAULT 0,
    terms_accepted_at DATETIME2(3) NULL,
    locked_until     DATETIME2(3) NULL,
    created_at       DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    updated_at       DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    CONSTRAINT fk_customer_credential_customer FOREIGN KEY (id_customer) REFERENCES customer (id_customer)
);
GO

-- Sesiones: se guarda el SHA-256 del token, así una copia de la tabla no
-- permite suplantar a nadie.
CREATE TABLE customer_session (
    token_hash   CHAR(64) NOT NULL PRIMARY KEY,
    id_customer  INT NOT NULL,
    created_at   DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    expires_at   DATETIME2(3) NOT NULL,
    revoked      BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_customer_session_customer FOREIGN KEY (id_customer) REFERENCES customer (id_customer)
);
GO

CREATE INDEX ix_customer_session_customer ON customer_session (id_customer);
GO

-- Cuentas de demostración (solo el hash bcrypt; las contraseñas las tiene el equipo):
-- 2 Prioritario, 5 Estándar, 6 Administrador.
INSERT INTO customer_credential (id_customer, password_hash) VALUES
(2, '$2y$10$LDms8HUAs32X0f71yGG97O/Pu.KMdrT60igDgm.22wExN7IQ8QWsa'),
(5, '$2y$10$HlWoiHfBwHaSKS8kof0jV.zVxSFarI9TVuFqnRlOOInEID2ozl98G'),
(6, '$2y$10$olHQB0hN51RjcJPq/vaXaO6ey7O1vlK21MFQc.sVUzMmfAjRz5m4e');
GO


-- =====================================================================
-- 10. Servicio Atención a clientes: comunicados y soporte
-- =====================================================================

-- Comunicados de la tienda. audience = 'priority' solo lo ven las cuentas
-- Prioritario y Administrador.
CREATE TABLE announcement (
    id_announcement  INT IDENTITY(1,1) PRIMARY KEY,
    audience         VARCHAR(8) NOT NULL,
    title            NVARCHAR(80) NOT NULL,
    body             NVARCHAR(600) NOT NULL,
    starts_on        DATE NOT NULL,
    ends_on          DATE NULL,
    id_author        INT NULL,
    -- columnas de consolidación
    source_node      VARCHAR(20) NOT NULL,
    created_at       DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    updated_at       DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted          BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_announcement_author FOREIGN KEY (id_author) REFERENCES customer (id_customer),
    CONSTRAINT ck_announcement_audience CHECK (audience IN ('all','priority')),
    CONSTRAINT ck_announcement_dates CHECK (ends_on IS NULL OR ends_on >= starts_on)
);
GO

CREATE INDEX ix_announcement_author ON announcement (id_author);
GO

INSERT INTO announcement (audience, title, body, starts_on, ends_on, id_author, source_node) VALUES
('priority', 'Preventa Black Friday', 'Como cliente Prioritario puedes comprar la colección de invierno desde hoy con tu descuento permanente, antes que nadie.', '2026-11-01', '2026-11-30', 6, 'web_node'),
('priority', 'Nueva línea de uniformes para equipos', 'Ya puedes pedir playeras y shorts de equipo en volumen. Escríbenos a soporte y te cotizamos en menos de 15 minutos.', '2026-10-15', NULL, 6, 'web_node'),
('all', 'Recoge en sucursal sin costo', 'Compra en línea y recoge el mismo día en Tijuana o CDMX.', '2026-01-01', NULL, 6, 'web_node');
GO

-- Tickets de soporte. La prioridad y el plazo de respuesta (respond_by) se
-- calculan con el tipo de cuenta del cliente al abrir el ticket.
CREATE TABLE support_ticket (
    id_ticket      INT IDENTITY(1,1) PRIMARY KEY,
    id_customer    INT NOT NULL,
    id_order       INT NULL,
    subject        NVARCHAR(100) NOT NULL,
    message        NVARCHAR(1000) NOT NULL,
    priority       VARCHAR(6) NOT NULL,
    status         VARCHAR(8) NOT NULL CONSTRAINT df_support_ticket_status DEFAULT 'open',
    response       NVARCHAR(1000) NULL,
    id_responder   INT NULL,
    respond_by     DATETIME2(3) NOT NULL,
    answered_at    DATETIME2(3) NULL,
    -- columnas de consolidación
    source_node    VARCHAR(20) NOT NULL,
    created_at     DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    updated_at     DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted        BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_support_ticket_customer FOREIGN KEY (id_customer) REFERENCES customer (id_customer),
    CONSTRAINT fk_support_ticket_order FOREIGN KEY (id_order) REFERENCES customer_order (id_order),
    CONSTRAINT fk_support_ticket_responder FOREIGN KEY (id_responder) REFERENCES customer (id_customer),
    CONSTRAINT ck_support_ticket_priority CHECK (priority IN ('normal','high')),
    CONSTRAINT ck_support_ticket_status CHECK (status IN ('open','answered','closed')),
    CONSTRAINT ck_support_ticket_answer CHECK (status = 'open' OR response IS NOT NULL)
);
GO

CREATE INDEX ix_support_ticket_queue ON support_ticket (status, priority, respond_by);
CREATE INDEX ix_support_ticket_customer ON support_ticket (id_customer);
CREATE INDEX ix_support_ticket_order ON support_ticket (id_order);
CREATE INDEX ix_support_ticket_responder ON support_ticket (id_responder);
GO

INSERT INTO support_ticket (id_customer, id_order, subject, message, priority, status, response, id_responder, respond_by, answered_at, source_node) VALUES
(2, 2, 'Factura del pedido', 'Necesito la factura del pedido 2 a nombre del club deportivo.', 'high', 'answered',
 'Listo, la factura se envió a tu correo. Gracias por tu confianza.', 6,
 DATEADD(MINUTE, 15, SYSUTCDATETIME()), SYSUTCDATETIME(), 'web_node'),
(5, 5, 'Reembolso de pedido cancelado', '¿En cuántos días se refleja el reembolso del pedido 5?', 'normal', 'open', NULL, NULL,
 DATEADD(MINUTE, 1440, SYSUTCDATETIME()), NULL, 'web_node');
GO


-- =====================================================================
-- 11. Servicio Seguimiento: historial de pedidos, correos y recuperación
--     de contraseña
-- =====================================================================

-- Cada cambio de estado de un pedido queda registrado. Lo llena el trigger
-- trg_customer_order_status_history; quien hizo el cambio se toma de
-- SESSION_CONTEXT(N'id_customer'), que la API fija en cada petición.
CREATE TABLE order_status_history (
    id_history     INT IDENTITY(1,1) PRIMARY KEY,
    id_order       INT NOT NULL,
    from_status    VARCHAR(9) NULL,
    to_status      VARCHAR(9) NOT NULL,
    changed_at     DATETIME2(3) NOT NULL CONSTRAINT df_order_status_history_changed_at DEFAULT SYSUTCDATETIME(),
    id_changed_by  INT NULL,
    source_node    VARCHAR(20) NOT NULL,
    CONSTRAINT fk_order_status_history_order FOREIGN KEY (id_order) REFERENCES customer_order (id_order),
    CONSTRAINT fk_order_status_history_customer FOREIGN KEY (id_changed_by) REFERENCES customer (id_customer),
    CONSTRAINT ck_order_status_history_to CHECK (to_status IN ('pending','paid','shipped','delivered','cancelled','returned')),
    CONSTRAINT ck_order_status_history_from CHECK (from_status IS NULL OR from_status IN ('pending','paid','shipped','delivered','cancelled','returned'))
);
GO

CREATE INDEX ix_order_status_history_order ON order_status_history (id_order, changed_at);
CREATE INDEX ix_order_status_history_changed_by ON order_status_history (id_changed_by);
GO

-- Historial de los pedidos que ya existen: se reconstruyen los pasos que
-- llevaron a cada pedido a su estado actual.
INSERT INTO order_status_history (id_order, from_status, to_status, changed_at, id_changed_by, source_node)
SELECT co.id_order, paso.from_status, paso.to_status,
       CASE WHEN DATEADD(MINUTE, paso.minutes, co.order_date) > SYSUTCDATETIME()
            THEN SYSUTCDATETIME() ELSE DATEADD(MINUTE, paso.minutes, co.order_date) END,
       CASE WHEN paso.to_status = 'pending' THEN co.id_customer END,
       co.source_node
FROM customer_order AS co
CROSS APPLY (VALUES
    (1, CAST(NULL AS VARCHAR(9)), 'pending',   0),
    (2, 'pending',                'paid',      CASE WHEN co.delivery_type = 'pickup' THEN 5 ELSE 20 END),
    (3, 'paid',                   'shipped',   1440),
    (4, CASE WHEN co.delivery_type = 'pickup' THEN 'paid' ELSE 'shipped' END,
                                  'delivered', CASE WHEN co.delivery_type = 'pickup' THEN 15 ELSE 4320 END),
    (5, 'pending',                'cancelled', 90)
) AS paso (step, from_status, to_status, minutes)
WHERE (paso.step = 1)
   OR (paso.step = 2 AND co.status IN ('paid', 'shipped', 'delivered', 'returned'))
   OR (paso.step = 3 AND co.delivery_type = 'shipping' AND co.status IN ('shipped', 'delivered', 'returned'))
   OR (paso.step = 4 AND co.status IN ('delivered', 'returned'))
   OR (paso.step = 5 AND co.status = 'cancelled');
GO

CREATE TRIGGER trg_customer_order_status_history ON customer_order AFTER INSERT, UPDATE AS
BEGIN
    SET NOCOUNT ON;
    INSERT INTO order_status_history (id_order, from_status, to_status, id_changed_by, source_node)
    SELECT i.id_order, d.status, i.status,
           COALESCE(TRY_CAST(SESSION_CONTEXT(N'id_customer') AS INT),
                    CASE WHEN d.id_order IS NULL THEN i.id_customer END),
           i.source_node
    FROM inserted AS i
    LEFT JOIN deleted AS d ON d.id_order = i.id_order
    WHERE d.id_order IS NULL OR d.status <> i.status;
END;
GO

-- Bandeja de correos simulada: la tienda no tiene servidor SMTP, así que los
-- correos (confirmación de pedido, cambio de estado, recuperación de
-- contraseña) se guardan aquí y el administrador los consulta en el panel.
CREATE TABLE email_outbox (
    id_email     INT IDENTITY(1,1) PRIMARY KEY,
    id_customer  INT NULL,
    id_order     INT NULL,
    kind         VARCHAR(16) NOT NULL,
    to_email     VARCHAR(100) NOT NULL,
    subject      NVARCHAR(120) NOT NULL,
    body         NVARCHAR(2000) NOT NULL,
    link         VARCHAR(300) NULL,
    created_at   DATETIME2(3) NOT NULL CONSTRAINT df_email_outbox_created_at DEFAULT SYSUTCDATETIME(),
    source_node  VARCHAR(20) NOT NULL,
    CONSTRAINT fk_email_outbox_customer FOREIGN KEY (id_customer) REFERENCES customer (id_customer),
    CONSTRAINT fk_email_outbox_order FOREIGN KEY (id_order) REFERENCES customer_order (id_order),
    CONSTRAINT ck_email_outbox_kind CHECK (kind IN ('welcome','order_created','order_status','password_reset'))
);
GO

CREATE INDEX ix_email_outbox_created ON email_outbox (created_at DESC);
CREATE INDEX ix_email_outbox_customer ON email_outbox (id_customer);
CREATE INDEX ix_email_outbox_order ON email_outbox (id_order);
GO

-- Enlaces para restablecer la contraseña: igual que las sesiones, solo se
-- guarda el SHA-256 del token. Vencen a los 30 minutos y sirven una sola vez.
CREATE TABLE password_reset (
    token_hash   CHAR(64) NOT NULL PRIMARY KEY,
    id_customer  INT NOT NULL,
    created_at   DATETIME2(3) NOT NULL CONSTRAINT df_password_reset_created_at DEFAULT SYSUTCDATETIME(),
    expires_at   DATETIME2(3) NOT NULL,
    used_at      DATETIME2(3) NULL,
    CONSTRAINT fk_password_reset_customer FOREIGN KEY (id_customer) REFERENCES customer (id_customer)
);
GO

CREATE INDEX ix_password_reset_customer ON password_reset (id_customer, created_at);
GO


-- =====================================================================
-- 12. Servicio Mercado: precios de la competencia
-- =====================================================================

-- Precios de lista de productos equivalentes de otras marcas, consultados
-- en sus tiendas oficiales o en tiendas departamentales. Los ven las cuentas
-- Prioritario y Administrador; el administrador los actualiza desde el panel.
CREATE TABLE competitor (
    id_competitor  TINYINT NOT NULL PRIMARY KEY,
    name           NVARCHAR(40) NOT NULL,
    website        VARCHAR(100) NULL,
    CONSTRAINT uq_competitor_name UNIQUE (name)
);
GO

INSERT INTO competitor (id_competitor, name, website) VALUES
(1, 'Nike',       'https://www.nike.com/mx'),
(2, 'Adidas',     'https://www.adidas.mx'),
(3, 'Puma',       'https://mx.puma.com'),
(4, 'Ice Shaker', NULL);
GO

CREATE TABLE market_price (
    id_market_price     INT IDENTITY(1,1) PRIMARY KEY,
    id_product          INT NOT NULL,
    id_competitor       TINYINT NOT NULL,
    competitor_product  NVARCHAR(100) NOT NULL,
    retailer            NVARCHAR(40) NOT NULL,
    price               DECIMAL(10,2) NOT NULL,
    source_url          VARCHAR(300) NULL,
    captured_on         DATE NOT NULL,
    -- columnas de consolidación
    source_node         VARCHAR(20) NOT NULL,
    updated_at          DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted             BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_market_price_product FOREIGN KEY (id_product) REFERENCES product (id_product),
    CONSTRAINT fk_market_price_competitor FOREIGN KEY (id_competitor) REFERENCES competitor (id_competitor),
    CONSTRAINT ck_market_price_price CHECK (price > 0)
);
GO

CREATE INDEX ix_market_price_product ON market_price (id_product) WHERE deleted = 0;
CREATE INDEX ix_market_price_competitor ON market_price (id_competitor);
GO

-- Precios de lista consultados el 19 de noviembre de 2026.
INSERT INTO market_price (id_product, id_competitor, competitor_product, retailer, price, source_url, captured_on, source_node) VALUES
( 1, 1, 'Playera Nike Dri-FIT para hombre',              'Nike.com',      799.00, 'https://www.nike.com/mx/t/playera-dri-fit-BgEkyhHy', '2026-11-19', 'market_node'),
( 1, 2, 'Playera Own the Run 3 Rayas',                   'adidas.mx',     899.00, 'https://www.adidas.mx/playera-own-the-run-3-rayas/IQ3861.html', '2026-11-19', 'market_node'),
( 1, 3, 'Playera de running Velocity Tri-Blend',         'puma.com',      749.00, 'https://mx.puma.com/mx/en/pd/playera-de-running-velocity-tri-blend-para-hombre/526603.html', '2026-11-19', 'market_node'),
( 2, 1, 'Shorts de correr Nike Challenger 18 cm',        'Nike.com',      849.00, 'https://www.nike.com/mx/w/hombres-entrenamiento-running-shorts-37v7jz38fphz4llhmznik1', '2026-11-19', 'market_node'),
( 3, 1, 'Leggings de tiro alto 7/8 Nike One',            'Nike.com',     1299.00, 'https://www.nike.com/mx/t/leggings-de-tiro-alto-de-7-8-one-H3WHa51W', '2026-11-19', 'market_node'),
( 4, 1, 'Sudadera con gorro Nike Club Fleece (hombre)',  'Nike.com',     1549.00, 'https://www.nike.com/mx/t/sudadera-con-gorro-sin-cierre-de-tejido-fleece-club-p5Zpxr', '2026-11-19', 'market_node'),
( 4, 1, 'Sudadera con gorro Sportswear Club Fleece (mujer)', 'Nike.com', 1299.00, 'https://www.nike.com/mx/t/sudadera-con-gorro-sin-cierre-sportswear-club-fleece-mgDrNz', '2026-11-19', 'market_node'),
( 5, 2, 'Tenis de entrenamiento Dropset 4',              'Liverpool',    2899.00, 'https://www.liverpool.com.mx/tienda?s=dropset', '2026-11-19', 'market_node'),
( 5, 2, 'Tenis Dropset Control Trainer',                 'Liverpool',    1999.00, 'https://www.liverpool.com.mx/tienda/pdp/tenis-para-entrenamiento-dropset-control-trainer-m-de-hombre/1194027417', '2026-11-19', 'market_node'),
( 6, 1, 'Tenis de running Nike Pegasus 41',              'Nike.com',     2999.00, 'https://www.nike.com/mx/w/negro-pegasus-37v7jz8mfrfz8nexhz90poy', '2026-11-19', 'market_node'),
( 7, 1, 'Tenis Nike Air Force 1 ''07',                   'Nike.com',     2599.00, 'https://www.nike.com/mx/t/air-force-1-07-Xm80HDys', '2026-11-19', 'market_node'),
( 8, 1, 'Tenis de básquetbol Giannis Immortality 5',     'Nike.com',     2399.00, 'https://www.nike.com/mx/t/tenis-de-basquetbol-giannis-immortality-5-yZBAd3eE', '2026-11-19', 'market_node'),
( 8, 3, 'Tenis de básquetbol Court Pro',                 'Liverpool',    1899.00, 'https://www.liverpool.com.mx/tienda/basquetbol/cat870625', '2026-11-19', 'market_node'),
( 8, 2, 'Tenis de básquetbol para hombre',               'Liverpool',    2099.00, 'https://www.liverpool.com.mx/tienda/basquetbol/cat870625', '2026-11-19', 'market_node'),
( 9, 1, 'Sandalias Nike Victori One',                    'Liverpool',     899.00, 'https://www.liverpool.com.mx/tienda/sandalias/catst40855933', '2026-11-19', 'market_node'),
( 9, 2, 'Sandalias Adilette Aqua',                       'Liverpool',     599.00, 'https://www.liverpool.com.mx/tienda/sandalias-deportivas/catst695239117', '2026-11-19', 'market_node'),
( 9, 3, 'Sandalias Leadcat 2.0',                         'puma.com',      699.00, 'https://mx.puma.com/mx/en/pd/leadcat-2.0-sandals/384139.html', '2026-11-19', 'market_node'),
(10, 1, 'Bra deportivo Nike Swoosh Medium Support',      'Nike.com',      899.00, 'https://www.nike.com/mx/t/bra-deportivo-con-almohadillas-swoosh-medium-support-z3c3B5', '2026-11-19', 'market_node'),
(11, 1, 'Playera de fútbol Nike Academy+ Dri-FIT',       'Nike.com',      899.00, 'https://www.nike.com/mx/w/transpirabilidad-playeras-y-tops-4u644z9om13', '2026-11-19', 'market_node'),
(12, 1, 'Pants Nike Sportswear Club Fleece',             'Mercado Libre',1249.00, 'https://listado.mercadolibre.com.mx/pants-nike-negro', '2026-11-19', 'market_node'),
(13, 1, 'Chamarra con gorro Nike Sportswear Windrunner', 'Nike.com',     2099.00, 'https://www.nike.com/mx/w/poliester-reciclado-chamarras-chalecos-abrigos-26eiez346rrz50r7yzah84x', '2026-11-19', 'market_node'),
(13, 1, 'Chamarra de correr Nike Miler Repel',           'Nike.com',     1799.00, 'https://www.nike.com/mx/w/poliester-reciclado-chamarras-chalecos-abrigos-26eiez346rrz50r7yzah84x', '2026-11-19', 'market_node'),
(14, 1, 'Leggings Nike One Seamless Front',              'Nike.com',     1099.00, 'https://www.nike.com/mx/t/leggings-de-tiro-alto-de-largo-completo-one-seamless-front-AKgvToF5', '2026-11-19', 'market_node'),
(15, 2, 'Gorra Béisbol 3 Franjas',                       'adidas.mx',     429.00, 'https://www.adidas.mx/a_rayas-casual-gorras-azul', '2026-11-19', 'market_node'),
(15, 2, 'Gorra de béisbol ligera',                       'adidas.mx',     349.00, 'https://www.adidas.mx/liviano-materiales_reciclados-gorras-verde', '2026-11-19', 'market_node'),
(16, 2, 'Mochila Classic Foundation',                    'Mercado Libre', 699.00, 'https://listado.mercadolibre.com.mx/mochilas-adidas-hombre', '2026-11-19', 'market_node'),
(17, 4, 'Termo de acero inoxidable 591 ml',              'Liverpool',     299.00, 'https://www.liverpool.com.mx/tienda/TermosyBotellas/catst55395607', '2026-11-19', 'market_node'),
(17, 2, 'Botella metálica 0.6 L',                        'Liverpool',     599.00, 'https://www.liverpool.com.mx/tienda/TermosyBotellas/catst55395607', '2026-11-19', 'market_node'),
(18, 1, 'Calcetas Nike Everyday Cushioned (3 pares)',    'Nike.com',      449.00, 'https://www.nike.com/mx/t/calcetas-de-entrenamiento-3-pares-everyday-cushioned-znMjdV', '2026-11-19', 'market_node'),
(18, 2, 'Set de calcetas comfort (3 pares)',             'Liverpool',     369.00, 'https://www.liverpool.com.mx/tienda/pdp/set-de-calceta-para-entrenamiento-unisex-3-pares/99983636895', '2026-11-19', 'market_node');
GO


-- =====================================================================
-- 13. Servicio Entregas y regalos: códigos postales y empaque de regalo
-- =====================================================================

-- Catálogo de códigos postales (Correos de México / SEPOMEX) de las zonas
-- donde están las sucursales: Tijuana y las alcaldías Cuauhtémoc y Benito
-- Juárez de la Ciudad de México. Al escribir el código postal en el pago,
-- la página llena el estado y la ciudad y sugiere las colonias.
CREATE TABLE postal_code (
    id_postal_code   INT IDENTITY(1,1) PRIMARY KEY,
    zip_code         CHAR(5) NOT NULL,
    settlement       NVARCHAR(80) NOT NULL,
    settlement_type  NVARCHAR(30) NOT NULL,
    municipality     NVARCHAR(60) NOT NULL,
    city             NVARCHAR(60) NOT NULL,
    id_state         TINYINT NOT NULL,
    CONSTRAINT fk_postal_code_state FOREIGN KEY (id_state) REFERENCES state (id_state),
    CONSTRAINT ck_postal_code_zip CHECK (zip_code LIKE '[0-9][0-9][0-9][0-9][0-9]')
);
GO

CREATE INDEX ix_postal_code_zip ON postal_code (zip_code);
CREATE INDEX ix_postal_code_state ON postal_code (id_state);
GO

INSERT INTO postal_code (zip_code, settlement, settlement_type, municipality, city, id_state) VALUES
('22000', 'Zona Centro', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22000', 'Zona Este', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22000', 'Zona Norte', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22010', 'Empleados Federales', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22010', 'Cuauhtémoc', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22010', 'Zona Urbana Río Tijuana', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22014', 'Escolar Agua Caliente', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22014', 'Agua Caliente Sección Pinos', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22014', 'Aviación', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22015', 'Marrón', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22015', 'Revolución', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22015', 'Plan de Barranquitas', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22020', 'Chapultepec', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22020', 'Hipódromo', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22020', 'Neidhart', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22020', 'Rincón Colonial Chapultepec', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22020', 'Campo de Golf', 'Equipamiento', 'Tijuana', 'Tijuana', 1),
('22020', 'Chapultepec Este', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22020', 'Chapultepec Decima Sección', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22024', 'Agua Caliente', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22024', 'Puerta de Hierro', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22024', 'Hipódromo Agua Caliente', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22024', 'Lomas de Agua Caliente', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22025', 'Lomas de Agua Caliente 1ra. Sección', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22025', 'Rancho el Grande', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22025', 'Conjunto Residencial Cataviña', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22025', 'Jardines de Chapultepec', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22025', 'Privada Catalana', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22025', 'San Jerónimo', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22025', 'Colinas de Chapultepec', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22030', 'Burócrata Hipódromo', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22030', 'Colinas de Agua Caliente', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22030', 'Lomas Hipódromo', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22030', 'López Mateos', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22030', 'Hacienda las Palomas', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22030', 'La Remosa', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22030', 'Hacienda Agua Caliente', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22034', 'El Bosque', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22034', 'Herradura', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22034', 'Villa Lomas', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22034', 'Lomas de Agua Caliente 5ta. Sección', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22034', 'Lomas de Agua Caliente 6ta. Sección (Lomas Altas)', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22034', 'Jardines de Chapultepec S-E', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22034', 'Planetario', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22034', 'Cañón Oasis', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22034', 'Pueblo Bonito', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22034', 'Vista Dorada', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22035', 'Cañón de La Pedrera', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22035', 'Herradura Sur', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22035', 'Cañón de La Pedrera Este', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22035', 'Cañón Primavera', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22040', 'Juárez', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22040', 'Madero (Cacho)', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22044', 'América', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22044', 'Dávila', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22044', 'Calette', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22044', 'Gabilondo', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22045', 'Cubillas', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22045', 'Cubillas Sur', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22045', 'Chula Vista', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22045', 'Lomas Doctores (Chapultepec Doctores)', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22046', 'Madero Sur', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22046', 'Guadalajara', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22046', 'Monterrey', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22046', 'Cumbres de Juárez', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22046', 'Laderas de Monterrey', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22046', 'Rincón Toscano', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22050', 'Castillo', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22050', 'Alemán', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22054', 'Altamira', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22054', 'Roma', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22054', 'Linda Vista', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22055', 'Altamira Sur', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22055', 'Azcona', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22055', 'Independencia', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22055', 'Guerrero', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22056', 'Hidalgo', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22056', 'México', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22056', 'Morelos', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22100', 'Anexa 20 de Noviembre', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22100', '20 de Noviembre', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22104', 'Los Españoles', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22104', 'El Pedregal', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22104', 'Alfonso Corona del Rosal', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22104', 'El Pedregal Oeste', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22104', 'Los Santos', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22104', 'San José del Alto', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22105', 'El Prado', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22105', 'Anáhuac', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22105', 'Ceceña', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22105', 'Dimenstein', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22105', 'José Sandoval', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22105', 'La Mesa', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22105', 'López', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22105', 'Los Ángeles', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22105', 'Santa Cruz', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22105', 'Las Lilas', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22105', 'Moreno', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22105', 'El Prado Este', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22105', 'FOVISSSTE Los Volcanes', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22105', 'La Mesa Sur', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22105', 'López Oeste', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Santa Rosa', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Cortez', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Alcalá', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Alicia Carrillo', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Angélica', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'El Paraíso', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Gerónimo Meza', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'La Escondida', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Las Palmas', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'López Lucio', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Luz Juárez', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Reynoso', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Jardines de San Carlos', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Camichin', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Contreras', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Gertrudis Green', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Guillen', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Leos Montoya', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Magaña', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Sonoita', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Electricistas', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22106', 'Contreras Oeste', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Gerónimo Meza Este', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Quinta Alta', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Reynoso Parcela 162', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22106', 'Electricistas', 'Unidad habitacional', 'Tijuana', 'Tijuana', 1),
('22106', 'San Carlos', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22110', 'Residencial Villaflores', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22110', 'Buenaventura', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22110', 'Los Pirules', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22110', 'Chapultepec Alamar', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22110', 'Los Álamos', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22114', 'Chihuahua La Mesa', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22114', 'Fortín de las Flores', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22114', 'Santa Elena', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22114', 'Yamille', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22114', 'San Antonio', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22114', 'Fortín de las Flores Oeste', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22114', 'INFONAVIT La Mesa', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22114', 'La Ciénega Poniente', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22114', 'San Antonio Oeste', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22114', 'Vaim', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22114', 'Río Tijuana 2da. Etapa', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22115', 'Moreno 2da. Sección', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22115', 'Las Brisas', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22115', 'Benton', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22115', 'Guadalajara (La Mesa)', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22115', 'Las Vegas', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22115', 'Ramírez', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22115', 'Castro', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22115', 'Las Huertas 3ra. Sección', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22115', 'La Joya', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22115', 'Gas y Anexas', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22115', 'La Joya Este', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22115', 'Las Brisas Norte', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22115', 'Los Olivos', 'Zona industrial', 'Tijuana', 'Tijuana', 1),
('22116', 'Jalisco', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22116', 'Balcón Las Huertas', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22116', 'Huertas 1ra. Sección', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22116', 'Huertas 2da. Sección', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22116', 'Ramos', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22116', 'Maurilio Magallón', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22116', 'Plan de Iguala', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22116', 'Cañón el Salado', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22116', 'Rubio', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22116', 'Urías', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22116', 'El Valle', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22116', 'Lomas Conjunto Residencial', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22116', 'Pinos del Agüero', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22116', 'Ramos Sur', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22116', 'Hacienda Acueducto', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22116', 'Jardín de las Bugambilias', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22117', 'Anexa Durango', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22117', 'Chapultepec California', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22117', 'Durango', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22117', 'La Esmeralda', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22117', 'Las Fuentes', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22117', 'Las Huertas 4ta. Sección', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22117', 'Los Árboles', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22117', 'Santa Fe', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22117', 'Veracruz', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22117', 'Anexa Veracruz', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22117', 'La Villa', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22117', 'Emperadores', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22117', 'Guanajuato', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22117', 'Anexa Santa Fe', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22117', 'Lomas Las Huertas', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22120', 'Arboledas de La Mesa', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22120', 'Constitución del 17', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22120', '18 de Marzo', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22120', 'La Ciénega', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22120', 'Zermeño (Mérida)', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22120', 'Niños Héroes (La Mesa)', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22120', 'Arboledas', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22120', 'La Ciénega Sur', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22120', 'Niños Héroes Este', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22120', 'Vivienda Magisterial 37', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22120', 'Los Pinos (Limón)', 'Zona industrial', 'Tijuana', 'Tijuana', 1),
('22120', 'División del Norte', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22123', 'Arboledas', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22123', 'García', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22123', 'Los Venados', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22123', 'Los Venados Oeste', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22123', 'Jardines de Las Arboledas', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22123', 'El Olivar', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22123', 'Camino Viejo', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22123', 'Villa de Alcázar', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22124', 'Tona', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22124', 'Presa Rodriguez', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22124', 'Terrazas de La Presa', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22125', 'Colinas de la Presa Sección Montebello', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22125', 'Senda Residencial', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22125', 'Lomas de La Presa', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22125', 'Los Reyes', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22125', 'Villas de Baja California', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22125', 'Los Naranjos', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22125', 'Bosques de La Presa', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22125', 'Colinas de la Presa', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22126', 'Cañón Palmas', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22126', 'Colinas de La Mesa', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22126', 'Jardines de La Mesa', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22126', 'México Lindo', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22126', '3 de Octubre', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22126', 'El Ranchito', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22126', 'Villa Urrutia', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22126', 'Residencial Alameda', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22127', 'Castro Green', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22127', 'Las Huertas 5ta. Sección', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22127', 'Luna Park', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22127', 'Orizaba', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22127', 'Baja California', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22127', 'Campos', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22127', 'Pinos de Narez', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22127', 'Villa Floresta', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22127', 'Colinas de La Cruz', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22127', 'INFONAVIT Lomas Verdes', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22127', 'Loma Dorada Campos', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22127', 'Martínez', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22127', 'Anexa Loma Dorada', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22160', 'Generación 2000', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22160', 'Granjas Buenos Aires Sección La Palma', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22163', 'Puerta Plata', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22163', 'Tomas Alva Edison', 'Zona industrial', 'Tijuana', 'Tijuana', 1),
('22163', 'La Ilusión', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22163', 'Hacienda Las Delicias', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22163', 'Hacienda los Venados', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22164', 'Palma Real', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22164', 'Los Valles', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22165', 'Natura Sección Arboledas', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22165', 'Natura Sección Vistas del Sol', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22165', 'Hacienda las Delicias III', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22165', 'Natura Sección Amanecer', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22165', 'Zona Cerril General', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22165', 'Natura Sección Montes', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22165', 'Natura Sección Bosques', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22170', 'Rancho Santa Cruz', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22170', 'Hacienda los Laureles', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22170', 'Santa Cruz', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22170', 'Granjas Buenos Aires', 'Ranchería', 'Tijuana', 'Tijuana', 1),
('22170', 'Paseos del Prado', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22170', 'Vista Horizonte', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22170', 'Emiliano Zapata', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22170', 'San Luis', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22170', 'Cañón del Sainz', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22170', 'Valle Dorado', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22170', 'Colinas San Rafael', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22170', 'Villa del Prado', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22170', 'Villa del Prado Segunda Sección', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22170', 'Villa del Álamo', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22180', 'INFONAVIT Latinos', 'Unidad habitacional', 'Tijuana', 'Tijuana', 1),
('22180', 'Lomas de La Amistad', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22180', 'Ampliación Reforma', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22180', 'INFONAVIT Cachanillas', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22180', 'Parque Industrial La Mesa', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22190', 'Camino Verde (Cañada Verde)', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22204', 'Alta Vista', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22204', 'Altiplano', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22200', 'Buenos Aires Norte', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22200', 'Ampliación Loma Bonita', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22224', 'Agraristas', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22224', 'Azteca', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('22226', 'Alba Roja', 'Unidad habitacional', 'Tijuana', 'Tijuana', 1),
('22404', 'Aeropuerto', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22410', 'Alfonso Garzón', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22415', 'Buena Vista', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22420', 'Altabrisa', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22465', 'Alamar', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22510', 'Alberto Bustamante', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22515', 'Albatros', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22560', 'Baja Malibú', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22566', 'Brisas del Mar', 'Colonia', 'Tijuana', 'Tijuana', 1),
('22607', 'Brisa Marina', 'Fraccionamiento', 'Tijuana', 'Tijuana', 1),
('06000', 'Centro (Área 1)', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06010', 'Centro (Área 2)', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06020', 'Centro (Área 3)', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06030', 'Tabacalera', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06040', 'Centro (Área 4)', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06050', 'Centro (Área 5)', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06060', 'Centro (Área 6)', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06070', 'Centro (Área 7)', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06080', 'Centro (Área 8)', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06090', 'Centro (Área 9)', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06100', 'Hipódromo', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06140', 'Condesa', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06170', 'Hipódromo Condesa', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06200', 'Morelos', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06220', 'Peralvillo', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06240', 'Valle Gómez', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06250', 'Ex-Hipódromo de Peralvillo', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06270', 'Maza', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06280', 'Felipe Pescador', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06300', 'Guerrero', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06350', 'Buenavista', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06400', 'Santa María la Ribera', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06430', 'Santa María Insurgentes', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06450', 'Atlampa', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06470', 'San Rafael', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06500', 'Cuauhtémoc', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06600', 'Juárez', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06700', 'Roma Norte', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06720', 'Doctores', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06760', 'Roma Sur', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06780', 'Buenos Aires', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06800', 'Obrera', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06820', 'Tránsito', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06840', 'Esperanza', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06850', 'Asturias', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06860', 'Vista Alegre', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06870', 'Paulino Navarro', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06880', 'Algarin', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06890', 'Ampliación Asturias', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06900', 'Nonoalco Tlatelolco', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('06920', 'San Simón Tolnáhuac', 'Colonia', 'Cuauhtémoc', 'Ciudad de México', 2),
('03000', 'Piedad Narvarte', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03010', 'Atenor Salas', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03020', 'Narvarte Poniente', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03023', 'Narvarte Oriente', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03100', 'Del Valle Centro', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03100', 'Insurgentes San Borja', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03103', 'Del Valle Norte', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03104', 'Del Valle Sur', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03200', 'Tlacoquemécatl', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03230', 'Actipan', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03240', 'Acacias', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03300', 'Portales Sur', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03303', 'Portales Norte', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03310', 'Santa Cruz Atoyac', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03320', 'Residencial Emperadores', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03330', 'Xoco', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03340', 'General Pedro María Anaya', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03400', 'Álamos', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03410', 'Postal', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03420', 'Miguel Alemán', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03430', 'Josefa Ortiz de Domínguez', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03440', 'Niños Héroes', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03500', 'Nativitas', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03510', 'Moderna', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03520', 'Iztaccihuatl', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03530', 'Villa de Cortés', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03540', 'Del Carmen', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03550', 'Zacahuitzco', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03560', 'Albert', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03570', 'Portales Oriente', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03580', 'Miravalle', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03590', 'Ermita', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03600', 'Vértiz Narvarte', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03610', 'Américas Unidas', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03620', 'Periodista', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03630', 'Independencia', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03640', 'Del Lago', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03650', 'Letrán Valle', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03660', 'San Simón Ticumac', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03700', 'Santa María Nonoalco', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03710', 'Ciudad de los Deportes', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03720', 'Nochebuena', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03730', 'San Juan', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03740', 'Extremadura Insurgentes', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03800', 'San Pedro de los Pinos', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03810', 'Nápoles', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03820', '8 de Agosto', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03840', 'Ampliación Nápoles', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03900', 'San José Insurgentes', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03910', 'Mixcoac', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03920', 'Insurgentes Mixcoac', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03930', 'Merced Gómez', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2),
('03940', 'Crédito Constructor', 'Colonia', 'Benito Juárez', 'Ciudad de México', 2);
GO

-- Empaque de regalo: el pedido cobra gift_cost ($30) y aquí se guarda la
-- ocasión, el color del papel y, si el cliente quiere, para quién es y una
-- dedicatoria. El regalo llega al domicilio de envío o se recoge en sucursal.
CREATE TABLE order_gift (
    id_order        INT NOT NULL PRIMARY KEY,
    occasion        VARCHAR(10) NOT NULL,
    wrap_color      VARCHAR(8) NOT NULL,
    recipient_name  NVARCHAR(60) NULL,
    message         NVARCHAR(200) NULL,
    source_node     VARCHAR(20) NOT NULL,
    created_at      DATETIME2(3) NOT NULL CONSTRAINT df_order_gift_created_at DEFAULT SYSUTCDATETIME(),
    CONSTRAINT fk_order_gift_order FOREIGN KEY (id_order) REFERENCES customer_order (id_order),
    CONSTRAINT ck_order_gift_occasion CHECK (occasion IN ('birthday','friendship','christmas')),
    CONSTRAINT ck_order_gift_color CHECK (wrap_color IN ('red','green','blue','pink','gold','silver','purple','black'))
);
GO


-- =====================================================================
-- 14. VISITAS, BÚSQUEDAS Y DESCARGAS DE LA APP
-- El navegador genera un identificador aleatorio (visitor_id) y lo guarda
-- en localStorage. No se guarda la IP ni ningún dato personal.
--   channel  'web' = página en el navegador, 'app' = aplicación instalada (PWA)
--   device   'movil' | 'tableta' | 'escritorio'
-- Una visita dura mientras el visitante siga enviando señales; después de
-- 30 minutos sin actividad, la siguiente señal abre una visita nueva.
-- Un visitante está "conectado" si su última señal tiene menos de 90 s.
-- Son tablas del nodo central: la analítica es global, no se fragmenta.
-- =====================================================================

CREATE TABLE site_visit (
    id_visit      BIGINT IDENTITY(1,1) PRIMARY KEY,
    visitor_id    CHAR(36)      NOT NULL,
    channel       VARCHAR(3)    NOT NULL,
    device        VARCHAR(10)   NOT NULL,
    id_customer   INT           NULL,
    landing_path  NVARCHAR(120) NULL,
    current_path  NVARCHAR(120) NULL,
    page_views    INT           NOT NULL CONSTRAINT df_site_visit_page_views DEFAULT 1,
    started_at    DATETIME2(3)  NOT NULL CONSTRAINT df_site_visit_started_at DEFAULT SYSUTCDATETIME(),
    last_seen     DATETIME2(3)  NOT NULL CONSTRAINT df_site_visit_last_seen DEFAULT SYSUTCDATETIME(),
    CONSTRAINT fk_site_visit_customer FOREIGN KEY (id_customer) REFERENCES customer (id_customer),
    CONSTRAINT ck_site_visit_channel CHECK (channel IN ('web','app')),
    CONSTRAINT ck_site_visit_device CHECK (device IN ('movil','tableta','escritorio')),
    CONSTRAINT ck_site_visit_dates CHECK (last_seen >= started_at)
);
GO

CREATE INDEX ix_site_visit_visitor ON site_visit (visitor_id, last_seen);
CREATE INDEX ix_site_visit_started ON site_visit (started_at) INCLUDE (channel, device);
CREATE INDEX ix_site_visit_last_seen ON site_visit (last_seen) INCLUDE (channel, device, current_path, id_customer);
GO

CREATE TABLE search_log (
    id_search    BIGINT IDENTITY(1,1) PRIMARY KEY,
    visitor_id   CHAR(36)     NULL,
    channel      VARCHAR(3)   NOT NULL,
    term         NVARCHAR(80) NOT NULL,
    results      INT          NOT NULL,
    searched_at  DATETIME2(3) NOT NULL CONSTRAINT df_search_log_searched_at DEFAULT SYSUTCDATETIME(),
    CONSTRAINT ck_search_log_channel CHECK (channel IN ('web','app')),
    CONSTRAINT ck_search_log_results CHECK (results >= 0)
);
GO

CREATE INDEX ix_search_log_date ON search_log (searched_at) INCLUDE (term, results);
GO

-- Una fila por instalación. source = 'instalacion' cuando el navegador avisa
-- que se instaló (Android, Windows, Mac) o 'primer_uso' cuando la app se abre
-- por primera vez sin ese aviso (iPhone y iPad no lo envían).
CREATE TABLE app_install (
    visitor_id    CHAR(36)     NOT NULL PRIMARY KEY,
    device        VARCHAR(10)  NOT NULL,
    platform      VARCHAR(10)  NOT NULL,
    source        VARCHAR(12)  NOT NULL,
    installed_at  DATETIME2(3) NOT NULL CONSTRAINT df_app_install_installed_at DEFAULT SYSUTCDATETIME(),
    CONSTRAINT ck_app_install_device CHECK (device IN ('movil','tableta','escritorio')),
    CONSTRAINT ck_app_install_platform CHECK (platform IN ('android','ios','windows','mac','linux','otro')),
    CONSTRAINT ck_app_install_source CHECK (source IN ('instalacion','primer_uso'))
);
GO

CREATE INDEX ix_app_install_date ON app_install (installed_at);
GO

-- ---------------------------------------------------------------------
-- Datos de ejemplo: un año de visitas, búsquedas y descargas para que el
-- panel tenga historia desde el primer día. Las fechas son relativas a la
-- fecha en que se ejecuta el script; hay más visitas en los meses recientes.
-- ---------------------------------------------------------------------
SET NOCOUNT ON;

IF OBJECT_ID('tempdb..#azar') IS NOT NULL DROP TABLE #azar;

SELECT TOP (9000)
    ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n,
    ABS(CHECKSUM(NEWID())) % 10000 AS r_dia,
    ABS(CHECKSUM(NEWID())) % 100   AS r_canal,
    ABS(CHECKSUM(NEWID())) % 100   AS r_disp,
    ABS(CHECKSUM(NEWID())) % 1440  AS r_minuto,
    ABS(CHECKSUM(NEWID())) % 100   AS r_pagina,
    ABS(CHECKSUM(NEWID())) % 9     AS r_vistas,
    ABS(CHECKSUM(NEWID())) % 25    AS r_duracion
INTO #azar
FROM sys.all_objects AS a CROSS JOIN sys.all_objects AS b;

INSERT INTO site_visit (visitor_id, channel, device, landing_path, current_path, page_views, started_at, last_seen)
SELECT
    CONVERT(CHAR(36), NEWID()),
    v.channel,
    CASE
        WHEN v.channel = 'app' AND z.r_disp < 85 THEN 'movil'
        WHEN v.channel = 'app' AND z.r_disp < 92 THEN 'tableta'
        WHEN v.channel = 'app' THEN 'escritorio'
        WHEN z.r_disp < 56 THEN 'movil'
        WHEN z.r_disp < 62 THEN 'tableta'
        ELSE 'escritorio'
    END,
    p.path,
    p.path,
    1 + z.r_vistas,
    v.started_at,
    DATEADD(MINUTE, 1 + z.r_duracion, v.started_at)
FROM #azar AS z
CROSS APPLY (
    SELECT
        CASE WHEN z.r_canal < 62 THEN 'web' ELSE 'app' END AS channel,
        DATEADD(MINUTE, -z.r_minuto,
            DATEADD(DAY, -CAST(FLOOR(POWER(z.r_dia / 10000.0, 1.6) * 365) AS INT),
                DATEADD(HOUR, -1, SYSUTCDATETIME()))) AS started_at
) AS v
CROSS APPLY (
    SELECT CASE
        WHEN z.r_pagina < 40 THEN N'/'
        WHEN z.r_pagina < 62 THEN N'/tienda'
        WHEN z.r_pagina < 74 THEN N'/outlet'
        WHEN z.r_pagina < 84 THEN N'/ofertas'
        WHEN z.r_pagina < 93 THEN N'/producto'
        ELSE N'/escanear'
    END AS path
) AS p;

DROP TABLE #azar;
GO

DECLARE @terminos TABLE (term NVARCHAR(80), peso INT, results INT);
INSERT INTO @terminos (term, peso, results) VALUES
(N'tenis para correr', 42, 9), (N'sudadera', 31, 6), (N'jersey de futbol', 26, 4),
(N'leggings', 23, 5), (N'balon de futbol', 19, 2), (N'gorra', 17, 3), (N'outlet', 15, 12),
(N'shorts', 14, 5), (N'lentes para correr', 12, 2), (N'tenis blancos', 11, 4),
(N'guantes de box', 10, 1), (N'mochila', 9, 2), (N'chamarra', 8, 3), (N'calcetas', 7, 2),
(N'raqueta de padel', 6, 0), (N'patines', 4, 0), (N'tenis nike', 5, 0), (N'goggles natacion', 5, 1);

INSERT INTO search_log (visitor_id, channel, term, results, searched_at)
SELECT
    CONVERT(CHAR(36), NEWID()),
    CASE WHEN ABS(CHECKSUM(NEWID())) % 100 < 58 THEN 'web' ELSE 'app' END,
    t.term,
    t.results,
    DATEADD(MINUTE, -(ABS(CHECKSUM(NEWID())) % 1440),
        DATEADD(DAY, -CAST(FLOOR(POWER((ABS(CHECKSUM(NEWID())) % 10000) / 10000.0, 1.5) * 365) AS INT),
            DATEADD(HOUR, -1, SYSUTCDATETIME())))
FROM @terminos AS t
JOIN (SELECT TOP (300) ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS n FROM sys.all_objects) AS k
  ON k.n <= t.peso * 6;
GO

INSERT INTO app_install (visitor_id, device, platform, source, installed_at)
SELECT
    CONVERT(CHAR(36), NEWID()),
    x.device,
    CASE
        WHEN x.device = 'escritorio' THEN CASE WHEN x.r_plat < 70 THEN 'windows' ELSE 'mac' END
        WHEN x.r_plat < 64 THEN 'android'
        ELSE 'ios'
    END,
    CASE WHEN x.device <> 'escritorio' AND x.r_plat >= 64 THEN 'primer_uso' ELSE 'instalacion' END,
    x.installed_at
FROM (
    SELECT
        CASE WHEN ABS(CHECKSUM(NEWID())) % 100 < 84 THEN 'movil'
             WHEN ABS(CHECKSUM(NEWID())) % 100 < 50 THEN 'tableta'
             ELSE 'escritorio' END AS device,
        ABS(CHECKSUM(NEWID())) % 100 AS r_plat,
        DATEADD(MINUTE, -(ABS(CHECKSUM(NEWID())) % 1440),
            DATEADD(DAY, -CAST(FLOOR(POWER((ABS(CHECKSUM(NEWID())) % 10000) / 10000.0, 1.4) * 365) AS INT),
                DATEADD(HOUR, -1, SYSUTCDATETIME()))) AS installed_at
    FROM (SELECT TOP (640) 1 AS x FROM sys.all_objects AS a CROSS JOIN sys.all_objects AS b) AS k
) AS x;
GO

SET NOCOUNT OFF;
GO


-- =====================================================================
-- INTEGRIDAD DEL NODO CENTRAL
-- En JAM_db todas las referencias entre servicios se validan con FK.
-- En los fragmentos de las sucursales quedan como referencias lógicas.
-- =====================================================================

ALTER TABLE store          ADD CONSTRAINT fk_store_owner              FOREIGN KEY (id_owner)    REFERENCES owner (id_owner);
ALTER TABLE employee       ADD CONSTRAINT fk_employee_store           FOREIGN KEY (id_store)    REFERENCES store (id_store);
ALTER TABLE customer       ADD CONSTRAINT fk_customer_store           FOREIGN KEY (id_store)    REFERENCES store (id_store);
ALTER TABLE customer       ADD CONSTRAINT fk_customer_address         FOREIGN KEY (id_address)  REFERENCES address (id_address);
ALTER TABLE inventory      ADD CONSTRAINT fk_inventory_store          FOREIGN KEY (id_store)    REFERENCES store (id_store);
ALTER TABLE customer_order ADD CONSTRAINT fk_customer_order_store     FOREIGN KEY (id_store)    REFERENCES store (id_store);
ALTER TABLE customer_order ADD CONSTRAINT fk_customer_order_customer  FOREIGN KEY (id_customer) REFERENCES customer (id_customer);
ALTER TABLE customer_order ADD CONSTRAINT fk_customer_order_employee  FOREIGN KEY (id_employee) REFERENCES employee (id_employee);
ALTER TABLE customer_order ADD CONSTRAINT fk_customer_order_address   FOREIGN KEY (id_address)  REFERENCES address (id_address);
ALTER TABLE order_item     ADD CONSTRAINT fk_order_item_variant       FOREIGN KEY (id_variant)  REFERENCES product_variant (id_variant);
ALTER TABLE message_log    ADD CONSTRAINT fk_message_log_store        FOREIGN KEY (id_store)    REFERENCES store (id_store);
ALTER TABLE audit_log      ADD CONSTRAINT fk_audit_log_store          FOREIGN KEY (id_store)    REFERENCES store (id_store);
GO

-- Reglas de negocio: el total siempre es subtotal - descuento + envío + empaque de regalo
ALTER TABLE customer_order ADD CONSTRAINT ck_customer_order_totals
    CHECK (discount <= subtotal AND gift_cost >= 0 AND total = subtotal - discount + shipping_cost + gift_cost);
GO

-- Unicidad compatible con el borrado lógico: un registro marcado como
-- deleted = 1 no impide volver a registrar el mismo correo, teléfono, etc.
CREATE UNIQUE INDEX uq_owner_email                        ON owner (email)                              WHERE deleted = 0;
CREATE UNIQUE INDEX uq_store_tax_id                       ON store (tax_id)                             WHERE deleted = 0;
CREATE UNIQUE INDEX uq_store_email                        ON store (email)                              WHERE deleted = 0;
CREATE UNIQUE INDEX uq_store_whatsapp                     ON store (whatsapp)                           WHERE deleted = 0;
CREATE UNIQUE INDEX uq_employee_store_phone               ON employee (id_store, phone)                  WHERE deleted = 0;
CREATE UNIQUE INDEX uq_customer_phone                     ON customer (phone)                           WHERE deleted = 0;
CREATE UNIQUE INDEX uq_customer_email                     ON customer (email)                           WHERE deleted = 0;
CREATE UNIQUE INDEX uq_product_variant_sku                ON product_variant (sku)                      WHERE deleted = 0;
CREATE UNIQUE INDEX uq_product_variant_product_size_color ON product_variant (id_product, id_size, id_color) WHERE deleted = 0;
CREATE UNIQUE INDEX uq_promotion_code                     ON promotion (code)                           WHERE deleted = 0;
GO

-- Índices de soporte para las llaves foráneas que no quedan cubiertas
-- por la columna inicial de otro índice.
CREATE INDEX ix_address_state           ON address (id_state);
CREATE INDEX ix_store_store_type        ON store (id_store_type);
CREATE INDEX ix_store_address           ON store (id_address);
CREATE INDEX ix_employee_role           ON employee (id_role);
CREATE INDEX ix_employee_absence_type   ON employee_absence (id_absence_type);
CREATE INDEX ix_verification_code_employee ON verification_code (id_employee);
CREATE INDEX ix_customer_loyalty_level  ON customer (id_loyalty_level);
CREATE INDEX ix_customer_account_tier   ON customer (id_account_tier);
CREATE INDEX ix_product_category        ON product (id_category);
CREATE INDEX ix_product_variant_product ON product_variant (id_product);
CREATE INDEX ix_product_variant_size    ON product_variant (id_size);
CREATE INDEX ix_product_variant_color   ON product_variant (id_color);
CREATE INDEX ix_inventory_variant       ON inventory (id_variant);
CREATE INDEX ix_customer_order_promotion ON customer_order (id_promotion);
CREATE INDEX ix_payment_order           ON payment (id_order);
CREATE INDEX ix_customer_order_address  ON customer_order (id_address);
GO

-- Control de concurrencia optimista: la API rechaza un UPDATE si el
-- registro cambió desde que el cliente lo leyó.
ALTER TABLE product        ADD row_version ROWVERSION;
ALTER TABLE inventory      ADD row_version ROWVERSION;
ALTER TABLE customer_order ADD row_version ROWVERSION;
GO


-- =====================================================================
-- TRIGGERS: actualizan updated_at automáticamente en cada UPDATE
-- (T-SQL no tiene "ON UPDATE CURRENT_TIMESTAMP" como MySQL/MariaDB)
-- =====================================================================

CREATE TRIGGER trg_owner_touch ON owner AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM owner AS t INNER JOIN inserted AS i ON t.id_owner = i.id_owner;
END;
GO

CREATE TRIGGER trg_address_touch ON address AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM address AS t INNER JOIN inserted AS i ON t.id_address = i.id_address;
END;
GO

CREATE TRIGGER trg_store_touch ON store AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM store AS t INNER JOIN inserted AS i ON t.id_store = i.id_store;
END;
GO

CREATE TRIGGER trg_store_config_touch ON store_config AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM store_config AS t
    INNER JOIN inserted AS i ON t.id_store = i.id_store AND t.config_key = i.config_key;
END;
GO

CREATE TRIGGER trg_employee_touch ON employee AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM employee AS t INNER JOIN inserted AS i ON t.id_employee = i.id_employee;
END;
GO

CREATE TRIGGER trg_employee_schedule_touch ON employee_schedule AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM employee_schedule AS t INNER JOIN inserted AS i ON t.id_schedule = i.id_schedule;
END;
GO

CREATE TRIGGER trg_employee_absence_touch ON employee_absence AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM employee_absence AS t INNER JOIN inserted AS i ON t.id_absence = i.id_absence;
END;
GO

CREATE TRIGGER trg_customer_touch ON customer AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM customer AS t INNER JOIN inserted AS i ON t.id_customer = i.id_customer;
END;
GO

CREATE TRIGGER trg_product_touch ON product AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM product AS t INNER JOIN inserted AS i ON t.id_product = i.id_product;
END;
GO

CREATE TRIGGER trg_product_variant_touch ON product_variant AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM product_variant AS t INNER JOIN inserted AS i ON t.id_variant = i.id_variant;
END;
GO

CREATE TRIGGER trg_inventory_touch ON inventory AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM inventory AS t INNER JOIN inserted AS i ON t.id_inventory = i.id_inventory;
END;
GO

CREATE TRIGGER trg_promotion_touch ON promotion AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM promotion AS t INNER JOIN inserted AS i ON t.id_promotion = i.id_promotion;
END;
GO

CREATE TRIGGER trg_customer_order_touch ON customer_order AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM customer_order AS t INNER JOIN inserted AS i ON t.id_order = i.id_order;
END;
GO

CREATE TRIGGER trg_order_item_touch ON order_item AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM order_item AS t INNER JOIN inserted AS i ON t.id_item = i.id_item;
END;
GO

CREATE TRIGGER trg_payment_touch ON payment AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM payment AS t INNER JOIN inserted AS i ON t.id_payment = i.id_payment;
END;
GO

CREATE TRIGGER trg_announcement_touch ON announcement AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM announcement AS t INNER JOIN inserted AS i ON t.id_announcement = i.id_announcement;
END;
GO

CREATE TRIGGER trg_support_ticket_touch ON support_ticket AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM support_ticket AS t INNER JOIN inserted AS i ON t.id_ticket = i.id_ticket;
END;
GO

CREATE TRIGGER trg_market_price_touch ON market_price AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    UPDATE t SET t.updated_at = SYSUTCDATETIME()
    FROM market_price AS t INNER JOIN inserted AS i ON t.id_market_price = i.id_market_price;
END;
GO


-- =====================================================================
-- CONSULTAS DE EJEMPLO
-- =====================================================================

-- 1) Ventas (total facturado) por sucursal
SELECT
    s.id_store,
    s.store_name,
    COUNT(co.id_order)      AS total_orders,
    SUM(co.total)           AS total_sales
FROM store AS s
LEFT JOIN customer_order AS co
    ON co.id_store = s.id_store
    AND co.status IN ('paid','shipped','delivered')
    AND co.deleted = 0
GROUP BY s.id_store, s.store_name
ORDER BY total_sales DESC;
GO

-- 2) Productos (variantes) con stock por debajo del mínimo
SELECT
    st.store_name,
    p.name          AS product_name,
    sz.code         AS size_code,
    cl.code         AS color_code,
    pv.sku,
    inv.stock,
    inv.min_stock
FROM inventory AS inv
JOIN product_variant AS pv ON pv.id_variant = inv.id_variant
JOIN product AS p          ON p.id_product = pv.id_product
JOIN size AS sz             ON sz.id_size = pv.id_size
JOIN color AS cl             ON cl.id_color = pv.id_color
JOIN store AS st              ON st.id_store = inv.id_store
WHERE inv.stock < inv.min_stock
  AND inv.deleted = 0;
GO

-- 3) Top 5 productos más vendidos (por cantidad)
SELECT TOP 5
    p.id_product,
    p.name              AS product_name,
    SUM(oi.quantity)     AS total_units_sold
FROM order_item AS oi
JOIN product_variant AS pv ON pv.id_variant = oi.id_variant
JOIN product AS p          ON p.id_product = pv.id_product
JOIN customer_order AS co   ON co.id_order = oi.id_order
WHERE co.status NOT IN ('cancelled','returned')
  AND oi.deleted = 0
GROUP BY p.id_product, p.name
ORDER BY total_units_sold DESC;
GO

-- 4) Pedidos pendientes de pago
SELECT
    co.id_order,
    co.order_date,
    c.first_name,
    c.last_name_paternal,
    co.total,
    co.status      AS order_status,
    pay.status     AS payment_status
FROM customer_order AS co
JOIN customer AS c ON c.id_customer = co.id_customer
LEFT JOIN payment AS pay ON pay.id_order = co.id_order
WHERE co.status = 'pending'
   OR pay.status = 'pending'
ORDER BY co.order_date;
GO

-- 5) Total comprado por cliente
SELECT
    c.id_customer,
    c.first_name,
    c.last_name_paternal,
    ll.name          AS loyalty_level,
    COUNT(co.id_order) AS total_orders,
    SUM(co.total)       AS total_spent
FROM customer AS c
JOIN loyalty_level AS ll ON ll.id_loyalty_level = c.id_loyalty_level
LEFT JOIN customer_order AS co
    ON co.id_customer = c.id_customer
    AND co.status NOT IN ('cancelled')
    AND co.deleted = 0
GROUP BY c.id_customer, c.first_name, c.last_name_paternal, ll.name
ORDER BY total_spent DESC;
GO