@extends('layouts.app')

@section('titulo', 'Precios de consumibles')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Precios vigentes por proveedor</h1>
        <a href="{{ route('compras.index') }}" class="btn btn-outline-secondary">Órdenes de compra</a>
    </div>

    <form method="POST" action="{{ route('precios-consumibles.store') }}" class="card card-body mb-4">
        @csrf
        <h2 class="h5">Registrar precio</h2>
        <div class="row g-3">
            <div class="col-md-3">
                <label for="consumible_id" class="form-label">Consumible</label>
                <select id="consumible_id" name="consumible_id" class="form-select" required>
                    <option value="">Seleccione…</option>
                    @foreach ($consumibles as $consumible)
                        <option value="{{ $consumible->id }}" @selected((int) old('consumible_id') === $consumible->id)>{{ $consumible->codigo }}</option>
                    @endforeach
                </select>
                @error('consumible_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="proveedor_id" class="form-label">Proveedor</label>
                <select id="proveedor_id" name="proveedor_id" class="form-select">
                    <option value="">Precio general (sin proveedor)</option>
                    @foreach ($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}" @selected((int) old('proveedor_id') === $proveedor->id)>{{ $proveedor->nombre }}</option>
                    @endforeach
                </select>
                @error('proveedor_id')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label for="precio" class="form-label">Precio unitario</label>
                <input id="precio" type="number" name="precio" min="0" step="0.01" value="{{ old('precio') }}" class="form-control" required>
                @error('precio')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-1">
                <label for="moneda" class="form-label">Moneda</label>
                <input id="moneda" name="moneda" maxlength="3" value="{{ old('moneda', 'BOB') }}" class="form-control text-uppercase" required>
                @error('moneda')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="vigente_desde" class="form-label">Vigente desde</label>
                <input id="vigente_desde" type="date" name="vigente_desde" value="{{ old('vigente_desde', now()->toDateString()) }}" class="form-control" required>
                @error('vigente_desde')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label for="vigente_hasta" class="form-label">Vigente hasta</label>
                <input id="vigente_hasta" type="date" name="vigente_hasta" value="{{ old('vigente_hasta') }}" class="form-control">
                @error('vigente_hasta')<div class="text-danger small">{{ $message }}</div>@enderror
                <div class="form-text">En blanco para vigencia abierta.</div>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <input type="hidden" name="incluye_iva" value="0">
                <div class="form-check mb-2">
                    <input id="incluye_iva" type="checkbox" name="incluye_iva" value="1" class="form-check-input" @checked(old('incluye_iva'))>
                    <label for="incluye_iva" class="form-check-label">Incluye IVA</label>
                </div>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary mb-1">Registrar precio</button>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm align-middle bg-body border">
            <thead>
                <tr><th>Consumible</th><th>Proveedor</th><th class="text-end">Precio</th><th>IVA</th><th>Vigencia</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                @forelse ($precios as $precio)
                    <tr>
                        <td>{{ $precio->consumible?->codigo }}</td>
                        <td>{{ $precio->proveedor?->nombre ?? 'General' }}</td>
                        <td class="text-end">{{ $precio->moneda }} {{ number_format((float) $precio->precio, 2) }}</td>
                        <td>{{ $precio->incluye_iva ? 'Incluido' : 'No incluido' }}</td>
                        <td>{{ $precio->vigente_desde->format('d/m/Y') }} – {{ $precio->vigente_hasta?->format('d/m/Y') ?? 'Sin vencimiento' }}</td>
                        <td>
                            @if ($precio->vigente_hasta === null)
                                <form method="POST" action="{{ route('precios-consumibles.cerrar', $precio) }}" class="d-flex gap-2">
                                    @csrf
                                    <input type="date" name="vigente_hasta" min="{{ $precio->vigente_desde->toDateString() }}"
                                           value="{{ now()->toDateString() }}" class="form-control form-control-sm"
                                           aria-label="Último día de vigencia">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Cerrar vigencia</button>
                                </form>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-3">No hay precios registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $precios->links() }}
@endsection
