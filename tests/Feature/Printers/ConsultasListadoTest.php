<?php

namespace Tests\Feature\Printers;

use App\Models\User;
use App\Modules\Printers\Models\Area;
use App\Modules\Printers\Models\EstadoImpresora;
use App\Modules\Printers\Models\Impresora;
use App\Modules\Printers\Models\Lugar;
use App\Modules\Printers\Models\Marca;
use App\Modules\Printers\Models\ModeloImpresora;
use App\Modules\Printers\Models\Zona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsultasListadoTest extends TestCase
{
    use RefreshDatabase;

    private function crearImpresoras(int $cantidad, int $desde): void
    {
        $estado = EstadoImpresora::firstOrCreate(['nombre' => 'activa'], ['es_operativo' => true]);

        for ($i = $desde; $i < $desde + $cantidad; $i++) {
            $zona = Zona::create(['nombre' => "Zona {$i}"]);
            $lugar = Lugar::create(['zona_id' => $zona->id, 'nombre' => "Lugar {$i}"]);
            $area = Area::create(['lugar_id' => $lugar->id, 'nombre' => "Area {$i}"]);
            $marca = Marca::create(['nombre' => "Marca {$i}"]);
            $modelo = ModeloImpresora::create([
                'marca_id' => $marca->id,
                'nombre' => "Modelo {$i}",
                'tipo_impresion' => 'mono',
                'tecnologia' => 'laser',
            ]);

            Impresora::create([
                'nombre' => "PRN-{$i}",
                'modelo_id' => $modelo->id,
                'area_id' => $area->id,
                'estado_id' => $estado->id,
                'tipo_conexion' => 'usb',
            ]);
        }
    }

    /** Cuenta las consultas de una petición, con un usuario recién leído para no arrastrar relaciones cacheadas. */
    private function contarConsultas(int $usuarioId): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs(User::findOrFail($usuarioId))->get('/impresoras')->assertOk();

        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }

    public function test_el_numero_de_consultas_del_listado_no_crece_con_las_impresoras(): void
    {
        $usuario = User::factory()->conRol('consulta')->create();

        $this->crearImpresoras(2, 1);
        $conPocas = $this->contarConsultas($usuario->id);

        $this->crearImpresoras(12, 3);
        $conMuchas = $this->contarConsultas($usuario->id);

        $this->assertSame(
            $conPocas,
            $conMuchas,
            "El listado hace {$conPocas} consultas con 2 impresoras y {$conMuchas} con 14: hay una consulta por fila."
        );
    }
}