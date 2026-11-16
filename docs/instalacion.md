# SGIIC — Instalación del entorno (Fase 1)

Última actualización: 13/11/2026

## Entorno verificado

| Componente | Versión |
|---|---|
| Laragon | (registrar versión) |
| PHP | 8.3.36, x64, Thread Safe, vs16 |
| Composer | 2.10.3 |
| Git | 2.47.1 |
| Laravel | 12.72.0 (piloto) |
| SQL Server | 2025 (17.00.1000), instancia local `MSSQLSERVER` |
| Driver PHP | `pdo_sqlsrv` 5.13.3 (y `sqlsrv`) |
| ODBC Driver | 18 (64-bit); el 17 también está instalado |
| Collation de la base | `Modern_Spanish_CI_AS` |

## Pasos realizados

1. Instalar SQL Server y SSMS en el equipo (instancia propia del proyecto, sin bases institucionales).
2. Crear la base de datos `laravel` y un usuario SQL con permisos solo sobre esa base (`db_owner`).
3. Descargar los DLL `php_sqlsrv_83_ts_x64.dll` y `php_pdo_sqlsrv_83_ts_x64.dll` y copiarlos a la carpeta `ext` de PHP.
4. Añadir las dos líneas `extension=` en `php.ini` y reiniciar Laragon.
5. Verificar con `php -m | findstr /i sqlsrv` y `php --ri pdo_sqlsrv`.
6. Crear el proyecto: `composer create-project laravel/laravel printer-management`.
7. Configurar en `.env`: `DB_CONNECTION=sqlsrv`, host, puerto 1433, base, usuario, clave, `DB_ENCRYPT=yes` y `DB_TRUST_SERVER_CERTIFICATE=true` (solo desarrollo).
8. Verificar con `php artisan db:show` y `php artisan migrate`.
9. Inicializar Git. `.env` queda fuera del repositorio.

## Verificación

- `php artisan db:show --counts`: conexión `sqlsrv` activa, 9 tablas por defecto de Laravel tras migrar.
- `php artisan migrate:status`: migraciones aplicadas.

## Problemas conocidos

- El ODBC Driver 18 cifra por defecto; sin `DB_TRUST_SERVER_CERTIFICATE=true` la conexión local se rechaza por el certificado de desarrollo.
- Si falla la conexión TCP, revisar que el protocolo TCP/IP esté habilitado en SQL Server Configuration Manager.
- La contraseña nunca se sube a Git. Cambiarla antes del despliegue.
