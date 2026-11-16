@extends('layouts.app')

@section('titulo', 'Modelos de impresora')

@section('contenido')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Modelos de impresora</h1>
        @can('gestionar-catalogos')
            <a href="{{ route('modelos.create') }}" class="btn btn-primary">Nuevo modelo</a>
        @endcan
    </div>

    <form method="GET" action="{{ route('modelos.index') }}" class="row g-2 mb-3">
        <div class="col-auto">
            <select name="marca" class="form-select form-select-sm" aria-label="Filtrar por marca"
                    onchange="this.form.submit()">
                <option value="">Todas las marcas</option>
                @foreach ($marcas as $marca)
                    <option value="{{ $marca->id }}" @selected((int) request('marca') == $marca->id)>
                        {{ $marca->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm align-middle bg-body border">
            <thead>
                <tr>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Tipo</th>
                    <th>Tecnología</th>
                    <th>SNMP</th>
                    <th class="text-center">Impresoras</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($modelos as $modelo)
                    <tr>
                        <td>{{ $modelo->marca->nombre }}</td>
                        <td>{{ $modelo->nombre }}</td>
                        <td>{{ \App\Modules\Printers\Models\ModeloImpresora::TIPOS_IMPRESION[$modelo->tipo_impresion] ?? $modelo->tipo_impresion }}</td>
                        <td>{{ \App\Modules\Printers\Models\ModeloImpresora::TECNOLOGIAS[$modelo->tecnologia] ?? $modelo->tecnologia }}</td>
                        <td>
                            @if (is_null($modelo->snmp_soportado))
                                Desconocido
                            @else
                                {{ $modelo->snmp_soportado ? 'Sí' : 'No' }}
                            @endif
                        </td>
                        <td class="text-center">{{ $modelo->impresoras_count }}</td>
                        <td class="text-end text-nowrap">
                            @can('gestionar-catalogos')
                                <a href="{{ route('modelos.edit', $modelo) }}"
                                   class="btn btn-sm btn-outline-primary">Editar</a>
                                <form method="POST" action="{{ route('modelos.destroy', $modelo) }}" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar este modelo?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-body-secondary py-3">No hay modelos.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection