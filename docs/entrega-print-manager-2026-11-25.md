# Print Manager — estado de monitoreo y traslado a otro equipo

**Fecha:** 25 de noviembre de 2026  
**Estado:** guía de entrega; la importación de la base de datos aún debe ejecutarse en los servidores de origen y destino.

## Cambios de monitoreo registrados

- En la vista Lecturas se quitó el botón de actualización masiva. El botón visible actualiza la impresora seleccionada.
- El recolector SNMP espera hasta cinco segundos y aísla los errores por columna de consumibles para que una columna sin respuesta no aborte todo el sondeo.
- SNMP `unit=19` se interpreta como porcentaje directo. Para la impresora 44 se agregó el mapeo `Black Cartridge HP CF280X`; el resultado compartido fue 62 %. La lectura 211 es anterior al ajuste y solo tiene contador; hay que ejecutar otra lectura para guardar el nivel.
- Para la impresora 26, Printer-MIB devuelve `level=-2` y `max=-2` (desconocido), pero la página HP informa CE505X al 7 %. Se configuró una alternativa HTTP a `/hp/device/supply_status.htm`, que asocia el porcentaje al código CE505X y guarda la lectura con origen `powershell`. Falta ejecutar una actualización real y confirmar en la vista que el worker puede leer esa página.
- La página de Lecturas filtra por origen. Para ver la lectura HTTP de la impresora 26, usar “Todos los orígenes” o “Powershell”.
- No se cambió la estructura de la base de datos y el programador automático todavía no está configurado. El worker de Laravel sigue siendo necesario para procesar las tareas encoladas.

## Qué se debe trasladar

El código fuente contiene las migraciones y seeders, pero **no contiene la base SQL Server activa ni los datos reales del inventario**. Para llevar el sistema a otro equipo conservando usuarios, impresoras, lecturas, inventario y ejecuciones, se debe transferir una copia completa de la base `laravel`.

También se requieren archivos de entorno que Git excluye:

- `.env`, con `APP_KEY`, conexión SQL Server, `MONITOREO_TOKEN`, `SNMP_COMMUNITY_GET` y rutas locales.
- `scripts/printers.snmp.local.json`, con los mapeos por ID de impresora y la ruta EWS configurada para la impresora 26.
- Certificados, drivers o configuración propia del servidor SQL Server, si aplica.

No incluir `.env`, contraseñas ni comunidades SNMP en Git ni en este documento. El respaldo `.bak` también contiene datos de usuarios e inventario; debe compartirse por un canal protegido.

## Snapshot de monitoreo para un visor sin SQL Server

Se preparó `database/exports/plantilla_snapshot_monitoreo.xlsx` y la consulta de solo lectura `database/exports/monitoreo_snmp_export.sql`. La consulta entrega las impresoras no eliminadas y hasta 30 lecturas `snmp`/`powershell` por impresora, incluyendo contador, fecha, origen y niveles de consumibles.

La plantilla Excel contiene encabezados e instrucciones, pero **todavía no contiene las filas reales**. La conexión de Artisan a SQL Server falla con `Encryption not supported on the client` tanto con ODBC 18 como con ODBC 17 en este entorno. Ejecuta el SQL desde SSMS y copia/exporta el resultado a la hoja `Monitoreo_SNMP`; así no se generan datos inventados.

El importador existente de `Insumos` procesa la hoja histórica `29052025`; este snapshot usa un comando independiente: `php artisan snapshot:import database/exports/plantilla_snapshot_monitoreo.xlsx`. El comando valida encabezados y datos, reemplaza la instantánea anterior en SQLite y no modifica el Excel ni la base SQL Server. El visor de solo lectura está disponible en `/visor`; al activar `APP_SNAPSHOT_ONLY=true`, la raíz dirige allí y las demás rutas web/API quedan bloqueadas.

SQLite es apropiado para este visor de consulta y se traslada como `storage/app/private/monitoreo_snapshot.sqlite`. En el equipo destino se habilita `pdo_sqlite`, se configura `APP_SNAPSHOT_ONLY=true`, `DB_CONNECTION=snapshot`, `SESSION_DRIVER=file`, `CACHE_STORE=file` y `SNAPSHOT_DB` con la ruta absoluta del archivo. El visor consulta únicamente esa instantánea; no ejecuta sondeos ni requiere worker, conexión a SQL Server o acceso a impresoras. El Excel preparado sigue vacío hasta ejecutar la consulta SQL desde SSMS, porque la conexión disponible desde Artisan no pudo negociar cifrado con ODBC 17/18. La página contiene direcciones IP internas y debe exponerse solo a usuarios autorizados de la red.

## Procedimiento recomendado: respaldo y restauración SQL Server

