SET NOCOUNT ON;
USE laravel;

BEGIN TRANSACTION;

DECLARE @u BIGINT, @i BIGINT;
SELECT TOP 1 @u = id FROM users ORDER BY id;

BEGIN TRY
    INSERT INTO importaciones (archivo_nombre, usuario_id, iniciada_en, created_at, updated_at)
    VALUES (N'verif.xlsx', @u, GETDATE(), GETDATE(), GETDATE());
    SET @i = SCOPE_IDENTITY();
    PRINT 'OK    1: importación válida aceptada';
END TRY BEGIN CATCH PRINT 'FALLA 1: ' + ERROR_MESSAGE(); END CATCH;

BEGIN TRY
    INSERT INTO importaciones (archivo_nombre, estado, usuario_id, iniciada_en, created_at, updated_at)
    VALUES (N'verif2.xlsx', 'cancelada', @u, GETDATE(), GETDATE(), GETDATE());
    PRINT 'FALLA 2: se aceptó un estado inválido';
END TRY BEGIN CATCH PRINT 'OK    2: estado inválido rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO importaciones (archivo_nombre, filas_leidas, usuario_id, iniciada_en, created_at, updated_at)
    VALUES (N'verif3.xlsx', -1, @u, GETDATE(), GETDATE(), GETDATE());
    PRINT 'FALLA 3: se aceptó un contador de filas negativo';
END TRY BEGIN CATCH PRINT 'OK    3: contador de filas negativo rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO errores_importacion (importacion_id, fila, mensaje, created_at, updated_at)
    VALUES (@i, 5, N'IP inválida', GETDATE(), GETDATE());
    PRINT 'OK    4: error de importación válido aceptado';
END TRY BEGIN CATCH PRINT 'FALLA 4: ' + ERROR_MESSAGE(); END CATCH;

BEGIN TRY
    INSERT INTO errores_importacion (importacion_id, fila, mensaje, created_at, updated_at)
    VALUES (@i, 0, N'fila cero', GETDATE(), GETDATE());
    PRINT 'FALLA 5: se aceptó la fila 0';
END TRY BEGIN CATCH PRINT 'OK    5: fila 0 rechazada'; END CATCH;

ROLLBACK TRANSACTION;
PRINT 'Prueba terminada. Se hizo ROLLBACK: no queda ningún dato de prueba.';