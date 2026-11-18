USE master;
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
    active            BIT           NOT NULL DEFAULT 1,
    CONSTRAINT uq_season_theme_code UNIQUE (code),
    CONSTRAINT ck_season_theme_type CHECK (theme_type IN ('season','campaign')),
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
                          color_primary, color_secondary, color_accent, color_accent_text, hero_title, hero_text) VALUES
(1, 'spring',        'Primavera',          'season',    3, 20,  6, 20, 1, '#1D4D3A', '#2A6B50', '#FFB3C7', '#1D4D3A', 'Renueva tu rutina',          'Colores frescos y prendas ligeras para entrenar al aire libre.'),
(2, 'summer',        'Verano',             'season',    6, 21,  9, 22, 1, '#0B3D5C', '#0E5A85', '#FFD23F', '#0B3D5C', 'Verano en movimiento',       'Shorts, tops y tenis transpirables para los días de más calor.'),
(3, 'autumn',        'Otoño',              'season',    9, 23, 12, 20, 1, '#3B1F14', '#5A2E1B', '#FF8C42', '#3B1F14', 'Entrena en otoño',           'Capas ligeras, rompevientos y tenis para correr con el clima fresco.'),
(4, 'winter',        'Invierno',           'season',   12, 21,  3, 19, 1, '#0F1D3D', '#1C2F5E', '#9BE7FF', '#0F1D3D', 'Invierno sin pausa',         'Sudaderas, chamarras y leggings térmicos para no dejar de moverte.'),
(5, 'back_to_school','Regreso a clases',   'campaign',  8,  1,  8, 31, 5, '#1E1B4B', '#312E81', '#FACC15', '#1E1B4B', 'Regreso a clases',           'Mochilas, tenis y uniformes deportivos para el nuevo ciclo escolar.'),
(6, 'buen_fin',      'El Buen Fin',        'campaign', 11, 13, 11, 17, 9, '#7A0019', '#A3001F', '#FFD000', '#3A0010', 'El Buen Fin JAM',            'Descuentos en calzado, ropa y accesorios del 13 al 17 de noviembre.'),
(7, 'black_friday',  'Black Friday',       'campaign', 11, 20, 11, 30, 9, '#0A0A0A', '#262626', '#FF2D55', '#FFFFFF', 'Black Friday',               'Hasta 25% de descuento con el cupón BLACKFRIDAY.'),
(8, 'christmas',     'Navidad',            'campaign', 12,  1,  1,  6, 8, '#14351F', '#1E4D2B', '#E63946', '#FFFFFF', 'Regala movimiento',          'Ideas de regalo deportivas para jóvenes y adultos.');
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
    -- columnas de consolidación
    source_node         VARCHAR(20) NOT NULL,
    updated_at          DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted             BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_customer_loyalty_level FOREIGN KEY (id_loyalty_level) REFERENCES loyalty_level (id_loyalty_level),
    CONSTRAINT ck_customer_sex CHECK (sex IN ('M','F','X'))
);
GO

CREATE INDEX ix_customer_store ON customer (id_store);
CREATE INDEX ix_customer_address ON customer (id_address);
GO

INSERT INTO customer (id_loyalty_level, id_store, id_address, first_name, last_name_paternal, last_name_maternal, birth_date, sex, phone, email, accepts_marketing, source_node) VALUES
(1, 1, NULL, 'María Fernanda', 'López',     'Hidalgo', '1996-04-12', 'F', '6647770001', 'mariaf.lopez@email.com',  1, 'customers_node'),
(2, 4, NULL, 'Luis Alberto',   'Gómez',     'Nava',    '1990-08-23', 'M', '6647770002', 'luis.gomez@email.com',   1, 'customers_node'),
(3, 3, 5,    'Daniela',        'Herrera',   'Campos',  '1993-01-30', 'F', '8187770003', 'daniela.herrera@email.com', 1, 'customers_node'),
(1, 5, NULL, 'Jorge',          'Escamilla', 'Pardo',   '1988-11-05', 'M', '6647770004', 'jorge.escamilla@email.com', 0, 'customers_node'),
(1, 4, NULL, 'Ana Patricia',   'Ruiz',      'Montes',  '1999-06-18', 'F', '6647770005', 'ana.ruiz@email.com',      1, 'customers_node');
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
    -- columnas de consolidación
    source_node     VARCHAR(20) NOT NULL,
    updated_at      DATETIME2(3) NOT NULL DEFAULT SYSUTCDATETIME(),
    deleted         BIT NOT NULL DEFAULT 0,
    CONSTRAINT fk_product_category FOREIGN KEY (id_category) REFERENCES category (id_category),
    CONSTRAINT ck_product_target_gender CHECK (target_gender IN ('M','F','U')),
    CONSTRAINT ck_product_base_price CHECK (base_price >= 0),
    CONSTRAINT ck_product_age_segment CHECK (age_segment IN ('youth','adult','all'))
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

-- Reglas de negocio: el total siempre es subtotal - descuento + envío
ALTER TABLE customer_order ADD CONSTRAINT ck_customer_order_totals
    CHECK (discount <= subtotal AND total = subtotal - discount + shipping_cost);
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