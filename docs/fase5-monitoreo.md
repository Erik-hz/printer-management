# SGIIC — Fase 5: monitoreo y lecturas

Última actualización: 19/11/2026

## Registro manual

- Ruta: `/lecturas`
- Registro: `/lecturas/crear`
- Consulta: todos los roles activos con `ver-monitoreo`.
- Registro manual: administrador, responsable de inventario y técnico con `registrar-lecturas`.

Una lectura manual requiere una impresora y la fecha. Debe contener al menos:

- contador de páginas;
- un nivel de consumible; o
- un motivo sin lectura.

No se puede combinar contador y motivo sin lectura.

## API local

Durante el piloto, la API solo acepta conexiones de localhost o redes privadas. También requiere un token Bearer compartido y limita las solicitudes a 300 por minuto por dirección IP. Tanto el listado de impresoras como el registro de lecturas usan estos controles.

El recolector obtiene las impresoras desde SQL mediante:

```text
GET /api/monitoring/impresoras
Authorization: Bearer <MONITOREO_TOKEN>
```

La respuesta incluye únicamente impresoras no eliminadas, conectadas por red, operativas, con soporte SNMP verdadero o todavía desconocido y con una IPv4 privada válida. Las marcadas explícitamente como no compatibles con SNMP quedan fuera. Se puede consultar una sola impresora con `?printer_id=17`. El puerto devuelto es UDP/161.

```json
{
  "printers": [
    {
      "printer_id": 17,
      "ip": "172.20.38.13",
      "port": 161
    }
  ]
}
```

Las lecturas se envían a:

```text
POST /api/monitoring/lecturas
Authorization: Bearer <MONITOREO_TOKEN>
```

Configure `MONITOREO_TOKEN` en `.env` con un valor secreto y no lo incluya en el repositorio. Si la variable está vacía o no configurada, la API rechaza todas las solicitudes. El endpoint no requiere sesión de usuario.

Ejemplo de cuerpo:

```json
{
  "impresora_id": 1,
  "fecha_lectura": "2026-11-19T10:00:00",
  "origen": "snmp",
  "contador_paginas": 12500,
  "niveles": [
    {
      "tipo_consumible_id": 1,
      "color": "negro",
      "nivel": 0.75
    }
  ],
  "observaciones": "Lectura SNMP"
}
```

Orígenes admitidos por API: `snmp` y `powershell`. La API rechaza solicitudes desde IP pública y responde con JSON incluso cuando el cliente no envía `Accept: application/json`.
Cada nivel debe incluir un `tipo_consumible_id` existente, un color admitido y un valor normalizado entre 0 y 1 (0 = vacío, 1 = lleno).

## Recolector SNMP v1

El archivo principal es `scripts/collect_snmp_v1.ps1`: solicita a la API el inventario vigente de SQL, consulta por UDP/161 mediante SNMPv1 y solicitudes GET-NEXT de solo lectura, y envía las lecturas a la API. `scripts/SnmpV1.psm1` implementa el protocolo. No requiere `snmpget`, `snmpwalk` ni barrer la red.

1. `scripts/printers.snmp.example.json` es una plantilla de mapeos. Cópiela como `scripts/printers.snmp.local.json` (ignorado por Git). Los mapeos usan `printer_id` y la descripción exacta del suministro, pero no almacenan IP ni puerto: esos datos provienen de SQL.
2. Ajuste cada mapeo con el ID, la descripción SNMP, un `tipo_consumible_id` existente y el color. Configure `SNMP_COMMUNITY_GET` en el entorno local; no guarde la comunidad en el JSON ni en Git.
3. Configure `MONITOREO_API_URL` con la URL completa de `/api/monitoring/lecturas` y `MONITOREO_TOKEN` localmente. Ambos son necesarios incluso con `-WhatIf`, porque el recolector consulta el inventario de SQL mediante el GET protegido.
4. Ejecute `.\scripts\collect_snmp_v1.ps1 -WhatIf` para consultar todas las impresoras elegibles sin registrar lecturas. Para limitar la prueba: `.\scripts\collect_snmp_v1.ps1 -PrinterId 17 -WhatIf`. El script muestra los metadatos Printer-MIB (descripción, índice de colorante, clase, tipo, unidad, capacidad y nivel). Normaliza los niveles mapeados como `level / max_capacity`, solo cuando ambos valores son válidos y la capacidad es positiva. Valores desconocidos, centinela o sin mapeo se omiten con advertencia. Si no hay un contador único, todavía puede preparar una lectura con niveles mapeados.
5. La ejecución es manual, sin tarea programada. En `/lecturas`, filtra una impresora y usa **Actualizar por SNMP** para encolar el recolector solo para esa impresora. El botón **Actualizar todas las disponibles** encola una tarea independiente por cada impresora elegible; los errores individuales no detienen las demás. El servidor web debe tener PowerShell, acceso SNMP a las impresoras, el archivo de mapeos local y las variables anteriores configuradas; también debe estar activo un worker de Laravel (`php artisan queue:work --tries=1`). El resultado o los errores aparecen al volver a cargar la página.
6. La disponibilidad SNMP no garantiza que el equipo responda: en la revisión del 19/11/2026, 31 de 49 impresoras elegibles agotaron el tiempo de espera. Revise el resultado por impresora; las lecturas exitosas se conservan aunque otras fallen. Para la operación manual masiva por consola, primero valide con `-WhatIf` y actualice solo impresoras confirmadas.

