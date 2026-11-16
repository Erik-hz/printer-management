SET NOCOUNT ON;
SET XACT_ABORT OFF;
USE laravel;

IF OBJECT_ID(N'dbo.motivos_sin_lectura', N'U') IS NULL
   OR OBJECT_ID(N'dbo.lecturas', N'U') IS NULL
   OR OBJECT_ID(N'dbo.lecturas_nivel', N'U') IS NULL
BEGIN
    THROW 50000, 'Faltan las tablas de Monitoreo. Ejecuta primero php artisan migrate en esta base.', 1;
END;

BEGIN TRANSACTION;

DECLARE @sufijo NVARCHAR(36) = CONVERT(NVARCHAR(36), NEWID());
DECLARE @ahora DATETIME2(0) = CONVERT(DATETIME2(0), SYSUTCDATETIME());
DECLARE @fecha DATETIME2(0) = DATEADD(SECOND, 10, @ahora);
DECLARE @rol BIGINT, @usuario BIGINT, @zona BIGINT, @lugar BIGINT, @area BIGINT;
DECLARE @marca BIGINT, @modelo BIGINT, @estado BIGINT, @impresora BIGINT;
DECLARE @tipo BIGINT, @motivo BIGINT, @lectura BIGINT;

INSERT INTO roles (nombre, descripcion, created_at, updated_at)
VALUES (N'rol-monitoreo-' + @sufijo, N'Prueba temporal', @ahora, @ahora);
SET @rol = SCOPE_IDENTITY();

INSERT INTO users (name, email, password, rol_id, activo, created_at, updated_at)
VALUES (
    N'Usuario de verificación',
    N'monitoreo-' + @sufijo + N'@example.test',
    CONVERT(VARCHAR(64), HASHBYTES('SHA2_256', 'verification-only'), 2),
    @rol,
    1,
    @ahora,
    @ahora
);
SET @usuario = SCOPE_IDENTITY();

INSERT INTO zonas (nombre, activa, created_at, updated_at)
VALUES (N'zona-monitoreo-' + @sufijo, 1, @ahora, @ahora);
SET @zona = SCOPE_IDENTITY();

INSERT INTO lugares (zona_id, nombre, activo, created_at, updated_at)
VALUES (@zona, N'lugar-monitoreo-' + @sufijo, 1, @ahora, @ahora);
SET @lugar = SCOPE_IDENTITY();

INSERT INTO areas (lugar_id, nombre, activa, created_at, updated_at)
VALUES (@lugar, N'area-monitoreo-' + @sufijo, 1, @ahora, @ahora);
SET @area = SCOPE_IDENTITY();

INSERT INTO marcas (nombre, created_at, updated_at)
VALUES (N'm-' + @sufijo, @ahora, @ahora);
SET @marca = SCOPE_IDENTITY();

INSERT INTO modelos_impresora (marca_id, nombre, tipo_impresion, tecnologia, snmp_soportado, created_at, updated_at)
VALUES (@marca, N'modelo-monitoreo-' + @sufijo, 'mono', 'laser', NULL, @ahora, @ahora);
SET @modelo = SCOPE_IDENTITY();

INSERT INTO estados_impresora (nombre, es_operativo, created_at, updated_at)
VALUES (N'e-' + @sufijo, 1, @ahora, @ahora);
SET @estado = SCOPE_IDENTITY();

INSERT INTO impresoras (nombre, modelo_id, area_id, estado_id, tipo_conexion, created_at, updated_at)
VALUES (N'PRN-MONITOREO-' + @sufijo, @modelo, @area, @estado, 'usb', @ahora, @ahora);
SET @impresora = SCOPE_IDENTITY();

INSERT INTO tipos_consumible (nombre, created_at, updated_at)
VALUES (N'tipo-monitoreo-' + @sufijo, @ahora, @ahora);
SET @tipo = SCOPE_IDENTITY();

INSERT INTO dbo.motivos_sin_lectura (nombre, created_at, updated_at)
VALUES (N'motivo-monitoreo-' + @sufijo, @ahora, @ahora);
SET @motivo = SCOPE_IDENTITY();

-- Una lectura manual válida con contador cero debe aceptarse.
BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, contador_paginas, usuario_id, created_at)
    VALUES (@impresora, @fecha, 'manual', 0, @usuario, @ahora);
    SET @lectura = SCOPE_IDENTITY();
    PRINT 'OK    1: lectura manual con contador cero aceptada';
END TRY
BEGIN CATCH
    PRINT 'FALLA 1: ' + ERROR_MESSAGE();
END CATCH;

-- Un contador negativo debe rechazarse.
BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, contador_paginas, usuario_id, created_at)
    VALUES (@impresora, DATEADD(SECOND, 1, @fecha), 'manual', -1, @usuario, @ahora);
    PRINT 'FALLA 2: se aceptó un contador negativo';
END TRY
BEGIN CATCH
    PRINT 'OK    2: contador negativo rechazado';
