@extends('layouts.app')

@php
    $filasValidas = collect($analisis['filas'])->where('errores', []);
    $filasConError = collect($analisis['filas'])->where('errores', '!=', []);
@endphp

@section('titulo', 'Vista previa de importación')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('importaciones.index') }}">Importaciones</a></li>
            <li class="breadcrumb-item active">Vista previa</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">Vista previa de importación</h1>
    <div class="card card-body mb-3">
        <div class="row g-2">
            <div class="col-md-4"><span class="text-body-secondary">Archivo</span><div>{{ $archivoNombre }}</div></div>
            <div class="col-md-2"><span class="text-body-secondary">Hoja</span><div>{{ $analisis['hoja'] }}</div></div>
            <div class="col-md-2"><span class="text-body-secondary">Filas con datos históricos</span><div>{{ $analisis['filas_leidas'] }}</div></div>
            <div class="col-md-2"><span class="text-body-secondary">Listas para importar</span><div>{{ $filasValidas->count() }}</div></div>
            <div class="col-md-2"><span class="text-body-secondary">Con errores</span><div>{{ $filasConError->count() }}</div></div>
            <div class="col-12 small text-body-secondary">
                {{ $analisis['filas_omitidas'] }} filas con solo cantidades de consumo serán omitidas.
            </div>
        </div>
    </div>

    @if ($filasConError->isNotEmpty())
        <div class="alert alert-warning">Las filas con errores se guardarán en el reporte; las filas válidas se procesarán.</div>
    @endif

    <div class="table-responsive">
        <table class="table table-sm align-middle bg-body border">
            <thead>
                <tr><th>Fila Excel</th><th>Impresora</th><th>Datos que se importarán</th><th>Validación</th></tr>
            </thead>
            <tbody>
                @forelse ($analisis['filas'] as $fila)
                    <tr class="{{ $fila['errores'] === [] ? '' : 'table-warning' }}">
                        <td>{{ $fila['fila'] }}</td>
                        <td>{{ $fila['impresora'] }}</td>
                        <td>
                            @forelse ($fila['lecturas'] as $lectura)
                                <div>
                                    {{ \Carbon\CarbonImmutable::parse($lectura['fecha_lectura'])->format('d/m/Y') }}
                                    @if ($lectura['tipo'] === 'nivel')
                                        ·
                                        @foreach ($lectura['niveles'] as $nivel)
                                            {{ $nivel['color'] }} {{ number_format($nivel['nivel'] * 100, 1) }}%@if (! $loop->last), @endif
                                        @endforeach
                                    @else
                                        · sin lectura: {{ strtoupper($lectura['valor_motivo']) }}
                                    @endif
                                </div>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>
                            @forelse ($fila['errores'] as $error)
                                <div><strong>{{ $error['columna'] }}:</strong> {{ $error['mensaje'] }}</div>
                            @empty
                                <span class="badge text-bg-success">Lista</span>
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-body-secondary py-3">No hay porcentajes ni motivos importables en la hoja.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex gap-2 mt-3">
        @if ($analisis['filas_leidas'] > 0)
            <form method="POST" action="{{ route('importaciones.ejecutar') }}"
                  onsubmit="return confirm('La importación guardará las filas válidas y registrará los errores. ¿Continuar?')">
                @csrf
                <button type="submit" class="btn btn-primary">Importar filas válidas</button>
            </form>
        @endif
        <form method="POST" action="{{ route('importaciones.cancelar') }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-secondary">Descartar vista previa</button>
        </form>
    </div>
@endsection
