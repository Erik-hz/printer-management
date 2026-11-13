SET NOCOUNT ON;
USE laravel;

BEGIN TRANSACTION;

DECLARE @zona BIGINT, @lugar BIGINT, @area BIGINT, @marca BIGINT, @modelo BIGINT, @estado BIGINT;

INSERT INTO zonas (nombre, activa, created_at, updated_at)
VALUES (N'ZONA PRUEBA', 1, GETDATE(), GETDATE());
SET @zona = SCOPE_IDENTITY();

INSERT INTO lugares (zona_id, nombre, activo, created_at, updated_at)
VALUES (@zona, N'LUGAR PRUEBA', 1, GETDATE(), GETDATE());
SET @lugar = SCOPE_IDENTITY();

INSERT INTO areas (lugar_id, nombre, activa, created_at, updated_at)
VALUES (@lugar, N'AREA PRUEBA', 1, GETDATE(), GETDATE());
SET @area = SCOPE_IDENTITY();

SELECT @marca = id FROM marcas WHERE nombre = N'HP';
SELECT @estado = id FROM estados_impresora WHERE nombre = N'activa';

INSERT INTO modelos_impresora (marca_id, nombre, tipo_impresion, tecnologia, snmp_soportado, created_at, updated_at)
VALUES (@marca, N'MODELO PRUEBA', 'mono', 'laser', NULL, GETDATE(), GETDATE());
SET @modelo = SCOPE_IDENTITY();

-- 1. Red con IP: debe aceptarse
BEGIN TRY
    INSERT INTO impresoras (nombre, modelo_id, area_id, estado_id, tipo_conexion, direccion_ip, created_at, updated_at)
    VALUES (N'PRN-T1', @modelo, @area, @estado, 'red', '192.0.2.10', GETDATE(), GETDATE());
    PRINT 'OK    1: impresora de red con IP aceptada';
END TRY
BEGIN CATCH
    PRINT 'FALLA 1: ' + ERROR_MESSAGE();
END CATCH;

-- 2. IP duplicada: debe rechazarse
BEGIN TRY
    INSERT INTO impresoras (nombre, modelo_id, area_id, estado_id, tipo_conexion, direccion_ip, created_at, updated_at)
    VALUES (N'PRN-T2', @modelo, @area, @estado, 'red', '192.0.2.10', GETDATE(), GETDATE());
    PRINT 'FALLA 2: se aceptó una IP duplicada';
END TRY
BEGIN CATCH
    PRINT 'OK    2: IP duplicada rechazada';
END CATCH;

-- 3. USB con IP: debe rechazarse
BEGIN TRY
    INSERT INTO impresoras (nombre, modelo_id, area_id, estado_id, tipo_conexion, direccion_ip, created_at, updated_at)
    VALUES (N'PRN-T3', @modelo, @area, @estado, 'usb', '192.0.2.11', GETDATE(), GETDATE());
    PRINT 'FALLA 3: se aceptó USB con IP';
END TRY
BEGIN CATCH
    PRINT 'OK    3: USB con IP rechazada';
END CATCH;

-- 4. Red sin IP: debe rechazarse
BEGIN TRY
    INSERT INTO impresoras (nombre, modelo_id, area_id, estado_id, tipo_conexion, direccion_ip, created_at, updated_at)
    VALUES (N'PRN-T4', @modelo, @area, @estado, 'red', NULL, GETDATE(), GETDATE());
    PRINT 'FALLA 4: se aceptó red sin IP';
END TRY
BEGIN CATCH
    PRINT 'OK    4: red sin IP rechazada';
END CATCH;

