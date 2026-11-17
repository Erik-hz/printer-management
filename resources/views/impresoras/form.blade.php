@extends('layouts.app')

@php
    $editando = $impresora->exists;
    $conexion = old('tipo_conexion', $impresora->tipo_conexion ?? 'red');
    $gruposModelos = $modelos->groupBy(fn ($modelo) => $modelo->marca->nombre);
    $gruposAreas = $areas->groupBy(fn ($area) => $area->lugar->zona->nombre.' › '.$area->lugar->nombre);
@endphp

@section('titulo', $editando ? 'Editar impresora' : 'Nueva impresora')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('impresoras.index') }}">Impresoras</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $editando ? 'Editar' : 'Nueva' }}</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">{{ $editando ? 'Editar impresora' : 'Nueva impresora' }}</h1>

    <form method="POST" novalidate class="card card-body"
          action="{{ $editando ? route('impresoras.update', $impresora) : route('impresoras.store') }}">
        @csrf
        @if ($editando)
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label for="nombre" class="form-label">Nombre</label>
                <input id="nombre" type="text" name="nombre" maxlength="100"
                       value="{{ old('nombre', $impresora->nombre) }}"
                       class="form-control @error('nombre') is-invalid @enderror" required>
                @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label for="modelo_id" class="form-label">Modelo</label>
                <select id="modelo_id" name="modelo_id"
                        class="form-select @error('modelo_id') is-invalid @enderror" required>
                    <option value="">Seleccione…</option>
                    @foreach ($gruposModelos as $marca => $items)
                        <optgroup label="{{ $marca }}">
                            @foreach ($items as $modelo)
                                <option value="{{ $modelo->id }}"
                                        @selected((int) old('modelo_id', $impresora->modelo_id) === $modelo->id)>
                                    {{ $modelo->nombre }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('modelo_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-8">
                <label for="area_id" class="form-label">Área</label>
                <select id="area_id" name="area_id"
                        class="form-select @error('area_id') is-invalid @enderror" required>
                    <option value="">Seleccione…</option>
                    @foreach ($gruposAreas as $etiqueta => $items)
                        <optgroup label="{{ $etiqueta }}">
                            @foreach ($items as $area)
                                <option value="{{ $area->id }}"
                                        @selected((int) old('area_id', $impresora->area_id) === $area->id)>
                                    {{ $area->nombre }}{{ $area->activa ? '' : ' (inactiva)' }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('area_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="estado_id" class="form-label">Estado</label>
                <select id="estado_id" name="estado_id"
                        class="form-select @error('estado_id') is-invalid @enderror" required>
                    @foreach ($estados as $estado)
                        <option value="{{ $estado->id }}"
                                @selected((int) old('estado_id', $impresora->estado_id) === $estado->id)>
                            {{ ucfirst($estado->nombre) }}
                        </option>
                    @endforeach
                </select>
                @error('estado_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="tipo_conexion" class="form-label">Tipo de conexión</label>
                <select id="tipo_conexion" name="tipo_conexion"
                        class="form-select @error('tipo_conexion') is-invalid @enderror" required>
                    <option value="red" @selected($conexion === 'red')>Red</option>
                    <option value="usb" @selected($conexion === 'usb')>USB</option>
                </select>
                @error('tipo_conexion')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-8" id="bloque-red">
                <label for="direccion_ip" class="form-label">Dirección IP</label>
                <input id="direccion_ip" type="text" name="direccion_ip" maxlength="15"
                       value="{{ old('direccion_ip', $impresora->direccion_ip) }}" placeholder="192.168.1.10"
                       class="form-control @error('direccion_ip') is-invalid @enderror">
                @error('direccion_ip')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Las impresoras de red necesitan IP. Los ceros a la izquierda se corrigen solos.</div>
            </div>

            <div class="col-md-8" id="bloque-usb">
                <label for="equipo_conectado" class="form-label">Equipo al que está conectada</label>
                <input id="equipo_conectado" type="text" name="equipo_conectado" maxlength="100"
                       value="{{ old('equipo_conectado', $impresora->equipo_conectado) }}"
                       class="form-control @error('equipo_conectado') is-invalid @enderror">
                @error('equipo_conectado')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Opcional. Las impresoras USB no tienen IP propia.</div>
            </div>

            <div class="col-md-6">
                <label for="numero_serie" class="form-label">Número de serie</label>
                <input id="numero_serie" type="text" name="numero_serie" maxlength="50"
                       value="{{ old('numero_serie', $impresora->numero_serie) }}"
                       class="form-control @error('numero_serie') is-invalid @enderror">
                @error('numero_serie')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label for="observaciones" class="form-label">Observaciones</label>
                <textarea id="observaciones" name="observaciones" rows="3" maxlength="500"
                          class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones', $impresora->observaciones) }}</textarea>
                @error('observaciones')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('impresoras.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>

    <script>
        // Muestra solo el campo que corresponde al tipo de conexión; el oculto no se envía.
        document.addEventListener('DOMContentLoaded', function () {
            const tipo = document.getElementById('tipo_conexion');
            const bloqueRed = document.getElementById('bloque-red');
            const bloqueUsb = document.getElementById('bloque-usb');

            function actualizar() {
                const esRed = tipo.value === 'red';
                bloqueRed.classList.toggle('d-none', !esRed);
                bloqueUsb.classList.toggle('d-none', esRed);
                bloqueRed.querySelector('input').disabled = !esRed;
                bloqueUsb.querySelector('input').disabled = esRed;
            }

            tipo.addEventListener('change', actualizar);
            actualizar();
        });
    </script>
@endsection