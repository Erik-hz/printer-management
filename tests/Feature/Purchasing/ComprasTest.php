<?php

namespace Tests\Feature\Purchasing;

use App\Models\User;
use App\Modules\Inventory\Models\Consumible;
use App\Modules\Inventory\Models\Existencia;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Inventory\Services\ConsumibleService;
use App\Modules\Purchasing\Models\Proveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComprasTest extends TestCase
{
    use RefreshDatabase;

    private function gestor(): User
    {
        return User::factory()->conRol('responsable_inventario')->create();
    }

    private function consumible(string $codigo = 'TONER-TEST'): Consumible
    {
        return (new ConsumibleService)->crear([
            'tipo_consumible_id' => TipoConsumible::firstOrCreate(['nombre' => 'Toner'])->id,
            'codigo' => $codigo,
            'color' => 'negro',
            'stock_minimo' => 0,
            'activo' => true,
        ]);
    }

    private function proveedor(string $nombre = 'Proveedor de prueba'): Proveedor
    {
        return Proveedor::query()->create(['nombre' => $nombre, 'activo' => true]);
    }

    public function test_el_responsable_crea_orden_y_registra_recepciones_parciales_hasta_completarla(): void
    {
        $usuario = $this->gestor();
        $proveedor = $this->proveedor();
        $consumible = $this->consumible();

        $this->actingAs($usuario)->post('/compras', [
            'proveedor_id' => $proveedor->id,
            'fecha_orden' => '2026-11-17',
            'numero_documento' => 'OC-001',
            'moneda' => 'BOB',
            'incluye_iva' => '1',
            'detalles' => [[
                'consumible_id' => $consumible->id,
                'cantidad' => 5,
                'precio_unitario' => 120.50,
            ]],
        ])->assertSessionHasNoErrors();

        $compraId = (int) DB::table('compras')->value('id');
        $detalleId = (int) DB::table('detalle_compra')->value('id');

        $this->actingAs($usuario)
            ->post("/compras/{$compraId}/detalles/{$detalleId}/recepciones", ['cantidad' => 2])
            ->assertRedirect("/compras/{$compraId}")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('compras', ['id' => $compraId, 'estado' => 'parcialmente_recibida']);
        $this->assertDatabaseHas('existencias', ['consumible_id' => $consumible->id, 'cantidad' => 2]);
        $this->assertDatabaseHas('movimientos_inventario', [
            'detalle_compra_id' => $detalleId,
            'tipo' => 'entrada',
            'cantidad' => 2,
            'usuario_id' => $usuario->id,
        ]);

        $this->actingAs($usuario)
            ->post("/compras/{$compraId}/detalles/{$detalleId}/recepciones", ['cantidad' => 3])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('compras', ['id' => $compraId, 'estado' => 'recibida']);
        $this->assertSame(5, (int) Existencia::findOrFail($consumible->id)->cantidad);
        $this->assertDatabaseCount('movimientos_inventario', 2);
    }

    public function test_no_se_acepta_recibir_mas_de_lo_pendiente(): void
    {
        $usuario = $this->gestor();
        $proveedor = $this->proveedor();
        $consumible = $this->consumible();

        $this->actingAs($usuario)->post('/compras', [
            'proveedor_id' => $proveedor->id,
            'fecha_orden' => '2026-11-17',
            'moneda' => 'BOB',
            'incluye_iva' => false,
            'detalles' => [[
                'consumible_id' => $consumible->id,
                'cantidad' => 2,
                'precio_unitario' => 20,
            ]],
        ]);
        $compraId = (int) DB::table('compras')->value('id');
        $detalleId = (int) DB::table('detalle_compra')->value('id');

        $this->actingAs($usuario)
            ->post("/compras/{$compraId}/detalles/{$detalleId}/recepciones", ['cantidad' => 3])
            ->assertSessionHasErrors('cantidad');

        $this->assertSame(0, (int) Existencia::findOrFail($consumible->id)->cantidad);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_los_usuarios_sin_permiso_no_pueden_gestionar_compras(): void
    {
        $this->get('/compras')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->conRol('tecnico')->create())
            ->get('/compras')
            ->assertForbidden();
    }

    public function test_la_pantalla_de_orden_muestra_las_cantidades_pendientes(): void
    {
        $usuario = $this->gestor();
        $proveedor = $this->proveedor();
        $consumible = $this->consumible();
        $this->actingAs($usuario)->post('/compras', [
            'proveedor_id' => $proveedor->id,
            'fecha_orden' => '2026-11-17',
            'moneda' => 'BOB',
            'incluye_iva' => false,
            'detalles' => [[
                'consumible_id' => $consumible->id,
                'cantidad' => 4,
                'precio_unitario' => 20,
            ]],
        ]);
        $compraId = (int) DB::table('compras')->value('id');

        $this->actingAs($usuario)->get("/compras/{$compraId}")
            ->assertOk()
            ->assertSee($consumible->codigo)
            ->assertSee('Pendiente')
            ->assertSee('max="4"', false);
    }
}
