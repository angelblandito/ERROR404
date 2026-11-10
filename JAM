USE master;
GO

DROP DATABASE IF EXISTS jam;
GO

CREATE DATABASE jam;
GO

USE jam;
GO

CREATE TABLE sucursales (
    id_sucursal INT IDENTITY(1,1) PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    direccion VARCHAR(MAX),
    telefono VARCHAR(20)
);

CREATE TABLE categorias (
    id_categoria INT IDENTITY(1,1) PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    descripcion VARCHAR(MAX)
);

CREATE TABLE proveedores (
    id_proveedor INT IDENTITY(1,1) PRIMARY KEY,
    nombre_empresa VARCHAR(100) NOT NULL,
    contacto VARCHAR(50),
    telefono VARCHAR(20)
);

CREATE TABLE metodos_pago (
    id_metodo INT IDENTITY(1,1) PRIMARY KEY,
    metodo VARCHAR(50) NOT NULL
);

CREATE TABLE productos (
    id_producto INT IDENTITY(1,1) PRIMARY KEY,
    id_categoria INT FOREIGN KEY REFERENCES categorias(id_categoria),
    id_proveedor INT FOREIGN KEY REFERENCES proveedores(id_proveedor),
    codigo_sku VARCHAR(20) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(MAX),
    precio_venta DECIMAL(10,2) NOT NULL
);

CREATE TABLE inventario_sucursales (
    id_inventario INT IDENTITY(1,1) PRIMARY KEY,
    id_sucursal INT FOREIGN KEY REFERENCES sucursales(id_sucursal),
    id_producto INT FOREIGN KEY REFERENCES productos(id_producto),
    stock INT NOT NULL DEFAULT 0
);

CREATE TABLE empleados (
    id_empleado INT IDENTITY(1,1) PRIMARY KEY,
    id_sucursal INT FOREIGN KEY REFERENCES sucursales(id_sucursal),
    nombre_completo VARCHAR(100) NOT NULL,
    puesto VARCHAR(50),
    fecha_contratacion DATE
);

CREATE TABLE clientes (
    id_cliente INT IDENTITY(1,1) PRIMARY KEY,
    nombre_completo VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    telefono VARCHAR(20),
    direccion_default VARCHAR(MAX)
);

CREATE TABLE ventas (
    id_venta INT IDENTITY(1,1) PRIMARY KEY,
    id_sucursal INT FOREIGN KEY REFERENCES sucursales(id_sucursal),
    id_cliente INT FOREIGN KEY REFERENCES clientes(id_cliente),
    id_empleado INT NULL FOREIGN KEY REFERENCES empleados(id_empleado),
    id_metodo INT FOREIGN KEY REFERENCES metodos_pago(id_metodo),
    fecha_venta DATETIME DEFAULT GETDATE(),
    total DECIMAL(10,2) NOT NULL
);

CREATE TABLE detalles_venta (
    id_detalle INT IDENTITY(1,1) PRIMARY KEY,
    id_venta INT FOREIGN KEY REFERENCES ventas(id_venta),
    id_producto INT FOREIGN KEY REFERENCES productos(id_producto),
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL
);

CREATE TABLE envios (
    id_envio INT IDENTITY(1,1) PRIMARY KEY,
    id_venta INT FOREIGN KEY REFERENCES ventas(id_venta),
    direccion_entrega VARCHAR(MAX) NOT NULL,
    estado_envio VARCHAR(30) DEFAULT 'En Preparación',
    numero_guia VARCHAR(50)
);

CREATE TABLE compras_proveedores (
    id_compra INT IDENTITY(1,1) PRIMARY KEY,
    id_proveedor INT FOREIGN KEY REFERENCES proveedores(id_proveedor),
    id_sucursal INT FOREIGN KEY REFERENCES sucursales(id_sucursal),
    fecha_pedido DATE,
    total_compra DECIMAL(10,2),
    estado VARCHAR(30) DEFAULT 'Pendiente'
);
USE jam;
GO

-- 1. Tabla de Proveedores (Surtidores de marcas deportivas)
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'Proveedores')
BEGIN
    CREATE TABLE Proveedores (
        proveedor_id INT IDENTITY(1,1) PRIMARY KEY,
        nombre_empresa VARCHAR(100) NOT NULL,
        contacto_nombre VARCHAR(100),
        email VARCHAR(100) UNIQUE,
        telefono VARCHAR(20),
        rfc VARCHAR(15),
        direccion VARCHAR(255),
        fecha_registro DATETIME DEFAULT GETDATE()
    );
END
GO

-- 2. Tabla de Órdenes de Compra (Reabastecimiento de inventario)
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'Ordenes_Compra')
BEGIN
    CREATE TABLE Ordenes_Compra (
        orden_compra_id INT IDENTITY(1,1) PRIMARY KEY,
        proveedor_id INT NOT NULL,
        fecha_orden DATETIME DEFAULT GETDATE(),
        total_orden DECIMAL(10,2) NOT NULL,
        estado VARCHAR(30) DEFAULT 'Pendiente', -- Pendiente, Recibido, Cancelado
        CONSTRAINT FK_OrdenesCompra_Proveedores FOREIGN KEY (proveedor_id) 
            REFERENCES Proveedores(proveedor_id)
    );
END
GO

-- 3. Tabla de Cupones de Descuento (Módulo de Marketing/Ventas)
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'Cupones_Descuento')
BEGIN
    CREATE TABLE Cupones_Descuento (
        cupon_id INT IDENTITY(1,1) PRIMARY KEY,
        codigo VARCHAR(30) UNIQUE NOT NULL,
        descuento_porcentaje DECIMAL(5,2) NOT NULL, -- ej: 15.00 para 15%
        fecha_inicio DATETIME NOT NULL,
        fecha_fin DATETIME NOT NULL,
        activo BIT DEFAULT 1
    );
END
GO

-- 4. Tabla de Reseñas de Productos (Feedback de clientes)
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'Resenas_Productos')
BEGIN
    CREATE TABLE Resenas_Productos (
        resena_id INT IDENTITY(1,1) PRIMARY KEY,
        producto_id INT NOT NULL,
        cliente_id INT NOT NULL,
        calificacion INT CHECK (calificacion BETWEEN 1 AND 5),
        comentario VARCHAR(MAX),
        fecha_resena DATETIME DEFAULT GETDATE(),
        CONSTRAINT FK_Resenas_Productos FOREIGN KEY (producto_id) 
            REFERENCES Productos(producto_id),
        CONSTRAINT FK_Resenas_Clientes FOREIGN KEY (cliente_id) 
            REFERENCES Clientes(cliente_id)
    );
END
GO

-- 5. Tabla de Devoluciones (Garantías y Soporte Post-Venta)
IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = 'Devoluciones')
BEGIN
    CREATE TABLE Devoluciones (
        devolucion_id INT IDENTITY(1,1) PRIMARY KEY,
        venta_id INT NOT NULL,
        cliente_id INT NOT NULL,
        motivo VARCHAR(255) NOT NULL,
        monto_reembolsado DECIMAL(10,2) NOT NULL,
        estado_devolucion VARCHAR(30) DEFAULT 'En Revision', -- En Revision, Aprobada, Rechazada
        fecha_solicitud DATETIME DEFAULT GETDATE(),
        CONSTRAINT FK_Devoluciones_Ventas FOREIGN KEY (venta_id) 
            REFERENCES Ventas(venta_id),
        CONSTRAINT FK_Devoluciones_Clientes FOREIGN KEY (cliente_id) 
            REFERENCES Clientes(cliente_id)
    );
END
GO

-- Inserción de Datos Iniciales
INSERT INTO sucursales (nombre, tipo, direccion, telefono) VALUES 
('JAM E-commerce', 'Online', 'Almacén Central', '800-JAM-WEB'),
('JAM Tijuana Macroplaza', 'Fisica', 'Blvd. Insurgentes, Tijuana', '664-111-2222');

INSERT INTO categorias (nombre) VALUES ('Calzado'), ('Ropa Superior'), ('Ropa Inferior'), ('Accesorios');

INSERT INTO proveedores (nombre_empresa, contacto) VALUES ('Nike Distribuidora', 'Arturo V.'), ('Dasking Sports', 'Laura P.');

INSERT INTO metodos_pago (metodo) VALUES ('Tarjeta de Crédito'), ('Efectivo'), ('PayPal');

INSERT INTO productos (id_categoria, id_proveedor, codigo_sku, nombre, precio_venta) VALUES 
(4, 2, 'ACC-DSK-01', 'Calcetas de Fútbol Antideslizantes', 250.00),
(2, 1, 'ROP-NIK-01', 'Playera Dry-Fit Sprint', 450.00),
(1, 1, 'CAL-NIK-01', 'Tenis Running Asfalto', 1850.00);

INSERT INTO inventario_sucursales (id_sucursal, id_producto, stock) VALUES (1, 1, 150), (1, 2, 80), (1, 3, 40);

INSERT INTO empleados (id_sucursal, nombre_completo, puesto, fecha_contratacion) VALUES (2, 'Roberto Díaz', 'Gerente', '2026-03-15');

INSERT INTO clientes (nombre_completo, email, telefono) VALUES ('Jorge Escamilla', 'jorge@email.com', '664-555-9090');

INSERT INTO ventas (id_sucursal, id_cliente, id_empleado, id_metodo, total) VALUES (2, 1, 1, 2, 450.00);
INSERT INTO detalles_venta (id_venta, id_producto, cantidad, precio_unitario, subtotal) VALUES (1, 2, 1, 450.00, 450.00);