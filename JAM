CREATE DATABASE IF NOT EXISTS JAM_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE JAM_db;

-- ---------------------------------------------------------------------
-- Limpieza previa (orden inverso de dependencias)
-- ---------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS message_log;
DROP TABLE IF EXISTS payment;
DROP TABLE IF EXISTS order_item;
DROP TABLE IF EXISTS customer_order;
DROP TABLE IF EXISTS promotion;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS product_variant;
DROP TABLE IF EXISTS product;
DROP TABLE IF EXISTS customer;
DROP TABLE IF EXISTS verification_code;
DROP TABLE IF EXISTS employee_absence;
DROP TABLE IF EXISTS employee_schedule;
DROP TABLE IF EXISTS employee;
DROP TABLE IF EXISTS store_config;
DROP TABLE IF EXISTS store;
DROP TABLE IF EXISTS address;
DROP TABLE IF EXISTS owner;
DROP TABLE IF EXISTS state;
DROP TABLE IF EXISTS absence_type;
DROP TABLE IF EXISTS loyalty_level;
DROP TABLE IF EXISTS color;
DROP TABLE IF EXISTS size;
DROP TABLE IF EXISTS category;
DROP TABLE IF EXISTS store_type;
DROP TABLE IF EXISTS role;

SET FOREIGN_KEY_CHECKS = 1;


-- =====================================================================
-- 1. CATÁLOGOS (replicados en todos los nodos/servicios; sin columnas
--    de consolidación; PK fija TINYINT UNSIGNED puesta a mano)
-- =====================================================================

CREATE TABLE role (
    id_role TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    code    VARCHAR(13) NOT NULL,
    name    VARCHAR(20) NOT NULL,
    CONSTRAINT uq_role_code UNIQUE (code)
) ENGINE=InnoDB;

INSERT INTO role (id_role, code, name) VALUES
(1, 'store_manager', 'Gerente de Tienda'),
(2, 'seller',        'Vendedor'),
(3, 'cashier',        'Cajero'),
(4, 'warehouse',      'Almacén');


CREATE TABLE store_type (
    id_store_type TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    code          VARCHAR(8) NOT NULL,
    name          VARCHAR(30) NOT NULL,
    CONSTRAINT uq_store_type_code UNIQUE (code)
) ENGINE=InnoDB;

INSERT INTO store_type (id_store_type, code, name) VALUES
(1, 'flagship', 'Tienda Insignia'),
(2, 'mall',     'Tienda en Centro Comercial'),
(3, 'outlet',   'Outlet'),
(4, 'online',   'Tienda en Línea'),
(5, 'kiosk',    'Kiosko');


CREATE TABLE category (
    id_category TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    code        VARCHAR(11) NOT NULL,
    name        VARCHAR(20) NOT NULL,
    CONSTRAINT uq_category_code UNIQUE (code)
) ENGINE=InnoDB;

INSERT INTO category (id_category, code, name) VALUES
(1, 'tops',        'Parte Superior'),
(2, 'bottoms',     'Parte Inferior'),
(3, 'leggings',    'Leggings'),
(4, 'hoodies',     'Sudaderas'),
(5, 'footwear',    'Calzado'),
(6, 'accessories', 'Accesorios');


CREATE TABLE size (
    id_size TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    code    VARCHAR(3) NOT NULL,
    name    VARCHAR(20) NOT NULL,
    CONSTRAINT uq_size_code UNIQUE (code)
) ENGINE=InnoDB;

INSERT INTO size (id_size, code, name) VALUES
(1, 'XS',  'Extra Chico'),
(2, 'S',   'Chico'),
(3, 'M',   'Mediano'),
(4, 'L',   'Grande'),
(5, 'XL',  'Extra Grande'),
(6, 'XXL', 'Extra Extra Grande'),
(7, '26',  'Calzado 26'),
(8, '27',  'Calzado 27');


CREATE TABLE color (
    id_color TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    code     VARCHAR(5) NOT NULL,
    name     VARCHAR(15) NOT NULL,
    CONSTRAINT uq_color_code UNIQUE (code)
) ENGINE=InnoDB;

INSERT INTO color (id_color, code, name) VALUES
(1, 'black', 'Negro'),
(2, 'white', 'Blanco'),
(3, 'red',   'Rojo'),
(4, 'navy',  'Azul Marino'),
(5, 'gray',  'Gris');


