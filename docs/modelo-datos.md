# SGIIC — Modelo de datos (Fase 2, propuesta v1)

Estado: pendiente de aprobación. No se ha creado ninguna migración.
Motor: SQL Server, collation `Modern_Spanish_CI_AS`. Nombres de tablas, columnas y modelos en español.

## Convenciones

- Claves primarias `id` (bigint autoincremental). Claves foráneas `{entidad}_id`.
- Columnas estándar del framework en inglés: `created_at`, `updated_at`, `deleted_at`. En `users` se conservan `name`, `email` y `password`, de los que depende la autenticación de Laravel.
- Los modelos Eloquent llevan `$table` explícito, porque Laravel pluraliza en inglés.
- Sin borrado en cascada: todas las claves foráneas usan restricción. El historial se conserva y se evitan rutas de cascada múltiples de SQL Server.
- Borrado lógico (`deleted_at`) en impresoras, consumibles y proveedores. Los índices únicos de esas tablas son filtrados (`WHERE deleted_at IS NULL`), así un código o IP puede reutilizarse tras retirar el registro.
- Listas cerradas: texto con restricción CHECK. Listas editables: tabla de catálogo.

## Orden de migraciones (por dependencias)

1. Acceso (`roles` y ampliación de `users`)
2. Importaciones
3. Ubicación
4. Impresoras
5. Consumibles
6. Compras
7. Inventario
8. Monitoreo

## 1. Acceso

**roles:** `id`, `nombre` (único), `descripcion`. Valores iniciales: administrador, responsable_inventario, tecnico, consulta.

**users (ampliación):** se añade `rol_id` (FK a `roles`, obligatorio) y `activo` (bit, por defecto 1).

## 2. Importaciones

**importaciones:** `id`, `archivo_nombre`, `hoja`, `estado` (CHECK: en_proceso, completada, con_errores, fallida), `filas_leidas`, `filas_importadas`, `filas_con_error`, `usuario_id` (FK), `iniciada_en`, `finalizada_en`.

**errores_importacion:** `id`, `importacion_id` (FK), `fila`, `columna` (nulo), `valor` (nulo), `mensaje`.

## 3. Ubicación

**zonas:** `id`, `nombre` (único), `activa`.

**lugares:** `id`, `zona_id` (FK), `nombre`, `activo`. Único (`zona_id`, `nombre`).

**areas:** `id`, `lugar_id` (FK), `nombre`, `activa`. Único (`lugar_id`, `nombre`).

## 4. Impresoras

**marcas:** `id`, `nombre` (único).

**modelos_impresora:** `id`, `marca_id` (FK), `nombre`, `tipo_impresion` (CHECK: mono, color), `tecnologia` (CHECK: laser, tinta, plotter, otra), `snmp_soportado` (bit, **nulo = desconocido**, regla 12). Único (`marca_id`, `nombre`).

**estados_impresora:** `id`, `nombre` (único), `es_operativo` (bit). Valores iniciales: activa, retirada, contingencia.

**impresoras:** `id`, `nombre` (hostname), `modelo_id`, `area_id`, `estado_id` (FK), `tipo_conexion` (CHECK: red, usb), `direccion_ip` (nulo), `equipo_conectado` (nulo, solo USB), `numero_serie` (nulo), `observaciones`, marcas de tiempo y `deleted_at`.

- CHECK de conexión: si `red`, `direccion_ip` es obligatoria y `equipo_conectado` es nulo; si `usb`, `direccion_ip` es nula (regla 11).
- Índice único filtrado de `direccion_ip` donde no sea nula y `deleted_at` sea nulo. Una restricción única normal solo admitiría una impresora USB sin IP.
- Índice único filtrado de `nombre` con `deleted_at` nulo.
- El formato de la IP se valida en la aplicación y en la limpieza de la importación.

## 5. Consumibles

**tipos_consumible:** `id`, `nombre` (único). Iniciales: toner, kit de mantenimiento, fusor, unidad de transferencia, tinta, cartucho de plotter, papel.

**consumibles:** `id`, `tipo_consumible_id` (FK), `codigo`, `descripcion`, `color` (CHECK: negro, cian, magenta, amarillo, tricolor, no_aplica), `rendimiento_paginas` (nulo), `stock_minimo` (por defecto 0, base de las alertas), `activo`, marcas de tiempo y `deleted_at`. Índice único filtrado de `codigo`.

**modelo_consumible:** `modelo_id` y `consumible_id` (FK), clave primaria compuesta. Un consumible sirve a varios modelos y un modelo usa varios consumibles.

## 6. Compras

**proveedores:** `id`, `nombre`, `nit` (nulo), `contacto`, `telefono`, `correo`, `activo`, `deleted_at`. Índice único filtrado de `nombre`.

