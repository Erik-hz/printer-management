@extends('layouts.app')

@section('titulo', 'Impresoras')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
            Impresoras
            <span class="badge text-bg-secondary fs-6 align-middle">{{ $impresoras->total() }}</span>
        </h1>
        @can('gestionar-impresoras')
            <a href="{{ route('impresoras.create') }}" class="btn btn-primary">Nueva impresora</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('impresoras.index') }}" class="row g-2 mb-3">
        <div class="col-12 col-md-4 col-lg-3">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Buscar por nombre, IP o serie" aria-label="Buscar">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <select name="zona" class="form-select form-select-sm" aria-label="Zona">
                <option value="">Todas las zonas</option>
                @foreach ($zonas as $zona)
                    <option value="{{ $zona->id }}" @selected((int) request('zona') === $zona->id)>{{ $zona->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <select name="marca" class="form-select form-select-sm" aria-label="Marca">
                <option value="">Todas las marcas</option>
                @foreach ($marcas as $marca)
                    <option value="{{ $marca->id }}" @selected((int) request('marca') === $marca->id)>{{ $marca->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <select name="estado" class="form-select form-select-sm" aria-label="Estado">
                <option value="">Todos los estados</option>
                @foreach ($estados as $estado)
                    <option value="{{ $estado->id }}" @selected((int) request('estado') === $estado->id)>
                        {{ ucfirst($estado->nombre) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <select name="conexion" class="form-select form-select-sm" aria-label="Conexión">
                <option value="">Toda conexión</option>
                <option value="red" @selected(request('conexion') === 'red')>Red</option>
                <option value="usb" @selected(request('conexion') === 'usb')>USB</option>
            </select>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            <a href="{{ route('impresoras.index') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
        </div>
    </form>

    <section class="card mb-3" aria-labelledby="impresoras-tinta-resumen">
        <div class="card-body">
            <h2 class="h5 mb-1" id="impresoras-tinta-resumen">Estado de tinta</h2>
            <p class="text-body-secondary small">
                Niveles de la última lectura de las impresoras de esta página.
                Crítico: ≤ {{ config('monitoring.umbrales_nivel.critico_hasta') }}%;
                bajo: > {{ config('monitoring.umbrales_nivel.critico_hasta') }}% y ≤ {{ config('monitoring.umbrales_nivel.bajo_hasta') }}%;
                normal: > {{ config('monitoring.umbrales_nivel.bajo_hasta') }}%.
                La barra cuenta niveles con porcentaje disponible; impresoras sin niveles se cuentan aparte.
            </p>
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2 h-100">
                        <span class="d-block text-body-secondary small">Críticos</span>
                        <strong>{{ $resumenTinta['criticos'] }}</strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2 h-100">
                        <span class="d-block text-body-secondary small">Bajos</span>
                        <strong>{{ $resumenTinta['bajos'] }}</strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2 h-100">
                        <span class="d-block text-body-secondary small">Normales</span>
                        <strong>{{ $resumenTinta['normales'] }}</strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2 h-100">
                        <span class="d-block text-body-secondary small">Impresoras sin niveles</span>
                        <strong>{{ $resumenTinta['sin_dato'] }}</strong>
                    </div>
                </div>
            </div>
            @if ($resumenTinta['total'] > 0)
                <div class="progress impresoras-tinta-resumen" role="img"
                     aria-label="Distribución de {{ $resumenTinta['total'] }} niveles de tinta: {{ $resumenTinta['criticos'] }} críticos, {{ $resumenTinta['bajos'] }} bajos y {{ $resumenTinta['normales'] }} normales">
                    @foreach ([
                        ['cantidad' => $resumenTinta['criticos'], 'clase' => 'danger', 'nombre' => 'Críticos'],
                        ['cantidad' => $resumenTinta['bajos'], 'clase' => 'warning', 'nombre' => 'Bajos'],
                        ['cantidad' => $resumenTinta['normales'], 'clase' => 'success', 'nombre' => 'Normales'],
                    ] as $segmento)
                        @if ($segmento['cantidad'] > 0)
                            <div class="progress-bar text-bg-{{ $segmento['clase'] }}"
                                 style="width: {{ round($segmento['cantidad'] / $resumenTinta['total'] * 100, 1) }}%"
                                 title="{{ $segmento['nombre'] }}: {{ $segmento['cantidad'] }}"
                                 aria-hidden="true"></div>
                        @endif
                    @endforeach
                </div>
            @else
                <p class="text-body-secondary mb-0">No hay porcentajes de tinta registrados en esta página.</p>
            @endif
        </div>
    </section>

    <div class="table-responsive">
        <table class="table table-sm align-middle bg-body border">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Modelo</th>
                    <th>Ubicación</th>
                    <th>Conexión</th>
                    <th>Estado</th>
                    <th>Niveles de tinta</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($impresoras as $impresora)
                    @php
                        $clase = match ($impresora->estado->nombre) {
                            'activa' => 'success',
                            'retirada' => 'secondary',
                            'contingencia' => 'warning',
                            default => 'light',
                        };
                    @endphp
                    <tr>
                        <td>{{ $impresora->nombre }}</td>
                        <td>{{ $impresora->modelo->marca->nombre }} {{ $impresora->modelo->nombre }}</td>
                        <td>
                            {{ $impresora->area->lugar->zona->nombre }} ›
                            {{ $impresora->area->lugar->nombre }} ›
                            {{ $impresora->area->nombre }}
                        </td>
                        <td>
                            @if ($impresora->esDeRed())
                                Red · {{ $impresora->direccion_ip }}
                            @else
                                USB @if ($impresora->equipo_conectado) · {{ $impresora->equipo_conectado }} @endif
                            @endif
                        </td>
                        <td><span class="badge text-bg-{{ $clase }}">{{ ucfirst($impresora->estado->nombre) }}</span></td>
                        <td>
                            @if ($impresora->ultimaLectura?->niveles->isNotEmpty())
                                <div class="d-grid gap-2">
                                    @foreach ($impresora->ultimaLectura->niveles as $nivel)
                                        @include('lecturas._nivel', ['nivel' => $nivel])
                                    @endforeach
                                </div>
                                <span class="d-block text-body-secondary small mt-1">
                                    {{ $impresora->ultimaLectura->fecha_lectura->format('d/m/Y H:i') }}
                                </span>
                            @else
                                <span class="text-body-secondary small">Sin dato</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @can('gestionar-impresoras')
                                <a href="{{ route('impresoras.edit', $impresora) }}"
                                   class="btn btn-sm btn-outline-primary">Editar</a>
                                @if ($impresora->estado->nombre !== 'retirada')
                                    <form method="POST" action="{{ route('impresoras.retirar', $impresora) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('¿Retirar esta impresora? Seguirá en el listado como retirada.')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Retirar</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('impresoras.destroy', $impresora) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('¿Eliminar esta impresora? Use esta opción solo para corregir errores de carga.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-body-secondary py-3">No hay impresoras.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $impresoras->links() }}
@endsection