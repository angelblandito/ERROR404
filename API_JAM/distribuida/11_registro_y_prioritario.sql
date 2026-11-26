/* =====================================================================
   JAM - Base de datos distribuida
   11. Registro con confirmación por correo y regla de Prioritario
   ---------------------------------------------------------------------
   Requisitos: db_jam.sql y 09_tokens_y_accesos.sql ejecutados.

   Solo AGREGA o ajusta; no borra datos:
     1. dbo.customer_signup: registros que esperan la confirmación del
        correo. La cuenta (dbo.customer) se crea hasta que la persona
        abre el enlace. Del enlace solo se guarda su SHA-256.
     2. dbo.email_outbox acepta los correos nuevos: confirmación de
        registro, ascenso a Prioritario y reporte urgente de soporte.
     3. dbo.sp_evaluar_clientes_prioritarios con la regla vigente (la
        misma del 06, para las bases donde el 06 ya se ejecutó con la
        regla anterior): más de $50,000 en compras (pedidos pagados,
        enviados o entregados) y más de un año con la cuenta.

   Se puede ejecutar varias veces.
   ===================================================================== */

USE JAM_db;
GO
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------
   1. Registros pendientes de confirmar
   --------------------------------------------------------------------- */
IF OBJECT_ID(N'dbo.customer_signup', N'U') IS NULL
CREATE TABLE dbo.customer_signup (
    id_signup     INT IDENTITY(1,1) NOT NULL,
    token_hash    CHAR(64)       NOT NULL,   -- SHA-256 del enlace del correo
    email         VARCHAR(100)   NOT NULL,
    phone         VARCHAR(15)    NOT NULL,
    first_name    NVARCHAR(50)   NOT NULL,
    payload       NVARCHAR(MAX)  NOT NULL,   -- datos del formulario (contraseña ya cifrada); se vacía al confirmar
    expires_at    DATETIME2(3)   NOT NULL,
    confirmed_at  DATETIME2(3)   NULL,
    id_customer   INT            NULL,       -- la cuenta creada al confirmar
    created_at    DATETIME2(3)   NOT NULL CONSTRAINT df_customer_signup_created DEFAULT SYSUTCDATETIME(),
    source_node   VARCHAR(20)    NOT NULL CONSTRAINT df_customer_signup_node DEFAULT 'web_node',
    CONSTRAINT pk_customer_signup PRIMARY KEY (id_signup),
    CONSTRAINT uq_customer_signup_token UNIQUE (token_hash),
    CONSTRAINT fk_customer_signup_customer FOREIGN KEY (id_customer) REFERENCES dbo.customer (id_customer)
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'ix_customer_signup_email' AND object_id = OBJECT_ID(N'dbo.customer_signup'))
    CREATE INDEX ix_customer_signup_email ON dbo.customer_signup (email, created_at);
GO

/* ---------------------------------------------------------------------
   2. Tipos de correo nuevos
   --------------------------------------------------------------------- */
IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = N'ck_email_outbox_kind' AND parent_object_id = OBJECT_ID(N'dbo.email_outbox'))
    ALTER TABLE dbo.email_outbox DROP CONSTRAINT ck_email_outbox_kind;
GO
ALTER TABLE dbo.email_outbox WITH CHECK ADD CONSTRAINT ck_email_outbox_kind
    CHECK (kind IN ('welcome', 'order_created', 'order_status', 'password_reset',
                    'signup_confirm', 'tier_upgrade', 'ticket_urgent'));
GO

/* ---------------------------------------------------------------------
   3. Regla para ser cliente Prioritario
   --------------------------------------------------------------------- */
CREATE OR ALTER PROCEDURE dbo.sp_evaluar_clientes_prioritarios
    @dias_minimos  INT           = 365,       -- más de un año con la cuenta
    @monto_minimo  DECIMAL(12,2) = 50000.00   -- más de $50,000 comprados
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @prioritario TINYINT = (SELECT id_account_tier FROM dbo.account_tier WHERE code = 'priority'),
            @estandar    TINYINT = (SELECT id_account_tier FROM dbo.account_tier WHERE code = 'standard');

    DECLARE @promovidos TABLE (id_customer INT NOT NULL PRIMARY KEY);

    UPDATE c
    SET c.id_account_tier = @prioritario,
        c.updated_at = SYSUTCDATETIME()
    OUTPUT inserted.id_customer INTO @promovidos (id_customer)
    FROM dbo.customer AS c
    WHERE c.deleted = 0
      AND c.id_account_tier = @estandar
      AND DATEDIFF(DAY, c.customer_since, CAST(SYSUTCDATETIME() AS DATE)) > @dias_minimos
      AND (SELECT ISNULL(SUM(co.total), 0)
           FROM dbo.customer_order AS co
           WHERE co.id_customer = c.id_customer
             AND co.deleted = 0
             AND co.status IN ('paid', 'shipped', 'delivered')) > @monto_minimo;

    SELECT c.id_customer, c.first_name, c.last_name_paternal, c.email
    FROM @promovidos AS p
    JOIN dbo.customer AS c ON c.id_customer = p.id_customer
    ORDER BY c.id_customer;
END;
GO

/* Resultado esperado: la tabla nueva (vacía la primera vez) y la regla del procedimiento */
SELECT N'customer_signup' AS tabla, COUNT(*) AS renglones FROM dbo.customer_signup;
SELECT definition AS tipos_de_correo
FROM sys.check_constraints WHERE name = N'ck_email_outbox_kind';
GO
