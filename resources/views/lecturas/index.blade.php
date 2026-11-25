@extends('layouts.app')

@section('titulo', 'Lecturas')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Lecturas</h1>
        <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('lecturas.conectividad') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">
                    Comprobar conectividad
                </button>
            </form>
            @can('registrar-lecturas')
                @if ($impresoraSeleccionada !== null)
                    <form method="POST" action="{{ route('lecturas.actualizar') }}">
                        @csrf
                        <input type="hidden" name="impresora_id" value="{{ $impresoraSeleccionada->id }}">
                        <button type="submit" class="btn btn-outline-primary">
                            Actualizar niveles de tinta
                        </button>
                    </form>
                @endif
                <a href="{{ route('lecturas.create') }}" class="btn btn-primary">Nueva lectura</a>
            @endcan
        </div>
    </div>

    <form method="GET" action="{{ route('lecturas.index') }}" class="row g-2 mb-3">
        <div class="col-12 col-md-5">
            <select name="impresora" class="form-select form-select-sm">
                <option value="">Todas las impresoras</option>
                @foreach ($impresoras as $impresora)
                    <option value="{{ $impresora->id }}" @selected((int) request('impresora') === $impresora->id)>
                        {{ $impresora->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-3">
            <select name="origen" class="form-select form-select-sm">
                <option value="">Todos los orígenes</option>
                @foreach (['manual', 'snmp', 'powershell', 'importacion'] as $origen)
                    <option value="{{ $origen }}" @selected(request('origen') === $origen)>
                        {{ ucfirst($origen) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            <a href="{{ route('lecturas.index') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
        </div>
    </form>

    @if ($resultadosConectividad !== null)
        @php
            $impresorasConectividad = collect($resultadosConectividad['impresoras'] ?? []);
            $accesibles = $impresorasConectividad->where('estado', 'online')->count();
        @endphp
        <section class="card mb-4" aria-labelledby="estado-conectividad">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h2 class="h5 mb-1" id="estado-conectividad">Conectividad de impresoras</h2>
                        <p class="text-body-secondary small mb-0">
                            Comprobado el {{ $resultadosConectividad['consultado_en'] ?? '—' }}.
                            {{ $accesibles }} de {{ $impresorasConectividad->count() }} accesibles.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('lecturas.conectividad') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Volver a comprobar</button>
                    </form>
                </div>

                @if ($impresorasConectividad->isEmpty())
                    <p class="text-body-secondary mb-0">No hay impresoras de red con dirección IP registrada.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Impresora</th>
                                    <th>IP</th>
                                    <th>Estado</th>
                                    <th>HTTP</th>
                                    <th>SNMP/161</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($impresorasConectividad as $estadoImpresora)
                                    @php
                                        [$claseEstado, $etiquetaEstado] = match ($estadoImpresora['estado']) {
                                            'online' => ['success', 'Accesible'],
                                            'offline' => ['danger', 'Sin respuesta'],
                                            default => ['warning', 'Error'],
                                        };
                                        $claseSnmp = match ($estadoImpresora['snmp']) {
                                            'online' => 'success',
                                            'offline' => 'danger',
                                            'skipped' => 'secondary',
                                            default => 'warning',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $estadoImpresora['nombre'] }}</td>
                                        <td><code>{{ $estadoImpresora['ip'] }}</code></td>
                                        <td><span class="badge text-bg-{{ $claseEstado }}">{{ $etiquetaEstado }}</span></td>
                                        <td>
                                            @if ($estadoImpresora['http_code'] !== null)
                                                <span class="badge text-bg-success">HTTP {{ $estadoImpresora['http_code'] }}</span>
                                            @else
                                                <span class="badge text-bg-secondary">Sin respuesta</span>
                                            @endif
                                        </td>
                                        <td><span class="badge text-bg-{{ $claseSnmp }}">{{ strtoupper($estadoImpresora['snmp']) }}</span></td>
                                    </tr>
                                    @if ($estadoImpresora['error'])
                                        <tr>
                                            <td colspan="5" class="pt-0 border-top-0">
                                                <small class="text-body-secondary">{{ $estadoImpresora['error'] }}</small>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if ($ultimaEjecucionSnmp !== null)
        @php
            $alertaEjecucion = match ($ultimaEjecucionSnmp->estado) {
                'completada' => 'success',
                'fallida' => 'danger',
                'pendiente', 'ejecutando' => 'info',
                default => 'secondary',
            };
            $estadoEjecucion = match ($ultimaEjecucionSnmp->estado) {
                'completada' => 'Actualización completada',
                'fallida' => 'La actualización falló',
                'pendiente' => 'Actualización pendiente',
                'ejecutando' => 'Actualización en curso',
                default => 'Estado de actualización desconocido',
            };
        @endphp
        <div class="alert alert-{{ $alertaEjecucion }}" role="status">
            <strong>{{ $estadoEjecucion }}</strong>
            @if ($ultimaEjecucionSnmp->error)
                <div>{{ $ultimaEjecucionSnmp->error }}</div>
                @if ($ultimaEjecucionSnmp->salida)
                    <details class="mt-2">
                        <summary>Ver salida del recolector</summary>
                        <pre class="small text-wrap mt-2 mb-0">{{ $ultimaEjecucionSnmp->salida }}</pre>
                    </details>
                @endif
            @elseif ($ultimaEjecucionSnmp->salida)
                <div>{{ $ultimaEjecucionSnmp->salida }}</div>
            @elseif ($ultimaEjecucionSnmp->estado === 'pendiente')
                <div>En cola; asegúrate de que haya un worker de Laravel activo. Actualiza esta página para ver el resultado.</div>
            @elseif ($ultimaEjecucionSnmp->estado === 'ejecutando')
                <div>La consulta se ejecuta en segundo plano. Actualiza esta página para ver el resultado.</div>
            @endif
        </div>
    @endif

    @if ($ejecucionesLote->isNotEmpty())
        @php
            $totalLote = $ejecucionesLote->count();
            $completadasLote = $ejecucionesLote->where('estado', 'completada')->count();
            $fallidasLote = $ejecucionesLote->where('estado', 'fallida')->count();
            $activasLote = $ejecucionesLote->whereIn('estado', ['pendiente', 'ejecutando'])->count();
            $claseAlertaLote = $activasLote > 0 ? 'info' : ($fallidasLote > 0 ? 'warning' : 'success');
        @endphp
        <div class="alert alert-{{ $claseAlertaLote }}" role="status">
            <strong>Actualización masiva:</strong>
            {{ $completadasLote }} completadas, {{ $fallidasLote }} fallidas y {{ $activasLote }} pendientes o en curso
            ({{ $totalLote }} impresoras).
            @if ($activasLote > 0)
                <a class="alert-link ms-2" href="{{ route('lecturas.index', ['lote' => $ejecucionesLote->first()->lote_id]) }}">
                    Actualizar estado
                </a>
            @endif
            <details class="mt-2">
                <summary>Ver resultado por impresora</summary>
                <ul class="list-group list-group-flush mt-2">
                    @foreach ($ejecucionesLote as $ejecucion)
                        @php
                            [$claseEstado, $etiquetaEstado] = match ($ejecucion->estado) {
                                'completada' => ['success', 'Completada'],
                                'fallida' => ['danger', 'Fallida'],
                                'ejecutando' => ['info', 'En curso'],
                                default => ['secondary', 'Pendiente'],
                            };
                        @endphp
                        <li class="list-group-item px-0">
                            <div class="d-flex justify-content-between gap-2">
                                <span>{{ $ejecucion->impresora->nombre }}</span>
                                <span class="badge text-bg-{{ $claseEstado }}">{{ $etiquetaEstado }}</span>
                            </div>
                            @if ($ejecucion->error)
                                <small class="text-body-secondary">{{ $ejecucion->error }}</small>
                                @if ($ejecucion->salida)
                                    <details class="mt-1">
                                        <summary>Ver salida del recolector</summary>
                                        <pre class="small text-wrap mt-2 mb-0">{{ $ejecucion->salida }}</pre>
                                    </details>
                                @endif
                            @elseif ($ejecucion->salida)
                                <details class="mt-1">
                                    <summary>Ver salida del recolector</summary>
                                    <pre class="small text-wrap mt-2 mb-0">{{ $ejecucion->salida }}</pre>
                                </details>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </details>
        </div>
    @endif

    <section class="mb-4" aria-labelledby="lecturas-tendencias">
        <div class="mb-3">
            <h2 class="h5 mb-1" id="lecturas-tendencias">
                {{ $impresoraSeleccionada ? "Tendencias: {$impresoraSeleccionada->nombre}" : 'Tendencias de lectura' }}
            </h2>
            <p class="text-body-secondary small mb-0">
                Hasta 30 lecturas recientes de la impresora seleccionada; se respeta el filtro de origen.
            </p>
        </div>

        @if ($impresoraSeleccionada === null)
            <div class="alert alert-info mb-0" role="status">
                Selecciona una impresora y pulsa «Filtrar» para ver las tendencias de contador y niveles.
            </div>
        @elseif (empty($grafica['etiquetas']))
            <div class="alert alert-light border mb-0" role="status">
                No hay lecturas para esta impresora con los filtros seleccionados.
            </div>
        @else
            <script type="application/json" id="datos-grafica-lecturas">@json($grafica)</script>
            <div class="row g-3">
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="h6">Contador de páginas</h3>
                            <div class="lecturas-chart">
                                <svg data-lecturas-chart="contador" role="img"
                                     aria-label="Tendencia del contador de páginas por fecha"></svg>
                            </div>
                            <div class="lecturas-chart-legend" data-chart-legend="contador"></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-6">
                    <div class="card h-100">
                        <div class="card-body">
                            <h3 class="h6">Niveles de consumibles</h3>
                            <div class="lecturas-chart">
                                <svg data-lecturas-chart="niveles" role="img"
                                     aria-label="Tendencia de niveles de consumibles por fecha"></svg>
                            </div>
                            <div class="lecturas-chart-legend" data-chart-legend="niveles"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </section>

    <div class="table-responsive">
        <table class="table table-sm align-middle bg-body border">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Impresora</th>
                    <th>Origen</th>
                    <th>Contador</th>
                    <th>Niveles / motivo</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($lecturas as $lectura)
                    <tr>
                        <td>{{ $lectura->fecha_lectura->format('d/m/Y H:i') }}</td>
                        <td>{{ $lectura->impresora->nombre }}</td>
                        <td>{{ ucfirst($lectura->origen) }}</td>
                        <td>{{ $lectura->contador_paginas === null ? '—' : number_format($lectura->contador_paginas, 0, ',', '.') }}</td>
                        <td>
                            @if ($lectura->motivoSinLectura)
                                {{ ucfirst($lectura->motivoSinLectura->nombre) }}
                            @elseif ($lectura->niveles->isNotEmpty())
                                <div class="d-grid gap-2">
                                    @foreach ($lectura->niveles as $nivel)
                                        @include('lecturas._nivel', ['nivel' => $nivel])
                                    @endforeach
                                </div>
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $lectura->usuario?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-body-secondary py-3">No hay lecturas registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $lecturas->links() }}
@endsection