END CATCH;

-- Debe existir contador o motivo, pero no ambos ni ninguno.
BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, usuario_id, created_at)
    VALUES (@impresora, DATEADD(SECOND, 2, @fecha), 'manual', @usuario, @ahora);
    PRINT 'FALLA 3: se aceptó una lectura sin contador ni motivo';
END TRY
BEGIN CATCH
    PRINT 'OK    3: lectura sin contador ni motivo rechazada';
END CATCH;

BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, contador_paginas, motivo_sin_lectura_id, usuario_id, created_at)
    VALUES (@impresora, DATEADD(SECOND, 3, @fecha), 'manual', 1, @motivo, @usuario, @ahora);
    PRINT 'FALLA 4: se aceptó contador junto con motivo';
END TRY
BEGIN CATCH
    PRINT 'OK    4: contador junto con motivo rechazado';
END CATCH;

-- Manual requiere usuario; importación requiere importacion_id.
BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, contador_paginas, created_at)
    VALUES (@impresora, DATEADD(SECOND, 4, @fecha), 'manual', 1, @ahora);
    PRINT 'FALLA 5: se aceptó una lectura manual sin usuario';
END TRY
BEGIN CATCH
    PRINT 'OK    5: lectura manual sin usuario rechazada';
END CATCH;

BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, contador_paginas, created_at)
    VALUES (@impresora, DATEADD(SECOND, 5, @fecha), 'importacion', 1, @ahora);
    PRINT 'FALLA 6: se aceptó importación sin lote';
END TRY
BEGIN CATCH
    PRINT 'OK    6: importación sin lote rechazada';
END CATCH;

-- Origen fuera del catálogo cerrado debe rechazarse.
BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, contador_paginas, created_at)
    VALUES (@impresora, DATEADD(SECOND, 6, @fecha), 'web', 1, @ahora);
    PRINT 'FALLA 7: se aceptó un origen inválido';
END TRY
BEGIN CATCH
    PRINT 'OK    7: origen inválido rechazado';
END CATCH;

-- La combinación impresora/fecha/origen debe ser única.
BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, contador_paginas, usuario_id, created_at)
    VALUES (@impresora, @fecha, 'manual', 1, @usuario, @ahora);
    PRINT 'FALLA 8: se aceptó una lectura duplicada';
END TRY
BEGIN CATCH
    PRINT 'OK    8: lectura duplicada rechazada';
END CATCH;

-- Nivel entre cero y uno válido; fuera del rango o color desconocido, no.
BEGIN TRY
    INSERT INTO lecturas_nivel (lectura_id, tipo_consumible_id, color, nivel)
    VALUES (@lectura, @tipo, 'negro', 0.0000);
    PRINT 'OK    9: nivel cero aceptado';
END TRY
BEGIN CATCH
    PRINT 'FALLA 9: ' + ERROR_MESSAGE();
END CATCH;

BEGIN TRY
    INSERT INTO lecturas_nivel (lectura_id, tipo_consumible_id, color, nivel)
    VALUES (@lectura, @tipo, 'cian', 1.0000);
    PRINT 'OK    10: nivel uno aceptado';
END TRY
BEGIN CATCH
    PRINT 'FALLA 10: ' + ERROR_MESSAGE();
END CATCH;

BEGIN TRY
    INSERT INTO lecturas_nivel (lectura_id, tipo_consumible_id, color, nivel)
    VALUES (@lectura, @tipo, 'magenta', 1.0001);
    PRINT 'FALLA 11: se aceptó un nivel superior a uno';
END TRY
BEGIN CATCH
    PRINT 'OK    11: nivel superior a uno rechazado';
END CATCH;

BEGIN TRY
    INSERT INTO lecturas_nivel (lectura_id, tipo_consumible_id, color, nivel)
    VALUES (@lectura, @tipo, 'rojo', 0.5000);
    PRINT 'FALLA 12: se aceptó un color inválido';
END TRY
BEGIN CATCH
    PRINT 'OK    12: color inválido rechazado';
END CATCH;

BEGIN TRY
    INSERT INTO lecturas_nivel (lectura_id, tipo_consumible_id, color, nivel)
    VALUES (@lectura, @tipo, 'negro', 0.5000);
    PRINT 'FALLA 13: se aceptó un nivel duplicado para lectura/tipo/color';
END TRY
BEGIN CATCH
    PRINT 'OK    13: nivel duplicado rechazado';
END CATCH;

-- Una impresora referenciada por lecturas no puede eliminarse físicamente.
BEGIN TRY
    DELETE FROM impresoras WHERE id = @impresora;
    PRINT 'FALLA 14: se eliminó una impresora con lecturas';
END TRY
BEGIN CATCH
    PRINT 'OK    14: borrado de impresora con lecturas rechazado';
END CATCH;

ROLLBACK TRANSACTION;
PRINT 'Pruebas terminadas. Se hizo ROLLBACK: no queda ningún dato de prueba.';
