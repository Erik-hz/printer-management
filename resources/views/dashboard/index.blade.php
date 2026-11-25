@extends('layouts.app')

@section('titulo', 'Dashboard')

@section('contenido')
    <div class="mb-4">
        <h1 class="h3 mb-2">Dashboard</h1>
        <p class="text-body-secondary mb-0">
            Bienvenido, <strong>{{ auth()->user()->name }}</strong>.
            Rol: {{ ucfirst(str_replace('_', ' ', auth()->user()->rol->nombre)) }}
        </p>
    </div>

    <div class="row g-3">
        @if ($impresoras !== null)
            <section class="col-12 col-md-6" aria-labelledby="dashboard-impresoras">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h5" id="dashboard-impresoras">Impresoras</h2>
                        <dl class="row mb-0">
                            <dt class="col-8">Registradas</dt>
                            <dd class="col-4 text-end">{{ $impresoras['registradas'] }}</dd>
                            <dt class="col-8">En estado operativo</dt>
                            <dd class="col-4 text-end">{{ $impresoras['operativas'] }}</dd>
                        </dl>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="{{ route('impresoras.index') }}">Ver impresoras</a>
                    </div>
                </div>
            </section>
        @endif

        @if ($inventario !== null)
            <section class="col-12 col-md-6" aria-labelledby="dashboard-inventario">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h5" id="dashboard-inventario">Consumibles bajo el mínimo</h2>
                        <p class="display-6 mb-3">{{ $inventario['bajo_minimo'] }}</p>
                        @if ($inventario['consumibles']->isNotEmpty())
                            <ul class="list-group list-group-flush">
                                @foreach ($inventario['consumibles'] as $consumible)
                                    <li class="list-group-item px-0 d-flex justify-content-between gap-3">
                                        <span>
                                            <strong>{{ $consumible->codigo }}</strong>
                                            <span class="d-block text-body-secondary small">{{ $consumible->descripcion }}</span>
                                        </span>
                                        <span class="text-nowrap">{{ $consumible->cantidad_actual }} / mínimo {{ $consumible->stock_minimo }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-body-secondary mb-0">No hay consumibles bajo el mínimo.</p>
                        @endif
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="{{ route('consumibles.index', ['bajo' => 1]) }}">Ver inventario bajo</a>
                    </div>
                </div>
            </section>
        @endif

        @if ($compras !== null)
            <section class="col-12 col-md-6" aria-labelledby="dashboard-compras">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h5" id="dashboard-compras">Órdenes por recibir</h2>
                        <p class="display-6 mb-3">{{ $compras['pendientes'] }}</p>
                        @if ($compras['ordenes']->isNotEmpty())
                            <ul class="list-group list-group-flush">
                                @foreach ($compras['ordenes'] as $compra)
                                    <li class="list-group-item px-0 d-flex justify-content-between gap-3">
                                        <span>
                                            <strong>{{ $compra->numero_documento ?: "OC-{$compra->id}" }}</strong>
                                            <span class="d-block text-body-secondary small">{{ $compra->proveedor->nombre }}</span>
                                        </span>
                                        <span class="text-nowrap">{{ $compra->fecha_orden->format('d/m/Y') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-body-secondary mb-0">No hay órdenes pendientes de recepción.</p>
                        @endif
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="{{ route('compras.index') }}">Ver órdenes de compra</a>
                    </div>
                </div>
            </section>
        @endif

        @if ($lecturas !== null)
            <section class="col-12 col-md-6" aria-labelledby="dashboard-lecturas">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h5" id="dashboard-lecturas">Últimas lecturas</h2>
                        <p class="text-body-secondary small">Por fecha de lectura; no representa monitoreo en tiempo real.</p>
                        @if ($lecturas->isNotEmpty())
                            <ul class="list-group list-group-flush">
                                @foreach ($lecturas as $lectura)
                                    <li class="list-group-item px-0">
                                        <div class="d-flex justify-content-between gap-3">
                                            <strong>{{ $lectura->impresora->nombre }}</strong>
                                            <span class="text-nowrap">{{ $lectura->fecha_lectura->format('d/m/Y H:i') }}</span>
                                        </div>
                                        <span class="text-body-secondary small">
                                            {{ match ($lectura->origen) {
                                                'manual' => 'Manual',
                                                'snmp' => 'SNMP',
                                                'powershell' => 'PowerShell',
                                                'importacion' => 'Importación',
                                                default => $lectura->origen,
                                            } }}
                                            @if ($lectura->contador_paginas !== null)
                                                · {{ number_format($lectura->contador_paginas) }} páginas
                                            @elseif ($lectura->motivoSinLectura !== null)
                                                · {{ $lectura->motivoSinLectura->nombre }}
                                            @endif
                                        </span>
                                        @if ($lectura->niveles->isNotEmpty())
                                            <div class="d-grid gap-2 mt-2">
                                                @foreach ($lectura->niveles as $nivel)
                                                    @include('lecturas._nivel', ['nivel' => $nivel])
                                                @endforeach
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-body-secondary mb-0">No hay lecturas registradas.</p>
                        @endif
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="{{ route('lecturas.index') }}">Ver lecturas</a>
                    </div>
                </div>
            </section>
        @endif
    </div>
@endsection
