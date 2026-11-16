<?php

namespace App\Modules\Printers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Printers\Http\Requests\AreaRequest;
use App\Modules\Printers\Models\Area;
use App\Modules\Printers\Models\Lugar;
use App\Modules\Printers\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AreaController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogos)
    {
    }

    public function index(Lugar $lugar): View
    {
        $lugar->load('zona');

        return view('ubicaciones.areas', [
            'lugar' => $lugar,
            'areas' => $lugar->areas()->withCount('impresoras')->orderBy('nombre')->get(),
        ]);
    }

    public function store(AreaRequest $request, Lugar $lugar): RedirectResponse
    {
        $lugar->areas()->create($request->validated());

        return redirect()->route('areas.index', $lugar->id)->with('estado', 'Área creada.');
    }

    public function update(AreaRequest $request, Area $area): RedirectResponse
    {
        $area->update($request->validated());

        return redirect()->route('areas.index', $area->lugar_id)->with('estado', 'Área actualizada.');
    }

    public function destroy(Area $area): RedirectResponse
    {
        $this->catalogos->eliminarArea($area);

        return redirect()->route('areas.index', $area->lugar_id)->with('estado', 'Área eliminada.');
    }
}