CREATE TABLE loyalty_level (
    id_loyalty_level TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    code              VARCHAR(6) NOT NULL,
    name              VARCHAR(10) NOT NULL,
    discount_percent  DECIMAL(4,2) NOT NULL,
    min_spent         DECIMAL(10,2) NOT NULL,
    CONSTRAINT uq_loyalty_level_code UNIQUE (code),
    CONSTRAINT ck_loyalty_level_discount CHECK (discount_percent BETWEEN 0 AND 99.99),
    CONSTRAINT ck_loyalty_level_min_spent CHECK (min_spent >= 0)
) ENGINE=InnoDB;

INSERT INTO loyalty_level (id_loyalty_level, code, name, discount_percent, min_spent) VALUES
(1, 'member', 'Miembro', 0.00, 0.00),
(2, 'silver', 'Plata',   5.00, 3000.00),
(3, 'gold',   'Oro',    10.00, 8000.00);


CREATE TABLE absence_type (
    id_absence_type TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    code            VARCHAR(11) NOT NULL,
    name            VARCHAR(20) NOT NULL,
    CONSTRAINT uq_absence_type_code UNIQUE (code)
) ENGINE=InnoDB;

INSERT INTO absence_type (id_absence_type, code, name) VALUES
(1, 'day_off',     'Día de Descanso'),
(2, 'no_show',     'Inasistencia'),
(3, 'vacation',    'Vacaciones'),
(4, 'sick_leave',  'Incapacidad'),
(5, 'resignation', 'Renuncia');


CREATE TABLE state (
    id_state TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    code     CHAR(3) NOT NULL,
    name     VARCHAR(20) NOT NULL,
    CONSTRAINT uq_state_code UNIQUE (code)
) ENGINE=InnoDB;

INSERT INTO state (id_state, code, name) VALUES
(1, 'BCN', 'Baja California'),
(2, 'CMX', 'Ciudad de México'),
(3, 'JAL', 'Jalisco'),
(4, 'NLE', 'Nuevo León'),
(5, 'SON', 'Sonora');


-- =====================================================================
-- 2. Servicio Admin
-- =====================================================================

CREATE TABLE owner (
    id_owner            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    first_name          VARCHAR(50) NOT NULL,
    last_name_paternal  VARCHAR(50) NOT NULL,
    last_name_maternal  VARCHAR(50) NULL,
    email               VARCHAR(100) NOT NULL,
    phone               VARCHAR(15) NOT NULL,
    password_hash       VARCHAR(100) NOT NULL,
    active              TINYINT(1) NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- columnas de consolidación
    source_node         VARCHAR(20) NOT NULL,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted             TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT uq_owner_email UNIQUE (email)
) ENGINE=InnoDB;

INSERT INTO owner (first_name, last_name_paternal, last_name_maternal, email, phone, password_hash, active, source_node) VALUES
('Mariana',   'Castillo', 'Rubio',    'mariana.castillo@jam.mx', '6641234501', '$2y$10$hash0001', 1, 'admin_node'),
('Héctor',    'Villareal','Nuñez',    'hector.villareal@jam.mx', '6641234502', '$2y$10$hash0002', 1, 'admin_node'),
('Paola',     'Esquivel', 'Mora',     'paola.esquivel@jam.mx',  '6641234503', '$2y$10$hash0003', 1, 'admin_node'),
('Ricardo',   'Beltrán',  'Solís',    'ricardo.beltran@jam.mx', '6641234504', '$2y$10$hash0004', 1, 'admin_node'),
('Fernanda',  'Ocampo',   'Reyes',    'fernanda.ocampo@jam.mx', '6641234505', '$2y$10$hash0005', 1, 'admin_node');


-- =====================================================================
-- 3. Servicio Sucursales
-- =====================================================================

CREATE TABLE address (
    id_address     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    street         VARCHAR(100) NOT NULL,
    ext_number     VARCHAR(10) NOT NULL,
    int_number     VARCHAR(10) NULL,
    neighborhood   VARCHAR(80) NOT NULL,
    city           VARCHAR(60) NOT NULL,
    id_state       TINYINT UNSIGNED NOT NULL,
    zip_code       CHAR(5) NOT NULL,
    reference      VARCHAR(150) NULL,
    -- columnas de consolidación
    source_node    VARCHAR(20) NOT NULL,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted        TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_address_state FOREIGN KEY (id_state) REFERENCES state (id_state)
) ENGINE=InnoDB;