**compras:** `id`, `proveedor_id` (FK, nulo), `numero_documento`, `fecha_compra`, `estado` (CHECK: ordenada, recibida, cancelada), `moneda` (por defecto BOB), `incluye_iva` (bit), `observaciones`, `usuario_id` (FK).

**detalle_compra:** `id`, `compra_id`, `consumible_id` (FK), `cantidad` (> 0), `cantidad_recibida` (0 a `cantidad`), `precio_unitario` (decimal 12,2, ≥ 0). Único (`compra_id`, `consumible_id`).

**precios_consumible:** `id`, `consumible_id` (FK), `proveedor_id` (FK, nulo), `precio` (decimal 12,2), `moneda`, `incluye_iva`, `vigente_desde`, `vigente_hasta` (nulo, CHECK ≥ `vigente_desde`). Índice por (`consumible_id`, `vigente_desde`).

## 7. Inventario (un solo almacén)

**existencias:** `consumible_id` (clave primaria y FK), `cantidad` (**CHECK ≥ 0**), `updated_at`. Es una copia de la existencia actual que se actualiza en la misma transacción que el movimiento.

**movimientos_inventario** (libro inmutable):

| Columna | Detalle |
|---|---|
| `id` | bigint |
| `consumible_id` | FK |
| `tipo` | CHECK: entrada, salida, ajuste |
| `cantidad` | Con signo y distinta de cero. Entrada > 0, salida < 0, ajuste ≠ 0 (CHECK según el tipo) |
| `saldo_resultante` | Existencia tras el movimiento (auditoría) |
| `impresora_id` | FK, nulo (destino de una salida) |
| `compra_id` | FK, nulo (origen de una entrada) |
| `ticket` | Nulo (referencias `R-xxxxxx`) |
| `motivo` | Obligatorio si el tipo es ajuste (CHECK) |
| `usuario_id` | FK, obligatorio |
| `importacion_id` | FK, nulo |
| `fecha_movimiento` | datetime2 |
| `created_at` | Sin `updated_at`: no se edita |

- Un trigger rechaza `UPDATE` y `DELETE` sobre esta tabla. Los errores se corrigen con un ajuste (regla 9). Si prefieres no usar triggers, la inmutabilidad queda solo en la aplicación.
- Una alerta de nivel bajo nunca crea un movimiento (regla 10).
- Control: un comando comparará `existencias` con la suma de los movimientos.

## 8. Monitoreo

**motivos_sin_lectura:** `id`, `nombre` (único). Iniciales: retirado (SR) y descuento (DESC%).

**lecturas:** `id`, `impresora_id` (FK), `fecha_lectura`, `origen` (CHECK: manual, snmp, powershell, importacion), `contador_paginas` (nulo), `motivo_sin_lectura_id` (FK, nulo), `observaciones`, `usuario_id` (FK, nulo), `importacion_id` (FK, nulo), `created_at`. Índice (`impresora_id`, `fecha_lectura`). Único (`impresora_id`, `fecha_lectura`, `origen`). Si hay motivo, el contador es nulo (CHECK).

**lecturas_nivel:** `id`, `lectura_id` (FK), `tipo_consumible_id` (FK), `color` (mismo CHECK de consumibles), `nivel` (decimal 5,4, CHECK entre 0 y 1, nulo si no se pudo leer). Único (`lectura_id`, `tipo_consumible_id`, `color`). Guarda el nivel por tipo y color, sin depender del código exacto del consumible.

## Relación con el Excel (para la Fase 7)

| Columna del Excel | Destino |
|---|---|
| ZONA / LUGAR / ÁREA | `zonas`, `lugares`, `areas` |
| MARCA / MODELO | `marcas`, `modelos_impresora` |
| IP | `impresoras.direccion_ip` (`USB` y `-` pasan a tipo `usb`) |
| TONER (varios códigos) | `consumibles` y `modelo_consumible` |
| % actual | `lecturas_nivel` |
| Consumo (`SR`, `DESC%`) | `lecturas` con `motivo_sin_lectura_id` |
| Precios | `precios_consumible` |
| Notas con `R-xxxxxx` | `movimientos_inventario.ticket` |

## Datos de prueba (ficticios, regla 16)

Zonas y lugares inventados, IPs del rango reservado para documentación (`192.0.2.x`), los tres fabricantes, impresoras de red y USB, consumibles compatibles con varios modelos, movimientos de prueba, lecturas (incluida una con motivo "retirado"), los cuatro roles y un usuario administrador de desarrollo.

## Criterios de aceptación

- `php artisan migrate:fresh --seed` termina sin errores.
- Las restricciones rechazan: IP duplicada, USB con IP, red sin IP, existencia negativa, movimiento con signo incorrecto y `UPDATE`/`DELETE` en movimientos.
- `php artisan migrate:rollback` funciona.
- Este documento queda actualizado con el diagrama final.
