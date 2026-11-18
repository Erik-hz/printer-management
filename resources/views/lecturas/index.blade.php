@extends('layouts.app')

@section('titulo', 'Lecturas')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Lecturas</h1>
        @can('registrar-lecturas')
            <a href="{{ route('lecturas.create') }}" class="btn btn-primary">Nueva lectura</a>
        @endcan
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
                                @foreach ($lectura->niveles as $nivel)
                                    <span class="badge text-bg-secondary">
                                        {{ $nivel->tipoConsumible->nombre }} {{ $nivel->color }}:
                                        {{ number_format((float) $nivel->nivel * 100, 1, ',', '.') }}%
                                    </span>
                                @endforeach
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
