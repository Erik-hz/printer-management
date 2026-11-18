@extends('layouts.app')

@section('titulo', 'Orden de compra #'.$compra->id)

@section('contenido')
    @php
        $etiquetas = [
            'ordenada' => ['Ordenada', 'primary'],
            'parcialmente_recibida' => ['Parcialmente recibida', 'warning'],
            'recibida' => ['Recibida', 'success'],
            'cancelada' => ['Cancelada', 'secondary'],
        ];
        [$estadoTexto, $estadoColor] = $etiquetas[$compra->estado] ?? [$compra->estado, 'secondary'];
    @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <a href="{{ route('compras.index') }}" class="small">← Órdenes de compra</a>
            <h1 class="h3 mb-1">Orden #{{ $compra->id }}</h1>
            <span class="badge text-bg-{{ $estadoColor }}">{{ $estadoTexto }}</span>
        </div>
        @if ($compra->estado === 'ordenada')
            <form method="POST" action="{{ route('compras.cancelar', $compra) }}"
                  onsubmit="return confirm('¿Cancelar esta orden de compra?')">
                @csrf
                <button type="submit" class="btn btn-outline-danger">Cancelar orden</button>
            </form>
        @endif
    </div>

    <div class="card card-body mb-3">
        <div class="row g-3">
            <div class="col-md-3"><span class="text-body-secondary">Proveedor</span><div>{{ $compra->proveedor?->nombre }}</div></div>
            <div class="col-md-2"><span class="text-body-secondary">Fecha</span><div>{{ $compra->fecha_orden->format('d/m/Y') }}</div></div>
            <div class="col-md-2"><span class="text-body-secondary">Documento</span><div>{{ $compra->numero_documento ?? '—' }}</div></div>
            <div class="col-md-2"><span class="text-body-secondary">Moneda / IVA</span><div>{{ $compra->moneda }} · {{ $compra->incluye_iva ? 'Incluye IVA' : 'Sin IVA' }}</div></div>
            <div class="col-md-3"><span class="text-body-secondary">Registrada por</span><div>{{ $compra->usuario?->name }}</div></div>
            @if ($compra->observaciones)<div class="col-12"><span class="text-body-secondary">Observaciones</span><div>{{ $compra->observaciones }}</div></div>@endif
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle bg-body border">
            <thead>
                <tr><th>Consumible</th><th class="text-end">Ordenado</th><th class="text-end">Recibido</th><th class="text-end">Pendiente</th><th class="text-end">Precio unitario</th><th>Recepción</th></tr>
            </thead>
            <tbody>
                @foreach ($compra->detalles as $detalle)
                    @php($pendiente = $detalle->cantidad - $detalle->cantidad_recibida)
                    <tr>
                        <td>{{ $detalle->consumible?->codigo }}</td>
                        <td class="text-end">{{ $detalle->cantidad }}</td>
                        <td class="text-end">{{ $detalle->cantidad_recibida }}</td>
                        <td class="text-end">{{ $pendiente }}</td>
                        <td class="text-end">{{ $compra->moneda }} {{ number_format((float) $detalle->precio_unitario, 2) }}</td>
                        <td>
                            @if ($pendiente > 0 && in_array($compra->estado, ['ordenada', 'parcialmente_recibida'], true))
                                <form method="POST" action="{{ route('compras.recibir', [$compra, $detalle]) }}" class="d-flex gap-2">
                                    @csrf
                                    <input type="number" name="cantidad" min="1" max="{{ $pendiente }}" value="{{ $pendiente }}"
                                           class="form-control form-control-sm" style="max-width: 7rem" aria-label="Cantidad a recibir">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Recibir</button>
                                </form>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="form-text">Cada recepción actualiza las existencias y genera un movimiento de inventario.</p>
@endsection
