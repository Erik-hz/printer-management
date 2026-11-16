@can('gestionar-catalogos')
    <form method="POST" action="{{ $accion }}" class="row g-2 align-items-end mb-3">
        @csrf
        <input type="hidden" name="_origen" value="nuevo">

        <div class="col-sm-6 col-md-5">
            <label class="form-label" for="nuevo-nombre">{{ $etiqueta }}</label>
            <input id="nuevo-nombre" type="text" name="nombre" maxlength="{{ $max }}"
                   value="{{ old('_origen') === 'nuevo' ? old('nombre') : '' }}"
                   class="form-control" required>
        </div>

        @if ($campoActivo ?? null)
            <div class="col-auto">
                <div class="form-check mb-2">
                    <input id="nuevo-activo" type="checkbox" class="form-check-input"
                           name="{{ $campoActivo }}" value="1" checked>
                    <label class="form-check-label" for="nuevo-activo">Activo</label>
                </div>
            </div>
        @endif

        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Agregar</button>
        </div>
    </form>
@endcan