# SGIIC — Diagrama entidad-relación (Fase 2)

Refleja las migraciones verificadas. Las relaciones muestran la cardinalidad: `||` uno obligatorio, `|o` uno opcional, `o{` muchos.

```mermaid
erDiagram
    roles ||--o{ users : "tiene"
    users ||--o{ importaciones : "ejecuta"
    importaciones ||--o{ errores_importacion : "registra"

    zonas ||--o{ lugares : "contiene"
    lugares ||--o{ areas : "contiene"
    areas ||--o{ impresoras : "ubica"

    marcas ||--o{ modelos_impresora : "fabrica"
    modelos_impresora ||--o{ impresoras : "es de"
    estados_impresora ||--o{ impresoras : "estado"

    tipos_consumible ||--o{ consumibles : "clasifica"
    modelos_impresora }o--o{ consumibles : "modelo_consumible"

    consumibles ||--|| existencias : "existencia actual"
    consumibles ||--o{ movimientos_inventario : "movimientos"
    users ||--o{ movimientos_inventario : "registra"
    impresoras |o--o{ movimientos_inventario : "destino de salida"
    detalle_compra |o--o{ movimientos_inventario : "origen de entrada"
    importaciones |o--o{ movimientos_inventario : "origen"

    proveedores ||--o{ compras : "vende"
    users ||--o{ compras : "ordena"
    compras ||--o{ detalle_compra : "detalla"
    consumibles ||--o{ detalle_compra : "se compra"
    consumibles ||--o{ precios_consumible : "precio"
    proveedores |o--o{ precios_consumible : "ofrece"

    impresoras ||--o{ lecturas : "se lee"
    motivos_sin_lectura |o--o{ lecturas : "explica"
    users |o--o{ lecturas : "registra"
    importaciones |o--o{ lecturas : "origen"
    lecturas ||--o{ lecturas_nivel : "niveles"
    tipos_consumible ||--o{ lecturas_nivel : "componente"
```

## Diferencias respecto a la propuesta v1 (`modelo-datos.md`)

| Propuesta v1 | Implementado | Motivo |
|---|---|---|
| `movimientos_inventario.compra_id` | `detalle_compra_id` | Permite recepciones parciales por consumible |
| `compras.fecha_compra` | `fecha_orden` | Es la fecha de la orden, no de la recepción |
| `compras.estado`: ordenada, recibida, cancelada | Añade `parcialmente_recibida` | Recepciones parciales |
| `compras.proveedor_id` opcional | Obligatorio | Toda compra real tiene proveedor |
| `lecturas.usuario_id` opcional | Obligatorio si el origen es `manual` | Trazabilidad de quién registró |
| `lecturas`: si hay motivo, el contador es nulo | Además el contador puede ser nulo sin motivo | Regla 12: no todos los modelos entregan contador |
| Índice único de documento de compra: no previsto | Único (`proveedor_id`, `numero_documento`) cuando hay número | Evita duplicar órdenes |
| `ON DELETE RESTRICT` | `NO ACTION` | SQL Server no admite `RESTRICT` |

## Pendiente de servicios (no es posible con restricciones de la base)

- Una fila en `existencias` por cada consumible nuevo (servicio de inventario, Fase 4).
- Existencia actual igual a la suma de movimientos (comando de control, Fase 4).
- Una lectura debe traer al menos un dato: contador, niveles o motivo (servicio de lecturas, Fase 5).
- Sin vigencias de precio solapadas por consumible y proveedor (servicio de precios, Fase 4).
