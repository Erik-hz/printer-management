<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Inventory\Models\Consumible;
use App\Modules\Inventory\Models\Existencia;
use App\Modules\Inventory\Models\MovimientoInventario;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Monitoring\Models\Lectura;
use App\Modules\Monitoring\Models\MotivoSinLectura;
use App\Modules\Printers\Models\Area;
use App\Modules\Printers\Models\EstadoImpresora;
use App\Modules\Printers\Models\Impresora;
use App\Modules\Printers\Models\Lugar;
use App\Modules\Printers\Models\Marca;
use App\Modules\Printers\Models\ModeloImpresora;
use App\Modules\Printers\Models\Zona;
use App\Modules\Purchasing\Models\Compra;
use App\Modules\Purchasing\Models\DetalleCompra;
use App\Modules\Purchasing\Models\PrecioConsumible;
use App\Modules\Purchasing\Models\Proveedor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatosPruebaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        if (Consumible::where('codigo', 'like', 'DEMO-%')->exists()) {
            return;
        }

        DB::transaction(function () {
            $admin = User::where('email', 'admin@sgiic.test')->firstOrFail();

            // ---- Ubicación ----
            $zonaNorte = Zona::firstOrCreate(['nombre' => 'Zona Demo Norte']);
            $zonaSur = Zona::firstOrCreate(['nombre' => 'Zona Demo Sur']);
            $oficina = Lugar::firstOrCreate(['zona_id' => $zonaNorte->id, 'nombre' => 'Oficina Demo']);
            $campamento = Lugar::firstOrCreate(['zona_id' => $zonaSur->id, 'nombre' => 'Campamento Demo']);
            $admArea = Area::firstOrCreate(['lugar_id' => $oficina->id, 'nombre' => 'Administración']);
            $ingArea = Area::firstOrCreate(['lugar_id' => $oficina->id, 'nombre' => 'Ingeniería']);
            $tallerArea = Area::firstOrCreate(['lugar_id' => $campamento->id, 'nombre' => 'Taller']);

            // ---- Modelos ----
            $hp = Marca::where('nombre', 'HP')->firstOrFail();
            $epson = Marca::where('nombre', 'Epson')->firstOrFail();
            $brother = Marca::where('nombre', 'Brother')->firstOrFail();

            $modelo = fn (Marca $marca, string $nombre, string $tipo, string $tec, ?bool $snmp) =>
                ModeloImpresora::firstOrCreate(
                    ['marca_id' => $marca->id, 'nombre' => $nombre],
                    ['tipo_impresion' => $tipo, 'tecnologia' => $tec, 'snmp_soportado' => $snmp]
                );

            $hpMono = $modelo($hp, 'LaserJet Demo M100', 'mono', 'laser', true);
            $hpColor = $modelo($hp, 'LaserJet Demo C200', 'color', 'laser', true);
            $hpPlotter = $modelo($hp, 'DesignJet Demo P300', 'color', 'plotter', null);
            $epsonTinta = $modelo($epson, 'EcoTank Demo L1', 'color', 'tinta', false);
            $brotherTinta = $modelo($brother, 'InkBenefit Demo B1', 'color', 'tinta', null);

            // ---- Impresoras (6 de red y 2 USB) ----
            $activa = EstadoImpresora::where('nombre', 'activa')->firstOrFail();
            $retirada = EstadoImpresora::where('nombre', 'retirada')->firstOrFail();

            $crear = fn (string $nombre, ModeloImpresora $m, Area $a, EstadoImpresora $e, array $conexion) =>
                Impresora::firstOrCreate(
                    ['nombre' => $nombre],
                    array_merge(['modelo_id' => $m->id, 'area_id' => $a->id, 'estado_id' => $e->id], $conexion)
                );

            $p1 = $crear('PRN-DEMO-01', $hpMono, $admArea, $activa, ['tipo_conexion' => 'red', 'direccion_ip' => '192.0.2.11']);
            $crear('PRN-DEMO-02', $hpMono, $admArea, $activa, ['tipo_conexion' => 'red', 'direccion_ip' => '192.0.2.12']);
            $p3 = $crear('PRN-DEMO-03', $hpColor, $ingArea, $activa, ['tipo_conexion' => 'red', 'direccion_ip' => '192.0.2.13']);
            $p4 = $crear('PRN-DEMO-04', $hpPlotter, $ingArea, $activa, ['tipo_conexion' => 'red', 'direccion_ip' => '192.0.2.14']);
            $crear('PRN-DEMO-05', $epsonTinta, $tallerArea, $activa, ['tipo_conexion' => 'red', 'direccion_ip' => '192.0.2.15']);
            $p6 = $crear('PRN-DEMO-06', $hpMono, $tallerArea, $retirada, ['tipo_conexion' => 'red', 'direccion_ip' => '192.0.2.16']);
            $crear('PRN-DEMO-07', $brotherTinta, $tallerArea, $activa, ['tipo_conexion' => 'usb', 'equipo_conectado' => 'PC-DEMO-01']);
            $crear('PRN-DEMO-08', $epsonTinta, $admArea, $activa, ['tipo_conexion' => 'usb', 'equipo_conectado' => 'PC-DEMO-02']);

            // ---- Consumibles y compatibilidad ----
            $toner = TipoConsumible::where('nombre', 'Tóner')->firstOrFail();
            $tinta = TipoConsumible::where('nombre', 'Tinta')->firstOrFail();
            $plotter = TipoConsumible::where('nombre', 'Cartucho de plotter')->firstOrFail();
            $kit = TipoConsumible::where('nombre', 'Kit de mantenimiento')->firstOrFail();

            $definiciones = [
                // codigo, tipo, color, rendimiento, stock mínimo, modelos compatibles
                ['DEMO-TN-N', $toner, 'negro', 6500, 3, [$hpMono]],
                ['DEMO-TC-N', $toner, 'negro', 3000, 2, [$hpColor]],
                ['DEMO-TC-C', $toner, 'cian', 2500, 2, [$hpColor]],
                ['DEMO-TC-M', $toner, 'magenta', 2500, 2, [$hpColor]],
                ['DEMO-TC-Y', $toner, 'amarillo', 2500, 2, [$hpColor]],
                ['DEMO-KIT-1', $kit, 'no_aplica', 100000, 2, [$hpMono, $hpColor]],
                ['DEMO-TINTA-N', $tinta, 'negro', 4500, 2, [$epsonTinta, $brotherTinta]],
                ['DEMO-PL-N', $plotter, 'negro', null, 1, [$hpPlotter]],
            ];

            $consumibles = [];
            foreach ($definiciones as [$codigo, $tipo, $color, $rend, $minimo, $modelos]) {
                $c = Consumible::create([
                    'tipo_consumible_id' => $tipo->id,
                    'codigo' => $codigo,
                    'descripcion' => "Consumible de prueba {$codigo}",
                    'color' => $color,
                    'rendimiento_paginas' => $rend,
                    'stock_minimo' => $minimo,
                ]);
                $c->modelos()->attach(collect($modelos)->pluck('id')->all());
                Existencia::create(['consumible_id' => $c->id, 'cantidad' => 0]);
                $consumibles[$codigo] = $c;
            }

            // ---- Compras y precios ----
            $proveedor = Proveedor::firstOrCreate(['nombre' => 'Proveedor Demo S.R.L.']);

            $compra = Compra::create([
                'proveedor_id' => $proveedor->id,
                'usuario_id' => $admin->id,
                'numero_documento' => 'DEMO-OC-001',
                'fecha_orden' => now()->subDays(30)->toDateString(),
                'estado' => 'recibida',
                'incluye_iva' => true,
                'observaciones' => 'Compra de prueba',
            ]);
            $detTn = DetalleCompra::create([
                'compra_id' => $compra->id,
                'consumible_id' => $consumibles['DEMO-TN-N']->id,
                'cantidad' => 10,
                'cantidad_recibida' => 10,
                'precio_unitario' => 150.00,
            ]);
            $detTinta = DetalleCompra::create([
                'compra_id' => $compra->id,
                'consumible_id' => $consumibles['DEMO-TINTA-N']->id,
                'cantidad' => 6,
                'cantidad_recibida' => 6,
                'precio_unitario' => 85.00,
            ]);

            PrecioConsumible::create([
                'consumible_id' => $consumibles['DEMO-TN-N']->id,
                'proveedor_id' => $proveedor->id,
                'precio' => 150.00,
                'incluye_iva' => true,
                'vigente_desde' => '2026-01-01',
                'vigente_hasta' => '2026-06-30',
            ]);
            PrecioConsumible::create([
                'consumible_id' => $consumibles['DEMO-TN-N']->id,
                'proveedor_id' => $proveedor->id,
                'precio' => 165.00,
                'incluye_iva' => true,
                'vigente_desde' => '2026-07-01',
            ]);

            // ---- Movimientos (la existencia siempre es la suma de los movimientos) ----
            $mover = function (Consumible $c, string $tipo, int $cantidad, array $extra = []) use ($admin) {
                $existencia = Existencia::findOrFail($c->id);
                $saldo = $existencia->cantidad + $cantidad;

                MovimientoInventario::create(array_merge([
                    'consumible_id' => $c->id,
                    'tipo' => $tipo,
                    'cantidad' => $cantidad,
                    'saldo_resultante' => $saldo,
                    'usuario_id' => $admin->id,
                    'fecha_movimiento' => now(),
                ], $extra));

                $existencia->update(['cantidad' => $saldo]);
            };

            $mover($consumibles['DEMO-TN-N'], 'entrada', 10, ['detalle_compra_id' => $detTn->id]);
            $mover($consumibles['DEMO-TN-N'], 'salida', -2, ['impresora_id' => $p1->id, 'ticket' => 'R-DEMO-001']);
            $mover($consumibles['DEMO-TINTA-N'], 'entrada', 6, ['detalle_compra_id' => $detTinta->id]);
            $mover($consumibles['DEMO-TINTA-N'], 'ajuste', -1, ['motivo' => 'Conteo físico (dato de prueba)']);

            foreach (['DEMO-TC-N' => 4, 'DEMO-TC-C' => 4, 'DEMO-TC-M' => 4, 'DEMO-TC-Y' => 4, 'DEMO-PL-N' => 2] as $codigo => $cantidad) {
                $mover($consumibles[$codigo], 'entrada', $cantidad, ['motivo' => 'Stock inicial de prueba']);
            }
            // Existencia por debajo del mínimo (2): sirve para probar alertas en la Fase 6.
            $mover($consumibles['DEMO-KIT-1'], 'entrada', 1, ['motivo' => 'Stock inicial de prueba']);

            // ---- Lecturas ----
            $lectura1 = Lectura::create([
                'impresora_id' => $p1->id,
                'fecha_lectura' => now()->subDays(10),
                'origen' => 'manual',
                'contador_paginas' => 12000,
                'usuario_id' => $admin->id,
            ]);
            $lectura1->niveles()->create(['tipo_consumible_id' => $toner->id, 'color' => 'negro', 'nivel' => 0.84]);

            $lectura2 = Lectura::create([
                'impresora_id' => $p3->id,
                'fecha_lectura' => now()->subDays(2),
                'origen' => 'snmp',
                'contador_paginas' => 5400,
            ]);
            foreach (['negro' => 0.65, 'cian' => 0.40, 'magenta' => 0.27, 'amarillo' => 0.14] as $color => $nivel) {
                $lectura2->niveles()->create(['tipo_consumible_id' => $toner->id, 'color' => $color, 'nivel' => $nivel]);
            }

            // Lectura sin contador (caso de la regla 12): solo nivel.
            $lectura3 = Lectura::create([
                'impresora_id' => $p4->id,
                'fecha_lectura' => now()->subDay(),
                'origen' => 'snmp',
            ]);
            $lectura3->niveles()->create(['tipo_consumible_id' => $plotter->id, 'color' => 'negro', 'nivel' => 0.50]);

            // Lectura sin datos, con motivo (el primer motivo sembrado).
            Lectura::create([
                'impresora_id' => $p6->id,
                'fecha_lectura' => now()->subDays(5),
                'origen' => 'manual',
                'motivo_sin_lectura_id' => MotivoSinLectura::where('nombre', 'retirado')->firstOrFail()->id,
                'usuario_id' => $admin->id,
            ]);
        });
    }
}