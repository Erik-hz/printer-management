@extends('layouts.base')

@section('titulo', 'Visor de monitoreo')

@section('cuerpo')
    <main class="container py-4">
        <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">Monitoreo de impresoras</h1>
                <p class="text-body-secondary mb-0">
                    Instantánea de solo lectura
                    @if ($exportadoEn)
                        · Exportada {{ $exportadoEn }} UTC
                    @endif
                </p>
            </div>
            <span class="badge text-bg-secondary fs-6">Datos importados · sin actualización en vivo</span>
        </header>

        @if ($sinSnapshot)
            <div class="alert alert-info">
                Aún no hay una instantánea importada. En el equipo donde se preparó el archivo, ejecuta
                <code>php artisan snapshot:import ruta/al/archivo.xlsx</code>.
            </div>
        @else
            <section class="row g-3 mb-4" aria-label="Resumen de la instantánea">
                <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-body-secondary small">Impresoras</div><div class="fs-3 fw-semibold">{{ $resumen['impresoras'] }}</div></div></div></div>
                <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-body-secondary small">Lecturas</div><div class="fs-3 fw-semibold">{{ $resumen['lecturas'] }}</div></div></div></div>
                <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-body-secondary small">Niveles críticos (≤10 %)</div><div class="fs-3 fw-semibold text-danger">{{ $resumen['criticos'] }}</div></div></div></div>
                <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="text-body-secondary small">Niveles bajos (10–25 %)</div><div class="fs-3 fw-semibold text-warning-emphasis">{{ $resumen['bajos'] }}</div></div></div></div>
            </section>

            <form method="GET" action="{{ route('snapshot.index') }}" class="row g-2 mb-3">
                <div class="col-12 col-md-5">
                    <label class="form-label" for="impresora">Impresora</label>
                    <select class="form-select" id="impresora" name="impresora">
                        <option value="">Todas las impresoras</option>
                        @foreach ($impresoras as $item)
                            <option value="{{ $item->impresora_id }}" @selected($impresoraSeleccionada === (int) $item->impresora_id)>
                                {{ $item->impresora }} · {{ $item->direccion_ip }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto align-self-end">
                    <button class="btn btn-primary" type="submit">Filtrar</button>
                    <a class="btn btn-outline-secondary" href="{{ route('snapshot.index') }}">Limpiar</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle bg-body border">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha de lectura (UTC)</th>
                            <th>Impresora</th>
                            <th>IP</th>
                            <th>Estado</th>
                            <th>Origen</th>
                            <th class="text-end">Contador</th>
                            <th>Consumible</th>
                            <th class="text-end">Nivel</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $fila)
                            <tr>
                                <td class="text-nowrap">{{ $fila->fecha_lectura ?? '—' }}</td>
                                <td>{{ $fila->impresora }}</td>
                                <td class="text-nowrap">{{ $fila->direccion_ip ?? '—' }}</td>
                                <td>{{ $fila->estado ?? '—' }}</td>
                                <td>{{ $fila->origen ? ucfirst($fila->origen) : '—' }}</td>
                                <td class="text-end">{{ $fila->contador_paginas === null ? '—' : number_format((int) $fila->contador_paginas, 0, ',', '.') }}</td>
                                <td>
                                    @if ($fila->tipo_consumible)
                                        {{ $fila->tipo_consumible }} {{ $fila->color }}
                                    @elseif ($fila->motivo_sin_lectura)
                                        {{ $fila->motivo_sin_lectura }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($fila->porcentaje !== null)
                                        <span class="badge text-bg-{{ (float) $fila->porcentaje <= 10 ? 'danger' : ((float) $fila->porcentaje <= 25 ? 'warning' : 'success') }}">
                                            {{ number_format((float) $fila->porcentaje, 1, ',', '.') }} %
                                        </span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-body-secondary py-4">No hay lecturas para el filtro seleccionado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $filas->links() }}
        @endif
    </main>
@endsection
