# SGIIC — Fase 5: monitoreo y lecturas

Última actualización: 18/11/2026

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

Durante el piloto, la API solo acepta conexiones de localhost o redes privadas. También requiere un token Bearer compartido y limita las solicitudes a 300 por minuto por dirección IP:

```text
POST /api/monitoring/lecturas
Authorization: Bearer <MONITOREO_TOKEN>
```

Configure `MONITOREO_TOKEN` en `.env` con un valor secreto y no lo incluya en el repositorio. Si la variable está vacía o no configurada, la API rechaza todas las solicitudes. El endpoint no requiere sesión de usuario.

Ejemplo de cuerpo:

```json
{
  "impresora_id": 1,
  "fecha_lectura": "2026-11-17T18:00:00",
  "origen": "snmp",
  "contador_paginas": 12500,
  "observaciones": "Lectura SNMP"
}
```

Orígenes admitidos por API: `snmp` y `powershell`. La API rechaza solicitudes desde IP pública y responde con JSON incluso cuando el cliente no envía `Accept: application/json`.
