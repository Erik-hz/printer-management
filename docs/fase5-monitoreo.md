# SGIIC — Fase 5: monitoreo y lecturas

Última actualización: 17/11/2026

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

Durante el piloto, la API solo acepta conexiones de localhost o redes privadas:

```text
POST /api/monitoring/lecturas
```

No requiere sesión ni autenticación en esta etapa. Ejemplo de cuerpo:

```json
{
  "impresora_id": 1,
  "fecha_lectura": "2026-11-17T18:00:00",
  "origen": "snmp",
  "contador_paginas": 12500,
  "observaciones": "Lectura SNMP"
}
```

Orígenes admitidos por API: `snmp` y `powershell`. La API rechaza solicitudes desde IP pública.
