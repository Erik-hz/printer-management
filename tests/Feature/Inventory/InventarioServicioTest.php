<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Modules\Inventory\Models\Consumible;
use App\Modules\Inventory\Models\Existencia;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Inventory\Services\ConsumibleService;
use App\Modules\Inventory\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventarioServicioTest extends TestCase
{
    use RefreshDatabase;

    private function consumible(): Consumible
    {
        return (new ConsumibleService)->crear([
            'tipo_consumible_id' => TipoConsumible::firstOrCreate(['nombre' => 'Toner'])->id,
            'codigo' => 'TN-'.uniqid(),
            'color' => 'negro',
            'stock_minimo' => 0,
            'activo' => true,
        ]);
    }

    private function usuario(): User
    {
        return User::factory()->conRol('responsable_inventario')->create();
    }

    private function existencia(Consumible $consumible): int
    {
        return (int) Existencia::findOrFail($consumible->id)->cantidad;
    }

    public function test_la_entrada_manual_exige_motivo(): void
    {
        $consumible = $this->consumible();

        try {
            (new InventarioService)->registrarEntradaManual($consumible->id, 3, '   ', $this->usuario()->id);
            $this->fail('Se aceptó una entrada manual sin motivo.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('motivo', $exception->errors());
        }

        $this->assertSame(0, $this->existencia($consumible));
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_la_entrada_manual_actualiza_la_existencia_y_registra_el_movimiento(): void
    {
        $consumible = $this->consumible();

        $movimiento = (new InventarioService)->registrarEntradaManual(
            $consumible->id,
            4,
            'Donación',
            $this->usuario()->id
        );

        $this->assertSame('entrada', $movimiento->tipo);
        $this->assertSame(4, $movimiento->cantidad);
        $this->assertSame(4, $movimiento->saldo_resultante);
        $this->assertSame('Donación', $movimiento->motivo);
        $this->assertNull($movimiento->detalle_compra_id);
        $this->assertSame(4, $this->existencia($consumible));
    }

    public function test_el_servicio_acepta_una_salida_sin_impresora_para_cargas_historicas(): void
    {
        $consumible = $this->consumible();
        $usuario = $this->usuario();
        $servicio = new InventarioService;

        $servicio->registrarEntradaManual($consumible->id, 3, 'Stock inicial', $usuario->id);
        $movimiento = $servicio->registrarSalida($consumible->id, 1, $usuario->id);

        $this->assertSame(-1, $movimiento->cantidad);
        $this->assertNull($movimiento->impresora_id);
        $this->assertSame(2, $this->existencia($consumible));
    }

    public function test_un_ticket_en_blanco_se_guarda_como_nulo_y_uno_con_espacios_se_recorta(): void
    {
        $consumible = $this->consumible();
        $usuario = $this->usuario();
        $servicio = new InventarioService;

        $enBlanco = $servicio->registrarEntradaManual($consumible->id, 1, 'Motivo', $usuario->id, '   ');
        $conEspacios = $servicio->registrarEntradaManual($consumible->id, 1, 'Motivo', $usuario->id, ' R-1 ');

        $this->assertNull($enBlanco->ticket);
        $this->assertSame('R-1', $conEspacios->ticket);
    }

    public function test_no_se_puede_ajustar_un_consumible_eliminado(): void
    {
        $consumible = $this->consumible();
        $consumible->delete();

        try {
            (new InventarioService)->registrarAjuste($consumible->id, 1, 'Motivo', $this->usuario()->id);
            $this->fail('Se aceptó un ajuste sobre un consumible eliminado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('consumible_id', $exception->errors());
        }

        $this->assertDatabaseCount('movimientos_inventario', 0);
    }
}