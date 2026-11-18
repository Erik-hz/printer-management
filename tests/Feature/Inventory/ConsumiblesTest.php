<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Modules\Inventory\Models\Consumible;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Inventory\Services\ConsumibleService;
use App\Modules\Inventory\Services\InventarioService;
use App\Modules\Printers\Models\Marca;
use App\Modules\Printers\Models\ModeloImpresora;
use App\Modules\Purchasing\Models\PrecioConsumible;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsumiblesTest extends TestCase
{
    use RefreshDatabase;

    private function gestor(): User
    {
        return User::factory()->conRol('responsable_inventario')->create();
    }

    private function admin(): User
    {
        return User::factory()->conRol('administrador')->create();
    }

    private function tipo(string $nombre = 'Toner'): TipoConsumible
    {
        return TipoConsumible::firstOrCreate(['nombre' => $nombre]);
    }

    private function modelo(string $nombre = 'Modelo A', string $marca = 'HP'): ModeloImpresora
    {
        $marca = Marca::firstOrCreate(['nombre' => $marca]);

        return ModeloImpresora::firstOrCreate(
            ['marca_id' => $marca->id, 'nombre' => $nombre],
            ['tipo_impresion' => 'mono', 'tecnologia' => 'laser']
        );
    }

    /** Datos de formulario de un consumible válido. */
    private function datos(array $extra = []): array
    {
        return array_merge([
            'tipo_consumible_id' => $this->tipo()->id,
            'codigo' => 'TN-001',
            'descripcion' => 'Consumible de prueba',
            'color' => 'negro',
            'rendimiento_paginas' => 3000,
            'stock_minimo' => 2,
            'activo' => '1',
        ], $extra);
    }

    /** Consumible creado por el servicio, con su existencia en cero. */
    private function crear(array $extra = []): Consumible
    {
        return (new ConsumibleService)->crear(array_merge([
            'tipo_consumible_id' => $this->tipo()->id,
            'codigo' => 'TN-'.uniqid(),
            'color' => 'negro',
            'stock_minimo' => 0,
            'activo' => true,
        ], $extra));
    }

    private function conStock(Consumible $consumible, int $cantidad, User $usuario): void
    {
        (new InventarioService)->registrarAjuste($consumible->id, $cantidad, 'Stock inicial de prueba', $usuario->id);
    }

    public function test_un_invitado_no_ve_los_consumibles(): void
    {
        $this->get('/consumibles')->assertRedirect(route('login'));
    }

    public function test_consulta_y_tecnico_ven_el_listado_pero_no_gestionan(): void
    {
        $consumible = $this->crear(['codigo' => 'VISIBLE-1']);

        foreach (['consulta', 'tecnico'] as $rol) {
            $usuario = User::factory()->conRol($rol)->create();

            $this->actingAs($usuario)->get('/consumibles')->assertOk()->assertSee('VISIBLE-1');
            $this->actingAs($usuario)->get('/consumibles/crear')->assertForbidden();
            $this->actingAs($usuario)->post('/consumibles', $this->datos(['codigo' => 'NO-'.$rol]))->assertForbidden();
            $this->actingAs($usuario)->get("/consumibles/{$consumible->id}/editar")->assertForbidden();
            $this->actingAs($usuario)->put("/consumibles/{$consumible->id}", $this->datos())->assertForbidden();
            $this->actingAs($usuario)->delete("/consumibles/{$consumible->id}")->assertForbidden();
        }

        $this->assertDatabaseCount('consumibles', 1);
    }

    public function test_administrador_y_responsable_crean_consumibles(): void
    {
        foreach (['administrador' => 'TN-ADM', 'responsable_inventario' => 'TN-RESP'] as $rol => $codigo) {
            $usuario = User::factory()->conRol($rol)->create();

            $this->actingAs($usuario)
                ->post('/consumibles', $this->datos(['codigo' => $codigo]))
                ->assertRedirect(route('consumibles.index'));

            $this->assertDatabaseHas('consumibles', ['codigo' => $codigo]);
        }
    }

    public function test_crear_deja_la_existencia_en_cero_y_guarda_la_compatibilidad(): void
    {
        $modelo = $this->modelo();

        $this->actingAs($this->gestor())
            ->post('/consumibles', $this->datos(['modelos' => [$modelo->id]]))
            ->assertSessionHasNoErrors();

        $consumible = Consumible::where('codigo', 'TN-001')->firstOrFail();

        $this->assertDatabaseHas('existencias', ['consumible_id' => $consumible->id, 'cantidad' => 0]);
        $this->assertDatabaseHas('modelo_consumible', ['consumible_id' => $consumible->id, 'modelo_id' => $modelo->id]);
    }

    public function test_un_codigo_duplicado_se_rechaza_sin_importar_las_mayusculas(): void
    {
        $this->crear(['codigo' => 'TN-001']);

        $this->actingAs($this->gestor())
            ->post('/consumibles', $this->datos(['codigo' => 'tn-001']))
            ->assertSessionHasErrors(['codigo' => 'Ya existe un consumible con ese código.']);

        $this->assertDatabaseCount('consumibles', 1);
    }

    public function test_los_valores_invalidos_se_rechazan(): void
    {
        $gestor = $this->gestor();

        $casos = [
            'color' => ['color' => 'rojo'],
            'stock_minimo' => ['stock_minimo' => -1],
            'rendimiento_paginas' => ['rendimiento_paginas' => 0],
            'tipo_consumible_id' => ['tipo_consumible_id' => 999999],
            'codigo' => ['codigo' => ''],
            'modelos.0' => ['modelos' => [999999]],
        ];

        foreach ($casos as $campo => $extra) {
            $this->actingAs($gestor)
                ->post('/consumibles', $this->datos($extra))
                ->assertSessionHasErrors($campo);
        }

        $this->assertDatabaseCount('consumibles', 0);
    }

    public function test_el_rendimiento_vacio_se_guarda_como_nulo(): void
    {
        $this->actingAs($this->gestor())
            ->post('/consumibles', $this->datos(['rendimiento_paginas' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Consumible::where('codigo', 'TN-001')->firstOrFail()->rendimiento_paginas);
    }

    public function test_editar_sincroniza_la_compatibilidad_y_conserva_las_existencias(): void
    {
        $gestor = $this->gestor();
        $a = $this->modelo('Modelo A');
        $b = $this->modelo('Modelo B');
        $consumible = $this->crear(['codigo' => 'TN-001', 'modelos' => [$a->id]]);
        $this->conStock($consumible, 5, $gestor);

        $this->actingAs($gestor)
            ->put("/consumibles/{$consumible->id}", $this->datos(['modelos' => [$b->id]]))
            ->assertRedirect(route('consumibles.index'));

        $this->assertDatabaseMissing('modelo_consumible', ['consumible_id' => $consumible->id, 'modelo_id' => $a->id]);
        $this->assertDatabaseHas('modelo_consumible', ['consumible_id' => $consumible->id, 'modelo_id' => $b->id]);
        $this->assertDatabaseHas('existencias', ['consumible_id' => $consumible->id, 'cantidad' => 5]);
    }

    public function test_editar_conserva_su_propio_codigo(): void
    {
        $consumible = $this->crear(['codigo' => 'TN-001']);

        $this->actingAs($this->gestor())
            ->put("/consumibles/{$consumible->id}", $this->datos(['codigo' => 'TN-001', 'descripcion' => 'Cambiada']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('consumibles', ['id' => $consumible->id, 'descripcion' => 'Cambiada']);
    }

    public function test_desmarcar_activo_desactiva_el_consumible(): void
    {
        $consumible = $this->crear(['codigo' => 'TN-001']);

        $datos = $this->datos();
        unset($datos['activo']);

        $this->actingAs($this->gestor())->put("/consumibles/{$consumible->id}", $datos);

        $this->assertDatabaseHas('consumibles', ['id' => $consumible->id, 'activo' => false]);
    }

    public function test_eliminar_sin_dependencias_es_un_borrado_logico_y_libera_el_modelo(): void
    {
        $modelo = $this->modelo();
        $consumible = $this->crear(['modelos' => [$modelo->id]]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->delete("/consumibles/{$consumible->id}")
            ->assertRedirect(route('consumibles.index'));

        $this->assertSoftDeleted('consumibles', ['id' => $consumible->id]);
        $this->assertDatabaseMissing('modelo_consumible', ['consumible_id' => $consumible->id]);

        // La compatibilidad desvinculada no bloquea el borrado del modelo.
        $this->actingAs($admin)->delete("/modelos/{$modelo->id}")->assertRedirect(route('modelos.index'));
        $this->assertDatabaseMissing('modelos_impresora', ['id' => $modelo->id]);
    }

    public function test_un_consumible_con_movimientos_no_se_elimina(): void
    {
        $gestor = $this->gestor();
        $consumible = $this->crear();
        $this->conStock($consumible, 1, $gestor);

        $this->actingAs($gestor)->delete("/consumibles/{$consumible->id}")->assertSessionHasErrors('general');

        $this->assertNotSoftDeleted('consumibles', ['id' => $consumible->id]);
    }

    public function test_un_consumible_con_compras_no_se_elimina(): void
    {
        $gestor = $this->gestor();
        $consumible = $this->crear();
        $now = now();

        $proveedorId = DB::table('proveedores')->insertGetId([
            'nombre' => 'Proveedor de prueba',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $compraId = DB::table('compras')->insertGetId([
            'proveedor_id' => $proveedorId,
            'usuario_id' => $gestor->id,
            'fecha_orden' => '2026-11-13',
            'estado' => 'ordenada',
            'moneda' => 'BOB',
            'incluye_iva' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('detalle_compra')->insert([
            'compra_id' => $compraId,
            'consumible_id' => $consumible->id,
            'cantidad' => 5,
            'cantidad_recibida' => 0,
            'precio_unitario' => 100,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($gestor)->delete("/consumibles/{$consumible->id}")->assertSessionHasErrors('general');

        $this->assertNotSoftDeleted('consumibles', ['id' => $consumible->id]);
    }

    public function test_un_consumible_con_precios_no_se_elimina(): void
    {
        $consumible = $this->crear();

        PrecioConsumible::create([
            'consumible_id' => $consumible->id,
            'precio' => 10,
            'incluye_iva' => false,
            'vigente_desde' => '2026-01-01',
        ]);

        $this->actingAs($this->gestor())->delete("/consumibles/{$consumible->id}")->assertSessionHasErrors('general');

        $this->assertNotSoftDeleted('consumibles', ['id' => $consumible->id]);
    }

    public function test_el_codigo_se_puede_reutilizar_tras_eliminar(): void
    {
        $gestor = $this->gestor();
        $consumible = $this->crear(['codigo' => 'TN-001']);

        $this->actingAs($gestor)->delete("/consumibles/{$consumible->id}");

        $this->actingAs($gestor)
            ->post('/consumibles', $this->datos(['codigo' => 'TN-001']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Consumible::where('codigo', 'TN-001')->count());
    }

    public function test_el_listado_se_filtra_por_tipo_color_y_busqueda(): void
    {
        $tinta = $this->tipo('Tinta');
        $this->crear(['codigo' => 'AAA-1', 'descripcion' => 'Primero']);
        $this->crear(['codigo' => 'BBB-2', 'tipo_consumible_id' => $tinta->id, 'color' => 'cian']);
        $lector = User::factory()->conRol('consulta')->create();

        $this->actingAs($lector)->get("/consumibles?tipo={$tinta->id}")
            ->assertOk()->assertSee('BBB-2')->assertDontSee('AAA-1');

        $this->actingAs($lector)->get('/consumibles?color=cian')
            ->assertOk()->assertSee('BBB-2')->assertDontSee('AAA-1');

        $this->actingAs($lector)->get('/consumibles?q=Primero')
            ->assertOk()->assertSee('AAA-1')->assertDontSee('BBB-2');
    }

    public function test_el_filtro_de_stock_bajo_y_la_etiqueta(): void
    {
        $gestor = $this->gestor();
        $bajo = $this->crear(['codigo' => 'BAJO-1', 'stock_minimo' => 5]);
        $ok = $this->crear(['codigo' => 'OK-1', 'stock_minimo' => 1]);
        $this->conStock($bajo, 2, $gestor);
        $this->conStock($ok, 3, $gestor);

        $this->actingAs($gestor)->get('/consumibles?bajo=1')
            ->assertOk()
            ->assertSee('BAJO-1')
            ->assertSee('Stock bajo')
            ->assertDontSee('OK-1');
    }

    public function test_el_listado_no_hace_consultas_por_fila(): void
    {
        $usuario = User::factory()->conRol('consulta')->create();
        $modelo = $this->modelo();

        $contar = function () use ($usuario): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs(User::findOrFail($usuario->id))->get('/consumibles')->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        for ($i = 1; $i <= 2; $i++) {
            $this->crear(['codigo' => "N-{$i}", 'modelos' => [$modelo->id]]);
        }
        $conPocos = $contar();

        for ($i = 3; $i <= 14; $i++) {
            $this->crear(['codigo' => "N-{$i}", 'modelos' => [$modelo->id]]);
        }
        $conMuchos = $contar();

        $this->assertSame($conPocos, $conMuchos, 'El listado de consumibles hace una consulta por fila.');
    }

    public function test_los_formularios_se_muestran(): void
    {
        $gestor = $this->gestor();
        $consumible = $this->crear(['codigo' => 'TN-001']);

        $this->actingAs($gestor)->get('/consumibles/crear')->assertOk()->assertSee('Nuevo consumible');
        $this->actingAs($gestor)->get("/consumibles/{$consumible->id}/editar")
            ->assertOk()
            ->assertSee('Editar consumible')
            ->assertSee('Existencias actuales');
    }
}