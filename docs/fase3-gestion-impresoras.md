# SGIIC — Fase 3: gestión de impresoras (resumen técnico)

Última actualización: 17/11/2026

## Pantallas y rutas

| Ruta | Acceso | Qué hace |
|---|---|---|
| `/ingresar`, `/salir` | Invitado / autenticado | Inicio y cierre de sesión |
| `/inicio` | Autenticado | Pantalla de bienvenida |
| `/perfil/clave` | Cualquier rol activo | Cambio de la propia contraseña |
| `/impresoras` | Todos los roles | Listado con búsqueda y filtros (zona, marca, estado, conexión) |
| `/impresoras/crear`, `/impresoras/{id}/editar` | Administrador | Alta y edición |
| `/impresoras/{id}/retirar`, `DELETE /impresoras/{id}` | Administrador | Retirar (estado) y eliminar (borrado lógico) |

El listado muestra los niveles de tinta de la lectura más reciente por impresora. Clasifica los niveles como críticos (≤ 10%), bajos (> 10% y ≤ 25%) o normales (> 25%); los niveles faltantes se indican aparte. El resumen refleja las impresoras de la página y los filtros vigentes.
| `/zonas`, `/zonas/{id}/lugares`, `/lugares/{id}/areas` | Consulta: todos. Gestión: administrador | Jerarquía de ubicaciones con edición en línea |
| `/marcas`, `/modelos` | Consulta: todos. Gestión: administrador | Catálogos de marcas y modelos |
| `/usuarios` | Administrador | Alta, edición, desactivación y restablecimiento de contraseña |

## Permisos por rol

| Habilidad | administrador | responsable_inventario | tecnico | consulta |
|---|---|---|---|---|
| ver-impresoras (impresoras y catálogos) | Sí | Sí | Sí | Sí |
| gestionar-impresoras | Sí | No | No | No |
| gestionar-catalogos | Sí | No | No | No |
| gestionar-usuarios | Sí | No | No | No |

La matriz está en `app/Modules/Access/Permisos.php`. Se aplica con el middleware `can:` en las rutas. Un usuario inactivo no tiene ningún permiso.

## Reglas de validación de impresoras

- `tipo_conexion`: solo `red` o `usb`.
- Red: la IP es obligatoria, en formato IPv4 y única entre las impresoras no eliminadas. No admite equipo conectado.
- USB: no admite IP. El equipo conectado es opcional.
- La IP se normaliza antes de validar (`172.20.015.25` pasa a `172.20.15.25`) con `App\Modules\Printers\Support\DireccionIp`, que reutilizará la importación del Excel.
- El nombre es único entre las impresoras no eliminadas, sin distinguir mayúsculas.
- La base de datos repite estas reglas con CHECK e índices únicos filtrados. El servicio traduce un error de unicidad a un mensaje comprensible.
- **Retirar** cambia el estado a `retirada` y la impresora sigue en el listado. **Eliminar** es un borrado lógico para corregir errores de carga.

## Seguridad

- Inicio de sesión: 5 intentos fallidos por combinación de correo e IP bloquean 5 minutos. Un usuario inactivo recibe el mismo mensaje que una clave incorrecta. La sesión se regenera al ingresar.
- Un usuario desactivado pierde el acceso en la siguiente petición, incluso con la sesión abierta (`UsuarioActivo`).
- Contraseñas con hash; mínimo 10 caracteres, con una letra y un número (`ReglasClave`).
- Todos los formularios llevan token CSRF.
- Los usuarios no se eliminan, se desactivan. El último administrador activo no se puede desactivar ni cambiar de rol.
- Una prueba automática recorre todas las rutas protegidas y comprueba que un invitado es redirigido al login.

## Limitaciones conocidas

- No hay recuperación de contraseña por correo; la restablece el administrador. Queda para la segunda etapa.
- La búsqueda usa `LIKE '%texto%'`, que no aprovecha índices. Es suficiente para el volumen actual (decenas de impresoras).
- La protección del último administrador no usa bloqueos: dos desactivaciones simultáneas podrían eludirla.
- No hay registro de auditoría de quién cambió qué en impresoras y catálogos. El inventario sí conserva su historial.
- `pdo_sqlsrv` devuelve como texto los enteros que no son clave primaria; el código y las pruebas lo tienen en cuenta.

## Pruebas

```
php artisan test
```

Las pruebas usan la base `laravel_test`. `tests/TestCase.php` aborta si la conexión apunta a otra base.
