<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Modules\Inventory\Models\Consumible;
use App\Modules\Inventory\Models\Existencia;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Inventory\Services\ConsumibleService;
use App\Modules\Inventory\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaImpresoras;
use Tests\TestCase;

class MovimientosTest extends TestCase
{
    use CreaImpresoras, RefreshDatabase;

    private function gestor(): User
    {
        return User::factory()->conRol('responsable_inventario')->create();
    }

    /** Consumible creado por el servicio; con stock inicial registra un ajuste. */
    private function consumible(
        array $extra = [],
        int $stock = 0,
        ?User $usuario = null,
        string $motivo = 'Stock inicial de prueba',
    ): Consumible {
        $consumible = (new ConsumibleService)->crear(array_merge([
            'tipo_consumible_id' => TipoConsumible::firstOrCreate(['nombre' => 'Toner'])->id,
            'codigo' => 'TN-'.uniqid(),
            'color' => 'negro',
            'stock_minimo' => 0,
            'activo' => true,
        ], $extra));

        if ($stock > 0) {
            (new InventarioService)->registrarAjuste($consumible->id, $stock, $motivo, ($usuario ?? $this->gestor())->id);
        }

        return $consumible;
    }

    private function existencia(Consumible $consumible): int
    {
        return (int) Existencia::findOrFail($consumible->id)->cantidad;
    }

    public function test_un_invitado_no_ve_los_movimientos(): void
    {
        $this->get('/movimientos')->assertRedirect(route('login'));
        $this->get('/movimientos/crear')->assertRedirect(route('login'));
    }

    public function test_consulta_y_tecnico_ven_el_listado_pero_no_registran(): void
    {
        $consumible = $this->consumible([], 3, null, 'MOTIVO-VISIBLE');

        foreach (['consulta', 'tecnico'] as $rol) {
            $usuario = User::factory()->conRol($rol)->create();

            $this->actingAs($usuario)->get('/movimientos')->assertOk()->assertSee('MOTIVO-VISIBLE');
            $this->actingAs($usuario)->get('/movimientos/crear')->assertForbidden();
            $this->actingAs($usuario)->post('/movimientos', [
                'tipo' => 'entrada',
                'consumible_id' => $consumible->id,
                'cantidad' => 1,
                'motivo' => 'No debería pasar',
            ])->assertForbidden();
        }

        $this->assertSame(3, $this->existencia($consumible));
    }

