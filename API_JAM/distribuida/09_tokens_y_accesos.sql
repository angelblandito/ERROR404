/* =====================================================================
   JAM - Base de datos distribuida
   09. Tokens de sesión e intentos de inicio de sesión
   ---------------------------------------------------------------------
   Requisitos: db_jam.sql ejecutado.

   Solo AGREGA dos tablas; no borra ni modifica lo que ya existe:
     1. dbo.users_tokens: sesiones abiertas. El token nunca se guarda
        tal cual: en TokenHash va su SHA-256. Al cerrar sesión o cambiar
        la contraseña, la API borra el renglón y el token deja de servir.
     2. dbo.login_attempts: intentos fallidos de inicio de sesión. La
        clave es el SHA-256 de "correo + IP" (o solo de la IP), así no se
        guardan correos ni direcciones IP en claro.

   Las dos tablas viven solo en el nodo central (JAM_db): no se fragmentan,
   no se replican y no se copian al espejo de Azure. La API v3 las necesita
   para iniciar sesión.

   El usuario jam_api (script 06) ya tiene permiso sobre todo el esquema
   dbo, así que no hace falta darle permisos nuevos.

   Se puede ejecutar varias veces.
   ===================================================================== */

USE JAM_db;
GO
SET NOCOUNT ON;
GO

/* ---------------------------------------------------------------------
   1. Tokens de sesión
   --------------------------------------------------------------------- */
IF OBJECT_ID(N'dbo.users_tokens', N'U') IS NULL
CREATE TABLE dbo.users_tokens (
    TokenId          INT IDENTITY(1,1) NOT NULL,
    UserType         VARCHAR(8)    NOT NULL,
    UserId           INT           NOT NULL,
    TokenHash        VARCHAR(255)  NOT NULL,
    TokenExpiration  DATETIME2(0)  NOT NULL,
    TokenCreatedAt   DATETIME2(0)  NOT NULL CONSTRAINT df_users_tokens_created DEFAULT SYSUTCDATETIME(),
    CONSTRAINT pk_users_tokens PRIMARY KEY (TokenId),
    CONSTRAINT uq_users_tokens_hash UNIQUE (TokenHash),
    CONSTRAINT ck_users_tokens_type CHECK (UserType IN ('owner', 'employee', 'customer'))
);
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'ix_users_tokens_user' AND object_id = OBJECT_ID(N'dbo.users_tokens'))
    CREATE INDEX ix_users_tokens_user ON dbo.users_tokens (UserType, UserId);
GO

/* ---------------------------------------------------------------------
   2. Intentos fallidos de inicio de sesión
   --------------------------------------------------------------------- */
IF OBJECT_ID(N'dbo.login_attempts', N'U') IS NULL
CREATE TABLE dbo.login_attempts (
    id_attempt        INT IDENTITY(1,1) NOT NULL,
    attempt_key       VARCHAR(190)  NOT NULL,
    failures          INT           NOT NULL CONSTRAINT df_login_attempts_failures DEFAULT 0,
    first_failure_at  DATETIME2(0)  NOT NULL,
    locked_until      DATETIME2(0)  NULL,
    updated_at        DATETIME2(0)  NOT NULL CONSTRAINT df_login_attempts_updated DEFAULT SYSUTCDATETIME(),
    CONSTRAINT pk_login_attempts PRIMARY KEY (id_attempt),
    CONSTRAINT uq_login_attempts_key UNIQUE (attempt_key),
    CONSTRAINT ck_login_attempts_failures CHECK (failures >= 0)
);
GO

/* Resultado esperado: las dos tablas, vacías la primera vez */
SELECT N'users_tokens' AS tabla, COUNT(*) AS renglones FROM dbo.users_tokens
UNION ALL
SELECT N'login_attempts', COUNT(*) FROM dbo.login_attempts;
GO