INSERT INTO address (street, ext_number, int_number, neighborhood, city, id_state, zip_code, reference, source_node) VALUES
('Blvd. Insurgentes',   '1500', NULL,  'Centro',           'Tijuana',    1, '22000', 'Frente a Plaza Macroplaza',     'stores_node'),
('Av. Reforma',          '222', 'A',   'Juárez',           'Ciudad de México', 2, '06600', 'Esquina con Niza',         'stores_node'),
('Av. Vallarta',        '3400', NULL,  'Vallarta Norte',   'Guadalajara',3, '44690', 'Junto a Plaza del Sol',        'stores_node'),
('Blvd. Solidaridad',    '500', NULL,  'Villa Satélite',   'Hermosillo', 5, '83200', 'Plaza Outlet Norte',           'stores_node'),
('Av. Constitución',     '800', '3',   'Centro',           'Monterrey',  4, '64000', 'Domicilio particular cliente', 'stores_node');


CREATE TABLE store (
    id_store       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_owner       INT UNSIGNED NOT NULL, -- ref. logica a owner (otro servicio: Admin)
    id_store_type  TINYINT UNSIGNED NOT NULL,
    id_address     INT UNSIGNED NULL, -- NULL = tienda en línea
    store_name     VARCHAR(80) NOT NULL,
    tax_id         VARCHAR(13) NOT NULL,
    contact_phone  VARCHAR(15) NOT NULL,
    email          VARCHAR(100) NOT NULL,
    whatsapp       VARCHAR(15) NOT NULL,
    opened_at      DATE NOT NULL,
    active         TINYINT(1) NOT NULL DEFAULT 1,
    -- columnas de consolidación
    source_node    VARCHAR(20) NOT NULL,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted        TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_store_store_type FOREIGN KEY (id_store_type) REFERENCES store_type (id_store_type),
    CONSTRAINT fk_store_address FOREIGN KEY (id_address) REFERENCES address (id_address),
    CONSTRAINT uq_store_tax_id UNIQUE (tax_id),
    CONSTRAINT uq_store_email UNIQUE (email),
    CONSTRAINT uq_store_whatsapp UNIQUE (whatsapp),
    INDEX ix_store_owner (id_owner)
) ENGINE=InnoDB;

INSERT INTO store (id_owner, id_store_type, id_address, store_name, tax_id, contact_phone, email, whatsapp, opened_at, source_node) VALUES
(1, 1, 1,    'JAM Tijuana Flagship',    'JAM010101AB1', '6641110001', 'tijuana@jam.mx',  '6641110001', '2022-03-01', 'stores_node'),
(1, 2, 2,    'JAM CDMX Reforma',        'JAM010101AB2', '5521110002', 'cdmx@jam.mx',     '5521110002', '2023-05-15', 'stores_node'),
(1, 2, 3,    'JAM Guadalajara Vallarta','JAM010101AB3', '3331110003', 'gdl@jam.mx',      '3331110003', '2023-09-20', 'stores_node'),
(1, 4, NULL, 'JAM Online Store',        'JAM010101AB4', '8001110004', 'online@jam.mx',   '8001110004', '2021-11-01', 'stores_node'),
(1, 3, 4,    'JAM Outlet Hermosillo',   'JAM010101AB5', '6621110005', 'hermosillo@jam.mx','6621110005','2024-02-10', 'stores_node');


CREATE TABLE store_config (
    id_store      INT UNSIGNED NOT NULL,
    config_key    VARCHAR(50) NOT NULL,
    config_value  VARCHAR(255) NOT NULL,
    -- columnas de consolidación
    source_node   VARCHAR(20) NOT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted       TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id_store, config_key),
    CONSTRAINT fk_store_config_store FOREIGN KEY (id_store) REFERENCES store (id_store)
) ENGINE=InnoDB;

INSERT INTO store_config (id_store, config_key, config_value, source_node) VALUES
(1, 'tax_rate',             '16.00',        'stores_node'),
(1, 'opening_hours',        '10:00-21:00',  'stores_node'),
(4, 'currency',             'MXN',          'stores_node'),
(4, 'whatsapp_bot_enabled', 'true',         'stores_node'),
(5, 'return_policy_days',   '30',           'stores_node');


-- =====================================================================
-- 4. Servicio Empleados
-- =====================================================================

