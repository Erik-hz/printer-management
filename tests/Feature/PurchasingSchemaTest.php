<?php

namespace Tests\Feature;

use App\Modules\Purchasing\Services\PrecioConsumibleService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchasingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_rejects_an_unknown_status(): void
    {
        [$proveedorId, $usuarioId] = $this->crearProveedorYUsuario();

        $this->expectException(QueryException::class);

        $this->insertarCompra($proveedorId, $usuarioId, ['estado' => 'pendiente']);
    }

    public function test_purchase_detail_rejects_received_quantity_above_ordered_quantity(): void
    {
        [$compraId, $consumibleId] = $this->crearCompraYConsumible();

        $this->expectException(QueryException::class);

        $this->insertarDetalle($compraId, $consumibleId, [
            'cantidad' => 3,
            'cantidad_recibida' => 4,
        ]);
    }

    public function test_purchase_detail_rejects_a_negative_unit_price(): void
    {
        [$compraId, $consumibleId] = $this->crearCompraYConsumible();

        $this->expectException(QueryException::class);

        $this->insertarDetalle($compraId, $consumibleId, ['precio_unitario' => -1]);
    }

    public function test_price_service_rejects_overlapping_vigencies(): void
    {
        [$compraId, $consumibleId] = $this->crearCompraYConsumible();
        $proveedorId = DB::table('compras')->where('id', $compraId)->value('proveedor_id');
        $servicio = new PrecioConsumibleService;

        $servicio->registrar([
            'consumible_id' => $consumibleId,
            'proveedor_id' => $proveedorId,
            'precio' => 10,
            'moneda' => 'BOB',
            'incluye_iva' => false,
            'vigente_desde' => '2026-11-01',
            'vigente_hasta' => '2026-11-30',
        ]);

        try {
            $servicio->registrar([
                'consumible_id' => $consumibleId,
                'proveedor_id' => $proveedorId,
                'precio' => 12,
                'moneda' => 'BOB',
                'incluye_iva' => false,
                'vigente_desde' => '2026-11-30',
                'vigente_hasta' => null,
            ]);
            $this->fail('Se aceptó una vigencia de precio solapada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('vigente_desde', $exception->errors());
        }
    }

    public function test_price_service_accepts_consecutive_vigencies(): void
    {
        [$compraId, $consumibleId] = $this->crearCompraYConsumible();
        $proveedorId = DB::table('compras')->where('id', $compraId)->value('proveedor_id');
        $servicio = new PrecioConsumibleService;

        $primero = $servicio->registrar([
            'consumible_id' => $consumibleId,
            'proveedor_id' => $proveedorId,
            'precio' => 10,
            'moneda' => 'BOB',
            'incluye_iva' => false,
            'vigente_desde' => '2026-11-01',
            'vigente_hasta' => '2026-11-29',
        ]);
        $segundo = $servicio->registrar([
            'consumible_id' => $consumibleId,
            'proveedor_id' => $proveedorId,
            'precio' => 12,
            'moneda' => 'BOB',
            'incluye_iva' => false,
            'vigente_desde' => '2026-11-30',
            'vigente_hasta' => null,
        ]);

        $this->assertNotNull($primero->id);
        $this->assertNotNull($segundo->id);
    }

    private function crearProveedorYUsuario(): array
    {
        $now = now();
        $rolId = DB::table('roles')->insertGetId([
            'nombre' => 'tecnico',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $usuarioId = DB::table('users')->insertGetId([
            'name' => 'Usuario de prueba',
            'email' => 'compras@example.test',
            'password' => Hash::make('password'),
            'rol_id' => $rolId,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $proveedorId = DB::table('proveedores')->insertGetId([
            'nombre' => 'Proveedor de prueba',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$proveedorId, $usuarioId];
    }

    private function crearCompraYConsumible(): array
    {
        [$proveedorId, $usuarioId] = $this->crearProveedorYUsuario();
        $compraId = $this->insertarCompra($proveedorId, $usuarioId);
        $now = now();
        $tipoId = DB::table('tipos_consumible')->insertGetId([
            'nombre' => 'toner',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $consumibleId = DB::table('consumibles')->insertGetId([
            'tipo_consumible_id' => $tipoId,
            'codigo' => 'TONER-TEST',
            'color' => 'negro',
            'stock_minimo' => 0,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$compraId, $consumibleId];
    }

    private function insertarCompra(int $proveedorId, int $usuarioId, array $overrides = []): int
    {
        $now = now();

        return DB::table('compras')->insertGetId(array_merge([
            'proveedor_id' => $proveedorId,
            'usuario_id' => $usuarioId,
            'fecha_orden' => '2026-11-13',
            'estado' => 'ordenada',
            'moneda' => 'BOB',
            'incluye_iva' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    private function insertarDetalle(int $compraId, int $consumibleId, array $overrides = []): void
    {
        $now = now();

        DB::table('detalle_compra')->insert(array_merge([
            'compra_id' => $compraId,
            'consumible_id' => $consumibleId,
            'cantidad' => 3,
            'cantidad_recibida' => 0,
            'precio_unitario' => 100,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }
}
