<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Consumible;
use App\Modules\Monitoring\Models\Lectura;
use App\Modules\Printers\Models\Impresora;
use App\Modules\Purchasing\Models\Compra;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $impresoras = null;
        $inventario = null;
        $compras = null;
        $lecturas = null;

        if (Gate::allows('ver-impresoras')) {
            $impresoras = [
                'registradas' => Impresora::query()->count(),
                'operativas' => Impresora::query()
                    ->whereHas('estado', fn (Builder $consulta) => $consulta->where('es_operativo', true))
                    ->count(),
            ];
        }

        if (Gate::allows('ver-inventario')) {
            $consulta = $this->consumiblesBajoMinimo();

            $inventario = [
                'bajo_minimo' => (clone $consulta)->count('consumibles.id'),
                'consumibles' => (clone $consulta)
                    ->orderByRaw('consumibles.stock_minimo - dashboard_existencias.cantidad DESC')
                    ->limit(5)
                    ->get([
                        'consumibles.id',
                        'consumibles.codigo',
                        'consumibles.descripcion',
                        'consumibles.stock_minimo',
                        'dashboard_existencias.cantidad as cantidad_actual',
                    ]),
            ];
        }

        if (Gate::allows('gestionar-compras')) {
            $consulta = Compra::query()->whereIn('estado', ['ordenada', 'parcialmente_recibida']);

            $compras = [
                'pendientes' => (clone $consulta)->count(),
                'ordenes' => (clone $consulta)
                    ->with('proveedor')
                    ->orderBy('fecha_orden')
                    ->orderBy('id')
                    ->limit(5)
                    ->get(),
            ];
        }

        if (Gate::allows('ver-monitoreo')) {
            $lecturas = Lectura::query()
                ->with(['impresora', 'motivoSinLectura', 'niveles.tipoConsumible'])
                ->orderByDesc('fecha_lectura')
                ->orderByDesc('id')
                ->limit(5)
                ->get();
        }

        return view('dashboard.index', compact('impresoras', 'inventario', 'compras', 'lecturas'));
    }

    private function consumiblesBajoMinimo(): Builder
    {
        return Consumible::query()
            ->join('existencias as dashboard_existencias', 'dashboard_existencias.consumible_id', '=', 'consumibles.id')
            ->where('consumibles.activo', true)
            ->where('consumibles.stock_minimo', '>', 0)
            ->whereColumn('dashboard_existencias.cantidad', '<', 'consumibles.stock_minimo');
    }
}
