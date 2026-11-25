<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Modules\Inventory\Models\Consumible;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Inventory\Services\ConsumibleService;
use App\Modules\Monitoring\Services\LecturaService;
use App\Modules\Purchasing\Models\Compra;
use App\Modules\Purchasing\Models\Proveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaImpresoras;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreaImpresoras, RefreshDatabase;

    public function test_el_tecnico_ve_resumenes_autorizados_y_no_ve_compras(): void
    {
        $tecnico = User::factory()->conRol('tecnico')->create();
        $gestor = User::factory()->conRol('responsable_inventario')->create();
        $impresora = $this->crearImpresora('PRN-DASHBOARD');
        $this->crearImpresora('PRN-RETIRADA', 'retirada');
        $consumible = $this->crearConsumible('TONER-BAJO', 5, 2);
        $this->crearConsumible('TONER-EN-MINIMO', 2, 2);
        $compra = $this->crearCompra($gestor, $consumible, 'OC-PRIVADA');

        $lecturaService = new LecturaService;
        $lecturaService->registrar([
            'impresora_id' => $impresora->id,
            'fecha_lectura' => '2026-11-19 10:00:00',
            'origen' => 'snmp',
            'contador_paginas' => 1200,
            'niveles' => [[
                'tipo_consumible_id' => TipoConsumible::firstOrCreate(['nombre' => 'Toner'])->id,
                'color' => 'negro',
                'nivel' => 0.75,
            ]],
        ]);
        $lecturaService->registrar([
            'impresora_id' => $impresora->id,
            'fecha_lectura' => '2026-11-18 10:00:00',
            'origen' => 'manual',
            'contador_paginas' => 1100,
            'usuario_id' => $tecnico->id,
        ]);

        $this->actingAs($tecnico)->get('/inicio')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('PRN-DASHBOARD')
            ->assertSeeInOrder(['Registradas', '2', 'En estado operativo', '1'])
            ->assertSee('Consumibles bajo el mínimo')
            ->assertSee('TONER-BAJO')
            ->assertDontSee('TONER-EN-MINIMO')
            ->assertSee('2 / mínimo 5')
            ->assertSee('Últimas lecturas')
            ->assertSeeInOrder([
                '19/11/2026 10:00',
                'SNMP',
                '1,200 páginas',
                '18/11/2026 10:00',
                'Manual',
                '1,100 páginas',
            ])
            ->assertSee('75,0%')
            ->assertSee('role="progressbar"', false)
            ->assertDontSee('Órdenes por recibir')
            ->assertDontSee('OC-PRIVADA')
            ->assertDontSee($compra->proveedor->nombre);
    }

    public function test_el_responsable_ve_las_ordenes_pendientes(): void
    {
        $responsable = User::factory()->conRol('responsable_inventario')->create();
        $consumible = $this->crearConsumible('TONER-COMPRA', 0, 0);
        $this->crearCompra($responsable, $consumible, 'OC-PENDIENTE');

        $this->actingAs($responsable)->get('/inicio')
            ->assertOk()
            ->assertSee('Órdenes por recibir')
            ->assertSee('OC-PENDIENTE')
            ->assertSee('Proveedor dashboard');
    }

    public function test_un_invitado_no_puede_ver_el_dashboard(): void
    {
        $this->get('/inicio')->assertRedirect(route('login'));
    }

    private function crearConsumible(string $codigo, int $minimo, int $cantidad): Consumible
    {
        $consumible = (new ConsumibleService)->crear([
            'tipo_consumible_id' => TipoConsumible::firstOrCreate(['nombre' => 'Toner'])->id,
            'codigo' => $codigo,
            'descripcion' => "Descripción {$codigo}",
            'color' => 'negro',
            'stock_minimo' => $minimo,
            'activo' => true,
        ]);

        $consumible->existencia()->update(['cantidad' => $cantidad]);

        return $consumible;
    }

    private function crearCompra(User $usuario, Consumible $consumible, string $documento): Compra
    {
        $proveedor = Proveedor::query()->create([
            'nombre' => 'Proveedor dashboard',
            'activo' => true,
        ]);
        $compra = Compra::query()->create([
            'proveedor_id' => $proveedor->id,
            'usuario_id' => $usuario->id,
            'numero_documento' => $documento,
            'fecha_orden' => '2026-11-18',
            'estado' => 'parcialmente_recibida',
            'moneda' => 'BOB',
            'incluye_iva' => false,
        ]);
        $compra->detalles()->create([
            'consumible_id' => $consumible->id,
            'cantidad' => 3,
            'cantidad_recibida' => 1,
            'precio_unitario' => 120,
        ]);

        return $compra;
    }
}
