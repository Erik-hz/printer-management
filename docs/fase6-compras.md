# SGIIC — Fase 6: compras y proveedores

## Alcance implementado

- Proveedores: alta, edición, activación/desactivación y borrado lógico cuando no hay compras ni precios asociados.
- Órdenes: selección de proveedor y consumibles, cantidades, precio unitario capturado en la orden, moneda, IVA y observaciones.
- Recepciones: registrar cantidades parciales desde el detalle de cada orden. El stock, el detalle recibido, el estado de la compra y el movimiento de entrada se actualizan con `InventarioService`.
- Precios: historial de precios por consumible y proveedor (o precio general), con moneda, IVA y periodo de vigencia. Se puede cerrar una vigencia abierta antes de registrar la tarifa sucesora; no se permiten periodos solapados para una misma combinación.
- Acceso: todas las pantallas y acciones requieren autenticación y el permiso `gestionar-compras` (administrador o responsable de inventario).

## Pantallas

- `/compras`: listado y creación de órdenes.
- `/compras/{id}`: detalle, cancelación de una orden aún no recibida y registro de recepciones.
- `/proveedores`: gestión de proveedores.
- `/precios-consumibles`: registro y consulta del historial de precios.

Las órdenes conservan el precio unitario informado al crearlas; los precios vigentes son un historial por proveedor y no se modifican retroactivamente al registrar nuevas tarifas.