CREATE TABLE employee (
    id_employee           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_store               INT UNSIGNED NOT NULL, -- ref. logica a store (otro servicio: Sucursales)
    id_role                TINYINT UNSIGNED NOT NULL,
    first_name             VARCHAR(50) NOT NULL,
    last_name_paternal     VARCHAR(50) NOT NULL,
    last_name_maternal     VARCHAR(50) NULL,
    phone                  VARCHAR(15) NOT NULL,
    phone_verified         TINYINT(1) NOT NULL DEFAULT 0,
    email                  VARCHAR(100) NOT NULL,
    password_hash          VARCHAR(100) NOT NULL,
    must_change_password   TINYINT(1) NOT NULL DEFAULT 1,
    hire_date              DATE NOT NULL,
    -- columnas de consolidación
    source_node            VARCHAR(20) NOT NULL,
    updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted                TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_employee_role FOREIGN KEY (id_role) REFERENCES role (id_role),
    CONSTRAINT uq_employee_store_phone UNIQUE (id_store, phone),
    INDEX ix_employee_store (id_store)
) ENGINE=InnoDB;

INSERT INTO employee (id_store, id_role, first_name, last_name_paternal, last_name_maternal, phone, phone_verified, email, password_hash, must_change_password, hire_date, source_node) VALUES
(1, 1, 'Roberto',  'Díaz',    'Lemus',   '6645550001', 1, 'roberto.diaz@jam.mx',   '$2y$10$hash1001', 0, '2022-03-05', 'employees_node'),
(1, 2, 'Laura',    'Jiménez', 'Castro',  '6645550002', 1, 'laura.jimenez@jam.mx',  '$2y$10$hash1002', 0, '2022-04-10', 'employees_node'),
(2, 3, 'Carlos',   'Mendoza', 'Ruiz',    '5545550003', 1, 'carlos.mendoza@jam.mx', '$2y$10$hash1003', 0, '2023-05-20', 'employees_node'),
(3, 4, 'Ana',      'Torres',  'Vega',    '3345550004', 0, 'ana.torres@jam.mx',     '$2y$10$hash1004', 1, '2023-09-25', 'employees_node'),
(5, 2, 'Jorge',    'Ramírez', 'Solano',  '6625550005', 1, 'jorge.ramirez@jam.mx',  '$2y$10$hash1005', 0, '2024-02-15', 'employees_node');


CREATE TABLE employee_schedule (
    id_schedule    INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_employee    INT UNSIGNED NOT NULL,
    weekday        VARCHAR(3) NOT NULL,
    start_time     TIME NOT NULL,
    end_time       TIME NOT NULL,
    -- columnas de consolidación
    source_node    VARCHAR(20) NOT NULL,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted        TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_employee_schedule_employee FOREIGN KEY (id_employee) REFERENCES employee (id_employee),
    CONSTRAINT uq_employee_schedule_employee_weekday UNIQUE (id_employee, weekday),
    CONSTRAINT ck_employee_schedule_weekday CHECK (weekday IN ('mon','tue','wed','thu','fri','sat','sun')),
    CONSTRAINT ck_employee_schedule_time CHECK (end_time > start_time)
) ENGINE=InnoDB;

INSERT INTO employee_schedule (id_employee, weekday, start_time, end_time, source_node) VALUES
(1, 'mon', '09:00:00', '18:00:00', 'employees_node'),
(2, 'tue', '10:00:00', '19:00:00', 'employees_node'),
(3, 'wed', '09:00:00', '17:00:00', 'employees_node'),
(4, 'thu', '08:00:00', '16:00:00', 'employees_node'),
(5, 'fri', '11:00:00', '20:00:00', 'employees_node');


CREATE TABLE employee_absence (
    id_absence       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_employee      INT UNSIGNED NOT NULL,
    id_absence_type  TINYINT UNSIGNED NOT NULL,
    absence_date     DATE NOT NULL,
    -- columnas de consolidación
    source_node      VARCHAR(20) NOT NULL,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted          TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_employee_absence_employee FOREIGN KEY (id_employee) REFERENCES employee (id_employee),
    CONSTRAINT fk_employee_absence_type FOREIGN KEY (id_absence_type) REFERENCES absence_type (id_absence_type),
    CONSTRAINT uq_employee_absence_employee_date UNIQUE (id_employee, absence_date)
) ENGINE=InnoDB;

INSERT INTO employee_absence (id_employee, id_absence_type, absence_date, source_node) VALUES
(2, 1, '2026-11-02', 'employees_node'),
(3, 4, '2026-10-15', 'employees_node'),
(4, 3, '2026-12-20', 'employees_node'),
(1, 2, '2026-09-05', 'employees_node'),
(5, 5, '2026-12-31', 'employees_node');


