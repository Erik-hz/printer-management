<?php

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Consumible;
use App\Modules\Purchasing\Http\Requests\CerrarPrecioRequest;
use App\Modules\Purchasing\Http\Requests\PrecioConsumibleRequest;
use App\Modules\Purchasing\Models\PrecioConsumible;
use App\Modules\Purchasing\Models\Proveedor;
use App\Modules\Purchasing\Services\PrecioConsumibleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PrecioConsumibleController extends Controller
{
    public function __construct(private readonly PrecioConsumibleService $precios) {}

    public function index(): View
    {
        return view('precios-consumibles.index', [
            'precios' => PrecioConsumible::query()
                ->with(['consumible', 'proveedor'])
                ->orderByDesc('vigente_desde')
                ->orderByDesc('id')
                ->paginate(20),
            'consumibles' => Consumible::query()->where('activo', true)->orderBy('codigo')->get(['id', 'codigo']),
            'proveedores' => Proveedor::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(PrecioConsumibleRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $datos['moneda'] = strtoupper($datos['moneda']);
        $datos['incluye_iva'] = (bool) $datos['incluye_iva'];
        $this->precios->registrar($datos);

        return redirect()->route('precios-consumibles.index')->with('estado', 'Precio vigente registrado.');
    }

    public function cerrar(CerrarPrecioRequest $request, PrecioConsumible $precio): RedirectResponse
    {
        $this->precios->cerrarVigencia($precio, $request->validated('vigente_hasta'));

        return redirect()->route('precios-consumibles.index')->with('estado', 'Vigencia del precio cerrada.');
    }
}
