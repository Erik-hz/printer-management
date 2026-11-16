@php
    $puedeGestionar = auth()->user()->can('gestionar-catalogos');
    $campoActivo = $campoActivo ?? null;
@endphp

<div class="table-responsive">
    <table class="table table-sm align-middle bg-body border">
        <thead>
            <tr>
                <th>Nombre</th>
                @isset($etiquetaConteo)
                    <th class="text-center">{{ $etiquetaConteo }}</th>
                @endisset
                @if ($campoActivo)
                    <th class="text-center">Activo</th>
                @endif
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>
                        @if ($puedeGestionar)
                            <input form="editar-{{ $item->id }}" type="text" name="nombre"
                                   value="{{ $item->nombre }}" maxlength="{{ $max }}"
                                   class="form-control form-control-sm" aria-label="Nombre" required>
                        @else
                            {{ $item->nombre }}
                        @endif
                    </td>
                    @isset($etiquetaConteo)
                        <td class="text-center">{{ $item->{$campoConteo} }}</td>
                    @endisset
                    @if ($campoActivo)
                        <td class="text-center">
                            @if ($puedeGestionar)
                                <input form="editar-{{ $item->id }}" type="checkbox" class="form-check-input"
                                       name="{{ $campoActivo }}" value="1"
                                       @checked($item->{$campoActivo}) aria-label="Activo">
                            @else
                                {{ $item->{$campoActivo} ? 'Sí' : 'No' }}
                            @endif
                        </td>
                    @endif
                    <td class="text-end text-nowrap">
                        @isset($rutas['hijos'])
                            <a href="{{ route($rutas['hijos'], $item) }}"
                               class="btn btn-sm btn-outline-secondary">{{ $etiquetaHijos }}</a>
                        @endisset
                        @if ($puedeGestionar)
                            <button form="editar-{{ $item->id }}" type="submit"
                                    class="btn btn-sm btn-outline-primary">Guardar</button>
                            <button form="eliminar-{{ $item->id }}" type="submit"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('¿Eliminar este registro?')">Eliminar</button>
                            <form id="editar-{{ $item->id }}" method="POST"
                                  action="{{ route($rutas['actualizar'], $item) }}">@csrf @method('PUT')</form>
                            <form id="eliminar-{{ $item->id }}" method="POST"
                                  action="{{ route($rutas['eliminar'], $item) }}">@csrf @method('DELETE')</form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center text-body-secondary py-3">No hay registros.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>