CREATE TABLE verification_code (
    id_code       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_employee   INT UNSIGNED NOT NULL,
    purpose       VARCHAR(14) NOT NULL,
    code_hash     VARCHAR(100) NOT NULL,
    expires_at    DATETIME NOT NULL,
    attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    used          TINYINT(1) NOT NULL DEFAULT 0,
    -- tabla de bitácora: source_node y created_at, sin deleted
    source_node   VARCHAR(20) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_verification_code_employee FOREIGN KEY (id_employee) REFERENCES employee (id_employee),
    CONSTRAINT ck_verification_code_purpose CHECK (purpose IN ('password_reset','phone_link'))
) ENGINE=InnoDB;

INSERT INTO verification_code (id_employee, purpose, code_hash, expires_at, attempts, used, source_node) VALUES
(1, 'password_reset', '$2y$10$codehash01', '2026-11-13 20:00:00', 0, 0, 'employees_node'),
(2, 'phone_link',     '$2y$10$codehash02', '2026-11-13 21:00:00', 1, 1, 'employees_node'),
(3, 'password_reset', '$2y$10$codehash03', '2026-11-14 10:00:00', 0, 0, 'employees_node'),
(4, 'phone_link',     '$2y$10$codehash04', '2026-11-14 11:00:00', 2, 1, 'employees_node'),
(5, 'password_reset', '$2y$10$codehash05', '2026-11-15 09:00:00', 0, 0, 'employees_node');


-- =====================================================================
-- 5. Servicio Clientes finales
-- =====================================================================

CREATE TABLE customer (
    id_customer         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_loyalty_level    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    id_store            INT UNSIGNED NOT NULL, -- ref. logica a store (otro servicio: Sucursales)
    id_address          INT UNSIGNED NULL,     -- ref. logica a address (otro servicio: Sucursales)
    first_name          VARCHAR(50) NOT NULL,
    last_name_paternal  VARCHAR(50) NOT NULL,
    last_name_maternal  VARCHAR(50) NULL,
    birth_date          DATE NOT NULL,
    sex                 CHAR(1) NOT NULL,
    phone               VARCHAR(15) NOT NULL,
    email               VARCHAR(100) NOT NULL,
    accepts_marketing   TINYINT(1) NOT NULL DEFAULT 0,
    -- columnas de consolidación
    source_node         VARCHAR(20) NOT NULL,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted             TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_customer_loyalty_level FOREIGN KEY (id_loyalty_level) REFERENCES loyalty_level (id_loyalty_level),
    CONSTRAINT uq_customer_phone UNIQUE (phone),
    CONSTRAINT uq_customer_email UNIQUE (email),
    CONSTRAINT ck_customer_sex CHECK (sex IN ('M','F','X')),
    INDEX ix_customer_store (id_store),
    INDEX ix_customer_address (id_address)
) ENGINE=InnoDB;

INSERT INTO customer (id_loyalty_level, id_store, id_address, first_name, last_name_paternal, last_name_maternal, birth_date, sex, phone, email, accepts_marketing, source_node) VALUES
(1, 1, NULL, 'María Fernanda', 'López',     'Hidalgo', '1996-04-12', 'F', '6647770001', 'mariaf.lopez@email.com',  1, 'customers_node'),
(2, 4, NULL, 'Luis Alberto',   'Gómez',     'Nava',    '1990-08-23', 'M', '6647770002', 'luis.gomez@email.com',   1, 'customers_node'),
(3, 3, 5,    'Daniela',        'Herrera',   'Campos',  '1993-01-30', 'F', '8187770003', 'daniela.herrera@email.com', 1, 'customers_node'),
(1, 5, NULL, 'Jorge',          'Escamilla', 'Pardo',   '1988-11-05', 'M', '6647770004', 'jorge.escamilla@email.com', 0, 'customers_node'),
(1, 4, NULL, 'Ana Patricia',   'Ruiz',      'Montes',  '1999-06-18', 'F', '6647770005', 'ana.ruiz@email.com',      1, 'customers_node');


-- =====================================================================
-- 6. Servicio Catálogo de productos
-- =====================================================================

CREATE TABLE product (
    id_product      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_category     TINYINT UNSIGNED NOT NULL,
    name            VARCHAR(80) NOT NULL,
    description     VARCHAR(255) NULL,
    target_gender   CHAR(1) NOT NULL,
    base_price      DECIMAL(8,2) NOT NULL,
    -- columnas de consolidación
    source_node     VARCHAR(20) NOT NULL,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted         TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_product_category FOREIGN KEY (id_category) REFERENCES category (id_category),
    CONSTRAINT ck_product_target_gender CHECK (target_gender IN ('M','F','U')),
    CONSTRAINT ck_product_base_price CHECK (base_price >= 0)
) ENGINE=InnoDB;

