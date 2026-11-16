<?php

namespace Tests\Feature;

use App\Modules\Monitoring\Models\MotivoSinLectura;
use Database\Seeders\MotivosSinLecturaSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MonitoringSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_reading_allows_null_counter_and_reason_when_it_only_has_levels(): void
    {
        $fixture = $this->crearBase();

        $id = $this->insertarLectura($fixture, [
            'contador_paginas' => null,
            'motivo_sin_lectura_id' => null,
        ]);

        $this->assertNotNull($id);
        $this->assertDatabaseHas('lecturas', ['id' => $id]);
    }

    public function test_reading_rejects_a_negative_counter(): void
    {
        $fixture = $this->crearBase();

        $this->expectException(QueryException::class);

        $this->insertarLectura($fixture, ['contador_paginas' => -1]);
    }

    public function test_import_origin_requires_an_import_record(): void
    {
        $fixture = $this->crearBase();

        $this->expectException(QueryException::class);

        $this->insertarLectura($fixture, [
            'origen' => 'importacion',
            'importacion_id' => null,
        ]);
    }

    public function test_manual_reading_requires_a_user(): void
    {
        $fixture = $this->crearBase();

        $this->expectException(QueryException::class);

        $this->insertarLectura($fixture, ['usuario_id' => null]);
    }

    public function test_level_rejects_values_above_one(): void
    {
        $fixture = $this->crearBase();

        $this->expectException(QueryException::class);

        $this->insertarNivel($fixture, ['nivel' => 1.0001]);
    }

    public function test_level_rejects_unknown_colors(): void
    {
        $fixture = $this->crearBase();

        $this->expectException(QueryException::class);

        $this->insertarNivel($fixture, ['color' => 'rojo']);
    }

    public function test_level_slot_is_unique_per_reading_type_and_color(): void
    {
        $fixture = $this->crearBase();
        $this->insertarNivel($fixture);

        $this->expectException(QueryException::class);

        $this->insertarNivel($fixture);
    }

    public function test_monitoring_migration_can_be_rolled_back(): void
    {
        $migration = require database_path('migrations/2026_11_13_223000_crear_tablas_monitoreo.php');

        $migration->down();

        $this->assertFalse(Schema::hasTable('lecturas_nivel'));
        $this->assertFalse(Schema::hasTable('lecturas'));
        $this->assertFalse(Schema::hasTable('motivos_sin_lectura'));
    }

    public function test_missing_read_reasons_are_seeded_idempotently(): void
    {
        $this->seed(MotivosSinLecturaSeeder::class);
        $this->seed(MotivosSinLecturaSeeder::class);

        $this->assertSame(
            ['descuento', 'retirado'],
            MotivoSinLectura::query()->orderBy('nombre')->pluck('nombre')->all()
        );
    }

    private function crearBase(): array
    {
        $now = now();
        $rolId = DB::table('roles')->insertGetId([
            'nombre' => 'tecnico',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $usuarioId = DB::table('users')->insertGetId([
            'name' => 'Usuario de monitoreo',
            'email' => 'monitoreo@example.test',
            'password' => Hash::make('password'),
            'rol_id' => $rolId,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $zonaId = DB::table('zonas')->insertGetId([
            'nombre' => 'Zona de monitoreo',
            'activa' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $lugarId = DB::table('lugares')->insertGetId([
            'zona_id' => $zonaId,
            'nombre' => 'Lugar de monitoreo',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $areaId = DB::table('areas')->insertGetId([
            'lugar_id' => $lugarId,
            'nombre' => 'Área de monitoreo',
            'activa' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $marcaId = DB::table('marcas')->insertGetId([
            'nombre' => 'Marca de monitoreo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $modeloId = DB::table('modelos_impresora')->insertGetId([
            'marca_id' => $marcaId,
            'nombre' => 'Modelo de monitoreo',
            'tipo_impresion' => 'mono',
            'tecnologia' => 'laser',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $estadoId = DB::table('estados_impresora')->insertGetId([
            'nombre' => 'activa',
            'es_operativo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $impresoraId = DB::table('impresoras')->insertGetId([
            'nombre' => 'PRN-MONITOREO-TEST',
            'modelo_id' => $modeloId,
            'area_id' => $areaId,
            'estado_id' => $estadoId,
            'tipo_conexion' => 'usb',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $tipoId = DB::table('tipos_consumible')->insertGetId([
            'nombre' => 'Tipo de monitoreo',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $lecturaId = DB::table('lecturas')->insertGetId([
            'impresora_id' => $impresoraId,
            'fecha_lectura' => '2026-11-13 12:00:00',
            'origen' => 'manual',
            'contador_paginas' => 0,
            'usuario_id' => $usuarioId,
            'created_at' => $now,
        ]);

        return [
            'impresora_id' => $impresoraId,
            'usuario_id' => $usuarioId,
            'tipo_consumible_id' => $tipoId,
            'lectura_id' => $lecturaId,
        ];
    }

    private function insertarLectura(array $fixture, array $overrides = []): int
    {
        $now = now();

        return DB::table('lecturas')->insertGetId(array_merge([
            'impresora_id' => $fixture['impresora_id'],
            'fecha_lectura' => '2026-11-13 12:01:00',
            'origen' => 'manual',
            'contador_paginas' => 10,
            'usuario_id' => $fixture['usuario_id'],
            'created_at' => $now,
        ], $overrides));
    }

    private function insertarNivel(array $fixture, array $overrides = []): int
    {
        return DB::table('lecturas_nivel')->insertGetId(array_merge([
            'lectura_id' => $fixture['lectura_id'],
            'tipo_consumible_id' => $fixture['tipo_consumible_id'],
            'color' => 'negro',
            'nivel' => 0.5,
        ], $overrides));
    }
}
