# Poner Print Manager en otro equipo

Esta guía configura una copia funcional de Print Manager en un equipo Windows. Cubre dos modalidades:

- **Sistema completo:** Laravel conectado a SQL Server, con inventario real, usuarios, lecturas y monitoreo SNMP.
- **Visor de snapshot:** consulta de solo lectura con una exportación SQLite; no requiere SQL Server ni acceso a impresoras.

Para conservar los datos reales y operar el monitoreo se necesita el sistema completo. El código del repositorio por sí solo no contiene la base activa ni los mapeos SNMP locales.

## 1. Preparar la entrega en el equipo actual

1. Haz un respaldo completo (`.bak`) de la base `laravel` con SQL Server Management Studio (SSMS). Comprueba que el respaldo haya terminado correctamente.
2. Entrega el respaldo por un canal protegido. Contiene cuentas y datos operativos.
3. Copia el proyecto al equipo destino o entrégalo desde el repositorio.
4. Transfiere por separado y de forma segura:
   - `.env`, como referencia para reconstruir la configuración. Contiene secretos; no lo añadas a Git ni a esta guía.
   - `scripts/printers.snmp.local.json`, con los mapeos de suministros. Este archivo está excluido de Git.
   - La comunidad SNMP y las credenciales SQL por un canal seguro.
5. Conserva el `APP_KEY` actual si quieres mantener compatibilidad con datos cifrados que dependan de esa clave. No la publiques. En el equipo destino se puede crear una clave nueva si la aplicación no necesita descifrar datos creados con la clave anterior.

Los IDs de impresoras y tipos de consumible del JSON deben corresponder a los de la base restaurada. La dirección IP y el puerto de las impresoras se leen desde SQL; no se agregan al JSON.

## 2. Instalar requisitos en el equipo destino

Instala y configura:

- Windows y Laragon (o Apache/PHP configurado de forma equivalente).
- PHP 8.3 x64 Thread Safe, Composer y Node.js/npm.
- Extensiones PHP `pdo_sqlsrv`, `sqlsrv` y `zip`.
- Driver ODBC de Microsoft para SQL Server. En el `.env` indica la versión instalada mediante `DB_ODBC_DRIVER`.
- SQL Server y SSMS, en el mismo equipo o accesibles por red.
- PowerShell 5.1 (`powershell.exe`) para ejecutar el recolector SNMP.

La versión de referencia y los pasos de configuración de PHP están en [instalacion.md](instalacion.md). Comprueba los drivers con:

```powershell
php -m | findstr /i "sqlsrv zip"
php --ri pdo_sqlsrv
composer --version
node --version
npm --version
powershell.exe -NoProfile -Command "$PSVersionTable.PSVersion"
```

## 3. Restaurar la base de datos

1. En SSMS, restaura el `.bak` como la base `laravel`. Si el servidor destino usa otras rutas para los archivos de datos, ajusta las rutas durante la restauración.
2. No ejecutes `migrate:fresh`, porque elimina todas las tablas y los datos.
3. El respaldo restaura los usuarios de base de datos, pero no necesariamente los logins de la instancia SQL Server. Pide a un administrador de SQL Server que cree o asocie el login de ejecución `sgiic_runtime` con el usuario existente de la base restaurada y le conceda `db_datareader` y `db_datawriter` sobre `laravel`. No le conceda `db_owner` para el uso normal de la aplicación. El script [crear_login_runtime.sql](../database/administracion/crear_login_runtime.sql) describe el permiso previsto, pero si el usuario ya vino en el respaldo el administrador debe asociar el login existente en vez de intentar crear de nuevo ese usuario.
4. Conserva una cuenta administrativa separada para aplicar migraciones, que requieren permisos de DDL. Usa la cuenta de ejecución en `.env` para el funcionamiento habitual.

Si no se necesita conservar datos reales y solo se quiere una instalación de demostración, crea una base vacía y ejecuta `php artisan migrate --seed` en un entorno `local`. Los seeders no reproducen la base real.

## 4. Instalar el proyecto y configurar Laravel

Desde la carpeta del proyecto:

```powershell
Copy-Item .env.example .env
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

Edita `.env` localmente. No guardes ese archivo en el repositorio. Para el sistema completo, como mínimo configura:

```dotenv
APP_NAME="Print Manager"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://nombre-del-servidor
APP_SNAPSHOT_ONLY=false

DB_CONNECTION=sqlsrv
DB_HOST=nombre-o-ip-del-servidor-sql
DB_PORT=1433
DB_DATABASE=laravel
DB_USERNAME=sgiic_runtime
DB_PASSWORD=clave-local-segura
DB_ENCRYPT=yes
DB_TRUST_SERVER_CERTIFICATE=false
DB_ODBC_DRIVER="ODBC Driver 18 for SQL Server"

