# SGIIC — Fase 7: importación histórica

## Libros admitidos

`Insumos - Fase 7.xlsx` es una copia de `Insumos (1).xlsx`; el original no se modifica. Su formato histórico aparece en la hoja `29052025`, pero la aplicación identifica esta estructura por los encabezados, así que la pestaña puede tener otro nombre.

También se aceptan las variantes del formato de `insumos6-10.xlsx`, originalmente en `Hoja1`, con encabezados en la fila 2 y datos desde la fila 3. El nombre de la pestaña puede variar: el sistema busca sus encabezados. La fecha puede estar en la columna `FECHA` o en el encabezado `CONSUMO dd/mm/aaaa` de K1. `NOMBRE` o `IP` identifica la impresora; `TIPO` debe ser `BN` o `CL`; `Porcentaje Actual` contiene el nivel o los niveles separados por comas. En equipos a color, varios porcentajes se asignan en orden negro, cian, magenta y amarillo. Un solo porcentaje de un equipo `CL` se guarda como `no_aplica`, porque el archivo no identifica su color.

Si el libro tiene varias hojas que coinciden con los formatos admitidos, el análisis se detiene y pide dejar una sola hoja válida para evitar importar la pestaña equivocada.

La impresora debe existir en el catálogo y coincidir por nombre o IP. El análisis no crea impresoras, ubicaciones, modelos ni consumibles. Los códigos descritos en `TONER` y las columnas de `CONSUMO ANUAL` (negro, cian, magenta y amarillo) se excluyen: el esquema actual no les asigna destino en este proceso. Las filas con porcentaje vacío se omiten; valores como `?`, `-` o listas con más de cuatro valores aparecen como errores para revisión.

## Datos que se importan

- `Porcentaje Actual 03/2025` se registra como nivel histórico con fecha 31/03/2025. Un valor numérico de `BN` se guarda como nivel negro; un valor numérico aislado de otro tipo se guarda como `no_aplica`.
- En las filas `CL`, una lista como `65%,40%,27,14%` se asigna en orden a negro, cian, magenta y amarillo. Los porcentajes no reconocidos (por ejemplo, `?`) quedan como error de fila para revisión.
- `SR` y `DESC%` en el bloque de consumo del 29/05/2025 se guardan como lecturas sin dato con sus motivos correspondientes.
- Las cifras numéricas de consumo anual y del 29/05/2025 no se convierten en movimientos ni se importan en esta fase.
- La impresora debe existir en el catálogo y coincidir por nombre o IP. Los registros no reconocidos se reportan como errores de fila.

## Flujo y requisitos

1. Abrir **Importaciones → Analizar Excel**, subir una copia de trabajo de uno de los libros admitidos y revisar la vista previa.
2. Confirmar la importación. Las filas válidas se procesan y los errores se guardan en el reporte de importación.
3. Las lecturas quedan vinculadas al lote y tienen origen `importacion`; repetir una lectura para la misma impresora/fecha se reporta como duplicado.

La aplicación requiere la extensión ZIP de PHP (`extension=zip`) para procesar `.xlsx`. En Laragon se habilita en el `php.ini` de la versión de PHP activa y luego se reinicia el servicio. Una lectura importada no se puede duplicar para la misma impresora y fecha; esa comprobación abarca todos los libros de importación.

## Resultado de la importación inicial

El 19/11/2026 se importaron desde la hoja `29052025` las 38 filas válidas, que generaron 49 lecturas. Las 25 filas con errores quedaron solo en el reporte y se excluyeron deliberadamente para revisarlas como una actualización posterior; no se infirieron impresoras ni porcentajes.

- Identificación de impresora pendiente: filas 21, 34 y 56–67.
- Porcentaje pendiente: filas 12, 19, 20, 22, 26, 30, 34, 36–38, 43, 52 y 56–67.

Al retomar esas filas, no se debe reimportar el libro completo sin revisar duplicados: las lecturas válidas ya están vinculadas al lote inicial.
