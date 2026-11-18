<?php

namespace Tests\Concerns;

use App\Modules\Printers\Models\Area;
use App\Modules\Printers\Models\EstadoImpresora;
use App\Modules\Printers\Models\Impresora;
use App\Modules\Printers\Models\Lugar;
use App\Modules\Printers\Models\Marca;
use App\Modules\Printers\Models\ModeloImpresora;
use App\Modules\Printers\Models\Zona;

/** Crea una impresora de prueba con toda su jerarquía. */
trait CreaImpresoras
{
    protected function crearImpresora(string $nombre = 'PRN-T', string $estado = 'activa'): Impresora
    {
        $zona = Zona::firstOrCreate(['nombre' => 'Zona T']);
        $lugar = Lugar::firstOrCreate(['zona_id' => $zona->id, 'nombre' => 'Lugar T']);
        $area = Area::firstOrCreate(['lugar_id' => $lugar->id, 'nombre' => 'Area T']);
        $marca = Marca::firstOrCreate(['nombre' => 'HP']);
        $modelo = ModeloImpresora::firstOrCreate(
            ['marca_id' => $marca->id, 'nombre' => 'Modelo T'],
            ['tipo_impresion' => 'mono', 'tecnologia' => 'laser']
        );
        $estadoImpresora = EstadoImpresora::firstOrCreate(
            ['nombre' => $estado],
            ['es_operativo' => $estado !== 'retirada']
        );

        return Impresora::create([
            'nombre' => $nombre,
            'modelo_id' => $modelo->id,
            'area_id' => $area->id,
            'estado_id' => $estadoImpresora->id,
            'tipo_conexion' => 'usb',
        ]);
    }
}