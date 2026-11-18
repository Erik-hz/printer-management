<?php

namespace Tests\Feature\Imports;

use App\Models\User;
use App\Modules\Imports\Models\Importacion;
use App\Modules\Imports\Services\ImportacionExcelService;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Monitoring\Models\Lectura;
use App\Modules\Monitoring\Models\MotivoSinLectura;
use App\Modules\Monitoring\Services\LecturaService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDOException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreaImpresoras;
use Tests\TestCase;

class ImportacionExcelTest extends TestCase
{
    use CreaImpresoras, RefreshDatabase;

    public function test_la_vista_previa_no_guarda_datos_y_la_importacion_registra_porcentajes_y_motivos(): void
    {
        Storage::fake('local');
        $usuario = User::factory()->conRol('responsable_inventario')->create();
        $impresora = $this->crearImpresora('PRN-HISTORICA');
        TipoConsumible::firstOrCreate(['nombre' => 'toner']);
        MotivoSinLectura::firstOrCreate(['nombre' => 'retirado']);
        MotivoSinLectura::firstOrCreate(['nombre' => 'descuento']);
        $archivo = $this->crearLibro([
            ['D' => 'PRN-HISTORICA', 'C' => 'BN', 'J' => 0.75, 'O' => 'SR'],
        ]);

        $this->actingAs($usuario)->post('/importaciones/analizar', [
            'archivo' => $this->archivoSubido($archivo),
        ])->assertRedirect(route('importaciones.vista-previa'));

        $this->actingAs($usuario)->get('/importaciones/vista-previa')
            ->assertOk()
            ->assertSee('PRN-HISTORICA')
            ->assertSee('31/03/2025')
            ->assertSee('sin lectura: SR');

        $this->assertDatabaseCount('importaciones', 0);
        $this->assertDatabaseCount('lecturas', 0);

        $this->actingAs($usuario)->post('/importaciones/ejecutar')
            ->assertRedirect();

        $importacion = Importacion::query()->firstOrFail();
        $this->assertSame('completada', $importacion->estado);
        $this->assertSame(1, (int) $importacion->filas_leidas);
        $this->assertSame(1, (int) $importacion->filas_importadas);
        $this->assertSame(0, (int) $importacion->filas_con_error);

        $this->assertDatabaseHas('lecturas', [
            'impresora_id' => $impresora->id,
            'origen' => 'importacion',
            'fecha_lectura' => '2025-03-31 00:00:00.0000000',
            'importacion_id' => $importacion->id,
        ]);
        $this->assertDatabaseHas('lecturas_nivel', [
            'lectura_id' => DB::table('lecturas')->whereDate('fecha_lectura', '2025-03-31')->value('id'),
            'tipo_consumible_id' => TipoConsumible::where('nombre', 'toner')->value('id'),
            'color' => 'negro',
            'nivel' => 0.75,
        ]);
        $this->assertDatabaseHas('lecturas', [
            'impresora_id' => $impresora->id,
            'origen' => 'importacion',
            'fecha_lectura' => '2025-05-29 00:00:00.0000000',
            'motivo_sin_lectura_id' => MotivoSinLectura::where('nombre', 'retirado')->value('id'),
            'importacion_id' => $importacion->id,
        ]);

        $this->eliminarArchivo($archivo);
    }

    public function test_importa_filas_validas_y_registra_las_impresoras_desconocidas_como_error(): void
    {
        Storage::fake('local');
        $usuario = User::factory()->conRol('administrador')->create();
        $this->crearImpresora('PRN-REGISTRADA');
        TipoConsumible::firstOrCreate(['nombre' => 'toner']);
        $archivo = $this->crearLibro([
            ['D' => 'PRN-REGISTRADA', 'C' => 'BN', 'J' => 0.5],
            ['D' => 'PRN-DESCONOCIDA', 'C' => 'BN', 'J' => 0.4],
        ]);

        $this->analizarYEjecutar($usuario, $archivo);

        $importacion = Importacion::query()->firstOrFail();
        $this->assertSame('con_errores', $importacion->estado);
        $this->assertSame(2, (int) $importacion->filas_leidas);
        $this->assertSame(1, (int) $importacion->filas_importadas);
        $this->assertSame(1, (int) $importacion->filas_con_error);
        $this->assertDatabaseHas('errores_importacion', [
            'importacion_id' => $importacion->id,
            'fila' => 4,
            'columna' => 'NOMBRE/IP',
        ]);
        $this->assertSame(1, Lectura::query()->count());

        $this->eliminarArchivo($archivo);
    }

    public function test_reimportar_el_mismo_archivo_no_duplica_lecturas(): void
    {
        Storage::fake('local');
        $usuario = User::factory()->conRol('administrador')->create();
        $this->crearImpresora('PRN-UNICA');
        TipoConsumible::firstOrCreate(['nombre' => 'toner']);
        $archivo = $this->crearLibro([
            ['D' => 'PRN-UNICA', 'C' => 'BN', 'J' => 0.65],
        ]);

        $this->analizarYEjecutar($usuario, $archivo);
        $this->analizarYEjecutar($usuario, $archivo);

        $importaciones = Importacion::query()->orderBy('id')->get();
        $this->assertSame('completada', $importaciones[0]->estado);
        $this->assertSame('con_errores', $importaciones[1]->estado);
        $this->assertSame(1, (int) $importaciones[1]->filas_con_error);
        $this->assertSame(1, Lectura::query()->count());

        $this->eliminarArchivo($archivo);
    }