INSERT INTO product (id_category, name, description, target_gender, base_price, source_node) VALUES
(1, 'Playera Dry-Fit JAM Pro',         'Playera deportiva de secado rápido', 'U', 450.00, 'catalog_node'),
(2, 'Short Running JAM Air',           'Short ligero para correr',           'U', 380.00, 'catalog_node'),
(3, 'Legging Compresión JAM Flex',     'Legging de compresión para entrenar','F', 520.00, 'catalog_node'),
(4, 'Sudadera Hoodie JAM Core',        'Sudadera con capucha de algodón',    'U', 750.00, 'catalog_node'),
(5, 'Tenis Trainer JAM Stride',        'Tenis para entrenamiento multiuso',  'U',1850.00, 'catalog_node');


CREATE TABLE product_variant (
    id_variant   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_product   INT UNSIGNED NOT NULL,
    id_size      TINYINT UNSIGNED NOT NULL,
    id_color     TINYINT UNSIGNED NOT NULL,
    sku          VARCHAR(20) NOT NULL,
    -- columnas de consolidación
    source_node  VARCHAR(20) NOT NULL,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted      TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_product_variant_product FOREIGN KEY (id_product) REFERENCES product (id_product),
    CONSTRAINT fk_product_variant_size FOREIGN KEY (id_size) REFERENCES size (id_size),
    CONSTRAINT fk_product_variant_color FOREIGN KEY (id_color) REFERENCES color (id_color),
    CONSTRAINT uq_product_variant_sku UNIQUE (sku),
    CONSTRAINT uq_product_variant_product_size_color UNIQUE (id_product, id_size, id_color)
) ENGINE=InnoDB;

INSERT INTO product_variant (id_product, id_size, id_color, sku, source_node) VALUES
(1, 3, 1, 'JAM-PRO-M-BLK',  'catalog_node'),
(2, 3, 4, 'JAM-AIR-M-NVY',  'catalog_node'),
(3, 2, 1, 'JAM-FLX-S-BLK',  'catalog_node'),
(4, 4, 5, 'JAM-CORE-L-GRY', 'catalog_node'),
(5, 8, 2, 'JAM-STR-27-WHT', 'catalog_node');


CREATE TABLE inventory (
    id_inventory  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_store      INT UNSIGNED NOT NULL, -- ref. logica a store (otro servicio: Sucursales)
    id_variant    INT UNSIGNED NOT NULL,
    stock         INT UNSIGNED NOT NULL DEFAULT 0,
    min_stock     INT UNSIGNED NOT NULL DEFAULT 5,
    -- columnas de consolidación
    source_node   VARCHAR(20) NOT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted       TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_inventory_variant FOREIGN KEY (id_variant) REFERENCES product_variant (id_variant),
    CONSTRAINT uq_inventory_store_variant UNIQUE (id_store, id_variant),
    INDEX ix_inventory_store (id_store)
) ENGINE=InnoDB;

INSERT INTO inventory (id_store, id_variant, stock, min_stock, source_node) VALUES
(1, 1, 40,  5,  'catalog_node'),
(1, 4, 3,   5,  'catalog_node'),
(2, 2, 60,  10, 'catalog_node'),
(5, 5, 2,   5,  'catalog_node'),
(4, 3, 100, 20, 'catalog_node');


-- =====================================================================
-- 7. Servicio Pedidos
-- =====================================================================

CREATE TABLE promotion (
    id_promotion      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code              VARCHAR(20) NOT NULL,
    description       VARCHAR(150) NULL,
    discount_percent  DECIMAL(5,2) NOT NULL,
    start_date        DATE NOT NULL,
    end_date          DATE NOT NULL,
    -- columnas de consolidación
    source_node       VARCHAR(20) NOT NULL,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted           TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT uq_promotion_code UNIQUE (code),
    CONSTRAINT ck_promotion_discount_percent CHECK (discount_percent BETWEEN 0 AND 100),
    CONSTRAINT ck_promotion_dates CHECK (end_date >= start_date)
) ENGINE=InnoDB;

INSERT INTO promotion (code, description, discount_percent, start_date, end_date, source_node) VALUES
('BIENVENIDA10', 'Descuento de bienvenida para nuevos clientes',      10.00, '2026-01-01', '2026-12-31', 'orders_node'),
('VERANO2026',   'Promoción de temporada de verano',                  15.00, '2026-06-01', '2026-08-31', 'orders_node'),
('BLACKFRIDAY',  'Descuento especial de Black Friday JAM',             25.00, '2026-11-20', '2026-11-30', 'orders_node'),
('VIPGOLD',      'Descuento exclusivo para clientes nivel Oro',       20.00, '2026-01-01', '2026-12-31', 'orders_node'),
('ENVIOGRATIS',  'Promoción de envío gratis (sin descuento en producto)', 0.00, '2026-01-01', '2026-12-31', 'orders_node');


