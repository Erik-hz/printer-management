<?php

namespace Tests\Feature;

use App\Modules\Inventory\Services\ConsumibleService;
use App\Modules\Inventory\Services\InventarioService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class InventorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_existences_reject_negative_quantities(): void
    {
        [$consumibleId] = $this->crearConsumibleYUsuario();

        $this->expectException(QueryException::class);

        DB::table('existencias')->insert([
            'consumible_id' => $consumibleId,
            'cantidad' => -1,
            'updated_at' => now(),
        ]);
    }

    public function test_movement_quantity_sign_must_match_its_type(): void
    {
        [$consumibleId, $usuarioId] = $this->crearConsumibleYUsuario();

        $this->expectException(QueryException::class);

        $this->insertarMovimiento($consumibleId, $usuarioId, [
            'tipo' => 'entrada',
            'cantidad' => -1,
        ]);
    }

    public function test_adjustment_requires_a_non_empty_reason(): void
    {
        [$consumibleId, $usuarioId] = $this->crearConsumibleYUsuario();

        $this->expectException(QueryException::class);

        $this->insertarMovimiento($consumibleId, $usuarioId, [
            'tipo' => 'ajuste',
            'cantidad' => 1,
            'saldo_resultante' => 1,
            'motivo' => '   ',
        ]);
    }

    public function test_purchase_detail_can_only_be_linked_to_an_incoming_movement(): void
    {
        [$consumibleId, $usuarioId, $detalleCompraId] = $this->crearDetalleCompra();

        $this->expectException(QueryException::class);

        $this->insertarMovimiento($consumibleId, $usuarioId, [
            'tipo' => 'salida',
            'cantidad' => -1,
            'detalle_compra_id' => $detalleCompraId,
        ]);
    }

    public function test_inventory_migration_can_be_rolled_back(): void
    {
        $migration = require database_path('migrations/2026_11_13_220000_crear_tablas_inventario.php');

        $migration->down();

        $this->assertFalse(Schema::hasTable('movimientos_inventario'));
        $this->assertFalse(Schema::hasTable('existencias'));
    }

    public function test_consumible_service_creates_zero_balance(): void
    {
        $tipoId = DB::table('tipos_consumible')->insertGetId([
            'nombre' => 'toner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $consumible = (new ConsumibleService)->crear([
            'tipo_consumible_id' => $tipoId,
            'codigo' => 'TONER-NUEVO',
            'color' => 'negro',
            'stock_minimo' => 0,
            'activo' => true,
        ]);

        $this->assertDatabaseHas('existencias', [
            'consumible_id' => $consumible->getKey(),
            'cantidad' => 0,
        ]);
    }

    public function test_partial_receipts_update_stock_detail_and_purchase_status(): void
    {
        [$consumibleId, $usuarioId, $detalleCompraId] = $this->crearDetalleCompra();
        $this->crearExistencia($consumibleId, 0);
        $service = new InventarioService;

        $service->registrarRecepcion($detalleCompraId, 3, $usuarioId);
        $service->registrarRecepcion($detalleCompraId, 2, $usuarioId);

        $compraId = DB::table('detalle_compra')
            ->where('id', $detalleCompraId)
            ->value('compra_id');

        $this->assertDatabaseHas('existencias', [
            'consumible_id' => $consumibleId,
            'cantidad' => 5,
        ]);
        $this->assertDatabaseHas('detalle_compra', [
            'id' => $detalleCompraId,
            'cantidad_recibida' => 5,
        ]);
        $this->assertDatabaseHas('compras', [
            'id' => $compraId,
            'estado' => 'recibida',
        ]);
        $this->assertDatabaseCount('movimientos_inventario', 2);
    }

    public function test_receipt_cannot_exceed_the_remaining_purchase_quantity(): void
    {
        [$consumibleId, $usuarioId, $detalleCompraId] = $this->crearDetalleCompra();
        $this->crearExistencia($consumibleId, 0);

        try {
            (new InventarioService)->registrarRecepcion($detalleCompraId, 6, $usuarioId);
            $this->fail('Se aceptó una recepción mayor a la cantidad ordenada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cantidad', $exception->errors());
        }

        $this->assertDatabaseHas('existencias', [
            'consumible_id' => $consumibleId,
            'cantidad' => 0,
        ]);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_output_cannot_make_stock_negative(): void
    {
        [$consumibleId, $usuarioId] = $this->crearConsumibleYUsuario();
        $this->crearExistencia($consumibleId, 2);

        try {
            (new InventarioService)->registrarSalida($consumibleId, 3, $usuarioId);
            $this->fail('Se aceptó una salida superior a las existencias.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cantidad', $exception->errors());
        }

        $this->assertDatabaseHas('existencias', [
            'consumible_id' => $consumibleId,
            'cantidad' => 2,
        ]);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_inventory_movements_cannot_be_updated_or_deleted_through_eloquent(): void
    {
        [$consumibleId, $usuarioId] = $this->crearConsumibleYUsuario();
        $this->crearExistencia($consumibleId, 0);
        $movimiento = (new InventarioService)->registrarAjuste(
            $consumibleId,
            1,
            'Conteo inicial',
            $usuarioId
        );

        try {
            $movimiento->update(['ticket' => 'R-123456']);
            $this->fail('Se permitió editar un movimiento de inventario.');
        } catch (LogicException) {
            $this->assertDatabaseHas('movimientos_inventario', [
                'id' => $movimiento->getKey(),
                'ticket' => null,
            ]);
        }

        try {
            $movimiento->delete();
            $this->fail('Se permitió borrar un movimiento de inventario.');
        } catch (LogicException) {
            $this->assertDatabaseHas('movimientos_inventario', [
                'id' => $movimiento->getKey(),
            ]);
        }
    }

    public function test_inventory_verification_detects_a_mismatched_balance(): void
    {
        [$consumibleId, $usuarioId] = $this->crearConsumibleYUsuario();
        $this->crearExistencia($consumibleId, 5);
        $this->insertarMovimiento($consumibleId, $usuarioId, [
            'tipo' => 'entrada',
            'cantidad' => 3,
            'saldo_resultante' => 3,
        ]);

        $this->assertSame(1, Artisan::call('inventario:verificar'));
        $this->assertStringContainsString('existencia 5, suma de movimientos 3', Artisan::output());
    }

    public function test_inventory_verification_accepts_matching_balances(): void
    {
        [$consumibleId, $usuarioId] = $this->crearConsumibleYUsuario();
        $this->crearExistencia($consumibleId, 3);
        $this->insertarMovimiento($consumibleId, $usuarioId, [
            'tipo' => 'entrada',
            'cantidad' => 3,
            'saldo_resultante' => 3,
        ]);

        $this->assertSame(0, Artisan::call('inventario:verificar'));
        $this->assertStringContainsString('coinciden', Artisan::output());
    }

    private function crearConsumibleYUsuario(): array
    {
        $now = now();
        $rolId = DB::table('roles')->insertGetId([
            'nombre' => 'tecnico',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $usuarioId = DB::table('users')->insertGetId([
            'name' => 'Usuario de inventario',
            'email' => 'inventario@example.test',
            'password' => Hash::make('password'),
            'rol_id' => $rolId,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $tipoId = DB::table('tipos_consumible')->insertGetId([
            'nombre' => 'toner',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $consumibleId = DB::table('consumibles')->insertGetId([
            'tipo_consumible_id' => $tipoId,
            'codigo' => 'TONER-INVENTORY-TEST',
            'color' => 'negro',
            'stock_minimo' => 0,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$consumibleId, $usuarioId];
    }

    private function crearDetalleCompra(): array
    {
        [$consumibleId, $usuarioId] = $this->crearConsumibleYUsuario();
        $now = now();
        $proveedorId = DB::table('proveedores')->insertGetId([
            'nombre' => 'Proveedor de inventario',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $compraId = DB::table('compras')->insertGetId([
            'proveedor_id' => $proveedorId,
            'usuario_id' => $usuarioId,
            'fecha_orden' => '2026-11-13',
            'estado' => 'ordenada',
            'moneda' => 'BOB',
            'incluye_iva' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $detalleCompraId = DB::table('detalle_compra')->insertGetId([
            'compra_id' => $compraId,
            'consumible_id' => $consumibleId,
            'cantidad' => 5,
            'cantidad_recibida' => 0,
            'precio_unitario' => 100,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$consumibleId, $usuarioId, $detalleCompraId];
    }

    private function insertarMovimiento(int $consumibleId, int $usuarioId, array $overrides = []): int
    {
        $now = now();

        return DB::table('movimientos_inventario')->insertGetId(array_merge([
            'consumible_id' => $consumibleId,
            'tipo' => 'entrada',
            'cantidad' => 1,
            'saldo_resultante' => 1,
            'usuario_id' => $usuarioId,
            'fecha_movimiento' => $now,
            'created_at' => $now,
        ], $overrides));
    }

    private function crearExistencia(int $consumibleId, int $cantidad): void
    {
        DB::table('existencias')->insert([
            'consumible_id' => $consumibleId,
            'cantidad' => $cantidad,
            'updated_at' => now(),
        ]);
    }
}
