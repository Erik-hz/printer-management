<?php

namespace App\Modules\Monitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Monitoring\Http\Requests\LecturaRequest;
use App\Modules\Monitoring\Models\Lectura;
use App\Modules\Monitoring\Models\MotivoSinLectura;
use App\Modules\Monitoring\Services\LecturaService;
use App\Modules\Printers\Models\Impresora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LecturaController extends Controller
{
    public function __construct(private readonly LecturaService $lecturas) {}

    public function index(Request $request): View
    {
        $lecturas = Lectura::query()
            ->with(['impresora', 'motivoSinLectura', 'usuario', 'niveles.tipoConsumible'])
            ->when(
                $request->filled('impresora'),
                fn ($consulta) => $consulta->where('impresora_id', $request->integer('impresora'))
            )
            ->when(
                $request->filled('origen'),
                fn ($consulta) => $consulta->where('origen', $request->string('origen')->toString())
            )
            ->orderByDesc('fecha_lectura')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('lecturas.index', [
            'lecturas' => $lecturas,
            'impresoras' => Impresora::withTrashed()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function create(): View
    {
        return view('lecturas.form', [
            'impresoras' => Impresora::with('estado')->orderBy('nombre')->get(),
            'motivos' => MotivoSinLectura::orderBy('nombre')->get(),
            'tipos' => TipoConsumible::orderBy('nombre')->get(),
        ]);
    }

    public function store(LecturaRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $lectura = $this->lecturas->registrar([
            ...$datos,
            'origen' => 'manual',
            'usuario_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('lecturas.index', ['impresora' => $lectura->impresora_id])
            ->with('estado', 'Lectura registrada.');
    }
}