MONITOREO_TOKEN=token-secreto-compartido
MONITOREO_API_URL="${APP_URL}/api/monitoring/lecturas"
SNMP_COMMUNITY_GET=comunidad-configurada-en-las-impresoras
MONITOREO_POWERSHELL_BINARY=powershell.exe
MONITOREO_SNMP_CONFIG="C:\\ruta\\al\\proyecto\\scripts\\printers.snmp.local.json"
```

Usa el nombre del driver ODBC que realmente está instalado. `DB_TRUST_SERVER_CERTIFICATE=true` solo es apropiado en desarrollo con un certificado local; en el servidor configura un certificado confiable.

Define un `APP_URL` que el proceso web y el worker puedan alcanzar. Si la API usa HTTP, el script solo lo permite para localhost; para una API en otro equipo usa HTTPS y un certificado válido. La URL debe resolver a localhost o a una dirección privada, y debe terminar en `/api/monitoring/lecturas`.

Si `.env` se creó desde cero, genera `APP_KEY` con `php artisan key:generate`. Si se conserva una clave existente, verifica que esté bien copiada y no la regeneres después de poner la aplicación en servicio.

## 5. Aplicar migraciones pendientes

Antes de migrar, confirma que `.env` apunta a la base restaurada `laravel` en el servidor destino. Usa temporalmente la cuenta administrativa aprobada por el DBA:

```powershell
php artisan config:clear
php artisan migrate --force
```

Este comando aplica solo migraciones pendientes; no borra tablas. Después devuelve `.env` a la cuenta `sgiic_runtime` y limpia la configuración cacheada:

```powershell
php artisan config:clear
php artisan config:cache
```

No ejecutes seeders sobre una copia con datos reales sin revisar previamente su efecto.

## 6. Instalar los mapeos y habilitar el monitoreo

1. Copia `scripts/printers.snmp.local.json` a la misma ruta del proyecto destino. Si cambia la ruta, ajusta `MONITOREO_SNMP_CONFIG`.
2. Revisa cada `printer_id`, descripción SNMP, `tipo_consumible_id` y color contra la base restaurada.
3. Comprueba que `MONITOREO_TOKEN` y `SNMP_COMMUNITY_GET` estén definidos y coincidan con la configuración de la API y las impresoras.
4. Asegúrate de que el equipo destino pueda conectarse a SQL Server, a la API HTTPS local y a las impresoras por UDP/161. Para las impresoras con lectura EWS, permite también el acceso HTTP/HTTPS necesario.
5. Asegúrate de que el usuario de Windows que ejecuta PHP y el worker pueda iniciar `powershell.exe` y leer el JSON local.
6. Inicia el worker en una consola para la primera verificación:

   ```powershell
   php artisan queue:work --tries=1
   ```

   Para operación continua, configura este comando como servicio supervisado de Windows y haz que se reinicie si falla. La actualización automática periódica no está configurada; las lecturas se solicitan manualmente desde **Lecturas**.

## 7. Arrancar y verificar

Para una prueba local:

```powershell
php artisan serve --host=127.0.0.1
```

En el servidor, configura Apache/Laragon para publicar la carpeta `public` del proyecto y limita el acceso a la red autorizada. No expongas `artisan serve` como servidor de producción.

Verifica en este orden:

1. Abre la aplicación y entra con una cuenta existente restaurada. No uses la cuenta ficticia del seeder para la base real.
2. Confirma impresoras, consumibles y lecturas históricas desde la interfaz.
3. En **Lecturas**, solicita una actualización de una sola impresora conocida.
4. Revisa la salida de la ejecución y confirma que se guardó una lectura nueva. Un timeout SNMP debe investigarse como conectividad, servicio SNMP o comunidad; no demuestra por sí solo que el modelo sea incompatible.
5. Compara los conteos básicos de usuarios, impresoras, consumibles y lecturas con el equipo de origen.

Para revisar el recolector manualmente sin enviar lecturas, usa `-WhatIf` desde PowerShell y proporciona `MONITOREO_API_URL`, `MONITOREO_TOKEN` y `SNMP_COMMUNITY_GET` en el entorno de esa consola, tal como requiere [fase5-monitoreo.md](fase5-monitoreo.md).

## 8. Alternativa: instalar solo el visor snapshot

Si el otro equipo solo necesita consultar lecturas exportadas, puede usarse el visor de solo lectura sin SQL Server, worker ni conectividad con impresoras. Necesita PHP con `pdo_sqlite` y el archivo `storage/app/private/monitoreo_snapshot.sqlite` ya importado. Configura `APP_SNAPSHOT_ONLY=true`, `DB_CONNECTION=snapshot`, `SESSION_DRIVER=file`, `CACHE_STORE=file` y `SNAPSHOT_DB` con la ruta absoluta del archivo.

Consulta la sección **Snapshot de monitoreo para un visor sin SQL Server** en [la guía de entrega](entrega-print-manager-2026-11-25.md). El snapshot contiene información de impresoras e IP internas; limita su distribución y acceso. Esta modalidad no mantiene datos en vivo ni permite registrar lecturas.

## Archivos que no deben publicarse

- `.env` y credenciales SQL.
- `scripts/printers.snmp.local.json` y comunidad SNMP.
- Respaldos `.bak`, snapshots SQLite y exportaciones con datos reales.

La comunidad SNMPv1 y los datos de SNMPv1 no están cifrados; usa el monitoreo únicamente en una red privada autorizada.
