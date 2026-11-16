SET NOCOUNT ON;
USE laravel;

BEGIN TRANSACTION;

DECLARE @usuario BIGINT, @zona BIGINT, @lugar BIGINT, @area BIGINT, @marca BIGINT, @estado BIGINT,
        @modelo BIGINT, @impresora BIGINT, @tipo BIGINT, @cons BIGINT, @prov BIGINT,
        @compra BIGINT, @compra2 BIGINT, @det BIGINT, @imp BIGINT, @motivo BIGINT, @lect BIGINT, @mov BIGINT;

SELECT TOP 1 @usuario = id FROM users ORDER BY id;
SELECT @marca = id FROM marcas WHERE nombre = N'HP';
SELECT @estado = id FROM estados_impresora WHERE nombre = N'activa';
SELECT TOP 1 @tipo = id FROM tipos_consumible ORDER BY id;

INSERT INTO zonas (nombre, activa, created_at, updated_at) VALUES (N'ZONA VERIF', 1, GETDATE(), GETDATE());
SET @zona = SCOPE_IDENTITY();
INSERT INTO lugares (zona_id, nombre, activo, created_at, updated_at) VALUES (@zona, N'LUGAR VERIF', 1, GETDATE(), GETDATE());
SET @lugar = SCOPE_IDENTITY();
INSERT INTO areas (lugar_id, nombre, activa, created_at, updated_at) VALUES (@lugar, N'AREA VERIF', 1, GETDATE(), GETDATE());
SET @area = SCOPE_IDENTITY();
INSERT INTO modelos_impresora (marca_id, nombre, tipo_impresion, tecnologia, created_at, updated_at)
VALUES (@marca, N'MODELO VERIF', 'mono', 'laser', GETDATE(), GETDATE());
SET @modelo = SCOPE_IDENTITY();
INSERT INTO impresoras (nombre, modelo_id, area_id, estado_id, tipo_conexion, direccion_ip, created_at, updated_at)
VALUES (N'PRN-V1', @modelo, @area, @estado, 'red', '192.0.2.200', GETDATE(), GETDATE());
SET @impresora = SCOPE_IDENTITY();
INSERT INTO consumibles (tipo_consumible_id, codigo, color, created_at, updated_at)
VALUES (@tipo, N'VER-001', 'negro', GETDATE(), GETDATE());
SET @cons = SCOPE_IDENTITY();
INSERT INTO proveedores (nombre, created_at, updated_at) VALUES (N'PROVEEDOR VERIF', GETDATE(), GETDATE());
SET @prov = SCOPE_IDENTITY();
INSERT INTO importaciones (archivo_nombre, usuario_id, iniciada_en, created_at, updated_at)
VALUES (N'verif.xlsx', @usuario, GETDATE(), GETDATE(), GETDATE());
SET @imp = SCOPE_IDENTITY();
INSERT INTO motivos_sin_lectura (nombre, created_at, updated_at) VALUES (N'MOTIVO VERIF', GETDATE(), GETDATE());
SET @motivo = SCOPE_IDENTITY();

-- ===== COMPRAS =====
BEGIN TRY
    INSERT INTO proveedores (nombre, created_at, updated_at) VALUES (N'PROVEEDOR VERIF', GETDATE(), GETDATE());
    PRINT 'FALLA 1: se aceptó un proveedor duplicado';
END TRY BEGIN CATCH PRINT 'OK    1: proveedor duplicado rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO compras (proveedor_id, usuario_id, numero_documento, fecha_orden, incluye_iva, created_at, updated_at)
    VALUES (@prov, @usuario, N'DOC-1', '2026-11-01', 1, GETDATE(), GETDATE());
    SET @compra = SCOPE_IDENTITY();
    PRINT 'OK    2: compra válida aceptada';
END TRY BEGIN CATCH PRINT 'FALLA 2: ' + ERROR_MESSAGE(); END CATCH;

