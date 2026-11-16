@extends('layouts.app')

@php
    $editando = $modelo->exists;
    $snmp = old('snmp_soportado', is_null($modelo->snmp_soportado) ? '' : (string) (int) $modelo->snmp_soportado);
@endphp

@section('titulo', $editando ? 'Editar modelo' : 'Nuevo modelo')

@section('contenido')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('modelos.index') }}">Modelos de impresora</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $editando ? 'Editar' : 'Nuevo' }}</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">{{ $editando ? 'Editar modelo' : 'Nuevo modelo' }}</h1>

    <form method="POST" novalidate
          action="{{ $editando ? route('modelos.update', $modelo) : route('modelos.store') }}"
          class="card card-body">
        @csrf
        @if ($editando)
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label for="marca_id" class="form-label">Marca</label>
                <select id="marca_id" name="marca_id" class="form-select" required>
                    <option value="">Seleccione…</option>
                    @foreach ($marcas as $marca)
                        <option value="{{ $marca->id }}" @selected(old('marca_id', $modelo->marca_id) == $marca->id)>
                            {{ $marca->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-6">
                <label for="nombre" class="form-label">Nombre del modelo</label>
                <input id="nombre" type="text" name="nombre" maxlength="100"
                       value="{{ old('nombre', $modelo->nombre) }}" class="form-control" required>
            </div>

            <div class="col-md-4">
                <label for="tipo_impresion" class="form-label">Tipo de impresión</label>
                <select id="tipo_impresion" name="tipo_impresion" class="form-select" required>
                    <option value="">Seleccione…</option>
                    @foreach (\App\Modules\Printers\Models\ModeloImpresora::TIPOS_IMPRESION as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('tipo_impresion', $modelo->tipo_impresion) === $valor)>
                            {{ $etiqueta }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label for="tecnologia" class="form-label">Tecnología</label>
                <select id="tecnologia" name="tecnologia" class="form-select" required>
                    <option value="">Seleccione…</option>
                    @foreach (\App\Modules\Printers\Models\ModeloImpresora::TECNOLOGIAS as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('tecnologia', $modelo->tecnologia) === $valor)>
                            {{ $etiqueta }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label for="snmp_soportado" class="form-label">Compatibilidad SNMP</label>
                <select id="snmp_soportado" name="snmp_soportado" class="form-select">
                    <option value="" @selected((string) $snmp === '')>Desconocido</option>
                    <option value="1" @selected((string) $snmp === '1')>Sí</option>
                    <option value="0" @selected((string) $snmp === '0')>No</option>
                </select>
                <div class="form-text">Déjelo en «Desconocido» si aún no se ha comprobado en este modelo.</div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('modelos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
@endsection