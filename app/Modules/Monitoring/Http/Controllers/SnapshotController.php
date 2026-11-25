<?php

namespace App\Modules\Monitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SnapshotController extends Controller
{
    public function index(Request $request): View
    {
        $conexion = DB::connection('snapshot');
        if (! Schema::connection('snapshot')->hasTable('monitoreo_snapshot')) {
            return view('snapshot.index', [
                'filas' => collect(),
                'impresoras' => collect(),
                'impresoraSeleccionada' => null,
                'resumen' => ['impresoras' => 0, 'lecturas' => 0, 'criticos' => 0, 'bajos' => 0],
                'exportadoEn' => null,
                'sinSnapshot' => true,
            ]);
        }

        $todos = $conexion->table('monitoreo_snapshot')
            ->orderBy('impresora_id')
            ->orderByDesc('fecha_lectura')
            ->orderByDesc('lectura_id')
            ->orderBy('color')
            ->get();

        $impresoras = $todos->unique('impresora_id')
            ->sortBy('impresora', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $impresoraSeleccionada = $request->integer('impresora') ?: null;
        $filasFiltradas = $impresoraSeleccionada
            ? $todos->where('impresora_id', $impresoraSeleccionada)->values()
            : $todos->values();

        $lecturasUnicas = $todos->whereNotNull('lectura_id')->unique('lectura_id')->count();
        $ultimosNiveles = collect();
        foreach ($todos->whereNotNull('lectura_id')->groupBy('impresora_id') as $filasImpresora) {
            $ultimaLecturaId = $filasImpresora->first()->lectura_id;
            $ultimosNiveles = $ultimosNiveles->concat(
                $filasImpresora->where('lectura_id', $ultimaLecturaId)->whereNotNull('nivel')
            );
        }

        $resumen = [
            'impresoras' => $impresoras->count(),
            'lecturas' => $lecturasUnicas,
            'criticos' => $ultimosNiveles->filter(fn ($fila) => (float) $fila->nivel <= 0.10)->count(),
            'bajos' => $ultimosNiveles->filter(fn ($fila) => (float) $fila->nivel > 0.10 && (float) $fila->nivel <= 0.25)->count(),
        ];

        $pagina = LengthAwarePaginator::resolveCurrentPage();
        $porPagina = 50;
        $filas = new LengthAwarePaginator(
            $filasFiltradas->forPage($pagina, $porPagina)->values(),
            $filasFiltradas->count(),
            $porPagina,
            $pagina,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('snapshot.index', [
            'filas' => $filas,
            'impresoras' => $impresoras,
            'impresoraSeleccionada' => $impresoraSeleccionada,
            'resumen' => $resumen,
            'exportadoEn' => $todos->first()?->exportado_en_utc,
            'sinSnapshot' => $todos->isEmpty(),
        ]);
    }
}
