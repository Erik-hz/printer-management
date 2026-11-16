<?php

namespace App\Modules\Printers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Printers\Http\Requests\ZonaRequest;
use App\Modules\Printers\Models\Zona;
use App\Modules\Printers\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ZonaController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogos)
    {
    }

    public function index(): View
    {
        return view('ubicaciones.zonas', [
            'zonas' => Zona::withCount('lugares')->orderBy('nombre')->get(),
        ]);
    }

    public function store(ZonaRequest $request): RedirectResponse
    {
        Zona::create($request->validated());

        return redirect()->route('zonas.index')->with('estado', 'Zona creada.');
    }

    public function update(ZonaRequest $request, Zona $zona): RedirectResponse
    {
        $zona->update($request->validated());

        return redirect()->route('zonas.index')->with('estado', 'Zona actualizada.');
    }

    public function destroy(Zona $zona): RedirectResponse
    {
        $this->catalogos->eliminarZona($zona);

        return redirect()->route('zonas.index')->with('estado', 'Zona eliminada.');
    }
}