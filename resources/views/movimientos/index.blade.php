@extends('layouts.app')

@section('titulo', 'Movimientos de inventario')

@php
    $etiquetas = [
        'entrada' => ['Entrada', 'success'],
        'salida' => ['Salida', 'danger'],
        'ajuste' => ['Ajuste', 'warning'],
    ];
@endphp

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
            Movimientos de inventario
            <span class="badge text-bg-secondary fs-6 align-middle">{{ $movimientos->total() }}</span>
        </h1>
        @can('registrar-movimientos')
            <a href="{{ route('movimientos.create') }}" class="btn btn-primary">Registrar movimiento</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('movimientos.index') }}" class="row g-2 mb-3 align-items-end">
        <div class="col-12 col-md-4 col-lg-3">
            <label for="f-consumible" class="form-label small mb-0">Consumible</label>
            <select id="f-consumible" name="consumible" class="form-select form-select-sm">
                <option value="">Todos</option>
                @foreach ($consumibles as $consumible)
                    <option value="{{ $consumible->id }}" @selected((int) request('consumible') === $consumible->id)>
                        {{ $consumible->codigo }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="f-tipo" class="form-label small mb-0">Tipo</label>
            <select id="f-tipo" name="tipo" class="form-select form-select-sm">
                <option value="">Todos</option>
                @foreach ($etiquetas as $valor => [$texto])
                    <option value="{{ $valor }}" @selected(request('tipo') === $valor)>{{ $texto }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label for="f-desde" class="form-label small mb-0">Desde</label>
            <input id="f-desde" type="date" name="desde" value="{{ request('desde') }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
            <label for="f-hasta" class="form-label small mb-0">Hasta</label>
            <input id="f-hasta" type="date" name="hasta" value="{{ request('hasta') }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label for="f-q" class="form-label small mb-0">Ticket o motivo</label>
            <input id="f-q" type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm">
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            <a href="{{ route('movimientos.index') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm align-middle bg-body border">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Consumible</th>
                    <th>Tipo</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-end">Saldo</th>
                    <th>Origen / destino</th>
                    <th>Ticket</th>
                    <th>Motivo</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movimientos as $movimiento)
                    @php [$texto, $clase] = $etiquetas[$movimiento->tipo] ?? [$movimiento->tipo, 'secondary']; @endphp
                    <tr>
                        <td class="text-nowrap">{{ $movimiento->fecha_movimiento->format('d/m/Y H:i') }}</td>
                        <td>{{ $movimiento->consumible?->codigo }}</td>
                        <td><span class="badge text-bg-{{ $clase }}">{{ $texto }}</span></td>
                        <td class="text-end">{{ $movimiento->cantidad > 0 ? '+' : '' }}{{ $movimiento->cantidad }}</td>
                        <td class="text-end">{{ $movimiento->saldo_resultante }}</td>
                        <td>
                            @if ($movimiento->impresora)
                                Destino: {{ $movimiento->impresora->nombre }}
                            @elseif ($movimiento->detalleCompra?->compra)
                                Compra {{ $movimiento->detalleCompra->compra->numero_documento ?? '#'.$movimiento->detalleCompra->compra_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $movimiento->ticket ?? '—' }}</td>
                        <td>{{ $movimiento->motivo ?? '—' }}</td>
                        <td>{{ $movimiento->usuario?->name }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-body-secondary py-3">No hay movimientos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $movimientos->links() }}
@endsection