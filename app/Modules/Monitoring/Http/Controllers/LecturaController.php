<?php

namespace App\Modules\Monitoring\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Monitoring\Http\Requests\LecturaRequest;
use App\Modules\Monitoring\Jobs\ActualizarLecturaSnmp;
use App\Modules\Monitoring\Models\EjecucionLecturaSnmp;
use App\Modules\Monitoring\Models\Lectura;
use App\Modules\Monitoring\Models\MotivoSinLectura;
use App\Modules\Monitoring\Services\ImpresorasActivasSnmp;
use App\Modules\Monitoring\Services\ImpresorasDisponiblesSnmp;
use App\Modules\Monitoring\Services\LecturaService;
use App\Modules\Printers\Models\Impresora;
use App\Modules\Printers\Services\PrinterConnectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LecturaController extends Controller
{
    public function __construct(
        private readonly LecturaService $lecturas,
        private readonly ImpresorasDisponiblesSnmp $impresorasSnmp,
    ) {}

    public function index(Request $request): View
    {
        $impresoras = Impresora::withTrashed()->orderBy('nombre')->get(['id', 'nombre']);
        $impresoraSeleccionada = $impresoras->firstWhere('id', $request->integer('impresora'));
        $ultimaEjecucionSnmp = $impresoraSeleccionada
            ? EjecucionLecturaSnmp::query()
                ->where('impresora_id', $impresoraSeleccionada->id)
                ->latest('id')
                ->first()
            : null;
        $loteSolicitado = $request->string('lote')->toString();
        $ejecucionesLote = Str::isUuid($loteSolicitado)
            ? EjecucionLecturaSnmp::query()
                ->with('impresora')
                ->where('lote_id', $loteSolicitado)
                ->orderBy('impresora_id')
                ->get()
            : collect();

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

        $lecturasGrafica = $impresoraSeleccionada
            ? Lectura::query()
                ->with('niveles.tipoConsumible')
                ->where('impresora_id', $impresoraSeleccionada->id)
                ->when(
                    $request->filled('origen'),
                    fn ($consulta) => $consulta->where('origen', $request->string('origen')->toString())
                )
                ->orderByDesc('fecha_lectura')
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->reverse()
                ->values()
            : collect();

        $series = [];
        foreach ($lecturasGrafica as $lectura) {
            foreach ($lectura->niveles as $nivel) {
                $clave = "{$nivel->tipo_consumible_id}:{$nivel->color}";
                $series[$clave] ??= [
                    'etiqueta' => "{$nivel->tipoConsumible->nombre} ".ucfirst($nivel->color),
                    'color' => $nivel->color,
                    'valores' => [],
                ];
            }
        }

        $contadores = [];
        foreach ($lecturasGrafica as $lectura) {
            $contadores[] = $lectura->contador_paginas;
            $nivelesLectura = [];

            foreach ($lectura->niveles as $nivel) {
                $clave = "{$nivel->tipo_consumible_id}:{$nivel->color}";
                $nivelesLectura[$clave] = $nivel->nivel === null ? null : (float) $nivel->nivel * 100;
            }

            foreach ($series as $clave => &$serie) {
                $serie['valores'][] = $nivelesLectura[$clave] ?? null;
            }
            unset($serie);
        }

        $grafica = [
            'etiquetas' => $lecturasGrafica->map(fn (Lectura $lectura) => $lectura->fecha_lectura->format('d/m H:i'))->all(),
            'fechas' => $lecturasGrafica->map(fn (Lectura $lectura) => $lectura->fecha_lectura->format('d/m/Y H:i'))->all(),
            'contadores' => $contadores,
            'niveles' => array_values($series),
            'umbrales' => [
                'critico' => (float) config('monitoring.umbrales_nivel.critico_hasta', 10),
                'bajo' => (float) config('monitoring.umbrales_nivel.bajo_hasta', 25),
            ],
        ];

        return view('lecturas.index', [
            'lecturas' => $lecturas,
            'impresoras' => $impresoras,
            'impresoraSeleccionada' => $impresoraSeleccionada,
            'grafica' => $grafica,
            'ultimaEjecucionSnmp' => $ultimaEjecucionSnmp,
            'ejecucionesLote' => $ejecucionesLote,
            'resultadosConectividad' => $request->session()->get('resultados_conectividad'),
        ]);
    }

    public function comprobarConectividad(PrinterConnectionService $conectividad): RedirectResponse
    {
        $impresoras = Impresora::query()
            ->where('tipo_conexion', 'red')
            ->whereNotNull('direccion_ip')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'direccion_ip']);

        $resultados = $impresoras->map(function (Impresora $impresora) use ($conectividad): array {
            $resultado = $conectividad->check((string) $impresora->direccion_ip, 1);

            return [
                'id' => $impresora->id,
                'nombre' => $impresora->nombre,
                'ip' => $resultado['ip'],
                'estado' => $resultado['status'],
                'http' => $resultado['http']['status'],
                'http_code' => $resultado['http']['http_code'],
                'snmp' => $resultado['snmp']['status'],
                'error' => $resultado['error'],
            ];
        })->all();

        return redirect()
            ->route('lecturas.index')
            ->with('resultados_conectividad', [
                'consultado_en' => now()->format('d/m/Y H:i:s'),
                'impresoras' => $resultados,
            ]);
    }

    public function actualizar(Request $request, ImpresorasActivasSnmp $impresorasActivas): RedirectResponse
    {
        $datos = $request->validate([
            'impresora_id' => [
                'required',
                'integer',
                Rule::exists('impresoras', 'id')->whereNull('deleted_at'),
            ],
        ]);
        $impresoraId = (int) $datos['impresora_id'];
        $destino = route('lecturas.index', ['impresora' => $impresoraId]);

        if ($error = $this->errorPreparacionSnmp()) {
            return redirect($destino)->withErrors(['actualizacion' => $error]);
        }

        $resultadoDisponibilidad = $impresorasActivas->listar($impresoraId);
        $impresora = $resultadoDisponibilidad['impresoras']->first();
        if ($impresora === null) {
            return redirect($destino)->withErrors([
                'actualizacion' => $resultadoDisponibilidad['elegibles'] === 0
                    ? 'La impresora no está configurada como operativa y compatible con SNMP.'
                    : 'La impresora no responde por SNMP; no se inició la consulta de niveles de tinta. '.($resultadoDisponibilidad['errores'][0] ?? ''),
            ]);
        }

        $hayEjecucionActiva = EjecucionLecturaSnmp::query()
            ->where('impresora_id', $impresoraId)
            ->whereIn('estado', ['pendiente', 'ejecutando'])
            ->exists();

        if ($hayEjecucionActiva) {
            return redirect($destino)->withErrors([
                'actualizacion' => 'Ya hay una actualización SNMP en curso para esta impresora.',
            ]);
        }

        $snmpVersion = $resultadoDisponibilidad['versiones'][$impresoraId] ?? '1';
        $ejecucion = DB::transaction(function () use ($request, $impresoraId, $snmpVersion): EjecucionLecturaSnmp {
            $ejecucion = EjecucionLecturaSnmp::query()->create([
                'impresora_id' => $impresoraId,
                'usuario_id' => $request->user()->id,
                'estado' => 'pendiente',
            ]);

            ActualizarLecturaSnmp::dispatch($ejecucion->id, $snmpVersion)->afterCommit();

            return $ejecucion;
        });

        return redirect($destino)->with('estado', 'Se inició la actualización SNMP de la impresora seleccionada.');
    }

    public function actualizarTodas(Request $request, ImpresorasActivasSnmp $impresorasActivas): RedirectResponse
    {
        $destino = route('lecturas.index');
        if ($error = $this->errorPreparacionSnmp()) {
            return redirect($destino)->withErrors(['actualizacion' => $error]);
        }

        // 1. Limpieza automática de ejecuciones colgadas por más de 15 minutos (evita bloqueos fantasma)
        $resultadoDisponibilidad = $impresorasActivas->listar();
        if ($resultadoDisponibilidad['elegibles'] === 0) {
            return redirect($destino)->withErrors([
                'actualizacion' => 'No hay impresoras operativas configuradas para consulta SNMP.',
            ]);
        }

        $sinRespuestaSnmp = $resultadoDisponibilidad['sin_respuesta'];
        $impresorasEnLinea = $resultadoDisponibilidad['impresoras'];

        if ($impresorasEnLinea->isEmpty()) {
            return redirect($destino)->withErrors([
                'actualizacion' => "Ninguna impresora operativa respondió por SNMP; no se inició ninguna consulta de niveles de tinta ({$sinRespuestaSnmp} sin respuesta). ".implode(' | ', array_slice($resultadoDisponibilidad['errores'], 0, 3)),
            ]);
        }

        EjecucionLecturaSnmp::query()
            ->whereIn('estado', ['pendiente', 'ejecutando'])
            ->where('updated_at', '<', now()->subMinutes(15))
            ->update([
                'estado' => 'fallido',
            ]);

        $impresorasDisponibles = $impresorasEnLinea;
        if ($impresorasDisponibles->isEmpty()) {
            return redirect($destino)->withErrors([
                'actualizacion' => 'No hay impresoras disponibles para consulta SNMP.',
            ]);
        }

        $loteId = (string) Str::uuid();
        $encoladasCount = 0;
        $omitidasCount = 0;

        $versionesSnmp = $resultadoDisponibilidad['versiones'];
        DB::transaction(function () use ($request, $impresorasDisponibles, $loteId, $versionesSnmp, &$encoladasCount, &$omitidasCount): void {
            foreach ($impresorasDisponibles as $impresora) {
                // 2. Verificar si ESTA impresora en particular tiene un proceso activo
                $tieneEjecucionActiva = EjecucionLecturaSnmp::query()
                    ->where('impresora_id', $impresora->id)
                    ->whereIn('estado', ['pendiente', 'ejecutando'])
                    ->exists();

                if ($tieneEjecucionActiva) {
                    $omitidasCount++;
                    continue; // Salta esta impresora y continúa con las demás conectadas
                }

                // 3. Crear el registro de ejecución y encolar el Job de forma independiente
                $ejecucion = EjecucionLecturaSnmp::query()->create([
                    'impresora_id' => $impresora->id,
                    'usuario_id' => $request->user()->id,
                    'lote_id' => $loteId,
                    'estado' => 'pendiente',
                ]);

                $snmpVersion = $versionesSnmp[$impresora->id] ?? '1';
                ActualizarLecturaSnmp::dispatch($ejecucion->id, $snmpVersion)->afterCommit();
                $encoladasCount++;
            }
        });

        if ($encoladasCount === 0) {
            return redirect($destino)->withErrors([
                'actualizacion' => 'No se pudo encolar ninguna impresora porque todas tienen una actualización en curso.',
            ]);
        }

        $mensaje = "Se encoló la actualización SNMP para {$encoladasCount} impresoras.";
        $mensaje .= " Se omitieron {$sinRespuestaSnmp} sin respuesta SNMP.";
        if ($omitidasCount > 0) {
            $mensaje .= " Se omitieron {$omitidasCount} por tener procesos activos.";
        }

        return redirect()
            ->route('lecturas.index', ['lote' => $loteId])
            ->with('estado', $mensaje);
    }

    private function errorPreparacionSnmp(): ?string
    {
        if (config('queue.default') === 'sync') {
            return 'La cola está configurada como síncrona. Configura QUEUE_CONNECTION=database e inicia un worker para ejecutar el sondeo en segundo plano.';
        }

        foreach ([
            'MONITOREO_API_URL' => config('monitoring.api_url'),
            'MONITOREO_TOKEN' => config('monitoring.token'),
            'SNMP_COMMUNITY_GET' => config('monitoring.snmp_community_get'),
        ] as $variable => $valor) {
            if (! is_string($valor) || ($variable !== 'SNMP_COMMUNITY_GET' && trim($valor) === '')) {
                return "Configura {$variable} en el entorno del servidor antes de actualizar lecturas.";
            }
        }

        foreach ([
            base_path('scripts'.DIRECTORY_SEPARATOR.'collect_snmp_v1.ps1'),
            base_path('scripts'.DIRECTORY_SEPARATOR.'SnmpV1.psm1'),
            (string) config('monitoring.snmp_config'),
        ] as $archivo) {
            if (! is_file($archivo)) {
                return 'No se encontró el recolector SNMP o su archivo local de mapeos en el servidor.';
            }
        }

        return null;
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
