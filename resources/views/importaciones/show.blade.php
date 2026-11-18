@extends('layouts.app')

@section('titulo', 'Resultado de importación')

@section('contenido')
    @php
        [$etiqueta, $color] = match ($importacion->estado) {
            'completada' => ['Completada', 'success'],
            'con_errores' => ['Completada con errores', 'warning'],
            'fallida' => ['Fallida', 'danger'],
            default => ['En proceso', 'secondary'],
        };
    @endphp
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <a href="{{ route('importaciones.index') }}" class="small">← Importaciones</a>
            <h1 class="h3 mb-1">Resultado de importación</h1>
            <span class="badge text-bg-{{ $color }}">{{ $etiqueta }}</span>
        </div>
        <a href="{{ route('importaciones.create') }}" class="btn btn-primary">Nueva importación</a>
    </div>

    <div class="card card-body mb-4">
        <div class="row g-3">
            <div class="col-md-4"><span class="text-body-secondary">Archivo / hoja</span><div>{{ $importacion->archivo_nombre }} · {{ $importacion->hoja }}</div></div>
            <div class="col-md-2"><span class="text-body-secondary">Filas leídas</span><div>{{ $importacion->filas_leidas }}</div></div>
            <div class="col-md-2"><span class="text-body-secondary">Importadas</span><div>{{ $importacion->filas_importadas }}</div></div>
            <div class="col-md-2"><span class="text-body-secondary">Con error</span><div>{{ $importacion->filas_con_error }}</div></div>
            <div class="col-md-2"><span class="text-body-secondary">Usuario</span><div>{{ $importacion->usuario?->name }}</div></div>
        </div>
    </div>

    @if ($importacion->errores->isNotEmpty())
        <h2 class="h5">Errores por fila</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle bg-body border">
                <thead><tr><th>Fila</th><th>Columna</th><th>Valor</th><th>Mensaje</th></tr></thead>
                <tbody>
                    @foreach ($importacion->errores as $error)
                        <tr>
                            <td>{{ $error->fila }}</td>
                            <td>{{ $error->columna ?? '—' }}</td>
                            <td>{{ $error->valor ?? '—' }}</td>
                            <td>{{ $error->mensaje }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="alert alert-success">No se encontraron errores.</div>
    @endif
@endsection
