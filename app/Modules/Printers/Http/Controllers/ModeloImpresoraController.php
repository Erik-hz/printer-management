<?php

namespace App\Modules\Printers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Printers\Http\Requests\ModeloImpresoraRequest;
use App\Modules\Printers\Models\Marca;
use App\Modules\Printers\Models\ModeloImpresora;
use App\Modules\Printers\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModeloImpresoraController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogos)
    {
    }

    public function index(Request $request): View
    {
        $modelos = ModeloImpresora::with('marca')
            ->withCount('impresoras')
            ->when($request->filled('marca'), fn ($consulta) => $consulta->where('marca_id', $request->integer('marca')))
            ->get()
            ->sortBy(fn (ModeloImpresora $modelo) => [$modelo->marca->nombre, $modelo->nombre])
            ->values();

        return view('modelos.index', [
            'modelos' => $modelos,
            'marcas' => Marca::orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('modelos.form', [
            'modelo' => new ModeloImpresora(),
            'marcas' => Marca::orderBy('nombre')->get(),
        ]);
    }

    public function store(ModeloImpresoraRequest $request): RedirectResponse
    {
        ModeloImpresora::create($request->validated());

        return redirect()->route('modelos.index')->with('estado', 'Modelo creado.');
    }

    public function edit(ModeloImpresora $modelo): View
    {
        return view('modelos.form', [
            'modelo' => $modelo,
            'marcas' => Marca::orderBy('nombre')->get(),
        ]);
    }

    public function update(ModeloImpresoraRequest $request, ModeloImpresora $modelo): RedirectResponse
    {
        $modelo->update($request->validated());

        return redirect()->route('modelos.index')->with('estado', 'Modelo actualizado.');
    }

    public function destroy(ModeloImpresora $modelo): RedirectResponse
    {
        $this->catalogos->eliminarModelo($modelo);

        return redirect()->route('modelos.index')->with('estado', 'Modelo eliminado.');
    }
}