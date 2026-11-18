<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\MovimientoRequest;
use App\Modules\Inventory\Models\Consumible;
use App\Modules\Inventory\Models\MovimientoInventario;
use App\Modules\Inventory\Services\InventarioService;
use App\Modules\Printers\Models\Impresora;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MovimientoController extends Controller
{
    public function __construct(private readonly InventarioService $inventario)
    {
    }

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'consumible' => ['nullable', 'integer'],
            'tipo' => ['nullable', Rule::in(MovimientoRequest::TIPOS)],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $busqueda = trim((string) ($filtros['q'] ?? ''));

        $movimientos = MovimientoInventario::query()
            ->with(['consumible', 'usuario', 'impresora', 'detalleCompra.compra'])
            ->when(filled($filtros['consumible'] ?? null), fn ($consulta) => $consulta->where('consumible_id', $filtros['consumible']))
            ->when(filled($filtros['tipo'] ?? null), fn ($consulta) => $consulta->where('tipo', $filtros['tipo']))
            ->when(filled($filtros['desde'] ?? null), fn ($consulta) => $consulta->where('fecha_movimiento', '>=', Carbon::parse($filtros['desde'])->startOfDay()))
            ->when(filled($filtros['hasta'] ?? null), fn ($consulta) => $consulta->where('fecha_movimiento', '<=', Carbon::parse($filtros['hasta'])->endOfDay()))
            ->when($busqueda !== '', fn ($consulta) => $consulta->where(
                fn ($donde) => $donde
                    ->where('ticket', 'like', "%{$busqueda}%")
                    ->orWhere('motivo', 'like', "%{$busqueda}%")
            ))
            ->orderByDesc('fecha_movimiento')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('movimientos.index', [
            'movimientos' => $movimientos,
            'consumibles' => Consumible::withTrashed()->orderBy('codigo')->get(['id', 'codigo']),
        ]);
    }

    public function create(Request $request): View
    {
        $tipo = in_array($request->input('tipo'), MovimientoRequest::TIPOS, true)
            ? $request->input('tipo')
            : 'entrada';

        return view('movimientos.form', [
            'tipoInicial' => $tipo,
            'consumibleInicial' => $request->integer('consumible') ?: null,
            'consumibles' => Consumible::with('existencia')->orderBy('codigo')->get(),
            'impresoras' => Impresora::with('area.lugar.zona')
                ->whereHas('estado', fn ($estado) => $estado->where('es_operativo', true))
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function store(MovimientoRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $usuarioId = $request->user()->id;
        $consumibleId = (int) $datos['consumible_id'];
        $cantidad = (int) $datos['cantidad'];
        $ticket = $datos['ticket'] ?? null;

        // Las validaciones de negocio (stock, impresora operativa...) las hace el servicio
        // y se muestran como errores del formulario.
        $movimiento = match ($datos['tipo']) {
            'entrada' => $this->inventario->registrarEntradaManual($consumibleId, $cantidad, $datos['motivo'], $usuarioId, $ticket),
            'salida' => $this->inventario->registrarSalida($consumibleId, $cantidad, $usuarioId, (int) $datos['impresora_id'], $ticket),
            'ajuste' => $this->inventario->registrarAjuste($consumibleId, $cantidad, $datos['motivo'], $usuarioId, $ticket),
        };

        return redirect()
            ->route('movimientos.index', ['consumible' => $consumibleId])
            ->with('estado', "Movimiento registrado. Existencias actuales: {$movimiento->saldo_resultante}.");
    }
}