<?php

namespace Tests\Feature\Monitoring;

use App\Modules\Monitoring\Models\Lectura;
use App\Modules\Monitoring\Models\MotivoSinLectura;
use App\Modules\Printers\Models\Area;
use App\Modules\Printers\Models\EstadoImpresora;
use App\Modules\Printers\Models\Impresora;
use App\Modules\Printers\Models\Lugar;
use App\Modules\Printers\Models\Marca;
use App\Modules\Printers\Models\ModeloImpresora;
use App\Modules\Printers\Models\Zona;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regla 12 (D-21): no todos los modelos entregan contador de páginas.
 * Una lectura puede traer solo niveles, pero contador y motivo no van juntos.
 */
class LecturaRestriccionesTest extends TestCase
{
    use RefreshDatabase;

    private function impresora(): Impresora
    {
        $zona = Zona::create(['nombre' => 'Zona T']);
        $lugar = Lugar::create(['zona_id' => $zona->id, 'nombre' => 'Lugar T']);
        $area = Area::create(['lugar_id' => $lugar->id, 'nombre' => 'Area T']);
        $marca = Marca::create(['nombre' => 'Marca T']);
        $modelo = ModeloImpresora::create([
            'marca_id' => $marca->id,
            'nombre' => 'Modelo T',
            'tipo_impresion' => 'mono',
            'tecnologia' => 'laser',
        ]);
        $estado = EstadoImpresora::create(['nombre' => 'activa', 'es_operativo' => true]);

        return Impresora::create([
            'nombre' => 'PRN-T',
            'modelo_id' => $modelo->id,
            'area_id' => $area->id,
            'estado_id' => $estado->id,
            'tipo_conexion' => 'usb',
        ]);
    }

    public function test_una_lectura_con_contador_y_motivo_a_la_vez_se_rechaza(): void
    {
        $impresora = $this->impresora();
        $motivo = MotivoSinLectura::create(['nombre' => 'retirado']);

        $this->expectException(QueryException::class);

        Lectura::create([
            'impresora_id' => $impresora->id,
            'fecha_lectura' => now(),
            'origen' => 'snmp',
            'contador_paginas' => 10,
            'motivo_sin_lectura_id' => $motivo->id,
        ]);
    }

    public function test_una_lectura_sin_contador_ni_motivo_se_acepta_porque_trae_solo_niveles(): void
    {
        $impresora = $this->impresora();

        $lectura = Lectura::create([
            'impresora_id' => $impresora->id,
            'fecha_lectura' => now(),
            'origen' => 'snmp',
        ]);

        $this->assertNull($lectura->fresh()->contador_paginas);
        $this->assertDatabaseCount('lecturas', 1);
    }

    public function test_una_lectura_con_solo_motivo_se_acepta(): void
    {
        $impresora = $this->impresora();
        $motivo = MotivoSinLectura::create(['nombre' => 'retirado']);

        Lectura::create([
            'impresora_id' => $impresora->id,
            'fecha_lectura' => now(),
            'origen' => 'snmp',
            'motivo_sin_lectura_id' => $motivo->id,
        ]);

        $this->assertDatabaseCount('lecturas', 1);
    }

    public function test_un_contador_en_cero_es_valido(): void
    {
        $impresora = $this->impresora();

        Lectura::create([
            'impresora_id' => $impresora->id,
            'fecha_lectura' => now(),
            'origen' => 'snmp',
            'contador_paginas' => 0,
        ]);

        $this->assertDatabaseCount('lecturas', 1);
    }
}