### Validación piloto HP LaserJet P2055dn

El 19/11/2026, la impresora respondió por SNMPv1: se encontró un contador único y el suministro `Cartucho negro HP CE505X`. Printer-MIB reportó `level=0`, `max_capacity=2650` y `unit=13` (décimas de gramo), que el recolector normaliza a 0.0. El EWS informó 1%, nivel bajo y una estimación de 51 páginas restantes. Ambos datos indican que el cartucho está casi agotado, aunque queda una diferencia de un punto porcentual; no se aplica un ajuste artificial. Tras validar el payload con `-WhatIf`, se envió una lectura real al API local y se confirmó en la base de datos el contador y el nivel negro `0.0000`.

### Revisión ampliada de impresoras SNMP

El 19/11/2026, el GET protegido devolvió 49 impresoras elegibles desde SQL. En un sondeo de solo lectura con `-WhatIf` y la comunidad `public`, 18 respondieron y 31 agotaron el tiempo de espera; no se enviaron lecturas durante esta revisión. Las impresoras sin respuesta fueron `10, 12-16, 19, 21-22, 25, 28-29, 31-32, 35, 37-38, 40-41, 44, 47-57`. Un timeout no demuestra que el modelo sea incompatible: puede deberse a disponibilidad o configuración de red.

La revisión de SQL confirmó que las 31 tienen conexión `red`, estado `activa` e IP privada. Varios modelos tienen otras impresoras que sí respondieron, por lo que el timeout debe revisarse por impresora o ruta de red; no se marcaron esos modelos como incompatibles.

Desde el equipo recolector, una comprobación adicional a esas mismas IP encontró respuesta ICMP y TCP/80 abierto para las impresoras `14, 15, 16, 32, 37`; las `19, 40` respondieron a ICMP, pero no a TCP/80 dentro del tiempo de espera. Las `10, 12, 13, 21, 22, 25, 28, 29, 31, 35, 38, 41, 44, 47-57` no respondieron a ninguna de esas dos comprobaciones. Esto no determina el estado de UDP/161: ICMP/TCP y SNMP usan protocolos distintos, y un timeout no distingue entre equipo apagado, filtrado o servicio deshabilitado.

En una comprobación SNMPv1 GET-NEXT puntual sobre las cuatro impresoras activas con EWS accesible (`14, 16, 32, 37`), la `16` respondió; el recorrido terminó por el límite intencional de una fila, no por timeout. Las `14, 32, 37` no respondieron dentro de tres segundos. No se registraron lecturas.

Mientras haya impresoras con timeout, no ejecute el recolector sin `-WhatIf` para toda la lista: podría registrar lecturas de las que respondan antes de terminar con errores. Para la operación real, use `-PrinterId` con cada impresora confirmada.

Las respuestas confirmaron soporte para los modelos HP `400 M451dw`, `400 M401dne`, `LaserJet 600 M603`, `LaserJet MFP M426fdw`, `LaserJet M605` y `LaserJet P2055dn`; se actualizaron sus valores `snmp_soportado`. Los mapeos locales incluyen los suministros de tóner identificados. Los kits de mantenimiento no se mapearon como tóner. La impresora 26 informó `level=-2` y `max=-2`, por lo que ese nivel se omite como valor no normalizable. La impresora 23 respondió en el sondeo inicial, pero agotó el tiempo de espera en la comprobación individual posterior.

SNMPv1 no cifra la comunidad ni los datos; úselo únicamente en una red privada autorizada.
