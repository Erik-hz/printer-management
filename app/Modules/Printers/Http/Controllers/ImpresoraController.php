<?php

namespace App\Modules\Printers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Printers\Http\Requests\ImpresoraRequest;
use App\Modules\Printers\Models\Area;
use App\Modules\Printers\Models\EstadoImpresora;
use App\Modules\Printers\Models\Impresora;
use App\Modules\Printers\Models\Marca;
use App\Modules\Printers\Models\ModeloImpresora;
use App\Modules\Printers\Models\Zona;
use App\Modules\Printers\Services\ImpresoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImpresoraController extends Controller
{
    public function __construct(private readonly ImpresoraService $impresoras)
    {
    }

    public function index(Request $request): View
    {
        $busqueda = $request->string('q')->trim()->toString();

        $impresoras = Impresora::query()
            ->with(['modelo.marca', 'area.lugar.zona', 'estado'])
            ->when($busqueda !== '', fn ($consulta) => $consulta->where(
                fn ($donde) => $donde
                    ->where('nombre', 'like', "%{$busqueda}%")
                    ->orWhere('direccion_ip', 'like', "%{$busqueda}%")
                    ->orWhere('numero_serie', 'like', "%{$busqueda}%")
            ))
            ->when($request->filled('zona'), fn ($consulta) => $consulta->whereHas(
                'area.lugar',
                fn ($lugar) => $lugar->where('zona_id', $request->integer('zona'))
            ))
            ->when($request->filled('marca'), fn ($consulta) => $consulta->whereHas(
                'modelo',
                fn ($modelo) => $modelo->where('marca_id', $request->integer('marca'))
            ))
            ->when($request->filled('estado'), fn ($consulta) => $consulta->where('estado_id', $request->integer('estado')))
            ->when(
                in_array($request->input('conexion'), ['red', 'usb'], true),
                fn ($consulta) => $consulta->where('tipo_conexion', $request->input('conexion'))
            )
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('impresoras.index', [
            'impresoras' => $impresoras,
            'zonas' => Zona::orderBy('nombre')->get(),
            'marcas' => Marca::orderBy('nombre')->get(),
            'estados' => EstadoImpresora::orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        $impresora = new Impresora([
            'tipo_conexion' => 'red',
            'estado_id' => EstadoImpresora::where('nombre', 'activa')->value('id'),
        ]);

        return view('impresoras.form', ['impresora' => $impresora] + $this->listas());
    }

    public function store(ImpresoraRequest $request): RedirectResponse
    {
        $this->impresoras->crear($request->validated());

        return redirect()->route('impresoras.index')->with('estado', 'Impresora creada.');
    }

    public function edit(Impresora $impresora): View
    {
        return view('impresoras.form', ['impresora' => $impresora] + $this->listas($impresora));
    }

    public function update(ImpresoraRequest $request, Impresora $impresora): RedirectResponse
    {
        $this->impresoras->actualizar($impresora, $request->validated());

        return redirect()->route('impresoras.index')->with('estado', 'Impresora actualizada.');
    }

    public function retirar(Impresora $impresora): RedirectResponse
    {
        $this->impresoras->retirar($impresora);

        return redirect()->route('impresoras.index')->with('estado', 'Impresora retirada.');
    }

    public function destroy(Impresora $impresora): RedirectResponse
    {
        $this->impresoras->eliminar($impresora);

        return redirect()->route('impresoras.index')->with('estado', 'Impresora eliminada.');
    }

    /** Listas de los selectores del formulario. Al editar se conserva el área actual aunque esté inactiva. */
    private function listas(?Impresora $impresora = null): array
    {
        return [
            'modelos' => ModeloImpresora::with('marca')->get()
                ->sortBy(fn (ModeloImpresora $modelo) => [$modelo->marca->nombre, $modelo->nombre])
                ->values(),
            'areas' => Area::with('lugar.zona')
                ->where(fn ($consulta) => $consulta->where('activa', true)
                    ->when($impresora?->area_id, fn ($interna, $id) => $interna->orWhere('id', $id)))
                ->get()
                ->sortBy(fn (Area $area) => [$area->lugar->zona->nombre, $area->lugar->nombre, $area->nombre])
                ->values(),
            'estados' => EstadoImpresora::orderBy('id')->get(),
        ];
    }
}