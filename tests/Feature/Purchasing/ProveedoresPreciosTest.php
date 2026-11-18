<?php

namespace Tests\Feature\Purchasing;

use App\Models\User;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Inventory\Services\ConsumibleService;
use App\Modules\Purchasing\Models\Proveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProveedoresPreciosTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_administrador_gestiona_proveedores(): void
    {
        $admin = User::factory()->conRol('administrador')->create();

        $this->actingAs($admin)->post('/proveedores', [
            'nombre' => 'Suministros SRL',
            'nit' => '123456',
            'contacto' => 'Ana',
            'telefono' => '70000000',
            'correo' => 'ventas@example.test',
            'activo' => '1',
        ])->assertRedirect(route('proveedores.index'))
            ->assertSessionHasNoErrors();

        $proveedor = Proveedor::query()->firstOrFail();
        $this->actingAs($admin)->get('/proveedores')
            ->assertOk()
            ->assertSee('Suministros SRL');

        $this->actingAs($admin)->put("/proveedores/{$proveedor->id}", [
            'nombre' => 'Suministros Bolivia SRL',
            'nit' => '123456',
            'contacto' => 'Ana',
            'telefono' => '70000000',
            'correo' => 'ventas@example.test',
            'activo' => '0',
        ])->assertRedirect(route('proveedores.index'));

        $this->assertDatabaseHas('proveedores', [
            'id' => $proveedor->id,
            'nombre' => 'Suministros Bolivia SRL',
            'activo' => false,
        ]);
    }

    public function test_se_registra_un_precio_por_proveedor_y_se_rechaza_un_solapamiento(): void
    {
        $admin = User::factory()->conRol('administrador')->create();
        $proveedor = Proveedor::query()->create(['nombre' => 'Suministros SRL', 'activo' => true]);
        $consumible = (new ConsumibleService)->crear([
            'tipo_consumible_id' => TipoConsumible::firstOrCreate(['nombre' => 'Toner'])->id,
            'codigo' => 'TONER-PRECIO',
            'color' => 'negro',
            'stock_minimo' => 0,
            'activo' => true,
        ]);
        $datos = [
            'consumible_id' => $consumible->id,
            'proveedor_id' => $proveedor->id,
            'precio' => 125.50,
            'moneda' => 'bob',
            'incluye_iva' => '1',
            'vigente_desde' => '2026-11-01',
            'vigente_hasta' => '',
        ];

        $this->actingAs($admin)->post('/precios-consumibles', $datos)
            ->assertRedirect(route('precios-consumibles.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('precios_consumible', [
            'consumible_id' => $consumible->id,
            'proveedor_id' => $proveedor->id,
            'precio' => 125.50,
            'moneda' => 'BOB',
        ]);
        $this->actingAs($admin)->get('/precios-consumibles')
            ->assertOk()
            ->assertSee('Precios vigentes por proveedor')
            ->assertSee('Suministros SRL');

        $this->actingAs($admin)->post('/precios-consumibles', [
            ...$datos,
            'precio' => 130,
            'vigente_desde' => '2026-11-30',
            'vigente_hasta' => '',
        ])->assertSessionHasErrors('vigente_desde');

        $precioId = (int) DB::table('precios_consumible')->value('id');
        $this->actingAs($admin)->post("/precios-consumibles/{$precioId}/cerrar", [
            'vigente_hasta' => '2026-11-29',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post('/precios-consumibles', [
            ...$datos,
            'precio' => 130,
            'vigente_desde' => '2026-11-30',
            'vigente_hasta' => '',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('precios_consumible', 2);
    }
}