BEGIN TRY
    INSERT INTO compras (proveedor_id, usuario_id, fecha_orden, estado, incluye_iva, created_at, updated_at)
    VALUES (@prov, @usuario, '2026-11-01', 'perdida', 1, GETDATE(), GETDATE());
    PRINT 'FALLA 3: se aceptó un estado de compra inválido';
END TRY BEGIN CATCH PRINT 'OK    3: estado de compra inválido rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO compras (proveedor_id, usuario_id, numero_documento, fecha_orden, incluye_iva, created_at, updated_at)
    VALUES (@prov, @usuario, N'DOC-1', '2026-11-02', 1, GETDATE(), GETDATE());
    PRINT 'FALLA 4: se aceptó un documento duplicado del mismo proveedor';
END TRY BEGIN CATCH PRINT 'OK    4: documento duplicado del mismo proveedor rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO compras (proveedor_id, usuario_id, fecha_orden, incluye_iva, created_at, updated_at)
    VALUES (@prov, @usuario, '2026-11-03', 1, GETDATE(), GETDATE());
    SET @compra2 = SCOPE_IDENTITY();
    INSERT INTO compras (proveedor_id, usuario_id, fecha_orden, incluye_iva, created_at, updated_at)
    VALUES (@prov, @usuario, '2026-11-04', 1, GETDATE(), GETDATE());
    PRINT 'OK    5: dos compras sin número de documento aceptadas';
END TRY BEGIN CATCH PRINT 'FALLA 5: ' + ERROR_MESSAGE(); END CATCH;

BEGIN TRY
    INSERT INTO detalle_compra (compra_id, consumible_id, cantidad, precio_unitario, created_at, updated_at)
    VALUES (@compra, @cons, 10, 150.00, GETDATE(), GETDATE());
    SET @det = SCOPE_IDENTITY();
    PRINT 'OK    6: detalle de compra válido aceptado';
END TRY BEGIN CATCH PRINT 'FALLA 6: ' + ERROR_MESSAGE(); END CATCH;

BEGIN TRY
    INSERT INTO detalle_compra (compra_id, consumible_id, cantidad, precio_unitario, created_at, updated_at)
    VALUES (@compra, @cons, 5, 150.00, GETDATE(), GETDATE());
    PRINT 'FALLA 7: se aceptó un consumible repetido en la compra';
END TRY BEGIN CATCH PRINT 'OK    7: consumible repetido en la compra rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO detalle_compra (compra_id, consumible_id, cantidad, precio_unitario, created_at, updated_at)
    VALUES (@compra2, @cons, 0, 150.00, GETDATE(), GETDATE());
    PRINT 'FALLA 8: se aceptó cantidad cero';
END TRY BEGIN CATCH PRINT 'OK    8: cantidad cero rechazada'; END CATCH;

BEGIN TRY
    INSERT INTO detalle_compra (compra_id, consumible_id, cantidad, cantidad_recibida, precio_unitario, created_at, updated_at)
    VALUES (@compra2, @cons, 5, 6, 150.00, GETDATE(), GETDATE());
    PRINT 'FALLA 9: se aceptó recibir más de lo pedido';
END TRY BEGIN CATCH PRINT 'OK    9: recibir más de lo pedido rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO detalle_compra (compra_id, consumible_id, cantidad, precio_unitario, created_at, updated_at)
    VALUES (@compra2, @cons, 5, -1.00, GETDATE(), GETDATE());
    PRINT 'FALLA 10: se aceptó un precio negativo';
END TRY BEGIN CATCH PRINT 'OK    10: precio negativo rechazado'; END CATCH;

-- ===== PRECIOS =====
BEGIN TRY
    INSERT INTO precios_consumible (consumible_id, proveedor_id, precio, incluye_iva, vigente_desde, created_at, updated_at)
    VALUES (@cons, @prov, 150.00, 1, '2026-01-01', GETDATE(), GETDATE());
    PRINT 'OK    11: precio válido aceptado';
END TRY BEGIN CATCH PRINT 'FALLA 11: ' + ERROR_MESSAGE(); END CATCH;