CREATE TABLE customer_order (
    id_order       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_store       INT UNSIGNED NOT NULL,      -- ref. logica a store (otro servicio: Sucursales)
    id_customer    INT UNSIGNED NOT NULL,      -- ref. logica a customer (otro servicio: Clientes finales)
    id_employee    INT UNSIGNED NULL,          -- ref. logica a employee (otro servicio: Empleados)
    id_promotion   INT UNSIGNED NULL,
    order_date     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status         VARCHAR(9) NOT NULL,
    channel        VARCHAR(8) NOT NULL,
    subtotal       DECIMAL(10,2) NOT NULL,
    discount       DECIMAL(10,2) NOT NULL DEFAULT 0,
    total          DECIMAL(10,2) NOT NULL,
    notes          VARCHAR(255) NULL,
    -- columnas de consolidación
    source_node    VARCHAR(20) NOT NULL,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted        TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_customer_order_promotion FOREIGN KEY (id_promotion) REFERENCES promotion (id_promotion),
    CONSTRAINT ck_customer_order_status CHECK (status IN ('pending','paid','shipped','delivered','cancelled','returned')),
    CONSTRAINT ck_customer_order_channel CHECK (channel IN ('in_store','online','whatsapp')),
    CONSTRAINT ck_customer_order_subtotal CHECK (subtotal >= 0),
    CONSTRAINT ck_customer_order_discount CHECK (discount >= 0),
    CONSTRAINT ck_customer_order_total CHECK (total >= 0),
    INDEX ix_customer_order_store (id_store),
    INDEX ix_customer_order_customer (id_customer),
    INDEX ix_customer_order_employee (id_employee)
) ENGINE=InnoDB;

INSERT INTO customer_order (id_store, id_customer, id_employee, id_promotion, status, channel, subtotal, discount, total, notes, source_node) VALUES
(1, 1, 1, NULL, 'paid',      'in_store', 900.00,  0.00,   900.00,  'Compra en tienda Tijuana',           'orders_node'),
(4, 2, NULL, 2, 'paid',      'online',   750.00,  112.50, 637.50,  'Aplicó cupón VERANO2026',            'orders_node'),
(3, 3, NULL, NULL,'pending', 'in_store', 520.00,  52.00,  468.00,  'Pendiente de pago, nivel Oro',       'orders_node'),
(5, 4, 5, 3,    'paid',      'in_store', 1850.00, 462.50, 1387.50, 'Aplicó cupón BLACKFRIDAY',           'orders_node'),
(4, 5, NULL, NULL,'cancelled','online',  1140.00, 0.00,   1140.00, 'Cliente canceló, pago reembolsado',  'orders_node');


CREATE TABLE order_item (
    id_item        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_order       INT UNSIGNED NOT NULL,
    id_variant     INT UNSIGNED NOT NULL, -- ref. logica a product_variant (otro servicio: Catálogo de productos)
    quantity       SMALLINT UNSIGNED NOT NULL,
    unit_price     DECIMAL(8,2) NOT NULL,
    -- columnas de consolidación
    source_node    VARCHAR(20) NOT NULL,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted        TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_order_item_order FOREIGN KEY (id_order) REFERENCES customer_order (id_order),
    CONSTRAINT uq_order_item_order_variant UNIQUE (id_order, id_variant),
    CONSTRAINT ck_order_item_quantity CHECK (quantity > 0),
    CONSTRAINT ck_order_item_unit_price CHECK (unit_price >= 0),
    INDEX ix_order_item_variant (id_variant)
) ENGINE=InnoDB;

INSERT INTO order_item (id_order, id_variant, quantity, unit_price, source_node) VALUES
(1, 1, 2, 450.00,  'orders_node'),
(2, 4, 1, 750.00,  'orders_node'),
(3, 3, 1, 520.00,  'orders_node'),
(4, 5, 1, 1850.00, 'orders_node'),
(5, 2, 3, 380.00,  'orders_node');