    public function test_un_error_de_base_de_datos_no_se_registra_como_error_de_fila(): void
    {
        $usuario = User::factory()->conRol('administrador')->create();
        TipoConsumible::firstOrCreate(['nombre' => 'toner']);
        $error = new QueryException('sqlite', 'insert into lecturas', [], new PDOException('Database unavailable', 8001));
        $lecturas = $this->mock(LecturaService::class);
        $lecturas->shouldReceive('registrar')->once()->andThrow($error);
        $servicio = new ImportacionExcelService($lecturas);

        try {
            $servicio->ejecutar([
                'hoja' => '29052025',
                'filas_leidas' => 1,
                'filas_omitidas' => 0,
                'filas' => [[
                    'fila' => 3,
                    'impresora' => 'PRN-TEST',
                    'impresora_id' => 1,
                    'lecturas' => [[
                        'fecha_lectura' => '2025-03-31 00:00:00',
                        'tipo' => 'nivel',
                        'niveles' => [['color' => 'negro', 'nivel' => 0.5]],
                    ]],
                    'errores' => [],
                ]],
            ], 'historico.xlsx', (int) $usuario->id);
            $this->fail('Se ocultó un error de base de datos.');
        } catch (QueryException $exception) {
            $this->assertSame($error, $exception);
        }

        $importacion = Importacion::query()->firstOrFail();
        $this->assertSame('fallida', $importacion->estado);
        $this->assertNotNull($importacion->finalizada_en);
        $this->assertSame(0, $importacion->errores()->count());
    }

    public function test_importa_porcentajes_de_color_por_canal_y_reporta_valores_desconocidos(): void
    {
        Storage::fake('local');
        $usuario = User::factory()->conRol('administrador')->create();
        $impresoraColor = $this->crearImpresora('PRN-COLOR');
        $this->crearImpresora('PRN-PORCENTAJE-DESCONOCIDO');
        TipoConsumible::firstOrCreate(['nombre' => 'toner']);
        $archivo = $this->crearLibro([
            ['D' => $impresoraColor->nombre, 'C' => 'CL', 'J' => '65%,40%,27,14%'],
            ['D' => 'PRN-PORCENTAJE-DESCONOCIDO', 'C' => 'BN', 'J' => '?'],
        ]);

        $this->analizarYEjecutar($usuario, $archivo);

        $importacion = Importacion::query()->firstOrFail();
        $this->assertSame('con_errores', $importacion->estado);
        $this->assertSame(1, (int) $importacion->filas_importadas);
        $this->assertSame(1, (int) $importacion->filas_con_error);
        $this->assertDatabaseCount('lecturas_nivel', 4);
        $this->assertDatabaseHas('lecturas_nivel', [
            'color' => 'negro',
            'nivel' => 0.65,
        ]);
        $this->assertDatabaseHas('lecturas_nivel', [
            'color' => 'cian',
            'nivel' => 0.40,
        ]);
        $this->assertDatabaseHas('lecturas_nivel', [
            'color' => 'magenta',
            'nivel' => 0.27,
        ]);
        $this->assertDatabaseHas('lecturas_nivel', [
            'color' => 'amarillo',
            'nivel' => 0.14,
        ]);
        $this->assertDatabaseHas('errores_importacion', [
            'importacion_id' => $importacion->id,
            'fila' => 4,
            'columna' => 'Porcentaje Actual 03/2025',
        ]);

        $this->eliminarArchivo($archivo);
    }

    public function test_solo_administrador_y_responsable_pueden_importar(): void
    {
        $this->get('/importaciones')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->conRol('tecnico')->create())
            ->get('/importaciones')
            ->assertForbidden();
    }

    private function analizarYEjecutar(User $usuario, string $archivo): void
    {
        $this->actingAs($usuario)->post('/importaciones/analizar', [
            'archivo' => $this->archivoSubido($archivo),
        ])->assertRedirect(route('importaciones.vista-previa'));

        $this->actingAs($usuario)->post('/importaciones/ejecutar')->assertRedirect();
    }

    private function archivoSubido(string $ruta): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('historico.xlsx', file_get_contents($ruta));
    }

    private function eliminarArchivo(string $ruta): void
    {
        if (file_exists($ruta)) {
            unlink($ruta);
        }
    }

    /** @param  array<int, array<string, mixed>>  $filas */
    private function crearLibro(array $filas): string
    {
        $libro = new Spreadsheet;
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('29052025');
        $hoja->setCellValue('O1', 'CONSUMO 29/05/2025');
        $hoja->setCellValue('D2', 'NOMBRE');
        $hoja->setCellValue('G2', 'IP');
        $hoja->setCellValue('J2', 'Porcentaje Actual 03/2025');
        $hoja->setCellValue('O2', 'NEGRO');

        foreach ($filas as $indice => $valores) {
            $numeroFila = $indice + 3;

            foreach ($valores as $columna => $valor) {
                $hoja->setCellValue("{$columna}{$numeroFila}", $valor);
            }
        }

        $ruta = sys_get_temp_dir().DIRECTORY_SEPARATOR.'importacion-'.uniqid().'.xlsx';
        (new Xlsx($libro))->save($ruta);
        $libro->disconnectWorksheets();

        return $ruta;
    }
}