    public function test_una_entrada_manual_con_motivo_suma_al_stock(): void
    {
        $gestor = $this->gestor();
        $consumible = $this->consumible();

        $this->actingAs($gestor)
            ->post('/movimientos', [
                'tipo' => 'entrada',
                'consumible_id' => $consumible->id,
                'cantidad' => 5,
                'motivo' => 'Stock inicial',
            ])
            ->assertRedirect(route('movimientos.index', ['consumible' => $consumible->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(5, $this->existencia($consumible));
        $this->assertDatabaseHas('movimientos_inventario', [
            'consumible_id' => $consumible->id,
            'tipo' => 'entrada',
            'cantidad' => 5,
            'saldo_resultante' => 5,
            'motivo' => 'Stock inicial',
            'usuario_id' => $gestor->id,
        ]);
    }

    public function test_una_entrada_sin_motivo_se_rechaza(): void
    {
        $consumible = $this->consumible();

        $this->actingAs($this->gestor())
            ->post('/movimientos', ['tipo' => 'entrada', 'consumible_id' => $consumible->id, 'cantidad' => 5])
            ->assertSessionHasErrors('motivo');

        $this->assertSame(0, $this->existencia($consumible));
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_no_se_ingresa_stock_a_un_consumible_inactivo(): void
    {
        $consumible = $this->consumible(['activo' => false]);

        $this->actingAs($this->gestor())
            ->post('/movimientos', [
                'tipo' => 'entrada',
                'consumible_id' => $consumible->id,
                'cantidad' => 5,
                'motivo' => 'Stock inicial',
            ])
            ->assertSessionHasErrors('consumible_id');

        $this->assertSame(0, $this->existencia($consumible));
    }

    public function test_una_salida_descuenta_y_guarda_impresora_y_ticket(): void
    {
        $gestor = $this->gestor();
        $impresora = $this->crearImpresora('PRN-1');
        $consumible = $this->consumible([], 5, $gestor);

        $this->actingAs($gestor)
            ->post('/movimientos', [
                'tipo' => 'salida',
                'consumible_id' => $consumible->id,
                'cantidad' => 2,
                'impresora_id' => $impresora->id,
                'ticket' => 'R-000123',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $this->existencia($consumible));
        $this->assertDatabaseHas('movimientos_inventario', [
            'consumible_id' => $consumible->id,
            'tipo' => 'salida',
            'cantidad' => -2,
            'saldo_resultante' => 3,
            'impresora_id' => $impresora->id,
            'ticket' => 'R-000123',
        ]);
    }

    public function test_una_salida_sin_impresora_se_rechaza_en_la_pantalla(): void
    {
        $gestor = $this->gestor();
        $consumible = $this->consumible([], 5, $gestor);

        $this->actingAs($gestor)
            ->post('/movimientos', ['tipo' => 'salida', 'consumible_id' => $consumible->id, 'cantidad' => 1])
            ->assertSessionHasErrors('impresora_id');

        $this->assertSame(5, $this->existencia($consumible));
    }

    public function test_una_salida_a_una_impresora_retirada_se_rechaza(): void
    {
        $gestor = $this->gestor();
        $retirada = $this->crearImpresora('PRN-RETIRADA', 'retirada');
        $consumible = $this->consumible([], 5, $gestor);

        $this->actingAs($gestor)
            ->post('/movimientos', [
                'tipo' => 'salida',
                'consumible_id' => $consumible->id,
                'cantidad' => 1,
                'impresora_id' => $retirada->id,
            ])
            ->assertSessionHasErrors('impresora_id');

        $this->assertSame(5, $this->existencia($consumible));
    }

    public function test_una_salida_mayor_que_el_stock_se_rechaza(): void
    {
        $gestor = $this->gestor();
        $impresora = $this->crearImpresora();
        $consumible = $this->consumible([], 2, $gestor);

        $this->actingAs($gestor)
            ->post('/movimientos', [
                'tipo' => 'salida',
                'consumible_id' => $consumible->id,
                'cantidad' => 3,
                'impresora_id' => $impresora->id,
            ])
            ->assertSessionHasErrors('cantidad');

        $this->assertSame(2, $this->existencia($consumible));
        $this->assertDatabaseCount('movimientos_inventario', 1); // solo el ajuste del stock inicial
    }

    public function test_una_salida_no_lleva_motivo(): void
    {
        $gestor = $this->gestor();
        $impresora = $this->crearImpresora();
        $consumible = $this->consumible([], 5, $gestor);

        $this->actingAs($gestor)
            ->post('/movimientos', [
                'tipo' => 'salida',
                'consumible_id' => $consumible->id,
                'cantidad' => 1,
                'impresora_id' => $impresora->id,
                'motivo' => 'No corresponde',
            ])
            ->assertSessionHasErrors('motivo');

        $this->assertSame(5, $this->existencia($consumible));
    }

    public function test_un_ajuste_con_motivo_suma_o_resta(): void
    {
        $gestor = $this->gestor();
        $consumible = $this->consumible([], 5, $gestor);

        $this->actingAs($gestor)
            ->post('/movimientos', ['tipo' => 'ajuste', 'consumible_id' => $consumible->id, 'cantidad' => 3, 'motivo' => 'Conteo físico'])
            ->assertSessionHasNoErrors();
        $this->assertSame(8, $this->existencia($consumible));

        $this->actingAs($gestor)
            ->post('/movimientos', ['tipo' => 'ajuste', 'consumible_id' => $consumible->id, 'cantidad' => -2, 'motivo' => 'Merma'])
            ->assertSessionHasNoErrors();
        $this->assertSame(6, $this->existencia($consumible));

        $this->assertDatabaseHas('movimientos_inventario', [
            'consumible_id' => $consumible->id,
            'tipo' => 'ajuste',
            'cantidad' => -2,
            'saldo_resultante' => 6,
            'motivo' => 'Merma',
        ]);
    }

    public function test_los_ajustes_invalidos_se_rechazan(): void
    {
        $gestor = $this->gestor();
        $consumible = $this->consumible([], 5, $gestor);
        $base = ['tipo' => 'ajuste', 'consumible_id' => $consumible->id];

        $this->actingAs($gestor)
            ->post('/movimientos', $base + ['cantidad' => 1])
            ->assertSessionHasErrors('motivo');

        $this->actingAs($gestor)
            ->post('/movimientos', $base + ['cantidad' => 0, 'motivo' => 'Cero'])
            ->assertSessionHasErrors('cantidad');

        $this->actingAs($gestor)
            ->post('/movimientos', $base + ['cantidad' => -99, 'motivo' => 'Demasiado'])
            ->assertSessionHasErrors('cantidad');

        $this->assertSame(5, $this->existencia($consumible));
    }

    public function test_el_tipo_y_la_cantidad_invalidos_se_rechazan(): void
    {
        $gestor = $this->gestor();
        $consumible = $this->consumible([], 5, $gestor);
        $base = ['consumible_id' => $consumible->id, 'motivo' => 'Prueba'];

        $this->actingAs($gestor)
            ->post('/movimientos', $base + ['tipo' => 'merma', 'cantidad' => 1])
            ->assertSessionHasErrors('tipo');

        foreach ([0, -1, 'abc', 1.5] as $cantidad) {
            $this->actingAs($gestor)
                ->post('/movimientos', $base + ['tipo' => 'entrada', 'cantidad' => $cantidad])
                ->assertSessionHasErrors('cantidad');
        }

        $this->assertSame(5, $this->existencia($consumible));
    }

    public function test_un_consumible_inexistente_o_eliminado_se_rechaza(): void
    {
        $gestor = $this->gestor();
        $eliminado = $this->consumible();
        $eliminado->delete();

        foreach ([999999, $eliminado->id] as $id) {
            $this->actingAs($gestor)
                ->post('/movimientos', ['tipo' => 'entrada', 'consumible_id' => $id, 'cantidad' => 1, 'motivo' => 'Prueba'])
                ->assertSessionHasErrors('consumible_id');
        }

        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_un_ticket_demasiado_largo_se_rechaza(): void
    {
        $consumible = $this->consumible();

        $this->actingAs($this->gestor())
            ->post('/movimientos', [
                'tipo' => 'entrada',
                'consumible_id' => $consumible->id,
                'cantidad' => 1,
                'motivo' => 'Prueba',
                'ticket' => str_repeat('A', 101),
            ])
            ->assertSessionHasErrors('ticket');

        $this->assertSame(0, $this->existencia($consumible));
    }

    public function test_no_hay_rutas_para_editar_ni_borrar_movimientos(): void
    {
        $admin = User::factory()->conRol('administrador')->create();

        foreach (['put', 'patch', 'delete'] as $metodo) {
            $estado = $this->actingAs($admin)->{$metodo}('/movimientos/1')->getStatusCode();

            $this->assertContains($estado, [404, 405], "{$metodo} /movimientos/1");
        }
    }

    public function test_el_listado_se_filtra_por_tipo_consumible_y_busqueda(): void
    {
        $gestor = $this->gestor();
        $impresora = $this->crearImpresora();
        $a = $this->consumible(['codigo' => 'AAA-FILTRO'], 5, $gestor, 'MOTIVO-AAA');
        $b = $this->consumible(['codigo' => 'BBB-FILTRO'], 5, $gestor, 'MOTIVO-BBB');
        (new InventarioService)->registrarSalida($a->id, 2, $gestor->id, $impresora->id, 'R-000777');
        $lector = User::factory()->conRol('consulta')->create();

        $this->actingAs($lector)->get('/movimientos?tipo=salida')
            ->assertOk()
            ->assertSee('R-000777')
            ->assertSee('-2')
            ->assertDontSee('MOTIVO-AAA')
            ->assertDontSee('MOTIVO-BBB');

        $this->actingAs($lector)->get('/movimientos?consumible='.$b->id)
            ->assertOk()
            ->assertSee('MOTIVO-BBB')
            ->assertSee('+5')
            ->assertDontSee('MOTIVO-AAA')
            ->assertDontSee('R-000777');

        $this->actingAs($lector)->get('/movimientos?q=MOTIVO-AAA')
            ->assertOk()
            ->assertSee('MOTIVO-AAA')
            ->assertDontSee('MOTIVO-BBB');
    }

    public function test_el_listado_no_hace_consultas_por_fila(): void
    {
        $gestor = $this->gestor();
        $impresora = $this->crearImpresora();
        $consumible = $this->consumible([], 50, $gestor);
        $servicio = new InventarioService;

        $contar = function () use ($gestor): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs(User::findOrFail($gestor->id))->get('/movimientos')->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        for ($i = 0; $i < 2; $i++) {
            $servicio->registrarSalida($consumible->id, 1, $gestor->id, $impresora->id);
        }
        $conPocos = $contar();

        for ($i = 0; $i < 12; $i++) {
            $servicio->registrarSalida($consumible->id, 1, $gestor->id, $impresora->id);
        }
        $conMuchos = $contar();

        $this->assertSame($conPocos, $conMuchos, 'El listado de movimientos hace una consulta por fila.');
    }

    public function test_el_formulario_se_muestra_para_cada_tipo(): void
    {
        $gestor = $this->gestor();
        $this->crearImpresora('PRN-FORM');
        $this->consumible();

        $this->actingAs($gestor)->get('/movimientos/crear')->assertOk()->assertSee('Registrar movimiento');
        $this->actingAs($gestor)->get('/movimientos/crear?tipo=salida')->assertOk()->assertSee('Impresora de destino');
        $this->actingAs($gestor)->get('/movimientos/crear?tipo=ajuste')->assertOk()->assertSee('Positiva suma');
        $this->actingAs($gestor)->get('/movimientos/crear?tipo=invalido')->assertOk();
    }
}