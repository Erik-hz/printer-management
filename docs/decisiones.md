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
#	Decisión	Motivo / nota
|D-25	|Zona horaria America/La_Paz en config/app.php	Coherencia con GETDATE() de SQL Server y la hora local
|D-26	|Autenticación propia y mínima, sin paquetes (sin Breeze)	Regla 18; los kits traen frontend distinto de Bootstrap
|D-27	|Permisos por rol con Gates, definidos en Permisos.php	Cuatro roles fijos; sin paquete de permisos
|D-28	|Mensajes de validación en español dentro de cada Form Request	Laravel 12 no trae lang/es
|D-29	|Pruebas contra la base laravel_test, con seguro en TestCase	Las pruebas reconstruyen la base; SQLite no soporta nuestras migraciones
|D-30	|Inicio de sesión: 5 intentos, bloqueo de 5 minutos; usuario inactivo recibe el mismo mensaje que clave incorrecta	No revelar qué cuentas existen
#	Decisión	Motivo / nota
|D-31	|Bootstrap 5.3 empaquetado con Vite e importado como CSS compilado, sin Sass	Regla 18; funciona sin internet
|D-32	|Tailwind eliminado del proyecto	El frontend usa Bootstrap
|D-33	|Las pruebas desactivan Vite	No dependen de que Node haya compilado los estilos
#	Decisión	Motivo / nota
|D-34	|Permisos aplicados con el middleware can: en las rutas	El controlador base de Laravel 12 no trae authorize()
|D-35	|Un registro de catálogo solo se elimina si nada depende de él; las impresoras con borrado lógico cuentan como dependencia	La base de datos las sigue referenciando
|D-36	|Errores de validación en una alerta global del layout	Una sola pieza de interfaz para todas las pantallas
|D-37	|Edición en línea en las tablas de catálogos con campos form=	Evita pantallas separadas para registros de un solo campo
#	Decisión	Motivo / nota
|D-38	|La IP se normaliza (ceros a la izquierda) antes de validar y guardar, con la clase DireccionIp	El Excel trae valores como 172.20.015.25; la importación la reutilizará
|D-39	|Retirar (estado retirada) y eliminar (borrado lógico) son acciones distintas	Una impresora retirada conserva su historial; eliminar es solo para corregir errores de carga
|D-40	|Red/USB se valida en el formulario y en la base de datos (CHECK e índices filtrados); el servicio traduce los errores de unicidad	Doble defensa ante carreras de concurrencia
|D-41|	Paginación con las vistas de Bootstrap 5	Tailwind se eliminó en D-32
|D-42	|Un script mínimo en el formulario alterna los campos IP y equipo según el tipo de conexión	Sin Livewire (D-08); el servidor valida igual
#	Decisión	Motivo / nota
D-43	pdo_sqlsrv devuelve como texto los enteros que no son clave primaria; las pruebas castean con (int) y las vistas comparan por id	Eloquent solo convierte la clave primaria; no se activó la conversión global del driver para no alterar otros tipos
D-44	Contraseñas: mínimo 10 caracteres, con una letra y un número, definidas en ReglasClave	Política en un solo lugar
D-45	Los usuarios no se eliminan, se desactivan	Movimientos, compras y lecturas los referencian
D-46	Middleware UsuarioActivo en el grupo web	Un usuario desactivado pierde el acceso con la sesión abierta
D-47	El último administrador activo no se puede desactivar ni cambiar de rol; la comprobación no usa bloqueos	Riesgo de carrera aceptado con pocos administradores
D-48	El administrador restablece las contraseñas; la recuperación por correo queda para la segunda etapa	Sin integración con correo en esta etapa
#	Decisión	Motivo / nota
D-49	Índices explícitos en las claves foráneas de impresoras y en modelo_consumible.consumible_id	SQL Server no los crea solo
D-50	Una prueba recorre todas las rutas con auth y exige redirección al login para invitados	Cubre las rutas de fases futuras sin escribir pruebas nuevas
D-51	Una prueba exige que las consultas del listado no crezcan con las filas	Detecta N+1 antes de que importe
## Pendientes

- Autenticación de la API de monitoreo (decidir en la Fase 5).
- Nombre de la base de datos: hoy `laravel`; considerar un nombre propio del proyecto.
- Herramienta actual: HP Web Jetadmin. Áreas candidatas indicadas para revisar: inventario y descubrimiento, estado y disponibilidad, consumibles y contadores, alertas, configuración remota, informes e historial, firmware y actualizaciones. No confirmadas como alcance; revisar el uso real antes de definir la Fase 3.
- Confirmar D-19 (proveedor obligatorio en compras).
- Versión de Laragon en `instalacion.md`.
- Identidad de Git: revisar el correo configurado automáticamente.
- Cambiar la clave del usuario SQL antes del despliegue.
