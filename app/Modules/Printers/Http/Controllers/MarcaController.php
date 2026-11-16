<?php

namespace App\Modules\Printers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Printers\Http\Requests\MarcaRequest;
use App\Modules\Printers\Models\Marca;
use App\Modules\Printers\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MarcaController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogos)
    {
    }

    public function index(): View
    {
        return view('marcas.index', [
            'marcas' => Marca::withCount('modelos')->orderBy('nombre')->get(),
        ]);
    }

    public function store(MarcaRequest $request): RedirectResponse
    {
        Marca::create($request->validated());

        return redirect()->route('marcas.index')->with('estado', 'Marca creada.');
    }

    public function update(MarcaRequest $request, Marca $marca): RedirectResponse
    {
        $marca->update($request->validated());

        return redirect()->route('marcas.index')->with('estado', 'Marca actualizada.');
    }

    public function destroy(Marca $marca): RedirectResponse
    {
        $this->catalogos->eliminarMarca($marca);

        return redirect()->route('marcas.index')->with('estado', 'Marca eliminada.');
    }
}