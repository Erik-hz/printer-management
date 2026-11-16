<?php

namespace App\Modules\Printers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Printers\Http\Requests\LugarRequest;
use App\Modules\Printers\Models\Lugar;
use App\Modules\Printers\Models\Zona;
use App\Modules\Printers\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LugarController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogos)
    {
    }

    public function index(Zona $zona): View
    {
        return view('ubicaciones.lugares', [
            'zona' => $zona,
            'lugares' => $zona->lugares()->withCount('areas')->orderBy('nombre')->get(),
        ]);
    }

    public function store(LugarRequest $request, Zona $zona): RedirectResponse
    {
        $zona->lugares()->create($request->validated());

        return redirect()->route('lugares.index', $zona->id)->with('estado', 'Lugar creado.');
    }

    public function update(LugarRequest $request, Lugar $lugar): RedirectResponse
    {
        $lugar->update($request->validated());

        return redirect()->route('lugares.index', $lugar->zona_id)->with('estado', 'Lugar actualizado.');
    }

    public function destroy(Lugar $lugar): RedirectResponse
    {
        $this->catalogos->eliminarLugar($lugar);

        return redirect()->route('lugares.index', $lugar->zona_id)->with('estado', 'Lugar eliminado.');
    }
}