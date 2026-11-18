# SGIIC — Fase 4: inventario y compras (resumen técnico)

Última actualización: 17/11/2026

## Servicios verificados

- `ConsumibleService` crea la existencia inicial en cero y sincroniza la compatibilidad.
- `InventarioService` es el único punto de entrada que modifica existencias.
- Las entradas manuales exigen motivo y solo permiten consumibles activos.
- Las recepciones parciales actualizan detalle de compra, compra y stock en una transacción.
- Las salidas rechazan stock insuficiente y requieren una impresora operativa en la pantalla.
- Los ajustes permiten corregir diferencias con un motivo obligatorio.
- Los movimientos son inmutables en el modelo y en la base de datos.

## Vigencias de precio

`PrecioConsumibleService` impide registrar dos precios solapados para el mismo consumible y proveedor.
Las fechas incluyen ambos extremos de la vigencia. Una vigencia sin fecha final es abierta.

## Comprobación de consistencia

Ejecutar:

```powershell
php artisan inventario:verificar
```

El comando informa consumibles sin fila de existencia y diferencias entre la existencia actual
y la suma de `cantidad` de todos los movimientos. No modifica datos.
