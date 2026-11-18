@extends('layouts.app')

@section('titulo', 'Proveedores')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Proveedores <span class="badge text-bg-secondary fs-6">{{ $proveedores->total() }}</span></h1>
        <a href="{{ route('proveedores.create') }}" class="btn btn-primary">Nuevo proveedor</a>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle bg-body border">
            <thead>
                <tr>
                    <th>Proveedor</th>
                    <th>NIT</th>
                    <th>Contacto</th>
                    <th>Teléfono / correo</th>
                    <th>Compras</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($proveedores as $proveedor)
                    <tr>
                        <td>{{ $proveedor->nombre }}</td>
                        <td>{{ $proveedor->nit ?? '—' }}</td>
                        <td>{{ $proveedor->contacto ?? '—' }}</td>
                        <td>
                            {{ $proveedor->telefono ?? '—' }}
                            @if ($proveedor->correo)<div class="small text-body-secondary">{{ $proveedor->correo }}</div>@endif
                        </td>
                        <td>{{ $proveedor->compras_count }}</td>
                        <td><span class="badge text-bg-{{ $proveedor->activo ? 'success' : 'secondary' }}">{{ $proveedor->activo ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('proveedores.edit', $proveedor) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                            <form method="POST" action="{{ route('proveedores.destroy', $proveedor) }}" class="d-inline"
                                  onsubmit="return confirm('¿Eliminar este proveedor? Si tiene historial de compras o precios, deberá desactivarlo.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-3">No hay proveedores registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $proveedores->links() }}
@endsection
