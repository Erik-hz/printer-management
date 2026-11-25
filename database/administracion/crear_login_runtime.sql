-- Ejecutar en SSMS con SQLCMD Mode y una cuenta con permisos para crear logins.
-- Reemplace la variable solo en la ventana de consulta sin guardar el cambio.
-- Use una contraseña aleatoria de al menos 32 caracteres, con letras, números,
-- guion o guion bajo; no la guarde en este archivo ni la comparta por chat.
:setvar RuntimePassword "__GENERAR_LOCALMENTE__"
GO

USE [master];
GO

DECLARE @runtimePassword nvarchar(max) = N'$(RuntimePassword)';

IF @runtimePassword = N'__GENERAR_LOCALMENTE__'
BEGIN
    THROW 51000, 'Defina RuntimePassword localmente antes de ejecutar este script.', 1;
END;

IF EXISTS (
    SELECT 1
    FROM [laravel].[sys].[database_principals]
    WHERE name = N'sgiic_runtime'
)
BEGIN
    THROW 51004, 'El usuario sgiic_runtime ya existe en laravel; verifique su configuración antes de continuar.', 1;
END;

IF LEN(@runtimePassword) < 32 OR @runtimePassword LIKE N'%[^A-Za-z0-9_-]%'
    OR LEN(@runtimePassword) > 128
BEGIN
    THROW 51001, 'Use entre 32 y 128 caracteres del conjunto A-Z, a-z, 0-9, guion o guion bajo.', 1;
END;

IF SUSER_ID(N'sgiic_runtime') IS NOT NULL
BEGIN
    THROW 51002, 'El login sgiic_runtime ya existe; verifique su configuración antes de continuar.', 1;
END;

DECLARE @crearLogin nvarchar(max) =
    N'CREATE LOGIN [sgiic_runtime] WITH PASSWORD = '
    + QUOTENAME(@runtimePassword, '''')
    + N', CHECK_POLICY = ON, CHECK_EXPIRATION = OFF, DEFAULT_DATABASE = [laravel];';

EXEC sys.sp_executesql @crearLogin;
GO

USE [laravel];
GO

IF DATABASE_PRINCIPAL_ID(N'sgiic_runtime') IS NOT NULL
BEGIN
    THROW 51003, 'El usuario sgiic_runtime ya existe en laravel; verifique su configuración.', 1;
END;

CREATE USER [sgiic_runtime] FOR LOGIN [sgiic_runtime];
ALTER ROLE [db_datareader] ADD MEMBER [sgiic_runtime];
ALTER ROLE [db_datawriter] ADD MEMBER [sgiic_runtime];
GO
