# SGIIC — Fase 7: importación histórica

## Libro de trabajo

`Insumos - Fase 7.xlsx` es una copia de `Insumos (1).xlsx`; el original no se modifica. La importación acepta libros `.xlsx` y analiza únicamente la hoja `29052025`.

## Datos que se importan

- `Porcentaje Actual 03/2025` se registra como nivel histórico con fecha 31/03/2025. Un valor numérico de `BN` se guarda como nivel negro; un valor numérico aislado de otro tipo se guarda como `no_aplica`.
- En las filas `CL`, una lista como `65%,40%,27,14%` se asigna en orden a negro, cian, magenta y amarillo. Los porcentajes no reconocidos (por ejemplo, `?`) quedan como error de fila para revisión.
- `SR` y `DESC%` en el bloque de consumo del 29/05/2025 se guardan como lecturas sin dato con sus motivos correspondientes.
- Las cifras numéricas de consumo anual y del 29/05/2025 no se convierten en movimientos ni se importan en esta fase.
- La impresora debe existir en el catálogo y coincidir por nombre o IP. Los registros no reconocidos se reportan como errores de fila.

## Flujo y requisitos

1. Abrir **Importaciones → Analizar Excel**, subir la copia de trabajo y revisar la vista previa.
2. Confirmar la importación. Las filas válidas se procesan y los errores se guardan en el reporte de importación.
3. Las lecturas quedan vinculadas al lote y tienen origen `importacion`; repetir una lectura para la misma impresora/fecha se reporta como duplicado.

La aplicación requiere la extensión ZIP de PHP (`extension=zip`) para procesar `.xlsx`. En Laragon se habilita en el `php.ini` de la versión de PHP activa y luego se reinicia el servicio.

## Resultado de la importación inicial

El 19/11/2026 se importaron desde la hoja `29052025` las 38 filas válidas, que generaron 49 lecturas. Las 25 filas con errores quedaron solo en el reporte y se excluyeron deliberadamente para revisarlas como una actualización posterior; no se infirieron impresoras ni porcentajes.

- Identificación de impresora pendiente: filas 21, 34 y 56–67.
- Porcentaje pendiente: filas 12, 19, 20, 22, 26, 30, 34, 36–38, 43, 52 y 56–67.

Al retomar esas filas, no se debe reimportar el libro completo sin revisar duplicados: las lecturas válidas ya están vinculadas al lote inicial.
