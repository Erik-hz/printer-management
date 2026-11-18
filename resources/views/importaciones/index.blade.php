@extends('layouts.app')

@section('titulo', 'Importaciones históricas')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Importaciones históricas</h1>
        <a href="{{ route('importaciones.create') }}" class="btn btn-primary">Analizar Excel</a>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle bg-body border">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Archivo / hoja</th>
                    <th>Estado</th>
                    <th class="text-end">Leídas</th>
                    <th class="text-end">Importadas</th>
                    <th class="text-end">Con error</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($importaciones as $importacion)
                    @php
                        [$etiqueta, $color] = match ($importacion->estado) {
                            'completada' => ['Completada', 'success'],
                            'con_errores' => ['Con errores', 'warning'],
                            'fallida' => ['Fallida', 'danger'],
                            default => ['En proceso', 'secondary'],
                        };
                    @endphp
                    <tr>
                        <td><a href="{{ route('importaciones.show', $importacion) }}">{{ $importacion->iniciada_en->format('d/m/Y H:i') }}</a></td>
                        <td>{{ $importacion->archivo_nombre }}<div class="small text-body-secondary">{{ $importacion->hoja }}</div></td>
                        <td><span class="badge text-bg-{{ $color }}">{{ $etiqueta }}</span></td>
                        <td class="text-end">{{ $importacion->filas_leidas }}</td>
                        <td class="text-end">{{ $importacion->filas_importadas }}</td>
                        <td class="text-end">{{ $importacion->filas_con_error }}</td>
                        <td>{{ $importacion->usuario?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-3">Todavía no hay importaciones.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $importaciones->links() }}
@endsection
