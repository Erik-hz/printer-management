# SGIIC — Instalación del entorno

Última actualización: 17/11/2026

## Entorno verificado

| Componente | Versión |
|---|---|
| Laragon | (registrar versión) |
| PHP | 8.3.36, x64, Thread Safe, vs16 |
| Composer | 2.10.3 |
| Git | 2.47.1 |
| Node / npm | 26.13.0 / 11.20.0 |
| Vite | 7.3.8 |
| Bootstrap | 5.3 (CSS empaquetado con Vite) |
| Laravel | 12.72.0 (piloto) |
| SQL Server | 2025 (17.00.1000), instancia local `MSSQLSERVER` |
| Driver PHP | `pdo_sqlsrv` 5.13.3 (y `sqlsrv`) |
| ODBC Driver | 17 en uso; el 18 también está instalado |
| Bases de datos | `laravel` (desarrollo) y `laravel_test` (pruebas), collation `Modern_Spanish_CI_AS` |

## Puesta en marcha

1. Instalar SQL Server y SSMS (instancia propia del proyecto, sin bases institucionales).
2. Crear las bases `laravel` y `laravel_test` y el usuario SQL con `db_owner` sobre ambas.
3. Copiar los DLL `php_sqlsrv_83_ts_x64.dll` y `php_pdo_sqlsrv_83_ts_x64.dll` a la carpeta `ext` de PHP, añadir las dos líneas `extension=` en `php.ini` y reiniciar Laragon.
4. Verificar con `php -m | findstr /i sqlsrv` y `php --ri pdo_sqlsrv`.
5. Clonar o abrir el proyecto, copiar `.env.example` a `.env` y configurar `DB_CONNECTION=sqlsrv`, host, puerto 1433, base, usuario, clave, `DB_ENCRYPT=yes`, `DB_TRUST_SERVER_CERTIFICATE=true`, `APP_LOCALE=es` y `APP_FALLBACK_LOCALE=es`.
6. `composer install`, `php artisan key:generate` y `npm install`.
7. `php artisan migrate:fresh --seed` para crear las tablas y los datos de prueba (solo en entorno `local`).
8. `npm run build` para compilar los estilos.

Usuario de desarrollo: `admin@sgiic.test` (la clave está en el seeder y solo existe en entorno `local`).

## Verificación

- `php artisan db:show --counts`: conexión `sqlsrv` activa.
- `php artisan test`: todas las pruebas deben pasar. `tests/TestCase.php` aborta si la conexión no apunta a `laravel_test`.

## Problemas conocidos

- El driver ODBC cifra por defecto; sin `DB_TRUST_SERVER_CERTIFICATE=true` la conexión local se rechaza por el certificado de desarrollo.
- Si falla la conexión TCP, revisar que el protocolo TCP/IP esté habilitado en SQL Server Configuration Manager.
- `pdo_sqlsrv` devuelve como texto los enteros que no son clave primaria.
- La contraseña nunca se sube a Git. Cambiarla antes del despliegue.
