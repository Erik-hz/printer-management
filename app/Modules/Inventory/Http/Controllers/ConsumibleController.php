<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\ConsumibleRequest;
use App\Modules\Inventory\Models\Consumible;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Inventory\Services\ConsumibleService;
use App\Modules\Printers\Models\ModeloImpresora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsumibleController extends Controller
{
    public function __construct(private readonly ConsumibleService $consumibles)
    {
    }

    public function index(Request $request): View
    {
        $busqueda = $request->string('q')->trim()->toString();
        $color = $request->input('color');
        $estado = $request->input('estado');

        $consumibles = Consumible::query()
            ->with(['tipo', 'existencia'])
            ->withCount('modelos')
            ->when($busqueda !== '', fn ($consulta) => $consulta->where(
                fn ($donde) => $donde
                    ->where('codigo', 'like', "%{$busqueda}%")
                    ->orWhere('descripcion', 'like', "%{$busqueda}%")
            ))
            ->when($request->filled('tipo'), fn ($consulta) => $consulta->where('tipo_consumible_id', $request->integer('tipo')))
            ->when(
                in_array($color, array_keys(Consumible::COLORES), true),
                fn ($consulta) => $consulta->where('color', $color)
            )
            ->when($request->boolean('bajo'), fn ($consulta) => $consulta->whereHas(
                'existencia',
                fn ($existencia) => $existencia->whereColumn('existencias.cantidad', '<', 'consumibles.stock_minimo')
            ))
            ->when($estado === 'activos', fn ($consulta) => $consulta->where('activo', true))
            ->when($estado === 'inactivos', fn ($consulta) => $consulta->where('activo', false))
            ->orderBy('codigo')
            ->paginate(15)
            ->withQueryString();

        return view('consumibles.index', [
            'consumibles' => $consumibles,
            'tipos' => TipoConsumible::orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('consumibles.form', ['consumible' => new Consumible(), 'seleccionados' => []] + $this->listas());
    }

    public function store(ConsumibleRequest $request): RedirectResponse
    {
        $this->consumibles->crear($request->validated());

        return redirect()->route('consumibles.index')->with('estado', 'Consumible creado.');
    }

    public function edit(Consumible $consumible): View
    {
        $consumible->load('existencia');

        $seleccionados = array_map('intval', $consumible->modelos()->pluck('modelos_impresora.id')->all());

        return view('consumibles.form', ['consumible' => $consumible, 'seleccionados' => $seleccionados] + $this->listas());
    }

    public function update(ConsumibleRequest $request, Consumible $consumible): RedirectResponse
    {
        $this->consumibles->actualizar($consumible, $request->validated());

        return redirect()->route('consumibles.index')->with('estado', 'Consumible actualizado.');
    }

    public function destroy(Consumible $consumible): RedirectResponse
    {
        $this->consumibles->eliminar($consumible);

        return redirect()->route('consumibles.index')->with('estado', 'Consumible eliminado.');
    }

    private function listas(): array
    {
        return [
            'tipos' => TipoConsumible::orderBy('id')->get(),
            'modelos' => ModeloImpresora::with('marca')->get()
                ->sortBy(fn (ModeloImpresora $modelo) => [$modelo->marca->nombre, $modelo->nombre])
                ->values(),
        ];
    }
}