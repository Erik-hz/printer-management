# SGIIC — Registro de decisiones técnicas

Última actualización: 16/11/2026

## Decisiones vigentes

| # | Decisión | Motivo / nota |
|---|---|---|
| D-01 | Base de datos: SQL Server en instancia local propia | Independencia de sistemas institucionales (reglas 15 y 17) |
| D-02 | Acceso con usuario SQL (no `sa` ni Windows) con `db_owner` solo sobre la base del proyecto | La autenticación de Windows desde PHP es más compleja |
| D-03 | Laravel 12.72.0 para el piloto; migrar a Laravel 13 antes de la Fase 8 | Laravel 12 solo recibe parches de seguridad hasta feb. 2027 |
| D-04 | `DB_TRUST_SERVER_CERTIFICATE=true` solo en desarrollo | ODBC 18 rechaza el certificado local |
| D-05 | Arquitectura monolítica modular en `app/Modules/` | Mantenible, sin paquetes adicionales |
| D-06 | Lógica de negocio en Services; controladores delgados | Regla 7 |
| D-07 | Scripts Python/PowerShell envían datos a una API de Laravel, no escriben en la base | Lógica y validaciones centralizadas |
| D-08 | Livewire no se instala hasta que aporte valor real | Regla 18 |
| D-09 | Excel usado solo como fuente de datos; hoja de referencia: `29052025` | Contiene errores; se limpian al importar |
| D-10 | `SR` = retirado y `DESC%` = descuento: se guardan como "sin lectura" con motivo | Asignación aprobada en Fase 0 |
| D-11 | Consumo anual como fracción de cartucho (1 = 100%) | Fase 0 |
| D-12 | Referencias `R-xxxxxx` = número de ticket, guardado en el movimiento de inventario | Se vinculará con incidentes en la segunda etapa |
| D-13 | Tintas Epson/Brother: referencia por contador de impresiones | Con carga manual de respaldo (regla 12) |
| D-14 | Tablas de catálogo en lugar de ENUM; listas cerradas con CHECK | Portabilidad y mantenimiento en SQL Server |
| D-15 | Tablas, columnas y modelos en español; columnas estándar de Laravel y de autenticación en inglés | Decisión del responsable; el framework depende de esas columnas |
| D-16 | Claves foráneas con `NO ACTION`, sin cascadas | SQL Server no admite `RESTRICT`; conserva el historial |
| D-17 | Trigger que bloquea `UPDATE`/`DELETE` en `movimientos_inventario` (verificado) | Trazabilidad garantizada en la base de datos |
| D-18 | Carpetas de módulos en inglés | Decisión del responsable; el idioma no afecta al proyecto |
| D-19 | `compras.proveedor_id` obligatorio | Toda compra real tiene proveedor (pendiente de confirmación del responsable) |
| D-20 | Los movimientos de entrada enlazan `detalle_compra_id` | Permite recepciones parciales |
| D-21 | `lecturas.contador_paginas` puede ser nulo sin motivo | Regla 12: no todos los modelos entregan contador |
| D-22 | La fila de `existencias` la crea el servicio de inventario (Fase 4) | Una restricción de base de datos no puede hacerlo por cada consumible nuevo |
| D-23 | `laravel_user` es `db_owner` y podría saltarse el trigger | Aceptable en desarrollo; en la Fase 8 se usa un usuario con permisos limitados |
| D-24 | Los datos de prueba solo se siembran en entorno `local` | Evita datos ficticios y claves de desarrollo en un despliegue |

## Pendientes

- Autenticación de la API de monitoreo (decidir en la Fase 5).
- Nombre de la base de datos: hoy `laravel`; considerar un nombre propio del proyecto.
- Herramienta de pago de HP a sustituir (antes de la Fase 3).
- Confirmar D-19 (proveedor obligatorio en compras).
- Versión de Laragon en `instalacion.md`.
- Identidad de Git: revisar el correo configurado automáticamente.
- Cambiar la clave del usuario SQL antes del despliegue.