BEGIN TRY
    INSERT INTO precios_consumible (consumible_id, precio, incluye_iva, vigente_desde, vigente_hasta, created_at, updated_at)
    VALUES (@cons, 150.00, 1, '2026-06-01', '2026-05-01', GETDATE(), GETDATE());
    PRINT 'FALLA 12: se aceptó vigencia con fin anterior al inicio';
END TRY BEGIN CATCH PRINT 'OK    12: vigencia con fin anterior al inicio rechazada'; END CATCH;

BEGIN TRY
    INSERT INTO precios_consumible (consumible_id, precio, incluye_iva, vigente_desde, created_at, updated_at)
    VALUES (@cons, -5.00, 1, '2026-01-01', GETDATE(), GETDATE());
    PRINT 'FALLA 13: se aceptó un precio negativo';
END TRY BEGIN CATCH PRINT 'OK    13: precio negativo rechazado'; END CATCH;

-- ===== EXISTENCIAS Y MOVIMIENTOS =====
BEGIN TRY
    INSERT INTO existencias (consumible_id, cantidad) VALUES (@cons, 0);
    PRINT 'OK    14: fila de existencias aceptada';
END TRY BEGIN CATCH PRINT 'FALLA 14: ' + ERROR_MESSAGE(); END CATCH;

BEGIN TRY
    UPDATE existencias SET cantidad = -1 WHERE consumible_id = @cons;
    PRINT 'FALLA 15: se aceptó existencia negativa';
END TRY BEGIN CATCH PRINT 'OK    15: existencia negativa rechazada'; END CATCH;

BEGIN TRY
    INSERT INTO movimientos_inventario (consumible_id, tipo, cantidad, saldo_resultante, detalle_compra_id, usuario_id, fecha_movimiento)
    VALUES (@cons, 'entrada', 10, 10, @det, @usuario, GETDATE());
    SET @mov = SCOPE_IDENTITY();
    PRINT 'OK    16: entrada válida aceptada';
END TRY BEGIN CATCH PRINT 'FALLA 16: ' + ERROR_MESSAGE(); END CATCH;

BEGIN TRY
    INSERT INTO movimientos_inventario (consumible_id, tipo, cantidad, saldo_resultante, usuario_id, fecha_movimiento)
    VALUES (@cons, 'entrada', -5, 5, @usuario, GETDATE());
    PRINT 'FALLA 17: se aceptó una entrada negativa';
END TRY BEGIN CATCH PRINT 'OK    17: entrada negativa rechazada'; END CATCH;

BEGIN TRY
    INSERT INTO movimientos_inventario (consumible_id, tipo, cantidad, saldo_resultante, usuario_id, fecha_movimiento)
    VALUES (@cons, 'salida', 5, 15, @usuario, GETDATE());
    PRINT 'FALLA 18: se aceptó una salida positiva';
END TRY BEGIN CATCH PRINT 'OK    18: salida positiva rechazada'; END CATCH;

BEGIN TRY
    INSERT INTO movimientos_inventario (consumible_id, tipo, cantidad, saldo_resultante, usuario_id, fecha_movimiento)
    VALUES (@cons, 'merma', -1, 9, @usuario, GETDATE());
    PRINT 'FALLA 19: se aceptó un tipo de movimiento inválido';
END TRY BEGIN CATCH PRINT 'OK    19: tipo de movimiento inválido rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO movimientos_inventario (consumible_id, tipo, cantidad, saldo_resultante, impresora_id, ticket, usuario_id, fecha_movimiento)
    VALUES (@cons, 'salida', -3, 7, @impresora, N'R-000001', @usuario, GETDATE());
    PRINT 'OK    20: salida válida con impresora y ticket aceptada';
END TRY BEGIN CATCH PRINT 'FALLA 20: ' + ERROR_MESSAGE(); END CATCH;

BEGIN TRY
    INSERT INTO movimientos_inventario (consumible_id, tipo, cantidad, saldo_resultante, usuario_id, fecha_movimiento)
    VALUES (@cons, 'ajuste', 1, 8, @usuario, GETDATE());
    PRINT 'FALLA 21: se aceptó un ajuste sin motivo';
