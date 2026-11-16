SET NOCOUNT ON;
USE laravel;

BEGIN TRANSACTION;

DECLARE @tipo BIGINT, @marca BIGINT, @modelo BIGINT, @c1 BIGINT, @c2 BIGINT;

SELECT TOP 1 @tipo = id FROM tipos_consumible ORDER BY id;
SELECT @marca = id FROM marcas WHERE nombre = N'HP';

INSERT INTO modelos_impresora (marca_id, nombre, tipo_impresion, tecnologia, created_at, updated_at)
VALUES (@marca, N'MODELO PRUEBA', 'mono', 'laser', GETDATE(), GETDATE());
SET @modelo = SCOPE_IDENTITY();

-- 1. Consumible válido: debe aceptarse
BEGIN TRY
    INSERT INTO consumibles (tipo_consumible_id, codigo, descripcion, color, rendimiento_paginas, stock_minimo, activo, created_at, updated_at)
    VALUES (@tipo, N'TEST-001', N'Consumible de prueba', 'negro', 6500, 2, 1, GETDATE(), GETDATE());
    SET @c1 = SCOPE_IDENTITY();
    PRINT 'OK    1: consumible válido aceptado';
END TRY
BEGIN CATCH
    PRINT 'FALLA 1: ' + ERROR_MESSAGE();
END CATCH;

-- 2. Código duplicado: debe rechazarse
BEGIN TRY
    INSERT INTO consumibles (tipo_consumible_id, codigo, color, stock_minimo, activo, created_at, updated_at)
    VALUES (@tipo, N'TEST-001', 'negro', 0, 1, GETDATE(), GETDATE());
    PRINT 'FALLA 2: se aceptó un código duplicado';
END TRY
BEGIN CATCH
    PRINT 'OK    2: código duplicado rechazado';
END CATCH;

-- 3. Código duplicado con otras mayúsculas: debe rechazarse
BEGIN TRY
    INSERT INTO consumibles (tipo_consumible_id, codigo, color, stock_minimo, activo, created_at, updated_at)
    VALUES (@tipo, N'test-001', 'negro', 0, 1, GETDATE(), GETDATE());
    PRINT 'FALLA 3: se aceptó un código con otras mayúsculas';
END TRY
BEGIN CATCH
    PRINT 'OK    3: código con otras mayúsculas rechazado';
END CATCH;

-- 4. Color inválido: debe rechazarse
BEGIN TRY
    INSERT INTO consumibles (tipo_consumible_id, codigo, color, stock_minimo, activo, created_at, updated_at)
    VALUES (@tipo, N'TEST-002', 'rojo', 0, 1, GETDATE(), GETDATE());
    PRINT 'FALLA 4: se aceptó un color inválido';
END TRY
BEGIN CATCH
    PRINT 'OK    4: color inválido rechazado';
END CATCH;

-- 5. Stock mínimo negativo: debe rechazarse
BEGIN TRY
    INSERT INTO consumibles (tipo_consumible_id, codigo, color, stock_minimo, activo, created_at, updated_at)
    VALUES (@tipo, N'TEST-003', 'negro', -1, 1, GETDATE(), GETDATE());
    PRINT 'FALLA 5: se aceptó un stock mínimo negativo';
END TRY
BEGIN CATCH
    PRINT 'OK    5: stock mínimo negativo rechazado';
END CATCH;

-- 6. Rendimiento cero: debe rechazarse
BEGIN TRY
    INSERT INTO consumibles (tipo_consumible_id, codigo, color, rendimiento_paginas, stock_minimo, activo, created_at, updated_at)
    VALUES (@tipo, N'TEST-004', 'negro', 0, 0, 1, GETDATE(), GETDATE());
    PRINT 'FALLA 6: se aceptó un rendimiento de cero páginas';
END TRY
BEGIN CATCH
    PRINT 'OK    6: rendimiento de cero páginas rechazado';
END CATCH;

-- 7. Reutilizar un código tras el borrado lógico: debe aceptarse
BEGIN TRY
    INSERT INTO consumibles (tipo_consumible_id, codigo, color, stock_minimo, activo, created_at, updated_at)
    VALUES (@tipo, N'TEST-005', 'cian', 0, 1, GETDATE(), GETDATE());
    SET @c2 = SCOPE_IDENTITY();
    UPDATE consumibles SET deleted_at = GETDATE() WHERE id = @c2;
    INSERT INTO consumibles (tipo_consumible_id, codigo, color, stock_minimo, activo, created_at, updated_at)
    VALUES (@tipo, N'TEST-005', 'cian', 0, 1, GETDATE(), GETDATE());
    PRINT 'OK    7: código reutilizado tras el borrado lógico';
END TRY
BEGIN CATCH
    PRINT 'FALLA 7: ' + ERROR_MESSAGE();
END CATCH;

-- 8. Compatibilidad modelo-consumible válida: debe aceptarse
BEGIN TRY
    INSERT INTO modelo_consumible (modelo_id, consumible_id) VALUES (@modelo, @c1);
    PRINT 'OK    8: compatibilidad aceptada';
END TRY
BEGIN CATCH
    PRINT 'FALLA 8: ' + ERROR_MESSAGE();
END CATCH;

-- 9. Compatibilidad duplicada: debe rechazarse
BEGIN TRY
    INSERT INTO modelo_consumible (modelo_id, consumible_id) VALUES (@modelo, @c1);
    PRINT 'FALLA 9: se aceptó una compatibilidad duplicada';
END TRY
BEGIN CATCH
    PRINT 'OK    9: compatibilidad duplicada rechazada';
END CATCH;

-- 10. Borrar un consumible con compatibilidad: debe rechazarse
BEGIN TRY
    DELETE FROM consumibles WHERE id = @c1;
    PRINT 'FALLA 10: se borró un consumible con compatibilidad';
END TRY
BEGIN CATCH
    PRINT 'OK    10: borrado de consumible con compatibilidad rechazado';
END CATCH;

-- 11. Borrar un tipo en uso: debe rechazarse
BEGIN TRY
    DELETE FROM tipos_consumible WHERE id = @tipo;
    PRINT 'FALLA 11: se borró un tipo en uso';
END TRY
BEGIN CATCH
    PRINT 'OK    11: borrado de tipo en uso rechazado';
END CATCH;

ROLLBACK TRANSACTION;
PRINT 'Prueba terminada. Se hizo ROLLBACK: no queda ningún dato de prueba.';