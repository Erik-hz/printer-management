# Dashboard

## Resumen

La ruta autenticada `/inicio` muestra tarjetas según los permisos del usuario:

- **Impresoras**: total registrado y total cuyo estado está marcado como operativo.
- **Inventario**: número de consumibles activos por debajo de un mínimo mayor que cero y hasta cinco de los más bajos.
- **Compras**: órdenes `ordenada` o `parcialmente_recibida`, visibles solo con `gestionar-compras`.
- **Lecturas**: hasta cinco registros recientes, con impresora, fecha, origen y contador o motivo de ausencia.

Las consultas de cada tarjeta se ejecutan únicamente cuando el usuario tiene el permiso correspondiente (`ver-impresoras`, `ver-inventario`, `gestionar-compras` o `ver-monitoreo`). Las tarjetas no autorizadas no se envían a la vista.

## Alcance

El dashboard usa datos persistidos en la aplicación. Las lecturas no son monitoreo en tiempo real; SNMP se ejecuta manualmente. Esta versión presenta tarjetas y listas resumidas, sin gráficas.

## Validación

Las pruebas de `tests/Feature/Reports/DashboardTest.php` verifican los conteos de impresoras y stock mínimo, la visibilidad de compras por permiso y la autenticación para `/inicio`.