CREATE TABLE payment (
    id_payment          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_order            INT UNSIGNED NOT NULL,
    amount              DECIMAL(10,2) NOT NULL,
    currency            CHAR(3) NOT NULL DEFAULT 'MXN',
    payment_date        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    method              VARCHAR(8) NOT NULL,
    external_reference  VARCHAR(50) NULL,
    status              VARCHAR(9) NOT NULL,
    -- columnas de consolidación
    source_node         VARCHAR(20) NOT NULL,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted             TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_payment_order FOREIGN KEY (id_order) REFERENCES customer_order (id_order),
    CONSTRAINT ck_payment_amount CHECK (amount >= 0),
    CONSTRAINT ck_payment_method CHECK (method IN ('card','transfer','cash','oxxo')),
    CONSTRAINT ck_payment_status CHECK (status IN ('pending','completed','failed','refunded'))
) ENGINE=InnoDB;

INSERT INTO payment (id_order, amount, currency, method, external_reference, status, source_node) VALUES
(1, 900.00,   'MXN', 'cash',     NULL,          'completed', 'orders_node'),
(2, 637.50,   'MXN', 'card',     'TXN-00234',   'completed', 'orders_node'),
(3, 468.00,   'MXN', 'transfer', NULL,          'pending',   'orders_node'),
(4, 1387.50,  'MXN', 'card',     'TXN-00567',   'completed', 'orders_node'),
(5, 1140.00,  'MXN', 'card',     'TXN-00812',   'refunded',  'orders_node');


-- =====================================================================
-- 8. Servicio Mensajería / Auditoría
-- =====================================================================

CREATE TABLE message_log (
    id_message        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_store          INT UNSIGNED NOT NULL, -- ref. logica a store (otro servicio: Sucursales)
    phone             VARCHAR(15) NOT NULL,
    direction         VARCHAR(8) NOT NULL,
    type              VARCHAR(20) NOT NULL,
    content           TEXT NOT NULL,
    response_time_ms  INT UNSIGNED NULL,
    -- tabla de bitácora: source_node y created_at, sin deleted
    source_node       VARCHAR(20) NOT NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ck_message_log_direction CHECK (direction IN ('inbound','outbound')),
    INDEX ix_message_log_store (id_store)
) ENGINE=InnoDB;

INSERT INTO message_log (id_store, phone, direction, type, content, response_time_ms, source_node) VALUES
(1, '6641230001', 'inbound',  'whatsapp_text', 'Buenas tardes, ¿tienen tenis talla 27?',        NULL, 'messaging_node'),
(1, '6641230001', 'outbound', 'whatsapp_text', 'Sí, tenemos disponibilidad en la tienda Tijuana', 4200, 'messaging_node'),
(4, '6641230002', 'inbound',  'webchat',       '¿Cuánto tarda el envío a Monterrey?',           NULL, 'messaging_node'),
(4, '6641230002', 'outbound', 'webchat',       'El envío tarda de 3 a 5 días hábiles',          1500, 'messaging_node'),
(5, '6621230003', 'inbound',  'sms',           '¿Cuál es su horario los domingos?',             NULL, 'messaging_node');


CREATE TABLE audit_log (
    id_audit     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_store     INT UNSIGNED NOT NULL, -- ref. logica a store (otro servicio: Sucursales)
    id_actor     INT UNSIGNED NOT NULL, -- ref. logica polimorfica a owner o employee segun actor_role
    actor_role   VARCHAR(20) NOT NULL,
    action       VARCHAR(50) NOT NULL,
    entity       VARCHAR(50) NOT NULL,
    entity_id    INT UNSIGNED NOT NULL,
    detail       JSON NULL,
    -- tabla de bitácora: source_node y created_at, sin deleted
    source_node  VARCHAR(20) NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX ix_audit_log_store (id_store),
    INDEX ix_audit_log_actor (id_actor)
) ENGINE=InnoDB;

INSERT INTO audit_log (id_store, id_actor, actor_role, action, entity, entity_id, detail, source_node) VALUES
(1, 1, 'employee', 'update_inventory',     'inventory',       2, JSON_OBJECT('old_stock', 10, 'new_stock', 3), 'messaging_node'),
(1, 1, 'owner',    'create_promotion',     'promotion',       3, JSON_OBJECT('code', 'BLACKFRIDAY'),           'messaging_node'),
(2, 3, 'employee', 'create_order',         'customer_order',  2, JSON_OBJECT('channel', 'online'),             'messaging_node'),
(5, 5, 'employee', 'process_payment',      'payment',         4, JSON_OBJECT('method', 'card'),                'messaging_node'),
(3, 1, 'owner',    'deactivate_employee',  'employee',        4, JSON_OBJECT('reason', 'licencia'),            'messaging_node');


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


-- 3) Top 5 productos más vendidos (por cantidad)
SELECT
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
ORDER BY total_units_sold DESC
LIMIT 5;


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