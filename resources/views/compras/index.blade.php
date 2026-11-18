@extends('layouts.app')

@section('titulo', 'Órdenes de compra')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0">Órdenes de compra <span class="badge text-bg-secondary fs-6">{{ $compras->total() }}</span></h1>
        <div class="d-flex gap-2">
            <a href="{{ route('proveedores.index') }}" class="btn btn-outline-secondary">Proveedores</a>
            <a href="{{ route('precios-consumibles.index') }}" class="btn btn-outline-secondary">Precios</a>
            <a href="{{ route('compras.create') }}" class="btn btn-primary">Nueva orden</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle bg-body border">
            <thead>
                <tr>
                    <th>Orden</th>
                    <th>Fecha</th>
                    <th>Proveedor</th>
                    <th>Documento</th>
                    <th>Detalle</th>
                    <th>Estado</th>
                    <th>Registrada por</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($compras as $compra)
                    @php
                        $estados = [
                            'ordenada' => ['Ordenada', 'primary'],
                            'parcialmente_recibida' => ['Parcial', 'warning'],
                            'recibida' => ['Recibida', 'success'],
                            'cancelada' => ['Cancelada', 'secondary'],
                        ];
                        [$etiqueta, $color] = $estados[$compra->estado] ?? [$compra->estado, 'secondary'];
                    @endphp
                    <tr>
                        <td><a href="{{ route('compras.show', $compra) }}">#{{ $compra->id }}</a></td>
                        <td>{{ $compra->fecha_orden->format('d/m/Y') }}</td>
                        <td>{{ $compra->proveedor?->nombre }}</td>
                        <td>{{ $compra->numero_documento ?? '—' }}</td>
                        <td>{{ $compra->detalles_count }} consumible(s)</td>
                        <td><span class="badge text-bg-{{ $color }}">{{ $etiqueta }}</span></td>
                        <td>{{ $compra->usuario?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-3">No hay órdenes de compra.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $compras->links() }}
@endsection
