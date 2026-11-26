@extends('layouts.app')

@section('titulo', 'Consumibles')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
            Consumibles
            <span class="badge text-bg-secondary fs-6 align-middle">{{ $consumibles->total() }}</span>
        </h1>
        @can('gestionar-consumibles')
            <a href="{{ route('consumibles.create') }}" class="btn btn-primary">Nuevo consumible</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('consumibles.index') }}" class="row g-2 mb-3 align-items-center">
        <div class="col-12 col-md-4 col-lg-3">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"
                   placeholder="Buscar por código o descripción" aria-label="Buscar">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <select name="tipo" class="form-select form-select-sm" aria-label="Tipo">
                <option value="">Todos los tipos</option>
                @foreach ($tipos as $tipo)
                    <option value="{{ $tipo->id }}" @selected((int) request('tipo') === $tipo->id)>{{ $tipo->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <select name="color" class="form-select form-select-sm" aria-label="Color">
                <option value="">Todos los colores</option>
                @foreach (\App\Modules\Inventory\Models\Consumible::COLORES as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected(request('color') === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <select name="estado" class="form-select form-select-sm" aria-label="Estado">
                <option value="">Activos e inactivos</option>
                <option value="activos" @selected(request('estado') === 'activos')>Solo activos</option>
                <option value="inactivos" @selected(request('estado') === 'inactivos')>Solo inactivos</option>
            </select>
        </div>
        <div class="col-6 col-md-auto">
            <div class="form-check">
                <input id="bajo" type="checkbox" name="bajo" value="1" class="form-check-input"
                       @checked(request()->boolean('bajo'))>
                <label for="bajo" class="form-check-label">Solo stock bajo</label>
            </div>
        </div>
        <div class="col-12 col-md-auto">
            <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            <a href="{{ route('consumibles.index') }}" class="btn btn-sm btn-outline-secondary">Limpiar</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm align-middle bg-body border">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Tipo</th>
                    <th>Color</th>
                    <th class="text-end">Existencias</th>
                    <th class="text-end">Mínimo</th>
                    <th class="text-center">Modelos</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($consumibles as $consumible)
                    <tr>
                        <td>
                            {{ $consumible->codigo }}
                            @if ($consumible->descripcion)
                                <div class="small text-body-secondary">{{ $consumible->descripcion }}</div>
                            @endif
                        </td>
                        <td>{{ $consumible->tipo->nombre }}</td>
                        <td>{{ $consumible->etiquetaColor() }}</td>
                        <td class="text-end">
                            {{ $consumible->existencia?->cantidad ?? 0 }}
                            @if ($consumible->estaBajoMinimo())
                                <span class="badge text-bg-danger">Stock bajo</span>
                            @endif
                        </td>
                        <td class="text-end">{{ $consumible->stock_minimo }}</td>
                        <td class="text-center">{{ $consumible->modelos_count }}</td>
                        <td>
                            <span class="badge text-bg-{{ $consumible->activo ? 'success' : 'secondary' }}">
                                {{ $consumible->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('movimientos.index', ['consumible' => $consumible->id]) }}"
                               class="btn btn-sm btn-outline-secondary">Movimientos</a>
                            @can('gestionar-consumibles')
                                <a href="{{ route('consumibles.edit', $consumible) }}"
                                   class="btn btn-sm btn-outline-primary">Editar</a>
                                <form method="POST" action="{{ route('consumibles.destroy', $consumible) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('¿Eliminar este consumible? Solo es posible si no tiene movimientos, compras ni precios. Para dejar de usarlo, desactívelo.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-body-secondary py-3">No hay consumibles.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $consumibles->links() }}
@endsection