END TRY BEGIN CATCH PRINT 'OK    21: ajuste sin motivo rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO movimientos_inventario (consumible_id, tipo, cantidad, saldo_resultante, motivo, usuario_id, fecha_movimiento)
    VALUES (@cons, 'ajuste', 1, 8, N'   ', @usuario, GETDATE());
    PRINT 'FALLA 22: se aceptó un ajuste con motivo en blanco';
END TRY BEGIN CATCH PRINT 'OK    22: ajuste con motivo en blanco rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO movimientos_inventario (consumible_id, tipo, cantidad, saldo_resultante, motivo, usuario_id, fecha_movimiento)
    VALUES (@cons, 'ajuste', 1, 8, N'Conteo físico', @usuario, GETDATE());
    PRINT 'OK    23: ajuste con motivo aceptado';
END TRY BEGIN CATCH PRINT 'FALLA 23: ' + ERROR_MESSAGE(); END CATCH;

BEGIN TRY
    INSERT INTO movimientos_inventario (consumible_id, tipo, cantidad, saldo_resultante, usuario_id, fecha_movimiento)
    VALUES (@cons, 'salida', -1, -1, @usuario, GETDATE());
    PRINT 'FALLA 24: se aceptó un saldo negativo';
END TRY BEGIN CATCH PRINT 'OK    24: saldo negativo rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO movimientos_inventario (consumible_id, tipo, cantidad, saldo_resultante, detalle_compra_id, usuario_id, fecha_movimiento)
    VALUES (@cons, 'salida', -1, 7, @det, @usuario, GETDATE());
    PRINT 'FALLA 25: se aceptó un detalle de compra en una salida';
END TRY BEGIN CATCH PRINT 'OK    25: detalle de compra en una salida rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO movimientos_inventario (consumible_id, tipo, cantidad, saldo_resultante, impresora_id, usuario_id, fecha_movimiento)
    VALUES (@cons, 'entrada', 1, 9, @impresora, @usuario, GETDATE());
    PRINT 'FALLA 26: se aceptó una impresora en una entrada';
END TRY BEGIN CATCH PRINT 'OK    26: impresora en una entrada rechazada'; END CATCH;

-- ===== LECTURAS =====
BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, contador_paginas, usuario_id)
    VALUES (@impresora, '2026-11-01T10:00:00', 'manual', 1000, @usuario);
    SET @lect = SCOPE_IDENTITY();
    PRINT 'OK    27: lectura manual válida aceptada';
END TRY BEGIN CATCH PRINT 'FALLA 27: ' + ERROR_MESSAGE(); END CATCH;

BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, contador_paginas, usuario_id)
    VALUES (@impresora, '2026-11-01T10:00:00', 'manual', 1001, @usuario);
    PRINT 'FALLA 28: se aceptó una lectura duplicada';
END TRY BEGIN CATCH PRINT 'OK    28: lectura duplicada rechazada'; END CATCH;

BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, contador_paginas, usuario_id)
    VALUES (@impresora, '2026-11-02T10:00:00', 'fax', 5, @usuario);
    PRINT 'FALLA 29: se aceptó un origen inválido';
END TRY BEGIN CATCH PRINT 'OK    29: origen inválido rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, contador_paginas)
    VALUES (@impresora, '2026-11-03T10:00:00', 'manual', 5);
    PRINT 'FALLA 30: se aceptó una lectura manual sin usuario';
END TRY BEGIN CATCH PRINT 'OK    30: lectura manual sin usuario rechazada'; END CATCH;

BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, contador_paginas)
    VALUES (@impresora, '2026-11-04T10:00:00', 'importacion', 5);
    PRINT 'FALLA 31: se aceptó origen importación sin importación';
END TRY BEGIN CATCH PRINT 'OK    31: origen importación sin importación rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, contador_paginas, importacion_id)
    VALUES (@impresora, '2026-11-05T10:00:00', 'snmp', 5, @imp);
    PRINT 'FALLA 32: se aceptó una importación con origen snmp';
END TRY BEGIN CATCH PRINT 'OK    32: importación con origen snmp rechazada'; END CATCH;

BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen)
    VALUES (@impresora, '2026-11-06T10:00:00', 'snmp');
    PRINT 'OK    33: lectura sin contador ni motivo (solo niveles) aceptada';
END TRY BEGIN CATCH PRINT 'FALLA 33: ' + ERROR_MESSAGE(); END CATCH;

BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, contador_paginas, motivo_sin_lectura_id, usuario_id)
    VALUES (@impresora, '2026-11-07T10:00:00', 'manual', 10, @motivo, @usuario);
    PRINT 'FALLA 34: se aceptó motivo y contador a la vez';
END TRY BEGIN CATCH PRINT 'OK    34: motivo y contador a la vez rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, contador_paginas, usuario_id)
    VALUES (@impresora, '2026-11-08T10:00:00', 'manual', -5, @usuario);
    PRINT 'FALLA 35: se aceptó un contador negativo';
END TRY BEGIN CATCH PRINT 'OK    35: contador negativo rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO lecturas (impresora_id, fecha_lectura, origen, motivo_sin_lectura_id, usuario_id)
    VALUES (@impresora, '2026-11-09T10:00:00', 'manual', @motivo, @usuario);
    PRINT 'OK    36: lectura sin datos con motivo aceptada';
END TRY BEGIN CATCH PRINT 'FALLA 36: ' + ERROR_MESSAGE(); END CATCH;

-- ===== NIVELES =====
BEGIN TRY
    INSERT INTO lecturas_nivel (lectura_id, tipo_consumible_id, color, nivel) VALUES (@lect, @tipo, 'negro', 0.8400);
    PRINT 'OK    37: nivel válido aceptado';
END TRY BEGIN CATCH PRINT 'FALLA 37: ' + ERROR_MESSAGE(); END CATCH;

BEGIN TRY
    INSERT INTO lecturas_nivel (lectura_id, tipo_consumible_id, color, nivel) VALUES (@lect, @tipo, 'cian', 1.5000);
    PRINT 'FALLA 38: se aceptó un nivel mayor que 1';
END TRY BEGIN CATCH PRINT 'OK    38: nivel mayor que 1 rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO lecturas_nivel (lectura_id, tipo_consumible_id, color, nivel) VALUES (@lect, @tipo, 'rojo', 0.5000);
    PRINT 'FALLA 39: se aceptó un color inválido';
END TRY BEGIN CATCH PRINT 'OK    39: color inválido rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO lecturas_nivel (lectura_id, tipo_consumible_id, color, nivel) VALUES (@lect, @tipo, 'negro', 0.5000);
    PRINT 'FALLA 40: se aceptó un nivel duplicado';
END TRY BEGIN CATCH PRINT 'OK    40: nivel duplicado rechazado'; END CATCH;

BEGIN TRY
    INSERT INTO lecturas_nivel (lectura_id, tipo_consumible_id, color, nivel) VALUES (@lect, @tipo, 'magenta', NULL);
    PRINT 'OK    41: nivel nulo (no legible) aceptado';
END TRY BEGIN CATCH PRINT 'FALLA 41: ' + ERROR_MESSAGE(); END CATCH;

-- ===== TRIGGER (va al final: el THROW de un trigger puede invalidar la transacción) =====
BEGIN TRY
    UPDATE movimientos_inventario SET motivo = N'cambio no permitido' WHERE id = @mov;
    PRINT 'FALLA 42: se pudo modificar un movimiento';
END TRY BEGIN CATCH PRINT 'OK    42: modificación de movimiento rechazada (trigger)'; END CATCH;

ROLLBACK TRANSACTION;
PRINT 'Prueba terminada. Se hizo ROLLBACK: no queda ningún dato de prueba.';
GO

-- 43. El trigger también bloquea DELETE (no necesita filas ni transacción)
BEGIN TRY
    DELETE FROM movimientos_inventario WHERE 1 = 0;
    PRINT 'FALLA 43: el trigger no bloquea DELETE';
END TRY BEGIN CATCH PRINT 'OK    43: DELETE sobre movimientos rechazado (trigger)'; END CATCH;