-- 5. Dos USB sin IP: deben aceptarse (el índice filtrado admite varios NULL)
BEGIN TRY
    INSERT INTO impresoras (nombre, modelo_id, area_id, estado_id, tipo_conexion, equipo_conectado, created_at, updated_at)
    VALUES (N'PRN-T5', @modelo, @area, @estado, 'usb', N'PC-01', GETDATE(), GETDATE());
    INSERT INTO impresoras (nombre, modelo_id, area_id, estado_id, tipo_conexion, created_at, updated_at)
    VALUES (N'PRN-T6', @modelo, @area, @estado, 'usb', GETDATE(), GETDATE());
    PRINT 'OK    5: dos impresoras USB sin IP aceptadas';
END TRY
BEGIN CATCH
    PRINT 'FALLA 5: ' + ERROR_MESSAGE();
END CATCH;

-- 6. Tipo de conexión inválido: debe rechazarse
BEGIN TRY
    INSERT INTO impresoras (nombre, modelo_id, area_id, estado_id, tipo_conexion, direccion_ip, created_at, updated_at)
    VALUES (N'PRN-T7', @modelo, @area, @estado, 'wifi', '192.0.2.12', GETDATE(), GETDATE());
    PRINT 'FALLA 6: se aceptó un tipo de conexión inválido';
END TRY
BEGIN CATCH
    PRINT 'OK    6: tipo de conexión inválido rechazado';
END CATCH;

-- 7. Red con equipo conectado: debe rechazarse
BEGIN TRY
    INSERT INTO impresoras (nombre, modelo_id, area_id, estado_id, tipo_conexion, direccion_ip, equipo_conectado, created_at, updated_at)
    VALUES (N'PRN-T8', @modelo, @area, @estado, 'red', '192.0.2.13', N'PC-02', GETDATE(), GETDATE());
    PRINT 'FALLA 7: se aceptó red con equipo conectado';
END TRY
BEGIN CATCH
    PRINT 'OK    7: red con equipo conectado rechazada';
END CATCH;

-- 8. Nombre duplicado: debe rechazarse
BEGIN TRY
    INSERT INTO impresoras (nombre, modelo_id, area_id, estado_id, tipo_conexion, created_at, updated_at)
    VALUES (N'PRN-T5', @modelo, @area, @estado, 'usb', GETDATE(), GETDATE());
    PRINT 'FALLA 8: se aceptó un nombre duplicado';
END TRY
BEGIN CATCH
    PRINT 'OK    8: nombre duplicado rechazado';
END CATCH;

-- 9. Reutilizar IP y nombre tras el borrado lógico: debe aceptarse
BEGIN TRY
    UPDATE impresoras SET deleted_at = GETDATE() WHERE nombre = N'PRN-T1';
    INSERT INTO impresoras (nombre, modelo_id, area_id, estado_id, tipo_conexion, direccion_ip, created_at, updated_at)
    VALUES (N'PRN-T1', @modelo, @area, @estado, 'red', '192.0.2.10', GETDATE(), GETDATE());
    PRINT 'OK    9: IP y nombre reutilizados tras el borrado lógico';
END TRY
BEGIN CATCH
    PRINT 'FALLA 9: ' + ERROR_MESSAGE();
END CATCH;

-- 10. Tipo de impresión inválido en el modelo: debe rechazarse
BEGIN TRY
    INSERT INTO modelos_impresora (marca_id, nombre, tipo_impresion, tecnologia, created_at, updated_at)
    VALUES (@marca, N'MODELO MALO', 'bn', 'laser', GETDATE(), GETDATE());
    PRINT 'FALLA 10: se aceptó un tipo de impresión inválido';
END TRY
BEGIN CATCH
    PRINT 'OK    10: tipo de impresión inválido rechazado';
END CATCH;

-- 11. Borrar una marca en uso: debe rechazarse (NO ACTION)
BEGIN TRY
    DELETE FROM marcas WHERE id = @marca;
    PRINT 'FALLA 11: se borró una marca en uso';
END TRY
BEGIN CATCH
    PRINT 'OK    11: borrado de marca en uso rechazado';
END CATCH;

ROLLBACK TRANSACTION;
PRINT 'Prueba terminada. Se hizo ROLLBACK: no queda ningún dato de prueba.';