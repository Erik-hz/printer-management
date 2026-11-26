# Importación del inventario de consumibles

Para cargar los artículos desde `Insumos (1).xlsx`, abre **Importaciones → Analizar Excel**, selecciona **Catálogo y stock inicial de consumibles** y elige el libro. El importador busca la hoja por los encabezados `TIPO`, `EN PRODUCCION`, `MODELO`, `CODIGO`, `COLOR`, `CANTIDAD`, `CANTIDAD DE IMPRESORAS` y `CONSUMO ANUAL`; el nombre de la pestaña puede variar.

La vista previa muestra códigos de origen y destino, tipo y color inferidos, stock inicial y modelos compatibles que coincidieron con el catálogo. Al confirmar, el sistema crea cada consumible y su compatibilidad. Si `CANTIDAD` es mayor que cero, también registra una entrada inicial enlazada al lote de importación.

Los códigos repetidos en distintos colores reciben un sufijo de color para conservar su unicidad. Los artículos con códigos ya existentes, cantidades inconsistentes o códigos resultantes duplicados se omiten y aparecen en el reporte de errores. Si un modelo del Excel no coincide con el catálogo, el consumible se importa sin esa relación y la vista previa lo advierte.

No se importan `CONSUMO ANUAL`, `CANTIDAD DE IMPRESORAS` ni datos de compras. Revisa y corrige esos datos por separado si se necesitan en el sistema.
