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
            <div class="col-md-2"><span class="text-body-secondary">Filas analizadas</span><div>{{ $analisis['filas_leidas'] }}</div></div>
            <div class="col-md-2"><span class="text-body-secondary">Listas para importar</span><div>{{ $filasValidas->count() }}</div></div>
            <div class="col-md-2"><span class="text-body-secondary">Con errores</span><div>{{ $filasConError->count() }}</div></div>
            <div class="col-12 small text-body-secondary">
                @if (str_starts_with($analisis['formato'], 'insumos6-10'))
                    {{ $analisis['filas_omitidas'] }} filas sin porcentaje serán omitidas. Los consumos anuales del Excel no se importan.
                @else
                    {{ $analisis['filas_omitidas'] }} filas con solo cantidades de consumo serán omitidas.
                @endif
            </div>
        </div>
    </div>

    @if ($analisis['formato'] === 'inventario-consumibles')
        <div class="alert alert-info">
            Se crearán los consumibles y se registrará la cantidad de <strong>CANTIDAD</strong> como saldo inicial mediante movimientos trazables.
            No se importan consumo anual, compras ni cantidad de impresoras. Los códigos repetidos en colores distintos se ajustan para ser únicos.
        </div>
        @if ($filasConError->isNotEmpty())
            <div class="alert alert-warning">Los artículos con errores se omitirán; se importarán los artículos válidos.</div>
        @endif
        <div class="table-responsive">
            <table class="table table-sm align-middle bg-body border">
                <thead><tr><th>Fila(s)</th><th>Código origen</th><th>Código a crear</th><th>Tipo / color</th><th>Descripción</th><th>Stock inicial</th><th>Modelos compatibles</th><th>Validación</th></tr></thead>
                <tbody>
                    @forelse ($analisis['filas'] as $fila)
                        <tr class="{{ $fila['errores'] === [] ? '' : 'table-warning' }}">
                            <td>{{ $fila['filas'] }}</td>
                            <td>{{ $fila['codigo_original'] }}</td>
                            <td>{{ $fila['codigo'] }}</td>
                            <td>{{ $fila['tipo'] }} / {{ $fila['color'] }}</td>
                            <td>{{ $fila['descripcion'] ?: '—' }}</td>
                            <td>{{ $fila['cantidad'] ?? 'Inválido' }}</td>
                            <td>{{ $fila['modelos'] === [] ? 'Sin coincidencias' : implode(', ', $fila['modelos']) }}</td>
                            <td>
                                @foreach ($fila['errores'] as $error)
                                    <div class="text-danger"><strong>{{ $error['columna'] }}:</strong> {{ $error['mensaje'] }}</div>
                                @endforeach
                                @foreach ($fila['advertencias'] as $advertencia)
                                    <div class="text-warning-emphasis">{{ $advertencia }}</div>
                                @endforeach
                                @if ($fila['errores'] === [] && $fila['advertencias'] === [])
                                    <span class="badge text-bg-success">Lista</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-body-secondary py-3">No se encontraron consumibles en la hoja.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else

    @if (str_starts_with($analisis['formato'], 'insumos6-10'))
        <div class="alert alert-info">
            Se crearán lecturas con origen <strong>importación</strong>. Este proceso no crea impresoras, consumibles ni existencias.
            @if ($analisis['formato'] === 'insumos6-10-encabezado')
                La fecha de la lectura se toma del encabezado de consumo (celda K1).
            @else
                La fecha de la lectura se toma de la columna FECHA.
            @endif
            Los porcentajes múltiples se asignan en orden negro, cian, magenta y amarillo.
        </div>
    @endif

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
    @endif

    <div class="d-flex gap-2 mt-3">
        @if ($analisis['filas_leidas'] > 0)
            <form method="POST" action="{{ route('importaciones.ejecutar') }}"
                  onsubmit="return confirm('{{ $analisis['formato'] === 'inventario-consumibles' ? 'Se crearán los consumibles válidos y se registrará su stock inicial. ¿Continuar?' : 'La importación guardará las filas válidas y registrará los errores. ¿Continuar?' }}')">
                @csrf
                <button type="submit" class="btn btn-primary">{{ $analisis['formato'] === 'inventario-consumibles' ? 'Importar consumibles' : 'Importar filas válidas' }}</button>
            </form>
        @endif
        <form method="POST" action="{{ route('importaciones.cancelar') }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-secondary">Descartar vista previa</button>
        </form>
    </div>
@endsection
