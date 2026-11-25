/*
 * Exportación de solo lectura para el snapshot de monitoreo.
 * Ejecutar en SSMS sobre la base laravel y exportar el resultado a la hoja
 * Monitoreo_SNMP de database/exports/plantilla_snapshot_monitoreo.xlsx.
 * Devuelve hasta 30 lecturas SNMP/Powershell por impresora no eliminada.
 */
WITH lecturas_recientes AS (
    SELECT
        l.id,
        l.impresora_id,
        l.fecha_lectura,
        l.origen,
        l.contador_paginas,
        l.motivo_sin_lectura_id,
        l.observaciones,
        ROW_NUMBER() OVER (
            PARTITION BY l.impresora_id
            ORDER BY l.fecha_lectura DESC, l.id DESC
        ) AS numero_reciente
    FROM lecturas AS l
    WHERE l.origen IN ('snmp', 'powershell')
)
SELECT
    CONVERT(varchar(33), SYSUTCDATETIME(), 126) AS exportado_en_utc,
    i.id AS impresora_id,
    i.nombre AS impresora,
    i.direccion_ip,
    ma.nombre AS marca,
    mo.nombre AS modelo,
    ei.nombre AS estado,
    z.nombre AS zona,
    lu.nombre AS lugar,
    a.nombre AS area,
    l.id AS lectura_id,
    CONVERT(varchar(33), l.fecha_lectura, 126) AS fecha_lectura,
    l.origen,
    l.contador_paginas,
    ms.nombre AS motivo_sin_lectura,
    ln.tipo_consumible_id,
    tc.nombre AS tipo_consumible,
    ln.color,
    CAST(ln.nivel AS decimal(6, 4)) AS nivel,
    CAST(ln.nivel * 100 AS decimal(6, 2)) AS porcentaje,
    l.observaciones
FROM impresoras AS i
LEFT JOIN modelos_impresora AS mo ON mo.id = i.modelo_id
LEFT JOIN marcas AS ma ON ma.id = mo.marca_id
LEFT JOIN estados_impresora AS ei ON ei.id = i.estado_id
LEFT JOIN areas AS a ON a.id = i.area_id
LEFT JOIN lugares AS lu ON lu.id = a.lugar_id
LEFT JOIN zonas AS z ON z.id = lu.zona_id
LEFT JOIN lecturas_recientes AS l
    ON l.impresora_id = i.id
   AND l.numero_reciente <= 30
LEFT JOIN motivos_sin_lectura AS ms ON ms.id = l.motivo_sin_lectura_id
LEFT JOIN lecturas_nivel AS ln ON ln.lectura_id = l.id
LEFT JOIN tipos_consumible AS tc ON tc.id = ln.tipo_consumible_id
WHERE i.deleted_at IS NULL
ORDER BY i.id, l.fecha_lectura DESC, l.id DESC, ln.id;
