# Print Manager

Aplicación web para administrar impresoras y sus operaciones: inventario de consumibles, compras, lecturas de uso y monitoreo SNMP. Está construida con Laravel 13, PHP 8.3, SQL Server y Bootstrap 5.

## Módulos

- **Acceso:** inicio de sesión, usuarios, roles y permisos.
- **Impresoras:** impresoras, marcas, modelos y ubicación (zonas, lugares y áreas).
- **Inventario:** catálogo de consumibles, existencias y movimientos con historial inmutable.
- **Compras:** proveedores, precios y órdenes con recepción parcial.
- **Monitoreo:** lecturas manuales o SNMP de contadores y niveles de consumibles.
- **Importaciones:** análisis e importación de lecturas históricas desde Excel.
- **Reportes:** dashboard e informes de estado.

La aplicación es un monolito modular: rutas, controladores, servicios y modelos de cada dominio viven en `app/Modules/`. Las reglas de negocio se concentran en servicios y la base de datos aplica restricciones adicionales.

## Requisitos

Consulta [docs/instalacion.md](docs/instalacion.md) para las versiones y la configuración detallada. El entorno verificado usa PHP 8.3 con `pdo_sqlsrv`/`sqlsrv`, SQL Server, Composer, Node.js/npm y PowerShell. La extensión ZIP de PHP es necesaria para importar Excel.

## Inicio local

1. Configura SQL Server y las bases `laravel` y `laravel_test` según la guía de instalación.
2. Prepara la configuración local en `.env`; no guardes secretos en Git.
3. Instala dependencias y genera la clave de Laravel:

   ```powershell
   composer install
   Copy-Item .env.example .env
   php artisan key:generate
   npm install
   ```

4. En una base de desarrollo nueva, ejecuta `php artisan migrate --seed` y luego `npm run build`.
5. Inicia la web con `php artisan serve`. Para procesar lecturas SNMP encoladas, también debe estar activo `php artisan queue:work --tries=1`.

`migrate:fresh` elimina y reconstruye todas las tablas; úsalo únicamente contra una base descartable. Las pruebas están protegidas para ejecutarse solo contra `laravel_test`.

## Monitoreo SNMP

El recolector está en `scripts/collect_snmp_v1.ps1` y el protocolo SNMPv1 en `scripts/SnmpV1.psm1`. El script obtiene por API las impresoras elegibles desde SQL, consulta sus equipos y envía las lecturas a Laravel. La API requiere `MONITOREO_TOKEN`; configura también la comunidad SNMP y la URL en el entorno local.

Usa `scripts/printers.snmp.example.json` como plantilla y crea `scripts/printers.snmp.local.json` con los mapeos reales. El archivo local está excluido de Git. Para inspeccionar sin registrar lecturas:

```powershell
.\scripts\collect_snmp_v1.ps1 -WhatIf
.\scripts\collect_snmp_v1.ps1 -PrinterId 17 -WhatIf
```

El sondeo es manual; el acceso SNMP debe limitarse a una red privada autorizada.

## Documentación

- [Instalación y configuración](docs/instalacion.md)
- [Diagrama entidad-relación](docs/diagrama-er.md)
- [Decisiones técnicas](docs/decisiones.md)
- [Fases de impresoras, inventario, monitoreo, compras e importación](docs/fase3-gestion-impresoras.md), [Fase 4](docs/fase4-inventario.md), [Fase 5](docs/fase5-monitoreo.md), [Fase 6](docs/fase6-compras.md) y [Fase 7](docs/fase7-importacion.md)
- [Dashboard](docs/dashboard.md)
- [Guía de entrega y traslado](docs/entrega-print-manager-2026-11-25.md)