1. En el equipo de origen, verificar que la aplicación esté detenida o en un estado controlado y realizar un respaldo completo de `laravel` desde SQL Server Management Studio (SSMS). Guardar el `.bak` en una ubicación a la que tenga acceso el servicio de SQL Server.
2. Transferir el `.bak` por un medio autorizado y protegido.
3. En el equipo de destino, instalar una versión compatible de SQL Server y restaurar el respaldo con SSMS. La restauración completa conserva los registros y los IDs, que son necesarios para los mapeos de consumibles.
4. Copiar el proyecto y configurar el `.env` local con la conexión a la base restaurada. No copiar credenciales a archivos versionados.
5. Copiar `scripts/printers.snmp.local.json` al mismo path del proyecto destino. Este archivo está excluido de Git.
6. Instalar PHP 8.3 o compatible con Laravel 13, `pdo_sqlsrv`/`sqlsrv`, el driver ODBC y PowerShell. Instalar dependencias con Composer y compilar los recursos de frontend con npm.
7. Ejecutar `php artisan migrate --force` para aplicar únicamente migraciones pendientes. No usar `migrate:fresh` sobre la base restaurada.
8. Iniciar el worker de Laravel y comprobar desde el nuevo equipo que hay conectividad con SQL Server, las impresoras (UDP 161 y las páginas HTTP necesarias) y la API local.
9. Ejecutar una actualización individual de prueba y comparar los niveles guardados. Después, validar recuentos de impresoras, lecturas, consumibles y usuarios entre origen y destino.

## Base nueva frente a copia de datos reales

- **Base nueva de demostración:** `php artisan migrate --seed` crea el esquema y los datos de prueba definidos por los seeders. No reproduce los datos reales de la base actual.
- **Copia fiel del sistema actual:** restaurar el `.bak` completo. Después, aplicar migraciones pendientes con `php artisan migrate --force`. No volver a cargar seeders de demostración encima sin revisar duplicados y catálogos.
- Un volcado de datos por tablas también es posible, pero exige conservar orden de claves foráneas, identidades y catálogos. Para esta aplicación, el respaldo/restauración nativo de SQL Server es más seguro.

## Alternativa: trasladar los datos a MySQL

Un respaldo `.bak` de SQL Server **no se restaura directamente en MySQL**. Es necesario convertir el esquema y mover los registros por separado. Laravel permite configurar MySQL en `.env`, pero eso no vuelve compatibles automáticamente las migraciones existentes.

En este proyecto hay DDL específico de SQL Server, por ejemplo los índices únicos filtrados `CREATE UNIQUE INDEX ... WHERE ...` de las migraciones de impresoras, consumibles y compras. Esos índices deben rediseñarse para MySQL; una opción es usar columnas generadas e índices únicos que mantengan la regla de unicidad para filas no eliminadas. También se deben adaptar las migraciones `up` y `down` que usan `DB::statement`. Para que MySQL aplique los `CHECK`, se requiere MySQL 8.0.16 o posterior.

Flujo recomendado:

1. Crear primero un respaldo recuperable de la base SQL Server.
2. Adaptar las migraciones para MySQL y crear una base destino vacía. No importar la tabla `migrations` de SQL Server; ejecutar las migraciones adaptadas con Laravel para registrar el esquema del destino.
3. Exportar desde SSMS las tablas de datos a CSV UTF-8 con el Asistente de importación y exportación. Exportar las tablas de negocio y catálogos, conservando los valores de `id`; no incluir la tabla `migrations`.
4. Importar en MySQL en orden de dependencias de claves foráneas. Mantener fechas en formato ISO, valores booleanos como 0/1, decimales sin separadores localizados y texto UTF-8. MySQL admite la carga de archivos delimitados mediante `LOAD DATA`/`LOAD DATA LOCAL INFILE`.
5. Validar conteos y relaciones entre tablas, así como los IDs usados por `printers.snmp.local.json`. Luego configurar `DB_CONNECTION=mysql` y las credenciales del destino en el `.env` local.
6. Probar la aplicación y sus operaciones principales antes de cambiar el entorno de producción. No ejecutar `migrate:fresh` sobre la base importada.

El asistente SSMA de Microsoft llamado “for MySQL” sirve para migrar **desde MySQL hacia SQL Server**, no para convertir SQL Server hacia MySQL; no es la herramienta adecuada para este sentido de migración.

## Requisitos operativos en un despliegue con SQL Server

- La aplicación debe poder resolver y conectar con el host, puerto y base SQL Server configurados.
- El equipo que ejecuta el worker debe tener ruta de red a las impresoras. Que la web abra desde el navegador de otra computadora no demuestra que el worker tenga la misma conectividad.
- Mantener configuradas de forma segura la comunidad SNMP y la URL/token de la API. Las comunidades SNMPv1/v2c viajan sin cifrado y deben usarse solo en una red privada autorizada.
- El worker (`php artisan queue:work`) debe mantenerse activo. La actualización periódica automática todavía requiere agregar y desplegar el scheduler de Laravel.

## Verificación pendiente del piloto

- Impresora 26 / CE505X: ejecutar actualización individual; la salida esperada incluye `Nivel HTTP mapeado ... 0.07 (7 %)` y una lectura con origen `powershell`.
- Impresora 44 / CF280X: ejecutar actualización individual; la salida esperada incluye `Nivel mapeado ... 0.62` y una nueva lectura SNMP.
- Revisar en Lecturas con filtro “Todos los orígenes”. Las lecturas antiguas no se modifican; la actualización crea registros nuevos.
