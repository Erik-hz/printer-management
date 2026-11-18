<?php

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Consumible;
use App\Modules\Inventory\Services\InventarioService;
use App\Modules\Purchasing\Http\Requests\CompraRequest;
use App\Modules\Purchasing\Http\Requests\RecepcionRequest;
use App\Modules\Purchasing\Models\Compra;
use App\Modules\Purchasing\Models\DetalleCompra;
use App\Modules\Purchasing\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CompraController extends Controller
{
    public function __construct(private readonly InventarioService $inventario) {}

    public function index(): View
    {
        return view('compras.index', [
            'compras' => Compra::query()
                ->with(['proveedor', 'usuario'])
                ->withCount('detalles')
                ->orderByDesc('fecha_orden')
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('compras.form', [
            'proveedores' => Proveedor::query()->where('activo', true)->orderBy('nombre')->get(),
            'consumibles' => Consumible::query()->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'descripcion']),
        ]);
    }

    public function store(CompraRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $detalles = $datos['detalles'];
        unset($datos['detalles']);
        $datos['moneda'] = strtoupper($datos['moneda']);

        $compra = DB::transaction(function () use ($datos, $detalles, $request): Compra {
            $compra = Compra::query()->create([
                ...$datos,
                'usuario_id' => $request->user()->id,
                'estado' => 'ordenada',
            ]);

            foreach ($detalles as $detalle) {
                $compra->detalles()->create([
                    ...$detalle,
                    'cantidad_recibida' => 0,
                ]);
            }

            return $compra;
        });

        return redirect()->route('compras.show', $compra)->with('estado', 'Orden de compra creada.');
    }

    public function show(Compra $compra): View
    {
        $compra->load(['proveedor', 'usuario', 'detalles.consumible.existencia']);

        return view('compras.show', ['compra' => $compra]);
    }

    public function recibir(
        RecepcionRequest $request,
        Compra $compra,
        DetalleCompra $detalle,
    ): RedirectResponse {
        abort_unless((int) $detalle->compra_id === (int) $compra->id, 404);

        $movimiento = $this->inventario->registrarRecepcion(
            (int) $detalle->id,
            (int) $request->validated('cantidad'),
            (int) $request->user()->id,
        );

        return redirect()->route('compras.show', $compra)
            ->with('estado', "Recepción registrada. Existencias actuales: {$movimiento->saldo_resultante}.");
    }

    public function cancelar(Compra $compra): RedirectResponse
    {
        DB::transaction(function () use ($compra): void {
            $bloqueada = Compra::query()->lockForUpdate()->findOrFail($compra->id);

            if ($bloqueada->estado !== 'ordenada' || $bloqueada->detalles()->where('cantidad_recibida', '>', 0)->exists()) {
                throw ValidationException::withMessages([
                    'compra' => 'Solo se pueden cancelar órdenes que aún no tengan recepciones.',
                ]);
            }

            $bloqueada->update(['estado' => 'cancelada']);
        });

        return redirect()->route('compras.show', $compra)->with('estado', 'Orden de compra cancelada.');
    }
}
