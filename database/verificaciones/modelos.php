<?php

$modelos = [
    App\Models\User::class,
    App\Modules\Access\Models\Rol::class,
    App\Modules\Imports\Models\Importacion::class,
    App\Modules\Imports\Models\ErrorImportacion::class,
    App\Modules\Printers\Models\Zona::class,
    App\Modules\Printers\Models\Lugar::class,
    App\Modules\Printers\Models\Area::class,
    App\Modules\Printers\Models\Marca::class,
    App\Modules\Printers\Models\ModeloImpresora::class,
    App\Modules\Printers\Models\EstadoImpresora::class,
    App\Modules\Printers\Models\Impresora::class,
    App\Modules\Inventory\Models\TipoConsumible::class,
    App\Modules\Inventory\Models\Consumible::class,
    App\Modules\Inventory\Models\Existencia::class,
    App\Modules\Inventory\Models\MovimientoInventario::class,
    App\Modules\Purchasing\Models\Proveedor::class,
    App\Modules\Purchasing\Models\Compra::class,
    App\Modules\Purchasing\Models\DetalleCompra::class,
    App\Modules\Purchasing\Models\PrecioConsumible::class,
    App\Modules\Monitoring\Models\MotivoSinLectura::class,
    App\Modules\Monitoring\Models\Lectura::class,
    App\Modules\Monitoring\Models\LecturaNivel::class,
];

foreach ($modelos as $clase) {
    echo str_pad(class_basename($clase), 24) . $clase::count() . " filas\n";
}